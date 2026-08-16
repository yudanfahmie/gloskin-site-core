from pathlib import Path
from playwright.sync_api import sync_playwright

CSS = Path(__file__).resolve().parents[1] / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-loader-system.css'
css = CSS.read_text()
baseline = r"""
:root{
 --gloskin-accent:rgb(177,46,47);
 --gloskin-accent-strong:rgb(143,32,37);
 --gloskin-accent-readable:rgb(177,46,47);
 --gloskin-inverse:rgb(251,251,250);
 --gloskin-surface-strong:rgb(236,235,232);
 --gloskin-commerce-handoff-travel:34px;
 --gloskin-commerce-handoff-delay:0s;
}
*{box-sizing:border-box}
.gloskin-ui1-commerce-handoff{position:fixed;top:50%;left:50%;width:120px;height:120px;opacity:0;transform:translate(-50%,-50%)}
.gloskin-ui1-commerce-handoff__goo{position:absolute;inset:0;filter:url("#gloskin-ui1-commerce-handoff-goo")}
.gloskin-ui1-commerce-handoff__blob{position:absolute;top:50%;left:50%;width:40px;height:40px;border-radius:50%;background:var(--gloskin-accent);transform:translate(-50%,-50%)}
.gloskin-ui1-commerce-handoff__blob:nth-child(2){--gloskin-commerce-handoff-delay:-.8s}.gloskin-ui1-commerce-handoff__blob:nth-child(3){--gloskin-commerce-handoff-delay:-1.6s}.gloskin-ui1-commerce-handoff__blob:nth-child(4){--gloskin-commerce-handoff-delay:-2.4s}
html.gloskin-ui1-commerce-journey-leaving body.woocommerce-cart .gloskin-ui1-commerce-handoff{opacity:1}
html.gloskin-ui1-commerce-journey-leaving body.woocommerce-cart .gloskin-ui1-commerce-handoff__blob{animation:old-dance 3.5s infinite ease-in-out;animation-delay:var(--gloskin-commerce-handoff-delay,0s)}
@keyframes old-dance{to{transform:translate(-50%,-50%)}}
.gloskin-ui1-hero-bg-video__loader{position:absolute;inset:0;display:grid;place-items:center;opacity:1}
.gloskin-ui1-hero-bg-video__loader-dot{width:34px;height:34px;border:3px solid #ddd;border-top-color:var(--gloskin-accent);border-radius:999px;animation:old-spin 900ms linear infinite}
.gloskin-ui1-hero--video-only.is-video-ready .gloskin-ui1-hero-bg-video__loader,.gloskin-ui1-hero--video-only.is-video-failed .gloskin-ui1-hero-bg-video__loader{opacity:0}
@keyframes old-spin{to{transform:rotate(360deg)}}
.gloskin-ui1-quickadd__loading::before{content:"";width:18px;height:18px;border:2px solid currentColor;border-right-color:transparent;border-radius:999px;animation:old-spin 650ms linear infinite}
"""
html = f"""<!doctype html><html class="gloskin-ui1-commerce-journey-leaving"><head><style>{baseline}\n{css}</style></head>
<body class="gloskin-ui1 woocommerce-cart">
<svg class="gloskin-ui1-goo-loader-defs" width="0" height="0"><defs><filter id="gloskin-ui1-commerce-handoff-goo" x="-80%" y="-80%" width="260%" height="260%"><feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur"/><feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -10" result="goo"/></filter></defs></svg>
<div class="gloskin-ui1-commerce-handoff"><div class="gloskin-ui1-commerce-handoff__goo"><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span></div></div>
<section class="gloskin-ui1-hero gloskin-ui1-hero--video-only is-video-preparing"><div class="gloskin-ui1-hero-bg-video__loader"><span class="gloskin-ui1-hero-bg-video__loader-dot"></span></div></section>
<div class="gloskin-ui1-quickadd__loading"><span>Memuat produk…</span></div>
</body></html>"""

def names(page):
    return page.evaluate("""() => {
      const c=[...document.querySelectorAll('.gloskin-ui1-commerce-handoff__blob')].map(x=>getComputedStyle(x).animationName);
      const h=document.querySelector('.gloskin-ui1-hero-bg-video__loader'), d=document.querySelector('.gloskin-ui1-hero-bg-video__loader-dot');
      const q=document.querySelector('.gloskin-ui1-quickadd__loading'), s=q.querySelector('span');
      return {
        c,
        h:[getComputedStyle(h,'::before').animationName,getComputedStyle(h,'::after').animationName,getComputedStyle(d,'::before').animationName,getComputedStyle(d,'::after').animationName],
        q:[getComputedStyle(q,'::before').animationName,getComputedStyle(q,'::after').animationName,getComputedStyle(s,'::before').animationName,getComputedStyle(s,'::after').animationName],
        filters:[getComputedStyle(h).filter,getComputedStyle(q).filter],
        sizes:[parseFloat(getComputedStyle(h).width),parseFloat(getComputedStyle(q).width)]
      }
    }""")

with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    pg=b.new_page(viewport={'width':1440,'height':900})
    pg.set_content(html)
    st=names(pg)
    assert all(x=='gloskin-ui1-goo-loader-dance' for x in st['c']+st['h']+st['q']), st
    assert all(x!='none' for x in st['filters']), st
    assert 96<=st['sizes'][0]<=112 and 72<=st['sizes'][1]<=88, st
    # hero release stops all hero animations
    pg.evaluate("document.querySelector('.gloskin-ui1-hero').className='gloskin-ui1-hero gloskin-ui1-hero--video-only is-video-ready'")
    st2=names(pg)
    assert all(x=='none' for x in st2['h']), st2
    red=b.new_page(viewport={'width':390,'height':844},reduced_motion='reduce')
    red.set_content(html)
    rs=names(red)
    assert all(x=='none' for x in rs['c']+rs['h']+rs['q']), rs
    b.close()
print("reusable loader smoke passed")
