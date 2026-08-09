#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css'
READINESS_CSS = ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-readiness.css'
JS = ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js'

HTML = r'''<!doctype html><html><body class="gloskin-ui1">
<header class="gloskin-ui1-header"><button id="search-open" data-gloskin-search-open aria-expanded="false">Search</button><a id="account-open" class="gloskin-ui1-utility-btn--account" href="/my-account/">Account</a></header>
<div class="gloskin-ui1-header__nav-row"></div>
<div data-gloskin-overlay="search" id="gloskin-search-overlay" class="gloskin-ui1-search-overlay" aria-hidden="true" hidden>
 <button data-gloskin-overlay-close>close</button><div class="gloskin-ui1-search-overlay__canvas" role="dialog">
 <div class="gloskin-ui1-search-overlay__field"><input data-gloskin-search-input><button data-gloskin-search-clear hidden>clear</button></div>
 <div class="gloskin-ui1-search-overlay__body" data-gloskin-search-results></div></div></div>
<div data-gloskin-overlay="auth" id="gloskin-auth-overlay" class="gloskin-ui1-auth-overlay" aria-hidden="true" hidden>
 <button data-gloskin-overlay-close>close</button><section class="gloskin-ui1-auth-overlay__panel" role="dialog">
 <div data-gloskin-auth-forms><div id="customer_login"><div class="u-column1"><form class="woocommerce-form-login" action="/my-account/"><input id="username"><input type="hidden" name="woocommerce-login-nonce" value="n1"><button name="login">Login</button></form></div><div class="u-column2"><form class="woocommerce-form-register" action="/my-account/"><input id="reg_email"><input type="hidden" name="woocommerce-register-nonce" value="n2"><button name="register">Register</button></form></div></div></div>
 <div class="gloskin-ui1-auth-switch"><button data-gloskin-auth-tab="login">Masuk</button><button data-gloskin-auth-tab="register">Buat Akun</button></div>
 </section></div>
<div data-gloskin-overlay="cart" class="gloskin-ui1-sheet" aria-hidden="true" hidden><button data-gloskin-overlay-close>close</button><div role="dialog"></div></div>
<button data-gloskin-cart-open>Cart</button>
<div data-gloskin-drawer hidden aria-hidden="true"><div role="dialog"><a data-gloskin-auth-open-from-drawer href="/my-account/">Masuk</a><button data-gloskin-drawer-close>close</button></div></div><button data-gloskin-drawer-open>menu</button>
</body></html>'''

def check(cond, message):
    if not cond:
        raise AssertionError(message)

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    page.set_content(HTML)
    page.add_style_tag(path=str(CSS))
    page.add_style_tag(path=str(READINESS_CSS))
    page.evaluate("""() => {
      window.gloskinData={restUrl:'/wp-json/gloskin/v1/',restNonce:'x',searchFallback:'/search?s=',woo:true,cartUrl:'/cart/'};
      window.__fetchCount=0;
      window.fetch=()=>{window.__fetchCount++; return Promise.resolve({ok:true,json:()=>Promise.resolve({groups:[]})});};
    }""")
    page.add_script_tag(path=str(JS))

    # Fresh overlay geometry: no reserved result-space footprint.
    page.click('#search-open')
    page.wait_for_timeout(100)
    style = page.eval_on_selector('[data-gloskin-search-results]', "e=>({margin:getComputedStyle(e).marginTop,min:getComputedStyle(e).minHeight,h:e.getBoundingClientRect().height})")
    check(style['margin'] == '0px' and style['min'] == '0px' and style['h'] == 0, f"search ghost geometry: {style}")
    check(page.locator('[data-gloskin-search-input]').evaluate('e=>document.activeElement===e'), 'search autofocus failed')

    # Loading -> meaningful zero state -> meaningful error fallback.
    page.fill('[data-gloskin-search-input]', 'zz')
    page.wait_for_timeout(320)
    page.wait_for_selector('.gloskin-ui1-empty-state--search')
    check('Tidak menemukan hasil yang sesuai' in page.locator('[data-gloskin-search-results]').inner_text(), 'search zero state missing')
    check(page.eval_on_selector('[data-gloskin-search-results]', 'e=>getComputedStyle(e).marginTop') == '20px', 'search state spacing missing')
    page.evaluate("window.fetch=()=>{window.__fetchCount++; return Promise.resolve({ok:false,json:()=>Promise.resolve({})});}")
    page.fill('[data-gloskin-search-input]', 'error')
    page.wait_for_timeout(320)
    page.wait_for_selector('.gloskin-ui1-empty-state--search')
    txt = page.locator('[data-gloskin-search-results]').inner_text()
    check('Pencarian belum dapat dimuat' in txt and 'Buka pencarian biasa' in txt, 'search error fallback missing')

    # Focus return after Search close.
    page.click('[data-gloskin-overlay="search"] [data-gloskin-overlay-close]')
    page.wait_for_timeout(340)
    check(page.locator('#search-open').evaluate('e=>document.activeElement===e'), 'search focus return failed')

    # Auth uses same overlay owner, native forms/nonces/actions, no credential fetch.
    before = page.evaluate('window.__fetchCount')
    page.click('#account-open')
    page.wait_for_timeout(100)
    check(page.get_attribute('[data-gloskin-overlay="auth"]', 'aria-hidden') == 'false', 'auth did not open')
    check(page.get_attribute('[data-gloskin-overlay="search"]', 'aria-hidden') == 'true', 'overlay mutual exclusion failed')
    check(page.locator('input[name="woocommerce-login-nonce"]').count() == 1 and page.locator('input[name="woocommerce-register-nonce"]').count() == 1, 'native Woo nonces missing')
    check(page.get_attribute('.woocommerce-form-login', 'action') == '/my-account/' and page.get_attribute('.woocommerce-form-register', 'action') == '/my-account/', 'native auth action changed')
    check(page.locator('#username').evaluate('e=>document.activeElement===e'), 'auth username focus failed')
    page.click('[data-gloskin-auth-tab="register"]')
    check(page.is_hidden('#customer_login .u-column1') and page.is_visible('#customer_login .u-column2'), 'auth register switch failed')
    check(page.evaluate('window.__fetchCount') == before, 'auth introduced a fetch credential path')
    page.eval_on_selector('[data-gloskin-cart-open]', 'e=>e.click()')
    page.wait_for_timeout(30)
    check(page.get_attribute('[data-gloskin-overlay="auth"]', 'aria-hidden') == 'true' and page.get_attribute('[data-gloskin-overlay="cart"]', 'aria-hidden') == 'false', 'cart/auth mutual exclusion failed')

    # Responsive auth/search usability at required widths.
    for width in (390, 600, 782, 1024, 1440, 1920):
        page.set_viewport_size({"width": width, "height": 900})
        if page.get_attribute('[data-gloskin-overlay="cart"]', 'aria-hidden') == 'false':
            page.click('[data-gloskin-overlay="cart"] [data-gloskin-overlay-close]')
            page.wait_for_timeout(340)
        if width <= 1040:
            page.click('[data-gloskin-drawer-open]')
            page.click('[data-gloskin-auth-open-from-drawer]')
            page.wait_for_timeout(100)
        else:
            page.click('#account-open')
            page.wait_for_timeout(30)
        box = page.locator('.gloskin-ui1-auth-overlay__panel').bounding_box()
        check(box and box['width'] <= width + 0.5, f'auth overflow at {width}: {box}')
        page.click('[data-gloskin-overlay="auth"] [data-gloskin-overlay-close]')
        page.wait_for_timeout(340)

    page.emulate_media(reduced_motion='reduce')
    page.click('#account-open')
    page.wait_for_timeout(20)
    duration = page.eval_on_selector('[data-gloskin-overlay="auth"]', 'e=>getComputedStyle(e).transitionDuration')
    check(duration == '0s', f'reduced-motion auth transition still active: {duration}')
    browser.close()

print('readiness-browser-smoke: OK')
