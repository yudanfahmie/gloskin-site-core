<?php
/**
 * Navigation typography contract.
 *
 * Asserts the DM Sans font asset, @font-face declaration, CSS token
 * architecture, and one canonical nav font owner introduced in v0.7.125.
 * Source-level checks only -- no browser required.
 *
 * @package GloskinSiteCore
 */
declare(strict_types=1);

$root         = dirname( __DIR__ );
$fonts_dir    = $root . '/plugin/gloskin-site-core/assets/fonts';
$css_dir      = $root . '/plugin/gloskin-site-core/assets/css';
$fonts_css    = file_get_contents( $css_dir . '/gloskin-ui1-fonts.css' );
$production   = file_get_contents( $css_dir . '/gloskin-ui1-production.css' );
$base         = file_get_contents( $css_dir . '/gloskin-ui1-core-base.css' );
$core         = file_get_contents( $css_dir . '/gloskin-ui1-core.css' );

if ( false === $fonts_css || false === $production || false === $base || false === $core ) {
	fwrite( STDERR, "nav-font-contract: unable to read required CSS files\n" );
	exit( 1 );
}

// ── FONT ASSET ──────────────────────────────────────────────────────────────

$woff2 = $fonts_dir . '/DMSans-Variable.woff2';
if ( ! file_exists( $woff2 ) ) {
	fwrite( STDERR, "nav-font-contract: DMSans-Variable.woff2 is missing from assets/fonts/\n" );
	exit( 1 );
}
if ( filesize( $woff2 ) < 10000 ) {
	fwrite( STDERR, "nav-font-contract: DMSans-Variable.woff2 suspiciously small (" . filesize( $woff2 ) . " bytes)\n" );
	exit( 1 );
}
// Verify WOFF2 magic bytes (ASCII "wOF2").
$fh = fopen( $woff2, 'rb' );
$magic = fread( $fh, 4 );
fclose( $fh );
if ( $magic !== 'wOF2' ) {
	fwrite( STDERR, "nav-font-contract: DMSans-Variable.woff2 has invalid WOFF2 magic bytes\n" );
	exit( 1 );
}

$ofl = $fonts_dir . '/DMSans-OFL.txt';
if ( ! file_exists( $ofl ) ) {
	fwrite( STDERR, "nav-font-contract: DMSans-OFL.txt license is missing from assets/fonts/\n" );
	exit( 1 );
}
$ofl_content = file_get_contents( $ofl );
if ( false === $ofl_content || false === strpos( $ofl_content, 'SIL OPEN FONT LICENSE' ) ) {
	fwrite( STDERR, "nav-font-contract: DMSans-OFL.txt does not contain the SIL Open Font License notice\n" );
	exit( 1 );
}

// ── @font-face DECLARATION ──────────────────────────────────────────────────

// Exactly one "DM Sans" @font-face in the fonts stylesheet.
$dm_face_count = substr_count( $fonts_css, '"DM Sans"' );
if ( 1 !== $dm_face_count ) {
	fwrite( STDERR, "nav-font-contract: expected exactly 1 DM Sans @font-face in gloskin-ui1-fonts.css, found {$dm_face_count}\n" );
	exit( 1 );
}
if ( false === strpos( $fonts_css, 'DMSans-Variable.woff2' ) ) {
	fwrite( STDERR, "nav-font-contract: DM Sans @font-face must reference DMSans-Variable.woff2\n" );
	exit( 1 );
}
if ( false === strpos( $fonts_css, 'font-style:normal' ) || false === strpos( $fonts_css, 'font-weight:400 700' ) ) {
	fwrite( STDERR, "nav-font-contract: DM Sans @font-face must declare font-style:normal and font-weight:400 700\n" );
	exit( 1 );
}
if ( false === strpos( $fonts_css, 'font-display:fallback' ) ) {
	fwrite( STDERR, "nav-font-contract: DM Sans @font-face must use font-display:fallback\n" );
	exit( 1 );
}

// No external Google Fonts URL in the fonts stylesheet.
if ( false !== strpos( $fonts_css, 'fonts.googleapis.com' ) || false !== strpos( $fonts_css, 'fonts.gstatic.com' ) ) {
	fwrite( STDERR, "nav-font-contract: gloskin-ui1-fonts.css must not contain external Google Fonts URLs\n" );
	exit( 1 );
}

// No unnecessary italic declaration for DM Sans.
// The italic face should not appear -- Gloskin only declares the normal face.
$dm_italic = preg_match( '/font-family:"DM Sans"[^}]*font-style:italic/s', $fonts_css );
if ( $dm_italic ) {
	fwrite( STDERR, "nav-font-contract: gloskin-ui1-fonts.css must not declare a DM Sans italic face\n" );
	exit( 1 );
}

// ── CSS TOKEN AND OWNERSHIP ARCHITECTURE ────────────────────────────────────

// Exactly one --gloskin-font-nav token definition across all CSS files.
$all_css = $fonts_css . $base . $core . $production;
$token_count = substr_count( $all_css, '--gloskin-font-nav:' );
if ( 1 !== $token_count ) {
	fwrite( STDERR, "nav-font-contract: expected exactly 1 --gloskin-font-nav token definition, found {$token_count}\n" );
	exit( 1 );
}

// The token lives in production.css (the production typography layer).
if ( false === strpos( $production, '--gloskin-font-nav:' ) ) {
	fwrite( STDERR, "nav-font-contract: --gloskin-font-nav must be defined in gloskin-ui1-production.css\n" );
	exit( 1 );
}

// The token references DM Sans as the first family.
if ( ! preg_match( '/--gloskin-font-nav:\s*"DM Sans"/', $production ) ) {
	fwrite( STDERR, "nav-font-contract: --gloskin-font-nav must begin with \"DM Sans\"\n" );
	exit( 1 );
}

// body and heading tokens must remain unchanged.
if ( false === strpos( $production, '--gloskin-font-body:' ) ) {
	fwrite( STDERR, "nav-font-contract: --gloskin-font-body must remain defined in production.css\n" );
	exit( 1 );
}
if ( false === strpos( $production, '--gloskin-font-heading:' ) ) {
	fwrite( STDERR, "nav-font-contract: --gloskin-font-heading must remain defined in production.css\n" );
	exit( 1 );
}
// Heading must still reference Marcellus (unchanged).
if ( ! preg_match( '/--gloskin-font-heading:\s*"Marcellus"/', $production ) ) {
	fwrite( STDERR, "nav-font-contract: --gloskin-font-heading must still begin with \"Marcellus\" (body/heading unchanged)\n" );
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

// No other nav-specific font-family override in production.css beyond the one owner.
// Allow font-family only on .gloskin-ui1-nav itself; reject descendants.
$nav_font_overrides = preg_match_all(
	'/\.gloskin-ui1-nav[_-][a-z]+[^{]*\{[^}]*font-family/s',
	$production,
	$matches
);
if ( $nav_font_overrides ) {
	fwrite( STDERR, "nav-font-contract: unexpected font-family on nav descendant selector(s): " . implode( ', ', $matches[0] ) . "\n" );
	exit( 1 );
}

// The brand (.gloskin-ui1-brand) is scoped by its own production rule and must
// NOT inherit DM Sans via an overly broad .gloskin-ui1-nav selector.
// The logo is an <a class="gloskin-ui1-brand"> OUTSIDE .gloskin-ui1-nav, so
// CSS inheritance cannot reach it. Verify the brand font is still heading.
if ( ! preg_match( '/\.gloskin-ui1-brand\s*\{[^}]*font-family\s*:\s*var\(--gloskin-font-heading\)/s', $production ) ) {
	fwrite( STDERR, "nav-font-contract: .gloskin-ui1-brand must still declare font-family:var(--gloskin-font-heading)\n" );
	exit( 1 );
}

// ── NO !important IN NAV FONT RULES ─────────────────────────────────────────

// The nav font rules must not use !important.
if ( preg_match( '/\.gloskin-ui1-nav[^}]*font-family[^}]*!important/s', $all_css ) ) {
	fwrite( STDERR, "nav-font-contract: nav font-family must not use !important\n" );
	exit( 1 );
}

// ── PRODUCTION JS UNCHANGED ──────────────────────────────────────────────────

$js_path  = dirname( $css_dir ) . '/js/gloskin-ui1-core.js';
if ( ! file_exists( $js_path ) ) {
	fwrite( STDERR, "nav-font-contract: gloskin-ui1-core.js not found\n" );
	exit( 1 );
}
// No nav-font-related modifications should be in JS.
$js = file_get_contents( $js_path );
if ( false !== strpos( $js, 'font-family' ) && false !== strpos( $js, 'DM Sans' ) ) {
	fwrite( STDERR, "nav-font-contract: gloskin-ui1-core.js must not reference DM Sans (font change is CSS-only)\n" );
	exit( 1 );
}

echo "nav-font-contract: OK\n";
