<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
$root = dirname( __DIR__ );
$registry = require $root . '/plugin/gloskin-site-core/config/assets.php';
$css = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-loader-system.css' );
$shell = file_get_contents( $root . '/plugin/gloskin-site-core/templates/shell.php' );
if ( false === $css || false === $shell ) {
	fwrite( STDERR, "Unable to read reusable loader owners\n" );
	exit( 1 );
}

$loader = $registry['styles']['gloskin-ui1-loader-system'] ?? array();
if ( ( $loader['src'] ?? '' ) !== 'assets/css/gloskin-ui1-loader-system.css'
	|| ( $loader['deps'] ?? array() ) !== array( 'gloskin-ui1-commerce-polish' ) ) {
	fwrite( STDERR, "Reusable loader stylesheet is not final-loaded after commerce polish\n" );
	exit( 1 );
}

$required = array(
	'@keyframes gloskin-ui1-goo-loader-dance',
	'gloskin-ui1-commerce-handoff__blob',
	'gloskin-ui1-hero-bg-video__loader::before',
	'gloskin-ui1-hero-bg-video__loader-dot::after',
	'gloskin-ui1-quickadd__loading::before',
	'gloskin-ui1-quickadd__loading>span::after',
	'body.gloskin-ui1 a.gloskin-ui1-cart-sheet__item-remove.remove_from_cart_button',
	'body.gloskin-ui1 button.gloskin-ui1-wishlist-sheet__item-remove[data-gloskin-wishlist-toggle]',
	'--gloskin-remove-icon-color:var(--gloskin-accent-strong)',
	'filter:url("#gloskin-ui1-commerce-handoff-goo")',
	'animation:gloskin-ui1-goo-loader-dance 3.5s infinite ease-in-out',
	'@media (prefers-reduced-motion:reduce)',
);
foreach ( $required as $needle ) {
	if ( false === strpos( $css, $needle ) ) {
		fwrite( STDERR, "Reusable loader/remove contract missing: {$needle}\n" );
		exit( 1 );
	}
}

if ( 1 !== substr_count( $shell, 'id="gloskin-ui1-commerce-handoff-goo"' ) ) {
	fwrite( STDERR, "Expected exactly one global goo SVG filter definition\n" );
	exit( 1 );
}
if ( false === strpos( $shell, '<feGaussianBlur in="SourceGraphic" stdDeviation="10"' )
	|| false === strpos( $shell, '0 0 0 20 -10' ) ) {
	fwrite( STDERR, "Global goo filter no longer matches the adopted reference composition\n" );
	exit( 1 );
}
if ( false !== strpos( $css, '!important' ) ) {
	fwrite( STDERR, "Loader/remove system must not add !important\n" );
	exit( 1 );
}
if ( false !== strpos( $css, 'codepen.io' ) || false !== strpos( $css, 'http://' ) || false !== strpos( $css, 'https://' ) ) {
	fwrite( STDERR, "Reusable loader introduced an external runtime dependency\n" );
	exit( 1 );
}
if ( false !== strpos( $css, '.woocommerce a.gloskin-ui1-cart-sheet__item-remove' ) ) {
	fwrite( STDERR, "Mini-cart remove styling regressed to a page-wrapper-dependent selector\n" );
	exit( 1 );
}

echo "reusable-loader-contract.php: OK\n";
