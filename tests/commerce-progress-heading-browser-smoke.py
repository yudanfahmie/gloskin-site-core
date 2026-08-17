#!/usr/bin/env python3
"""Focused Cart/Checkout journey-heading and native Cart hydration presentation smoke."""
import os
import shutil
from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
COMMERCE_CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-commerce-polish.css').read_text(encoding='utf-8')
PRODUCTION_CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-production.css').read_text(encoding='utf-8')

BASE = '''
:root{--gloskin-font-heading:serif;--gloskin-font-body:Arial,sans-serif;--gloskin-container:1180px;--gloskin-gutter:24px;--gloskin-text:#2A232C;--gloskin-muted:#6F6667;--gloskin-border:#DDD7D3;--gloskin-bg:#fff;--gloskin-surface:#F8F5F3;--gloskin-surface-strong:#EFE9E6;--gloskin-accent:#B12E2F;--gloskin-accent-strong:#8F2025;--gloskin-accent-readable:#8F2025;--gloskin-inverse:#fff;--gloskin-radius-sm:8px;--gloskin-radius-md:12px;--gloskin-action-radius:8px}
*{box-sizing:border-box}html,body{margin:0}body{min-height:100vh}.gloskin-ui1-container{width:min(calc(100% - 48px),1180px);margin:auto}
/* Woo-like initial Cart block hydration footprint. It is intentionally native-owned. */
.wc-block-components-skeleton--cart{display:grid;grid-template-columns:2fr 1fr;gap:28px;min-height:230px;padding:24px;border:1px solid #ece7e4;background:#fff}
.wc-block-components-skeleton--cart span{display:block;min-height:28px;background:#eee8e5;border-radius:6px}
.wc-block-components-skeleton--cart span:first-child{min-height:170px}
'''


def journey_markup():
    return '''<header class="gloskin-ui1-commerce-heading gloskin-ui1-commerce-heading--journey"><div class="gloskin-ui1-container"><nav class="gloskin-ui1-commerce-progress gloskin-ui1-commerce-progress--cart" data-gloskin-commerce-progress><h1 class="gloskin-ui1-commerce-progress__step gloskin-ui1-commerce-progress__step--cart is-active">Keranjang</h1><span class="gloskin-ui1-commerce-progress__connector"><span class="gloskin-ui1-commerce-progress__connector-progress"></span></span><a class="gloskin-ui1-commerce-progress__step gloskin-ui1-commerce-progress__step--checkout" href="#checkout">Checkout</a></nav></div></header>'''


def handoff_markup():
    return '''<div class="gloskin-ui1-commerce-handoff" data-gloskin-commerce-handoff aria-hidden="true"><svg class="gloskin-ui1-commerce-handoff__defs" xmlns="http://www.w3.org/2000/svg" width="0" height="0"><defs><filter id="gloskin-ui1-commerce-handoff-goo" x="-80%" y="-80%" width="260%" height="260%"><feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur"/><feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -10" result="goo"/></filter></defs></svg><div class="gloskin-ui1-commerce-handoff__goo"><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span></div></div>'''


def build_html(hydrated=False):
    if hydrated:
        cart = '''<div class="gloskin-ui1-container gloskin-ui1-commerce-native"><div class="wp-block-woocommerce-cart"><div class="wp-block-woocommerce-cart-line-items-block"><table class="wc-block-cart-items" data-hydrated-cart><thead><tr><th>Product</th><th>Total</th></tr></thead><tbody><tr class="wc-block-cart-items__row"><td>Product</td><td>Rp 100.000</td></tr></tbody></table></div></div></div>'''
    else:
        cart = '''<div class="gloskin-ui1-container gloskin-ui1-commerce-native"><div class="wp-block-woocommerce-cart" data-prehydration-cart><div class="wc-block-cart__main"><div class="wc-block-components-skeleton wc-block-components-skeleton--cart" data-woo-cart-skeleton aria-hidden="true"><span></span><span></span></div></div></div></div>'''
    return f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{BASE}\n{COMMERCE_CSS}\n/* production intentionally follows commerce to reproduce the real global-H1 cascade pressure */\n{PRODUCTION_CSS}</style></head><body class="gloskin-ui1 woocommerce-cart">{journey_markup()}{handoff_markup()}{cart}</body></html>'''


def heading_state(page):
    return page.evaluate('''() => {
      const root=document.querySelector('[data-gloskin-commerce-progress]');
      const active=root.querySelector('h1.gloskin-ui1-commerce-progress__step');
      const inactive=root.querySelector('a.gloskin-ui1-commerce-progress__step');
      const connector=root.querySelector('.gloskin-ui1-commerce-progress__connector');
      const a=getComputedStyle(active), i=getComputedStyle(inactive);
      return {
        h1Count:root.querySelectorAll('h1').length,
        progressCount:document.querySelectorAll('[data-gloskin-commerce-progress]').length,
        active:{font:parseFloat(a.fontSize),shadow:a.textShadow,synthesis:a.fontSynthesis,box:active.getBoundingClientRect().toJSON()},
        inactive:{font:parseFloat(i.fontSize),box:inactive.getBoundingClientRect().toJSON()},
        connector:connector.getBoundingClientRect().toJSON()
      };
    }''')


def assert_heading(page, max_font=54):
    s=heading_state(page)
    assert s['h1Count'] == 1 and s['progressCount'] == 1, s
    assert s['active']['font'] <= max_font, s
    assert abs(s['active']['font'] - s['inactive']['font']) <= 0.5, s
    assert s['active']['shadow'] == 'none', s
    assert s['active']['synthesis'] == 'none', s
    assert s['connector']['left'] >= s['active']['box']['right'] - 0.5, s
    assert s['connector']['right'] <= s['inactive']['box']['left'] + 0.5, s
    return s


def handoff_state(page):
    return page.evaluate('''() => {
      const host=document.querySelector('[data-gloskin-commerce-handoff]');
      const blobs=[...document.querySelectorAll('.gloskin-ui1-commerce-handoff__blob')];
      const hs=getComputedStyle(host), content=getComputedStyle(document.querySelector('.gloskin-ui1-commerce-native'));
      return {host:{position:hs.position,width:parseFloat(hs.width),opacity:parseFloat(hs.opacity)},filter:getComputedStyle(document.querySelector('.gloskin-ui1-commerce-handoff__goo')).filter,blobs:blobs.map(b=>{const s=getComputedStyle(b);return {name:s.animationName,duration:s.animationDuration,delay:s.animationDelay}}),content:{opacity:parseFloat(content.opacity),pointer:content.pointerEvents}};
    }''')

executable = os.environ.get('CHROMIUM_PATH') or shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')
assert executable, 'Chromium executable not found'

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path=executable, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    page.set_content(build_html(False))

    desktop = assert_heading(page)
    skeleton = page.locator('[data-woo-cart-skeleton]')
    assert skeleton.is_visible(), 'native Woo pre-hydration skeleton must remain visible'
    header_box = page.locator('.gloskin-ui1-commerce-heading--journey').bounding_box()
    skeleton_box = skeleton.bounding_box()
    assert header_box and skeleton_box and skeleton_box['y'] >= header_box['y'] + header_box['height'] - 0.5, (header_box, skeleton_box)

    idle = handoff_state(page)
    assert idle['host']['opacity'] == 0 and idle['content']['opacity'] == 1
    assert len(idle['blobs']) == 4 and idle['filter'] != 'none'

    page.evaluate("document.documentElement.classList.add('gloskin-ui1-commerce-journey-leaving')")
    page.wait_for_timeout(220)
    leaving = handoff_state(page)
    assert leaving['host']['position'] == 'fixed' and 104 <= leaving['host']['width'] <= 122
    assert leaving['host']['opacity'] > 0 and leaving['content']['opacity'] == 0 and leaving['content']['pointer'] == 'none'
    page.evaluate("document.documentElement.classList.remove('gloskin-ui1-commerce-journey-leaving')")
    page.wait_for_timeout(220)

    for width in (390, 320):
        page.set_viewport_size({'width': width, 'height': 800})
        mobile = assert_heading(page)
        assert mobile['active']['font'] <= 45, mobile
        assert page.evaluate('document.documentElement.scrollWidth') <= width + 1

    hydrated = browser.new_page(viewport={'width': 1440, 'height': 900})
    hydrated.set_content(build_html(True))
    assert_heading(hydrated)
    table = hydrated.locator('[data-hydrated-cart]')
    assert table.is_visible()
    thead_style = hydrated.eval_on_selector('[data-hydrated-cart] thead', "e=>({background:getComputedStyle(e).backgroundColor,border:getComputedStyle(e).borderTopWidth})")
    th_style = hydrated.eval_on_selector('[data-hydrated-cart] thead th', "e=>({color:getComputedStyle(e).color,border:getComputedStyle(e).borderTopWidth,weight:getComputedStyle(e).fontWeight})")
    assert thead_style['background'] == 'rgb(177, 46, 47)', thead_style
    assert thead_style['border'] == '0px', thead_style
    assert th_style['color'] == 'rgb(255, 255, 255)', th_style
    assert th_style['border'] == '0px' and int(th_style['weight']) >= 700, th_style

    reduced = browser.new_page(viewport={'width': 390, 'height': 800}, reduced_motion='reduce')
    reduced.set_content(build_html(False))
    assert_heading(reduced)
    reduced.evaluate("document.documentElement.classList.add('gloskin-ui1-commerce-journey-arriving')")
    reduced_state = handoff_state(reduced)
    assert reduced_state['host']['opacity'] > 0 and reduced_state['content']['opacity'] == 0
    reduced.close()
    hydrated.close()
    browser.close()

print('commerce progress heading/native Cart hydration presentation smoke passed')
