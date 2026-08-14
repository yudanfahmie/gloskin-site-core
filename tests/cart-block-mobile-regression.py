#!/usr/bin/env python3
"""Real-Chromium regression for the WooCommerce Cart block's mobile row
presentation (gloskin-ui1-production.css's "Cart item presentation"
section). Exists because two earlier fixes for this exact area passed
rect-math/diagnostic-CSS checks and still regressed on real staging: the
diagnostic style tags used for verification did not match the cascade of
the actually-shipped CSS against WC's own !important-heavy Cart Block
stylesheet. This fixture embeds a minimal, faithful reproduction of that
WC baseline (the specific !important declarations reverse-engineered from
real staging this session -- grid-template-columns, forced grid-row-start
on every cell, the product cell's forced grid-column-end, the remove
button's forced width/height) alongside the real, unmodified production
CSS file, so a future change to this section is checked against the same
constraints that caused the previous regressions, locally, before ever
touching staging."""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("cart-block-mobile-regression: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugin" / "gloskin-site-core"
CSS_BASE = (PLUGIN / "assets/css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CSS_CORE = (PLUGIN / "assets/css/gloskin-ui1-core.css").read_text(encoding="utf-8")
CSS_PRODUCTION = (PLUGIN / "assets/css/gloskin-ui1-production.css").read_text(encoding="utf-8")

# The exact !important constraints WC Blocks' own Cart stylesheet applies
# on mobile, reverse-engineered from real staging (gloskin-id.markas.cloud)
# this session via getComputedStyle probing -- not guessed. This is what
# actually defeated the two earlier (reverted) fix attempts.
WC_CART_BASELINE = r"""
.wc-block-cart-items{display:block}
.wc-block-cart-items thead{display:none}
@media (max-width:768px){
  .wc-block-cart-items__row{
    display:grid;
    grid-template-columns:80px 132px !important;
    column-gap:16px;
    padding:16px 0;
    border-bottom:1px solid rgb(226,232,240);
  }
  .wc-block-cart-item__image{grid-row-start:1 !important;grid-column-start:1 !important}
  .wc-block-cart-item__product{grid-row-start:1 !important;grid-column-start:2 !important;grid-column-end:4 !important}
  .wc-block-cart-item__total{grid-row-start:1 !important}
  .wc-block-cart-item__wrap{display:flex;flex-direction:column;gap:4px}
  .wc-block-cart-item__quantity{display:flex;align-items:center;gap:12px;margin-top:8px}
  .wc-block-components-quantity-selector{display:flex;border:1px solid rgba(42,35,44,.3);border-radius:4px;height:18px}
  .wc-block-components-quantity-selector__input{order:2;width:45px;height:16px;border:0}
  .wc-block-components-quantity-selector__button--minus{order:1;width:30px;border-radius:4px 0 0 4px}
  .wc-block-components-quantity-selector__button--plus{order:3;width:30px;border-radius:0 4px 4px 0}
  .wc-block-cart-item__remove-link{width:24px !important;height:24px !important}
  .wc-block-cart-item__total{display:flex;justify-content:space-between;align-items:center}
}
"""

FIXTURE_LAYOUT = r"""
body{margin:0}
.gloskin-ui1-commerce-native{width:min(calc(100% - 36px),1400px);margin-inline:auto}
"""

STRESS_TITLES = {
    "normal": "Gloskin Gentle Balance Facial Cleanser",
    "long": "Gloskin Ultra Premium Anti-Aging Advanced Repair Night Cream With Retinol And Ceramide Complex For Sensitive Skin",
}
STRESS_SUBTOTALS = ["Rp165.000", "Rp1.209.000", "Rp12.345.678"]
STRESS_QUANTITIES = [1, 99]
VIEWPORTS = [320, 375, 390]


def markup(title, qty, subtotal):
    return f"""<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="woocommerce-cart woocommerce-page gloskin-ui1">
<div class="woocommerce gloskin-ui1-commerce-native">
<div class="wp-block-woocommerce-cart alignwide">
<div class="wc-block-components-sidebar-layout wc-block-cart wp-block-woocommerce-filled-cart-block">
<div class="wc-block-components-main wc-block-cart__main wp-block-woocommerce-cart-items-block">
<div class="table-container">
<table class="wc-block-cart-items wp-block-woocommerce-cart-line-items-block" tabindex="-1">
<thead><tr class="wc-block-cart-items__header"><th>Product</th><th>Details</th><th>Total</th></tr></thead>
<tbody><tr role="row" class="wc-block-cart-items__row" tabindex="-1">
<td class="wc-block-cart-item__image" aria-hidden="true"><a href="#" tabindex="-1"><img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7" width="80" height="80" alt=""></a></td>
<td role="rowheader" class="wc-block-cart-item__product">
<div class="wc-block-cart-item__wrap">
<a class="wc-block-components-product-name" href="#">{title}</a>
<div class="wc-block-cart-item__prices"><span class="price wc-block-components-product-price"><span class="wc-block-components-product-price__value">Rp165.000</span></span></div>
<div class="wc-block-cart-item__quantity">
<div class="wc-block-components-quantity-selector">
<input class="wc-block-components-quantity-selector__input" type="number" step="1" min="1" max="9999" value="{qty}">
<button class="wc-block-components-quantity-selector__button wc-block-components-quantity-selector__button--minus">&#8722;</button>
<button class="wc-block-components-quantity-selector__button wc-block-components-quantity-selector__button--plus">+</button>
</div>
<button class="wc-block-cart-item__remove-link" aria-label="Remove"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"></svg></button>
</div>
</div>
</td>
<td class="wc-block-cart-item__total"><div class="wc-block-cart-item__total-price-and-sale-badge-wrapper"><span class="price wc-block-components-product-price"><span class="wc-block-components-product-price__value">{subtotal}</span></span></div></td>
</tr></tbody>
</table>
</div>
</div>
<div class="wc-block-components-sidebar wc-block-cart__sidebar wp-block-woocommerce-cart-totals-block">
<div class="wc-block-cart__submit wp-block-woocommerce-proceed-to-checkout-block">
<div class="wc-block-cart__submit-container"><a class="wc-block-components-button wp-element-button wc-block-cart__submit-button contained" href="#">Proceed to Checkout</a></div>
</div>
</div>
</div>
</div>
</div>
</body></html>"""


def require(value, message):
    if not value:
        raise AssertionError(message)


def launch_browser(p):
    chromium = Path('/usr/bin/chromium')
    if chromium.exists():
        return p.chromium.launch(headless=True, args=['--no-sandbox'], executable_path=str(chromium))
    bundled = Path(p.chromium.executable_path)
    if not bundled.exists():
        print('cart-block-mobile-regression: SKIPPED (chromium unavailable)')
        raise SystemExit(77)
    return p.chromium.launch(headless=True, args=['--no-sandbox'])


def rect(page, selector):
    return page.evaluate(
        """(sel) => {
            const el = document.querySelector(sel);
            if (!el) return null;
            const b = el.getBoundingClientRect();
            return {top: b.top, left: b.left, right: b.right, bottom: b.bottom, width: b.width, height: b.height};
        }""",
        selector,
    )


def intersects(a, b):
    if not a or not b:
        return False
    return a['left'] < b['right'] and a['right'] > b['left'] and a['top'] < b['bottom'] and a['bottom'] > b['top']


def title_glyph_rects(page, selector):
    """The title reserves space via padding-right rather than a hard
    width, so its own element box intentionally extends into that
    reserved zone (harmless empty space, not text) -- checking the
    element's bounding box for overlap would false-positive on that
    padding. What actually matters is where the rendered TEXT glyphs
    land, which this reads via Range.getClientRects() the same way a
    real screenshot comparison was verified on staging."""
    return page.evaluate(
        """(sel) => {
            const el = document.querySelector(sel);
            if (!el) return [];
            const range = document.createRange();
            range.selectNodeContents(el.firstChild || el);
            return Array.from(range.getClientRects()).map(b => ({top: b.top, left: b.left, right: b.right, bottom: b.bottom}));
        }""",
        selector,
    )


with sync_playwright() as p:
    browser = launch_browser(p)
    checked = 0
    for width in VIEWPORTS:
        for title_key, title in STRESS_TITLES.items():
            for qty in STRESS_QUANTITIES:
                for subtotal in STRESS_SUBTOTALS:
                    page = browser.new_page(viewport={'width': width, 'height': 900})
                    page.set_content(markup(title, qty, subtotal))
                    page.add_style_tag(content=CSS_BASE + '\n' + CSS_CORE + '\n' + WC_CART_BASELINE + '\n' + CSS_PRODUCTION + '\n' + FIXTURE_LAYOUT)
                    page.wait_for_timeout(30)

                    label = f'{width}px title={title_key} qty={qty} subtotal={subtotal}'

                    overflow_x = page.evaluate('document.documentElement.scrollWidth - document.documentElement.clientWidth')
                    require(overflow_x <= 1, f'{label}: horizontal document overflow of {overflow_x}px')

                    row = rect(page, '.wc-block-cart-items__row')
                    image = rect(page, '.wc-block-cart-item__image')
                    product = rect(page, '.wc-block-cart-item__product')
                    total = rect(page, '.wc-block-cart-item__total')
                    title_lines = title_glyph_rects(page, '.wc-block-components-product-name')

                    require(row['width'] <= width + 1, f'{label}: row width {row["width"]} exceeds viewport {width}')
                    require(image['right'] <= row['right'] + 1, f'{label}: image escapes row right edge: {image} vs {row}')
                    require(product['right'] <= row['right'] + 1, f'{label}: product escapes row right edge: {product} vs {row}')
                    require(total['right'] <= row['right'] + 1, f'{label}: subtotal escapes row right edge: {total} vs {row}')
                    require(total['right'] <= width + 1, f'{label}: subtotal escapes viewport: {total}')

                    # Checked against actual rendered text glyphs, not the
                    # title element's own box -- the title intentionally
                    # reserves space via padding-right, so its box extends
                    # into the subtotal's column as harmless empty space;
                    # only real glyph overlap is a genuine defect (this
                    # exact gap between box-overlap and text-overlap is
                    # what real staging caught that this fixture's earlier
                    # box-only check did not).
                    require(not any(intersects(total, line) for line in title_lines), f'{label}: subtotal intersects product title text: total={total} title_lines={title_lines}')
                    require(not intersects(total, image), f'{label}: subtotal intersects image: total={total} image={image}')

                    qty_selector = rect(page, '.wc-block-components-quantity-selector')
                    require(qty_selector['height'] >= 40, f'{label}: quantity control below touch-safe height: {qty_selector["height"]}px')
                    require(qty_selector['right'] <= row['right'] + 1, f'{label}: quantity control escapes row: {qty_selector} vs {row}')

                    remove_hit_area = page.evaluate(
                        """() => {
                            const el = document.querySelector('.wc-block-cart-item__remove-link');
                            const cs = getComputedStyle(el, '::after');
                            return {position: getComputedStyle(el).position, afterContent: cs.content, afterPosition: cs.position};
                        }"""
                    )
                    require(remove_hit_area['position'] == 'relative', f'{label}: remove action lost its positioning context for the expanded hit area')
                    require(remove_hit_area['afterContent'] not in ('none', ''), f'{label}: remove action expanded hit area (::after) missing')

                    require(row['right'] <= width + 1, f'{label}: row itself overflows viewport: {row}')

                    checked += 1
                    page.close()
    browser.close()

require(checked == len(VIEWPORTS) * len(STRESS_TITLES) * len(STRESS_QUANTITIES) * len(STRESS_SUBTOTALS), f'unexpected checked count: {checked}')
print(f'cart-block-mobile-regression: OK ({checked} stress combinations across {VIEWPORTS} viewports)')
