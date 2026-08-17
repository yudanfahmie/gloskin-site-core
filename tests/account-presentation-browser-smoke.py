#!/usr/bin/env python3
"""Focused logged-in My Account address-grid and notice-presentation smoke."""
import os
import shutil
from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
READINESS_CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-readiness.css').read_text(encoding='utf-8')

TOKENS = '''
:root{--gloskin-font-heading:serif;--gloskin-font-body:Arial,sans-serif;--gloskin-text:#2A232C;--gloskin-muted:#6F6667;--gloskin-border:#DDD7D3;--gloskin-bg:#fff;--gloskin-surface:#F8F5F3;--gloskin-accent:#B12E2F;--gloskin-accent-strong:#8F2025;--gloskin-accent-readable:#8F2025;--gloskin-accent-soft:#F8EAEA;--gloskin-inverse:#fff;--gloskin-radius-sm:8px;--gloskin-radius-md:12px;--gloskin-field-height:46px;--gloskin-field-border:#DDD7D3;--gloskin-field-radius:8px;--gloskin-field-bg:#fff;--gloskin-action-radius:8px}
*{box-sizing:border-box}html,body{margin:0}body{font:16px/1.5 Arial,sans-serif}.gloskin-ui1-commerce-native{width:min(calc(100% - 40px),980px);margin:24px auto}
'''

# This deliberately follows Gloskin readiness CSS to model the real staging
# coexistence problem: generic Woo/WPCodeBox-era clearfix/float/icon rules load
# later, but the explicit logged-in component owners must still win cleanly.
LATE_BASELINE = '''
.woocommerce-account .col2-set::before,.woocommerce-account .col2-set::after{content:" ";display:table}.woocommerce-account .col2-set::after{clear:both}.woocommerce-account .col-1{float:left;width:48%}.woocommerce-account .col-2{float:right;width:48%}
.woocommerce-account .woocommerce-info::before,.woocommerce-account .woocommerce-message::before{content:"i";display:inline-block;color:#1677ff}.woocommerce-account .woocommerce-info::after,.woocommerce-account .woocommerce-message::after{content:"";display:table;clear:both}
'''

HTML = f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{TOKENS}\n{READINESS_CSS}\n{LATE_BASELINE}</style></head><body class="gloskin-ui1 woocommerce-account logged-in"><main class="gloskin-ui1-commerce-native"><div class="woocommerce">
<nav class="woocommerce-MyAccount-navigation"><ul><li class="is-active"><a href="#">Dashboard</a></li><li><a href="#">Orders</a></li><li><a href="#">Downloads</a></li><li><a href="#">Addresses</a></li><li><a href="#">Account details</a></li><li><a href="#">Logout</a></li></ul></nav>
<div class="woocommerce-MyAccount-content">
<div class="woocommerce-info" data-notice="one"><span data-notice-copy>Confirm your email address to keep your account secure.</span><a class="button" href="#">Confirm email address</a></div>
<div class="woocommerce-info" data-notice="two"><span data-notice-copy>No order has been made yet.</span><a class="button" href="#">Browse products</a></div>
<p data-intro>The following addresses will be used on the checkout page by default.</p>
<div class="woocommerce-Addresses col2-set addresses" data-addresses>
<div class="u-column1 col-1 woocommerce-Address" data-address="billing"><header class="woocommerce-Address-title"><h2>Billing address</h2><a class="edit" href="#">Edit</a></header><address>Olivia Miller<br>Example Street 1<br>Jakarta</address></div>
<div class="u-column2 col-2 woocommerce-Address" data-address="shipping"><header class="woocommerce-Address-title"><h2>Shipping address</h2><a class="edit" href="#">Edit</a></header><address>Olivia Miller<br>Example Street 2<br>Jakarta</address></div>
</div></div></div></main></body></html>'''


def box(page, selector):
    value = page.locator(selector).bounding_box()
    assert value, selector
    return value


def pseudo_content(page, selector, pseudo):
    return page.eval_on_selector(selector, f"e=>getComputedStyle(e,'::{pseudo}').content")


def assert_no_text_button_overlap(page, notice):
    copy = box(page, f'{notice} [data-notice-copy]')
    button = box(page, f'{notice} .button')
    separated = copy['x'] + copy['width'] <= button['x'] + 0.5 or copy['y'] + copy['height'] <= button['y'] + 0.5
    assert separated, (copy, button)

executable = os.environ.get('CHROMIUM_PATH') or shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')
assert executable, 'Chromium executable not found'

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path=executable, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    page.set_content(HTML)

    billing = box(page, '[data-address="billing"]')
    shipping = box(page, '[data-address="shipping"]')
    assert billing['x'] < shipping['x'], (billing, shipping)
    assert abs(billing['y'] - shipping['y']) <= 2, (billing, shipping)
    assert abs(billing['width'] - shipping['width']) <= 2, (billing, shipping)
    assert abs(billing['height'] - shipping['height']) <= 2, (billing, shipping)
    assert pseudo_content(page, '[data-addresses]', 'before') == 'none'
    assert pseudo_content(page, '[data-addresses]', 'after') == 'none'

    intro = box(page, '[data-intro]')
    addresses = box(page, '[data-addresses]')
    intro_gap = addresses['y'] - (intro['y'] + intro['height'])
    assert 14 <= intro_gap <= 18, intro_gap

    heading_size = page.eval_on_selector('[data-address="billing"] h2', 'e=>parseFloat(getComputedStyle(e).fontSize)')
    assert 22 <= heading_size <= 25, heading_size
    assert page.eval_on_selector('[data-address="billing"] address', 'e=>getComputedStyle(e).fontStyle') == 'normal'

    for notice in ('[data-notice="one"]', '[data-notice="two"]'):
        assert pseudo_content(page, notice, 'before') == 'none'
        assert pseudo_content(page, notice, 'after') == 'none'
        assert_no_text_button_overlap(page, notice)
    first = box(page, '[data-notice="one"]')
    second = box(page, '[data-notice="two"]')
    assert second['y'] - (first['y'] + first['height']) >= 8, (first, second)

    nav_link_height = page.eval_on_selector('.woocommerce-MyAccount-navigation a', 'e=>e.getBoundingClientRect().height')
    active_shadow = page.eval_on_selector('.woocommerce-MyAccount-navigation .is-active>a', 'e=>getComputedStyle(e).boxShadow')
    assert nav_link_height >= 40, nav_link_height
    assert '5px' not in active_shadow, active_shadow

    for width in (390, 320):
        page.set_viewport_size({'width': width, 'height': 900})
        billing = box(page, '[data-address="billing"]')
        shipping = box(page, '[data-address="shipping"]')
        assert billing['y'] < shipping['y'], (width, billing, shipping)
        assert abs(billing['x'] - shipping['x']) <= 2, (width, billing, shipping)
        assert abs(billing['width'] - shipping['width']) <= 2, (width, billing, shipping)
        assert pseudo_content(page, '[data-addresses]', 'before') == 'none'
        assert pseudo_content(page, '[data-addresses]', 'after') == 'none'
        nav = page.locator('.woocommerce-MyAccount-navigation')
        scroll = nav.evaluate('e=>({scroll:e.scrollWidth,client:e.clientWidth})')
        assert scroll['scroll'] > scroll['client'], (width, scroll)
        assert page.evaluate('document.documentElement.scrollWidth') <= width + 1
        first_copy = box(page, '[data-notice="one"] [data-notice-copy]')
        first_button = box(page, '[data-notice="one"] .button')
        assert first_button['y'] >= first_copy['y'] + first_copy['height'] - 0.5, (width, first_copy, first_button)

    browser.close()

print('logged-in My Account address/notice presentation smoke passed')
