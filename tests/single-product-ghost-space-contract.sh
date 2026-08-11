#!/usr/bin/env bash
# Focused static guard for the Woo classic-layout -> Gloskin primary PDP grid boundary.
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_root="$repo_root/plugin/gloskin-site-core"
geometry="$plugin_root/assets/css/gloskin-ui1-single-product-geometry.css"
assets="$plugin_root/config/assets.php"
runner="$repo_root/tests/check-runtime.sh"

fail() { echo "$1" >&2; exit 1; }
[[ -f "$geometry" ]] || fail "single-product ghost-space geometry stylesheet missing"

grep -qF 'body.single-product.gloskin-ui1 .gloskin-ui1-commerce-native>div.product{row-gap:0}' "$geometry" \
  || fail "ghost-space: primary product root must neutralize implicit section row gap"
grep -qF '>div.product>.woocommerce-product-gallery,' "$geometry" \
  || fail "ghost-space: primary gallery direct-child reset missing"
grep -qF '>div.product>.summary{float:none;clear:none;width:100%;max-width:none;margin:0;box-sizing:border-box}' "$geometry" \
  || fail "ghost-space: Woo gallery/summary legacy float/48%/2em reset missing"
grep -qF '.related.products ul.products::before,' "$geometry" \
  || fail "ghost-space: Related Products clearfix ::before reset missing"
grep -qF '.related.products ul.products::after{content:none;display:none}' "$geometry" \
  || fail "ghost-space: Related Products clearfix pseudo reset missing"
grep -qF '.related.products ul.products li.product{float:none;clear:none;width:100%;max-width:none;margin:0;box-sizing:border-box}' "$geometry" \
  || fail "ghost-space: Woo related-card 22.05%/float/margin reset missing"
grep -qF '@media (max-width:1040px)' "$geometry" \
  || fail "ghost-space: responsive summary-spacing rule missing"
grep -qF '>div.product>.summary{margin-top:clamp(24px,4vw,32px)}' "$geometry" \
  || fail "ghost-space: mobile gallery->summary intentional spacing missing"
if grep -qF '!important' "$geometry"; then
  fail "ghost-space: new geometry owner must not add !important"
fi

grep -qF "'gloskin-ui1-single-product-geometry'" "$assets" \
  || fail "ghost-space: geometry stylesheet is not registered by canonical AssetService registry"
grep -qF "'deps'  => array( 'gloskin-ui1-single-product-geometry' )" "$assets" \
  || fail "ghost-space: readiness layer must depend on the geometry normalization"
grep -qF 'single-product-ghost-space-contract.sh' "$runner" \
  || fail "ghost-space: focused static contract not wired into runtime checks"
grep -qF 'single-product-ghost-space-browser-smoke.py' "$runner" \
  || fail "ghost-space: computed-geometry browser regression not wired into runtime checks"

echo "single-product ghost-space contract passed"
