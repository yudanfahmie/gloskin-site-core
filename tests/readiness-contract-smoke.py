#!/usr/bin/env python3
"""Static contract checks for search/breadcrumb/zero-state/account readiness."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugin" / "gloskin-site-core"

def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")

def require(cond, message):
    if not cond:
        raise AssertionError(message)

production = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-production.css")
css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css") + "\n" + read("plugin/gloskin-site-core/assets/css/gloskin-ui1-readiness.css") + "\n" + production
js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
helper = read("plugin/gloskin-site-core/templates/parts/readiness-helpers.php")
shell = read("plugin/gloskin-site-core/templates/shell.php")
adapter = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php")
mobile = read("plugin/gloskin-site-core/templates/parts/mobile-drawer.php")
main_plugin = read("plugin/gloskin-site-core/gloskin-site-core.php")
kernel = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php")
assets = read("plugin/gloskin-site-core/config/assets.php")
runner = read("tests/check-runtime.sh")

# Asset chain keeps one owner while loading the readiness layer before production polish.
require("gloskin-ui1-readiness" in assets and "assets/css/gloskin-ui1-readiness.css" in assets, "readiness stylesheet must be registered by the canonical asset owner")
require("array( 'gloskin-ui1-readiness' )" in assets, "production CSS must depend on readiness layer")

# Search geometry and dynamic zero/error copy.
require(".gloskin-ui1-search-overlay__body{margin-top:0;min-height:0}" in css, "search body must collapse when empty")
require(".gloskin-ui1-search-overlay__body:not(:empty){margin-top:20px}" in css, "search spacing must be state-driven")
require("Tidak menemukan hasil yang sesuai" in js, "search zero-state title missing")
require("Coba kata lain atau gunakan istilah yang lebih singkat." in js, "search zero-state copy missing")
require("Pencarian belum dapat dimuat" in js and "Buka pencarian biasa" in js, "search error fallback missing")
require("AbortController" in js and "220" in js, "search debounce/abort behavior must remain")
require("price_html" in adapter and "get_price_html()" in adapter[adapter.index("public function search_products"):], "product search must use live Woo price_html")

# Breadcrumb ownership and SEO/GEO non-ownership.
require("function_exists( 'rank_math_the_breadcrumbs' )" in helper, "Rank Math must be provider-first")
require("rank_math_the_breadcrumbs();" in helper, "Rank Math frontend breadcrumb function missing")
require('aria-label="Breadcrumb"' in helper and 'aria-current="page"' in helper, "fallback breadcrumb accessibility missing")
require("'home' === $view" in helper, "homepage breadcrumb suppression missing")
require("remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 )" in shell, "Woo duplicate breadcrumb suppression missing")
require("remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 )" in shell and "gloskin_ui1_render_native_cart_empty_state" in helper, "native empty cart must use shared Gloskin zero state")
for forbidden in ("BreadcrumbList", "application/ld+json", 'rel="canonical"', "wp_head", "meta name=", "og:"):
    require(forbidden not in helper, f"readiness helper must not own SEO metadata/schema: {forbidden}")

# Shared empty-state contract and truthful collection rendering.
require("gloskin_ui1_render_empty_state" in helper and "gloskin_ui1_empty_state_icon" in helper, "shared empty-state helper missing")
require('aria-hidden="true" focusable="false"' in helper, "empty SVG must be decorative")
require("gloskin-empty-settle 220ms" in css and "prefers-reduced-motion:reduce" in css, "empty-state motion/reduced-motion contract missing")
for rel in (
    "plugin/gloskin-site-core/templates/pages/clinics.php",
    "plugin/gloskin-site-core/templates/pages/contact.php",
    "plugin/gloskin-site-core/templates/pages/home.php",
    "plugin/gloskin-site-core/templates/pages/about.php",
):
    require("gloskin_ui1_real_cards" in read(rel), f"{rel} must not present synthetic clinic placeholders as records")
for rel in (
    "plugin/gloskin-site-core/templates/pages/treatments.php",
    "plugin/gloskin-site-core/templates/pages/clinics.php",
    "plugin/gloskin-site-core/templates/pages/doctors.php",
    "plugin/gloskin-site-core/templates/pages/insights.php",
    "plugin/gloskin-site-core/templates/pages/shop.php",
    "plugin/gloskin-site-core/templates/pages/skincare-category.php",
):
    require("gloskin_ui1_render_empty_state" in read(rel), f"meaningful zero state missing in {rel}")

# Native Woo My Account/auth ownership and real shell lifecycle.
require("woocommerce-MyAccount-navigation" in css and "woocommerce-MyAccount-content" in css, "native My Account workspace styling missing")
require("wc_get_template( 'myaccount/form-login.php' )" in adapter, "quick auth must render Woo native form template")
require("woocommerce_enable_myaccount_registration" in adapter, "Woo registration setting must control switch")
require("should_render_quick_auth" in adapter and "is_account_page()" in adapter, "native account page must suppress duplicate overlay form")
require('data-gloskin-overlay="auth"' in adapter, "auth must use unified overlay state")
require("add_action( 'gloskin_site_core_shell_footer', array( $this, 'render_quick_auth_overlay' ), 10 )" in adapter, "quick auth must bind to the Gloskin shell lifecycle")
require("do_action( 'gloskin_site_core_shell_footer' );" in shell, "shell auth integration hook missing")
require("add_action( 'wp_footer', array( $this, 'render_quick_auth_overlay'" not in adapter, "quick auth must not depend on generic footer rendering")
require("initAuth()" in js and "overlay.open('auth')" in js, "auth must use existing overlay controller")
require("data-gloskin-auth-open-from-drawer" in mobile and "data-gloskin-auth-open-from-drawer" in js, "mobile quick-auth path missing")
for forbidden in ("wp_ajax_nopriv", "wp_ajax_", "register_rest_route( 'gloskin/v1', '/login", "localStorage.setItem('password", "sessionStorage"):
    require(forbidden not in adapter + js, f"custom credential/auth path forbidden: {forbidden}")
require(js.count("fetch(") == 2, "unexpected frontend fetch path added")

# Final shell/header/drawer/footer polish stays scoped to existing presentation owners.
require(".gloskin-ui1-sheet{top:var(--gloskin-ui1-admin-bar-height)}" in production, "commerce sheets must reuse the canonical admin-bar offset")
require(".gloskin-ui1-nav--desktop>.gloskin-ui1-nav__list>" in production and "::before" in production, "desktop top-level nav indicator missing")
require(".gloskin-ui1-footer__brand::after" in production and ".gloskin-ui1-footer__grid>div:not(.gloskin-ui1-footer__brand)" in production, "footer hierarchy polish missing")
for required in ("readiness-contract-smoke.py", "readiness-php-smoke.php", "readiness-browser-smoke.py", "rendered-shell-auth-smoke.php"):
    require(required in runner, f"{required} must run through tests/check-runtime.sh")

# Woo/account page heading and version sync.
require("gloskin_ui1_render_commerce_page_heading" in shell, "cart/checkout/account H1 owner missing")
header_version = re.search(r"\* Version:\s*([0-9.]+)", main_plugin).group(1)
kernel_version = re.search(r"const VERSION = '([^']+)'", kernel).group(1)
require(header_version == kernel_version == "0.7.1", "plugin/kernel version mismatch")

print("readiness-contract-smoke: OK")
