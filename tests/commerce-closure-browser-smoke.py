#!/usr/bin/env python3
"""Focused browser regression for final variable commerce closure.

Claimed-submit propagation and modal presentation mirroring are owned
directly by gloskin-ui1-core.js (initSingleProductAjax() / initQuickAdd()) --
there is no separate post-core commerce-closure module."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CORE = (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js").read_text(encoding="utf-8")
DOCK = (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-purchase-dock.js").read_text(encoding="utf-8")
CSS = "\n".join((ROOT / path).read_text(encoding="utf-8") for path in (
    "plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css",
    "plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css",
    "plugin/gloskin-site-core/assets/css/gloskin-ui1-single-product-geometry.css",
    "plugin/gloskin-site-core/assets/css/gloskin-ui1-quickadd-polish.css",
))


def require(condition, message):
    if not condition:
        raise AssertionError(message)


require("function renderSingleProductViewCartLink" not in CORE, "PDP View Cart create-then-delete choreography must not exist")
require("wc-forward" not in CORE, "core must never create a PDP added_to_cart forward link")
require("event.stopImmediatePropagation();" in CORE, "claimed-submit propagation guard missing from the canonical submit owner")
require(not (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-commerce-closure.js").exists(), "post-core commerce closure module must not exist")
print("commerce-closure-source-contract: OK")

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("commerce-closure-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)


def launch_browser(p):
    chromium = Path("/usr/bin/chromium")
    if chromium.exists():
        launch_kwargs = {"executable_path": str(chromium)}
    else:
        bundled = Path(p.chromium.executable_path)
        if not bundled.exists():
            print("commerce-closure-browser-smoke: SKIPPED (chromium unavailable)")
            raise SystemExit(77)
        launch_kwargs = {}
    return p.chromium.launch(headless=True, args=["--no-sandbox"], **launch_kwargs)

HTML = """<!doctype html><html><head><meta charset='utf-8'></head>
<body class='gloskin-ui1 single-product'>
<main class='gloskin-ui1-commerce-native'><div class='product product-type-variable'>
<div class='woocommerce-product-gallery'><div class='woocommerce-product-gallery__image'><img class='wp-post-image' src='data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="96" height="96"/%3E' alt='Hydrating Serum'></div></div>
<div class='summary'><h1 class='product_title'>Hydrating Serum</h1><p class='price'>Rp250.000</p>
<div data-gloskin-purchase-dock><div data-gloskin-purchase-identity><span class='gloskin-ui1-purchase-dock__title'>Hydrating Serum</span><span class='gloskin-ui1-purchase-dock__price'>Rp250.000</span></div>
<form class='variations_form cart' method='post' data-product_id='202'>
<table class='variations'><tbody><tr><th><label for='size'>Ukuran</label></th><td><select id='size' name='attribute_pa_size'><option value=''>Pilih</option><option value='30ml' selected>30 ml</option></select></td></tr></tbody></table>
<div class='single_variation_wrap'><div class='woocommerce-variation single_variation'><span class='price'>Rp250.000</span></div>
<div class='woocommerce-variation-add-to-cart variations_button'><div class='quantity'><input class='qty' name='quantity' type='number' value='1' min='1'></div><button type='submit' class='single_add_to_cart_button button alt' name='add-to-cart' value='202'>Add to cart</button><input type='hidden' name='product_id' value='202'><input class='variation_id' name='variation_id' type='hidden' value='205'></div></div>
</form></div><section class='related products'></section></div></main>
<div data-gloskin-overlay='quickadd' aria-hidden='true' hidden><button data-gloskin-overlay-close type='button'>close</button><div role='dialog'><button class='gloskin-ui1-quickadd__close' data-gloskin-overlay-close type='button'>×</button><div data-gloskin-quickadd-body></div></div></div>
<div data-gloskin-overlay='cart' aria-hidden='true' hidden><div role='dialog'><button data-gloskin-overlay-close type='button'>close cart</button></div></div>
</body></html>"""

RUNTIME = r"""
window.gloskinData={woo:true,restUrl:'/wp-json/gloskin/v1/',addToCartAjaxUrl:'/?wc-ajax=add_to_cart',cartUrl:'#cart'};
window.wc_cart_fragments_params={}; window.wc_add_to_cart_params={};
window.__posts=0; window.__nativeClicks=0; window.__delegatedMutations=0; window.__added=0; window.__cartQty=4; window.__release=null;
window.Audio=function(){this.play=function(){return Promise.resolve();};};
window.confirm=function(){window.__confirm=(window.__confirm||0)+1;return false;};
window.alert=function(){window.__alert=(window.__alert||0)+1;};
(function(){
 const handlers=new WeakMap();
 function map(t){if(!handlers.has(t))handlers.set(t,{});return handlers.get(t);}
 function base(n){return String(n||'').split('.')[0];}
 function emit(t,n,args){if(base(n)==='added_to_cart')window.__added+=1;(map(t)[base(n)]||[]).slice().forEach(h=>h.apply(t,[null].concat(args||[])));}
 function jq(t){return {length:t?1:0,on(names,h){String(names||'').split(/\s+/).filter(Boolean).forEach(n=>{const b=base(n),m=map(t);(m[b]||(m[b]=[])).push(h);});return this;},trigger(n,args){emit(t,n,args);return this;},attr(n,v){if(t&&v!==undefined)t.setAttribute(n,v);return this;},wc_variation_form(){return this;}};}
 jq.fn={wc_variation_form:function(){}}; window.jQuery=jq;
}());
document.addEventListener('click',e=>{if(e.target&&e.target.classList&&e.target.classList.contains('single_add_to_cart_button'))window.__nativeClicks+=1;},true);
/* Simulate a delegated third-party/Woo submit mutation that must never see a submit already claimed by Gloskin core. */
document.addEventListener('submit',e=>{if(e.target&&e.target.matches&&e.target.matches('[data-gloskin-purchase-dock] form.cart')){window.__delegatedMutations+=1;window.__cartQty+=Number(e.target.querySelector('input.qty').value||1);}},false);
window.fetch=function(url,opts){
 if(String(url).indexOf('wc-ajax=add_to_cart')===-1)return Promise.reject(new Error('unexpected fetch'));
 window.__posts+=1;
 const qty=Number(opts.body.get('quantity')||1);
 return new Promise(resolve=>{window.__release=function(){window.__cartQty+=qty;resolve({ok:true,json:()=>Promise.resolve({fragments:{},cart_hash:'fixture'})});};});
};
"""

with sync_playwright() as p:
    browser=launch_browser(p)
    page=browser.new_page(viewport={"width":1280,"height":900})
    page.set_content(HTML)
    page.add_style_tag(content=CSS)
    page.add_script_tag(content=RUNTIME)
    page.add_script_tag(content=CORE)
    page.add_script_tag(content=DOCK)
    page.wait_for_selector('[data-gloskin-purchase-dock][data-gloskin-purchase-composed="true"]')
    page.wait_for_selector('[data-gloskin-variable-pdp-trigger]')

    require(page.locator('form.cart').count()==1,'PDP must retain one native form')
    require(page.locator('input.variation_id').count()==1,'PDP must retain one variation_id')
    require(page.locator('input.qty').count()==1,'PDP must retain one native qty')
    require(page.locator('.single_add_to_cart_button').count()==1,'PDP must retain one native submit')

    page.locator('[data-gloskin-variable-pdp-trigger]').click()
    page.wait_for_selector('[data-gloskin-overlay="quickadd"][aria-hidden="false"]')
    page.wait_for_selector('[data-gloskin-variable-submit-proxy]')
    page.wait_for_function("document.querySelector('[data-gloskin-variable-modal-identity]')!==null")
    require(page.locator('[data-gloskin-variable-modal-identity] img').count()==1,'PDP modal must have exactly one presentation image')
    require(page.locator('[data-gloskin-quickadd-body] form').count()==0,'PDP modal must contain zero second form')
    require(page.locator('[data-gloskin-variable-modal-identity]').inner_text().strip().replace('\n',' ')=='Hydrating Serum Rp250.000','PDP identity must expose same name/price contract')

    proxy=page.locator('[data-gloskin-variable-submit-proxy]')
    action=page.locator('[data-gloskin-purchase-action]')
    before_action=action.bounding_box(); before_proxy=proxy.bounding_box()
    start_qty=page.evaluate('window.__cartQty')
    proxy.click()
    page.wait_for_function('window.__posts===1')
    require(proxy.get_attribute('aria-busy')=='true' and 'is-loading' in (proxy.get_attribute('class') or ''),'visible proxy must own busy/loading presentation')
    require(page.evaluate('window.__delegatedMutations')==0,'claimed submit must not escape to delegated mutation owner')
    proxy.click(force=True)
    require(page.evaluate('window.__posts')==1,'repeat proxy click while busy must not dispatch second POST')
    page.evaluate('window.__release()')
    page.wait_for_function('window.__added===1')
    page.wait_for_function("document.querySelector('[data-gloskin-variable-submit-proxy]').getAttribute('aria-busy')===null")
    require(page.evaluate('window.__cartQty')==start_qty+1,'one modal click must increase cart quantity by exactly +1')
    require(page.evaluate('window.__nativeClicks')==1,'one modal click must delegate to same native submit exactly once')
    require(page.evaluate('window.__posts')==1 and page.evaluate('window.__added')==1,'one modal click must produce one POST and one added_to_cart')
    page.wait_for_timeout(0)
    require(page.locator('[data-gloskin-purchase-dock] a.added_to_cart.wc-forward').count()==0,'PDP must retain zero View Cart forward links -- none are ever created')
    after_action=action.bounding_box()
    require(before_action and after_action and abs(before_action['height']-after_action['height'])<=2,'Purchase Dock action geometry must remain stable after success')
    require(before_proxy and before_proxy['height']>=44,'visible modal CTA must remain touch-safe')
    require(page.locator('[data-gloskin-overlay="cart"]').get_attribute('aria-hidden')=='false','existing Cart overlay must own normal success feedback')

    # Close the success overlay normally before an intentional later purchase.
    page.locator('[data-gloskin-overlay="cart"] [data-gloskin-overlay-close]').click()
    page.wait_for_function("document.querySelector('[data-gloskin-overlay=\"cart\"]').getAttribute('aria-hidden')==='true'")

    # Intentional later click after settlement must be allowed again.
    page.locator('[data-gloskin-variable-pdp-trigger]').click()
    page.wait_for_selector('[data-gloskin-overlay="quickadd"][aria-hidden="false"]')
    page.locator('[data-gloskin-variable-submit-proxy]').click()
    page.wait_for_function('window.__posts===2')
    require(page.evaluate('window.__delegatedMutations')==0,'later intentional click must still have zero delegated duplicate mutation')
    page.evaluate('window.__release()')
    page.wait_for_function('window.__added===2')
    require(page.evaluate('window.__cartQty')==start_qty+2,'later intentional click must add exactly one more item')
    page.locator('[data-gloskin-overlay="cart"] [data-gloskin-overlay-close]').click()
    page.wait_for_function("document.querySelector('[data-gloskin-overlay=\"cart\"]').getAttribute('aria-hidden')==='true'")

    # Invalid Buy Now: backdrop below settled home, trigger focused, no mutation.
    page.evaluate("""() => {const f=document.querySelector('form.cart');f.querySelector('input.variation_id').value='0';const s=f.querySelector('.single_add_to_cart_button');s.classList.add('disabled','wc-variation-selection-needed');} """)
    posts=page.evaluate('window.__posts'); delegated=page.evaluate('window.__delegatedMutations')
    page.locator('[data-gloskin-buy-now]').click()
    require(page.evaluate('window.__posts')==posts and page.evaluate('window.__delegatedMutations')==delegated,'invalid Buy Now must have zero mutation')
    require(page.locator('[data-gloskin-overlay="quickadd"]').get_attribute('aria-hidden')=='true','invalid Buy Now must keep variable modal closed')
    require(page.locator('.gloskin-ui1-action-spotlight__backdrop').count()==1,'invalid Buy Now must create one backdrop')
    require(page.evaluate("document.activeElement===document.querySelector('[data-gloskin-variable-pdp-trigger]')"),'Pilih Varian must receive focus')
    z=page.evaluate("""() => ({home:parseInt(getComputedStyle(document.querySelector('.gloskin-ui1-purchase-dock-home')).zIndex||'0',10),backdrop:parseInt(getComputedStyle(document.querySelector('.gloskin-ui1-action-spotlight__backdrop')).zIndex||'0',10)})""")
    require(z['home']>z['backdrop'],f'Purchase Dock home must sit above backdrop: {z}')
    require(page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]')).position")!='relative','spotlight must not override settled dock position')
    page.locator('[data-gloskin-variable-pdp-trigger]').click()
    page.wait_for_selector('[data-gloskin-overlay="quickadd"][aria-hidden="false"]')
    require(page.locator('.gloskin-ui1-action-spotlight__backdrop').count()==0,'trigger click must dismiss spotlight')

    browser.close()

print('commerce-closure-browser-smoke: OK')
