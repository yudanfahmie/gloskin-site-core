#!/usr/bin/env python3
from pathlib import Path
import base64
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-commerce-polish.css').resolve()
FONT = (ROOT / 'plugin/gloskin-site-core/assets/fonts/Marcellus-Regular.woff2').resolve()

font_face = ''
if FONT.is_file():
    encoded = base64.b64encode(FONT.read_bytes()).decode('ascii')
    font_face = f"@font-face{{font-family:Marcellus;src:url(data:font/woff2;base64,{encoded}) format('woff2');font-weight:400;font-style:normal;font-display:swap}}"

html = f'''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>{font_face}
:root{{--gloskin-font-heading:"Marcellus","Times New Roman",serif;--gloskin-container:1180px;--gloskin-gutter:clamp(18px,4vw,40px);--gloskin-text:#2A232C;--gloskin-border:#DDD7D3;--gloskin-accent:#B12E2F;--gloskin-accent-readable:#B12E2F;}}
*{{box-sizing:border-box}}html,body{{margin:0}}body{{background:#FBFBFA;color:var(--gloskin-text)}}
.gloskin-ui1-container{{width:min(calc(100% - (2 * var(--gloskin-gutter))),var(--gloskin-container));margin-inline:auto}}
.gloskin-ui1 :focus-visible{{outline:3px solid var(--gloskin-accent-readable);outline-offset:3px;border-radius:3px}}
{CSS.read_text()}</style></head><body class="gloskin-ui1 woocommerce-cart">
<header class="gloskin-ui1-commerce-heading gloskin-ui1-commerce-heading--journey"><div class="gloskin-ui1-container">
<nav class="gloskin-ui1-commerce-progress gloskin-ui1-commerce-progress--cart" aria-label="Tahapan belanja" data-gloskin-commerce-progress>
<h1 class="gloskin-ui1-commerce-progress__step gloskin-ui1-commerce-progress__step--cart is-active" aria-current="page">Keranjang</h1>
<span class="gloskin-ui1-commerce-progress__connector" aria-hidden="true"><span class="gloskin-ui1-commerce-progress__connector-progress"></span></span>
<a class="gloskin-ui1-commerce-progress__step gloskin-ui1-commerce-progress__step--checkout" href="#checkout">Checkout</a>
</nav></div></header>
<div class="gloskin-ui1-commerce-native"><p>Commerce content</p></div>
</body></html>'''

widths = [1440, 1024, 768, 430, 390, 375, 320]
with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    page.set_content(html, wait_until='load')
    page.evaluate('document.fonts && document.fonts.ready')
    desktop_size = None
    mobile_size = None
    for width in widths:
        page.set_viewport_size({'width': width, 'height': 800})
        page.wait_for_timeout(30)
        m = page.evaluate('''() => {
          const nav=document.querySelector('.gloskin-ui1-commerce-progress');
          const left=document.querySelector('.gloskin-ui1-commerce-progress__step--cart');
          const line=document.querySelector('.gloskin-ui1-commerce-progress__connector');
          const right=document.querySelector('.gloskin-ui1-commerce-progress__step--checkout');
          const c=nav.parentElement.getBoundingClientRect(), n=nav.getBoundingClientRect(), l=left.getBoundingClientRect(), x=line.getBoundingClientRect(), r=right.getBoundingClientRect();
          const cs=getComputedStyle(nav), ls=getComputedStyle(left), rs=getComputedStyle(right);
          return {bodyScroll:document.documentElement.scrollWidth, inner:innerWidth, c:{left:c.left,right:c.right,width:c.width}, n:{left:n.left,right:n.right,width:n.width}, l:{left:l.left,right:l.right,width:l.width,height:l.height}, x:{left:x.left,right:x.right,width:x.width}, r:{left:r.left,right:r.right,width:r.width,height:r.height}, gap:parseFloat(cs.columnGap), font:parseFloat(ls.fontSize), whiteL:ls.whiteSpace, whiteR:rs.whiteSpace, lineMin:getComputedStyle(line).minWidth};
        }''')
        assert m['bodyScroll'] <= m['inner'], f'{width}px horizontal overflow: {m}'
        assert abs(m['l']['left'] - m['c']['left']) < 1.5, f'{width}px Cart not pinned left: {m}'
        assert abs(m['r']['right'] - m['c']['right']) < 1.5, f'{width}px Checkout not pinned right: {m}'
        assert m['whiteL'] == 'nowrap' and m['whiteR'] == 'nowrap', f'{width}px title wrapping allowed'
        assert m['l']['right'] < m['x']['left'] and m['x']['right'] < m['r']['left'], f'{width}px collision: {m}'
        expected = m['n']['width'] - m['l']['width'] - m['r']['width'] - (2 * m['gap'])
        assert abs(m['x']['width'] - expected) < 2, f'{width}px connector not auto-filled: {m}'
        assert m['x']['width'] >= 14, f'{width}px connector not useful: {m}'
        if width == 1440: desktop_size = m['font']
        if width == 320: mobile_size = m['font']

    assert desktop_size and mobile_size and desktop_size > mobile_size * 1.5, (desktop_size, mobile_size)

    page.set_viewport_size({'width': 320, 'height': 800})
    page.locator('.gloskin-ui1-commerce-progress__step--checkout').focus()
    focused = page.evaluate('''() => { const r=document.activeElement.getBoundingClientRect(); return {left:r.left,right:r.right,width:innerWidth,minHeight:parseFloat(getComputedStyle(document.activeElement).minHeight)} }''')
    assert focused['left'] >= 6 and focused['right'] <= focused['width'] - 6, focused
    assert focused['minHeight'] >= 44, focused

    reduced = browser.new_page(viewport={'width': 390, 'height': 800}, reduced_motion='reduce')
    reduced.set_content(html, wait_until='load')
    reduced_state = reduced.evaluate('''() => ({transition:getComputedStyle(document.querySelector('.gloskin-ui1-commerce-progress__step--checkout')).transitionDuration})''')
    assert reduced_state['transition'] == '0s', reduced_state
    reduced.close()
    browser.close()

print('commerce progress heading browser smoke passed')
