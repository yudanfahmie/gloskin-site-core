<?php
declare(strict_types=1);

ob_start();
require __DIR__ . '/runtime-smoke.php';
ob_end_clean();

$view = getenv( 'GLOSKIN_FIXTURE_VIEW' ) ?: 'home';

// Asset-loading tests create an unrelated editorial post. It must never appear
// in client-presentation fixtures, because production provisioning does not seed it.
foreach ( $GLOBALS['gl_posts'] as $id => $post ) {
	if ( 'fixture-editorial-post' === $post->post_name ) {
		unset( $GLOBALS['gl_posts'][ $id ], $GLOBALS['gl_meta'][ $id ] );
	}
}

// Runtime sanitization tests populate synthetic gallery/map values on Kebayoran.
// Client-presentation fixtures intentionally mirror the canonical sparse staging state.
$fixture_clinic = get_page_by_path( 'kebayoran-baru', OBJECT, Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE );
if ( $fixture_clinic instanceof WP_Post ) {
	foreach ( array( 'gloskin_gallery_image_ids', 'gloskin_map_embed', 'gloskin_map_url' ) as $fixture_key ) {
		$GLOBALS['gl_meta'][ $fixture_clinic->ID ][ $fixture_key ] = 'gloskin_gallery_image_ids' === $fixture_key ? array() : '';
	}
	unset( $GLOBALS['gl_meta'][ $fixture_clinic->ID ]['_thumbnail_id'] );
}

// Keep presentation fixtures representative of production seed state. Runtime-only
// treatment/doctor fixtures remain available only for their dedicated detail tests.
if ( ! in_array( $view, array( 'treatment', 'doctor' ), true ) ) {
	foreach ( $GLOBALS['gl_posts'] as $id => $post ) {
		if ( in_array( $post->post_name, array( 'fixture-treatment', 'fixture-doctor' ), true ) ) {
			unset( $GLOBALS['gl_posts'][ $id ], $GLOBALS['gl_meta'][ $id ] );
		}
	}
	$home = get_page_by_path( 'home', OBJECT, 'page' );
	if ( $home instanceof WP_Post ) {
		$home->post_content = '';
	}
	$GLOBALS['gl_options'][ Gloskin_Site_Core_Form_Adapter::SETTINGS_OPTION ] = array( 'design_variant' => 'medical', 'form_shortcode' => '' );
}

// Optional test-only factual hero media proves that native attachment ownership
// remains higher priority than staging/editorial photography.
if ( '1' === getenv( 'GLOSKIN_FIXTURE_REAL_MEDIA' ) ) {
	$home = get_page_by_path( 'home', OBJECT, 'page' );
	if ( $home instanceof WP_Post ) {
		$attachment_id = wp_insert_post(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_title'  => 'Approved Fixture Media',
				'post_name'   => 'approved-fixture-media',
			),
			true
		);
		$GLOBALS['gl_meta'][ $home->ID ]['gloskin_hero_media_id'] = $attachment_id;
	}
}

$route = array( 'front' => false, 'page' => false, 'singular' => '', 'object' => null );

switch ( $view ) {
	case 'home':
		$route = array( 'front' => true, 'page' => true, 'singular' => '', 'object' => get_page_by_path( 'home', OBJECT, 'page' ) );
		break;
	case 'treatment':
		$route['singular'] = Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE;
		$route['object'] = get_page_by_path( 'fixture-treatment', OBJECT, Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE );
		break;
	case 'clinic':
		$route['singular'] = Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE;
		$route['object'] = get_page_by_path( 'kebayoran-baru', OBJECT, Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE );
		break;
	case 'doctor':
		$route['singular'] = Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE;
		$route['object'] = get_page_by_path( 'fixture-doctor', OBJECT, Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE );
		break;
	case 'skincare-category':
		$route['page'] = true;
		$route['object'] = get_page_by_path( 'skincare/facial-wash', OBJECT, 'page' );
		break;
	default:
		$route['page'] = true;
		$route['object'] = get_page_by_path( $view, OBJECT, 'page' );
		break;
}

if ( ! $route['object'] instanceof WP_Post ) {
	fwrite( STDERR, "Fixture route unavailable: {$view}\n" );
	exit( 1 );
}

$GLOBALS['gl_route'] = $route;
$template = apply_filters( 'template_include', '/theme/index.php' );

ob_start();
require $template;
$html = ob_get_clean();

if ( ! is_string( $html ) || false === strpos( $html, 'data-gloskin-drawer' ) ) {
	fwrite( STDERR, "Fixture render failed for {$view}\n" );
	exit( 1 );
}

// The runtime stub intentionally does not emulate WordPress image metadata. Give
// attachment output stable geometry only inside browser fixtures.
$html = str_replace(
	'<img alt="">',
	'<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" width="1200" height="800" alt="" data-test-wordpress-media="true">',
	$html
);

echo $html;
