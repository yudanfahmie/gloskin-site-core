#!/usr/bin/env bash
# Focused presentation/micro-feedback regression contract.
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_root="$repo_root/plugin/gloskin-site-core"
core_js="$plugin_root/assets/js/gloskin-ui1-core.js"
core_css="$plugin_root/assets/css/gloskin-ui1-core.css"
readiness_css="$plugin_root/assets/css/gloskin-ui1-readiness.css"
production_css="$plugin_root/assets/css/gloskin-ui1-production.css"

fail() { echo "$1" >&2; exit 1; }

# One shared presentation-only success helper; one embedded local sound;
# reusable Audio instance, cooldown, hidden-tab guard and non-blocking play.
grep -qF 'function successFeedback(type, runtime)' "$core_js" || fail "success feedback: shared helper missing"
grep -qF "type !== 'cart' && type !== 'wishlist'" "$core_js" || fail "success feedback: helper accepts unsupported types"
grep -qF "var SUCCESS_SOUND_URI = 'data:audio/wav;base64," "$core_js" || fail "success feedback: embedded WAV data URI missing"
[[ "$(grep -c 'new root.Audio(SUCCESS_SOUND_URI)' "$core_js")" == "1" ]] || fail "success feedback: expected exactly one reusable Audio construction site"
grep -qF 'SUCCESS_SOUND_COOLDOWN_MS = 280' "$core_js" || fail "success feedback: audio cooldown missing"
grep -qF "root.document.visibilityState !== 'visible'" "$core_js" || fail "success feedback: hidden-tab audio guard missing"
grep -qF 'playback.catch(function () {})' "$core_js" || fail "success feedback: audio rejection is not swallowed"

# Cart feedback is downstream of Woo's confirmed added_to_cart lifecycle.
[[ "$(grep -c "successFeedback('cart')" "$core_js")" == "1" ]] || fail "cart feedback: expected one success-feedback call"
grep -qF "jQuery(document.body).on('added_to_cart'" "$core_js" || fail "cart feedback: Woo confirmed-success listener missing"
grep -qF "requestAnimationFrame(function () { successFeedback('cart'); });" "$core_js" || fail "cart feedback: feedback is not queued after confirmed Woo success/state listeners"
# Cart count remains fragment/server owned: frontend JS never writes it.
if grep -qF 'data-gloskin-cart-count' "$core_js"; then
	fail "cart feedback: JS reached into the authoritative cart-count nodes"
fi

# Wishlist feedback runs only on a persisted SAVE; removal has no celebration.
grep -qF 'function saveIds(ids)' "$core_js" || fail "wishlist feedback: canonical persistence helper missing"
grep -qF 'return true;' "$core_js" || fail "wishlist feedback: persistence success is not observable"
[[ "$(grep -c "successFeedback('wishlist')" "$core_js")" == "1" ]] || fail "wishlist feedback: expected one save-only feedback call"
grep -qF "if (!wasActive && active) { successFeedback('wishlist'); }" "$core_js" || fail "wishlist feedback: helper is not gated to a confirmed save transition"

# Dynamic Quick Add inherits the canonical Form/Action Kit without JS styling.
grep -qF 'gloskin-ui1-quickadd__form gloskin-ui1-form' "$core_js" || fail "form kit: Quick Add dynamic root does not inherit .gloskin-ui1-form"
if grep -qE '^\.gloskin-ui1-quickadd__form \.single_add_to_cart_button\{(background|color|border-color):' "$core_css"; then
	fail "form kit: Quick Add reintroduced modal-specific CTA skin"
fi
if grep -qE '^\.gloskin-ui1-auth-overlay input\.input-text|^\.gloskin-ui1-auth-overlay \.button\{' "$readiness_css"; then
	fail "form kit: Auth overlay still owns a duplicate field/button skin"
fi
grep -qF '.gloskin-ui1-auth-forms input.input-text' "$core_css" || fail "form kit: captured native Woo auth fields are not bridged into the global kit"
grep -qF '.gloskin-ui1-action-icon,.gloskin-ui1-sheet__close,.gloskin-ui1-quickadd__close' "$core_css" || fail "action kit: shared compact modal action owner missing"

# Nav bubble has final geometry before showing and visibly animates scale/
# opacity only. No liquid bridge/translate/size transition remains.
grep -qF 'transform:scale(0);' "$production_css" || fail "nav bubble: scale(0) initial state missing"
grep -qF 'transform-origin:center;' "$production_css" || fail "nav bubble: centered transform origin missing"
grep -qF '.gloskin-ui1-nav__bubble.is-visible{opacity:1;transform:scale(1)}' "$production_css" || fail "nav bubble: scale(1) visible state missing"
grep -qF 'transition:transform 180ms cubic-bezier(.22,1,.36,1),opacity 160ms ease;' "$production_css" || fail "nav bubble: restrained transform/opacity transition missing"
if grep -qF '.gloskin-ui1-nav__bubble.is-settling' "$production_css"; then fail "nav bubble: old liquid settling owner returned"; fi
if grep -qE 'gloskin-ui1-nav__bubble\{[^}]*transition:[^}]*\b(width|height|left|top)\b' "$production_css"; then fail "nav bubble: geometry property is animated"; fi
nav_block="$(awk '/function initNavBubble\(\) \{/,/^[[:space:]]*\/\* -----------------------------------------------------------------/' "$core_js")"
if echo "$nav_block" | grep -q 'translate('; then fail "nav bubble: directional translate returned"; fi
echo "$nav_block" | grep -q "target.row.addEventListener('focusin'" || fail "nav bubble: keyboard focus parity missing"

# Decorative pulse is skipped under reduced motion; real state code remains.
grep -qF "feedbackReducedMotion(root)" "$core_js" || fail "success feedback: reduced-motion check missing"
grep -qF '.gloskin-ui1-utility-btn.is-success-pulse' "$core_css" || fail "success feedback: canonical utility pulse styling missing"
grep -qF 'animation:none' "$core_css" || fail "success feedback: reduced-motion pulse suppression missing"

# This pass starts from seven !important occurrences across these three
# presentation layers (six compatibility/reduced-motion exceptions plus the
# old nav-bubble foreground override). The nav refactor removes that override
# and must introduce none: at most six remain.
important_count="$(grep -ho '!important' "$core_css" "$readiness_css" "$production_css" | wc -l | tr -d ' ')"
[[ "$important_count" -le 6 ]] || fail "micro interactions: new !important introduced (count=$important_count)"

echo "micro interactions contract passed"
