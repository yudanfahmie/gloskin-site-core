<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['gl_asset_context'] = array();
$GLOBALS['gl_styles'] = array();
$GLOBALS['gl_scripts'] = array();

function add_action() {}
function get_query_var( $key, $default = '' ) { return 'gloskin_context' === $key ? $GLOBALS['gl_asset_context'] : $default; }
function plugins_url( $src, $file ) { return '/plugins/gloskin/' . ltrim( (string) $src, '/' ); }
function wp_register_style() {}
function wp_register_script() {}
function wp_enqueue_style( $handle ) { $GLOBALS['gl_styles'][] = (string) $handle; }
function wp_enqueue_script( $handle ) { $GLOBALS['gl_scripts'][] = (string) $handle; }

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php';

$commerce = false;
$service = new Gloskin_Site_Core_Asset_Service(
	dirname( __DIR__ ) . '/plugin/gloskin-site-core/gloskin-site-core.php',
	'0.3.0',
	static function () use ( &$commerce ) { return $commerce; }
);

$service->enqueue_frontend();
if ( $GLOBALS['gl_styles'] || $GLOBALS['gl_scripts'] ) {
	fwrite( STDERR, "assets loaded on unrelated request\n" ); exit( 1 );
}

$GLOBALS['gl_asset_context'] = array( 'view' => 'home' );
$service->enqueue_frontend();
if ( ! in_array( 'gloskin-ui1-core', $GLOBALS['gl_styles'], true ) || ! in_array( 'gloskin-ui1-core', $GLOBALS['gl_scripts'], true ) ) {
	fwrite( STDERR, "assets missing on Gloskin shell request\n" ); exit( 1 );
}

$GLOBALS['gl_asset_context'] = array();
$GLOBALS['gl_styles'] = array();
$GLOBALS['gl_scripts'] = array();
$commerce = true;
$service->enqueue_frontend();
if ( ! in_array( 'gloskin-ui1-core', $GLOBALS['gl_styles'], true ) || ! in_array( 'gloskin-ui1-core', $GLOBALS['gl_scripts'], true ) ) {
	fwrite( STDERR, "assets missing on Woo presentation request\n" ); exit( 1 );
}

echo "asset loading smoke passed\n";
