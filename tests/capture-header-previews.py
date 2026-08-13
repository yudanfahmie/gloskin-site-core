#!/usr/bin/env python3
"""
One-time local asset-generation utility (NOT part of the CI-style contract
suite run by tests/check-runtime.sh): renders the two real Gloskin header
variants with their actual compiled CSS/JS in local Playwright/Chromium,
crops each to its header region, and writes the static preview PNGs the
admin Header picker cards use.

Run manually, on demand, whenever the header markup/CSS changes:

    python tests/capture-header-previews.py

Never required in production, never runs Chromium in wp-admin, no external
screenshot API, no Media Library -- see CONTRIBUTING.md / the Header Type
task's explicit constraints.
"""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("capture-header-previews: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "plugin/gloskin-site-core/assets"
OUT_DIR = ROOT / "plugin/gloskin-site-core/assets/admin"
OUT_DIR.mkdir(parents=True, exist_ok=True)

CSS_FONTS = (ASSETS / "css/gloskin-ui1-fonts.css").read_text(encoding="utf-8")
CSS_BASE = (ASSETS / "css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CSS_CORE = (ASSETS / "css/gloskin-ui1-core.css").read_text(encoding="utf-8")
CSS_PRODUCTION = (ASSETS / "css/gloskin-ui1-production.css").read_text(encoding="utf-8")

NAV_ITEMS = [
    ("Tentang Gloskin", False),
    ("Perawatan", False),
    ("Skincare", False),
    ("Belanja", True),
    ("Klinik", False),
    ("Dokter", False),
    ("Insight", False),
]


def nav_html(scope):
    items = "".join(
        '<li class="gloskin-ui1-nav__item{active_cls}"><div class="gloskin-ui1-nav__row">'
        '<a class="gloskin-ui1-nav__link" href="#"{aria}>{label}</a></div></li>'.format(
            active_cls=" is-active" if active else "",
            aria=' aria-current="page"' if active else "",
            label=label,
        )
        for label, active in NAV_ITEMS
    )
    return '<ul class="gloskin-ui1-nav__list">' + items + "</ul>"


BRAND_SVG = (
    '<svg width="140" height="34" viewBox="0 0 140 34" xmlns="http://www.w3.org/2000/svg">'
    '<text x="0" y="24" font-family="Georgia,serif" font-size="24" font-weight="700" fill="#B12E2F">Gloskin</text>'
    "</svg>"
)

ACTIONS_ICONS = """
<button class="gloskin-ui1-utility-btn" type="button" aria-label="Cari"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.5"/><path d="m13 13 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></button>
<a class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--account" href="#" aria-label="Akun saya"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="7.5" r="3.5" stroke="currentColor" stroke-width="1.4"/><path d="M3.5 17.5c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></a>
<button class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--wishlist" type="button" aria-label="Produk favorit"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 16.8C8.4 15.5 3 11.4 3 7.8 3 5.6 4.8 3.5 7.2 3.5c1.3 0 2.2.7 2.8 1.3.6-.6 1.5-1.3 2.8-1.3C15.2 3.5 17 5.6 17 7.8c0 3.6-5.4 7.7-7 9z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg><span class="gloskin-ui1-badge is-active" data-gloskin-wishlist-count>2</span></button>
<button class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--cart" type="button" aria-label="Keranjang"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M5 6h10l.8 10H4.2L5 6z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7.5 6V5a2.5 2.5 0 0 1 5 0v1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg><span class="gloskin-ui1-badge is-active" data-gloskin-cart-count>1</span></button>
"""

HEADER_1 = """
<header class="gloskin-ui1-header" data-gloskin-header="header-1">
  <div class="gloskin-ui1-container gloskin-ui1-header__inner">
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--start">
      <button class="gloskin-ui1-utility-btn" type="button" aria-label="Cari"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.5"/><path d="m13 13 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></button>
    </div>
    <a class="gloskin-ui1-brand" href="#">{brand}</a>
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--end">
      {actions}
      <a class="gloskin-ui1-button gloskin-ui1-button--ghost gloskin-ui1-header__contact" href="#">Hubungi Kami</a>
    </div>
  </div>
</header>
<div class="gloskin-ui1-header__nav-row">
  <div class="gloskin-ui1-container gloskin-ui1-header__nav-row-inner">
    <a class="gloskin-ui1-compact-brand" href="#" inert>{brand}</a>
    <nav class="gloskin-ui1-nav gloskin-ui1-nav--desktop" aria-label="Navigasi utama"><span class="gloskin-ui1-nav__bubble" aria-hidden="true"></span>{nav}</nav>
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--compact" inert></div>
  </div>
</div>
""".format(brand=BRAND_SVG, actions=ACTIONS_ICONS, nav=nav_html("desktop"))

HEADER_2 = """
<header class="gloskin-ui1-header" data-gloskin-header="header-2">
  <div class="gloskin-ui1-container gloskin-ui1-header__inner">
    <a class="gloskin-ui1-brand" href="#">{brand}</a>
    <nav class="gloskin-ui1-nav gloskin-ui1-nav--desktop" aria-label="Navigasi utama"><span class="gloskin-ui1-nav__bubble" aria-hidden="true"></span>{nav}</nav>
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--end">
      {actions}
    </div>
  </div>
</header>
""".format(brand=BRAND_SVG, actions=ACTIONS_ICONS, nav=nav_html("split"))


def html_page(header_markup):
    return """<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="gloskin-ui1">
{header}
<main><div class="gloskin-ui1-container" style="padding-block:40px;color:#6F6667">Preview content</div></main>
</body></html>""".format(header=header_markup)


def capture(page, header_markup, out_path):
    page.set_content(html_page(header_markup))
    page.add_style_tag(content=CSS_FONTS + "\n" + CSS_BASE + "\n" + CSS_CORE + "\n" + CSS_PRODUCTION)
    # Static preview only -- deliberately does not load gloskin-ui1-core.js.
    # The interactive nav-bubble/compact-sticky JS animates into place on
    # real pageviews; without a real scroll/settle cycle its snapshot mid-
    # transition looks wrong, and none of that behavior is what this image
    # exists to document (see Header Type acceptance criteria) -- only the
    # static CSS layout is. The plain .is-active CSS background/text tint
    # still shows the current-page nav item correctly on its own.
    page.wait_for_timeout(80)
    header = page.locator("header.gloskin-ui1-header")
    box = header.bounding_box()
    # Include the nav row (header-1 only) in the crop when present.
    nav_row = page.locator(".gloskin-ui1-header__nav-row")
    height = box["height"]
    if nav_row.count():
        nav_box = nav_row.bounding_box()
        if nav_box:
            height = (nav_box["y"] + nav_box["height"]) - box["y"]
    page.screenshot(
        path=str(out_path),
        clip={"x": 0, "y": 0, "width": 1440, "height": min(height, 260)},
    )
    print("wrote", out_path)


with sync_playwright() as p:
    chromium = Path("/usr/bin/chromium")
    launch_kwargs = {"headless": True}
    if chromium.exists():
        launch_kwargs["executable_path"] = str(chromium)
        launch_kwargs["args"] = ["--no-sandbox"]
    browser = p.chromium.launch(**launch_kwargs)
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    capture(page, HEADER_1, OUT_DIR / "header-type-1.png")
    capture(page, HEADER_2, OUT_DIR / "header-type-2.png")
    browser.close()

print("capture-header-previews: OK")
