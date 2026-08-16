#!/usr/bin/env python3
"""Focused regression for one Woo AJAX request -> one server mutation and one variable-field renderer."""
from pathlib import Path
import os

ROOT = Path(__file__).resolve().parents[1]
CORE_PATH = Path(os.environ.get('GLOSKIN_CORE_JS', ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js'))
CORE = CORE_PATH.read_text(encoding='utf-8')
DOCK = (ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-purchase-dock.js').read_text(encoding='utf-8')


def require(cond, message):
    if not cond:
        raise AssertionError(message)

require(CORE.count('function claimWooAjaxSubmit(') == 1, 'exactly one canonical submit claim helper required')
require(CORE.count('function bindWooAjaxSubmitOwner(') == 1, 'exactly one canonical submit binder required')
require(CORE.count('function renderVariableFields(form, host)') == 1, 'exactly one variable-field renderer required')
require('if (!renderVariableFields(form, fields))' in CORE, 'Catalog must use shared field renderer')
require('if (!fields || !renderVariableFields(form, fields))' in CORE, 'PDP must use shared field renderer')
require("formData.delete('add-to-cart');" in CORE, 'AJAX projection must defensively delete add-to-cart')
require('formData.append(submitter.name, submitter.value);' not in CORE, 'AJAX projection must never append native submitter add-to-cart')
require("['.woocommerce-variation-price', '.woocommerce-variation-availability']" in CORE, 'variation-state allowlist missing')
require('.woocommerce-variation-description' not in CORE, 'variation description must never be selected by presentation renderer')
require('state.innerHTML = nativeState.innerHTML' not in CORE, 'arbitrary native variation-state mirror forbidden')
require('nativeFields.hidden = true;' in CORE, 'Catalog native fields must become state-only after successful enhancement')
require('new MutationObserver' not in CORE and 'setInterval(' not in CORE, 'no observer/polling owner allowed')
print('variable-commerce-hardening-source-contract: OK')

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('variable-commerce-hardening-browser-smoke: SKIPPED (playwright unavailable)')
    raise SystemExit(77)

LEAK = 'LEAK SENTINEL ' + ('DO NOT SHOW ' * 20)
VARIABLE_FORM = f'''<form class="variations_form cart" method="post" data-product_id="202">
<table class="variations"><tbody><tr><th><label for="size">Ukuran</label></th><td><select id="size" name="attribute_pa_size"><option value="30ml" selected>30 ml</option></select><a class="reset_variations" href="#">Clear</a></td></tr></tbody></table>
<div class="single_variation_wrap"><div class="woocommerce-variation single_variation"><div class="woocommerce-variation-description"><p>{LEAK}</p></div><div class="woocommerce-variation-price"><span class="price">Rp250.000</span></div><div class="woocommerce-variation-availability"><p class="stock in-stock">Tersedia</p></div></div>
<div class="woocommerce-variation-add-to-cart variations_button"><div class="quantity"><input class="qty" name="quantity" type="number" value="1" min="1"></div><button type="submit" class="single_add_to_cart_button button alt" name="add-to-cart" value="202">Add</button><input type="hidden" name="product_id" value="202"><input type="hidden" name="add-to-cart" value="202"><input class="variation_id" name="variation_id" type="hidden" value="205"></div></div></form>'''
SIMPLE_FORM = '''<form class="cart" method="post"><div class="quantity"><input class="qty" name="quantity" type="number" value="1" min="1"></div><button type="submit" class="single_add_to_cart_button" name="add-to-cart" value="301">Add</button><input type="hidden" name="product_id" value="301"><input type="hidden" name="add-to-cart" value="301"></form>'''
MODALS = '''<div data-gloskin-overlay="quickadd" aria-hidden="true" hidden><button data-gloskin-overlay-close type="button">x</button><div role="dialog"><div data-gloskin-quickadd-body></div></div></div><div data-gloskin-overlay="cart" aria-hidden="true" hidden><div role="dialog"><button data-gloskin-overlay-close type="button">x</button></div></div>'''
RUNTIME = r'''
window.gloskinData={woo:true,restUrl:'/wp-json/gloskin/v1/',addToCartAjaxUrl:'/?wc-ajax=add_to_cart',cartUrl:'#cart'};
window.wc_cart_fragments_params={};window.wc_add_to_cart_params={};
window.__posts=0;window.__added=0;window.__delegated=0;window.__serverMutations=0;window.__cartQty=10;window.__release=null;window.__catalogPayload=null;window.__payloads=[];
window.Audio=function(){this.play=function(){return Promise.resolve();};};
window.matchMedia=function(){return {matches:false,addEventListener(){},removeEventListener(){}}};
window.IntersectionObserver=function(){this.observe=function(){};this.disconnect=function(){}};
window.ResizeObserver=function(){this.observe=function(){};this.disconnect=function(){}};
(function(){const handlers=new WeakMap();function m(t){if(!handlers.has(t))handlers.set(t,{});return handlers.get(t)}function b(n){return String(n||'').split('.')[0]}function emit(t,n,args){if(b(n)==='added_to_cart')window.__added++;(m(t)[b(n)]||[]).slice().forEach(h=>h.apply(t,[null].concat(args||[])))}function jq(t){return {length:t?1:0,on(ns,h){String(ns||'').split(/\s+/).filter(Boolean).forEach(n=>{const q=m(t),k=b(n);(q[k]||(q[k]=[])).push(h)});return this},trigger(n,args){emit(t,n,args);return this},attr(n,v){if(t&&v!==undefined)t.setAttribute(n,v);return this},wc_variation_form(){return this}}}jq.fn={wc_variation_form:function(){}};window.jQuery=jq;}());
window.__wooServer=function(body){const q=Number(body.get('quantity')||1);let mutations=0,delta=0;if(body.has('add-to-cart')){mutations++;delta+=q}if(body.get('product_id')){mutations++;delta+=q}return {mutations,delta}};
document.addEventListener('submit',e=>{if(e.target&&e.target.matches&&e.target.matches('form.cart'))window.__delegated++},false);
window.fetch=function(url,opts){url=String(url);if(url.includes('products/quick-add'))return Promise.resolve({ok:true,json:()=>Promise.resolve(window.__catalogPayload)});if(!url.includes('wc-ajax=add_to_cart'))return Promise.reject(new Error('unexpected fetch '+url));window.__posts++;const body=opts.body;window.__payloads.push({addToCart:body.get('add-to-cart'),productId:body.get('product_id'),variationId:body.get('variation_id'),quantity:body.get('quantity'),size:body.get('attribute_pa_size')});return new Promise(resolve=>{window.__release=()=>{const server=window.__wooServer(body);window.__serverMutations+=server.mutations;window.__cartQty+=server.delta;resolve({ok:true,json:()=>Promise.resolve({fragments:{},cart_hash:'fixture'})})}})};
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


def historical_collision(page):
    result = page.evaluate("""() => {const b=new FormData();b.set('add-to-cart','301');b.set('product_id','301');b.set('quantity','1');return window.__wooServer(b)}""")
    require(result == {'mutations': 2, 'delta': 2}, 'fixture must reproduce historical one-POST/two-server-mutation collision')


def last_payload(page):
    return page.evaluate('window.__payloads.at(-1)')


with sync_playwright() as p:
    browser=launch(p)

    # Shop variable modal: native table/select/reset remain state owners but the
    # successful enhancement exposes only the shared Gloskin field renderer.
    page=browser.new_page(viewport={'width':1280,'height':900})
    page.set_content(f'''<!doctype html><body class="gloskin-ui1"><a href="/product/x" data-gloskin-quickadd-open data-gloskin-quickadd-product="202">open</a>{MODALS}</body>''')
    page.add_script_tag(content=RUNTIME);historical_collision(page)
    page.evaluate('(payload)=>window.__catalogPayload=payload', {'found':True,'url':'/product/x','name':'X','image_html':'','price_html':'Rp250.000','form_html':VARIABLE_FORM})
    page.add_script_tag(content=CORE)
    page.locator('[data-gloskin-quickadd-open]').click();page.wait_for_selector('[data-gloskin-variable-submit-proxy]')
    state=page.locator('[data-gloskin-variable-state]')
    require('Rp250.000' in state.inner_text() and 'Tersedia' in state.inner_text(),'Catalog state must retain price/availability')
    require('LEAK SENTINEL' not in state.inner_text(),'Catalog variation description leak')
    require(page.locator('table.variations').count()==1 and page.locator('table.variations').is_hidden(),'Catalog native variations table must remain in DOM but not visible')
    require(page.locator('select[name^="attribute_"]').count()==1,'Catalog native select state must remain in DOM')
    require(page.locator('.reset_variations').count()==1 and page.locator('.reset_variations').is_hidden(),'Catalog native Clear must remain state-only')
    require(page.locator('[data-gloskin-variable-fields] .gloskin-ui1-variable-field').count()==1,'Catalog must use shared visible field structure')
    start=page.evaluate('window.__cartQty');proxy=page.locator('[data-gloskin-variable-submit-proxy]');proxy.click();page.wait_for_function('window.__posts===1');require(page.evaluate('window.__delegated')==0,'Catalog submit escaped canonical owner');proxy.click(force=True);require(page.evaluate('window.__posts')==1,'repeat pending click started second POST');page.evaluate('window.__release()');page.wait_for_function('window.__added===1');require(page.evaluate('window.__cartQty')==start+1 and page.evaluate('window.__serverMutations')==1,'Catalog qty1 must be one server mutation / +1')
    pay=last_payload(page);require(pay['addToCart'] is None and pay['productId']=='205' and pay['variationId']=='205' and pay['quantity']=='1' and pay['size']=='30ml','Catalog variable AJAX payload contract failed')
    page.locator('[data-gloskin-overlay="cart"] [data-gloskin-overlay-close]').click();page.locator('[data-gloskin-quickadd-open]').click();page.wait_for_selector('[data-gloskin-variable-submit-proxy]');page.locator('input.qty').fill('2');start=page.evaluate('window.__cartQty');page.locator('[data-gloskin-variable-submit-proxy]').click();page.wait_for_function('window.__posts===2');page.evaluate('window.__release()');page.wait_for_function('window.__added===2');require(page.evaluate('window.__cartQty')==start+2 and page.evaluate('window.__serverMutations')==2,'Catalog qty2 must add exactly +2 via one additional server mutation')

    # PDP variable uses the same renderer, state allowlist and payload builder.
    page=browser.new_page(viewport={'width':1280,'height':900})
    page.set_content(f'''<!doctype html><body class="gloskin-ui1 single-product"><main class="gloskin-ui1-commerce-native"><div class="product product-type-variable"><div class="summary"><div data-gloskin-purchase-dock data-gloskin-purchase-composed="true"><div data-gloskin-purchase-identity><span class="gloskin-ui1-purchase-dock__title">X</span><span class="gloskin-ui1-purchase-dock__price">Rp250.000</span></div><div data-gloskin-purchase-action></div>{VARIABLE_FORM}</div></div><section class="related products"></section></div></main>{MODALS}</body>''')
    page.add_script_tag(content=RUNTIME);page.add_script_tag(content=CORE);page.wait_for_selector('[data-gloskin-variable-pdp-trigger]');page.locator('[data-gloskin-variable-pdp-trigger]').click();page.wait_for_selector('[data-gloskin-variable-submit-proxy]')
    state=page.locator('[data-gloskin-quickadd-body] [data-gloskin-variable-state]');require('LEAK SENTINEL' not in state.inner_text() and 'Tersedia' in state.inner_text(),'PDP allowlisted state regression');require(page.locator('[data-gloskin-quickadd-body] form.cart').count()==0,'PDP modal must contain zero second form');require(page.locator('[data-gloskin-quickadd-body] .gloskin-ui1-variable-field').count()==1,'PDP must use shared field structure')
    start=page.evaluate('window.__cartQty');page.locator('[data-gloskin-variable-submit-proxy]').click();page.wait_for_function('window.__posts===1');page.evaluate('window.__release()');page.wait_for_function('window.__added===1');require(page.evaluate('window.__cartQty')==start+1 and page.evaluate('window.__serverMutations')==1,'PDP variable must be one mutation / +1');pay=last_payload(page);require(pay['addToCart'] is None and pay['productId']=='205' and pay['variationId']=='205','PDP variable payload must omit add-to-cart and target selected variation')

    # PDP simple, including a hidden add-to-cart input, must strip it only from AJAX.
    page=browser.new_page(viewport={'width':1280,'height':900})
    page.set_content(f'''<!doctype html><body class="gloskin-ui1 single-product"><main class="gloskin-ui1-commerce-native"><div class="product product-type-simple"><div class="summary"><div data-gloskin-purchase-dock>{SIMPLE_FORM}</div></div><section class="related products"></section></div></main>{MODALS}</body>''')
    page.add_script_tag(content=RUNTIME);page.add_script_tag(content=CORE);page.locator('input.qty').fill('2');start=page.evaluate('window.__cartQty');page.locator('.single_add_to_cart_button').click();page.wait_for_function('window.__posts===1');page.evaluate('window.__release()');page.wait_for_function('window.__added===1');require(page.evaluate('window.__cartQty')==start+2 and page.evaluate('window.__serverMutations')==1,'PDP simple qty2 must be one server mutation / +2');pay=last_payload(page);require(pay['addToCart'] is None and pay['productId']=='301' and pay['quantity']=='2','PDP simple payload contract failed');require(page.locator('.single_add_to_cart_button').get_attribute('name')=='add-to-cart','native fallback button semantics must remain untouched')

    # Valid Buy Now still delegates through the same native submit exactly once.
    page=browser.new_page(viewport={'width':1280,'height':900})
    page.set_content(f'''<!doctype html><body class="gloskin-ui1 single-product"><main class="gloskin-ui1-commerce-native"><div class="product product-type-simple"><div class="summary"><div data-gloskin-purchase-dock><div data-gloskin-purchase-identity><strong>X</strong></div>{SIMPLE_FORM}</div></div><section class="related products"></section></div></main>{MODALS}</body>''')
    page.add_script_tag(content=RUNTIME);page.add_script_tag(content=CORE);page.add_script_tag(content=DOCK);page.wait_for_selector('[data-gloskin-buy-now]');start=page.evaluate('window.__cartQty');page.locator('[data-gloskin-buy-now]').click();page.wait_for_function('window.__posts===1');page.evaluate('window.__release()');page.wait_for_function('window.__added===1');require(page.evaluate('window.__cartQty')==start+1 and page.evaluate('window.__serverMutations')==1,'valid Buy Now must remain one POST / one server mutation / +1');pay=last_payload(page);require(pay['addToCart'] is None and pay['productId']=='301','Buy Now AJAX payload must not be Form Handler eligible')

    browser.close()

print('variable-commerce-hardening-browser-smoke: OK')