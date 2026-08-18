#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(name):
    return (ROOT / name).read_text(encoding="utf-8")

def require(condition, message):
    if not condition:
        raise AssertionError(message)

js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-sample-product-import.js")
admin = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-prototype-ia-migration-admin.php")
migration = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-prototype-ia-migration.php")
lifecycle = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-lifecycle-service.php")
nav = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-navigation-service.php")

for token in (
    "data-gloskin-ia-migration",
    "data-gloskin-migration-loader",
    "data-gloskin-migration-progressbar",
    "data-gloskin-migration-step",
):
    require(token in admin or token in js, f"loader/progress token missing: {token}")
require("class=\"spinner\"" in admin, "WordPress real spinner is missing")
require("<progress " in admin, "native progress element is missing")
require("request('start').then(continueChain)" in js, "single start action must enter autonomous chain")
require("request('continue').then(continueChain)" in js, "checkpoint continuation must be autonomous")
require("window.requestAnimationFrame" in js, "browser must get a paint frame between real checkpoints")
require("loaderNode.classList.toggle('is-active', busy)" in js, "spinner must reflect actual in-flight state")
require("progressBar.value" in js, "progress element must reflect server checkpoint count")
require("root.setAttribute('aria-busy'" in js, "busy state must be exposed accessibly")
require("setInterval" not in js, "migration loader must not poll")
require("Promise.all" not in js, "migration writes must not run concurrently")
require("data-gloskin-no-redirect" in admin and "hasAttribute('data-gloskin-no-redirect')" in js, "IA completion must stay visible instead of redirecting away")

require("final class Gloskin_Site_Core_Prototype_IA_Migration" in migration, "bounded IA migration coordinator missing")
require("STATE_OPTION" in migration and "LOCK_OPTION" in migration, "checkpoint/lock state missing")
require("run_to_completion()" in migration, "non-JS one-click fallback missing")
require("processed_products" in migration and "expected_products" in migration, "server progress counters missing")
require("woocommerce_shop_page_id" in migration and "woocommerce_cart_page_id" in migration and "woocommerce_checkout_page_id" in migration and "woocommerce_myaccount_page_id" in migration, "Woo page safety snapshot incomplete")
require("PRESERVED_MENU_NAME" in migration and "preserve_primary_menu_snapshot" in migration, "deterministic unassigned editor-menu preservation is missing")
require("PRESERVED_SOURCE_META" in migration and "PRESERVED_REVISION_META" in migration, "backup menu idempotency markers missing")
require("'nav_menu_item' === get_post_type( $item_id )" in migration and "wp_delete_post( $item_id, true )" in migration, "primary cleanup must delete nav_menu_item records only")
require("Primary navigation harus tepat Perawatan, Promo, Skincare, Tentang Gloskin tanpa item tambahan." in migration, "exact-four primary verification missing")
require("Snapshot menu editor tidak lengkap" in migration, "editor backup completeness verification missing")
require("Kepemilikannya tidak dapat dibuktikan" in migration and "page_on_front" in migration, "ambiguous front-page ownership must stop with actionable warning")
require("sudah ada di Trash; kepemilikan ambigu" in migration, "ambiguous trashed Page must fail safe rather than create duplicate slug")
require("'/promo/'" in migration and "'promo'      => 'Promo'" in migration, "Promo native Page ownership must live in one-shot migration")

require("const BASE_SCHEMA_VERSION = '0.2.2';" in lifecycle, "base schema checkpoint missing")
require("const SCHEMA_VERSION      = '0.3.0';" in lifecycle, "target IA schema missing")
require("Do not auto-run schema 0.3.0 here" in lifecycle, "lifecycle must explicitly defer to one-shot IA runner")
require("version_compare( $current, self::BASE_SCHEMA_VERSION, '<' )" in lifecycle, "activation must guard baseline writes with a monotonic schema comparison")
require("deliberately NOT owned here" in lifecycle, "baseline lifecycle must not duplicate Promo revision ownership")
require("approved_primary_tree" in nav, "runtime exact-four primary projection owner missing")
for label, path in (("Perawatan", "/treatments/"), ("Promo", "/promo/"), ("Skincare", "/skincare/"), ("Tentang Gloskin", "/about/")):
    require(label in nav and path in nav, f"approved primary destination missing: {label}")

print("prototype-ia-loader-contract: OK")
