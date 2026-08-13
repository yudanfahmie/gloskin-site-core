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
grep -qF "productRegion.appendChild(identityBefore);" "$dock_js" || fail "server identity is not moved into the left product region"
grep -qF "productRegion.appendChild(variationTableBefore);" "$dock_js" || fail "native variation table is not moved into the left product region"
grep -qF "actionRegion.appendChild(singleVariationWrapBefore);" "$dock_js" || fail "native variable purchase action is not moved into the right action region"
grep -qF "formBefore.querySelector('.quantity') === quantityBefore" "$dock_js" || fail "quantity node identity assertion missing"
grep -qF "formBefore.querySelector('.single_add_to_cart_button') === submitBefore" "$dock_js" || fail "submit node identity assertion missing"
grep -qF "sameNodeList(variationSelectsBefore, afterSelects)" "$dock_js" || fail "variation-select identity assertion missing"

# Geometry ownership is anchored to Gloskin-only semantic classes added to
# the SAME captured native Woo nodes -- presentation-only CSS hooks, never a
# clone/rebuild -- so the command bar owns its cascade by class instead of
# racing broad native Woo selectors for specificity.
grep -qF "formBefore.classList.add('gloskin-ui1-purchase-dock__form');" "$dock_js" || fail "native form.cart is not given its own CSS ownership class"
grep -qF "variationTableBefore.classList.add('gloskin-ui1-purchase-dock__variants');" "$dock_js" || fail "native table.variations is not given its own CSS ownership class"
grep -qF "singleVariationWrapBefore.classList.add('gloskin-ui1-purchase-dock__variation-action');" "$dock_js" || fail "native .single_variation_wrap is not given its own CSS ownership class"
grep -qF "singleVariationBefore.classList.add('gloskin-ui1-purchase-dock__variation-state');" "$dock_js" || fail "native .woocommerce-variation.single_variation is not given its own CSS ownership class"
grep -qF "quantityBefore.classList.add('gloskin-ui1-purchase-dock__quantity');" "$dock_js" || fail "native .quantity is not given its own CSS ownership class"
grep -qF "submitBefore.classList.add('gloskin-ui1-purchase-dock__submit');" "$dock_js" || fail "native .single_add_to_cart_button is not given its own CSS ownership class"
grep -qF "formBefore.classList.contains('gloskin-ui1-purchase-dock__form')" "$dock_js" || fail "post-composition identity check no longer verifies the ownership class survived on the SAME native form node"

# Compact minus/plus quantity steppers: enhance the SAME native input.qty in
# place -- no clone, no second quantity state, idempotent via a data flag,
# and clicks are handled by exactly one delegated listener bound once on the
# stable dock root (never per-button, never polled/intervalled), resolving
# the current input at click time.
grep -qF "function enhanceQuantityControls(quantity)" "$dock_js" || fail "quantity stepper enhancement owner missing"
grep -qF "if (!quantity || quantity.dataset.gloskinQtyEnhanced === '1') { return; }" "$dock_js" || fail "quantity stepper enhancement is not idempotent"
grep -qF "var input = quantity.querySelector('input.qty');" "$dock_js" || fail "quantity stepper must enhance the SAME native input.qty, never a clone"
grep -qF "input.insertAdjacentElement('beforebegin', minus);" "$dock_js" || fail "minus control must be inserted next to the SAME native input, not clone it"
grep -qF "input.insertAdjacentElement('afterend', plus);" "$dock_js" || fail "plus control must be inserted next to the SAME native input, not clone it"
grep -qF "quantity.dataset.gloskinQtyEnhanced = '1';" "$dock_js" || fail "quantity stepper enhancement flag never set"
grep -qF "enhanceQuantityControls(quantityBefore);" "$dock_js" || fail "quantity stepper enhancement is not wired into dock composition"
if grep -qE "cloneNode\(" "$dock_js"; then fail "dock controller must never clone the native quantity input"; fi
grep -qF "function stepQuantityInput(input, direction)" "$dock_js" || fail "quantity step owner missing"
if grep -qF "input.disabled || input.readOnly" "$dock_js"; then :; else fail "quantity step must respect native disabled/readonly state"; fi
grep -qF "input.dispatchEvent(new Event('input', { bubbles: true }));" "$dock_js" || fail "quantity step must dispatch a native input event"
grep -qF "input.dispatchEvent(new Event('change', { bubbles: true }));" "$dock_js" || fail "quantity step must dispatch a native change event"
grep -qF "if (next < min) { next = min; }" "$dock_js" || fail "quantity step does not clamp to the native min"
grep -qF "if (next > max) { next = max; }" "$dock_js" || fail "quantity step does not clamp to the native max"
grep -qF "dock.addEventListener('click', function (event) {" "$dock_js" || fail "quantity stepper must use one delegated click listener on the dock root"
if grep -qE "addEventListener\(['\"]click['\"].*qty-(minus|plus)" "$dock_js"; then fail "quantity stepper must not bind a listener directly per button"; fi
if grep -qF "setInterval(" "$dock_js"; then fail "quantity stepper must never poll"; fi
grep -qF "quantityBefore.classList.contains('gloskin-ui1-purchase-dock__qty-control')" "$dock_js" || fail "post-composition identity check no longer verifies the qty-control class survived"
grep -qF "quantityBefore.querySelector('input.qty') === quantityInputBefore" "$dock_js" || fail "post-composition identity check no longer verifies the SAME native input.qty node survived"

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

# Enhanced presentation is one deliberate accent command bar. Core CSS stays
# the base/no-JS owner; this geometry file is the sole enhanced composition owner.
grep -qF '>.gloskin-ui1-purchase-dock-home{grid-column:1/-1;width:100%;min-width:0;margin-top:24px}' "$geometry" || fail "full-width dock-home owner missing"
grep -qF '>.gloskin-ui1-purchase-dock-home>.gloskin-ui1-purchase-dock{position:static;grid-column:1/-1;z-index:5;bottom:auto;width:100%;max-width:none;margin:0;padding:12px clamp(18px,2vw,28px);border:0;border-radius:var(--gloskin-radius-sm);background:var(--gloskin-accent);color:var(--gloskin-inverse)' "$geometry" || fail "enhanced accent purchase surface missing"
grep -qF '.gloskin-ui1-purchase-dock__form{display:grid;width:100%;max-width:none;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:clamp(20px,3vw,48px)' "$geometry" || fail "desktop one-row command-bar grid missing"
grep -qF '.gloskin-ui1-purchase-dock__product{display:flex;align-items:center' "$geometry" || fail "left product/variant region missing"
grep -qF '.gloskin-ui1-purchase-dock__action{display:flex;align-items:center;justify-content:flex-end' "$geometry" || fail "right purchase-action region missing"
grep -qF '.gloskin-ui1-purchase-dock__variants select{width:auto;flex:1 1 auto;max-width:100%;min-width:0;min-height:46px;background:var(--gloskin-bg);color:var(--gloskin-text)}' "$geometry" || fail "native variation select lost its light field surface"
grep -qF '.gloskin-ui1-purchase-dock__submit{width:auto;min-width:clamp(160px,13vw,210px);max-width:240px;min-height:46px;padding:10px 18px;background:var(--gloskin-inverse);border-color:var(--gloskin-inverse);color:var(--gloskin-accent-strong)}' "$geometry" || fail "on-accent inverse CTA treatment missing"
grep -qF '.gloskin-ui1-purchase-dock__qty-control{display:flex' "$geometry" || fail "qty-control pill shell missing"
grep -qF '.gloskin-ui1-purchase-dock__qty-minus,' "$geometry" || fail "qty-minus button styling missing"
grep -qF '.gloskin-ui1-purchase-dock__qty-plus{' "$geometry" || fail "qty-plus button styling missing"
# Proven live against the real hydrated staging PDP: WooCommerce's own
# woocommerce.css applies `content:" ";display:table` clearfix pseudo-
# elements to form.cart, which become real (empty) grid items and split the
# one-row command bar into two rows the instant form.cart becomes a CSS
# Grid container. Neutralized at the exact same specificity pattern already
# used for Related Products' ul.products::before/::after clearfix above.
grep -qF '.gloskin-ui1-purchase-dock__form::before,' "$geometry" || fail "form.cart clearfix pseudo-element neutralization missing"
grep -qF '.gloskin-ui1-purchase-dock__form::after{content:none;display:none}' "$geometry" || fail "form.cart clearfix pseudo-element neutralization incomplete"
# A third-party site plugin (wpcodebox2, outside this repository) wraps every
# <table> in document.body -- including the reparented table.variations --
# in an unstyled `.table-container` div via its own MutationObserver, and
# that plugin's own responsive stylesheet gives `.table-container table
# tr`/`td` a card border/radius/padding at its own max-width:768px
# breakpoint. display:contents removes the wrapper from the render tree so
# table.variations stays a direct flex item of __product; the shared
# structural-wrapper reset explicitly also zeroes border/margin (not just
# background/shadow/radius) so that third-party card treatment cannot win.
grep -qF '.table-container{display:contents}' "$geometry" || fail "third-party table-wrapper neutralization missing"
grep -qF 'background:transparent;box-shadow:none;border-radius:0;border:0;margin:0}' "$geometry" || fail "structural wrapper reset no longer also zeroes border/margin"
grep -qF '@media (max-width:680px)' "$geometry" || fail "proven narrow-mobile stacked composition missing"
if grep -qF 'grid-template-columns:minmax(0,1.35fr)' "$geometry"; then fail "old 1.35fr/.65fr dock composition returned"; fi
if grep -qE 'purchase-dock-home.*purchase-dock\.is-floating\{[^}]*background:var\(--gloskin-bg\)' "$geometry"; then fail "floating dock regressed to neutral/white outer background"; fi
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
