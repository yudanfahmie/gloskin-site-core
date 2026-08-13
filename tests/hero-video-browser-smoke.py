#!/usr/bin/env python3
"""Real-browser smoke for the Home hero video poster/facade: zero iframes
in the initial render, stable geometry (no CLS), progressive enhancement
into exactly one iframe once visible/idle, the reduced-motion gate, the
explicit Play control, and the invalid/disabled fallback -- all against
the actual compiled CSS/JS, never a hand-simulated approximation.

The "zero iframes before enhancement" proof is made deterministic (not a
timing race against the real browser's IntersectionObserver callback
schedule) by placing the hero below a tall off-screen spacer: it is
genuinely NOT intersecting until the page is scrolled, so the observer
cannot have fired yet -- then scrolling it into view is what triggers
enhancement, exactly like a real visitor scrolling to the hero."""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("hero-video-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "plugin/gloskin-site-core/assets"
CSS_FONTS = (ASSETS / "css/gloskin-ui1-fonts.css").read_text(encoding="utf-8")
CSS_BASE = (ASSETS / "css/gloskin-ui1-core-base.css").read_text(encoding="utf-8")
CSS_CORE = (ASSETS / "css/gloskin-ui1-core.css").read_text(encoding="utf-8")
CSS_PRODUCTION = (ASSETS / "css/gloskin-ui1-production.css").read_text(encoding="utf-8")
JS_CORE = (ASSETS / "js/gloskin-ui1-core.js").read_text(encoding="utf-8")

VIDEO_ID = "otej7WLdPh0"

# Byte-for-byte the same shape gloskin_ui1_render_hero_video() emits (see
# plugin/gloskin-site-core/templates/parts/template-helpers.php).
FACADE = f"""
<section class="gloskin-ui1-hero">
  <div class="gloskin-ui1-container gloskin-ui1-hero__grid">
    <div class="gloskin-ui1-hero__content">
      <p class="gloskin-ui1-eyebrow">Gloskin</p>
      <h1 class="gloskin-ui1-hero__title">Perawatan kulit</h1>
      <p class="gloskin-ui1-hero__copy">Copy</p>
    </div>
    <div class="gloskin-ui1-hero__media">
      <div class="gloskin-ui1-hero-video gloskin-ui1-hero__image" data-gloskin-hero-video data-video-id="{VIDEO_ID}" data-video-title="Gloskin hero video">
        <img class="gloskin-ui1-hero-video__poster" src="https://i.ytimg.com/vi/{VIDEO_ID}/maxresdefault.jpg" data-gloskin-hero-video-fallback="https://i.ytimg.com/vi/{VIDEO_ID}/hqdefault.jpg" alt="" width="1280" height="720" fetchpriority="high" decoding="async" />
        <button type="button" class="gloskin-ui1-hero-video__play" data-gloskin-hero-video-play aria-label="Play hero video">
          <svg width="22" height="22" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M6.5 4.5v11l9-5.5-9-5.5z" fill="currentColor"/></svg>
        </button>
      </div>
    </div>
  </div>
</section>
"""

FALLBACK_MEDIA = """
<section class="gloskin-ui1-hero">
  <div class="gloskin-ui1-container gloskin-ui1-hero__grid">
    <div class="gloskin-ui1-hero__content">
      <h1 class="gloskin-ui1-hero__title">Perawatan kulit</h1>
    </div>
    <div class="gloskin-ui1-hero__media">
      <img class="gloskin-ui1-hero__image gloskin-ui1-hero__image--editorial" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1200' height='1500'/%3E" width="1200" height="1500" alt="" aria-hidden="true" decoding="async" loading="eager" />
    </div>
  </div>
</section>
"""

SPACER = '<div style="height:3000px">spacer</div>'


def page_html(body, spaced=False):
    content = (SPACER + body) if spaced else body
    return f"""<!doctype html><html><head><meta charset="utf-8"></head><body class="gloskin-ui1">
<main id="gloskin-main">{content}</main>
</body></html>"""


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def load(page, body, spaced=False, reduced_motion=None, save_data=False):
    page.route("http://gloskin.test/**", lambda route: route.fulfill(status=200, content_type="text/html", body="<!doctype html>"))
    # Never make a real request to YouTube -- stub the embed origin.
    page.route("https://www.youtube-nocookie.com/**", lambda route: route.fulfill(status=200, content_type="text/html", body="<!doctype html><body>stub player</body>"))
    if reduced_motion:
        page.emulate_media(reduced_motion=reduced_motion)
    if save_data:
        page.add_init_script("Object.defineProperty(navigator, 'connection', {value: {saveData: true}, configurable: true});")
    page.goto("http://gloskin.test/hero-fixture", wait_until="domcontentloaded")
    page.set_content(page_html(body, spaced=spaced))
    page.add_style_tag(content=CSS_FONTS + "\n" + CSS_BASE + "\n" + CSS_CORE + "\n" + CSS_PRODUCTION)
    page.add_script_tag(content=JS_CORE)
    page.wait_for_timeout(80)


with sync_playwright() as p:
    chromium = Path("/usr/bin/chromium")
    try:
        if chromium.exists():
            browser = p.chromium.launch(headless=True, executable_path=str(chromium), args=["--no-sandbox"])
        else:
            browser = p.chromium.launch(headless=True)
    except Exception:
        print("hero-video-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)

    # --- Enabled facade, off-screen initially: deterministic pre-
    # enhancement state, then scroll-triggered progressive enhancement ---
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    console_errors = []
    page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)
    page.on("pageerror", lambda exc: console_errors.append(str(exc)))
    load(page, FACADE, spaced=True)

    require(page.locator("[data-gloskin-hero-video]").count() == 1, "exactly one hero video facade must render")
    require(page.locator("[data-gloskin-hero-video] iframe").count() == 0, "INITIAL IFRAME COUNT must be 0 while the hero is off-screen")
    require(page.locator(".gloskin-ui1-hero-video__play").count() == 1, "exactly one real Play <button> must exist")
    require(page.eval_on_selector(".gloskin-ui1-hero-video__play", "el => el.tagName") == "BUTTON", "Play control must be a real <button>, not a div")

    box_before = page.eval_on_selector(".gloskin-ui1-hero-video", "el => { const r = el.getBoundingClientRect(); return {w: Math.round(r.width), h: Math.round(r.height)}; }")
    require(box_before["w"] > 0 and box_before["h"] > 0, f"hero video box must have real stable geometry before enhancement: {box_before}")

    # Scroll the hero into view -- this is what should trigger enhancement.
    page.eval_on_selector(".gloskin-ui1-hero-video", "el => el.scrollIntoView()")
    page.wait_for_function("document.querySelector('[data-gloskin-hero-video]').classList.contains('is-loaded')", timeout=3000)
    require(page.locator("[data-gloskin-hero-video] iframe").count() == 1, "POST-ENHANCEMENT IFRAME COUNT must be exactly 1")
    iframe_src = page.eval_on_selector("[data-gloskin-hero-video] iframe", "el => el.src")
    require(iframe_src.startswith(f"https://www.youtube-nocookie.com/embed/{VIDEO_ID}"), f"iframe must use youtube-nocookie.com with the exact video id: {iframe_src}")
    require("autoplay=1" in iframe_src and "mute=1" in iframe_src and "controls=0" in iframe_src, f"iframe src must request muted autoplay, no controls: {iframe_src}")
    iframe_title = page.eval_on_selector("[data-gloskin-hero-video] iframe", "el => el.title")
    require(bool(iframe_title), "iframe must carry a meaningful title")

    box_after = page.eval_on_selector(".gloskin-ui1-hero-video", "el => { const r = el.getBoundingClientRect(); return {w: Math.round(r.width), h: Math.round(r.height)}; }")
    require(box_after == box_before, f"HERO CLS: geometry must not shift after enhancement: before={box_before} after={box_after}")

    # Idempotency: clicking Play after auto-enhancement must never create a
    # second iframe, and no duplicate youtube-nocookie requests must fire.
    yt_requests = []
    page.on("request", lambda req: yt_requests.append(req.url) if "youtube-nocookie.com" in req.url else None)
    page.locator(".gloskin-ui1-hero-video__play").click(force=True)
    page.wait_for_timeout(150)
    require(page.locator("[data-gloskin-hero-video] iframe").count() == 1, "a Play click after auto-enhancement must never create a second iframe")
    require(len(yt_requests) <= 1, f"must not fire duplicate youtube-nocookie requests after the one iframe already exists: {yt_requests}")

    require(console_errors == [], f"console must be free of errors: {console_errors}")
    page.close()

    # --- Reduced motion: no auto-instantiation even when visible, Play
    # still fully works ---
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    load(page, FACADE, spaced=False, reduced_motion="reduce")
    page.wait_for_timeout(400)
    require(page.locator("[data-gloskin-hero-video] iframe").count() == 0, "REDUCED MOTION: must never auto-instantiate the iframe even while visible")
    require(page.locator(".gloskin-ui1-hero-video__poster").is_visible(), "REDUCED MOTION: poster must remain the visible facade")
    page.locator(".gloskin-ui1-hero-video__play").click()
    page.wait_for_function("document.querySelector('[data-gloskin-hero-video]').classList.contains('is-loaded')", timeout=3000)
    require(page.locator("[data-gloskin-hero-video] iframe").count() == 1, "REDUCED MOTION: an explicit Play click must still work")
    page.close()

    # --- Save-data: same gate as reduced motion ---
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    load(page, FACADE, spaced=False, save_data=True)
    page.wait_for_timeout(400)
    require(page.locator("[data-gloskin-hero-video] iframe").count() == 0, "SAVE-DATA: must never auto-instantiate the iframe even while visible")
    page.close()

    # --- Disabled/fallback: existing non-video hero media renders cleanly,
    # never a broken facade, no console errors. ---
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    fallback_errors = []
    page.on("console", lambda msg: fallback_errors.append(msg.text) if msg.type == "error" else None)
    load(page, FALLBACK_MEDIA)
    require(page.locator("[data-gloskin-hero-video]").count() == 0, "disabled hero video must not render the facade at all")
    require(page.locator(".gloskin-ui1-hero__image--editorial").count() == 1, "disabled hero video must render the existing non-video hero media")
    require(fallback_errors == [], f"disabled fallback must be console-clean: {fallback_errors}")
    page.close()

    # --- Mobile geometry: facade still fills the hero media slot cleanly ---
    page = browser.new_page(viewport={"width": 390, "height": 844})
    load(page, FACADE)
    require(page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), "hero video must not cause horizontal overflow at 390px")
    mobile_box = page.eval_on_selector(".gloskin-ui1-hero-video", "el => { const r = el.getBoundingClientRect(); return {w: Math.round(r.width), h: Math.round(r.height)}; }")
    require(mobile_box["w"] > 0 and mobile_box["h"] > 0, f"MOBILE HERO: must have real stable geometry at 390px: {mobile_box}")
    page.close()

    browser.close()

print("hero-video-browser-smoke: OK")
