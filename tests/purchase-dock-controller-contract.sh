#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin="$repo_root/plugin/gloskin-site-core"
dock_js="$plugin/assets/js/gloskin-ui1-purchase-dock.js"
assets="$plugin/config/assets.php"
core_css="$plugin/assets/css/gloskin-ui1-core.css"
geometry="$plugin/assets/css/gloskin-ui1-single-product-geometry.css"
adapter="$plugin/includes/class-gloskin-site-core-woocommerce-adapter.php"
fail(){ echo "$1" >&2; exit 1; }

[[ -f "$dock_js" ]] || fail "purchase dock controller missing"
grep -qF "document.querySelector('.gloskin-ui1-commerce-native > div.product')" "$dock_js" || fail "dock controller lost direct primary-product scope"
grep -qF "summary.querySelectorAll('[data-gloskin-purchase-dock]')" "$dock_js" || fail "dock controller must require exactly one canonical dock"
grep -qF "dock.querySelectorAll('form.cart').length !== 1" "$dock_js" || fail "dock controller no longer requires exactly one native form.cart"
grep -qF "new IntersectionObserver" "$dock_js" || fail "dock controller must use IntersectionObserver"
grep -qF "new ResizeObserver" "$dock_js" || fail "dock controller must use one ResizeObserver for dynamic form height"
grep -qF -- "--gloskin-purchase-dock-bottom" "$dock_js" || fail "dock bottom safe-area variable missing"
grep -qF "gloskin-ui1-purchase-dock-slot" "$dock_js" || fail "fixed-mode placeholder missing"
grep -qF "dock.style.position = 'fixed'" "$dock_js" || fail "genuine viewport-bottom fixed state missing"
grep -qF "dock.style.position = 'absolute'" "$dock_js" || fail "tabs-boundary settlement state missing"
grep -qF "window.innerHeight >= 560" "$dock_js" || fail "short-viewport degrade threshold missing"
grep -qF "height <= window.innerHeight * 0.55" "$dock_js" || fail "oversized-dock degrade guard missing"

# The dock must float through Tabs AND Related Products, releasing only at
# the end-of-Related boundary -- never settling before/at Tabs (the fixed
# early-release bug).
grep -qF "product.querySelector(':scope > .related.products')" "$dock_js" || fail "release boundary no longer targets Related Products"
grep -qF "related.insertAdjacentElement('afterend', boundary)" "$dock_js" || fail "end-of-Related sentinel is not placed immediately after .related.products"
grep -qF "product.appendChild(boundary)" "$dock_js" || fail "product-end fallback boundary missing for Related-Products-absent case"
if grep -qF "product.querySelector(':scope > .woocommerce-tabs')" "$dock_js"; then
  fail "dock controller regressed to releasing at the Tabs boundary instead of end-of-Related"
fi

if grep -qE "addEventListener\(['\"]scroll|setInterval\(|cloneNode\(|innerHTML\s*=|requestAnimationFrame\([^)]*scroll" "$dock_js"; then
  fail "dock controller introduced a scroll loop, clone/rebuild, or HTML replacement"
fi
if grep -qF '!important' "$dock_js"; then fail "dock controller introduced !important"; fi

grep -qF "'gloskin-ui1-purchase-dock' => array(" "$assets" || fail "dock controller is not registered by canonical AssetService registry"
grep -qF "'src'       => 'assets/js/gloskin-ui1-purchase-dock.js'" "$assets" || fail "dock controller registry path changed"
grep -qF "'deps'      => array( 'gloskin-ui1-core' )" "$assets" || fail "dock controller must load after canonical core interaction owner"

grep -qF 'data-gloskin-purchase-dock' "$adapter" || fail "server purchase-dock wrapper missing"
grep -qF 'private static $purchase_dock_rendered = false;' "$adapter" || fail "one-shot server dock guard missing"
if grep -qE 'cloneNode|form\.cart.*innerHTML' "$dock_js"; then fail "native form.cart is being rebuilt"; fi

for expected in \
  '>.woocommerce-product-gallery,' \
  '>.summary{float:none;clear:none;width:100%;max-width:none;margin:0;box-sizing:border-box}' \
  '.related.products ul.products::before,' \
  '.related.products ul.products::after{content:none;display:none}' \
  '.related.products ul.products li.product{float:none;clear:none;width:100%;max-width:none;margin:0;box-sizing:border-box}'; do
  grep -qF -- "$expected" "$geometry" || fail "ghost-space normalization regressed: $expected"
done

grep -qF '.gloskin-ui1-purchase-dock{' "$core_css" || fail "purchase dock visual surface left canonical core CSS"
if grep -qE 'gloskin-ui1-purchase-dock\{[^}]*overflow-(x|y):(auto|scroll)|gloskin-ui1-purchase-dock\{[^}]*overflow:(auto|scroll)' "$core_css"; then
  fail "purchase dock must not gain an internal scrollbar"
fi

echo "purchase dock controller contract passed"
