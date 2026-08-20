from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise SystemExit(f"FAIL: {message}")


admin = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php")
exporter = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-diagnostic-exporter.php")
asset = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php")
registry = read("plugin/gloskin-site-core/config/assets.php")
javascript = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-diagnostic.js")
kernel = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php")
plugin = read("plugin/gloskin-site-core/gloskin-site-core.php")

require("const DIAGNOSTIC_USER_LOGIN      = 'namaste';" in admin, "exact namaste owner missing")
require("const DIAGNOSTIC_CAPABILITY      = 'manage_options';" in admin, "capability gate missing")
require("self::DIAGNOSTIC_USER_LOGIN === (string) $user->user_login" in admin,
        "case-sensitive user_login check missing")
require("wp_ajax_' . self::DIAGNOSTIC_ACTION" in admin and "admin_post_' . self::DIAGNOSTIC_ACTION" in admin,
        "authenticated AJAX/admin-post handlers missing")
require("wp_ajax_nopriv_' . self::DIAGNOSTIC_ACTION" not in admin and
        "admin_post_nopriv_' . self::DIAGNOSTIC_ACTION" not in admin,
        "public diagnostic endpoint detected")
require(admin.index("current_user_may_download_diagnostic()") < admin.index("$exporter = $this->diagnostic_exporter();"),
        "authorization must precede exporter initialization")
require(admin.index("wp_verify_nonce( $nonce, self::DIAGNOSTIC_NONCE )") < admin.index("$exporter = $this->diagnostic_exporter();"),
        "nonce must precede exporter initialization")
require("class-gloskin-site-core-diagnostic-exporter.php" not in kernel,
        "exporter must not load through the Kernel/public branch")

for needle in (
    "wp_tempnam(", "ZipArchive", "register_shutdown_function(", "@unlink(",
    "MAX_SOURCE_FILE_BYTES", "MAX_SOURCE_TOTAL_BYTES", "MAX_ARCHIVE_BYTES", "MAX_ROUTE_CHECKS",
    "realpath(", "isLink()", "manifest.json", "promo-diagnostic.json", "migration-state.json",
    "woocommerce-boundary.json", "media-manifest.json", "code-manifest.json", "runtime-health.json",
    "route-checks.json",
):
    require(needle in exporter, f"missing exporter safety/schema owner: {needle}")

require("} finally {" in admin and "@unlink( $path )" in admin,
        "streaming success/failure cleanup must use finally")

require("wc_get_orders(" not in exporter and "get_users(" not in exporter and "$wpdb->users" not in exporter,
        "private Woo/user collection API detected")
require("'cookies' => array()" in exporter and "'redirection' => 0" in exporter,
        "route checks must be anonymous and must not follow redirects")
require("enqueue_admin_diagnostic" in asset and "'gloskin-ui1-diagnostic'" in registry,
        "diagnostic assets must stay registry-owned")

for needle in ("var attempts = 3", "data-gloskin-diagnostic-spinner", "response.status >= 500",
               "429 === response.status", "800 * Math.pow( 2, attempt - 1 )", "credentials: 'same-origin'",
               "window.URL.createObjectURL", "window.URL.revokeObjectURL"):
    require(needle in javascript, f"missing AJAX loader/retry owner: {needle}")

require("Version: 0.7.157" in plugin and "const VERSION = '0.7.157';" in kernel,
        "version must be synchronized at 0.7.157")
print("diagnostic-export-contract.py: OK (0.7.157)")
