#!/usr/bin/env python3
"""Fail-closed media graph, immutable manifest, deletion, and AJAX contracts."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
resolver = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-media-cleanup-resolver.php").read_text(encoding="utf-8")
admin = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-media-cleanup-admin.php").read_text(encoding="utf-8")
js = (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-media-cleanup.js").read_text(encoding="utf-8")
plugin = (ROOT / "plugin/gloskin-site-core/gloskin-site-core.php").read_text(encoding="utf-8")
kernel = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


for token in (
    "2026-08-20-media-cleanup-v1", "STATE_OPTION", "LOCK_OPTION", "MANIFEST_OPTION",
    "'pending'", "'indexing'", "'review_ready'", "'deleting'", "'verifying'", "'consumed'", "'failed'",
    "const BATCH_SIZE      = 15", "const RECENT_DAYS     = 30",
    "const REQUEST_BUDGET_SECONDS = 12.0", "microtime( true )",
):
    require(token in resolver, f"state/revision contract missing: {token}")

index_block = resolver.split("public function index_batch", 1)[1].split("public function delete_batch", 1)[0]
delete_block = resolver.split("public function delete_batch", 1)[1].split("public function verify_batch", 1)[0]
require("wp_delete_attachment" not in index_block, "dry-run must never delete")
require(resolver.count("wp_delete_attachment( $id, true )") == 1,
        "wp_delete_attachment($id,true) must be the sole deletion owner")
for forbidden in ("unlink(", "$wpdb->delete", "DELETE FROM", "rmdir("):
    require(forbidden not in resolver, f"direct destructive owner forbidden: {forbidden}")
require("classify_attachment( $id )" in delete_block and
        "'confirmed-unused' !== (string) $fresh['classification']" in delete_block,
        "every candidate must be immediately revalidated")
require("validated_manifest" in delete_block and "hash_equals" in resolver and "add_option( self::MANIFEST_OPTION" in resolver,
        "immutable server manifest/hash/token contract missing")
require("get_current_blog_id" in resolver and "Manifest/token/site tidak valid" in resolver,
        "manifest candidates must remain bound to the current multisite blog")
require("(int) $client_cursor !== (int) $state['deletion_cursor']" in delete_block,
        "duplicate/stale AJAX calls must be idempotent")
require("post_type = 'attachment'" in resolver and "post_mime_type LIKE 'image/" in resolver,
        "image attachments must be the only indexed records")
require("post_parent" not in resolver, "unattached must not be treated as unused")

for token in ("_thumbnail_id", "_product_image_gallery", "$wpdb->termmeta", "$wpdb->options",
              "site_icon", "custom_logo", "get_theme_mods", "_gloskin_", "gloskin_",
              '"id":', '"mediaId":', '"media_id":', '"ids":[', "wp-image-", "ids=\"",
              "wp_get_attachment_url", "original_image", "metadata['sizes']", "post_content", "post_excerpt",
              "maybe_unserialize", "json_decode", "RecursiveDirectoryIterator"):
    require(token in resolver, f"reference graph coverage missing: {token}")
require("Metadata attachment hilang atau malformed" in resolver and
        "Referensi lemah atau pemindaian tidak lengkap; fail closed" in resolver,
        "malformed/uncertain data must become ambiguous")
require("$wpdb->last_error" in resolver and "scan_failed:" in resolver and "scan_failed" in admin,
        "incomplete database scans must fail closed before manifest deletion")
require("Diunggah atau diubah dalam 30 hari terakhir" in resolver,
        "recent images must be protected")
require("is_current_upload_file" in delete_block and "realpath" in resolver and "wp_get_upload_dir" in resolver,
        "current uploads boundary must be rechecked")
require("protected_boundary_fingerprint" in resolver and "Fingerprint attachment terlindungi berubah" in resolver and
        "Registry media terlindungi berubah" in resolver,
        "final verification must detect protected/Woo/Gloskin mutation")
require("get_post( $id )" in resolver and "status'] = 'consumed'" in resolver,
        "deleted/skipped records must be verified before consumption")
require("file_exists( $deleted_file )" in resolver and "File attachment terhapus masih tersisa" in resolver,
        "final verification must confirm original/generated files are gone")
require("'consumed' === (string) $state['status']" in index_block and delete_block,
        "consumed reruns must be inert")

for token in ("progress", "processed", "current_file", "deleted", "skipped", "failed",
              "backup_confirmed", "Download JSON", "Download CSV"):
    require(token in admin, f"review/AJAX UI field missing: {token}")
for token in ("Math.pow( 2, attempt )", "retryLimit = 3", "response.status >= 500", "data.retryable",
              "'TypeError' === String( error.name", "data-media-cleanup-pause", "requestAnimationFrame", "running", "disabled", "loadReview"):
    require(token in js, f"robust AJAX behavior missing: {token}")
require("Object.keys( state.results" not in js and "attachment_ids" not in js,
        "browser must not receive/own the full authoritative candidate list")

# Synthetic classification truth table documents the expected fail-closed priority.
def classify(image=True, recent=False, system=False, hard=False, soft=False, warning=False, valid_file=True):
    if not image or not valid_file:
        return "ambiguous"
    if recent or system:
        return "protected"
    if hard:
        return "used"
    if soft or warning:
        return "ambiguous"
    return "confirmed-unused"


require(classify(hard=True) == "used", "featured/Gutenberg/Woo hard references must be used")
require(classify(recent=True) == "protected" and classify(system=True) == "protected",
        "recent and registry media must be protected")
require(classify(soft=True) == "ambiguous" and classify(warning=True) == "ambiguous" and
        classify(valid_file=False) == "ambiguous", "uncertainty must fail closed")
require(classify() == "confirmed-unused", "only fully clean old image can enter manifest")

require("Version: 0.7.161" in plugin and "const VERSION = '0.7.161';" in kernel,
        "plugin and Kernel patch versions must be synchronized")
print("media-cleanup-resolver-contract.py: OK (0.7.161)")
