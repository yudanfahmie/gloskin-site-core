#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_root="$repo_root/plugin/gloskin-site-core"
templates="$plugin_root/templates"
public_runtime=(
  "$templates"
  "$plugin_root/includes/class-gloskin-site-core-navigation-service.php"
  "$plugin_root/includes/class-gloskin-site-core-template-service.php"
  "$plugin_root/includes/class-gloskin-site-core-woocommerce-adapter.php"
  "$plugin_root/assets/css/gloskin-ui1-core.css"
  "$plugin_root/assets/js/gloskin-ui1-core.js"
)

visitor_leaks='not configured|content pending|missing data|architecture supports|approved doctor profiles|approved treatment categories|woocommerce product data is currently unavailable|coming soon|lorem ipsum|dummy|developer placeholder|debug message'
if grep -RInEi "$visitor_leaks" "${public_runtime[@]}" --include='*.php' --include='*.js' --include='*.css'; then
  echo "client-facing staging/dummy language found in public runtime" >&2
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

# Factual images must come from WordPress attachment rendering; presentation
# media is CSS-driven decorative markup and carries aria-hidden="true".
if grep -RInE '<img[[:space:]]' "$templates" --include='*.php'; then
  echo "manual img markup found in public templates; use wp_get_attachment_image" >&2
  exit 1
fi
if ! grep -q 'function gloskin_ui1_render_presentation_media' "$templates/parts/template-helpers.php" \
  || ! grep -q 'gloskin-ui1-media--hero' "$plugin_root/assets/css/gloskin-ui1-core.css"; then
  echo "production presentation media system missing" >&2
  exit 1
fi
if grep -RInE "url\([\"']?https?://" "$plugin_root/assets" --include='*.css' --include='*.js'; then
  echo "critical first-party presentation asset depends on a remote URL" >&2
  exit 1
fi

required_views=(home about treatments treatment skincare skincare-category clinics clinic doctors doctor contact insights shop)
for view in "${required_views[@]}"; do
  [[ -f "$templates/pages/$view.php" ]] || { echo "missing public view: $view" >&2; exit 1; }
done

echo "presentation safety checks passed (${#required_views[@]} public views, abstract media enabled)"
