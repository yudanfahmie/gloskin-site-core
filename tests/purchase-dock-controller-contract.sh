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

# The SAME native dock/form is reparented exactly once into its real,
# full-width, normal-flow home directly after Related Products. This is DOM
# placement only; no clone/rebuild, and the identity of the native form node
# is captured before the move and checked again after settle.
grep -qF "var formBefore = dock.querySelector('form.cart');" "$dock_js" || fail "native form node identity must be captured before relocation"
grep -qF "origin.className = 'gloskin-ui1-purchase-dock-origin';" "$dock_js" || fail "inert activation marker at the original purchase location is missing"
grep -qF "origin.setAttribute('aria-hidden', 'true');" "$dock_js" || fail "origin marker must be aria-hidden"
grep -qF "dock.parentNode.insertBefore(origin, dock);" "$dock_js" || fail "origin marker must be inserted where the dock originally lived"
grep -qF "home.className = 'gloskin-ui1-purchase-dock-home';" "$dock_js" || fail "dock home element missing"
grep -qF "related.insertAdjacentElement('afterend', home);" "$dock_js" || fail "dock home must be inserted directly after Related Products"
grep -qF "product.appendChild(home);" "$dock_js" || fail "dock home must fall back to the end of the primary product root when Related is absent"
grep -qF "home.appendChild(dock);" "$dock_js" || fail "the SAME dock node must be reparented into its home"
if grep -qE "cloneNode\(|innerHTML\s*=|outerHTML\s*=" "$dock_js"; then fail "dock controller must never clone/rebuild native Woo markup"; fi

# Full-width fixed geometry is PDP-container geometry, never a summary/
# purchase-slot rect and never a hard desktop cap.
grep -qF "function fullWidthGeometry()" "$dock_js" || fail "full-width geometry owner missing"
grep -qF "var rect = container.getBoundingClientRect();" "$dock_js" || fail "full-width geometry must measure the primary PDP container, not a slot"
grep -qF "dock.style.width = geometry.width + 'px';" "$dock_js" || fail "dock does not use full-width container width"
grep -qF "dock.style.left = geometry.left + 'px';" "$dock_js" || fail "floating dock does not use full-width container left edge"
grep -qF "dock.style.position = 'fixed';" "$dock_js" || fail "viewport-bottom floating state missing"
if grep -qF "DESKTOP_MAX_WIDTH" "$dock_js"; then fail "dock reintroduced a desktop width cap"; fi
if grep -qF "slot.getBoundingClientRect" "$dock_js"; then fail "dock width regressed to summary-slot ownership"; fi
if grep -qF "anchorGeometry" "$dock_js"; then fail "dock reintroduced the old anchorGeometry() width model"; fi
if grep -qF "widthCap" "$dock_js"; then fail "dock reintroduced an arbitrary width cap"; fi

# Home reserves the dock's real measured height while floating (intentional
# occupancy, not ghost space) and releases it once the dock settles back
# into normal flow; the placeholder is never reserved back inside .summary.
grep -qF "function reserveHomeHeight()" "$dock_js" || fail "home height reservation owner missing"
grep -qF "home.style.minHeight = dockHeight() + 'px';" "$dock_js" || fail "home does not reserve the dock's real measured height while floating"
grep -qF "function releaseHomeHeight()" "$dock_js" || fail "home height release owner missing"
grep -qF "home.style.removeProperty('min-height');" "$dock_js" || fail "home does not release its reserved height once the dock settles"
if grep -qF "summary.style.minHeight" "$dock_js"; then fail "dock controller reserved ghost space back inside .summary"; fi

# Lifecycle: once DOM is ready, one frame resolves full-width home-anchored
# layout and the dock reveals by transform+opacity. It stays floating until
# its own footprint would reach its real home, then settles there in normal
# flow -- never re-entering Footer because the home lives before Footer.
grep -qF "window.requestAnimationFrame(function ()" "$dock_js" || fail "DOM-ready one-frame settle missing"
grep -qF "translateY(calc(100% + 20px))" "$dock_js" || fail "slide-up entrance transform missing"
grep -qF "dock.style.opacity = '0';" "$dock_js" || fail "entrance opacity start missing"
grep -qF "dock.style.opacity = '1';" "$dock_js" || fail "entrance opacity completion missing"
grep -qF "function homeReachedNow()" "$dock_js" || fail "post-Related home release-line geometry missing"
grep -qF "var releaseLine = window.innerHeight - BOTTOM_GAP - height;" "$dock_js" || fail "release line must match the floating dock's own footprint"
grep -qF "setState(atHome ? 'home' : 'floating', animate);" "$dock_js" || fail "dock must float until its home is reached, then settle there"
grep -qF "clearFloatingGeometry();" "$dock_js" || fail "home state must return the moved dock to normal flow"
grep -qF "window.innerHeight >= MIN_FLOAT_HEIGHT" "$dock_js" || fail "short-viewport degrade guard missing"
grep -qF "height <= window.innerHeight * 0.55" "$dock_js" || fail "oversized-dock degrade guard missing"
grep -qF "'preparing'" "$dock_js" || fail "preparing state missing"
if grep -qE "'mounting'|'boundary'|'normal'" "$dock_js"; then fail "dock controller retained the superseded 4-state summary/boundary model"; fi

# Anti-flicker and fail-safe: scripting-capable first paint is suppressed only
# until the dock is marked ready, but native no-JS behaviour is not hidden. A
# timed fail-safe restores visibility if enhancement fails after scripting
# started, and the CSS-only preparing state has no implied transition.
grep -qF '@media (scripting:enabled)' "$geometry" || fail "scripting-aware anti-flicker gate missing"
grep -qF '.gloskin-ui1-purchase-dock:not(.is-ready){visibility:hidden;animation:gloskin-purchase-dock-failsafe 0s linear 900ms forwards}' "$geometry" || fail "anti-flicker selector/fail-safe missing"
grep -qF '.gloskin-ui1-purchase-dock.is-preparing{visibility:hidden;opacity:0;transition:none}' "$geometry" || fail "CSS preparing-state guard missing"
grep -qF "var safetyReveal = window.setTimeout" "$dock_js" || fail "runtime anti-flicker fail-safe missing"
grep -qF "window.clearTimeout(safetyReveal);" "$dock_js" || fail "successful init must clear safety reveal timer"

# Enhanced presentation is intentionally not a card/surface. Full-width form
# controls distribute horizontally on desktop; only real field/CTA primitives
# keep their own surfaces. The home wrapper spans the full grid row.
grep -qF '>.gloskin-ui1-purchase-dock-home{grid-column:1/-1;width:100%;min-width:0}' "$geometry" || fail "full-width dock-home owner missing"
grep -qF '>.gloskin-ui1-purchase-dock-home>.gloskin-ui1-purchase-dock{position:static;grid-column:1/-1;z-index:5;bottom:auto;width:100%;max-width:none;margin:0;padding:clamp(14px,1.4vw,18px) 0;border:0;border-radius:0;background:transparent' "$geometry" || fail "home-anchored full-width transparent dock owner missing"
grep -qF '>.gloskin-ui1-purchase-dock-home>.gloskin-ui1-purchase-dock form.cart{display:grid;width:100%;max-width:none;grid-template-columns:minmax(0,1.35fr) minmax(340px,.65fr)' "$geometry" || fail "desktop full-width purchase layout missing"
grep -qF '>.gloskin-ui1-purchase-dock-home>.gloskin-ui1-purchase-dock table.variations tr{display:grid;width:100%;grid-template-columns:auto minmax(0,1fr)' "$geometry" || fail "variation row is not compact/horizontal"
grep -qF '@media (max-width:760px)' "$geometry" || fail "narrow-screen stacked dock layout missing"
if grep -qF '!important' "$geometry"; then fail "single-product geometry introduced !important"; fi
if grep -qF 'max-width:720px' "$geometry" "$core_css"; then fail "an old-contract 720px desktop width cap still exists"; fi
if grep -qF '.is-relocated' "$geometry" "$dock_js"; then fail "dock reintroduced the superseded summary-slot .is-relocated absolute-boundary model"; fi

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
