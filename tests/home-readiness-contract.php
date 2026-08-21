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

/* Reference completeness: these are visible copy/structure requirements, not optional polish. */
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
home_must( false !== strpos( $home, "__( 'Piagam & Penghargaan'" ), 'Piagam heading matches reference' );

/* The shared heading helper already owns optional subtitles; Home must actually use it. */
home_must( false !== strpos( $helpers, 'function gloskin_ui1_render_section_heading( $title, $copy = \'\' )' ), 'shared section heading helper supports copy' );
home_must( substr_count( $home, 'gloskin_ui1_render_section_heading(' ) === 3, 'all three reference section headers use the shared heading helper' );

/* Reference cardinality: one three-card Treatment row, a bounded testimonial slider, five award cards. */
home_must( false !== strpos( $home, "array_slice( isset( \$gloskin_context['treatments'] ) && is_array( \$gloskin_context['treatments'] ) ? \$gloskin_context['treatments'] : array(), 0, 3 )" ), 'Home Treatment row is bounded to 3' );
home_must( false !== strpos( $home, 'array_slice( $gloskin_home_testimonials, 0, 3 )' ), 'Home testimonials remain bounded to 3' );
home_must( false !== strpos( $home, "array_slice( isset( \$gloskin_context['achievements'] ) && is_array( \$gloskin_context['achievements'] ) ? \$gloskin_context['achievements'] : array(), 0, 5 )" ), 'Home awards are bounded to 5' );

/* Short descriptions must not disappear just because gloskin_summary is blank. */
home_must( false !== strpos( $home, "['summary'] ?? ''" ) && false !== strpos( $home, "['excerpt'] ?? ''" ), 'Treatment card short-description fallback is present' );
home_must( false !== strpos( $home, "\$gloskin_home_treatment_card['summary'] = (string) \$gloskin_home_treatment_card['excerpt'];" ), 'Treatment excerpt falls back into the existing summary renderer' );

/* Piagam uses factual managed-CPT fields rather than image-only or dummy reference data. */
foreach ( array( "['title'] ?? ''", "['meta']['issuer'] ?? ''", "['meta']['year'] ?? ''", 'gloskin-home-piagam__title', 'gloskin-home-piagam__meta' ) as $needle ) {
	home_must( false !== strpos( $home, $needle ), 'Piagam factual presentation missing: ' . $needle );
}
foreach ( array( 'TOP CLINIC', 'INNOVATION', 'BEST SERVICE', 'Customer Choice', 'Brand Award' ) as $dummy ) {
	home_must( false === strpos( $home, $dummy ), 'reference dummy content must not become production fact: ' . $dummy );
}

/* Existing data ownership stays intact: WP-managed records and video-only Home hero. */
$home_ctx_start = strpos( $template, 'private function home_context()' );
$home_ctx_end   = strpos( $template, 'private function about_context()', $home_ctx_start );
$home_ctx       = false !== $home_ctx_start && false !== $home_ctx_end ? substr( $template, $home_ctx_start, $home_ctx_end - $home_ctx_start ) : '';
home_must( false !== strpos( $home_ctx, "\$hero['mode'] = 'video_only';" ), 'Home keeps video-only hero mode' );
home_must( false !== strpos( $home_ctx, '$this->curated_home_treatments()' ), 'Treatment data owner remains TemplateService' );
home_must( false !== strpos( $home_ctx, 'published_managed_records' ), 'testimonial/achievement data owner remains managed CPT records' );

/* Current editorial owner still supplies the reference journey primitives. */
foreach ( array(
	'.gloskin-home-why{background:var(--gloskin-brand-ivory)}',
	'.gloskin-home-treatments__grid{grid-template-columns:repeat(3,minmax(0,1fr))',
	'.gloskin-home-testimonials__slider{width:min(100%,900px);margin:0 auto}',
	'.gloskin-home-piagam__rail{display:flex;',
) as $needle ) {
	home_must( false !== strpos( $editorial, $needle ), 'Home editorial primitive missing: ' . $needle );
}

/* Keep fallback behavior and release owners healthy. */
home_must( substr_count( $home, 'gloskin_ui1_render_empty_state(' ) >= 3, 'Home retains explicit empty states' );
home_must( preg_match( "/const VERSION = '([^']+)'/", $kernel, $kernel_version ) === 1, 'kernel version is readable' );
home_must( preg_match( '/Version:\s*([^\s]+)/', $bootstrap, $bootstrap_version ) === 1, 'plugin header version is readable' );
home_must( $kernel_version[1] === $bootstrap_version[1], 'release owners remain synchronized' );

echo "home-readiness-contract.php: OK (canonical reference copy, 3 Treatments, factual short descriptions, testimonials, 5 awards)\n";
