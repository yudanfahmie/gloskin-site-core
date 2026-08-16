#!/usr/bin/env python3
from pathlib import Path
import math

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('purchase actions/brand shimmer browser smoke: SKIPPED (playwright unavailable)')
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-brand-purchase-polish.css').read_text(encoding='utf-8')

BASE = r'''
:root{
  --gloskin-brand-champagne:#8F7953;
  --gloskin-inverse:#FBFBFA;
  --gloskin-accent:#B12E2F;
  --gloskin-accent-strong:#961F24;
  --gloskin-text:#2A232C;
  --gloskin-action-radius:8px;
}
*{box-sizing:border-box}
html,body{margin:0}
body{font:16px Arial,sans-serif}
.gloskin-ui1-commerce-native>div.product{display:grid}
.gloskin-ui1-purchase-dock-home{width:100%}
.gloskin-ui1-purchase-dock{background:var(--gloskin-accent);padding:12px;display:block}
.gloskin-ui1-purchase-dock__action{display:flex;align-items:center;gap:12px}
.gloskin-ui1-purchase-dock__submit{width:auto;min-width:160px;min-height:46px;padding:10px 18px;border:1px solid var(--gloskin-inverse);border-radius:var(--gloskin-action-radius);background:var(--gloskin-inverse);color:var(--gloskin-accent-strong);font:inherit;font-weight:700}
.gloskin-ui1-purchase-dock__buy-now{display:inline-flex;min-width:140px;min-height:46px;align-items:center;justify-content:center;padding:10px 16px;border:1px solid var(--gloskin-inverse);border-radius:var(--gloskin-action-radius);background:transparent;color:var(--gloskin-inverse);font:inherit;font-weight:700}
a.added_to_cart.wc-forward{display:inline-block;padding:.5em 0;color:#222;text-decoration:underline}
.gloskin-ui1-brand,.gloskin-ui1-compact-brand{display:inline-flex;align-items:center}
.gloskin-ui1-brand--footer{display:inline-block}
.gloskin-ui1-brand img,.gloskin-ui1-compact-brand img,.gloskin-ui1-brand--footer img{display:block;width:154px;height:50px}
.gloskin-ui1-compact-brand{max-width:0;overflow:hidden;opacity:0;pointer-events:none}
.gloskin-ui1-header__nav-row.is-compact-sticky .gloskin-ui1-compact-brand{max-width:180px;opacity:1;pointer-events:auto}
'''

SVG = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='154' height='50' viewBox='0 0 154 50'%3E%3Crect width='154' height='50' fill='transparent'/%3E%3Cpath d='M7 25h140' stroke='%23961F24' stroke-width='10'/%3E%3C/svg%3E"
HTML = f'''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="single-product gloskin-ui1">
<header data-gloskin-header="header-1"><a class="gloskin-ui1-brand" href="/"><img src="{SVG}" width="154" height="50" alt="Gloskin"></a></header>
<div class="gloskin-ui1-header__nav-row"><a class="gloskin-ui1-compact-brand" href="/" inert><img src="{SVG}" width="154" height="50" alt="Gloskin"></a></div>
<header data-gloskin-header="header-2"><a class="gloskin-ui1-brand" href="/"><img src="{SVG}" width="154" height="50" alt="Gloskin"></a></header>
<div class="gloskin-ui1-commerce-native"><div class="product"><div class="gloskin-ui1-purchase-dock-home"><div class="gloskin-ui1-purchase-dock"><form class="cart"><div class="gloskin-ui1-purchase-dock__action">
<button type="submit" class="single_add_to_cart_button gloskin-ui1-purchase-dock__submit">+ Keranjang ✓</button>
<a class="added_to_cart wc-forward" href="/cart/">View cart</a>
<button type="button" class="gloskin-ui1-purchase-dock__buy-now">Beli Sekarang</button>
</div></form></div></div></div></div>
<a class="added_to_cart wc-forward outside" href="/cart/">Outside View cart</a>
<footer><a class="gloskin-ui1-brand--footer" href="/"><img src="{SVG}" width="154" height="50" alt="Gloskin"></a></footer>
</body></html>'''


def require(cond, message):
    if not cond:
        raise AssertionError(message)


def rgb_tuple(value):
    value = value.strip()
    if value.startswith('rgba('):
        parts = value[5:-1].split(',')
        vals=[]
        for part in parts[:3]:
            p=part.strip(); vals.append(round(float(p[:-1])*2.55) if p.endswith('%') else round(float(p)))
        return tuple(vals)
    if value.startswith('rgb('):
        parts = value[4:-1].split(',')
        vals=[]
        for part in parts[:3]:
            p=part.strip(); vals.append(round(float(p[:-1])*2.55) if p.endswith('%') else round(float(p)))
        return tuple(vals)
    if value.startswith('color(srgb '):
        body=value[len('color(srgb '):-1].split('/')[0].strip()
        vals=[float(x) for x in body.split()[:3]]
        return tuple(round(max(0,min(1,x))*255) for x in vals)
    if value.startswith('oklab('):
        body=value[len('oklab('):-1].split('/')[0].strip()
        L,a,b=[float(x) for x in body.split()[:3]]
        l_ = L + 0.3963377774*a + 0.2158037573*b
        m_ = L - 0.1055613458*a - 0.0638541728*b
        s_ = L - 0.0894841775*a - 1.2914855480*b
        l,m,ss=l_**3,m_**3,s_**3
        lin=(4.0767416621*l - 3.3077115913*m + 0.2309699292*ss,
             -1.2684380046*l + 2.6097574011*m - 0.3413193965*ss,
             -0.0041960863*l - 0.7034186147*m + 1.707614701*ss)
        def gamma(x):
            x=max(0,min(1,x))
            return 12.92*x if x <= 0.0031308 else 1.055*(x**(1/2.4))-0.055
        return tuple(round(gamma(x)*255) for x in lin)
    raise ValueError(f'unexpected color {value}')

def luminance(rgb):
    out=[]
    for c in rgb:
        x=c/255.0
        out.append(x/12.92 if x <= 0.04045 else ((x+0.055)/1.055)**2.4)
    return 0.2126*out[0]+0.7152*out[1]+0.0722*out[2]


def contrast(a,b):
    la,lb=luminance(a),luminance(b)
    hi,lo=max(la,lb),min(la,lb)
    return (hi+0.05)/(lo+0.05)


def action_metrics(page, selector):
    return page.locator(selector).evaluate('''el => { const r=el.getBoundingClientRect(), s=getComputedStyle(el); return {w:r.width,h:r.height,display:s.display,bg:s.backgroundColor,color:s.color,border:s.borderTopColor,line:s.lineHeight,radius:s.borderRadius}; }''')


def pseudo(page, selector, pseudo_name='::after'):
    return page.locator(selector).evaluate('''(el,pseudoName) => {const s=getComputedStyle(el,pseudoName);return {name:s.animationName,duration:s.animationDuration,delay:s.animationDelay,pointer:s.pointerEvents,blend:s.mixBlendMode,opacity:s.opacity,position:s.position};}''', pseudo_name)

with sync_playwright() as p:
    executable=Path('/usr/bin/chromium')
    if not executable.exists():
        print('purchase actions/brand shimmer browser smoke: SKIPPED (chromium unavailable)')
        raise SystemExit(77)
    browser=p.chromium.launch(headless=True, executable_path=str(executable), args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':1440,'height':900})
    page.set_content(HTML)
    page.add_style_tag(content=BASE)

    # Snapshot intrinsic/click-host geometry before shimmer CSS; the new layer must not shift it.
    before=page.evaluate('''() => [...document.querySelectorAll('.gloskin-ui1-brand,.gloskin-ui1-brand--footer')].map(a=>{const ar=a.getBoundingClientRect(),i=a.querySelector('img').getBoundingClientRect();return [ar.width,ar.height,i.width,i.height]})''')
    page.add_style_tag(content=CSS)
    page.wait_for_timeout(220)
    after=page.evaluate('''() => [...document.querySelectorAll('.gloskin-ui1-brand,.gloskin-ui1-brand--footer')].map(a=>{const ar=a.getBoundingClientRect(),i=a.querySelector('img').getBoundingClientRect();return [ar.width,ar.height,i.width,i.height]})''')
    require(len(before)==len(after)==3, 'expected two main brand wrappers and one footer wrapper')
    for b,a in zip(before,after):
        require(all(abs(x-y)<0.25 for x,y in zip(b,a)), f'logo layout shifted: before={b} after={a}')

    selectors=['.gloskin-ui1-purchase-dock__submit','.gloskin-ui1-purchase-dock a.added_to_cart.wc-forward','.gloskin-ui1-purchase-dock__buy-now']
    desktop=[action_metrics(page,s) for s in selectors]
    for m in desktop:
        require(abs(m['h']-46)<0.25, f'desktop action not 46px: {m}')
        require(m['display']=='flex', f'action not inline-flex computed flex: {m}')
        require(m['line'] not in ('normal','0px'), f'action line-height unstable: {m}')

    # Hover/focus must not move geometry.
    view=page.locator('.gloskin-ui1-purchase-dock a.added_to_cart.wc-forward')
    buy=page.locator('.gloskin-ui1-purchase-dock__buy-now')
    view_before=action_metrics(page,'.gloskin-ui1-purchase-dock a.added_to_cart.wc-forward')
    view.hover(); view_hover=action_metrics(page,'.gloskin-ui1-purchase-dock a.added_to_cart.wc-forward')
    view.focus(); view_focus=action_metrics(page,'.gloskin-ui1-purchase-dock a.added_to_cart.wc-forward')
    for state in (view_hover,view_focus):
        require(abs(state['h']-view_before['h'])<0.25 and abs(state['w']-view_before['w'])<0.25, f'View Cart geometry shifted: {state}')
        require(contrast(rgb_tuple(state['bg']),rgb_tuple(state['color']))>=4.5, f'View Cart hover/focus contrast below 4.5: {state}')
    buy_before=action_metrics(page,'.gloskin-ui1-purchase-dock__buy-now')
    buy.hover(); buy_hover=action_metrics(page,'.gloskin-ui1-purchase-dock__buy-now')
    buy.focus(); buy_focus=action_metrics(page,'.gloskin-ui1-purchase-dock__buy-now')
    for state in (buy_hover,buy_focus):
        require(abs(state['h']-buy_before['h'])<0.25 and abs(state['w']-buy_before['w'])<0.25, f'Buy Now geometry shifted: {state}')
        require(contrast(rgb_tuple(state['bg']),rgb_tuple(state['color']))>=4.5, f'Buy Now hover/focus contrast below 4.5: {state}')

    require(view.get_attribute('href')=='/cart/', 'canonical View Cart href changed')
    outside=action_metrics(page,'a.added_to_cart.wc-forward.outside')
    require(abs(outside['h']-46)>0.25, f'View Cart rule leaked outside dock: {outside}')

    view_idle=view_before
    buy_idle=buy_before
    view_ratio=contrast(rgb_tuple(view_idle['bg']),rgb_tuple(view_idle['color']))
    buy_ratio=contrast(rgb_tuple(buy_idle['bg']),rgb_tuple(buy_idle['color']))
    require(view_ratio>=4.5, f'View Cart contrast below 4.5: {view_ratio:.2f}')
    require(buy_ratio>=4.5, f'Buy Now contrast below 4.5: {buy_ratio:.2f}')
    require(luminance(rgb_tuple(buy_idle['bg'])) < luminance(rgb_tuple(view_idle['bg'])), 'Buy Now must be the richer/darker warm action')

    # Mobile retains exact heights.
    page.set_viewport_size({'width':390,'height':844})
    for s in selectors:
        require(abs(action_metrics(page,s)['h']-46)<0.25, f'mobile action not 46px: {s}')

    # Canonical shimmer hosts and intentionally desynchronised cadence.
    main=pseudo(page,'[data-gloskin-header="header-1"] .gloskin-ui1-brand')
    h2=pseudo(page,'[data-gloskin-header="header-2"] .gloskin-ui1-brand')
    compact=pseudo(page,'.gloskin-ui1-compact-brand')
    footer=pseudo(page,'.gloskin-ui1-brand--footer')
    for state in (main,h2,footer):
        require(state['name']=='gloskin-ui1-brand-shimmer', f'main/footer shimmer missing: {state}')
        require(state['pointer']=='none' and state['blend']=='screen', f'shimmer layer interaction/blend wrong: {state}')
    require(main['duration']=='9.4s' and main['delay']=='-2.3s', f'main cadence wrong: {main}')
    require(h2['duration']=='9.4s' and h2['delay']=='-2.3s', f'Header 2 cadence wrong: {h2}')
    require(footer['duration']=='13.1s' and footer['delay']=='-4.9s', f'footer cadence wrong: {footer}')
    require(compact['name']=='none', f'hidden compact shimmer must sleep: {compact}')
    page.locator('.gloskin-ui1-header__nav-row').evaluate("el=>el.classList.add('is-compact-sticky')")
    compact_active=pseudo(page,'.gloskin-ui1-compact-brand')
    require(compact_active['name']=='gloskin-ui1-brand-shimmer' and compact_active['duration']=='11.2s' and compact_active['delay']=='-6.7s', f'compact active cadence wrong: {compact_active}')
    rim=pseudo(page,'[data-gloskin-header="header-1"] .gloskin-ui1-brand','::before')
    require(rim['pointer']=='none' and rim['blend']=='soft-light', f'rim interaction/blend wrong: {rim}')

    reduced=browser.new_page(viewport={'width':390,'height':844}, reduced_motion='reduce')
    reduced.set_content(HTML)
    reduced.add_style_tag(content=BASE+'\n'+CSS)
    reduced.wait_for_timeout(220)
    reduced.locator('.gloskin-ui1-header__nav-row').evaluate("el=>el.classList.add('is-compact-sticky')")
    for s in ['[data-gloskin-header="header-1"] .gloskin-ui1-brand','.gloskin-ui1-compact-brand','[data-gloskin-header="header-2"] .gloskin-ui1-brand','.gloskin-ui1-brand--footer']:
        require(pseudo(reduced,s)['name']=='none', f'reduced motion still animates {s}')
    reduced.close()
    browser.close()

print(f'purchase actions/brand shimmer browser smoke passed (contrast View Cart={view_ratio:.2f}:1, Buy Now={buy_ratio:.2f}:1)')
