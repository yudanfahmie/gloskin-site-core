<?php
declare(strict_types=1);

ob_start();
require __DIR__ . '/runtime-smoke.php';
ob_end_clean();

$home = get_page_by_path( 'home', OBJECT, 'page' );
$GLOBALS['gl_route'] = array( 'front' => true, 'page' => true, 'singular' => '', 'object' => $home );
$template = apply_filters( 'template_include', '/theme/index.php' );

ob_start();
require $template;
$html = ob_get_clean();

if ( ! is_string( $html ) || false === strpos( $html, 'data-gloskin-drawer' ) ) {
	fwrite( STDERR, "Fixture render failed\n" );
	exit( 1 );
}

echo $html;
