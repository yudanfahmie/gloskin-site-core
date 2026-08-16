#!/usr/bin/env bash
# Focused presentation/micro-feedback regression contract.
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_root="$repo_root/plugin/gloskin-site-core"
core_js="$plugin_root/assets/js/gloskin-ui1-core.js"
core_css="$plugin_root/assets/css/gloskin-ui1-core.css"
commerce_css="$plugin_root/assets/css/gloskin-ui1-commerce-polish.css"
readiness_css="$plugin_root/assets/css/gloskin-ui1-readiness.css"
production_css="$plugin_root/assets/css/gloskin-ui1-production.css"
adapter="$plugin_root/includes/class-gloskin-site-core-woocommerce-adapter.php"

fail() { echo "$1" >&2; exit 1; }

# One shared presentation-only success sound; badge delta is now the sole
# visually dominant commerce success motion owner.
grep -qF 'function successFeedback(type, runtime)' "$core_js" || fail "success feedback: shared helper missing"
grep -qF "type !== 'cart' && type !== 'wishlist'" "$core_js" || fail "success feedback: helper accepts unsupported types"
grep -qF "var SUCCESS_SOUND_URI = 'data:audio/wav;base64," "$core_js" || fail "success feedback: embedded WAV data URI missing"
[[ "$(grep -c 'new root.Audio(SUCCESS_SOUND_URI)' "$core_js")" == "1" ]] || fail "success feedback: expected exactly one reusable Audio construction site"
grep -qF 'SUCCESS_SOUND_COOLDOWN_MS = 280' "$core_js" || fail "success feedback: audio cooldown missing"
grep -qF "root.document.visibilityState !== 'visible'" "$core_js" || fail "success feedback: hidden-tab audio guard missing"
grep -qF 'playback.catch(function () {})' "$core_js" || fail "success feedback: audio rejection is not swallowed"
if grep -qF "'is-success-pulse'" "$core_js"; then fail "success feedback: parent utility pulse still has a JS motion owner"; fi

# ONE badge-delta presentation helper reads already-rendered DOM and never
# writes cart/wishlist business state.
grep -qF 'var commerceBadgeLastRendered = { cart: null, wishlist: null };' "$core_js" || fail "badge delta: presentation cache missing"
grep -qF 'function initializeCommerceBadgeCounts(runtime)' "$core_js" || fail "badge delta: SSR initializer missing"
grep -qF 'function animateCommerceBadgeDelta(type, runtime)' "$core_js" || fail "badge delta: shared helper missing"
grep -qF "return '[data-gloskin-cart-count]'" "$core_js" || fail "badge delta: cart DOM read missing"
grep -qF "return '[data-gloskin-wishlist-count]'" "$core_js" || fail "badge delta: wishlist DOM read missing"
badge_block="$(awk '/function animateCommerceBadgeDelta\(type, runtime\) \{/,/^[[:space:]]*\/\* Woo owns the remove link/' "$core_js")"
if echo "$badge_block" | grep -qE 'textContent[[:space:]]*='; then fail "badge delta: helper writes canonical count"; fi

# Cart feedback stays on the existing confirmed Woo lifecycle only. The rAF
# runs after Woo fragment listeners have committed replacement DOM.
[[ "$(grep -c "jQuery(document.body).on('added_to_cart', function" "$core_js")" == "1" ]] || fail "cart feedback: expected one canonical added_to_cart success listener"
grep -qF "animateCommerceBadgeDelta('cart');" "$core_js" || fail "cart feedback: badge delta not connected to existing lifecycle"
[[ "$(grep -c "successFeedback('cart')" "$core_js")" == "1" ]] || fail "cart feedback: expected one success-sound call"

# Wishlist keeps its one localStorage/updateBadges owner. Badge motion runs
# only after the canonical writer and celebration remains SAVE-only.
grep -qF 'function saveIds(ids)' "$core_js" || fail "wishlist feedback: canonical persistence helper missing"
grep -qF 'function updateBadges()' "$core_js" || fail "wishlist feedback: canonical badge writer missing"
grep -qF "updateBadges();" "$core_js" || fail "wishlist feedback: canonical badge update missing"
grep -qF "animateCommerceBadgeDelta('wishlist');" "$core_js" || fail "wishlist feedback: badge delta missing"
[[ "$(grep -c "successFeedback('wishlist')" "$core_js")" == "1" ]] || fail "wishlist feedback: expected one save-only success-sound call"
grep -qF "if (!wasActive && active) { successFeedback('wishlist'); }" "$core_js" || fail "wishlist feedback: sound is not gated to confirmed save transition"

# Remove control: one existing action primitive + one danger modifier. Native
# Woo removal attributes/classes stay in PHP, wishlist localStorage toggle stays
# in JS, and both keep the one shared remove icon primitive.
grep -qF '.gloskin-ui1-action-icon,.gloskin-ui1-sheet__close,.gloskin-ui1-quickadd__close' "$core_css" || fail "action kit: shared action primitive missing"
grep -qF '.gloskin-ui1-action-icon--danger{' "$commerce_css" || fail "action kit: danger modifier missing"
grep -qF 'width:38px;' "$commerce_css" || fail "action kit: danger width is not close-button geometry"
grep -qF 'height:38px;' "$commerce_css" || fail "action kit: danger height is not close-button geometry"
grep -qF 'place-items:center;' "$commerce_css" || fail "action kit: danger centering missing"
grep -qF 'border-radius:999px;' "$commerce_css" || fail "action kit: danger radius missing"
grep -qF 'background:var(--gloskin-accent);' "$commerce_css" || fail "action kit: danger background missing"
grep -qF 'border:1px solid var(--gloskin-accent);' "$commerce_css" || fail "action kit: danger border missing"
grep -qF 'color:var(--gloskin-inverse);' "$commerce_css" || fail "action kit: danger foreground missing"
grep -qF 'background:var(--gloskin-accent-strong);' "$commerce_css" || fail "action kit: danger hover missing"

grep -qF 'class="remove remove_from_cart_button gloskin-ui1-cart-sheet__item-remove"' "$adapter" || fail "mini cart: native Woo remove classes changed"
grep -qF 'data-product_id=' "$adapter" || fail "mini cart: data-product_id missing"
grep -qF 'data-cart_item_key=' "$adapter" || fail "mini cart: data-cart_item_key missing"
grep -qF 'classList.add('\''gloskin-ui1-action-icon'\'')' "$core_js" || fail "mini cart: shared action presentation class missing"
grep -qF 'classList.add('\''gloskin-ui1-action-icon--danger'\'')' "$core_js" || fail "mini cart: danger presentation class missing"
[[ "$(grep -o 'remove_from_cart_button gloskin-ui1-cart-sheet__item-remove' "$adapter" | wc -l | tr -d ' ')" == "1" ]] || fail "mini cart: expected exactly one remove action renderer"
grep -qF '<span class="gloskin-ui1-icon-remove" aria-hidden="true"></span>' "$adapter" || fail "mini cart: shared remove icon missing"

grep -qF 'gloskin-ui1-wishlist-sheet__item-remove gloskin-ui1-action-icon gloskin-ui1-action-icon--danger' "$core_js" || fail "wishlist: shared danger action classes missing"
grep -qF 'data-gloskin-wishlist-toggle=' "$core_js" || fail "wishlist: existing toggle owner missing"
grep -qF '<span class="gloskin-ui1-icon-remove" aria-hidden="true"></span>' "$core_js" || fail "wishlist: shared remove icon missing"

# Badge motion is compositor-only and explicitly disabled for reduced motion.
grep -qF 'animation:gloskin-ui1-commerce-badge-added 270ms cubic-bezier(.22,1,.36,1) both;' "$commerce_css" || fail "badge delta: motion timing missing"
grep -qF 'transform:translateY(4px) scale(.86)' "$commerce_css" || fail "badge delta: added-in start transform missing"
grep -qF 'transform:translateY(0) scale(1.06)' "$commerce_css" || fail "badge delta: restrained overshoot missing"
grep -qF '@media (prefers-reduced-motion:reduce)' "$commerce_css" || fail "badge delta: reduced-motion gate missing"
grep -qF 'animation:none;' "$commerce_css" || fail "badge delta: reduced-motion animation suppression missing"
grep -qF 'transform:none;' "$commerce_css" || fail "badge delta: reduced-motion transform suppression missing"
if grep -qE '(width|height|left|top)[[:space:]]*:' "$commerce_css" | grep -q 'keyframes'; then fail "badge delta: layout animation introduced"; fi

# Canonical screen-reader updates remain immediate inside updateBadges().
grep -qF "label.textContent = count + ' produk favorit';" "$core_js" || fail "wishlist accessibility: immediate SR count update missing"

# Dynamic Quick Add inherits the canonical Form/Action Kit without JS styling.
grep -qF 'gloskin-ui1-quickadd__form gloskin-ui1-form' "$core_js" || fail "form kit: Quick Add dynamic root does not inherit .gloskin-ui1-form"
if grep -qE '^\.gloskin-ui1-quickadd__form \.single_add_to_cart_button\{(background|color|border-color):' "$core_css"; then
	fail "form kit: Quick Add reintroduced modal-specific CTA skin"
fi
if grep -qE '^\.gloskin-ui1-auth-overlay input\.input-text|^\.gloskin-ui1-auth-overlay \.button\{' "$readiness_css"; then
	fail "form kit: Auth overlay still owns a duplicate field/button skin"
fi

# Nav bubble remains transform/opacity only.
grep -qF 'transform:scale(0);' "$production_css" || fail "nav bubble: scale(0) initial state missing"
grep -qF 'transform-origin:center;' "$production_css" || fail "nav bubble: centered transform origin missing"
grep -qF '.gloskin-ui1-nav__bubble.is-visible{opacity:1;transform:scale(1)}' "$production_css" || fail "nav bubble: scale(1) visible state missing"
grep -qF 'transition:transform 180ms cubic-bezier(.22,1,.36,1),opacity 160ms ease;' "$production_css" || fail "nav bubble: restrained transform/opacity transition missing"
if grep -qF '.gloskin-ui1-nav__bubble.is-settling' "$production_css"; then fail "nav bubble: old liquid settling owner returned"; fi

# Sacred negatives and P0 double-add guard.
grep -qF "formData.delete('add-to-cart');" "$core_js" || fail "P0 double-add fix regressed"
if grep -qF 'MutationObserver' "$core_js"; then fail "commerce polish: MutationObserver introduced"; fi
if grep -qF 'setInterval(' "$core_js"; then fail "commerce polish: polling introduced"; fi
if grep -qF '!important' "$commerce_css"; then fail "commerce polish: new !important introduced"; fi

important_count="$(grep -hoE '!important[};]' "$core_css" "$readiness_css" "$production_css" "$commerce_css" | wc -l | tr -d ' ')"
[[ "$important_count" -le 6 ]] || fail "micro interactions: new !important introduced (count=$important_count)"

node "$repo_root/tests/commerce-microinteraction-contract.test.js"
echo "micro interactions contract passed"
