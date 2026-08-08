<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['gl_asset_context'] = array();
$GLOBALS['gl_styles'] = array();
$GLOBALS['gl_scripts'] = array();
$GLOBALS['gl_registered_styles'] = array();
$GLOBALS['gl_registered_scripts'] = array();

function add_action() {}
function get_query_var( $key, $default = '' ) { return 'gloskin_context' === $key ? $GLOBALS['gl_asset_context'] : $default; }
function plugins_url( $src, $file ) { return '/plugins/gloskin/' . ltrim( (string) $src, '/' ); }
function wp_register_style( $handle, $src, $deps = array(), $version = false, $media = 'all' ) { $GLOBALS['gl_registered_styles'][ $handle ] = compact( 'src', 'deps', 'version', 'media' ); }
function wp_register_script( $handle, $src, $deps = array(), $version = false, $in_footer = false ) { $GLOBALS['gl_registered_scripts'][ $handle ] = compact( 'src', 'deps', 'version', 'in_footer' ); }
function wp_enqueue_style( $handle ) { $GLOBALS['gl_styles'][] = (string) $handle; }
function wp_enqueue_script( $handle ) { $GLOBALS['gl_scripts'][] = (string) $handle; }

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php';

$commerce = false;
$asset_version = '0.3.3';
$service = new Gloskin_Site_Core_Asset_Service(
	dirname( __DIR__ ) . '/plugin/gloskin-site-core/gloskin-site-core.php',
	$asset_version,
	static function () use ( &$commerce ) { return $commerce; }
);

$service->enqueue_frontend();
if ( $GLOBALS['gl_styles'] || $GLOBALS['gl_scripts'] ) { fwrite( STDERR, "assets loaded on unrelated request\n" ); exit( 1 ); }

$GLOBALS['gl_asset_context'] = array( 'view' => 'home' );
$service->enqueue_frontend();
foreach ( array( 'gloskin-ui1-fonts', 'gloskin-ui1-core-base', 'gloskin-ui1-core', 'gloskin-ui1-production' ) as $style_handle ) {
	if ( ! in_array( $style_handle, $GLOBALS['gl_styles'], true ) ) { fwrite( STDERR, "frontend style missing on Gloskin shell request: {$style_handle}\n" ); exit( 1 ); }
}
if ( ! in_array( 'gloskin-ui1-core', $GLOBALS['gl_scripts'], true ) ) { fwrite( STDERR, "frontend script missing on Gloskin shell request\n" ); exit( 1 ); }
$font = $GLOBALS['gl_registered_styles']['gloskin-ui1-fonts'] ?? array();
if ( empty( $font['src'] ) || 0 !== strpos( $font['src'], 'https://fonts.googleapis.com/css2?family=Marcellus&family=Mulish:wght@400;600;700;800' ) ) { fwrite( STDERR, "authoritative Marcellus/Mulish font stylesheet registration failed\n" ); exit( 1 ); }
$base = $GLOBALS['gl_registered_styles']['gloskin-ui1-core-base'] ?? array();
$core = $GLOBALS['gl_registered_styles']['gloskin-ui1-core'] ?? array();
$production = $GLOBALS['gl_registered_styles']['gloskin-ui1-production'] ?? array();
$core_script = $GLOBALS['gl_registered_scripts']['gloskin-ui1-core'] ?? array();
if ( ( $base['deps'] ?? array() ) !== array( 'gloskin-ui1-fonts' ) || ( $core['deps'] ?? array() ) !== array( 'gloskin-ui1-core-base' ) || ( $production['deps'] ?? array() ) !== array( 'gloskin-ui1-core' ) ) { fwrite( STDERR, "frontend stylesheet dependency order failed\n" ); exit( 1 ); }
foreach ( array( 'font' => $font, 'core-base' => $base, 'core' => $core, 'production' => $production, 'core-script' => $core_script ) as $label => $asset ) {
	if ( ( $asset['version'] ?? null ) !== $asset_version ) { fwrite( STDERR, "stale frontend asset version for {$label}\n" ); exit( 1 ); }
}

$GLOBALS['gl_asset_context'] = array();
$GLOBALS['gl_styles'] = array();
$GLOBALS['gl_scripts'] = array();
$commerce = true;
$service->enqueue_frontend();
if ( ! in_array( 'gloskin-ui1-production', $GLOBALS['gl_styles'], true ) || ! in_array( 'gloskin-ui1-core', $GLOBALS['gl_scripts'], true ) ) { fwrite( STDERR, "assets missing on Woo presentation request\n" ); exit( 1 ); }

echo "asset loading smoke passed\n";
