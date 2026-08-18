#!/usr/bin/env python3
"""Static regression contract for the approved 2026-08-18 prototype refresh."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugin" / "gloskin-site-core"


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css")
assets = read("plugin/gloskin-site-core/config/assets.php")
home = read("plugin/gloskin-site-core/templates/pages/home.php")
skincare = read("plugin/gloskin-site-core/templates/pages/skincare.php")
page_matrix = read("docs/page-matrix.csv")

# Approved brand system: one bounded convergence layer, no priority escalation.
for value in ("#CA050E", "#784F0C", "#F6D179", "#FBE2B2", "#FFEBBB", "#FFF2EB", "#000000"):
    require(value in css, f"approved brand color missing: {value}")
require('"Graphik"' in css, "Graphik role missing")
require('"Felix Titling"' in css, "Felix Titling role missing")
require("!important" not in css, "prototype refresh must add zero !important declarations")
require("@media (prefers-reduced-motion:reduce)" in css, "reduced-motion contract missing")
require(":focus-visible" in css, "keyboard focus contract missing")
require("min-height:44px" in css, "practical touch target contract missing")

# Asset ownership stays in the existing AssetService registry. Treatments keep
# their bounded specialist CSS after the shared refresh rather than forking it.
require("'gloskin-ui1-prototype-refresh' => array(" in assets, "refresh style is not registered")
require("'assets/css/gloskin-ui1-prototype-refresh.css'" in assets, "refresh asset path missing")
require("'deps'  => array( 'gloskin-ui1-product-grid' )" in assets, "refresh must follow the shared product-grid layer")
consultation = assets[assets.index("'gloskin-ui1-consultation' => array("):]
require("'deps'  => array( 'gloskin-ui1-prototype-refresh' )" in consultation, "Treatments specialist layer must follow refresh")

# Home sequence: one editorial H1 first, the same configured Media Library
# video second as a campaign, then the existing data-driven content families.
require("$gloskin_home_editorial_hero = $gloskin_context['hero'];" in home, "Home editorial hero projection missing")
require("unset( $gloskin_home_editorial_hero['mode'], $gloskin_home_editorial_hero['sources'] );" in home, "Home editorial hero must use the standard existing renderer")
require("$gloskin_home_campaign['heading'] = '';" in home, "campaign must not create a second semantic H1")
require('data-gloskin-section="home-campaign"' in home, "Home video campaign marker missing")
require("! empty( $gloskin_home_campaign['sources'] )" in home, "missing video must fail open by omission")
editorial_call = home.index("gloskin_ui1_render_hero( $gloskin_home_editorial_hero )")
campaign_call = home.index("gloskin_ui1_render_hero( $gloskin_home_campaign )")
orientation = home.index('data-gloskin-section="home-orientation"')
require(editorial_call < campaign_call < orientation, "Home must read editorial hero -> video campaign -> orientation")
for fabricated in ("testimonial", "testimoni", "piagam", "award", "penghargaan"):
    require(fabricated not in home.lower(), f"Home must not fabricate unavailable {fabricated} content")

# Product presentation converges on the existing shared grid owner.
require("gloskin-ui1-product-grid" in skincare and "data-gloskin-product-grid" in skincare, "Skincare must reuse the canonical product grid")

# Canonical page matrix must no longer describe the superseded video-only
# primary Home hero.
require("editorial Home hero" in page_matrix, "page matrix must record the refreshed Home composition")
require("strict native video-only hero" not in page_matrix, "superseded Home hero requirement remains canonical")

print("prototype-refresh-contract: OK")
