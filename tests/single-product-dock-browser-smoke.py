#!/usr/bin/env python3
"""Real Chromium geometry/state smoke for the compact bounded single-product purchase dock."""
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
CSS_GEOMETRY = (PLUGIN / "assets/css/gloskin-ui1-single-product-geometry.css").read_text(encoding="utf-8")
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
        <div class="single_variation_wrap">
          <div class="woocommerce-variation"><div class="woocommerce-variation-availability"><p class="stock">Tersedia</p></div></div>
          <div class="woocommerce-variation-add-to-cart variations_button">
            <div class="quantity"><input class="input-text qty text" type="number" value="1"></div>
            <button id="primary-add" type="submit" class="single_add_to_cart_button button alt">Tambah ke keranjang</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="woocommerce-tabs wc-tabs-wrapper" data-test-tabs>
    <div class="woocommerce-Tabs-panel"><p>Deskripsi utama.</p>
      <div class="woocommerce"><div class="single-product"><div id="product-777" class="product product-type-simple" data-test-nested-product>
        <div class="summary" data-test-nested-summary>Different product editorial embed.</div>
      </div></div></div>
    </div>
  </div>
  <div class="related products" data-test-related>
    <h2>Produk terkait</h2>
    <ul class="products"><li class="product" data-test-related-card><a href="#"><span class="woocommerce-loop-product__title">Related</span></a></li></ul>
  </div>
</div></div>
<footer data-test-footer style="height:500px">Footer</footer>
</body></html>
"""

# Deliberately emulate the kind of Woo/theme structural white chrome visible
# in the reported hydrated PDP. The canonical dock component must neutralize
# these wrappers while leaving actual Form Kit controls surfaced.
LEGACY_FORM_CHROME = r"""
.woocommerce div.product form.cart,
.woocommerce div.product table.variations,
.woocommerce div.product table.variations tbody,
.woocommerce div.product table.variations tr,
.woocommerce div.product table.variations td,
.woocommerce div.product .single_variation_wrap,
.woocommerce div.product .woocommerce-variation,
.woocommerce div.product .woocommerce-variation-add-to-cart{background:#fff;box-shadow:0 0 0 1px #fff}
"""

FIXTURE_CSS = r"""
[data-test-pre]{height:700px}
.gloskin-ui1-commerce-native>div.product>.woocommerce-product-gallery .woocommerce-product-gallery__wrapper{min-height:1500px}
.gloskin-ui1-commerce-native>div.product>.summary .fixture-summary-copy{height:900px}
.gloskin-ui1-commerce-native>div.product>.woocommerce-tabs{min-height:520px}
.gloskin-ui1-commerce-native>div.product>.related.products{min-height:1200px}
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
      const slot=summary.querySelector('.gloskin-ui1-purchase-dock-slot');
      const related=product.querySelector(':scope>.related.products');
      const relatedCard=related.querySelector('[data-test-related-card]');
      const footer=document.querySelector('[data-test-footer]');
      const d=dock.getBoundingClientRect(), s=summary.getBoundingClientRect(), sl=slot.getBoundingClientRect(), r=related.getBoundingClientRect(), rc=relatedCard.getBoundingClientRect(), f=footer.getBoundingClientRect();
      const transparentSelectors=['form.cart','table.variations','table.variations tbody','table.variations tr','table.variations td','.single_variation_wrap','.woocommerce-variation','.woocommerce-variation-add-to-cart'];
      const backgrounds=transparentSelectors.map(sel=>getComputedStyle(dock.querySelector(sel)).backgroundColor);
      function resolvedBackground(value){ const probe=document.createElement('i'); probe.style.cssText='position:absolute;visibility:hidden;background:'+value; dock.appendChild(probe); const result=getComputedStyle(probe).backgroundColor; probe.remove(); return result; }
      function intersectionArea(a,b){ const w=Math.max(0,Math.min(a.right,b.right)-Math.max(a.left,b.left)); const h=Math.max(0,Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top)); return w*h; }
      return {
        classes:dock.className,position:getComputedStyle(dock).position,left:d.left,width:d.width,top:d.top,bottom:d.bottom,height:d.height,
        summaryLeft:s.left,summaryWidth:s.width,slotLeft:sl.left,slotWidth:sl.width,slotHeight:sl.height,
        overflow:getComputedStyle(dock).overflowY,pageOverflow:document.documentElement.scrollWidth-window.innerWidth,
        backgrounds:backgrounds,selectBackground:getComputedStyle(dock.querySelector('select')).backgroundColor,
        fieldBackground:resolvedBackground('var(--gloskin-field-bg)'),dockBackground:getComputedStyle(dock).backgroundColor,
        expectedDockBackground:resolvedBackground('var(--gloskin-bg)'),ctaBackground:getComputedStyle(dock.querySelector('.single_add_to_cart_button')).backgroundColor,
        accentBackground:resolvedBackground('var(--gloskin-accent)'),
        relatedIntersection:intersectionArea(d, r),relatedCardIntersection:intersectionArea(d, rc),footerIntersection:intersectionArea(d, f)
      };
    }""")


def assert_visual_contract(data, width, height):
    require(data['overflow'] not in ('auto', 'scroll'), f'dock gained internal scrolling at {width}x{height}: {data}')
    require(data['pageOverflow'] <= 1, f'horizontal overflow at {width}x{height}: {data}')
    require(all(bg == 'rgba(0, 0, 0, 0)' for bg in data['backgrounds']), f'structural Woo wrapper kept a panel background at {width}x{height}: {data}')
    require(data['selectBackground'] == data['fieldBackground'], f'variation select left canonical Form Kit field surface at {width}x{height}: {data}')
    require(data['dockBackground'] == data['expectedDockBackground'], f'dock is not the neutral Gloskin surface at {width}x{height}: {data}')
    require(data['ctaBackground'] == data['accentBackground'], f'active Add to Cart is not the Gloskin accent at {width}x{height}: {data}')


with sync_playwright() as p:
    chromium = Path('/usr/bin/chromium')
    if not chromium.exists():
        print("single-product-dock-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)
    browser = p.chromium.launch(headless=True, executable_path=str(chromium), args=['--no-sandbox'])

    for width, height in [(1728,900),(1440,900),(1024,768),(768,1024),(430,932),(390,844),(844,390)]:
        page = browser.new_page(viewport={"width":width,"height":height})
        page.set_content(HTML)
        page.add_style_tag(content=CSS_BASE + "\n" + LEGACY_FORM_CHROME + "\n" + CSS_CORE + "\n" + CSS_GEOMETRY + "\n" + FIXTURE_CSS)
        page.add_script_tag(content=RUNTIME)
        page.evaluate("window.__primaryFormBefore=document.querySelector('.gloskin-ui1-commerce-native>div.product form.cart')")
        page.add_script_tag(content=JS_CORE)
        page.add_script_tag(content=JS_DOCK)
        page.wait_for_timeout(120)

        require(page.evaluate("getComputedStyle(document.querySelector('.gloskin-ui1-commerce-native>div.product')).display") == 'grid', 'primary product lost grid scope')
        require(page.evaluate("getComputedStyle(document.querySelector('[data-test-nested-product]')).display") != 'grid', 'nested product inherited primary grid scope')
        require(page.locator('[data-gloskin-purchase-dock]').count() == 1, 'purchase dock duplicated')
        require(page.locator('.gloskin-ui1-commerce-native>div.product form.cart').count() == 1, 'primary product must retain exactly one form.cart')
        require(page.evaluate("window.__primaryFormBefore===document.querySelector('.gloskin-ui1-commerce-native>div.product form.cart')"), 'form node identity changed during initialization')

        marker_doc_top = page.evaluate("document.querySelector('.gloskin-ui1-purchase-dock-marker').getBoundingClientRect().top+scrollY")
        page.evaluate("y=>scrollTo(0,y)", max(0, marker_doc_top + 20))
        page.wait_for_timeout(180)
        active = snapshot(page)
        assert_visual_contract(active, width, height)

        if height < 560:
            require('is-floating' not in active['classes'] and 'is-boundary' not in active['classes'], f'short viewport floated: {active}')
            require(active['position'] not in ('fixed','absolute'), f'short viewport did not degrade to flow: {active}')
            page.close(); continue

        require('is-floating' in active['classes'] and active['position'] == 'fixed', f'dock did not genuinely float at {width}x{height}: {active}')
        require(active['width'] <= active['slotWidth'] + 1.1, f'floating dock exceeded original purchase-column anchor at {width}x{height}: {active}')
        require(active['left'] >= 15 and active['left'] + active['width'] <= width - 15, f'floating dock violated viewport gutter at {width}x{height}: {active}')
        if width >= 1024:
            require(active['width'] <= 721, f'desktop dock exceeded deliberate 720px cap at {width}x{height}: {active}')
        require(active['slotHeight'] >= active['height'] - 1, f'placeholder does not preserve dock height at {width}x{height}: {active}')
        baseline_width = active['width']

        old_height = active['height']
        page.evaluate("""() => { const x=document.createElement('div'); x.style.height='64px'; x.textContent='variation availability'; document.querySelector('[data-gloskin-purchase-dock] form.cart').appendChild(x); }""")
        page.wait_for_timeout(180)
        grown = snapshot(page)
        assert_visual_contract(grown, width, height)
        require(grown['height'] >= old_height + 60, f'dock resize was not observed at {width}x{height}: {grown}')
        require(grown['slotHeight'] >= grown['height'] - 1, f'placeholder stale after dock resize at {width}x{height}: {grown}')
        require(abs(grown['width'] - baseline_width) < 1.1, f'dock width jumped after content resize at {width}x{height}: {grown}')

        page.locator('#pa_ukuran').focus()
        tabs_doc_top = page.evaluate("document.querySelector('.gloskin-ui1-commerce-native>div.product>.woocommerce-tabs').getBoundingClientRect().top+scrollY")
        page.evaluate("y=>scrollTo(0,y)", tabs_doc_top + 80)
        page.wait_for_timeout(180)
        through_tabs = snapshot(page)
        require('is-floating' in through_tabs['classes'] and through_tabs['position'] == 'fixed', f'dock stopped before/inside Tabs at {width}x{height}: {through_tabs}')
        require(abs(through_tabs['width'] - baseline_width) < 1.1, f'dock width jumped through Tabs at {width}x{height}: {through_tabs}')
        require(page.evaluate("document.activeElement===document.querySelector('#pa_ukuran')"), f'focus moved during dock state change at {width}x{height}')

        related_doc_top = page.evaluate("document.querySelector('.gloskin-ui1-commerce-native>div.product>.related.products').getBoundingClientRect().top+scrollY")
        page.evaluate("y=>scrollTo(0,y)", related_doc_top + 100)
        page.wait_for_timeout(180)
        through_related = snapshot(page)
        require('is-floating' in through_related['classes'] and through_related['position'] == 'fixed', f'dock did not float through Related Products at {width}x{height}: {through_related}')
        require(abs(through_related['width'] - baseline_width) < 1.1, f'dock width jumped through Related Products at {width}x{height}: {through_related}')

        boundary_doc_top = page.evaluate("document.querySelector('.gloskin-ui1-purchase-dock-end').getBoundingClientRect().top+scrollY")
        release_line = height - 16 - through_related['height'] - 16
        page.evaluate("y=>scrollTo(0,y)", max(0, boundary_doc_top - release_line + 2))
        page.wait_for_timeout(200)
        boundary = snapshot(page)
        assert_visual_contract(boundary, width, height)
        require('is-boundary' in boundary['classes'] and boundary['position'] == 'absolute', f'dock did not release at end of Related Products at {width}x{height}: {boundary}')
        require(abs(boundary['width'] - baseline_width) < 1.1, f'dock width jumped at boundary at {width}x{height}: {boundary}')
        require(boundary['relatedIntersection'] == 0 and boundary['relatedCardIntersection'] == 0, f'released dock obscures Related Products at {width}x{height}: {boundary}')
        require(boundary['footerIntersection'] == 0, f'released dock overlaps footer at {width}x{height}: {boundary}')
        require(page.evaluate("window.__primaryFormBefore===document.querySelector('.gloskin-ui1-commerce-native>div.product form.cart')"), f'form node identity changed at {width}x{height}')

        page.evaluate('window.scrollTo(0, document.body.scrollHeight)')
        page.wait_for_timeout(160)
        after = snapshot(page)
        require(after['position'] != 'fixed' and 'is-floating' not in after['classes'], f'dock still covers Footer after release at {width}x{height}: {after}')
        require(after['footerIntersection'] == 0, f'dock/footer intersection returned after release at {width}x{height}: {after}')
        require(page.evaluate("window.__primaryFormBefore===document.querySelector('.gloskin-ui1-commerce-native>div.product form.cart')"), f'form identity changed after release at {width}x{height}')
        page.close()

    browser.close()

print('single-product-dock-browser-smoke: OK')
