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
$template  = home_text( $plugin . '/includes/class-gloskin-site-core-template-service.php' );
$content   = home_text( $plugin . '/includes/class-gloskin-site-core-content-service.php' );
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
home_must( 1 === substr_count( $home, 'data-gloskin-section="achievements"' ), 'Achievement shell is unique' );

/* Home still uses TemplateService's native managed video hero. */
home_must( false === strpos( $home, "['mode']    = 'home_reference'" ), 'Home must not override managed hero mode' );
$home_ctx_start = strpos( $template, 'private function home_context()' );
$home_ctx_end   = strpos( $template, 'private function about_context()', $home_ctx_start );
$home_ctx       = false !== $home_ctx_start && false !== $home_ctx_end ? substr( $template, $home_ctx_start, $home_ctx_end - $home_ctx_start ) : '';
home_must( false !== strpos( $home_ctx, '$this->hero_background_video()' ), 'Home context resolves native background video' );
home_must( false !== strpos( $home_ctx, "\$hero['mode'] = 'video_only';" ), 'Home context keeps video-only hero mode' );

/* Achievement eligibility belongs to the existing projection owner, not the template. */
$achievement_profile_start = strpos( $content, 'self::ACHIEVEMENT_POST_TYPE => array(' );
$achievement_profile_end   = false !== $achievement_profile_start ? strpos( $content, ');', $achievement_profile_start ) : false;
$achievement_profile       = false !== $achievement_profile_start && false !== $achievement_profile_end ? substr( $content, $achievement_profile_start, $achievement_profile_end - $achievement_profile_start ) : '';
home_must( false !== strpos( $achievement_profile, "'active_meta'      => 'gloskin_achievement_active'" ), 'Achievement profile owns active eligibility' );
home_must( false !== strpos( $achievement_profile, "'home_meta'        => 'gloskin_achievement_feature_on_home'" ), 'Achievement profile owns Home feature eligibility' );
home_must( false !== strpos( $achievement_profile, "'requires_image'   => true" ), 'Achievement profile declares image-required eligibility' );
home_must( false !== strpos( $template, 'if ( $requires_image )' ) && false !== strpos( $template, 'get_post_thumbnail_id( $post->ID ) ) > 0' ), 'TemplateService applies image-required eligibility before projection' );
home_must( false === strpos( $home, 'array_filter(' ), 'Home template does not own Achievement eligibility filtering' );

/* Achievement marquee remains pure-image semantic markup only. */
home_must( false !== strpos( $home, "['image_id']" ), 'Achievement image_id is the visual source' );
home_must( false !== strpos( $home, 'wp_get_attachment_image( $gloskin_home_achievement_image_id' ), 'Achievement item renders canonical attachment image' );
home_must( false !== strpos( $home, '<figure class="gloskin-home-piagam__item"' ), 'Achievement item uses pure figure markup' );
home_must( false !== strpos( $home, '$gloskin_home_piagam_loop < 2' ), 'Achievement set duplicates once for seamless marquee motion' );
home_must( false !== strpos( $home, 'aria-hidden="true"' ), 'duplicated marquee set remains hidden from accessibility APIs' );
home_must( false === strpos( $home, 'style=' ), 'Home Piagam static presentation is not inline-owned' );
home_must( false !== strpos( $editorial, '.gloskin-home-piagam__item{display:flex;flex:0 0 auto;width:min(72vw,320px);height:clamp(180px,18vw,230px);align-items:center;justify-content:center;margin:0}' ), 'editorial CSS owns Piagam item geometry' );
home_must( false !== strpos( $editorial, '.gloskin-home-piagam__image{display:block;width:auto;height:auto;max-width:100%;max-height:100%;object-fit:contain}' ), 'editorial CSS owns pure-image containment geometry' );

foreach ( array(
	'gloskin-home-piagam__title',
	'gloskin-home-piagam__meta',
	'gloskin-home-piagam__icon',
	'gloskin-home-piagam__card',
	"['meta']['issuer']",
	"['meta']['year']",
	'>G<',
) as $forbidden ) {
	home_must( false === strpos( $home, $forbidden ), 'Achievement runtime must not render legacy card/fallback content: ' . $forbidden );
	home_must( false === strpos( $editorial, $forbidden ), 'Achievement CSS must not retain dead card/fallback selector: ' . $forbidden );
}

/* Existing marquee mechanics and reduced-motion fallback remain one owner. */
home_must( false !== strpos( $home, 'gloskin-home-piagam__marquee' ) && false !== strpos( $home, 'gloskin-home-piagam__track' ), 'Achievement marquee structure remains' );
home_must( false !== strpos( $editorial, '@keyframes gloskin-home-piagam-marquee' ), 'Achievement marquee keyframes remain' );
home_must( false !== strpos( $editorial, 'animation:gloskin-home-piagam-marquee 25s linear infinite' ), 'Achievement marquee motion remains' );
home_must( false !== strpos( $editorial, '@media (prefers-reduced-motion:reduce)' ), 'reduced-motion owner remains' );
home_must( false !== strpos( $editorial, '.gloskin-home-piagam__track{animation:none;will-change:auto}' ), 'reduced-motion marquee fallback remains static/scrollable' );

/* No dummy award facts may leak from a reference. */
foreach ( array( 'TOP CLINIC', 'INNOVATION', 'BEST SERVICE', 'Customer Choice', 'Brand Award' ) as $dummy ) {
	home_must( false === strpos( $home, $dummy ), 'reference dummy content must not become production fact: ' . $dummy );
}

/* Release owners stay synchronized; closure intentionally does not force a bump. */
home_must( preg_match( "/const VERSION = '([^']+)'/", $kernel, $kernel_version ) === 1, 'kernel version is readable' );
home_must( preg_match( '/Version:\s*([^\s]+)/', $bootstrap, $bootstrap_version ) === 1, 'plugin header version is readable' );
home_must( $kernel_version[1] === $bootstrap_version[1], 'release owners remain synchronized' );

echo "home-readiness-contract.php: OK (native video hero, projection-owned pure-image Achievement marquee)\n";
