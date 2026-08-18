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
$real_tree = $nav->tree();
ok( 4 === count( $real_tree ), 'rendered primary must contain exactly four top-level destinations' );
ok( array( 'Perawatan', 'Promo', 'Skincare', 'Tentang Gloskin' ) === array_column( $real_tree, 'label' ), 'legacy/unknown source menu must project to exact approved primary order' );
ok( false === in_array( 'Custom Editor Item', array_column( $real_tree, 'label' ), true ), 'unknown editor item must not contaminate primary header' );
ok( true === $real_tree[1]['active'], 'native canonical Promo state should survive the read-only projection' );

echo "navigation fallback contract: OK\n";
