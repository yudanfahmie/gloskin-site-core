#!/usr/bin/env python3
from pathlib import Path
import json
import os
import subprocess
from playwright.sync_api import sync_playwright, Error as PlaywrightError

ROOT = Path(__file__).resolve().parents[1]
BASE_CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css'
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
header_widths = [390, 600, 601, 782, 1024, 1440, 1920]
public_leaks = [
    'not configured', 'content pending', 'missing data', 'architecture supports',
    'approved doctor profiles', 'approved treatment categories',
    'woocommerce product data is currently unavailable', 'coming soon',
    'lorem ipsum', 'dummy', 'test product', 'test-001', 'na00000000000',
    'test composition', 'test usage', 'fixture editorial post',
    'woocommerce', 'wordpress', 'mapping', 'pemetaan', 'source data', 'sumber data',
    'template ownership', 'catalog ownership', 'second catalog', 'katalog kedua',
    'kepemilikan produk', 'kepemilikan katalog',
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
    # render-fixture.php uses example.test as its intentionally fake WordPress
    # origin. Fulfill only fixture-owned asset requests locally; REST requests must
    # fall through to the more specific commerce mocks registered by each test.
    def fixture_origin(route):
        if '/wp-json/' in route.request.url:
            route.fallback()
        elif route.request.resource_type == 'document':
            route.fulfill(status=200, content_type='text/html', body='<!doctype html><title>Gloskin browser fixture</title>')
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
    # Establish a normal same-origin document before set_content(). This keeps
    # localStorage-backed wishlist behavior testable instead of running on the
    # opaque about:blank origin used by a bare set_content() page.
    page.goto('https://example.test/__gloskin-browser-fixture', wait_until='domcontentloaded')
    page.set_content(html, wait_until='domcontentloaded')
    # Chromium may legitimately defer off-screen loading=lazy images. Promote only
    # inside the browser fixture so the existing broken-source assertion tests the
    # URL response rather than viewport-dependent lazy-loading heuristics.
    page.locator('main img[loading="lazy"]').evaluate_all("els => els.forEach(el => { el.loading = 'eager'; })")
    page.wait_for_function("() => Array.from(document.querySelectorAll('main img')).every(img => img.complete && img.naturalWidth > 0 && img.naturalHeight > 0)", timeout=5000)
    page.add_style_tag(path=str(BASE_CSS))
    page.add_style_tag(path=str(CSS))
    page.add_style_tag(path=str(PRODUCTION_CSS))
    page.add_script_tag(path=str(JS))
    page.wait_for_timeout(220)


def scroll_beyond_header(page, extra=280):
    metrics = page.evaluate("""extra => {
        const header=document.querySelector('.gloskin-ui1-header');
        const nav=document.querySelector('.gloskin-ui1-header__nav-row');
        const guard=(header?header.offsetHeight:0)+(nav?nav.offsetHeight:0);
        const max=Math.max(0,document.documentElement.scrollHeight-window.innerHeight);
        return {guard,max,target:Math.min(max,guard+extra)};
    }""", extra)
    if metrics['target'] <= metrics['guard'] + 20:
        raise SystemExit(f'page does not provide enough real content to exercise sticky state: {metrics}')
    return metrics


def assert_contrast(page, selector, minimum=4.5):
    locator = page.locator(selector)
    if locator.count() == 0:
        return
    ratio = locator.first.evaluate("""el => {
        const parse = value => {
            const nums = (value.match(/[\\d.]+/g) || []).map(Number);
            if (value.startsWith('color(srgb')) {
                return {r:(nums[0]||0)*255,g:(nums[1]||0)*255,b:(nums[2]||0)*255,a:nums.length>3?nums[3]:1};
            }
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
        let fg=over(parse(getComputedStyle(el).color),bg);
        const lum = c => {
            const vals=[c.r,c.g,c.b].map(v=>v/255).map(v=>v<=0.04045?v/12.92:Math.pow((v+0.055)/1.055,2.4));
            return .2126*vals[0]+.7152*vals[1]+.0722*vals[2];
        };
        const a=lum(bg), b=lum(fg);
        return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
    }""")
    if ratio < minimum:
        raise SystemExit(f'contrast below {minimum}: {selector}={ratio:.2f}')


def assert_dark_surface_contrast(page):
    selectors = [
        '.gloskin-ui1-section--contrast > .gloskin-ui1-container > .gloskin-ui1-section-heading h2',
        '.gloskin-ui1-section--contrast > .gloskin-ui1-container > .gloskin-ui1-section-heading p',
        '.gloskin-ui1-section--contrast > .gloskin-ui1-container > .gloskin-ui1-section__action .gloskin-ui1-text-link',
        '.gloskin-ui1-footer__cta .gloskin-ui1-eyebrow',
        '.gloskin-ui1-footer__cta h2',
        '.gloskin-ui1-closing-cta .gloskin-ui1-eyebrow',
        '.gloskin-ui1-closing-cta h2',
        '.gloskin-ui1-closing-cta__copy > p:not(.gloskin-ui1-eyebrow)',
        '.gloskin-ui1-closing-cta .gloskin-ui1-button--on-dark',
    ]
    for selector in selectors:
        assert_contrast(page, selector)


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

            if page.locator('main h1').count() != 1:
                raise SystemExit(f'{viewport_name}/{view}: expected one main h1')

            images = page.locator('main img')
            for image_index in range(images.count()):
                image = images.nth(image_index)
                if not image.get_attribute('src') or not image.get_attribute('width') or not image.get_attribute('height'):
                    raise SystemExit(f'{viewport_name}/{view}: image source/intrinsic geometry missing')
                if not image.evaluate('el => el.complete && el.naturalWidth > 0 && el.naturalHeight > 0'):
                    raise SystemExit(f'{viewport_name}/{view}: broken image source')

            assert_dark_surface_contrast(page)

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
                    if not (editorial.nth(editorial_index).get_attribute('src') or '').startswith('https://images.unsplash.com/photo-'):
                        raise SystemExit(f'{viewport_name}/home: editorial media is not a fixed Unsplash photo URL')

                clinic_placeholders = page.locator('.gloskin-ui1-card--clinic .gloskin-ui1-media--clinic')
                if clinic_placeholders.count() == 0 or page.locator('.gloskin-ui1-card--clinic [data-gloskin-editorial]').count():
                    raise SystemExit(f'{viewport_name}/home: factual clinic empty-state boundary failed')

                body_font = page.locator('body').evaluate('el => getComputedStyle(el).fontFamily')
                heading_font = page.locator('.gloskin-ui1-hero__title').evaluate('el => getComputedStyle(el).fontFamily')
                nav_font = page.locator('.gloskin-ui1-nav__link').first.evaluate('el => getComputedStyle(el).fontFamily')
                if 'Mulish' not in body_font or 'Mulish' not in nav_font or 'Inter' in body_font or 'Inter' in nav_font:
                    raise SystemExit(f'{viewport_name}/home: Mulish typography failed')
                if 'Marcellus' not in heading_font or 'Georgia' in heading_font:
                    raise SystemExit(f'{viewport_name}/home: Marcellus typography failed')
                assert_contrast(page, '.gloskin-ui1-hero__copy')
                assert_contrast(page, '.gloskin-ui1-button--primary')

            if view == 'clinic':
                if page.locator('.gloskin-ui1-detail-hero .gloskin-ui1-media--clinic').count() != 1 or page.locator('.gloskin-ui1-detail-hero [data-gloskin-editorial]').count():
                    raise SystemExit(f'{viewport_name}/clinic: factual clinic media boundary failed')
            if view == 'doctor':
                if page.locator('.gloskin-ui1-detail-hero .gloskin-ui1-media--doctor').count() != 1 or page.locator('.gloskin-ui1-detail-hero [data-gloskin-editorial]').count():
                    raise SystemExit(f'{viewport_name}/doctor: factual doctor media boundary failed')

            page.keyboard.press('Tab')
            focus_outline = page.evaluate("() => { const s=getComputedStyle(document.activeElement); return [s.outlineStyle,s.outlineWidth].join(':'); }")
            if focus_outline.startswith('none') or focus_outline.endswith(':0px'):
                raise SystemExit(f'{viewport_name}/{view}: keyboard focus indicator missing: {focus_outline}')

            if viewport_name in ('exhibition', 'large-exhibition') and view == 'home':
                metrics = page.evaluate("""() => {
                    const hero=document.querySelector('.gloskin-ui1-hero__grid').getBoundingClientRect();
                    const container=document.querySelector('.gloskin-ui1-container').getBoundingClientRect();
                    const title=document.querySelector('.gloskin-ui1-hero__title').getBoundingClientRect();
                    return {heroH:hero.height,containerW:container.width,titleW:title.width};
                }""")
                if not (0.50 * width <= metrics['containerW'] <= 0.92 * width) or not (500 <= metrics['heroH'] <= 980) or metrics['titleW'] > metrics['containerW'] * 0.62:
                    raise SystemExit(f'{viewport_name}/home: exhibition proportion regression: {metrics}')

            if viewport_name == 'desktop' and view == 'home':
                header = page.locator('.gloskin-ui1-header')
                nav_row = page.locator('.gloskin-ui1-header__nav-row')
                geometry = page.evaluate("""() => {
                    const header=document.querySelector('.gloskin-ui1-header');
                    const bar=document.querySelector('.gloskin-ui1-header__inner');
                    const navRow=document.querySelector('.gloskin-ui1-header__nav-row');
                    const brand=document.querySelector('.gloskin-ui1-brand');
                    const contact=document.querySelector('.gloskin-ui1-header__contact');
                    const main=document.querySelector('.gloskin-ui1-main');
                    const center = el => { const r=el.getBoundingClientRect(); return r.top+r.height/2; };
                    const hcenter = el => { const r=el.getBoundingClientRect(); return r.left+r.width/2; };
                    const hs=getComputedStyle(header), ns=getComputedStyle(navRow);
                    return {
                        barHeight:bar.getBoundingClientRect().height,
                        headerBottom:header.getBoundingClientRect().bottom,
                        navTop:navRow.getBoundingClientRect().top,
                        navBottom:navRow.getBoundingClientRect().bottom,
                        mainTop:main.getBoundingClientRect().top,
                        mainDocTop:main.getBoundingClientRect().top+window.scrollY,
                        brandCenterY:center(brand),
                        contactCenterY:contact?center(contact):null,
                        brandX:hcenter(brand),
                        viewportCenterX:window.innerWidth/2,
                        headerPosition:hs.position,
                        navPosition:ns.position,
                        navStickyTop:ns.top,
                        border:ns.borderBottomWidth,
                        backdrop:ns.backdropFilter||ns.webkitBackdropFilter,
                    };
                }""")
                if not (68 <= geometry['barHeight'] <= 88):
                    raise SystemExit(f'desktop/home: premium header bar density failed: {geometry}')
                if geometry['contactCenterY'] is not None and abs(geometry['brandCenterY'] - geometry['contactCenterY']) > 3:
                    raise SystemExit(f'desktop/home: brand/utility bar alignment failed: {geometry}')
                if abs(geometry['brandX'] - geometry['viewportCenterX']) > 4:
                    raise SystemExit(f'desktop/home: brand is not optically centered: {geometry}')
                if abs(geometry['headerBottom'] - geometry['navTop']) > 1 or abs(geometry['navBottom'] - geometry['mainTop']) > 1:
                    raise SystemExit(f'desktop/home: ghost vertical header gap detected: {geometry}')
                if geometry['headerPosition'] in ('sticky', 'fixed') or geometry['navPosition'] != 'sticky' or geometry['navStickyTop'] != '0px':
                    raise SystemExit(f'desktop/home: sticky ownership failed: {geometry}')
                if geometry['border'] != '1px' or 'blur(' not in geometry['backdrop']:
                    raise SystemExit(f'desktop/home: nav separation/translucency failed: {geometry}')

                real_scroll = scroll_beyond_header(page)
                desktop_toggle = page.locator('.gloskin-ui1-nav--desktop [data-gloskin-submenu-toggle]').first
                desktop_toggle.click(); page.wait_for_timeout(160)
                transform = desktop_toggle.locator('.gloskin-ui1-nav__chevron').evaluate('el => getComputedStyle(el).transform')
                if desktop_toggle.get_attribute('aria-expanded') != 'true' or transform in ('none', 'matrix(1, 0, 0, 1, 0, 0)'):
                    raise SystemExit('desktop/home: expanded chevron visual state failed')
                page.evaluate('(y) => window.scrollTo(0,y)', real_scroll['target']); page.wait_for_timeout(100)
                if nav_row.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('desktop/home: nav hid while submenu was open')
                scrolled = page.evaluate("""() => {
                    const h=document.querySelector('.gloskin-ui1-header').getBoundingClientRect();
                    const n=document.querySelector('.gloskin-ui1-header__nav-row').getBoundingClientRect();
                    const m=document.querySelector('.gloskin-ui1-main').getBoundingClientRect();
                    return {brandBottom:h.bottom,navTop:n.top,mainDocTop:m.top+window.scrollY};
                }""")
                if scrolled['brandBottom'] >= 0 or abs(scrolled['navTop']) > 1:
                    raise SystemExit(f'desktop/home: brand row did not scroll away while nav stayed sticky: {scrolled}')
                if abs(scrolled['mainDocTop'] - geometry['mainDocTop']) > 1:
                    raise SystemExit(f'desktop/home: main layout shifted during sticky state: {scrolled}')

                desktop_toggle.click()
                nav_link = page.locator('.gloskin-ui1-nav--desktop .gloskin-ui1-nav__link').first
                nav_link.focus()
                focus_y = min(real_scroll['max'], real_scroll['target'] + 90)
                page.evaluate('(y) => window.scrollTo(0,y)', focus_y); page.wait_for_timeout(90)
                if nav_row.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('desktop/home: nav hid while keyboard focus was inside')
                page.evaluate('document.activeElement && document.activeElement.blur()')
                hide_y = min(real_scroll['max'], focus_y + 120)
                page.evaluate('(y) => window.scrollTo(0,y)', hide_y); page.wait_for_timeout(100)
                if not nav_row.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('desktop/home: scroll-down nav hide failed')
                page.evaluate('(y) => window.scrollTo(0,y)', max(real_scroll['guard'] + 30, hide_y - 50)); page.wait_for_timeout(90)
                if nav_row.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('desktop/home: immediate scroll-up nav reveal failed')

                down_again = min(real_scroll['max'], hide_y + 40)
                page.evaluate('(y) => window.scrollTo(0,y)', down_again); page.wait_for_timeout(90)
                if not nav_row.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('desktop/home: second scroll-down nav hide failed')
                page.evaluate("document.querySelector('[data-gloskin-search-open]').click()")
                page.wait_for_timeout(80)
                if nav_row.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('desktop/home: nav stayed hidden while Search overlay opened')
                page.keyboard.press('Escape'); page.wait_for_timeout(350)
                page.evaluate('window.scrollTo(0,0)'); page.wait_for_timeout(80)

            if viewport_name == 'mobile' and view == 'home':
                opener = page.locator('[data-gloskin-drawer-open]'); opener.click()
                drawer = page.locator('[data-gloskin-drawer]')
                nav_row = page.locator('.gloskin-ui1-header__nav-row')
                if drawer.get_attribute('aria-hidden') != 'false' or opener.get_attribute('aria-expanded') != 'true' or not page.evaluate("document.querySelector('[data-gloskin-drawer]').contains(document.activeElement)"):
                    raise SystemExit('mobile/home: drawer open/focus state failed')
                if nav_row.evaluate("el => el.classList.contains('is-hidden')"):
                    raise SystemExit('mobile/home: drawer open left nav controller hidden')
                toggle = drawer.locator('[data-gloskin-submenu-toggle]').first; toggle.click(); target_id = toggle.get_attribute('aria-controls'); page.wait_for_timeout(160)
                if toggle.get_attribute('aria-expanded') != 'true' or page.locator('#' + target_id).get_attribute('hidden') is not None:
                    raise SystemExit('mobile/home: submenu disclosure state failed')
                page.keyboard.press('Escape')
                if drawer.get_attribute('aria-hidden') != 'true' or opener.get_attribute('aria-expanded') != 'false' or not opener.evaluate('el => document.activeElement === el'):
                    raise SystemExit('mobile/home: Escape/focus return failed')
                opener.click(); page.locator('[data-gloskin-drawer-close]').first.click(position={'x': 2, 'y': 2})
                if drawer.get_attribute('aria-hidden') != 'true':
                    raise SystemExit('mobile/home: backdrop close failed')

            if errors:
                raise SystemExit(f'{viewport_name}/{view}: console/page errors: {errors}')
            page.close()
        print(f'browser smoke passed ({viewport_name} {width}x{height}, {len(views)} views)')

    for width in header_widths:
        height = 1000 if width < 1200 else 900
        page = browser.new_page(viewport={'width': width, 'height': height})
        load(page, fixtures['home'])
        geometry = page.evaluate("""() => {
            const h=document.querySelector('.gloskin-ui1-header').getBoundingClientRect();
            const n=document.querySelector('.gloskin-ui1-header__nav-row');
            const nr=n.getBoundingClientRect();
            const m=document.querySelector('.gloskin-ui1-main').getBoundingClientRect();
            const b=document.querySelector('.gloskin-ui1-brand').getBoundingClientRect();
            const ns=getComputedStyle(n), hs=getComputedStyle(document.querySelector('.gloskin-ui1-header'));
            return {headerBottom:h.bottom,navTop:nr.top,navBottom:nr.bottom,mainTop:m.top,navDisplay:ns.display,headerPosition:hs.position,brandCenter:b.left+b.width/2,overflow:document.documentElement.scrollWidth-window.innerWidth};
        }""")
        if geometry['overflow'] > 1 or abs(geometry['brandCenter'] - width / 2) > 4 or geometry['headerPosition'] in ('sticky', 'fixed'):
            raise SystemExit(f'header-width/{width}: overflow/centering/sticky-owner regression: {geometry}')
        if geometry['navDisplay'] == 'none':
            if abs(geometry['headerBottom'] - geometry['mainTop']) > 1:
                raise SystemExit(f'header-width/{width}: compact header ghost gap: {geometry}')
        else:
            if abs(geometry['headerBottom'] - geometry['navTop']) > 1 or abs(geometry['navBottom'] - geometry['mainTop']) > 1:
                raise SystemExit(f'header-width/{width}: layered header ghost gap: {geometry}')
        page.close()
    print('browser smoke passed (header computed geometry: 390/600/601/782/1024/1440/1920)')

    # ------------------------------------------------------------------
    # Owner-presentation polish: canonical logo, favicon fallback, compact
    # branded sticky-nav state, 6-dot mobile trigger.
    # ------------------------------------------------------------------

    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    load(page, fixtures['home'])
    logo_check = page.evaluate("""() => {
        const imgs = Array.from(document.querySelectorAll('.gloskin-ui1-brand__image'));
        const brandTexts = Array.from(document.querySelectorAll('.gloskin-ui1-brand, .gloskin-ui1-brand--footer'))
            .map(el => Array.from(el.childNodes).filter(n => n.nodeType === 3 && n.textContent.trim()).map(n => n.textContent.trim()))
            .flat();
        return {
            count: imgs.length,
            allHaveDims: imgs.every(img => img.getAttribute('width') && img.getAttribute('height')),
            allLoaded: imgs.every(img => img.complete && img.naturalWidth > 0 && img.naturalHeight > 0),
            aspectRatiosMatch: imgs.every(img => Math.abs((img.naturalWidth / img.naturalHeight) - (1600/520)) < 0.01),
            residualTextWordmarks: brandTexts,
        };
    }""")
    if logo_check['count'] < 2:
        raise SystemExit(f'logo: expected header + footer logo images, found: {logo_check}')
    if not logo_check['allHaveDims']:
        raise SystemExit(f'logo: missing explicit intrinsic width/height (CLS risk): {logo_check}')
    if not logo_check['allLoaded']:
        raise SystemExit(f'logo: canonical SVG failed to load: {logo_check}')
    if not logo_check['aspectRatiosMatch']:
        raise SystemExit(f'logo: aspect ratio distorted: {logo_check}')
    if logo_check['residualTextWordmarks']:
        raise SystemExit(f'logo: text-only wordmark duplicate still present: {logo_check}')
    page.close()
    print('browser smoke passed (canonical logo: header + footer, no CLS, text wordmark removed)')

    # Favicon: Gloskin's branded set is the sole canonical owner regardless of
    # any WordPress Site Icon, and the native wp_site_icon() output must stay
    # unhooked either way so the two never both render.
    no_icon_html = fixture('home')
    with_icon_html = fixture('home', GLOSKIN_FIXTURE_SITE_ICON='1')
    expected_favicons = ['favicon.ico', 'favicon-16x16.png', 'favicon-32x32.png', 'icon-192.png', 'icon-512.png', 'apple-touch-icon.png']
    if not all(name in no_icon_html for name in expected_favicons):
        raise SystemExit('favicon: Gloskin favicon missing when no Site Icon is configured')
    if not all(name in with_icon_html for name in expected_favicons):
        raise SystemExit('favicon: Gloskin favicon missing despite a configured Site Icon (must always win)')
    if 'stale-wp-site-icon.png' in with_icon_html:
        raise SystemExit('favicon: native wp_site_icon() was not unhooked -- duplicate/stale icon rendered')
    print('browser smoke passed (favicon: Gloskin favicon always wins, native Site Icon unhooked)')

    # All 7 derived favicon files exist and are reachable.
    images_base = ROOT / 'plugin/gloskin-site-core/assets/images'
    for name in ['favicon-master-g.png'] + expected_favicons:
        if not (images_base / name).is_file():
            raise SystemExit(f'favicon: derivative file missing on disk: {name}')
    print('browser smoke passed (favicon derivatives present on disk)')

    # Compact branded sticky-nav state: small logo + centered nav + compact
    # utilities appear only after the brand row has fully scrolled away.
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    load(page, fixtures['home'])
    nav_row = page.locator('.gloskin-ui1-header__nav-row')
    if nav_row.evaluate("el => el.classList.contains('is-compact-sticky')"):
        raise SystemExit('compact-sticky: activated at page top before the brand row scrolled away')
    compact_pre = page.evaluate("""() => {
        const b = document.querySelector('.gloskin-ui1-compact-brand');
        const z = document.querySelector('.gloskin-ui1-header__zone--compact');
        return { brandInert: b.inert, zoneInert: z.inert };
    }""")
    if not compact_pre['brandInert'] or not compact_pre['zoneInert']:
        raise SystemExit('compact-sticky: collapsed compact controls must stay inert at page top')

    real_scroll = scroll_beyond_header(page, 260)
    page.evaluate('(y) => window.scrollTo(0,y)', real_scroll['target'])
    page.wait_for_timeout(250)
    if not nav_row.evaluate("el => el.classList.contains('is-compact-sticky')"):
        raise SystemExit('compact-sticky: did not activate after the brand row fully left the viewport')
    compact_geometry = page.evaluate("""() => {
        const navList = document.querySelector('.gloskin-ui1-nav--desktop .gloskin-ui1-nav__list').getBoundingClientRect();
        const brand = document.querySelector('.gloskin-ui1-compact-brand');
        const zone = document.querySelector('.gloskin-ui1-header__zone--compact');
        const brandImg = brand.querySelector('img');
        return {
            navCenterX: navList.left + navList.width / 2,
            viewportCenterX: window.innerWidth / 2,
            brandInert: brand.inert,
            zoneInert: zone.inert,
            brandImgVisible: brandImg.getBoundingClientRect().width > 0,
            searchReachable: !!zone.querySelector('[data-gloskin-search-open]'),
        };
    }""")
    if abs(compact_geometry['navCenterX'] - compact_geometry['viewportCenterX']) > 6:
        raise SystemExit(f'compact-sticky: nav not centered in compact state: {compact_geometry}')
    if compact_geometry['brandInert'] or compact_geometry['zoneInert']:
        raise SystemExit(f'compact-sticky: controls still inert once active: {compact_geometry}')
    if not compact_geometry['brandImgVisible']:
        raise SystemExit(f'compact-sticky: small logo did not become visible: {compact_geometry}')
    if not compact_geometry['searchReachable']:
        raise SystemExit('compact-sticky: search trigger missing from compact utilities')
    page.evaluate('window.scrollTo(0,0)'); page.wait_for_timeout(200)
    if nav_row.evaluate("el => el.classList.contains('is-compact-sticky')"):
        raise SystemExit('compact-sticky: did not deactivate when scrolled back to the top')
    page.close()
    print('browser smoke passed (compact branded sticky-nav state)')

    # 6-dot mobile trigger: no legacy 3-line hamburger, dots converge to one point.
    page = browser.new_page(viewport={'width': 390, 'height': 844})
    load(page, fixtures['home'])
    toggle = page.locator('[data-gloskin-drawer-open]')
    dot_check = page.evaluate("""() => {
        const dots = document.querySelectorAll('.gloskin-ui1-drawer-toggle__dot');
        const legacyPath = document.querySelector('.gloskin-ui1-drawer-toggle svg path');
        return { dotCount: dots.length, hasLegacyPath: !!legacyPath };
    }""")
    if dot_check['dotCount'] != 6:
        raise SystemExit(f'6-dot trigger: expected 6 dots, found {dot_check["dotCount"]}')
    if dot_check['hasLegacyPath']:
        raise SystemExit('6-dot trigger: legacy 3-line hamburger path still present')
    toggle.click(); page.wait_for_timeout(220)
    converge = page.evaluate("""() => {
        const dots = Array.from(document.querySelectorAll('.gloskin-ui1-drawer-toggle__dot'));
        const points = dots.map(d => { const r = d.getBoundingClientRect(); return [r.left + r.width/2, r.top + r.height/2]; });
        const xs = points.map(p => p[0]), ys = points.map(p => p[1]);
        return { spreadX: Math.max(...xs) - Math.min(...xs), spreadY: Math.max(...ys) - Math.min(...ys) };
    }""")
    if converge['spreadX'] > 1 or converge['spreadY'] > 1:
        raise SystemExit(f'6-dot trigger: dots did not converge to one point when open: {converge}')
    if toggle.get_attribute('aria-expanded') != 'true':
        raise SystemExit('6-dot trigger: aria-expanded not set on open')
    page.close()
    print('browser smoke passed (6-dot mobile trigger: converges to one point, no legacy hamburger)')

    # Reduced motion: compact-sticky and the 6-dot trigger apply their target
    # state immediately, without a transition, matching the rest of the header.
    page = browser.new_page(viewport={'width': 1440, 'height': 900}, reduced_motion='reduce')
    load(page, fixtures['home'])
    reduced_transitions = page.evaluate("""() => {
        const brand = document.querySelector('.gloskin-ui1-compact-brand');
        const dot = document.querySelector('.gloskin-ui1-drawer-toggle__dot');
        return { brand: getComputedStyle(brand).transitionDuration, dot: getComputedStyle(dot).transitionDuration };
    }""")
    if reduced_transitions['brand'] != '0s' or reduced_transitions['dot'] != '0s':
        raise SystemExit(f'reduced-motion: compact-sticky/6-dot transitions not disabled: {reduced_transitions}')
    page.close()
    print('browser smoke passed (reduced motion: compact-sticky + 6-dot trigger)')

    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    load(page, home_real_media)
    hero = page.locator('.gloskin-ui1-hero__media')
    if hero.locator('[data-test-wordpress-media="true"]').count() != 1 or hero.locator('[data-gloskin-editorial]').count():
        raise SystemExit('desktop/home: native attachment hero media priority failed')
    page.close(); print('browser smoke passed (native media priority)')

    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    load(page, fixtures['about'])
    page.evaluate("""() => {
        document.body.classList.remove('gloskin-ui1--medical'); document.body.classList.add('gloskin-ui1--luxury');
        const s=document.createElement('section'); s.className='gloskin-ui1-section gloskin-ui1-section--contrast';
        s.innerHTML='<div class="gloskin-ui1-container"><p class="gloskin-ui1-eyebrow">Gloskin</p><div class="gloskin-ui1-section-heading"><h2>Contrast</h2><p>Supporting copy</p></div><p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="#contrast">Link</a></p></div>';
        document.body.appendChild(s);
    }""")
    assert_dark_surface_contrast(page)
    page.close(); print('browser smoke passed (contrast surface tokens)')

    # expected_gap is the distance between the admin bar's bottom edge and the
    # sticky nav row's top edge once revealed -- must be 0 (no ghost strip),
    # regardless of admin-bar height at each breakpoint.
    admin_bar_cases = [
        ('desktop', 1440, 900, 32, 0, '32px'), ('toolbar-782', 782, 1000, 46, 0, '46px'),
        ('toolbar-601', 601, 1000, 46, 0, '46px'), ('mobile-absolute', 600, 900, 46, 0, '0px'),
    ]
    for case_name, width, height, toolbar_height, expected_gap, expected_top in admin_bar_cases:
        page = browser.new_page(viewport={'width': width, 'height': height}); errors = []
        page.on('console', lambda msg, e=errors: e.append(msg.text) if msg.type == 'error' else None)
        page.on('pageerror', lambda err, e=errors: e.append(str(err))); load(page, fixtures['home'])
        page.evaluate("""({toolbarHeight,fixedToolbar}) => {
            document.body.classList.add('admin-bar'); document.documentElement.style.scrollBehavior='auto';
            const bar=document.createElement('div'); bar.id='wpadminbar'; bar.style.height=toolbarHeight+'px'; bar.style.left='0'; bar.style.right='0'; bar.style.top='0'; bar.style.zIndex='99999'; bar.style.transform='none'; bar.style.position=fixedToolbar?'fixed':'static'; document.body.prepend(bar);
        }""", {'toolbarHeight': toolbar_height, 'fixedToolbar': width > 600})
        nav_row=page.locator('.gloskin-ui1-header__nav-row'); admin_bar=page.locator('#wpadminbar')
        if nav_row.evaluate('el => getComputedStyle(el).top') != expected_top:
            raise SystemExit(f'admin-bar/{case_name}: unexpected canonical nav sticky top offset')
        if width > 1040:
            real_scroll = scroll_beyond_header(page, 360)
            page.evaluate('(y) => window.scrollTo(0,y)', real_scroll['target']); page.wait_for_timeout(100)
            if not nav_row.evaluate("el => el.classList.contains('is-hidden')"):
                raise SystemExit(f'admin-bar/{case_name}: scroll-down hide failed')
            reveal_y = max(real_scroll['guard'] + 30, real_scroll['target'] - 70)
            page.evaluate('(y) => window.scrollTo(0,y)', reveal_y); page.wait_for_timeout(260)
            if nav_row.evaluate("el => el.classList.contains('is-hidden')"):
                raise SystemExit(f'admin-bar/{case_name}: scroll-up reveal failed')
            geometry=page.evaluate("""() => { const b=document.querySelector('#wpadminbar').getBoundingClientRect(),n=document.querySelector('.gloskin-ui1-header__nav-row').getBoundingClientRect(); return {barTop:b.top,barBottom:b.bottom,navTop:n.top,gap:n.top-b.bottom}; }""")
            if abs(geometry['gap']-expected_gap)>1:
                raise SystemExit(f'admin-bar/{case_name}: sticky nav geometry gap failed: {geometry}')
            down_y = min(real_scroll['max'], reveal_y + 110)
            page.evaluate('(y) => window.scrollTo(0,y)', down_y); page.wait_for_timeout(100)
            if not nav_row.evaluate("el => el.classList.contains('is-hidden')"):
                raise SystemExit(f'admin-bar/{case_name}: repeated scroll-down hide failed')
            page.evaluate('(y) => window.scrollTo(0,y)', max(real_scroll['guard'] + 30, down_y - 45)); page.wait_for_timeout(90)
            if nav_row.evaluate("el => el.classList.contains('is-hidden')"):
                raise SystemExit(f'admin-bar/{case_name}: repeated scroll-up reveal failed')
        if admin_bar.evaluate('el => getComputedStyle(el).transform') != 'none' or (width > 600 and abs(admin_bar.evaluate('el => el.getBoundingClientRect().top')) > 1):
            raise SystemExit(f'admin-bar/{case_name}: toolbar moved/transformed')
        if errors: raise SystemExit(f'admin-bar/{case_name}: console/page errors: {errors}')
        page.close()
    print('browser smoke passed (WordPress admin geometry + canonical nav offset)')

    def mock_search(route):
        payload = {'groups': [{'type': 'produk', 'label': 'Produk', 'items': [{
            'id': 1, 'title': 'Test Product', 'url': 'https://example.test/produk/test-product/',
            'excerpt': 'Deskripsi singkat produk.', 'price_html': '<span class="amount">Rp 150.000</span>',
        }]}]}
        route.fulfill(status=200, content_type='application/json', body=json.dumps(payload))

    def mock_resolve(route):
        payload = {'products': [{
            'id': 1, 'name': 'Test Product', 'url': 'https://example.test/produk/test-product/',
            'price_html': '<span class="amount">Rp 150.000</span>', 'image_id': 0,
        }]}
        route.fulfill(status=200, content_type='application/json', body=json.dumps(payload))

    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    errors = []
    page.on('console', lambda msg, e=errors: e.append(msg.text) if msg.type == 'error' else None)
    page.on('pageerror', lambda err, e=errors: e.append(str(err)))
    load(page, fixtures['shop'])
    # The layered header has one primary search trigger plus a compact-sticky
    # trigger. Assert the primary owner rather than treating the compact duplicate
    # presentation as a commerce-control duplication.
    if page.locator('.gloskin-ui1-header__inner [data-gloskin-search-open]').count() != 1:
        raise SystemExit('commerce/woo-absent: primary search trigger missing')
    if (page.locator('[data-gloskin-cart-open]').count()
            or page.locator('[data-gloskin-wishlist-open]').count()
            or page.locator('.gloskin-ui1-utility-btn--account').count()):
        raise SystemExit('commerce/woo-absent: commerce controls unexpectedly present')
    if page.evaluate('document.documentElement.scrollWidth - window.innerWidth') > 1:
        raise SystemExit('commerce/woo-absent: header overflow/imbalance')
    if errors:
        raise SystemExit(f'commerce/woo-absent: console/page errors: {errors}')
    page.close()
    print('browser smoke passed (commerce header: Woo absent)')

    woo_shop_html = fixture('shop', GL_TEST_WOO='1')
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    errors = []
    page.on('console', lambda msg, e=errors: e.append(msg.text) if msg.type == 'error' else None)
    page.on('pageerror', lambda err, e=errors: e.append(str(err)))
    page.route('**/wp-json/gloskin/v1/search**', mock_search)
    page.route('**/wp-json/gloskin/v1/products/resolve**', mock_resolve)
    load(page, woo_shop_html)

    primary_tools = '.gloskin-ui1-header__inner'
    compact_tools = '.gloskin-ui1-header__zone--compact'
    for selector, label in (
        ('.gloskin-ui1-utility-btn--account', 'account'),
        ('[data-gloskin-wishlist-open]', 'wishlist'),
        ('[data-gloskin-cart-open]', 'cart'),
    ):
        if page.locator(f'{primary_tools} {selector}').count() != 1 or page.locator(f'{compact_tools} {selector}').count() != 1:
            raise SystemExit(f'commerce/woo-active: layered {label} controls missing or duplicated')
    if page.locator('.gloskin-ui1-header__nav-row').count() != 1:
        raise SystemExit('commerce/woo-active: two-layer nav row missing')
    badge_text = page.locator(f'{primary_tools} [data-gloskin-cart-count]').inner_text().strip()
    if badge_text != '2':
        raise SystemExit(f'commerce/woo-active: cart badge incorrect: {badge_text!r}')

    nav_row = page.locator('.gloskin-ui1-header__nav-row')
    real_scroll = scroll_beyond_header(page, 320)
    page.evaluate('(y) => window.scrollTo(0,y)', real_scroll['target']); page.wait_for_timeout(100)
    if not nav_row.evaluate("el => el.classList.contains('is-hidden')"):
        raise SystemExit('commerce/woo-active: nav did not hide before overlay safeguard test')
    page.evaluate("document.querySelector('.gloskin-ui1-header__inner [data-gloskin-cart-open]').click()")
    page.wait_for_timeout(80)
    if nav_row.evaluate("el => el.classList.contains('is-hidden')"):
        raise SystemExit('commerce/woo-active: nav stayed hidden while cart sheet opened')
    page.keyboard.press('Escape'); page.wait_for_timeout(350)
    page.evaluate('window.scrollTo(0,0)'); page.wait_for_timeout(80)

    search_trigger = page.locator('.gloskin-ui1-header__inner [data-gloskin-search-open]')
    search_trigger.click(); page.wait_for_timeout(80)
    search_overlay = page.locator('[data-gloskin-overlay="search"]')
    if search_overlay.get_attribute('aria-hidden') != 'false':
        raise SystemExit('commerce/woo-active: search overlay did not open')
    search_input = page.locator('[data-gloskin-search-input]')
    if not search_input.evaluate('el => el === document.activeElement'):
        raise SystemExit('commerce/woo-active: search input did not receive focus')
    search_input.fill('produk'); page.wait_for_timeout(400)
    if page.locator('.gloskin-ui1-search-results__price').count() == 0:
        raise SystemExit('commerce/woo-active: product search result missing price')
    page.keyboard.press('Escape'); page.wait_for_timeout(30)
    if search_overlay.get_attribute('hidden') is not None:
        raise SystemExit('commerce/woo-active: overlay hid before its exit transition completed')
    page.wait_for_timeout(350)
    if search_overlay.get_attribute('hidden') is None:
        raise SystemExit('commerce/woo-active: overlay never finalized hidden state after close')
    if not search_trigger.evaluate('el => el === document.activeElement'):
        raise SystemExit('commerce/woo-active: focus did not return to the search trigger')

    wishlist_toggle = page.locator('[data-gloskin-wishlist-toggle]').first
    if wishlist_toggle.count() == 0:
        raise SystemExit('commerce/woo-active: no wishlist toggle on product card')
    wishlist_toggle.click(); page.wait_for_timeout(50)
    if wishlist_toggle.get_attribute('aria-pressed') != 'true':
        raise SystemExit('commerce/woo-active: wishlist toggle did not report pressed state')
    page.locator('.gloskin-ui1-header__inner [data-gloskin-wishlist-open]').click(); page.wait_for_timeout(250)
    wishlist_sheet = page.locator('[data-gloskin-overlay="wishlist"]')
    if wishlist_sheet.get_attribute('aria-hidden') != 'false':
        raise SystemExit('commerce/woo-active: wishlist sheet did not open')
    if page.locator('[data-gloskin-wishlist-body] .gloskin-ui1-wishlist-sheet__item').count() == 0:
        raise SystemExit('commerce/woo-active: wishlist sheet did not list the resolved product')
    page.keyboard.press('Escape'); page.wait_for_timeout(350)

    cart_trigger = page.locator('.gloskin-ui1-header__inner [data-gloskin-cart-open]')
    cart_trigger.click(); page.wait_for_timeout(80)
    cart_sheet = page.locator('[data-gloskin-overlay="cart"]')
    if cart_sheet.get_attribute('aria-hidden') != 'false':
        raise SystemExit('commerce/woo-active: cart sheet did not open')
    if page.locator('.gloskin-ui1-cart-sheet__item-media').count() == 0:
        raise SystemExit('commerce/woo-active: cart item media slot missing')
    if page.locator('.gloskin-ui1-cart-sheet__item-name').count() == 0:
        raise SystemExit('commerce/woo-active: cart item name missing')
    if page.locator('.gloskin-ui1-cart-sheet__item-variation').count() == 0:
        raise SystemExit('commerce/woo-active: cart item variation missing')
    if page.locator('.gloskin-ui1-cart-sheet__summary').count() == 0:
        raise SystemExit('commerce/woo-active: cart subtotal summary missing')
    checkout_href = page.locator('.gloskin-ui1-cart-sheet__actions a').first.get_attribute('href') or ''
    if not checkout_href.startswith('https://example.test/'):
        raise SystemExit(f'commerce/woo-active: checkout/cart link not canonical: {checkout_href!r}')
    page.locator('[data-gloskin-overlay="cart"] .gloskin-ui1-sheet__backdrop').click(force=True); page.wait_for_timeout(350)
    if cart_sheet.get_attribute('hidden') is None:
        raise SystemExit('commerce/woo-active: cart sheet did not close via backdrop')
    if not cart_trigger.evaluate('el => el === document.activeElement'):
        raise SystemExit('commerce/woo-active: focus did not return to the cart trigger')

    if errors:
        raise SystemExit(f'commerce/woo-active: console/page errors: {errors}')
    page.close()
    print('browser smoke passed (commerce header: Woo active)')

    page = browser.new_page(viewport={'width': 390, 'height': 844})
    page.route('**/wp-json/gloskin/v1/search**', mock_search)
    page.route('**/wp-json/gloskin/v1/products/resolve**', mock_resolve)
    load(page, woo_shop_html)
    if page.evaluate('document.documentElement.scrollWidth - window.innerWidth') > 1:
        raise SystemExit('commerce/mobile: header overflow')
    if page.locator('.gloskin-ui1-utility-btn--account:visible').count() or page.locator('.gloskin-ui1-utility-btn--wishlist:visible').count():
        raise SystemExit('commerce/mobile: account/wishlist crowd the mobile header')
    mobile_cart = page.locator('.gloskin-ui1-header__inner [data-gloskin-cart-open]')
    if mobile_cart.count() != 1 or not mobile_cart.is_visible():
        raise SystemExit('commerce/mobile: primary cart trigger missing or hidden on mobile')
    page.locator('[data-gloskin-drawer-open]').click(); page.wait_for_timeout(80)
    if page.locator('.gloskin-ui1-drawer__utility-link').count() < 2:
        raise SystemExit('commerce/mobile: account/wishlist not reachable from the drawer')
    page.close()
    print('browser smoke passed (commerce header: mobile)')

    page = browser.new_page(viewport={'width': 1440, 'height': 900}, reduced_motion='reduce')
    load(page, woo_shop_html)
    page.locator('.gloskin-ui1-header__inner [data-gloskin-cart-open]').click(); page.wait_for_timeout(50)
    page.keyboard.press('Escape'); page.wait_for_timeout(30)
    if page.locator('[data-gloskin-overlay="cart"]').get_attribute('hidden') is None:
        raise SystemExit('commerce/reduced-motion: cart sheet did not close immediately')
    page.close()
    print('browser smoke passed (commerce header: reduced motion)')

    reduced=browser.new_page(viewport={'width':390,'height':844},reduced_motion='reduce'); load(reduced,fixtures['home'])
    if not reduced.evaluate("matchMedia('(prefers-reduced-motion: reduce)').matches"):
        raise SystemExit('reduced-motion media query did not activate')
    if reduced.locator('.gloskin-ui1-header__nav-row').evaluate('el => getComputedStyle(el).transitionDuration') != '0s' or reduced.locator('.gloskin-ui1-nav__chevron').first.evaluate('el => getComputedStyle(el).transitionDuration') != '0s':
        raise SystemExit('reduced-motion nav/chevron transition not disabled')
    reduced.close(); print('browser smoke passed (reduced motion)'); browser.close()

    for engine_name in ('firefox','webkit'):
        engine=getattr(p,engine_name)
        try: extra=engine.launch(headless=True)
        except PlaywrightError:
            print(f'browser smoke unavailable ({engine_name}: engine binary not installed)'); continue
        page=extra.new_page(viewport={'width':1440,'height':900}); errors=[]
        page.on('console',lambda msg,e=errors:e.append(msg.text) if msg.type=='error' else None); page.on('pageerror',lambda err,e=errors:e.append(str(err)))
        load(page,fixtures['home'])
        if page.evaluate('document.documentElement.scrollWidth - window.innerWidth') > 1 or errors:
            raise SystemExit(f'{engine_name}/home: rendering or console regression')
        page.close(); extra.close(); print(f'browser smoke passed ({engine_name} home 1440x900)')
