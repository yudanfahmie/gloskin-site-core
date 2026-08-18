<?php
declare(strict_types=1);

/**
 * Latest client-approved primary navigation contract.
 *
 * NavigationService itself remains read-only: it consumes one normalized native
 * WordPress menu for desktop/mobile and exposes the same four-item fallback when
 * no menu is assigned. The one-shot IA migration is the only owner allowed to
 * mutate an existing primary menu.
 */

define( 'ABSPATH', __DIR__ . '/' );

function ok( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$GLOBALS['gl_has_menu']   = false;
$GLOBALS['gl_menu_items'] = array();

function add_action() {}
function register_nav_menus() {}
function has_nav_menu( $location ) { return 'gloskin-primary' === $location && $GLOBALS['gl_has_menu']; }
function get_nav_menu_locations() { return array( 'gloskin-primary' => 1 ); }
function wp_get_nav_menu_items( $id ) { return $GLOBALS['gl_menu_items']; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function sanitize_title( $value ) { return strtolower( trim( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_url( $url, $component ) { return parse_url( (string) $url, $component ); }
function trailingslashit( $value ) { return rtrim( (string) $value, '/' ) . '/'; }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/' ); }
function wp_unslash( $value ) { return $value; }
function __( $text, $domain = 'default' ) { return $text; }

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-navigation-service.php';

$nav = new Gloskin_Site_Core_Navigation_Service();

/* No real menu assigned: fallback is exactly the client-approved primary IA. */
$GLOBALS['gl_has_menu'] = false;
$tree   = $nav->tree();
$labels = array_column( $tree, 'label' );

ok(
	array( 'Perawatan', 'Promo', 'Skincare', 'Tentang Gloskin' ) === $labels,
	'fallback primary nav must be Perawatan, Promo, Skincare, Tentang Gloskin'
);
foreach ( array( 'Belanja', 'Klinik', 'Dokter', 'Insight', 'Kontak', 'Beranda' ) as $support_label ) {
	ok( ! in_array( $support_label, $labels, true ), "support destination leaked into primary fallback: {$support_label}" );
}

/* NavigationService remains a read-only consumer of editor/native menu state. */
$GLOBALS['gl_has_menu']   = true;
$GLOBALS['gl_menu_items'] = array(
	(object) array( 'ID' => 1, 'menu_item_parent' => '0', 'title' => 'Custom Editor Item', 'url' => 'https://example.test/custom/', 'classes' => array() ),
	(object) array( 'ID' => 2, 'menu_item_parent' => '0', 'title' => 'Promo Lama', 'url' => 'https://example.test/promo/', 'classes' => array() ),
	(object) array( 'ID' => 3, 'menu_item_parent' => '0', 'title' => 'Partner Shop', 'url' => 'https://partner.example/shop/', 'classes' => array() ),
);
$real_tree   = $nav->tree();
$real_labels = array_column( $real_tree, 'label' );

ok( 3 === count( $real_tree ), 'NavigationService must not inject/delete native menu items at render time' );
ok( in_array( 'Custom Editor Item', $real_labels, true ), 'custom editor item must remain verbatim' );
ok( in_array( 'Promo', $real_labels, true ), 'known same-site Promo route label should normalize publicly without mutating storage' );
ok( in_array( 'Partner Shop', $real_labels, true ), 'external custom label must remain editor-owned even when its path resembles /shop/' );

echo "navigation fallback contract: OK\n";
