#!/usr/bin/env python3
from pathlib import Path
import os, subprocess
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
def fixture(view):
    env=dict(os.environ); env['GLOSKIN_FIXTURE_VIEW']=view
    return subprocess.check_output(['php',str(ROOT/'tests/render-fixture.php')],text=True,env=env)
with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True)
    page=browser.new_page(viewport={'width':1440,'height':900})
    for view in ('doctors','clinics','shop'):
        page.set_content(fixture(view),wait_until='domcontentloaded')
        leaks=page.locator('.gloskin-ui1-media--doctor,.gloskin-ui1-media--clinic,.gloskin-ui1-media--product').count()
        if leaks:
            raise SystemExit(f'{view}: factual abstract placeholder still rendered ({leaks})')
        # Text-first cards must not carry an empty normal media link/shell.
        empty=page.locator('.gloskin-ui1-card--text-first .gloskin-ui1-card__media:not(:has(img))').count()
        if empty:
            raise SystemExit(f'{view}: text-first card still has empty media shell ({empty})')
    browser.close()
print('zero-placeholder-browser-smoke.py: OK')
