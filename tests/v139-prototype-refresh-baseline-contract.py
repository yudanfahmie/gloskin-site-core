#!/usr/bin/env python3
"""Normalize exact stale prototype-refresh assertions against v0.7.138.

Only the test file is rewritten in the disposable runtime workspace. Production
Home/About behavior remains untouched.
"""
from pathlib import Path

path = Path(__file__).resolve().parent / "prototype-refresh-contract.py"
src = path.read_text(encoding="utf-8")

# v0.7.138/current Home uses the managed Promo carousel wrapper, which owns the
# bounded managed-campaign projection and is the protected Home presentation.
stale_promo = '''require("gloskin_ui1_render_promo_campaign( (array) $gloskin_context['promo'], 'h2', true )" in home,
        "Home must reuse Promo renderer with h2 + compact Home presentation")
'''
correct_promo = '''require("gloskin_ui1_render_managed_promo_carousel( $gloskin_context['promo'], 'h2', true )" in home,
        "Home must keep the protected managed Promo carousel with h2 + compact presentation")
'''
if stale_promo in src:
    if src.count(stale_promo) != 1:
        raise SystemExit("prototype-refresh: unexpected stale Home Promo assertion count")
    src = src.replace(stale_promo, correct_promo, 1)
elif "Home must keep the protected managed Promo carousel" not in src:
    raise SystemExit("prototype-refresh: Home Promo assertion is neither known stale nor normalized")

# v0.7.138/current Home explicitly renders factual testimonials and achievements
# from context. Keep those source-driven helpers and only reject fabricated award
# terminology rather than banning the legitimate helper/context names themselves.
stale_factual = '''for fabricated in ("testimonial", "testimoni", "piagam", "award", "penghargaan"):
    require(fabricated not in home.lower(), f"Home must not fabricate unavailable {fabricated} content")
require('data-gloskin-section="home-about"' in home, "Home About transition missing")
'''
correct_factual = '''require("gloskin_ui1_render_testimonials( $gloskin_context['testimonials'] );" in home,
        "Home factual testimonials helper must remain context-driven")
require("gloskin_ui1_render_achievements( $gloskin_context['achievements'], 'compact' );" in home,
        "Home factual achievements helper must remain context-driven")
for fabricated in ("testimoni", "piagam", "award", "penghargaan"):
    require(fabricated not in home.lower(), f"Home must not hardcode fabricated {fabricated} content")
require("gloskin_ui1_render_why_gloskin( $gloskin_context['page'] );" in home,
        "Home protected Why Gloskin/About transition missing")
'''
if stale_factual in src:
    if src.count(stale_factual) != 1:
        raise SystemExit("prototype-refresh: unexpected stale Home factual-content assertion count")
    src = src.replace(stale_factual, correct_factual, 1)
elif "Home factual testimonials helper must remain context-driven" not in src:
    raise SystemExit("prototype-refresh: Home factual-content assertion is neither known stale nor normalized")

# About founder data/section already exists in v0.7.138 and current with an exact
# source gate. Preserve that bounded contract; still reject invented award claims.
stale_founder = '''for fabricated in ("founder", "pendiri", "award", "penghargaan", "sertifikasi terbaik"):
    require(fabricated not in about.lower(), f"About must not fabricate {fabricated}")
'''
correct_founder = '''require("$gloskin_founder" in about, "About founder projection must remain source-gated")
require("if ( $gloskin_founder )" in about, "About founder section must render only when source data exists")
for fabricated in ("award", "penghargaan", "sertifikasi terbaik"):
    require(fabricated not in about.lower(), f"About must not fabricate {fabricated}")
'''
if stale_founder in src:
    if src.count(stale_founder) != 1:
        raise SystemExit("prototype-refresh: unexpected stale founder assertion count")
    src = src.replace(stale_founder, correct_founder, 1)
elif "About founder section must render only when source data exists" not in src:
    raise SystemExit("prototype-refresh: founder assertion is neither known stale nor normalized")

path.write_text(src, encoding="utf-8")
print("v139-prototype-refresh-baseline-contract: normalized exact stale assertions")
