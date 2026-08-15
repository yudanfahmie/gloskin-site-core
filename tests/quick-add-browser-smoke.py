#!/usr/bin/env python3
"""Focused browser fixture for product-card + variable Quick Add interaction."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
JS_CORE_SOURCE = (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js").read_text(encoding="utf-8")
CSS_CORE_SOURCE = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css").read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


# -----------------------------------------------------------------------
# Static/source guards -- always run, no browser required, so this file
# still proves something even when Chromium/Playwright is unavailable.
# -----------------------------------------------------------------------
# initQuickAdd() and the next SINGLE-TAB-indented (top-level) function
# after it bound the whole Quick Add controller, regardless of how many
# nested helper functions live inside it.
quickadd_js_start = JS_CORE_SOURCE.index('\tfunction initQuickAdd() {')
quickadd_js_end = JS_CORE_SOURCE.index('\n\tfunction ', quickadd_js_start + 1)
quickadd_js = JS_CORE_SOURCE[quickadd_js_start:quickadd_js_end]

require('function enhanceQuantityControls(quantity)' in quickadd_js, 'Quick Add quantity enhancer must exist')
require('function stepQuantityInput(input, direction)' in quickadd_js, 'Quick Add quantity stepper must exist')
require('input.stepUp()' in quickadd_js and 'input.stepDown()' in quickadd_js, 'stepper must prefer native stepUp()/stepDown()')
require("new Event('input', { bubbles: true }" in quickadd_js and "new Event('change', { bubbles: true }" in quickadd_js, 'stepper must dispatch bubbling input+change events')
require("document.createElement('input')" not in quickadd_js, 'ZERO second quantity input: Quick Add must never create a new <input>, only decorate the existing input.qty')
require(quickadd_js.count("querySelector('input.qty')") <= 2, 'quantity resolution must stay a simple querySelector lookup, not a second quantity state')
require('wc_variation_form()' in quickadd_js, 'native Woo variation runtime call must remain')
require('ajaxAddToCart(form, submitter' in quickadd_js, 'existing AJAX add-to-cart bridge call must remain untouched')
require('nativeFallbackSubmit(form, submitter)' in quickadd_js, 'existing native fallback submit call must remain untouched')
require('single_add_to_cart_button.disabled =' not in quickadd_js and 'single_add_to_cart_button"].disabled =' not in quickadd_js, 'ZERO custom disabled-state toggling: Add to Cart disabled state must stay Woo-driven, not JS-forced')
require('register_rest_route' not in quickadd_js and 'wp_ajax_' not in quickadd_js, 'ZERO Quick Add custom cart endpoint in the frontend controller (REST projection route lives server-side and is read-only)')

quickadd_css_start = CSS_CORE_SOURCE.index('/* Quick Add release hardening.')
quickadd_css_end = CSS_CORE_SOURCE.index('Shop catalog enhancement:', quickadd_css_start)
quickadd_css = CSS_CORE_SOURCE[quickadd_css_start:quickadd_css_end]
require('!important' not in quickadd_css, 'NEW !IMPORTANT: Quick Add CSS must add zero !important declarations')
require('.gloskin-ui1-quickadd__qty-control' in quickadd_css, 'Quick-Add-scoped quantity control class must exist')
require('.gloskin-ui1-quickadd__qty-minus' in quickadd_css and '.gloskin-ui1-quickadd__qty-plus' in quickadd_css, 'Quick-Add-scoped minus/plus classes must exist')
for benchmark in ('min-height:46px', 'width:44px', 'width:34px'):
    require(benchmark in quickadd_css, f'quantity stepper must match the Purchase Dock geometry benchmark: {benchmark}')
require(quickadd_css.count('border-right:1px solid var(--gloskin-border)') >= 1 and quickadd_css.count('border-left:1px solid var(--gloskin-border)') >= 1, 'stepper must keep exactly one separator each side of the numeric value')
require('display:contents' in quickadd_css, 'desktop composition must use the sanctioned display:contents technique on non-semantic Woo wrapper divs')
print('quick-add-source-contract: OK (static, no browser required)')

# -----------------------------------------------------------------------
# Browser fixture -- honestly skips if Chromium/Playwright is unavailable.
# -----------------------------------------------------------------------
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("quick-add-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

CSS_BASE = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CSS_CORE = CSS_CORE_SOURCE
JS_CORE = JS_CORE_SOURCE

HTML = r"""
<!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="gloskin-ui1">
<main style="max-width:980px;margin:40px auto;padding:0 20px">
  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px">
    <article class="gloskin-ui1-card gloskin-ui1-card--product" data-test-product-card>
      <div class="gloskin-ui1-card__media-wrap">
        <a class="gloskin-ui1-card__media" href="/product/hydrating-serum/" tabindex="-1" aria-hidden="true"><div style="aspect-ratio:1;background:#eee"></div></a>
        <button type="button" class="gloskin-ui1-wishlist-toggle" aria-label="Simpan ke favorit"></button>
      </div>
      <div class="gloskin-ui1-card__body">
        <h3 class="gloskin-ui1-card__title"><a href="/product/hydrating-serum/">Hydrating Serum</a></h3>
        <div class="gloskin-ui1-product-price">Rp250.000</div>
        <p class="gloskin-ui1-card__copy">Serum hidrasi dengan pilihan ukuran.</p>
        <div class="gloskin-ui1-card__actions">
          <a href="/product/hydrating-serum/" data-quantity="1" class="gloskin-ui1-button gloskin-ui1-button--small button add_to_cart_button product_type_variable gloskin-ui1-quickadd-trigger" data-product_id="202" data-product_sku="GLS-002" data-gloskin-quickadd-open data-gloskin-quickadd-product="202" aria-haspopup="dialog" rel="nofollow">Pilih Varian</a>
        </div>
      </div>
    </article>
  </div>
</main>
<div class="gloskin-ui1-quickadd" data-gloskin-overlay="quickadd" aria-hidden="true" hidden>
  <button class="gloskin-ui1-quickadd__backdrop" type="button" data-gloskin-overlay-close aria-label="Tutup pilihan produk"></button>
  <div class="gloskin-ui1-quickadd__panel" role="dialog" aria-modal="true" aria-labelledby="quickadd-title">
    <div class="gloskin-ui1-quickadd__head">
      <strong id="quickadd-title">Pilih varian</strong>
      <button class="gloskin-ui1-quickadd__close" type="button" data-gloskin-overlay-close aria-label="Tutup">×</button>
    </div>
    <div class="gloskin-ui1-quickadd__body" data-gloskin-quickadd-body aria-live="polite"></div>
  </div>
</div>
</body>
</html>
"""

# form_html mirrors WooCommerce's real woocommerce_template_single_add_to_cart()
# output for a variable product: table.variations -> single_variation_wrap >
# (woocommerce-variation.single_variation price/availability state +
# woocommerce-variation-add-to-cart.variations_button > quantity + submit +
# hidden product_id/variation_id). input.qty carries min/max/step so the
# stepper's respect for all three is genuinely exercised.
FORM_HTML = (
    '<form class="variations_form cart" action="/product/hydrating-serum/" method="post">'
    '<table class="variations"><tbody><tr><th><label for="pa_size">Ukuran</label></th>'
    '<td><select id="pa_size" name="attribute_pa_size"><option value="">Pilih</option><option value="30ml">30 ml</option></select></td></tr></tbody></table>'
    '<div class="single_variation_wrap">'
    '<div class="woocommerce-variation single_variation">'
    '<div class="woocommerce-variation-price">Rp250.000</div>'
    '<div class="woocommerce-variation-availability">Tersedia</div>'
    '</div>'
    '<div class="woocommerce-variation-add-to-cart variations_button">'
    '<div class="quantity"><input class="input-text qty text" type="number" name="quantity" value="1" min="1" max="5" step="1"></div>'
    '<button type="submit" class="single_add_to_cart_button button alt" name="add-to-cart" value="202">Tambah ke keranjang</button>'
    '<input type="hidden" name="product_id" value="202">'
    '<input type="hidden" class="variation_id" name="variation_id" value="205">'
    '</div></div></form>'
)

RUNTIME = (
    r"""
window.gloskinData = {
  woo: true,
  restUrl: '/wp-json/gloskin/v1/',
  restNonce: 'fixture',
  addToCartAjaxUrl: '/?wc-ajax=add_to_cart'
};
window.wc_cart_fragments_params = {};
window.wc_add_to_cart_params = {};
(function () {
  const handlers = {};
  function jq(target) {
    return {
      length: target ? 1 : 0,
      on(name, handler) { handlers[name] = handler; return this; },
      trigger(name, args) { if (handlers[name]) { handlers[name].apply(target, [null].concat(args || [])); } return this; },
      attr(name, value) { if (target && target.setAttribute && value !== undefined) { target.setAttribute(name, value); } return this; },
      wc_variation_form() { return this; }
    };
  }
  jq.fn = { wc_variation_form: function () {} };
  window.jQuery = jq;
})();
window.fetch = function (url) {
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
          form_html: """
    + repr(FORM_HTML)
    + r"""
        });
      }
    });
  }
  return Promise.reject(new Error('fixture does not perform live Woo mutations'));
};
"""
)


def open_quickadd(page):
    trigger = page.locator('[data-gloskin-quickadd-open]')
    trigger.click()
    page.wait_for_selector('[data-gloskin-overlay="quickadd"][aria-hidden="false"]')
    page.wait_for_selector('[data-gloskin-quickadd-body] form.variations_form')
    return trigger


with sync_playwright() as p:
    chromium_path = Path('/usr/bin/chromium')
    if not chromium_path.exists():
        print("quick-add-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)
    browser = p.chromium.launch(headless=True, executable_path=str(chromium_path), args=['--no-sandbox'])
    page = browser.new_page(viewport={"width": 1280, "height": 900})
    page.set_content(HTML)
    page.add_style_tag(content=CSS_BASE + "\n" + CSS_CORE)
    page.add_script_tag(content=RUNTIME)
    page.add_script_tag(content=JS_CORE)

    # Desktop product card: one deliberate action, contained by its card.
    require(page.locator('[data-test-product-card] .gloskin-ui1-card__actions a').count() == 1, 'desktop product card must render exactly one footer CTA')
    card_box = page.locator('[data-test-product-card]').bounding_box()
    action_box = page.locator('[data-test-product-card] .gloskin-ui1-card__actions a').bounding_box()
    require(card_box and action_box and action_box['x'] >= card_box['x'] - 1 and action_box['x'] + action_box['width'] <= card_box['x'] + card_box['width'] + 1, 'desktop CTA must remain inside card width')

    # Open/focus: meaningful dialog close button wins, never the backdrop.
    trigger = open_quickadd(page)
    active_class = page.evaluate('document.activeElement.className')
    require('gloskin-ui1-quickadd__close' in active_class, 'initial Quick Add focus must land on close button')
    require(page.evaluate('document.activeElement.classList.contains("gloskin-ui1-quickadd__backdrop")') is False, 'backdrop must never receive initial focus')

    # Escape closes and returns focus to the exact trigger.
    page.keyboard.press('Escape')
    require(page.locator('[data-gloskin-overlay="quickadd"]').get_attribute('aria-hidden') == 'true', 'Escape must close Quick Add')
    require(page.evaluate('document.activeElement.hasAttribute("data-gloskin-quickadd-open")'), 'Escape must return focus to card CTA')

    # Close button works.
    open_quickadd(page)
    page.locator('.gloskin-ui1-quickadd__close').click()
    require(page.locator('[data-gloskin-overlay="quickadd"]').get_attribute('aria-hidden') == 'true', 'close button must close Quick Add')

    # Backdrop works.
    open_quickadd(page)
    page.locator('.gloskin-ui1-quickadd__backdrop').click(position={"x": 8, "y": 8}, force=True)
    require(page.locator('[data-gloskin-overlay="quickadd"]').get_attribute('aria-hidden') == 'true', 'backdrop must close Quick Add')

    # Rapid close/reopen must cancel stale hidden-state finalization.
    open_quickadd(page)
    page.locator('.gloskin-ui1-quickadd__close').click()
    trigger.click()
    page.wait_for_timeout(380)
    require(page.locator('[data-gloskin-overlay="quickadd"]').get_attribute('aria-hidden') == 'false', 'rapid reopen must remain aria-visible')
    require(page.locator('[data-gloskin-overlay="quickadd"]').get_attribute('hidden') is None, 'rapid reopen must not be clobbered to hidden')

    # ---------------------------------------------------------------
    # Quantity stepper: native input.qty, exactly one control, no clones.
    # ---------------------------------------------------------------
    require(page.locator('[data-gloskin-quickadd-body] input.qty').count() == 1, 'exactly ONE input.qty must exist')
    require(page.locator('[data-gloskin-quickadd-body] .gloskin-ui1-quickadd__qty-minus').count() == 1, 'exactly ONE minus button must exist')
    require(page.locator('[data-gloskin-quickadd-body] .gloskin-ui1-quickadd__qty-plus').count() == 1, 'exactly ONE plus button must exist')
    qty_input = page.locator('[data-gloskin-quickadd-body] input.qty')
    require(qty_input.get_attribute('name') == 'quantity' and qty_input.get_attribute('min') == '1', 'native qty input (same node/attributes) must be preserved, not replaced')

    events = page.evaluate(
        """() => {
            const input = document.querySelector('[data-gloskin-quickadd-body] input.qty');
            const seen = [];
            input.addEventListener('input', () => seen.push('input'));
            input.addEventListener('change', () => seen.push('change'));
            window.__gloskinQtyEvents = seen;
            return true;
        }"""
    )
    require(events, 'event listener installation must succeed')

    page.locator('.gloskin-ui1-quickadd__qty-plus').click()
    require(qty_input.input_value() == '2', 'plus must increment the native input by one step')
    page.locator('.gloskin-ui1-quickadd__qty-plus').click()
    require(qty_input.input_value() == '3', 'plus must keep incrementing')
    page.locator('.gloskin-ui1-quickadd__qty-minus').click()
    require(qty_input.input_value() == '2', 'minus must decrement the native input by one step')

    dispatched = page.evaluate('window.__gloskinQtyEvents')
    require(dispatched.count('input') >= 3 and dispatched.count('change') >= 3, 'each step must dispatch bubbling input+change events')

    # min respected: step down to the floor and past it.
    page.locator('.gloskin-ui1-quickadd__qty-minus').click()
    require(qty_input.input_value() == '1', 'minus must land on min')
    page.locator('.gloskin-ui1-quickadd__qty-minus').click()
    require(qty_input.input_value() == '1', 'minus must never go below min')

    # max respected: step up to the ceiling (max="5") and past it.
    for _ in range(6):
        page.locator('.gloskin-ui1-quickadd__qty-plus').click()
    require(qty_input.input_value() == '5', 'plus must never exceed max')

    # step respected: value always lands on a whole step from min.
    final_value = float(qty_input.input_value())
    require(final_value == int(final_value), 'stepping must respect the input step and never produce a fractional value')

    # Repeated modal render/open must not duplicate steppers or listeners.
    page.locator('.gloskin-ui1-quickadd__close').click()
    open_quickadd(page)
    require(page.locator('[data-gloskin-quickadd-body] input.qty').count() == 1, 'reopen must not duplicate input.qty')
    require(page.locator('[data-gloskin-quickadd-body] .gloskin-ui1-quickadd__qty-minus').count() == 1, 'reopen must not duplicate the minus button')
    require(page.locator('[data-gloskin-quickadd-body] .gloskin-ui1-quickadd__qty-plus').count() == 1, 'reopen must not duplicate the plus button')
    reopened_input = page.locator('[data-gloskin-quickadd-body] input.qty')
    require(reopened_input.input_value() == '1', 'reopen must reflect a fresh render (cache/fixture default), not stale duplicated state')
    page.locator('.gloskin-ui1-quickadd__qty-plus').click()
    require(reopened_input.input_value() == '2', 'the single delegated listener must still work correctly after re-render')

    # Variation select remains the real, native Woo <select> -- not
    # replaced -- and keeps its own visible Gloskin Form Kit field
    # styling (border + minimum control height), even though the
    # structural wrappers around it lost their card look.
    select_tag = page.evaluate('document.querySelector("[data-gloskin-quickadd-body] table.variations select").tagName')
    require(select_tag == 'SELECT', 'variation control must remain a native <select>')
    select_style = page.evaluate(
        """() => {
            const el = document.querySelector('[data-gloskin-quickadd-body] table.variations select');
            const cs = getComputedStyle(el);
            return { borderWidth: cs.borderTopWidth, minHeight: parseFloat(cs.minHeight) || el.getBoundingClientRect().height };
        }"""
    )
    require(select_style['borderWidth'] not in ('0px', '', None), 'select must retain a visible Gloskin field border')
    require(select_style['minHeight'] >= 40, 'select must retain a real Gloskin field control height')

    # Structural variation wrappers are transparent -- no second card
    # background/border inside the modal.
    wrapper_styles = page.evaluate(
        """() => {
            const selectors = ['.single_variation_wrap', '.woocommerce-variation', '.woocommerce-variation-add-to-cart', 'table.variations'];
            return selectors.map((sel) => {
                const el = document.querySelector('[data-gloskin-quickadd-body] ' + sel);
                if (!el) { return null; }
                const cs = getComputedStyle(el);
                return { sel, backgroundColor: cs.backgroundColor, borderWidth: cs.borderTopWidth, boxShadow: cs.boxShadow };
            });
        }"""
    )
    for entry in wrapper_styles:
        require(entry is not None, 'structural wrapper must exist for the transparency check')
        require(entry['backgroundColor'] in ('rgba(0, 0, 0, 0)', 'transparent'), f"{entry['sel']} must be transparent, got {entry['backgroundColor']}")
        require(entry['borderWidth'] == '0px', f"{entry['sel']} must have no border, got {entry['borderWidth']}")
        require(entry['boxShadow'] in ('none', ''), f"{entry['sel']} must have no box-shadow, got {entry['boxShadow']}")

    # Desktop: variant selector + quantity share one command row; the
    # final Add to Cart is the one full-width bottom row, no secondary
    # action beside it.
    table_box = page.locator('[data-gloskin-quickadd-body] table.variations').bounding_box()
    qty_control_box = page.locator('[data-gloskin-quickadd-body] .gloskin-ui1-quickadd__qty-control').bounding_box()
    submit_box = page.locator('[data-gloskin-quickadd-body] .single_add_to_cart_button').bounding_box()
    require(table_box and qty_control_box, 'selector and quantity control must both be visible on desktop')
    require(qty_control_box['x'] > table_box['x'] + table_box['width'] - 1, 'quantity must sit to the right of the variant selector on desktop')
    row_overlap = min(table_box['y'] + table_box['height'], qty_control_box['y'] + qty_control_box['height']) - max(table_box['y'], qty_control_box['y'])
    require(row_overlap > 0, 'variant selector and quantity must visually share one row on desktop')
    require(qty_control_box['width'] < table_box['width'], 'quantity control must stay content-width, not stretch like the selector')
    require(submit_box, 'final Add to Cart row must be visible')
    require(submit_box['width'] >= table_box['width'] + qty_control_box['width'] - 2, 'Add to Cart must occupy the full-width final row, spanning both the selector and quantity columns')
    require(submit_box['y'] > table_box['y'] + table_box['height'] - 1, 'Add to Cart must sit below the selector/quantity row')
    require(page.locator('[data-gloskin-quickadd-body] .gloskin-ui1-quickadd__form .gloskin-ui1-button, [data-gloskin-quickadd-body] .gloskin-ui1-quickadd__form button:not(.gloskin-ui1-quickadd__qty-minus):not(.gloskin-ui1-quickadd__qty-plus):not(.single_add_to_cart_button)').count() == 0, 'no secondary action must exist beside Add to Cart')

    # Mobile: bottom-sheet geometry, selectors and CTA remain inside viewport.
    page.set_viewport_size({"width": 390, "height": 844})
    page.wait_for_timeout(40)
    require(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), 'mobile page must not horizontally overflow')
    select_box = page.locator('.gloskin-ui1-quickadd__form select').bounding_box()
    mobile_qty_box = page.locator('.gloskin-ui1-quickadd__qty-control').bounding_box()
    button_box = page.locator('.gloskin-ui1-quickadd__form .single_add_to_cart_button').bounding_box()
    panel_box = page.locator('.gloskin-ui1-quickadd__panel').bounding_box()
    require(select_box and select_box['x'] >= -1 and select_box['x'] + select_box['width'] <= 391, 'mobile variation select must fit viewport')
    require(button_box and button_box['x'] >= -1 and button_box['x'] + button_box['width'] <= 391 and button_box['height'] >= 44, 'mobile Add to Cart must fit viewport and remain touch-safe')
    require(panel_box and panel_box['y'] + panel_box['height'] <= 845, 'mobile bottom sheet must fit viewport/safe geometry')
    require(page.locator('.gloskin-ui1-quickadd__close').is_visible(), 'mobile close button must remain reachable')
    require(mobile_qty_box and mobile_qty_box['width'] < 391 * 0.6, 'mobile quantity stepper must remain content-width, not a giant full-width number field')
    require(mobile_qty_box and select_box and mobile_qty_box['y'] > select_box['y'] + select_box['height'] - 1, 'mobile must stack the quantity stepper below the variant selector, not beside it')

    # Mobile product card CTA remains contained.
    page.keyboard.press('Escape')
    mobile_card = page.locator('[data-test-product-card]').bounding_box()
    mobile_action = page.locator('[data-test-product-card] .gloskin-ui1-card__actions a').bounding_box()
    require(mobile_card and mobile_action and mobile_action['x'] + mobile_action['width'] <= mobile_card['x'] + mobile_card['width'] + 1, 'mobile product-card CTA must not overflow')

    browser.close()

print('quick-add-browser-smoke: OK')
