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

/* Reference composition: three Treatment cards, up to four testimonial dots, five factual award cards. */
home_must( false !== strpos( $home, "array_slice( isset( \$gloskin_context['treatments'] ) && is_array( \$gloskin_context['treatments'] ) ? \$gloskin_context['treatments'] : array(), 0, 3 )" ), 'Home Treatment row is bounded to 3' );
home_must( false !== strpos( $home, 'array_slice( $gloskin_home_testimonials, 0, 4 )' ), 'Home testimonials are bounded to 4' );
home_must( false !== strpos( $home, "array_slice( isset( \$gloskin_context['achievements'] ) && is_array( \$gloskin_context['achievements'] ) ? \$gloskin_context['achievements'] : array(), 0, 5 )" ), 'Home awards are bounded to 5 source records' );

/* Canonical Treatment cards are image + title + detail link only. */
home_must( false !== strpos( $home, "\$gloskin_home_treatment_card['summary'] = '';" ), 'Home suppresses Treatment summary copy' );
home_must( false !== strpos( $home, "\$gloskin_home_treatment_card['excerpt'] = '';" ), 'Home suppresses Treatment excerpt copy' );
home_must( false !== strpos( $editorial, '.gloskin-home-treatments .gloskin-ui1-card__copy{display:none}' ), 'Home Treatment card copy stays visually absent' );

/* Home must use TemplateService's native managed video; the template may not downgrade it to a static image. */
home_must( false === strpos( $home, "['mode']    = 'home_reference'" ), 'Home does not override the managed hero mode' );
home_must( false === strpos( $home, "['sources'] = array()" ), 'Home does not discard managed video sources' );
$home_ctx_start = strpos( $template, 'private function home_context()' );
$home_ctx_end   = strpos( $template, 'private function about_context()', $home_ctx_start );
$home_ctx       = false !== $home_ctx_start && false !== $home_ctx_end ? substr( $template, $home_ctx_start, $home_ctx_end - $home_ctx_start ) : '';
home_must( false !== strpos( $home_ctx, '$this->hero_background_video()' ), 'Home context resolves the native background video' );
home_must( false !== strpos( $home_ctx, "\$hero['mode'] = 'video_only';" ), 'Home context keeps video-only hero mode' );
home_must( false !== strpos( $editorial, '.gloskin-ui1-hero--video-only .gloskin-ui1-hero-bg-video__media{display:block;width:100%;height:100%;object-fit:cover;' ), 'Home video covers the hero without letterboxing' );
home_must( false !== strpos( $editorial, 'linear-gradient(135deg,rgba(168,28,49,.30) 0%,rgba(168,28,49,.12) 100%)' ), 'Home video retains the restrained rose tint' );

/* Awards mirror the reference infinite marquee: duplicated set, seamless 50% travel, edge fades and hover pause. */
foreach ( array( "['title'] ?? ''", "['meta']['issuer'] ?? ''", "['meta']['year'] ?? ''", 'gloskin-home-piagam__title', 'gloskin-home-piagam__icon', 'gloskin-home-piagam__meta' ) as $needle ) {
	home_must( false !== strpos( $home, $needle ), 'Piagam factual presentation missing: ' . $needle );
}
home_must( false !== strpos( $home, '$gloskin_home_piagam_loop < 2' ), 'Piagam duplicates the factual set exactly once for seamless looping' );
home_must( false !== strpos( $home, 'gloskin-home-piagam__marquee' ) && false !== strpos( $home, 'gloskin-home-piagam__track' ), 'Piagam marquee structure is present' );
home_must( false !== strpos( $editorial, '@keyframes gloskin-home-piagam-marquee' ), 'Piagam marquee keyframes exist' );
home_must( false !== strpos( $editorial, 'animation:gloskin-home-piagam-marquee 25s linear infinite' ), 'Piagam marquee runs continuously at the canonical desktop pace' );
home_must( false !== strpos( $editorial, 'translateX(calc(-50% - 15px))' ), 'Piagam desktop loop travels exactly one duplicated set plus half-gap' );
home_must( false !== strpos( $editorial, 'animation-play-state:paused' ), 'Piagam marquee pauses on interaction' );
home_must( false !== strpos( $editorial, '.gloskin-home-piagam__marquee::before,.gloskin-home-piagam__marquee::after' ), 'Piagam marquee has reference edge fades' );
home_must( false === strpos( $home, "wp_get_attachment_image( absint( \$gloskin_home_achievement['image_id'] )" ), 'Home does not render giant certificate images' );
foreach ( array( 'TOP CLINIC', 'INNOVATION', 'BEST SERVICE', 'Customer Choice', 'Brand Award' ) as $dummy ) {
	home_must( false === strpos( $home, $dummy ), 'reference dummy content must not become production fact: ' . $dummy );
}

/* Responsive contract: desktop 3-col Treatment, tablet 2-col, mobile 1-col; video and marquee have dedicated mobile geometry. */
foreach ( array(
	'.gloskin-home-treatments__grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:40px}',
	'@media (max-width:1024px){.gloskin-home-treatments__grid{grid-template-columns:repeat(2,minmax(0,1fr))}',
	'@media (max-width:768px){body.gloskin-ui1--home .gloskin-ui1-hero--video-only{min-height:62vh}',
	'.gloskin-home-treatments__grid{grid-template-columns:1fr}',
	'.gloskin-home-piagam__marquee::before,.gloskin-home-piagam__marquee::after{width:60px}',
	'@media (max-width:480px){body.gloskin-ui1--home .gloskin-ui1-hero--video-only{min-height:56vh}',
	'@media (prefers-reduced-motion:reduce)',
	'.gloskin-home-piagam__track{animation:none;will-change:auto}',
) as $needle ) {
	home_must( false !== strpos( $editorial, $needle ), 'Home responsive/reduced-motion primitive missing: ' . $needle );
}

/* Existing canonical data owners remain in TemplateService. */
home_must( false !== strpos( $home_ctx, '$this->curated_home_treatments()' ), 'Treatment data owner remains TemplateService' );
home_must( false !== strpos( $home_ctx, 'published_managed_records' ), 'testimonial/achievement data owner remains managed CPT records' );

/* Keep fallback behavior and release owners healthy. */
home_must( substr_count( $home, 'gloskin_ui1_render_empty_state(' ) >= 3, 'Home retains explicit empty states' );
home_must( preg_match( "/const VERSION = '([^']+)'/", $kernel, $kernel_version ) === 1, 'kernel version is readable' );
home_must( preg_match( '/Version:\s*([^\s]+)/', $bootstrap, $bootstrap_version ) === 1, 'plugin header version is readable' );
home_must( $kernel_version[1] === $bootstrap_version[1], 'release owners remain synchronized' );

echo "home-readiness-contract.php: OK (native video hero, 3 Treatments, testimonial slider, infinite responsive awards marquee)\n";
