#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const { chromium } = require("playwright");

const root = path.resolve(__dirname, "..");
const cssRoot = path.join(root, "plugin/gloskin-site-core/assets/css");
const styles = [
  "gloskin-ui1-fonts.css",
  "gloskin-ui1-core-base.css",
  "gloskin-ui1-core.css",
  "gloskin-ui1-single-product-geometry.css",
  "gloskin-ui1-readiness.css",
  "gloskin-ui1-production.css",
  "gloskin-ui1-quickadd-polish.css",
  "gloskin-ui1-commerce-polish.css",
  "gloskin-ui1-loader-system.css",
  "gloskin-ui1-brand-purchase-polish.css",
  "gloskin-ui1-editorial.css",
  "gloskin-ui1-product-grid.css",
  "gloskin-ui1-prototype-refresh.css",
];
const css = styles.map((file) => fs.readFileSync(path.join(cssRoot, file), "utf8")).join("\n");
const routes = ["home", "treatments", "shop", "about", "insights", "clinics", "doctors", "contact", "product"];
const widths = [1440, 1024, 768, 390];

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

const html = `<!doctype html><html><head><meta charset="utf-8"><style>${css}</style></head>
<body class="gloskin-ui1"><header class="gloskin-ui1-header"><div class="gloskin-ui1-header__inner">
<nav class="gloskin-ui1-nav gloskin-ui1-nav--desktop"><ul class="gloskin-ui1-nav__list"><li class="gloskin-ui1-nav__item"><div class="gloskin-ui1-nav__row"><a class="gloskin-ui1-nav__link" href="#">Perawatan</a></div><ul class="gloskin-ui1-nav__submenu"><li><a class="gloskin-ui1-nav__link" href="#">Wajah</a></li></ul></li></ul></nav>
<nav class="gloskin-ui1-nav gloskin-ui1-nav--mobile"><a class="gloskin-ui1-nav__link" href="#">Skincare</a></nav></div></header>
<main><section class="gloskin-ui1-section"><div class="gloskin-ui1-container"><div class="gloskin-ui1-section-heading"><h2>Perawatan pilihan</h2><p>Deskripsi singkat yang nyaman dibaca dan memiliki satu warna tinta.</p></div><article class="gloskin-ui1-card"><div class="gloskin-ui1-card__body"><h3 class="gloskin-ui1-card__title">Judul kartu</h3><p class="gloskin-ui1-card__copy">Salinan kartu editorial.</p></div></article><div class="gloskin-ui1-prose"><p>Paragraf prose publik.</p></div><div class="woocommerce-product-details__short-description"><p>Deskripsi singkat produk.</p></div></div></section>
<section class="gloskin-ui1-section gloskin-ui1-section--contrast"><div class="gloskin-ui1-container"><p class="gloskin-ui1-lead">Salinan inverse kontras.</p></div></section>
<section class="gloskin-ui1-closing-cta"><div class="gloskin-ui1-closing-cta__copy"><p>Salinan closing CTA.</p></div></section></main>
<footer class="gloskin-ui1-footer"><div class="gloskin-ui1-footer__brand"><p>Salinan footer inverse.</p></div></footer></body></html>`;

(async () => {
  const executablePath = chromium.executablePath();
  if (!fs.existsSync(executablePath)) {
    console.log("css-ownership-browser-smoke: SKIPPED (Chromium unavailable)");
    process.exit(77);
  }
  const browser = await chromium.launch({ headless: true, executablePath, args: ["--no-sandbox"] });
  try {
    for (const route of routes) {
      for (const width of widths) {
        const page = await browser.newPage({ viewport: { width, height: 1000 } });
        await page.setContent(html);
        await page.evaluate((name) => document.body.classList.add(`gloskin-route-${name}`), route);
        await page.waitForTimeout(30);
        const result = await page.evaluate(() => {
          const style = (selector) => getComputedStyle(document.querySelector(selector));
          const heading = document.querySelector(".gloskin-ui1-section-heading");
          const before = heading.getBoundingClientRect().toJSON();
          return new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => {
            const after = heading.getBoundingClientRect().toJSON();
            resolve({
              nav: [".gloskin-ui1-nav--desktop>.gloskin-ui1-nav__list .gloskin-ui1-nav__link", ".gloskin-ui1-nav__submenu .gloskin-ui1-nav__link", ".gloskin-ui1-nav--mobile .gloskin-ui1-nav__link"].map((selector) => ({ weight: style(selector).fontWeight, family: style(selector).fontFamily })),
              copy: [".gloskin-ui1-section-heading p", ".gloskin-ui1-card__copy", ".gloskin-ui1-prose p", ".woocommerce-product-details__short-description p"].map((selector) => ({ color: style(selector).color, weight: style(selector).fontWeight })),
              inverse: [".gloskin-ui1-section--contrast .gloskin-ui1-lead", ".gloskin-ui1-closing-cta__copy p", ".gloskin-ui1-footer__brand p"].map((selector) => style(selector).color),
              columns: style(".gloskin-ui1-section-heading").gridTemplateColumns.split(" ").filter(Boolean),
              align: style(".gloskin-ui1-section-heading p").textAlign,
              overflow: document.documentElement.scrollWidth - innerWidth,
              stable: Math.abs(before.x - after.x) < 0.1 && Math.abs(before.y - after.y) < 0.1 && Math.abs(before.width - after.width) < 0.1 && Math.abs(before.height - after.height) < 0.1,
            });
          })));
        });
        result.nav.forEach((nav) => {
          assert(nav.weight === "400", `${route}/${width}: nav weight ${nav.weight}`);
          assert(nav.family.includes("Graphik"), `${route}/${width}: nav family ${nav.family}`);
        });
        result.copy.forEach((copy) => {
          assert(copy.weight === "300", `${route}/${width}: copy weight ${copy.weight}`);
          assert(copy.color === "rgb(42, 35, 44)", `${route}/${width}: copy color ${copy.color}`);
        });
        result.inverse.forEach((color) => assert(color !== "rgb(42, 35, 44)" && color !== "rgba(0, 0, 0, 0)", `${route}/${width}: inverse color ${color}`));
        assert(result.columns.length === (width >= 1041 ? 2 : 1), `${route}/${width}: section columns ${result.columns}`);
        assert(result.align === (width >= 1041 ? "right" : "left"), `${route}/${width}: section alignment ${result.align}`);
        assert(result.overflow <= 1, `${route}/${width}: horizontal overflow ${result.overflow}`);
        assert(result.stable, `${route}/${width}: layout shifted after settle`);
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
  console.log("css-ownership-browser-smoke: OK (9 routes x 4 viewports)");
})().catch((error) => { console.error(`css-ownership-browser-smoke: FAIL: ${error.message}`); process.exit(1); });
