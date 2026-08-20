#!/usr/bin/env python3
"""Focused ownership contract for shared headings, Treatments CTA and Skincare presentation."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css")
helpers = read("plugin/gloskin-site-core/templates/parts/template-helpers.php")
skincare = read("plugin/gloskin-site-core/templates/pages/skincare.php")
treatments = read("plugin/gloskin-site-core/templates/pages/treatments.php")
shop = read("plugin/gloskin-site-core/templates/pages/shop.php")
core_js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
kernel = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php")
plugin = read("plugin/gloskin-site-core/gloskin-site-core.php")

require(helpers.count("function gloskin_ui1_render_section_heading") == 1 and
        skincare.count("gloskin_ui1_render_section_heading( __( 'Produk yang Tersedia'") == 1,
        "shared helper must remain the only section-heading markup owner")

heading_rule = css.split(".gloskin-ui1-section-heading{", 1)[1].split("}", 1)[0]
heading_h2 = css.split(".gloskin-ui1-section-heading h2{", 1)[1].split("}", 1)[0]
heading_p = css.split(".gloskin-ui1-section-heading p{", 1)[1].split("}", 1)[0]
require("grid-template-columns" in heading_rule and "align-items:end" in heading_rule,
        "desktop heading must use the balanced two-column composition")
require("22ch" not in heading_h2 and "text-wrap:balance" in heading_h2 and "repeat(2,minmax(0,1fr))" in heading_rule,
        "heading h2 must not have narrow 22ch cap; grid must use exact 50/50 repeat(2,minmax(0,1fr))")
for token in ("justify-self:end", "max-width:40ch", "line-height:1.65", "text-wrap:pretty", "font-weight:300", "color:var(--gloskin-copy-ink)", "text-align:right"):
    require(token in heading_p, f"desktop description composition missing: {token}")
tablet = css.split("@media (max-width:1040px){", 1)[1].split("@media (max-width:759px)", 1)[0]
require("grid-template-columns:1fr" in tablet and "justify-self:start" in tablet and "margin:0" in tablet,
        "tablet/mobile heading must stack and left-align the description")

generic_pos = css.index(".gloskin-ui1-section{padding:")
tight_pos = css.index(".gloskin-ui1-section--tight{padding:")
require(tight_pos > generic_pos, "final tight rule must follow the generic section shorthand")
mobile = css.split("@media (max-width:760px){", 1)[1]
require(mobile.index(".gloskin-ui1-section--tight{padding:") > mobile.index(".gloskin-ui1-section{padding:"),
        "mobile tight rule must not be overridden by the later generic shorthand")
require("gloskin-ui1-section--intro-only" in skincare and
        ".gloskin-ui1-section--intro-only .gloskin-ui1-section__intro{margin-bottom:0}" in css,
        "standalone Skincare intro must remove only its own trailing margin")
require("gloskin-ui1-section--tight gloskin-ui1-section--intro-only" in shop,
        "standalone Shop intro must share the semantic compact modifier")

require(css.count(".gloskin-ui1-chip{") == 1, "chip component must have exactly one canonical final owner")
for selector in (".gloskin-ui1-chip:hover", ".gloskin-ui1-chip.is-active", '[aria-selected="true"]',
                 ".gloskin-ui1-chip:focus-visible", ".gloskin-ui1-chip:disabled"):
    require(selector in css, f"chip state missing: {selector}")
chip_rule = css.split(".gloskin-ui1-chip{", 1)[1].split("}", 1)[0]
for token in ("appearance:none", "font:inherit", "border-radius:999px", "min-height:42px", "cursor:pointer", "font-weight:500"):
    require(token in chip_rule, f"chip skin missing: {token}")
require("flex-wrap:nowrap" in mobile and "overflow-x:auto" in mobile and "max-width:100%" in mobile,
        "390px filter treatment must scroll safely without viewport overflow")
require("data-gloskin-chip" in skincare and "data-category-slugs" in skincare and
        "data-gloskin-product-card hidden" not in skincare,
        "existing ARIA/filter attributes and no-JS visible cards must remain")
require(core_js.count("function initSkincareChips()") == 1,
        "there must be no second Skincare filtering implementation")

# Premium Shop gateway must sit between the intro and product listing and own
# the only Shop CTA on Skincare; the lower Clinic pathway remains useful.
gateway_pos = skincare.index('data-gloskin-section="skincare-shop-gateway"')
intro_pos = skincare.index('data-gloskin-section="skincare-intro"')
products_pos = skincare.index('data-gloskin-section="skincare-products"')
require(intro_pos < gateway_pos < products_pos,
        "Skincare Shop gateway must render immediately between intro and products")
for copy in (
    "BELANJA GLOSKIN",
    "Lengkapi rutinitas skincare Anda.",
    "Jelajahi seluruh koleksi, lihat detail produk, harga, dan pilihan yang tersedia di halaman Belanja.",
    "Lihat Semua Produk",
):
    require(copy in skincare, f"Skincare Shop gateway copy missing: {copy}")
require(skincare.count("home_url( '/shop/' )") == 1,
        "Skincare must have exactly one canonical Shop destination owner")
require("'eyebrow' => __( 'Shop'" not in skincare and "home_url( '/clinics/' )" in skincare,
        "redundant lower Shop pathway must be removed while Clinic remains")

gateway_rule = css.split(".gloskin-ui1-skincare-shop-gateway{", 1)[1].split("}", 1)[0]
for token in ("display:grid", "grid-template-columns:minmax(0,1fr) auto", "border-radius:var(--gloskin-radius-lg)", "var(--gloskin-refresh-cream)"):
    require(token in gateway_rule, f"Skincare gateway desktop composition missing: {token}")
require(".gloskin-ui1-skincare-shop-gateway__action .gloskin-ui1-button{width:100%}" in css,
        "Skincare gateway CTA must become full-width/tap-friendly on narrow mobile")

# Treatments keeps the shared closing CTA owner but gets locally scoped measure,
# responsive Felix sizing and one concise supporting paragraph.
treatments_close = treatments[treatments.index('data-gloskin-section="treatments-closing"'):]
require("Informasi di situs membantu menyiapkan pertanyaan sebelum konsultasi." in treatments_close,
        "Treatments closing CTA heading missing")
require("Gunakan informasi ini sebagai panduan awal, lalu pilih klinik atau hubungi Gloskin untuk melanjutkan konsultasi melalui kanal yang tersedia." in treatments_close,
        "Treatments closing CTA supporting copy missing")
require("<br" not in treatments_close.lower(), "Treatments closing CTA must not hard-code line breaks")

treatment_rule = css.split('[data-gloskin-section="treatments-closing"] .gloskin-ui1-closing-cta{', 1)[1].split("}", 1)[0]
treatment_h2 = css.split('[data-gloskin-section="treatments-closing"] .gloskin-ui1-closing-cta h2{', 1)[1].split("}", 1)[0]
for token in ("grid-template-columns:minmax(0,1.55fr) auto", "align-items:center"):
    require(token in treatment_rule, f"Treatments CTA geometry missing: {token}")
for token in ("max-width:24ch", "font-size:clamp(", "line-height:1.02", "text-wrap:balance"):
    require(token in treatment_h2, f"Treatments CTA heading treatment missing: {token}")
scoped_tail = css[css.index('/* Treatments closing CTA:'):]
require("line-clamp" not in scoped_tail and "text-overflow:ellipsis" not in scoped_tail and "!important" not in scoped_tail,
        "Treatments/Skincare polish must not hide text or override ownership with !important")
require("@media (max-width:900px)" in scoped_tail and "grid-template-columns:1fr" in scoped_tail,
        "Treatments/Skincare polish must stack cleanly below desktop")

require("!important" not in css, "final presentation owner must not introduce !important")
require("Version: 0.7.163" in plugin and "const VERSION = '0.7.163';" in kernel,
        "plugin and Kernel version must be synchronized")
print("shared-section-skincare-contract.py: OK (Treatments + Skincare UX polish)")
