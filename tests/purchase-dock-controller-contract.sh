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
grep -qF "function anchorGeometry()" "$dock_js" || fail "stable normal-flow geometry anchor missing"
grep -qF "var slotRect = slot.getBoundingClientRect();" "$dock_js" || fail "dock width must be measured from preserved summary slot"
grep -qF "var DESKTOP_MIN_WIDTH = 1024;" "$dock_js" || fail "desktop dock width threshold missing"
grep -qF "var DESKTOP_MAX_WIDTH = 720;" "$dock_js" || fail "desktop dock width cap missing"
grep -qF "dock.style.width = anchor.width + 'px';" "$dock_js" || fail "dock does not use stable anchor width"
grep -qF "dock.style.left = anchor.left + 'px';" "$dock_js" || fail "floating dock does not use stable anchor left"
grep -qF "dock.style.top = (anchor.slotRect.top - productRect.top) + 'px';" "$dock_js" || fail "boundary dock must settle over original purchase slot"
grep -qF "function currentMarkerPosition()" "$dock_js" || fail "dock state resolver must read current marker geometry to avoid observer callback ordering races"
grep -qF "var boundaryBottomMargin = Math.max(0, BOTTOM_GAP + height + BOUNDARY_GAP);" "$dock_js" || fail "Related release line must account for the live dock height"
if grep -qF "dock.style.width = productRect.width + 'px';" "$dock_js"; then fail "dock regressed to full product-container width"; fi

grep -qF "gloskin-ui1-purchase-dock-end" "$dock_js" || fail "end-of-Related release sentinel missing"
grep -qF "related.insertAdjacentElement('afterend', boundary)" "$dock_js" || fail "release boundary must stay after Related Products"
grep -qF "product.appendChild(boundary)" "$dock_js" || fail "no-Related product-end boundary fallback missing"
grep -qF "dock.style.position = 'fixed'" "$dock_js" || fail "genuine viewport-bottom fixed state missing"
grep -qF "dock.style.position = 'absolute'" "$dock_js" || fail "end-of-Related boundary settlement state missing"
grep -qF "window.innerHeight >= 560" "$dock_js" || fail "short-viewport degrade threshold missing"
grep -qF "height <= window.innerHeight * 0.55" "$dock_js" || fail "oversized-dock degrade guard missing"

grep -qF "dock.style.transform = 'translateY(100%)'" "$dock_js" || fail "floating-dock entrance transform missing"
grep -qF "dock.style.removeProperty('transform')" "$dock_js" || fail "floating-dock entrance transform is not cleared"
grep -qF "void dock.offsetHeight" "$dock_js" || fail "floating-dock entrance does not establish its start frame"

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

# One cohesive neutral dock surface in core.css; structural Woo wrappers are
# transparent and the global Form/Action Kit remains the CTA/field color owner.
grep -qF '.gloskin-ui1-purchase-dock{z-index:5;width:100%;max-width:calc(100vw - 32px)' "$core_css" || fail "compact dock surface owner missing"
grep -qF 'background:var(--gloskin-bg);color:var(--gloskin-text)' "$core_css" || fail "dock surface must use neutral Gloskin background/text"
grep -qF '.gloskin-ui1-purchase-dock{max-width:720px}' "$core_css" || fail "desktop dock CSS max-width cap missing"
grep -qF '.gloskin-ui1-purchase-dock form.cart{display:grid;grid-template-columns:minmax(0,1fr);gap:12px;margin:0;max-width:none;background:transparent;box-shadow:none}' "$core_css" || fail "dock form is not one compact transparent layout"
grep -qF '.gloskin-ui1-purchase-dock .woocommerce-variation-add-to-cart{display:grid;grid-template-columns:auto minmax(0,1fr)' "$core_css" || fail "quantity + CTA purchase row missing"
grep -qF '.gloskin-ui1-purchase-dock .woocommerce-variation{margin:0;padding:0;border:0;border-radius:0}' "$core_css" || fail "variation result wrapper still carries inner-card chrome"
grep -qF '.gloskin-ui1-purchase-dock .single_add_to_cart_button{width:100%;min-width:0;max-width:100%}' "$core_css" || fail "dock CTA geometry missing"
if grep -qF '.gloskin-ui1-purchase-dock .single_add_to_cart_button{background:var(--gloskin-inverse)' "$core_css"; then
  fail "dock reintroduced inverse/white CTA skin instead of canonical accent CTA"
fi
if grep -qE 'gloskin-ui1-purchase-dock\{[^}]*background:var\(--gloskin-accent\)' "$core_css"; then
  fail "dock regressed to giant accent-red slab"
fi
if grep -qE 'gloskin-ui1-purchase-dock\{[^}]*overflow-(x|y):(auto|scroll)|gloskin-ui1-purchase-dock\{[^}]*overflow:(auto|scroll)' "$core_css"; then
  fail "purchase dock must not gain an internal scrollbar"
fi

echo "purchase dock controller contract passed"
