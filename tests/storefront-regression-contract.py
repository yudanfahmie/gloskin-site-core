#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def read(rel):
    return (ROOT / rel).read_text(encoding='utf-8')

def require(cond, message):
    if not cond:
        raise AssertionError(message)

js = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js')
css = read('plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css')
header = read('plugin/gloskin-site-core/templates/parts/header.php')
template_service = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php')
woo = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php')
assets = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php')
plugin = read('plugin/gloskin-site-core/gloskin-site-core.php')
kernel = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')

# All four Gloskin REST reads are public projections and share one nonce-free
# GET option owner. A stale nonce must be unable to poison guest transport.
require("function publicRestGetOptions()" in js, 'shared public REST GET transport helper missing')
require("return { method: 'GET', credentials: 'same-origin' };" in js, 'public GET helper must stay read-only/same-origin')
require('X-WP-Nonce' not in js and 'restNonce' not in js, 'public GET client must not send/read REST nonce')
require("wp_create_nonce( 'wp_rest' )" not in header and 'restNonce' not in header, 'header must not localize an unused public REST nonce')
require(js.count('publicRestGetOptions()') == 5, 'expected helper declaration plus exactly four public GET call sites')
for endpoint in ('search?q=', 'products/quick-add?id=', 'shop/catalog?category=', 'products/resolve?ids='):
    require(endpoint in js, f'public projection endpoint missing: {endpoint}')
require("window.fetch(config.addToCartAjaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })" in js, 'Woo add-to-cart mutation POST must remain untouched')

# Public server permissions must stay genuinely guest-readable.
for route in ("'/search'", "'/shop/catalog'", "'/products/resolve'", "'/products/quick-add'"):
    owner = template_service if route in ("'/search'", "'/shop/catalog'") else woo
    pos = owner.find(route)
    require(pos >= 0, f'route missing: {route}')
    block = owner[pos:pos + 900]
    require("'methods'             => 'GET'" in block, f'{route} must remain GET-only')
    require("'permission_callback' => '__return_true'" in block, f'{route} must remain public/guest-readable')

# Shop behavior remains the same one AbortController/requestSequence/results
# owner; only its transport changed.
for invariant in ('var requestSequence = 0', 'new window.AbortController()', 'sequence !== requestSequence', "results.setAttribute('aria-busy', busy ? 'true' : 'false')", 'showCatalogFailure(fallbackHref)', "document.dispatchEvent(new CustomEvent('gloskin:catalog-updated'"):
    require(invariant in js, f'Shop invariant missing: {invariant}')

# Mini-cart removal stays Woo-native: native class/key in markup plus native
# Woo handle enqueued by the one AssetService. Gloskin owns presentation only.
require('remove_from_cart_button gloskin-ui1-cart-sheet__item-remove' in woo, 'mini-cart remove link lost Woo native delegated class')
require('data-cart_item_key=' in woo, 'mini-cart remove link lost Woo cart item key')
require("if ( wp_script_is( 'wc-add-to-cart', 'registered' ) )" in assets and "wp_enqueue_script( 'wc-add-to-cart' );" in assets, 'AssetService must enqueue Woo native add-to-cart/remove runtime when registered')
require("remove_from_cart_button" in js and "removed_from_cart" in js, 'cart pending presentation must remain tied to Woo lifecycle')
require('wc-ajax=remove_from_cart' not in js and "remove_from_cart'" not in js, 'Gloskin must not create a second cart removal request owner')

# Wishlist count is only another reflection of the existing localStorage owner.
require(header.count('data-gloskin-wishlist-count aria-hidden="true"') == 2, 'both existing header wishlist controls need count badges')
require(header.count('data-gloskin-wishlist-count-sr aria-live="polite"') == 2, 'both wishlist controls need accessible count reflection')
require("var count = getIds().length;" in js, 'wishlist count must derive from existing getIds owner')
require("document.querySelectorAll('[data-gloskin-wishlist-count]')" in js, 'wishlist visual count reflection missing')
require("document.querySelectorAll('[data-gloskin-wishlist-count-sr]')" in js, 'wishlist accessible count reflection missing')
require("localStorage.setItem(STORAGE_KEY" in js and js.count("var STORAGE_KEY = 'gloskin_wishlist'") == 1, 'wishlist must keep one localStorage owner')

# Wishlist zero-badge: updateBadges() stays the sole badge owner, but must
# also hide the bubble at count 0 -- a visible display:grid badge otherwise
# defeats the [hidden] attribute, so one wishlist-scoped CSS rule (never
# touching the Cart badge, never !important) is required alongside it.
require('badge.hidden = count < 1;' in js, 'updateBadges() must hide the wishlist badge at count 0')
require('[data-gloskin-wishlist-count][hidden]{display:none}' in css, 'wishlist-specific hidden-badge CSS rule missing')
require('!important' not in css.split('[data-gloskin-wishlist-count][hidden]')[1][:40], 'wishlist hidden-badge rule must not use !important')
require(css.count('[data-gloskin-wishlist-count][hidden]') == 1, 'wishlist hidden-badge rule must not duplicate/generalize to Cart badge')

# Live staging proof (2026-08-13): wc_get_products( array( 'paginate' => true,
# ... ) ) with no 'category' arg -- the only shape the unfiltered "Semua
# Produk" REST projection used -- fatals deterministically and uncatchably
# through rest_shop_catalog() (a try/catch boundary around the identical
# call never even triggered); every mapped category (which always sets
# 'category') already proved safe. The unfiltered branch must sidestep the
# broken bulk-paginate query entirely via the existing products_paginated()
# owner, using the same get_posts()-based approach already proven safe by
# WordPress's own unfiltered /wp-json/wp/v2/product projection, then
# hydrate each page's few IDs with the same wc_get_product() every other
# single-product lookup in this class already uses.
require('private function products_paginated_unfiltered(' in woo, 'products_paginated() must keep a dedicated unfiltered-branch owner')
require("'post_type'      => 'product'" in woo and "'fields'         => 'ids'" in woo, 'unfiltered branch must resolve IDs via get_posts(), not the broken wc_get_products() paginate path')
require('wc_get_product( $id )' in woo, 'unfiltered branch must hydrate each page slice with the existing single-product Woo lookup')
require("return $this->products_paginated_unfiltered( $page, $per_page );" in woo, 'products_paginated() must delegate the empty-category case to the dedicated safe owner')

header_version = re.search(r'\* Version:\s*([0-9.]+)', plugin).group(1)
kernel_version = re.search(r"const VERSION = '([^']+)'", kernel).group(1)
require(header_version == kernel_version == '0.7.51', 'production version must be synchronized at 0.7.51')

print('storefront regression contract: OK')
