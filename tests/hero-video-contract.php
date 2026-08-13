<?php
declare(strict_types=1);

/**
 * Focused contract for the admin-configurable Home hero video: settings
 * schema/defaults, the pure YouTube-ID resolver, and the performance-
 * first poster/facade renderer (never an initial-render iframe).
 */

define( 'ABSPATH', __DIR__ . '/' );

function ok( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES ); }
// Mirrors the one relevant real behavior of WordPress core's esc_url_raw()
// this test needs: angle brackets/quotes (the only way raw HTML could ride
// along in a "URL" field) are always stripped.
function esc_url_raw( $url ) { return str_replace( array( '<', '>', '"', "'" ), '', (string) $url ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function __( $text, $domain = 'default' ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_html_class( $value, $fallback = '' ) {
	$sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value );
	return '' !== $sanitized ? $sanitized : $fallback;
}
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) { return '<img class="gloskin-ui1-card__image" src="stub.jpg" alt="">'; }
function wp_kses_post( $html ) { return (string) $html; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_trim_words( $text, $words = 55 ) { return (string) $text; }

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/templates/parts/template-helpers.php';
require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php';

// -----------------------------------------------------------------------
// 1. Defaults: enabled=true, and the exact supplied default URL.
// -----------------------------------------------------------------------
$defaults = Gloskin_Site_Core_Admin_Service::settings_defaults();
ok( true === $defaults['hero_video_enabled'], 'default hero_video_enabled must be true' );
ok( 'https://www.youtube.com/watch?v=otej7WLdPh0&pp=ygUPc2tpbmNhcmUgdGVhc2Vy' === $defaults['hero_video_url'], 'default hero_video_url must match the exact supplied URL' );

// -----------------------------------------------------------------------
// 2/3. resolve_youtube_video_id(): the four supported shapes all resolve
// the SAME id; non-YouTube/malformed URLs are rejected to ''.
// -----------------------------------------------------------------------
$expected_id = 'otej7WLdPh0';
foreach ( array(
	'https://www.youtube.com/watch?v=otej7WLdPh0&pp=ygUPc2tpbmNhcmUgdGVhc2Vy',
	'https://youtu.be/otej7WLdPh0',
	'https://www.youtube.com/embed/otej7WLdPh0',
	'https://www.youtube.com/shorts/otej7WLdPh0',
	'http://youtube.com/watch?v=otej7WLdPh0',
) as $url ) {
	ok( $expected_id === Gloskin_Site_Core_Admin_Service::resolve_youtube_video_id( $url ), "must resolve the canonical id from: {$url}" );
}
foreach ( array(
	'https://evil.com/watch?v=otej7WLdPh0',
	'not a url at all',
	'',
	'javascript:alert(1)',
	'https://www.youtube.com/watch?v=short',
	'<iframe src="https://www.youtube.com/embed/otej7WLdPh0"></iframe>',
) as $url ) {
	ok( '' === Gloskin_Site_Core_Admin_Service::resolve_youtube_video_id( $url ), "must reject (never resolve an id from): {$url}" );
}

// -----------------------------------------------------------------------
// 4. sanitize_settings(): still the one existing settings owner/option,
// stores the URL as plain text (never trusts HTML), preserves enabled state.
// -----------------------------------------------------------------------
$admin = new Gloskin_Site_Core_Admin_Service( null, null, '' );
$sanitized_on = $admin->sanitize_settings( array( 'hero_video_enabled' => '1', 'hero_video_url' => 'https://youtu.be/otej7WLdPh0' ) );
ok( true === $sanitized_on['hero_video_enabled'], 'sanitize_settings must persist an enabled checkbox as true' );
ok( 'https://youtu.be/otej7WLdPh0' === $sanitized_on['hero_video_url'], 'sanitize_settings must persist a real URL' );
$sanitized_off = $admin->sanitize_settings( array( 'hero_video_enabled' => '', 'hero_video_url' => '' ) );
ok( false === $sanitized_off['hero_video_enabled'], 'sanitize_settings must persist an unchecked checkbox as false' );
$sanitized_html = $admin->sanitize_settings( array( 'hero_video_enabled' => '1', 'hero_video_url' => '<iframe src="evil"></iframe>' ) );
ok( strpos( $sanitized_html['hero_video_url'], '<iframe' ) === false, 'sanitize_settings must never persist raw iframe/HTML in the stored URL' );

// -----------------------------------------------------------------------
// 5. Initial render facade: NO iframe, real <button>, poster with maxres +
// hqdefault fallback, validated data-video-id -- this is the one and only
// canonical Home hero media slot, not a duplicate hero section.
// -----------------------------------------------------------------------
ob_start();
gloskin_ui1_render_hero( array(
	'heading' => 'Perawatan kulit',
	'copy' => 'Copy',
	'cta_label' => '',
	'cta_url' => '',
	'media_id' => 0,
	'video_enabled' => true,
	'video_id' => 'otej7WLdPh0',
) );
$video_html = (string) ob_get_clean();
ok( substr_count( $video_html, '<section class="gloskin-ui1-hero">' ) === 1, 'must still render exactly one hero section' );
ok( strpos( $video_html, 'data-gloskin-hero-video' ) !== false, 'facade root must carry the documented data attribute' );
ok( strpos( $video_html, 'data-video-id="otej7WLdPh0"' ) !== false, 'facade must carry the validated video id' );
ok( strpos( $video_html, '<iframe' ) === false, 'initial server-rendered HTML must NEVER contain an iframe' );
ok( strpos( $video_html, '<button type="button" class="gloskin-ui1-hero-video__play"' ) !== false, 'facade must use a real <button>, not a clickable div' );
ok( strpos( $video_html, 'aria-label=' ) !== false, 'Play control must carry an accessible label' );
ok( strpos( $video_html, 'maxresdefault.jpg' ) !== false, 'poster must prefer the maxres thumbnail' );
ok( strpos( $video_html, 'data-gloskin-hero-video-fallback=' ) !== false && strpos( $video_html, 'hqdefault.jpg' ) !== false, 'poster must carry a real hqdefault fallback' );
ok( strpos( $video_html, 'youtube.com/embed' ) === false && strpos( $video_html, 'youtube-nocookie' ) === false, 'initial render must never reference any embed domain at all -- only the JS enhancer does, later' );

// -----------------------------------------------------------------------
// 6. Disabled / invalid fallback: the existing non-video hero media
// behavior must return cleanly, never a broken/empty facade.
// -----------------------------------------------------------------------
ob_start();
gloskin_ui1_render_hero( array( 'heading' => 'H', 'media_id' => 0, 'video_enabled' => false, 'video_id' => 'otej7WLdPh0' ) );
$disabled_html = (string) ob_get_clean();
ok( strpos( $disabled_html, 'data-gloskin-hero-video' ) === false, 'disabled hero video must fall back to the existing non-video media, never render the facade' );
ok( strpos( $disabled_html, 'gloskin-ui1-editorial-image' ) !== false, 'disabled hero video must still render the existing editorial-media fallback' );

ob_start();
gloskin_ui1_render_hero( array( 'heading' => 'H', 'media_id' => 0, 'video_enabled' => true, 'video_id' => '' ) );
$empty_id_html = (string) ob_get_clean();
ok( strpos( $empty_id_html, 'data-gloskin-hero-video' ) === false, 'an unresolvable/empty video id must fall back cleanly, never render a broken facade' );

// -----------------------------------------------------------------------
// 7. Architecture guards: no new option, no new service, existing owners
// only.
// -----------------------------------------------------------------------
$admin_src = file_get_contents( dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php' );
ok( substr_count( $admin_src, 'register_setting(' ) === 1, 'must not register a second settings option -- still the one gloskin_site_core_settings owner' );
ok( strpos( $admin_src, "'hero_video_enabled'" ) !== false && strpos( $admin_src, "'hero_video_url'" ) !== false, 'Hero Video settings must live inside the existing settings schema' );
ok( strpos( $admin_src, "id=\"gloskin-admin-tab-hero\"" ) !== false || strpos( $admin_src, 'gloskin-admin-tab-hero' ) !== false, 'Hero tab must be added to the existing settings tab shell' );

foreach ( glob( dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-*.php' ) as $file ) {
	$base = basename( $file );
	if ( preg_match( '/hero|video|youtube/i', $base ) ) {
		fwrite( STDERR, "FAIL: a new dedicated Hero/Video service file was introduced: {$base}\n" );
		exit( 1 );
	}
}

$asset_src = file_get_contents( dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php' );
ok( strpos( $asset_src, 'function print_hero_video_preconnect' ) !== false, 'Asset Service must be the one extended for hero-video performance (preconnect)' );
ok( strpos( $asset_src, 'youtube-nocookie.com' ) !== false, 'preconnect must target the actual privacy-enhanced embed origin' );

$template_src = file_get_contents( dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php' );
ok( strpos( $template_src, 'function hero_video()' ) !== false, 'Template Service must own the frontend hero-video resolution' );
ok( strpos( $template_src, "require_once __DIR__ . '/class-gloskin-site-core-admin-service.php';" ) !== false, 'frontend hero-video resolution must explicitly load its Admin Service dependency (Kernel is_admin() path never does)' );

// Real staging bug, fixed: get_option()'s own $default is only ever used
// when the option row is entirely absent, never merged key-by-key into an
// existing stored value -- an existing site's already-saved settings
// option (genuinely missing the two new hero_video_* keys) must still
// resolve to the documented recommended defaults (enabled=true, the
// supplied URL), not silently read as disabled/blank.
$hero_video_fn = substr( $template_src, strpos( $template_src, 'private function hero_video()' ) );
$hero_video_fn = substr( $hero_video_fn, 0, strpos( $hero_video_fn, "\n\t}\n" ) );
ok( strpos( $hero_video_fn, 'array_merge(' ) !== false, 'hero_video() must merge missing keys against settings_defaults(), never trust get_option()\'s own unused $default on an existing option' );
ok( strpos( $hero_video_fn, "empty( \$settings['hero_video_enabled'] )" ) !== false, 'hero_video() must read the already-merged settings array, not fall back to a hand-written default inline' );

$admin_settings_fn = substr( $admin_src, strpos( $admin_src, 'function render_settings_page()' ) );
$admin_settings_fn = substr( $admin_settings_fn, 0, strpos( $admin_settings_fn, 'render_header_variant_card(' ) );
ok( strpos( $admin_settings_fn, 'array_merge( $defaults, get_option(' ) !== false, 'the Settings screen must also merge missing keys against settings_defaults(), for the exact same reason' );

// -----------------------------------------------------------------------
// 8. JS static guards: one canonical initializer, youtube-nocookie only.
// -----------------------------------------------------------------------
$core_js = file_get_contents( dirname( __DIR__ ) . '/plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js' );
ok( substr_count( $core_js, 'function initHeroVideo()' ) === 1, 'must define exactly one canonical Hero Video initializer' );
ok( substr_count( $core_js, 'initHeroVideo();' ) === 1, 'initHeroVideo() must be called exactly once, from the one existing init() bootstrap' );
ok( strpos( $core_js, 'youtube-nocookie.com' ) !== false, 'JS embed must use the privacy-enhanced domain' );
ok( strpos( $core_js, "'youtube.com/embed" ) === false, 'JS must never build a tracking youtube.com/embed URL' );
ok( strpos( $core_js, 'setInterval' ) === false || strpos( substr( $core_js, strpos( $core_js, 'Hero Video' ) ), 'setInterval' ) === false, 'Hero Video section must never use setInterval/polling' );
ok( strpos( $core_js, 'IntersectionObserver' ) !== false, 'must use IntersectionObserver for visibility-based enhancement, not scroll polling' );

echo "hero-video-contract: OK\n";
