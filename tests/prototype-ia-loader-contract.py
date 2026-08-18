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

# Real progress UI: one click starts an automatically chained checkpoint flow.
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

# Bounded, resumable, idempotent migration ownership.
require("final class Gloskin_Site_Core_Prototype_IA_Migration" in migration, "bounded IA migration coordinator missing")
require("STATE_OPTION" in migration and "LOCK_OPTION" in migration, "checkpoint/lock state missing")
require("run_to_completion()" in migration, "non-JS one-click fallback missing")
require("processed_products" in migration and "expected_products" in migration, "server progress counters missing")
require("woocommerce_shop_page_id" in migration and "woocommerce_cart_page_id" in migration, "Woo page safety snapshot missing")
require("wp_delete_post( $delete_id, true )" in migration and "'nav_menu_item' === get_post_type( $delete_id )" in migration, "menu-only deletion guard missing")
require("PHP_URL_HOST" in migration and "strtolower( $host ) !== strtolower( $site_host )" in migration, "external custom menu URLs must be excluded from Gloskin cleanup matching")
require("sudah ada di Trash; kepemilikan ambigu" in migration, "ambiguous trashed Page must fail safe rather than create a duplicate slug")
require("'/promo/'" in migration and "'promo'      => 'Promo'" in migration, "Promo native Page ownership must live in the one-shot migration")
require("'/shop/'" in migration and "'/clinics/'" in migration and "'/doctors/'" in migration and "'/insights/'" in migration and "'/contact/'" in migration, "legacy primary cleanup set incomplete")

# Lifecycle doesn't repeatedly repair the menu or duplicate revision-specific Page ownership.
require("const BASE_SCHEMA_VERSION = '0.2.2';" in lifecycle, "base schema checkpoint missing")
require("const SCHEMA_VERSION      = '0.3.0';" in lifecycle, "target IA schema missing")
require("Do not auto-run schema 0.3.0 here" in lifecycle, "lifecycle must explicitly defer to one-shot IA runner")
require("deliberately NOT owned here" in lifecycle, "baseline lifecycle must not duplicate Promo revision ownership")
require("array(\n\t\t\t$this->fallback_item( 'Perawatan'" in nav, "new primary nav order missing")
require("$this->fallback_item( 'Promo', '/promo/' )" in nav, "Promo missing from fallback nav")
require("$this->fallback_item( 'Tentang Gloskin', '/about/' )" in nav, "About missing from fallback nav")

print("prototype-ia-loader-contract: OK")
