<?php
/**
 * Navigation typography and font-system contract.
 *
 * Asserts the Graphik/Felix Titling font asset, @font-face declaration,
 * CSS token architecture, and one canonical nav font owner.
 * Updated from the DM Sans era (v0.7.125) to the Graphik era (v0.7.132).
 * Source-level checks only -- no browser required.
 *
 * @package GloskinSiteCore
 */
declare(strict_types=1);

$root       = dirname( __DIR__ );
$fonts_dir  = $root . '/plugin/gloskin-site-core/assets/fonts';
$css_dir    = $root . '/plugin/gloskin-site-core/assets/css';
$fonts_css  = file_get_contents( $css_dir . '/gloskin-ui1-fonts.css' );
$production = file_get_contents( $css_dir . '/gloskin-ui1-production.css' );
$base       = file_get_contents( $css_dir . '/gloskin-ui1-core-base.css' );
$core       = file_get_contents( $css_dir . '/gloskin-ui1-core.css' );

if ( false === $fonts_css || false === $production || false === $base || false === $core ) {
	fwrite( STDERR, "nav-font-contract: unable to read required CSS files\n" );
	exit( 1 );
}

// ── FONT ASSET PRESENCE ─────────────────────────────────────────────────────

/**
 * Verify a woff2 file exists, is non-trivially sized, and has valid WOFF2
 * magic bytes (ASCII "wOF2").
 */
function gloskin_assert_woff2( string $path, string $label ): void {
	if ( ! file_exists( $path ) ) {
		fwrite( STDERR, "nav-font-contract: $label is missing from assets/fonts/\n" );
		exit( 1 );
	}
	if ( filesize( $path ) < 10000 ) {
		fwrite( STDERR, "nav-font-contract: $label suspiciously small (" . filesize( $path ) . " bytes)\n" );
		exit( 1 );
	}
	$fh = fopen( $path, 'rb' );
	if ( false === $fh ) {
		fwrite( STDERR, "nav-font-contract: cannot open $label\n" );
		exit( 1 );
	}
	$magic = fread( $fh, 4 );
	fclose( $fh );
	if ( 'wOF2' !== $magic ) {
		fwrite( STDERR, "nav-font-contract: $label has invalid WOFF2 magic bytes\n" );
		exit( 1 );
	}
}

// Critical preloaded faces.
gloskin_assert_woff2( $fonts_dir . '/GraphikRegular.woff2', 'GraphikRegular.woff2' );
gloskin_assert_woff2( $fonts_dir . '/Felixti.woff2', 'Felixti.woff2' );

// Full Graphik weight coverage.
foreach ( array( 'GraphikLight', 'GraphikLightItalic', 'GraphikRegularItalic', 'GraphikMedium', 'GraphikMediumItalic', 'GraphikSemibold', 'GraphikBold' ) as $face ) {
	gloskin_assert_woff2( $fonts_dir . "/{$face}.woff2", "{$face}.woff2" );
}

// Legacy fonts must be absent (no consumers remain).
foreach ( array( 'Marcellus-Regular.woff2', 'Mulish-Variable.woff2', 'DMSans-Variable.woff2' ) as $legacy ) {
	if ( file_exists( $fonts_dir . '/' . $legacy ) ) {
		fwrite( STDERR, "nav-font-contract: retired legacy font {$legacy} still present in assets/fonts/\n" );
		exit( 1 );
	}
}

// ── @font-face DECLARATIONS ─────────────────────────────────────────────────

// Graphik must appear as exactly one family name across all @font-face rules
// (eight rules: 300n, 300i, 400n, 400i, 500n, 500i, 600n, 700n).
$graphik_face_count = substr_count( $fonts_css, '"Graphik"' );
if ( $graphik_face_count < 8 ) {
	fwrite( STDERR, "nav-font-contract: expected >= 8 Graphik @font-face rules in gloskin-ui1-fonts.css, found {$graphik_face_count}\n" );
	exit( 1 );
}

// Felix Titling must appear exactly once.
$felix_face_count = substr_count( $fonts_css, '"Felix Titling"' );
if ( 1 !== $felix_face_count ) {
	fwrite( STDERR, "nav-font-contract: expected exactly 1 Felix Titling @font-face in gloskin-ui1-fonts.css, found {$felix_face_count}\n" );
	exit( 1 );
}

// font-display:swap required for both families (FOIT prevention).
if ( false === strpos( $fonts_css, 'font-display:swap' ) ) {
	fwrite( STDERR, "nav-font-contract: gloskin-ui1-fonts.css must use font-display:swap\n" );
	exit( 1 );
}

// No external Google Fonts URLs.
if ( false !== strpos( $fonts_css, 'fonts.googleapis.com' ) || false !== strpos( $fonts_css, 'fonts.gstatic.com' ) ) {
	fwrite( STDERR, "nav-font-contract: gloskin-ui1-fonts.css must not contain external Google Fonts URLs\n" );
	exit( 1 );
}

// No legacy DM Sans / Marcellus / Mulish @font-face in fonts stylesheet.
foreach ( array( '"DM Sans"', '"Marcellus"', '"Mulish"' ) as $legacy_family ) {
	if ( false !== strpos( $fonts_css, $legacy_family ) ) {
		fwrite( STDERR, "nav-font-contract: retired font family {$legacy_family} remains in gloskin-ui1-fonts.css\n" );
		exit( 1 );
	}
}

// ── CSS TOKEN AND OWNERSHIP ARCHITECTURE ────────────────────────────────────

// Exactly one --gloskin-font-nav token definition across all CSS files.
// The prototype-refresh layer ALSO defines it; check only the production layer.
$token_in_production = substr_count( $production, '--gloskin-font-nav:' );
if ( 0 === $token_in_production ) {
	fwrite( STDERR, "nav-font-contract: --gloskin-font-nav must be defined in gloskin-ui1-production.css\n" );
	exit( 1 );
}

// body and heading font tokens must remain present.
if ( false === strpos( $production, '--gloskin-font-body:' ) ) {
	fwrite( STDERR, "nav-font-contract: --gloskin-font-body must remain defined in production.css\n" );
	exit( 1 );
}
if ( false === strpos( $production, '--gloskin-font-heading:' ) ) {
	fwrite( STDERR, "nav-font-contract: --gloskin-font-heading must remain defined in production.css\n" );
	exit( 1 );
}

// Font tokens must reference the Graphik / Felix Titling family names.
if ( ! preg_match( '/--gloskin-font-body:\s*"Graphik"/', $production ) ) {
	fwrite( STDERR, "nav-font-contract: --gloskin-font-body must begin with \"Graphik\" in production.css\n" );
	exit( 1 );
}
if ( ! preg_match( '/--gloskin-font-heading:\s*"Felix Titling"/', $production ) ) {
	fwrite( STDERR, "nav-font-contract: --gloskin-font-heading must begin with \"Felix Titling\" in production.css\n" );
	exit( 1 );
}

// ── ONE CANONICAL NAV FONT OWNER ────────────────────────────────────────────

// .gloskin-ui1-nav { font-family: var(--gloskin-font-nav) } is the ONE owner.
if ( ! preg_match( '/\.gloskin-ui1-nav\s*\{[^}]*font-family\s*:\s*var\(--gloskin-font-nav\)/s', $production ) ) {
	fwrite( STDERR, "nav-font-contract: .gloskin-ui1-nav must own font-family:var(--gloskin-font-nav) in production.css\n" );
	exit( 1 );
}

// .gloskin-ui1-nav__link must NOT repeat a font-family declaration
// (it should inherit from .gloskin-ui1-nav).
if ( preg_match( '/\.gloskin-ui1-nav__link\s*\{[^}]*font-family/s', $production ) ) {
	fwrite( STDERR, "nav-font-contract: .gloskin-ui1-nav__link must not repeat font-family (should inherit from .gloskin-ui1-nav)\n" );
	exit( 1 );
}

// ── NO !important IN NAV FONT RULES ─────────────────────────────────────────

$all_css = $fonts_css . $base . $core . $production;
if ( preg_match( '/\.gloskin-ui1-nav[^}]*font-family[^}]*!important/s', $all_css ) ) {
	fwrite( STDERR, "nav-font-contract: nav font-family must not use !important\n" );
	exit( 1 );
}

// ── PRODUCTION JS UNCHANGED ──────────────────────────────────────────────────

$js_path = dirname( $css_dir ) . '/js/gloskin-ui1-core.js';
if ( ! file_exists( $js_path ) ) {
	fwrite( STDERR, "nav-font-contract: gloskin-ui1-core.js not found\n" );
	exit( 1 );
}
$js = file_get_contents( $js_path );
// JS must not reference legacy font names.
foreach ( array( 'DM Sans', 'Marcellus', 'Mulish' ) as $legacy ) {
	if ( false !== strpos( $js, $legacy ) ) {
		fwrite( STDERR, "nav-font-contract: gloskin-ui1-core.js must not reference retired font {$legacy}\n" );
		exit( 1 );
	}
}

echo "nav-font-contract: OK (Graphik/Felix Titling era, v0.7.132+)\n";
