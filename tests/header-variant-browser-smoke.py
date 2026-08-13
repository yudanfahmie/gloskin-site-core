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

# Real <img class="gloskin-ui1-brand__image"> node -- matching
# gloskin_ui1_render_brand_logo()'s actual output shape (1600x520 source,
# 200x65 intrinsic) -- required so compact-sticky logo *height* CSS
# transitions/measurements below are testing the real thing, not a text node.
LOGO_SVG = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1600' height='520'/%3E"
BRAND_IMG = f'<img class="gloskin-ui1-brand__image" src="{LOGO_SVG}" width="200" height="65" alt="Gloskin">'
COMPACT_BRAND_IMG = f'<img class="gloskin-ui1-brand__image gloskin-ui1-brand__image--compact" src="{LOGO_SVG}" width="200" height="65" alt="Gloskin">'

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
    <a class="gloskin-ui1-brand" href="#">{brand_img}</a>
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--end">
      {actions}
      <a class="gloskin-ui1-button gloskin-ui1-button--ghost gloskin-ui1-header__contact" href="#">Hubungi Kami</a>
      {drawer_toggle}
    </div>
  </div>
</header>
<div class="gloskin-ui1-header__nav-row">
  <div class="gloskin-ui1-container gloskin-ui1-header__nav-row-inner">
    <a class="gloskin-ui1-compact-brand" href="#" inert>{compact_brand_img}</a>
    <nav class="gloskin-ui1-nav gloskin-ui1-nav--desktop" aria-label="Navigasi utama"><span class="gloskin-ui1-nav__bubble" aria-hidden="true"></span>{nav}</nav>
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--compact" inert>{search}{actions}</div>
  </div>
</div>
""".format(search=SEARCH_BUTTON, actions=ACCOUNT_WISHLIST_CART, drawer_toggle=DRAWER_TOGGLE, nav=NAV, brand_img=BRAND_IMG, compact_brand_img=COMPACT_BRAND_IMG)

HEADER_2 = """
<header class="gloskin-ui1-header" data-gloskin-header="header-2">
  <div class="gloskin-ui1-container gloskin-ui1-header__inner">
    <a class="gloskin-ui1-brand" href="#">{brand_img}</a>
    <nav class="gloskin-ui1-nav gloskin-ui1-nav--desktop" aria-label="Navigasi utama"><span class="gloskin-ui1-nav__bubble" aria-hidden="true"></span>{nav}</nav>
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--end">
      {actions}
      {drawer_toggle}
    </div>
  </div>
</header>
""".format(actions=ACTIONS, drawer_toggle=DRAWER_TOGGLE, nav=NAV, brand_img=BRAND_IMG)


def page_html(header_markup):
    return """<!doctype html><html><head><meta charset="utf-8"></head><body class="gloskin-ui1">
<a class="gloskin-ui1-skip-link" href="#gloskin-main">Lewati ke konten utama</a>
{header}
{overlays}
<main id="gloskin-main"><div class="gloskin-ui1-container" style="padding-block:4000px">content</div></main>
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

    # --- Smart sticky parity: the SAME initSmartHeader()/initCompactSticky()
    # owner must drive both variants -- scroll-down hide, scroll-up reveal,
    # top-state reset, submenu/overlay hold, repeated cycles, and Header 2's
    # compact logo/row-height using the SAME logo node as Header 1's compact
    # dimensions (no clone). ---------------------------------------------
    def scroll_to(page, y):
        page.evaluate(f"window.scrollTo(0, {y})")
        page.wait_for_timeout(140)  # allow the rAF-scheduled scroll tick to settle.

    def scroll_gradually(page, target_y, step=4):
        # downDistance accumulates only from the delta of each individual
        # scroll tick, not the whole journey (showNav() keeps resetting it to
        # 0 on every tick while still under topGuard()) -- so, exactly like a
        # real user's wheel/trackpad scroll, small steps are required to
        # observe compact-sticky engage *before* accumulating enough
        # downward delta to also cross the separate hide threshold.
        current = page.evaluate("window.scrollY")
        while current < target_y:
            current = min(current + step, target_y)
            page.evaluate(f"window.scrollTo(0, {current})")
            page.wait_for_timeout(24)
        page.wait_for_timeout(140)

    # Header 1 baseline: nav-row hide/reveal/top-reset + compact logo size.
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    load(page, HEADER_1)
    nav_row = page.locator(".gloskin-ui1-header__nav-row")
    scroll_to(page, 2000)
    require("is-hidden" in (nav_row.get_attribute("class") or ""), "Header 1 nav row must hide after scrolling down past the threshold")
    require("is-compact-sticky" in (nav_row.get_attribute("class") or ""), "Header 1 must still be in compact-sticky state while scrolled down")
    scroll_to(page, 1800)
    require("is-hidden" not in (nav_row.get_attribute("class") or ""), "Header 1 nav row must reveal immediately on scroll-up")
    header1_compact_logo = page.evaluate("(() => { const img = document.querySelector('.gloskin-ui1-compact-brand img'); const r = img.getBoundingClientRect(); return {width: r.width, height: r.height}; })()")
    scroll_to(page, 0)
    require("is-compact-sticky" not in (nav_row.get_attribute("class") or ""), "Header 1 compact-sticky state must reset cleanly at the top")
    require("is-hidden" not in (nav_row.get_attribute("class") or ""), "Header 1 must be visible at the top")
    page.close()

    # Header 2: same state vocabulary, same owner, one row, no clone.
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    load(page, HEADER_2)
    split = page.locator('[data-gloskin-header="header-2"]')
    natural_row_height = page.evaluate("document.querySelector('[data-gloskin-header=\"header-2\"]').offsetHeight")

    scroll_gradually(page, natural_row_height + 8)
    require("is-compact-sticky" in (split.get_attribute("class") or ""), "Header 2 must enter compact-sticky state once scrolled past its own natural height")
    # Compact-sticky and hidden are deliberately independent, sequential
    # phases (compact first, hide only after a further, separate downward
    # threshold) -- matching the task's own ordering. Whether "is-hidden"
    # has also engaged by this exact point depends on fine-grained scroll
    # delta accounting this test does not try to pin to the pixel; the
    # dimension/geometry checks below hold regardless (transform, which
    # hidden uses, never changes an element's own offsetHeight/rect size).
    compact_row_height = page.evaluate("document.querySelector('[data-gloskin-header=\"header-2\"]').offsetHeight")
    require(compact_row_height < natural_row_height, f"Header 2 compact row must reduce its vertical footprint: natural={natural_row_height} compact={compact_row_height}")
    header2_compact_logo = page.evaluate("(() => { const img = document.querySelector('[data-gloskin-header=\"header-2\"] img.gloskin-ui1-brand__image'); const r = img.getBoundingClientRect(); return {width: r.width, height: r.height}; })()")
    require(abs(header2_compact_logo["height"] - header1_compact_logo["height"]) <= 1, f"Header 2 compact logo height must match Header 1's compact logo height at the same viewport: header1={header1_compact_logo} header2={header2_compact_logo}")
    require(header2_compact_logo["width"] > 0 and header1_compact_logo["width"] > 0, "compact logos must keep natural aspect-ratio width (no hard-coded width)")

    scroll_to(page, 2000)
    require("is-hidden" in (split.get_attribute("class") or ""), "Header 2 must hide after scrolling down past the hide threshold, same as Header 1")
    hidden_transform = page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-header=\"header-2\"]')).transform")
    require(hidden_transform not in ("none", ""), f"Header 2 hidden state must apply a real transform: {hidden_transform}")

    scroll_to(page, 1800)
    require("is-hidden" not in (split.get_attribute("class") or ""), "Header 2 must reveal immediately on scroll-up, same as Header 1")
    visible_transform = page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-header=\"header-2\"]')).transform")
    require(visible_transform in ("none", "matrix(1, 0, 0, 1, 0, 0)"), f"Header 2 visible state must not carry a stale hide transform: {visible_transform}")

    # Repeated down/up/down/up cycles: no stale state.
    for cycle_y in (2100, 1700, 2200, 1600):
        scroll_to(page, cycle_y)
    require("is-hidden" not in (split.get_attribute("class") or ""), "after a down/up/down/up cycle ending on scroll-up, Header 2 must end visible, not stuck hidden")

    scroll_to(page, 0)
    require("is-compact-sticky" not in (split.get_attribute("class") or ""), "Header 2 compact-sticky state must reset cleanly at the top")
    require("is-hidden" not in (split.get_attribute("class") or ""), "Header 2 must be visible at the top")

    # Submenu/overlay/focus interaction must keep Header 2 visible even past
    # the hide threshold -- the same interactionActive() guard Header 1 uses.
    scroll_to(page, 2000)
    require("is-hidden" in (split.get_attribute("class") or ""), "precondition: Header 2 must be hidden before the interaction-hold check")
    page.evaluate("document.dispatchEvent(new CustomEvent('gloskin:sticky-nav-hold'))")
    page.wait_for_timeout(60)
    require("is-hidden" not in (split.get_attribute("class") or ""), "an active interaction (submenu/overlay open) must keep Header 2 visible, same guard as Header 1")

    # Nav hover bubble stays owned solely by initNavBubble(); Header 2's grid
    # containing block must not break its positioning/entrance.
    scroll_to(page, 0)
    bubble = page.locator(".gloskin-ui1-nav__bubble")
    page.locator(".gloskin-ui1-nav--desktop .gloskin-ui1-nav__link", has_text="Perawatan").hover()
    page.wait_for_timeout(80)
    require("is-visible" in (bubble.get_attribute("class") or ""), "hovering a Header 2 nav link must still show the one existing nav bubble")
    require(page.evaluate("document.querySelectorAll('.gloskin-ui1-nav__bubble').length") == 1, "Header 2 must not introduce a second nav bubble/hover controller")
    page.close()

    # --- Admin bar offset: same --gloskin-ui1-nav-sticky-top ownership for
    # both variants, logged-in (admin bar present) state. ------------------
    for header_markup, selector in ((HEADER_1, ".gloskin-ui1-header__nav-row"), (HEADER_2, '[data-gloskin-header="header-2"]')):
        page = browser.new_page(viewport={"width": 1440, "height": 900})
        load(page, header_markup)
        page.evaluate("document.body.classList.add('admin-bar')")
        sticky_top = page.evaluate(f"getComputedStyle(document.querySelector('{selector}')).top")
        require(sticky_top == "32px", f"{selector} must track the 32px admin-bar sticky-top offset when logged in: {sticky_top}")
        page.close()

    browser.close()

print("header-variant-browser-smoke: OK")
