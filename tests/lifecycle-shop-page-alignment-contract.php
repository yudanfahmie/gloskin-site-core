<?php
declare(strict_types=1);

/**
 * Gloskin Catalog Discovery v1 -- proves LifecycleService safely aligns
 * WooCommerce's woocommerce_shop_page_id to Gloskin's own provisioned
 * /shop/ page only when genuinely unconfigured (empty or pointing at a
 * page that no longer exists), and never overwrites an existing valid
 * merchant-configured Shop page.
 */

define( 'ABSPATH', __DIR__ . '/' );
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }

function ok( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_title;
	public $post_name;
	public $post_parent;
	public function __construct( array $data ) {
		foreach ( $data as $key => $value ) { $this->$key = $value; }
	}
}
class WooCommerce {}

$GLOBALS['gl_options']  = array();
$GLOBALS['gl_posts']    = array();
$GLOBALS['gl_next_id']  = 1;

function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['gl_options'] ) ? $GLOBALS['gl_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['gl_options'][ $key ] = $value; return true; }
function get_post( $id ) { return $GLOBALS['gl_posts'][ $id ] ?? null; }
function get_page_uri( $id ) { return isset( $GLOBALS['gl_posts'][ $id ] ) ? $GLOBALS['gl_posts'][ $id ]->post_name : ''; }
function get_page_by_path( $path, $output = null, $post_type = 'page' ) {
	foreach ( $GLOBALS['gl_posts'] as $post ) {
		if ( $post->post_type === $post_type && $post->post_name === basename( (string) $path ) ) { return $post; }
	}
	return null;
}
function wp_insert_post( $data, $wp_error = false ) {
	$id = $GLOBALS['gl_next_id']++;
	$GLOBALS['gl_posts'][ $id ] = new WP_Post( array_merge( array( 'post_status' => 'publish' ), $data, array( 'ID' => $id ) ) );
	return $id;
}
function is_wp_error( $value ) { return false; }
function absint( $value ) { return abs( (int) $value ); }
function flush_rewrite_rules( $hard = true ) {}
function add_action() {}

class Gloskin_Site_Core_Content_Service {
	const CLINIC_POST_TYPE = 'gloskin_clinic';
	public static function skincare_definitions() { return array(); }
	public static function clinic_definitions() { return array(); }
	public static function register_content_types() {}
}

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-lifecycle-service.php';

function reset_world() {
	$GLOBALS['gl_options'] = array();
	$GLOBALS['gl_posts']   = array();
	$GLOBALS['gl_next_id'] = 1;
}

/* Genuinely unconfigured: safely associate with Gloskin's own /shop/. */
reset_world();
( new Gloskin_Site_Core_Lifecycle_Service() )->activate();
$shop_page = get_page_by_path( 'shop', null, 'page' );
ok( $shop_page instanceof WP_Post, 'Gloskin /shop/ page is provisioned' );
ok( (int) get_option( 'woocommerce_shop_page_id', 0 ) === (int) $shop_page->ID, 'unconfigured Woo shop page setting is safely aligned to /shop/' );

/* Already configured to a different, still-valid page: preserved untouched. */
reset_world();
$other_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Merchant Shop', 'post_name' => 'merchant-shop' ) );
$GLOBALS['gl_options']['woocommerce_shop_page_id'] = $other_id;
( new Gloskin_Site_Core_Lifecycle_Service() )->activate();
ok( (int) get_option( 'woocommerce_shop_page_id' ) === (int) $other_id, 'an existing valid merchant Shop page configuration is never overwritten' );

/* Configured to a page that no longer exists: treated as unconfigured. */
reset_world();
$GLOBALS['gl_options']['woocommerce_shop_page_id'] = 999;
( new Gloskin_Site_Core_Lifecycle_Service() )->activate();
$shop_page3 = get_page_by_path( 'shop', null, 'page' );
ok( (int) get_option( 'woocommerce_shop_page_id' ) === (int) $shop_page3->ID, 'a shop_page_id pointing at a nonexistent page is safely realigned' );

/* No duplicate /shop/ page is ever created. */
reset_world();
( new Gloskin_Site_Core_Lifecycle_Service() )->activate();
( new Gloskin_Site_Core_Lifecycle_Service() )->activate();
$shop_pages = array_filter( $GLOBALS['gl_posts'], static function ( $post ) { return 'page' === $post->post_type && 'shop' === $post->post_name; } );
ok( 1 === count( $shop_pages ), 'no duplicate /shop/ page is created on repeated activation' );

echo "lifecycle shop page alignment contract: OK\n";
