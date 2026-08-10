<?php
declare(strict_types=1);

/**
 * Gloskin Catalog Discovery v1 -- proves the WooCommerce Adapter's public
 * catalog projections (products_for_category(), products_paginated()) stay
 * strictly Woo-native: status=publish only, catalog-visibility respected,
 * exact category filtering, and Woo's own limit/page/paginate=true
 * pagination contract instead of a fixed record ceiling.
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

/**
 * Woo's own documented visibility-term resolver -- the adapter must use
 * this (or return no filter) rather than hand-rolling taxonomy SQL.
 */
function wc_get_product_visibility_term_ids() {
	return array( 'exclude-from-catalog' => 999, 'exclude-from-search' => 998 );
}

/**
 * Minimal WC_Product_Query simulation: status filter, exact category
 * filter, the catalog-visibility tax_query, and -- critically -- Woo's own
 * limit/page/paginate=true contract (not a hand-rolled fixed ceiling).
 */
function wc_get_products( $args ) {
	$results = array();
	foreach ( $GLOBALS['gl_catalog'] as $product ) {
		if ( isset( $args['status'] ) && $product->status !== $args['status'] ) { continue; }
		if ( ! empty( $args['category'] ) && ! array_intersect( (array) $args['category'], $product->categories ) ) { continue; }
		if ( ! empty( $args['tax_query'] ) && in_array( $product->catalog_visibility, array( 'hidden', 'search' ), true ) ) { continue; }
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

function get_term_by( $field, $value, $taxonomy ) {
	if ( 'product_cat' !== $taxonomy ) { return false; }
	foreach ( $GLOBALS['gl_catalog'] as $product ) {
		if ( in_array( $value, $product->categories, true ) ) { return new WP_Term( $value ); }
	}
	return false;
}
function get_term_link( $term ) { return 'https://example.test/product-category/' . $term->slug . '/'; }
function get_permalink( $id ) { return 'https://example.test/product/' . $id . '/'; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_title( $value ) { $value = strtolower( trim( (string) $value ) ); return preg_replace( '/[^a-z0-9-]+/', '-', $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_strip_all_tags( $value ) { return trim( strip_tags( (string) $value ) ); }
function __( $text, $domain = 'default' ) { return $text; }

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php';

$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();

/**
 * @param array<int,array<string,mixed>> $products
 */
function seed( array $products ) {
	$GLOBALS['gl_catalog'] = array();
	foreach ( $products as $p ) {
		$fixture = new Fixture_Product( array_merge( array( 'sku' => 'GLS-' . $p['id'] ), $p ) );
		$GLOBALS['gl_catalog'][ $fixture->id ] = $fixture;
	}
}

/* A + B: the shop-style paginated projection returns only published
 * products; the draft parent products the sample importer intentionally
 * creates never leak into it. */
seed( array(
	array( 'id' => 1, 'name' => 'Published Simple', 'status' => 'publish', 'categories' => array( 'serum' ) ),
	array( 'id' => 2, 'name' => 'Draft Parent', 'status' => 'draft', 'categories' => array( 'serum' ) ),
) );
$catalog = $adapter->products_paginated( 1, 12 );
ok( 1 === count( $catalog['products'] ), 'A: shop catalog projection returns only published catalog-visible products' );
ok( 'Published Simple' === $catalog['products'][0]['name'], 'A: the correct published product is surfaced' );
ok( ! in_array( 'Draft Parent', array_column( $catalog['products'], 'name' ), true ), 'B: a draft parent product never appears publicly' );

/* Catalog-visibility: hidden/search-only products never leak either. */
seed( array(
	array( 'id' => 3, 'name' => 'Visible', 'status' => 'publish', 'categories' => array( 'serum' ) ),
	array( 'id' => 4, 'name' => 'Hidden', 'status' => 'publish', 'categories' => array( 'serum' ), 'catalog_visibility' => 'hidden' ),
	array( 'id' => 5, 'name' => 'Search Only', 'status' => 'publish', 'categories' => array( 'serum' ), 'catalog_visibility' => 'search' ),
) );
$visibility_catalog = $adapter->products_paginated( 1, 12 );
$visible_names = array_column( $visibility_catalog['products'], 'name' );
ok( in_array( 'Visible', $visible_names, true ), 'catalog visibility: a normally-visible product still appears' );
ok( ! in_array( 'Hidden', $visible_names, true ), 'catalog visibility: a hidden product does not leak into the catalog' );
ok( ! in_array( 'Search Only', $visible_names, true ), 'catalog visibility: a search-only product does not leak into the catalog' );

/* C + D + E: category context filters by exact Woo category slug -- a
 * product assigned to serum appears there and nowhere else. */
seed( array(
	array( 'id' => 10, 'name' => 'Serum A', 'status' => 'publish', 'categories' => array( 'serum' ) ),
	array( 'id' => 11, 'name' => 'Toner A', 'status' => 'publish', 'categories' => array( 'toner' ) ),
) );
$serum = $adapter->products_for_category( 'serum', 20 );
ok( 1 === count( $serum ) && 'Serum A' === $serum[0]['name'], 'D: a published product assigned to serum appears in the Serum catalog projection' );
ok( ! in_array( 'Toner A', array_column( $serum, 'name' ), true ), 'E: a product assigned to another category cannot appear in Serum' );
$toner = $adapter->products_for_category( 'toner', 20 );
ok( 1 === count( $toner ) && 'Toner A' === $toner[0]['name'], 'C: category context filters by exact Woo category slug' );

/* F: pagination uses Woo's own limit/page/paginate contract, not a fixed
 * 20-record ceiling -- prove with 15 published products across two pages
 * of 12. */
$many = array();
for ( $i = 1; $i <= 15; $i++ ) {
	$many[] = array( 'id' => 100 + $i, 'name' => 'Product ' . $i, 'status' => 'publish', 'categories' => array( 'serum' ) );
}
seed( $many );
$page1 = $adapter->products_paginated( 1, 12 );
ok( 12 === count( $page1['products'] ), 'F: page 1 respects the 12-per-page limit' );
ok( 15 === $page1['total'], 'F: total reflects the full published catalog, not a 20-record assumption' );
ok( 2 === $page1['max_pages'], 'F: max_pages is computed from Woo pagination, not a fixed page count' );
$page2 = $adapter->products_paginated( 2, 12 );
ok( 3 === count( $page2['products'] ), 'F: page 2 returns the remaining products via Woo pagination, not a hard ceiling' );

echo "catalog discovery contract: OK\n";
