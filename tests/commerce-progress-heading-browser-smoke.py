#!/usr/bin/env python3
"""Focused Cart/Checkout heading and goo-loader browser smoke."""
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-commerce-polish.css').read_text()

html = f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>
:root{{--gloskin-font-heading:serif;--gloskin-container:1180px;--gloskin-gutter:24px;--gloskin-text:#2A232C;--gloskin-border:#DDD7D3;--gloskin-accent:#B12E2F;--gloskin-accent-strong:#8F2025;--gloskin-inverse:#fff;}}
*{{box-sizing:border-box}}html,body{{margin:0}}body{{min-height:100vh}}
.gloskin-ui1-container{{width:min(calc(100% - 48px),1180px);margin:auto}}
{CSS}</style></head><body class="gloskin-ui1 woocommerce-cart">
<header class="gloskin-ui1-commerce-heading gloskin-ui1-commerce-heading--journey"><div class="gloskin-ui1-container"><nav class="gloskin-ui1-commerce-progress gloskin-ui1-commerce-progress--cart" data-gloskin-commerce-progress><h1 class="gloskin-ui1-commerce-progress__step gloskin-ui1-commerce-progress__step--cart is-active">Keranjang</h1><span class="gloskin-ui1-commerce-progress__connector"><span class="gloskin-ui1-commerce-progress__connector-progress"></span></span><a class="gloskin-ui1-commerce-progress__step gloskin-ui1-commerce-progress__step--checkout" href="#checkout">Checkout</a></nav></div></header>
<div class="gloskin-ui1-commerce-handoff" data-gloskin-commerce-handoff aria-hidden="true"><svg class="gloskin-ui1-commerce-handoff__defs" xmlns="http://www.w3.org/2000/svg" width="0" height="0"><defs><filter id="gloskin-ui1-commerce-handoff-goo" x="-80%" y="-80%" width="260%" height="260%"><feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur"/><feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -10" result="goo"/></filter></defs></svg><div class="gloskin-ui1-commerce-handoff__goo"><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span></div></div>
<div class="gloskin-ui1-commerce-native"><p data-commerce-content>Commerce content</p></div></body></html>'''


def state(page):
    return page.evaluate('''() => {
      const host=document.querySelector('[data-gloskin-commerce-handoff]');
      const blobs=[...document.querySelectorAll('.gloskin-ui1-commerce-handoff__blob')];
      const hs=getComputedStyle(host), content=getComputedStyle(document.querySelector('[data-commerce-content]'));
      return {host:{position:hs.position,width:parseFloat(hs.width),opacity:parseFloat(hs.opacity)},filter:getComputedStyle(document.querySelector('.gloskin-ui1-commerce-handoff__goo')).filter,blobs:blobs.map(b=>{const s=getComputedStyle(b);return {name:s.animationName,duration:s.animationDuration,delay:s.animationDelay}}),content:{opacity:parseFloat(content.opacity),pointer:content.pointerEvents}};
    }''')

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    page.set_content(html)
    idle = state(page)
    assert idle['host']['opacity'] == 0 and idle['content']['opacity'] == 1
    assert len(idle['blobs']) == 4 and idle['filter'] != 'none'

    page.evaluate("document.documentElement.classList.add('gloskin-ui1-commerce-journey-leaving')")
    page.wait_for_timeout(220)
    leaving = state(page)
    assert leaving['host']['position'] == 'fixed' and 104 <= leaving['host']['width'] <= 122
    assert leaving['host']['opacity'] > 0 and leaving['content']['opacity'] == 0 and leaving['content']['pointer'] == 'none'
    assert all(b['name'] == 'gloskin-ui1-commerce-handoff-goo-dance' and b['duration'] == '3.5s' for b in leaving['blobs'])
    assert [b['delay'] for b in leaving['blobs']] == ['0s', '-0.8s', '-1.6s', '-2.4s']

    page.evaluate("document.documentElement.classList.remove('gloskin-ui1-commerce-journey-leaving')")
    page.wait_for_timeout(220)
    cleared = state(page)
    assert cleared['host']['opacity'] == 0 and cleared['content']['opacity'] == 1
    assert all(b['name'] == 'none' for b in cleared['blobs'])

    page.evaluate("document.documentElement.classList.add('gloskin-ui1-commerce-journey-arriving')")
    page.wait_for_timeout(220)
    arriving = state(page)
    assert arriving['host']['opacity'] > 0 and arriving['content']['opacity'] == 0
    page.evaluate("document.documentElement.classList.remove('gloskin-ui1-commerce-journey-arriving')")

    page.set_viewport_size({'width': 390, 'height': 800})
    page.evaluate("document.documentElement.classList.add('gloskin-ui1-commerce-journey-leaving')")
    assert 82 <= state(page)['host']['width'] <= 98
    page.evaluate("document.documentElement.classList.remove('gloskin-ui1-commerce-journey-leaving')")

    reduced = browser.new_page(viewport={'width': 390, 'height': 800}, reduced_motion='reduce')
    reduced.set_content(html)
    reduced.evaluate("document.documentElement.classList.add('gloskin-ui1-commerce-journey-arriving')")
    reduced_state = state(reduced)
    assert reduced_state['host']['opacity'] > 0 and reduced_state['content']['opacity'] == 0
    assert all(b['name'] == 'none' for b in reduced_state['blobs'])
    reduced.evaluate("document.documentElement.classList.remove('gloskin-ui1-commerce-journey-arriving')")
    assert state(reduced)['host']['opacity'] == 0 and state(reduced)['content']['opacity'] == 1
    reduced.close()
    browser.close()

print('commerce progress heading/journey loader presentation smoke passed')
