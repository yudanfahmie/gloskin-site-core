<?php
declare(strict_types=1);

/**
 * Gloskin Catalog Discovery v1 -- proves public catalog projections and live
 * search stay Woo-native. Catalog uses exclude-from-catalog; live search uses
 * exclude-from-search, so Search only remains searchable while Catalog only
 * and Hidden do not leak into search results.
 */

define( 'ABSPATH', __DIR__ . '/' );

function ok( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class WP_Error {}
class WP_Term {
	public $term_id;
	public $slug;
	public function __construct( $slug ) { $this->term_id = crc32( (string) $slug ); $this->slug = $slug; }
}
class WooCommerce {}

$GLOBALS['gl_catalog'] = array();
$GLOBALS['gl_last_get_posts_args'] = array();

class Fixture_Product {
	public $id;
	public $name;
	public $status;
	public $type = 'simple';
	public $categories = array();
	public $catalog_visibility = 'visible';
	public $purchasable = true;
	public $in_stock = true;
	public $sku = '';
	public function __construct( array $data ) {
		foreach ( $data as $key => $value ) { $this->$key = $value; }
	}
	public function get_id() { return $this->id; }
	public function get_name() { return $this->name; }
	public function get_status() { return $this->status; }
	public function get_type() { return $this->type; }
	public function get_image_id() { return 0; }
	public function get_price_html() { return '<span class="amount">100</span>'; }
	public function get_short_description() { return 'Deskripsi produk.'; }
	public function get_sku() { return $this->sku; }
	public function add_to_cart_url() { return 'https://example.test/?add-to-cart=' . $this->id; }
	public function add_to_cart_text() { return 'Tambah ke keranjang'; }
	public function add_to_cart_description() { return 'Add to cart'; }
	public function is_purchasable() { return $this->purchasable; }
	public function is_in_stock() { return $this->in_stock; }
	public function supports( $feature ) { return 'ajax_add_to_cart' === $feature; }
}

function wc_get_product_visibility_term_ids() {
	return array( 'exclude-from-catalog' => 999, 'exclude-from-search' => 998 );
}

/** @return array<int,int> */
function excluded_visibility_terms( $args ) {
	$excluded = array();
	foreach ( (array) ( $args['tax_query'] ?? array() ) as $clause ) {
		if ( ! is_array( $clause ) || 'product_visibility' !== ( $clause['taxonomy'] ?? '' ) || 'NOT IN' !== ( $clause['operator'] ?? '' ) ) {
			continue;
		}
		$excluded = array_merge( $excluded, array_map( 'intval', (array) ( $clause['terms'] ?? array() ) ) );
	}
	return array_values( array_unique( $excluded ) );
}

function product_has_visibility_term( $product, $term_id ) {
	if ( 999 === (int) $term_id ) {
		return in_array( $product->catalog_visibility, array( 'search', 'hidden' ), true );
	}
	if ( 998 === (int) $term_id ) {
		return in_array( $product->catalog_visibility, array( 'catalog', 'hidden' ), true );
	}
	return false;
}

/** Minimal WC_Product_Query simulation used by catalog projections. */
function wc_get_products( $args ) {
	$results = array();
	$excluded_terms = excluded_visibility_terms( $args );
	foreach ( $GLOBALS['gl_catalog'] as $product ) {
		if ( isset( $args['status'] ) && $product->status !== $args['status'] ) { continue; }
		if ( ! empty( $args['category'] ) && ! array_intersect( (array) $args['category'], $product->categories ) ) { continue; }
		$excluded = false;
		foreach ( $excluded_terms as $term_id ) {
			if ( product_has_visibility_term( $product, $term_id ) ) { $excluded = true; break; }
		}
		if ( $excluded ) { continue; }
		$results[] = $product;
	}
	if ( ! empty( $args['paginate'] ) ) {
		$per_page = max( 1, (int) ( $args['limit'] ?? 10 ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$total    = count( $results );
		return (object) array(
			'products'      => array_slice( $results, ( $page - 1 ) * $per_page, $per_page ),
			'total'         => $total,
			'max_num_pages' => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}
	if ( isset( $args['limit'] ) ) { $results = array_slice( $results, 0, (int) $args['limit'] ); }
	return $results;
}

/** WordPress get_posts simulation used by live product search and the
 *  unfiltered shop catalog projection (real get_posts() honors 'fields' =>
 *  'ids' by returning plain integer IDs instead of post objects). */
function get_posts( $args ) {
	$GLOBALS['gl_last_get_posts_args'] = $args;
	$excluded_terms = excluded_visibility_terms( $args );
	$needle = strtolower( (string) ( $args['s'] ?? '' ) );
	$posts = array();
	foreach ( $GLOBALS['gl_catalog'] as $product ) {
		if ( 'product' !== ( $args['post_type'] ?? 'product' ) ) { continue; }
		if ( isset( $args['post_status'] ) && $product->status !== $args['post_status'] ) { continue; }
		if ( '' !== $needle && false === strpos( strtolower( $product->name ), $needle ) ) { continue; }
		$excluded = false;
		foreach ( $excluded_terms as $term_id ) {
			if ( product_has_visibility_term( $product, $term_id ) ) { $excluded = true; break; }
		}
		if ( $excluded ) { continue; }
		$posts[] = 'ids' === ( $args['fields'] ?? '' ) ? $product->id : (object) array(
			'ID'           => $product->id,
			'post_title'   => $product->name,
			'post_content' => 'Deskripsi ' . $product->name,
			'post_excerpt' => '',
		);
	}
	$limit = (int) ( $args['posts_per_page'] ?? 5 );
	return $limit < 0 ? $posts : array_slice( $posts, 0, max( 1, $limit ) );
}

function wc_get_product( $id ) { return $GLOBALS['gl_catalog'][ (int) $id ] ?? null; }
function get_term_by( $field, $value, $taxonomy ) {
	if ( 'product_cat' !== $taxonomy ) { return false; }
	foreach ( $GLOBALS['gl_catalog'] as $product ) {
		if ( in_array( $value, $product->categories, true ) ) { return new WP_Term( $value ); }
	}
	return false;
}
function get_term_link( $term ) { return 'https://example.test/product-category/' . $term->slug . '/'; }
function get_permalink( $post_or_id ) {
	$id = is_object( $post_or_id ) && isset( $post_or_id->ID ) ? $post_or_id->ID : (int) $post_or_id;
	return 'https://example.test/product/' . $id . '/';
}
function get_the_title( $post ) { return is_object( $post ) ? (string) $post->post_title : ''; }
function has_excerpt( $post ) { return is_object( $post ) && '' !== trim( (string) ( $post->post_excerpt ?? '' ) ); }
function get_the_excerpt( $post ) { return is_object( $post ) ? (string) ( $post->post_excerpt ?? '' ) : ''; }
function get_post_thumbnail_id( $id ) { return 0; }
function wp_trim_words( $text, $limit = 55 ) { return (string) $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_title( $value ) { $value = strtolower( trim( (string) $value ) ); return preg_replace( '/[^a-z0-9-]+/', '-', $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_strip_all_tags( $value ) { return trim( strip_tags( (string) $value ) ); }
function __( $text, $domain = 'default' ) { return $text; }
if ( ! function_exists( 'mb_strlen' ) ) { function mb_strlen( $value ) { return strlen( (string) $value ); } }

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php';

$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();

function seed( array $products ) {
	$GLOBALS['gl_catalog'] = array();
	foreach ( $products as $p ) {
		$fixture = new Fixture_Product( array_merge( array( 'sku' => 'GLS-' . $p['id'] ), $p ) );
		$GLOBALS['gl_catalog'][ $fixture->id ] = $fixture;
	}
}

/* Published catalog only; drafts do not leak. */
seed( array(
	array( 'id' => 1, 'name' => 'Published Simple', 'status' => 'publish', 'categories' => array( 'serum' ) ),
	array( 'id' => 2, 'name' => 'Draft Parent', 'status' => 'draft', 'categories' => array( 'serum' ) ),
) );
$catalog = $adapter->products_paginated( 1, 12 );
ok( 1 === count( $catalog['products'] ), 'shop catalog projection returns only published products' );
ok( 'Published Simple' === $catalog['products'][0]['name'], 'correct published product surfaced' );

/* Catalog semantics: Search only + Hidden are excluded from catalog. */
seed( array(
	array( 'id' => 3, 'name' => 'Visible', 'status' => 'publish', 'categories' => array( 'serum' ) ),
	array( 'id' => 4, 'name' => 'Search Only', 'status' => 'publish', 'categories' => array( 'serum' ), 'catalog_visibility' => 'search' ),
	array( 'id' => 5, 'name' => 'Catalog Only', 'status' => 'publish', 'categories' => array( 'serum' ), 'catalog_visibility' => 'catalog' ),
	array( 'id' => 6, 'name' => 'Hidden', 'status' => 'publish', 'categories' => array( 'serum' ), 'catalog_visibility' => 'hidden' ),
) );
$visibility_catalog = $adapter->products_paginated( 1, 12 );
$catalog_names = array_column( $visibility_catalog['products'], 'name' );
ok( in_array( 'Visible', $catalog_names, true ), 'visible product appears in catalog' );
ok( in_array( 'Catalog Only', $catalog_names, true ), 'catalog-only product remains in catalog' );
ok( ! in_array( 'Search Only', $catalog_names, true ), 'search-only product excluded from catalog' );
ok( ! in_array( 'Hidden', $catalog_names, true ), 'hidden product excluded from catalog' );

/* Live-search semantics: Visible + Search only appear; exclude-from-search does not. */
seed( array(
	array( 'id' => 20, 'name' => 'Searchable Visible', 'status' => 'publish', 'categories' => array( 'serum' ) ),
	array( 'id' => 21, 'name' => 'Searchable Search Only', 'status' => 'publish', 'categories' => array( 'serum' ), 'catalog_visibility' => 'search' ),
	array( 'id' => 22, 'name' => 'Searchable Catalog Only', 'status' => 'publish', 'categories' => array( 'serum' ), 'catalog_visibility' => 'catalog' ),
	array( 'id' => 23, 'name' => 'Searchable Hidden', 'status' => 'publish', 'categories' => array( 'serum' ), 'catalog_visibility' => 'hidden' ),
	array( 'id' => 24, 'name' => 'Searchable Draft', 'status' => 'draft', 'categories' => array( 'serum' ) ),
) );
$search = $adapter->search_products( 'Searchable', 6 );
$search_names = array_column( $search, 'title' );
ok( in_array( 'Searchable Visible', $search_names, true ), 'visible product appears in live search' );
ok( in_array( 'Searchable Search Only', $search_names, true ), 'search-only product remains searchable' );
ok( ! in_array( 'Searchable Catalog Only', $search_names, true ), 'catalog-only product excluded from live search' );
ok( ! in_array( 'Searchable Hidden', $search_names, true ), 'hidden product excluded from live search' );
ok( ! in_array( 'Searchable Draft', $search_names, true ), 'draft product excluded from live search' );
$search_terms = excluded_visibility_terms( $GLOBALS['gl_last_get_posts_args'] );
ok( in_array( 998, $search_terms, true ), 'live search uses Woo exclude-from-search visibility term' );
ok( ! in_array( 999, $search_terms, true ), 'live search must not reuse exclude-from-catalog' );

/* Exact Woo category projection. */
seed( array(
	array( 'id' => 10, 'name' => 'Serum A', 'status' => 'publish', 'categories' => array( 'serum' ) ),
	array( 'id' => 11, 'name' => 'Toner A', 'status' => 'publish', 'categories' => array( 'toner' ) ),
) );
$serum = $adapter->products_for_category( 'serum', 20 );
ok( 1 === count( $serum ) && 'Serum A' === $serum[0]['name'], 'serum projection filters exact category' );
ok( ! in_array( 'Toner A', array_column( $serum, 'name' ), true ), 'other category does not leak into serum' );

/* Woo pagination, not a fixed catalog ceiling. */
$many = array();
for ( $i = 1; $i <= 15; $i++ ) {
	$many[] = array( 'id' => 100 + $i, 'name' => 'Product ' . $i, 'status' => 'publish', 'categories' => array( 'serum' ) );
}
seed( $many );
$page1 = $adapter->products_paginated( 1, 12 );
ok( 12 === count( $page1['products'] ), 'page 1 respects 12-per-page limit' );
ok( 15 === $page1['total'] && 2 === $page1['max_pages'], 'pagination reflects full Woo result set' );
$page2 = $adapter->products_paginated( 2, 12 );
ok( 3 === count( $page2['products'] ), 'page 2 returns remaining products' );

echo "catalog discovery contract: OK\n";
