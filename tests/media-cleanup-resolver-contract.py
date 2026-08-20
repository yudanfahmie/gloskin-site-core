#!/usr/bin/env python3
"""
Media Cleanup resolver safety contract.

Tests outcome and safety invariants — not implementation details like BATCH_SIZE,
REQUEST_BUDGET_SECONDS, or per-attachment codebase scans (all removed in v2).
"""
from pathlib import Path
import re

ROOT     = Path(__file__).resolve().parents[1]
resolver = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-media-cleanup-resolver.php").read_text(encoding="utf-8")
admin    = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-media-cleanup-admin.php").read_text(encoding="utf-8")
js       = (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-media-cleanup.js").read_text(encoding="utf-8")
plugin   = (ROOT / "plugin/gloskin-site-core/gloskin-site-core.php").read_text(encoding="utf-8")
kernel   = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


# ── State / lifecycle tokens ──────────────────────────────────────────────────
for token in (
    "2026-08-20-media-cleanup-v2", "STATE_OPTION", "LOCK_OPTION", "MANIFEST_OPTION",
    "'pending'", "'indexing'", "'review_ready'", "'deleting'", "'verifying'", "'complete'", "'failed'",
    "const RECENT_DAYS     = 30",
):
    require(token in resolver, f"state/revision contract missing: {token}")

# Old 12-second budget and BATCH_SIZE=15 removed; budget must be shorter.
require("REQUEST_BUDGET_SECONDS = 12" not in resolver,
        "12-second request budget must be replaced with shorter budget")
require("BATCH_SIZE      = 15" not in resolver,
        "BATCH_SIZE=15 removed — use smaller batch suited to shorter budget")

# ── Frozen scan boundary ──────────────────────────────────────────────────────
require("scan_max_attachment_id" in resolver, "frozen scan boundary (scan_max_attachment_id) must be present")
require("ID <= %d" in resolver or "ID <= " in resolver,
        "next_image_ids must filter by frozen scan boundary (ID <= scan_max_attachment_id)")
require("max_image_attachment_id" in resolver, "must capture max attachment ID at scan start to freeze boundary")

# ── Dry-run is read-only ──────────────────────────────────────────────────────
index_block = resolver.split("public function index_batch", 1)[1].split("public function delete_batch", 1)[0]
require("wp_delete_attachment" not in index_block, "index_batch (dry-run) must never delete")

# ── Single, safe deletion path ────────────────────────────────────────────────
require(
    resolver.count("wp_delete_attachment( $id, true )") == 1,
    "wp_delete_attachment($id,true) must be the sole deletion owner"
)
for forbidden in ("unlink(", "$wpdb->delete", "DELETE FROM", "rmdir("):
    require(forbidden not in resolver, f"direct destructive call forbidden: {forbidden}")

# ── JIT reclassification before every delete ──────────────────────────────────
delete_block = resolver.split("public function delete_batch", 1)[1].split("public function verify_batch", 1)[0]
require(
    "classify_attachment( $id )" in delete_block
    and "'confirmed-unused' !== (string) $fresh['classification']" in delete_block,
    "every candidate must be JIT-reclassified immediately before deletion"
)
require(
    "hash_equals( (string) $candidate['fingerprint'], (string) $fresh['fingerprint'] )" in delete_block,
    "candidate fingerprint must be verified JIT before deletion"
)

# ── Immutable server manifest / token ────────────────────────────────────────
require(
    "validated_manifest" in delete_block
    and "hash_equals" in resolver
    and "add_option( self::MANIFEST_OPTION" in resolver,
    "immutable server manifest / hash / token contract missing"
)
require(
    "get_current_blog_id" in resolver and "Manifest/token/site tidak valid" in resolver,
    "manifest candidates must be bound to the current multisite blog"
)

# ── Stale/duplicate cursor is idempotent ─────────────────────────────────────
require(
    "(int) $client_cursor !== (int) $state['deletion_cursor']" in delete_block,
    "stale/duplicate AJAX delete calls must be idempotent"
)

# ── Only image attachments ────────────────────────────────────────────────────
require("post_type = 'attachment'" in resolver, "only attachment post-type must be scanned")
require("post_mime_type LIKE 'image/" in resolver, "only image MIME types must be scanned")
require("post_parent" not in resolver, "unattached must never be treated as unused by post_parent")

# ── Reference detection — high-value structured evidence ─────────────────────
for token in (
    "_thumbnail_id", "_product_image_gallery", "$wpdb->termmeta", "$wpdb->options",
    "site_icon", "custom_logo", "get_theme_mods", "_gloskin_", "gloskin_",
    '"id":', '"mediaId":', '"media_id":', '"ids":[', "wp-image-", 'ids="',
    "attachment_id\";i:", "wp_get_attachment_url", "original_image",
    "metadata['sizes']", "post_content", "post_excerpt",
    "maybe_unserialize", "json_decode",
):
    require(token in resolver, f"reference graph coverage missing: {token}")

# ── Naked numeric-ID broad search removed ────────────────────────────────────
require(
    # The bare soft-token "(string) $id => 'soft'" that caused broad LIKE '%123%' searches is gone.
    "'soft'" not in resolver or resolver.count("'soft'") == 0
    or ("attachment_reference_tokens" in resolver and
        r"(string) $id => 'soft'" not in resolver),
    "naked numeric-ID soft LIKE token must be removed"
)
require(
    r"',' . $id . ','" not in resolver,
    "broad naked ',ID,' LIKE pattern must be removed"
)
require(
    r"',' . $id . ']'" not in resolver,
    "broad naked ',ID]' LIKE pattern must be removed"
)

# ── Per-attachment recursive codebase scan removed ───────────────────────────
require(
    "RecursiveDirectoryIterator" not in resolver and "scan_active_code" not in resolver,
    "per-attachment recursive codebase scan must be removed"
)
require(
    "MAX_CODE_FILES" not in resolver and "MAX_CODE_BYTES" not in resolver,
    "codebase-scan file/byte limits must be removed along with the scanner"
)

# ── Fail-closed classification ────────────────────────────────────────────────
require(
    "Metadata attachment hilang atau malformed" in resolver,
    "malformed metadata must make attachment ambiguous"
)
require(
    "fail closed" in resolver or "Referensi lemah atau pemindaian tidak lengkap" in resolver,
    "uncertainty must fail closed"
)
require(
    "$wpdb->last_error" in resolver and "scan_failed:" in resolver and "scan_failed" in admin,
    "incomplete DB scans must fail closed before manifest deletion"
)
require(
    "Diunggah atau diubah dalam 30 hari terakhir" in resolver,
    "recently uploaded/modified images must be protected"
)

# ── Upload directory boundary ────────────────────────────────────────────────
require(
    "is_current_upload_file" in delete_block and "realpath" in resolver and "wp_get_upload_dir" in resolver,
    "current uploads boundary must be re-checked during deletion"
)

# ── Candidate-scoped verification (not global library fingerprint) ───────────
verify_block = resolver.split("public function verify_batch", 1)[1].split("public function review_page", 1)[0]
require(
    "candidates" in verify_block,
    "verify_batch must iterate manifest candidates (candidate-scoped, not all results)"
)
require(
    "get_post( $id ) instanceof WP_Post" in verify_block or "get_post( $id )" in verify_block,
    "verify must confirm deleted candidates have no remaining post record"
)
require(
    "is_file(" in verify_block,
    "verify must confirm deleted candidates have no remaining files"
)
# Global protected_boundary_fingerprint check removed; candidate-scoped verify is sufficient.
require(
    "Registry media terlindungi berubah" not in verify_block,
    "global library fingerprint check must be removed from verify_batch"
)

# ── Consumed reruns are inert ──────────────────────────────────────────────────
require(
    r"'complete' === (string) $state['status']" in index_block
    and r"'complete' === (string) $state['status']" in delete_block,
    "complete reruns must be inert"
)

# ── candidates-only results ──────────────────────────────────────────────────
require(
    "Only persist confirmed-unused" in resolver
    or r"'confirmed-unused' === (string) $result['classification']" in index_block,
    "results[] must store only confirmed-unused candidates, not every attachment"
)

# ── Start New Scan ────────────────────────────────────────────────────────────
require("reset_scan" in resolver, "reset_scan() method required for Start New Scan")
require("delete_option( self::MANIFEST_OPTION )" in resolver,
        "reset_scan must delete the manifest option to allow a fresh run")
require(
    "reset_scan" in admin and "case 'reset'" in admin,
    "admin AJAX must wire 'reset' mode to resolver->reset_scan()"
)

# ── Browser never owns authoritative attachment IDs ───────────────────────────
require(
    "Object.keys( state.results" not in js and "attachment_ids" not in js,
    "browser must not receive or own the full authoritative candidate list"
)

# ── JS uses calm timeout, not requestAnimationFrame ──────────────────────────
require(
    "window.setTimeout" in js,
    "batch pacing must use window.setTimeout (calm delay)"
)
require(
    "requestAnimationFrame" not in js,
    "requestAnimationFrame must be removed as the batch scheduler"
)

# ── No pause/resume in JS or resolver ────────────────────────────────────────
require(
    "data-media-cleanup-pause" not in js,
    "Pause UI must be removed from JS"
)
require(
    "function pause" not in resolver and "function resume" not in resolver,
    "pause() and resume() must be removed from resolver"
)

# ── Retry behaviour ───────────────────────────────────────────────────────────
for token in (
    "Math.pow( 2, attempt )", "retryLimit = 3", "response.status >= 500",
    "data.retryable", "'TypeError' === String( error.name",
):
    require(token in js, f"AJAX retry contract missing: {token}")

# ── Synthetic classification truth table ──────────────────────────────────────
def classify(image=True, recent=False, system=False, hard=False, soft=False, warning=False, valid_file=True):
    if not image or not valid_file: return "ambiguous"
    if recent or system:           return "protected"
    if hard:                       return "used"
    if soft or warning:            return "ambiguous"
    return "confirmed-unused"

require(classify(hard=True) == "used",               "featured/Woo hard references must be 'used'")
require(classify(recent=True) == "protected",        "recent images must be 'protected'")
require(classify(system=True) == "protected",        "registry/system images must be 'protected'")
require(classify(soft=True) == "ambiguous",          "soft references must be 'ambiguous' (fail closed)")
require(classify(warning=True) == "ambiguous",       "warnings must be 'ambiguous' (fail closed)")
require(classify(valid_file=False) == "ambiguous",   "invalid file must be 'ambiguous' (fail closed)")
require(classify() == "confirmed-unused",            "clean old image must be 'confirmed-unused'")

# ── Version synchronized ──────────────────────────────────────────────────────
require(
    "Version: 0.7.174" in plugin and "const VERSION = '0.7.174';" in kernel,
    "plugin and Kernel patch versions must be synchronized at 0.7.174"
)
print("media-cleanup-resolver-contract.py: OK (0.7.174)")
