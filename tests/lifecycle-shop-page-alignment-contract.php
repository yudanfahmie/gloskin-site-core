<?php
declare(strict_types=1);

/**
 * Lifecycle baseline + prototype IA handoff contract.
 *
 * Baseline provisioning remains responsible for safe native structure/Woo Shop
 * alignment. The 2026-08-18 IA revision (including /promo/) is deliberately
 * NOT auto-consumed by admin_init: its bounded migration/loader owns 0.3.0.
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
	public function __construct( array $data ) { foreach ( $data as $key => $value ) { $this->$key = $value; } }
}
class WooCommerce {}

$GLOBALS['gl_options']  = array();
$GLOBALS['gl_posts']    = array();
$GLOBALS['gl_next_id']  = 1;
$GLOBALS['gl_can_manage_options'] = true;

function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['gl_options'] ) ? $GLOBALS['gl_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['gl_options'][ $key ] = $value; return true; }
function get_post( $id ) { return $GLOBALS['gl_posts'][ $id ] ?? null; }
function get_page_uri( $id ) { return isset( $GLOBALS['gl_posts'][ $id ] ) ? $GLOBALS['gl_posts'][ $id ]->post_name : ''; }
function get_page_by_path( $path, $output = null, $post_type = 'page' ) {
	foreach ( $GLOBALS['gl_posts'] as $post ) {
		if ( $post->post_type === $post_type && $post->post_name === basename( (string) $path ) && 'trash' !== $post->post_status ) { return $post; }
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
function current_user_can( $capability ) { return 'manage_options' !== $capability || $GLOBALS['gl_can_manage_options']; }
function get_post_meta( $id, $key, $single = false ) { return ''; }
function update_post_meta( $id, $key, $value ) { return true; }

class Gloskin_Site_Core_Content_Service {
	const CLINIC_POST_TYPE = 'gloskin_clinic';
	public static function skincare_definitions() { return array(); }
	public static function clinic_definitions() { return array(); }
	public static function register_content_types() {}
	public static function register_taxonomies() {}
	public static function ensure_family_terms() {}
}

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-lifecycle-service.php';

function reset_world() {
	$GLOBALS['gl_options'] = array();
	$GLOBALS['gl_posts']   = array();
	$GLOBALS['gl_next_id'] = 1;
	$GLOBALS['gl_can_manage_options'] = true;
}

/* Activation provisions only the safe pre-revision baseline and aligns Woo Shop. */
reset_world();
( new Gloskin_Site_Core_Lifecycle_Service() )->activate();
$shop_page = get_page_by_path( 'shop', null, 'page' );
ok( $shop_page instanceof WP_Post, 'Gloskin /shop/ page is provisioned' );
ok( null === get_page_by_path( 'promo', null, 'page' ), 'Promo must remain owned by the one-shot IA migration, not baseline activation' );
ok( (int) get_option( 'woocommerce_shop_page_id', 0 ) === (int) $shop_page->ID, 'unconfigured Woo Shop is safely aligned' );
ok( Gloskin_Site_Core_Lifecycle_Service::BASE_SCHEMA_VERSION === (string) get_option( Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ), 'new activation records the baseline schema' );

/* Existing valid merchant Shop choice survives activation. */
reset_world();
$merchant_shop_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Merchant Shop', 'post_name' => 'merchant-shop' ) );
$GLOBALS['gl_options']['woocommerce_shop_page_id'] = $merchant_shop_id;
( new Gloskin_Site_Core_Lifecycle_Service() )->activate();
ok( (int) get_option( 'woocommerce_shop_page_id' ) === (int) $merchant_shop_id, 'valid merchant Shop setting is never overwritten' );

/* Invalid Shop pointer is repaired to the existing/provisioned Gloskin Shop. */
reset_world();
$GLOBALS['gl_options']['woocommerce_shop_page_id'] = 999;
( new Gloskin_Site_Core_Lifecycle_Service() )->activate();
$shop_page = get_page_by_path( 'shop', null, 'page' );
ok( (int) get_option( 'woocommerce_shop_page_id' ) === (int) $shop_page->ID, 'invalid Shop pointer is safely realigned' );

/* Baseline remains idempotent and does not provision Promo. */
reset_world();
( new Gloskin_Site_Core_Lifecycle_Service() )->activate();
( new Gloskin_Site_Core_Lifecycle_Service() )->activate();
$shop_pages = array_filter( $GLOBALS['gl_posts'], static function ( $post ) { return 'page' === $post->post_type && 'shop' === $post->post_name; } );
ok( 1 === count( $shop_pages ), 'baseline never duplicates /shop/' );
ok( null === get_page_by_path( 'promo', null, 'page' ), 'baseline does not take ownership of /promo/' );

/* Regression: consumed IA + schema 0.3.0 -> deactivate/reactivate -> still 0.3.0. */
reset_world();
$GLOBALS['gl_options'][ Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ] = Gloskin_Site_Core_Lifecycle_Service::SCHEMA_VERSION;
$GLOBALS['gl_options']['gloskin_site_core_prototype_ia_20260818_state'] = array( 'revision' => '2026-08-18', 'status' => 'consumed' );
$service = new Gloskin_Site_Core_Lifecycle_Service();
$service->deactivate();
$service->activate();
ok( Gloskin_Site_Core_Lifecycle_Service::SCHEMA_VERSION === (string) get_option( Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ), 'reactivation must never downgrade a completed 0.3.0 schema to baseline' );
ok( 'consumed' === $GLOBALS['gl_options']['gloskin_site_core_prototype_ia_20260818_state']['status'], 'reactivation must leave consumed IA state intact' );

/* A future/newer schema is also monotonic across activation. */
reset_world();
$GLOBALS['gl_options'][ Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ] = '0.4.0';
( new Gloskin_Site_Core_Lifecycle_Service() )->activate();
ok( '0.4.0' === (string) get_option( Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ), 'activation must never reduce a schema newer than current code' );

/* Old existing install gets only the safe baseline upgrade on admin_init. */
reset_world();
$existing_shop_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Belanja', 'post_name' => 'shop' ) );
$GLOBALS['gl_options'][ Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ] = '0.2.0';
( new Gloskin_Site_Core_Lifecycle_Service() )->maybe_upgrade();
ok( (int) get_option( 'woocommerce_shop_page_id', 0 ) === (int) $existing_shop_id, 'old install baseline upgrade aligns existing /shop/' );
ok( Gloskin_Site_Core_Lifecycle_Service::BASE_SCHEMA_VERSION === (string) get_option( Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ), 'old install stops at baseline schema until explicit IA migration completes' );

/* A current baseline install must not be continuously repaired/auto-consumed. */
reset_world();
$GLOBALS['gl_options'][ Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ] = Gloskin_Site_Core_Lifecycle_Service::BASE_SCHEMA_VERSION;
( new Gloskin_Site_Core_Lifecycle_Service() )->maybe_upgrade();
ok( Gloskin_Site_Core_Lifecycle_Service::BASE_SCHEMA_VERSION === (string) get_option( Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ), 'admin_init does not silently consume the 0.3.0 prototype IA migration' );

/* Completed state is inert on admin_init. */
reset_world();
$GLOBALS['gl_options'][ Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ] = Gloskin_Site_Core_Lifecycle_Service::SCHEMA_VERSION;
( new Gloskin_Site_Core_Lifecycle_Service() )->maybe_upgrade();
ok( array() === $GLOBALS['gl_posts'], 'completed schema performs no provisioning on subsequent admin_init' );

/* Non-admin request never provisions. */
reset_world();
$GLOBALS['gl_can_manage_options'] = false;
$GLOBALS['gl_options'][ Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ] = '0.2.0';
( new Gloskin_Site_Core_Lifecycle_Service() )->maybe_upgrade();
ok( '0.2.0' === (string) get_option( Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION ), 'maybe_upgrade requires manage_options' );

echo "lifecycle shop page alignment contract: OK\n";
