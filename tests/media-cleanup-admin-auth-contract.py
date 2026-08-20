#!/usr/bin/env python3
"""Security contract for the exact-owner Media Cleanup Resolver surface."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
admin = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-media-cleanup-admin.php").read_text(encoding="utf-8")
kernel = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php").read_text(encoding="utf-8")
assets = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php").read_text(encoding="utf-8")
registry = (ROOT / "plugin/gloskin-site-core/config/assets.php").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


require("const CAPABILITY      = 'manage_options';" in admin, "manage_options owner missing")
require("const USER_LOGIN      = 'namaste';" in admin, "exact namaste owner missing")
require("self::USER_LOGIN === (string) $user->user_login" in admin, "case-sensitive user_login check missing")
require("current_user_can( self::CAPABILITY )" in admin and "wp_get_current_user()" in admin,
        "capability and authenticated user gates must be independent")

for method in ("register_menu", "render_notice", "enqueue_assets", "render", "ajax", "download_manifest"):
    block = admin.split(f"function {method}", 1)[1].split("\n\t}", 1)[0]
    require("current_user_is_owner()" in block, f"{method} does not independently enforce exact owner")

require("add_action( 'wp_ajax_' . self::AJAX_ACTION" in admin, "authenticated AJAX endpoint missing")
require("add_action( 'admin_post_' . self::DOWNLOAD_ACTION" in admin, "authenticated manifest download missing")
for forbidden in ("wp_ajax_nopriv", "admin_post_nopriv", "register_rest_route"):
    require(forbidden not in admin, f"public resolver endpoint forbidden: {forbidden}")
require("check_ajax_referer( self::NONCE, 'nonce', false )" in admin and "check_admin_referer( self::NONCE )" in admin,
        "AJAX and download nonce checks missing")
require("nonce_expired" in admin and "retryable' => false" in admin,
        "expired nonce/session must fail clearly without retry")
require("Gloskin_Site_Core_Media_Cleanup_Resolver::REVISION !== $revision" in admin,
        "client revision must be validated")
require("isset( $_POST['ids'] )" not in admin and "$_POST['attachment_ids']" not in admin,
        "client must never submit authoritative attachment IDs")
require("$this->resolver->is_consumed()" in admin and admin.count("is_consumed()") >= 3,
        "menu, notice, and assets must disappear after consumption")
require("Gloskin_Site_Core_Media_Cleanup_Admin" in kernel and
        "class-gloskin-site-core-media-cleanup-admin.php" in kernel,
        "Kernel registration missing")
require("enqueue_admin_media_cleanup" in assets and "gloskin-ui1-media-cleanup" in registry,
        "single AssetService/registry owner missing")
require(not re.search(r"^(?:<<<<<<<|=======|>>>>>>>)", admin + kernel, re.MULTILINE),
        "unresolved merge marker in runtime owner")

print("media-cleanup-admin-auth-contract.py: OK")
