#!/usr/bin/env python3
"""Real Chromium geometry/state smoke for the bounded single-product purchase dock."""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("single-product-dock-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugin" / "gloskin-site-core"
CSS_BASE = (PLUGIN / "assets/css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CSS_CORE = (PLUGIN / "assets/css/gloskin-ui1-core.css").read_text(encoding="utf-8")
JS_CORE = (PLUGIN / "assets/js/gloskin-ui1-core.js").read_text(encoding="utf-8")
JS_DOCK = (PLUGIN / "assets/js/gloskin-ui1-purchase-dock.js").read_text(encoding="utf-8")

HTML = r"""
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="wp-singular single single-product gloskin-ui1 gloskin-ui1--medical woocommerce woocommerce-page">
<div data-test-pre style="height:700px"></div>
<div class="woocommerce gloskin-ui1-commerce-native">
<div id="product-501" class="product type-product product-type-variable">
  <div class="woocommerce-product-gallery"><div class="woocommerce-product-gallery__wrapper"><div class="woocommerce-product-gallery__image"><img src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22600%22 height=%22600%22/%3E" alt=""></div></div></div>
  <div class="summary entry-summary">
    <h1 class="product_title">Gloskin Fresh Gel Facial Wash</h1>
    <div class="fixture-summary-copy">Purchase information</div>
    <div class="gloskin-ui1-purchase-dock" data-gloskin-purchase-dock>
      <form class="variations_form cart" action="#" method="post" data-product_id="501">
        <table class="variations"><tbody><tr><th><label for="pa_ukuran">Ukuran</label></th><td><select id="pa_ukuran"><option>30 ml</option></select></td></tr></tbody></table>
        <div class="single_variation_wrap"><div class="woocommerce-variation-add-to-cart variations_button">
          <div class="quantity"><input class="input-text qty text" type="number" value="1"></div>
          <button id="primary-add" type="submit" class="single_add_to_cart_button button alt">Tambah ke keranjang</button>
        </div></div>
      </form>
    </div>
  </div>
  <div class="woocommerce-tabs wc-tabs-wrapper" data-test-tabs>
    <div class="woocommerce-Tabs-panel"><p>Deskripsi utama.</p>
      <div class="woocommerce"><div class="single-product"><div id="product-777" class="product product-type-simple" data-test-nested-product>
        <div class="summary" data-test-nested-summary><form class="cart"><button>Nested add</button></form></div>
      </div></div></div>
    </div>
  </div>
  <div class="related products" data-test-related><h2>Produk terkait</h2></div>
</div></div>
<footer data-test-footer style="height:500px">Footer</footer>
</body></html>
"""

FIXTURE_CSS = r"""
[data-test-pre]{height:700px}
.gloskin-ui1-commerce-native>div.product>.woocommerce-product-gallery .woocommerce-product-gallery__wrapper{min-height:1500px}
.gloskin-ui1-commerce-native>div.product>.summary .fixture-summary-copy{height:900px}
.gloskin-ui1-commerce-native>div.product>.woocommerce-tabs{min-height:520px}
.gloskin-ui1-commerce-native>div.product>.related.products{min-height:420px}
@media(max-width:1040px){[data-test-pre]{height:300px}}
"""

RUNTIME = r"""
window.gloskinData={woo:true,restUrl:'/wp-json/gloskin/v1/',restNonce:'fixture',cartUrl:'/cart/',addToCartAjaxUrl:'/?wc-ajax=add_to_cart'};
window.wc_cart_fragments_params={}; window.wc_add_to_cart_params={};
(function(){ function jq(target){ return {length:target?1:0,on:function(){return this;},trigger:function(){return this;},attr:function(n,v){if(target&&target.setAttribute)target.setAttribute(n,v);return this;}};} jq.fn={wc_variation_form:function(){}}; window.jQuery=jq; }());
"""


def require(value, message):
    if not value:
        raise AssertionError(message)


def snapshot(page):
    return page.evaluate("""() => {
      const product=document.querySelector('.gloskin-ui1-commerce-native>div.product');
      const summary=product.querySelector(':scope>.summary');
      const dock=summary.querySelector('[data-gloskin-purchase-dock]');
      const tabs=product.querySelector(':scope>.woocommerce-tabs');
      const slot=summary.querySelector('.gloskin-ui1-purchase-dock-slot');
      const d=dock.getBoundingClientRect(), s=summary.getBoundingClientRect(), t=tabs.getBoundingClientRect();
      return {classes:dock.className,position:getComputedStyle(dock).position,left:d.left,width:d.width,top:d.top,bottom:d.bottom,height:d.height,summaryLeft:s.left,summaryWidth:s.width,tabsTop:t.top,slotHeight:slot?slot.getBoundingClientRect().height:-1,formCount:product.querySelectorAll(':scope>.summary [data-gloskin-purchase-dock] form.cart').length,pageForms:product.querySelectorAll('form.cart').length,overflow:getComputedStyle(dock).overflowY};
    }""")


with sync_playwright() as p:
    chromium = Path('/usr/bin/chromium')
    if not chromium.exists():
        print("single-product-dock-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)
    browser = p.chromium.launch(headless=True, executable_path=str(chromium), args=['--no-sandbox'])

    for width, height in [(1728,900),(1440,900),(1024,768),(768,1024),(390,844),(430,932),(844,390)]:
        page = browser.new_page(viewport={"width":width,"height":height})
        page.set_content(HTML)
        page.add_style_tag(content=CSS_BASE + "\n" + CSS_CORE + "\n" + FIXTURE_CSS)
        page.add_script_tag(content=RUNTIME)
        page.evaluate("window.__primaryFormBefore=document.querySelector('[data-gloskin-purchase-dock] form.cart')")
        page.add_script_tag(content=JS_CORE)
        page.add_script_tag(content=JS_DOCK)
        page.wait_for_timeout(120)

        require(page.evaluate("getComputedStyle(document.querySelector('.gloskin-ui1-commerce-native>div.product')).display") == 'grid', 'primary product lost grid scope')
        require(page.evaluate("getComputedStyle(document.querySelector('[data-test-nested-product]')).display") != 'grid', 'nested product inherited primary grid scope')
        require(page.locator('[data-gloskin-purchase-dock]').count() == 1, 'purchase dock duplicated')
        require(page.locator('[data-gloskin-purchase-dock] form.cart').count() == 1, 'dock must retain exactly one native form.cart')

        summary_doc_top = page.evaluate("document.querySelector('.gloskin-ui1-commerce-native>div.product>.summary').getBoundingClientRect().top+scrollY")
        page.evaluate("y=>scrollTo(0,y)", max(0, summary_doc_top + 30))
        page.wait_for_timeout(160)
        active = snapshot(page)
        require(active['overflow'] not in ('auto','scroll'), f'dock gained internal scrolling at {width}x{height}: {active}')
        require(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), f'horizontal overflow at {width}x{height}')

        if height < 560:
            require('is-floating' not in active['classes'] and 'is-boundary' not in active['classes'], f'short viewport floated: {active}')
            require(active['position'] not in ('fixed','absolute'), f'short viewport did not degrade to flow: {active}')
            page.close(); continue

        require('is-floating' in active['classes'] and active['position'] == 'fixed', f'dock did not genuinely float at {width}x{height}: {active}')
        require(abs(active['left'] - active['summaryLeft']) < 1.1, f'floating dock left drifted from summary at {width}x{height}: {active}')
        require(abs(active['width'] - active['summaryWidth']) < 1.1, f'floating dock width drifted from summary at {width}x{height}: {active}')
        require(abs((height - active['bottom']) - 16) < 1.5, f'floating bottom gap wrong at {width}x{height}: {active}')
        require(active['slotHeight'] >= active['height'] - 1, f'placeholder does not preserve dock height at {width}x{height}: {active}')

        old_height = active['height']
        page.evaluate("""() => { const x=document.createElement('div'); x.style.height='64px'; x.textContent='variation availability'; document.querySelector('[data-gloskin-purchase-dock] form.cart').appendChild(x); }""")
        page.wait_for_timeout(160)
        grown = snapshot(page)
        require(grown['height'] >= old_height + 60, f'dock resize was not observed at {width}x{height}: {grown}')
        require(grown['slotHeight'] >= grown['height'] - 1, f'placeholder stale after dock resize at {width}x{height}: {grown}')

        page.locator('#pa_ukuran').focus()
        tabs_doc_top = page.evaluate("document.querySelector('.gloskin-ui1-commerce-native>div.product>.woocommerce-tabs').getBoundingClientRect().top+scrollY")
        page.evaluate("y=>scrollTo(0,y)", max(0, tabs_doc_top - height + 8))
        page.wait_for_timeout(180)
        boundary = snapshot(page)
        require('is-boundary' in boundary['classes'] and boundary['position'] == 'absolute', f'dock did not settle before tabs at {width}x{height}: {boundary}')
        require(boundary['bottom'] <= boundary['tabsTop'] - 10, f'dock overlaps tabs at {width}x{height}: {boundary}')
        require(page.evaluate("document.activeElement===document.querySelector('#pa_ukuran')"), f'focus moved during dock state change at {width}x{height}')
        require(page.evaluate("window.__primaryFormBefore===document.querySelector('[data-gloskin-purchase-dock] form.cart')"), f'form node identity changed at {width}x{height}')

        related_doc_top = page.evaluate("document.querySelector('.gloskin-ui1-commerce-native>div.product>.related.products').getBoundingClientRect().top+scrollY")
        page.evaluate("y=>scrollTo(0,y)", related_doc_top + 120)
        page.wait_for_timeout(160)
        after = snapshot(page)
        require(after['position'] != 'fixed' and 'is-floating' not in after['classes'], f'dock still covers Related/Footer at {width}x{height}: {after}')
        require(page.evaluate("window.__primaryFormBefore===document.querySelector('[data-gloskin-purchase-dock] form.cart')"), f'form identity changed after release at {width}x{height}')
        page.close()

    browser.close()

print('single-product-dock-browser-smoke: OK')
