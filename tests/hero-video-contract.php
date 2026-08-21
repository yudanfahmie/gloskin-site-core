<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

function ok( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}
function __( $text, $domain = 'default' ) { return $text; }
function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function esc_attr__( $text, $domain = 'default' ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_html_class( $value, $fallback = '' ) { return (string) $value ?: $fallback; }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\' ); }
function wp_get_attachment_image( $id = 0 ) { return '<img class="gloskin-ui1-hero__image" src="stub.jpg" alt="">'; }
function wp_kses_post( $html ) { return (string) $html; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_trim_words( $text ) { return (string) $text; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }

$GLOBALS['hero_settings'] = array( 'hero_video_media_id' => 7 );
$GLOBALS['hero_url'] = 'https://example.test/hero.mp4';
$GLOBALS['hero_mime'] = 'video/mp4';
function get_option() { return $GLOBALS['hero_settings']; }
function wp_get_attachment_url() { return $GLOBALS['hero_url']; }
function get_post_mime_type() { return $GLOBALS['hero_mime']; }

class Gloskin_Site_Core_Form_Adapter { const SETTINGS_OPTION = 'gloskin_site_core_settings'; }

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/templates/parts/template-helpers.php';
require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php';
require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php';

$defaults = Gloskin_Site_Core_Admin_Service::settings_defaults();
ok( array_key_exists( 'hero_video_media_id', $defaults ), 'native Media Library attachment setting must remain' );
ok( ! array_key_exists( 'hero_video_enabled', $defaults ) && ! array_key_exists( 'hero_video_url', $defaults ), 'legacy/second video settings must remain retired' );
$sanitized = ( new Gloskin_Site_Core_Admin_Service( null ) )->sanitize_settings( array( 'hero_video_media_id' => '17', 'hero_video_url' => 'https://youtube.test' ) );
ok( 17 === $sanitized['hero_video_media_id'], 'attachment ID must sanitize as an integer' );
ok( ! array_key_exists( 'hero_video_url', $sanitized ), 'removed legacy keys must be ignored' );

$service = new Gloskin_Site_Core_Template_Service( __DIR__, null, null, null );
$resolver = new ReflectionMethod( $service, 'hero_background_video' );
$resolver->setAccessible( true );
foreach ( array( 'video/mp4', 'video/webm' ) as $mime ) {
	$GLOBALS['hero_mime'] = $mime;
	$GLOBALS['hero_url'] = 'https://example.test/hero.' . ( 'video/mp4' === $mime ? 'mp4' : 'webm' );
	$result = $resolver->invoke( $service );
	ok( 1 === count( $result['sources'] ) && $mime === $result['sources'][0]['type'], "{$mime} must be accepted" );
}
$GLOBALS['hero_mime'] = 'video/quicktime';
ok( array() === $resolver->invoke( $service )['sources'], 'unsupported video MIME must be rejected' );
$GLOBALS['hero_mime'] = 'image/jpeg';
ok( array() === $resolver->invoke( $service )['sources'], 'non-video MIME must be rejected' );

$video_only = array(
	'heading' => 'Perawatan kulit',
	'copy' => 'Konsultasi dan perawatan yang disesuaikan.',
	'cta_label' => 'Jelajahi Perawatan',
	'cta_url' => 'https://example.test/treatments/',
	'mode' => 'video_only',
	'media_id' => 88,
	'sources' => array( array( 'src' => 'https://example.test/hero.mp4', 'type' => 'video/mp4' ) ),
);
ob_start(); gloskin_ui1_render_hero( $video_only ); $html = (string) ob_get_clean();
ok( 1 === substr_count( $html, '<section class="gloskin-ui1-hero gloskin-ui1-hero--video-only' ), 'Home must render exactly one video-only hero section' );
ok( 1 === substr_count( $html, '<video ' ), 'configured Home must render exactly one native video' );
ok( false === strpos( $html, '<h1' ) && false === strpos( $html, 'gloskin-ui1-hero__copy' ) && false === strpos( $html, 'gloskin-ui1-hero__actions' ), 'video-only Home must not overlay campaign text/CTA' );
ok( false === strpos( $html, '<iframe' ) && false === strpos( $html, ' controls' ) && false === strpos( $html, ' poster=' ), 'Home video must remain native, decorative and control-free' );
foreach ( array( ' muted', ' autoplay', ' loop', ' playsinline', 'preload="auto"' ) as $attribute ) {
	ok( false !== strpos( $html, $attribute ), "native video missing {$attribute}" );
}

$video_only['sources'] = array();
ob_start(); gloskin_ui1_render_hero( $video_only ); $unavailable = (string) ob_get_clean();
ok( false !== strpos( $unavailable, 'gloskin-ui1-hero--video-only is-video-unavailable' ), 'no-source Home keeps the same hero shell in unavailable state' );
ok( 0 === substr_count( $unavailable, '<video ' ), 'no-source Home must not fabricate a video' );

$root = dirname( __DIR__ );
$template  = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php' );
$helpers   = file_get_contents( $root . '/plugin/gloskin-site-core/templates/parts/template-helpers.php' );
$home      = file_get_contents( $root . '/plugin/gloskin-site-core/templates/pages/home.php' );
$editorial = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-editorial.css' );
$js        = file_get_contents( $root . '/plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js' );
$home_start = strpos( $template, 'private function home_context()' );
$home_end = strpos( $template, 'private function about_context()', $home_start );
$home_context = substr( $template, $home_start, $home_end - $home_start );
ok( false !== strpos( $home_context, "\$hero['mode'] = 'video_only';" ), 'Home context must use video-only mode' );
ok( false !== strpos( $home_context, 'hero_background_video()' ), 'Home context must keep native Media Library video resolver' );
ok( 1 === substr_count( $home, 'gloskin_ui1_render_hero(' ), 'Home template must own exactly one hero renderer call' );
ok( false !== strpos( $helpers, 'gloskin-ui1-hero--video-only' ), 'shared hero renderer must expose video-only path' );
ok( false !== strpos( $editorial, 'body.gloskin-ui1--home .gloskin-ui1-hero--video-only .gloskin-ui1-hero-bg-video__media{object-fit:cover;object-position:center center}' ), 'Home video-only hero must use cover geometry' );

$controller_start = strpos( $js, 'function setupHeroBackgroundVideo' );
$controller_end   = strpos( $js, 'function initHeroBackgroundVideo', $controller_start );
$controller       = false !== $controller_start && false !== $controller_end ? substr( $js, $controller_start, $controller_end - $controller_start ) : '';
foreach ( array( 'video.muted = true', 'video.defaultMuted = true', 'video.autoplay = true', 'video.loop = true', 'video.playsInline = true' ) as $property ) {
	ok( false !== strpos( $controller, $property ), "controller must enforce {$property} before play" );
}
ok( 1 === substr_count( $controller, 'video.play()' ), 'controller must attempt play at most once' );
ok( false === strpos( $js, 'setInterval' ), 'hero controller must not poll' );

echo "hero-video-contract: OK (video-only + cover)\n";
