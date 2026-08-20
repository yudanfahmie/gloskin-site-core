"""
skincare-filter-contract.py

Static contract test: skincare filter chips and no-JS product state.

Asserts:
  1.  skincare_context() fetches products per Woo category (products_for_category call).
  2.  Products are tagged with category_slugs (space-separated slug string).
  3.  Fallback to all products when no categorized products found (no-JS state).
  4.  Skincare template renders filter chip bar with data-gloskin-chip-filter.
  5.  Skincare template renders "Semua" chip (empty slug = show all).
  6.  Skincare template wraps product cards in data-gloskin-product-card containers.
  7.  data-category-slugs attribute is set on product card wrappers.
  8.  initSkincareChips() is defined in core JS.
  9.  initSkincareChips() is called from the init() function.
  10. JS chip filter reads data-gloskin-chip attribute from chip buttons.
  11. JS chip filter reads data-category-slugs from product card containers.
  12. No-JS: products are visible without chip interaction (no initial hidden state applied by PHP).
  13. Skincare intro copy no longer says "Pilih kategori, lalu..." (old gating copy removed).
  14. Plugin / Kernel version synchronized at 0.7.144.
"""

import re, sys, os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

def read(path):
    with open(os.path.join(ROOT, path), encoding='utf-8') as f:
        return f.read()

failures = []

def require(cond, msg):
    if not cond:
        failures.append(msg)

svc      = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php')
skincare = read('plugin/gloskin-site-core/templates/pages/skincare.php')
js       = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js')
plugin_h = read('plugin/gloskin-site-core/gloskin-site-core.php')
kernel   = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')

require('products_for_category' in svc,'skincare_context() must call products_for_category() for per-category product fetching')
require("category_slugs" in svc,"skincare_context() must assign category_slugs to each product")
require('empty( $products )' in svc or 'empty($products)' in svc,'skincare_context() must have a fallback for empty categorized product set')
require('data-gloskin-chip-filter' in skincare,'skincare template must render filter chip bar with data-gloskin-chip-filter')
require('data-gloskin-chip=""' in skincare,'skincare template must render Semua chip with data-gloskin-chip="" (show all)')
require('Semua' in skincare,'skincare template must render Semua chip label')
require('data-gloskin-product-card' in skincare,'skincare template must wrap products in data-gloskin-product-card containers')
require('data-category-slugs' in skincare,'skincare template must set data-category-slugs on product card wrappers')
require('function initSkincareChips()' in js,'gloskin-ui1-core.js must define initSkincareChips()')
init_start = js.find('function init()')
init_end   = js.find('\n\t}', init_start) if init_start != -1 else -1
init_body  = js[init_start:init_end] if init_start != -1 and init_end != -1 else ''
require('initSkincareChips()' in init_body,'initSkincareChips() must be called from init() in gloskin-ui1-core.js')
require("data-gloskin-chip" in js,'JS chip filter must read data-gloskin-chip attribute from chip buttons')
require("'data-category-slugs'" in js or '"data-category-slugs"' in js,'JS chip filter must read data-category-slugs from product card containers')
require('data-gloskin-product-card hidden' not in skincare,'PHP must not initially hide product cards; no-JS state must show all products')
require('Pilih kategori, lalu lihat produk' not in skincare,'Old "Pilih kategori, lalu..." intro copy must be removed from skincare template')
require("Version: 0.7.144" in plugin_h, "plugin header must be 0.7.144")
require("const VERSION = '0.7.144';" in kernel, "Kernel VERSION must be 0.7.144")

if failures:
    for f in failures:
        print('FAIL:', f)
    sys.exit(1)

print('skincare-filter-contract.py: OK (0.7.144)')
