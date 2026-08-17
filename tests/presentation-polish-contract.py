#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "plugin" / "gloskin-site-core" / "assets"


def read(path):
    return path.read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise SystemExit("presentation-polish-contract.py: FAIL: " + message)


production = read(ASSETS / "css" / "gloskin-ui1-production.css")
shop_css = read(ASSETS / "css" / "gloskin-ui1-shop-discovery.css")
loader_css = read(ASSETS / "css" / "gloskin-ui1-loader-system.css")
readiness = read(ASSETS / "css" / "gloskin-ui1-readiness.css")
shop_js = read(ASSETS / "js" / "gloskin-ui1-shop-discovery.js")

# Cart: desktop header only; the established mobile grid contract must remain.
cart_desktop = production.split("@media (min-width:769px){", 1)[1].split("@media (max-width:768px){", 1)[0]
require(".wc-block-cart-items thead{" in cart_desktop, "desktop Cart thead owner missing")
require("border:0;\n\t\tbackground:var(--gloskin-accent);" in cart_desktop, "Cart thead must use accent with no border")
require("border:0;\n\t\tcolor:var(--gloskin-inverse);" in cart_desktop, "Cart th must use inverse text with no border")
require("font-weight:700;" in cart_desktop, "Cart th must be weight 700")
require("grid-auto-columns:0px 80px;" in production, "mobile Cart grid contract changed")
require("@media (max-width:359px)" in production and "grid-auto-columns:0px 52px" in production, "narrow Cart grid contract changed")

# Shop: keep one loading lifecycle/request owner and add one aria-hidden visual overlay.
require("var SKELETON_CARD_COUNT = 8;" in shop_js, "Shop skeleton count must remain eight")
require(shop_js.count("function setBusy(busy)") == 1, "setBusy must remain the single loading lifecycle owner")
require("aria-hidden=\"true\"" in shop_js, "skeleton parent must remain aria-hidden")
require(shop_js.count("gloskin-ui1-shop-skeleton__loader") == 1, "Shop must render exactly one goo overlay host")
require("gloskin-ui1-shop-skeleton__goo" in shop_js, "Shop semantic goo host missing")
require("Loading product…" in shop_js, "visible Shop loading label missing")
require("data-gloskin-shop-status-live" in shop_js and "live.textContent = 'Memuat produk';" in shop_js, "existing live region owner missing")
require("requestSequence" in shop_js and "AbortController" in shop_js, "existing request sequencing/abort owners missing")
require(shop_js.count("return window.fetch(endpoint, fetchOptions)") == 1, "Shop must keep one REST request owner")
require("MutationObserver" not in shop_js, "Shop loading must not add a MutationObserver")
require(loader_css.count("@keyframes gloskin-ui1-goo-loader-dance") == 1, "goo motion must have one shared keyframe owner")
require(".gloskin-ui1-quickadd__loading,\n.gloskin-ui1-shop-skeleton__goo" in loader_css, "Shop must reuse Quick Add goo declarations")
require(".gloskin-ui1-shop-skeleton__goo::before" in loader_css and "animation:gloskin-ui1-goo-loader-dance" in loader_css, "Shop goo must reuse shared animation")
require("@media (prefers-reduced-motion:reduce)" in loader_css and ".gloskin-ui1-shop-skeleton__goo>span::after" in loader_css, "Shop goo reduced-motion contract missing")
require(".gloskin-ui1-shop-skeleton__grid" in shop_css and "opacity: var(--gloskin-shop-loading-opacity);" in shop_css, "skeleton must stay visible beneath loader")
require(".gloskin-ui1-shop-skeleton__loader" in shop_css and "pointer-events: none;" in shop_css, "Shop loader must be non-interactive")

# Account: native Woo selectors remain the composition owner, scoped logged-in only.
account = readiness.split("/* Logged-in My Account polish:", 1)[1]
require(".woocommerce-account.logged-in" in account, "Account polish must be logged-in scoped")
require(".woocommerce-MyAccount-navigation" in account and "border-radius:12px" in account, "segmented native My Account rail missing")
require("content:none" in account, "old active navigation underline must be removed")
require(".woocommerce-MyAccount-content{\n\tpadding:0;\n\tborder:0;\n\tborder-radius:0;\n\tbackground:transparent;" in account, "Account content must be a transparent composition canvas")
require("font-size:clamp(1.4rem,2vw,1.8rem);" in account, "scoped Account H2 size missing")
require("font-size:clamp(1.15rem,1.5vw,1.35rem);" in account, "scoped Account H3 size missing")
require(".woocommerce-Addresses" in account and "grid-template-columns:repeat(2,minmax(0,1fr));" in account, "desktop address grid missing")
require("@media (max-width:782px)" in account and ".woocommerce-Addresses{grid-template-columns:1fr" in account, "responsive address stack missing")
require("form.woocommerce-EditAccountForm" in account and "grid-template-columns:repeat(2,minmax(0,1fr));" in account, "native Edit Account grid missing")
require("border:0;\n\tborder-top:1px solid var(--gloskin-border);" in account, "password fieldset must be flattened")
require(".woocommerce-info" in account and ".woocommerce-message" in account and ".woocommerce-error" in account, "native Woo notices must remain styled")
require("!important" not in account, "Account polish must add no !important")

# Ownership: no My Account template override is allowed.
templates = ROOT / "plugin" / "gloskin-site-core" / "templates"
require(not any("myaccount" in p.as_posix().lower() for p in templates.rglob("*") if p.is_file()), "My Account template override detected")

print("presentation-polish-contract.py: OK")
