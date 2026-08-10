#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_root="$repo_root/plugin/gloskin-site-core"
templates="$plugin_root/templates"
helpers="$templates/parts/template-helpers.php"
composition_helpers="$templates/parts/composition-helpers.php"
core_base_css="$plugin_root/assets/css/gloskin-ui1-core-base.css"
core_css="$plugin_root/assets/css/gloskin-ui1-core.css"
production_css="$plugin_root/assets/css/gloskin-ui1-production.css"
core_js="$plugin_root/assets/js/gloskin-ui1-core.js"
public_runtime=(
  "$templates"
  "$plugin_root/includes/class-gloskin-site-core-navigation-service.php"
  "$plugin_root/includes/class-gloskin-site-core-template-service.php"
  "$plugin_root/includes/class-gloskin-site-core-woocommerce-adapter.php"
  "$core_base_css"
  "$core_css"
  "$production_css"
  "$plugin_root/assets/js/gloskin-ui1-core.js"
)

visitor_leaks='not configured|content pending|missing data|architecture supports|approved doctor profiles|approved treatment categories|woocommerce product data is currently unavailable|coming soon|lorem ipsum|dummy|developer placeholder|debug message'
if grep -RInEi "$visitor_leaks" "${public_runtime[@]}" --include='*.php' --include='*.js' --include='*.css'; then
  echo "client-facing staging/dummy language found in public runtime" >&2
  exit 1
fi

backend_copy_leaks='woocommerce|wordpress|pemetaan|sumber data|kepemilikan produk|kepemilikan katalog|katalog kedua|template ownership|catalog ownership|second catalog|source data'
if grep -RInEi "(__|esc_html__|esc_attr__)\([^)]*($backend_copy_leaks)" "$templates/pages" --include='*.php'; then
  echo "backend implementation terminology found in visitor-facing translated copy" >&2
  exit 1
fi

if ! grep -q "gloskin-ui1-empty--form" "$templates/pages/contact.php"; then
  echo "Contact does not suppress the internal missing-form adapter state" >&2
  exit 1
fi

medical_claims='100% aman|tanpa risiko|tanpa rasa sakit|hasil permanen|pasti berhasil|langsung terlihat|tanpa downtime|terbaik di Indonesia|clinically proven|FDA approved|BPOM approved'
if grep -RInEi "$medical_claims" "$templates" "$plugin_root/includes/class-gloskin-site-core-template-service.php" --include='*.php'; then
  echo "unsupported medical/marketing claim found in public runtime" >&2
  exit 1
fi

fixture_leaks='Test Product|TEST-001|NA00000000000|Test composition|Test usage|Fixture Treatment|Fixture Doctor|fixture-treatment|fixture-doctor|fixture-editorial-post|example\.test|localhost'
if grep -RInE "$fixture_leaks" "$plugin_root" --include='*.php' --include='*.js' --include='*.css'; then
  echo "test fixture value found in production runtime" >&2
  exit 1
fi

# Homepage and footer are the production-content refinement scope for this pass;
# guard the specific staging/meta phrasing removed from them against regression.
home_footer_staging='yang telah dipublikasikan|yang dipublikasikan Gloskin|langkah berikutnya|Jelajahi klinik, informasi perawatan'
if grep -RInEi "$home_footer_staging" "$templates/pages/home.php" "$templates/parts/footer.php"; then
  echo "staging/meta copy phrasing found in homepage or footer" >&2
  exit 1
fi

if grep -RInE 'href="#"|href="javascript:' "$templates" --include='*.php' \
  || grep -RInE "href='#'|href='javascript:" "$templates" --include='*.php'; then
  echo "dummy or javascript CTA found in public templates" >&2
  exit 1
fi

# Factual entity images stay WordPress/Woo-owned. Manual img markup is allowed only
# inside the canonical helper for curated generic staging/editorial photography.
if grep -RInE '<img[[:space:]]' "$templates" --include='*.php' --exclude='template-helpers.php'; then
  echo "manual img markup found outside canonical presentation helper" >&2
  exit 1
fi
if ! grep -q 'function gloskin_ui1_render_presentation_media' "$helpers" \
  || ! grep -q 'function gloskin_ui1_render_editorial_media' "$helpers"; then
  echo "canonical factual/editorial media helpers missing" >&2
  exit 1
fi
if ! grep -q 'function gloskin_ui1_render_pathway_grid' "$composition_helpers" \
  || ! grep -q 'function gloskin_ui1_render_closing_cta' "$composition_helpers"; then
  echo "reusable page-richness composition helpers missing" >&2
  exit 1
fi
if ! grep -q "array( 'clinic', 'doctor' )" "$helpers" \
  || ! grep -q "gloskin_ui1_render_presentation_media( 'product'" "$helpers" \
  || ! grep -q "gloskin_ui1_render_presentation_media( 'doctor'" "$templates/pages/doctor.php" \
  || ! grep -q "gloskin_ui1_render_presentation_media( 'clinic'" "$templates/pages/clinic.php"; then
  echo "safe factual doctor/clinic/product empty-state boundary missing" >&2
  exit 1
fi
if ! grep -q 'https://images.unsplash.com/photo-' "$helpers" \
  || grep -Eq 'source\.unsplash\.com|images\.unsplash\.com/[^p]' "$helpers"; then
  echo "editorial staging media must use fixed curated Unsplash photo URLs" >&2
  exit 1
fi
if grep -RInE "url\([\"']?https?://" "$plugin_root/assets" --include='*.css' --include='*.js'; then
  echo "critical first-party presentation asset depends on a remote CSS/JS URL" >&2
  exit 1
fi

# Sticky/admin-bar offsets have one owner: core refinement CSS. Foundation and
# production layers may reference the canonical variables, but must not define competing owners.
if grep -Eq -- '--gloskin-ui1-admin-bar-height:|--gloskin-ui1-nav-sticky-top:' "$production_css" "$core_base_css"; then
  echo "foundation/production CSS still competes for sticky admin-bar offset ownership" >&2
  exit 1
fi
for expected in \
  '--gloskin-ui1-admin-bar-height:32px' \
  '--gloskin-ui1-admin-bar-height:46px' \
  '--gloskin-ui1-nav-sticky-top:var(--gloskin-ui1-admin-bar-height)'; do
  grep -q -- "$expected" "$core_css" || { echo "canonical core admin-bar rule missing: $expected" >&2; exit 1; }
done
if ! grep -q '@media (max-width:600px).*--gloskin-ui1-admin-bar-height:0px' "$core_css"; then
  echo "core CSS does not clear fixed toolbar offset at <=600px" >&2
  exit 1
fi
# The positional gap regression: no rule may add extra px on top of the admin-bar height itself.
if grep -Eq -- '--gloskin-ui1-admin-bar-gap|nav-sticky-top:calc\(' "$core_css"; then
  echo "sticky nav top offset must equal the admin-bar height exactly, no added gap" >&2
  exit 1
fi

# Brand palette ownership stays centralized in the foundation token layer.
python - "$core_base_css" "$core_css" "$production_css" <<'PYBRAND'
import re
import sys
from pathlib import Path

css = Path(sys.argv[1]).read_text()
production_css = '\n'.join(Path(path).read_text() for path in sys.argv[1:])
for token, value in {
    '--gloskin-brand-red': '#B12E2F',
    '--gloskin-brand-red-deep': '#961F24',
    '--gloskin-brand-ivory': '#FBFBFA',
    '--gloskin-brand-surface': '#F6F3F1',
    '--gloskin-brand-surface-strong': '#ECEBE8',
    '--gloskin-brand-border': '#DDD7D3',
    '--gloskin-brand-charcoal': '#2A232C',
    '--gloskin-brand-muted': '#6F6667',
}.items():
    if f'{token}:{value}' not in css:
        raise SystemExit(f'brand token missing or changed: {token}')

for legacy in ('#173f59', '#0d2f45', '#dbe9f1', '#183044', '#f5f8fa', '#eaf0f4', '#d7e0e6'):
    if legacy.lower() in production_css.lower():
        raise SystemExit(f'legacy blue production palette returned: {legacy}')

for selector in (':root', '.gloskin-ui1--modern', '.gloskin-ui1--luxury'):
    match = re.search(re.escape(selector) + r'\{([^}]*)\}', css)
    if not match:
        raise SystemExit(f'missing design token block: {selector}')
    block = match.group(1)
    if '--gloskin-accent:var(--gloskin-brand-red)' not in block:
        raise SystemExit(f'{selector} no longer shares the Gloskin crimson anchor')
    if '--gloskin-accent-strong:var(--gloskin-brand-red-deep)' not in block:
        raise SystemExit(f'{selector} no longer shares the Gloskin deep-crimson state')

if '--gloskin-accent-readable:' not in css or 'outline:3px solid var(--gloskin-accent-readable)' not in css:
    raise SystemExit('readable accent/focus semantic token missing')

def rgb(value):
    value = value.lstrip('#')
    return tuple(int(value[i:i+2], 16) for i in (0, 2, 4))

def luminance(color):
    values = []
    for channel in color:
        c = channel / 255
        values.append(c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4)
    return 0.2126 * values[0] + 0.7152 * values[1] + 0.0722 * values[2]

def contrast(a, b):
    la, lb = luminance(rgb(a)), luminance(rgb(b))
    return (max(la, lb) + 0.05) / (min(la, lb) + 0.05)

for label, fg, bg, minimum in (
    ('body text', '#2A232C', '#FBFBFA', 7.0),
    ('muted text', '#6F6667', '#FBFBFA', 4.5),
    ('crimson CTA/link', '#B12E2F', '#FBFBFA', 4.5),
    ('deep crimson inverse', '#FBFBFA', '#961F24', 4.5),
    ('luxury body text', '#FBFBFA', '#2A232C', 7.0),
):
    ratio = contrast(fg, bg)
    if ratio < minimum:
        raise SystemExit(f'{label} contrast below {minimum}: {ratio:.2f}')
PYBRAND

# Contrast surfaces use one semantic foreground state instead of inheriting the
# global accent/muted colors that can become dark-on-dark.
for expected in \
  '--gloskin-ui1-contrast-foreground' \
  '.gloskin-ui1-footer__cta .gloskin-ui1-eyebrow' \
  '.gloskin-ui1-closing-cta .gloskin-ui1-eyebrow' \
  '.gloskin-ui1-section--contrast>.gloskin-ui1-container>.gloskin-ui1-section-heading p'; do
  grep -Fq -- "$expected" "$core_css" || { echo "contrast foreground ownership missing: $expected" >&2; exit 1; }
done

# Header visual polish remains CSS-only and must not change accepted behavior.
for expected in \
  'backdrop-filter:saturate(120%) blur(14px)' \
  '.gloskin-ui1-header__inner{min-height:72px' \
  '.gloskin-ui1-nav__chevron{display:block;width:11px;height:11px'; do
  grep -Fq -- "$expected" "$core_css" || { echo "premium header refinement missing: $expected" >&2; exit 1; }
done

if [[ ! -f "$production_css" ]] \
  || ! grep -q -- '--gloskin-font-body:"Mulish"' "$production_css" \
  || ! grep -q -- '--gloskin-font-heading:"Marcellus"' "$production_css"; then
  echo "Marcellus/Mulish production typography layer missing" >&2
  exit 1
fi
if ! grep -q 'family=Marcellus&family=Mulish:wght@400;600;700;800' "$plugin_root/config/assets.php"; then
  echo "required Google Fonts family/weight registration missing" >&2
  exit 1
fi

# Favicon derivatives: all sizes must exist and derive from the same master,
# at the exact pixel dimensions each context expects.
images_dir="$plugin_root/assets/images"
if [[ ! -f "$images_dir/gloskin-logotext.svg" ]]; then
  echo "canonical logo SVG missing" >&2
  exit 1
fi
php -r '
$images = $argv[1];
$expected = [
  "favicon-16x16.png" => [16, 16],
  "favicon-32x32.png" => [32, 32],
  "apple-touch-icon.png" => [180, 180],
  "icon-192.png" => [192, 192],
  "icon-512.png" => [512, 512],
];
foreach ( $expected as $file => [$w, $h] ) {
  $path = $images . "/" . $file;
  if ( ! is_readable( $path ) ) { fwrite( STDERR, "missing favicon derivative: $file\n" ); exit(1); }
  $size = getimagesize( $path );
  if ( ! $size || $size[0] !== $w || $size[1] !== $h ) {
    fwrite( STDERR, "favicon derivative wrong dimensions: $file (" . ($size[0] ?? "?") . "x" . ($size[1] ?? "?") . ", expected {$w}x{$h})\n" );
    exit(1);
  }
}
if ( ! is_readable( $images . "/favicon.ico" ) || filesize( $images . "/favicon.ico" ) < 500 ) {
  fwrite( STDERR, "favicon.ico missing or implausibly small\n" ); exit(1);
}
if ( ! is_readable( $images . "/favicon-master-g.png" ) ) {
  fwrite( STDERR, "favicon master derivative missing\n" ); exit(1);
}
' "$images_dir" || exit 1

required_views=(home about treatments treatment skincare skincare-category clinics clinic doctors doctor contact insights shop)
for view in "${required_views[@]}"; do
  [[ -f "$templates/pages/$view.php" ]] || { echo "missing public view: $view" >&2; exit 1; }
done

closing_views=(home about treatments treatment skincare-category clinics clinic doctors doctor)
for view in "${closing_views[@]}"; do
  grep -q 'data-gloskin-section=".*closing"' "$templates/pages/$view.php" || { echo "required closing composition missing: $view" >&2; exit 1; }
done

# Gloskin Form Kit v1 -- small reusable field/select/textarea/checkbox/radio/
# quantity primitive over native WooCommerce markup. Presentation only; Woo
# keeps every field's markup, validation, cart/checkout/order logic.
readiness_css="$plugin_root/assets/css/gloskin-ui1-readiness.css"

for expected in \
  '--gloskin-field-height:52px' \
  '--gloskin-field-radius:8px' \
  '--gloskin-field-border:var(--gloskin-border)' \
  '--gloskin-field-bg:var(--gloskin-surface)' \
  '--gloskin-field-focus-ring:0 0 0 3px var(--gloskin-accent-soft)'; do
  grep -qF -- "$expected" "$core_base_css" || { echo "Form Kit field token missing: $expected" >&2; exit 1; }
done

# The canonical primitive stays one shared declaration, not copy/pasted per
# Woo selector (input/select/textarea + Woo input.input-text/select/textarea).
grep -qF -- '.gloskin-ui1-form input:not([type="checkbox"]):not([type="radio"]),.gloskin-ui1-form select,.gloskin-ui1-form textarea,.gloskin-ui1 .woocommerce input.input-text,.gloskin-ui1 .woocommerce select,.gloskin-ui1 .woocommerce textarea{width:100%;min-height:var(--gloskin-field-height)' "$core_base_css" \
  || { echo "Form Kit canonical field primitive missing or no longer shared" >&2; exit 1; }

# Woo cart/checkout/account/product coverage: quantity, invalid state,
# checkbox/radio, Select2 adapter, notices, autofill, WC Blocks bridge.
for expected in \
  '.gloskin-ui1 .woocommerce .quantity .qty{width:76px' \
  '.gloskin-ui1 .woocommerce form .form-row.woocommerce-invalid input.input-text' \
  '.gloskin-ui1 input[type="checkbox"],.gloskin-ui1 input[type="radio"]{width:18px;height:18px' \
  '.gloskin-ui1 .select2-container .select2-selection--single{height:var(--gloskin-field-height)' \
  '.gloskin-ui1 .select2-dropdown{' \
  '.gloskin-ui1 .woocommerce-error,.gloskin-ui1 .woocommerce-message,.gloskin-ui1 .woocommerce-info{' \
  '.gloskin-ui1 input:-webkit-autofill' \
  '.gloskin-ui1 .wc-block-components-text-input input'; do
  grep -qF -- "$expected" "$core_css" || { echo "Form Kit Woo coverage missing: $expected" >&2; exit 1; }
done

# My-account fields stay on the same field kit as cart/checkout instead of a
# separate/older set of values.
grep -qF -- '.woocommerce-account .woocommerce form .form-row input.input-text,.woocommerce-account .woocommerce form .form-row textarea,.woocommerce-account .woocommerce form select{min-height:var(--gloskin-field-height)' "$readiness_css" \
  || { echo "account-page fields diverged from the Form Kit tokens" >&2; exit 1; }

# Keyboard focus stays visible and obvious: the global focus-visible ring is
# untouched, and fields add their own accent border + soft ring on top of it.
grep -qF -- '.gloskin-ui1 :focus-visible{outline:3px solid var(--gloskin-accent-readable)' "$core_base_css" \
  || { echo "global focus-visible ring regressed" >&2; exit 1; }
grep -qF -- ':focus-visible{outline:none;border-color:var(--gloskin-accent);background:var(--gloskin-field-bg-focus);box-shadow:var(--gloskin-field-focus-ring)}' "$core_base_css" \
  || { echo "Form Kit focus-visible field ring missing" >&2; exit 1; }

# A real mobile rule exists for the kit.
grep -qF -- '@media (max-width:760px){.gloskin-ui1 .woocommerce .quantity .qty{width:84px}' "$core_css" \
  || { echo "Form Kit mobile rule missing" >&2; exit 1; }

# Every new Form Kit selector stays scoped under .gloskin-ui1 -- no bare
# select2/checkbox/radio/qty rule leaking outside Gloskin-owned markup.
if grep -nE '^\.select2-|^input\[type="checkbox"\]|^input\[type="radio"\]|^\.quantity \.qty|^\.wc-block-components-' "$core_css" "$core_base_css"; then
  echo "Form Kit selector leaks outside .gloskin-ui1 scope" >&2
  exit 1
fi

# No WooCommerce template override was introduced to achieve this styling --
# presentation must stay CSS-only and survive Woo updates.
if find "$templates" \( -iname 'cart.php' -o -iname 'checkout.php' -o -iname 'form-billing.php' \
  -o -iname 'form-shipping.php' -o -iname 'payment.php' -o -iname 'form-login.php' \
  -o -iname 'form-edit-address.php' \) | grep -q .; then
  echo "a WooCommerce template override was introduced; presentation must stay CSS-only" >&2
  exit 1
fi

# Gloskin Commerce Interaction Bridge v1 -- native Woo add-to-cart/cart
# fragments/wishlist behavior only, no second cart/checkout engine.
adapter="$plugin_root/includes/class-gloskin-site-core-woocommerce-adapter.php"
asset_service="$plugin_root/includes/class-gloskin-site-core-asset-service.php"

# A/B/C (product-card native contract, ajax eligibility never hand-invented,
# JS-disabled fallback) are covered behaviorally by
# tests/product-card-commerce-contract.php, run via check-runtime.sh.

# D. Native added_to_cart opens the existing Gloskin cart sheet, and the
# busy-state bridge exists -- presentation only, no cart mutation.
init_cart_block="$(awk '/function initCart\(\) \{/,/^\t\}$/' "$core_js")"
[[ -n "$init_cart_block" ]] || { echo "initCart() bridge function not found" >&2; exit 1; }
echo "$init_cart_block" | grep -q "added_to_cart" || { echo "added_to_cart bridge missing from initCart()" >&2; exit 1; }
echo "$init_cart_block" | grep -qF "overlay.open('cart')" || { echo "cart sheet open call missing from initCart()" >&2; exit 1; }
echo "$init_cart_block" | grep -q "aria-busy" || { echo "aria-busy busy-state bridge missing from initCart()" >&2; exit 1; }

# E. Woo fragments still update cart count, screen-reader count, and the
# cart-sheet body -- the one canonical mini-cart update contract.
for expected in \
  "\$fragments['.gloskin-ui1-badge[data-gloskin-cart-count]']" \
  "\$fragments['[data-gloskin-cart-count-sr]']" \
  "\$fragments['.gloskin-ui1-cart-sheet__body']"; do
  grep -qF -- "$expected" "$adapter" || { echo "Woo cart fragment contract missing: $expected" >&2; exit 1; }
done

# F. Mini-cart remove stays native Woo-fragment-driven -- no custom remove
# endpoint, just the a.remove markup wc-cart-fragments.js already binds to.
grep -qF -- 'class="remove gloskin-ui1-cart-sheet__item-remove"' "$adapter" \
  || { echo "native Woo remove-link markup missing from mini-cart" >&2; exit 1; }

# G. Wishlist toggle stays localStorage-only -- no network mutation request.
wishlist_toggle="$(awk '/function toggle\(productId\) \{/,/^\t\t\}$/' "$core_js")"
[[ -n "$wishlist_toggle" ]] || { echo "wishlist toggle() function not found" >&2; exit 1; }
if echo "$wishlist_toggle" | grep -qE 'fetch\(|XMLHttpRequest|\.ajax\(|\$\.post\(|\$\.get\('; then
  echo "wishlist toggle performs a network mutation request; must stay localStorage-only" >&2
  exit 1
fi

# H/I. No new Gloskin nopriv cart/checkout AJAX endpoint, custom REST cart/
# checkout mutation route, or custom cart/checkout fetch()/XHR call exists.
commerce_bridge_files=( "$adapter" "$asset_service" "$core_js" "$helpers" )
if grep -RInE 'wp_ajax_(nopriv_)?gloskin_(add_to_cart|cart|checkout)' "${commerce_bridge_files[@]}"; then
  echo "a custom Gloskin cart/checkout AJAX endpoint was introduced" >&2
  exit 1
fi
if grep -RInE "register_rest_route\([^)]*['\"](cart|checkout)" "${commerce_bridge_files[@]}"; then
  echo "a custom Gloskin cart/checkout REST endpoint was introduced" >&2
  exit 1
fi
if grep -RInE 'fetch\([^)]*(cart|checkout|add-to-cart|add_to_cart)' "$core_js"; then
  echo "a custom cart/checkout fetch()/XHR call was introduced; Woo must own cart/checkout mutation" >&2
  exit 1
fi

# Ajax eligibility is read from WC_Product/Woo settings, never hand-invented,
# and AssetService (the sole asset owner) only ever enqueues Woo's own
# already-registered handles by handle.
grep -qF -- "supports( 'ajax_add_to_cart' )" "$adapter" \
  || { echo "ajax_add_to_cart eligibility no longer derived from WC_Product::supports()" >&2; exit 1; }
grep -qF -- 'enqueue_native_commerce_scripts' "$asset_service" \
  || { echo "native Woo commerce script enqueue owner missing from AssetService" >&2; exit 1; }
for expected in 'wc-cart-fragments' 'wc-add-to-cart' "woocommerce_enable_ajax_add_to_cart"; do
  grep -qF -- "$expected" "$asset_service" || { echo "native Woo script contract missing: $expected" >&2; exit 1; }
done

echo "presentation safety checks passed (${#required_views[@]} public views, contrast/header/copy polish guarded)"
