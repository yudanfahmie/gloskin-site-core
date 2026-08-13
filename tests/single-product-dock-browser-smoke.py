#!/usr/bin/env python3
"""Chromium regression for the Gloskin one-row single-product purchase
command bar, including the explicit floating/settling/home/lifting state
machine, the stable footer-handoff sentinel, hysteresis, transition lock,
FLIP settle/lift continuity, the ambient floating signature, and reduced-
motion coverage."""
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

for old_contract in ("DESKTOP_MAX_WIDTH", "max-width:720px", "slot.getBoundingClientRect", "anchorGeometry", ".is-relocated", "grid-template-columns:minmax(0,1.35fr)", "function homeReachedNow", "function rebuildHomeObserver"):
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
            <div class="quantity"><input class="input-text qty text" type="number" value="1" min="1" max="6" step="1"></div>
            <button type="submit" class="single_add_to_cart_button button alt">Add to cart</button>
          </div>
        </div>
      </form>""" if variable else r"""
      <form class="cart" action="#" method="post">
        <div class="quantity"><input class="input-text qty text" type="number" value="1" min="1" max="6" step="1"></div>
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

VIEWPORTS = [(1728, 900), (1440, 900), (1024, 768), (768, 1024), (430, 932), (390, 844), (844, 390)]

ENTRANCE_SETTLE_MS = 380  # comfortably past the 300ms CSS entrance transition
FLIP_SETTLE_MS = 420  # comfortably past the 280ms CSS FLIP transition + fallback margin


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
      const sentinel=product.querySelector(':scope>.gloskin-ui1-purchase-dock-sentinel');
      const home=product.querySelector(':scope>.gloskin-ui1-purchase-dock-home');
      const origin=product.querySelector('.summary .gloskin-ui1-purchase-dock-origin');
      const footer=document.querySelector('[data-footer]');
      const relatedCard=document.querySelector('[data-related-card]');
      const d=dock.getBoundingClientRect(), p=product.getBoundingClientRect(), r=related.getBoundingClientRect(), h=home?home.getBoundingClientRect():null, s=sentinel?sentinel.getBoundingClientRect():null, f=footer.getBoundingClientRect(), rc=relatedCard.getBoundingClientRect();
      const pr=productRegion.getBoundingClientRect(), ar=actionRegion.getBoundingClientRect();
      const ds=getComputedStyle(dock), fs=getComputedStyle(form), cta=dock.querySelector('.single_add_to_cart_button'), select=dock.querySelector('select');
      const wrappers=['form.cart','[data-gloskin-purchase-product]','[data-gloskin-purchase-action]','table.variations','table.variations tbody','table.variations tr','table.variations td','.single_variation_wrap','.woocommerce-variation','.woocommerce-variation-add-to-cart'].map(sel=>dock.querySelector(sel)).filter(Boolean);
      function resolved(value){const x=document.createElement('i');x.style.cssText='position:absolute;visibility:hidden;background:'+value+';color:'+value;document.body.appendChild(x);const s=getComputedStyle(x);const out={background:s.backgroundColor,color:s.color};x.remove();return out;}
      return {classes:dock.className,position:ds.position,visibility:ds.visibility,opacity:Number(ds.opacity),transform:ds.transform,left:d.left,width:d.width,top:d.top,bottom:d.bottom,height:d.height,dockRadius:ds.borderRadius,
        productLeft:p.left,productWidth:p.width,relatedBottom:r.bottom,relatedCardTop:rc.top,relatedCardBottom:rc.bottom,relatedCardLeft:rc.left,relatedCardRight:rc.right,footerTop:f.top,footerBottom:f.bottom,footerLeft:f.left,footerRight:f.right,
        sentinelTop:s?s.top:null,homeTop:h?h.top:null,homeMinHeight:home?home.style.minHeight:null,dockBackground:ds.backgroundColor,formColumns:fs.gridTemplateColumns,
        wrapperBackgrounds:wrappers.map(el=>getComputedStyle(el).backgroundColor),wrapperShadows:wrappers.map(el=>getComputedStyle(el).boxShadow),wrapperRadii:wrappers.map(el=>getComputedStyle(el).borderRadius),
        selectBackground:select?getComputedStyle(select).backgroundColor:null,ctaBackground:getComputedStyle(cta).backgroundColor,ctaColor:getComputedStyle(cta).color,
        accent:resolved('var(--gloskin-accent)').background,inverse:resolved('var(--gloskin-inverse)').background,accentStrong:resolved('var(--gloskin-accent-strong)').color,field:resolved('var(--gloskin-bg)').background,
        title:identity.querySelector('.gloskin-ui1-purchase-dock__title').textContent.trim(),price:identity.querySelector('.gloskin-ui1-purchase-dock__price').textContent.trim(),
        productRect:{left:pr.left,right:pr.right,top:pr.top,bottom:pr.bottom},actionRect:{left:ar.left,right:ar.right,top:ar.top,bottom:ar.bottom},
        pageOverflow:document.documentElement.scrollWidth-document.documentElement.clientWidth,
        dockParentIsHome:!!home&&dock.parentElement===home,homeIsDirectChildOfProduct:!!home&&home.parentElement===product,homeAfterRelated:!!home&&!!sentinel&&related.nextElementSibling===sentinel&&sentinel.nextElementSibling===home,originInSummary:!!origin&&origin.parentElement===product.querySelector(':scope>.summary'),
        identityInProduct:identity.parentElement===productRegion,variationInProduct:!select||select.closest('table.variations').parentElement===productRegion,quantityInAction:actionRegion.contains(dock.querySelector('.quantity')),submitInAction:actionRegion.contains(cta),
        customFormClass:form.classList.contains('gloskin-ui1-purchase-dock__form'),customSubmitClass:cta.classList.contains('gloskin-ui1-purchase-dock__submit'),
        customQuantityClass:!dock.querySelector('.quantity')||dock.querySelector('.quantity').classList.contains('gloskin-ui1-purchase-dock__quantity'),
        customVariantsClass:!dock.querySelector('table.variations')||dock.querySelector('table.variations').classList.contains('gloskin-ui1-purchase-dock__variants'),
        customVariationActionClass:!dock.querySelector('.single_variation_wrap')||dock.querySelector('.single_variation_wrap').classList.contains('gloskin-ui1-purchase-dock__variation-action'),
        customVariationStateClass:!dock.querySelector('.woocommerce-variation.single_variation')||dock.querySelector('.woocommerce-variation.single_variation').classList.contains('gloskin-ui1-purchase-dock__variation-state')};
    }""")


def rects_intersect(a_left, a_top, a_right, a_bottom, b_left, b_top, b_right, b_bottom):
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
        cols = [x for x in data['formColumns'].split(' ') if x.endswith('px')]
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
    require(page.locator('input.qty').count() == 1 and page.locator('.gloskin-ui1-purchase-dock__qty-minus').count() == 1 and page.locator('.gloskin-ui1-purchase-dock__qty-plus').count() == 1, f'{kind}: quantity steppers duplicated at {width}x{height}')


def assert_qty_steppers(page, kind, width, height):
    require(page.evaluate("window.__quantityBefore.classList.contains('gloskin-ui1-purchase-dock__qty-control')"), f'{kind}: qty-control class missing at {width}x{height}')
    before = page.evaluate("document.querySelector('input.qty').value")
    page.click('.gloskin-ui1-purchase-dock__qty-plus')
    page.click('.gloskin-ui1-purchase-dock__qty-plus')
    after_plus = page.evaluate("document.querySelector('input.qty').value")
    require(float(after_plus) == float(before) + 2, f'{kind}: plus stepper did not increase by native step at {width}x{height}: before={before} after={after_plus}')
    page.click('.gloskin-ui1-purchase-dock__qty-minus')
    after_minus = page.evaluate("document.querySelector('input.qty').value")
    require(float(after_minus) == float(before) + 1, f'{kind}: minus stepper did not decrease by native step at {width}x{height}: after={after_minus}')
    page.evaluate("() => { const i=document.querySelector('input.qty'); i.value = i.min || '1'; i.dispatchEvent(new Event('change',{bubbles:true})); }")
    page.click('.gloskin-ui1-purchase-dock__qty-minus')
    at_min = page.evaluate("document.querySelector('input.qty').value")
    require(float(at_min) == float(page.evaluate("document.querySelector('input.qty').min") or 1), f'{kind}: minus stepper did not clamp to native min at {width}x{height}: {at_min}')
    page.evaluate("() => { const i=document.querySelector('input.qty'); i.value = i.max || '99'; i.dispatchEvent(new Event('change',{bubbles:true})); }")
    page.click('.gloskin-ui1-purchase-dock__qty-plus')
    at_max = page.evaluate("document.querySelector('input.qty').value")
    require(float(at_max) == float(page.evaluate("document.querySelector('input.qty').max")), f'{kind}: plus stepper did not clamp to native max at {width}x{height}: {at_max}')
    page.evaluate("() => { const i=document.querySelector('input.qty'); i.value = i.min || '1'; i.dispatchEvent(new Event('change',{bubbles:true})); }")
    events = page.evaluate("""() => {
      const input = document.querySelector('input.qty');
      const seen = {input:false, change:false};
      const onInput = () => { seen.input = true; };
      const onChange = () => { seen.change = true; };
      input.addEventListener('input', onInput);
      input.addEventListener('change', onChange);
      document.querySelector('.gloskin-ui1-purchase-dock__qty-plus').click();
      input.removeEventListener('input', onInput);
      input.removeEventListener('change', onChange);
      return seen;
    }""")
    require(events['input'] and events['change'], f'{kind}: stepper click did not dispatch native input+change events at {width}x{height}: {events}')
    require(page.evaluate("window.__quantityBefore===document.querySelector('[data-gloskin-purchase-dock] .quantity') && window.__quantityBefore.querySelector('input.qty')===document.querySelector('input.qty')"), f'{kind}: quantity input node identity changed by stepper interaction at {width}x{height}')
    require(page.locator('.gloskin-ui1-purchase-dock__qty-minus').count() == 1 and page.locator('.gloskin-ui1-purchase-dock__qty-plus').count() == 1, f'{kind}: stepper buttons duplicated after interaction at {width}x{height}')


def launch_browser(p):
    chromium = Path('/usr/bin/chromium')
    if chromium.exists():
        launch_kwargs = {'executable_path': str(chromium)}
    else:
        bundled = Path(p.chromium.executable_path)
        if not bundled.exists():
            print('single-product-dock-browser-smoke: SKIPPED (chromium unavailable)')
            raise SystemExit(77)
        launch_kwargs = {}
    return p.chromium.launch(headless=True, args=['--no-sandbox'], **launch_kwargs)


def load_page(browser, kind, width, height, reduced_motion=None):
    page = browser.new_page(viewport={'width': width, 'height': height})
    if reduced_motion:
        page.emulate_media(reduced_motion=reduced_motion)
    page.set_content(markup(kind))
    page.add_style_tag(content=WOO_LEGACY_CLEARFIX + '\n' + CSS_BASE + '\n' + CSS_CORE + '\n' + CSS_GEOMETRY + '\n' + FIXTURE)
    capture_nodes(page)
    if page.evaluate("matchMedia('(scripting: enabled)').matches"):
        require(page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]')).visibility") == 'hidden', f'{kind}: pre-init anti-flicker gate missing at {width}x{height}')
    page.add_script_tag(content=JS_DOCK)
    page.wait_for_function("document.querySelector('[data-gloskin-purchase-dock]').classList.contains('is-ready')", timeout=5000)
    return page


with sync_playwright() as p:
    browser = launch_browser(p)
    for kind in ('variable', 'simple'):
        for width, height in VIEWPORTS:
            page = load_page(browser, kind, width, height)
            page.wait_for_timeout(ENTRANCE_SETTLE_MS if height >= 560 else 60)
            data = snapshot(page)
            assert_nodes(page, kind, width, height)
            assert_visual(data, kind, width, height)
            if width >= 1024:
                require(data['height'] <= 104.5, f'{kind}: desktop dock exceeds 104px target at {width}x{height}: {data}')
            require(data['dockParentIsHome'] and data['homeIsDirectChildOfProduct'] and data['homeAfterRelated'] and data['originInSummary'], f'{kind}: home/sentinel/origin architecture regressed at {width}x{height}: {data}')
            assert_qty_steppers(page, kind, width, height)

            if kind == 'variable' and width >= 1024:
                page.evaluate("""() => {const v=document.querySelector('.woocommerce-variation.single_variation');v.style.display='flex';document.querySelector('select').value='100ml';}""")
                page.wait_for_timeout(180)
                selected = snapshot(page)
                assert_nodes(page, kind, width, height)
                assert_visual(selected, kind, width, height)
                require(selected['height'] <= 104.5, f'variable: selected desktop dock exceeds 104px target at {width}x{height}: {selected}')
                page.evaluate("""() => {const b=document.querySelector('.single_add_to_cart_button');b.disabled=true;b.classList.add('disabled');document.querySelector('.stock').textContent='Stok habis';}""")
                page.wait_for_timeout(80)
                require(page.locator('.single_add_to_cart_button:disabled').count() == 1, f'variable: out-of-stock state lost native disabled submit at {width}x{height}')
                assert_nodes(page, kind, width, height)
                page.evaluate("""() => {const b=document.querySelector('.single_add_to_cart_button');b.disabled=false;b.classList.remove('disabled');document.querySelector('.woocommerce-variation.single_variation').style.display='none';}""")
                page.wait_for_timeout(80)

            if height < 560:
                # H. SHORT VIEWPORT: normal-flow home, never an oversized fixed bar.
                require('is-floating' not in data['classes'] and data['position'] != 'fixed', f'{kind}: short viewport should stay flow-only at {width}x{height}: {data}')
                page.close()
                continue

            # B. ENTRANCE: reaches floating state once, opacity/transform settle cleanly.
            require('is-floating' in data['classes'] and 'is-floating-enter' not in data['classes'] and data['position'] == 'fixed', f'{kind}: dock did not reach a clean settled floating state at {width}x{height}: {data}')
            require(abs(data['left'] - data['productLeft']) <= 1.5 and abs(data['width'] - data['productWidth']) <= 1.5, f'{kind}: floating dock lost full PDP width at {width}x{height}: {data}')
            require(data['visibility'] == 'visible' and data['opacity'] > .99, f'{kind}: dock entrance opacity did not settle at {width}x{height}: {data}')
            require(data['transform'] in ('none', 'matrix(1, 0, 0, 1, 0, 0)'), f'{kind}: dock entrance transform did not settle to identity at {width}x{height}: {data}')
            require(not rects_intersect(data['left'], data['top'], data['left'] + data['width'], data['bottom'], data['footerLeft'], data['footerTop'], data['footerRight'], data['footerBottom']), f'{kind}: floating dock overlaps Footer at {width}x{height}: {data}')

            # G. DYNAMIC HEIGHT: while floating, height changes must reflect and reserve correctly, no jitter.
            old_height = data['height']
            page.evaluate("""() => {const x=document.createElement('div');x.style.height='56px';x.textContent='dynamic variation status';document.querySelector('[data-gloskin-purchase-dock] form.cart').appendChild(x)}""")
            page.wait_for_timeout(220)
            grown = snapshot(page)
            require(grown['height'] >= old_height + 50, f'{kind}: ResizeObserver missed dynamic dock height at {width}x{height}: {grown}')
            require(abs(grown['width'] - grown['productWidth']) <= 1.5, f'{kind}: full width changed after dynamic content at {width}x{height}: {grown}')
            require('is-floating' in grown['classes'], f'{kind}: dynamic height change must not itself trigger a settle/lift jitter loop at {width}x{height}: {grown}')

            # D. SETTLE: scroll to the very bottom of the page -- floating -> home
            # must happen exactly once, the dock must never intersect Footer, and
            # native nodes/dock count must survive the transition.
            page.evaluate('scrollTo(0, document.body.scrollHeight)')
            page.wait_for_function("document.querySelector('[data-gloskin-purchase-dock]').classList.contains('is-home')", timeout=FLIP_SETTLE_MS + 2500)
            page.wait_for_timeout(60)
            stopped = snapshot(page)
            assert_visual(stopped, kind, width, height)
            assert_nodes(page, kind, width, height)
            require('is-home' in stopped['classes'] and stopped['position'] != 'fixed', f'{kind}: dock did not settle into home at page bottom at {width}x{height}: {stopped}')
            require(page.locator('[data-gloskin-purchase-dock]').count() == 1, f'{kind}: exactly one dock must exist after settle at {width}x{height}')
            require(not rects_intersect(stopped['left'], stopped['top'], stopped['left'] + stopped['width'], stopped['bottom'], stopped['footerLeft'], stopped['footerTop'], stopped['footerRight'], stopped['footerBottom']), f'{kind}: FOOTER OVERLAP: settled dock overlaps Footer at {width}x{height}: {stopped}')
            require(not rects_intersect(stopped['left'], stopped['top'], stopped['left'] + stopped['width'], stopped['bottom'], stopped['relatedCardLeft'], stopped['relatedCardTop'], stopped['relatedCardRight'], stopped['relatedCardBottom']), f'{kind}: dock overlaps final Related card at {width}x{height}: {stopped}')

            # E. BOTTOM OF PAGE: must remain home, never attempt to float again
            # while scrolling further down (there is no further down at scrollHeight,
            # so re-assert immediately after a no-op scroll).
            page.evaluate('scrollTo(0, document.body.scrollHeight)')
            page.wait_for_timeout(120)
            still_bottom = snapshot(page)
            require('is-home' in still_bottom['classes'] and still_bottom['position'] != 'fixed', f'{kind}: dock must stay home at the bottom of the page, never re-float at {width}x{height}: {still_bottom}')

            # F. RETURN UP: scroll back to the very top -- exactly one home ->
            # floating transition, same native nodes.
            page.evaluate('scrollTo(0,0)')
            page.wait_for_function("document.querySelector('[data-gloskin-purchase-dock]').classList.contains('is-floating')", timeout=FLIP_SETTLE_MS + 2500)
            page.wait_for_timeout(60)
            returned = snapshot(page)
            require('is-floating' in returned['classes'] and returned['position'] == 'fixed', f'{kind}: dock did not resume floating when scrolling upward at {width}x{height}: {returned}')
            assert_nodes(page, kind, width, height)
            require(page.locator('[data-gloskin-purchase-dock]').count() == 1, f'{kind}: exactly one dock must exist after lift at {width}x{height}')

            page.close()
    browser.close()

# -----------------------------------------------------------------------
# C. FOOTER HYSTERESIS (the actual bug class this task fixes): repeatedly
# nudge the scroll position by a few px right around the settle boundary
# and require that this does NOT repeatedly toggle floating/home. A real
# shake/bounce bug would show up here as many state-class flips.
# -----------------------------------------------------------------------
with sync_playwright() as p:
    browser = launch_browser(p)
    page = load_page(browser, 'simple', 1440, 900)
    page.wait_for_timeout(ENTRANCE_SETTLE_MS)

    transitions = page.evaluate("""() => {
      const dock = document.querySelector('[data-gloskin-purchase-dock]');
      const home = document.querySelector('.gloskin-ui1-purchase-dock-home');
      let seenClasses = dock.className;
      let flips = 0;
      const observer = new MutationObserver(() => {
        if (dock.className !== seenClasses) { flips += 1; seenClasses = dock.className; }
      });
      observer.observe(dock, { attributes: true, attributeFilter: ['class'] });
      window.__hysteresisObserver = observer;
      window.__hysteresisFlips = () => flips;
      return true;
    }""")
    require(transitions, 'hysteresis: instrumentation failed to install')

    # Find the approximate settle boundary once (a real full scroll to
    # bottom, then most of the way back) so the nudges below start right
    # near it.
    page.evaluate('scrollTo(0, document.body.scrollHeight)')
    page.wait_for_function("document.querySelector('[data-gloskin-purchase-dock]').classList.contains('is-home')", timeout=FLIP_SETTLE_MS + 2500)
    boundary_y = page.evaluate('window.scrollY')

    page.evaluate("window.__hysteresisFlips_before = window.__hysteresisFlips()")
    for delta in (2, -2, 4, -4, 2, -2, 4, -4, 2, -2):
        page.evaluate("y => scrollTo(0, Math.max(0, window.scrollY + y))", delta)
        page.wait_for_timeout(40)
    page.wait_for_timeout(200)
    flips_during_nudge = page.evaluate("window.__hysteresisFlips() - window.__hysteresisFlips_before")
    require(flips_during_nudge <= 1, f'hysteresis: +-2/+-4px scroll nudges around the settle boundary must not repeatedly toggle floating/home, got {flips_during_nudge} class-change events')
    page.close()
    browser.close()

# -----------------------------------------------------------------------
# FLIP continuity: native node identity must survive a full floating ->
# settling -> home -> lifting -> floating round trip, with no listener
# duplication (steppers/Buy Now still work identically afterward).
# -----------------------------------------------------------------------
with sync_playwright() as p:
    browser = launch_browser(p)
    page = load_page(browser, 'simple', 1440, 900)
    page.wait_for_timeout(ENTRANCE_SETTLE_MS)
    assert_nodes(page, 'simple', 1440, 900)

    page.evaluate('scrollTo(0, document.body.scrollHeight)')
    page.wait_for_function("document.querySelector('[data-gloskin-purchase-dock]').classList.contains('is-home')", timeout=FLIP_SETTLE_MS + 2500)
    assert_nodes(page, 'simple', 1440, 900)

    page.evaluate('scrollTo(0, 0)')
    page.wait_for_function("document.querySelector('[data-gloskin-purchase-dock]').classList.contains('is-floating')", timeout=FLIP_SETTLE_MS + 2500)
    assert_nodes(page, 'simple', 1440, 900)

    # Quantity stepper and Buy Now must still work identically after a full
    # round trip -- no duplicated listeners, no stale nodes.
    before = page.evaluate("document.querySelector('input.qty').value")
    page.click('.gloskin-ui1-purchase-dock__qty-plus')
    after = page.evaluate("document.querySelector('input.qty').value")
    require(float(after) == float(before) + 1, f'FLIP continuity: quantity stepper broken after a full settle/lift round trip: before={before} after={after}')
    require(page.locator('.gloskin-ui1-purchase-dock__buy-now').count() == 1, 'FLIP continuity: Buy Now control duplicated/lost after a full settle/lift round trip')
    page.close()
    browser.close()

# -----------------------------------------------------------------------
# Ambient floating signature: present while floating, paused on hover,
# absent while settled/home, pointer-events:none (never blocks clicks).
# -----------------------------------------------------------------------
with sync_playwright() as p:
    browser = launch_browser(p)
    page = load_page(browser, 'simple', 1440, 900)
    page.wait_for_timeout(ENTRANCE_SETTLE_MS)

    ambient = page.evaluate("""() => {
      const dock = document.querySelector('[data-gloskin-purchase-dock]');
      const before = getComputedStyle(dock, '::before');
      return { animation: before.animationName, pointerEvents: before.pointerEvents };
    }""")
    require(ambient['animation'] == 'gloskin-purchase-dock-sheen', f'ambient: floating state must run the sheen animation, got {ambient}')
    require(ambient['pointerEvents'] == 'none', f'ambient: decorative layer must never intercept clicks, got {ambient}')

    page.hover('[data-gloskin-purchase-dock]')
    page.wait_for_timeout(60)
    hovered = page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]'), '::before').animationPlayState")
    require(hovered == 'paused', f'ambient: sheen must pause on hover, got {hovered}')

    # Add to cart mutation triggers aria-busy on the submit button; the
    # ambient sheen must pause during it (only checked where the browser
    # supports :has(), which real Chromium does).
    page.evaluate("document.querySelector('.single_add_to_cart_button').setAttribute('aria-busy', 'true')")
    page.wait_for_timeout(60)
    busy_opacity = page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]'), '::before').opacity")
    require(float(busy_opacity) < 0.4, f'ambient: sheen must pause/fade during an active add-to-cart mutation, got opacity={busy_opacity}')
    page.close()
    browser.close()

# -----------------------------------------------------------------------
# I. REDUCED MOTION: no entrance slide, no FLIP animation, no shimmer;
# states remain correct and commerce fully functional.
# -----------------------------------------------------------------------
with sync_playwright() as p:
    browser = launch_browser(p)
    page = load_page(browser, 'simple', 1440, 900, reduced_motion='reduce')
    page.wait_for_timeout(80)  # reduced motion settles near-instantly -- no 300ms wait needed
    data = snapshot(page)
    require('is-floating' in data['classes'], f'reduced motion: dock must still reach floating state, got {data["classes"]}')
    require(data['opacity'] > .99, f'reduced motion: entrance must be immediate, no lingering partial opacity: {data}')

    ambient_reduced = page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]'), '::before').animationName")
    require(ambient_reduced in ('none', ''), f'reduced motion: ambient sheen must be disabled, got {ambient_reduced}')

    page.evaluate('scrollTo(0, document.body.scrollHeight)')
    page.wait_for_timeout(150)  # no 280ms FLIP to wait for under reduced motion
    settled = snapshot(page)
    require('is-home' in settled['classes'] and settled['position'] != 'fixed', f'reduced motion: settle must still work, immediately: {settled}')
    require(not rects_intersect(settled['left'], settled['top'], settled['left'] + settled['width'], settled['bottom'], settled['footerLeft'], settled['footerTop'], settled['footerRight'], settled['footerBottom']), f'reduced motion: settled dock must not overlap Footer: {settled}')
    assert_nodes(page, 'simple', 1440, 900)
    page.close()
    browser.close()

print('single-product-dock-browser-smoke: OK')
