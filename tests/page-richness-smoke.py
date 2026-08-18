#!/usr/bin/env python3
from pathlib import Path
import os
import subprocess
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
BASE_CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css'
CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css'
PRODUCTION_CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-production.css'
REFRESH_CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css'

views = [
    'home', 'about', 'treatments', 'promo', 'treatment', 'skincare', 'skincare-category',
    'clinics', 'clinic', 'doctors', 'doctor', 'insights', 'shop', 'contact',
]
viewports = [390, 760, 782, 1024, 1440, 1920, 2560]
required_sections = {
    'home': ['home-orientation', 'home-treatments', 'home-promo', 'home-skincare', 'home-about', 'home-closing'],
    # About story/principles/team/network are factual and therefore optional in sparse fixtures.
    'about': ['about-closing'],
    'treatments': ['treatments-closing'],
    # Native Promo campaign and closing CTA remain even when factual Page media/content is empty.
    'promo': ['promo-campaign', 'promo-closing'],
    'treatment': ['treatment-orientation', 'treatment-closing'],
    'skincare': ['skincare-intro', 'skincare-categories', 'skincare-pathways'],
    'skincare-category': ['skincare-category-products', 'skincare-category-closing'],
    'clinics': ['clinics-orientation', 'clinics-grid', 'clinics-closing'],
    'clinic': ['clinic-closing'],
    'doctors': ['doctors-intro', 'doctors-grid', 'doctors-pathways', 'doctors-closing'],
    'doctor': ['doctor-closing'],
    'insights': ['insights-intro', 'insights-list'],
    'shop': ['shop-products'],
    'contact': ['contact-clinics'],
}
closing_views = {'home', 'about', 'treatments', 'promo', 'treatment', 'skincare-category', 'clinics', 'clinic', 'doctors', 'doctor'}


def fixture(view: str) -> str:
    env = dict(os.environ)
    env['GLOSKIN_FIXTURE_VIEW'] = view
    return subprocess.check_output(['php', str(ROOT / 'tests/render-fixture.php')], text=True, env=env)

fixtures = {view: fixture(view) for view in views}
EDITORIAL_STUB = '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1200"><rect width="1600" height="1200" fill="#fbe2b2"/></svg>'

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
    for width in viewports:
        height = 900 if width >= 1024 else 1000
        for view in views:
            page = browser.new_page(viewport={'width': width, 'height': height})
            errors = []
            page.on('console', lambda msg, e=errors: e.append(msg.text) if msg.type == 'error' else None)
            page.on('pageerror', lambda err, e=errors: e.append(str(err)))
            page.route('https://images.unsplash.com/**', lambda route: route.fulfill(status=200, content_type='image/svg+xml', body=EDITORIAL_STUB))
            page.route(
                'https://example.test/**',
                lambda route: route.fulfill(
                    status=200 if route.request.resource_type == 'image' else 204,
                    content_type='image/svg+xml' if route.request.resource_type == 'image' else 'text/plain',
                    body=EDITORIAL_STUB if route.request.resource_type == 'image' else '',
                ),
            )
            page.set_content(fixtures[view], wait_until='domcontentloaded')
            page.add_style_tag(path=str(BASE_CSS))
            page.add_style_tag(path=str(CSS))
            page.add_style_tag(path=str(PRODUCTION_CSS))
            page.add_style_tag(path=str(REFRESH_CSS))
            page.wait_for_timeout(100)

            if page.locator('main h1').count() != 1:
                raise SystemExit(f'{width}/{view}: expected exactly one main H1')
            if not page.locator('main h1').is_visible():
                raise SystemExit(f'{width}/{view}: main H1 must be visible')
            if page.evaluate('document.documentElement.scrollWidth - window.innerWidth') > 1:
                raise SystemExit(f'{width}/{view}: horizontal overflow')

            for section in required_sections[view]:
                if page.locator(f'[data-gloskin-section="{section}"]').count() != 1:
                    raise SystemExit(f'{width}/{view}: missing composition section {section}')

            if view in closing_views and page.locator('[data-gloskin-composition="closing-cta"]').count() != 1:
                raise SystemExit(f'{width}/{view}: closing CTA contract failed')

            if view == 'home':
                if page.locator('.gloskin-ui1-hero').count() != 1:
                    raise SystemExit(f'{width}/home: expected exactly one primary hero')
                if page.locator('.gloskin-ui1-hero__copy').count() != 1 or page.locator('.gloskin-ui1-hero__actions a').count() != 1:
                    raise SystemExit(f'{width}/home: visible hero copy/CTA hierarchy missing')
            if view == 'promo' and page.locator('.gloskin-ui1-promo-campaign').count() != 1:
                raise SystemExit(f'{width}/promo: expected one native campaign composition')
            if view == 'clinic':
                sparse_or_factual = page.locator('[data-gloskin-section="clinic-sparse"], [data-gloskin-section="clinic-facts"]')
                if sparse_or_factual.count() != 1:
                    raise SystemExit(f'{width}/clinic: expected one factual or sparse-state composition')
            if view == 'doctor':
                sparse_or_factual = page.locator('[data-gloskin-section="doctor-sparse"], [data-gloskin-section="doctor-facts"]')
                if sparse_or_factual.count() != 1:
                    raise SystemExit(f'{width}/doctor: expected one factual or sparse-state composition')

            if errors:
                raise SystemExit(f'{width}/{view}: console/page errors: {errors}')
            page.close()
        print(f'page richness smoke passed ({width}px, {len(views)} views)')
    browser.close()

print('page-richness-smoke: OK')
