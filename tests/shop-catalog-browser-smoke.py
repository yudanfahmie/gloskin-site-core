#!/usr/bin/env python3
"""Focused browser smoke for SSR-first Shop catalog enhancement."""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("shop-catalog-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
CSS_BASE = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CSS_CORE = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css").read_text(encoding="utf-8")
JS_CORE = (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js").read_text(encoding="utf-8")

HTML = r"""<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="gloskin-ui1">
<main>
<section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-shop-catalog data-gloskin-shop-url="/shop/" data-gloskin-shop-initial-page="1">
<div class="gloskin-ui1-container">
<div class="gloskin-ui1-shop-catalog">
<nav class="gloskin-ui1-shop-categories" data-gloskin-shop-categories aria-label="Kategori produk"><ul>
<li><a href="/shop/" data-gloskin-shop-category="" aria-current="page">Semua Produk</a></li>
<li><a href="/skincare/facial-wash/" data-gloskin-shop-category="facial-wash">Facial Wash</a></li>
<li><a href="/skincare/serum/" data-gloskin-shop-category="serum">Serum</a></li>
<li><a href="/skincare/toner/" data-gloskin-shop-category="toner">Toner</a></li>
</ul></nav>
<div class="gloskin-ui1-shop-results-column"><div class="gloskin-ui1-shop-results" data-gloskin-shop-results aria-busy="false">
<div class="gloskin-ui1-catalog-header"><h2 tabindex="-1" data-gloskin-shop-results-heading>Semua Produk</h2><span data-gloskin-shop-count aria-live="polite">24 produk</span></div>
<div class="gloskin-ui1-shop-status" data-gloskin-shop-status role="status" aria-live="polite"></div>
<div class="gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-ui1-shop-grid" data-gloskin-shop-grid data-result-marker="initial">
<article class="gloskin-ui1-card gloskin-ui1-card--product"><div class="gloskin-ui1-card__body"><h3>SSR Product</h3><div class="gloskin-ui1-card__actions"><a class="gloskin-ui1-button button add_to_cart_button ajax_add_to_cart product_type_simple" href="/?add-to-cart=101" data-product_id="101">Tambah</a></div></div></article>
</div>
<nav class="gloskin-ui1-pagination gloskin-ui1-shop-pagination"><ul><li><a href="/shop/page/2/" data-gloskin-shop-page="2">2</a></li></ul></nav>
</div>
<span class="screen-reader-text" data-gloskin-shop-status-live aria-live="polite"></span>
</div>
</div></div></section>
</main>
<div class="gloskin-ui1-quickadd" data-gloskin-overlay="quickadd" aria-hidden="true" hidden>
<button class="gloskin-ui1-quickadd__backdrop" type="button" data-gloskin-overlay-close aria-label="Tutup"></button>
<div class="gloskin-ui1-quickadd__panel" role="dialog" aria-modal="true" aria-labelledby="quickadd-title"><div class="gloskin-ui1-quickadd__head"><strong id="quickadd-title">Pilih varian</strong><button class="gloskin-ui1-quickadd__close" type="button" data-gloskin-overlay-close>Tutup</button></div><div class="gloskin-ui1-quickadd__body" data-gloskin-quickadd-body></div></div>
</div>
<div class="gloskin-ui1-sheet" data-gloskin-overlay="cart" aria-hidden="true" hidden><button data-gloskin-overlay-close></button><div role="dialog"><button data-gloskin-overlay-close>Tutup</button></div></div>
</body></html>"""

RUNTIME = r"""
window.gloskinData = { woo:true, restUrl:'/wp-json/gloskin/v1/', restNonce:'fixture', addToCartAjaxUrl:'/?wc-ajax=add_to_cart' };
window.wc_cart_fragments_params = {};
window.wc_add_to_cart_params = {};
window.__shopFail = false;
window.__publicGetRequests = [];
(function(){
  const handlers = {};
  function jq(target){ return {
    length: target ? 1 : 0,
    on(name, handler){ handlers[name] = handler; return this; },
    trigger(name, args){ if (handlers[name]) { handlers[name].apply(target, [null].concat(args || [])); } return this; },
    attr(name, value){ if (target && target.setAttribute && value !== undefined) target.setAttribute(name, value); return this; },
    wc_variation_form(){ return this; }
  }; }
  jq.fn = { wc_variation_form:function(){} };
  window.jQuery = jq;
})();
function resultHtml(category, page){
  const label = category ? category.replace(/-/g,' ') : 'Semua Produk';
  const variable = category === 'serum';
  const productId = variable ? 202 : 301;
  const action = variable
    ? '<a href="/product/serum-variable/" class="gloskin-ui1-button button add_to_cart_button product_type_variable gloskin-ui1-quickadd-trigger" data-product_id="202" data-gloskin-quickadd-open data-gloskin-quickadd-product="202" aria-haspopup="dialog">Pilih Varian</a>'
    : '<a href="/?add-to-cart=301" class="gloskin-ui1-button button add_to_cart_button ajax_add_to_cart product_type_simple" data-product_id="301">Tambah</a>';
  const pagination = page === 1 ? '<nav class="gloskin-ui1-pagination gloskin-ui1-shop-pagination"><ul><li><a href="/shop/page/2/" data-gloskin-shop-page="2">2</a></li></ul></nav>' : '<nav class="gloskin-ui1-pagination gloskin-ui1-shop-pagination"><ul><li><a href="/shop/" data-gloskin-shop-page="1">1</a></li></ul></nav>';
  return '<div class="gloskin-ui1-catalog-header"><h2 tabindex="-1" data-gloskin-shop-results-heading>'+label+'</h2><span data-gloskin-shop-count aria-live="polite">12 produk</span></div>'+
    '<div class="gloskin-ui1-shop-status" data-gloskin-shop-status role="status" aria-live="polite"></div>'+
    '<div class="gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-ui1-shop-grid" data-gloskin-shop-grid data-result-marker="'+category+'-'+page+'">'+
    '<article class="gloskin-ui1-card gloskin-ui1-card--product"><div class="gloskin-ui1-card__body"><h3>'+label+' '+page+'</h3><button type="button" class="gloskin-ui1-wishlist-toggle" data-gloskin-wishlist-toggle="'+productId+'" aria-pressed="false" data-label-add="Simpan" data-label-remove="Hapus">♥</button><div class="gloskin-ui1-card__actions">'+action+'</div></div></article></div>'+pagination;
}
window.fetch = function(url, options){
  const text = String(url); options = options || {};
  if (text.indexOf('products/quick-add') !== -1 || text.indexOf('shop/catalog') !== -1) {
    window.__publicGetRequests.push({url:text, method:options.method || 'GET', credentials:options.credentials || '', headers:options.headers || null});
  }
  if (text.indexOf('products/quick-add') !== -1) {
    return Promise.resolve({ok:true,json:function(){return Promise.resolve({found:true,id:202,name:'Serum Variable',url:'/product/serum-variable/',price_html:'Rp200.000',image_html:'',form_html:'<form class="variations_form cart"><select name="attribute_pa_size"><option value="30ml">30 ml</option></select><div class="single_variation_wrap"><button type="submit" class="single_add_to_cart_button button" name="add-to-cart" value="202">Tambah</button><input class="variation_id" name="variation_id" value="205"><input name="product_id" value="202"></div></form>'});}});
  }
  if (text.indexOf('shop/catalog') !== -1) {
    const u = new URL(text, location.href); const category = u.searchParams.get('category') || ''; const page = parseInt(u.searchParams.get('page') || '1',10);
    if (window.__shopFail && category === 'facial-wash') return Promise.reject(new Error('fixture failure'));
    const delay = category === 'facial-wash' ? 160 : (category === 'toner' ? 15 : 25);
    return new Promise(function(resolve){ setTimeout(function(){ resolve({ok:true,json:function(){return Promise.resolve({html:resultHtml(category,page),category:category,page:page,total:24,max_pages:2});}}); }, delay); });
  }
  return Promise.reject(new Error('unexpected fetch '+text));
};
"""


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def marker(page):
    return page.locator('[data-gloskin-shop-grid]').get_attribute('data-result-marker')


with sync_playwright() as p:
    chromium = Path('/usr/bin/chromium')
    if not chromium.exists():
        print("shop-catalog-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)
    browser = p.chromium.launch(headless=True, executable_path=str(chromium), args=['--no-sandbox'])
    page = browser.new_page(viewport={"width": 1280, "height": 900})
    page.route('http://gloskin.test/shop/', lambda route: route.fulfill(status=200, content_type='text/html', body=HTML))
    page.goto('http://gloskin.test/shop/')
    page.evaluate("localStorage.setItem('gloskin_wishlist', JSON.stringify([202]))")
    page.add_style_tag(content=CSS_BASE + "\n" + CSS_CORE)
    page.add_script_tag(content=RUNTIME)
    page.add_script_tag(content=JS_CORE)

    require(marker(page) == 'initial', 'initial product grid must remain SSR')
    require(page.locator('[data-gloskin-shop-category]').count() == 4, 'fixture must expose one all-products link plus mapped category links')
    require(page.locator('[data-gloskin-shop-category="serum"]').get_attribute('href') == '/skincare/serum/', 'category must retain canonical no-JS skincare URL')
    require(page.locator('[data-gloskin-shop-page="2"]').get_attribute('href') == '/shop/page/2/', 'SSR pagination must retain a real URL')
    require(page.locator('[role="tab"], [role="tabpanel"]').count() == 0, 'category navigation must not masquerade as tabs')

    sidebar = page.locator('[data-gloskin-shop-categories]').bounding_box()
    require(sidebar and 205 <= sidebar['width'] <= 245, 'desktop category sidebar must stay approximately 210-240px')
    require(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), 'desktop Shop must not horizontally overflow')

    results_height_before = page.locator('[data-gloskin-shop-results]').bounding_box()['height']
    serum = page.locator('[data-gloskin-shop-category="serum"]')
    # A single atomic snapshot immediately after the click: the fixture's
    # fetch for this category resolves quickly (~25ms), so multiple
    # separate Playwright round-trips risk crossing into the success path
    # mid-check the way one batched read cannot.
    serum.click()
    snapshot = page.evaluate(r"""() => {
      const results = document.querySelector('[data-gloskin-shop-results]');
      const skeleton = document.querySelector('[data-gloskin-shop-skeleton]');
      const live = document.querySelector('[data-gloskin-shop-status-live]');
      return {
        busy: results.getAttribute('aria-busy'),
        marker: document.querySelector('[data-gloskin-shop-grid]').dataset.resultMarker,
        skeletonPresent: !!skeleton,
        skeletonAriaHidden: skeleton ? skeleton.getAttribute('aria-hidden') : null,
        skeletonCards: skeleton ? skeleton.querySelectorAll('.gloskin-ui1-shop-skeleton__card').length : 0,
        previousGridPresent: !!document.querySelector('[data-gloskin-shop-grid][data-result-marker="initial"]'),
        resultsHeight: results.getBoundingClientRect().height,
        liveStatus: live ? live.textContent : null
      };
    }""")
    require(snapshot['busy'] == 'true', f'category request must mark results busy: {snapshot}')
    require(snapshot['marker'] == 'initial', f'loading must preserve the previous successful grid: {snapshot}')
    require(snapshot['skeletonPresent'], f'category request must overlay a skeleton immediately: {snapshot}')
    require(snapshot['skeletonAriaHidden'] == 'true', f'skeleton overlay must be aria-hidden: {snapshot}')
    require(snapshot['skeletonCards'] >= 6, f'skeleton must render enough placeholder cards to fill the visible result area: {snapshot}')
    require(snapshot['previousGridPresent'], f'previous grid must remain in the DOM underneath the skeleton overlay: {snapshot}')
    require(abs(snapshot['resultsHeight'] - results_height_before) <= 2, f"skeleton must not shift page height ({results_height_before} -> {snapshot['resultsHeight']})")
    require((snapshot['liveStatus'] or '').strip() != '', f'skeleton must expose a screen-reader loading status: {snapshot}')
    page.wait_for_function("document.querySelector('[data-gloskin-shop-grid]').dataset.resultMarker === 'serum-1'")
    require(page.locator('[data-gloskin-shop-results]').get_attribute('aria-busy') == 'false', 'success must clear aria-busy')
    require(page.locator('[data-gloskin-shop-skeleton]').count() == 0, 'success must remove the skeleton overlay')
    require(page.evaluate("document.querySelector('[data-gloskin-shop-status-live]').textContent") == '', 'success must clear the screen-reader loading status')
    require(page.evaluate("!document.querySelector('[data-gloskin-shop-results]').style.minHeight"), 'success must release the locked results height')
    require(page.locator('[data-gloskin-shop-category="serum"]').get_attribute('aria-current') == 'page', 'successful category must update aria-current')
    require('#category=serum' in page.url and 'page=' not in page.url, 'category state must use clean hash state and reset page 1')
    require(page.evaluate("document.activeElement === document.querySelector('[data-gloskin-shop-category=\"serum\"]')"), 'ordinary category selection must not force focus into results')
    first_shop_request = page.evaluate("window.__publicGetRequests.find(r => r.url.includes('shop/catalog'))")
    require(first_shop_request and first_shop_request['method'] == 'GET' and first_shop_request['credentials'] == 'same-origin', f'Shop category GET transport invalid: {first_shop_request}')
    require(first_shop_request['headers'] in (None, {}), f'Shop category GET leaked stale nonce/header: {first_shop_request}')

    wished = page.locator('[data-gloskin-wishlist-toggle="202"]')
    require(wished.get_attribute('aria-pressed') == 'true' and 'is-wished' in (wished.get_attribute('class') or ''), 'injected wished product must restore active wishlist state')

    page.locator('[data-gloskin-quickadd-open]').click()
    page.wait_for_selector('[data-gloskin-overlay="quickadd"][aria-hidden="false"]')
    page.wait_for_selector('[data-gloskin-quickadd-body] form.variations_form')
    quick_request = page.evaluate("window.__publicGetRequests.find(r => r.url.includes('products/quick-add'))")
    require(quick_request and quick_request['headers'] in (None, {}) and quick_request['method'] == 'GET', f'Quick Add public GET leaked stale nonce/header: {quick_request}')
    page.keyboard.press('Escape')

    page.locator('[data-gloskin-shop-page="2"]').click()
    page.wait_for_function("document.querySelector('[data-gloskin-shop-grid]').dataset.resultMarker === 'serum-2'")
    require('#category=serum&page=2' in page.url, 'pagination must preserve selected category in history state')
    page_two_request = page.evaluate("window.__publicGetRequests.filter(r => r.url.includes('shop/catalog') && r.url.includes('page=2')).slice(-1)[0]")
    require(page_two_request and page_two_request['headers'] in (None, {}) and page_two_request['method'] == 'GET', f'Shop pagination GET leaked stale nonce/header: {page_two_request}')
    require(page.evaluate("document.activeElement.hasAttribute('data-gloskin-shop-results-heading')"), 'pagination success must move focus to new results heading')

    page.locator('[data-gloskin-shop-category="facial-wash"]').click()
    page.wait_for_timeout(8)
    require(page.locator('[data-gloskin-shop-skeleton]').count() == 1, 'rapid successive category clicks must keep exactly one skeleton overlay')
    page.locator('[data-gloskin-shop-category="toner"]').click()
    require(page.locator('[data-gloskin-shop-skeleton]').count() == 1, 'skeleton must not flash/duplicate when a newer request supersedes an in-flight one')
    page.wait_for_function("document.querySelector('[data-gloskin-shop-grid]').dataset.resultMarker === 'toner-1'")
    page.wait_for_timeout(190)
    require(marker(page) == 'toner-1', 'stale response must not overwrite latest category')
    require(page.locator('[data-gloskin-shop-category="toner"]').get_attribute('aria-current') == 'page', 'latest category remains active after stale response resolves')
    require(page.locator('[data-gloskin-shop-grid] .ajax_add_to_cart.product_type_simple').count() == 1, 'injected simple card must retain Woo native delegated Add to Cart contract')
    require(page.locator('[data-gloskin-shop-skeleton]').count() == 0, 'skeleton must be gone once the latest (non-stale) request finally settles')

    page.evaluate('window.__shopFail = true')
    page.locator('[data-gloskin-shop-category="facial-wash"]').click()
    page.wait_for_function("document.querySelector('[data-gloskin-shop-results]').getAttribute('aria-busy') === 'false'")
    require(page.locator('[data-gloskin-shop-skeleton]').count() == 0, 'failure must remove the skeleton and reveal the previous grid again')
    require(marker(page) == 'toner-1', 'failed GET must preserve previous successful results')
    require(page.locator('[data-gloskin-shop-retry]').count() == 1, 'failed GET must expose safe retry')
    require(page.locator('[data-gloskin-shop-status] a').get_attribute('href') == '/skincare/facial-wash/', 'failure must expose normal canonical fallback link')
    page.evaluate('window.__shopFail = false')
    page.locator('[data-gloskin-shop-retry]').click()
    page.wait_for_function("document.querySelector('[data-gloskin-shop-grid]').dataset.resultMarker === 'facial-wash-1'")

    page.locator('[data-gloskin-shop-category="serum"]').click()
    page.wait_for_function("document.querySelector('[data-gloskin-shop-grid]').dataset.resultMarker === 'serum-1'")
    page.locator('[data-gloskin-shop-category="toner"]').click()
    page.wait_for_function("document.querySelector('[data-gloskin-shop-grid]').dataset.resultMarker === 'toner-1'")
    page.evaluate('history.back()')
    page.wait_for_function("document.querySelector('[data-gloskin-shop-grid]').dataset.resultMarker === 'serum-1'")
    require(page.locator('[data-gloskin-shop-category="serum"]').get_attribute('aria-current') == 'page', 'back must restore previous category state')
    page.evaluate('history.forward()')
    page.wait_for_function("document.querySelector('[data-gloskin-shop-grid]').dataset.resultMarker === 'toner-1'")

    page.set_viewport_size({"width": 390, "height": 844})
    page.wait_for_timeout(50)
    require(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), 'mobile Shop must not horizontally overflow')
    nav_overflow = page.evaluate("(function(){var n=document.querySelector('[data-gloskin-shop-categories]'); return n.scrollWidth >= n.clientWidth;})()")
    require(nav_overflow, 'mobile category nav must remain usable as the same horizontal scroller')
    grid_box = page.locator('[data-gloskin-shop-grid]').bounding_box()
    require(grid_box and grid_box['x'] >= -1 and grid_box['x'] + grid_box['width'] <= 391, 'mobile product grid must stay within viewport')

    browser.close()

print('shop-catalog-browser-smoke: OK')
