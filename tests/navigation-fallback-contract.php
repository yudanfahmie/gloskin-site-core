<?php
declare(strict_types=1);

/**
 * Latest client-approved primary navigation contract.
 *
 * NavigationService remains read-only, but its rendered projection is bounded
 * to exactly the four approved top-level destinations. Editor-owned unknown
 * source-menu content is preserved by the one-shot migration backup and must
 * never leak into the public primary header while migration is pending/failed.
 */

define( 'ABSPATH', __DIR__ . '/' );

function ok( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
$GLOBALS['gl_has_menu'] = false;
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

$_SERVER['REQUEST_URI'] = '/';
$GLOBALS['gl_has_menu'] = false;
$tree = $nav->tree();
ok( array( 'Perawatan', 'Promo', 'Skincare', 'Tentang Gloskin' ) === array_column( $tree, 'label' ), 'fallback primary nav must be the exact approved four' );

/* Mixed/legacy editor menu source: public projection still renders exact four.
 * Existing canonical entries may supply native state/children; unknown items
 * are not deleted or promoted by this read-only service. */
$GLOBALS['gl_has_menu'] = true;
$GLOBALS['gl_menu_items'] = array(
	(object) array( 'ID' => 1, 'menu_item_parent' => '0', 'title' => 'Custom Editor Item', 'url' => 'https://example.test/custom/', 'classes' => array() ),
	(object) array( 'ID' => 2, 'menu_item_parent' => '0', 'title' => 'Promo Lama', 'url' => 'https://example.test/promo/', 'classes' => array( 'current-menu-item' ) ),
	(object) array( 'ID' => 3, 'menu_item_parent' => '0', 'title' => 'Partner Shop', 'url' => 'https://partner.example/shop/', 'classes' => array() ),
	(object) array( 'ID' => 4, 'menu_item_parent' => '0', 'title' => 'Tentang Lama', 'url' => 'https://example.test/about/', 'classes' => array() ),
);
$_SERVER['REQUEST_URI'] = '/unrelated/';
$real_tree = $nav->tree();
ok( 4 === count( $real_tree ), 'rendered primary must contain exactly four top-level destinations' );
ok( array( 'Perawatan', 'Promo', 'Skincare', 'Tentang Gloskin' ) === array_column( $real_tree, 'label' ), 'legacy/unknown source menu must project to exact approved primary order' );
ok( false === in_array( 'Custom Editor Item', array_column( $real_tree, 'label' ), true ), 'unknown editor item must not contaminate primary header' );
ok( true === $real_tree[1]['active'], 'native canonical Promo state should survive the read-only projection' );

/* wp_get_nav_menu_items() does not guarantee the contextual current-menu-*
 * classes that wp_nav_menu() normally resolves. Canonical request state must
 * therefore still mark a native editor item active on the server. */
$GLOBALS['gl_menu_items'][1]->classes = array();
$_SERVER['REQUEST_URI'] = '/about/';
$about_tree = $nav->tree();
ok( false === $about_tree[1]['active'], 'unrelated Promo item must not stay active without native state' );
ok( true === $about_tree[3]['active'], 'native About item must become active from the canonical request route' );

/* Detail routes inherit their canonical top-level section without loose prefix
 * matches that would incorrectly activate /about/ for /aboutness/. */
$_SERVER['REQUEST_URI'] = '/skincare/routine/';
$skincare_tree = $nav->tree();
ok( true === $skincare_tree[2]['active'], 'Skincare detail route must keep the Skincare top level active' );
$_SERVER['REQUEST_URI'] = '/aboutness/';
$boundary_tree = $nav->tree();
ok( false === $boundary_tree[3]['active'], 'route matching must respect path boundaries' );

/* When the editor navigation explicitly models a child relationship, an active
 * child at a different URL still keeps its approved top-level parent active. */
$GLOBALS['gl_menu_items'] = array(
	(object) array( 'ID' => 5, 'menu_item_parent' => '0', 'title' => 'Perawatan Lama', 'url' => 'https://example.test/treatments/', 'classes' => array() ),
	(object) array( 'ID' => 6, 'menu_item_parent' => '5', 'title' => 'Detail Perawatan', 'url' => 'https://example.test/custom-treatment-detail/', 'classes' => array() ),
);
$_SERVER['REQUEST_URI'] = '/custom-treatment-detail/';
$relationship_tree = $nav->tree();
ok( true === $relationship_tree[0]['active'], 'active native child relationship must bubble state to Perawatan' );

echo "navigation fallback contract: OK\n";
