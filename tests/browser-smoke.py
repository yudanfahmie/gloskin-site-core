#!/usr/bin/env python3
from pathlib import Path
import os
import subprocess
from playwright.sync_api import sync_playwright, Error as PlaywrightError

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css'
PRODUCTION_CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-production.css'
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

def fixture(view: str, **extra_env) -> str:
    env = dict(os.environ)
    env['GLOSKIN_FIXTURE_VIEW'] = view
    env.update({key: str(value) for key, value in extra_env.items()})
    return subprocess.check_output(['php', str(ROOT / 'tests/render-fixture.php')], text=True, env=env)

fixtures = {view: fixture(view) for view in views}
home_real_media = fixture('home', GLOSKIN_FIXTURE_REAL_MEDIA=1)

EDITORIAL_STUB = '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1200" viewBox="0 0 1600 1200"><rect width="1600" height="1200" fill="#eaf0f4"/></svg>'

def load(page, html):
    page.route(
        'https://images.unsplash.com/**',
        lambda route: route.fulfill(status=200, content_type='image/svg+xml', body=EDITORIAL_STUB),
    )
    page.set_content(html, wait_until='domcontentloaded')
    page.add_style_tag(path=str(CSS))
    page.add_style_tag(path=str(PRODUCTION_CSS))
    page.add_script_tag(path=str(JS))
    # CSS is injected by the fixture harness after HTML; allow UI transitions and
    # deterministic editorial image responses to settle before measuring.
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
                if not image.evaluate('el => el.complete && el.naturalWidth > 0 && el.naturalHeight > 0'):
                    raise SystemExit(f'{viewport_name}/{view}: broken image source')

            if view == 'home':
                toggles = page.locator('[data-gloskin-submenu-toggle]')
                chevrons = page.locator('[data-gloskin-submenu-toggle] svg.gloskin-ui1-nav__chevron')
                if toggles.count() == 0 or chevrons.count() != toggles.count():
                    raise SystemExit(f'{viewport_name}/home: parent menu SVG chevron coverage failed')
                for toggle_index in range(toggles.count()):
                    toggle = toggles.nth(toggle_index)
                    if '⌄' in toggle.inner_text():
                        raise SystemExit(f'{viewport_name}/home: legacy Unicode disclosure glyph remains')
                    icon = toggle.locator('svg.gloskin-ui1-nav__chevron')
                    if icon.get_attribute('aria-hidden') != 'true' or icon.get_attribute('focusable') != 'false':
                        raise SystemExit(f'{viewport_name}/home: chevron accessibility state failed')

                editorial = page.locator('main img[data-gloskin-editorial="unsplash"]')
                if editorial.count() < 8:
                    raise SystemExit(f'{viewport_name}/home: sparse staging editorial photography missing')
                for editorial_index in range(editorial.count()):
                    src = editorial.nth(editorial_index).get_attribute('src') or ''
                    if not src.startswith('https://images.unsplash.com/photo-'):
                        raise SystemExit(f'{viewport_name}/home: editorial media is not a fixed Unsplash photo URL')

                clinic_placeholders = page.locator('.gloskin-ui1-card--clinic .gloskin-ui1-media--clinic')
                if clinic_placeholders.count() == 0 or page.locator('.gloskin-ui1-card--clinic [data-gloskin-editorial]').count():
                    raise SystemExit(f'{viewport_name}/home: factual clinic empty-state boundary failed')

                body_font = page.locator('body').evaluate('el => getComputedStyle(el).fontFamily')
                heading_font = page.locator('.gloskin-ui1-hero__title').evaluate('el => getComputedStyle(el).fontFamily')
                nav_font = page.locator('.gloskin-ui1-nav__link').first.evaluate('el => getComputedStyle(el).fontFamily')
                if 'Mulish' not in body_font or 'Mulish' not in nav_font or 'Inter' in body_font or 'Inter' in nav_font:
                    raise SystemExit(f'{viewport_name}/home: body/navigation typography did not resolve to Mulish: {body_font}/{nav_font}')
                if 'Marcellus' not in heading_font or 'Georgia' in heading_font:
                    raise SystemExit(f'{viewport_name}/home: display typography did not resolve to Marcellus: {heading_font}')

            if view == 'clinic':
                if page.locator('.gloskin-ui1-detail-hero .gloskin-ui1-media--clinic').count() != 1:
                    raise SystemExit(f'{viewport_name}/clinic: missing factual clinic placeholder')
                if page.locator('.gloskin-ui1-detail-hero [data-gloskin-editorial]').count():
                    raise SystemExit(f'{viewport_name}/clinic: stock photography used as clinic identity')

            if view == 'doctor':
                if page.locator('.gloskin-ui1-detail-hero .gloskin-ui1-media--doctor').count() != 1:
                    raise SystemExit(f'{viewport_name}/doctor: missing factual doctor placeholder')
                if page.locator('.gloskin-ui1-detail-hero [data-gloskin-editorial]').count():
                    raise SystemExit(f'{viewport_name}/doctor: stock photography used as doctor identity')

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

            if viewport_name == 'desktop' and view == 'home':
                header = page.locator('.gloskin-ui1-header')
                desktop_toggle = page.locator('.gloskin-ui1-nav--desktop [data-gloskin-submenu-toggle]').first
                desktop_toggle.click()
                page.wait_for_timeout(200)
                transform = desktop_toggle.locator('.gloskin-ui1-nav__chevron').evaluate('el => getComputedStyle(el).transform')
                if desktop_toggle.get_attribute('aria-expanded') != 'true' or transform in ('none', 'matrix(1, 0, 0, 1, 0, 0)'):
                    raise SystemExit('desktop/home: expanded chevron visual state failed')

                page.evaluate("document.documentElement.style.scrollBehavior='auto'; document.body.style.minHeight='3200px'; window.scrollTo(0, 500)")
                page.wait_for_timeout(80)
                if header.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('desktop/home: header hid while submenu was open')

                desktop_toggle.click()
                page.locator('.gloskin-ui1-brand').focus()
                page.evaluate('window.scrollTo(0, 650)')
                page.wait_for_timeout(80)
                if header.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('desktop/home: header hid while keyboard focus was inside header')

                page.evaluate("document.activeElement && document.activeElement.blur(); window.scrollTo(0, 800)")
                page.wait_for_timeout(80)
                if not header.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('desktop/home: scroll-down header hide state failed')

                page.evaluate('window.scrollTo(0, 740)')
                page.wait_for_timeout(80)
                if header.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('desktop/home: scroll-up header reveal state failed')

                page.evaluate('window.scrollTo(0, 0)')
                page.wait_for_timeout(80)
                if header.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('desktop/home: header did not return to default state at top')

            if viewport_name == 'mobile' and view == 'home':
                opener = page.locator('[data-gloskin-drawer-open]')
                opener.click(); drawer = page.locator('[data-gloskin-drawer]')
                if drawer.get_attribute('aria-hidden') != 'false' or opener.get_attribute('aria-expanded') != 'true':
                    raise SystemExit('mobile/home: drawer ARIA open state failed')
                if not page.evaluate("document.querySelector('[data-gloskin-drawer]').contains(document.activeElement)"):
                    raise SystemExit('mobile/home: focus did not move into drawer')
                toggle = drawer.locator('[data-gloskin-submenu-toggle]').first
                toggle.click(); target_id = toggle.get_attribute('aria-controls')
                page.wait_for_timeout(200)
                chevron_transform = toggle.locator('.gloskin-ui1-nav__chevron').evaluate('el => getComputedStyle(el).transform')
                if toggle.get_attribute('aria-expanded') != 'true' or page.locator('#' + target_id).get_attribute('hidden') is not None:
                    raise SystemExit('mobile/home: submenu disclosure state failed')
                if chevron_transform in ('none', 'matrix(1, 0, 0, 1, 0, 0)'):
                    raise SystemExit('mobile/home: submenu chevron did not rotate')
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

    # Native WordPress hero media must override the staging editorial fallback.
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    load(page, home_real_media)
    hero = page.locator('.gloskin-ui1-hero__media')
    if hero.locator('[data-test-wordpress-media="true"]').count() != 1:
        raise SystemExit('desktop/home: native attachment hero media did not render')
    if hero.locator('[data-gloskin-editorial]').count():
        raise SystemExit('desktop/home: editorial fallback overrode native attachment media')
    page.close()
    print('browser smoke passed (native media priority)')

    admin_bar_cases = [
        ('desktop', 1440, 900, 32, 8, '40px'),
        ('toolbar-782', 782, 1000, 46, 8, '54px'),
        ('toolbar-601', 601, 1000, 46, 8, '54px'),
        ('mobile-absolute', 600, 900, 46, 0, '0px'),
    ]
    for case_name, width, height, toolbar_height, expected_gap, expected_top in admin_bar_cases:
        page = browser.new_page(viewport={'width': width, 'height': height})
        errors = []
        page.on('console', lambda msg, e=errors: e.append(msg.text) if msg.type == 'error' else None)
        page.on('pageerror', lambda err, e=errors: e.append(str(err)))
        load(page, fixtures['home'])
        page.evaluate("""({toolbarHeight, fixedToolbar}) => {
            document.body.classList.add('admin-bar');
            document.documentElement.style.scrollBehavior='auto';
            document.body.style.minHeight='3600px';
            const bar=document.createElement('div');
            bar.id='wpadminbar';
            bar.style.height=toolbarHeight+'px';
            bar.style.left='0';
            bar.style.right='0';
            bar.style.top='0';
            bar.style.zIndex='99999';
            bar.style.transform='none';
            bar.style.position=fixedToolbar ? 'fixed' : 'static';
            document.body.prepend(bar);
        }""", {'toolbarHeight': toolbar_height, 'fixedToolbar': width > 600})
        header = page.locator('.gloskin-ui1-header')
        admin_bar = page.locator('#wpadminbar')
        if header.evaluate('el => getComputedStyle(el).top') != expected_top:
            raise SystemExit(f'admin-bar/{case_name}: unexpected sticky top offset')

        geometry = page.evaluate("""() => {
            const bar=document.querySelector('#wpadminbar').getBoundingClientRect();
            const header=document.querySelector('.gloskin-ui1-header').getBoundingClientRect();
            return {barTop:bar.top,barBottom:bar.bottom,headerTop:header.top,gap:header.top-bar.bottom};
        }""")
        if abs(geometry['gap'] - expected_gap) > 1:
            raise SystemExit(f'admin-bar/{case_name}: actual header/admin geometry gap failed: {geometry}')

        page.evaluate('window.scrollTo(0, 800)')
        page.wait_for_timeout(100)
        if not header.evaluate("el => el.classList.contains('is-hidden')"):
            raise SystemExit(f'admin-bar/{case_name}: 800 down did not hide header')
        page.evaluate('window.scrollTo(0, 1200)')
        page.wait_for_timeout(100)
        if not header.evaluate("el => el.classList.contains('is-hidden')"):
            raise SystemExit(f'admin-bar/{case_name}: 1200 continued-down did not stay hidden')
        page.evaluate('window.scrollTo(0, 1100)')
        page.wait_for_timeout(100)
        if header.evaluate("el => el.classList.contains('is-hidden')"):
            raise SystemExit(f'admin-bar/{case_name}: 1100 reversal-up did not reveal header')
        visible_top = header.evaluate('el => Math.round(el.getBoundingClientRect().top)')
        if visible_top != int(float(expected_top[:-2])):
            raise SystemExit(f'admin-bar/{case_name}: revealed header geometry incorrect: {visible_top}px')
        page.evaluate('window.scrollTo(0, 1500)')
        page.wait_for_timeout(100)
        if not header.evaluate("el => el.classList.contains('is-hidden')"):
            raise SystemExit(f'admin-bar/{case_name}: 1500 reversal-down did not hide header')
        page.evaluate('window.scrollTo(0, 1350)')
        page.wait_for_timeout(100)
        if header.evaluate("el => el.classList.contains('is-hidden')"):
            raise SystemExit(f'admin-bar/{case_name}: 1350 second reversal-up did not reveal header')

        if admin_bar.evaluate('el => getComputedStyle(el).transform') != 'none':
            raise SystemExit(f'admin-bar/{case_name}: admin toolbar was transformed')
        if width > 600 and abs(admin_bar.evaluate('el => el.getBoundingClientRect().top')) > 1:
            raise SystemExit(f'admin-bar/{case_name}: fixed admin toolbar moved during header scrolling')
        if errors:
            raise SystemExit(f'admin-bar/{case_name}: console/page errors: {errors}')
        page.close()
    print('browser smoke passed (WordPress admin geometry + repeated directional sticky state)')

    reduced = browser.new_page(viewport={'width': 390, 'height': 844}, reduced_motion='reduce')
    load(reduced, fixtures['home'])
    if not reduced.evaluate("matchMedia('(prefers-reduced-motion: reduce)').matches"):
        raise SystemExit('reduced-motion media query did not activate')
    duration = reduced.locator('.gloskin-ui1-button').first.evaluate("el => getComputedStyle(el).transitionDuration")
    if duration not in ('0s', '0.00001s', '1e-05s'):
        raise SystemExit(f'reduced-motion transition not minimized: {duration}')
    header_duration = reduced.locator('.gloskin-ui1-header').evaluate("el => getComputedStyle(el).transitionDuration")
    chevron_duration = reduced.locator('.gloskin-ui1-nav__chevron').first.evaluate("el => getComputedStyle(el).transitionDuration")
    if header_duration != '0s' or chevron_duration != '0s':
        raise SystemExit(f'reduced-motion header/chevron transition not disabled: {header_duration}/{chevron_duration}')
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
