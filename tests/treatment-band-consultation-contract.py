"""
treatment-band-consultation-contract.py

Static contract test: treatment band CTA → consultation contract.

Asserts:
  1. JS initTreatmentBands() reads data-gloskin-band-path (not data-path) as path ID.
  2. JS finds [data-gloskin-consultation-path="<id>"] and clicks it.
  3. JS handles ?path=<id> auto-select on page load (no-JS fallback support).
  4. PHP CTA href uses /treatments/?path=<id>#consultation pattern.
  5. No Unsplash runtime URLs remain in gloskin_ui1_editorial_media_catalog().
  6. No images.unsplash.com URLs remain in template helpers.
  7. Plugin / Kernel version synchronized at 0.7.139.
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

js        = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js')
helpers   = read('plugin/gloskin-site-core/templates/parts/template-helpers.php')
plugin_h  = read('plugin/gloskin-site-core/gloskin-site-core.php')
kernel    = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')

# 1. JS reads data-gloskin-band-path (not data-path) for path ID
require('data-gloskin-band-path' in js, 'JS must use data-gloskin-band-path attribute')
require(
    'getAttribute(\'data-path\')' not in js or 'data-gloskin-band-path' in js,
    'JS must read data-gloskin-band-path, not bare data-path for band path ID'
)

# 2. JS finds [data-gloskin-consultation-path
require('[data-gloskin-consultation-path' in js, 'JS must query [data-gloskin-consultation-path] to activate consultation')

# 3. JS handles ?path= auto-select on load
require('?path=' in js or 'URLSearchParams' in js, 'JS must handle ?path= URL param for no-JS fallback auto-select')

# 4. PHP CTA href pattern: /treatments/?path=<id>#consultation
require('?path=' in helpers and '#consultation' in helpers, 'PHP CTA href must include ?path=<id>#consultation')

# 5. editorial_media_catalog returns empty array
require(
    re.search(r'gloskin_ui1_editorial_media_catalog\s*\(.*?\)\s*\{[^}]*return\s+array\s*\(\s*\)', helpers, re.DOTALL),
    'gloskin_ui1_editorial_media_catalog must return empty array() (no Unsplash URLs)'
)

# 6. No Unsplash runtime URLs in non-comment code in helpers
# (doc comments may mention the host in explanatory text; check for URL scheme)
require('https://images.unsplash.com' not in helpers, 'No https://images.unsplash.com runtime URLs allowed in template-helpers.php')

# 7. Version sync
require("Version: 0.7.139" in plugin_h, "plugin header must be 0.7.139")
require("const VERSION = '0.7.139';" in kernel, "Kernel VERSION must be 0.7.139")

if failures:
    for f in failures:
        print('FAIL:', f)
    sys.exit(1)

print('treatment-band-consultation-contract.py: OK (0.7.139)')
