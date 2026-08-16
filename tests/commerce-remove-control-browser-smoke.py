#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-loader-system.css').read_text(encoding='utf-8')

BASELINE = r'''
:root{
 --gloskin-accent:rgb(174,42,75);
 --gloskin-accent-strong:rgb(130,25,53);
 --gloskin-accent-readable:rgb(174,42,75);
 --gloskin-inverse:rgb(255,255,255);
 --gloskin-surface-strong:rgb(244,240,242);
 --gloskin-muted:rgb(100,90,96);
 --gloskin-text:rgb(35,30,33);
 --gloskin-icon-remove:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='M3 3l10 10M13 3L3 13' stroke='black' stroke-width='2'/%3E%3C/svg%3E");
}
.gloskin-ui1-action-icon,.gloskin-ui1-cart-sheet__item-remove,.gloskin-ui1-wishlist-sheet__item-remove{display:grid;flex:0 0 auto;width:38px;height:38px;padding:0;border-radius:999px;place-items:center}
.gloskin-ui1-cart-sheet__item-remove,.gloskin-ui1-wishlist-sheet__item-remove{width:28px;height:28px;border:0;background:var(--gloskin-surface-strong);color:var(--gloskin-muted)}
.gloskin-ui1-action-icon--danger{width:38px;height:38px;background:var(--gloskin-accent);color:var(--gloskin-inverse)}
.gloskin-ui1-icon-remove{display:block;width:16px;height:16px;background-color:currentColor;-webkit-mask:var(--gloskin-icon-remove) center/contain no-repeat;mask:var(--gloskin-icon-remove) center/contain no-repeat}
.woocommerce a.remove{display:block;width:1em;height:1em;font-size:1.5em;line-height:1;color:rgb(130,0,0)!important;background:transparent;border:0;border-radius:100%;text-decoration:none}
.woocommerce a.remove:hover{color:rgb(255,255,255)!important;background:rgb(130,0,0)}
'''
HTML = '''<!doctype html><html><head><meta charset="utf-8"></head><body class="gloskin-ui1">
<section id="home">
<a href="/?remove_item=home" class="remove remove_from_cart_button gloskin-ui1-cart-sheet__item-remove gloskin-ui1-action-icon gloskin-ui1-action-icon--danger" data-product_id="123" data-cart_item_key="home" data-product_sku=""><span class="gloskin-ui1-icon-remove"></span></a>
</section>
<section id="shop" class="woocommerce">
<a href="/?remove_item=shop" class="remove remove_from_cart_button gloskin-ui1-cart-sheet__item-remove gloskin-ui1-action-icon gloskin-ui1-action-icon--danger" data-product_id="123" data-cart_item_key="shop" data-product_sku=""><span class="gloskin-ui1-icon-remove"></span></a>
</section>
<button id="wishlist" class="gloskin-ui1-wishlist-sheet__item-remove gloskin-ui1-action-icon gloskin-ui1-action-icon--danger" type="button" data-gloskin-wishlist-toggle="123"><span class="gloskin-ui1-icon-remove"></span></button>
</body></html>'''

def state(page, selector):
    return page.evaluate("""sel => {
      const a=document.querySelector(sel), icon=a.querySelector('.gloskin-ui1-icon-remove'), r=a.getBoundingClientRect(), cs=getComputedStyle(a), ic=getComputedStyle(icon);
      return {w:r.width,h:r.height,display:cs.display,bg:cs.backgroundColor,icon:ic.backgroundColor,font:cs.fontSize,line:cs.lineHeight}
    }""", selector)

with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    pg=b.new_page(viewport={'width':390,'height':844})
    pg.set_content(HTML)
    pg.add_style_tag(content=BASELINE+'\n'+CSS)
    pg.wait_for_timeout(180)
    selectors=['#home a','#shop a','#wishlist']
    states=[state(pg,s) for s in selectors]
    for st in states:
        assert abs(st['w']-28)<.25 and abs(st['h']-28)<.25, states
        assert st['display']=='grid', states
        assert st['bg']=='rgb(244, 240, 242)', states
        assert st['icon']=='rgb(130, 25, 53)', states
    for sel in selectors:
        pg.locator(sel).hover()
        pg.wait_for_timeout(180)
        st=state(pg,sel)
        assert st['bg']=='rgb(130, 25, 53)', st
        assert st['icon']=='rgb(255, 255, 255)', st
    attrs=pg.evaluate("""() => {
      const a=document.querySelector('#shop a');
      return {href:a.getAttribute('href'),pid:a.dataset.product_id,key:a.dataset.cart_item_key,classes:a.className}
    }""")
    assert attrs['href']=='/?remove_item=shop' and attrs['pid']=='123' and attrs['key']=='shop', attrs
    assert 'remove' in attrs['classes'].split() and 'remove_from_cart_button' in attrs['classes'].split(), attrs
    assert CSS.count('!important')==0
    b.close()
print('commerce remove control browser smoke passed')
