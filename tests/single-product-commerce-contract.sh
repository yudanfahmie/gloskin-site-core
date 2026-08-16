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
geometry="$plugin_root/assets/css/gloskin-ui1-single-product-geometry.css"

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
grep -qF '<a href="<?php echo esc_url( $action_url ); ?>"' "$helpers" || fail "SP-004: product card add-to-cart control is no longer a real <a href> fallback"

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

# Gallery presentation: no artificial surface tint behind the packshot, and
# the thumbnail rail stays centered regardless of thumbnail count. object-
# fit/aspect-ratio/border/radius/zoom behavior are untouched.
grep -qF 'div.product .woocommerce-product-gallery__image{background:transparent}' "$core_css" \
	|| fail "gallery: primary image container must render a transparent background"
grep -qF 'div.product .woocommerce-product-gallery__image img{width:100%;height:auto;aspect-ratio:1/1;object-fit:contain;background:transparent}' "$core_css" \
	|| fail "gallery: primary image element must render a transparent background"
if grep -qE "woocommerce-product-gallery__image(\s*img)?\{[^}]*background:var\(--gloskin-surface-strong\)" "$core_css"; then
	fail "gallery: artificial surface-strong tint reintroduced behind the gallery image"
fi

# Gallery ghost-gutter fix, proven live on the real hydrated staging PDP: the
# frame (border/radius/background/clip) must live on the outer, JS-
# independent .woocommerce-product-gallery box -- present and correctly
# sized in the server-rendered no-JS markup too -- never on
# .woocommerce-product-gallery__wrapper, which FlexSlider resizes into a
# multi-slide strip (real slides + infinite-loop clones) far wider than the
# visible frame and translates horizontally. Framing the wrapper instead
# only put the rounded corner at the strip's own x:0, producing an
# inconsistent "ghost" rounded gutter distinct from the always-stable
# viewport frame.
grep -qF 'div.product .woocommerce-product-gallery{border:1px solid var(--gloskin-border);border-radius:var(--gloskin-radius-lg);background:var(--gloskin-bg);overflow:hidden}' "$core_css" \
	|| fail "gallery: frame ownership is not anchored to the stable outer .woocommerce-product-gallery box"
if grep -qE "woocommerce-product-gallery__wrapper\{[^}]*(border:|border-radius:|overflow:hidden)" "$core_css"; then
	fail "gallery: frame ownership regressed back onto the FlexSlider-managed multi-slide wrapper"
fi
grep -qF '.flex-control-thumbs{display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:10px' "$core_css" \
	|| fail "gallery: thumbnail rail is not centered"

# 14. Mobile single product collapses to one column and the Quick Add
# modal stays a touch-safe bottom sheet; neither introduces horizontal
# overflow (no fixed px widths wider than a narrow viewport).
grep -qF 'body.single-product.gloskin-ui1 .gloskin-ui1-commerce-native>div.product{grid-template-columns:1fr}' "$core_css" \
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

# -----------------------------------------------------------------------
# 2026-08-12 hardening: SP-001 sku-targeted self-reference (proven live on
# staging), the floating/sticky purchase dock, commerce accent
# normalization, and the post-success View Cart helper.
# -----------------------------------------------------------------------

# SP-001: the guard now also resolves a [product_page sku="..."] self-
# reference through Woo's own documented wc_get_product_id_by_sku(), the
# exact live-proven root cause -- never a hand-rolled SKU lookup.
grep -qF 'function is_self_referencing_product_page_shortcode(' "$adapter" \
	|| fail "SP-001 hardening: sku-aware self-reference helper missing"
grep -qF 'wc_get_product_id_by_sku( $sku )' "$adapter" \
	|| fail "SP-001 hardening: sku self-reference must resolve through Woo's own wc_get_product_id_by_sku()"

# Purchase dock: exactly one native form.cart is wrapped (never cloned) via
# Woo's own woocommerce_before/after_add_to_cart_form hooks, scoped to the
# page's own primary product only.
grep -qF "add_action( 'woocommerce_before_add_to_cart_form', array( \$this, 'open_purchase_dock' ) );" "$adapter" \
	|| fail "purchase dock: open hook not registered on woocommerce_before_add_to_cart_form"
grep -qF "add_action( 'woocommerce_after_add_to_cart_form', array( \$this, 'close_purchase_dock' ) );" "$adapter" \
	|| fail "purchase dock: close hook not registered on woocommerce_after_add_to_cart_form"
grep -qF 'function is_primary_single_product_context(' "$adapter" \
	|| fail "purchase dock: primary-product scoping guard missing (would also wrap a legitimate nested different-product embed)"
grep -qF 'gloskin-ui1-purchase-dock' "$adapter" || fail "purchase dock: wrapper markup missing from the adapter"
grep -qF '.gloskin-ui1-purchase-dock{' "$core_css" || fail "purchase dock: dock surface styling missing from core CSS"
if grep -qE '\.summary\{[^}]*position:sticky|div\.product>\.summary\{position:sticky' "$core_css"; then
	fail "summary scroll: the whole-summary sticky/scrollable model must be removed, not just relocated"
fi
if grep -qE 'gloskin-ui1-purchase-dock\{[^}]*overflow' "$core_css"; then
	fail "purchase dock: must never grow an internal scrollbar"
fi

# Purchase dock visual contract: core.css remains the base/no-JS Form Kit
# owner, while enhanced geometry deliberately becomes the Gloskin accent
# command bar with transparent structural wrappers and an inverse CTA.
grep -qF -- 'gloskin-ui1-purchase-dock{z-index:5;width:100%;max-width:calc(100vw - 32px);margin:6px auto 0;padding:clamp(14px,1.4vw,18px)' "$core_css" \
	|| fail "purchase dock: base/no-JS fallback geometry missing"
grep -qF -- 'background:var(--gloskin-accent);color:var(--gloskin-inverse)' "$geometry" \
	|| fail "purchase dock: enhanced accent outer surface missing"
grep -qF -- '.gloskin-ui1-purchase-dock__form{display:grid;width:100%;max-width:none;grid-template-columns:minmax(0,1fr) auto' "$geometry" \
	|| fail "purchase dock: enhanced one-row form composition missing"
grep -qF -- '.gloskin-ui1-purchase-dock__submit{width:auto;min-width:clamp(160px,13vw,210px);max-width:240px;min-height:46px;padding:10px 18px;background:var(--gloskin-inverse)' "$geometry" \
	|| fail "purchase dock: inverse CTA on accent surface missing"
if grep -qE 'purchase-dock-home.*purchase-dock\.is-floating\{[^}]*background:var\(--gloskin-bg\)' "$geometry"; then
	fail "purchase dock: floating outer background regressed to neutral/white"
fi
if grep -qF 'grid-template-columns:minmax(0,1.35fr)' "$geometry"; then
	fail "purchase dock: previous oversized 1.35fr/.65fr composition returned"
fi

# Commerce accent: catalog cards still need their explicit card action skin.
# Quick Add now carries the canonical .gloskin-ui1-form root, so its native
# Woo button inherits the global Form/Action Kit instead of keeping a second
# modal-specific color/state owner.
grep -qF '.gloskin-ui1-card--product .gloskin-ui1-card__actions a.add_to_cart_button{background:var(--gloskin-accent);color:var(--gloskin-inverse);border-color:transparent;border-radius:var(--gloskin-action-radius)}' "$core_css" \
	|| fail "commerce accent: product-card Add to Cart accent rule missing"
grep -qF 'gloskin-ui1-quickadd__form gloskin-ui1-form' "$core_js" \
	|| fail "commerce accent: Quick Add dynamic form no longer inherits the canonical Form/Action Kit"
if grep -qF '.gloskin-ui1-quickadd__form .single_add_to_cart_button{background:' "$core_css"; then
	fail "commerce accent: Quick Add reintroduced a modal-specific button color owner"
fi
grep -qF 'var(--gloskin-accent-strong)' "$core_css" || fail "commerce accent: hover state must use --gloskin-accent-strong"

# Commerce accent hardening: PDP primary Add to Cart and native Related
# Products loop buttons own their accent/hover/disabled state by the real
# WooCommerce button class, never only the generic co-class -- so a later-
# loaded theme/plugin stylesheet targeting `.single_add_to_cart_button` or
# `.add_to_cart_button` directly can no longer win with native purple.
for expected in \
  '.gloskin-ui1 .woocommerce form.cart button.single_add_to_cart_button,' \
  '.gloskin-ui1 .gloskin-ui1-form form.cart button.single_add_to_cart_button{background:var(--gloskin-accent);border-color:var(--gloskin-accent);border-radius:var(--gloskin-action-radius);color:var(--gloskin-inverse)}' \
  '.related.products ul.products li.product .add_to_cart_button{background:var(--gloskin-accent);border-color:var(--gloskin-accent);border-radius:var(--gloskin-action-radius);color:var(--gloskin-inverse)}'; do
  grep -qF -- "$expected" "$core_css" || fail "commerce accent: PDP/related CTA hardening missing: $expected"
done
if grep -qE "single_add_to_cart_button:disabled[^{]*\{[^}]*background:var\(--gloskin-accent\)" "$core_css"; then
  fail "commerce accent: disabled Add to Cart must not render the active accent color"
fi

# View Cart: normal PDP Add to Cart success is directly canonical
# added_to_cart -> Cart overlay/count. A single-product-specific View Cart
# link create-then-delete choreography must never exist (Woo's own
# wc-add-to-cart.js still owns the catalog-loop ajax_add_to_cart forward
# link independently; that CSS contract stays, only the PDP JS helper that
# used to shadow it is gone).
if grep -qF 'function renderSingleProductViewCartLink' "$core_js"; then
	fail "View Cart: PDP create-then-delete success helper must not exist"
fi
if grep -qF 'wc-forward' "$core_js"; then
	fail "View Cart: core must never create a PDP added_to_cart forward link"
fi
grep -qF 'onSuccess: function () { handleSingleProductAddToCartSuccess(submitter); }' "$core_js" \
	|| fail "single-product success handler must run only after a confirmed successful Woo mutation, not on dispatch"
if [[ -f "$plugin_root/assets/js/gloskin-ui1-commerce-closure.js" ]]; then
	fail "post-core commerce closure module must not exist"
fi

# -----------------------------------------------------------------------
# Regression-hardening pass: primary-product CSS scope tightened to a
# direct child of .gloskin-ui1-commerce-native (never a bare
# "body.single-product.gloskin-ui1 div.product"), and single-product AJAX
# bound to the canonical purchase-dock form only (never a bare
# "div.product form.cart" that could also match a legitimate different-
# product [product_page] embed nested in the Description tab).
# -----------------------------------------------------------------------
if grep -qE 'body\.single-product\.gloskin-ui1 div\.product[ {,.>:]' "$core_css"; then
	fail "primary PDP scope: a bare 'body.single-product.gloskin-ui1 div.product' selector reappeared -- must anchor on .gloskin-ui1-commerce-native>div.product"
fi
grep -qF 'body.single-product.gloskin-ui1 .gloskin-ui1-commerce-native>div.product{display:grid' "$core_css" \
	|| fail "primary PDP scope: direct-child grid anchor rule missing"
if grep -qF "document.querySelector('div.product form.cart')" "$core_js"; then
	fail "single-product AJAX: must not re-introduce the unscoped 'div.product form.cart' query"
fi
grep -qF "document.querySelector('[data-gloskin-purchase-dock] form.cart')" "$core_js" \
	|| fail "single-product AJAX: must bind the canonical purchase-dock form only"

# 2026-08-12 release-gate finding, live-proven on staging: an external,
# same-product duplicate render (is_primary_single_product_context() alone
# cannot distinguish "this same product rendered a second time by
# something outside this plugin" from the genuine primary render -- both
# legitimately see get_post() === get_queried_object()) could otherwise
# make open_purchase_dock()/close_purchase_dock() emit a second floating
# dock. A one-shot-per-request static guard makes the first invocation the
# sole owner, guaranteeing at most one dock renders regardless of cause.
grep -qF 'private static $purchase_dock_rendered = false;' "$adapter" \
	|| fail "purchase dock: one-shot-per-request guard missing (duplicate dock reproduced live on staging)"
grep -qF 'if ( self::$purchase_dock_rendered || ! $this->is_primary_single_product_context() ) {' "$adapter" \
	|| fail "purchase dock: open_purchase_dock() no longer gates on the one-shot flag"
grep -qF 'if ( ! self::$purchase_dock_open ) {' "$adapter" \
	|| fail "purchase dock: close_purchase_dock() no longer stays balanced with the one-shot open flag"

# -----------------------------------------------------------------------
# Storefront/PDP refinement pass: one modest Action Kit radius token, a
# custom Gloskin Add-to-Cart loader replacing Woo's default gear, and
# ZAP-like PDP structural simplification via Woo's own documented hooks.
# -----------------------------------------------------------------------
core_base_css="$plugin_root/assets/css/gloskin-ui1-core-base.css"
readiness_css="$plugin_root/assets/css/gloskin-ui1-readiness.css"
description_boundary="$templates/parts/product-description-boundary.php"

# One action-radius owner, reused (not redefined) by every textual CTA.
grep -qF -- '--gloskin-action-radius:var(--gloskin-field-radius);' "$core_base_css" \
	|| fail "action radius: one canonical --gloskin-action-radius token missing"
for expected in \
	'.gloskin-ui1-button{display:inline-flex;min-height:46px;align-items:center;justify-content:center;gap:8px;padding:10px 18px;border:1px solid transparent;border-radius:var(--gloskin-action-radius)' \
	'.gloskin-ui1-form button,.gloskin-ui1-form input[type="submit"],.gloskin-ui1 .woocommerce a.button,.gloskin-ui1 .woocommerce button.button,.gloskin-ui1 .woocommerce input.button{display:inline-flex;min-height:46px;align-items:center;justify-content:center;padding:12px 22px;border:0;border-radius:var(--gloskin-action-radius)'; do
	grep -qF -- "$expected" "$core_base_css" || fail "action radius: text CTA no longer uses the shared token: $expected"
done
grep -qF -- '.gloskin-ui1-search-overlay__close{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 16px;border:1px solid var(--gloskin-border);border-radius:var(--gloskin-action-radius)' "$core_css" \
	|| fail "action radius: search overlay Cancel button no longer uses the shared token"
grep -qF -- '.gloskin-ui1-auth-forms .button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 18px;border:1px solid var(--gloskin-accent);border-radius:var(--gloskin-action-radius)' "$core_css" \
	|| fail "action radius: Auth submit button no longer uses the shared token"
grep -qF -- '.gloskin-ui1 a.added_to_cart.wc-forward{display:inline-flex;align-items:center;justify-content:center;min-height:38px;margin-top:8px;padding:8px 14px;border:1px solid var(--gloskin-border);border-radius:var(--gloskin-action-radius)' "$core_css" \
	|| fail "action radius: View Cart link no longer uses the shared token"
grep -qF -- '.woocommerce-account .woocommerce .button{border-radius:var(--gloskin-action-radius)}' "$readiness_css" \
	|| fail "action radius: My Account button no longer uses the shared token"

# The Auth login/register switch ("Masuk"/"Buat Akun") is a visible textual
# toggle, not an icon-only/decorative control -- it must share the same
# modest radius too, not stay a 999px pill.
grep -qF -- '.gloskin-ui1-auth-switch{display:flex;gap:6px;margin:0 0 22px;padding:4px;border-radius:var(--gloskin-action-radius)' "$readiness_css" \
	|| fail "action radius: Auth login/register switch container no longer uses the shared token"
grep -qF -- '.gloskin-ui1-auth-switch button{flex:1;min-height:38px;border:0;border-radius:var(--gloskin-action-radius)' "$readiness_css" \
	|| fail "action radius: Auth login/register switch buttons no longer use the shared token"

# WooCommerce Blocks (confirmed live on staging as the actual hydrated
# Cart/Checkout implementation -- no classic form present on either page):
# both real observed CTAs ("Proceed to Checkout", "Place Order") share
# Woo Blocks' own real .wc-block-components-button class.
grep -qF -- '.gloskin-ui1 .wc-block-components-button{border-radius:var(--gloskin-action-radius)}' "$readiness_css" \
	|| fail "action radius: WooCommerce Blocks Cart/Checkout CTA (.wc-block-components-button) no longer uses the shared token"

# Real staging proof (found live, not locally -- this repo has no way to
# load WooCommerce's own actual core CSS, whose real `.woocommerce a.button,
# .woocommerce button.button{border-radius:3px}` default otherwise wins
# over the generic base rule wherever a Woo `.button` co-class renders
# outside a nested `.gloskin-ui1 .woocommerce` ancestor chain -- e.g. Shop
# card/related/PDP Add to Cart controls that live directly under a body
# carrying Woo's own page-level classes). Radius must be hardened on the
# exact same three real-WooCommerce-class selectors already hardened for
# accent color, not left on the generic base rule alone.
for radius_hardened in \
	'.gloskin-ui1-card--product .gloskin-ui1-card__actions a.add_to_cart_button{background:var(--gloskin-accent);color:var(--gloskin-inverse);border-color:transparent;border-radius:var(--gloskin-action-radius)}' \
	'.gloskin-ui1 .gloskin-ui1-form form.cart button.single_add_to_cart_button{background:var(--gloskin-accent);border-color:var(--gloskin-accent);border-radius:var(--gloskin-action-radius);color:var(--gloskin-inverse)}' \
	'.related.products ul.products li.product .add_to_cart_button{background:var(--gloskin-accent);border-color:var(--gloskin-accent);border-radius:var(--gloskin-action-radius);color:var(--gloskin-inverse)}'; do
	grep -qF -- "$radius_hardened" "$core_css" || fail "action radius: production-hardened Add to Cart radius missing (would render at Woo's native 3px, not the modest 8px token): $radius_hardened"
done

# Intentional circular controls are explicitly preserved -- icon-only
# utility buttons, close icons, badges/counters, nav bubble.
for preserved in \
	'.gloskin-ui1-utility-btn{position:relative;display:inline-grid;width:40px;height:40px;border:0;border-radius:999px' \
	'.gloskin-ui1-badge{position:absolute;top:2px;right:2px;display:grid;min-width:17px;height:17px;padding:0 4px;border-radius:999px'; do
	grep -qF -- "$preserved" "$core_css" || fail "action radius: an intentionally-circular icon/badge control lost its radius: $preserved"
done
nav_bubble_rule="$(sed -n '/\.gloskin-ui1-nav__bubble{/,/^}/p' "$plugin_root/assets/css/gloskin-ui1-production.css")"
echo "$nav_bubble_rule" | grep -qF 'border-radius:999px;' || fail "action radius: nav bubble must stay circular"

# Custom Gloskin Add-to-Cart loader: reuses the existing .loading/
# [aria-busy="true"] state markers (Woo's own native ajax_add_to_cart.js
# and this plugin's own existing custom AJAX bridge, respectively) across
# Shop card, Quick Add and PDP purchase dock alike -- never a new
# fetch/network owner, never a Woo gear glyph.
grep -qF 'a.add_to_cart_button.loading::after' "$core_css" || fail "Add to Cart loader: shop-card/related loading state rule missing"
grep -qF 'button.single_add_to_cart_button.loading::after' "$core_css" || fail "Add to Cart loader: PDP/Quick Add loading state rule missing"
grep -qF '[aria-busy="true"]::after' "$core_css" || fail "Add to Cart loader: aria-busy loading state rule missing"
grep -qF '@keyframes gloskin-atc-spin{to{transform:rotate(360deg)}}' "$core_css" || fail "Add to Cart loader: spin animation missing"
grep -qF 'animation:gloskin-atc-spin 650ms linear infinite' "$core_css" || fail "Add to Cart loader: expected ~650ms rotation timing missing"
grep -qF 'border:2px solid currentColor' "$core_css" || fail "Add to Cart loader: ring must use currentColor"
if grep -qE '\.add_to_cart_button\.loading::after\{[^}]*content:"\\\\' "$core_css"; then
	fail "Add to Cart loader: Woo's own icon-font glyph content must be suppressed, not reused"
fi
grep -qF 'img.wc-loading{display:none}' "$core_css" || fail "Add to Cart loader: legacy Woo <img class=\"wc-loading\"> must also be suppressed"
if grep -RqE "fetch\(|XMLHttpRequest|\.ajax\(" <(sed -n '/gloskin-atc-spin/,/^$/p' "$core_css"); then
	fail "Add to Cart loader: must be presentation-only CSS, no new network logic"
fi

# ZAP-like PDP simplification via Woo's own documented hooks -- never a
# template fork, never CSS display:none over live rating/meta/sharing/
# related markup.
grep -qF "function simplify_single_product_summary()" "$adapter" || fail "PDP simplify: hook-owning method missing"
grep -qF "remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );" "$adapter" || fail "PDP simplify: rating summary must be removed via Woo's own hook"
grep -qF "remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );" "$adapter" || fail "PDP simplify: native SKU/category/tag meta block must be removed via Woo's own hook"
grep -qF "remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );" "$adapter" || fail "PDP simplify: sharing must be removed via Woo's own hook"
grep -qF "remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );" "$adapter" || fail "PDP simplify: Related Products must be removed via Woo's own hook"
grep -qF "add_action( 'woocommerce_before_single_product', array( \$this, 'simplify_single_product_summary' ) );" "$adapter" || fail "PDP simplify: removal must be deferred past Woo's own hook registration (load-order safe)"
if grep -qF "add_action( 'woocommerce_single_product_summary', array( \$this, 'render_wishlist_toggle' )" "$adapter"; then
	fail "PDP simplify: the PDP-only wishlist detail control must no longer be hooked into the primary summary"
fi
grep -qF "function render_wishlist_toggle()" "$adapter" || fail "PDP simplify: render_wishlist_toggle() itself must remain (header/product-card Wishlist stays untouched, only the PDP hook is removed)"
grep -qF "add_action( 'woocommerce_single_product_summary', array( \$this, 'render_product_facts' ), 21 );" "$adapter" || fail "PDP simplify: product facts must be rehooked directly into the primary summary (no longer depend on the removed meta wrapper)"
grep -qF 'return array();' "$description_boundary" || fail "PDP simplify: all Woo product tabs (Description/Additional Information/Reviews) must be removed"
grep -qF "add_filter( 'woocommerce_short_description', 'gloskin_ui1_render_primary_pdp_description' );" "$description_boundary" || fail "PDP simplify: the short description (now the one primary PDP body field) must reuse the existing safety-formatting pipeline"

# Live description merge: the display-time equivalent of the one-time
# admin consolidation action, reusing the exact same pure helper (never a
# second/divergent merge implementation).
grep -qF 'function gloskin_ui1_render_primary_pdp_description(' "$description_boundary" || fail "live description merge owner missing"
grep -qF 'Gloskin_Site_Core_WooCommerce_Adapter::consolidate_description_content(' "$description_boundary" || fail "live description merge must reuse the existing pure consolidate_description_content() helper, never a second implementation"
grep -qF 'return gloskin_ui1_format_product_description( $content );' "$description_boundary" || fail "live description merge must still route through the existing safety-formatting pipeline"

# "Cara Penggunaan" (usage) removed from the PDP facts display per client
# request; BPOM/Komposisi remain.
if grep -qF "'Cara Penggunaan'" "$adapter"; then
	fail "PDP facts: 'Cara Penggunaan' (usage) must no longer be rendered"
fi
grep -qF "'BPOM'" "$adapter" && grep -qF "'Komposisi'" "$adapter" || fail "PDP facts: BPOM/Komposisi must remain"

# Exactly one native form.cart is still wrapped (purchase dock unchanged)
# and no second cart-mutation owner was introduced by any of the above.
grep -qF "add_action( 'woocommerce_before_add_to_cart_form', array( \$this, 'open_purchase_dock' ) );" "$adapter" || fail "purchase dock regression: open hook missing after PDP simplification"
if grep -RInE "wp_ajax_(nopriv_)?gloskin_(add_to_cart|cart|checkout)" "$adapter" "$core_js"; then
	fail "PDP simplify: a second cart-mutation owner was introduced"
fi

# -----------------------------------------------------------------------
# Purchase dock spacing + Buy Now: settled-dock top spacing, action-area
# gap, and a Buy Now control that only ever triggers the SAME real submit
# button (never a second form/mutation owner), redirecting to the cart
# page on confirmed success.
# -----------------------------------------------------------------------
purchase_dock_js="$plugin_root/assets/js/gloskin-ui1-purchase-dock.js"

grep -qF 'body.single-product.gloskin-ui1 .gloskin-ui1-commerce-native>div.product>.gloskin-ui1-purchase-dock-home{grid-column:1/-1;width:100%;min-width:0;margin-top:24px}' "$geometry" \
	|| fail "purchase dock: settled-state top spacing missing"
grep -qF 'gloskin-ui1-purchase-dock__action{display:flex;align-items:center;justify-content:flex-end;gap:12px' "$geometry" \
	|| fail "purchase dock: quantity/Add-to-cart action area must have breathing room (gap)"

grep -qF "buyNowBefore = document.createElement('button');" "$purchase_dock_js" || fail "Buy Now: control creation missing"
grep -qF "buyNowBefore.type = 'button';" "$purchase_dock_js" || fail "Buy Now: must be type=button, never a second real form submit control"
grep -qF "actionRegion.appendChild(buyNowBefore);" "$purchase_dock_js" || fail "Buy Now: must be appended into the existing action region, not a duplicate structure"
grep -qF "submitBefore.setAttribute('data-gloskin-buy-now-redirect', '1');" "$purchase_dock_js" || fail "Buy Now: must flag the real submit button before triggering it"
grep -qF 'submitBefore.click();' "$purchase_dock_js" || fail "Buy Now: must trigger the SAME real native submit button's own click, never open a second mutation path"
if grep -RInE "wp_ajax_(nopriv_)?gloskin_(add_to_cart|cart|checkout)" "$purchase_dock_js"; then
	fail "Buy Now: a second cart-mutation owner was introduced"
fi
grep -qF 'buyNowBefore.disabled = !!submitBefore.disabled;' "$purchase_dock_js" || fail "Buy Now: enabled state must stay mirrored to the real submit button's own disabled state"

grep -qF 'function handleSingleProductAddToCartSuccess(submitter)' "$core_js" || fail "Buy Now: single-product success handler missing"
grep -qF "submitter.hasAttribute('data-gloskin-buy-now-redirect')" "$core_js" || fail "Buy Now: success handler must check the one-shot redirect flag"
grep -qF 'window.location.href = cartUrl;' "$core_js" || fail "Buy Now: confirmed success must redirect to the existing canonical cart URL"
# The normal (non-Buy-Now) Add to Cart path is directly canonical
# added_to_cart -> Cart overlay/count now that the PDP View Cart
# create-then-delete choreography is gone (asserted absent above).

grep -qF -- '.gloskin-ui1-purchase-dock__buy-now{display:inline-flex' "$geometry" || fail "Buy Now: button styling missing"
grep -qF -- 'border-radius:var(--gloskin-action-radius)' "$geometry" || fail "Buy Now: must share the one Action Kit radius token, not a bespoke pill"

echo "single-product commerce contract passed"
