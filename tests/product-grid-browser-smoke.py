#!/usr/bin/env python3
"""Computed-column smoke for Home/Shop product grids and Shop skeleton parity."""
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
PRODUCT_CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-product-grid.css').read_text(encoding='utf-8')
SHOP_CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-shop-discovery.css').read_text(encoding='utf-8')

BASE_CSS = '''
:root{--gloskin-container:1180px;--gloskin-gutter:clamp(18px,4vw,40px);--gloskin-border:#ddd7d3;--gloskin-bg:#fbfbfa;--gloskin-surface:#f6f3f1;--gloskin-surface-strong:#ecebe8;--gloskin-text:#2a232c;--gloskin-muted:#6f6667;--gloskin-accent:#b12e2f;--gloskin-accent-readable:#b12e2f;--gloskin-accent-strong:#961f24;--gloskin-accent-soft:#f8e9e9;--gloskin-brand-champagne:#8f7953;--gloskin-radius-sm:10px;--gloskin-radius-md:18px;--gloskin-action-radius:8px;--gloskin-field-focus-ring:0 0 0 3px #f8e9e9;--gloskin-ui1-nav-sticky-top:0px}
*{box-sizing:border-box}body{margin:0}.gloskin-ui1-container{width:min(calc(100% - (2 * var(--gloskin-gutter))),var(--gloskin-container));margin-inline:auto}.gloskin-ui1-grid{display:grid;gap:clamp(16px,2.4vw,28px)}.gloskin-ui1-grid--cards{grid-template-columns:repeat(3,minmax(0,1fr))}.gloskin-ui1-card{overflow:hidden;border:1px solid var(--gloskin-border);border-radius:var(--gloskin-radius-md);background:var(--gloskin-bg)}.gloskin-ui1-card--product .gloskin-ui1-card__image{display:block;width:100%;aspect-ratio:1;background:var(--gloskin-surface-strong)}.gloskin-ui1-shop-catalog{display:grid;grid-template-columns:minmax(210px,240px) minmax(0,1fr);align-items:start;gap:clamp(24px,4vw,48px);min-width:0}.gloskin-ui1-shop-results-column{min-width:0}
@media(max-width:1040px){.gloskin-ui1-grid--cards{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:900px){.gloskin-ui1-shop-catalog{grid-template-columns:minmax(0,1fr);gap:24px}}@media(max-width:760px){.gloskin-ui1-grid--cards{grid-template-columns:1fr}}
'''

def cards(count):
    return ''.join('<article class="gloskin-ui1-card gloskin-ui1-card--product"><div class="gloskin-ui1-card__image"></div></article>' for _ in range(count))

def skeletons(count):
    return ''.join('<div class="gloskin-ui1-shop-skeleton__card"><div class="gloskin-ui1-shop-skeleton__media"></div><div class="gloskin-ui1-shop-skeleton__line gloskin-ui1-shop-skeleton__line--title"></div><div class="gloskin-ui1-shop-skeleton__line gloskin-ui1-shop-skeleton__line--price"></div></div>' for _ in range(count))

HTML = f'''<!doctype html><html><head><style>{BASE_CSS}</style><style>{SHOP_CSS}</style><style>{PRODUCT_CSS}</style></head><body>
<section id="home"><div class="gloskin-ui1-container"><div class="gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-ui1-product-grid">{cards(8)}</div></div></section>
<section id="shop"><div class="gloskin-ui1-container"><div class="gloskin-ui1-shop-catalog"><aside class="gloskin-ui1-shop-catalog__rail"><div class="gloskin-ui1-shop-rail-section">Pencarian</div><div class="gloskin-ui1-shop-rail-section">Harga</div><div class="gloskin-ui1-shop-rail-section"><nav class="gloskin-ui1-shop-categories">Kategori</nav></div></aside><div class="gloskin-ui1-shop-results-column"><div class="gloskin-ui1-product-grid gloskin-ui1-shop-grid">{cards(12)}</div><div class="gloskin-ui1-shop-skeleton__grid">{skeletons(8)}</div></div></div></div></section>
</body></html>'''

EXPECTED = {1440: 4, 1280: 4, 1024: 3, 768: 2, 390: 1, 320: 1}

def column_count(locator):
    return locator.evaluate("el => getComputedStyle(el).gridTemplateColumns.trim().split(/\\s+/).filter(Boolean).length")

def main():
    try:
        from playwright.sync_api import sync_playwright
    except Exception as exc:
        print(f'SKIP: Playwright unavailable: {exc}')
        return 77

    with sync_playwright() as p:
        try:
            browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
        except Exception as exc:
            print(f'SKIP: Chromium unavailable: {exc}')
            return 77
        for width, expected in EXPECTED.items():
            page = browser.new_page(viewport={'width': width, 'height': 900})
            page.set_content(HTML)
            home = page.locator('#home .gloskin-ui1-product-grid')
            shop = page.locator('#shop .gloskin-ui1-shop-grid')
            skeleton = page.locator('#shop .gloskin-ui1-shop-skeleton__grid')
            assert column_count(home) == expected, (width, 'home', column_count(home), expected)
            assert column_count(shop) == expected, (width, 'shop', column_count(shop), expected)
            assert column_count(skeleton) == expected, (width, 'skeleton', column_count(skeleton), expected)
            assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth') is True, f'{width}: horizontal overflow'
            rail_position = page.locator('.gloskin-ui1-shop-catalog__rail').evaluate("el => getComputedStyle(el).position")
            assert rail_position == ('sticky' if width > 900 else 'static'), (width, rail_position)
            if width == 1440:
                real_width = shop.locator('.gloskin-ui1-card--product').first.bounding_box()['width']
                skeleton_width = skeleton.locator('.gloskin-ui1-shop-skeleton__card').first.bounding_box()['width']
                assert abs(real_width - skeleton_width) < 1.0, (real_width, skeleton_width)
                media_box = skeleton.locator('.gloskin-ui1-shop-skeleton__media').first.bounding_box()
                assert abs(media_box['width'] - media_box['height']) < 1.0, media_box
                action_height = skeleton.locator('.gloskin-ui1-shop-skeleton__card').first.evaluate("el => parseFloat(getComputedStyle(el,'::after').height)")
                assert abs(action_height - 38) < 0.5, action_height
            page.close()

        page = browser.new_page(viewport={'width': 1440, 'height': 900})
        page.emulate_media(reduced_motion='reduce')
        page.set_content(HTML)
        animation = page.locator('.gloskin-ui1-shop-skeleton__line').first.evaluate("el => getComputedStyle(el).animationName")
        assert animation == 'none', animation
        page.close()
        browser.close()
    print('product-grid-browser-smoke.py: OK (1440/1280=4, 1024=3, 768=2, 390/320=1; skeleton parity/no overflow)')
    return 0

if __name__ == '__main__':
    sys.exit(main())
