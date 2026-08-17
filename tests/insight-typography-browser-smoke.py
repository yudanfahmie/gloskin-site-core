#!/usr/bin/env python3
"""Computed-style regression for the Insight editorial heading cascade."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css').read_text(encoding='utf-8')
CORE = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css').read_text(encoding='utf-8')
PRODUCTION = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-production.css').read_text(encoding='utf-8')
EDITORIAL = (ROOT / 'plugin/gloskin-site-core/assets/css/gloskin-ui1-editorial.css').read_text(encoding='utf-8')

HTML = '''<!doctype html><html><head><meta charset="utf-8"><style>{css}</style></head>
<body class="gloskin-ui1"><main class="gloskin-ui1-main">
<div class="gloskin-ui1-container" style="padding-block:24px">
<h1 data-global-h1>Global display H1</h1><h2 data-global-h2>Global display H2</h2>
<section class="gloskin-ui1-insights-archive">
<article class="gloskin-ui1-insights-archive__card"><div class="gloskin-ui1-insights-archive__body"><h2 class="gloskin-ui1-insights-archive__title" data-regular><a href="#">Rutinitas Perawatan Kulit yang Konsisten untuk Menjaga Skin Barrier</a></h2></div></article>
<article class="gloskin-ui1-insights-archive__card gloskin-ui1-insights-archive__card--lead"><div class="gloskin-ui1-insights-archive__body"><h2 class="gloskin-ui1-insights-archive__title" data-lead><a href="#">Panduan Memahami Perawatan Kulit secara Bertahap dan Terukur</a></h2></div></article>
</section>
<article class="gloskin-ui1-insight-single">
<header class="gloskin-ui1-insight-single__header"><div class="gloskin-ui1-container gloskin-ui1-insight-single__header-inner"><span class="gloskin-ui1-insight-single__category">Perawatan Kulit</span><h1 class="gloskin-ui1-insight-single__title" data-single>Cara Menjaga Skin Barrier agar Tetap Sehat di Tengah Aktivitas Harian</h1><p class="gloskin-ui1-insight-single__dek">Ringkasan artikel tetap berada di bawah judul utama.</p><div class="gloskin-ui1-insight-single__meta">17 Agustus 2026 · 5 menit baca</div></div></header>
<div class="gloskin-ui1-container gloskin-ui1-insight-single__reading"><div class="gloskin-ui1-insight-single__content"><p data-body-copy>Isi artikel mengikuti ritme baca editorial yang tenang dan tetap WordPress-native.</p><h2 data-body-h2>Memahami kebutuhan dasar kulit</h2><h3 data-body-h3>Membaca perubahan secara bertahap</h3></div></div>
<section class="gloskin-ui1-insight-single__related"><div class="gloskin-ui1-container"><div class="gloskin-ui1-insight-single__related-head"><p class="gloskin-ui1-eyebrow">Lanjut membaca</p><h2 data-related>Artikel terkait</h2></div><div class="gloskin-ui1-insights-archive__grid"><article class="gloskin-ui1-insights-archive__card"><div class="gloskin-ui1-insights-archive__body"><h2 class="gloskin-ui1-insights-archive__title" data-related-card><a href="#">Artikel terkait memakai pemilik tipografi kartu yang sama</a></h2></div></article></div></div></section>
</article></div></main></body></html>'''


def size(page, selector):
    return float(page.locator(selector).evaluate("el => parseFloat(getComputedStyle(el).fontSize)"))


def line_ratio(page, selector):
    return page.locator(selector).evaluate("""el => {
        const s=getComputedStyle(el); return el.getBoundingClientRect().height / parseFloat(s.lineHeight);
    }""")


def assert_range(value, low, high, label):
    assert low <= value <= high, f'{label}: {value:.2f}px outside {low:.2f}-{high:.2f}px'


def run_view(browser, width, mobile=False):
    page = browser.new_page(viewport={'width': width, 'height': 1100})
    page.set_content(HTML.format(css=BASE + '\n' + CORE + '\n' + PRODUCTION + '\n' + EDITORIAL), wait_until='domcontentloaded')
    values = {
        'global_h1': size(page, '[data-global-h1]'),
        'global_h2': size(page, '[data-global-h2]'),
        'regular': size(page, '[data-regular]'),
        'lead': size(page, '[data-lead]'),
        'single': size(page, '[data-single]'),
        'related': size(page, '[data-related]'),
        'related_card': size(page, '[data-related-card]'),
        'body_h2': size(page, '[data-body-h2]'),
        'body_h3': size(page, '[data-body-h3]'),
        'body_copy': size(page, '[data-body-copy]'),
    }
    assert abs(values['regular'] - values['related_card']) < 0.05, 'related cards must reuse the regular card title owner'
    assert values['lead'] > values['regular'], 'lead title must remain stronger than a regular card'
    assert values['single'] > values['lead'], 'single H1 must remain above lead-card typography'
    assert values['single'] > values['body_h2'] > values['body_h3'] > values['body_copy'], 'article H1/H2/H3/body hierarchy regressed'
    assert abs(values['regular'] - values['global_h2']) > 4, 'regular card fell back to global H2 scale'
    assert abs(values['single'] - values['global_h1']) > 4, 'single H1 fell back to global display H1 scale'
    assert_range(values['body_h2'], 23.5, 32.1, 'body H2')
    assert_range(values['body_h3'], 19.0, 24.1, 'body H3')
    if mobile:
        assert_range(values['regular'], 17.5, 22.0, 'mobile regular card')
        assert_range(values['lead'], 25.0, 31.0, 'mobile lead card')
        assert_range(values['single'], 31.5, 42.0, 'mobile single H1')
        assert_range(values['related'], 24.5, 30.0, 'mobile related heading')
        assert line_ratio(page, '[data-single]') <= 4.1, 'mobile single title became an overly tall headline tower'
    else:
        assert_range(values['regular'], 17.5, 24.0, 'desktop regular card')
        assert_range(values['lead'], 25.0, 38.0, 'desktop lead card')
        assert_range(values['single'], 31.5, 60.0, 'desktop single H1')
        assert_range(values['related'], 24.5, 36.0, 'desktop related heading')
        assert line_ratio(page, '[data-single]') <= 3.1, 'desktop single title should remain around two to three balanced lines'
    assert page.evaluate('document.documentElement.scrollWidth - window.innerWidth') <= 1, 'horizontal overflow detected'
    unclipped = page.locator('[data-single]').evaluate("""el => {
        const s=getComputedStyle(el); return s.overflowX === 'visible' && s.overflowY === 'visible' && s.whiteSpace === 'normal' && el.scrollWidth <= el.clientWidth + 1;
    }""")
    assert unclipped, 'single title wrapping is clipped or forced'
    page.close()
    return values


def main():
    try:
        from playwright.sync_api import sync_playwright
    except Exception as exc:
        print(f'SKIP: Playwright unavailable: {exc}')
        return 77
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
        desktop = run_view(browser, 1440, False)
        mobile = run_view(browser, 390, True)
        browser.close()
    print('insight typography browser smoke: OK '
          f"(desktop regular={desktop['regular']:.2f}px lead={desktop['lead']:.2f}px H1={desktop['single']:.2f}px related={desktop['related']:.2f}px; "
          f"mobile regular={mobile['regular']:.2f}px lead={mobile['lead']:.2f}px H1={mobile['single']:.2f}px related={mobile['related']:.2f}px)")
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
