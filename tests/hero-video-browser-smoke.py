#!/usr/bin/env python3
"""Real Chromium fixture for the visible Home campaign + native Media Library video enhancement."""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("hero-video-browser-smoke: SKIPPED (playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "plugin/gloskin-site-core/assets"
CSS = "\n".join(
    (ASSETS / name).read_text(encoding="utf-8")
    for name in [
        "css/gloskin-ui1-core-base.css",
        "css/gloskin-ui1-core.css",
        "css/gloskin-ui1-production.css",
        "css/gloskin-ui1-prototype-refresh.css",
    ]
)
JS = (ASSETS / "js/gloskin-ui1-core.js").read_text(encoding="utf-8")

MARKUP = """<!doctype html><html><head><meta charset="utf-8"></head><body class="gloskin-ui1">
<main class="gloskin-ui1-main">
  <section class="gloskin-ui1-hero gloskin-ui1-hero--campaign is-video-preparing" data-gloskin-hero-bg-video-root>
    <div class="gloskin-ui1-container gloskin-ui1-hero__grid">
      <div class="gloskin-ui1-hero__content">
        <p class="gloskin-ui1-eyebrow">Gloskin</p>
        <h1 class="gloskin-ui1-hero__title">Perawatan kulit</h1>
        <p class="gloskin-ui1-hero__copy">Konsultasi dan perawatan yang disesuaikan.</p>
        <p class="gloskin-ui1-hero__actions"><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="#next">Jelajahi Perawatan</a></p>
      </div>
      <div class="gloskin-ui1-hero__media gloskin-ui1-hero__media--campaign">
        <img class="gloskin-ui1-hero__image gloskin-ui1-hero__media-fallback" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="">
        <div class="gloskin-ui1-hero-bg-video" data-gloskin-hero-bg-video-wrap>
          <video class="gloskin-ui1-hero-bg-video__media" data-gloskin-hero-bg-video muted autoplay loop playsinline preload="auto" aria-hidden="true" tabindex="-1">
            <source src="data:video/mp4;base64," type="video/mp4">
          </video>
          <div class="gloskin-ui1-hero-bg-video__loader" aria-hidden="true"><span class="gloskin-ui1-hero-bg-video__loader-dot"></span></div>
        </div>
      </div>
    </div>
    <div class="gloskin-ui1-hero__fade" aria-hidden="true"></div>
    <button type="button" class="gloskin-ui1-hero__scroll-cue" data-gloskin-hero-scroll-cue aria-label="Gulir ke konten berikutnya"><span class="gloskin-ui1-hero__scroll-cue-dot"></span></button>
  </section>
  <section id="next" style="height:600px">Next</section>
</main></body></html>"""

MARKUP_NO_VIDEO = MARKUP.replace(' is-video-preparing" data-gloskin-hero-bg-video-root', '"').replace(
    '''        <div class="gloskin-ui1-hero-bg-video" data-gloskin-hero-bg-video-wrap>
          <video class="gloskin-ui1-hero-bg-video__media" data-gloskin-hero-bg-video muted autoplay loop playsinline preload="auto" aria-hidden="true" tabindex="-1">
            <source src="data:video/mp4;base64," type="video/mp4">
          </video>
          <div class="gloskin-ui1-hero-bg-video__loader" aria-hidden="true"><span class="gloskin-ui1-hero-bg-video__loader-dot"></span></div>
        </div>\n''',
    ''
)


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def install_fixture(page, ready_state=0, paused=True):
    page.set_content(MARKUP)
    page.add_style_tag(content=CSS)
    page.evaluate(
        """([readyState, paused]) => {
          const video = document.querySelector('[data-gloskin-hero-bg-video]');
          window.__heroReadyState = readyState;
          window.__heroPaused = paused;
          window.__heroPlayCalls = 0;
          window.__heroPauseCalls = 0;
          Object.defineProperty(video, 'readyState', {configurable:true, get:() => window.__heroReadyState});
          Object.defineProperty(video, 'paused', {configurable:true, get:() => window.__heroPaused});
          video.play = () => { window.__heroPlayCalls += 1; return Promise.resolve(); };
          video.pause = () => { window.__heroPauseCalls += 1; window.__heroPaused = true; };
        }""",
        [ready_state, paused],
    )
    page.add_script_tag(content=JS)


with sync_playwright() as playwright:
    chromium_path = Path("/usr/bin/chromium")
    try:
        kwargs = {"headless": True}
        if chromium_path.exists():
            kwargs.update(executable_path=str(chromium_path), args=["--no-sandbox"])
        browser = playwright.chromium.launch(**kwargs)
    except Exception:
        print("hero-video-browser-smoke: SKIPPED (chromium unavailable)")
        raise SystemExit(77)

    page = browser.new_page(viewport={"width": 390, "height": 844})
    install_fixture(page)
    hero = page.locator(".gloskin-ui1-hero--campaign")
    media = page.locator(".gloskin-ui1-hero-bg-video__media")
    loader = page.locator(".gloskin-ui1-hero-bg-video__loader")

    require(page.locator("main h1").count() == 1, "campaign must render exactly one visible H1")
    require(page.locator("main h1").is_visible(), "Home H1 must be visible")
    require(page.locator(".gloskin-ui1-hero__copy").is_visible(), "Home supporting copy must be visible")
    require(page.locator(".gloskin-ui1-hero__actions a").is_visible(), "Home CTA must be visible")
    require(page.locator("video[data-gloskin-hero-bg-video]").count() == 1, "configured campaign must render exactly one native video")
    require(page.locator(".gloskin-ui1-hero__media-fallback").count() == 1, "campaign must retain a fallback media composition")
    require(page.locator("iframe").count() == 0, "Home must render no iframe/second video service")
    require(page.locator("video[poster], video[controls]").count() == 0, "Home video must render no poster or controls")
    require(page.locator("[data-gloskin-hero-scroll-cue]").count() == 1, "exactly one scroll cue must render")
    require(page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), "hero must not cause horizontal overflow")
    require(media.evaluate("el => getComputedStyle(el).opacity") == "0", "video must remain hidden before READY")
    require(media.evaluate("el => getComputedStyle(el).objectFit") == "cover", "native video must use object-fit cover")
    for selector in [".gloskin-ui1-hero-bg-video", ".gloskin-ui1-hero-bg-video__media", ".gloskin-ui1-hero-bg-video__loader"]:
        require(page.locator(selector).evaluate("el => getComputedStyle(el).pointerEvents") == "none", f"{selector} must be pointerless")

    page.evaluate("""() => {
      window.__heroReadyState = 2;
      const video = document.querySelector('[data-gloskin-hero-bg-video]');
      video.dispatchEvent(new Event('loadeddata'));
      video.dispatchEvent(new Event('playing'));
    }""")
    page.wait_for_function("document.querySelector('.gloskin-ui1-hero').classList.contains('is-video-ready')")
    require(page.evaluate("window.__heroPlayCalls") == 1, "controller must call play once")
    require(media.evaluate("el => getComputedStyle(el).opacity") == "1", "READY must reveal native video")
    require(loader.evaluate("el => getComputedStyle(el).opacity") == "0", "READY must hide loader")
    page.locator("[data-gloskin-hero-scroll-cue]").click()
    require(page.evaluate("document.querySelector('#next').getBoundingClientRect().top < window.innerHeight"), "scroll cue must reach next section")
    page.close()

    failed = browser.new_page(viewport={"width": 390, "height": 844})
    install_fixture(failed)
    failed.evaluate("document.querySelector('[data-gloskin-hero-bg-video]').dispatchEvent(new Event('error'))")
    failed.wait_for_function("document.querySelector('.gloskin-ui1-hero').classList.contains('is-video-failed')")
    require(failed.locator(".gloskin-ui1-hero__media-fallback").is_visible(), "video failure must leave fallback media visible")
    require(failed.locator("main h1").is_visible(), "video failure must never hide visible Home H1")
    require(failed.locator(".gloskin-ui1-hero-bg-video__media").evaluate("el => getComputedStyle(el).opacity") == "0", "failed video must remain hidden")
    require(failed.locator(".gloskin-ui1-hero-bg-video__loader").evaluate("el => getComputedStyle(el).opacity") == "0", "native error must release loader")
    failed.close()

    unavailable = browser.new_page(viewport={"width": 390, "height": 844})
    unavailable.set_content(MARKUP_NO_VIDEO)
    unavailable.add_style_tag(content=CSS)
    require(unavailable.locator("main h1").count() == 1 and unavailable.locator("main h1").is_visible(), "no-video Home must retain one visible H1")
    require(unavailable.locator("video, iframe, .gloskin-ui1-hero-bg-video__loader").count() == 0, "no-source state must create no video/player/loader")
    require(unavailable.locator(".gloskin-ui1-hero__media-fallback").is_visible(), "no-video Home must retain editorial/factual media fallback")
    unavailable.close()

    reduced = browser.new_page(viewport={"width": 390, "height": 844}, reduced_motion="reduce")
    install_fixture(reduced, ready_state=2, paused=True)
    reduced.wait_for_function("document.querySelector('.gloskin-ui1-hero').classList.contains('is-video-ready')")
    require(reduced.evaluate("window.__heroPlayCalls") == 0, "reduced motion must not call play")
    require(reduced.evaluate("window.__heroPauseCalls") == 1, "reduced motion must pause the usable static frame")
    reduced.close()
    browser.close()

print("hero-video-browser-smoke: OK")
