#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugin" / "gloskin-site-core"

production = (PLUGIN / "assets" / "css" / "gloskin-ui1-production.css").read_text(encoding="utf-8")
core_js = (PLUGIN / "assets" / "js" / "gloskin-ui1-core.js").read_text(encoding="utf-8")
navigation = (PLUGIN / "includes" / "class-gloskin-site-core-navigation-service.php").read_text(encoding="utf-8")
header = (PLUGIN / "templates" / "parts" / "header.php").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise SystemExit("nav-active-state-contract.py: FAIL: " + message)


# One server-rendered current-state owner. The renderer projects the existing
# navigation-service boolean into both the canonical class and aria-current;
# client JS must not derive current state from window.location.
require("current-menu-item" in navigation and "current-menu-ancestor" in navigation,
        "navigation service must keep WordPress current/ancestor state")
require("path_is_active" in navigation,
        "fallback navigation must keep its existing server-side route state")
require("is-active" in header and 'aria-current="page"' in header,
        "header must expose canonical server-rendered current state")

nav_js = core_js.split("function initNavBubble() {", 1)[1].split("/* -----------------------------------------------------------------", 1)[0]
require("window.location" not in nav_js,
        "desktop bubble must not add client-side URL matching")
require("function activeLink()" in nav_js and "classList.contains('is-active')" in nav_js,
        "desktop bubble must consume the existing server-rendered marker")
require("restToActive();" in nav_js,
        "desktop bubble must return to the current item after interaction")

# Canonical nav presentation owner: active is the exact existing hover-bubble
# visual language at rest (same accent surface + white foreground) on both
# desktop and mobile. No pale second skin or active-only geometry is allowed.
nav_css = production.split("/* Top-level desktop navigation:", 1)[0]
active_selector = ".gloskin-ui1-nav>.gloskin-ui1-nav__list>.gloskin-ui1-nav__item.is-active>.gloskin-ui1-nav__row>.gloskin-ui1-nav__link"
require(active_selector in nav_css,
        "shared server-rendered active selector missing")
active_rule = nav_css.split(active_selector + "{", 1)[1].split("}", 1)[0]
require("background:var(--gloskin-accent);" in active_rule and "color:#fff;" in active_rule,
        "active nav must match the existing hover bubble surface and foreground")
require("--gloskin-nav-active-surface" not in nav_css,
        "pale active-only surface token must not return")

bubble_css = production.split("/* Top-level desktop navigation:", 1)[1].split("/* Desktop dropdown submenu:", 1)[0]
require(".gloskin-ui1-nav__link.is-bubbled" in bubble_css,
        "existing desktop bubble link state missing")
require("background:var(--gloskin-accent);" in bubble_css,
        "existing moving bubble must keep canonical accent surface")
require("is-active" not in bubble_css,
        "desktop bubble owner must not carry a second active skin")
require("!important" not in nav_css + bubble_css,
        "navigation presentation must add no !important")

print("nav-active-state-contract.py: OK")
