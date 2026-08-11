<?php
declare(strict_types=1);

/**
 * Gloskin Catalog Discovery v1 -- proves the fallback primary navigation
 * includes Belanja right after Skincare and no longer carries the
 * redundant Kontak entry, while an editor-owned real WordPress menu at
 * gloskin-primary is never mutated by either change.
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

class Gloskin_Site_Core_Content_Service {
	public static function skincare_definitions() {
		return array( 'serum' => 'Serum', 'toner' => 'Toner' );
	}
	public static function clinic_definitions() {
		return array( 'kebayoran-baru' => 'Kebayoran Baru' );
	}
}

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-navigation-service.php';

$nav = new Gloskin_Site_Core_Navigation_Service();

/* H + I: fallback tree (no real menu assigned to gloskin-primary). */
$GLOBALS['gl_has_menu'] = false;
$tree   = $nav->tree();
$labels = array_column( $tree, 'label' );

ok( in_array( 'Belanja', $labels, true ), 'H: fallback nav includes Belanja' );
$skincare_index = array_search( 'Skincare', $labels, true );
$belanja_index  = array_search( 'Belanja', $labels, true );
ok( false !== $skincare_index && $belanja_index === $skincare_index + 1, 'H: Belanja sits immediately after Skincare in the fallback nav' );
ok( ! in_array( 'Kontak', $labels, true ), 'I: the redundant fallback Kontak entry is removed' );
ok( array( 'Tentang Gloskin', 'Perawatan', 'Skincare', 'Belanja', 'Klinik', 'Dokter', 'Insight' ) === $labels, 'H+I: fallback nav matches the exact expected order' );

/* J: an editor-owned real WordPress menu is never mutated -- when
 * has_nav_menu() is true, tree() must return exactly the real menu items,
 * including a Kontak item the editor deliberately kept, with no Belanja
 * silently injected and no items removed. */
$GLOBALS['gl_has_menu']   = true;
$GLOBALS['gl_menu_items'] = array(
	(object) array( 'ID' => 1, 'menu_item_parent' => '0', 'title' => 'Kontak', 'url' => 'https://example.test/contact/', 'classes' => array() ),
	(object) array( 'ID' => 2, 'menu_item_parent' => '0', 'title' => 'Custom Editor Item', 'url' => 'https://example.test/custom/', 'classes' => array() ),
);
$real_tree   = $nav->tree();
$real_labels = array_column( $real_tree, 'label' );

ok( 2 === count( $real_tree ), 'J: real menu item count is not mutated' );
ok( in_array( 'Kontak', $real_labels, true ), 'J: an editor-owned real menu keeps its own Kontak item untouched' );
ok( in_array( 'Custom Editor Item', $real_labels, true ), 'J: editor-owned custom menu item label is preserved verbatim' );
ok( ! in_array( 'Belanja', $real_labels, true ), 'J: Gloskin never injects Belanja into a real menu that does not already have it' );

echo "navigation fallback contract: OK\n";
