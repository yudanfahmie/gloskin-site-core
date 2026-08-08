<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['gl_admin_menus'] = array();
$GLOBALS['gl_admin_submenus'] = array();
$GLOBALS['gl_option_pages'] = array();
$GLOBALS['gl_post_types'] = array();

function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function absint( $value ) { return abs( (int) $value ); }
function current_user_can( $capability ) { return in_array( $capability, array( 'edit_posts', 'manage_options' ), true ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
function wp_count_posts( $post_type ) { return (object) array( 'publish' => 'gloskin_clinic' === $post_type ? 9 : 0 ); }
function register_post_type( $post_type, $args ) { $GLOBALS['gl_post_types'][ $post_type ] = $args; }
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
	$GLOBALS['gl_admin_menus'][] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url', 'position' );
	return 'toplevel_page_' . $menu_slug;
}
function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
	$GLOBALS['gl_admin_submenus'][] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'position' );
	return $parent_slug . '_page_' . $menu_slug;
}
function add_options_page( ...$args ) { $GLOBALS['gl_option_pages'][] = $args; }

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-content-service.php';
require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php';

Gloskin_Site_Core_Content_Service::register_content_types();
$content = new Gloskin_Site_Core_Content_Service();
$admin = new Gloskin_Site_Core_Admin_Service( $content );
$admin->register_admin_menu();

if ( count( $GLOBALS['gl_admin_menus'] ) !== 1 ) {
	fwrite( STDERR, "expected one Gloskin Content top-level menu\n" ); exit( 1 );
}
$parent = $GLOBALS['gl_admin_menus'][0];
if ( $parent['menu_slug'] !== 'gloskin-content' || $parent['menu_title'] !== 'Gloskin Content' || $parent['capability'] !== 'edit_posts' ) {
	fwrite( STDERR, "Gloskin Content parent menu mapping/capability failed\n" ); exit( 1 );
}

foreach ( array( 'gloskin_treatment', 'gloskin_clinic', 'gloskin_doctor' ) as $post_type ) {
	$args = $GLOBALS['gl_post_types'][ $post_type ] ?? array();
	if ( empty( $args['show_ui'] ) || ( $args['show_in_menu'] ?? '' ) !== 'gloskin-content' ) {
		fwrite( STDERR, "native CPT submenu ownership failed for {$post_type}\n" ); exit( 1 );
	}
}

$overview = null;
$settings = null;
foreach ( $GLOBALS['gl_admin_submenus'] as $submenu ) {
	if ( 'gloskin-content' === $submenu['menu_slug'] ) { $overview = $submenu; }
	if ( 'gloskin-site-core' === $submenu['menu_slug'] ) { $settings = $submenu; }
}
if ( ! $overview || $overview['parent_slug'] !== 'gloskin-content' || $overview['menu_title'] !== 'Overview' || $overview['capability'] !== 'edit_posts' ) {
	fwrite( STDERR, "Gloskin Content Overview submenu failed\n" ); exit( 1 );
}
if ( ! $settings || $settings['parent_slug'] !== 'gloskin-content' || $settings['menu_title'] !== 'Settings' || $settings['capability'] !== 'manage_options' ) {
	fwrite( STDERR, "Gloskin Settings submenu failed\n" ); exit( 1 );
}
if ( $GLOBALS['gl_option_pages'] ) {
	fwrite( STDERR, "legacy Settings > Gloskin Site Core registration remains\n" ); exit( 1 );
}

ob_start();
$admin->render_content_overview();
$overview_html = ob_get_clean();
foreach ( array(
	'edit.php?post_type=gloskin_treatment',
	'edit.php?post_type=gloskin_clinic',
	'edit.php?post_type=gloskin_doctor',
	'edit.php?post_type=page',
	'edit.php',
	'upload.php',
	'admin.php?page=gloskin-site-core',
) as $native_path ) {
	if ( false === strpos( $overview_html, $native_path ) ) {
		fwrite( STDERR, "overview missing native admin link: {$native_path}\n" ); exit( 1 );
	}
}

echo "admin navigation smoke passed\n";
