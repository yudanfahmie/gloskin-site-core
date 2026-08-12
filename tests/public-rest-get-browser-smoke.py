#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
JS = (ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js').read_text(encoding='utf-8')

HTML = r"""<!doctype html><html><body class="gloskin-ui1">
<button data-gloskin-search-open aria-controls="search">Search</button>
<div data-gloskin-overlay="search" aria-hidden="true" hidden><div role="dialog"><button data-gloskin-overlay-close>Close</button><input data-gloskin-search-input><button data-gloskin-search-clear hidden>Clear</button><div data-gloskin-search-results></div></div></div>
<button data-gloskin-wishlist-open aria-controls="wishlist">Wishlist<span data-gloskin-wishlist-count>0</span><span data-gloskin-wishlist-count-sr>0 produk favorit</span></button>
<div class="gloskin-ui1-sheet" data-gloskin-overlay="wishlist" aria-hidden="true" hidden><div role="dialog"><button data-gloskin-overlay-close>Close</button><div data-gloskin-wishlist-body></div></div></div>
<a href="/product/202/" data-gloskin-quickadd-open data-gloskin-quickadd-product="202">Quick</a>
<div data-gloskin-overlay="quickadd" aria-hidden="true" hidden><div role="dialog"><button data-gloskin-overlay-close>Close</button><div data-gloskin-quickadd-body></div></div></div>
<div data-gloskin-shop-catalog data-gloskin-shop-url="/shop/" data-gloskin-shop-initial-page="1"><nav data-gloskin-shop-categories><a href="/shop/" data-gloskin-shop-category="">All</a><a href="/skincare/serum/" data-gloskin-shop-category="serum">Serum</a></nav><div data-gloskin-shop-results aria-busy="false"><div data-gloskin-shop-status></div><div data-gloskin-shop-grid data-marker="initial">Initial</div></div><span data-gloskin-shop-status-live></span></div>
<div data-gloskin-overlay="cart" aria-hidden="true" hidden><div role="dialog"><button data-gloskin-overlay-close>Close</button></div></div>
<script>
window.gloskinData={woo:true,restUrl:'/wp-json/gloskin/v1/',restNonce:'known-stale-nonce',addToCartAjaxUrl:''};
window.__publicRequests=[];
(function(){
  const handlers={};
  function jq(target){return {length:target?1:0,on:function(name,fn){handlers[name]=fn;return this;},trigger:function(name,args){if(handlers[name])handlers[name].apply(target,[{type:name}].concat(args||[]));return this;},attr:function(name,value){if(target&&target.setAttribute&&value!==undefined)target.setAttribute(name,value);return this;},wc_variation_form:function(){return this;}};}
  jq.fn={wc_variation_form:function(){}}; window.jQuery=jq;
})();
function response(data){return Promise.resolve({ok:true,json:function(){return Promise.resolve(data);}});}
window.fetch=function(url,options){
  const text=String(url); options=options||{};
  window.__publicRequests.push({url:text,method:options.method||'GET',credentials:options.credentials||'',headers:options.headers||null});
  if(text.indexOf('/search?')!==-1)return response({groups:[]});
  if(text.indexOf('products/resolve?')!==-1)return response({products:[{id:42,name:'Saved',url:'/product/42/',price_html:'Rp1'}]});
  if(text.indexOf('products/quick-add?')!==-1)return response({found:true,id:202,name:'Variable',url:'/product/202/',price_html:'Rp2',image_html:'',form_html:'<form class="variations_form cart"><select name="attribute_pa_size"><option value="30">30</option></select><div class="single_variation_wrap"><button type="submit" class="single_add_to_cart_button" name="add-to-cart" value="202">Add</button><input class="variation_id" name="variation_id" value="205"><input name="product_id" value="202"></div></form>'});
  if(text.indexOf('shop/catalog?')!==-1)return response({html:'<div data-gloskin-shop-status></div><div data-gloskin-shop-grid data-marker="serum">Serum</div>',category:'serum',page:1,total:1,max_pages:1});
  return Promise.reject(new Error('unexpected '+text));
};
</script>
</body></html>"""

def require(cond,msg):
    if not cond: raise AssertionError(msg)

with sync_playwright() as p:
    chromium=Path('/usr/bin/chromium')
    if not chromium.exists():
        print('public REST GET browser smoke: SKIPPED (chromium unavailable)')
        raise SystemExit(77)
    browser=p.chromium.launch(headless=True,executable_path=str(chromium),args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':1280,'height':900})
    page.route('http://gloskin.test/**',lambda route: route.fulfill(status=200,content_type='text/html',body='<!doctype html>'))
    page.goto('http://gloskin.test/storefront')
    page.set_content(HTML)
    page.evaluate("localStorage.setItem('gloskin_wishlist', JSON.stringify([42]))")
    page.add_script_tag(content=JS)

    page.locator('[data-gloskin-search-open]').click()
    page.locator('[data-gloskin-search-input]').fill('serum')
    page.wait_for_timeout(280)
    page.locator('[data-gloskin-overlay="search"] [data-gloskin-overlay-close]').click()

    page.locator('[data-gloskin-wishlist-open]').click()
    page.wait_for_function("window.__publicRequests.some(r=>r.url.includes('products/resolve?'))")
    page.locator('[data-gloskin-overlay="wishlist"] [data-gloskin-overlay-close]').click()

    page.locator('[data-gloskin-quickadd-open]').click()
    page.wait_for_function("window.__publicRequests.some(r=>r.url.includes('products/quick-add?'))")
    page.locator('[data-gloskin-overlay="quickadd"] [data-gloskin-overlay-close]').click()

    page.locator('[data-gloskin-shop-category="serum"]').click()
    page.wait_for_function("window.__publicRequests.some(r=>r.url.includes('shop/catalog?'))")

    requests=page.evaluate('window.__publicRequests')
    expected=('search?','products/resolve?','products/quick-add?','shop/catalog?')
    for marker in expected:
        matching=[r for r in requests if marker in r['url']]
        require(matching,f'public GET not exercised: {marker}; requests={requests}')
        for req in matching:
            require(req['method']=='GET',f'public route not GET: {req}')
            require(req['credentials']=='same-origin',f'public GET lost same-origin browser semantics: {req}')
            require(req['headers'] is None or req['headers']=={},f'stale nonce/header leaked into public GET: {req}')
    require(all('known-stale-nonce' not in str(r) for r in requests),'stale nonce leaked into request options')
    browser.close()

print('public REST GET browser smoke: OK')
