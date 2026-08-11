#!/usr/bin/env python3
"""Computed-geometry regression for Woo classic CSS inside Gloskin's PDP grid."""
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print("single-product-ghost-space-browser-smoke: SKIPPED (Playwright unavailable)")
    raise SystemExit(77)

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugin" / "gloskin-site-core"
core = (PLUGIN / "assets/css/gloskin-ui1-core.css").read_text(encoding="utf-8")
geometry = (PLUGIN / "assets/css/gloskin-ui1-single-product-geometry.css").read_text(encoding="utf-8")

# Exact relevant geometry from WooCommerce 11.0.0 classic frontend styles:
# woocommerce-layout.css (48% floats, ul.products clearfix, 22.05% cards)
# plus woocommerce.css (2em primary gallery/summary margins).
woo_legacy = r"""
.woocommerce div.product div.images,.woocommerce-page div.product div.images{float:left;width:48%;margin-bottom:2em}
.woocommerce div.product div.summary,.woocommerce-page div.product div.summary{float:right;width:48%;clear:none;margin-bottom:2em}
.woocommerce div.product .woocommerce-tabs,.woocommerce-page div.product .woocommerce-tabs{clear:both}
.woocommerce ul.products,.woocommerce-page ul.products{clear:both;width:100%}
.woocommerce ul.products::before,.woocommerce ul.products::after,.woocommerce-page ul.products::before,.woocommerce-page ul.products::after{content:" ";display:table}
.woocommerce ul.products::after,.woocommerce-page ul.products::after{clear:both}
.woocommerce ul.products li.product,.woocommerce-page ul.products li.product{float:left;margin:0 3.8% 2.992em 0;padding:0;position:relative;width:22.05%;margin-left:0}
.woocommerce ul.products li.product.last,.woocommerce-page ul.products li.product.last{margin-right:0}
"""

html = r"""
<!doctype html><html><head><meta charset="utf-8"></head>
<body class="single-product gloskin-ui1 woocommerce woocommerce-page">
<div class="woocommerce gloskin-ui1-commerce-native" style="width:1200px;margin:0 auto">
<div id="product-501" class="product">
 <div class="woocommerce-product-gallery images" data-gallery><div style="height:300px"></div></div>
 <div class="summary entry-summary" data-summary><div style="height:300px"></div></div>
 <div class="woocommerce-tabs" data-tabs><div style="height:220px"></div></div>
 <section class="related products" data-related><ul class="products columns-3" data-products>
  <li class="product first"><div style="height:300px"></div></li>
  <li class="product"><div style="height:300px"></div></li>
  <li class="product last"><div style="height:300px"></div></li>
 </ul></section>
</div></div></body></html>
"""

def require(condition, message):
    if not condition:
        raise AssertionError(message)

def px(value):
    return float(value.removesuffix("px"))

chromium = Path("/usr/bin/chromium")
if not chromium.exists():
    print("single-product-ghost-space-browser-smoke: SKIPPED (Chromium unavailable)")
    raise SystemExit(77)

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True, executable_path=str(chromium), args=["--no-sandbox"])
    page = browser.new_page(viewport={"width": 1440, "height": 1000})
    page.set_content(html)
    page.add_style_tag(content=woo_legacy + "\n" + core + "\n" + geometry)
    metrics = page.evaluate(r"""() => {
      const root = document.querySelector('.gloskin-ui1-commerce-native>div.product');
      const gallery = document.querySelector('[data-gallery]');
      const summary = document.querySelector('[data-summary]');
      const tabs = document.querySelector('[data-tabs]');
      const related = document.querySelector('[data-related]');
      const list = document.querySelector('[data-products]');
      const cards = [...list.children];
      const rs = getComputedStyle(root), gs = getComputedStyle(gallery), ss = getComputedStyle(summary), ts = getComputedStyle(tabs), rels = getComputedStyle(related), ls = getComputedStyle(list);
      const gr = gallery.getBoundingClientRect(), sr = summary.getBoundingClientRect(), tr = tabs.getBoundingClientRect(), rr = related.getBoundingClientRect(), lr = list.getBoundingClientRect();
      return {
        rootClient: root.clientWidth, rootScroll: root.scrollWidth,
        columns: rs.gridTemplateColumns, columnGap: rs.columnGap, rowGap: rs.rowGap,
        gallery: {w: gr.width, l: gr.left, r: gr.right, bottom: gr.bottom, float: gs.float, mb: gs.marginBottom},
        summary: {w: sr.width, l: sr.left, r: sr.right, bottom: sr.bottom, float: ss.float, mb: ss.marginBottom},
        tabs: {top: tr.top, bottom: tr.bottom, mt: ts.marginTop},
        related: {top: rr.top, mt: rels.marginTop},
        list: {left: lr.left, width: lr.width, columns: ls.gridTemplateColumns, gap: ls.columnGap},
        cards: cards.map(c => { const r=c.getBoundingClientRect(), s=getComputedStyle(c); return {left:r.left,width:r.width,float:s.float,clear:s.clear,margin:s.margin}; })
      };
    }""")

    tracks = [float(value.removesuffix("px")) for value in metrics["columns"].split() if value.endswith("px")]
    require(metrics["rootScroll"] <= metrics["rootClient"] + 1, "primary product scrollWidth must ~= clientWidth")
    require(len(tracks) == 2, f"desktop primary product must have exactly two tracks, got {metrics['columns']}")
    require(metrics["gallery"]["w"] >= tracks[0] * .90, "gallery must fill >=90% of assigned track")
    require(metrics["summary"]["w"] >= tracks[1] * .90, "summary must fill >=90% of assigned track")
    require(abs((metrics["summary"]["l"] - metrics["gallery"]["r"]) - px(metrics["columnGap"])) <= 1.5, "occupied primary gap must equal declared CSS column gap")
    require(px(metrics["rowGap"]) <= .5, "primary grid must not reserve vertical section row-gap")
    require(metrics["gallery"]["float"] == "none" and metrics["summary"]["float"] == "none", "Woo legacy primary floats must be neutralized")
    require(px(metrics["gallery"]["mb"]) <= .5 and px(metrics["summary"]["mb"]) <= .5, "Woo legacy 2em primary margins must be neutralized")

    related_tracks = [float(value.removesuffix("px")) for value in metrics["list"]["columns"].split() if value.endswith("px")]
    require(len(related_tracks) == 3, "Related Products must expose exactly three explicit desktop tracks")
    require(abs(metrics["cards"][0]["left"] - metrics["list"]["left"]) <= 1.5, "clearfix pseudo-element must not reserve the first related-product cell")
    require(all(card["float"] == "none" and card["margin"] == "0px" for card in metrics["cards"]), "related cards must not retain Woo float/margin geometry")
    require(all(card["width"] >= related_tracks[i] * .90 for i, card in enumerate(metrics["cards"])), "related cards must fill >=90% of their assigned tracks")

    primary_end = max(metrics["gallery"]["bottom"], metrics["summary"]["bottom"])
    require(abs((metrics["tabs"]["top"] - primary_end) - px(metrics["tabs"]["mt"])) <= 1.5, "primary->Tabs distance must be only intentional Tabs margin")
    require(abs((metrics["related"]["top"] - metrics["tabs"]["bottom"]) - px(metrics["related"]["mt"])) <= 1.5, "Tabs->Related distance must be only intentional Related margin")

    page.set_viewport_size({"width": 390, "height": 844})
    page.evaluate("document.querySelector('.gloskin-ui1-commerce-native').style.width='100%'")
    mobile = page.evaluate(r"""() => {
      const root = document.querySelector('.gloskin-ui1-commerce-native>div.product');
      const summary = document.querySelector('[data-summary]');
      const rs=getComputedStyle(root), ss=getComputedStyle(summary);
      return {columns:rs.gridTemplateColumns,rowGap:rs.rowGap,summaryMarginTop:ss.marginTop,scroll:root.scrollWidth,client:root.clientWidth};
    }""")
    mobile_tracks = [value for value in mobile["columns"].split() if value.endswith("px")]
    require(len(mobile_tracks) == 1, "mobile primary product must collapse to one explicit track")
    require(px(mobile["rowGap"]) <= .5, "mobile primary grid row-gap must remain zero")
    require(px(mobile["summaryMarginTop"]) >= 24, "mobile gallery->summary spacing must remain intentional")
    require(mobile["scroll"] <= mobile["client"] + 1, "mobile primary product must not horizontally overflow")
    browser.close()

print("single-product-ghost-space-browser-smoke: OK")
