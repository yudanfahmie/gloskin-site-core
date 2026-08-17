#!/usr/bin/env python3
"""Lightweight browser fixture for the single active Shop catalog owner."""
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path
from threading import Thread
from urllib.parse import parse_qs, urlparse
import json
import sys

ROOT = Path(__file__).resolve().parents[1]
CORE = (ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js').read_text(encoding='utf-8')
OWNER = (ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-shop-discovery.js').read_text(encoding='utf-8')
REQUESTS = []

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
<form data-gloskin-shop-price-form><input data-gloskin-shop-min-price><input data-gloskin-shop-max-price><button type="submit">Apply</button><button type="button" data-gloskin-shop-price-reset hidden>Reset</button></form>
<button type="button" data-gloskin-shop-clear-all hidden>Clear all</button>
<p data-gloskin-shop-filter-validation hidden></p><span data-gloskin-shop-status-live></span>
<section data-gloskin-shop-results aria-busy="false"><div data-gloskin-shop-status></div><h2 data-gloskin-shop-results-heading tabindex="-1">Products</h2><a href="#page=2" data-gloskin-shop-page="2">2</a></section>
</section>
<script src="/core.js"></script>
</body></html>'''


def result_html():
    return '<div data-gloskin-shop-status></div><h2 data-gloskin-shop-results-heading tabindex="-1">Products</h2><a href="#page=2" data-gloskin-shop-page="2">2</a>'


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        parsed = urlparse(self.path)
        if parsed.path == '/owner.js':
            data = OWNER.encode()
            self.send_response(200); self.send_header('Content-Type', 'text/javascript'); self.send_header('Content-Length', str(len(data))); self.end_headers(); self.wfile.write(data); return
        if parsed.path == '/core.js':
            data = CORE.encode()
            self.send_response(200); self.send_header('Content-Type', 'text/javascript'); self.send_header('Content-Length', str(len(data))); self.end_headers(); self.wfile.write(data); return
        if parsed.path == '/wp-json/gloskin/v1/shop/catalog':
            query = parse_qs(parsed.query, keep_blank_values=True)
            state = {key: query.get(key, [''])[0] for key in ('category', 'q', 'min_price', 'max_price', 'page')}
            REQUESTS.append(state.copy())
            payload = {
                'html': result_html(),
                'category': state['category'],
                'q': state['q'],
                'min_price': state['min_price'],
                'max_price': state['max_price'],
                'page': int(state['page'] or '1'),
                'total': 20,
                'max_pages': 2,
            }
            data = json.dumps(payload).encode()
            self.send_response(200); self.send_header('Content-Type', 'application/json'); self.send_header('Content-Length', str(len(data))); self.end_headers(); self.wfile.write(data); return
        data = HTML.encode()
        self.send_response(200); self.send_header('Content-Type', 'text/html'); self.send_header('Content-Length', str(len(data))); self.end_headers(); self.wfile.write(data)

    def log_message(self, *_args):
        pass


def wait_for_count(page, expected):
    for _ in range(30):
        if len(REQUESTS) == expected:
            return
        page.wait_for_timeout(50)
    raise AssertionError(f'expected {expected} requests, got {REQUESTS!r}')


def main():
    try:
        from playwright.sync_api import sync_playwright
    except Exception as exc:
        print(f'SKIP: Playwright unavailable: {exc}')
        return 77

    server = HTTPServer(('127.0.0.1', 0), Handler)
    Thread(target=server.serve_forever, daemon=True).start()
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

            page.fill('[data-gloskin-shop-search]', 'bright')
            wait_for_count(page, 1)
            page.fill('[data-gloskin-shop-min-price]', '100000')
            page.fill('[data-gloskin-shop-max-price]', '300000')
            page.click('[data-gloskin-shop-price-form] button[type="submit"]')
            wait_for_count(page, 2)
            page.click('[data-gloskin-shop-category="serum"]')
            wait_for_count(page, 3)
            page.click('[data-gloskin-shop-page="2"]')
            wait_for_count(page, 4)
            assert REQUESTS[-1] == {'category': 'serum', 'q': 'bright', 'min_price': '100000', 'max_price': '300000', 'page': '2'}

            page.go_back(wait_until='commit')
            wait_for_count(page, 5)
            assert REQUESTS[-1] == {'category': 'serum', 'q': 'bright', 'min_price': '100000', 'max_price': '300000', 'page': '1'}
            page.go_forward(wait_until='commit')
            wait_for_count(page, 6)
            assert REQUESTS[-1] == {'category': 'serum', 'q': 'bright', 'min_price': '100000', 'max_price': '300000', 'page': '2'}

            assert page.input_value('[data-gloskin-shop-search]') == 'bright'
            assert page.input_value('[data-gloskin-shop-min-price]') == '100000'
            assert page.input_value('[data-gloskin-shop-max-price]') == '300000'
            assert page.locator('[data-gloskin-shop-catalog-owner]').count() == 1
            browser.close()
        print('shop-catalog-browser-smoke.py: OK (6 logical catalog requests)')
        return 0
    finally:
        server.shutdown()


if __name__ == '__main__':
    sys.exit(main())
