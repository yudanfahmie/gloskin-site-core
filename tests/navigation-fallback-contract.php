<?php
declare(strict_types=1);

/**
 * Latest client-approved primary navigation contract.
 *
 * NavigationService remains read-only at runtime, but its rendered projection
 * is bounded to exactly the four approved top-level destinations. The one-shot
 * lifecycle resolver persists the same label map; this fixture verifies the
 * deterministic runtime fallback plus active-route behavior.
 */

define( 'ABSPATH', __DIR__ . '/' );

function ok( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
$GLOBALS['gl_has_menu'] = false;
$GLOBALS['gl_menu_items'] = array();
$GLOBALS['gl_options'] = array();
function add_action() {}
function register_nav_menus() {}
function has_nav_menu( $location ) { return 'gloskin-primary' === $location && $GLOBALS['gl_has_menu']; }
function get_nav_menu_locations() { return array( 'gloskin-primary' => 1 ); }
function wp_get_nav_menu_items( $id ) { return $GLOBALS['gl_menu_items']; }
function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['gl_options'] ) ? $GLOBALS['gl_options'][ $name ] : $default; }
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
$expected_labels = array( 'Treatment', 'Promo', 'Skincare', 'Tentang Kami' );

$_SERVER['REQUEST_URI'] = '/';
$GLOBALS['gl_has_menu'] = false;
$nav = new Gloskin_Site_Core_Navigation_Service();
$tree = $nav->tree();
ok( $expected_labels === array_column( $tree, 'label' ), 'bootstrap fallback primary nav must use the approved Phase 1 labels' );

/* A completed persisted record is read by the same Navigation Service runtime
 * owner. Desktop and mobile both consume this one tree downstream. */
$GLOBALS['gl_options'][ Gloskin_Site_Core_Navigation_Service::APPROVED_LABELS_OPTION ] = array(
	'version' => Gloskin_Site_Core_Navigation_Service::APPROVED_LABELS_VERSION,
	'status'  => 'complete',
	'labels'  => Gloskin_Site_Core_Navigation_Service::approved_label_defaults(),
);

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
$nav = new Gloskin_Site_Core_Navigation_Service();
$real_tree = $nav->tree();
ok( 4 === count( $real_tree ), 'rendered primary must contain exactly four top-level destinations' );
ok( $expected_labels === array_column( $real_tree, 'label' ), 'native/editor menu projection must normalize to the persisted approved labels' );
ok( false === in_array( 'Custom Editor Item', array_column( $real_tree, 'label' ), true ), 'unknown editor item must not contaminate primary header' );
ok( true === $real_tree[1]['active'], 'native canonical Promo state should survive the read-only projection' );

/* wp_get_nav_menu_items() does not guarantee the contextual current-menu-*
 * classes that wp_nav_menu() normally resolves. Canonical request state must
 * therefore still mark a native editor item active on the server. */
$GLOBALS['gl_menu_items'][1]->classes = array();
$_SERVER['REQUEST_URI'] = '/about/';
$nav = new Gloskin_Site_Core_Navigation_Service();
$about_tree = $nav->tree();
ok( false === $about_tree[1]['active'], 'unrelated Promo item must not stay active without native state' );
ok( true === $about_tree[3]['active'], 'native About item must become active from the canonical request route' );
ok( 'Tentang Kami' === $about_tree[3]['label'], 'About route must render the approved Tentang Kami label' );

/* Detail routes inherit their canonical top-level section without loose prefix
 * matches that would incorrectly activate /about/ for /aboutness/. */
$_SERVER['REQUEST_URI'] = '/treatments/acne-detail/';
$nav = new Gloskin_Site_Core_Navigation_Service();
$treatment_tree = $nav->tree();
ok( true === $treatment_tree[0]['active'], 'Treatment detail route must keep Treatment active' );
ok( 'Treatment' === $treatment_tree[0]['label'], 'Treatment detail route must keep the approved Treatment label' );
$_SERVER['REQUEST_URI'] = '/skincare/routine/';
$nav = new Gloskin_Site_Core_Navigation_Service();
$skincare_tree = $nav->tree();
ok( true === $skincare_tree[2]['active'], 'Skincare detail route must keep the Skincare top level active' );
$_SERVER['REQUEST_URI'] = '/aboutness/';
$nav = new Gloskin_Site_Core_Navigation_Service();
$boundary_tree = $nav->tree();
ok( false === $boundary_tree[3]['active'], 'route matching must respect path boundaries' );

/* When the editor navigation explicitly models a child relationship, an active
 * child at a different URL still keeps its approved top-level parent active. */
$GLOBALS['gl_menu_items'] = array(
	(object) array( 'ID' => 5, 'menu_item_parent' => '0', 'title' => 'Perawatan Lama', 'url' => 'https://example.test/treatments/', 'classes' => array() ),
	(object) array( 'ID' => 6, 'menu_item_parent' => '5', 'title' => 'Detail Perawatan', 'url' => 'https://example.test/custom-treatment-detail/', 'classes' => array() ),
);
$_SERVER['REQUEST_URI'] = '/custom-treatment-detail/';
$nav = new Gloskin_Site_Core_Navigation_Service();
$relationship_tree = $nav->tree();
ok( true === $relationship_tree[0]['active'], 'active native child relationship must bubble state to Treatment' );

echo "navigation fallback contract: OK\n";
