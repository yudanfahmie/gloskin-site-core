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
$guard     = home_text( $plugin . '/includes/class-gloskin-site-core-home-readiness-contract.php' );
$finalizer = home_text( $plugin . '/includes/class-gloskin-site-core-content-finalizer-admin.php' );
$kernel    = home_text( $plugin . '/includes/class-gloskin-site-core-kernel.php' );
$bootstrap = home_text( $plugin . '/gloskin-site-core.php' );
$css       = home_text( $plugin . '/assets/css/gloskin-ui1-editorial.css' );

/* Final Home order: Hero -> Why -> Treatments -> Testimonials -> Piagam. */
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
home_must( 1 === substr_count( $home, 'data-gloskin-section="achievements"' ), 'Piagam is the final unique Home content section' );
home_must( strpos( substr( $home, $last ), '<section' ) === false, 'nothing renders after Piagam inside Home template' );

/* Presentation remains scoped: no broad section padding ownership. */
home_must(
	false !== strpos( $css, '.gloskin-home-why,.gloskin-home-treatments,.gloskin-home-testimonials,.gloskin-home-piagam{padding-block:clamp(3rem,6vw,5.5rem)}' ),
	'four Home roots own restored vertical rhythm'
);
home_must( false === strpos( $css, '.gloskin-ui1-section{padding' ), 'no global section padding hotfix' );
home_must( false !== strpos( $css, '.gloskin-home-treatments__grid{grid-template-columns:repeat(3,minmax(0,1fr))}' ), 'Treatments remain 3-column desktop' );

/* Frontend cardinality and factual Testimonial rendering. */
home_must( false !== strpos( $home, 'array_slice( $gloskin_home_treatments, 0, 6 )' ), 'Home renders at most 6 Treatments' );
home_must( false !== strpos( $home, "array(), 0, 3 );" ), 'Home renders at most 3 Testimonials' );
home_must( false !== strpos( $home, "array(), 0, 4 );" ), 'Home renders at most 4 Piagam' );
home_must( false !== strpos( $home, "trim( (string) ( \$gloskin_home_testimonial['excerpt'] ?? '' ) )" ), 'Testimonial quote comes from factual excerpt' );
home_must( false === strpos( $home, "\$gloskin_home_testimonial['title'] ?? ''" ), 'Testimonial title is never substituted as quote' );
home_must( false === strpos( $home, 'carousel' ) && false === strpos( $home, 'gloskin-promo' ), 'no Testimonial carousel or Promo reintroduced' );

/* TemplateService still gives Home one canonical deterministic treatment owner. */
$curated_start = strpos( $template, 'private function curated_home_treatments()' );
$curated_end   = strpos( $template, 'private function skincare_category_context()', $curated_start );
home_must( false !== $curated_start && false !== $curated_end, 'curated Home Treatment owner exists' );
$curated = substr( $template, $curated_start, $curated_end - $curated_start );
home_must( false !== strpos( $curated, "'posts_per_page' => 3" ), 'featured set capped at 3' );
home_must( false !== strpos( $curated, "'gloskin_treatment_feature_on_home'" ), 'feature flag is canonical' );
home_must( false !== strpos( $curated, "'post__not_in'   => \$exclude" ), 'additional set excludes featured IDs' );
home_must( false !== strpos( $curated, "'orderby'        => 'title'" ), 'additional selection is deterministic' );
home_must( false !== strpos( $curated, 'array_slice( $cards, 0, 6 )' ), 'frontend collection capped at 6' );

/* Existing Finalizer state is guarded fail-closed; no second runner/state. */
home_must( false !== strpos( $guard, "const STATE_OPTION = 'gloskin_site_core_phase4_finalizer_v1_state'" ), 'guard reuses Content Finalizer state' );
home_must( false !== strpos( $guard, 'pre_update_option_' ) && false !== strpos( $guard, 'guard_completion' ), 'complete transition is guarded' );
home_must( false !== strpos( $guard, "3 !== count( \$featured )" ) && false !== strpos( $guard, "6 !== count( \$selected_ids )" ), 'Treatment readiness is exactly 3 featured + 3 additional' );
home_must( false !== strpos( $guard, "3 !== count( \$posts )" ) && false !== strpos( $guard, 'gloskin_testimonial_active' ) && false !== strpos( $guard, 'gloskin_testimonial_attribution' ), 'Testimonial readiness is exactly 3 active factual records' );
home_must( false !== strpos( $guard, "4 !== count( \$posts )" ) && false !== strpos( $guard, 'gloskin_achievement_active' ) && false !== strpos( $guard, 'gloskin_achievement_feature_on_home' ) && false !== strpos( $guard, 'attachment_is_usable_image' ), 'Piagam readiness is exactly 4 active featured usable images' );
home_must( false !== strpos( $guard, "\$quote       = trim( wp_strip_all_tags( (string) get_the_excerpt( \$post ) ) );" ), 'guard requires real Testimonial quote content' );
home_must( false === strpos( $guard, 'get_the_title( $post )' ), 'guard never treats Testimonial title as quote' );
foreach ( array( 'wp_insert_post', 'wp_update_post', 'wp_trash_post', 'wp_delete_post', 'wp_delete_attachment', 'wp_delete_file' ) as $forbidden ) {
	home_must( false === strpos( $guard, $forbidden ), 'guard remains verification-only: ' . $forbidden );
}
home_must( false === strpos( $guard, 'admin_post_' ) && false === strpos( $guard, 'add_submenu_page' ), 'no second Finalizer runner/admin UI' );
home_must( false !== strpos( $finalizer, 'catch ( Throwable $e )' ) && false !== strpos( $finalizer, "['status']     = 'failed'" ), 'readiness exception lands in existing failed-state path' );

/* Kernel wiring and one release bump. */
home_must( false !== strpos( $kernel, 'class-gloskin-site-core-home-readiness-contract.php' ) && false !== strpos( $kernel, 'Gloskin_Site_Core_Home_Readiness_Contract' ), 'Home readiness guard is wired once' );
home_must( false !== strpos( $kernel, "const VERSION = '0.7.188'" ) && false !== strpos( $bootstrap, 'Version: 0.7.188' ), 'release owners are synchronized at 0.7.188' );

echo "home-readiness-contract.php: OK (Home order/rhythm, Treatments 6, Testimoni 3 factual, Piagam 4 usable, fail-closed Finalizer, version 0.7.188)\n";
