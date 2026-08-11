#!/usr/bin/env bash
# Focused regression contract for docs/audits/single-product-commerce-remediation-2026-08-11.md.
# Static guards over the specific SP-001..SP-007 invariants this pass
# introduced/relies on. Behavioral proof for the SP-001 content guard
# itself lives in tests/single-product-guard-contract.php. Real browser
# DOM verification is separately reported SKIPPED where this repo has no
# live WordPress/WooCommerce/browser runtime -- see the engineer report.
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_root="$repo_root/plugin/gloskin-site-core"
includes="$plugin_root/includes"
templates="$plugin_root/templates"
shell="$templates/shell.php"
header="$templates/parts/header.php"
quickadd_tpl="$templates/parts/quick-add.php"
helpers="$templates/parts/template-helpers.php"
adapter="$includes/class-gloskin-site-core-woocommerce-adapter.php"
asset_service="$includes/class-gloskin-site-core-asset-service.php"
template_service="$includes/class-gloskin-site-core-template-service.php"
core_js="$plugin_root/assets/js/gloskin-ui1-core.js"
core_css="$plugin_root/assets/css/gloskin-ui1-core.css"

fail() { echo "$1" >&2; exit 1; }

# 1. Exactly one Woo native commerce render entrypoint; woocommerce_content()
# is never called more than once anywhere in the plugin.
call_count="$(grep -rn 'woocommerce_content();' "$plugin_root" --include='*.php' | wc -l | tr -d ' ')"
[[ "$call_count" == "1" ]] || fail "SP-001/SP-007#1: expected exactly one woocommerce_content() call site, found $call_count"
grep -qF 'woocommerce_content();' "$shell" || fail "SP-001/SP-007#1: the one woocommerce_content() call site must live in shell.php"

# 3. The SP-001 content-integrity guard is registered on the_content and
# scoped to the product's own singular render (behavioral proof: see
# tests/single-product-guard-contract.php).
grep -qF "add_filter( 'the_content', array( \$this, 'guard_single_product_description_content' ), 1 );" "$adapter" \
	|| fail "SP-001: description-tab content guard is not registered on the_content"
grep -qF 'function guard_single_product_description_content(' "$adapter" || fail "SP-001: guard_single_product_description_content() missing"

# 4/9. Simple + variable AJAX both use Woo's own documented endpoint; no
# custom Gloskin cart mutation endpoint exists anywhere in the bridge.
grep -qF "WC_AJAX::get_endpoint( 'add_to_cart' )" "$adapter" || fail "SP-003: add_to_cart_ajax_url() no longer reads Woo's own documented endpoint"
if grep -RInE 'wp_ajax_(nopriv_)?gloskin_(add_to_cart|cart|checkout)' "$adapter" "$core_js" "$asset_service"; then
	fail "SP-003/SP-004: a custom Gloskin cart mutation AJAX endpoint was introduced"
fi
if grep -RInE "register_rest_route\([^)]*['\"](cart|checkout)" "$adapter"; then
	fail "SP-003/SP-004: a custom Gloskin cart/checkout REST endpoint was introduced"
fi
grep -qF "config.addToCartAjaxUrl" "$core_js" || fail "SP-003: ajaxAddToCart() no longer uses the Woo-supplied AJAX URL"

# 5. Simple product native POST fallback: the single-product submit
# handler only ever intercepts via preventDefault after confirming AJAX
# is actually usable -- it never removes/replaces the native form.
grep -qF 'event.preventDefault();' "$core_js" || fail "SP-003: single-product AJAX handler no longer calls preventDefault() before intercepting"
if grep -qE "form\.(action|method)\s*=" "$core_js"; then
	fail "SP-003: JS must never rewrite form.cart's native action/method (breaks the no-JS/native POST fallback)"
fi

# 6/9. Variable single product and Quick Add both require a Woo-computed
# variation_id before ever calling ajaxAddToCart() -- never guessed.
variation_guard_count="$(grep -c "if (!variationId || !parseInt(variationId.value, 10))" "$core_js")"
[[ "$variation_guard_count" -ge 2 ]] || fail "SP-003/SP-004: expected a variation_id guard before both the single-product and Quick Add AJAX submits, found $variation_guard_count"

# 7. Variable catalog card keeps the canonical product-detail href as the
# no-JS/error fallback -- the Quick Add trigger enhances the same anchor,
# it never replaces it with a JS-only control.
grep -qF "data-gloskin-quickadd-open data-gloskin-quickadd-product=" "$helpers" || fail "SP-004: Quick Add trigger attributes missing from the product card"
grep -qF '<a href="<?php echo esc_url( (string) $product[' "$helpers" || fail "SP-004: product card add-to-cart control is no longer a real <a href> fallback"

# 8. Quick Add uses Woo's own native variation-form plugin, never a
# hand-rolled Gloskin variation resolver.
grep -qF 'wc_variation_form()' "$core_js" || fail "SP-004: Quick Add no longer binds Woo's native wc_variation_form() plugin"
if grep -qiE 'matchingVariation|resolveVariation|findVariation' "$core_js"; then
	fail "SP-004: a hand-rolled Gloskin variation resolver was introduced"
fi
grep -qF 'woocommerce_template_single_add_to_cart();' "$adapter" || fail "SP-004: quick-add projection no longer captures Woo's own native add-to-cart template output"

# 10. Successful AJAX add dispatches Woo's own added_to_cart event, reusing
# the existing initCart() cart-sheet bridge -- never a second cart-open path.
added_to_cart_count="$(grep -c "trigger('added_to_cart'" "$core_js")"
[[ "$added_to_cart_count" == "1" ]] || fail "SP-003/SP-004: expected exactly one added_to_cart trigger site (the shared ajaxAddToCart bridge), found $added_to_cart_count"
grep -qF "jQuery(document.body).on('added_to_cart'" "$core_js" || fail "SP-003/SP-004: existing initCart() added_to_cart listener missing/changed"

# 11. Gallery HD policy is scoped to the single-product main gallery only
# -- never a global full-size override for cards/thumbnails/related/cart.
grep -qF "add_filter( 'woocommerce_gallery_image_size', array( \$this, 'single_product_gallery_image_size' ) );" "$adapter" \
	|| fail "SP-005: single-product gallery HD filter no longer registered"
if grep -qE "add_filter\(\s*'(woocommerce_gallery_thumbnail_size|woocommerce_gallery_full_size)'" "$adapter"; then
	fail "SP-005: thumbnail/full-size gallery filters must stay untouched (only the main display-size filter is scoped)"
fi
grep -qF "'woocommerce_thumbnail'" "$helpers" || fail "SP-005: catalog product cards must keep an optimized (non-full) image size"
grep -qF "'medium'" "$adapter" || fail "SP-005: Quick Add projection image must keep an optimized (non-full) image size"
if grep -qE "wp_get_attachment_image\([^)]*'full'" "$adapter" "$helpers"; then
	fail "SP-005: an image helper call was forced to 'full' outside the single-product main gallery"
fi

# 14. Mobile single product collapses to one column and the Quick Add
# modal stays a touch-safe bottom sheet; neither introduces horizontal
# overflow (no fixed px widths wider than a narrow viewport).
grep -qF 'body.single-product.gloskin-ui1 div.product{grid-template-columns:1fr}' "$core_css" \
	|| fail "SP-002/SP-007#14: single-product mobile one-column collapse rule missing"
grep -qF '.gloskin-ui1-quickadd{align-items:flex-end;padding:0}' "$core_css" \
	|| fail "SP-004/SP-007#14: Quick Add mobile bottom-sheet rule missing"

# Asset ownership: Gloskin_Site_Core_Asset_Service remains the only
# first-party asset owner; Woo's variation script is conditionally
# enqueued, never enqueued from a template.
grep -qF 'variation_form_may_render' "$asset_service" || fail "SP-007: conditional wc-add-to-cart-variation enqueue owner missing from AssetService"
if grep -RInE "wp_enqueue_script\(\s*'wc-add-to-cart" "$templates"; then
	fail "SP-007: a Woo script was enqueued directly from a template instead of AssetService"
fi

# Overlay ownership: Quick Add uses the exact same overlay contract as
# Search/Auth/Cart/Wishlist -- one overlay controller, no second one.
grep -qF 'data-gloskin-overlay="quickadd"' "$quickadd_tpl" || fail "SP-004/SP-007: Quick Add markup missing the shared overlay contract"
overlay_controller_count="$(grep -c 'var overlay = (function ()' "$core_js")"
[[ "$overlay_controller_count" == "1" ]] || fail "SP-007: expected exactly one overlay controller definition, found $overlay_controller_count"

echo "single-product commerce contract passed"
