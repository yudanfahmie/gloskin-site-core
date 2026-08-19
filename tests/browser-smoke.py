#!/usr/bin/env python3
"""Broad browser compatibility smoke for the canonical public shell.

Release-specific contracts live in their focused browser suites. In particular:
- migrated zero-placeholder/local-media behavior is owned by
  zero-placeholder-browser-smoke.py;
- typography binaries are owned by font-browser-smoke.py;
- header microinteraction/geometry has dedicated browser contracts;
- commerce behavior has dedicated browser contracts.

This file intentionally avoids historical submenu/Unsplash/placeholder/font
assumptions that conflict with the approved final IA and local-media closure.
"""
from pathlib import Path
import os
import subprocess

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
BASE_CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css'
CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css'
PRODUCTION_CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-production.css'
JS = ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js'

VIEWS = (
    'home', 'about', 'treatments', 'treatment', 'clinics', 'clinic',
    'doctors', 'doctor', 'skincare', 'skincare-category', 'contact',
    'insights', 'shop',
)
VIEWPORTS = (
    ('mobile', 390, 844),
    ('tablet', 820, 1180),
    ('desktop', 1440, 900),
    ('exhibition', 1920, 1080),
    ('large-exhibition', 2560, 1440),
)
HEADER_WIDTHS = (390, 600, 601, 782, 1024, 1440, 1920)
PUBLIC_LEAKS = (
    'not configured', 'content pending', 'missing data', 'architecture supports',
    'approved doctor profiles', 'approved treatment categories',
    'woocommerce product data is currently unavailable', 'coming soon',
    'lorem ipsum', 'dummy', 'fixture editorial post',
    'template ownership', 'catalog ownership', 'second catalog', 'katalog kedua',
    'kepemilikan produk', 'kepemilikan katalog',
)
EDITORIAL_STUB = (
    '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1200" '
    'viewBox="0 0 1600 1200"><rect width="1600" height="1200" fill="#eaf0f4"/></svg>'
)


def fixture(view: str, **extra_env) -> str:
    env = dict(os.environ)
    env['GLOSKIN_FIXTURE_VIEW'] = view
    env.update({key: str(value) for key, value in extra_env.items()})
    return subprocess.check_output(
        ['php', str(ROOT / 'tests/render-fixture.php')], text=True, env=env
    )


def install_routes(page):
    # Legacy sparse fixtures may still exercise the catastrophic editorial
    # fallback. The final migrated-state no-hotlink contract is asserted by
    # zero-placeholder-browser-smoke.py, not by this pre-final fixture.
    page.route(
        'https://images.unsplash.com/**',
        lambda route: route.fulfill(
            status=200, content_type='image/svg+xml', body=EDITORIAL_STUB
        ),
    )

    def fixture_origin(route):
        if '/wp-json/' in route.request.url:
            route.fallback()
        elif route.request.resource_type == 'document':
            route.fulfill(
                status=200,
                content_type='text/html',
                body='<!doctype html><title>Gloskin browser fixture</title>',
            )
        elif route.request.url.endswith('/gloskin-logotext.svg'):
            route.fulfill(
                status=200,
                content_type='image/svg+xml',
                path=str(ROOT / 'plugin/gloskin-site-core/assets/images/gloskin-logotext.svg'),
            )
        elif route.request.resource_type == 'image':
            route.fulfill(status=200, content_type='image/svg+xml', body=EDITORIAL_STUB)
        else:
            route.fulfill(status=204, body='')

    page.route('https://example.test/**', fixture_origin)


def load(page, html: str):
    install_routes(page)
    page.goto('https://example.test/__gloskin-browser-fixture', wait_until='domcontentloaded')
    page.set_content(html, wait_until='domcontentloaded')
    page.locator('main img[loading="lazy"]').evaluate_all(
        "els => els.forEach(el => { el.loading = 'eager'; })"
    )
    page.wait_for_function(
        "() => Array.from(document.querySelectorAll('main img')).every(" 
        "img => img.complete && img.naturalWidth > 0 && img.naturalHeight > 0)",
        timeout=5000,
    )
    page.add_style_tag(path=str(BASE_CSS))
    page.add_style_tag(path=str(CSS))
    page.add_style_tag(path=str(PRODUCTION_CSS))
    page.add_script_tag(path=str(JS))
    page.wait_for_timeout(220)


def assert_focus_visible(page, label: str):
    page.keyboard.press('Tab')
    outline = page.evaluate(
        "() => { const s=getComputedStyle(document.activeElement); "
        "return [s.outlineStyle,s.outlineWidth].join(':'); }"
    )
    if outline.startswith('none') or outline.endswith(':0px'):
        raise SystemExit(f'{label}: keyboard focus indicator missing: {outline}')


fixtures = {view: fixture(view) for view in VIEWS}
home_real_media = fixture('home', GLOSKIN_FIXTURE_REAL_MEDIA=1)

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True)

    for viewport_name, width, height in VIEWPORTS:
        for view in VIEWS:
            page = browser.new_page(viewport={'width': width, 'height': height})
            errors = []
            page.on(
                'console',
                lambda msg, bucket=errors: bucket.append(msg.text)
                if msg.type == 'error' else None,
            )
            page.on('pageerror', lambda err, bucket=errors: bucket.append(str(err)))
            load(page, fixtures[view])

            overflow = page.evaluate('document.documentElement.scrollWidth - window.innerWidth')
            if overflow > 1:
                raise SystemExit(f'{viewport_name}/{view}: horizontal overflow {overflow}px')

            body_text = page.locator('body').inner_text().lower()
            for leak in PUBLIC_LEAKS:
                if leak in body_text:
                    raise SystemExit(f'{viewport_name}/{view}: public leak: {leak}')

            if page.locator('main h1').count() != 1:
                raise SystemExit(f'{viewport_name}/{view}: expected one main h1')

            images = page.locator('main img')
            for image_index in range(images.count()):
                image = images.nth(image_index)
                src = (image.get_attribute('src') or '').strip()
                if not src or not image.get_attribute('width') or not image.get_attribute('height'):
                    raise SystemExit(
                        f'{viewport_name}/{view}: image source/intrinsic geometry missing'
                    )
                if not image.evaluate(
                    'el => el.complete && el.naturalWidth > 0 && el.naturalHeight > 0'
                ):
                    raise SystemExit(f'{viewport_name}/{view}: broken image source')

            # Approved final primary IA is flat. A zero-toggle header is valid and
            # must not be treated as a missing-chevron regression. If a fixture
            # intentionally supplies submenu toggles, every toggle must still own
            # one accessible SVG chevron.
            toggles = page.locator('[data-gloskin-submenu-toggle]')
            chevrons = page.locator(
                '[data-gloskin-submenu-toggle] svg.gloskin-ui1-nav__chevron'
            )
            if chevrons.count() != toggles.count():
                raise SystemExit(
                    f'{viewport_name}/{view}: submenu SVG chevron coverage mismatch'
                )
            for toggle_index in range(toggles.count()):
                toggle = toggles.nth(toggle_index)
                if '⌄' in toggle.inner_text():
                    raise SystemExit(
                        f'{viewport_name}/{view}: legacy Unicode disclosure glyph remains'
                    )
                icon = toggle.locator('svg.gloskin-ui1-nav__chevron')
                if (
                    icon.get_attribute('aria-hidden') != 'true'
                    or icon.get_attribute('focusable') != 'false'
                ):
                    raise SystemExit(
                        f'{viewport_name}/{view}: chevron accessibility state failed'
                    )

            assert_focus_visible(page, f'{viewport_name}/{view}')

            if viewport_name == 'mobile' and view == 'home':
                opener = page.locator('[data-gloskin-drawer-open]')
                drawer = page.locator('[data-gloskin-drawer]')
                opener.click()
                page.wait_for_timeout(80)
                if (
                    drawer.get_attribute('aria-hidden') != 'false'
                    or opener.get_attribute('aria-expanded') != 'true'
                    or not page.evaluate(
                        "document.querySelector('[data-gloskin-drawer]').contains(document.activeElement)"
                    )
                ):
                    raise SystemExit('mobile/home: drawer open/focus state failed')
                page.keyboard.press('Escape')
                page.wait_for_timeout(40)
                if (
                    drawer.get_attribute('aria-hidden') != 'true'
                    or opener.get_attribute('aria-expanded') != 'false'
                    or not opener.evaluate('el => document.activeElement === el')
                ):
                    raise SystemExit('mobile/home: Escape/focus return failed')

            if errors:
                raise SystemExit(f'{viewport_name}/{view}: console/page errors: {errors}')
            page.close()

        print(
            f'browser smoke passed ({viewport_name} {width}x{height}, {len(VIEWS)} views)'
        )

    # Header geometry remains broad and IA-independent at every breakpoint.
    for width in HEADER_WIDTHS:
        height = 1000 if width < 1200 else 900
        page = browser.new_page(viewport={'width': width, 'height': height})
        load(page, fixtures['home'])
        geometry = page.evaluate(
            """() => {
                const header=document.querySelector('.gloskin-ui1-header');
                const h=header.getBoundingClientRect();
                const n=document.querySelector('.gloskin-ui1-header__nav-row');
                const nr=n ? n.getBoundingClientRect() : null;
                const m=document.querySelector('.gloskin-ui1-main').getBoundingClientRect();
                const b=document.querySelector('.gloskin-ui1-brand').getBoundingClientRect();
                const ns=n ? getComputedStyle(n) : null;
                const hs=getComputedStyle(header);
                return {
                    headerBottom:h.bottom,
                    navTop:nr ? nr.top : h.bottom,
                    navBottom:nr ? nr.bottom : h.bottom,
                    mainTop:m.top,
                    navDisplay:ns ? ns.display : 'none',
                    headerPosition:hs.position,
                    brandCenter:b.left+b.width/2,
                    overflow:document.documentElement.scrollWidth-window.innerWidth
                };
            }"""
        )
        if (
            geometry['overflow'] > 1
            or abs(geometry['brandCenter'] - width / 2) > 4
            or geometry['headerPosition'] in ('sticky', 'fixed')
        ):
            raise SystemExit(
                f'header-width/{width}: overflow/centering/sticky-owner regression: {geometry}'
            )
        if geometry['navDisplay'] == 'none':
            if abs(geometry['headerBottom'] - geometry['mainTop']) > 1:
                raise SystemExit(f'header-width/{width}: compact header ghost gap: {geometry}')
        elif (
            abs(geometry['headerBottom'] - geometry['navTop']) > 1
            or abs(geometry['navBottom'] - geometry['mainTop']) > 1
        ):
            raise SystemExit(f'header-width/{width}: layered header ghost gap: {geometry}')
        page.close()
    print('browser smoke passed (header computed geometry)')

    # Canonical logo/favicons remain available; detailed typography and header
    # animation are covered by their focused suites.
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    load(page, fixtures['home'])
    logo = page.locator('.gloskin-ui1-brand__image')
    if logo.count() < 2:
        raise SystemExit('logo: expected header + footer canonical images')
    for index in range(logo.count()):
        item = logo.nth(index)
        if not item.get_attribute('width') or not item.get_attribute('height'):
            raise SystemExit('logo: missing intrinsic dimensions')
        if not item.evaluate('el => el.complete && el.naturalWidth > 0'):
            raise SystemExit('logo: canonical image failed to load')
    page.close()

    expected_favicons = (
        'favicon.ico', 'favicon-16x16.png', 'favicon-32x32.png',
        'icon-192.png', 'icon-512.png', 'apple-touch-icon.png',
    )
    no_icon_html = fixture('home')
    with_icon_html = fixture('home', GLOSKIN_FIXTURE_SITE_ICON='1')
    if not all(name in no_icon_html for name in expected_favicons):
        raise SystemExit('favicon: Gloskin favicon set missing without Site Icon')
    if not all(name in with_icon_html for name in expected_favicons):
        raise SystemExit('favicon: Gloskin favicon set missing with Site Icon')
    if 'stale-wp-site-icon.png' in with_icon_html:
        raise SystemExit('favicon: native Site Icon duplicate rendered')

    # Native editor-selected media remains higher priority than fallback media.
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    load(page, home_real_media)
    hero = page.locator('.gloskin-ui1-hero__media')
    if (
        hero.locator('[data-test-wordpress-media="true"]').count() != 1
        or hero.locator('[data-gloskin-editorial]').count()
    ):
        raise SystemExit('desktop/home: native attachment hero media priority failed')
    page.close()

    browser.close()

print('browser-smoke.py: OK (canonical broad compatibility; release specifics delegated)')
