<?php
declare(strict_types=1);

/**
 * Shop smart-search & dual-range price slider architecture contract.
 * Verifies: search trait composition, relevance ranking, SKU/taxonomy search,
 * dual-range slider wiring, IDR formatting, CSS custom props track fill,
 * strict no-monkeypatch / no-new-library / no-!important invariants.
 */

$root = dirname( __DIR__ );
$read = static function ( string $relative ) use ( $root ): string {
	$data = file_get_contents( $root . '/' . $relative );
	if ( false === $data ) { fwrite( STDERR, "FAIL: unable to read {$relative}\n" ); exit( 1 ); }
	return $data;
};
$ok = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
};

$search_trait = $read( 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-search-trait.php' );
$query_trait  = $read( 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-query-trait.php' );
$rest_trait   = $read( 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-rest-trait.php' );
$discovery    = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-shop-discovery.php' );
$batch        = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-production-batch.php' );
$shop_tpl     = $read( 'plugin/gloskin-site-core/templates/pages/shop.php' );
$results_tpl  = $read( 'plugin/gloskin-site-core/templates/parts/shop-results.php' );
$js           = $read( 'plugin/gloskin-site-core/assets/js/gloskin-ui1-shop-discovery.js' );
$css          = $read( 'plugin/gloskin-site-core/assets/css/gloskin-ui1-shop-discovery.css' );

/* ── Search trait composition ─────────────────────────────────────────────── */

$ok( false !== strpos( $search_trait, 'trait Gloskin_Site_Core_Shop_Discovery_Search_Trait' ),
	'search trait class declaration missing' );

$ok( false !== strpos( $search_trait, 'function normalize_q_tokens(' ),
	'token normalizer missing from search trait' );

$ok( false !== strpos( $search_trait, 'array_slice' ) && false !== strpos( $search_trait, ', 0, 5' ),
	'normalize_q_tokens must cap tokens at 5' );

$ok( false !== strpos( $search_trait, "preg_replace( '/[^\\\\p{L}\\\\p{N}\\\\s]/u'" ) ||
	 false !== strpos( $search_trait, "preg_replace('/[^\\\\p{L}\\\\p{N}\\\\s]/u'" ) ||
	 false !== strpos( $search_trait, '[^\p{L}\p{N}\s]' ),
	'token normalizer must strip punctuation via Unicode regex' );

$ok( false !== strpos( $search_trait, 'function searchable_product_taxonomies(' ),
	'searchable_product_taxonomies() missing from search trait' );

$ok( false !== strpos( $search_trait, "'product_cat'" ) && false !== strpos( $search_trait, "'product_tag'" ),
	'searchable_product_taxonomies must include product_cat and product_tag' );

$ok( false !== strpos( $search_trait, 'wc_get_attribute_taxonomies' ),
	'searchable_product_taxonomies must include wc attribute taxonomies (pa_*)' );

$ok( false !== strpos( $search_trait, 'function search_token_sql(' ),
	'search_token_sql() missing from search trait' );

$ok( false !== strpos( $search_trait, 'wc_product_meta_lookup' ) || false !== strpos( $search_trait, 'gloskin_sku_lookup' ),
	'search_token_sql must query SKU via lookup table (not postmeta scan)' );

$ok( false === strpos( $search_trait, "meta_key LIKE '%'" ) && false === strpos( $search_trait, "LIKE '%gloskin" ),
	'search trait must not perform arbitrary postmeta LIKE wildcard scan' );

$ok( false !== strpos( $search_trait, "'_global_unique_id'" ),
	'search trait must include _global_unique_id meta in explicit allowlist' );

$ok( false !== strpos( $search_trait, 'function build_relevance_sql(' ),
	'build_relevance_sql() missing from search trait' );

$ok( false !== strpos( $search_trait, 'CASE WHEN' ),
	'relevance ranking must use CASE WHEN scoring' );

$ok( false !== strpos( $search_trait, 'AS gloskin_relevance' ),
	'relevance alias gloskin_relevance missing' );

$ok( false !== strpos( $search_trait, 'function get_price_bounds(' ),
	'get_price_bounds() missing from search trait' );

$ok( false !== strpos( $search_trait, 'available_min' ) && false !== strpos( $search_trait, 'available_max' ) ||
	 false !== strpos( $search_trait, 'avail_min' ) && false !== strpos( $search_trait, 'avail_max' ),
	'get_price_bounds must return min/max aggregate' );

/* ── One-call prepare: no nested $wpdb->prepare() ───────────────────────────
   search_token_sql returns raw SQL template + params array; caller prepares once. */
$ok( false === strpos( $search_trait, '$wpdb->prepare( $tok' ) &&
	 false === strpos( $search_trait, '$wpdb->prepare($tok' ),
	'search_token_sql must NOT call $wpdb->prepare internally — callers do it once' );

/* ── Query trait: posts_clauses, not pre_get_posts ───────────────────────── */

$ok( false !== strpos( $query_trait, "'posts_clauses'" ),
	'query trait must use posts_clauses filter' );

$ok( false !== strpos( $query_trait, 'normalize_q_tokens(' ),
	'query trait must call normalize_q_tokens from search trait' );

$ok( false !== strpos( $query_trait, 'build_relevance_sql(' ),
	'query trait must append relevance score via build_relevance_sql' );

$ok( false !== strpos( $query_trait, 'gloskin_relevance DESC' ),
	'query trait must order by gloskin_relevance descending' );

$ok( false === strpos( $query_trait, "add_action( 'pre_get_posts'" ) &&
	 false === strpos( $query_trait, "add_filter( 'pre_get_posts'" ),
	'query trait must not register global pre_get_posts hook' );

/* ── REST trait: returns available bounds ────────────────────────────────── */

$ok( false !== strpos( $rest_trait, 'available_min_price' ),
	'REST response must include available_min_price' );

$ok( false !== strpos( $rest_trait, 'available_max_price' ),
	'REST response must include available_max_price' );

$ok( false !== strpos( $rest_trait, 'get_price_bounds(' ),
	'REST trait must call get_price_bounds() for dynamic bounds' );

/* ── Discovery class: 5 traits ───────────────────────────────────────────── */

$ok( false !== strpos( $discovery, 'Gloskin_Site_Core_Shop_Discovery_Search_Trait' ),
	'Discovery class must use Search trait' );

/* ── Production batch: search trait in require loop ─────────────────────── */

$ok( false !== strpos( $batch, "'search'" ) &&
	 false !== strpos( $batch, "'route', 'rest', 'query', 'normalize', 'search'" ),
	"production batch must include 'search' in the shop discovery require loop" );

/* ── Shop template: dual-range slider, pill search ───────────────────────── */

$ok( false !== strpos( $shop_tpl, 'data-gloskin-shop-min-price-slider' ),
	'shop template must have min price slider input' );

$ok( false !== strpos( $shop_tpl, 'data-gloskin-shop-max-price-slider' ),
	'shop template must have max price slider input' );

$ok( false !== strpos( $shop_tpl, 'type="range"' ),
	'price filter must use input[type=range]' );

$ok( 2 === substr_count( $shop_tpl, 'type="range"' ),
	'price filter must have exactly two range inputs (dual-range)' );

$ok( false !== strpos( $shop_tpl, 'data-gloskin-price-label-min' ) &&
	 false !== strpos( $shop_tpl, 'data-gloskin-price-label-max' ),
	'price labels must be bound via data attributes' );

$ok( false !== strpos( $shop_tpl, 'data-gloskin-price-avail-min' ) &&
	 false !== strpos( $shop_tpl, 'data-gloskin-price-avail-max' ),
	'available bounds must be stamped as data attributes for JS to read' );

$ok( false !== strpos( $shop_tpl, 'data-gloskin-shop-price-filter' ),
	'price filter wrapper data-attr missing from template' );

$ok( false !== strpos( $shop_tpl, 'data-gloskin-shop-price-reset' ),
	'price reset button missing from template' );

$ok( false !== strpos( $shop_tpl, 'data-gloskin-shop-search-form' ) &&
	 false !== strpos( $shop_tpl, 'data-gloskin-shop-search' ),
	'search form data-attrs missing from template' );

$ok( false !== strpos( $shop_tpl, 'Cari produk, SKU, atau kebutuhan kulit' ),
	'search field placeholder copy missing' );

$ok( false !== strpos( $shop_tpl, 'data-gloskin-shop-search-clear' ),
	'search clear button missing from template' );

$ok( false !== strpos( $shop_tpl, 'gloskin-ui1-shop-rail-section' ),
	'rail section wrapper class missing from template' );

/* No more numeric price inputs */
$ok( false === strpos( $shop_tpl, 'type="number"' ) ||
	 false === strpos( $shop_tpl, 'data-gloskin-shop-min-price' ),
	'template must not mix numeric-input price filter with new slider' );

/* ── Results partial: filtered empty state ───────────────────────────────── */

$ok( false !== strpos( $results_tpl, '$gloskin_shop_filtered' ),
	'results partial must extract filtered flag' );

$ok( false !== strpos( $results_tpl, 'gloskin-ui1-shop-empty-search' ),
	'filtered empty-state element missing from results partial' );

$ok( false !== strpos( $results_tpl, 'data-gloskin-shop-clear-search' ),
	'Reset pencarian button (data-gloskin-shop-clear-search) missing from results partial' );

/* ── JS: IDR formatting, slider wiring, no monkeypatch, no library ────────── */

$ok( false !== strpos( $js, 'function formatIDR(' ),
	'formatIDR() IDR currency formatter missing from JS' );

$ok( false !== strpos( $js, "replace(/\\\\B(?=(\\\\d{3})+(?!\\\\d))/g, '.')" ) ||
	 false !== strpos( $js, "replace(/\\B(?=(\\d{3})+(?!\\d))/g, '.')" ),
	'IDR dot-thousands separator regex missing from JS' );

$ok( false !== strpos( $js, "'Rp '" ) || false !== strpos( $js, "\"Rp \"" ) || false !== strpos( $js, "'Rp'" ),
	"IDR prefix 'Rp' missing from formatIDR" );

$ok( false !== strpos( $js, 'function renderSlider(' ),
	'renderSlider() missing from JS' );

$ok( false !== strpos( $js, 'function applySliderBounds(' ),
	'applySliderBounds() missing from JS — needed to update bounds from REST response' );

$ok( false !== strpos( $js, "'--gloskin-price-min-pct'" ) || false !== strpos( $js, '"--gloskin-price-min-pct"' ),
	'CSS custom prop --gloskin-price-min-pct not set from JS' );

$ok( false !== strpos( $js, "'--gloskin-price-max-pct'" ) || false !== strpos( $js, '"--gloskin-price-max-pct"' ),
	'CSS custom prop --gloskin-price-max-pct not set from JS' );

$ok( false !== strpos( $js, "addEventListener('input'" ) || false !== strpos( $js, "addEventListener( 'input'" ),
	'slider must respond to input event for visual-only update' );

$ok( false !== strpos( $js, "addEventListener('change'" ) || false !== strpos( $js, "addEventListener( 'change'" ),
	'slider must respond to change event to commit filter' );

$ok( false !== strpos( $js, 'data.available_min_price' ) && false !== strpos( $js, 'data.available_max_price' ),
	'JS must consume available_min_price / available_max_price from REST response' );

/* Visuals update immediately; fetch only on change — not on every input tick */
$ok( false === strpos( $js, "addEventListener('input', function () { onSliderChange" ) &&
	 false === strpos( $js, "addEventListener(\"input\", function () { onSliderChange" ),
	'slider must NOT call onSliderChange on input events (visual-only on input, fetch on change)' );

/* Strict invariants */
$ok( ! preg_match( '/window\.fetch\s*=(?!=)/', $js ),
	'window.fetch monkeypatch forbidden' );

$ok( ! preg_match( '/(?:window\.)?history\.pushState\s*=(?!=)/', $js ),
	'history.pushState monkeypatch forbidden' );

$ok( ! preg_match( '/(?:window\.)?history\.replaceState\s*=(?!=)/', $js ),
	'history.replaceState monkeypatch forbidden' );

$ok( false === strpos( $js, "require('" ) && false === strpos( $js, 'import ' ),
	'JS must not add new library imports/requires' );

/* ── CSS: custom props track fill, no !important ─────────────────────────── */

$ok( false !== strpos( $css, '--gloskin-price-min-pct' ),
	'CSS must define/use --gloskin-price-min-pct custom prop' );

$ok( false !== strpos( $css, '--gloskin-price-max-pct' ),
	'CSS must define/use --gloskin-price-max-pct custom prop' );

$ok( false !== strpos( $css, 'input[type=range]' ) || false !== strpos( $css, 'input[type="range"]' ) ||
	 false !== strpos( $css, 'price-slider__input' ),
	'CSS must style range input thumb/track' );

$ok( 0 === substr_count( $css, '!important' ),
	'discovery CSS must contain zero !important declarations' );

echo "shop smart-search contract: OK\n";
