#!/usr/bin/env python3
"""Focused ownership contract for shared headings and Skincare presentation."""
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
require("!important" not in css, "final presentation owner must not introduce !important")

require("Version: 0.7.164" in plugin and "const VERSION = '0.7.164';" in kernel,
        "plugin and Kernel version must be synchronized")
print("shared-section-skincare-contract.py: OK (0.7.164)")
