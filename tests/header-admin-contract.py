#!/usr/bin/env python3
"""Canonical public header/presentation hygiene contract."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugin/gloskin-site-core"


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


header = read("plugin/gloskin-site-core/templates/parts/header.php")
shell = read("plugin/gloskin-site-core/templates/shell.php")
admin = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php")
template_service = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php")
nav = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-navigation-service.php")
core_css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css")
core_js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
admin_css = read("plugin/gloskin-site-core/assets/css/gloskin-admin.css")
admin_js = read("plugin/gloskin-site-core/assets/js/gloskin-admin.js")
check_runtime = read("tests/check-runtime.sh")
plugin = read("plugin/gloskin-site-core/gloskin-site-core.php")
kernel = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php")

# The approved prototype has exactly one public header markup path. Historical
# header_variant data may still exist in the settings option, but the renderer
# must have no branch and no ability to read it.
require(header.count('data-gloskin-header="header-2"') == 1, "canonical header-2 markup must render exactly once")
require('data-gloskin-header="header-1"' not in header, "legacy header-1 public markup must be retired")
require("$gloskin_header_variant" not in header and "header_variant" not in header, "public header must not read legacy header_variant state")
require("if ( 'header-2'" not in header and "else : ?>\n<header class=\"gloskin-ui1-header\"" not in header, "public header must have no variant branch")
require(header.count("function gloskin_ui1_render_nav_tree") == 1, "one nav-tree renderer must remain")
require(header.count("gloskin_ui1_render_nav_tree( $gloskin_navigation") == 1, "desktop header must render one NavigationService tree")
for utility in ("data-gloskin-search-open", "data-gloskin-auth-open", "data-gloskin-wishlist-open", "data-gloskin-cart-open", "data-gloskin-drawer-open"):
    require(utility in header, f"canonical header lost commerce/search utility: {utility}")
require("gloskin-ui1-header__nav-row" not in header and "gloskin-ui1-compact-brand" not in header,
        "legacy two-row header-1 markup must be absent")

# The public shell is similarly presentation-agnostic: stale medical/modern/
# luxury settings cannot become body classes and revive an old visual system.
require("design_variant" not in shell and "$gloskin_variant" not in shell, "public shell must not consume legacy design_variant")
require("$gloskin_body_classes = array( 'gloskin-ui1' );" in shell, "public shell must use one canonical presentation root")
for legacy_class in ("gloskin-ui1--medical", "gloskin-ui1--modern", "gloskin-ui1--luxury"):
    require(legacy_class not in shell, f"legacy public design class remains reachable: {legacy_class}")
require("gloskin-ui1--home" in shell, "Home-specific non-variant state class must remain")

# Presentation variant settings are fully retired in v0.7.140:
# - No private methods in template-service that read design/header variant
# - No context projection of design_variant / header_variant
# - No hidden inputs in admin settings UI
# The important invariant: neither public renderer consumes them.
require("private function design_variant()" not in template_service, "design_variant() private method must be fully removed from template-service")
require("private function header_variant()" not in template_service, "header_variant() private method must be fully removed from template-service")
require("$context['design_variant']" not in template_service, "design_variant must not be projected into context")
require("$context['header_variant']" not in template_service, "header_variant must not be projected into context")
require("header_variant" not in header and "design_variant" not in shell,
        "historical settings must terminate before public rendering")

# Primary IA remains exact and ordered. Desktop and mobile both consume this
# same NavigationService tree; no support route returns to primary nav.
targets_start = nav.index("$targets = array(")
targets_end = nav.index(");", targets_start)
targets = nav[targets_start:targets_end]
ordered = [
    ("'/treatments/'", "'Perawatan'"),
    ("'/promo/'", "'Promo'"),
    ("'/skincare/'", "'Skincare'"),
    ("'/about/'", "'Tentang Gloskin'"),
]
positions = []
for path, label in ordered:
    require(path in targets and label in targets, f"approved primary destination missing: {label}")
    positions.append(targets.index(path))
require(positions == sorted(positions), "primary nav order must be Perawatan, Promo, Skincare, Tentang Gloskin")
for obsolete in ("'/shop/'", "'/clinics/'", "'/doctors/'", "'/insights/'", "'/contact/'"):
    require(obsolete not in targets, f"support route returned to primary nav: {obsolete}")
require("return $this->approved_primary_tree" in nav and "return $this->fallback_tree()" in nav,
        "assigned and fallback nav must share the approved projection")

# Canonical header keeps the proven Header-2 sticky/glass CSS and one existing
# JS interaction owner. We do not add a second header controller.
require('[data-gloskin-header="header-2"]{position:sticky' in core_css, "canonical header sticky CSS missing")
require('body.gloskin-ui1--home [data-gloskin-header="header-2"]' in core_css, "Home canonical glass header CSS missing")
require('[data-gloskin-header="header-2"] .gloskin-ui1-header__inner{grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);' in core_css,
        "Header V2 geometric centering must remain the approved 1fr/auto/1fr structure")
require(core_js.count("function initSmartHeader()") == 1, "must keep exactly one smart-header JS owner")
require(core_js.count("function initNavBubble()") == 1, "must keep exactly one nav-bubble JS owner")
for forbidden in ("initHeader2Sticky", "initHeader2Scroll", "Header2Controller", "initSmartHeaderType2"):
    require(forbidden not in core_js, f"second header controller introduced: {forbidden}")

# Existing admin shell remains presentation-only. Its historical variant fields
# are inert compatibility UI, not a second public presentation owner.
require("#gloskin-admin-root" in admin_css and "!important" not in admin_css, "admin shell scope/purity regressed")
require("function init()" in admin_js, "admin settings progressive enhancement missing")
require("localStorage" not in admin_js and "fetch(" not in admin_js and "XMLHttpRequest" not in admin_js,
        "admin settings JS must not become a state/network owner")

# Commerce behavior stays protected by the unchanged native owners plus the
# existing full-suite contracts for Shop/PDP/Cart/Checkout/My Account.
for token in ("$gloskin_commerce_native", "woocommerce_content()", "is_cart()", "is_checkout()", "gloskin_ui1_register_product_description_boundary"):
    require(token in shell, f"native commerce shell boundary lost: {token}")
for test_command in (
    "php tests/shop-catalog-contract.php",
    "./tests/single-product-commerce-contract.sh",
    "php tests/rendered-shell-auth-smoke.php",
    "python tests/cart-block-mobile-regression.py",
    "python tests/checkout-block-presentation-regression.py",
):
    require(test_command in check_runtime, f"full suite no longer protects commerce path: {test_command}")

# Release/cache version must move coherently; migration schema is intentionally
# unrelated and therefore not asserted/changed here.
require("Version: 0.7.140" in plugin, "plugin header must be 0.7.140")
require("const VERSION = '0.7.140';" in kernel, "Kernel VERSION must be 0.7.140")
require("0.7.137" not in plugin and "0.7.137" not in kernel, "stale active release version remains")

print("header-admin-contract: OK (canonical prototype header)")
