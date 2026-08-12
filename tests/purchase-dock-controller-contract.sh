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
grep -qF "document.querySelector('.gloskin-ui1-commerce-native > div.product')" "$dock_js" || fail "dock controller lost primary-product scope"
grep -qF "summary.querySelectorAll('[data-gloskin-purchase-dock]')" "$dock_js" || fail "dock controller must discover exactly one server-rendered dock in summary"
grep -qF "dock.querySelectorAll('form.cart').length !== 1" "$dock_js" || fail "dock controller no longer requires exactly one native form.cart"
grep -qF "new IntersectionObserver" "$dock_js" || fail "dock controller must use IntersectionObserver"
grep -qF "new ResizeObserver" "$dock_js" || fail "dock controller must use ResizeObserver"
grep -qF -- "--gloskin-purchase-dock-bottom" "$dock_js" || fail "dock bottom safe-area variable missing"

# The same native dock/form is relocated exactly once into a direct full-width
# row after Related Products. This is DOM placement only; no clone/rebuild.
grep -qF "related.insertAdjacentElement('afterend', boundary);" "$dock_js" || fail "dock boundary must be inserted after Related Products"
grep -qF "boundary.insertAdjacentElement('afterend', dock);" "$dock_js" || fail "same dock node must be moved after the Related boundary"
grep -qF "dock.classList.add('is-relocated');" "$dock_js" || fail "relocated-state marker missing"
grep -qF "grid-column:1/-1" "$dock_js" || fail "boundary marker must span the full product grid"
if grep -qE "cloneNode\(|innerHTML\s*=|outerHTML\s*=" "$dock_js"; then fail "dock controller must never clone/rebuild native Woo markup"; fi

# Full-block fixed geometry is product-lane geometry, never summary-slot or a
# hard desktop cap. Floating state is viewport-fixed and independent of the
# Related/summary containing blocks.
grep -qF "function fullBlockGeometry()" "$dock_js" || fail "full-block geometry owner missing"
grep -qF "var rect = product.getBoundingClientRect();" "$dock_js" || fail "full-block geometry must measure the primary PDP lane"
grep -qF "dock.style.width = geometry.width + 'px';" "$dock_js" || fail "dock does not use full-block width"
grep -qF "dock.style.left = geometry.left + 'px';" "$dock_js" || fail "floating dock does not use full-block left edge"
grep -qF "dock.style.position = 'fixed';" "$dock_js" || fail "viewport-bottom floating state missing"
if grep -qF "DESKTOP_MAX_WIDTH" "$dock_js"; then fail "dock reintroduced a desktop width cap"; fi
if grep -qF "slot.getBoundingClientRect" "$dock_js"; then fail "dock width regressed to summary-slot ownership"; fi

# Lifecycle: once DOM is ready, one frame resolves relocated layout and the
# dock reveals by transform+opacity. It stays floating until its real post-
# Related flow position reaches the floating footprint, then stops there.
grep -qF "window.requestAnimationFrame(function ()" "$dock_js" || fail "DOM-ready one-frame settle missing"
grep -qF "translateY(calc(100% + 24px))" "$dock_js" || fail "slide-up entrance transform missing"
grep -qF "dock.style.opacity = '0';" "$dock_js" || fail "entrance opacity start missing"
grep -qF "dock.style.opacity = '1';" "$dock_js" || fail "entrance opacity completion missing"
grep -qF "function boundaryReachedNow()" "$dock_js" || fail "post-Related stop-line geometry missing"
grep -qF "var releaseLine = window.innerHeight - BOTTOM_GAP - height;" "$dock_js" || fail "release line must match the floating dock top"
grep -qF "setState(boundaryReached ? 'boundary' : 'floating', animate);" "$dock_js" || fail "dock must float until the Related boundary, then stop"
grep -qF "clearFloatingGeometry();" "$dock_js" || fail "boundary state must return the moved dock to normal flow"
grep -qF "window.innerHeight >= MIN_FLOAT_HEIGHT" "$dock_js" || fail "short-viewport degrade guard missing"
grep -qF "height <= window.innerHeight * 0.55" "$dock_js" || fail "oversized-dock degrade guard missing"

# Anti-flicker and fail-safe: scripting-capable first paint is suppressed only
# until relocation, but native no-JS behaviour is not hidden. A timed fail-safe
# restores visibility if enhancement fails after scripting started.
grep -qF '@media (scripting:enabled)' "$geometry" || fail "scripting-aware anti-flicker gate missing"
grep -qF '.gloskin-ui1-purchase-dock:not(.is-relocated){visibility:hidden;animation:gloskin-purchase-dock-failsafe 0s linear 900ms forwards}' "$geometry" || fail "anti-flicker selector/fail-safe missing"
grep -qF "var safetyReveal = window.setTimeout" "$dock_js" || fail "runtime anti-flicker fail-safe missing"
grep -qF "window.clearTimeout(safetyReveal);" "$dock_js" || fail "successful init must clear safety reveal timer"

# Enhanced presentation is intentionally not a card/surface. Full-width form
# controls distribute horizontally on desktop; only real field/CTA primitives
# keep their own surfaces.
grep -qF '>.gloskin-ui1-purchase-dock.is-relocated{grid-column:1/-1;position:relative;z-index:5;bottom:auto;width:100%;max-width:none;margin:0;padding:0;border:0;border-radius:0;background:transparent' "$geometry" || fail "relocated full-width transparent dock owner missing"
grep -qF '>.gloskin-ui1-purchase-dock.is-relocated form.cart{display:grid;width:100%;max-width:none;grid-template-columns:minmax(0,1.35fr) minmax(340px,.65fr)' "$geometry" || fail "desktop full-block purchase layout missing"
grep -qF '>.gloskin-ui1-purchase-dock.is-relocated table.variations tr{display:grid;width:100%;grid-template-columns:auto minmax(0,1fr)' "$geometry" || fail "variation row is not compact/horizontal"
grep -qF '@media (max-width:760px)' "$geometry" || fail "narrow-screen stacked dock layout missing"
if grep -qF '!important' "$geometry"; then fail "single-product geometry introduced !important"; fi

# Canonical ownership remains unchanged.
grep -qF "'gloskin-ui1-purchase-dock' => array(" "$assets" || fail "dock controller is not registered by canonical AssetService"
grep -qF "'src'       => 'assets/js/gloskin-ui1-purchase-dock.js'" "$assets" || fail "dock controller registry path changed"
grep -qF 'data-gloskin-purchase-dock' "$adapter" || fail "server purchase-dock wrapper missing"
grep -qF 'private static $purchase_dock_rendered = false;' "$adapter" || fail "one-shot server dock guard missing"
if grep -qE "addEventListener\(['\"]scroll|setInterval\(" "$dock_js"; then fail "dock controller introduced a scroll loop/polling owner"; fi
if grep -qF '!important' "$dock_js"; then fail "dock controller introduced !important"; fi

for expected in \
  '>.woocommerce-product-gallery,' \
  '>.summary{float:none;clear:none;width:100%;max-width:none;margin:0;box-sizing:border-box}' \
  '.related.products ul.products::before,' \
  '.related.products ul.products::after{content:none;display:none}' \
  '.related.products ul.products li.product{float:none;clear:none;width:100%;max-width:none;margin:0;box-sizing:border-box}'; do
  grep -qF -- "$expected" "$geometry" || fail "ghost-space normalization regressed: $expected"
done

echo "purchase dock controller contract passed"
