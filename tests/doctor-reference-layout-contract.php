<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$template_path = $root . '/plugin/gloskin-site-core/templates/pages/doctor.php';
$style_path = $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-doctor-single.css';
$assets_path = $root . '/plugin/gloskin-site-core/config/assets.php';
$footer_path = $root . '/plugin/gloskin-site-core/templates/parts/footer.php';

$template = file_get_contents( $template_path );
$style = file_get_contents( $style_path );
$assets = file_get_contents( $assets_path );
$footer = file_get_contents( $footer_path );
if ( false === $template || false === $style || false === $assets || false === $footer ) {
	fwrite( STDERR, "Unable to read single Doctor contract owners\n" );
	exit( 1 );
}

$sections = array(
	'data-gloskin-section="doctor-hero"',
	'data-gloskin-section="doctor-professional-detail"',
	'data-gloskin-section="doctor-consultation-transition"',
);
$last = -1;
foreach ( $sections as $section ) {
	$pos = strpos( $template, $section );
	if ( false === $pos || $pos <= $last ) {
		fwrite( STDERR, "Single Doctor reference section missing or out of order: {$section}\n" );
		exit( 1 );
	}
	$last = $pos;
}

foreach ( array(
	'degree_title', 'specialization', 'profile', 'credentials', 'sip_number',
	'schedule', 'image_id', 'branches', 'treatments', 'booking_target',
) as $dynamic_key ) {
	if ( false === strpos( $template, $dynamic_key ) ) {
		fwrite( STDERR, "Single Doctor lost dynamic context key: {$dynamic_key}\n" );
		exit( 1 );
	}
}

foreach ( array(
	'wp_get_attachment_image',
	'gloskin_ui1_render_page_content',
	'gloskin_ui1_render_editorial_media',
	'gloskin_ui1_arrow_icon',
	'gloskin-doctor-single__hero-grid',
	'gloskin-doctor-single__detail-grid',
	'gloskin-doctor-single__transition-inner',
) as $needle ) {
	if ( false === strpos( $template, $needle ) ) {
		fwrite( STDERR, "Single Doctor canonical owner missing: {$needle}\n" );
		exit( 1 );
	}
}

foreach ( array(
	'images.unsplash.com', 'unsplash.com', 'fontawesome', 'Playfair',
	'gloskin-ui1-detail-hero', 'data-gloskin-section="doctor-facts"',
	'data-gloskin-section="doctor-branches"', 'data-gloskin-section="doctor-treatments"',
	'data-gloskin-section="doctor-closing"', 'IntersectionObserver',
) as $retired ) {
	if ( false !== stripos( $template, $retired ) ) {
		fwrite( STDERR, "Single Doctor still contains retired/reference-only owner: {$retired}\n" );
		exit( 1 );
	}
}

foreach ( array(
	'.gloskin-doctor-single__hero-grid',
	'aspect-ratio:4/5',
	'.gloskin-doctor-single__detail-grid',
	'aspect-ratio:16/10',
	'.gloskin-doctor-single__transition',
	'grid-template-areas:"media" "copy"',
	'@media (max-width:768px)',
	'@media (prefers-reduced-motion:reduce)',
) as $css_contract ) {
	if ( false === strpos( $style, $css_contract ) ) {
		fwrite( STDERR, "Single Doctor CSS contract missing: {$css_contract}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $assets, "'gloskin-ui1-doctor-single'" ) || false === strpos( $assets, "assets/css/gloskin-ui1-doctor-single.css" ) ) {
	fwrite( STDERR, "Single Doctor stylesheet is not registered through the existing asset registry\n" );
	exit( 1 );
}
if ( false === strpos( $footer, 'gloskin-ui1-dark-consultation gloskin-ui1-footer__cta' ) ) {
	fwrite( STDERR, "Shared footer dark consultation CTA owner missing\n" );
	exit( 1 );
}
if ( false !== strpos( $template, 'gloskin-ui1-dark-consultation__inner' ) ) {
	fwrite( STDERR, "Single Doctor duplicates the shared footer dark CTA\n" );
	exit( 1 );
}

echo "doctor-reference-layout-contract.php: OK\n";
