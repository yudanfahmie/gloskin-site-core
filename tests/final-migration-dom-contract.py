"""
final-migration-dom-contract.py

Static contract: admin render and asset wiring for v0.7.139 AJAX flow.

Asserts:
  1.  Admin render contains data-gloskin-final-migration attribute on root div.
  2.  Admin render contains data-gloskin-migration-form on the POST form.
  3.  Admin render contains data-gloskin-migration-run on the submit button.
  4.  Admin render contains data-gloskin-migration-progressbar on progress element.
  5.  Admin render contains data-gloskin-migration-step on the step paragraph.
  6.  Admin render contains data-gloskin-migration-error for inline error div.
  7.  enqueue_assets() calls enqueue_admin_final_migration() (not the old migration method).
  8.  enqueue_admin_final_migration() method exists in asset service.
  9.  gloskin-ui1-final-migration registered in config/assets.php.
  10. gloskin-ui1-final-migration.js exists at expected asset path.
  11. New JS targets [data-gloskin-final-migration] root selector.
  12. New JS reads data-ajax / data-action / data-nonce from root element.
  13. Old [data-gloskin-sample-import] and [data-gloskin-ia-migration] selectors absent from new JS.
  14. Plugin / Kernel version synchronized at 0.7.139.
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

admin_php  = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration-admin.php')
asset_svc  = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php')
assets_cfg = read('plugin/gloskin-site-core/config/assets.php')
kernel     = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')
plugin_h   = read('plugin/gloskin-site-core/gloskin-site-core.php')
js_path    = os.path.join(ROOT, 'plugin/gloskin-site-core/assets/js/gloskin-ui1-final-migration.js')
js_exists  = os.path.isfile(js_path)
js         = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-final-migration.js') if js_exists else ''

# 1. Root div has data-gloskin-final-migration
require(
    'data-gloskin-final-migration' in admin_php,
    "Admin render must include data-gloskin-final-migration attribute on root div"
)

# 2. Form has data-gloskin-migration-form
require(
    'data-gloskin-migration-form' in admin_php,
    "Admin render must include data-gloskin-migration-form on the POST form"
)

# 3. Button has data-gloskin-migration-run
require(
    'data-gloskin-migration-run' in admin_php,
    "Admin render must include data-gloskin-migration-run on the submit button"
)

# 4. Progress has data-gloskin-migration-progressbar
require(
    'data-gloskin-migration-progressbar' in admin_php,
    "Admin render must include data-gloskin-migration-progressbar on progress element"
)

# 5. Step paragraph has data-gloskin-migration-step
require(
    'data-gloskin-migration-step' in admin_php,
    "Admin render must include data-gloskin-migration-step on step paragraph"
)

# 6. Error div has data-gloskin-migration-error
require(
    'data-gloskin-migration-error' in admin_php,
    "Admin render must include data-gloskin-migration-error for inline error div"
)

# 7. enqueue_assets calls enqueue_admin_final_migration (not old method)
require(
    'enqueue_admin_final_migration' in admin_php,
    "enqueue_assets() must call enqueue_admin_final_migration()"
)
require(
    'enqueue_admin_migration(' not in admin_php,
    "enqueue_assets() must NOT call old enqueue_admin_migration() — final migration uses dedicated method"
)

# 8. Asset service has enqueue_admin_final_migration()
require(
    'function enqueue_admin_final_migration' in asset_svc,
    "Asset service must define enqueue_admin_final_migration() method"
)

# 9. gloskin-ui1-final-migration in config/assets.php
require(
    'gloskin-ui1-final-migration' in assets_cfg,
    "config/assets.php must register gloskin-ui1-final-migration admin script"
)
require(
    'gloskin-ui1-final-migration.js' in assets_cfg,
    "config/assets.php must point gloskin-ui1-final-migration to the correct JS file"
)

# 10. JS file exists
require(
    js_exists,
    "assets/js/gloskin-ui1-final-migration.js must exist on disk"
)

# 11. New JS targets [data-gloskin-final-migration]
require(
    '[data-gloskin-final-migration]' in js,
    "New JS must select root element via [data-gloskin-final-migration]"
)

# 12. New JS reads data-ajax / data-action / data-nonce from root
require(
    "getAttribute( 'data-ajax' )" in js or "getAttribute('data-ajax')" in js,
    "New JS must read data-ajax from root element attribute"
)
require(
    "getAttribute( 'data-action' )" in js or "getAttribute('data-action')" in js,
    "New JS must read data-action from root element attribute"
)
require(
    "getAttribute( 'data-nonce' )" in js or "getAttribute('data-nonce')" in js,
    "New JS must read data-nonce from root element attribute"
)

# 13. Old selectors absent from new JS
require(
    'data-gloskin-sample-import' not in js,
    "New JS must NOT contain old [data-gloskin-sample-import] selector"
)
require(
    'data-gloskin-ia-migration' not in js,
    "New JS must NOT contain old [data-gloskin-ia-migration] selector"
)

# 14. Version sync
require("Version: 0.7.139" in plugin_h, "Plugin header must be 0.7.139")
require("const VERSION = '0.7.139';" in kernel, "Kernel VERSION must be 0.7.139")

if failures:
    for f in failures:
        print('FAIL:', f)
    sys.exit(1)

print('final-migration-dom-contract.py: OK (0.7.139)')
