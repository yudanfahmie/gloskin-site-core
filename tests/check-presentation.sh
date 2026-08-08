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
# production layers must not own logged-in offset variables.
if grep -Eq 'gloskin-ui1-admin-bar-(height|gap)|gloskin-ui1-header-offset' "$production_css" "$core_base_css"; then
  echo "foundation/production CSS still competes for sticky admin-bar offset ownership" >&2
  exit 1
fi
for expected in \
  '--gloskin-ui1-admin-bar-height:32px' \
  '--gloskin-ui1-admin-bar-gap:8px' \
  '--gloskin-ui1-admin-bar-height:46px' \
  '--gloskin-ui1-header-offset:calc('; do
  grep -q -- "$expected" "$core_css" || { echo "canonical core admin-bar rule missing: $expected" >&2; exit 1; }
done
if ! grep -q '@media (max-width:600px).*--gloskin-ui1-admin-bar-height:0px.*--gloskin-ui1-admin-bar-gap:0px' "$core_css"; then
  echo "core CSS does not clear fixed toolbar offset at <=600px" >&2
  exit 1
fi

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

required_views=(home about treatments treatment skincare skincare-category clinics clinic doctors doctor contact insights shop)
for view in "${required_views[@]}"; do
  [[ -f "$templates/pages/$view.php" ]] || { echo "missing public view: $view" >&2; exit 1; }
done

closing_views=(about treatments treatment skincare-category clinics clinic doctors doctor)
for view in "${closing_views[@]}"; do
  grep -q 'data-gloskin-section=".*closing"' "$templates/pages/$view.php" || { echo "required closing composition missing: $view" >&2; exit 1; }
done

echo "presentation safety checks passed (${#required_views[@]} public views, contrast/header/copy polish guarded)"
