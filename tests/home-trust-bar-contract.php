<?php
declare(strict_types=1);

$root    = dirname( __DIR__ );
$home    = file_get_contents( $root . '/plugin/gloskin-site-core/templates/pages/home.php' );
$css     = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-editorial.css' );
$core    = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css' );
$product = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-product-grid.css' );
$ref     = file_get_contents( $root . '/docs/2026-08-18-prototype-refresh/homepage-trust-bar-reference.html' );

function trust_fail( string $message ): void {
	fwrite( STDERR, "home-trust-bar-contract.php: FAIL: {$message}\n" );
	exit( 1 );
}
function trust_must( bool $condition, string $message ): void {
	if ( ! $condition ) { trust_fail( $message ); }
}

trust_must( is_string( $home ) && is_string( $css ) && is_string( $core ) && is_string( $product ) && is_string( $ref ), 'required source files are readable' );

/* Placement: exactly one Home-only Trust Bar between the canonical hero and Why. */
$hero  = strpos( $home, 'gloskin_ui1_render_hero' );
$trust = strpos( $home, 'data-gloskin-section="home-trust"' );
$why   = strpos( $home, 'data-gloskin-section="why-gloskin"' );
trust_must( false !== $hero && false !== $trust && false !== $why && $hero < $trust && $trust < $why, 'journey must be hero -> Trust Bar -> Why' );
trust_must( 1 === substr_count( $home, 'data-gloskin-section="home-trust"' ), 'Trust Bar shell must be unique' );
trust_must( false !== strpos( $home, '<div class="gloskin-ui1-container">' ), 'Trust Bar must use the global container' );
trust_must( false !== strpos( $core, '--gloskin-container:1320px' ) && false !== strpos( $core, '--gloskin-gutter:clamp(18px,4vw,40px)' ), 'Trust Bar inherits canonical Gloskin container geometry' );

/* Production keeps the four reference trust statements and the three valid reference icons. */
foreach ( array(
	'Ditangani Ahli',
	'Dokter bersertifikat & terapis profesional.',
	'Teruji Klinis',
	'Metode aman berbasis',
	'Hasil Natural',
	'Meningkatkan kualitas tanpa merubah karakter.',
	'Klinik Terpercaya',
	'Meraih berbagai penghargaan prestisius.',
) as $needle ) {
	trust_must( false !== strpos( $home, $needle ), 'production Trust Bar missing reference content: ' . $needle );
	trust_must( false !== strpos( $ref, $needle ), 'reference source no longer contains expected Trust Bar content: ' . $needle );
}
foreach ( array(
	'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
	'M12 3l1.91 5.8a2 2 0 0 0 1.29 1.29L21 12',
	'M15.477 12.89L17 22l-5-3-5 3 1.523-9.11',
) as $needle ) {
	trust_must( false !== strpos( $home, $needle ), 'production Trust Bar missing valid reference SVG geometry: ' . $needle );
	trust_must( false !== strpos( $ref, $needle ), 'reference source no longer contains expected SVG geometry: ' . $needle );
}
/* The prototype's first expert path is malformed in-browser; production uses a normalized user-check icon. */
trust_must( false !== strpos( $home, '<circle cx="9" cy="7" r="4"></circle>' ), 'expert icon must use normalized valid user geometry' );
trust_must( false !== strpos( $home, 'M2.5 21v-2.5A6.5 6.5 0 0 1 9 12h2' ), 'expert icon must include valid shoulder geometry' );
trust_must( false === strpos( $home, 'M12 11c2.21 0 4-1.79 4-4s-1.79-4-4-4 1.79-4 4 1.79 4 4 4z' ), 'malformed prototype expert path must not return to production' );
trust_must( 4 === substr_count( $home, 'class="gloskin-home-trust__item"' ), 'Trust Bar must render exactly four items' );
trust_must( 3 === substr_count( $home, 'gloskin-home-trust__separator gloskin-home-trust__separator--' ), 'desktop Trust Bar must have exactly three separators' );

/* Reference geometry, hover treatment and responsive composition live in Home editorial CSS. */
foreach ( array(
	'.gloskin-home-trust{position:relative;z-index:4;margin-top:-60px;',
	'.gloskin-home-trust__bar{display:grid;',
	'width:100%;margin:0;padding:15px',
	'border-radius:24px',
	'box-shadow:0 25px 50px rgba(0,0,0,.04)',
	'backdrop-filter:blur(10px)',
	'padding:35px 20px',
	'width:50px;height:50px',
	'transform:translateY(-8px)',
	'transform:scale(1.15) translateY(-5px)',
	'@media (max-width:1024px)',
	'grid-template-columns:minmax(0,1fr) 1px minmax(0,1fr)',
	'@media (max-width:600px)',
	'grid-template-columns:1fr;padding:20px;border-radius:20px',
	'.gloskin-home-trust__separator{display:none}',
	'@media (prefers-reduced-motion:reduce)',
) as $needle ) {
	trust_must( false !== strpos( $css, $needle ), 'Trust Bar CSS contract missing: ' . $needle );
}
trust_must( false === strpos( $css, '.gloskin-home-trust__bar{display:grid;grid-template-columns:minmax(0,1fr) 1px minmax(0,1fr) 1px minmax(0,1fr) 1px minmax(0,1fr);align-items:stretch;width:min(100%,1200px)' ), 'Trust Bar must not reintroduce a private 1200px container cap' );

/* Trust titles intentionally inherit the exact retail product-title typography. */
foreach ( array(
	'font-family:var(--gloskin-font-ui)',
	'font-size:1.1rem',
	'font-weight:600',
	'line-height:1.3',
	'letter-spacing:0',
) as $needle ) {
	trust_must( false !== strpos( $product, $needle ), 'product-card title owner missing expected typography: ' . $needle );
	trust_must( false !== strpos( $css, $needle ), 'Trust title must match product-card title typography: ' . $needle );
}
trust_must( false !== strpos( $css, '.gloskin-home-trust__text p{margin:0;color:var(--gloskin-home-muted);font-family:var(--gloskin-font-body);font-size:.85rem;font-weight:400;line-height:1.5;letter-spacing:0;word-spacing:normal}' ), 'Trust short description uses normal body spacing' );

/* Integration must not import the standalone reference design system. */
foreach ( array( 'Playfair Display', 'FontAwesome', 'fonts.googleapis.com', 'flagcdn.com', 'IntersectionObserver' ) as $forbidden ) {
	trust_must( false === strpos( $home, $forbidden ), 'Home Trust Bar must not introduce reference dependency: ' . $forbidden );
}
trust_must( false === strpos( $home, 'style=' ), 'Trust Bar/Home static presentation must remain CSS-owned' );

echo "home-trust-bar-contract.php: OK (valid icons, global container, product-title typography, responsive overlap)\n";
