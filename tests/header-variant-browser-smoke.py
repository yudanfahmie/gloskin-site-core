#!/usr/bin/env python3
"""Real-browser smoke for the Header Type 1/2 variant system: one canonical
DOM owner, zero duplicate nav/actions, existing overlay/wishlist/cart JS
reused unmodified, and the existing compact/mobile fallback at narrow
widths for both variants."""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("header-variant-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "plugin/gloskin-site-core/assets"
CSS_FONTS = (ASSETS / "css/gloskin-ui1-fonts.css").read_text(encoding="utf-8")
CSS_BASE = (ASSETS / "css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CSS_CORE = (ASSETS / "css/gloskin-ui1-core.css").read_text(encoding="utf-8")
CSS_PRODUCTION = (ASSETS / "css/gloskin-ui1-production.css").read_text(encoding="utf-8")
JS_CORE = (ASSETS / "js/gloskin-ui1-core.js").read_text(encoding="utf-8")

NAV = (
    '<ul class="gloskin-ui1-nav__list">'
    '<li class="gloskin-ui1-nav__item"><div class="gloskin-ui1-nav__row"><a class="gloskin-ui1-nav__link" href="#">Tentang Gloskin</a></div></li>'
    '<li class="gloskin-ui1-nav__item"><div class="gloskin-ui1-nav__row"><a class="gloskin-ui1-nav__link" href="#">Perawatan</a></div></li>'
    '<li class="gloskin-ui1-nav__item is-active"><div class="gloskin-ui1-nav__row"><a class="gloskin-ui1-nav__link" href="#" aria-current="page">Belanja</a></div></li>'
    '<li class="gloskin-ui1-nav__item"><div class="gloskin-ui1-nav__row"><a class="gloskin-ui1-nav__link" href="#">Klinik</a></div></li>'
    "</ul>"
)

ACCOUNT_WISHLIST_CART = """
<a class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--account" href="#" aria-label="Akun saya"><svg width="20" height="20" viewBox="0 0 20 20"></svg></a>
<button class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--wishlist" type="button" data-gloskin-wishlist-open aria-expanded="false" aria-controls="gloskin-wishlist-sheet" aria-label="Produk favorit"><svg width="20" height="20" viewBox="0 0 20 20"></svg><span class="gloskin-ui1-badge" data-gloskin-wishlist-count aria-hidden="true">0</span><span class="screen-reader-text" data-gloskin-wishlist-count-sr aria-live="polite">0 produk favorit</span></button>
<button class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--cart" type="button" data-gloskin-cart-open aria-expanded="false" aria-controls="gloskin-cart-sheet" aria-label="Keranjang"><svg width="20" height="20" viewBox="0 0 20 20"></svg><span class="gloskin-ui1-badge" data-gloskin-cart-count aria-hidden="true">0</span></button>
"""

SEARCH_BUTTON = '<button class="gloskin-ui1-utility-btn" type="button" data-gloskin-search-open aria-expanded="false" aria-controls="gloskin-search-overlay" aria-label="Cari"><svg width="20" height="20" viewBox="0 0 20 20"></svg></button>'

# Header 2's actions zone: search joins account/wishlist/cart (all right).
ACTIONS = SEARCH_BUTTON + ACCOUNT_WISHLIST_CART

DRAWER_TOGGLE = """
<button class="gloskin-ui1-drawer-toggle" type="button" data-gloskin-drawer-open aria-expanded="false" aria-controls="gloskin-mobile-drawer">
  <span class="screen-reader-text">Buka navigasi</span>
  <svg width="24" height="24" viewBox="0 0 22 22">
    <circle class="gloskin-ui1-drawer-toggle__dot" cx="6" cy="7" r="1.7"></circle>
  </svg>
</button>
"""

SHARED_OVERLAYS = """
<div class="gloskin-ui1-search-overlay" id="gloskin-search-overlay" data-gloskin-overlay="search" aria-hidden="true" hidden>
  <button class="gloskin-ui1-search-overlay__backdrop" data-gloskin-overlay-close></button>
  <div class="gloskin-ui1-search-overlay__canvas" role="dialog" aria-modal="true"><button data-gloskin-overlay-close>Tutup</button><input data-gloskin-search-input><div data-gloskin-search-results></div></div>
</div>
<div class="gloskin-ui1-sheet" id="gloskin-cart-sheet" data-gloskin-overlay="cart" aria-hidden="true" hidden>
  <button class="gloskin-ui1-sheet__backdrop" type="button" data-gloskin-overlay-close></button>
  <div class="gloskin-ui1-sheet__panel" role="dialog" aria-modal="true"><button data-gloskin-overlay-close>Tutup</button></div>
</div>
<div class="gloskin-ui1-sheet" id="gloskin-wishlist-sheet" data-gloskin-overlay="wishlist" aria-hidden="true" hidden>
  <button class="gloskin-ui1-sheet__backdrop" type="button" data-gloskin-overlay-close></button>
  <div class="gloskin-ui1-sheet__panel" role="dialog" aria-modal="true"><button data-gloskin-overlay-close>Tutup</button><div data-gloskin-wishlist-body></div></div>
</div>
<div class="gloskin-ui1-drawer" id="gloskin-mobile-drawer" data-gloskin-drawer aria-hidden="true" hidden>
  <button class="gloskin-ui1-drawer__backdrop" type="button" data-gloskin-drawer-close></button>
  <div class="gloskin-ui1-drawer__panel" role="dialog" aria-modal="true">
    <div class="gloskin-ui1-drawer__head"><button class="gloskin-ui1-drawer__close" type="button" data-gloskin-drawer-close>Tutup</button></div>
    <nav class="gloskin-ui1-nav gloskin-ui1-nav--mobile">{nav}</nav>
  </div>
</div>
<script>
window.gloskinData = {{ woo:true, restUrl:'/wp-json/gloskin/v1/', addToCartAjaxUrl:'' }};
</script>
""".format(nav=NAV)

HEADER_1 = """
<header class="gloskin-ui1-header" data-gloskin-header="header-1">
  <div class="gloskin-ui1-container gloskin-ui1-header__inner">
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--start">
      {search}
    </div>
    <a class="gloskin-ui1-brand" href="#">Gloskin</a>
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--end">
      {actions}
      <a class="gloskin-ui1-button gloskin-ui1-button--ghost gloskin-ui1-header__contact" href="#">Hubungi Kami</a>
      {drawer_toggle}
    </div>
  </div>
</header>
<div class="gloskin-ui1-header__nav-row">
  <div class="gloskin-ui1-container gloskin-ui1-header__nav-row-inner">
    <a class="gloskin-ui1-compact-brand" href="#" inert>Gloskin</a>
    <nav class="gloskin-ui1-nav gloskin-ui1-nav--desktop" aria-label="Navigasi utama"><span class="gloskin-ui1-nav__bubble" aria-hidden="true"></span>{nav}</nav>
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--compact" inert>{search}{actions}</div>
  </div>
</div>
""".format(search=SEARCH_BUTTON, actions=ACCOUNT_WISHLIST_CART, drawer_toggle=DRAWER_TOGGLE, nav=NAV)

HEADER_2 = """
<header class="gloskin-ui1-header" data-gloskin-header="header-2">
  <div class="gloskin-ui1-container gloskin-ui1-header__inner">
    <a class="gloskin-ui1-brand" href="#">Gloskin</a>
    <nav class="gloskin-ui1-nav gloskin-ui1-nav--desktop" aria-label="Navigasi utama"><span class="gloskin-ui1-nav__bubble" aria-hidden="true"></span>{nav}</nav>
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--end">
      {actions}
      {drawer_toggle}
    </div>
  </div>
</header>
""".format(actions=ACTIONS, drawer_toggle=DRAWER_TOGGLE, nav=NAV)


def page_html(header_markup):
    return """<!doctype html><html><head><meta charset="utf-8"></head><body class="gloskin-ui1">
<a class="gloskin-ui1-skip-link" href="#gloskin-main">Lewati ke konten utama</a>
{header}
{overlays}
<main id="gloskin-main"><div class="gloskin-ui1-container" style="padding-block:1200px">content</div></main>
</body></html>""".format(header=header_markup, overlays=SHARED_OVERLAYS)


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def load(page, header_markup):
    # A real origin (not about:blank) is required for localStorage access,
    # matching the existing pattern in tests/micro-interactions-browser-smoke.py.
    page.route("http://gloskin.test/**", lambda route: route.fulfill(status=200, content_type="text/html", body="<!doctype html>"))
    page.goto("http://gloskin.test/header-fixture", wait_until="domcontentloaded")
    page.set_content(page_html(header_markup))
    page.add_style_tag(content=CSS_FONTS + "\n" + CSS_BASE + "\n" + CSS_CORE + "\n" + CSS_PRODUCTION)
    page.add_script_tag(content=JS_CORE)
    page.wait_for_timeout(60)


with sync_playwright() as p:
    chromium = Path("/usr/bin/chromium")
    try:
        if chromium.exists():
            browser = p.chromium.launch(headless=True, executable_path=str(chromium), args=["--no-sandbox"])
        else:
            browser = p.chromium.launch(headless=True)
    except Exception:
        print("header-variant-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)

    # --- Header Type 1: default, regression-safe -------------------------
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    load(page, HEADER_1)
    require(page.locator('[data-gloskin-header="header-1"]').count() == 1, "Header 1 must be the one canonical header owner")
    require(page.locator('[data-gloskin-header="header-2"]').count() == 0, "Header 1 page must not also render Header 2")
    require(page.locator(".gloskin-ui1-header__nav-row").count() == 1, "Header 1 must keep its existing sticky nav row")
    require(page.locator(".gloskin-ui1-nav--desktop").count() == 1, "exactly one desktop nav tree (no duplicate nav)")
    # Header 1's own existing, pre-existing design: the top row's utilities
    # and the sticky nav row's compact-mode duplicate are both always in the
    # DOM (collapsed to zero footprint + inert until scroll reveals them --
    # see initCompactSticky()). That is the current production behavior
    # this task must not change, so 2 is the correct count here, not 1.
    require(page.locator("[data-gloskin-wishlist-open]").count() == 2, "Header 1's existing top+compact wishlist controls must be unchanged")
    require(page.locator("[data-gloskin-cart-open]").count() == 2, "Header 1's existing top+compact cart controls must be unchanged")
    require(page.locator("[data-gloskin-search-open]").count() == 2, "Header 1's existing top+compact search controls must be unchanged")
    ids = page.eval_on_selector_all("[id]", "els => els.map(e => e.id)")
    require(len(ids) == len(set(ids)), f"no duplicate IDs on the page: {ids}")
    require(page.evaluate("getComputedStyle(document.querySelector('.gloskin-ui1-header__nav-row')).position") == "sticky", "Header 1's nav row remains the one sticky owner")
    require(page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), "Header 1 must not horizontally overflow at 1440")
    # Wishlist/cart badges still driven by the one existing updateBadges()/fragment owner.
    page.evaluate("localStorage.setItem('gloskin_wishlist', JSON.stringify([1,2]))")
    page.evaluate("document.dispatchEvent(new CustomEvent('gloskin:catalog-updated'))")
    require(page.locator("[data-gloskin-wishlist-count]").first.inner_text() == "2", "Header 1 wishlist badge must reflect existing localStorage owner")
    page.locator("[data-gloskin-search-open]").first.click()
    page.wait_for_function("document.querySelector('[data-gloskin-overlay=\"search\"]').getAttribute('aria-hidden') === 'false'")
    page.close()

    # Header 1 at existing compact/tablet + mobile breakpoints.
    for width in (1024, 390):
        page = browser.new_page(viewport={"width": width, "height": 900})
        load(page, HEADER_1)
        require(not page.locator(".gloskin-ui1-header__nav-row").is_visible(), f"Header 1 must fall back to the existing compact/mobile architecture at {width}px")
        require(page.locator(".gloskin-ui1-drawer-toggle").first.is_visible(), f"Header 1 drawer toggle must be reachable at {width}px")
        require(page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), f"Header 1 must not horizontally overflow at {width}px")
        page.locator("[data-gloskin-drawer-open]").first.click()
        page.wait_for_function("document.querySelector('#gloskin-mobile-drawer').getAttribute('aria-hidden') === 'false'")
        page.close()

    # --- Header Type 2: single-row logo/nav/actions -----------------------
    for width in (1440, 1280):
        page = browser.new_page(viewport={"width": width, "height": 900})
        load(page, HEADER_2)
        require(page.locator('[data-gloskin-header="header-2"]').count() == 1, "Header 2 must be the one canonical header owner")
        require(page.locator('[data-gloskin-header="header-1"]').count() == 0, "Header 2 page must not also render Header 1")
        require(page.locator(".gloskin-ui1-header__nav-row").count() == 0, f"Header 2 must not render a second sticky row at {width}")
        require(page.locator(".gloskin-ui1-nav--desktop").count() == 1, f"exactly one desktop nav tree at {width} (no duplicate nav)")
        require(page.locator("[data-gloskin-wishlist-open]").count() == 1, f"no duplicate wishlist action at {width}")
        require(page.locator("[data-gloskin-cart-open]").count() == 1, f"no duplicate cart action at {width}")
        require(page.locator("[data-gloskin-search-open]").count() == 1, f"no duplicate search action at {width}")
        require(page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-header=\"header-2\"]')).position") == "sticky", f"Header 2 itself must be the one sticky owner at {width}")
        require(page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), f"Header 2 must not horizontally overflow at {width}")
        logo_box = page.locator(".gloskin-ui1-brand").bounding_box()
        nav_box = page.locator(".gloskin-ui1-nav--desktop").bounding_box()
        actions_box = page.locator(".gloskin-ui1-header__zone--end").bounding_box()
        require(logo_box["x"] < nav_box["x"] < actions_box["x"], f"composition must read logo -> nav -> actions left to right at {width}: {logo_box} {nav_box} {actions_box}")
        require(page.locator(".gloskin-ui1-header__zone--start").count() == 0, "Header 2 must not carry Header 1's separate search-only start zone")
        one_row_bottom = page.locator('[data-gloskin-header="header-2"]').bounding_box()["height"]
        require(one_row_bottom < 100, f"Header 2 must stay one row (no duplicate header height): {one_row_bottom}")
        page.evaluate("localStorage.setItem('gloskin_wishlist', JSON.stringify([1]))")
        page.evaluate("document.dispatchEvent(new CustomEvent('gloskin:catalog-updated'))")
        require(page.locator("[data-gloskin-wishlist-count]").first.inner_text() == "1", f"Header 2 wishlist badge must reflect existing localStorage owner at {width}")
        page.locator("[data-gloskin-cart-open]").first.click()
        page.wait_for_function("document.querySelector('[data-gloskin-overlay=\"cart\"]').getAttribute('aria-hidden') === 'false'")
        page.close()

    # Header 2 at existing compact/tablet + mobile breakpoints -- must reuse
    # the exact same fallback as Header 1, not a second mobile system.
    for width in (1024, 390):
        page = browser.new_page(viewport={"width": width, "height": 900})
        load(page, HEADER_2)
        require(not page.locator(".gloskin-ui1-nav--desktop").is_visible(), f"Header 2 must hide desktop nav at {width}px, reusing the existing compact/mobile breakpoint")
        require(page.locator(".gloskin-ui1-drawer-toggle").first.is_visible(), f"Header 2 drawer toggle must be reachable at {width}px")
        require(page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), f"Header 2 must not horizontally overflow at {width}px")
        page.locator("[data-gloskin-drawer-open]").first.click()
        page.wait_for_function("document.querySelector('#gloskin-mobile-drawer').getAttribute('aria-hidden') === 'false'")
        page.close()

    browser.close()

print("header-variant-browser-smoke: OK")
