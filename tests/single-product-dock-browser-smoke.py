#!/usr/bin/env python3
"""Chromium regression for the full-block, DOM-relocated single-product purchase dock."""
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
JS_DOCK = (PLUGIN / "assets/js/gloskin-ui1-purchase-dock.js").read_text(encoding="utf-8")

HTML = r"""<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="single-product gloskin-ui1 woocommerce woocommerce-page">
<div data-pre></div>
<div class="woocommerce gloskin-ui1-commerce-native">
<div class="product product-type-variable">
  <div class="woocommerce-product-gallery"><div class="woocommerce-product-gallery__wrapper"></div></div>
  <div class="summary entry-summary">
    <div class="fixture-summary-copy">Summary</div>
    <div class="gloskin-ui1-purchase-dock" data-gloskin-purchase-dock>
      <form class="variations_form cart" action="#" method="post">
        <table class="variations"><tbody><tr><th><label for="pa_ukuran">Ukuran</label></th><td><select id="pa_ukuran"><option>Choose an option</option></select></td></tr></tbody></table>
        <div class="single_variation_wrap">
          <div class="woocommerce-variation"><div class="woocommerce-variation-availability"><p class="stock">Tersedia</p></div></div>
          <div class="woocommerce-variation-add-to-cart variations_button">
            <div class="quantity"><input class="input-text qty text" type="number" value="1"></div>
            <button type="submit" class="single_add_to_cart_button button alt">Add to cart</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="woocommerce-tabs wc-tabs-wrapper" data-tabs>Tabs</div>
  <div class="related products" data-related><h2>Related products</h2><div data-related-card></div></div>
</div></div>
<footer data-footer>Footer</footer>
</body></html>"""

FIXTURE = r"""
[data-pre]{height:700px}
.gloskin-ui1-commerce-native{width:min(calc(100% - 40px),1400px);margin-inline:auto}
.gloskin-ui1-commerce-native>div.product>.woocommerce-product-gallery .woocommerce-product-gallery__wrapper{min-height:1500px}
.gloskin-ui1-commerce-native>div.product>.summary .fixture-summary-copy{height:900px}
.gloskin-ui1-commerce-native>div.product>.woocommerce-tabs{min-height:520px}
.gloskin-ui1-commerce-native>div.product>.related.products{min-height:1200px}
[data-related-card]{height:900px;background:var(--gloskin-surface)}
[data-footer]{height:500px;background:#222;color:#fff}
.woocommerce div.product form.cart,.woocommerce div.product table.variations,.woocommerce div.product table.variations tbody,.woocommerce div.product table.variations tr,.woocommerce div.product table.variations td,.woocommerce div.product .single_variation_wrap,.woocommerce div.product .woocommerce-variation,.woocommerce div.product .woocommerce-variation-add-to-cart{background:#fff;box-shadow:0 0 0 1px #fff}
@media(max-width:1040px){[data-pre]{height:300px}}
"""


def require(value, message):
    if not value:
        raise AssertionError(message)


def snapshot(page):
    return page.evaluate("""() => {
      const product=document.querySelector('.gloskin-ui1-commerce-native>div.product');
      const dock=product.querySelector(':scope>[data-gloskin-purchase-dock]');
      const related=product.querySelector(':scope>.related.products');
      const boundary=product.querySelector(':scope>.gloskin-ui1-purchase-dock-end');
      const footer=document.querySelector('[data-footer]');
      const p=product.getBoundingClientRect(), d=dock.getBoundingClientRect(), r=related.getBoundingClientRect(), b=boundary.getBoundingClientRect(), f=footer.getBoundingClientRect();
      const ds=getComputedStyle(dock), fs=getComputedStyle(dock.querySelector('form.cart'));
      const wrappers=['form.cart','table.variations','table.variations tbody','table.variations tr','table.variations td','.single_variation_wrap','.woocommerce-variation','.woocommerce-variation-add-to-cart'];
      const wrapperBackgrounds=wrappers.map(sel=>getComputedStyle(dock.querySelector(sel)).backgroundColor);
      function resolved(value){const x=document.createElement('i');x.style.cssText='position:absolute;visibility:hidden;background:'+value;document.body.appendChild(x);const out=getComputedStyle(x).backgroundColor;x.remove();return out;}
      return {classes:dock.className,position:ds.position,visibility:ds.visibility,opacity:Number(ds.opacity),left:d.left,width:d.width,top:d.top,bottom:d.bottom,height:d.height,
        productLeft:p.left,productWidth:p.width,relatedBottom:r.bottom,boundaryTop:b.top,footerTop:f.top,dockBackground:ds.backgroundColor,radius:ds.borderRadius,shadow:ds.boxShadow,borderTop:ds.borderTopWidth,
        formColumns:fs.gridTemplateColumns,wrapperBackgrounds,selectBackground:getComputedStyle(dock.querySelector('select')).backgroundColor,fieldBackground:resolved('var(--gloskin-field-bg)'),ctaBackground:getComputedStyle(dock.querySelector('.single_add_to_cart_button')).backgroundColor,accentBackground:resolved('var(--gloskin-accent)'),
        pageOverflow:document.documentElement.scrollWidth-document.documentElement.clientWidth,
        parentIsProduct:dock.parentElement===product,afterRelated:related.nextElementSibling===boundary&&boundary.nextElementSibling===dock};
    }""")


def assert_visual(data, width, height):
    require(data['pageOverflow'] <= 1, f'horizontal overflow at {width}x{height}: {data}')
    require(data['dockBackground'] == 'rgba(0, 0, 0, 0)', f'floating/settled dock regained white surface at {width}x{height}: {data}')
    require(data['borderTop'] == '0px', f'dock border returned at {width}x{height}: {data}')
    require(data['radius'] == '0px', f'dock radius returned at {width}x{height}: {data}')
    require(data['shadow'] == 'none', f'dock shadow returned at {width}x{height}: {data}')
    require(all(bg == 'rgba(0, 0, 0, 0)' for bg in data['wrapperBackgrounds']), f'inner white wrapper panel returned at {width}x{height}: {data}')
    require(data['selectBackground'] == data['fieldBackground'], f'field left Form Kit surface at {width}x{height}: {data}')
    require(data['ctaBackground'] == data['accentBackground'], f'CTA left canonical accent at {width}x{height}: {data}')


with sync_playwright() as p:
    chromium = Path('/usr/bin/chromium')
    if not chromium.exists():
        print("single-product-dock-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)
    browser = p.chromium.launch(headless=True, executable_path=str(chromium), args=['--no-sandbox'])

    for width, height in [(1728,900),(1440,900),(1024,768),(768,1024),(430,932),(390,844),(844,390)]:
        page = browser.new_page(viewport={"width":width,"height":height})
        page.set_content(HTML)
        page.add_style_tag(content=CSS_BASE + "\n" + CSS_CORE + "\n" + CSS_GEOMETRY + "\n" + FIXTURE)
        page.evaluate("window.__formBefore=document.querySelector('.gloskin-ui1-commerce-native>div.product form.cart')")
        if page.evaluate("matchMedia('(scripting: enabled)').matches"):
            require(page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]')).visibility") == 'hidden', f'pre-init dock not anti-flicker hidden at {width}x{height}')
        page.add_script_tag(content=JS_DOCK)
        page.wait_for_timeout(360)

        data = snapshot(page)
        require(data['parentIsProduct'] and data['afterRelated'], f'dock was not relocated once directly after Related at {width}x{height}: {data}')
        require(page.evaluate("window.__formBefore===document.querySelector('[data-gloskin-purchase-dock] form.cart')"), f'native form.cart identity changed at {width}x{height}')
        require(page.locator('[data-gloskin-purchase-dock]').count() == 1, f'dock duplicated at {width}x{height}')
        require(page.locator('form.cart').count() == 1, f'form.cart duplicated at {width}x{height}')
        assert_visual(data, width, height)

        if height < 560:
            require('is-floating' not in data['classes'] and data['position'] != 'fixed', f'short viewport should stay flow-only at {width}x{height}: {data}')
            page.close(); continue

        require('is-floating' in data['classes'] and data['position'] == 'fixed', f'dock did not slide into immediate floating state after DOM readiness at {width}x{height}: {data}')
        require(abs(data['left'] - data['productLeft']) <= 1.5, f'floating dock left edge is not full product lane at {width}x{height}: {data}')
        require(abs(data['width'] - data['productWidth']) <= 1.5, f'floating dock is not full product-lane width at {width}x{height}: {data}')
        require(data['visibility'] == 'visible' and data['opacity'] > .99, f'dock did not finish reveal at {width}x{height}: {data}')
        if width >= 1024:
            require(len([x for x in data['formColumns'].split(' ') if x.endswith('px')]) >= 2, f'desktop purchase controls did not distribute horizontally at {width}x{height}: {data}')

        old_height = data['height']
        page.evaluate("""() => {const x=document.createElement('div');x.style.height='56px';x.textContent='dynamic variation status';document.querySelector('[data-gloskin-purchase-dock] form.cart').appendChild(x)}""")
        page.wait_for_timeout(220)
        grown = snapshot(page)
        require(grown['height'] >= old_height + 50, f'ResizeObserver missed dynamic dock height at {width}x{height}: {grown}')
        require(abs(grown['width'] - grown['productWidth']) <= 1.5, f'full width changed after dynamic content at {width}x{height}: {grown}')

        boundary_doc_top = page.evaluate("document.querySelector('.gloskin-ui1-purchase-dock-end').getBoundingClientRect().top+scrollY")
        release_line = height - 16 - grown['height']
        page.evaluate("y=>scrollTo(0,y)", max(0, boundary_doc_top - release_line + 2))
        page.wait_for_timeout(260)
        stopped = snapshot(page)
        assert_visual(stopped, width, height)
        require('is-boundary' in stopped['classes'] and stopped['position'] != 'fixed', f'dock did not stop at its post-Related flow position at {width}x{height}: {stopped}')
        require(stopped['top'] >= stopped['relatedBottom'] - 2, f'settled dock entered Related content at {width}x{height}: {stopped}')
        require(stopped['bottom'] <= stopped['footerTop'] + 2, f'settled dock entered Footer at {width}x{height}: {stopped}')

        page.evaluate('scrollTo(0, document.body.scrollHeight)')
        page.wait_for_timeout(180)
        after = snapshot(page)
        require(after['position'] != 'fixed' and 'is-floating' not in after['classes'], f'dock stayed fixed into Footer at {width}x{height}: {after}')

        page.evaluate('scrollTo(0, 0)')
        page.wait_for_timeout(280)
        returned = snapshot(page)
        require('is-floating' in returned['classes'] and returned['position'] == 'fixed', f'dock did not resume floating when returning above Related at {width}x{height}: {returned}')
        require(page.evaluate("window.__formBefore===document.querySelector('[data-gloskin-purchase-dock] form.cart')"), f'form node identity changed after full lifecycle at {width}x{height}')
        page.close()

    browser.close()

print("single-product-dock-browser-smoke: OK")
