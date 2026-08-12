#!/usr/bin/env python3
"""Chromium regression for the Gloskin one-row single-product purchase command bar."""
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

for old_contract in ("DESKTOP_MAX_WIDTH", "max-width:720px", "slot.getBoundingClientRect", "anchorGeometry", ".is-relocated", "grid-template-columns:minmax(0,1.35fr)"):
    if old_contract in CSS_GEOMETRY or old_contract in JS_DOCK:
        raise SystemExit(f"single-product-dock-browser-smoke: FAILED old-contract text still present: {old_contract!r}")
if "background:var(--gloskin-accent);color:var(--gloskin-inverse)" not in CSS_GEOMETRY:
    raise SystemExit("single-product-dock-browser-smoke: FAILED enhanced accent outer surface missing")


def markup(kind):
    variable = kind == "variable"
    form = r"""
      <form class="variations_form cart" action="#" method="post">
        <table class="variations"><tbody><tr><th><label for="pa_ukuran">Ukuran</label></th><td><select id="pa_ukuran"><option>Choose an option</option><option>100ml</option></select></td></tr></tbody></table>
        <div class="single_variation_wrap">
          <div class="woocommerce-variation single_variation" style="display:none"><div class="woocommerce-variation-price"><span class="price">Rp189.000</span></div><div class="woocommerce-variation-availability"><p class="stock">Tersedia</p></div></div>
          <div class="woocommerce-variation-add-to-cart variations_button">
            <div class="quantity"><input class="input-text qty text" type="number" value="1"></div>
            <button type="submit" class="single_add_to_cart_button button alt">Add to cart</button>
          </div>
        </div>
      </form>""" if variable else r"""
      <form class="cart" action="#" method="post">
        <div class="quantity"><input class="input-text qty text" type="number" value="1"></div>
        <button type="submit" class="single_add_to_cart_button button alt">Add to cart</button>
      </form>"""
    return f"""<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="single-product gloskin-ui1 woocommerce woocommerce-page">
<div data-pre></div><div class="woocommerce gloskin-ui1-commerce-native"><div class="product product-type-{kind}">
<div class="woocommerce-product-gallery"><div class="woocommerce-product-gallery__wrapper"></div></div>
<div class="summary entry-summary"><div class="fixture-summary-copy">Summary</div>
<div class="gloskin-ui1-purchase-dock" data-gloskin-purchase-dock>
<div class="gloskin-ui1-purchase-dock__identity" data-gloskin-purchase-identity><span class="gloskin-ui1-purchase-dock__title">Fresh Gel Facial Wash</span><span class="gloskin-ui1-purchase-dock__price">Rp119.000 – Rp189.000</span></div>{form}
</div></div><div class="woocommerce-tabs wc-tabs-wrapper" data-tabs>Tabs</div>
<div class="related products" data-related><h2>Related products</h2><div data-related-card></div></div></div></div>
<footer data-footer>Footer</footer></body></html>"""

# Exact relevant clearfix from WooCommerce 11.0.0's own woocommerce.css:
# `.woocommerce div.product form.cart::before,::after{content:" ";display:table}`
# plus `::after{clear:both}`. Proven live on the real hydrated staging PDP as
# the actual root cause of the one-row command bar regressing into two rows
# with product/action swapped into the wrong track: once this stylesheet
# makes form.cart a CSS Grid container, those two generated pseudo-elements
# stop being harmless clearfix decoration and become real (empty) grid
# items. This fixture embeds the exact upstream rule so a regression here is
# caught the same way single-product-ghost-space-browser-smoke.py embeds
# real WooCommerce classic-layout CSS for its own conflict class.
WOO_LEGACY_CLEARFIX = r"""
.woocommerce div.product form.cart::before,.woocommerce div.product form.cart::after{content:" ";display:table}
.woocommerce div.product form.cart::after{clear:both}
"""

FIXTURE = r"""
[data-pre]{height:700px}
.gloskin-ui1-commerce-native{width:min(calc(100% - 40px),1400px);margin-inline:auto}
.gloskin-ui1-commerce-native>div.product>.woocommerce-product-gallery .woocommerce-product-gallery__wrapper{min-height:1500px}
.gloskin-ui1-commerce-native>div.product>.summary .fixture-summary-copy{height:900px}
.gloskin-ui1-commerce-native>div.product>.woocommerce-tabs{min-height:520px}
.gloskin-ui1-commerce-native>div.product>.related.products{min-height:1200px}
[data-related-card]{height:900px;background:var(--gloskin-surface)}
[data-footer]{height:500px;background:#222;color:#fff}
.woocommerce div.product form.cart,.woocommerce div.product table.variations,.woocommerce div.product table.variations tbody,.woocommerce div.product table.variations tr,.woocommerce div.product table.variations td,.woocommerce div.product .single_variation_wrap,.woocommerce div.product .woocommerce-variation,.woocommerce div.product .woocommerce-variation-add-to-cart{background:#fff;box-shadow:0 0 0 1px #fff;border-radius:12px}
@media(max-width:1040px){[data-pre]{height:300px}}
"""

VIEWPORTS = [(1728,900),(1440,900),(1024,768),(768,1024),(430,932),(390,844),(844,390)]


def require(value, message):
    if not value:
        raise AssertionError(message)


def snapshot(page):
    return page.evaluate(r"""() => {
      const product=document.querySelector('.gloskin-ui1-commerce-native>div.product');
      const dock=product.querySelector('[data-gloskin-purchase-dock]');
      const form=dock.querySelector('form.cart');
      const productRegion=dock.querySelector('[data-gloskin-purchase-product]');
      const actionRegion=dock.querySelector('[data-gloskin-purchase-action]');
      const identity=dock.querySelector('[data-gloskin-purchase-identity]');
      const related=product.querySelector(':scope>.related.products');
      const home=product.querySelector(':scope>.gloskin-ui1-purchase-dock-home');
      const origin=product.querySelector('.summary .gloskin-ui1-purchase-dock-origin');
      const footer=document.querySelector('[data-footer]');
      const relatedCard=document.querySelector('[data-related-card]');
      const d=dock.getBoundingClientRect(), p=product.getBoundingClientRect(), r=related.getBoundingClientRect(), h=home?home.getBoundingClientRect():null, f=footer.getBoundingClientRect(), rc=relatedCard.getBoundingClientRect();
      const pr=productRegion.getBoundingClientRect(), ar=actionRegion.getBoundingClientRect();
      const ds=getComputedStyle(dock), fs=getComputedStyle(form), cta=dock.querySelector('.single_add_to_cart_button'), select=dock.querySelector('select');
      const wrappers=['form.cart','[data-gloskin-purchase-product]','[data-gloskin-purchase-action]','table.variations','table.variations tbody','table.variations tr','table.variations td','.single_variation_wrap','.woocommerce-variation','.woocommerce-variation-add-to-cart'].map(sel=>dock.querySelector(sel)).filter(Boolean);
      function resolved(value){const x=document.createElement('i');x.style.cssText='position:absolute;visibility:hidden;background:'+value+';color:'+value;document.body.appendChild(x);const s=getComputedStyle(x);const out={background:s.backgroundColor,color:s.color};x.remove();return out;}
      return {classes:dock.className,position:ds.position,visibility:ds.visibility,opacity:Number(ds.opacity),left:d.left,width:d.width,top:d.top,bottom:d.bottom,height:d.height,dockRadius:ds.borderRadius,
        productLeft:p.left,productWidth:p.width,relatedBottom:r.bottom,relatedCardTop:rc.top,relatedCardBottom:rc.bottom,relatedCardLeft:rc.left,relatedCardRight:rc.right,footerTop:f.top,footerBottom:f.bottom,footerLeft:f.left,footerRight:f.right,
        homeTop:h?h.top:null,homeMinHeight:home?home.style.minHeight:null,dockBackground:ds.backgroundColor,formColumns:fs.gridTemplateColumns,
        wrapperBackgrounds:wrappers.map(el=>getComputedStyle(el).backgroundColor),wrapperShadows:wrappers.map(el=>getComputedStyle(el).boxShadow),wrapperRadii:wrappers.map(el=>getComputedStyle(el).borderRadius),
        selectBackground:select?getComputedStyle(select).backgroundColor:null,ctaBackground:getComputedStyle(cta).backgroundColor,ctaColor:getComputedStyle(cta).color,
        accent:resolved('var(--gloskin-accent)').background,inverse:resolved('var(--gloskin-inverse)').background,accentStrong:resolved('var(--gloskin-accent-strong)').color,field:resolved('var(--gloskin-bg)').background,
        title:identity.querySelector('.gloskin-ui1-purchase-dock__title').textContent.trim(),price:identity.querySelector('.gloskin-ui1-purchase-dock__price').textContent.trim(),
        productRect:{left:pr.left,right:pr.right,top:pr.top,bottom:pr.bottom},actionRect:{left:ar.left,right:ar.right,top:ar.top,bottom:ar.bottom},
        pageOverflow:document.documentElement.scrollWidth-document.documentElement.clientWidth,
        dockParentIsHome:!!home&&dock.parentElement===home,homeIsDirectChildOfProduct:!!home&&home.parentElement===product,homeAfterRelated:!!home&&related.nextElementSibling===home,originInSummary:!!origin&&origin.parentElement===product.querySelector(':scope>.summary'),
        identityInProduct:identity.parentElement===productRegion,variationInProduct:!select||select.closest('table.variations').parentElement===productRegion,quantityInAction:actionRegion.contains(dock.querySelector('.quantity')),submitInAction:actionRegion.contains(cta),
        customFormClass:form.classList.contains('gloskin-ui1-purchase-dock__form'),customSubmitClass:cta.classList.contains('gloskin-ui1-purchase-dock__submit'),
        customQuantityClass:!dock.querySelector('.quantity')||dock.querySelector('.quantity').classList.contains('gloskin-ui1-purchase-dock__quantity'),
        customVariantsClass:!dock.querySelector('table.variations')||dock.querySelector('table.variations').classList.contains('gloskin-ui1-purchase-dock__variants'),
        customVariationActionClass:!dock.querySelector('.single_variation_wrap')||dock.querySelector('.single_variation_wrap').classList.contains('gloskin-ui1-purchase-dock__variation-action'),
        customVariationStateClass:!dock.querySelector('.woocommerce-variation.single_variation')||dock.querySelector('.woocommerce-variation.single_variation').classList.contains('gloskin-ui1-purchase-dock__variation-state')};
    }""")


def rects_intersect(a_left,a_top,a_right,a_bottom,b_left,b_top,b_right,b_bottom):
    return a_left < b_right and a_right > b_left and a_top < b_bottom and a_bottom > b_top


def assert_visual(data, kind, width, height):
    require(data['pageOverflow'] <= 1, f'{kind}: horizontal overflow at {width}x{height}: {data}')
    require(data['dockBackground'] == data['accent'], f'{kind}: outer dock is not Gloskin accent at {width}x{height}: {data}')
    dock_radius_px = float(data['dockRadius'].removesuffix('px'))
    require(8 <= dock_radius_px <= 12, f'{kind}: outer dock radius is not a restrained 8-12px premium radius at {width}x{height}: {data}')
    require(all(bg == 'rgba(0, 0, 0, 0)' for bg in data['wrapperBackgrounds']), f'{kind}: structural wrapper surface returned at {width}x{height}: {data}')
    require(all(sh == 'none' for sh in data['wrapperShadows']), f'{kind}: structural wrapper shadow returned at {width}x{height}: {data}')
    require(all(rad == '0px' for rad in data['wrapperRadii']), f'{kind}: structural wrapper radius returned at {width}x{height}: {data}')
    if kind == 'variable':
        require(data['selectBackground'] == data['field'], f'variable: select lost light field surface at {width}x{height}: {data}')
    require(data['ctaBackground'] == data['inverse'], f'{kind}: CTA is not inverse on accent at {width}x{height}: {data}')
    require(data['ctaColor'] == data['accentStrong'], f'{kind}: CTA foreground is not accent-strong at {width}x{height}: {data}')
    require(data['title'] == 'Fresh Gel Facial Wash' and data['price'], f'{kind}: product identity missing at {width}x{height}: {data}')
    require(data['identityInProduct'] and data['variationInProduct'] and data['quantityInAction'] and data['submitInAction'], f'{kind}: left/right composition ownership incorrect at {width}x{height}: {data}')
    require(data['customFormClass'] and data['customSubmitClass'] and data['customQuantityClass'] and data['customVariantsClass'] and data['customVariationActionClass'] and data['customVariationStateClass'], f'{kind}: Gloskin semantic CSS-ownership classes missing from the SAME native nodes at {width}x{height}: {data}')
    if width >= 768:
        cols=[x for x in data['formColumns'].split(' ') if x.endswith('px')]
        require(len(cols) == 2, f'{kind}: wide purchase interface is not one two-track row at {width}x{height}: {data}')
        require(data['productRect']['right'] <= data['actionRect']['left'] + 1, f'{kind}: left product and right action overlap at {width}x{height}: {data}')
        require(data['productRect']['bottom'] > data['actionRect']['top'] and data['actionRect']['bottom'] > data['productRect']['top'], f'{kind}: wide regions are not visually on one row at {width}x{height}: {data}')


def capture_nodes(page):
    page.evaluate(r"""() => {
      const d=document.querySelector('[data-gloskin-purchase-dock]');const f=d.querySelector('form.cart');
      window.__dockBefore=d;window.__formBefore=f;window.__identityBefore=d.querySelector('[data-gloskin-purchase-identity]');
      window.__selectsBefore=[...f.querySelectorAll('table.variations select')];window.__quantityBefore=f.querySelector('.quantity');window.__submitBefore=f.querySelector('.single_add_to_cart_button');window.__singleVariationBefore=f.querySelector('.woocommerce-variation.single_variation');
    }""")


def assert_nodes(page, kind, width, height):
    require(page.evaluate("window.__dockBefore===document.querySelector('[data-gloskin-purchase-dock]')"), f'{kind}: dock identity changed at {width}x{height}')
    require(page.evaluate("window.__formBefore===document.querySelector('[data-gloskin-purchase-dock] form.cart')"), f'{kind}: form identity changed at {width}x{height}')
    require(page.evaluate("window.__identityBefore===document.querySelector('[data-gloskin-purchase-identity]')"), f'{kind}: identity node changed at {width}x{height}')
    require(page.evaluate("window.__quantityBefore===document.querySelector('[data-gloskin-purchase-dock] .quantity')"), f'{kind}: quantity identity changed at {width}x{height}')
    require(page.evaluate("window.__submitBefore===document.querySelector('[data-gloskin-purchase-dock] .single_add_to_cart_button')"), f'{kind}: submit identity changed at {width}x{height}')
    require(page.evaluate("window.__selectsBefore.length===document.querySelectorAll('[data-gloskin-purchase-dock] table.variations select').length && window.__selectsBefore.every((n,i)=>n===document.querySelectorAll('[data-gloskin-purchase-dock] table.variations select')[i])"), f'{kind}: variation select identity/count changed at {width}x{height}')
    require(page.evaluate("!window.__singleVariationBefore||window.__singleVariationBefore===document.querySelector('[data-gloskin-purchase-dock] .woocommerce-variation.single_variation')"), f'{kind}: single-variation node identity changed at {width}x{height}')
    require(page.locator('[data-gloskin-purchase-dock]').count() == 1 and page.locator('form.cart').count() == 1 and page.locator('.quantity').count() == 1 and page.locator('.single_add_to_cart_button').count() == 1, f'{kind}: native Woo control count duplicated at {width}x{height}')


with sync_playwright() as p:
    chromium=Path('/usr/bin/chromium')
    if chromium.exists(): launch_kwargs={'executable_path':str(chromium)}
    else:
        bundled=Path(p.chromium.executable_path)
        if not bundled.exists():
            print('single-product-dock-browser-smoke: SKIPPED (chromium unavailable)'); raise SystemExit(77)
        launch_kwargs={}
    browser=p.chromium.launch(headless=True,args=['--no-sandbox'],**launch_kwargs)
    for kind in ('variable','simple'):
      for width,height in VIEWPORTS:
        page=browser.new_page(viewport={'width':width,'height':height})
        page.set_content(markup(kind))
        page.add_style_tag(content=WOO_LEGACY_CLEARFIX+'\n'+CSS_BASE+'\n'+CSS_CORE+'\n'+CSS_GEOMETRY+'\n'+FIXTURE)
        capture_nodes(page)
        if page.evaluate("matchMedia('(scripting: enabled)').matches"):
            require(page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]')).visibility") == 'hidden', f'{kind}: pre-init anti-flicker gate missing at {width}x{height}')
        page.add_script_tag(content=JS_DOCK)
        page.wait_for_function("document.querySelector('[data-gloskin-purchase-dock]').classList.contains('is-ready')",timeout=5000)
        page.wait_for_timeout(140)
        data=snapshot(page); assert_nodes(page,kind,width,height); assert_visual(data,kind,width,height)
        if width >= 1024:
            require(data['height'] <= 104.5, f'{kind}: desktop dock exceeds 104px target at {width}x{height}: {data}')
        require(data['dockParentIsHome'] and data['homeIsDirectChildOfProduct'] and data['homeAfterRelated'] and data['originInSummary'], f'{kind}: home/origin architecture regressed at {width}x{height}: {data}')

        if kind == 'variable' and width >= 1024:
            page.evaluate("""() => {const v=document.querySelector('.woocommerce-variation.single_variation');v.style.display='flex';document.querySelector('select').value='100ml';}""")
            page.wait_for_timeout(180)
            selected=snapshot(page); assert_nodes(page,kind,width,height); assert_visual(selected,kind,width,height)
            require(selected['height'] <= 104.5, f'variable: selected desktop dock exceeds 104px target at {width}x{height}: {selected}')
            page.evaluate("""() => {const b=document.querySelector('.single_add_to_cart_button');b.disabled=true;b.classList.add('disabled');document.querySelector('.stock').textContent='Stok habis';}""")
            page.wait_for_timeout(80)
            require(page.locator('.single_add_to_cart_button:disabled').count()==1, f'variable: out-of-stock state lost native disabled submit at {width}x{height}')
            assert_nodes(page,kind,width,height)
            page.evaluate("""() => {const b=document.querySelector('.single_add_to_cart_button');b.disabled=false;b.classList.remove('disabled');document.querySelector('.woocommerce-variation.single_variation').style.display='none';}""")
            page.wait_for_timeout(80)

        if height < 560:
            require('is-floating' not in data['classes'] and data['position'] != 'fixed', f'{kind}: short viewport should stay flow-only at {width}x{height}: {data}')
            page.close(); continue

        require('is-floating' in data['classes'] and data['position']=='fixed', f'{kind}: dock did not enter floating state at {width}x{height}: {data}')
        require(abs(data['left']-data['productLeft'])<=1.5 and abs(data['width']-data['productWidth'])<=1.5, f'{kind}: floating dock lost full PDP width at {width}x{height}: {data}')
        require(data['visibility']=='visible' and data['opacity']>.99, f'{kind}: dock reveal incomplete at {width}x{height}: {data}')
        require(not rects_intersect(data['left'],data['top'],data['left']+data['width'],data['bottom'],data['footerLeft'],data['footerTop'],data['footerRight'],data['footerBottom']), f'{kind}: floating dock overlaps Footer at {width}x{height}: {data}')

        old_height=data['height']
        page.evaluate("""() => {const x=document.createElement('div');x.style.height='56px';x.textContent='dynamic variation status';document.querySelector('[data-gloskin-purchase-dock] form.cart').appendChild(x)}""")
        page.wait_for_timeout(220)
        grown=snapshot(page)
        require(grown['height']>=old_height+50, f'{kind}: ResizeObserver missed dynamic dock height at {width}x{height}: {grown}')
        require(abs(grown['width']-grown['productWidth'])<=1.5, f'{kind}: full width changed after dynamic content at {width}x{height}: {grown}')

        home_doc_top=page.evaluate("document.querySelector('.gloskin-ui1-purchase-dock-home').getBoundingClientRect().top+scrollY")
        release_line=height-16-grown['height']; page.evaluate("y=>scrollTo(0,y)",max(0,home_doc_top-release_line+2)); page.wait_for_timeout(260)
        stopped=snapshot(page); assert_visual(stopped,kind,width,height); assert_nodes(page,kind,width,height)
        require('is-home' in stopped['classes'] and stopped['position']!='fixed', f'{kind}: dock did not settle into home at {width}x{height}: {stopped}')
        require(stopped['top']>=stopped['relatedBottom']-2 and stopped['bottom']<=stopped['footerTop']+2, f'{kind}: settled dock crossed Related/Footer boundary at {width}x{height}: {stopped}')
        require(not rects_intersect(stopped['left'],stopped['top'],stopped['left']+stopped['width'],stopped['bottom'],stopped['relatedCardLeft'],stopped['relatedCardTop'],stopped['relatedCardRight'],stopped['relatedCardBottom']), f'{kind}: dock overlaps final Related card at {width}x{height}: {stopped}')

        page.evaluate('scrollTo(0, document.body.scrollHeight)'); page.wait_for_timeout(180); after=snapshot(page)
        require(after['position']!='fixed' and 'is-floating' not in after['classes'], f'{kind}: dock stayed fixed into Footer at {width}x{height}: {after}')
        require(not rects_intersect(after['left'],after['top'],after['left']+after['width'],after['bottom'],after['footerLeft'],after['footerTop'],after['footerRight'],after['footerBottom']), f'{kind}: dock overlaps Footer at page bottom {width}x{height}: {after}')
        page.evaluate('scrollTo(0,0)'); page.wait_for_timeout(280); returned=snapshot(page)
        require('is-floating' in returned['classes'] and returned['position']=='fixed', f'{kind}: dock did not resume floating when scrolling upward at {width}x{height}: {returned}')
        assert_nodes(page,kind,width,height); page.close()
    browser.close()
print('single-product-dock-browser-smoke: OK')
