#!/usr/bin/env python3
"""Focused browser fixture for product-card + variable Quick Add interaction."""
from pathlib import Path
import sys

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("quick-add-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
CSS_BASE = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CSS_CORE = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css").read_text(encoding="utf-8")
JS_CORE = (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js").read_text(encoding="utf-8")

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

RUNTIME = r"""
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
          form_html: '<form class="variations_form cart" action="/product/hydrating-serum/" method="post"><table class="variations"><tbody><tr><th><label for="pa_size">Ukuran</label></th><td><select id="pa_size" name="attribute_pa_size"><option value="">Pilih</option><option value="30ml">30 ml</option></select></td></tr></tbody></table><div class="single_variation_wrap"><div class="single_variation"><div class="woocommerce-variation-price">Rp250.000</div><div class="woocommerce-variation-availability">Tersedia</div></div><div class="woocommerce-variation-add-to-cart variations_button"><div class="quantity"><input class="input-text qty text" type="number" name="quantity" value="1" min="1"></div><button type="submit" class="single_add_to_cart_button button alt" name="add-to-cart" value="202">Tambah ke keranjang</button><input type="hidden" name="product_id" value="202"><input type="hidden" class="variation_id" name="variation_id" value="205"></div></div></form>'
        });
      }
    });
  }
  return Promise.reject(new Error('fixture does not perform live Woo mutations'));
};
"""


def require(condition, message):
    if not condition:
        raise AssertionError(message)


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

    # Mobile: bottom-sheet geometry, selectors and CTA remain inside viewport.
    page.set_viewport_size({"width": 390, "height": 844})
    page.wait_for_timeout(40)
    require(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), 'mobile page must not horizontally overflow')
    select_box = page.locator('.gloskin-ui1-quickadd__form select').bounding_box()
    button_box = page.locator('.gloskin-ui1-quickadd__form .single_add_to_cart_button').bounding_box()
    panel_box = page.locator('.gloskin-ui1-quickadd__panel').bounding_box()
    require(select_box and select_box['x'] >= -1 and select_box['x'] + select_box['width'] <= 391, 'mobile variation select must fit viewport')
    require(button_box and button_box['x'] >= -1 and button_box['x'] + button_box['width'] <= 391 and button_box['height'] >= 44, 'mobile Add to Cart must fit viewport and remain touch-safe')
    require(panel_box and panel_box['y'] + panel_box['height'] <= 845, 'mobile bottom sheet must fit viewport/safe geometry')
    require(page.locator('.gloskin-ui1-quickadd__close').is_visible(), 'mobile close button must remain reachable')

    # Mobile product card CTA remains contained.
    page.keyboard.press('Escape')
    mobile_card = page.locator('[data-test-product-card]').bounding_box()
    mobile_action = page.locator('[data-test-product-card] .gloskin-ui1-card__actions a').bounding_box()
    require(mobile_card and mobile_action and mobile_action['x'] + mobile_action['width'] <= mobile_card['x'] + mobile_card['width'] + 1, 'mobile product-card CTA must not overflow')

    browser.close()

print('quick-add-browser-smoke: OK')
