<?php
declare(strict_types=1);

$root     = dirname( __DIR__ );
$template = (string) file_get_contents( $root . '/plugin/gloskin-site-core/templates/pages/about.php' );
$css      = (string) file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-editorial.css' );

function about_reference_fail( string $message ): void {
	fwrite( STDERR, "about-reference-layout-contract.php: FAIL: {$message}\n" );
	exit( 1 );
}
function about_reference_must( bool $condition, string $message ): void {
	if ( ! $condition ) {
		about_reference_fail( $message );
	}
}

foreach ( array( 'about-header', 'about-story', 'about-founder', 'about-principles', 'about-philosophy', 'about-explore' ) as $section ) {
	about_reference_must( 1 === substr_count( $template, 'data-gloskin-section="' . $section . '"' ), 'unique About section: ' . $section );
}

about_reference_must( false !== strpos( $template, 'gloskin-about-philosophy__inner' ), 'philosophy uses a bounded left-aligned inner block' );
about_reference_must( false !== strpos( $template, "__( 'Tentang Kami', 'gloskin-site-core' )" ), 'reference philosophy eyebrow is present' );
about_reference_must( false !== strpos( $template, "'Perawatan Wajah, Anti-aging, Bedah Plastik'" ), 'reference treatment pathway title is present' );
about_reference_must( false !== strpos( $template, "'Produk Skincare Spesialis'" ), 'reference skincare pathway title is present' );
about_reference_must( false !== strpos( $template, "'Jaringan Klinik Kami'" ), 'reference clinic pathway title is present' );
about_reference_must( false === strpos( $template, 'gloskin-ui1-dark-consultation' ), 'About does not duplicate the universal footer CTA owner' );

about_reference_must( false !== strpos( $css, '.gloskin-about-header h1{margin:0;font-size:clamp(2.8rem,5vw,4rem)' ), 'reference-scale About H1 is owned by editorial.css' );
about_reference_must( false !== strpos( $css, '.gloskin-about-founder{padding:clamp(88px,8vw,120px) 0 clamp(54px,5vw,64px);background:' ), 'Founder owns reference vertical rhythm' );
about_reference_must( false !== strpos( $css, '.gloskin-about-principles{padding:0 0 clamp(88px,8vw,120px);background:' ), 'Founder and VMN remain visually continuous' );
about_reference_must( false !== strpos( $css, '.gloskin-about-principles__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:' ), 'desktop VMN is a three-card grid' );
about_reference_must( false !== strpos( $css, '.gloskin-about-principle__icon{display:block;width:clamp(60px,5vw,72px)' ), 'VMN icon scale matches reference composition' );
about_reference_must( false !== strpos( $css, '.gloskin-about-philosophy__inner{max-width:900px}' ), 'philosophy measure matches reference' );
about_reference_must( false !== strpos( $css, '.gloskin-about-explore .gloskin-ui1-pathway-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:' ), 'desktop Explore is a three-column editorial grid' );
about_reference_must( false !== strpos( $css, 'border-top:1px solid var(--gloskin-border);border-radius:0;background:transparent' ), 'Explore uses reference top-rule cards instead of boxed global cards' );
about_reference_must( false !== strpos( $css, 'grid-template-columns:repeat(2,minmax(0,1fr))}.gloskin-about-explore .gloskin-ui1-pathway-grid{grid-template-columns:repeat(2,minmax(0,1fr))}' ), 'tablet VMN and Explore converge to two columns' );
about_reference_must( false !== strpos( $css, '.gloskin-about-principles__grid{grid-template-columns:1fr}' ) && false !== strpos( $css, '.gloskin-about-explore .gloskin-ui1-pathway-grid{grid-template-columns:1fr}' ), 'mobile VMN and Explore converge to one column' );

about_reference_must( false === strpos( $css, '--gloskin-about-' ), 'About introduces no new palette/token namespace' );
$about_start = strpos( $css, '/* About canonical reference' );
$about_end   = strpos( $css, '@media (max-width:960px)', $about_start );
$about_css   = false !== $about_start && false !== $about_end ? substr( $css, $about_start, $about_end - $about_start ) : '';
about_reference_must( false !== strpos( $about_css, 'var(--gloskin-font-heading)' ) && false !== strpos( $about_css, 'var(--gloskin-font-ui)' ), 'About reuses established heading/UI font owners' );
about_reference_must( false === strpos( $about_css, 'font-family:"' ) && false === strpos( $about_css, "font-family:'" ), 'About adds no literal font family' );

echo "about-reference-layout-contract.php: OK (reference geometry + responsive flow + existing palette/fonts only)\n";
