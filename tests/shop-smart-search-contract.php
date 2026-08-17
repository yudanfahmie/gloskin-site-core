<?php
declare(strict_types=1);

/** Focused Shop discovery rail, price-state, and smart-search contract. */
$root = dirname( __DIR__ );
$read = static function ( string $relative ) use ( $root ): string {
	$data = file_get_contents( $root . '/' . $relative );
	if ( false === $data ) { fwrite( STDERR, "FAIL: unable to read {$relative}\n" ); exit( 1 ); }
	return $data;
};
$ok = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
};

$adapter_shop = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter-shop-catalog.php' );
$query_trait  = $read( 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-query-trait.php' );
$rest_trait   = $read( 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-rest-trait.php' );
$discovery    = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-shop-discovery.php' );
$batch        = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-production-batch.php' );
$route        = $read( 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-route-trait.php' );
$shop_tpl     = $read( 'plugin/gloskin-site-core/templates/pages/shop.php' );
$results_tpl  = $read( 'plugin/gloskin-site-core/templates/parts/shop-results.php' );
$js           = $read( 'plugin/gloskin-site-core/assets/js/gloskin-ui1-shop-discovery.js' );
$css          = $read( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-shop-discovery.css' );

/* Smart search remains adapter-owned and bounded. */
$ok( false !== strpos( $adapter_shop, 'final class Gloskin_Site_Core_WooCommerce_Adapter_Shop_Catalog' ), 'adapter-owned Shop catalog component missing' );
$ok( false !== strpos( $adapter_shop, 'function normalize_q_tokens(' ) && false !== strpos( $adapter_shop, ', 0, 5' ), 'multi-token normalizer/cap missing' );
$ok( false !== strpos( $adapter_shop, '[^\p{L}\p{N}\s]' ), 'token normalizer must strip punctuation via Unicode regex' );
$ok( false !== strpos( $adapter_shop, "'product_cat'" ) && false !== strpos( $adapter_shop, "'product_tag'" ), 'product_cat/product_tag search missing' );
$ok( false !== strpos( $adapter_shop, 'wc_get_attribute_taxonomies' ), 'registered pa_* search missing' );
$ok( false !== strpos( $adapter_shop, "taxonomy_exists( 'product_brand' )" ), 'registered product_brand guard missing' );
$ok( false !== strpos( $adapter_shop, "gl_meta.meta_key = '_global_unique_id'" ), 'global unique ID allowlist missing' );
$ok( false === strpos( $adapter_shop, "meta_key LIKE '%'" ) && false === strpos( $adapter_shop, '_product_attributes' ), 'arbitrary/serialized metadata search forbidden' );
$ok( false !== strpos( $adapter_shop, 'CASE WHEN' ) && false !== strpos( $adapter_shop, 'AS gloskin_relevance' ) && false !== strpos( $adapter_shop, 'gloskin_relevance DESC' ), 'weighted relevance must remain' );
$ok( false !== strpos( $adapter_shop, 'min( 12, absint( $per_page ) )' ), 'filtered catalog must stay bounded at 12/page' );
$ok( false !== strpos( $adapter_shop, 'gloskin_price_lookup.max_price >= %f' ) && false !== strpos( $adapter_shop, 'gloskin_price_lookup.min_price <= %f' ), 'Woo variable-price overlap semantics missing' );
$ok( false === strpos( $query_trait, 'WP_Query' ) && false === strpos( $query_trait, 'posts_clauses' ) && false === strpos( $query_trait, 'global $wpdb' ), 'Shop Discovery must own zero product SQL/query mechanics' );
$ok( false !== strpos( $query_trait, 'products_paginated_filtered( $page, self::PER_PAGE, $filters )' ), 'Discovery must delegate filters to adapter owner' );
$ok( false === strpos( $discovery, 'Shop_Discovery_Search_Trait' ) && false === strpos( $discovery, 'Shop_Discovery_Normalize_Trait' ), 'retired Discovery query/search owners must stay absent' );
$ok( false !== strpos( $batch, 'class-gloskin-site-core-woocommerce-adapter-shop-catalog.php' ) && false !== strpos( $batch, "array( 'route', 'rest', 'query' )" ), 'Production Batch adapter/query ownership regression' );

/* One existing endpoint and one browser request owner. */
$ok( false !== strpos( $route, "\$route = '/gloskin/v1/shop/catalog';" ), 'existing Shop endpoint extension missing' );
$ok( 1 === substr_count( $js, 'function requestCatalog(' ) && 1 === substr_count( $js, 'return window.fetch(' ), 'one catalog request/fetch owner expected' );
$ok( false !== strpos( $js, 'new window.AbortController()' ) && false !== strpos( $js, 'sequence !== requestSequence' ), 'AbortController/stale guard missing' );
$ok( ! preg_match( '/window\.fetch\s*=(?!=)/', $js ), 'window.fetch monkeypatch forbidden' );
$ok( ! preg_match( '/(?:window\.)?history\.(?:pushState|replaceState)\s*=(?!=)/', $js ), 'History monkeypatch forbidden' );

/* Canonical Gloskin skin only. */
$ok( false === stripos( $css, '#2d6a4f' ) && false === stripos( $css, '45 106 79' ), 'non-Gloskin green palette drift must be zero' );
foreach ( array( '--gloskin-accent', '--gloskin-accent-readable', '--gloskin-accent-soft', '--gloskin-brand-champagne', '--gloskin-border', '--gloskin-muted', '--gloskin-field-focus-ring' ) as $token ) {
	$ok( false !== strpos( $css, $token ), "canonical token missing: {$token}" );
}
$ok( 0 === substr_count( $css, '!important' ), 'discovery CSS must add zero !important declarations' );

/* Dead numeric/Apply generation is gone. */
$ok( false === strpos( $shop_tpl, 'type="number"' ) && false === strpos( $css, 'input[type="number"]' ), 'legacy numeric price inputs/rules must be absent' );
$ok( false === strpos( $css, 'price-grid' ) && false === strpos( $css, 'shop-filter__actions' ), 'legacy price-grid/filter-action CSS must be absent' );
$ok( false === strpos( $shop_tpl, '>Terapkan<' ) && false === strpos( $shop_tpl, '>Apply<' ), 'legacy Apply button must be absent' );

/* Rail is the effective sticky owner; categories are explicitly neutralized. */
$ok( false !== strpos( $css, '.gloskin-ui1-shop-catalog__rail {' ) && false !== strpos( $css, 'position: sticky;' ), 'complete Shop rail must be desktop sticky owner' );
$ok( false !== strpos( $css, 'top: calc(var(--gloskin-ui1-nav-sticky-top) + var(--gloskin-shop-rail-sticky-clearance));' ), 'sticky top must use canonical nav/admin offset token' );
$ok( false !== strpos( $css, '.gloskin-ui1-shop-categories {' ) && false !== strpos( $css, 'position: static;' ) && false !== strpos( $css, 'border: 0;' ), 'category-only sticky/border owner must be neutralized' );
$ok( false !== strpos( $css, '.gloskin-ui1-shop-rail-section:not(:last-of-type)' ), 'dividers must exist only between logical rail sections' );
$ok( false !== strpos( $css, '@media (max-width: 900px)' ) && false !== strpos( $css, '.gloskin-ui1-shop-catalog__rail {' ) && false !== strpos( $css, 'top: auto;' ), 'single-column breakpoint must disable rail sticky' );
$ok( false === strpos( $css, 'overflow-x: auto' ) && false === strpos( $css, 'width: max-content' ), 'mobile categories must remain coherent vertical flow without horizontal strip overflow' );
$ok( false !== strpos( $css, '.gloskin-ui1-shop-filter__clear {' ) && false !== strpos( $css, 'background: transparent;' ) && false !== strpos( $css, 'width: auto;' ), 'Clear All must be lightweight rather than a full-width bordered box' );
$ok( false === strpos( $js, "addEventListener('scroll'" ) && false === strpos( $js, 'IntersectionObserver' ), 'sticky behavior must have zero JS owner' );

/* Price availability is explicit: normal / single / empty, never fictional. */
foreach ( array( "'normal'", "'single'", "'empty'" ) as $state ) {
	$ok( false !== strpos( $adapter_shop, $state ) && false !== strpos( $rest_trait, $state ), "price state missing: {$state}" );
}
$price_bounds_pos = strpos( $adapter_shop, 'public function price_bounds(' );
$price_bounds_src = false === $price_bounds_pos ? '' : substr( $adapter_shop, $price_bounds_pos, 6500 );
$ok( false !== strpos( $price_bounds_src, "return array( 'state' => 'empty', 'min' => null, 'max' => null )" ), 'empty price availability must be explicit' );
$ok( false !== strpos( $price_bounds_src, "'state' => \$max > \$min ? 'normal' : 'single'" ), 'equal real bounds must become single-price state' );
$ok( false === strpos( $price_bounds_src, '5000000' ), 'price_bounds must not invent 5,000,000 fallback' );

$bounds_pos  = strpos( $rest_trait, '$bounds      = $this->catalog_price_bounds( $category, $q );' );
$catalog_pos = strpos( $rest_trait, '$catalog = $this->catalog( $page, $filters );' );
$ok( false !== $bounds_pos && false !== $catalog_pos && $bounds_pos < $catalog_pos, 'same response must resolve price availability before its one catalog query' );
$ok( false !== strpos( $rest_trait, '$effective_min = null;' ) && false !== strpos( $rest_trait, '$effective_max = null;' ), 'single/empty/disjoint stale price state must be cleared server-side' );
$ok( false !== strpos( $rest_trait, 'max( $available_min, min(' ), 'normal stale price bounds must clamp to real availability' );
$ok( false !== strpos( $rest_trait, '$effective_min <= $available_min' ) && false !== strpos( $rest_trait, '$effective_max >= $available_max' ), 'clamped full-range edges must clear ghost active price filters' );
$ok( false !== strpos( $rest_trait, "'price_state'         => \$price_state" ), 'REST price_state field missing' );

$ok( false !== strpos( $shop_tpl, 'data-gloskin-price-state=' ), 'SSR price state hydration missing' );
$ok( false !== strpos( $shop_tpl, "__( 'Harga belum tersedia', 'gloskin-site-core' )" ), 'empty price availability copy missing' );
$ok( 2 === substr_count( $shop_tpl, 'type="range"' ), 'normal price control remains dual native range inputs' );
$ok( false !== strpos( $shop_tpl, "'normal' === \$gloskin_shop_price_state ? '' : ' hidden disabled'" ), 'single/empty must expose no focusable ghost range handles' );

$ok( false !== strpos( $js, 'function normalizePriceState(' ) && false !== strpos( $js, 'function applySliderBounds(' ), 'client price-state normalization missing' );
$ok( false !== strpos( $js, "priceState === 'single'" ) && false !== strpos( $js, "priceState === 'empty'" ), 'single/empty client states missing' );
$ok( false !== strpos( $js, "priceMinLabel.textContent = 'Harga belum tersedia'" ), 'empty client copy missing' );
$ok( false !== strpos( $js, 'slider.disabled = !normal;' ) && false !== strpos( $js, 'slider.hidden = !normal;' ), 'single/empty handles must be disabled and hidden' );
$ok( false !== strpos( $js, 'applySliderBounds(' ) && false !== strpos( $js, 'data.price_state' ) && false !== strpos( $js, 'data.available_min_price' ) && false !== strpos( $js, 'data.available_max_price' ), 'same catalog response must apply price state/bounds' );
$ok( 1 === substr_count( $js, 'return window.fetch(' ), 'price availability must not add a second REST request' );

/* Existing result/semantic wiring remains. */
$ok( false !== strpos( $results_tpl, '$gloskin_shop_filtered' ) && false !== strpos( $results_tpl, 'gloskin-ui1-shop-empty-search' ), 'filtered empty-state regression' );
$ok( false !== strpos( $shop_tpl, 'data-gloskin-shop-search-form' ) && false !== strpos( $shop_tpl, 'Cari produk, SKU, atau kebutuhan kulit' ), 'smart search presentation missing' );

echo "shop smart-search contract: OK\n";
