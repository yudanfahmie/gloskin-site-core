#!/usr/bin/env python3
"""Static contract checks for the Header Type 1/2 variant system and the
Gloskin admin presentation shell."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugin/gloskin-site-core"


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def require(cond, message):
    if not cond:
        raise AssertionError(message)


admin = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php")
template_service = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php")
assets_service = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php")
assets_config = read("plugin/gloskin-site-core/config/assets.php")
header = read("plugin/gloskin-site-core/templates/parts/header.php")
core_css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css")
admin_css = read("plugin/gloskin-site-core/assets/css/gloskin-admin.css")
admin_js = read("plugin/gloskin-site-core/assets/js/gloskin-admin.js")

# --- Canonical setting: gloskin_ui1_header_variant lives inside the one
# existing Gloskin settings option, strict allowlist, header-1 fallback. ---
require("'header_variant' => 'header-1'" in admin, "settings_defaults() must default header_variant to header-1")
require("in_array( $header_variant, array( 'header-1', 'header-2' ), true ) ? $header_variant : 'header-1'" in admin, "sanitize_settings() must strict-allowlist header_variant with header-1 fallback")
require("class Gloskin_Site_Core_Header_Settings_Service" not in "\n".join(p.read_text(encoding="utf-8") for p in PLUGIN.rglob("*.php")), "must not introduce a second Header_Settings_Service settings framework")
require("private function header_variant()" in template_service, "Template Service must own reading the canonical header_variant setting")
require("in_array( $value, array( 'header-1', 'header-2' ), true ) ? $value : 'header-1'" in template_service, "Template Service header_variant() must strict-allowlist the same way")
require("$context['header_variant'] = $this->header_variant();" in template_service, "header_variant must be threaded into the one shared page context")

# --- One canonical server-rendered header, composition branches on the one
# variant value; Header 1 stays the literal, unmodified default branch. ---
require(header.count("data-gloskin-header=\"header-1\"") == 1 and header.count("data-gloskin-header=\"header-2\"") == 1, "header.php must expose exactly one header-1 and one header-2 branch")
require("if ( 'header-2' === $gloskin_header_variant ) :" in header and "else :" in header, "header.php must branch on the one variant value, never render both")
require(header.count("gloskin_ui1_render_nav_tree( $gloskin_navigation,") >= 2, "both variants must reuse the one canonical nav-tree renderer")
require("function gloskin_ui1_render_nav_tree" in header and header.count("function gloskin_ui1_render_nav_tree") == 1, "must not introduce a second nav-tree renderer")
require("gloskin-ui1-header__nav-row" not in header[header.index('data-gloskin-header="header-2"'):header.index('<?php else :')], "Header Type 2 must not render a second sticky nav row")
require("data-gloskin-drawer-open" in header, "both header variants must keep the one existing mobile drawer trigger")

# --- Header Type 2 CSS: scoped to the variant attribute, one sticky owner,
# reuses the existing compact/mobile breakpoint, zero new !important. ---
require('[data-gloskin-header="header-2"]{position:sticky' in core_css, "Header Type 2 must be its own sticky owner")
require('[data-gloskin-header="header-2"] .gloskin-ui1-header__inner{grid-template-columns:auto minmax(0,1fr) auto}' in core_css, "Header Type 2 must use the specified auto/flex/auto grid")
require('@media (max-width:1040px){[data-gloskin-header="header-2"] .gloskin-ui1-nav--desktop{display:none}}' in core_css, "Header Type 2 must reuse the existing 1040px compact/mobile breakpoint, not invent a new one")
require("!important" not in core_css[core_css.index('[data-gloskin-header="header-2"]'):core_css.index('[data-gloskin-header="header-2"]') + 900], "Header Type 2 CSS must not use !important")

# --- Admin shell: Gloskin-owned naming, isolated scope, zero !important. ---
require("#gloskin-admin-root" in admin_css, "admin shell must be scoped beneath #gloskin-admin-root")
for owned_class in (".gloskin-admin-shell", ".gloskin-admin-shell__sidebar", ".gloskin-admin-tabs", ".gloskin-admin-canvas", ".gloskin-admin-card"):
    require(owned_class in admin_css, f"admin shell must define Gloskin-owned class: {owned_class}")
require("!important" not in admin_css, "admin shell CSS must carry zero !important")
require("id=\"gloskin-admin-root\"" in admin, "Settings page must render the #gloskin-admin-root shell")
for tab_label in ("Brand", "Header", "Booking & Social", "Page Mapping"):
    require(f"'{tab_label}'" in admin or f'"{tab_label}"' in admin, f"Settings page must expose a {tab_label} tab")

# --- Header picker: real radio inputs, one per variant, label-wrapped,
# decorative preview image (image is not the control). ---
require(admin.count('type="radio"') >= 2, "Header picker must use real native radio inputs")
require("self::SETTINGS_OPTION ); ?>[header_variant]\"" in admin, "Header picker radios must post into the one settings option's header_variant key")
require("render_header_variant_card( 'header-1'" in admin and "render_header_variant_card( 'header-2'" in admin, "Settings page must render exactly the header-1 and header-2 cards")
require('<label class="gloskin-admin-header-card' in admin, "each Header Type card must be a real <label> so the whole card activates its radio")
require('alt=""' in admin, "Header Type preview image must stay presentation-only (empty alt), not a duplicate control/announcement")

# --- Preview PNGs: real local Playwright captures, not fabricated/missing. ---
for filename in ("header-type-1.png", "header-type-2.png"):
    png_path = PLUGIN / "assets/admin" / filename
    require(png_path.is_file(), f"missing real preview screenshot: assets/admin/{filename}")
    require(png_path.stat().st_size > 2000, f"assets/admin/{filename} looks empty/fabricated (too small)")
    require(png_path.read_bytes()[:8] == b"\x89PNG\r\n\x1a\n", f"assets/admin/{filename} must be a real PNG")

# --- Assets: AssetService remains the sole registry/enqueue owner; the
# admin shell CSS/JS load only on the Settings screen, never globally. ---
require("'gloskin-admin' => array(" in assets_config, "gloskin-admin CSS/JS must be declared in the one asset registry")
require("public function enqueue_admin_settings()" in assets_service, "AssetService must own enqueueing the admin shell assets")
require(assets_service.count("wp_enqueue_style( 'gloskin-admin' )") == 1, "gloskin-admin.css must be enqueued from exactly one place")
require("public function enqueue_settings_assets( $hook_suffix )" in admin, "Admin Service must gate the admin shell enqueue to the exact Settings screen hook")
require("if ( '' === $this->settings_hook || $hook_suffix !== $this->settings_hook ) { return; }" in admin, "admin shell assets must never load on unrelated wp-admin screens")

# --- Zero Morgen runtime dependency: pattern adoption only, no reference to
# the source repo/its namespace/branding anywhere in the shipped plugin. ---
plugin_php_and_css_and_js = "\n".join(
    p.read_text(encoding="utf-8")
    for p in list(PLUGIN.rglob("*.php")) + list(PLUGIN.rglob("*.css")) + list(PLUGIN.rglob("*.js"))
)
require("morgen" not in plugin_php_and_css_and_js.lower(), "zero runtime reference to morgen-core anywhere in the shipped plugin")
require("MGA" not in plugin_php_and_css_and_js, "must not adopt the Morgen MGA namespace")

# --- Admin JS: presentation-only tab switching, owns no settings state. ---
require("function init()" in admin_js, "admin tab-switching script present")
require("localStorage" not in admin_js and "fetch(" not in admin_js and "XMLHttpRequest" not in admin_js, "admin shell JS must own no state/network -- presentation-only tab switching")

print("header-admin-contract: OK")
