#!/usr/bin/env python3
"""Browser smoke for Shop rail geometry, one request owner, and price states."""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from threading import Thread
from urllib.parse import parse_qs, urlparse
import json
import sys
import time

ROOT = Path(__file__).resolve().parents[1]
OWNER = (ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-shop-discovery.js').read_text(encoding='utf-8')
SHOP_CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-shop-discovery.css').read_text(encoding='utf-8')
REQUESTS = []
FAIL_ONCE = {'fail': True}

LAYOUT_HTML = '''<!doctype html><html><head><style>
:root{--gloskin-ui1-nav-sticky-top:0px;--gloskin-accent:#b12e2f;--gloskin-accent-readable:#b12e2f;--gloskin-accent-strong:#961f24;--gloskin-accent-soft:#f8e9e9;--gloskin-brand-champagne:#8f7953;--gloskin-border:#ddd7d3;--gloskin-muted:#6f6667;--gloskin-text:#2a232c;--gloskin-surface:#f6f3f1;--gloskin-surface-strong:#ecebe8;--gloskin-field-focus-ring:0 0 0 3px #f8e9e9;--gloskin-radius-sm:10px}
*{box-sizing:border-box}body{margin:0}.gloskin-ui1-shop-catalog{display:grid;grid-template-columns:minmax(210px,240px) minmax(0,1fr);align-items:start;gap:48px;max-width:1100px;margin:0 auto}.results-fixture{height:2200px}
/* Simulate the older core structural owner. Shop discovery CSS loads after it
   and must neutralize category-only sticky/right-border/horizontal-mobile rules. */
.gloskin-ui1-shop-categories{position:sticky;top:76px;padding:2px 18px 2px 0;border-right:1px solid #000}
@media(max-width:900px){.gloskin-ui1-shop-catalog{grid-template-columns:minmax(0,1fr);gap:24px}.gloskin-ui1-shop-categories{position:static;top:auto;overflow-x:auto;padding:0 0 8px;border-right:0;border-bottom:1px solid #000}.gloskin-ui1-shop-categories ul{display:flex;width:max-content;min-width:100%}}
</style><style>''' + SHOP_CSS + '''</style></head><body>
<div data-gloskin-shop-catalog-owner>
<div class="gloskin-ui1-shop-catalog">
<aside class="gloskin-ui1-shop-catalog__rail">
<div class="gloskin-ui1-shop-rail-section"><span class="gloskin-ui1-shop-rail-section__label">Pencarian</span><form class="gloskin-ui1-shop-search-field"><input type="search"></form></div>
<div class="gloskin-ui1-shop-rail-section"><span class="gloskin-ui1-shop-rail-section__label">Harga</span><div class="gloskin-ui1-price-filter" data-gloskin-price-state="normal"><div class="gloskin-ui1-price-filter__labels"><span>Rp 100.000</span><span class="gloskin-ui1-price-filter__label-sep">–</span><span>Rp 500.000</span></div><div class="gloskin-ui1-price-slider"><div class="gloskin-ui1-price-slider__track"></div></div></div></div>
<div class="gloskin-ui1-shop-rail-section"><span class="gloskin-ui1-shop-rail-section__label">Kategori</span><nav class="gloskin-ui1-shop-categories"><ul><li><a>Semua Produk</a></li><li><a>Serum</a></li></ul></nav></div>
<button class="gloskin-ui1-shop-filter__clear">Hapus semua filter</button>
</aside>
<div class="results-fixture"></div>
</div></div>
</body></html>'''

HTML = '''<!doctype html><html><head>
<script>window.gloskinData={woo:false,restUrl:'/wp-json/gloskin/v1/'};</script>
<script src="/owner.js"></script>
</head><body>
<section data-gloskin-shop-catalog-owner data-gloskin-shop-initial-page="1" data-gloskin-shop-url="/shop/">
<nav data-gloskin-shop-categories>
<a href="/shop/" data-gloskin-shop-category="">All</a>
<a href="/shop/#category=serum" data-gloskin-shop-category="serum">Serum</a>
</nav>
<form data-gloskin-shop-search-form><input data-gloskin-shop-search><button type="submit">Search</button><button type="button" data-gloskin-shop-search-clear hidden>Clear</button></form>
<div data-gloskin-shop-price-filter data-gloskin-price-state="normal" data-gloskin-price-avail-min="0" data-gloskin-price-avail-max="500000">
<div><span data-gloskin-price-label-min></span><span class="gloskin-ui1-price-filter__label-sep">–</span><span data-gloskin-price-label-max></span></div>
<div data-gloskin-price-slider><div data-gloskin-price-track></div>
<input type="range" min="0" max="500000" value="0" data-gloskin-shop-min-price-slider>
<input type="range" min="0" max="500000" value="500000" data-gloskin-shop-max-price-slider>
</div>
<button type="button" data-gloskin-shop-price-reset hidden>Reset</button>
</div>
<button type="button" data-gloskin-shop-clear-all hidden>Clear all</button>
<span data-gloskin-shop-status-live></span>
<section data-gloskin-shop-results aria-busy="false"><div data-gloskin-shop-status></div><h2 data-gloskin-shop-results-heading tabindex="-1">Products</h2><a href="#page=2" data-gloskin-shop-page="2">2</a></section>
</section>
</body></html>'''


def result_html(empty=False):
    if empty:
        return '<div data-gloskin-shop-status></div><div data-empty-results>Empty</div>'
    return '<div data-gloskin-shop-status></div><h2 data-gloskin-shop-results-heading tabindex="-1">Products</h2><a href="#page=2" data-gloskin-shop-page="2">2</a>'


def price_payload(state):
    if state['q'] == 'single':
        return 'single', 295000, 295000, '', ''
    if state['q'] == 'none':
        return 'empty', None, None, '', ''
    return 'normal', 0, 500000, state['min_price'], state['max_price']


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        parsed = urlparse(self.path)
        if parsed.path == '/owner.js':
            data = OWNER.encode(); self.send_response(200); self.send_header('Content-Type', 'text/javascript'); self.send_header('Content-Length', str(len(data))); self.end_headers(); self.wfile.write(data); return
        if parsed.path == '/wp-json/gloskin/v1/shop/catalog':
            query = parse_qs(parsed.query, keep_blank_values=True)
            state = {key: query.get(key, [''])[0] for key in ('category', 'q', 'min_price', 'max_price', 'page')}
            REQUESTS.append(state.copy())
            if state['q'] == 'slow':
                time.sleep(0.35)
            if state['q'] == 'fail' and FAIL_ONCE['fail']:
                FAIL_ONCE['fail'] = False
                self.send_response(500); self.end_headers(); return
            price_state, avail_min, avail_max, effective_min, effective_max = price_payload(state)
            payload = {
                'html': result_html(state['q'] == 'none'),
                'category': state['category'], 'q': state['q'],
                'min_price': effective_min, 'max_price': effective_max,
                'page': int(state['page'] or '1'), 'total': 0 if state['q'] == 'none' else 20, 'max_pages': 2,
                'price_state': price_state,
                'available_min_price': avail_min, 'available_max_price': avail_max,
            }
            data = json.dumps(payload).encode(); self.send_response(200); self.send_header('Content-Type', 'application/json'); self.send_header('Content-Length', str(len(data))); self.end_headers()
            try: self.wfile.write(data)
            except BrokenPipeError: pass
            return
        data = HTML.encode(); self.send_response(200); self.send_header('Content-Type', 'text/html'); self.send_header('Content-Length', str(len(data))); self.end_headers(); self.wfile.write(data)

    def log_message(self, *_args): pass


def wait_count(page, expected, timeout_ms=3000):
    deadline = time.time() + timeout_ms / 1000
    while time.time() < deadline:
        if len(REQUESTS) >= expected: return
        page.wait_for_timeout(25)
    raise AssertionError(f'expected >= {expected} requests, got {REQUESTS!r}')


def submit_search(page, value):
    page.fill('[data-gloskin-shop-search]', value)
    page.press('[data-gloskin-shop-search]', 'Enter')


def set_slider(page, selector, value):
    page.eval_on_selector(selector, "(el, value) => { el.value = value; el.dispatchEvent(new Event('input', {bubbles:true})); el.dispatchEvent(new Event('change', {bubbles:true})); }", str(value))


def run_layout_smoke(browser):
    page = browser.new_page(viewport={'width': 1200, 'height': 800})
    page.set_content(LAYOUT_HTML)
    rail = page.locator('.gloskin-ui1-shop-catalog__rail')
    assert rail.evaluate("(el)=>getComputedStyle(el).position") == 'sticky'
    assert page.locator('.gloskin-ui1-shop-categories').evaluate("(el)=>getComputedStyle(el).position") == 'static'
    assert page.locator('.gloskin-ui1-shop-categories').evaluate("(el)=>getComputedStyle(el).borderRightWidth") == '0px'
    sections = page.locator('.gloskin-ui1-shop-rail-section')
    assert sections.nth(0).evaluate("(el)=>getComputedStyle(el).borderBottomWidth") != '0px'
    assert sections.nth(1).evaluate("(el)=>getComputedStyle(el).borderBottomWidth") != '0px'
    assert sections.nth(2).evaluate("(el)=>getComputedStyle(el).borderBottomWidth") == '0px'
    page.evaluate("window.scrollTo(0, 700)")
    page.wait_for_timeout(50)
    assert 70 <= rail.bounding_box()['y'] <= 82
    clear = page.locator('.gloskin-ui1-shop-filter__clear')
    assert clear.evaluate("(el)=>getComputedStyle(el).backgroundColor") == 'rgba(0, 0, 0, 0)'
    assert clear.evaluate("(el)=>getComputedStyle(el).borderRightWidth") == '0px'
    page.close()

    page = browser.new_page(viewport={'width': 390, 'height': 800})
    page.set_content(LAYOUT_HTML)
    assert page.locator('.gloskin-ui1-shop-catalog__rail').evaluate("(el)=>getComputedStyle(el).position") == 'static'
    assert page.locator('.gloskin-ui1-shop-categories ul').evaluate("(el)=>getComputedStyle(el).display") == 'grid'
    assert page.evaluate("document.documentElement.scrollWidth <= window.innerWidth") is True
    page.close()


def main():
    try:
        from playwright.sync_api import sync_playwright
    except Exception as exc:
        print(f'SKIP: Playwright unavailable: {exc}'); return 77

    server = ThreadingHTTPServer(('127.0.0.1', 0), Handler); Thread(target=server.serve_forever, daemon=True).start()
    try:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
            run_layout_smoke(browser)
            print('shop rail layout fixture: OK (desktop sticky/mobile normal-flow/no overflow)')

            page = browser.new_page()
            try:
                page.goto(f'http://127.0.0.1:{server.server_port}/shop/')
            except Exception as exc:
                browser.close()
                print(f'SKIP: browser navigation unavailable after layout fixture passed: {exc}')
                return 77

            page.fill('[data-gloskin-shop-search]', 'bright'); wait_count(page, 1)
            assert REQUESTS[-1]['q'] == 'bright'

            before = len(REQUESTS); page.fill('[data-gloskin-shop-search]', 'enter-now'); page.press('[data-gloskin-shop-search]', 'Enter'); wait_count(page, before + 1)
            page.wait_for_timeout(375); assert len(REQUESTS) == before + 1 and REQUESTS[-1]['q'] == 'enter-now'

            before = len(REQUESTS); set_slider(page, '[data-gloskin-shop-min-price-slider]', 100000); wait_count(page, before + 1)
            assert REQUESTS[-1]['min_price'] == '100000'
            before = len(REQUESTS); set_slider(page, '[data-gloskin-shop-max-price-slider]', 300000); wait_count(page, before + 1)
            assert REQUESTS[-1]['min_price'] == '100000' and REQUESTS[-1]['max_price'] == '300000'

            before = len(REQUESTS); submit_search(page, 'single'); wait_count(page, before + 1); page.wait_for_timeout(50)
            assert len(REQUESTS) == before + 1
            assert page.get_attribute('[data-gloskin-shop-price-filter]', 'data-gloskin-price-state') == 'single'
            assert page.text_content('[data-gloskin-price-label-min]') == 'Rp 295.000'
            assert page.is_hidden('[data-gloskin-shop-min-price-slider]') and page.is_disabled('[data-gloskin-shop-min-price-slider]')
            assert page.is_hidden('[data-gloskin-shop-max-price-slider]') and page.is_disabled('[data-gloskin-shop-max-price-slider]')
            assert page.is_hidden('[data-gloskin-shop-price-reset]')

            before = len(REQUESTS); submit_search(page, 'none'); wait_count(page, before + 1); page.wait_for_selector('[data-empty-results]')
            assert len(REQUESTS) == before + 1
            assert page.get_attribute('[data-gloskin-shop-price-filter]', 'data-gloskin-price-state') == 'empty'
            assert page.text_content('[data-gloskin-price-label-min]') == 'Harga belum tersedia'
            assert page.is_hidden('[data-gloskin-price-slider]')

            before = len(REQUESTS); submit_search(page, 'normal-again'); wait_count(page, before + 1); page.wait_for_timeout(50)
            assert page.get_attribute('[data-gloskin-shop-price-filter]', 'data-gloskin-price-state') == 'normal'
            assert not page.is_hidden('[data-gloskin-shop-min-price-slider]') and not page.is_disabled('[data-gloskin-shop-min-price-slider]')

            before = len(REQUESTS); page.click('[data-gloskin-shop-category="serum"]'); wait_count(page, before + 1)
            before = len(REQUESTS); page.click('[data-gloskin-shop-page="2"]'); wait_count(page, before + 1)
            assert REQUESTS[-1]['page'] == '2'

            before = len(REQUESTS); page.go_back(wait_until='commit'); wait_count(page, before + 1)
            before = len(REQUESTS); page.go_forward(wait_until='commit'); wait_count(page, before + 1)

            before = len(REQUESTS); submit_search(page, 'slow'); wait_count(page, before + 1); submit_search(page, 'fast'); wait_count(page, before + 2); page.wait_for_timeout(450)
            assert page.input_value('[data-gloskin-shop-search]') == 'fast' and REQUESTS[-1]['q'] == 'fast'

            before = len(REQUESTS); submit_search(page, 'fail'); wait_count(page, before + 1); page.wait_for_selector('[data-gloskin-shop-retry]')
            page.click('[data-gloskin-shop-retry]'); wait_count(page, before + 2); page.wait_for_timeout(75)
            assert REQUESTS[-1]['q'] == 'fail' and page.locator('[data-gloskin-shop-retry]').count() == 0

            assert page.locator('[data-gloskin-shop-catalog-owner]').count() == 1
            browser.close()
        print('shop-catalog-browser-smoke.py: OK (rail + price states + request owner/history/abort/retry)')
        return 0
    finally:
        server.shutdown()


if __name__ == '__main__': sys.exit(main())
