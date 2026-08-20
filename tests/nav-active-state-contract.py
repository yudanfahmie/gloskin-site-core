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

# Canonical nav presentation owner: one scoped active surface shared by desktop
# and mobile, with the existing desktop hover bubble allowed through on
# active+hover/focus. No dimensions are changed, so state changes cannot shift
# layout. The global focus-visible and reduced-motion owners remain untouched.
nav_css = production.split("/* Top-level desktop navigation:", 1)[0]
for token in (
    "--gloskin-nav-active-surface:",
    "--gloskin-nav-active-surface-hover:",
    ".gloskin-ui1-nav>.gloskin-ui1-nav__list>.gloskin-ui1-nav__item.is-active",
    "background:var(--gloskin-nav-active-surface);",
    "background:var(--gloskin-nav-active-surface-hover);",
):
    require(token in nav_css, f"shared current-nav treatment missing: {token}")

bubble_css = production.split("/* Top-level desktop navigation:", 1)[1].split("/* Desktop dropdown submenu:", 1)[0]
require(".gloskin-ui1-nav__link.is-bubbled" in bubble_css,
        "existing desktop bubble link state missing")
require(".gloskin-ui1-nav__row:hover>.gloskin-ui1-nav__link.is-bubbled" in bubble_css,
        "active+hover must reveal the existing stronger bubble")
require(".gloskin-ui1-nav__row:focus-within>.gloskin-ui1-nav__link.is-bubbled" in bubble_css,
        "active+focus must reveal the existing stronger bubble")
require("background:transparent;" in bubble_css and "color:#fff;" in bubble_css,
        "interactive active state must reveal full hover bubble contrast")
require("!important" not in nav_css + bubble_css,
        "navigation presentation must add no !important")

print("nav-active-state-contract.py: OK")
