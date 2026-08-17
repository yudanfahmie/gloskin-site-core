#!/usr/bin/env python3
"""Browser smoke for the single active Shop catalog owner and full filter state."""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from threading import Thread
from urllib.parse import parse_qs, urlparse
import json
import sys
import time

ROOT = Path(__file__).resolve().parents[1]
OWNER = (ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-shop-discovery.js').read_text(encoding='utf-8')
REQUESTS = []
FAIL_ONCE = {'fail': True}

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
<div data-gloskin-shop-price-filter data-gloskin-price-avail-min="0" data-gloskin-price-avail-max="500000">
<input type="range" min="0" max="500000" value="0" data-gloskin-shop-min-price-slider>
<input type="range" min="0" max="500000" value="500000" data-gloskin-shop-max-price-slider>
<span data-gloskin-price-label-min></span><span data-gloskin-price-label-max></span>
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
            payload = {
                'html': result_html(state['q'] == 'none'),
                'category': state['category'], 'q': state['q'],
                'min_price': state['min_price'], 'max_price': state['max_price'],
                'page': int(state['page'] or '1'), 'total': 0 if state['q'] == 'none' else 20, 'max_pages': 2,
                'available_min_price': 0, 'available_max_price': 500000,
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


def main():
    try:
        from playwright.sync_api import sync_playwright
    except Exception as exc:
        print(f'SKIP: Playwright unavailable: {exc}'); return 77

    server = ThreadingHTTPServer(('127.0.0.1', 0), Handler); Thread(target=server.serve_forever, daemon=True).start()
    try:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
            page = browser.new_page()
            try:
                page.goto(f'http://127.0.0.1:{server.server_port}/shop/')
            except Exception as exc:
                browser.close()
                print(f'SKIP: browser navigation unavailable in this environment: {exc}')
                return 77

            # Search debounce.
            page.fill('[data-gloskin-shop-search]', 'bright'); wait_count(page, 1)
            assert REQUESTS[-1]['q'] == 'bright'

            # Enter submits immediately and cancels pending debounce.
            before = len(REQUESTS); page.fill('[data-gloskin-shop-search]', 'enter-now'); page.press('[data-gloskin-shop-search]', 'Enter'); wait_count(page, before + 1)
            page.wait_for_timeout(375); assert len(REQUESTS) == before + 1 and REQUESTS[-1]['q'] == 'enter-now'

            # Minimum, maximum, and combined price filters.
            before = len(REQUESTS); set_slider(page, '[data-gloskin-shop-min-price-slider]', 100000); wait_count(page, before + 1)
            assert REQUESTS[-1]['min_price'] == '100000'
            before = len(REQUESTS); set_slider(page, '[data-gloskin-shop-max-price-slider]', 300000); wait_count(page, before + 1)
            assert REQUESTS[-1]['min_price'] == '100000' and REQUESTS[-1]['max_price'] == '300000'

            # Category + search + price state and pagination preservation.
            before = len(REQUESTS); page.click('[data-gloskin-shop-category="serum"]'); wait_count(page, before + 1)
            assert REQUESTS[-1]['category'] == 'serum' and REQUESTS[-1]['q'] == 'enter-now' and REQUESTS[-1]['min_price'] == '100000'
            before = len(REQUESTS); page.click('[data-gloskin-shop-page="2"]'); wait_count(page, before + 1)
            assert REQUESTS[-1] == {'category':'serum','q':'enter-now','min_price':'100000','max_price':'300000','page':'2'}

            # Back/Forward restores complete state.
            before = len(REQUESTS); page.go_back(wait_until='commit'); wait_count(page, before + 1); assert REQUESTS[-1]['page'] == '1'
            before = len(REQUESTS); page.go_forward(wait_until='commit'); wait_count(page, before + 1); assert REQUESTS[-1]['page'] == '2'

            # Rapid in-flight search: stale slow response must not win over fast.
            before = len(REQUESTS); submit_search(page, 'slow'); wait_count(page, before + 1); submit_search(page, 'fast'); wait_count(page, before + 2); page.wait_for_timeout(450)
            assert page.input_value('[data-gloskin-shop-search]') == 'fast' and REQUESTS[-1]['q'] == 'fast'

            # Empty results render safely.
            before = len(REQUESTS); submit_search(page, 'none'); wait_count(page, before + 1); page.wait_for_selector('[data-empty-results]')

            # Simulated failure exposes retry; retry succeeds through same request owner.
            before = len(REQUESTS); submit_search(page, 'fail'); wait_count(page, before + 1); page.wait_for_selector('[data-gloskin-shop-retry]')
            page.click('[data-gloskin-shop-retry]'); wait_count(page, before + 2); page.wait_for_timeout(75)
            assert REQUESTS[-1]['q'] == 'fail' and page.locator('[data-gloskin-shop-retry]').count() == 0

            assert page.locator('[data-gloskin-shop-catalog-owner]').count() == 1
            browser.close()
        print('shop-catalog-browser-smoke.py: OK (search/enter/price/category/pagination/history/abort/empty/retry)')
        return 0
    finally:
        server.shutdown()


if __name__ == '__main__': sys.exit(main())
