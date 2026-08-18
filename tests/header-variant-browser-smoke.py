#!/usr/bin/env python3
"""Real-browser smoke for the single canonical prototype header."""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("header-variant-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "plugin/gloskin-site-core/assets"
CSS = "\n".join(
    (ASSETS / f"css/{name}").read_text(encoding="utf-8")
    for name in (
        "gloskin-ui1-fonts.css",
        "gloskin-ui1-core-base.css",
        "gloskin-ui1-core.css",
        "gloskin-ui1-production.css",
        "gloskin-ui1-prototype-refresh.css",
    )
)
JS_CORE = (ASSETS / "js/gloskin-ui1-core.js").read_text(encoding="utf-8")

NAV = (
    '<ul class="gloskin-ui1-nav__list">'
    '<li class="gloskin-ui1-nav__item"><div class="gloskin-ui1-nav__row"><a class="gloskin-ui1-nav__link" href="/treatments/">Perawatan</a></div></li>'
    '<li class="gloskin-ui1-nav__item"><div class="gloskin-ui1-nav__row"><a class="gloskin-ui1-nav__link" href="/promo/">Promo</a></div></li>'
    '<li class="gloskin-ui1-nav__item"><div class="gloskin-ui1-nav__row"><a class="gloskin-ui1-nav__link" href="/skincare/">Skincare</a></div></li>'
    '<li class="gloskin-ui1-nav__item"><div class="gloskin-ui1-nav__row"><a class="gloskin-ui1-nav__link" href="/about/">Tentang Gloskin</a></div></li>'
    "</ul>"
)

LOGO_SVG = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1600' height='520'/%3E"
BRAND = f'<img class="gloskin-ui1-brand__image" src="{LOGO_SVG}" width="200" height="65" alt="Gloskin">'
SEARCH = '<button class="gloskin-ui1-utility-btn" type="button" data-gloskin-search-open aria-expanded="false" aria-controls="gloskin-search-overlay" aria-label="Cari">Cari</button>'
ACCOUNT = '<a class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--account" href="/my-account/" aria-label="Akun saya">Akun</a>'
WISHLIST = '<button class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--wishlist" type="button" data-gloskin-wishlist-open aria-expanded="false" aria-controls="gloskin-wishlist-sheet"><span data-gloskin-wishlist-count>0</span><span class="screen-reader-text" data-gloskin-wishlist-count-sr>0 produk favorit</span></button>'
CART = '<button class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--cart" type="button" data-gloskin-cart-open aria-expanded="false" aria-controls="gloskin-cart-sheet"><span data-gloskin-cart-count>0</span><span class="screen-reader-text" data-gloskin-cart-count-sr>0 item di keranjang</span></button>'
DRAWER = '<button class="gloskin-ui1-drawer-toggle" type="button" data-gloskin-drawer-open aria-expanded="false" aria-controls="gloskin-mobile-drawer">Menu</button>'

HEADER = f"""
<header class="gloskin-ui1-header" data-gloskin-header="header-2">
  <div class="gloskin-ui1-container gloskin-ui1-header__inner">
    <a class="gloskin-ui1-brand" href="/">{BRAND}</a>
    <nav class="gloskin-ui1-nav gloskin-ui1-nav--desktop" aria-label="Navigasi utama"><span class="gloskin-ui1-nav__bubble" aria-hidden="true"></span>{NAV}</nav>
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--end">{SEARCH}{ACCOUNT}{WISHLIST}{CART}{DRAWER}</div>
  </div>
</header>
"""

OVERLAYS = f"""
<div class="gloskin-ui1-search-overlay" id="gloskin-search-overlay" data-gloskin-overlay="search" aria-hidden="true" hidden>
  <button class="gloskin-ui1-search-overlay__backdrop" data-gloskin-overlay-close></button>
  <div class="gloskin-ui1-search-overlay__canvas" role="dialog" aria-modal="true"><button data-gloskin-overlay-close>Tutup</button><input data-gloskin-search-input><div data-gloskin-search-results></div></div>
</div>
<div class="gloskin-ui1-sheet" id="gloskin-cart-sheet" data-gloskin-overlay="cart" aria-hidden="true" hidden>
  <button class="gloskin-ui1-sheet__backdrop" data-gloskin-overlay-close></button><div class="gloskin-ui1-sheet__panel" role="dialog" aria-modal="true"><button data-gloskin-overlay-close>Tutup</button></div>
</div>
<div class="gloskin-ui1-sheet" id="gloskin-wishlist-sheet" data-gloskin-overlay="wishlist" aria-hidden="true" hidden>
  <button class="gloskin-ui1-sheet__backdrop" data-gloskin-overlay-close></button><div class="gloskin-ui1-sheet__panel" role="dialog" aria-modal="true"><button data-gloskin-overlay-close>Tutup</button><div data-gloskin-wishlist-body></div></div>
</div>
<div class="gloskin-ui1-drawer" id="gloskin-mobile-drawer" data-gloskin-drawer aria-hidden="true" hidden>
  <button class="gloskin-ui1-drawer__backdrop" data-gloskin-drawer-close></button>
  <div class="gloskin-ui1-drawer__panel" role="dialog" aria-modal="true"><button data-gloskin-drawer-close>Tutup</button><nav class="gloskin-ui1-nav gloskin-ui1-nav--mobile">{NAV}</nav></div>
</div>
<script>window.gloskinData={{woo:true,restUrl:'/wp-json/gloskin/v1/',addToCartAjaxUrl:'',cartCtaLabel:'Keranjang'}};</script>
"""


def html(home=False):
    cls = "gloskin-ui1 gloskin-ui1--home" if home else "gloskin-ui1"
    return f'<!doctype html><html><head><meta charset="utf-8"></head><body class="{cls}">{HEADER}{OVERLAYS}<main id="gloskin-main"><div style="height:4000px">content</div></main></body></html>'


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def load(page, home=False):
    page.route("http://gloskin.test/**", lambda route: route.fulfill(status=200, content_type="text/html", body="<!doctype html>"))
    page.goto("http://gloskin.test/header-fixture", wait_until="domcontentloaded")
    page.set_content(html(home))
    page.add_style_tag(content=CSS)
    page.add_script_tag(content=JS_CORE)
    page.wait_for_timeout(80)


with sync_playwright() as p:
    chromium = Path("/usr/bin/chromium")
    try:
        browser = p.chromium.launch(headless=True, executable_path=str(chromium), args=["--no-sandbox"]) if chromium.exists() else p.chromium.launch(headless=True)
    except Exception:
        print("header-variant-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)

    page = browser.new_page(viewport={"width": 1440, "height": 900})
    load(page)
    require(page.locator('[data-gloskin-header="header-2"]').count() == 1, "one canonical header must render")
    require(page.locator('[data-gloskin-header="header-1"]').count() == 0, "legacy header-1 must not render")
    require(page.locator(".gloskin-ui1-header__nav-row").count() == 0, "legacy two-row header must be absent")
    labels = page.locator(".gloskin-ui1-nav--desktop .gloskin-ui1-nav__link").all_inner_texts()
    require(labels == ["Perawatan", "Promo", "Skincare", "Tentang Gloskin"], f"canonical primary nav order regressed: {labels}")
    require(page.locator("[data-gloskin-search-open]").count() == 1, "search utility must have one public owner")
    require(page.locator("[data-gloskin-wishlist-open]").count() == 1, "wishlist utility must have one public owner")
    require(page.locator("[data-gloskin-cart-open]").count() == 1, "cart utility must have one public owner")
    require(page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-header=\"header-2\"]')).position") == "sticky", "canonical header must remain sticky")
    require(page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), "canonical desktop header must not overflow")

    page.evaluate("localStorage.setItem('gloskin_wishlist', JSON.stringify([11,22]))")
    page.evaluate("document.dispatchEvent(new CustomEvent('gloskin:catalog-updated'))")
    page.wait_for_timeout(40)
    require(page.locator("[data-gloskin-wishlist-count]").inner_text() == "2", "wishlist badge must retain existing owner behavior")
    page.locator("[data-gloskin-search-open]").click()
    page.wait_for_function("document.querySelector('[data-gloskin-overlay=\"search\"]').getAttribute('aria-hidden') === 'false'")
    page.locator("[data-gloskin-overlay-close]").first.click()
    page.locator("[data-gloskin-cart-open]").click()
    page.wait_for_function("document.querySelector('[data-gloskin-overlay=\"cart\"]').getAttribute('aria-hidden') === 'false'")
    page.close()

    # Home uses the same canonical markup, only the existing glass state class differs.
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    load(page, home=True)
    require(page.locator('[data-gloskin-header="header-2"]').count() == 1, "Home must use the same canonical header")
    require(page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-header=\"header-2\"]')).position") == "fixed", "Home glass header must remain the existing fixed overlay")
    page.close()

    for width in (1024, 390):
        page = browser.new_page(viewport={"width": width, "height": 900})
        load(page)
        require(not page.locator(".gloskin-ui1-nav--desktop").is_visible(), f"desktop nav must collapse at {width}px")
        require(page.locator(".gloskin-ui1-drawer-toggle").is_visible(), f"drawer trigger must be visible at {width}px")
        require(page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), f"canonical header must not overflow at {width}px")
        page.locator("[data-gloskin-drawer-open]").click()
        page.wait_for_function("document.querySelector('#gloskin-mobile-drawer').getAttribute('aria-hidden') === 'false'")
        mobile_labels = page.locator("#gloskin-mobile-drawer .gloskin-ui1-nav__link").all_inner_texts()
        require(mobile_labels == ["Perawatan", "Promo", "Skincare", "Tentang Gloskin"], f"mobile nav tree/order regressed at {width}px: {mobile_labels}")
        page.close()

    browser.close()

print("header-variant-browser-smoke: OK (canonical prototype header)")
