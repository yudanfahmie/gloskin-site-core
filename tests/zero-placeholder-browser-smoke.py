#!/usr/bin/env python3
from pathlib import Path
import json
import os
import subprocess
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
ROUTES = (
    'home', 'treatments', 'promo', 'skincare', 'about', 'doctors',
    'doctor-detail', 'clinics', 'clinic-detail', 'insights',
    'treatment-detail', 'shop',
)


def migrated_fixture():
    env = dict(os.environ)
    env['GLOSKIN_MIGRATION_RENDER_JSON'] = '1'
    raw = subprocess.check_output(
        ['php', str(ROOT / 'tests/final-migration-render-fixture-runner.php')],
        text=True,
        env=env,
    )
    payload = json.loads(raw)
    if payload.get('final_status') != 'consumed':
        raise SystemExit('integration fixture did not consume Final Migration')
    if payload.get('roster_status') != 'consumed' or payload.get('roster_index') != 13:
        raise SystemExit('integration fixture doctor roster did not consume at 13')
    expected_catalog = {
        'home_why', 'home_brand_story', 'treatment_discovery',
        'treatment_clinical', 'skincare_editorial', 'about_story',
    }
    if set(payload.get('catalog_keys', [])) != expected_catalog:
        raise SystemExit('integration fixture editorial catalog is incomplete')
    missing = [route for route in ROUTES if not payload.get('routes', {}).get(route)]
    if missing:
        raise SystemExit(f'integration fixture missing rendered routes: {missing}')
    return payload


payload = migrated_fixture()

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True)
    page = browser.new_page(viewport={'width': 1440, 'height': 900})

    for route in ROUTES:
        page.set_content(payload['routes'][route], wait_until='domcontentloaded')

        # Successful final migration must have zero normal abstract-media consumers.
        abstracts = page.locator('.gloskin-ui1-media').count()
        if abstracts:
            raise SystemExit(f'{route}: normal abstract .gloskin-ui1-media rendered ({abstracts})')

        # No factual entity/product may acquire a generic editorial/stock identity.
        factual_substitution = page.locator(
            '.gloskin-ui1-card--doctor .gloskin-ui1-card__image--editorial,'
            '.gloskin-ui1-card--clinic .gloskin-ui1-card__image--editorial,'
            '.gloskin-ui1-card--product .gloskin-ui1-card__image--editorial,'
            '.gloskin-ui1-media--doctor,.gloskin-ui1-media--clinic,.gloskin-ui1-media--product'
        ).count()
        if factual_substitution:
            raise SystemExit(f'{route}: factual stock/editorial substitution rendered ({factual_substitution})')

        # Text-first entities must not retain a dead media wrapper/aspect-ratio shell.
        empty_text_first = page.locator(
            '.gloskin-ui1-card--text-first .gloskin-ui1-card__media,'
            '.gloskin-ui1-card--text-first .gloskin-ui1-card__media-wrap,'
            '.gloskin-ui1-detail-hero__grid--text-first .gloskin-ui1-detail-hero__media'
        ).count()
        if empty_text_first:
            raise SystemExit(f'{route}: text-first state still carries a media shell ({empty_text_first})')

        images = page.locator('img')
        for index in range(images.count()):
            image = images.nth(index)
            src = (image.get_attribute('src') or '').strip()
            if not src:
                raise SystemExit(f'{route}: img[{index}] has empty src')
            if src.startswith(('http://', 'https://')):
                host = (urlparse(src).hostname or '').lower()
                if host not in {'example.test'}:
                    raise SystemExit(f'{route}: external runtime image host leaked: {host}')
            natural_width = image.evaluate('(el) => el.complete ? el.naturalWidth : -1')
            if natural_width == 0:
                raise SystemExit(f'{route}: broken img[{index}] src={src[:120]}')

    # Key editorial slots must resolve from the locally imported catalog.
    page.set_content(payload['routes']['home'], wait_until='domcontentloaded')
    if page.locator('[data-gloskin-section="why-gloskin"] .gloskin-ui1-why__primary-media img[data-test-attachment-id]').count() != 1:
        raise SystemExit('home: Why Gloskin did not resolve local home_why media')
    if page.locator('[data-gloskin-section="home-brand-story"]').count() != 0:
        raise SystemExit('home: Phase-2 client structure must not render the retired standalone Brand Story section')

    page.set_content(payload['routes']['treatments'], wait_until='domcontentloaded')
    if page.locator('img[data-test-attachment-id]').count() < 1:
        raise SystemExit('treatments: expected local editorial media was not rendered')
    page.set_content(payload['routes']['treatment-detail'], wait_until='domcontentloaded')
    if page.locator('img[data-test-attachment-id]').count() < 1:
        raise SystemExit('treatment-detail: expected local treatment editorial media was not rendered')

    # Sparse factual states remain intentionally text-first, never stock-filled.
    page.set_content(payload['routes']['doctors'], wait_until='domcontentloaded')
    if page.locator('.gloskin-ui1-card--doctor.gloskin-ui1-card--text-first').count() < 1:
        raise SystemExit('doctors: missing factual doctor media did not degrade text-first')
    page.set_content(payload['routes']['clinics'], wait_until='domcontentloaded')
    if page.locator('.gloskin-ui1-card--clinic.gloskin-ui1-card--text-first').count() < 1:
        raise SystemExit('clinics: missing factual clinic media did not degrade text-first')
    page.set_content(payload['routes']['clinic-detail'], wait_until='domcontentloaded')
    if page.locator('.gloskin-ui1-detail-hero__grid--text-first').count() != 1:
        raise SystemExit('clinic-detail: missing factual clinic media did not degrade text-first')
    page.set_content(payload['routes']['shop'], wait_until='domcontentloaded')
    if page.locator('.gloskin-ui1-card--product.gloskin-ui1-card--text-first').count() < 1:
        raise SystemExit('shop: missing factual product media did not degrade text-first')

    browser.close()

print('zero-placeholder-browser-smoke.py: OK (12 migrated routes)')
