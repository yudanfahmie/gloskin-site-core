#!/usr/bin/env python3
"""Real-Chromium regression for the WooCommerce Checkout block's Country/
Region + Province select geometry and the shared Cart/Checkout primary CTA
typography (gloskin-ui1-core.css's "WooCommerce Blocks cart/checkout"
section). Embeds a minimal, faithful reproduction of WC's own !important
select/checkbox constraints (reverse-engineered from real staging this
session via getComputedStyle probing) alongside the real, unmodified
Gloskin CSS files, so a future change here is checked locally against the
same cascade that produced the original label/value collision, before
staging is ever touched again."""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("checkout-block-presentation-regression: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugin" / "gloskin-site-core"
CSS_BASE = (PLUGIN / "assets/css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CSS_CORE = (PLUGIN / "assets/css/gloskin-ui1-core.css").read_text(encoding="utf-8")
CSS_PRODUCTION = (PLUGIN / "assets/css/gloskin-ui1-production.css").read_text(encoding="utf-8")

# WC Blocks' own select/checkbox/CTA constraints, reverse-engineered from
# real staging (gloskin-id.markas.cloud) this session -- not guessed. This
# is what caused the original label/value collision and the checkbox's
# zero gap. None of these are actually !important on the real site (an
# earlier draft of this fixture assumed they were and was corrected after
# proving, on real staging, that a plain non-important 3-class rule could
# win against them -- CSS cannot let a non-important declaration beat a
# real !important one, so the win itself was proof there was none). The
# specificity below (a single class each) is deliberately lower than the
# real fix's 3-class selector, matching what was actually needed to win
# on real staging.
WC_CHECKOUT_BASELINE = r"""
.wc-blocks-components-select__container{position:relative}
.wc-blocks-components-select__select{
  min-height:52px;
  height:52px;
  width:100%;
  padding:10px 40px 10px 16px;
  border:1px solid rgba(42,35,44,.3);
  border-radius:4px;
  appearance:none;
  font-size:16px;
}
.wc-blocks-components-select__label{
  position:absolute;
  top:6px;
  left:10px;
  font-size:14.72px;
  line-height:19.136px;
  pointer-events:none;
}
.wc-blocks-components-select__expand{position:absolute;top:14px;right:8px;width:24px;height:24px}
.wc-block-components-checkbox label{display:block}
.wc-block-components-checkbox__input{width:20px;height:20px;display:inline-block;vertical-align:top}
.wc-block-components-checkbox__label{display:inline;font-size:16px}
.wc-block-components-checkout-place-order-button{font-size:13px;font-weight:400}
.wc-block-cart__submit-button{font-size:16px;font-weight:400}
"""

FIXTURE_LAYOUT = r"""
body{margin:0}
.gloskin-ui1-commerce-native{width:min(calc(100% - 36px),1400px);margin-inline:auto}
"""


def checkout_markup(country_value, country_label, province_value, province_label):
    return f"""<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="woocommerce-checkout woocommerce-page gloskin-ui1">
<div class="woocommerce gloskin-ui1-commerce-native">
<div class="wp-block-woocommerce-checkout alignwide wc-block-checkout">
<div class="wc-block-components-sidebar-layout wc-block-checkout">
<div class="wc-block-components-main wc-block-checkout__main wp-block-woocommerce-checkout-fields-block">
<form class="wc-block-components-form wc-block-checkout__form">
<fieldset class="wc-block-checkout__billing-fields wp-block-woocommerce-checkout-billing-address-block wc-block-components-checkout-step">
<div class="wc-block-components-checkout-step__content">
<div class="wc-block-components-address-form">
<div class="wc-block-components-address-form__country wc-block-components-country-input">
<div class="wc-blocks-components-select">
<div class="wc-blocks-components-select__container">
<label for="billing-country" class="wc-blocks-components-select__label">Country/Region</label>
<select class="wc-blocks-components-select__select" id="billing-country">
<option value="">Select a country/region</option>
<option value="{country_value}" selected>{country_label}</option>
</select>
<svg class="wc-blocks-components-select__expand" viewBox="0 0 24 24"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg>
</div>
</div>
</div>
<div class="wc-block-components-address-form__state wc-block-components-state-input">
<div class="wc-blocks-components-select">
<div class="wc-blocks-components-select__container">
<label for="billing-state" class="wc-blocks-components-select__label">Province</label>
<select class="wc-blocks-components-select__select" id="billing-state">
<option value="">Select a province</option>
<option value="{province_value}" selected>{province_label}</option>
</select>
<svg class="wc-blocks-components-select__expand" viewBox="0 0 24 24"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg>
</div>
</div>
</div>
</div>
</div>
</fieldset>
<div class="wc-block-checkout__order-notes wp-block-woocommerce-checkout-order-note-block wc-block-components-checkout-step">
<div class="wc-block-components-checkout-step__content">
<div class="wc-block-checkout__add-note">
<div class="wc-block-components-checkbox">
<label for="checkbox-control-0">
<input id="checkbox-control-0" class="wc-block-components-checkbox__input" type="checkbox" value="">
<svg class="wc-block-components-checkbox__mark" viewBox="0 0 24 20"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"></path></svg>
<span class="wc-block-components-checkbox__label">Add a note to your order</span>
</label>
</div>
</div>
</div>
</div>
<button type="submit" class="wc-block-components-button wp-element-button wc-block-components-checkout-place-order-button contained">Place Order</button>
</form>
</div>
</div>
</div>
</div>
</body></html>"""


def cart_cta_markup():
    return """<!doctype html><html><head><meta charset="utf-8"></head>
<body class="woocommerce-cart woocommerce-page gloskin-ui1">
<div class="woocommerce gloskin-ui1-commerce-native">
<div class="wp-block-woocommerce-cart alignwide">
<a class="wc-block-components-button wp-element-button wc-block-cart__submit-button contained" href="#">Proceed to Checkout</a>
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
        print('checkout-block-presentation-regression: SKIPPED (chromium unavailable)')
        raise SystemExit(77)
    return p.chromium.launch(headless=True, args=['--no-sandbox'])


def select_geometry(page, select_id):
    return page.evaluate(
        """(id) => {
            const container = document.getElementById(id).closest('.wc-blocks-components-select__container');
            const label = container.querySelector('.wc-blocks-components-select__label');
            const select = container.querySelector('.wc-blocks-components-select__select');
            const lb = label.getBoundingClientRect();
            const sb = select.getBoundingClientRect();
            const paddingTop = parseFloat(getComputedStyle(select).paddingTop);
            return {
                labelBottom: lb.bottom,
                selectContentTop: sb.top + paddingTop,
                selectRight: sb.right,
                selectHeight: sb.height,
            };
        }""",
        select_id,
    )


with sync_playwright() as p:
    browser = launch_browser(p)

    # A. Populated selects: label must never overlap where the selected
    # value renders (content-box top, i.e. past the select's own padding-
    # top) -- for both Country/Region and Province, at both a short and a
    # long selected label to guard the "long selected text" case too.
    for width in (375, 1024):
        for country_label in ("Indonesia", "United States (US) Minor Outlying Islands"):
            page = browser.new_page(viewport={'width': width, 'height': 900})
            page.set_content(checkout_markup("ID", country_label, "JK", "Daerah Khusus Ibukota Jakarta"))
            page.add_style_tag(content=CSS_BASE + '\n' + CSS_CORE + '\n' + WC_CHECKOUT_BASELINE + '\n' + CSS_PRODUCTION + '\n' + FIXTURE_LAYOUT)
            page.wait_for_timeout(30)

            label = f'{width}px country_label={country_label!r}'
            overflow_x = page.evaluate('document.documentElement.scrollWidth - document.documentElement.clientWidth')
            require(overflow_x <= 1, f'{label}: horizontal document overflow of {overflow_x}px')

            country = select_geometry(page, 'billing-country')
            province = select_geometry(page, 'billing-state')

            require(country['labelBottom'] <= country['selectContentTop'] + 0.5, f'{label}: Country label overlaps selected-value content area: {country}')
            require(province['labelBottom'] <= province['selectContentTop'] + 0.5, f'{label}: Province label overlaps selected-value content area: {province}')
            require(country['selectRight'] <= width + 1, f'{label}: Country select overflows viewport: {country}')
            require(province['selectRight'] <= width + 1, f'{label}: Province select overflows viewport: {province}')

            # B. Country and Province must share the same geometry (same
            # component, same fix -- this is what makes that guarantee
            # cheap: if it ever stops being true, this catches it).
            require(abs(country['selectHeight'] - province['selectHeight']) < 0.5, f'{label}: Country/Province select heights differ: {country} vs {province}')

            # G. Order note checkbox: gap must be real, mark/label vertically
            # aligned, comfortable clickable height.
            checkbox = page.evaluate(
                """() => {
                    const input = document.querySelector('.wc-block-components-checkbox__input');
                    const span = document.querySelector('.wc-block-components-checkbox__label');
                    const label = document.querySelector('.wc-block-components-checkbox label');
                    const ib = input.getBoundingClientRect();
                    const sb = span.getBoundingClientRect();
                    const lb = label.getBoundingClientRect();
                    return {gap: sb.left - ib.right, centerDelta: Math.abs((ib.top + ib.height / 2) - (sb.top + sb.height / 2)), labelHeight: lb.height};
                }"""
            )
            require(checkbox['gap'] >= 6, f'{label}: order note checkbox/label gap too tight: {checkbox}')
            require(checkbox['centerDelta'] <= 2, f'{label}: order note checkbox/label not vertically centered: {checkbox}')
            require(checkbox['labelHeight'] >= 40, f'{label}: order note label below a comfortable touch height: {checkbox}')

            page.close()

    # C. CTA typography: Place Order must match the shared kit (>=16px,
    # weight >=700), and Cart's Proceed to Checkout must match it exactly
    # (same family, not just "both acceptable").
    page = browser.new_page(viewport={'width': 1024, 'height': 900})
    page.set_content(checkout_markup("ID", "Indonesia", "JK", "DKI Jakarta"))
    page.add_style_tag(content=CSS_BASE + '\n' + CSS_CORE + '\n' + WC_CHECKOUT_BASELINE + '\n' + CSS_PRODUCTION + '\n' + FIXTURE_LAYOUT)
    page.wait_for_timeout(30)
    place_order = page.evaluate(
        """() => {
            const el = document.querySelector('.wc-block-components-checkout-place-order-button');
            const c = getComputedStyle(el);
            return {fontSize: parseFloat(c.fontSize), fontWeight: parseInt(c.fontWeight, 10)};
        }"""
    )
    require(place_order['fontSize'] >= 16, f'Place Order font-size below 16px: {place_order}')
    require(place_order['fontWeight'] >= 700, f'Place Order font-weight below 700: {place_order}')
    page.close()

    page = browser.new_page(viewport={'width': 1024, 'height': 900})
    page.set_content(cart_cta_markup())
    page.add_style_tag(content=CSS_BASE + '\n' + CSS_CORE + '\n' + WC_CHECKOUT_BASELINE + '\n' + CSS_PRODUCTION + '\n' + FIXTURE_LAYOUT)
    page.wait_for_timeout(30)
    proceed = page.evaluate(
        """() => {
            const el = document.querySelector('.wc-block-cart__submit-button');
            const c = getComputedStyle(el);
            return {fontSize: parseFloat(c.fontSize), fontWeight: parseInt(c.fontWeight, 10)};
        }"""
    )
    require(proceed['fontSize'] >= 16, f'Proceed to Checkout font-size below 16px: {proceed}')
    require(proceed['fontWeight'] >= 700, f'Proceed to Checkout font-weight below 700: {proceed}')
    require(proceed['fontSize'] == place_order['fontSize'] and proceed['fontWeight'] == place_order['fontWeight'], f'Cart/Checkout primary CTA typography does not match: proceed={proceed} place_order={place_order}')
    page.close()

    browser.close()

print('checkout-block-presentation-regression: OK')
