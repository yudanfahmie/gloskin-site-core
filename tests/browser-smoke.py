#!/usr/bin/env python3
from pathlib import Path
import subprocess
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css'
JS = ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js'

html = subprocess.check_output(['php', str(ROOT / 'tests/render-fixture.php')], text=True)

viewports = [
    ('mobile', 390, 844),
    ('tablet', 820, 1180),
    ('desktop', 1440, 900),
]

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
    for name, width, height in viewports:
        page = browser.new_page(viewport={'width': width, 'height': height})
        errors = []
        page.on('console', lambda msg, e=errors: e.append(msg.text) if msg.type == 'error' else None)
        page.on('pageerror', lambda err, e=errors: e.append(str(err)))
        page.set_content(html, wait_until='domcontentloaded')
        page.add_style_tag(path=str(CSS))
        page.add_script_tag(path=str(JS))
        page.wait_for_timeout(50)

        overflow = page.evaluate('document.documentElement.scrollWidth - window.innerWidth')
        if overflow > 1:
            raise SystemExit(f'{name}: horizontal overflow {overflow}px')

        page.keyboard.press('Tab')
        focus_outline = page.evaluate("() => { const s=getComputedStyle(document.activeElement); return [s.outlineStyle,s.outlineWidth].join(':'); }")
        if focus_outline.startswith('none') or focus_outline.endswith(':0px'):
            raise SystemExit(f'{name}: keyboard focus indicator missing: {focus_outline}')

        if name == 'mobile':
            opener = page.locator('[data-gloskin-drawer-open]')
            opener.click()
            drawer = page.locator('[data-gloskin-drawer]')
            if drawer.get_attribute('aria-hidden') != 'false' or opener.get_attribute('aria-expanded') != 'true':
                raise SystemExit('mobile: drawer did not open with ARIA state')
            inside = page.evaluate("document.querySelector('[data-gloskin-drawer]').contains(document.activeElement)")
            if not inside:
                raise SystemExit('mobile: focus did not move into drawer')

            toggle = drawer.locator('[data-gloskin-submenu-toggle]').first
            toggle.click()
            target_id = toggle.get_attribute('aria-controls')
            if toggle.get_attribute('aria-expanded') != 'true' or page.locator('#' + target_id).get_attribute('hidden') is not None:
                raise SystemExit('mobile: submenu disclosure state failed')

            page.keyboard.press('Escape')
            if drawer.get_attribute('aria-hidden') != 'true' or opener.get_attribute('aria-expanded') != 'false':
                raise SystemExit('mobile: Escape did not close drawer')

            opener.click()
            page.locator('[data-gloskin-drawer-close]').first.click(position={'x': 2, 'y': 2})
            if drawer.get_attribute('aria-hidden') != 'true':
                raise SystemExit('mobile: backdrop did not close drawer')

        if errors:
            raise SystemExit(f'{name}: console/page errors: {errors}')
        page.close()
        print(f'browser smoke passed ({name} {width}x{height})')

    reduced = browser.new_page(viewport={'width': 390, 'height': 844}, reduced_motion='reduce')
    reduced.set_content(html, wait_until='domcontentloaded')
    reduced.add_style_tag(path=str(CSS))
    if not reduced.evaluate("matchMedia('(prefers-reduced-motion: reduce)').matches"):
        raise SystemExit('reduced-motion media query did not activate')
    duration = reduced.locator('.gloskin-ui1-button').first.evaluate("el => getComputedStyle(el).transitionDuration")
    if duration not in ('0s', '0.00001s', '1e-05s'):
        raise SystemExit(f'reduced-motion transition not minimized: {duration}')
    reduced.close()
    print('browser smoke passed (reduced motion)')
    browser.close()
