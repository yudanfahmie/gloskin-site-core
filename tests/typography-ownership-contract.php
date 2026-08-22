<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$paths = array(
	'core_base'      => $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css',
	'editorial'      => $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-editorial.css',
	'consultation'   => $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-consultation.css',
	'production'     => $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-production.css',
	'single_product' => $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-single-product-geometry.css',
);

$css = array();
foreach ( $paths as $key => $path ) {
	$value = file_get_contents( $path );
	if ( false === $value ) {
		fwrite( STDERR, "Unable to read typography owner: {$path}\n" );
		exit( 1 );
	}
	$css[ $key ] = $value;
}

/**
 * Fail closed when a semantic selector is missing or mapped to the wrong family.
 *
 * @param string $source   Stylesheet content.
 * @param string $selector Exact selector whose declaration block is canonical.
 * @param string $family   Expected font-family value.
 * @param string $label    Human-readable contract label.
 * @return void
 */
function gloskin_require_font_owner( string $source, string $selector, string $family, string $label ): void {
	$pattern = '/' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/s';
	if ( ! preg_match( $pattern, $source, $match ) ) {
		fwrite( STDERR, "Typography owner missing: {$label} ({$selector})\n" );
		exit( 1 );
	}
	if ( false === strpos( $match[1], 'font-family:' . $family ) ) {
		fwrite( STDERR, "Typography family mismatch: {$label}; expected {$family}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $css['core_base'], '--gloskin-font-body:"Graphik","Helvetica Neue",Arial,sans-serif;' )
	|| false === strpos( $css['core_base'], '--gloskin-font-heading:"Felix Titling",Georgia,"Times New Roman",serif;' )
	|| false === strpos( $css['core_base'], '--gloskin-font-ui:"Montserrat","Helvetica Neue",Arial,sans-serif;' ) ) {
	fwrite( STDERR, "Typography token contract is incomplete\n" );
	exit( 1 );
}

// Preserve the semantic split: prose stays Graphik and editorial display stays Felix.
gloskin_require_font_owner( $css['core_base'], 'body.gloskin-ui1', 'var(--gloskin-font-body)', 'global body/prose' );
gloskin_require_font_owner( $css['core_base'], '.gloskin-ui1 h1,.gloskin-ui1 h2,.gloskin-ui1 h3,.gloskin-ui1 h4', 'var(--gloskin-font-heading)', 'global editorial headings' );

// Compact shared identity/UI primitives use the same Montserrat family as product titles.
gloskin_require_font_owner( $css['core_base'], '.gloskin-ui1-eyebrow', 'var(--gloskin-font-ui)', 'shared eyebrow/kicker' );
gloskin_require_font_owner( $css['core_base'], '.gloskin-ui1-card__title', 'var(--gloskin-font-ui)', 'shared card title' );
gloskin_require_font_owner( $css['core_base'], '.gloskin-ui1-button', 'var(--gloskin-font-ui)', 'shared action button' );

// Screenshot-critical Home treatment identity: never fall back to the body font.
gloskin_require_font_owner( $css['editorial'], '.gloskin-home-treatments .gloskin-ui1-card__title', 'var(--gloskin-font-ui)', 'Home treatment card title' );

// Treatment Finder compact prompts/path choices/actions are UI, not prose or display headings.
gloskin_require_font_owner( $css['consultation'], '.gloskin-ui1 .gloskin-ui1-consultation__prompt', 'var(--gloskin-font-ui)', 'Treatment Finder prompt' );
gloskin_require_font_owner( $css['consultation'], '.gloskin-ui1-consultation__path-label', 'var(--gloskin-font-ui)', 'Treatment Finder path label' );
gloskin_require_font_owner( $css['consultation'], '.gloskin-ui1-consultation__submit', 'var(--gloskin-font-ui)', 'Treatment Finder submit action' );

// Global navigation is compact UI; typed search content deliberately remains body copy.
gloskin_require_font_owner( $css['production'], '.gloskin-ui1-nav', 'var(--gloskin-font-nav)', 'site navigation' );
gloskin_require_font_owner( $css['production'], '.gloskin-ui1-search-overlay__input', 'var(--gloskin-font-body)', 'search field content' );

// Native Woo compact product UI must match first-party product-card identity.
gloskin_require_font_owner( $css['single_product'], 'body.single-product.gloskin-ui1 .gloskin-ui1-commerce-native>div.product .woocommerce-tabs ul.tabs li a', 'var(--gloskin-font-ui)', 'PDP tabs' );
gloskin_require_font_owner( $css['single_product'], 'body.single-product.gloskin-ui1 .gloskin-ui1-commerce-native>div.product .related.products .woocommerce-loop-product__title', 'var(--gloskin-font-ui)', 'related product title' );
gloskin_require_font_owner( $css['single_product'], 'body.single-product.gloskin-ui1 .gloskin-ui1-commerce-native>div.product .related.products .price', 'var(--gloskin-font-ui)', 'related product price' );

echo "typography-ownership-contract.php: OK\n";
