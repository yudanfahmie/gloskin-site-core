#!/usr/bin/env python3
"""Browser geometry smoke for Phase-2 Skincare/Promo plus protected Home shell."""
from pathlib import Path
import os
import subprocess
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
CSS_FILES = [
    ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css',
    ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css',
    ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-product-grid.css',
    ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css',
]
WIDTHS = (390, 768, 1024, 1440)


def render_php(path: Path, env=None) -> str:
    return subprocess.check_output(['php', str(path)], text=True, env=env)


component_html = render_php(ROOT / 'tests/phase2-client-feedback-fixture.php')
home_env = dict(os.environ)
home_env['GLOSKIN_FIXTURE_VIEW'] = 'home'
home_html = render_php(ROOT / 'tests/render-fixture.php', home_env)

with sync_playwright() as pw:
    managed = Path(pw.chromium.executable_path)
    executable = str(managed) if managed.is_file() else '/usr/bin/chromium'
    browser = pw.chromium.launch(executable_path=executable, headless=True, args=['--no-sandbox'])
    for width in WIDTHS:
        height = 1000 if width < 1024 else 900

        page = browser.new_page(viewport={'width': width, 'height': height})
        page.set_content(component_html, wait_until='domcontentloaded')
        for css in CSS_FILES:
            page.add_style_tag(path=str(css))
        page.wait_for_timeout(50)

        if page.evaluate('document.documentElement.scrollWidth - window.innerWidth') > 1:
            raise SystemExit(f'{width}: Phase-2 component fixture has horizontal overflow')

        card = page.locator('.gloskin-ui1-card--product-skincare')
        if card.count() != 1 or not card.is_visible():
            raise SystemExit(f'{width}: Skincare variant card missing')
        image = card.locator('.gloskin-ui1-card__image')
        if image.evaluate("el => getComputedStyle(el).objectFit") != 'contain':
            raise SystemExit(f'{width}: Skincare packshot must remain contained')
        for selector in ('.gloskin-ui1-card__title', '.gloskin-ui1-product-price', '.gloskin-ui1-card__actions a'):
            target = card.locator(selector)
            if target.count() != 1 or not target.is_visible():
                raise SystemExit(f'{width}: Skincare hierarchy missing {selector}')
        if card.locator('[class*="wishlist"], [class*="rating"], [class*="review"]').count():
            raise SystemExit(f'{width}: Skincare card must not render wishlist/rating/review UI')
        action = card.locator('.gloskin-ui1-card__actions a')
        action_style = action.evaluate("el => {const s=getComputedStyle(el); return {bg:s.backgroundColor,border:s.borderTopWidth,color:s.color}}")
        if action_style['border'] == '0px':
            raise SystemExit(f'{width}: Skincare purchase action must remain outlined')
        if action.bounding_box()['width'] > card.bounding_box()['width'] + 1:
            raise SystemExit(f'{width}: Skincare action overflows its card')

        promo = page.locator('.gloskin-ui1-promo-carousel--page')
        if promo.count() != 1 or not promo.is_visible():
            raise SystemExit(f'{width}: page Promo variant missing')
        if promo.locator('.gloskin-ui1-promo-carousel__page-heading').inner_text().strip() != 'Promo Terbatas':
            raise SystemExit(f'{width}: Promo Terbatas hierarchy missing')
        if promo.locator('.gloskin-ui1-promo-carousel__poster').count() != 3:
            raise SystemExit(f'{width}: Promo poster selector must reuse all managed records')
        if not promo.locator('.gloskin-ui1-promo-carousel__poster-section').is_visible():
            raise SystemExit(f'{width}: Promo poster section not visible')
        page.close()

        # Existing rendered Home fixture protects Phase-1 shell and verifies that
        # the Phase-2 composition did not reintroduce retired Home surfaces.
        home = browser.new_page(viewport={'width': width, 'height': height})
        home.set_content(home_html, wait_until='domcontentloaded')
        for css in CSS_FILES:
            home.add_style_tag(path=str(css))
        home.wait_for_timeout(50)
        if home.evaluate('document.documentElement.scrollWidth - window.innerWidth') > 1:
            raise SystemExit(f'{width}: Home has horizontal overflow')
        if home.locator('.gloskin-ui1-hero').count() != 1:
            raise SystemExit(f'{width}: Home must retain exactly one hero')
        if home.locator('[data-gloskin-section="why-gloskin"]').count() != 1:
            raise SystemExit(f'{width}: Home Why section missing')
        if home.locator('[data-gloskin-section="home-discovery"], [data-gloskin-section="home-brand-story"], .gloskin-ui1-promo-carousel').count():
            raise SystemExit(f'{width}: reference-absent Home surface returned')
        if home.locator('.gloskin-ui1-breadcrumb-slot, [data-gloskin-breadcrumb-owner]').count():
            raise SystemExit(f'{width}: Phase-1 breadcrumb removal regressed')
        cta = home.locator('[data-gloskin-section="home-closing"] [data-gloskin-composition="closing-cta"]')
        if cta.count() != 1 or not cta.is_visible():
            raise SystemExit(f'{width}: Phase-1 closing CTA missing')
        links = cta.locator('a')
        for idx in range(links.count()):
            if not links.nth(idx).is_visible() or not links.nth(idx).inner_text().strip():
                raise SystemExit(f'{width}: Phase-1 closing CTA label unreadable/missing')
        home.close()

    browser.close()

print('phase2-client-feedback-browser-smoke.py: OK (390/768/1024/1440)')
