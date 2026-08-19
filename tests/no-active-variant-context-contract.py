"""
no-active-variant-context-contract.py

Static contract test: design_variant and header_variant fully retired from active runtime.

Asserts:
  1.  $context['design_variant'] not projected in template-service.
  2.  $context['header_variant'] not projected in template-service.
  3.  private function design_variant() fully removed from template-service.
  4.  private function header_variant() fully removed from template-service.
  5.  design_variant not read from settings in body_classes() in woocommerce-adapter.
  6.  design_variant removed from settings_defaults() in admin-service.
  7.  header_variant removed from settings_defaults() in admin-service.
  8.  design_variant removed from sanitize_settings() in admin-service.
  9.  header_variant removed from sanitize_settings() in admin-service.
  10. No hidden input for design_variant in admin settings UI.
  11. No hidden input for header_variant in admin settings UI.
  12. header_variant_previews() private method removed from admin-service.
  13. render_header_variant_card() private method removed from admin-service.
  14. Prototype IA Migration admin NOT registered in kernel (single migration action).
  15. Insight Migration admin IS still registered in kernel (genuinely independent).
  16. Public shell does not consume design_variant or header_variant.
  17. Public header does not read header_variant state.
  18. Plugin / Kernel version synchronized at 0.7.138.
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
woo      = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php')
admin    = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php')
kernel   = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')
shell    = read('plugin/gloskin-site-core/templates/shell.php')
header   = read('plugin/gloskin-site-core/templates/parts/header.php')
plugin_h = read('plugin/gloskin-site-core/gloskin-site-core.php')

# 1. No design_variant context projection in template-service
require(
    "$context['design_variant']" not in svc,
    "$context['design_variant'] must not be projected in template-service"
)

# 2. No header_variant context projection in template-service
require(
    "$context['header_variant']" not in svc,
    "$context['header_variant'] must not be projected in template-service"
)

# 3. design_variant() private method removed
require(
    'private function design_variant()' not in svc and 'function design_variant(' not in svc,
    'private function design_variant() must be fully removed from template-service'
)

# 4. header_variant() private method removed
require(
    'private function header_variant()' not in svc and 'function header_variant(' not in svc,
    'private function header_variant() must be fully removed from template-service'
)

# 5. body_classes() no longer reads design_variant from settings
require(
    "settings['design_variant']" not in woo and "design_variant" not in woo,
    'body_classes() in woocommerce-adapter must not read design_variant from settings'
)

# 6. design_variant removed from settings_defaults()
require(
    "'design_variant' => 'medical'" not in admin,
    "design_variant must be removed from settings_defaults() in admin-service"
)

# 7. header_variant removed from settings_defaults()
require(
    "'header_variant' => 'header-1'" not in admin,
    "header_variant must be removed from settings_defaults() in admin-service"
)

# 8. design_variant removed from sanitize_settings()
require(
    "sanitize_key( $value['design_variant'] )" not in admin
    and "value['design_variant']" not in admin,
    "design_variant must be removed from sanitize_settings() in admin-service"
)

# 9. header_variant removed from sanitize_settings()
require(
    "sanitize_key( $value['header_variant'] )" not in admin
    and "value['header_variant']" not in admin,
    "header_variant must be removed from sanitize_settings() in admin-service"
)

# 10. No hidden design_variant input
require(
    'design_variant]"' not in admin,
    'design_variant hidden input must not appear in admin settings UI'
)

# 11. No hidden header_variant input
require(
    'header_variant]"' not in admin,
    'header_variant hidden input must not appear in admin settings UI'
)

# 12. header_variant_previews() removed
require(
    'function header_variant_previews()' not in admin,
    'header_variant_previews() must be removed from admin-service (zero consumers)'
)

# 13. render_header_variant_card() removed
require(
    'function render_header_variant_card(' not in admin,
    'render_header_variant_card() must be removed from admin-service (zero consumers)'
)

# 14. Prototype IA Migration NOT registered in kernel
require(
    'class-gloskin-site-core-prototype-ia-migration-admin.php' not in kernel,
    'Prototype IA Migration admin must NOT be registered in kernel (single migration action)'
)
require(
    'Gloskin_Site_Core_Prototype_IA_Migration_Admin' not in kernel,
    'Gloskin_Site_Core_Prototype_IA_Migration_Admin must NOT be instantiated in kernel'
)

# 15. Insight Migration IS still registered (genuinely independent)
require(
    'class-gloskin-site-core-insight-migration-admin.php' in kernel,
    'Insight Migration admin MUST remain registered in kernel (genuinely independent)'
)

# 16. Public shell does not consume design/header variant
require(
    'design_variant' not in shell,
    'Public shell must not consume design_variant'
)
require(
    'header_variant' not in shell,
    'Public shell must not consume header_variant'
)

# 17. Public header does not read header_variant
require(
    'header_variant' not in header,
    'Public header must not read header_variant state'
)

# 18. Version sync
require("Version: 0.7.141" in plugin_h, "plugin header must be 0.7.141")
require("const VERSION = '0.7.141';" in kernel, "Kernel VERSION must be 0.7.141")

if failures:
    for f in failures:
        print('FAIL:', f)
    sys.exit(1)

print('no-active-variant-context-contract.py: OK (0.7.141)')
