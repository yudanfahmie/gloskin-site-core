#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_root="$repo_root/plugin/gloskin-site-core"
templates="$plugin_root/templates"

visitor_leaks='not configured|content pending|missing data|architecture supports|approved doctor profiles|approved treatment categories|WooCommerce product data is currently unavailable|developer placeholder|debug message'
if grep -RInEi "$visitor_leaks" "$templates" --include='*.php'; then
	echo "client-facing implementation language found in public templates" >&2
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


# Public media must come through WordPress attachment rendering, not hand-built
# image URLs that bypass responsive srcset/dimensions/attachment alt behavior.
if grep -RInE '<img[[:space:]]' "$templates" --include='*.php'; then
	echo "manual img markup found in public templates; use wp_get_attachment_image" >&2
	exit 1
fi

required_views=(home about treatments treatment skincare skincare-category clinics clinic doctors doctor contact insights shop)
for view in "${required_views[@]}"; do
	[[ -f "$templates/pages/$view.php" ]] || { echo "missing public view: $view" >&2; exit 1; }
done

echo "presentation safety checks passed (${#required_views[@]} public views)"
