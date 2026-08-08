#!/usr/bin/env python3
from pathlib import Path
import os
import subprocess
from playwright.sync_api import sync_playwright, Error as PlaywrightError

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css'
JS = ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js'

views = [
    'home', 'about', 'treatments', 'treatment', 'clinics', 'clinic',
    'doctors', 'doctor', 'skincare', 'skincare-category', 'contact',
    'insights', 'shop',
]
viewports = [
    ('mobile', 390, 844),
    ('tablet', 820, 1180),
    ('desktop', 1440, 900),
    ('exhibition', 1920, 1080),
    ('large-exhibition', 2560, 1440),
]
public_leaks = [
    'not configured', 'content pending', 'missing data', 'architecture supports',
    'approved doctor profiles', 'approved treatment categories',
    'woocommerce product data is currently unavailable', 'coming soon',
    'lorem ipsum', 'dummy', 'test product', 'test-001', 'na00000000000',
    'test composition', 'test usage', 'fixture editorial post',
]
english_owned = [
    'clinic network', 'view treatments', 'visit shop', 'find a clinic',
    'view details', 'view product', 'open map', 'related treatments',
    'medical team', 'browse skincare', 'contact form', 'practice branches',
]

def fixture(view: str) -> str:
    env = dict(os.environ)
    env['GLOSKIN_FIXTURE_VIEW'] = view
    return subprocess.check_output(['php', str(ROOT / 'tests/render-fixture.php')], text=True, env=env)

fixtures = {view: fixture(view) for view in views}

def load(page, html):
    page.set_content(html, wait_until='domcontentloaded')
    page.add_style_tag(path=str(CSS))
    page.add_script_tag(path=str(JS))
    # CSS is injected by the fixture harness after HTML; allow UI transitions to settle
    # before measuring presentation/contrast. Production styles load normally in wp_head.
    page.wait_for_timeout(220)

def assert_contrast(page, selector, minimum=4.5):
    ratio = page.locator(selector).first.evaluate("""el => {
        const parse = value => {
            const nums = (value.match(/[\\d.]+/g) || []).map(Number);
            return {r:nums[0]||0,g:nums[1]||0,b:nums[2]||0,a:nums.length>3?nums[3]:1};
        };
        const over = (front, back) => {
            const a = front.a + back.a * (1-front.a);
            if (!a) return {r:255,g:255,b:255,a:0};
            return {
                r:(front.r*front.a + back.r*back.a*(1-front.a))/a,
                g:(front.g*front.a + back.g*back.a*(1-front.a))/a,
                b:(front.b*front.a + back.b*back.a*(1-front.a))/a,
                a
            };
        };
        let bg={r:255,g:255,b:255,a:1};
        const chain=[]; let node=el;
        while(node){chain.push(parse(getComputedStyle(node).backgroundColor)); node=node.parentElement;}
        for(let i=chain.length-1;i>=0;i--){bg=over(chain[i],bg);}
        let fg=parse(getComputedStyle(el).color);
        fg=over(fg,bg);
        const lum = c => {
            const vals=[c.r,c.g,c.b].map(v=>v/255).map(v=>v<=0.04045?v/12.92:Math.pow((v+0.055)/1.055,2.4));
            return .2126*vals[0]+.7152*vals[1]+.0722*vals[2];
        };
        const a=lum(bg), b=lum(fg);
        return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
    }""")
    if ratio < minimum:
        raise SystemExit(f'contrast below {minimum}: {selector}={ratio:.2f}')

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
    for viewport_name, width, height in viewports:
        for view in views:
            page = browser.new_page(viewport={'width': width, 'height': height})
            errors = []
            page.on('console', lambda msg, e=errors: e.append(msg.text) if msg.type == 'error' else None)
            page.on('pageerror', lambda err, e=errors: e.append(str(err)))
            load(page, fixtures[view])

            overflow = page.evaluate('document.documentElement.scrollWidth - window.innerWidth')
            if overflow > 1:
                raise SystemExit(f'{viewport_name}/{view}: horizontal overflow {overflow}px')

            body_text = page.locator('body').inner_text().lower()
            for leak in public_leaks + english_owned:
                if leak in body_text:
                    raise SystemExit(f'{viewport_name}/{view}: public leak: {leak}')

            h1_count = page.locator('main h1').count()
            if h1_count != 1:
                raise SystemExit(f'{viewport_name}/{view}: expected one main h1, found {h1_count}')

            images = page.locator('main img')
            for image_index in range(images.count()):
                image = images.nth(image_index)
                if not image.get_attribute('src'):
                    raise SystemExit(f'{viewport_name}/{view}: image without src')
                if not image.get_attribute('width') or not image.get_attribute('height'):
                    raise SystemExit(f'{viewport_name}/{view}: image missing intrinsic dimensions')

            if view == 'home' and page.locator('.gloskin-ui1-media').count() < 10:
                raise SystemExit(f'{viewport_name}/home: sparse-state presentation media not fully composed')

            page.keyboard.press('Tab')
            focus_outline = page.evaluate("() => { const s=getComputedStyle(document.activeElement); return [s.outlineStyle,s.outlineWidth].join(':'); }")
            if focus_outline.startswith('none') or focus_outline.endswith(':0px'):
                raise SystemExit(f'{viewport_name}/{view}: keyboard focus indicator missing: {focus_outline}')

            if view == 'home':
                assert_contrast(page, '.gloskin-ui1-hero__copy')
                assert_contrast(page, '.gloskin-ui1-button--primary')

            if viewport_name in ('exhibition', 'large-exhibition') and view == 'home':
                metrics = page.evaluate("""() => {
                    const hero=document.querySelector('.gloskin-ui1-hero__grid').getBoundingClientRect();
                    const container=document.querySelector('.gloskin-ui1-container').getBoundingClientRect();
                    const title=document.querySelector('.gloskin-ui1-hero__title').getBoundingClientRect();
                    return {heroH:hero.height, containerW:container.width, titleW:title.width, vw:innerWidth};
                }""")
                if not (0.50 * width <= metrics['containerW'] <= 0.92 * width):
                    raise SystemExit(f'{viewport_name}/home: container scale looks undersized/overstretched: {metrics}')
                if not (500 <= metrics['heroH'] <= 980):
                    raise SystemExit(f'{viewport_name}/home: hero proportion outside exhibition range: {metrics}')
                if metrics['titleW'] > metrics['containerW'] * 0.62:
                    raise SystemExit(f'{viewport_name}/home: hero line length too wide: {metrics}')

            if viewport_name == 'mobile' and view == 'home':
                opener = page.locator('[data-gloskin-drawer-open]')
                opener.click(); drawer = page.locator('[data-gloskin-drawer]')
                if drawer.get_attribute('aria-hidden') != 'false' or opener.get_attribute('aria-expanded') != 'true':
                    raise SystemExit('mobile/home: drawer ARIA open state failed')
                if not page.evaluate("document.querySelector('[data-gloskin-drawer]').contains(document.activeElement)"):
                    raise SystemExit('mobile/home: focus did not move into drawer')
                toggle = drawer.locator('[data-gloskin-submenu-toggle]').first
                toggle.click(); target_id = toggle.get_attribute('aria-controls')
                if toggle.get_attribute('aria-expanded') != 'true' or page.locator('#' + target_id).get_attribute('hidden') is not None:
                    raise SystemExit('mobile/home: submenu disclosure state failed')
                page.keyboard.press('Escape')
                if drawer.get_attribute('aria-hidden') != 'true' or opener.get_attribute('aria-expanded') != 'false':
                    raise SystemExit('mobile/home: Escape close failed')
                if not opener.evaluate('el => document.activeElement === el'):
                    raise SystemExit('mobile/home: focus return failed')
                opener.click(); page.locator('[data-gloskin-drawer-close]').first.click(position={'x': 2, 'y': 2})
                if drawer.get_attribute('aria-hidden') != 'true':
                    raise SystemExit('mobile/home: backdrop close failed')

            if errors:
                raise SystemExit(f'{viewport_name}/{view}: console/page errors: {errors}')
            page.close()
        print(f'browser smoke passed ({viewport_name} {width}x{height}, {len(views)} views)')

    reduced = browser.new_page(viewport={'width': 390, 'height': 844}, reduced_motion='reduce')
    load(reduced, fixtures['home'])
    if not reduced.evaluate("matchMedia('(prefers-reduced-motion: reduce)').matches"):
        raise SystemExit('reduced-motion media query did not activate')
    duration = reduced.locator('.gloskin-ui1-button').first.evaluate("el => getComputedStyle(el).transitionDuration")
    if duration not in ('0s', '0.00001s', '1e-05s'):
        raise SystemExit(f'reduced-motion transition not minimized: {duration}')
    reduced.close(); print('browser smoke passed (reduced motion)')
    browser.close()

    # Additional engines are mandatory only when their Playwright binaries exist.
    for engine_name in ('firefox', 'webkit'):
        engine = getattr(p, engine_name)
        try:
            extra = engine.launch(headless=True)
        except PlaywrightError:
            print(f'browser smoke unavailable ({engine_name}: engine binary not installed)')
            continue
        page = extra.new_page(viewport={'width': 1440, 'height': 900})
        errors = []
        page.on('console', lambda msg, e=errors: e.append(msg.text) if msg.type == 'error' else None)
        page.on('pageerror', lambda err, e=errors: e.append(str(err)))
        load(page, fixtures['home'])
        if page.evaluate('document.documentElement.scrollWidth - window.innerWidth') > 1 or errors:
            raise SystemExit(f'{engine_name}/home: rendering or console regression')
        page.close(); extra.close()
        print(f'browser smoke passed ({engine_name} home 1440x900)')
