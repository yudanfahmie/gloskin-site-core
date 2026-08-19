#!/usr/bin/env python3
"""Static regression contract for the approved 2026-08-18 prototype refresh."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")

def require(condition, message):
    if not condition:
        raise AssertionError(message)

css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css")
consultation_css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-consultation.css")
consultation_rules = re.sub(r"/\*.*?\*/", "", consultation_css, flags=re.S)
assets = read("plugin/gloskin-site-core/config/assets.php")
fonts = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-fonts.css")
home = read("plugin/gloskin-site-core/templates/pages/home.php")
promo = read("plugin/gloskin-site-core/templates/pages/promo.php")
about = read("plugin/gloskin-site-core/templates/pages/about.php")
skincare = read("plugin/gloskin-site-core/templates/pages/skincare.php")
helpers = read("plugin/gloskin-site-core/templates/parts/template-helpers.php")
template_service = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php")
page_matrix = read("docs/page-matrix.csv")

for value in ("#CA050E", "#784F0C", "#F6D179", "#FBE2B2", "#FFEBBB", "#FFF2EB", "#000000"):
    require(value in css, f"approved brand color missing: {value}")
require('"Graphik"' in css, "Graphik target role missing")
require('"Felix Titling"' in css, "Felix Titling target role missing")
require("!important" not in css, "prototype refresh must add zero !important declarations")
require("@media (prefers-reduced-motion:reduce)" in css, "reduced-motion contract missing")
require(":focus-visible" in css, "keyboard focus contract missing")
require("min-height:44px" in css, "practical touch target contract missing")
require("not distributed in this repository" not in css, "stale absent-font-binary comment must not return")
require("does not establish redistribution rights" in css, "font runtime note must keep licensing/redistribution boundary explicit")

# Owner-supplied Graphik and Felix Titling binaries are installed in the current
# release. The @font-face registry must declare both families; the preload list
# must reference only the two critical faces; legacy Marcellus/Mulish preloads
# must be retired.
require('"Graphik"' in fonts, "Graphik @font-face must be declared in gloskin-ui1-fonts.css")
require('"Felix Titling"' in fonts, "Felix Titling @font-face must be declared in gloskin-ui1-fonts.css")
require("'assets/fonts/Graphik-Regular.woff'" in assets, "Graphik-Regular.woff must be in the preload list")
require("'assets/fonts/Felixti.woff2'" in assets, "Felixti.woff2 must be in the preload list")
require("Marcellus-Regular.woff2" not in assets, "retired Marcellus preload must be removed from assets.php")
require("Mulish-Variable.woff2" not in assets, "retired Mulish preload must be removed from assets.php")

require("'gloskin-ui1-prototype-refresh' => array(" in assets, "refresh style is not registered")
require("'assets/css/gloskin-ui1-prototype-refresh.css'" in assets, "refresh asset path missing")
require("'deps'  => array( 'gloskin-ui1-product-grid' )" in assets, "refresh must follow the shared product-grid layer")
consultation = assets[assets.index("'gloskin-ui1-consultation' => array("):]
require("'deps'  => array( 'gloskin-ui1-prototype-refresh' )" in consultation, "Treatments specialist layer must follow refresh")

require(home.count("gloskin_ui1_render_hero(") == 1, "Home must render exactly one shared hero")
require("gloskin_ui1_render_managed_promo_carousel( $gloskin_context['promo'], 'h2', true )" in home,
        "Home must keep the protected managed Promo carousel with h2 + compact presentation")
for obsolete in ("home-doctors", "home-clinics", "home-insights"):
    require(obsolete not in home, f"superseded primary Home section remains: {obsolete}")
require("gloskin_ui1_render_testimonials( $gloskin_context['testimonials'] );" in home,
        "Home factual testimonials helper must remain context-driven")
require("gloskin_ui1_render_achievements( $gloskin_context['achievements'], 'compact' );" in home,
        "Home factual achievements helper must remain context-driven")
for fabricated in ("piagam", "award", "penghargaan"):
    require(fabricated not in home.lower(), f"Home must not hardcode fabricated {fabricated} content")
require("gloskin_ui1_render_why_gloskin( $gloskin_context['page'] );" in home,
        "Home protected Why Gloskin/About transition missing")
home_context = template_service.split("private function home_context()", 1)[1].split("private function about_context()", 1)[0]
require("$hero['mode'] = 'campaign';" in home_context, "Home must use visible campaign hero mode")
require("'video-only'" not in home_context, "strict video-only Home authority must remain retired")
require("$hero['media_id'] = 0;" not in home_context, "Home must not discard its factual/editorial fallback media")
for removed_owner in ("'clinics'", "'doctors'", "'insights'"):
    require(removed_owner not in home_context, f"Home context still requires support data: {removed_owner}")
require("gloskin-ui1-hero--campaign" in helpers and '<h1 class="gloskin-ui1-hero__title">' in helpers,
        "shared hero renderer must keep a visible semantic H1 in campaign mode")
require("data-gloskin-hero-bg-video" in helpers and helpers.count("<video class=\"gloskin-ui1-hero-bg-video__media\"") == 1,
        "campaign hero must reuse exactly one existing native video node")

require("'promo'      => 'Promo'" in template_service, "Promo document-title mapping missing")
require("'promo' => 'promo'" in template_service, "Promo Page view mapping missing")
require("case 'promo': return $this->promo_context();" in template_service, "Promo context routing missing")
require("private function managed_promo_records" in template_service, "managed Promo record projection missing")
require("gloskin_ui1_render_managed_promo_carousel( $gloskin_context['promos'], 'h1', false )" in promo
        and "gloskin_ui1_render_page_content" in promo,
        "Promo route must keep managed campaign carousel and native Page long-form content")
require("function gloskin_ui1_render_promo_campaign" in helpers, "shared Promo renderer missing")
for invented in ("diskon", "harga promo", "berlaku sampai", "syarat promo", "bpom"):
    require(invented not in promo.lower(), f"Promo template must not invent commercial fact: {invented}")

require("gloskin_ui1_has_content" in about, "About story must be source-gated")
require("$gloskin_has_principles" in about, "About principles must be source-gated")
require("if ( $gloskin_context['doctors'] )" in about, "About team section must be source-gated")
require("if ( $gloskin_about_clinics )" in about, "About network section must be source-gated")
require("$gloskin_founder" in about, "About founder projection must remain source-gated")
require("if ( $gloskin_founder )" in about, "About founder section must render only when source data exists")
for fabricated in ("award", "penghargaan", "sertifikasi terbaik"):
    require(fabricated not in about.lower(), f"About must not fabricate {fabricated}")

# Treatments keeps the existing consultation engine; only pathway geometry is
# converged: configured path labels/media remain canonical, 4 -> 2 -> 1.
require("grid-template-columns:repeat(4,minmax(0,1fr))" in consultation_rules, "desktop Treatments pathways must present four configured cards")
require("object-fit:cover" in consultation_rules, "Treatment pathway media must use prototype-style cover geometry")
require("@media (max-width:900px)" in consultation_rules and "repeat(2,minmax(0,1fr))" in consultation_rules,
        "Treatment pathway grid must collapse to two columns")
require("@media (max-width:480px)" in consultation_rules and "grid-template-columns:1fr" in consultation_rules,
        "Treatment pathway grid must collapse to one column on narrow phones")
require("@media (prefers-reduced-motion:reduce)" in consultation_rules, "Treatment pathway motion needs reduced-motion handling")
for hardcoded_path in ("Face", "Hair", "Body", "Wellness"):
    require(hardcoded_path not in consultation_rules, f"Treatments CSS rules must not hardcode prototype pathway label: {hardcoded_path}")

require("gloskin-ui1-product-grid" in skincare and "data-gloskin-product-grid" in skincare, "Skincare must reuse the canonical product grid")
require("Prototype-controlled primary hero/campaign" in page_matrix, "page matrix must record the current Home target")
require("strict native video-only hero" not in page_matrix, "superseded Home hero requirement remains canonical")
require("/promo/" in page_matrix, "Promo route missing from current page matrix")

print("prototype-refresh-contract: OK")
