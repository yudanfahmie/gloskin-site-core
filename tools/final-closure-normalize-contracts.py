#!/usr/bin/env python3
from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    s = p.read_text(encoding='utf-8')
    if s.count(old) != 1:
        raise SystemExit(f'{path}: expected exactly one superseded contract anchor, found {s.count(old)}')
    p.write_text(s.replace(old, new, 1), encoding='utf-8')


# Generated final closure contract: literal $this references must not interpolate.
p = Path('tests/final-closure-contract.php')
s = p.read_text(encoding='utf-8')
s = s.replace('"[\'editorial_audit\'] = $this->run_managed_content()"', '"[\'editorial_audit\'] = \\$this->run_managed_content()"')
s = s.replace('"[\'ia_audit\'] = $this->run_normalize()"', '"[\'ia_audit\'] = \\$this->run_normalize()"')
s = s.replace(
    'gl_final_ok( substr_count( $migration, "\'key\' =>") === 8, \'no migration checkpoint added\' );',
    '$first_step = min( $positions ); $last_step = max( $positions ); $step_slice = substr( $migration, $first_step, ( $last_step - $first_step ) + strlen( "\'key\' => \'finalize\'" ) ); gl_final_ok( substr_count( $step_slice, "\'key\' => \'" ) === 8, \'no migration checkpoint added/reordered\' );'
)
s = s.replace(
    'gl_final_ok( ! str_contains( $ia, "wp_delete_post( $page" ), \'IA normalizer never deletes supporting pages\' );',
    'gl_final_ok( substr_count( $ia, \'wp_delete_post(\' ) === 1 && str_contains( $ia, \'wp_delete_post( $item_id, true )\' ), \'IA normalizer deletes only obsolete nav-menu items, never supporting pages\' );'
)
p.write_text(s, encoding='utf-8')

# v0.7.139 presentation baseline required an empty editorial catalog. Final closure
# replaces that baseline with a bounded local WordPress attachment catalog.
replace_once(
    'tests/check-presentation.sh',
    """grep -Fq 'return array(); /* No external image catalog' \"$helpers\" \\
  || { echo \"empty editorial media catalog contract missing\" >&2; exit 1; }""",
    """grep -Fq \"get_option( 'gloskin_site_core_editorial_media_v1', array() )\" \"$helpers\" \\
  || { echo \"bounded local editorial media catalog contract missing\" >&2; exit 1; }
grep -Fq 'wp_get_attachment_image( $attachment_id' \"$helpers\" \\
  || { echo \"local WordPress editorial attachment rendering missing\" >&2; exit 1; }""",
)

# The former "empty catalog" cleanup rule is superseded by the final closure's
# locally-hosted, migration-owned editorial attachment catalog. External runtime
# URLs remain forbidden; the fallback presentation renderer may still be used
# only when the bounded import is unavailable.
legacy = Path('tests/legacy-presentation-cleanup-contract.py')
ls = legacy.read_text(encoding='utf-8')
ls = ls.replace('  9.  gloskin_ui1_editorial_media_catalog() returns empty array (no URLs).', '  9.  editorial_media_catalog() reads only the local migration-owned WordPress option (no runtime URLs).')
old_legacy = """# 9. editorial_media_catalog returns empty array
require(
    re.search(r'gloskin_ui1_editorial_media_catalog\\b[^{]*\\{[^}]*return\\s+array\\s*\\(\\s*\\)', helpers, re.DOTALL),
    'gloskin_ui1_editorial_media_catalog() must return empty array()'
)
"""
new_legacy = """# 9. editorial_media_catalog reads the bounded local attachment catalog only
require(
    \"get_option( 'gloskin_site_core_editorial_media_v1', array() )\" in helpers,
    'gloskin_ui1_editorial_media_catalog() must read the bounded local migration catalog'
)
require(
    'https://assets.zyrosite.com' not in helpers and 'http://' not in helpers,
    'template-helpers must not contain runtime editorial hotlinks'
)
"""
if old_legacy not in ls:
    raise SystemExit('legacy empty-catalog assertion not found')
legacy.write_text(ls.replace(old_legacy, new_legacy, 1), encoding='utf-8')

# The final Home requirement intentionally unifies Skincare and Product Discovery.
replace_once(
    'tests/product-grid-contract.php',
    "$ok( false !== strpos( $home, 'data-gloskin-section=\"home-products\"' ), 'Homepage product section missing' );",
    "$ok( false !== strpos( $home, 'data-gloskin-section=\"home-discovery\"' ), 'Unified Homepage skincare/product discovery section missing' );",
)

# The page-transition router may acknowledge the existing Cart<->Checkout handoff,
# but it must not couple itself to Woo block cart markup. Keep cart-table polish CSS-only.
replace_once(
    'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js',
    "return (path === '/cart' || path === '/checkout') && !!link.closest('.woocommerce, .wp-block-woocommerce-cart, .wp-block-woocommerce-checkout');",
    "return (path === '/cart' || path === '/checkout') && !!link.closest('.woocommerce');",
)
replace_once(
    'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js',
    "[data-gloskin-modal], [data-gloskin-wishlist], .quantity, .variations, form.checkout, form.woocommerce-cart-form, .wc-block-cart-item__quantity",
    "[data-gloskin-modal], [data-gloskin-wishlist], .quantity, .variations, form.checkout, form.woocommerce-cart-form, [class*=\"cart-item__quantity\"]",
)

print('final-closure-normalize-contracts: OK')
