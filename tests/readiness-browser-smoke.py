#!/usr/bin/env python3
import os
import shutil
import subprocess
from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
BASE_CSS = ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css"
CORE_CSS = ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css"
READINESS_CSS = ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-readiness.css"
PRODUCTION_CSS = ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-production.css"
JS = ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js"
SHELL_FIXTURE = ROOT / "tests/rendered-shell-auth-smoke.php"

def check(cond, message):
    if not cond:
        raise AssertionError(message)

def render_shell(logged_in=False):
    env = os.environ.copy()
    env["GL_TEST_EMIT_HTML"] = "1"
    if logged_in:
        env["GL_TEST_LOGGED_IN"] = "1"
    else:
        env.pop("GL_TEST_LOGGED_IN", None)
    return subprocess.check_output(
        ["php", str(SHELL_FIXTURE)],
        cwd=str(ROOT),
        env=env,
        text=True,
    )

def install_page(page, html):
    page.set_content(html, wait_until="domcontentloaded")
    for stylesheet in (BASE_CSS, CORE_CSS, READINESS_CSS, PRODUCTION_CSS):
        page.add_style_tag(path=str(stylesheet))
    page.evaluate("""() => {
        window.__fetchCount = 0;
        window.fetch = () => {
            window.__fetchCount++;
            return Promise.resolve({ok: true, json: () => Promise.resolve({groups: []})});
        };
    }""")
    page.add_script_tag(path=str(JS))
    page.wait_for_timeout(40)

def no_horizontal_overflow(page, width):
    scroll_width = page.evaluate("document.documentElement.scrollWidth")
    check(scroll_width <= width + 1, f"horizontal overflow at {width}: {scroll_width}")

executable = (
    os.environ.get("CHROMIUM_PATH")
    or shutil.which("chromium")
    or shutil.which("chromium-browser")
    or shutil.which("google-chrome")
)
check(executable, "Chromium executable not found")

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=executable, headless=True, args=["--no-sandbox"])
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    errors = []
    page.on("pageerror", lambda error: errors.append(f"pageerror: {error}"))
    page.on("console", lambda msg: errors.append(f"console: {msg.text}") if msg.type == "error" else None)

    # Actual shell render: Woo active + logged out + normal Gloskin page.
    install_page(page, render_shell(False))
    check(page.locator("#gloskin-auth-overlay").count() == 1, "actual shell must render exactly one auth overlay")
    check(page.locator('input[name="woocommerce-login-nonce"]').count() == 1, "login nonce duplicated/missing")
    check(page.locator('input[name="woocommerce-register-nonce"]').count() == 1, "register nonce duplicated/missing")

    account = page.locator(".gloskin-ui1-header__zone--end .gloskin-ui1-utility-btn--account")
    before_url = page.url
    account.click()
    page.wait_for_timeout(80)
    check(page.url == before_url, f"Account navigated before modal open: {before_url} -> {page.url}")
    check(page.get_attribute("#gloskin-auth-overlay", "aria-hidden") == "false", "actual Account click did not open auth modal")
    check(page.locator("#username").evaluate("e => document.activeElement === e"), "auth username focus failed")
    page.keyboard.press("Escape")
    page.wait_for_timeout(340)
    check(account.evaluate("e => document.activeElement === e"), "auth Escape did not return focus to Account")

    # Fresh Search still owns zero geometry and no state space until a query exists.
    search_open = page.locator(".gloskin-ui1-header__zone--start [data-gloskin-search-open]")
    search_open.click()
    page.wait_for_timeout(80)
    search_style = page.eval_on_selector(
        "[data-gloskin-search-results]",
        "e => ({margin:getComputedStyle(e).marginTop,min:getComputedStyle(e).minHeight,h:e.getBoundingClientRect().height})",
    )
    check(search_style["margin"] == "0px" and search_style["min"] == "0px" and search_style["h"] == 0, f"search ghost geometry: {search_style}")
    page.fill("[data-gloskin-search-input]", "zz")
    page.wait_for_timeout(320)
    page.wait_for_selector(".gloskin-ui1-empty-state--search")
    check("Tidak menemukan hasil yang sesuai" in page.locator("[data-gloskin-search-results]").inner_text(), "search zero state missing")
    page.keyboard.press("Escape")
    page.wait_for_timeout(340)

    # Desktop top-level nav: the liquid bubble owns white foreground, parent
    # chevrons stay naked, and nested submenu tracks stretch to the wrapper.
    top_links = page.locator(".gloskin-ui1-nav--desktop > .gloskin-ui1-nav__list > .gloskin-ui1-nav__item > .gloskin-ui1-nav__row > .gloskin-ui1-nav__link")
    check(top_links.count() >= 2, "desktop top-level navigation missing")
    inverse = page.evaluate("""() => {
        const probe = document.createElement('span');
        probe.style.color = 'var(--gloskin-inverse)';
        document.body.appendChild(probe);
        const value = getComputedStyle(probe).color;
        probe.remove();
        return value;
    }""")
    active = page.locator(".gloskin-ui1-nav--desktop > .gloskin-ui1-nav__list > .gloskin-ui1-nav__item.is-active > .gloskin-ui1-nav__row > .gloskin-ui1-nav__link").first
    hover = page.locator(".gloskin-ui1-nav--desktop > .gloskin-ui1-nav__list > .gloskin-ui1-nav__item:not(.is-active) > .gloskin-ui1-nav__row > .gloskin-ui1-nav__link").first
    bubble = page.locator(".gloskin-ui1-nav--desktop > .gloskin-ui1-nav__bubble")
    check(active.count() == 1 and bubble.count() == 1, "active nav/bubble fixture missing")
    check(active.evaluate("e => e.classList.contains('is-bubbled')"), "active desktop nav did not receive bubble state")
    check(active.evaluate("e => getComputedStyle(e).color") == inverse, "active bubbled nav text is not white")
    hover.hover()
    page.wait_for_timeout(220)
    check(hover.evaluate("e => e.classList.contains('is-bubbled')"), "hover desktop nav did not receive bubble state")
    check(hover.evaluate("e => getComputedStyle(e).color") == inverse, "hover bubbled nav text is not white")
    check(bubble.evaluate("e => getComputedStyle(e).opacity") == "1", "desktop nav bubble did not become visible")
    hover.focus()
    focus_style = hover.evaluate("e => ({style:getComputedStyle(e).outlineStyle,width:getComputedStyle(e).outlineWidth})")
    check(focus_style["style"] != "none" and float(focus_style["width"].replace("px", "")) >= 3, f"desktop nav focus-visible weakened: {focus_style}")

    parent_toggle = page.locator(".gloskin-ui1-nav--desktop > .gloskin-ui1-nav__list > .gloskin-ui1-nav__item > .gloskin-ui1-nav__row > .gloskin-ui1-nav__toggle").first
    check(parent_toggle.count() == 1, "desktop parent-menu chevron missing")
    parent_toggle.hover()
    toggle_bg = parent_toggle.evaluate("e => getComputedStyle(e).backgroundColor")
    check(toggle_bg in ("rgba(0, 0, 0, 0)", "transparent"), f"desktop chevron hover still has a background: {toggle_bg}")
    parent_toggle.click()
    submenu_id = parent_toggle.get_attribute("aria-controls")
    submenu = page.locator(f"#{submenu_id}")
    check(submenu.count() == 1 and submenu.is_visible(), "desktop submenu did not open")
    sublist = submenu.locator(":scope > .gloskin-ui1-nav__list")
    submenu_link = sublist.locator(":scope > .gloskin-ui1-nav__item > .gloskin-ui1-nav__row > .gloskin-ui1-nav__link, :scope > .gloskin-ui1-nav__item > .gloskin-ui1-nav__link").first
    sublist_style = sublist.evaluate("e => ({justify:getComputedStyle(e).justifyContent,align:getComputedStyle(e).alignItems})")
    check(sublist_style["justify"] == "stretch" and sublist_style["align"] == "stretch", f"submenu inherited centered wrapper alignment: {sublist_style}")
    sublist_box = sublist.bounding_box()
    submenu_link_box = submenu_link.bounding_box()
    check(sublist_box and submenu_link_box, "submenu geometry missing")
    check(abs(submenu_link_box["x"] - sublist_box["x"]) <= 1 and abs(submenu_link_box["width"] - sublist_box["width"]) <= 1, f"submenu link did not stretch to wrapper: {sublist_box} vs {submenu_link_box}")
    submenu_link.hover()
    check(not submenu_link.evaluate("e => e.classList.contains('is-bubbled')"), "submenu link incorrectly received top-level bubble state")

    # Header remains truly centered and comfortably spaced without overflow.
    for width in (1100, 1440, 1920, 2560):
        page.set_viewport_size({"width": width, "height": 900})
        page.wait_for_timeout(30)
        inner = page.locator(".gloskin-ui1-header__nav-row-inner").bounding_box()
        nav = page.locator(".gloskin-ui1-nav--desktop").bounding_box()
        check(inner and nav, f"desktop nav geometry missing at {width}")
        inner_center = inner["x"] + inner["width"] / 2
        nav_center = nav["x"] + nav["width"] / 2
        check(abs(inner_center - nav_center) <= 1.5, f"desktop nav lost true centering at {width}: {inner_center} vs {nav_center}")
        no_horizontal_overflow(page, width)
    page.set_viewport_size({"width": 1100, "height": 900})
    check(page.eval_on_selector(".gloskin-ui1-nav--desktop > .gloskin-ui1-nav__list", "e => getComputedStyle(e).gap") == "0px", "1041-1279 nav spacing is not compact")
    page.set_viewport_size({"width": 1440, "height": 900})
    check(page.eval_on_selector(".gloskin-ui1-nav--desktop > .gloskin-ui1-nav__list", "e => getComputedStyle(e).gap") == "5px", "comfortable desktop nav spacing missing")
    page.set_viewport_size({"width": 1024, "height": 900})
    check(page.eval_on_selector(".gloskin-ui1-header__nav-row", "e => getComputedStyle(e).display") == "none", "1024 mobile navigation switch regressed")

    # Footer stays responsive and grouped at required widths.
    for width in (390, 600, 601, 782, 1024, 1440, 1920):
        page.set_viewport_size({"width": width, "height": 900})
        page.wait_for_timeout(20)
        footer = page.locator(".gloskin-ui1-footer").bounding_box()
        check(footer and footer["width"] <= width + 1, f"footer overflow at {width}: {footer}")
        no_horizontal_overflow(page, width)
    page.set_viewport_size({"width": 1440, "height": 900})
    separator = page.locator(".gloskin-ui1-footer__grid > div:not(.gloskin-ui1-footer__brand)").first
    check(separator.evaluate("e => getComputedStyle(e).borderLeftWidth") == "1px", "desktop footer grouping separator missing")
    brand_line = page.locator(".gloskin-ui1-footer__brand")
    check(brand_line.evaluate("e => getComputedStyle(e,'::after').width") == "72px", "footer brand line treatment missing")

    # Logged-in fixed Cart/Wishlist sheets follow the existing toolbar variable.
    install_page(page, render_shell(True))
    check(page.locator("#gloskin-auth-overlay").count() == 0, "logged-in shell must not render auth overlay")
    logged_account = page.locator(".gloskin-ui1-header__zone--end .gloskin-ui1-utility-btn--account")
    check(logged_account.get_attribute("href") == "https://example.test/my-account/", "logged-in Account canonical href changed")
    check(logged_account.get_attribute("data-gloskin-auth-open") is None, "logged-in Account was enhanced as quick auth")

    for width in (390, 600, 601, 782, 1024, 1440, 1920):
        page.set_viewport_size({"width": width, "height": 900})
        page.wait_for_timeout(25)
        expected = 0 if width <= 600 else (46 if width <= 782 else 32)

        page.locator(".gloskin-ui1-header__zone--end [data-gloskin-cart-open]").click(force=True)
        page.wait_for_timeout(35)
        cart_box = page.locator("#gloskin-cart-sheet").bounding_box()
        check(cart_box and abs(cart_box["y"] - expected) <= 0.6, f"cart admin-bar top at {width}: {cart_box} expected {expected}")
        check(abs((cart_box["y"] + cart_box["height"]) - 900) <= 1, f"cart bottom gap at {width}: {cart_box}")
        page.keyboard.press("Escape")
        page.wait_for_timeout(340)

        if width <= 1040:
            page.locator("[data-gloskin-drawer-open]").click()
            page.locator("[data-gloskin-wishlist-open-from-drawer]").click()
            page.wait_for_timeout(120)
        else:
            page.locator(".gloskin-ui1-header__zone--end [data-gloskin-wishlist-open]").click()
            page.wait_for_timeout(35)
        wishlist_box = page.locator("#gloskin-wishlist-sheet").bounding_box()
        check(wishlist_box and abs(wishlist_box["y"] - expected) <= 0.6, f"wishlist admin-bar top at {width}: {wishlist_box} expected {expected}")
        check(abs((wishlist_box["y"] + wishlist_box["height"]) - 900) <= 1, f"wishlist bottom gap at {width}: {wishlist_box}")
        page.keyboard.press("Escape")
        page.wait_for_timeout(340)
        no_horizontal_overflow(page, width)

    # Reduced motion remains honored by the unified overlay controller.
    install_page(page, render_shell(False))
    page.emulate_media(reduced_motion="reduce")
    page.set_viewport_size({"width": 1440, "height": 900})
    page.locator(".gloskin-ui1-header__zone--end .gloskin-ui1-utility-btn--account").click()
    page.wait_for_timeout(20)
    duration = page.eval_on_selector("#gloskin-auth-overlay", "e => getComputedStyle(e).transitionDuration")
    check(duration == "0s", f"reduced-motion auth transition still active: {duration}")
    page.keyboard.press("Escape")

    check(not errors, "browser console/page errors: " + " | ".join(errors))
    browser.close()

print("readiness-browser-smoke: OK")
