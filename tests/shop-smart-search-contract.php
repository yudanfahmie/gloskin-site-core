<?php
declare(strict_types=1);

/** Shop smart-search & dual-range price slider architecture contract. */
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
$shop_tpl     = $read( 'plugin/gloskin-site-core/templates/pages/shop.php' );
$results_tpl  = $read( 'plugin/gloskin-site-core/templates/parts/shop-results.php' );
$js           = $read( 'plugin/gloskin-site-core/assets/js/gloskin-ui1-shop-discovery.js' );
$css          = $read( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-shop-discovery.css' );

/* Smart search/query implementation is adapter-owned, not Shop Discovery-owned. */
$ok( false !== strpos( $adapter_shop, 'final class Gloskin_Site_Core_WooCommerce_Adapter_Shop_Catalog' ), 'adapter-owned Shop catalog component missing' );
$ok( false !== strpos( $adapter_shop, 'function normalize_q_tokens(' ), 'adapter query token normalizer missing' );
$ok( false !== strpos( $adapter_shop, 'array_slice' ) && false !== strpos( $adapter_shop, ', 0, 5' ), 'query token normalizer must cap tokens at 5' );
$ok( false !== strpos( $adapter_shop, '[^\p{L}\p{N}\s]' ), 'token normalizer must strip punctuation via Unicode regex' );
$ok( false !== strpos( $adapter_shop, 'function searchable_product_taxonomies(' ), 'adapter searchable taxonomy helper missing' );
$ok( false !== strpos( $adapter_shop, "'product_cat'" ) && false !== strpos( $adapter_shop, "'product_tag'" ), 'search must include product_cat/product_tag' );
$ok( false !== strpos( $adapter_shop, 'wc_get_attribute_taxonomies' ), 'search must include Woo attribute taxonomies' );
$ok( false !== strpos( $adapter_shop, 'function search_token_sql(' ), 'adapter search_token_sql missing' );
$ok( false !== strpos( $adapter_shop, 'wc_product_meta_lookup' ), 'SKU/price search must use Woo lookup data' );
$ok( false === strpos( $adapter_shop, "meta_key LIKE '%'" ) && false === strpos( $adapter_shop, "LIKE '%gloskin" ), 'adapter search must not perform arbitrary postmeta LIKE scan' );
$ok( false !== strpos( $adapter_shop, "'_global_unique_id'" ), 'explicit global unique ID allowlist missing' );
$ok( false !== strpos( $adapter_shop, 'function build_relevance_sql(' ) && false !== strpos( $adapter_shop, 'CASE WHEN' ) && false !== strpos( $adapter_shop, 'AS gloskin_relevance' ), 'smart relevance ranking missing' );
$ok( false !== strpos( $adapter_shop, 'public function price_bounds(' ) && false !== strpos( $adapter_shop, 'avail_min' ) && false !== strpos( $adapter_shop, 'avail_max' ), 'adapter-owned dynamic price bounds missing' );
$ok( false !== strpos( $adapter_shop, '.post_title LIKE %s' ) && false !== strpos( $adapter_shop, '.post_excerpt LIKE %s' ) && false !== strpos( $adapter_shop, '.post_content LIKE %s' ), 'search must cover title/excerpt/content' );
$ok( false !== strpos( $adapter_shop, "'posts_clauses'" ) && false !== strpos( $adapter_shop, 'gloskin_relevance DESC' ), 'scoped adapter relevance query missing' );
$ok( false === strpos( $adapter_shop, "add_action( 'pre_get_posts'" ) && false === strpos( $adapter_shop, "add_filter( 'pre_get_posts'" ), 'global pre_get_posts hook forbidden' );
$ok( false === strpos( $query_trait, 'posts_clauses' ) && false === strpos( $query_trait, 'WP_Query' ) && false === strpos( $query_trait, 'global $wpdb' ), 'Shop Discovery must contain zero query SQL ownership' );
$ok( false !== strpos( $query_trait, 'products_paginated_filtered( $page, self::PER_PAGE, $filters )' ), 'Shop Discovery must delegate full filter state to adapter-owned API' );
$ok( false !== strpos( $rest_trait, 'available_min_price' ) && false !== strpos( $rest_trait, 'available_max_price' ), 'REST response must include dynamic available bounds' );
$ok( false !== strpos( $rest_trait, 'catalog_price_bounds( $category, $q )' ), 'REST bounds must delegate to adapter-owned query component' );
$ok( false === strpos( $discovery, 'Shop_Discovery_Search_Trait' ) && false === strpos( $discovery, 'Shop_Discovery_Normalize_Trait' ), 'Discovery must not compose retired query/search normalization owners' );
$ok( false !== strpos( $batch, 'class-gloskin-site-core-woocommerce-adapter-shop-catalog.php' ) && false !== strpos( $batch, "array( 'route', 'rest', 'query' )" ), 'Production Batch must load adapter query owner and only orchestration traits' );

/* Shop template: dual-range slider, pill search. */
$ok( false !== strpos( $shop_tpl, 'data-gloskin-shop-min-price-slider' ) && false !== strpos( $shop_tpl, 'data-gloskin-shop-max-price-slider' ), 'dual range inputs missing' );
$ok( 2 === substr_count( $shop_tpl, 'type="range"' ), 'price filter must have exactly two range inputs' );
$ok( false !== strpos( $shop_tpl, 'data-gloskin-price-label-min' ) && false !== strpos( $shop_tpl, 'data-gloskin-price-label-max' ), 'price labels must remain bound' );
$ok( false !== strpos( $shop_tpl, 'data-gloskin-price-avail-min' ) && false !== strpos( $shop_tpl, 'data-gloskin-price-avail-max' ), 'available bounds hydration missing' );
$ok( false !== strpos( $shop_tpl, 'data-gloskin-shop-price-filter' ) && false !== strpos( $shop_tpl, 'data-gloskin-shop-price-reset' ), 'price filter/reset wiring missing' );
$ok( false !== strpos( $shop_tpl, 'data-gloskin-shop-search-form' ) && false !== strpos( $shop_tpl, 'data-gloskin-shop-search' ), 'search wiring missing' );
$ok( false !== strpos( $shop_tpl, 'Cari produk, SKU, atau kebutuhan kulit' ), 'smart-search placeholder missing' );
$ok( false !== strpos( $shop_tpl, 'data-gloskin-shop-search-clear' ) && false !== strpos( $shop_tpl, 'gloskin-ui1-shop-rail-section' ), 'search clear/rail layout missing' );

/* Results empty-state contract. */
$ok( false !== strpos( $results_tpl, '$gloskin_shop_filtered' ) && false !== strpos( $results_tpl, 'gloskin-ui1-shop-empty-search' ) && false !== strpos( $results_tpl, 'data-gloskin-shop-clear-search' ), 'filtered empty state regression' );

/* JS: IDR formatting, slider wiring, no monkeypatch/library. */
$ok( false !== strpos( $js, 'function formatIDR(' ) && false !== strpos( $js, "'Rp '" ), 'IDR formatter missing' );
$ok( false !== strpos( $js, 'function renderSlider(' ) && false !== strpos( $js, 'function applySliderBounds(' ), 'slider renderer/bounds wiring missing' );
$ok( false !== strpos( $js, "'--gloskin-price-min-pct'" ) && false !== strpos( $js, "'--gloskin-price-max-pct'" ), 'slider CSS custom props missing' );
$ok( false !== strpos( $js, "addEventListener('input'" ) && false !== strpos( $js, "addEventListener('change'" ), 'slider input/change events missing' );
$ok( false !== strpos( $js, 'data.available_min_price' ) && false !== strpos( $js, 'data.available_max_price' ), 'JS must consume dynamic bounds' );
$ok( ! preg_match( '/window\.fetch\s*=(?!=)/', $js ), 'window.fetch monkeypatch forbidden' );
$ok( ! preg_match( '/(?:window\.)?history\.pushState\s*=(?!=)/', $js ) && ! preg_match( '/(?:window\.)?history\.replaceState\s*=(?!=)/', $js ), 'History monkeypatch forbidden' );
$ok( false === strpos( $js, "require('" ) && false === strpos( $js, 'import ' ), 'new JS library forbidden' );

/* CSS: slider custom props, no !important. */
$ok( false !== strpos( $css, '--gloskin-price-min-pct' ) && false !== strpos( $css, '--gloskin-price-max-pct' ), 'slider custom props missing from CSS' );
$ok( false !== strpos( $css, 'input[type=range]' ) || false !== strpos( $css, 'price-slider__input' ), 'range input styling missing' );
$ok( 0 === substr_count( $css, '!important' ), 'discovery CSS must contain zero !important declarations' );

echo "shop smart-search contract: OK\n";
