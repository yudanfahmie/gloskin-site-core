<?php
declare(strict_types=1);

$root   = dirname( __DIR__ );
$plugin = $root . '/plugin/gloskin-site-core';

function home_fail( string $message ): void {
	fwrite( STDERR, "home-readiness-contract.php: FAIL: {$message}\n" );
	exit( 1 );
}

function home_must( bool $condition, string $message ): void {
	if ( ! $condition ) {
		home_fail( $message );
	}
}

function home_text( string $path ): string {
	$value = @file_get_contents( $path );
	if ( false === $value ) {
		home_fail( 'cannot read ' . $path );
	}
	return $value;
}

$home      = home_text( $plugin . '/templates/pages/home.php' );
$helpers   = home_text( $plugin . '/templates/parts/template-helpers.php' );
$template  = home_text( $plugin . '/includes/class-gloskin-site-core-template-service.php' );
$editorial = home_text( $plugin . '/assets/css/gloskin-ui1-editorial.css' );
$kernel    = home_text( $plugin . '/includes/class-gloskin-site-core-kernel.php' );
$bootstrap = home_text( $plugin . '/gloskin-site-core.php' );

/* Canonical Home journey remains one ordered composition. */
$order = array(
	'gloskin_ui1_render_hero',
	'data-gloskin-section="why-gloskin"',
	'data-gloskin-section="home-treatments"',
	'data-gloskin-section="testimonials"',
	'data-gloskin-section="achievements"',
);
$last = -1;
foreach ( $order as $needle ) {
	$position = strpos( $home, $needle );
	home_must( false !== $position && $position > $last, 'Home order ' . $needle );
	$last = $position;
}
home_must( 1 === substr_count( $home, 'data-gloskin-section="home-treatments"' ), 'Treatment shell is unique' );
home_must( 1 === substr_count( $home, 'data-gloskin-section="testimonials"' ), 'Testimonial shell is unique' );
home_must( 1 === substr_count( $home, 'data-gloskin-section="achievements"' ), 'Piagam shell is unique' );
home_must( false === strpos( substr( $home, $last ), '<section' ), 'nothing renders after Piagam inside Home template' );

/* Visible copy comes directly from docs/home-canonical-reference.html. */
$reference_copy = array(
	'Kami hadir untuk memberikan solusi estetika terdepan yang tidak hanya merawat, tetapi juga menyehatkan kulit Anda dari dalam.',
	'Tersedia pilihan perawatan yang lengkap dan inovatif berdasarkan keilmuan estetik terkini.',
	'Ditangani oleh dokter dan terapis yang berpengalaman, tersertifikasi, dan terus mendapatkan update ilmu.',
	'Produk skincare dirancang khusus dan teruji klinis untuk menjawab berbagai masalah kulit masyarakat.',
	'Rangkaian perawatan eksklusif yang dirancang secara personal dengan teknologi mutakhir untuk memancarkan kecantikan sejati kulit Anda.',
	'Pengalaman nyata dari mereka yang telah mempercayakan perjalanan kecantikannya dan merasakan transformasi luar biasa bersama GLOSKIN.',
	'Bukti komitmen dan dedikasi tinggi kami dalam menjaga standar mutu pelayanan estetika dan inovasi medis terbaik di Indonesia.',
);
foreach ( $reference_copy as $copy ) {
	home_must( false !== strpos( $home, $copy ), 'canonical Home reference copy missing: ' . $copy );
}
home_must( false !== strpos( $home, "__( 'KENAPA MEMILIH'" ) && false !== strpos( $home, "__( 'GLOSKIN'" ), 'Why heading preserves the reference line break' );
home_must( false !== strpos( $home, "__( 'PIAGAM & PENGHARGAAN'" ), 'Piagam heading matches reference' );

/* The shared heading helper owns subtitles; Home must pass all three. */
home_must( false !== strpos( $helpers, 'function gloskin_ui1_render_section_heading( $title, $copy = \'\' )' ), 'shared section heading helper supports copy' );
home_must( substr_count( $home, 'gloskin_ui1_render_section_heading(' ) === 3, 'all three reference section headers use the shared heading helper' );

/* Reference composition: three Treatment cards, up to four testimonial dots, five award cards in the rail. */
home_must( false !== strpos( $home, "array_slice( isset( \$gloskin_context['treatments'] ) && is_array( \$gloskin_context['treatments'] ) ? \$gloskin_context['treatments'] : array(), 0, 3 )" ), 'Home Treatment row is bounded to 3' );
home_must( false !== strpos( $home, 'array_slice( $gloskin_home_testimonials, 0, 4 )' ), 'Home testimonials are bounded to 4' );
home_must( false !== strpos( $home, "array_slice( isset( \$gloskin_context['achievements'] ) && is_array( \$gloskin_context['achievements'] ) ? \$gloskin_context['achievements'] : array(), 0, 5 )" ), 'Home awards are bounded to 5' );

/* Canonical Treatment cards are image + title + detail link only. */
home_must( false !== strpos( $home, "\$gloskin_home_treatment_card['summary'] = '';" ), 'Home suppresses Treatment summary copy' );
home_must( false !== strpos( $home, "\$gloskin_home_treatment_card['excerpt'] = '';" ), 'Home suppresses Treatment excerpt copy' );
home_must( false !== strpos( $editorial, '.gloskin-home-treatments .gloskin-ui1-card__copy{display:none}' ), 'Home Treatment card copy stays visually absent' );

/* Home explicitly disables the legacy campaign-video path while reusing the shared hero renderer. */
home_must( false !== strpos( $home, "\$gloskin_home_hero['mode']    = 'home_reference';" ), 'Home canonical hero mode is explicit' );
home_must( false !== strpos( $home, "\$gloskin_home_hero['sources'] = array();" ), 'Home canonical hero disables video sources' );
home_must( false !== strpos( $editorial, 'body.gloskin-ui1--home .gloskin-ui1-hero{min-height:clamp(440px,80vh,780px);background:#cf9aa2}' ), 'Home hero owns the reference full-bleed geometry' );
home_must( false !== strpos( $editorial, 'linear-gradient(135deg,rgba(168,28,49,.5) 0%,rgba(168,28,49,.2) 100%)' ), 'Home hero owns the reference rose tint' );

/* Awards follow the canonical small text-card treatment; production facts remain managed CPT data. */
foreach ( array( "['title'] ?? ''", "['meta']['issuer'] ?? ''", "['meta']['year'] ?? ''", 'gloskin-home-piagam__title', 'gloskin-home-piagam__icon', 'gloskin-home-piagam__meta' ) as $needle ) {
	home_must( false !== strpos( $home, $needle ), 'Piagam factual presentation missing: ' . $needle );
}
home_must( false === strpos( $home, "wp_get_attachment_image( absint( \$gloskin_home_achievement['image_id'] )" ), 'Home no longer renders giant certificate images' );
foreach ( array( 'TOP CLINIC', 'INNOVATION', 'BEST SERVICE', 'Customer Choice', 'Brand Award' ) as $dummy ) {
	home_must( false === strpos( $home, $dummy ), 'reference dummy content must not become production fact: ' . $dummy );
}

/* Canonical journey palette and component geometry stay local to Home. */
foreach ( array(
	'--gloskin-home-white:#fff',
	'--gloskin-home-peach:#fcf9f7',
	'--gloskin-home-blush:#f7ebe8',
	'.gloskin-home-why{background:var(--gloskin-home-white)}',
	'.gloskin-home-treatments{background:var(--gloskin-home-peach)}',
	'.gloskin-home-testimonials{background:var(--gloskin-home-blush)}',
	'.gloskin-home-piagam{background:var(--gloskin-home-white)}',
	'.gloskin-home-treatments__grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:40px}',
	'.gloskin-home-testimonials__slider{width:min(100%,900px);margin:0 auto}',
	'.gloskin-home-piagam__card{position:relative;flex:0 0 260px;',
) as $needle ) {
	home_must( false !== strpos( $editorial, $needle ), 'Home editorial primitive missing: ' . $needle );
}

/* Existing canonical data owners remain in TemplateService. */
$home_ctx_start = strpos( $template, 'private function home_context()' );
$home_ctx_end   = strpos( $template, 'private function about_context()', $home_ctx_start );
$home_ctx       = false !== $home_ctx_start && false !== $home_ctx_end ? substr( $template, $home_ctx_start, $home_ctx_end - $home_ctx_start ) : '';
home_must( false !== strpos( $home_ctx, '$this->curated_home_treatments()' ), 'Treatment data owner remains TemplateService' );
home_must( false !== strpos( $home_ctx, 'published_managed_records' ), 'testimonial/achievement data owner remains managed CPT records' );

/* Keep fallback behavior and release owners healthy. */
home_must( substr_count( $home, 'gloskin_ui1_render_empty_state(' ) >= 3, 'Home retains explicit empty states' );
home_must( preg_match( "/const VERSION = '([^']+)'/", $kernel, $kernel_version ) === 1, 'kernel version is readable' );
home_must( preg_match( '/Version:\s*([^\s]+)/', $bootstrap, $bootstrap_version ) === 1, 'plugin header version is readable' );
home_must( $kernel_version[1] === $bootstrap_version[1], 'release owners remain synchronized' );

echo "home-readiness-contract.php: OK (canonical visual reference, static tinted hero, 3 Treatments, testimonial slider, compact awards)\n";