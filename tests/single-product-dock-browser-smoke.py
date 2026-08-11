#!/usr/bin/env python3
"""Focused browser fixture for the single-product purchase dock and the
primary-product CSS/JS scope hardening (2026-08-12 regression-hardening
pass).

Mirrors the fixture-driven pattern already used by
tests/quick-add-browser-smoke.py: load the real production CSS/JS against a
synthetic page built to match WooCommerce's actual rendered markup (verified
live on staging -- see docs/audits/single-product-commerce-remediation-2026-08-11.md),
including a legitimate different-product [product_page] embed nested inside
the Description tab, in the exact DOM position WooCommerce's own shortcode
template renders it. No live WordPress/WooCommerce instance is required or
contacted.
"""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("single-product-dock-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
CSS_BASE = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CSS_CORE = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css").read_text(encoding="utf-8")
JS_CORE = (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js").read_text(encoding="utf-8")

# Primary product (#501) purchase dock, wrapped exactly the way
# WooCommerce_Adapter::open_purchase_dock()/close_purchase_dock() do around
# woocommerce_before/after_add_to_cart_form -- one native form.cart, never
# cloned. Nested inside it: WooCommerce's own Description tab, containing a
# legitimate *different*-product (#777) [product_page] embed in the exact
# ancestry live staging proved for the self-referencing case
# (.woocommerce-Tabs-panel--description > div.woocommerce > div.single-product
# > div.product), which must never inherit the primary PDP grid/gallery/
# tabs/dock treatment.
HTML = r"""
<!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="wp-singular product-template-default single single-product postid-501 gloskin-ui1 gloskin-ui1--medical woocommerce woocommerce-page">
<header style="height:80px;background:#fafafa" data-test-header>site header</header>
<main id="gloskin-main" class="gloskin-ui1-main">
<div class="woocommerce gloskin-ui1-commerce-native">
<div id="product-501" class="product type-product post-501 status-publish instock product_cat-facial-wash purchasable product-type-variable">
  <div class="woocommerce-product-gallery">
    <div class="woocommerce-product-gallery__wrapper"><div class="woocommerce-product-gallery__image"><img src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22600%22 height=%22600%22/%3E" alt=""></div></div>
  </div>
  <div class="summary entry-summary">
    <h1 class="product_title">Gloskin Fresh Gel Facial Wash</h1>
    <p class="price"><span class="amount">Rp119.000&ndash;Rp189.000</span></p>
    <div class="woocommerce-product-details__short-description"><p>Facial wash bertekstur gel segar.</p></div>
    <div class="gloskin-ui1-purchase-dock" data-gloskin-purchase-dock>
      <form class="variations_form cart" action="/product/fresh-gel-facial-wash/" method="post" data-product_id="501">
        <table class="variations"><tbody><tr><th><label for="pa_ukuran">Ukuran</label></th><td><select id="pa_ukuran" name="attribute_pa_ukuran"><option value="">Pilih</option><option value="30ml">30 ml</option></select></td></tr></tbody></table>
        <div class="single_variation_wrap">
          <div class="woocommerce-variation-add-to-cart variations_button">
            <div class="quantity"><input class="input-text qty text" type="number" name="quantity" value="1" min="1"></div>
            <button type="submit" class="single_add_to_cart_button button alt" name="add-to-cart" value="501">Tambah ke keranjang</button>
            <input type="hidden" name="product_id" value="501">
            <input type="hidden" class="variation_id" name="variation_id" value="0">
          </div>
        </div>
      </form>
    </div>
    <div class="product_meta">SKU: GLS-SMP-002</div>
  </div>
  <div class="woocommerce-tabs wc-tabs-wrapper">
    <ul class="tabs"><li class="description_tab active"><a href="#tab-description">Deskripsi</a></li></ul>
    <div id="tab-description" class="woocommerce-Tabs-panel woocommerce-Tabs-panel--description panel entry-content wc-tab">
      <h3>Karakter produk</h3>
      <p>Pembersih wajah berformat gel.</p>
      <p>Lihat juga:</p>
      <div class="woocommerce">
        <div class="single-product">
          <div id="product-777" class="product type-product post-777 status-publish instock purchasable product-type-simple" data-test-nested-product>
            <div class="woocommerce-product-gallery">
              <div class="woocommerce-product-gallery__wrapper"><div class="woocommerce-product-gallery__image"><img src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22600%22 height=%22600%22/%3E" alt=""></div></div>
            </div>
            <div class="summary entry-summary" data-test-nested-summary>
              <h1 class="product_title">A Different Cross-Sell Product</h1>
              <p class="price"><span class="amount">Rp99.000</span></p>
              <form class="cart" action="/product/other-product/" method="post" data-product_id="777">
                <button type="submit" class="single_add_to_cart_button button alt" name="add-to-cart" value="777">Tambah ke keranjang</button>
                <input type="hidden" name="product_id" value="777">
              </form>
            </div>
            <div class="woocommerce-tabs wc-tabs-wrapper" data-test-nested-tabs>
              <ul class="tabs"><li class="description_tab active"><a href="#">Deskripsi</a></li></ul>
              <div class="woocommerce-Tabs-panel woocommerce-Tabs-panel--description panel entry-content wc-tab"><p>Different product's own description.</p></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="related products">
    <h2>Produk terkait</h2>
    <ul class="products">
      <li class="product"><a href="/product/related-1/"><img src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22300%22 height=%22300%22/%3E" alt=""><span class="woocommerce-loop-product__title">Related 1</span></a><span class="price"><span class="amount">Rp150.000</span></span><a class="button add_to_cart_button" href="#">Tambah ke keranjang</a></li>
    </ul>
  </div>
</div>
</div>
</main>
<footer style="height:200px;background:#eee" data-test-footer>site footer</footer>
</body>
</html>
"""

RUNTIME = r"""
window.gloskinData = {
  woo: true,
  restUrl: '/wp-json/gloskin/v1/',
  restNonce: 'fixture',
  cartUrl: '/cart/',
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
      attr(name, value) { if (target && target.setAttribute && value !== undefined) { target.setAttribute(name, value); } return this; }
    };
  }
  window.jQuery = jq;
})();
"""


def require(condition, message):
    if not condition:
        raise AssertionError(message)


with sync_playwright() as p:
    chromium_path = Path('/usr/bin/chromium')
    if not chromium_path.exists():
        print("single-product-dock-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)
    browser = p.chromium.launch(headless=True, executable_path=str(chromium_path), args=['--no-sandbox'])
    page = browser.new_page(viewport={"width": 1280, "height": 900})
    page.set_content(HTML)
    page.add_style_tag(content=CSS_BASE + "\n" + CSS_CORE)
    page.add_script_tag(content=RUNTIME)
    page.add_script_tag(content=JS_CORE)

    # A. Exactly one product root gets the primary PDP two-column grid --
    # the nested different-product embed must default to plain block flow,
    # never inherit gallery/summary/tabs/dock geometry.
    primary_display = page.evaluate("getComputedStyle(document.querySelector('.gloskin-ui1-commerce-native > div.product')).display")
    nested_display = page.evaluate("getComputedStyle(document.querySelector('[data-test-nested-product]')).display")
    require(primary_display == 'grid', 'primary product root must receive the PDP two-column grid layout')
    require(nested_display != 'grid', 'nested different-product embed must NOT inherit the primary PDP grid layout')
    nested_summary_display = page.evaluate("getComputedStyle(document.querySelector('[data-test-nested-summary]')).flexDirection")
    require(nested_summary_display != 'column', 'nested embed summary must not receive primary summary flex layout')

    # B. Exactly one purchase dock, wrapping exactly one form.cart -- the
    # dock-scoped selector gloskin-ui1-core.js now queries.
    dock_count = page.locator('[data-gloskin-purchase-dock]').count()
    require(dock_count == 1, f'expected exactly one purchase dock, found {dock_count}')
    dock_form_count = page.locator('[data-gloskin-purchase-dock] form.cart').count()
    require(dock_form_count == 1, f'expected exactly one form.cart inside the purchase dock, found {dock_form_count}')
    bound_product_id = page.evaluate("document.querySelector('[data-gloskin-purchase-dock] form.cart').getAttribute('data-product_id')")
    require(bound_product_id == '501', 'JS single-product AJAX must bind the primary product\'s own form, never the nested embed\'s')

    # C. Desktop, tall viewport: the dock floats/sticks.
    dock_position_desktop = page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]')).position")
    require(dock_position_desktop == 'sticky', 'desktop dock must be position:sticky when viewport height permits')
    dock_overflow_desktop = page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]')).overflowY")
    require(dock_overflow_desktop not in ('auto', 'scroll'), 'dock must never grow an internal scrollbar on desktop')

    # D. Mobile viewport, still tall enough: same floating dock, no
    # horizontal overflow, touch-safe CTA.
    page.set_viewport_size({"width": 390, "height": 844})
    page.wait_for_timeout(30)
    dock_position_mobile = page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]')).position")
    require(dock_position_mobile == 'sticky', 'mobile dock must also be position:sticky -- never desktop-only')
    require(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), 'mobile page must not horizontally overflow')
    button_box = page.locator('[data-gloskin-purchase-dock] .single_add_to_cart_button').bounding_box()
    require(button_box and button_box['height'] >= 44, 'mobile Add to Cart must remain touch-safe (>=44px)')
    dock_overflow_mobile = page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]')).overflowY")
    require(dock_overflow_mobile not in ('auto', 'scroll'), 'dock must never grow an internal scrollbar on mobile')

    # E. Short viewport: degrade to normal document flow, never a scroll box.
    page.set_viewport_size({"width": 1280, "height": 420})
    page.wait_for_timeout(30)
    dock_position_short = page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]')).position")
    require(dock_position_short != 'sticky', 'short viewport must degrade the dock to normal document flow')
    dock_overflow_short = page.evaluate("getComputedStyle(document.querySelector('[data-gloskin-purchase-dock]')).overflowY")
    require(dock_overflow_short not in ('auto', 'scroll'), 'degraded short-viewport dock must still never grow an internal scrollbar')

    # F. No overlap: scrolled to the bottom of a tall desktop viewport, the
    # sticky dock never covers the footer or the sticky header.
    page.set_viewport_size({"width": 1280, "height": 900})
    page.wait_for_timeout(30)
    page.evaluate('window.scrollTo(0, document.body.scrollHeight)')
    page.wait_for_timeout(30)
    dock_box = page.locator('[data-gloskin-purchase-dock]').bounding_box()
    footer_box = page.locator('[data-test-footer]').bounding_box()
    header_box = page.locator('[data-test-header]').bounding_box()
    require(dock_box and footer_box and dock_box['y'] + dock_box['height'] <= footer_box['y'] + 1, 'purchase dock must never overlap the footer')
    require(dock_box and header_box and dock_box['y'] >= header_box['y'] + header_box['height'] - 1, 'purchase dock must never overlap the header')

    browser.close()

print('single-product-dock-browser-smoke: OK')
