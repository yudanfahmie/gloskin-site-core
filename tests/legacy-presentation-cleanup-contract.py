"""
legacy-presentation-cleanup-contract.py

Static contract test: retired presentation surfaces and external asset removal.

Asserts:
  1.  design_variant <select> is removed from admin settings brand tab HTML.
  2.  design_variant hidden input is removed from admin settings (fully retired).
  3.  header_variant picker cards are removed from admin settings header tab HTML.
  4.  header_variant hidden input is removed from admin settings (fully retired).
  5.  private function header_variant() fully removed from template-service.
  6.  private function design_variant() fully removed from template-service.
  7.  No images.unsplash.com runtime URLs in template-helpers.php.
  8.  gloskin_ui1_render_editorial_media() calls gloskin_ui1_render_presentation_media (no Unsplash).
  9.  gloskin_ui1_editorial_media_catalog() returns empty array (no URLs).
  10. Why Gloskin meta box renders for 'home' page in admin service.
  11. Why Gloskin keys in admin save_schema() page strings.
  12. List-table column hooks for Promo, Testimonial, Achievement CPTs registered.
  13. Plugin / Kernel version synchronized at 0.7.140.
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

admin    = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php')
tmpl_svc = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php')
helpers  = read('plugin/gloskin-site-core/templates/parts/template-helpers.php')
plugin_h = read('plugin/gloskin-site-core/gloskin-site-core.php')
kernel   = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')

# 1. design_variant <select> removed from brand tab
require(
    '<select' not in admin or 'design_variant]">' not in admin,
    'design_variant <select> must be removed from admin brand tab'
)
require(
    'gloskin-design-variant' not in admin,
    'id="gloskin-design-variant" select must be removed from admin UI'
)

# 2. design_variant hidden input fully removed; settings_defaults() no longer declares it
require(
    'design_variant]"' not in admin,
    'design_variant input name must not appear in admin form (hidden input fully retired)'
)
require(
    "'design_variant' => 'medical'" not in admin,
    "design_variant must be removed from settings_defaults()"
)

# 3. header_variant picker cards removed from header tab
require(
    'gloskin-admin-header-picker' not in admin,
    'gloskin-admin-header-picker div must be removed from admin header tab'
)
require(
    'render_header_variant_card' not in admin
    or admin.count('render_header_variant_card') < 3,
    'header_variant picker card render calls must be removed from active settings rendering'
)

# 4. header_variant hidden input fully removed; settings_defaults() no longer declares it
require(
    'header_variant]"' not in admin,
    'header_variant input name must not appear in admin form (hidden input fully retired)'
)
require(
    "'header_variant' => 'header-1'" not in admin,
    "header_variant must be removed from settings_defaults()"
)

# 5. header_variant() private method fully removed from template-service
require(
    'private function header_variant()' not in tmpl_svc and 'function header_variant(' not in tmpl_svc,
    'private function header_variant() must be fully removed from template-service'
)

# 6. design_variant() private method fully removed from template-service
require(
    'private function design_variant()' not in tmpl_svc and 'function design_variant(' not in tmpl_svc,
    'private function design_variant() must be fully removed from template-service'
)

# 7. No Unsplash runtime URLs in helpers (doc comments may mention host in text; check for URL scheme)
require('https://images.unsplash.com' not in helpers, 'No https://images.unsplash.com runtime URLs allowed in template-helpers.php')

# 8. render_editorial_media delegates to render_presentation_media
require(
    'gloskin_ui1_render_presentation_media' in helpers,
    'gloskin_ui1_render_editorial_media() must delegate to gloskin_ui1_render_presentation_media()'
)

# 9. editorial_media_catalog returns empty array
require(
    re.search(r'gloskin_ui1_editorial_media_catalog\b[^{]*\{[^}]*return\s+array\s*\(\s*\)', helpers, re.DOTALL),
    'gloskin_ui1_editorial_media_catalog() must return empty array()'
)

# 10. Why Gloskin meta box for home page
require("'home' === $post->post_name" in admin, "render_page_meta_box() must branch on 'home' === $post->post_name for Why Gloskin fields")
require('gloskin_why_heading' in admin, 'admin must render gloskin_why_heading field for home page')

# 11. Why Gloskin keys in save_schema
require(
    admin.count('gloskin_why_heading') >= 2,
    'gloskin_why_heading must appear in both render_page_meta_box() and save_schema() (at least 2 occurrences)'
)
require('gloskin_why_lead' in admin, 'gloskin_why_lead must be in admin (render + save_schema)')
require('gloskin_why_primary_title' in admin, 'gloskin_why_primary_title must be in admin')
require('gloskin_why_primary_copy' in admin, 'gloskin_why_primary_copy must be in admin')

# 12. List-table column hooks for managed CPTs
require(
    'manage_edit-' in admin and 'promo_list_columns' in admin,
    'manage_edit-{promo}_columns filter must be registered'
)
require(
    'testimonial_list_columns' in admin,
    'manage_edit-{testimonial}_columns filter must be registered'
)
require(
    'achievement_list_columns' in admin,
    'manage_edit-{achievement}_columns filter must be registered'
)

# 13. Version sync
require("Version: 0.7.140" in plugin_h, "plugin header must be 0.7.140")
require("const VERSION = '0.7.140';" in kernel, "Kernel VERSION must be 0.7.140")

if failures:
    for f in failures:
        print('FAIL:', f)
    sys.exit(1)

print('legacy-presentation-cleanup-contract.py: OK (0.7.140)')
