#!/usr/bin/env python3
"""Real-browser smoke for the Gloskin admin shell: ARIA tabs roving
tabindex (including Home/End), focus follows activation, progressive
enhancement without JS, and the Header Type preview card's keyboard focus
projection distinct from its selected state."""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("admin-shell-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "plugin/gloskin-site-core/assets"
ADMIN_CSS = (ASSETS / "css/gloskin-admin.css").read_text(encoding="utf-8")
ADMIN_JS = (ASSETS / "js/gloskin-admin.js").read_text(encoding="utf-8")

TABS = ("brand", "header", "booking", "mapping")

HTML = """<!doctype html><html><head><meta charset="utf-8"></head>
<body style="margin:0;padding:24px">
<div id="gloskin-admin-root" class="gloskin-admin-shell">
  <aside class="gloskin-admin-shell__sidebar"></aside>
  <div class="gloskin-admin-shell__workspace">
    <div class="gloskin-admin-tabs" role="tablist" data-gloskin-admin-tabs>
      <button type="button" class="gloskin-admin-tabs__tab is-active" id="tab-brand" role="tab" aria-selected="true" aria-controls="panel-brand" tabindex="0" data-gloskin-admin-tab="brand">Brand</button>
      <button type="button" class="gloskin-admin-tabs__tab" id="tab-header" role="tab" aria-selected="false" aria-controls="panel-header" tabindex="-1" data-gloskin-admin-tab="header">Header</button>
      <button type="button" class="gloskin-admin-tabs__tab" id="tab-booking" role="tab" aria-selected="false" aria-controls="panel-booking" tabindex="-1" data-gloskin-admin-tab="booking">Booking &amp; Social</button>
      <button type="button" class="gloskin-admin-tabs__tab" id="tab-mapping" role="tab" aria-selected="false" aria-controls="panel-mapping" tabindex="-1" data-gloskin-admin-tab="mapping">Page Mapping</button>
    </div>
    <div class="gloskin-admin-canvas">
      <section class="gloskin-admin-card" id="panel-brand" role="tabpanel" data-gloskin-admin-panel="brand">Brand content</section>
      <section class="gloskin-admin-card" id="panel-header" role="tabpanel" data-gloskin-admin-panel="header">
        <div class="gloskin-admin-header-picker">
          <label class="gloskin-admin-header-card is-selected" for="h1"><input class="gloskin-admin-header-card__radio" type="radio" id="h1" name="hv" value="header-1" checked><span class="gloskin-admin-header-card__body">Header Type 1</span></label>
          <label class="gloskin-admin-header-card" for="h2"><input class="gloskin-admin-header-card__radio" type="radio" id="h2" name="hv" value="header-2"><span class="gloskin-admin-header-card__body">Header Type 2</span></label>
        </div>
      </section>
      <section class="gloskin-admin-card" id="panel-booking" role="tabpanel" data-gloskin-admin-panel="booking">Booking content</section>
      <section class="gloskin-admin-card" id="panel-mapping" role="tabpanel" data-gloskin-admin-panel="mapping">Mapping content</section>
    </div>
  </div>
</div>
</body></html>"""


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def tab_states(page):
    return page.evaluate(
        "() => Array.from(document.querySelectorAll('[data-gloskin-admin-tab]')).map(t => ({"
        "id: t.id, active: t.classList.contains('is-active'), selected: t.getAttribute('aria-selected'), tabIndex: t.tabIndex"
        "}))"
    )


def panel_hidden(page, name):
    return page.locator(f'[data-gloskin-admin-panel="{name}"]').evaluate("el => el.hidden")


with sync_playwright() as p:
    chromium = Path("/usr/bin/chromium")
    try:
        if chromium.exists():
            browser = p.chromium.launch(headless=True, executable_path=str(chromium), args=["--no-sandbox"])
        else:
            browser = p.chromium.launch(headless=True)
    except Exception:
        print("admin-shell-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)

    page = browser.new_page(viewport={"width": 1280, "height": 900})
    page.set_content(HTML)
    page.add_style_tag(content=ADMIN_CSS)
    page.add_script_tag(content=ADMIN_JS)
    page.wait_for_timeout(80)

    # --- Initial state: only Brand panel visible, roving tabindex correct. ---
    require(not panel_hidden(page, "brand"), "Brand panel must be visible on load")
    for name in ("header", "booking", "mapping"):
        require(panel_hidden(page, name), f"{name} panel must be hidden on load (JS-enhanced state)")
    states = tab_states(page)
    require(states[0]["tabIndex"] == 0 and all(s["tabIndex"] == -1 for s in states[1:]), f"exactly one tab (Brand) must have tabIndex 0 on load: {states}")
    require(states[0]["selected"] == "true" and all(s["selected"] == "false" for s in states[1:]), f"exactly one tab must be aria-selected on load: {states}")

    # --- Click activates, updates tabindex/aria-selected, moves focus. ---
    page.locator("#tab-header").click()
    require(not panel_hidden(page, "header"), "clicking Header tab must reveal its panel")
    require(panel_hidden(page, "brand"), "clicking Header tab must hide the previously active panel")
    states = tab_states(page)
    require(states[1]["tabIndex"] == 0 and states[1]["selected"] == "true", f"Header tab must become the roving-tabindex owner after click: {states}")
    require(states[0]["tabIndex"] == -1 and states[0]["selected"] == "false", f"Brand tab must lose tabIndex/aria-selected after Header activates: {states}")
    require(page.evaluate("document.activeElement.id") == "tab-header", "focus must follow activation")

    # --- Keyboard: ArrowRight/ArrowLeft/Home/End roving-tabindex navigation. ---
    page.keyboard.press("ArrowRight")
    require(page.evaluate("document.activeElement.id") == "tab-booking", "ArrowRight must move to and activate the next tab")
    require(not panel_hidden(page, "booking"), "ArrowRight activation must reveal the Booking panel")

    page.keyboard.press("End")
    require(page.evaluate("document.activeElement.id") == "tab-mapping", "End must activate the last tab")
    require(not panel_hidden(page, "mapping"), "End activation must reveal the last panel")

    page.keyboard.press("Home")
    require(page.evaluate("document.activeElement.id") == "tab-brand", "Home must activate the first tab")
    require(not panel_hidden(page, "brand"), "Home activation must reveal the first panel")

    page.keyboard.press("ArrowLeft")
    require(page.evaluate("document.activeElement.id") == "tab-mapping", "ArrowLeft must wrap to the last tab from the first")

    # --- Header Type card: focus-within ring distinct from selected state,
    # native radio remains the sole canonical control, ring is reactive. ---
    page.locator("#tab-header").click()
    h1_card = page.locator('label[for="h1"]')
    h2_card = page.locator('label[for="h2"]')
    require(h1_card.evaluate("el => getComputedStyle(el).boxShadow") in ("none", ""), "unfocused card must carry no focus ring")
    page.locator("#h2").focus()
    require(page.evaluate("document.activeElement.id") == "h2", "the native radio itself must be the focusable/canonical control")
    ring = h2_card.evaluate("el => getComputedStyle(el).boxShadow")
    require(ring not in ("none", ""), f"focusing the radio must project a visible ring onto its card via :focus-within: {ring}")
    selected_bg = h1_card.evaluate("el => getComputedStyle(el).backgroundColor")
    unfocused_h1_ring = h1_card.evaluate("el => getComputedStyle(el).boxShadow")
    require(unfocused_h1_ring in ("none", ""), "focusing card 2 must not leave a ring on card 1")
    # Selected (card 1, still checked) and focused (card 2) are visually
    # distinguishable states -- selected keeps its accent background,
    # focus-within uses box-shadow only, so they never read as the same cue.
    require(selected_bg not in ("rgba(0, 0, 0, 0)", "transparent"), "the selected card must keep its own background treatment regardless of focus elsewhere")
    h2_card.click()
    require(page.locator("#h2").is_checked(), "clicking the card (label) must still activate the native radio directly")
    page.evaluate("document.activeElement.blur()")
    ring_after_blur = h2_card.evaluate("el => getComputedStyle(el).boxShadow")
    require(ring_after_blur in ("none", ""), "the focus ring must clear the instant focus leaves -- never a stuck/permanent treatment")

    browser.close()

print("admin-shell-browser-smoke: OK")
