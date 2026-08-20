#!/usr/bin/env python3
"""Contract: Home Promo Carousel autoplay opt-in and shared section-heading 50/50 grid."""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


failures = []


def require(cond: bool, msg: str) -> None:
    if not cond:
        failures.append(msg)


css     = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css")
helpers = read("plugin/gloskin-site-core/templates/parts/template-helpers.php")
js      = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
plugin  = read("plugin/gloskin-site-core/gloskin-site-core.php")
kernel  = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php")

# ── Section heading 50/50 grid ──────────────────────────────────────────────

heading_rule = css.split(".gloskin-ui1-section-heading{", 1)[1].split("}", 1)[0]
heading_h2   = css.split(".gloskin-ui1-section-heading h2{", 1)[1].split("}", 1)[0]
heading_p    = css.split(".gloskin-ui1-section-heading p{", 1)[1].split("}", 1)[0]

require("repeat(2,minmax(0,1fr))" in heading_rule,
        "desktop heading must use exact 50/50 repeat(2,minmax(0,1fr)) grid")
require("22ch" not in heading_h2,
        "h2 must not have narrow 22ch width constraint")
require("text-wrap:balance" in heading_h2,
        "h2 must retain text-wrap:balance")
require("text-align:right" in heading_p,
        "desktop description must be right-aligned")
require("margin:0 0 0 auto" in heading_p or "margin-left:auto" in heading_p,
        "desktop description must push right with margin-left:auto")
require("font-weight:300" in heading_p,
        "desktop description must have font-weight:300")

# Mobile: left-align
mobile_block = css.split("@media (max-width:760px){", 1)[1]
require("text-align:left" in mobile_block,
        "mobile section-heading description must explicitly set text-align:left")

# ── Carousel: autoplay opt-in ────────────────────────────────────────────────

require("data-gloskin-promo-autoplay" in helpers,
        "compact carousel template must render data-gloskin-promo-autoplay on the section element")
require("$compact && $count > 1" in helpers or "compact && $count > 1" in helpers,
        "autoplay attribute must be conditional on compact mode AND more than one slide")
require("data-gloskin-promo-live" in helpers,
        "carousel template must include a live region with data-gloskin-promo-live")
require("gloskin-ui1-promo-carousel__slide-inner--no-media" in helpers,
        "slide-inner must add --no-media modifier class when no Featured Image")
require("gloskin-ui1-promo-carousel__slide-inner--no-media" in css,
        "CSS must have a rule for the --no-media slide-inner modifier")

# ── Carousel JS ─────────────────────────────────────────────────────────────

fn_start = js.find("function initPromoCarousel()")
fn_end   = js.find("\n\t}", fn_start + 1) if fn_start != -1 else -1
fn_body  = js[fn_start:fn_end] if fn_start != -1 and fn_end != -1 else ""

require("data-gloskin-promo-enhanced" in fn_body,
        "initPromoCarousel() must set data-gloskin-promo-enhanced on the root element")
require("setInterval" in fn_body,
        "initPromoCarousel() must use setInterval for autoplay")
require("data-gloskin-promo-autoplay" in fn_body,
        "initPromoCarousel() must gate autoplay on data-gloskin-promo-autoplay attribute")
require("clearInterval" in fn_body or "stopTimer" in fn_body,
        "initPromoCarousel() must clear the interval timer (no duplicate timers)")
require("n > 1" in fn_body or "slides.length > 1" in fn_body or "&& n > 1" in fn_body,
        "autoplay must only start when more than one slide exists")
require("prefers-reduced-motion" in fn_body or "reducedMotion" in fn_body,
        "initPromoCarousel() must respect prefers-reduced-motion")
require("visibilitychange" in fn_body,
        "initPromoCarousel() must pause autoplay when document is hidden")
require("data-gloskin-promo-live" in fn_body or "liveRegion" in fn_body,
        "initPromoCarousel() must update the live region on user-triggered navigation")

# ── CSS carousel enhancement ─────────────────────────────────────────────────

require("data-gloskin-promo-enhanced" in css,
        "CSS must scope carousel grid-stacking under [data-gloskin-promo-enhanced]")
require("prefers-reduced-motion:reduce" in css,
        "CSS must have a prefers-reduced-motion rule for the carousel transition")
require("!important" not in css,
        "CSS must not introduce !important declarations")

# ── Version ──────────────────────────────────────────────────────────────────

require("Version: 0.7.160" in plugin and "const VERSION = '0.7.160';" in kernel,
        "plugin header and Kernel VERSION must both be 0.7.160")

if failures:
    for f in failures:
        print("FAIL:", f)
    sys.exit(1)

print("carousel-section-heading-contract.py: OK (0.7.160)")
