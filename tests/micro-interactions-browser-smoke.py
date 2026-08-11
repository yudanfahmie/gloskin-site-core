#!/usr/bin/env python3
"""Focused browser smoke for micro-interaction presentation geometry/state feedback."""
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugin" / "gloskin-site-core"
CORE_BASE = (PLUGIN / "assets/css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CORE = (PLUGIN / "assets/css/gloskin-ui1-core.css").read_text(encoding="utf-8")
PRODUCTION = (PLUGIN / "assets/css/gloskin-ui1-production.css").read_text(encoding="utf-8")
JS = (PLUGIN / "assets/js/gloskin-ui1-core.js").read_text(encoding="utf-8")


def require(value, message):
    if not value:
        raise AssertionError(message)


HTML = """
<!doctype html><html><head><meta charset='utf-8'></head>
<body class='gloskin-ui1'>
<nav class='gloskin-ui1-nav gloskin-ui1-nav--desktop' aria-label='Main'>
  <span class='gloskin-ui1-nav__bubble' aria-hidden='true'></span>
  <ul class='gloskin-ui1-nav__list'>
    <li class='gloskin-ui1-nav__item'><div class='gloskin-ui1-nav__row'><a class='gloskin-ui1-nav__link' href='#a'>Tentang Gloskin</a></div></li>
    <li class='gloskin-ui1-nav__item'><div class='gloskin-ui1-nav__row'><a class='gloskin-ui1-nav__link' href='#b'>Perawatan</a></div></li>
    <li class='gloskin-ui1-nav__item'><div class='gloskin-ui1-nav__row'><a class='gloskin-ui1-nav__link' href='#c'>Skincare</a></div></li>
  </ul>
</nav>

<div style='display:flex;gap:12px;margin-top:40px'>
  <button id='cartUtility' class='gloskin-ui1-utility-btn gloskin-ui1-utility-btn--cart' data-gloskin-cart-open aria-label='Cart'>C<span class='gloskin-ui1-badge'>5</span></button>
  <button id='wishlistUtility' class='gloskin-ui1-utility-btn gloskin-ui1-utility-btn--wishlist' data-gloskin-wishlist-open aria-label='Wishlist'>W</button>
</div>
<button id='wishlistToggle' data-gloskin-wishlist-toggle='17' data-label-add='Save' data-label-remove='Remove'>heart</button>
<button id='fakeAdd'>add</button>
<div class='gloskin-ui1-sheet' data-gloskin-overlay='cart' aria-hidden='true' hidden><div class='gloskin-ui1-sheet__panel' role='dialog'><button data-gloskin-overlay-close>close</button></div></div>
<div class='gloskin-ui1-sheet' data-gloskin-overlay='wishlist' aria-hidden='true' hidden><div class='gloskin-ui1-sheet__panel' role='dialog'><button data-gloskin-overlay-close>close</button><div data-gloskin-wishlist-body></div></div></div>

<div class='gloskin-ui1-form'><input id='globalField'><button id='globalButton'>Global action</button></div>
<div class='gloskin-ui1-auth-forms'><input id='authField' class='input-text'><button id='authButton' class='button'>Auth action</button></div>
<button id='sheetClose' class='gloskin-ui1-sheet__close'>x</button>

<script>
window.gloskinData = {woo:true};
window.wc_cart_fragments_params = {};
window.__handlers = {};
window.__plays = 0;
window.Audio = function(uri) {
  this.src = uri; this.volume = 1; this.currentTime = 0;
  this.play = function(){ window.__plays += 1; return Promise.resolve(); };
};
(function(){
  function jq(target){
    return {
      length: target ? 1 : 0,
      attr: function(name,value){ if(target && target.setAttribute){ target.setAttribute(name,value); } return this; },
      on: function(name,fn){ window.__handlers[name] = fn; return this; },
      trigger: function(name,args){ if(window.__handlers[name]){ window.__handlers[name].apply(target,[{type:name}].concat(args || [])); } return this; }
    };
  }
  jq.fn = {};
  window.jQuery = jq;
})();
</script>
</body></html>
"""

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path="/usr/bin/chromium")
    page = browser.new_page(viewport={"width": 1280, "height": 900})
    page.set_content(HTML)
    page.add_style_tag(content=CORE_BASE + "\n" + CORE + "\n" + PRODUCTION)
    page.add_script_tag(content=JS)
    page.wait_for_timeout(80)

    kit = page.evaluate("""() => {
      const gf=getComputedStyle(document.querySelector('#globalField'));
      const af=getComputedStyle(document.querySelector('#authField'));
      const gb=getComputedStyle(document.querySelector('#globalButton'));
      const ab=getComputedStyle(document.querySelector('#authButton'));
      const close=getComputedStyle(document.querySelector('#sheetClose'));
      return {gh:gf.minHeight, ah:af.minHeight, gbg:gb.backgroundColor, abg:ab.backgroundColor, radius:close.borderRadius};
    }""")
    require(kit["gh"] == kit["ah"] == "52px", f"field kit height diverged: {kit}")
    require(kit["gbg"] == kit["abg"], f"primary action color diverged: {kit}")
    require(kit["radius"] == "999px", f"compact action skin missing: {kit}")

    before = page.locator(".gloskin-ui1-nav__link").evaluate_all("els => els.map(e => e.getBoundingClientRect().left)")
    page.locator(".gloskin-ui1-nav__row").nth(1).hover()
    page.wait_for_timeout(35)
    nav_mid = page.evaluate("""() => {
      const b=document.querySelector('.gloskin-ui1-nav__bubble').getBoundingClientRect();
      const l=document.querySelectorAll('.gloskin-ui1-nav__link')[1].getBoundingClientRect();
      const s=getComputedStyle(document.querySelector('.gloskin-ui1-nav__bubble'));
      return {dx:Math.abs((b.left+b.width/2)-(l.left+l.width/2)),dy:Math.abs((b.top+b.height/2)-(l.top+l.height/2)),prop:s.transitionProperty,transform:s.transform,opacity:s.opacity};
    }""")
    require(nav_mid["dx"] < 0.6 and nav_mid["dy"] < 0.6, f"nav bubble center traveled: {nav_mid}")
    require(nav_mid["prop"].replace(" ", "") == "transform,opacity", f"nav transition includes geometry: {nav_mid}")
    page.wait_for_timeout(220)
    nav_final = page.evaluate("""() => getComputedStyle(document.querySelector('.gloskin-ui1-nav__bubble')).transform""")
    require(nav_final in ("matrix(1, 0, 0, 1, 0, 0)", "none"), f"nav bubble did not settle to scale 1: {nav_final}")
    page.locator(".gloskin-ui1-nav__link").nth(2).focus()
    page.wait_for_timeout(210)
    focus_center = page.evaluate("""() => { const b=document.querySelector('.gloskin-ui1-nav__bubble').getBoundingClientRect(), l=document.querySelectorAll('.gloskin-ui1-nav__link')[2].getBoundingClientRect(); return Math.abs((b.left+b.width/2)-(l.left+l.width/2)); }""")
    require(focus_center < 0.6, f"keyboard focus bubble not centered: {focus_center}")
    after = page.locator(".gloskin-ui1-nav__link").evaluate_all("els => els.map(e => e.getBoundingClientRect().left)")
    require(before == after, f"nav interaction shifted layout: {before} -> {after}")

    # Five confirmed events exercise pulse restart and audio rate limiting.
    require(page.locator("#cartUtility .gloskin-ui1-badge").inner_text() == "5", "fixture cart count changed before success")
    page.evaluate("""() => { for(let i=0;i<5;i++){ window.jQuery(document.body).trigger('added_to_cart',[{},'hash',window.jQuery(document.querySelector('#fakeAdd'))]); } }""")
    page.wait_for_timeout(40)
    require(page.locator("#cartUtility").evaluate("e => e.classList.contains('is-success-pulse')"), "confirmed cart success did not pulse utility")
    require(page.locator("#cartUtility .gloskin-ui1-badge").inner_text() == "5", "feedback fabricated cart count")
    require(page.evaluate("window.__plays") == 1, "rapid confirmed cart successes bypassed audio cooldown")
    require(page.locator("#cartUtility").evaluate("e => e.className.split('is-success-pulse').length-1") == 1, "pulse class accumulated")
    page.wait_for_timeout(500)
    require(not page.locator("#cartUtility").evaluate("e => e.classList.contains('is-success-pulse')"), "pulse class remained permanently")

    page.locator("#wishlistToggle").click()
    page.wait_for_timeout(40)
    require(page.evaluate("localStorage.getItem('gloskin_wishlist')") == "[17]", "wishlist save was not persisted")
    require(page.locator("#wishlistUtility").evaluate("e => e.classList.contains('is-active')"), "wishlist header reflection missing after save")
    require(page.locator("#wishlistUtility").evaluate("e => e.classList.contains('is-success-pulse')"), "wishlist save did not pulse utility")
    require(page.evaluate("window.__plays") == 2, "wishlist save did not reuse success sound")
    page.wait_for_timeout(310)
    page.locator("#wishlistToggle").click()
    page.wait_for_timeout(40)
    require(page.evaluate("localStorage.getItem('gloskin_wishlist')") == "[]", "wishlist removal state incorrect")
    require(not page.locator("#wishlistUtility").evaluate("e => e.classList.contains('is-active')"), "wishlist header reflection stale after removal")
    require(page.evaluate("window.__plays") == 2, "wishlist removal incorrectly played celebratory sound")

    page.emulate_media(reduced_motion="reduce")
    page.locator(".gloskin-ui1-nav__row").nth(0).hover()
    page.wait_for_timeout(20)
    reduced = page.evaluate("""() => { const s=getComputedStyle(document.querySelector('.gloskin-ui1-nav__bubble')); return {opacity:s.opacity,duration:s.transitionDuration}; }""")
    require(reduced["opacity"] == "1", f"reduced-motion final bubble hidden: {reduced}")
    require(all(float(x.replace('s','') or 0) <= 0.01 for x in reduced["duration"].split(',')), f"reduced-motion transition remains: {reduced}")

    browser.close()

print("micro interactions browser smoke passed")
