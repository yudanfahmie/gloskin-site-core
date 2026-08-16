#!/usr/bin/env python3
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('commerce success motion browser smoke: SKIPPED (playwright unavailable)')
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
CSS = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-commerce-polish.css').read_text(encoding='utf-8')
JS = (ROOT / 'plugin/gloskin-site-core/assets/js/gloskin-ui1-commerce-motion.js').read_text(encoding='utf-8')

HTML = '''<!doctype html><html class=""><head><meta charset="utf-8"></head><body class="gloskin-ui1">
<header class="gloskin-ui1-header">
  <div id="hidden-zone" style="opacity:0;pointer-events:none"><button id="hidden-cart" data-gloskin-cart-open><span data-gloskin-cart-count>1</span></button></div>
  <button id="visible-cart" data-gloskin-cart-open style="position:fixed;right:24px;top:24px;width:44px;height:44px"><span id="cart-badge" data-gloskin-cart-count style="display:block;width:18px;height:18px">1</span></button>
  <button id="visible-wish" data-gloskin-wishlist-open style="position:fixed;right:84px;top:24px;width:44px;height:44px"><span id="wish-badge" data-gloskin-wishlist-count style="display:block;width:18px;height:18px">1</span></button>
</header>
<main><button id="source" style="position:fixed;left:90px;top:520px;width:44px;height:44px">Add</button></main>
</body></html>'''


def require(value, message):
    if not value:
        raise AssertionError(message)

with sync_playwright() as p:
    executable = Path('/usr/bin/chromium')
    if not executable.exists():
        print('commerce success motion browser smoke: SKIPPED (chromium unavailable)')
        raise SystemExit(77)
    browser = p.chromium.launch(headless=True, executable_path=str(executable), args=['--no-sandbox'])

    page = browser.new_page(viewport={'width': 1024, 'height': 768})
    page.set_content(HTML)
    page.add_style_tag(content=CSS)
    page.add_script_tag(content=JS)

    chosen = page.evaluate("window.GloskinCommerceMotion.resolveVisibleTarget('cart').trigger.id")
    require(chosen == 'visible-cart', f'hidden header duplicate selected instead of visible target: {chosen}')

    start = page.evaluate("""() => {
      const source=document.getElementById('source');
      const ok=window.GloskinCommerceMotion.rememberSource('cart', source);
      source.remove();
      return ok && window.GloskinCommerceMotion.animateCommerceFlyToTarget('cart', null);
    }""")
    require(start is True, 'Cart fly did not start from captured real geometry')
    orb = page.locator('.gloskin-ui1-commerce-fly-target')
    require(orb.count() == 1, 'expected one temporary commerce orb')
    attrs = orb.evaluate("""el => ({aria:el.getAttribute('aria-hidden'), position:getComputedStyle(el).position, pointer:getComputedStyle(el).pointerEvents, z:getComputedStyle(el).zIndex})""")
    require(attrs['aria'] == 'true', f'fly orb is not decorative: {attrs}')
    require(attrs['position'] == 'fixed' and attrs['pointer'] == 'none', f'fly orb does not stay out of layout/input: {attrs}')
    require(int(attrs['z']) > 9998, f'fly orb would be covered by existing commerce sheet: {attrs}')
    page.wait_for_timeout(950)
    require(page.locator('.gloskin-ui1-commerce-fly-target').count() == 0, 'completed fly orb was not cleaned up')
    require(page.evaluate("window.GloskinCommerceMotion.activeParticleCount()") == 0, 'completed particle still tracked')

    reduced = browser.new_page(viewport={'width': 390, 'height': 844}, reduced_motion='reduce')
    reduced.set_content(HTML)
    reduced.add_style_tag(content=CSS)
    reduced.add_script_tag(content=JS)
    handled = reduced.evaluate("window.GloskinCommerceMotion.confirmedSuccess('wishlist', document.getElementById('source'))")
    require(handled is True, 'reduced-motion confirmed success was not safely handled')
    require(reduced.locator('.gloskin-ui1-commerce-fly-target').count() == 0, 'reduced motion must skip flying travel')

    reduced.close()
    page.close()
    browser.close()

print('commerce success motion browser smoke passed')
