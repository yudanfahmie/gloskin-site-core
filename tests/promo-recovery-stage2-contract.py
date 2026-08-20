#!/usr/bin/env python3
"""Focused contract for Promo visibility and the bounded Stage 2 recovery."""
from datetime import datetime
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


service = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php")
lookup = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-page-lookup.php")
normalizer = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-final-ia-normalizer.php")
final_migration = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php")
recovery = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260820-promo-recovery.php")
admin = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260820-promo-recovery-admin.php")
diagnostic = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-diagnostic-exporter.php")
helpers = read("plugin/gloskin-site-core/templates/parts/template-helpers.php")
kernel = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php")
plugin = read("plugin/gloskin-site-core/gloskin-site-core.php")

for php_path in (ROOT / "plugin/gloskin-site-core").rglob("*.php"):
    php_source = php_path.read_text(encoding="utf-8")
    require(not re.search(r"^(?:<<<<<<<|=======|>>>>>>>)", php_source, re.MULTILINE),
            f"unresolved merge marker in runtime PHP: {php_path.relative_to(ROOT)}")

managed = service.split("private function managed_promo_records", 1)[1].split("private function is_promo_date_eligible", 1)[0]
require("_gloskin_demo_identity" not in managed, "Promo provenance must not be a frontend visibility rule")
require("'post_status'    => 'publish'" in managed and "'gloskin_promo_active'" in managed,
        "Promo eligibility must remain published + active")
require("is_promo_date_eligible" in managed and "compare_managed_posts" in managed,
        "Promo date and order owners must remain intact")
require("$slide_heading_tag = $is_first ? $heading_tag : 'h2';" in helpers and
        "esc_attr( $slide_heading_tag )" in helpers,
        "multiple eligible Promo slides must retain exactly one primary H1")

require("get_page_by_path( $slug, OBJECT, array( 'page' ) )" in lookup,
        "strict lookup must query only the Page post type")
for source, label in ((service, "TemplateService"), (normalizer, "Final IA normalizer"),
                      (final_migration, "final verification"), (diagnostic, "diagnostic"),
                      (recovery, "Stage 2")):
    require("Gloskin_Site_Core_Page_Lookup::find" in source, f"{label} must use the shared strict Page lookup")

for token in ("2026-08-20-promo-recovery-v1",
              "gloskin_site_core_revision_20260820_promo_recovery_state",
              "gloskin_site_core_revision_20260820_promo_recovery_lock"):
    require(token in recovery, f"Stage 2 identity missing: {token}")
require("pre_wp_unique_post_slug" in recovery and "'post_type' => 'page'" in recovery and
        "'post_name' => 'promo'" in recovery and "'post_content' => ''" in recovery,
        "Stage 2 must safely provision one exact empty Promo Page around the attachment collision")
require("wp_update_nav_menu_item( $menu_id, $item_id" in recovery and "wp_create_nav_menu" not in recovery,
        "Stage 2 must rebind the existing menu item without rebuilding the menu")
require("wp_delete_post" not in recovery and "wp_delete_attachment" not in recovery,
        "Stage 2 must never delete records or the collision attachment")
require("'production' === $environment ? array()" in recovery and "production_promo_mutations' => 'production' === $environment ? 0" in recovery,
        "production must execute zero Promo mutations")
require("promo_fingerprints" in recovery and "WooCommerce Page IDs berubah" in recovery,
        "production Promo and Woo Page boundaries must be verified")
require("assert_no_promo_identity_duplicates" in recovery,
        "Stage 2 verification must reject duplicate managed Promo identities")
require("'2026-08-19-final' ===" in recovery,
        "non-production promotion must exclude final-migration engineering fixtures")
for identity in ("gloskin-demo-r2-promo-brightening", "gloskin-demo-r2-promo-konsultasi-gratis", "gloskin-demo-r2-promo-acne-program"):
    require(identity in recovery, f"allowlisted Reset Demo identity missing: {identity}")
require("flush_rewrite_rules( false )" in recovery and recovery.count("flush_rewrite_rules( false )") == 1,
        "rewrite rules must have one bounded conditional owner")
require("verification_pending" in recovery and "'consumed' === (string) $state['status']" in recovery,
        "Stage 2 must remain resumable and consumed runs must be inert")

require("const CAPABILITY  = 'manage_options'" in admin and "check_ajax_referer" in admin and "check_admin_referer" in admin,
        "Stage 2 admin entrypoints require capability and nonce")
require("wp_ajax_nopriv" not in admin and "register_rest_route" not in admin,
        "Stage 2 must expose no public AJAX or REST path")
require("Gloskin_Site_Core_Revision_20260820_Promo_Recovery_Admin" in kernel,
        "Kernel must register the Stage 2 admin owner")

require("const SCHEMA_VERSION          = '1.1'" in diagnostic, "diagnostic schema minor must be bumped")
require("'demo_identity' => $demo_identity" in diagnostic and "reasons[] = 'demo_identity'" not in diagnostic,
        "diagnostics must keep provenance but not exclude it")
require("no_demo_identity" not in diagnostic and "non_page_path_collisions" in diagnostic and
        "route_resolution" in diagnostic and "'post_type' => $page->post_type" in diagnostic,
        "diagnostics must match frontend eligibility and explain the collision/route")


def eligible(status: str, active: str, start: str, end: str, now: datetime) -> bool:
    if status != "publish" or active != "1":
        return False
    if start and now < datetime.fromisoformat(start + "T00:00:00"):
        return False
    if end and now > datetime.fromisoformat(end + "T23:59:59"):
        return False
    return True


now = datetime.fromisoformat("2026-08-20T12:00:00")
require(eligible("publish", "1", "2026-08-20", "2026-08-20", now),
        "inclusive date boundaries must render a published active identity record")
require(not eligible("draft", "1", "", "", now) and not eligible("publish", "0", "", "", now),
        "draft and inactive identity records must stay excluded")
ordered = sorted([(0, "Zulu", 9), (2, "Beta", 7), (1, "Alpha", 8)],
                 key=lambda row: (row[0] <= 0, row[0] if row[0] > 0 else 0, row[1], row[2]))
require([row[2] for row in ordered] == [8, 7, 9], "explicit order then title/ID behavior changed")

require("Version: 0.7.161" in plugin and "const VERSION = '0.7.161';" in kernel,
        "plugin and Kernel version must be synchronized")
print("promo-recovery-stage2-contract.py: OK (0.7.161)")
