#!/usr/bin/env python3
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('commerce remove control browser smoke: SKIPPED (playwright unavailable)')
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-commerce-polish.css').read_text(encoding='utf-8')

# Representative real Gloskin predecessor rule from gloskin-ui1-core.css plus
# representative Woo .woocommerce a.remove geometry/color competition. The
# file under test itself is the real, final commerce-polish.css.
BASELINE = r'''
:root{
  --gloskin-accent:rgb(174,42,75);
  --gloskin-accent-strong:rgb(130,25,53);
  --gloskin-inverse:rgb(255,255,255);
  --gloskin-field-border:rgb(220,215,217);
  --gloskin-surface:rgb(250,248,249);
  --gloskin-surface-strong:rgb(244,240,242);
  --gloskin-muted:rgb(100,90,96);
  --gloskin-text:rgb(35,30,33);
  --gloskin-icon-remove:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='M3 3l10 10M13 3L3 13' stroke='black' stroke-width='2'/%3E%3C/svg%3E");
}
.gloskin-ui1-action-icon,.gloskin-ui1-cart-sheet__item-remove{display:grid;flex:0 0 auto;width:38px;height:38px;padding:0;border:1px solid var(--gloskin-field-border);border-radius:999px;background:transparent;color:var(--gloskin-text);place-items:center}
.gloskin-ui1-cart-sheet__item-remove{width:28px;height:28px;border:0;background:var(--gloskin-surface-strong);color:var(--gloskin-muted)}
.gloskin-ui1-icon-remove{display:block;width:16px;height:16px;background-color:currentColor;-webkit-mask:var(--gloskin-icon-remove) center/contain no-repeat;mask:var(--gloskin-icon-remove) center/contain no-repeat}
.woocommerce a.remove{display:block;width:1em;height:1em;font-size:1.5em;line-height:1;color:rgb(130,0,0)!important;background:transparent;border:0;border-radius:100%;text-decoration:none}
.woocommerce a.remove:hover{color:rgb(255,255,255)!important;background:rgb(130,0,0)}
'''

HTML = '''<!doctype html><html><head><meta charset="utf-8"></head>
<body class="gloskin-ui1"><div class="woocommerce">
<ul class="gloskin-ui1-cart-sheet__list"><li class="gloskin-ui1-cart-sheet__item">
<span></span><span></span>
<a href="/?remove_item=abc" class="remove remove_from_cart_button gloskin-ui1-cart-sheet__item-remove" aria-label="Hapus Produk" data-product_id="123" data-cart_item_key="abc" data-product_sku="SKU-1"><span class="gloskin-ui1-icon-remove" aria-hidden="true"></span></a>
</li></ul></div></body></html>'''


def require(condition, message):
    if not condition:
        raise AssertionError(message)

with sync_playwright() as p:
    executable = '/usr/bin/chromium'
    if not Path(executable).exists():
        print('commerce remove control browser smoke: SKIPPED (chromium unavailable)')
        raise SystemExit(77)
    browser = p.chromium.launch(headless=True, executable_path=executable, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 390, 'height': 844})
    page.set_content(HTML)
    page.add_style_tag(content=BASELINE + '\n' + CSS)
    link = page.locator('.gloskin-ui1-cart-sheet__item-remove')

    def metrics():
        return page.evaluate('''() => {
          const a=document.querySelector('.gloskin-ui1-cart-sheet__item-remove');
          const icon=a.querySelector('.gloskin-ui1-icon-remove');
          const r=a.getBoundingClientRect();
          const cs=getComputedStyle(a); const ic=getComputedStyle(icon);
          return {width:r.width,height:r.height,display:cs.display,bg:cs.backgroundColor,icon:ic.backgroundColor,fontSize:cs.fontSize,lineHeight:cs.lineHeight};
        }''')

    idle = metrics()
    require(abs(idle['width'] - 38) < .25 and abs(idle['height'] - 38) < .25, f'idle geometry not 38x38: {idle}')
    require(idle['display'] == 'grid', f'idle display not grid: {idle}')
    require(idle['bg'] == 'rgb(174, 42, 75)', f'idle circle not branded red: {idle}')
    require(idle['icon'] == 'rgb(255, 255, 255)', f'idle X not inverse white: {idle}')

    link.hover()
    hover = metrics()
    require(abs(hover['width'] - 38) < .25 and abs(hover['height'] - 38) < .25, f'hover geometry moved: {hover}')
    require(hover['bg'] == 'rgb(130, 25, 53)', f'hover circle not branded strong red: {hover}')
    require(hover['icon'] == 'rgb(255, 255, 255)', f'hover X not inverse white: {hover}')

    link.focus()
    focus = metrics()
    require(abs(focus['width'] - 38) < .25 and abs(focus['height'] - 38) < .25, f'focus geometry moved: {focus}')
    require(focus['icon'] == 'rgb(255, 255, 255)', f'focus X not inverse white: {focus}')

    attrs = page.evaluate('''() => {const a=document.querySelector('.gloskin-ui1-cart-sheet__item-remove');return {classes:a.className,href:a.getAttribute('href'),pid:a.dataset.product_id,key:a.dataset.cart_item_key,sku:a.dataset.product_sku,text:a.textContent};}''')
    require('remove_from_cart_button' in attrs['classes'].split(), f'native Woo remove class missing: {attrs}')
    require('remove' in attrs['classes'].split(), f'native Woo remove class missing: {attrs}')
    require(attrs['href'] == '/?remove_item=abc' and attrs['pid'] == '123' and attrs['key'] == 'abc' and attrs['sku'] == 'SKU-1', f'native remove attributes changed: {attrs}')
    require('×' not in attrs['text'], f'literal multiplication glyph leaked into control: {attrs}')
    require(page.locator('.gloskin-ui1-cart-sheet__item-remove .gloskin-ui1-icon-remove').count() == 1, 'expected one shared remove icon primitive')
    browser.close()

print('commerce remove control browser smoke passed')
