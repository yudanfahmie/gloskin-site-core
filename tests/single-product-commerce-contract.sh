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

# 7 (hotfix): the guard is narrowed to true self-recursion only (target
# ID === current product's own ID) -- never a blanket shortcode-family
# ban that would silently delete legitimate editorial/cross-sell content.
# Behavioral proof (self-reference stripped, other-product/legitimate
# content preserved) lives in tests/single-product-guard-contract.php.
grep -qF 'function strip_if_self_referencing_single_product(' "$adapter" || fail "SP-001 hotfix: self-referencing scoping helper missing"
grep -qF '$target_id === $current_id' "$adapter" || fail "SP-001 hotfix: guard no longer compares the embed's own target ID against the current product"
if grep -qE "product_page\|add_to_cart\|add_to_cart_url\|products\|product_category\|product\|woocommerce_" "$adapter"; then
	fail "SP-001 hotfix: the old broad shortcode-family strip list must not reappear"
fi

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
# is actually usable -- it never removes/replaces the native form, and
# the real fallback path re-dispatches a genuine native submission
# (requestSubmit with the real submitter) rather than a bare form.submit()
# that would silently drop a simple product's only product-id carrier.
grep -qF 'event.preventDefault();' "$core_js" || fail "SP-003: single-product AJAX handler no longer calls preventDefault() before intercepting"
if grep -qE "form\.(action|method)\s*=" "$core_js"; then
	fail "SP-003: JS must never rewrite form.cart's native action/method (breaks the no-JS/native POST fallback)"
fi
grep -qF 'function nativeFallbackSubmit(form, submitter)' "$core_js" || fail "SP-003: native fallback helper missing"
grep -qF 'form.requestSubmit(submitter || undefined);' "$core_js" || fail "SP-003: native fallback must use requestSubmit() with the real submitter, not a bare form.submit()"
grep -qF "data-gloskin-ajax-bypass" "$core_js" || fail "SP-003: native fallback must guard against re-entering AJAX interception on its own requestSubmit() resubmission"

# Product-id normalization: simple products carry their id on the submit
# BUTTON (name="add-to-cart"), never a hidden field -- Woo's own
# simple.php template never renders one. The bridge must derive it from
# the actual activated submitter, not assume a nonexistent hidden input.
grep -qF 'function resolveWooSubmitter(form, event)' "$core_js" || fail "hotfix: resolveWooSubmitter() missing"
grep -qF 'event.submitter' "$core_js" || fail "hotfix: submitter resolution must prefer the real SubmitEvent.submitter"
grep -qF "submitter.name === 'add-to-cart'" "$core_js" || fail "hotfix: simple-product product_id must be derived from the real Woo submitter, not assumed to be a hidden field"

# 6/9. Variable single product and Quick Add both require a Woo-computed
# variation_id before ever calling ajaxAddToCart() -- never guessed. The
# eligibility decision lives in exactly one shared function so both
# callers can never drift, and it must be used by both.
grep -qF 'function shouldInterceptWooSubmit(form, submitter)' "$core_js" || fail "hotfix: shouldInterceptWooSubmit() missing"
guard_use_count="$(grep -c 'shouldInterceptWooSubmit(form, submitter)' "$core_js")"
[[ "$guard_use_count" -ge 2 ]] || fail "SP-003/SP-004: expected shouldInterceptWooSubmit() used before both the single-product and Quick Add AJAX submits, found $guard_use_count"

# Variable AJAX must post the *selected variation* as product_id, per
# WC_AJAX::add_to_cart()'s own contract -- never the variable parent.
grep -qF "formData.set('product_id', String(variationId));" "$core_js" || fail "hotfix: variable AJAX payload must post the selected variation_id as product_id"

# 7. Variable catalog card keeps the canonical product-detail href as the
# no-JS/error fallback -- the Quick Add trigger enhances the same anchor,
# it never replaces it with a JS-only control.
grep -qF "data-gloskin-quickadd-open data-gloskin-quickadd-product=" "$helpers" || fail "SP-004: Quick Add trigger attributes missing from the product card"
grep -qF '<a href="<?php echo esc_url( (string) $product[' "$helpers" || fail "SP-004: product card add-to-cart control is no longer a real <a href> fallback"

# 7b. Native Woo Related Products cards (not the Gloskin card helper) can
# open the same Quick Add modal via progressive-enhancement event
# delegation -- no second related-products query, no duplicated card markup.
grep -qF "'body.single-product .related.products a.add_to_cart_button.product_type_variable[data-product_id]" "$core_js" \
	|| fail "SP-004: native Woo Related Products variable-card Quick Add bridge missing"
if grep -qE "get_related|related_products\s*\(|wc_get_related_products|WP_Query.*related" "$adapter" "$helpers" "$template_service"; then
	fail "SP-006/SP-004: a second related-products query owner was introduced"
fi

# 8. Quick Add uses Woo's own native variation-form plugin, never a
# hand-rolled Gloskin variation resolver.
grep -qF 'wc_variation_form()' "$core_js" || fail "SP-004: Quick Add no longer binds Woo's native wc_variation_form() plugin"
if grep -qiE 'matchingVariation|resolveVariation|findVariation' "$core_js"; then
	fail "SP-004: a hand-rolled Gloskin variation resolver was introduced"
fi
grep -qF 'woocommerce_template_single_add_to_cart();' "$adapter" || fail "SP-004: quick-add projection no longer captures Woo's own native add-to-cart template output"

# 8 (hotfix): the public Quick Add projection is aligned with the same
# catalog-visibility policy as the rest of the catalog -- a product
# explicitly hidden/search-only cannot be pulled in merely by ID.
grep -qF 'function is_excluded_from_catalog(' "$adapter" || fail "hotfix: Quick Add catalog-visibility guard missing"
grep -qF '$this->is_excluded_from_catalog( $id )' "$adapter" || fail "hotfix: rest_quick_add_projection() no longer checks catalog visibility"

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
