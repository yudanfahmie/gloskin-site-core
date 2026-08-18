#!/usr/bin/env python3
"""Browser geometry smoke for semi-full container + doctor-specific grid."""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("width-doctor-grid-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "plugin/gloskin-site-core/assets/css"
CSS = "\n".join(
    (ASSETS / name).read_text(encoding="utf-8")
    for name in (
        "gloskin-ui1-core-base.css",
        "gloskin-ui1-core.css",
        "gloskin-ui1-production.css",
        "gloskin-ui1-prototype-refresh.css",
    )
)

DOCTORS = "".join(
    f'''<article class="gloskin-ui1-card gloskin-ui1-card--doctor">
      <div class="gloskin-ui1-card__media"><div class="gloskin-ui1-media gloskin-ui1-media--doctor"></div></div>
      <div class="gloskin-ui1-card__body"><h3 class="gloskin-ui1-card__title">Dokter Factual {i}</h3><p>Degree</p><p class="gloskin-ui1-card__copy">Specialization</p><a class="gloskin-ui1-text-link" href="#d{i}">Lihat Profil</a></div>
    </article>'''
    for i in range(1, 14)
)

HTML = f'''<!doctype html><html><head><meta charset="utf-8"></head>
<body class="gloskin-ui1">
<header class="gloskin-ui1-header" data-gloskin-header="header-2">
  <div class="gloskin-ui1-container gloskin-ui1-header__inner">
    <a class="gloskin-ui1-brand" href="#">GLOSKIN</a>
    <nav class="gloskin-ui1-nav gloskin-ui1-nav--desktop"><ul class="gloskin-ui1-nav__list">
      <li>Perawatan</li><li>Promo</li><li>Skincare</li><li>Tentang Gloskin</li>
    </ul></nav>
    <div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--end"><button>Cari</button><button>Akun</button><button>Cart</button></div>
  </div>
</header>
<main><section class="gloskin-ui1-section" data-gloskin-section="doctors-grid"><div class="gloskin-ui1-container">
<div class="gloskin-ui1-grid gloskin-ui1-grid--cards">{DOCTORS}</div>
</div></section></main></body></html>'''


def require(condition, message):
    if not condition:
        raise AssertionError(message)


expected_columns = {375: 1, 768: 2, 1024: 2, 1280: 4, 1440: 4, 1920: 4}

with sync_playwright() as p:
    chromium = Path("/usr/bin/chromium")
    try:
        browser = p.chromium.launch(headless=True, executable_path=str(chromium) if chromium.exists() else None, args=["--no-sandbox"])
    except Exception:
        print("width-doctor-grid-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)

    for width, columns in expected_columns.items():
        page = browser.new_page(viewport={"width": width, "height": 1000})
        page.set_content(HTML)
        page.add_style_tag(content=CSS)
        page.wait_for_timeout(30)

        container = page.locator('[data-gloskin-section="doctors-grid"] .gloskin-ui1-container').bounding_box()
        header = page.locator('[data-gloskin-header="header-2"] .gloskin-ui1-header__inner').bounding_box()
        nav = page.locator('[data-gloskin-header="header-2"] .gloskin-ui1-nav--desktop').bounding_box()
        cards = page.locator('[data-gloskin-section="doctors-grid"] .gloskin-ui1-card--doctor')
        first = cards.nth(0).bounding_box()
        second = cards.nth(1).bounding_box()
        fifth = cards.nth(4).bounding_box()

        require(container is not None and header is not None, f"container/header geometry missing at {width}")
        require(page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), f"horizontal overflow at {width}px")
        if width == 1440:
            require(abs(container["width"] - 1320) <= 1.5, f"1440 viewport must expose ~1320px content, got {container['width']}")
        if width == 1920:
            require(abs(container["width"] - 1480) <= 1.5, f"1920 viewport must retain ~1480px wide breakpoint, got {container['width']}")

        if columns == 1:
            require(abs(first["width"] - second["width"]) <= 1 and abs(first["x"] - second["x"]) <= 1,
                    f"doctor grid must be one column at {width}px")
        elif columns == 2:
            require(abs(first["y"] - second["y"]) <= 1 and abs(first["x"] - second["x"]) > 10,
                    f"doctor grid must be two columns at {width}px")
            require(abs(first["x"] - fifth["x"]) <= 1, f"fifth card should begin a later two-column row at {width}px")
        else:
            fourth = cards.nth(3).bounding_box()
            require(abs(first["y"] - fourth["y"]) <= 1, f"first four doctors must share desktop row at {width}px")
            require(abs(first["x"] - fifth["x"]) <= 1, f"fifth doctor must start row two at {width}px")

        if nav is not None and width >= 1100:
            nav_center = nav["x"] + nav["width"] / 2
            container_center = header["x"] + header["width"] / 2
            require(abs(nav_center - container_center) <= 2.5, f"Header V2 nav lost geometric centering at {width}px")

        ratio = first["width"] / max(1, page.locator('[data-gloskin-section="doctors-grid"] .gloskin-ui1-card--doctor .gloskin-ui1-media--doctor').first.bounding_box()["height"])
        require(0.77 <= ratio <= 0.83, f"doctor media should remain approximately 4:5 at {width}px")
        page.close()

    browser.close()

print("width-doctor-grid-browser-smoke: OK")
