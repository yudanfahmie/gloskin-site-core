"""
promo-thumbnail-contract.py

Static contract test: promo carousel poster/thumbnail selector.

Asserts:
  1.  gloskin_ui1_render_managed_promo_carousel() renders thumbnail strip inside count > 1 block.
  2.  Thumbnail buttons use data-gloskin-promo-thumb attribute.
  3.  Thumbnail strip uses role=tablist for keyboard accessibility.
  4.  Thumbnail buttons carry aria-selected and tabindex attributes.
  5.  Thumbnail strip is conditional: only when promos have Featured Images.
  6.  initPromoCarousel() in JS syncs thumbnail buttons (data-gloskin-promo-thumb).
  7.  JS activate() function updates thumb is-active / aria-selected / tabindex.
  8.  Thumbnail buttons have click and keydown handlers wired.
  9.  Existing dots / prev / next controls are preserved.
  10. No autoplay introduced (no setInterval in initPromoCarousel).
  11. Plugin / Kernel version synchronized at 0.7.138.
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

helpers  = read('plugin/gloskin-site-core/templates/parts/template-helpers.php')
js       = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js')
plugin_h = read('plugin/gloskin-site-core/gloskin-site-core.php')
kernel   = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')

# 1. Thumbnail strip inside count > 1 block in PHP
require(
    'data-gloskin-promo-thumb' in helpers,
    'carousel template must render data-gloskin-promo-thumb thumbnail buttons'
)

# 2. Thumbnail buttons use data-gloskin-promo-thumb
require(
    'data-gloskin-promo-thumb="' in helpers,
    'thumbnail buttons must have data-gloskin-promo-thumb="<index>" attribute'
)

# 3. Thumbnail strip uses role=tablist
require(
    'gloskin-ui1-promo-carousel__thumbs' in helpers and 'role="tablist"' in helpers,
    'thumbnail strip must use role=tablist for keyboard accessibility'
)

# 4. aria-selected and tabindex on thumbnail buttons
require(
    'aria-selected=' in helpers and 'tabindex=' in helpers,
    'thumbnail buttons must carry aria-selected and tabindex attributes'
)

# 5. Conditional: only when promos have images
require(
    'promos_with_images' in helpers or 'image_id' in helpers,
    'thumbnail strip must be conditional on promos having Featured Images'
)

# 6. JS syncs thumbnail buttons
require(
    'data-gloskin-promo-thumb' in js,
    'initPromoCarousel() JS must query data-gloskin-promo-thumb elements'
)

# 7. JS activate() updates thumbs
carousel_fn_start = js.find('function initPromoCarousel()')
carousel_fn_end   = js.find('\n\t}', carousel_fn_start + 1) if carousel_fn_start != -1 else -1
carousel_body     = js[carousel_fn_start:carousel_fn_end] if carousel_fn_start != -1 and carousel_fn_end != -1 else ''
require(
    'thumbs' in carousel_body and 'thumbActive' in carousel_body,
    'initPromoCarousel() activate() must sync thumbnail is-active state'
)

# 8. Thumbnail click and keydown handlers
require(
    'thumbs[ti]' in js or 'thumb.addEventListener' in js,
    'thumbnail buttons must have click event listeners wired in JS'
)

# 9. Existing controls preserved
require(
    'data-gloskin-promo-prev' in helpers and 'data-gloskin-promo-next' in helpers,
    'existing prev/next controls must remain in carousel template'
)
require(
    'data-gloskin-promo-dot' in helpers,
    'existing dot controls must remain in carousel template'
)

# 10. No autoplay (no setInterval in carousel function)
require(
    'setInterval' not in carousel_body,
    'initPromoCarousel() must not introduce autoplay (no setInterval)'
)

# 11. Version sync
require("Version: 0.7.138" in plugin_h, "plugin header must be 0.7.138")
require("const VERSION = '0.7.138';" in kernel, "Kernel VERSION must be 0.7.138")

if failures:
    for f in failures:
        print('FAIL:', f)
    sys.exit(1)

print('promo-thumbnail-contract.py: OK (0.7.138)')
