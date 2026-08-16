#!/usr/bin/env python3
"""Focused browser regression for one variable-commerce submit/state owner."""
from pathlib import Path
import os

ROOT = Path(__file__).resolve().parents[1]
CORE_PATH = Path(os.environ.get('GLOSKIN_CORE_JS', ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js'))
CORE = CORE_PATH.read_text(encoding='utf-8')


def require(cond, message):
    if not cond:
        raise AssertionError(message)

require(CORE.count('function claimWooAjaxSubmit(') == 1, 'exactly one canonical submit claim helper required')
require(CORE.count('function bindWooAjaxSubmitOwner(') == 1, 'exactly one canonical submit binder required')
require('bindCatalogMutationOwner(form)' in CORE, 'Catalog must bind the shared submit owner')
require('bindWooAjaxSubmitOwner(form, function (submitter)' in CORE, 'PDP must bind the shared submit owner')
require("['.woocommerce-variation-price', '.woocommerce-variation-availability']" in CORE, 'variation-state allowlist missing')
require('.woocommerce-variation-description' not in CORE, 'variation description must never be selected by presentation renderer')
require('state.innerHTML = nativeState.innerHTML' not in CORE, 'arbitrary native variation-state mirror forbidden')
require('nativeState.hidden = true;' in CORE, 'Catalog native state must be presentation-hidden only after successful enhancement')
require('new MutationObserver' not in CORE and 'setInterval(' not in CORE, 'no new observer/polling state owner allowed')
print('variable-commerce-hardening-source-contract: OK')

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('variable-commerce-hardening-browser-smoke: SKIPPED (playwright unavailable)')
    raise SystemExit(77)

LEAK = 'LEAK SENTINEL ' + ('DO NOT SHOW ' * 32)
FORM = f'''<form class="variations_form cart" method="post" data-product_id="202">
<table class="variations"><tbody><tr><th><label for="size">Ukuran</label></th><td><select id="size" name="attribute_pa_size"><option value="30ml" selected>30 ml</option></select></td></tr></tbody></table>
<div class="single_variation_wrap"><div class="woocommerce-variation single_variation"><div class="woocommerce-variation-description"><p>{LEAK}</p></div><div class="woocommerce-variation-price"><span class="price">Rp250.000</span></div><div class="woocommerce-variation-availability"><p class="stock in-stock">Tersedia</p></div></div>
<div class="woocommerce-variation-add-to-cart variations_button"><div class="quantity"><input class="qty" name="quantity" type="number" value="1" min="1"></div><button type="submit" class="single_add_to_cart_button button alt" name="add-to-cart" value="202">Add</button><input type="hidden" name="product_id" value="202"><input class="variation_id" name="variation_id" type="hidden" value="205"></div></div></form>'''
MODAL = '''<div data-gloskin-overlay="quickadd" aria-hidden="true" hidden><button data-gloskin-overlay-close type="button">x</button><div role="dialog"><div data-gloskin-quickadd-body></div></div></div><div data-gloskin-overlay="cart" aria-hidden="true" hidden><div role="dialog"><button data-gloskin-overlay-close type="button">x</button></div></div>'''
RUNTIME = r'''
window.gloskinData={woo:true,restUrl:'/wp-json/gloskin/v1/',addToCartAjaxUrl:'/?wc-ajax=add_to_cart',cartUrl:'#cart'};
window.wc_cart_fragments_params={};window.wc_add_to_cart_params={};
window.__posts=0;window.__added=0;window.__delegated=0;window.__cartQty=10;window.__release=null;window.__catalogPayload=null;
window.Audio=function(){this.play=function(){return Promise.resolve();};};
(function(){const handlers=new WeakMap();function m(t){if(!handlers.has(t))handlers.set(t,{});return handlers.get(t)}function b(n){return String(n||'').split('.')[0]}function emit(t,n,args){if(b(n)==='added_to_cart')window.__added++;(m(t)[b(n)]||[]).slice().forEach(h=>h.apply(t,[null].concat(args||[])))}function jq(t){return {length:t?1:0,on(ns,h){String(ns||'').split(/\s+/).filter(Boolean).forEach(n=>{const q=m(t),k=b(n);(q[k]||(q[k]=[])).push(h)});return this},trigger(n,args){emit(t,n,args);return this},attr(n,v){if(t&&v!==undefined)t.setAttribute(n,v);return this},wc_variation_form(){return this}}}jq.fn={wc_variation_form:function(){}};window.jQuery=jq;}());
document.addEventListener('submit',e=>{if(e.target&&e.target.matches&&e.target.matches('form.cart')){window.__delegated++;window.__cartQty+=Number(e.target.querySelector('input.qty')?.value||1)}},false);
window.fetch=function(url,opts){url=String(url);if(url.includes('products/quick-add'))return Promise.resolve({ok:true,json:()=>Promise.resolve(window.__catalogPayload)});if(!url.includes('wc-ajax=add_to_cart'))return Promise.reject(new Error('unexpected fetch '+url));window.__posts++;const q=Number(opts.body.get('quantity')||1);return new Promise(resolve=>{window.__release=()=>{window.__cartQty+=q;resolve({ok:true,json:()=>Promise.resolve({fragments:{},cart_hash:'fixture'})})}})};
'''

def launch(p):
    chromium = Path('/usr/bin/chromium')
    if chromium.exists():
        return p.chromium.launch(headless=True, executable_path=str(chromium), args=['--no-sandbox'])
    bundled = Path(p.chromium.executable_path)
    if not bundled.exists():
        print('variable-commerce-hardening-browser-smoke: SKIPPED (chromium unavailable)')
        raise SystemExit(77)
    return p.chromium.launch(headless=True)

with sync_playwright() as p:
    browser=launch(p)
    page=browser.new_page(viewport={'width':1280,'height':900})
    # Catalog Quick Add with a real competing delegated submit handler.
    page.set_content(f'''<!doctype html><body class="gloskin-ui1"><a href="/product/x" data-gloskin-quickadd-open data-gloskin-quickadd-product="202">open</a>{MODAL}</body>''')
    page.add_script_tag(content=RUNTIME)
    page.evaluate('(payload)=>window.__catalogPayload=payload', {'found':True,'url':'/product/x','name':'X','image_html':'','price_html':'Rp250.000','form_html':FORM})
    page.add_script_tag(content=CORE)
    page.locator('[data-gloskin-quickadd-open]').click()
    page.wait_for_selector('[data-gloskin-variable-submit-proxy]')
    target=page.locator('[data-gloskin-variable-state]')
    require(target.count()==1,'Catalog enhanced mode needs one presentation state target')
    require('Rp250.000' in target.inner_text() and 'Tersedia' in target.inner_text(),'Catalog state must retain price and availability')
    require('LEAK SENTINEL' not in target.inner_text(),'Catalog state leaked variation description')
    native=page.locator('.woocommerce-variation.single_variation')
    require(native.count()==1 and native.get_attribute('hidden') is not None,'native Woo state must remain alive but presentation-hidden after successful enhancement')
    start=page.evaluate('window.__cartQty')
    proxy=page.locator('[data-gloskin-variable-submit-proxy]')
    proxy.click();page.wait_for_function('window.__posts===1')
    require(page.evaluate('window.__delegated')==0,'Catalog claimed submit escaped to delegated mutation owner')
    proxy.click(force=True);require(page.evaluate('window.__posts')==1,'Catalog repeat while pending started second POST')
    page.evaluate('window.__release()');page.wait_for_function('window.__added===1')
    require(page.evaluate('window.__cartQty')==start+1,'Catalog qty 1 must change cart by exactly +1')
    page.locator('[data-gloskin-overlay="cart"] [data-gloskin-overlay-close]').click();page.wait_for_function("document.querySelector('[data-gloskin-overlay=\"cart\"]').getAttribute('aria-hidden')==='true'")
    page.locator('[data-gloskin-quickadd-open]').click();page.wait_for_selector('[data-gloskin-variable-submit-proxy]')
    page.locator('input.qty').fill('2');start2=page.evaluate('window.__cartQty');page.locator('[data-gloskin-variable-submit-proxy]').click();page.wait_for_function('window.__posts===2');page.evaluate('window.__release()');page.wait_for_function('window.__added===2')
    require(page.evaluate('window.__cartQty')==start2+2,'Catalog qty 2 must change cart by exactly +2')
    require(page.evaluate('window.__delegated')==0,'Catalog later intentional submit escaped to delegated mutation owner')

    # PDP variable shares the same state allowlist and same native submit owner.
    page=browser.new_page(viewport={'width':1280,'height':900})
    page.set_content(f'''<!doctype html><body class="gloskin-ui1 single-product"><main><div class="product product-type-variable"><div data-gloskin-purchase-dock data-gloskin-purchase-composed="true"><div data-gloskin-purchase-identity><span class="gloskin-ui1-purchase-dock__title">X</span><span class="gloskin-ui1-purchase-dock__price">Rp250.000</span></div><div data-gloskin-purchase-action></div>{FORM}</div></div></main>{MODAL}</body>''')
    page.add_script_tag(content=RUNTIME);page.add_script_tag(content=CORE)
    page.wait_for_selector('[data-gloskin-variable-pdp-trigger]');page.locator('[data-gloskin-variable-pdp-trigger]').click();page.wait_for_selector('[data-gloskin-variable-submit-proxy]')
    state=page.locator('[data-gloskin-quickadd-body] [data-gloskin-variable-state]')
    require('Rp250.000' in state.inner_text() and 'Tersedia' in state.inner_text(),'PDP state must retain price and availability')
    require('LEAK SENTINEL' not in state.inner_text(),'PDP state leaked variation description')
    require(page.locator('[data-gloskin-quickadd-body] form.cart').count()==0,'PDP modal must contain zero second form')
    start=page.evaluate('window.__cartQty');page.locator('[data-gloskin-variable-submit-proxy]').click();page.wait_for_function('window.__posts===1');require(page.evaluate('window.__delegated')==0,'PDP variable delegated duplicate mutation');page.evaluate('window.__release()');page.wait_for_function('window.__added===1');require(page.evaluate('window.__cartQty')==start+1,'PDP variable one click must add exactly +1')

    # PDP simple uses the same canonical submit binder.
    page=browser.new_page(viewport={'width':1280,'height':900})
    simple='''<form class="cart" method="post"><div class="quantity"><input class="qty" name="quantity" value="1"></div><button type="submit" class="single_add_to_cart_button" name="add-to-cart" value="301">Add</button><input type="hidden" name="product_id" value="301"></form>'''
    page.set_content(f'''<!doctype html><body class="gloskin-ui1 single-product"><div class="product product-type-simple"><div data-gloskin-purchase-dock>{simple}</div></div>{MODAL}</body>''')
    page.add_script_tag(content=RUNTIME);page.add_script_tag(content=CORE)
    start=page.evaluate('window.__cartQty');page.locator('.single_add_to_cart_button').click();page.wait_for_function('window.__posts===1');require(page.evaluate('window.__delegated')==0,'PDP simple delegated duplicate mutation');page.evaluate('window.__release()');page.wait_for_function('window.__added===1');require(page.evaluate('window.__cartQty')==start+1,'PDP simple one click must add exactly +1')
    browser.close()

print('variable-commerce-hardening-browser-smoke: OK')
