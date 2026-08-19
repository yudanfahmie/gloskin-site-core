"""
final-migration-js-behavior-contract.py

Static contract: JS-level behaviors for the v0.7.139 AJAX migration controller.

Asserts:
  1.  JS exits early if [data-gloskin-final-migration] root is not found.
  2.  JS exits early if data-ajax / data-action / data-nonce are empty.
  3.  mode=start used only for status=pending (fresh start).
  4.  mode=continue used directly for failed/running/verifying (no-reload resume).
  5.  No page reload on failure (no window.location assignment in onFail).
  6.  Inline error shown on failure (showError or equivalent called in onFail).
  7.  Button re-enabled on failure with resume label.
  8.  On consumed: window.location.href navigates to gloskin-content.
  9.  On consumed: DOM cleanup removes migration notice and menu item.
  10. continueChain calls requestAnimationFrame between checkpoints (no tight loop).
  11. JS intercepts form submit with event.preventDefault().
  12. AJAX uses fetch() with application/x-www-form-urlencoded Content-Type.
  13. state.processed_steps used (not processed_products) for progress bar.
  14. state.total_steps used (not expected_products or hardcoded 13).
  15. Plugin / Kernel version synchronized at 0.7.139.
"""

import os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

def read(path):
    with open(os.path.join(ROOT, path), encoding='utf-8') as f:
        return f.read()

failures = []

def require(cond, msg):
    if not cond:
        failures.append(msg)

js       = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-final-migration.js')
kernel   = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')
plugin_h = read('plugin/gloskin-site-core/gloskin-site-core.php')

# 1. Early return if root not found
require(
    "if ( ! root ) { return; }" in js or "if (!root)" in js or "if ( ! root )" in js,
    "JS must return early if [data-gloskin-final-migration] root not found"
)

# 2. Early return if action/nonce missing
require(
    "if ( ! ajaxUrl || ! action || ! nonce ) { return; }" in js
    or "if (!ajaxUrl || !action || !nonce)" in js,
    "JS must return early if data-ajax, data-action, or data-nonce are empty"
)

# 3. mode=start only for pending status
require(
    "'pending' === status" in js or '"pending" === status' in js
    or "=== 'pending'" in js,
    "JS must call mode=start only when data-status is 'pending'"
)
require(
    "'start'" in js,
    "JS must issue mode=start AJAX request for fresh starts"
)

# 4. mode=continue for failed/running/verifying
require(
    "'continue'" in js,
    "JS must issue mode=continue AJAX request for resume"
)
# Failed state goes directly to continue (not start)
require(
    "failed" in js and "'continue'" in js,
    "JS must use mode=continue for failed/running/verifying states (no start handshake)"
)

# 5. No window.location in onFail
fail_fn_match = re.search(r'function onFail\s*\([^)]*\)\s*\{(.+?)\n\s*\}', js, re.S)
if fail_fn_match:
    fail_body = fail_fn_match.group(1)
    require(
        'window.location' not in fail_body,
        "onFail() must NOT reload the page (no window.location assignment)"
    )
else:
    require(False, "onFail() function must be defined in JS")

# 6. Inline error shown in onFail
require(
    'showError(' in js,
    "onFail() must call showError() to display inline error message"
)

# 7. Button re-enabled in onFail with resume label
require(
    'Lanjutkan Finalisasi' in js,
    "onFail() must restore button with 'Lanjutkan Finalisasi' label"
)
require(
    'button.disabled = false' in js or 'button.disabled=false' in js,
    "onFail() must re-enable the button"
)

# 8. On consumed: navigate to gloskin-content
require(
    'gloskin-content' in js,
    "onConsumed() must navigate to gloskin-content admin page"
)
require(
    'window.location.href' in js,
    "onConsumed() must set window.location.href for navigation"
)

# 9. On consumed: DOM cleanup
require(
    'Finalisasi Prototype' in js,
    "onConsumed() must target 'Finalisasi Prototype' text to remove notice and menu item"
)
require(
    '#adminmenu' in js,
    "onConsumed() must remove the migration item from #adminmenu"
)

# 10. requestAnimationFrame between checkpoints
require(
    'requestAnimationFrame' in js,
    "continueChain() must use requestAnimationFrame to yield between checkpoints"
)

# 11. form submit intercepted with preventDefault
require(
    'preventDefault' in js,
    "JS must call event.preventDefault() on form submit to intercept the POST fallback"
)

# 12. fetch() with correct Content-Type
require(
    'fetch(' in js or 'fetch (' in js,
    "JS must use fetch() for AJAX requests"
)
require(
    'application/x-www-form-urlencoded' in js,
    "AJAX request must use application/x-www-form-urlencoded Content-Type"
)

# 13. processed_steps used (not processed_products)
require(
    'processed_steps' in js,
    "JS must read processed_steps from AJAX state response"
)
require(
    'processed_products' not in js,
    "JS must NOT reference processed_products (old sample-import key)"
)

# 14. total_steps used (not expected_products or hardcoded 13)
require(
    'total_steps' in js,
    "JS must read total_steps from AJAX state response"
)
require(
    'expected_products' not in js,
    "JS must NOT reference expected_products (old sample-import key)"
)

# 15. Version sync
require("Version: 0.7.139" in plugin_h, "Plugin header must be 0.7.139")
require("const VERSION = '0.7.139';" in kernel, "Kernel VERSION must be 0.7.139")

if failures:
    for f in failures:
        print('FAIL:', f)
    sys.exit(1)

print('final-migration-js-behavior-contract.py: OK (0.7.139)')
