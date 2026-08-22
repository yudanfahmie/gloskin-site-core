#!/usr/bin/env python3
"""Static contract for owner-only retained-image optimization inside Media Cleanup."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
INC = ROOT / "plugin/gloskin-site-core/includes"
admin = (INC / "class-gloskin-site-core-media-cleanup-admin.php").read_text(encoding="utf-8")
resolver = (INC / "class-gloskin-site-core-media-cleanup-resolver.php").read_text(encoding="utf-8")
optimizer = (INC / "gloskin-site-core-media-cleanup-optimizer-trait.php").read_text(encoding="utf-8")
kernel = (INC / "class-gloskin-site-core-kernel.php").read_text(encoding="utf-8")
nav = (INC / "class-gloskin-site-core-admin-navigation-service.php").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


# One exact-user owner and one existing AJAX endpoint.
require("const USER_LOGIN      = 'namaste';" in admin, "exact namaste owner missing")
require("self::USER_LOGIN === (string) $user->user_login" in admin, "case-sensitive owner check missing")
require("case 'optimize':" in admin and "->optimize_batch(" in admin, "optimizer not wired into existing AJAX controller")
require(admin.count("add_action( 'wp_ajax_' . self::AJAX_ACTION") == 1, "must retain one Media Cleanup AJAX endpoint")
require("data-media-optimization-start" in admin and "Optimize Images" in admin, "optimizer must live in existing Media Cleanup page")

# Nested state + same resolver infrastructure.
require("use Gloskin_Site_Core_Media_Cleanup_Optimizer_Trait;" in resolver, "optimizer must be composed into existing resolver")
require("'optimization'           => $this->optimizer_default_state()" in resolver, "nested optimization state missing")
for token in ("$this->acquire_lock()", "$this->next_image_ids(", "$this->attachment_files(", "$this->save_state("):
    require(token in optimizer, f"optimizer must reuse resolver primitive: {token}")
require("max_attachment_id" in optimizer and "$this->max_image_attachment_id()" in optimizer, "frozen optimizer boundary missing")
require("'complete' !== (string) $state['status']" in optimizer, "optimizer must require stable completed cleanup")
require("assert_optimizer_not_running" in resolver, "cleanup mutations must reject active optimization")

# In-place, transactional, no permanent backup copies.
require("finally" in optimizer and "@unlink( $temp )" in optimizer, "temporary candidate must always be removed")
require("@rename( $temp, $file )" in optimizer, "validated candidate must replace source in-place")
require("unlink( $file )" not in optimizer, "source must never be unlinked before replacement")
for forbidden in (".bak", "backup folder", "ZipArchive", "wp_insert_attachment"):
    require(forbidden not in optimizer, f"persistent image backup/duplicate forbidden: {forbidden}")
for mime in ("image/jpeg", "image/png", "image/webp", "image/avif"):
    require(mime in optimizer, f"capability-driven safe raster support missing: {mime}")
require("getimagesize" in optimizer and "wp_get_image_mime" in optimizer, "candidate readability/format validation missing")
require("candidate['width']" in optimizer and "candidate['height']" in optimizer, "dimension equality validation missing")
require("candidate['alpha']" in optimizer and "source['icc']" in optimizer and "source['frames']" in optimizer, "presentation-preservation validation missing")
require("$after >= $before" in optimizer, "non-smaller candidate must be rejected")

# Idempotence uses sampled file-set fingerprint, not duplicate image storage.
require("_gloskin_media_optimizer_state" in optimizer, "optimizer attachment marker missing")
require("2026-08-22-image-optimizer-v1" in optimizer, "optimizer revision missing")
require("optimizer_sample_hash" in optimizer and "65536" in optimizer, "bounded file sampling fingerprint missing")
require("wp_update_attachment_metadata" in optimizer and "filesize" in optimizer, "affected WordPress filesize metadata must be updated")

# Obsolete Finalisasi Konten runtime is gone, historical WP state untouched by this cleanup.
require("class-gloskin-site-core-content-finalizer-admin.php" not in kernel, "Content Finalizer require still live")
require("Gloskin_Site_Core_Content_Finalizer_Admin" not in kernel, "Content Finalizer service still live")
require("gloskin-content-finalizer" not in nav, "Content Finalizer menu ordering still live")
require(not (INC / "class-gloskin-site-core-content-finalizer-admin.php").exists(), "dead Content Finalizer admin file should be removed")

print("media-cleanup-optimizer-contract.py: OK")
