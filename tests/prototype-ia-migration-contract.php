<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }

function ok( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class WP_Post {
	public $ID = 0;
	public $post_type = '';
	public $post_status = 'publish';
	public $post_name = '';
	public $post_title = '';
	public $post_parent = 0;
	public $menu_item_parent = '0';
	public $title = '';
	public $url = '';
	public $type = 'custom';
	public $object = 'custom';
	public $object_id = 0;
	public $classes = array();
	public $menu_order = 0;
	public $description = '';
	public $attr_title = '';
	public $target = '';
	public $xfn = '';
	public function __construct( array $data ) { foreach ( $data as $key => $value ) { $this->{$key} = $value; } }
}
class WP_Error {
	private $message;
	public function __construct( $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }

$GLOBALS['opt'] = array();
$GLOBALS['posts'] = array();
$GLOBALS['post_meta'] = array();
$GLOBALS['next_post_id'] = 1000;
$GLOBALS['menus'] = array();
$GLOBALS['menu_names'] = array();
$GLOBALS['next_menu_id'] = 20;
$GLOBALS['next_menu_item_id'] = 200;
$GLOBALS['theme_mods'] = array();
$GLOBALS['deleted'] = array();
$GLOBALS['uuid'] = 0;

function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['opt'] ) ? $GLOBALS['opt'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['opt'][ $key ] = $value; return true; }
function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) { if ( array_key_exists( $key, $GLOBALS['opt'] ) ) { return false; } $GLOBALS['opt'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['opt'][ $key ] ); return true; }
function wp_generate_uuid4() { $GLOBALS['uuid']++; return 'token-' . $GLOBALS['uuid']; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function trailingslashit( $value ) { return rtrim( (string) $value, '/' ) . '/'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['post_meta'][ (int) $id ][ $key ] ?? ( $single ? '' : array() ); }
function update_post_meta( $id, $key, $value ) { $GLOBALS['post_meta'][ (int) $id ][ $key ] = $value; return true; }

function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) {
	$path = trim( (string) $path, '/' );
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( $post->post_type !== $post_type ) { continue; }
		$candidate = $post->post_name;
		if ( $post->post_parent ) {
			$parent = get_post( $post->post_parent );
			$candidate = $parent ? $parent->post_name . '/' . $candidate : $candidate;
		}
		if ( $candidate === $path ) { return $post; }
	}
	return null;
}
function wp_insert_post( $data, $wp_error = false ) {
	$id = $GLOBALS['next_post_id']++;
	$GLOBALS['posts'][ $id ] = new WP_Post( array(
		'ID' => $id,
		'post_type' => $data['post_type'] ?? 'post',
		'post_status' => $data['post_status'] ?? 'draft',
		'post_name' => $data['post_name'] ?? '',
		'post_title' => $data['post_title'] ?? '',
		'post_parent' => (int) ( $data['post_parent'] ?? 0 ),
	) );
	return $id;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_type( $id ) {
	if ( isset( $GLOBALS['posts'][ (int) $id ] ) ) { return $GLOBALS['posts'][ (int) $id ]->post_type; }
	foreach ( $GLOBALS['menus'] as $items ) { if ( isset( $items[ (int) $id ] ) ) { return 'nav_menu_item'; } }
	return '';
}

function get_theme_mod( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['theme_mods'] ) ? $GLOBALS['theme_mods'][ $key ] : $default; }
function set_theme_mod( $key, $value ) { $GLOBALS['theme_mods'][ $key ] = $value; }
function wp_get_nav_menu_object( $id_or_name ) {
	if ( is_string( $id_or_name ) && ! ctype_digit( $id_or_name ) ) {
		foreach ( $GLOBALS['menu_names'] as $id => $name ) { if ( $name === $id_or_name ) { return (object) array( 'term_id' => (int) $id, 'name' => $name ); } }
		return false;
	}
	$id = (int) $id_or_name;
	return isset( $GLOBALS['menus'][ $id ] ) ? (object) array( 'term_id' => $id, 'name' => $GLOBALS['menu_names'][ $id ] ?? '' ) : false;
}
function wp_create_nav_menu( $name ) {
	$id = $GLOBALS['next_menu_id']++;
	$GLOBALS['menus'][ $id ] = array();
	$GLOBALS['menu_names'][ $id ] = (string) $name;
	return $id;
}
function wp_get_nav_menu_items( $menu_id ) {
	$items = array_values( $GLOBALS['menus'][ (int) $menu_id ] ?? array() );
	usort( $items, static function ( $a, $b ) { return (int) $a->menu_order <=> (int) $b->menu_order; } );
	return $items;
}
function wp_update_nav_menu_item( $menu_id, $item_id, $args ) {
	$menu_id = (int) $menu_id;
	if ( ! isset( $GLOBALS['menus'][ $menu_id ] ) ) { return new WP_Error( 'missing menu' ); }
	$item_id = (int) $item_id;
	if ( ! $item_id ) { $item_id = $GLOBALS['next_menu_item_id']++; }
	$existing = $GLOBALS['menus'][ $menu_id ][ $item_id ] ?? new WP_Post( array( 'ID' => $item_id ) );
	$type = (string) ( $args['menu-item-type'] ?? $existing->type );
	$object = (string) ( $args['menu-item-object'] ?? $existing->object );
	$object_id = (int) ( $args['menu-item-object-id'] ?? $existing->object_id );
	$url = (string) ( $args['menu-item-url'] ?? $existing->url );
	if ( 'post_type' === $type && 'page' === $object && $object_id ) {
		$page = get_post( $object_id );
		$url = $page ? home_url( '/' . $page->post_name . '/' ) : $url;
	}
	$existing->ID = $item_id;
	$existing->title = (string) ( $args['menu-item-title'] ?? $existing->title );
	$existing->url = $url;
	$existing->type = $type;
	$existing->object = $object;
	$existing->object_id = $object_id;
	$existing->menu_item_parent = (string) ( $args['menu-item-parent-id'] ?? $existing->menu_item_parent );
	$existing->menu_order = (int) ( $args['menu-item-position'] ?? $existing->menu_order );
	$existing->description = (string) ( $args['menu-item-description'] ?? $existing->description );
	$existing->attr_title = (string) ( $args['menu-item-attr-title'] ?? $existing->attr_title );
	$existing->target = (string) ( $args['menu-item-target'] ?? $existing->target );
	$existing->classes = (array) ( $args['menu-item-classes'] ?? $existing->classes );
	$existing->xfn = (string) ( $args['menu-item-xfn'] ?? $existing->xfn );
	$GLOBALS['menus'][ $menu_id ][ $item_id ] = $existing;
	return $item_id;
}
function wp_delete_post( $id, $force = false ) {
	$id = (int) $id;
	foreach ( $GLOBALS['menus'] as $menu_id => $items ) {
		if ( isset( $GLOBALS['menus'][ $menu_id ][ $id ] ) ) {
			$GLOBALS['deleted'][] = array( 'id' => $id, 'type' => 'nav_menu_item' );
			unset( $GLOBALS['menus'][ $menu_id ][ $id ] );
			return true;
		}
	}
	if ( isset( $GLOBALS['posts'][ $id ] ) ) {
		$GLOBALS['deleted'][] = array( 'id' => $id, 'type' => $GLOBALS['posts'][ $id ]->post_type );
		unset( $GLOBALS['posts'][ $id ] );
		return true;
	}
	return false;
}

function seed_world( $ambiguous_front = false ) {
	$GLOBALS['opt'] = array(
		'gloskin_site_core_schema_version' => '0.2.2',
		'woocommerce_shop_page_id' => 501,
		'woocommerce_cart_page_id' => 502,
		'woocommerce_checkout_page_id' => 503,
		'woocommerce_myaccount_page_id' => 504,
		'page_on_front' => 0,
		'show_on_front' => 'posts',
	);
	$GLOBALS['posts'] = array();
	$GLOBALS['post_meta'] = array();
	$GLOBALS['next_post_id'] = 1000;
	$GLOBALS['menus'] = array( 10 => array() );
	$GLOBALS['menu_names'] = array( 10 => 'Legacy Primary' );
	$GLOBALS['next_menu_id'] = 20;
	$GLOBALS['next_menu_item_id'] = 200;
	$GLOBALS['theme_mods'] = array( 'nav_menu_locations' => array( 'gloskin-primary' => 10 ) );
	$GLOBALS['deleted'] = array();
	$GLOBALS['uuid'] = 0;

	foreach (
		array(
			301 => array( 'clinics', 'Klinik' ), 302 => array( 'doctors', 'Dokter' ),
			303 => array( 'contact', 'Kontak' ), 304 => array( 'insights', 'Insight' ),
			305 => array( 'shop', 'Belanja' ), 306 => array( 'about', 'Tentang Gloskin' ),
			307 => array( 'treatments', 'Perawatan' ), 308 => array( 'skincare', 'Skincare' ),
			501 => array( 'woo-shop', 'Woo Shop' ), 502 => array( 'cart', 'Cart' ),
			503 => array( 'checkout', 'Checkout' ), 504 => array( 'my-account', 'My Account' ),
		) as $id => $pair
	) {
		$GLOBALS['posts'][ $id ] = new WP_Post( array( 'ID' => $id, 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $pair[0], 'post_title' => $pair[1], 'post_parent' => 0 ) );
	}
	if ( $ambiguous_front ) {
		$GLOBALS['posts'][600] = new WP_Post( array( 'ID' => 600, 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'editor-home', 'post_title' => 'Editor Landing', 'post_parent' => 0 ) );
		$GLOBALS['opt']['page_on_front'] = 600;
		$GLOBALS['opt']['show_on_front'] = 'page';
	}

	$seed = static function ( $id, $title, $path, $position, $parent = 0 ) {
		$GLOBALS['menus'][10][ $id ] = new WP_Post( array(
			'ID' => $id, 'title' => $title, 'url' => home_url( $path ), 'type' => 'custom',
			'object' => 'custom', 'object_id' => 0, 'menu_item_parent' => (string) $parent,
			'menu_order' => $position, 'classes' => array(),
		) );
	};
	$seed( 100, 'Tentang', '/about/', 1 );
	$seed( 101, 'Klinik', '/clinics/', 2 );
	$seed( 102, 'Membership', '/membership/', 3 );
	$seed( 103, 'Skincare', '/skincare/', 4 );
	$seed( 104, 'Belanja', '/shop/', 5 );
	$seed( 105, 'Treatment', '/treatments/', 6 );
	$seed( 106, 'Custom child', '/custom-child/', 7, 101 );
	$seed( 107, 'Insight', '/insights/', 8 );
	$seed( 108, 'Kontak', '/contact/', 9 );
}

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-lifecycle-service.php';
require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-prototype-ia-migration.php';

seed_world();
$migration = new Gloskin_Site_Core_Prototype_IA_Migration();
$before_woo = array( $GLOBALS['opt']['woocommerce_shop_page_id'], $GLOBALS['opt']['woocommerce_cart_page_id'], $GLOBALS['opt']['woocommerce_checkout_page_id'], $GLOBALS['opt']['woocommerce_myaccount_page_id'] );
$before_support = array( 301, 302, 303, 304, 305 );
$before_page_ids = array_keys( $GLOBALS['posts'] );
$result = $migration->run_to_completion();
ok( 'consumed' === $result['status'], 'migration must consume automatically' );
ok( 4 === $result['processed_products'] && 4 === $result['expected_products'], 'all four deterministic checkpoints must complete' );
ok( '0.3.0' === $GLOBALS['opt']['gloskin_site_core_schema_version'], 'schema version must advance only at final checkpoint' );
ok( ! isset( $GLOBALS['opt'][ Gloskin_Site_Core_Prototype_IA_Migration::LOCK_OPTION ] ), 'lock must release after completion' );

foreach ( array( 'home', 'treatments', 'promo', 'skincare', 'about' ) as $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	ok( $page instanceof WP_Post && 'trash' !== $page->post_status, "approved page missing: {$slug}" );
}
$home = get_page_by_path( 'home', OBJECT, 'page' );
ok( 'page' === $GLOBALS['opt']['show_on_front'] && (int) $GLOBALS['opt']['page_on_front'] === (int) $home->ID, 'unset/invalid front-page ownership must normalize to canonical Home' );
foreach ( $before_support as $id ) { ok( isset( $GLOBALS['posts'][ $id ] ), "support page deleted: {$id}" ); }
foreach ( $before_page_ids as $id ) { ok( isset( $GLOBALS['posts'][ $id ] ), "pre-existing Page/data record deleted: {$id}" ); }
foreach ( $GLOBALS['deleted'] as $deleted ) { ok( 'nav_menu_item' === $deleted['type'], 'migration must only delete nav menu item records' ); }

$primary = wp_get_nav_menu_items( 10 );
$top_paths = array();
foreach ( $primary as $item ) {
	ok( 0 === (int) $item->menu_item_parent, 'approved primary must not retain unexpected legacy submenu hierarchy' );
	$top_paths[] = parse_url( $item->url, PHP_URL_PATH );
}
ok( array( '/treatments/', '/promo/', '/skincare/', '/about/' ) === $top_paths, 'rendered/assigned primary must be exactly Perawatan, Promo, Skincare, Tentang Gloskin' );

$preserved = wp_get_nav_menu_object( Gloskin_Site_Core_Prototype_IA_Migration::PRESERVED_MENU_NAME );
ok( $preserved && 10 !== (int) $preserved->term_id, 'old editor menu snapshot must live in a deterministic unassigned backup menu' );
$preserved_locations = $GLOBALS['theme_mods']['nav_menu_locations'];
ok( ! in_array( (int) $preserved->term_id, array_map( 'intval', array_values( $preserved_locations ) ), true ), 'preserved backup menu must remain unassigned' );
$backup = wp_get_nav_menu_items( (int) $preserved->term_id );
ok( 9 === count( $backup ), 'complete original primary menu snapshot must be preserved' );
$membership = null; $custom_child = null; $clinic_copy = null;
foreach ( $backup as $item ) {
	$path = parse_url( $item->url, PHP_URL_PATH );
	if ( '/membership/' === $path ) { $membership = $item; }
	if ( '/custom-child/' === $path ) { $custom_child = $item; }
	if ( '/clinics/' === $path ) { $clinic_copy = $item; }
}
ok( $membership instanceof WP_Post, 'unknown editor top-level content must be preserved in backup' );
ok( $custom_child instanceof WP_Post && $clinic_copy instanceof WP_Post, 'unknown editor child and its legacy parent must be preserved' );
ok( (int) $custom_child->menu_item_parent === (int) $clinic_copy->ID, 'unknown child hierarchy must be preserved instead of promoted into primary' );

$after_woo = array( $GLOBALS['opt']['woocommerce_shop_page_id'], $GLOBALS['opt']['woocommerce_cart_page_id'], $GLOBALS['opt']['woocommerce_checkout_page_id'], $GLOBALS['opt']['woocommerce_myaccount_page_id'] );
ok( $before_woo === $after_woo, 'Woo Shop/Cart/Checkout/My Account page IDs must remain unchanged' );

$page_count = count( $GLOBALS['posts'] );
$primary_count = count( $GLOBALS['menus'][10] );
$backup_count = count( $GLOBALS['menus'][ (int) $preserved->term_id ] );
$delete_count = count( $GLOBALS['deleted'] );
$second = $migration->run_to_completion();
ok( 'consumed' === $second['status'], 'second run must return consumed idempotently' );
ok( $page_count === count( $GLOBALS['posts'] ), 'second run must not create duplicate pages' );
ok( $primary_count === count( $GLOBALS['menus'][10] ), 'second run must not create duplicate primary items' );
ok( $backup_count === count( $GLOBALS['menus'][ (int) $preserved->term_id ] ), 'second run must not duplicate preserved editor items' );
ok( $delete_count === count( $GLOBALS['deleted'] ), 'second run must not re-delete menu items' );

/* Ambiguous editor-owned front page is preserved and reported rather than guessed. */
seed_world( true );
$migration = new Gloskin_Site_Core_Prototype_IA_Migration();
$thrown = false;
try { $migration->run_to_completion(); } catch ( RuntimeException $error ) {
	$thrown = true;
	ok( false !== strpos( $error->getMessage(), 'Kepemilikannya tidak dapat dibuktikan' ), 'ambiguous front page must fail with actionable ownership warning' );
}
ok( $thrown, 'ambiguous editor front page must stop the migration before menu mutation/finalize' );
ok( 600 === (int) $GLOBALS['opt']['page_on_front'] && isset( $GLOBALS['posts'][600] ), 'ambiguous editor front page must remain untouched' );
ok( '0.2.2' === $GLOBALS['opt']['gloskin_site_core_schema_version'], 'failed ambiguous ownership check must not advance schema' );
ok( 9 === count( $GLOBALS['menus'][10] ), 'front-page ownership failure must occur before primary menu mutation' );

echo "prototype-ia-migration-contract: OK\n";
