#!/usr/bin/env python3
"""Static regression contract for the approved 2026-08-18 prototype refresh."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")

def require(condition, message):
    if not condition:
        raise AssertionError(message)

css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css")
assets = read("plugin/gloskin-site-core/config/assets.php")
home = read("plugin/gloskin-site-core/templates/pages/home.php")
promo = read("plugin/gloskin-site-core/templates/pages/promo.php")
skincare = read("plugin/gloskin-site-core/templates/pages/skincare.php")
template_service = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php")
page_matrix = read("docs/page-matrix.csv")

for value in ("#CA050E", "#784F0C", "#F6D179", "#FBE2B2", "#FFEBBB", "#FFF2EB", "#000000"):
    require(value in css, f"approved brand color missing: {value}")
require('"Graphik"' in css, "Graphik role missing")
require('"Felix Titling"' in css, "Felix Titling role missing")
require("!important" not in css, "prototype refresh must add zero !important declarations")
require("@media (prefers-reduced-motion:reduce)" in css, "reduced-motion contract missing")
require(":focus-visible" in css, "keyboard focus contract missing")
require("min-height:44px" in css, "practical touch target contract missing")

require("'gloskin-ui1-prototype-refresh' => array(" in assets, "refresh style is not registered")
require("'assets/css/gloskin-ui1-prototype-refresh.css'" in assets, "refresh asset path missing")
require("'deps'  => array( 'gloskin-ui1-product-grid' )" in assets, "refresh must follow the shared product-grid layer")
consultation = assets[assets.index("'gloskin-ui1-consultation' => array("):]
require("'deps'  => array( 'gloskin-ui1-prototype-refresh' )" in consultation, "Treatments specialist layer must follow refresh")

# Current Home: one primary hero only; old support-route sections are no longer mandatory.
require(home.count("gloskin_ui1_render_hero(") == 1, "Home must render exactly one shared hero")
require('data-gloskin-section="home-promo"' in home and 'href="<?php echo esc_url( home_url( \'/promo/\' ) ); ?>"' in home,
        "Home Promo section/link missing")
for obsolete in ("home-doctors", "home-clinics", "home-insights"):
    require(obsolete not in home, f"superseded primary Home section remains: {obsolete}")
for fabricated in ("testimonial", "testimoni", "piagam", "award", "penghargaan"):
    require(fabricated not in home.lower(), f"Home must not fabricate unavailable {fabricated} content")
require('data-gloskin-section="home-about"' in home, "Home About transition missing")

# Promo is a real native Page routed through the Gloskin shell/template service.
require("'promo'      => 'Promo'" in template_service, "Promo document-title mapping missing")
require("'promo' => 'promo'" in template_service, "Promo Page view mapping missing")
require("case 'promo': return $this->promo_context();" in template_service, "Promo context routing missing")
require("private function promo_context()" in template_service, "Promo context owner missing")
require("gloskin_ui1_render_page_content" in promo, "Promo template must render native Page content")
require("harga" not in promo.lower() and "diskon" not in promo.lower() and "bpom" not in promo.lower(), "Promo empty state must not invent commercial facts")

# Home context no longer spends queries on supporting route preview sections.
home_context = template_service.split("private function home_context()", 1)[1].split("private function about_context()", 1)[0]
for removed_owner in ("'clinics'", "'doctors'", "'insights'"):
    require(removed_owner not in home_context, f"Home context still requires support data: {removed_owner}")

require("gloskin-ui1-product-grid" in skincare and "data-gloskin-product-grid" in skincare, "Skincare must reuse the canonical product grid")
require("Prototype-controlled primary hero/campaign" in page_matrix, "page matrix must record the current Home target")
require("strict native video-only hero" not in page_matrix, "superseded Home hero requirement remains canonical")
require("/promo/" in page_matrix, "Promo route missing from current page matrix")

print("prototype-refresh-contract: OK")
