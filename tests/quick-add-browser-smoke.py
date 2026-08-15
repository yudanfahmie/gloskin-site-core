#!/usr/bin/env python3
"""Focused browser fixture for the reusable Gloskin variable-product modal."""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
JS_CORE_SOURCE = (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js").read_text(encoding="utf-8")
PURCHASE_DOCK_JS_SOURCE = (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-purchase-dock.js").read_text(encoding="utf-8")
CSS_BASE_SOURCE = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CSS_CORE_SOURCE = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css").read_text(encoding="utf-8")
CSS_POLISH_SOURCE = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-quickadd-polish.css").read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


# Static guards always run, even when Playwright/Chromium is unavailable.
quickadd_start = JS_CORE_SOURCE.index('\tfunction initQuickAdd() {')
quickadd_end = JS_CORE_SOURCE.index('\n\tfunction ', quickadd_start + 1)
quickadd_js = JS_CORE_SOURCE[quickadd_start:quickadd_end]
require("select[name^=\"attribute_\"]" in quickadd_js, "variable modal must read native Woo attribute selects")
require("data-gloskin-variable-chip" in quickadd_js, "variable modal chips missing")
require("select.value = chip.getAttribute('data-gloskin-variable-chip')" in quickadd_js, "chip must update the same native select")
require("select.dispatchEvent(new Event('change', { bubbles: true }))" in quickadd_js, "chip must dispatch native bubbling change")
require("data-gloskin-variable-submit-proxy" in quickadd_js, "always-active CTA proxy missing")
require("Pilih varian terlebih dahulu." in quickadd_js, "required-selection notice missing")
require("Varian yang dipilih belum tersedia." in quickadd_js, "unavailable-selection notice missing")
require("submit.click();" in quickadd_js, "valid proxy must trigger the same native submit")
require("function allSelectsCanEnhance(selects)" in quickadd_js, "transactional attribute preflight missing")
require("function failOpenPdp(form, dock)" in quickadd_js, "PDP fail-open rollback missing")
require("gloskin-ui1-variable-catalog-enhanced" in quickadd_js, "successful catalog enhancement marker missing")
require("showActionSpotlight(trigger);" in quickadd_js, "invalid Buy Now spotlight handoff missing")
require("MutationObserver" not in quickadd_js and "setInterval" not in quickadd_js, "variable modal must have no observer/polling state owner")
require("submit.disabled = false" not in quickadd_js and "removeAttribute('disabled')" not in quickadd_js, "native Woo submit must never be manually enabled")
require(".gloskin-ui1 .gloskin-ui1-variable-chips .gloskin-ui1-variable-chip" in CSS_POLISH_SOURCE, "shared semantic chip owner missing")
require(".gloskin-ui1 .gloskin-ui1-variable-catalog-enhanced .reset_variations" in CSS_POLISH_SOURCE, "catalog-only native Clear suppression missing")
require("!important" not in CSS_POLISH_SOURCE, "variable modal CSS must add zero !important")
require("grid-template-columns:auto minmax(0,1fr)" in CSS_POLISH_SOURCE, "bottom row must be quantity + flexible CTA")
require(".gloskin-ui1-variable-modal__actions.is-quantity-hidden .gloskin-ui1-variable-modal__qty-proxy" in CSS_POLISH_SOURCE and "display:none" in CSS_POLISH_SOURCE, "sold-individually PDP qty proxy hide rule missing")
require("new MutationObserver" not in PURCHASE_DOCK_JS_SOURCE and "setInterval(" not in PURCHASE_DOCK_JS_SOURCE, "Purchase Dock must keep zero new mutation observer/polling")
require("window.confirm" not in JS_CORE_SOURCE + PURCHASE_DOCK_JS_SOURCE and "window.alert" not in JS_CORE_SOURCE + PURCHASE_DOCK_JS_SOURCE, "native confirm/alert must not be introduced")
print('quick-add-source-contract: OK (static, no browser required)')

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("quick-add-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

PRODUCT_VARIATIONS = [
    {
        'variation_id': 205,
        'attributes': {'attribute_pa_size': '30ml', 'attribute_pa_finish': 'natural'},
        'is_in_stock': True,
        'is_purchasable': True,
        'variation_is_active': True,
        'is_sold_individually': 'no',
        'min_qty': 1,
        'max_qty': 3,
        'price_html': '<span class="price">Rp250.000</span>',
        'availability_html': '<p class="stock in-stock">Tersedia</p>',
    },
    {
        'variation_id': 206,
        'attributes': {'attribute_pa_size': '30ml', 'attribute_pa_finish': 'rose'},
        'is_in_stock': True,
        'is_purchasable': True,
        'variation_is_active': True,
        'is_sold_individually': 'no',
        'min_qty': 1,
        'max_qty': 2,
        'price_html': '<span class="price">Rp265.000</span>',
        'availability_html': '<p class="stock in-stock">Tersedia</p>',
    },
    {
        'variation_id': 207,
        'attributes': {'attribute_pa_size': '50ml', 'attribute_pa_finish': 'natural'},
        'is_in_stock': True,
        'is_purchasable': True,
        'variation_is_active': True,
        'is_sold_individually': 'yes',
        'min_qty': 1,
        'max_qty': 1,
        'price_html': '<span class="price">Rp400.000</span>',
        'availability_html': '<p class="stock in-stock">Tersedia (satuan)</p>',
    },
]

FORM_HTML = (
    '<form class="variations_form cart" action="/product/hydrating-serum/" method="post" data-product_id="202" data-product_variations=\'PRODUCT_VARIATIONS_JSON\'>'
    '<div class="table-container"><table class="variations"><tbody>'
    '<tr><th><label for="pa_size">Ukuran</label></th><td><select id="pa_size" name="attribute_pa_size">'
    '<option value="">Pilih</option><option value="30ml">30 ml</option><option value="50ml">50 ml</option></select></td></tr>'
    '<tr><th><label for="pa_finish">Warna</label></th><td><select id="pa_finish" name="attribute_pa_finish">'
    '<option value="">Pilih</option><option value="natural">Natural</option><option value="rose">Rose</option></select>'
    '<a class="reset_variations" href="#">Clear</a></td></tr>'
    '</tbody></table></div>'
    '<div class="single_variation_wrap">'
    '<div class="woocommerce-variation single_variation"></div>'
    '<div class="woocommerce-variation-add-to-cart variations_button">'
    '<div class="quantity"><input class="input-text qty text" type="number" name="quantity" value="1" min="1" max="5" step="1"></div>'
    '<button type="submit" class="single_add_to_cart_button button alt wc-variation-selection-needed disabled" disabled name="add-to-cart" value="202">Add to cart</button>'
    '<input type="hidden" name="product_id" value="202">'
    '<input type="hidden" class="variation_id" name="variation_id" value="0">'
    '</div></div></form>'
).replace('PRODUCT_VARIATIONS_JSON', json.dumps(PRODUCT_VARIATIONS))

BROKEN_FORM_HTML = (
    '<form class="variations_form cart" action="/product/broken/" method="post" data-product_id="303" data-product_variations="[]">'
    '<table class="variations"><tbody>'
    '<tr><th><label for="broken_size">Ukuran</label></th><td><select id="broken_size" name="attribute_pa_size">'
    '<option value="">Pilih</option><option value="30ml">30 ml</option></select></td></tr>'
    '<tr><th><label for="broken_finish">Warna</label></th><td><select id="broken_finish" name="attribute_pa_finish">'
    '<option value="">Pilih</option></select></td></tr>'
    '</tbody></table>'
    '<div class="single_variation_wrap"><div class="woocommerce-variation single_variation"></div>'
    '<div class="woocommerce-variation-add-to-cart variations_button">'
    '<div class="quantity"><input class="input-text qty text" type="number" name="quantity" value="1" min="1" max="5" step="1"></div>'
    '<button type="submit" class="single_add_to_cart_button button alt disabled" disabled name="add-to-cart" value="303">Add to cart</button>'
    '<input type="hidden" name="product_id" value="303"><input type="hidden" class="variation_id" name="variation_id" value="0">'
    '</div></div></form>'
)

MODAL_HTML = r"""
<div class="gloskin-ui1-quickadd" data-gloskin-overlay="quickadd" aria-hidden="true" hidden>
  <button class="gloskin-ui1-quickadd__backdrop" type="button" data-gloskin-overlay-close aria-label="Tutup pilihan produk"></button>
  <div class="gloskin-ui1-quickadd__panel" role="dialog" aria-modal="true" aria-labelledby="quickadd-title">
    <div class="gloskin-ui1-quickadd__head">
      <strong id="quickadd-title">Pilih Varian</strong>
      <button class="gloskin-ui1-quickadd__close" type="button" data-gloskin-overlay-close aria-label="Tutup">×</button>
    </div>
    <div class="gloskin-ui1-quickadd__body" data-gloskin-quickadd-body aria-live="polite"></div>
  </div>
</div>
"""

HTML = r"""
<!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="gloskin-ui1">
<main style="max-width:980px;margin:40px auto;padding:0 20px">
  <a href="/product/hydrating-serum/" class="button add_to_cart_button product_type_variable gloskin-ui1-quickadd-trigger"
     data-product_id="202" data-gloskin-quickadd-open data-gloskin-quickadd-product="202" aria-haspopup="dialog">Pilih Varian</a>
</main>
MODAL_TOKEN
</body>
</html>
""".replace('MODAL_TOKEN', MODAL_HTML)

PDP_HTML = r"""
<!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="gloskin-ui1 single-product">
<main class="gloskin-ui1-commerce-native" style="max-width:980px;margin:40px auto;padding:0 20px">
  <div class="product product-type-variable">
    <div class="summary">
      <div data-gloskin-purchase-dock>
        <div data-gloskin-purchase-identity><strong>Hydrating Serum</strong><span>Rp250.000</span></div>
        FORM_TOKEN
      </div>
    </div>
    <section class="related products"><h2>Produk terkait</h2></section>
  </div>
</main>
MODAL_TOKEN
</body>
</html>
""".replace('FORM_TOKEN', FORM_HTML).replace('MODAL_TOKEN', MODAL_HTML)

BROKEN_PDP_HTML = r"""
<!doctype html>
<html><head><meta charset="utf-8"></head>
<body class="gloskin-ui1 single-product">
<main class="gloskin-ui1-commerce-native"><div class="product product-type-variable"><div class="summary">
<div data-gloskin-purchase-dock><div data-gloskin-purchase-identity><strong>Broken Product</strong></div>FORM_TOKEN</div>
</div><section class="related products"></section></div></main>
MODAL_TOKEN
</body></html>
""".replace('FORM_TOKEN', BROKEN_FORM_HTML).replace('MODAL_TOKEN', MODAL_HTML)

RUNTIME = r"""
window.gloskinData = {
  woo: true,
  restUrl: '/wp-json/gloskin/v1/',
  addToCartAjaxUrl: '/?wc-ajax=add_to_cart',
  cartUrl: '/cart/'
};
window.wc_cart_fragments_params = {};
window.wc_add_to_cart_params = {};
window.__mutationCount = 0;
window.__nativeSubmitClicks = 0;
window.__audioPlayCount = 0;
window.__confirmCount = 0;
window.__alertCount = 0;
window.__fetchCalls = [];
window.confirm = function () { window.__confirmCount += 1; return false; };
window.alert = function () { window.__alertCount += 1; };
window.Audio = function (src) {
  this.src = src;
  this.preload = '';
  this.volume = 1;
  this.loop = false;
  this.currentTime = 0;
  this.play = function () { window.__audioPlayCount += 1; return Promise.resolve(); };
};

document.addEventListener('click', function (event) {
  if (event.target && event.target.classList && event.target.classList.contains('single_add_to_cart_button')) {
    window.__nativeSubmitClicks += 1;
  }
}, true);

(function () {
  const targetHandlers = new WeakMap();
  function registry(target) {
    if (!targetHandlers.has(target)) { targetHandlers.set(target, {}); }
    return targetHandlers.get(target);
  }
  function baseEvent(name) { return String(name || '').split('.')[0]; }
  function emit(target, name, args) {
    const handlers = (registry(target)[baseEvent(name)] || []).slice();
    handlers.forEach(function (handler) { handler.apply(target, [null].concat(args || [])); });
  }
  function wireVariationForm(form) {
    if (!form || form.dataset.fixtureVariationReady === '1') { return; }
    form.dataset.fixtureVariationReady = '1';
    const selects = Array.from(form.querySelectorAll('select[name^="attribute_"]'));
    const variationId = form.querySelector('input.variation_id');
    const submit = form.querySelector('.single_add_to_cart_button');
    const quantity = form.querySelector('.quantity');
    const qty = quantity && quantity.querySelector('input.qty');
    const state = form.querySelector('.woocommerce-variation.single_variation');
    let variations = [];
    try { variations = JSON.parse(form.getAttribute('data-product_variations') || '[]'); } catch (e) {}

    function reset() {
      variationId.value = '0';
      submit.disabled = true;
      submit.classList.add('disabled');
      state.innerHTML = '';
      if (quantity) { quantity.style.display = ''; }
      if (qty) { qty.min = '1'; qty.max = '5'; qty.step = '1'; qty.value = '1'; }
      emit(form, 'reset_data');
    }
    function refresh() {
      const size = form.querySelector('[name="attribute_pa_size"]');
      const finish = form.querySelector('[name="attribute_pa_finish"]');
      const rose = finish && finish.querySelector('option[value="rose"]');
      if (rose && size) {
        rose.disabled = size.value === '50ml';
        if (rose.disabled && finish.value === 'rose') { finish.value = ''; }
      }
      emit(form, 'woocommerce_update_variation_values');

      const values = {};
      let complete = true;
      selects.forEach(function (select) { values[select.name] = select.value; if (!select.value) { complete = false; } });
      if (!complete) { reset(); return; }
      const match = variations.find(function (variation) {
        return Object.keys(values).every(function (name) { return variation.attributes[name] === values[name]; });
      });
      if (!match || !match.is_in_stock || !match.is_purchasable || !match.variation_is_active) { reset(); return; }
      variationId.value = String(match.variation_id);
      submit.disabled = false;
      submit.classList.remove('disabled', 'wc-variation-selection-needed');
      if (qty) { qty.min = String(match.min_qty); qty.max = String(match.max_qty); qty.value = '1'; }
      if (quantity) { quantity.style.display = match.is_sold_individually === 'yes' ? 'none' : ''; }
      state.innerHTML = match.price_html + match.availability_html;
      emit(form, 'found_variation', [match]);
    }
    selects.forEach(function (select) { select.addEventListener('change', refresh); });
    reset();
  }
  window.__wireVariationForm = wireVariationForm;

  function jq(target) {
    return {
      length: target ? 1 : 0,
      on(names, handler) {
        String(names || '').split(/\s+/).filter(Boolean).forEach(function (name) {
          const base = baseEvent(name);
          const map = registry(target);
          if (!map[base]) { map[base] = []; }
          map[base].push(handler);
        });
        return this;
      },
      trigger(name, args) { emit(target, name, args); return this; },
      attr(name, value) { if (target && target.setAttribute && value !== undefined) { target.setAttribute(name, value); } return this; },
      wc_variation_form() { if (target) { wireVariationForm(target); } return this; }
    };
  }
  jq.fn = { wc_variation_form: function () {} };
  window.jQuery = jq;
}());

window.fetch = function (url) {
  window.__fetchCalls.push(String(url));
  if (String(url).indexOf('products/quick-add') !== -1) {
    return Promise.resolve({
      ok: true,
      json: function () {
        return Promise.resolve({
          found: true,
          id: 202,
          name: 'Hydrating Serum',
          url: '/product/hydrating-serum/',
          price_html: '<span class="amount">Rp250.000</span>',
          image_html: '<img class="gloskin-ui1-quickadd__image" src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2296%22 height=%2296%22/%3E" alt="">',
          form_html: FORM_HTML_TOKEN
        });
      }
    });
  }
  if (String(url).indexOf('wc-ajax=add_to_cart') !== -1) {
    window.__mutationCount += 1;
    return Promise.resolve({ ok: true, json: function () { return Promise.resolve({ fragments: {}, cart_hash: 'fixture' }); } });
  }
  return Promise.reject(new Error('fixture unexpected fetch'));
};
""".replace('FORM_HTML_TOKEN', json.dumps(FORM_HTML))


def add_styles(page):
    page.add_style_tag(content=CSS_BASE_SOURCE + "\n" + CSS_CORE_SOURCE + "\n" + CSS_POLISH_SOURCE)


def open_quickadd(page):
    trigger = page.locator('[data-gloskin-quickadd-open]')
    trigger.click()
    page.wait_for_selector('[data-gloskin-overlay="quickadd"][aria-hidden="false"]')
    page.wait_for_selector('[data-gloskin-quickadd-body] form.variations_form')
    page.wait_for_selector('[data-gloskin-variable-submit-proxy]')
    return trigger


def chip_style(locator):
    return locator.evaluate("""node => {
      const cs = getComputedStyle(node);
      return {
        borderRadius: cs.borderRadius,
        backgroundColor: cs.backgroundColor,
        borderColor: cs.borderTopColor,
        borderStyle: cs.borderTopStyle,
        borderWidth: cs.borderTopWidth,
        height: cs.height,
        paddingTop: cs.paddingTop,
        paddingRight: cs.paddingRight,
        paddingBottom: cs.paddingBottom,
        paddingLeft: cs.paddingLeft,
        fontSize: cs.fontSize,
        fontWeight: cs.fontWeight,
        color: cs.color
      };
    }""")


def assert_pdp_native_identity(page, context):
    counts = page.evaluate("""() => ({
      forms: document.querySelectorAll('form.cart').length,
      variationIds: document.querySelectorAll('input.variation_id').length,
      qty: document.querySelectorAll('input.qty').length,
      submits: document.querySelectorAll('.single_add_to_cart_button').length,
      selects: document.querySelectorAll('select[name^="attribute_"]').length
    })""")
    require(counts == {'forms': 1, 'variationIds': 1, 'qty': 1, 'submits': 1, 'selects': 2}, f'{context}: native Woo control counts changed: {counts}')
    require(page.evaluate("""() => {
      const n = window.__pdpNodes;
      const form = document.querySelector('form.cart');
      const selects = Array.from(document.querySelectorAll('select[name^="attribute_"]'));
      return !!n && n.form === form && n.dock === document.querySelector('[data-gloskin-purchase-dock]') &&
        n.variationId === document.querySelector('input.variation_id') && n.qty === document.querySelector('input.qty') &&
        n.submit === document.querySelector('.single_add_to_cart_button') && n.selects.length === selects.length &&
        n.selects.every((node, index) => node === selects[index]);
    }"""), f'{context}: native Woo node identity changed')


with sync_playwright() as p:
    chromium_path = Path('/usr/bin/chromium')
    if not chromium_path.exists():
        print("quick-add-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)

    browser = p.chromium.launch(headless=True, executable_path=str(chromium_path), args=['--no-sandbox'])

    # ------------------------------------------------------------------
    # Catalog Quick Add coverage (existing behavior retained).
    # ------------------------------------------------------------------
    page = browser.new_page(viewport={"width": 1280, "height": 900})
    page.set_content(HTML)
    add_styles(page)
    page.add_script_tag(content=RUNTIME)
    page.add_script_tag(content=JS_CORE_SOURCE)
    open_quickadd(page)

    require(page.locator('[data-gloskin-quickadd-body] select[name^="attribute_"]').count() == 2, 'native Woo select count must remain unchanged')
    require(page.locator('[data-gloskin-quickadd-body] input.variation_id').count() == 1, 'exactly one native variation_id must remain')
    require(page.locator('[data-gloskin-quickadd-body] input.qty').count() == 1, 'exactly one native qty input must remain')
    require(page.locator('[data-gloskin-quickadd-body] .single_add_to_cart_button').count() == 1, 'exactly one native Woo submit must remain')
    require(all(not page.locator('[data-gloskin-quickadd-body] select[name^="attribute_"]').nth(i).is_visible() for i in range(2)), 'enhanced native selects must have ZERO visual chrome')
    catalog_form = page.locator('[data-gloskin-quickadd-body] form.variations_form')
    require('gloskin-ui1-variable-catalog-enhanced' in (catalog_form.get_attribute('class') or ''), 'catalog form must mark only complete enhancement')
    require(page.locator('[data-gloskin-quickadd-body] .reset_variations').count() == 1, 'native Woo Clear must remain in enhanced catalog source')
    require(not page.locator('[data-gloskin-quickadd-body] .reset_variations').is_visible(), 'native Woo Clear must have ZERO visible leak after complete catalog enhancement')

    groups = page.locator('[data-gloskin-variable-select-key]')
    require(groups.count() == 2, 'multiple Woo attributes must create one chip group each')
    require(page.get_by_role('button', name='30 ml').count() == 1 and page.get_by_role('button', name='Natural').count() == 1, 'chips must be generated from actual option text')
    require(groups.nth(0).get_attribute('role') == 'group' and groups.nth(0).get_attribute('aria-labelledby'), 'chip group ARIA semantics must be present')
    catalog_labels = page.locator('.gloskin-ui1-variable-chip').all_inner_texts()
    catalog_unselected_style = chip_style(page.get_by_role('button', name='30 ml'))
    require(catalog_unselected_style['borderRadius'] == '999px', 'catalog chip must compute to pill radius')

    proxy = page.locator('[data-gloskin-variable-submit-proxy]')
    native_submit = page.locator('.single_add_to_cart_button')
    require(proxy.is_enabled(), 'visible CTA proxy must be active before selection')
    require(native_submit.get_attribute('disabled') is not None, 'native Woo submit must remain disabled before valid variation')

    proxy.click()
    require(page.evaluate('window.__mutationCount') == 0, 'incomplete proxy click must dispatch ZERO mutation')
    require(page.locator('.gloskin-ui1-toast-region').count() == 1, 'exactly one toast region must exist')
    require(page.locator('.gloskin-ui1-toast-region').inner_text() == 'Pilih varian terlebih dahulu.', 'incomplete selection toast copy mismatch')
    require(page.locator('.gloskin-ui1-toast-region').get_attribute('aria-live') == 'polite', 'toast must be aria-live polite')
    require(page.evaluate('window.__audioPlayCount') == 1, 'direct incomplete click must attempt one attention tone')
    require(page.evaluate('document.activeElement.hasAttribute("data-gloskin-variable-select-key")'), 'first unresolved chip group must receive focus')
    proxy.click()
    require(page.evaluate('window.__audioPlayCount') == 1, 'attention tone cooldown must prevent click spam')

    page.get_by_role('button', name='30 ml').click()
    require(page.locator('[name="attribute_pa_size"]').input_value() == '30ml', 'size chip must update SAME native Woo select')
    page.get_by_role('button', name='Natural').click()
    page.wait_for_function("document.querySelector('input.variation_id').value === '205'")
    require(page.locator('[name="attribute_pa_finish"]').input_value() == 'natural', 'finish chip must update SAME native Woo select')
    require(page.get_by_role('button', name='30 ml').get_attribute('aria-pressed') == 'true', 'selected chip must synchronize from native select')
    require(native_submit.get_attribute('disabled') is None, 'Woo runtime must enable native submit after valid selection')
    require('Rp250.000' in page.locator('.woocommerce-variation.single_variation').inner_text(), 'Woo variation price must remain visible')
    catalog_selected_style = chip_style(page.get_by_role('button', name='30 ml'))
    catalog_cta_bg = proxy.evaluate("node => getComputedStyle(node).backgroundColor")
    require(catalog_selected_style['backgroundColor'] != catalog_cta_bg, 'catalog selected chip must NOT become solid accent/red CTA')

    proxy.click()
    page.wait_for_function('window.__mutationCount === 1')
    require(page.evaluate('window.__nativeSubmitClicks') == 1, 'valid proxy must click SAME native Woo submit once')
    require(page.evaluate('window.__mutationCount') == 1, 'valid proxy must produce exactly one cart mutation')

    open_quickadd(page)
    page.get_by_role('button', name='50 ml').click()
    rose = page.get_by_role('button', name='Rose')
    require(rose.is_disabled(), 'Woo-disabled option must synchronize to disabled chip')
    page.get_by_role('button', name='Natural').click()
    page.wait_for_function("document.querySelector('input.variation_id').value === '207'")
    quantity = page.locator('.gloskin-ui1-quickadd__qty-control')
    require(not quantity.is_visible(), 'sold-individually must hide the SAME quantity control')
    require(page.locator('input.qty').count() == 1, 'sold-individually must never remove/clone native qty')
    require('is-quantity-hidden' in page.locator('.woocommerce-variation-add-to-cart').get_attribute('class'), 'sold-individually row must switch to full CTA geometry')

    page.evaluate("""() => {
      const select = document.querySelector('[name="attribute_pa_size"]');
      select.value = '';
      select.dispatchEvent(new Event('change', {bubbles:true}));
    }""")
    require(page.get_by_role('button', name='50 ml').get_attribute('aria-pressed') == 'false', 'Woo reset must clear selected chip')
    require(native_submit.get_attribute('disabled') is not None, 'Woo reset must disable native submit again')

    wrapper_style = page.evaluate("""() => {
      const cs = getComputedStyle(document.querySelector('.table-container'));
      return {bg: cs.backgroundColor, border: cs.borderTopWidth, shadow: cs.boxShadow};
    }""")
    require(wrapper_style['bg'] in ('rgba(0, 0, 0, 0)', 'transparent'), 'table-container must remain transparent')
    require(wrapper_style['border'] == '0px' and wrapper_style['shadow'] in ('none', ''), 'table-container must have no card border/shadow')

    for width in (390, 375, 320):
        page.set_viewport_size({"width": width, "height": 844})
        page.wait_for_timeout(30)
        require(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), f'variable modal must not overflow at {width}px')
        box = proxy.bounding_box()
        require(box and box['x'] >= -1 and box['x'] + box['width'] <= width + 1 and box['height'] >= 44, f'CTA must remain touch-safe and inside {width}px viewport')
    page.close()

    # ------------------------------------------------------------------
    # PDP coverage: one native Woo form, presentation-only modal, no fetch.
    # ------------------------------------------------------------------
    pdp = browser.new_page(viewport={"width": 1280, "height": 900})
    pdp.set_content(PDP_HTML)
    add_styles(pdp)
    pdp.add_script_tag(content=RUNTIME)
    pdp.evaluate("""() => {
      const form = document.querySelector('[data-gloskin-purchase-dock] form.variations_form');
      window.__wireVariationForm(form);
      window.__pdpNodes = {
        dock: document.querySelector('[data-gloskin-purchase-dock]'),
        form: form,
        selects: Array.from(form.querySelectorAll('select[name^="attribute_"]')),
        variationId: form.querySelector('input.variation_id'),
        qty: form.querySelector('input.qty'),
        submit: form.querySelector('.single_add_to_cart_button')
      };
    }""")
    assert_pdp_native_identity(pdp, 'before Gloskin PDP enhancement')
    pdp.add_script_tag(content=JS_CORE_SOURCE)
    pdp.add_script_tag(content=PURCHASE_DOCK_JS_SOURCE)
    pdp.wait_for_selector('[data-gloskin-purchase-dock][data-gloskin-purchase-composed="true"]')
    pdp.wait_for_selector('[data-gloskin-variable-pdp-trigger]')
    assert_pdp_native_identity(pdp, 'after Purchase Dock ready handoff')
    require(pdp.locator('[data-gloskin-variable-pdp-trigger]').inner_text() == 'Pilih Varian', 'PDP handoff must render one Pilih Varian trigger')
    require(pdp.locator('[data-gloskin-variable-pdp-trigger]').count() == 1, 'PDP must have exactly one variation trigger')
    require(pdp.evaluate("window.__pdpNodes.dock.parentElement.classList.contains('gloskin-ui1-purchase-dock-home')"), 'Purchase Dock FSM must keep the SAME dock in its canonical home')
    require(pdp.locator('.reset_variations').count() == 1 and not pdp.locator('.reset_variations').is_visible(), 'PDP must expose no stray visible native Clear after enhancement')

    fetch_before_open = pdp.evaluate('window.__fetchCalls.length')
    pdp.locator('[data-gloskin-variable-pdp-trigger]').click()
    pdp.wait_for_selector('[data-gloskin-overlay="quickadd"][aria-hidden="false"]')
    pdp.wait_for_selector('[data-gloskin-variable-submit-proxy]')
    require(pdp.evaluate('window.__fetchCalls.length') == fetch_before_open == 0, 'PDP modal open must use NO fetch')
    assert_pdp_native_identity(pdp, 'during initial PDP modal')
    require(pdp.locator('[data-gloskin-quickadd-body] form').count() == 0, 'PDP modal must contain ZERO second form')
    require(pdp.locator('[data-gloskin-quickadd-body] input.variation_id').count() == 0, 'PDP modal must contain ZERO second variation_id')
    require(pdp.locator('[data-gloskin-quickadd-body] input.qty').count() == 0, 'PDP modal must contain ZERO second native qty')
    require(pdp.locator('[data-gloskin-quickadd-body] .single_add_to_cart_button').count() == 0, 'PDP modal must contain ZERO second native submit')
    require(pdp.locator('[data-gloskin-variable-select-key]').count() == 2, 'PDP modal must render both native Woo attributes as chip groups')
    require(pdp.locator('[data-gloskin-quickadd-body] .reset_variations').count() == 0 and 'Clear' not in pdp.locator('[data-gloskin-quickadd-body]').inner_text(), 'PDP presentation modal must contain no native Clear leak')
    pdp_labels = pdp.locator('.gloskin-ui1-variable-chip').all_inner_texts()
    require(pdp_labels == catalog_labels, f'catalog/PDP chip labels must match native Woo options: {catalog_labels} != {pdp_labels}')
    pdp_unselected_style = chip_style(pdp.get_by_role('button', name='30 ml'))
    require(pdp_unselected_style == catalog_unselected_style, f'catalog/PDP unselected chip styles drifted: {catalog_unselected_style} != {pdp_unselected_style}')

    pdp_proxy = pdp.locator('[data-gloskin-variable-submit-proxy]')
    mutation_before = pdp.evaluate('window.__mutationCount')
    audio_before = pdp.evaluate('window.__audioPlayCount')
    pdp_proxy.click()
    require(pdp.evaluate('window.__mutationCount') == mutation_before, 'PDP incomplete CTA must dispatch ZERO mutation')
    require(pdp.locator('.gloskin-ui1-toast-region').inner_text() == 'Pilih varian terlebih dahulu.', 'PDP incomplete CTA must show required-selection toast')
    require(pdp.evaluate('window.__audioPlayCount') == audio_before + 1, 'PDP incomplete CTA must attempt attention tone')
    require(pdp.evaluate('document.activeElement.hasAttribute("data-gloskin-variable-select-key")'), 'PDP incomplete CTA must focus unresolved chip group, not Purchase Dock spotlight')

    pdp.get_by_role('button', name='30 ml').click()
    pdp.get_by_role('button', name='Natural').click()
    pdp.wait_for_function("document.querySelector('input.variation_id').value === '205'")
    require(pdp.evaluate("window.__pdpNodes.selects[0].value === '30ml' && window.__pdpNodes.selects[1].value === 'natural'"), 'PDP chips must update the SAME native selects')
    pdp_selected_style = chip_style(pdp.get_by_role('button', name='30 ml'))
    require(pdp_selected_style == catalog_selected_style, f'catalog/PDP selected chip styles drifted: {catalog_selected_style} != {pdp_selected_style}')
    assert_pdp_native_identity(pdp, 'after PDP chip selection')

    pdp.locator('.gloskin-ui1-quickadd__close').click()
    pdp.wait_for_function("document.querySelector('[data-gloskin-overlay=\"quickadd\"]').getAttribute('aria-hidden') === 'true'")
    require(pdp.evaluate("window.__pdpNodes.selects[0].value === '30ml' && window.__pdpNodes.selects[1].value === 'natural'"), 'PDP selection must survive modal close')
    pdp.locator('[data-gloskin-variable-pdp-trigger]').click()
    pdp.wait_for_selector('[data-gloskin-overlay="quickadd"][aria-hidden="false"]')
    require(pdp.get_by_role('button', name='30 ml').get_attribute('aria-pressed') == 'true', 'PDP selected size must survive close/reopen')
    require(pdp.get_by_role('button', name='Natural').get_attribute('aria-pressed') == 'true', 'PDP selected finish must survive close/reopen')
    require(pdp.evaluate('window.__fetchCalls.length') == 0, 'PDP reopen must still use NO fetch')

    # Sold individually: Woo hides only native quantity; presentation proxy follows it.
    pdp.get_by_role('button', name='50 ml').click()
    pdp.wait_for_function("document.querySelector('input.variation_id').value === '207'")
    actions = pdp.locator('[data-gloskin-variable-actions]')
    qty_proxy = pdp.locator('.gloskin-ui1-variable-modal__qty-proxy')
    pdp_proxy = pdp.locator('[data-gloskin-variable-submit-proxy]')
    pdp.wait_for_function("document.querySelector('[data-gloskin-variable-actions]').classList.contains('is-quantity-hidden')")
    require(not qty_proxy.is_visible(), 'PDP sold-individually must explicitly hide presentation qty proxy')
    require(pdp.evaluate("window.__pdpNodes.qty === document.querySelector('input.qty') && window.__pdpNodes.qty.closest('.quantity').style.display === 'none'"), 'PDP sold-individually must preserve SAME native qty while Woo hides its wrapper')
    action_box = actions.bounding_box()
    cta_box = pdp_proxy.bounding_box()
    require(action_box and cta_box and abs(action_box['width'] - cta_box['width']) <= 2, 'PDP sold-individually CTA must span full actions row')
    assert_pdp_native_identity(pdp, 'during sold-individually PDP modal')

    pdp.get_by_role('button', name='30 ml').click()
    pdp.wait_for_function("document.querySelector('input.variation_id').value === '205'")
    require(qty_proxy.is_visible(), 'switching from sold-individually must restore presentation qty proxy')
    require('is-quantity-hidden' not in (actions.get_attribute('class') or ''), 'switching back must restore two-column actions state')
    require(pdp.evaluate("window.__pdpNodes.qty.closest('.quantity').style.display !== 'none'"), 'Woo native quantity must be restored by Woo fixture')

    # Invalid Buy Now: zero mutation and no modal auto-open; guide the user to the existing trigger.
    pdp.locator('.gloskin-ui1-quickadd__close').click()
    pdp.wait_for_function("document.querySelector('[data-gloskin-overlay=\"quickadd\"]').getAttribute('aria-hidden') === 'true'")
    pdp.evaluate("""() => {
      window.__pdpNodes.selects[0].value = '';
      window.__pdpNodes.selects[0].dispatchEvent(new Event('change', {bubbles:true}));
    }""")
    pdp.wait_for_function("window.__pdpNodes.submit.disabled === true")
    pdp.wait_for_timeout(650)
    fetch_before_buy_now = pdp.evaluate('window.__fetchCalls.length')
    mutation_before_buy_now = pdp.evaluate('window.__mutationCount')
    native_before_buy_now = pdp.evaluate('window.__nativeSubmitClicks')
    audio_before_buy_now = pdp.evaluate('window.__audioPlayCount')
    confirm_before_buy_now = pdp.evaluate('window.__confirmCount')
    alert_before_buy_now = pdp.evaluate('window.__alertCount')
    pdp.locator('[data-gloskin-buy-now]').click()
    require(pdp.locator('[data-gloskin-overlay="quickadd"]').get_attribute('aria-hidden') == 'true', 'invalid Buy Now must NOT auto-open variable modal')
    require(pdp.evaluate('window.__fetchCalls.length') == fetch_before_buy_now, 'invalid Buy Now must dispatch ZERO fetch')
    require(pdp.evaluate('window.__mutationCount') == mutation_before_buy_now, 'invalid Buy Now must dispatch ZERO mutation')
    require(pdp.evaluate('window.__nativeSubmitClicks') == native_before_buy_now, 'invalid Buy Now must not click native Woo submit')
    require(pdp.evaluate('window.__confirmCount') == confirm_before_buy_now and pdp.evaluate('window.__alertCount') == alert_before_buy_now, 'invalid Buy Now must use ZERO native confirm/alert')
    require(pdp.locator('.gloskin-ui1-toast-region').inner_text() == 'Pilih varian terlebih dahulu.', 'invalid Buy Now must use same required-selection toast')
    require(pdp.evaluate('window.__audioPlayCount') == audio_before_buy_now + 1, 'invalid Buy Now must use same attention tone')
    require(pdp.locator('.gloskin-ui1-action-spotlight__backdrop').count() == 1, 'invalid Buy Now must create exactly one spotlight backdrop')
    require(pdp.locator('.gloskin-ui1-action-spotlight__backdrop').get_attribute('aria-hidden') == 'true', 'spotlight backdrop must be aria-hidden')
    require('is-action-spotlight-target' in (pdp.locator('[data-gloskin-variable-pdp-trigger]').get_attribute('class') or ''), 'Pilih Varian trigger must receive spotlight target state')
    require(pdp.evaluate("document.activeElement === document.querySelector('[data-gloskin-variable-pdp-trigger]')"), 'Pilih Varian trigger must receive keyboard focus')
    require(pdp.locator('[data-gloskin-variable-pdp-trigger]').is_enabled(), 'Pilih Varian trigger must remain visibly/actionably enabled')
    z_order = pdp.evaluate("""() => ({
      dock: parseInt(getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]')).zIndex || '0', 10),
      backdrop: parseInt(getComputedStyle(document.querySelector('.gloskin-ui1-action-spotlight__backdrop')).zIndex || '0', 10)
    })""")
    require(z_order['dock'] > z_order['backdrop'], f'Purchase Dock/trigger must stay above spotlight backdrop: {z_order}')

    # Only the user's normal trigger click opens the SAME reusable PDP modal.
    pdp.locator('[data-gloskin-variable-pdp-trigger]').click()
    pdp.wait_for_selector('[data-gloskin-overlay="quickadd"][aria-hidden="false"]')
    require(pdp.locator('.gloskin-ui1-action-spotlight__backdrop').count() == 0, 'trigger click must dismiss spotlight before modal open')
    require(pdp.evaluate('window.__fetchCalls.length') == fetch_before_buy_now, 'highlighted trigger must open SAME PDP modal without fetch')
    require(pdp.locator('[data-gloskin-quickadd-body] form').count() == 0, 'Buy Now prerequisite modal path must still have ZERO second form')
    assert_pdp_native_identity(pdp, 'after user-opened Buy Now prerequisite modal')

    pdp.get_by_role('button', name='30 ml').click()
    pdp.get_by_role('button', name='Natural').click()
    pdp.wait_for_function("document.querySelector('input.variation_id').value === '205'")
    require(pdp.locator('.gloskin-ui1-action-spotlight__backdrop').count() == 0, 'valid variation must leave spotlight absent')
    require(chip_style(pdp.get_by_role('button', name='30 ml')) == catalog_selected_style, 'PDP selected chip must remain visually identical after Buy Now prerequisite flow')

    # Existing valid modal CTA still triggers the SAME native submit exactly once.
    native_clicks_before = pdp.evaluate('window.__nativeSubmitClicks')
    mutations_before = pdp.evaluate('window.__mutationCount')
    pdp.locator('[data-gloskin-variable-submit-proxy]').click()
    pdp.wait_for_function(f'window.__mutationCount === {mutations_before + 1}')
    require(pdp.evaluate('window.__nativeSubmitClicks') == native_clicks_before + 1, 'valid PDP proxy must click SAME native submit exactly once')
    require(pdp.evaluate('window.__mutationCount') == mutations_before + 1, 'valid PDP proxy must produce exactly one Woo mutation')
    assert_pdp_native_identity(pdp, 'after valid PDP submit')

    # Valid Buy Now remains unchanged: same native submit -> same one mutation -> redirect behavior.
    pdp.locator('.gloskin-ui1-quickadd__close').click()
    pdp.wait_for_function("document.querySelector('[data-gloskin-overlay=\"quickadd\"]').getAttribute('aria-hidden') === 'true'")
    pdp.evaluate("window.gloskinData.cartUrl = '#cart'")
    native_clicks_before_buy_now = pdp.evaluate('window.__nativeSubmitClicks')
    mutations_before_valid_buy_now = pdp.evaluate('window.__mutationCount')
    pdp.locator('[data-gloskin-buy-now]').click()
    pdp.wait_for_function(f'window.__mutationCount === {mutations_before_valid_buy_now + 1}')
    require(pdp.evaluate('window.__nativeSubmitClicks') == native_clicks_before_buy_now + 1, 'valid Buy Now must click SAME native submit exactly once')
    require(pdp.evaluate('window.__mutationCount') == mutations_before_valid_buy_now + 1, 'valid Buy Now must produce exactly one existing Woo mutation')
    pdp.wait_for_function("window.location.hash === '#cart'")
    require(pdp.locator('.gloskin-ui1-action-spotlight__backdrop').count() == 0, 'valid Buy Now must not activate prerequisite spotlight')
    assert_pdp_native_identity(pdp, 'after valid Buy Now')
    require(pdp.evaluate("window.__pdpNodes.dock.parentElement.classList.contains('gloskin-ui1-purchase-dock-home')"), 'Purchase Dock FSM/node placement must remain intact after modal/Buy Now flow')
    pdp.close()

    # ------------------------------------------------------------------
    # Transactional fail-open: an unenhanceable PDP never hides native Woo.
    # ------------------------------------------------------------------
    broken = browser.new_page(viewport={"width": 1280, "height": 900})
    broken.set_content(BROKEN_PDP_HTML)
    add_styles(broken)
    broken.add_script_tag(content=RUNTIME)
    broken.add_script_tag(content=JS_CORE_SOURCE)
    broken.add_script_tag(content=PURCHASE_DOCK_JS_SOURCE)
    broken.wait_for_selector('[data-gloskin-purchase-dock][data-gloskin-purchase-composed="true"]')
    require(broken.locator('form.cart').count() == 1, 'fail-open PDP must retain exactly one native form.cart')
    require(broken.locator('[data-gloskin-variable-pdp-trigger]').count() == 0, 'fail-open PDP must not leave a broken Pilih Varian trigger')
    require('gloskin-ui1-variable-pdp-enhanced' not in (broken.locator('form.cart').get_attribute('class') or ''), 'fail-open PDP must not hide native variation UI')
    require(all(broken.locator('select[name^="attribute_"]').nth(i).is_visible() for i in range(2)), 'fail-open PDP native selects must remain visible')
    require(all('gloskin-ui1-variable-select--enhanced' not in (broken.locator('select[name^="attribute_"]').nth(i).get_attribute('class') or '') for i in range(2)), 'fail-open PDP must not partially hide any native select')
    broken.close()

    browser.close()

print("quick-add-browser-smoke: OK")
