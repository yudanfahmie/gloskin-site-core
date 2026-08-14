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
function wp_get_attachment_image() { return '<img class="gloskin-ui1-hero__image" src="stub.jpg" alt="">'; }
function wp_kses_post( $html ) { return (string) $html; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_trim_words( $text ) { return (string) $text; }

$GLOBALS['hero_settings'] = array( 'hero_video_media_id' => 7 );
$GLOBALS['hero_url'] = 'https://example.test/hero.mp4';
$GLOBALS['hero_mime'] = 'video/mp4';
function get_option() { return $GLOBALS['hero_settings']; }
function wp_get_attachment_url() { return $GLOBALS['hero_url']; }
function get_post_mime_type() { return $GLOBALS['hero_mime']; }

class Gloskin_Site_Core_Form_Adapter {
	const SETTINGS_OPTION = 'gloskin_site_core_settings';
}

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/templates/parts/template-helpers.php';
require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php';
require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php';

$defaults = Gloskin_Site_Core_Admin_Service::settings_defaults();
ok( array_key_exists( 'hero_video_media_id', $defaults ), 'native Media Library attachment setting must remain' );
ok( ! array_key_exists( 'hero_video_enabled', $defaults ) && ! array_key_exists( 'hero_video_url', $defaults ), 'legacy YouTube settings must be retired' );
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

ob_start();
gloskin_ui1_render_hero( array(
	'heading' => 'Perawatan kulit',
	'mode' => 'video-only',
	'sources' => array( array( 'src' => 'https://example.test/hero.mp4', 'type' => 'video/mp4' ) ),
) );
$html = (string) ob_get_clean();
ok( 1 === substr_count( $html, '<video ' ), 'Home must render exactly one native video' );
foreach ( array( ' muted', ' autoplay', ' loop', ' playsinline', 'preload="auto"' ) as $attribute ) {
	ok( false !== strpos( $html, $attribute ), "native video missing {$attribute}" );
}
foreach ( array( '<iframe', ' controls', ' poster=', 'data-gloskin-hero-video-play' ) as $forbidden ) {
	ok( false === strpos( $html, $forbidden ), "native Home must not render {$forbidden}" );
}
ok( 1 === substr_count( $html, 'data-gloskin-hero-scroll-cue' ), 'Home must render exactly one scroll cue' );
ok( false !== strpos( $html, 'gloskin-ui1-hero__fade' ), 'bottom gradient owner must remain' );

foreach (
	array(
		'empty sources with media' => array( 'heading' => 'Perawatan kulit', 'mode' => 'video-only', 'sources' => array(), 'media_id' => 88 ),
		'empty sources without media' => array( 'heading' => 'Perawatan kulit', 'mode' => 'video-only', 'sources' => array() ),
		'unsupported source' => array( 'heading' => 'Perawatan kulit', 'mode' => 'video-only', 'sources' => array( array( 'src' => 'https://example.test/hero.mov', 'type' => 'video/quicktime' ) ), 'media_id' => 88 ),
	) as $label => $fixture
) {
	ob_start();
	gloskin_ui1_render_hero( $fixture );
	$empty_html = (string) ob_get_clean();
	ok( false !== strpos( $empty_html, 'is-video-unavailable' ), "{$label}: clean unavailable state missing" );
	foreach ( array( '<video ', '<img ', 'data-gloskin-editorial=', 'gloskin-ui1-hero__media--full', 'data-gloskin-hero-bg-video-wrap' ) as $forbidden ) {
		ok( false === strpos( $empty_html, $forbidden ), "{$label}: strict video-only rendered {$forbidden}" );
	}
	ok( 1 === substr_count( $empty_html, 'data-gloskin-hero-scroll-cue' ), "{$label}: scroll cue must remain" );
}

$root = dirname( __DIR__ );
$admin = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php' );
$asset = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php' );
$template = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php' );
$helpers = file_get_contents( $root . '/plugin/gloskin-site-core/templates/parts/template-helpers.php' );
$js = file_get_contents( $root . '/plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js' );
$css = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-production.css' );
$core_css = file_get_contents( $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css' );
$production = $admin . $asset . $template . $helpers . $js;
foreach ( array( 'youtube.com', 'youtu.be', 'youtube-nocookie.com', 'print_hero_video_preconnect', 'gloskin_ui1_render_hero_video', 'function hero_video()', 'buildHeroVideoEmbedUrl', 'enhanceHeroVideo', 'initHeroVideo' ) as $forbidden ) {
	ok( false === stripos( $production, $forbidden ), "retired production path remains: {$forbidden}" );
}
foreach ( array( 'gloskin-ui1-hero-video__poster', 'gloskin-ui1-hero-video__iframe', 'gloskin-ui1-hero-video__play' ) as $forbidden ) {
	ok( false === strpos( $core_css, $forbidden ), "obsolete legacy player CSS remains: {$forbidden}" );
}
ok( false !== strpos( $css, 'object-fit:cover' ) && false === strpos( substr( $css, strpos( $css, '.gloskin-ui1-hero--video-only' ), 2500 ), 'object-fit:contain' ), 'Home native media must use cover, never contain' );
$video_css = substr( $css, strpos( $css, '/* Home strict video-only mode' ), 3000 );
foreach ( array( 'object-fit:contain', 'background:#000', 'gloskin-ui1-hero__media--full' ) as $forbidden ) {
	ok( false === strpos( $video_css, $forbidden ), "Home strict video CSS must exclude {$forbidden}" );
}
ok( false !== strpos( $video_css, 'is-video-unavailable' ) && false !== strpos( $video_css, 'is-video-failed{background:#fff}' ), 'unavailable and failure outcomes must stay white' );
foreach ( array( '.gloskin-ui1-hero-bg-video', '.gloskin-ui1-hero-bg-video__media', '.gloskin-ui1-hero-bg-video__loader' ) as $selector ) {
	ok( preg_match( '/' . preg_quote( $selector, '/' ) . '[^{,]*[,{][^}]*pointer-events:none/s', $css ) || false !== strpos( $css, $selector . '{pointer-events:none}' ), "{$selector} must be pointerless" );
}
ok( false !== strpos( $js, 'video.readyState >= 2' ), 'controller must reconcile current readyState' );
ok( false !== strpos( $js, '!video.paused && video.readyState >= 2' ), 'controller must reconcile already-playing media' );
$controller = substr( $js, strpos( $js, 'function setupHeroBackgroundVideo' ), strpos( $js, 'function initHeroBackgroundVideo' ) - strpos( $js, 'function setupHeroBackgroundVideo' ) );
foreach ( array( 'video.muted = true', 'video.defaultMuted = true', 'video.autoplay = true', 'video.loop = true', 'video.playsInline = true' ) as $property ) {
	ok( false !== strpos( $controller, $property ), "controller must enforce {$property} before play" );
}
ok( 1 === substr_count( $controller, 'video.play()' ), 'controller must attempt play at most once' );
ok( false === strpos( $js, 'setInterval' ), 'hero controller must not poll' );
foreach ( array( "addEventListener('ended'", 'addEventListener("ended"' ) as $ended_listener ) {
	ok( false === strpos( $controller, $ended_listener ), 'hero controller must own zero ended-based replay listener -- native loop is the sole looping owner' );
}
ok( false !== strpos( $controller, 'video.loop = true' ), 'controller must retain native loop=true; it never owns looping itself' );

$video_branch_start = strpos( $helpers, 'if ( $video_only ) {' );
$video_branch_end = strpos( $helpers, '<section class="gloskin-ui1-hero">', $video_branch_start );
$video_branch = substr( $helpers, $video_branch_start, $video_branch_end - $video_branch_start );
foreach ( array( 'wp_get_attachment_image', 'gloskin_ui1_render_editorial_media', 'gloskin-ui1-hero__media--full', 'poster=', '<iframe', ' controls' ) as $forbidden ) {
	ok( false === strpos( $video_branch, $forbidden ), "video-only renderer branch must exclude {$forbidden}" );
}
$home_start = strpos( $template, 'private function home_context()' );
$home_end = strpos( $template, 'private function treatments_context()', $home_start );
$home_context = substr( $template, $home_start, $home_end - $home_start );
ok( false !== strpos( $home_context, '$hero[\'media_id\'] = 0;' ), 'Home context must neutralize featured-image fallback data' );
ok( false !== strpos( $admin, 'Belum ada video dipilih. Home akan menggunakan latar putih sampai video MP4/WebM dipilih.' ), 'Settings must explain the white no-video state' );
ok( false !== strpos( $admin, 'File yang dipilih bukan MP4/WebM. Home akan tetap menggunakan latar putih.' ), 'Settings must warn about unsupported MIME' );

echo "hero-video-contract: OK\n";
