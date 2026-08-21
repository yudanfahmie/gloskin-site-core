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

$home        = home_text( $plugin . '/templates/pages/home.php' );
$promo       = home_text( $plugin . '/templates/pages/promo.php' );
$helpers     = home_text( $plugin . '/templates/parts/readiness-helpers.php' );
$media       = home_text( $plugin . '/templates/parts/template-helpers.php' );
$template    = home_text( $plugin . '/includes/class-gloskin-site-core-template-service.php' );
$finalizer   = home_text( $plugin . '/includes/class-gloskin-site-core-content-finalizer-admin.php' );
$kernel      = home_text( $plugin . '/includes/class-gloskin-site-core-kernel.php' );
$bootstrap   = home_text( $plugin . '/gloskin-site-core.php' );
$translation = home_text( $plugin . '/includes/class-gloskin-site-core-translation.php' );
$editorial   = home_text( $plugin . '/assets/css/gloskin-ui1-editorial.css' );
$core_base   = home_text( $plugin . '/assets/css/gloskin-ui1-core-base.css' );
$readiness   = home_text( $plugin . '/assets/css/gloskin-ui1-readiness.css' );
$assets      = home_text( $plugin . '/config/assets.php' );
$not_found   = home_text( $plugin . '/templates/pages/not-found.php' );
$footer      = home_text( $plugin . '/templates/parts/footer.php' );

/* One shared generic frontend empty-state renderer and one CSS owner. */
home_must( false !== strpos( $helpers, 'function gloskin_ui1_render_empty_state(' ), 'shared empty-state renderer exists' );
foreach ( array( 'gloskin-ui1-empty-state', 'gloskin-ui1-empty-state__title', 'gloskin-ui1-empty-state__copy', 'gloskin-ui1-empty-state__action' ) as $class ) {
	home_must( false !== strpos( $helpers, $class ), 'renderer owns canonical class ' . $class );
}
home_must( false !== strpos( $core_base, '.gloskin-ui1-empty-state{' ), 'generic empty-state CSS lives in shared core foundation' );
home_must( false === strpos( $readiness, '.gloskin-ui1-empty-state{' ), 'readiness layer no longer owns generic empty-state CSS' );
foreach ( array( 'empty-state.css', 'frontend-hotfix.css', 'final.css' ) as $forbidden_css ) {
	home_must( false === strpos( $assets, $forbidden_css ), 'no new stylesheet owner: ' . $forbidden_css );
}

/* Final Home structure is contractual even when collections are empty. */
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
home_must( 1 === substr_count( $home, 'data-gloskin-section="achievements"' ), 'Piagam shell is unique and final' );
home_must( false === strpos( substr( $home, $last ), '<section' ), 'nothing renders after Piagam inside Home template' );

/* Empty collections use the centralized fallback inside those persistent shells. */
home_must( substr_count( $home, 'gloskin_ui1_render_empty_state(' ) >= 3, 'Home has centralized fallbacks for its contractual dynamic sections' );
home_must( false !== strpos( $home, "gloskin_ui1_render_empty_state( 'generic', __( 'Testimoni'" ), 'Testimoni empty state is explicit' );
home_must( false !== strpos( $home, "gloskin_ui1_render_empty_state( 'generic', __( 'Piagam'" ), 'Piagam empty state is explicit' );
home_must( false !== strpos( $home, "gloskin_ui1_render_empty_state( 'treatment', __( 'Treatment Unggulan'" ), 'Treatment empty state is explicit' );

/* Real content replaces the fallback without fabricated facts. */
home_must( false !== strpos( $home, "array_slice( \$gloskin_context['treatments'], 0, 6 )" ), 'Treatment collection remains bounded to 6' );
home_must( false !== strpos( $home, 'array_slice( $gloskin_home_testimonials, 0, 3 )' ), 'Testimonial collection remains bounded to 3' );
home_must( false !== strpos( $home, "array_slice( \$gloskin_context['achievements'], 0, 4 )" ), 'Piagam collection remains bounded to 4' );
home_must( false !== strpos( $home, "trim( (string) ( \$gloskin_home_testimonial['excerpt'] ?? '' ) )" ), 'Testimonial quote comes from factual excerpt' );
home_must( false === strpos( $home, "\$gloskin_home_testimonial['title'] ?? ''" ), 'Testimonial title is never fabricated as quote' );
home_must( false !== strpos( $home, "if ( ! empty( \$gloskin_home_testimonial['image_id'] ) )" ), 'missing testimonial avatar is optional' );
home_must( false !== strpos( $home, "gloskin_ui1_render_presentation_media( 'editorial', 'piagam-'" ), 'missing Piagam image gets shared neutral media fallback' );

/* Home rhythm and video geometry are scoped; no global section hotfix. */
home_must( false !== strpos( $editorial, '.gloskin-home-why,.gloskin-home-treatments,.gloskin-home-testimonials,.gloskin-home-piagam{padding-block:clamp(3rem,6vw,5.5rem)}' ), 'Home roots own restored responsive vertical rhythm' );
home_must( false !== strpos( $editorial, '.gloskin-home-treatments .gloskin-ui1-section-heading,.gloskin-home-testimonials .gloskin-ui1-section-heading,.gloskin-home-piagam .gloskin-ui1-section-heading{max-width:760px;margin:0 auto clamp(1.5rem,3vw,2.5rem);text-align:center}' ), 'Home section headings retain centered wireframe cadence' );
home_must( false !== strpos( $editorial, 'body.gloskin-ui1--home .gloskin-ui1-hero--video-only .gloskin-ui1-hero-bg-video__media{object-fit:cover;object-position:center center}' ), 'Home video-only hero uses cover with centered focal point' );
home_must( false === strpos( $editorial, '.gloskin-ui1-section{padding' ), 'no broad global section padding patch' );
home_must( false !== strpos( $editorial, '.gloskin-home-treatments__grid{grid-template-columns:repeat(3,minmax(0,1fr))}' ), 'Treatment desktop grid remains 3 columns' );
home_must( false !== strpos( $editorial, '.gloskin-home-piagam__grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}' ), 'Piagam desktop grid remains 4 columns' );

/* Existing shared media owner degrades editorial media gracefully. */
home_must( false !== strpos( $media, 'function gloskin_ui1_render_editorial_media(' ), 'shared editorial media renderer exists' );
$editorial_media_start = strpos( $media, 'function gloskin_ui1_render_editorial_media(' );
$editorial_media_end   = strpos( $media, "\n}\n", $editorial_media_start );
$editorial_media_block = false !== $editorial_media_start && false !== $editorial_media_end ? substr( $media, $editorial_media_start, $editorial_media_end - $editorial_media_start + 3 ) : '';
home_must( false !== strpos( $editorial_media_block, 'gloskin_ui1_render_presentation_media(' ), 'editorial media falls back to neutral presentation media' );

/* Promo uses the shared fallback and the enhanced carousel stacking contract. */
home_must( false !== strpos( $promo, "gloskin_ui1_render_empty_state( 'generic', __( 'Informasi promo belum tersedia.'" ), 'empty Promo collection uses shared empty state' );
home_must( false !== strpos( $promo, "gloskin_ui1_render_presentation_media( 'editorial', 'promo-'" ), 'missing Promo artwork uses shared media fallback' );
home_must( false === strpos( $promo, '<div class="gloskin-ui1-empty">' ), 'Promo no longer duplicates generic empty markup' );
home_must( false !== strpos( $editorial, '[data-gloskin-promo-enhanced] .gloskin-ui1-promo-carousel__stage{display:grid;grid-template-areas:"slide";overflow:hidden;width:100%;min-width:0}' ), 'enhanced Promo stage stacks slides and clips inactive slides' );
home_must( false !== strpos( $editorial, '[data-gloskin-promo-enhanced] [data-gloskin-promo-slide]{grid-area:slide;min-width:0;transition:transform .52s cubic-bezier(.4,0,.2,1);will-change:transform}' ), 'enhanced Promo slides share one grid area' );
home_must( false !== strpos( $footer, "array( 'home', 'contact', 'about', 'promo' )" ), 'footer CTA stays excluded on Home, Contact, About, and Promo' );

/* Existing specialized states remain specialized. */
home_must( false !== strpos( $not_found, 'class="gloskin-ui1-not-found"' ) && false !== strpos( $not_found, 'status_header( 404 )' ), 'specialized 404 remains intact' );
home_must( false !== strpos( $helpers, 'function gloskin_ui1_render_native_cart_empty_state()' ), 'purpose-built Woo empty-cart path remains intact' );

/* Frontend completeness no longer blocks the durable Content Finalizer. */
home_must( ! is_file( $plugin . '/includes/class-gloskin-site-core-home-readiness-contract.php' ), 'frontend completeness guard file is removed' );
home_must( false === strpos( $kernel, 'Gloskin_Site_Core_Home_Readiness_Contract' ), 'Kernel no longer wires frontend readiness into Finalizer completion' );
home_must( false !== strpos( $finalizer, 'resolve_canonical_products()' ) && false !== strpos( $finalizer, 'reconcile_product_content( $canonical )' ) && false !== strpos( $finalizer, 'apply_woo_categories( $canonical )' ), 'Finalizer hard durable reconciliation remains' );
foreach ( array( 'unrelated_woo_mutations', 'hard_deleted_posts', 'media_deletions' ) as $zero_invariant ) {
	home_must( (bool) preg_match( "/'" . preg_quote( $zero_invariant, '/' ) . "'\\s*=>\\s*0/", $finalizer ), 'hard zero invariant remains: ' . $zero_invariant );
}
home_must( false !== strpos( $finalizer, 'catch ( Throwable $e )' ) && false !== strpos( $finalizer, "['status']     = 'failed'" ), 'real Finalizer failures still fail closed' );

/* Existing translation/interface owner already covers the reused public copy. */
home_must( false !== strpos( $translation, 'Detail tambahan belum tersedia untuk ditampilkan.' ), 'generic Home fallback copy stays in translation owner' );
home_must( false !== strpos( $translation, 'Informasi promo belum tersedia.' ), 'Promo fallback copy stays in translation owner' );

/* Treatment data owner stays deterministic and Home remains video-only. */
$curated_start = strpos( $template, 'private function curated_home_treatments()' );
$curated_end   = strpos( $template, 'private function skincare_category_context()', $curated_start );
$curated       = false !== $curated_start && false !== $curated_end ? substr( $template, $curated_start, $curated_end - $curated_start ) : '';
home_must( false !== strpos( $curated, "'gloskin_treatment_feature_on_home'" ) && false !== strpos( $curated, "'post__not_in'   => \$exclude" ) && false !== strpos( $curated, 'array_slice( $cards, 0, 6 )' ), 'canonical Home treatment selection remains deterministic and bounded' );
$home_ctx_start = strpos( $template, 'private function home_context()' );
$home_ctx_end   = strpos( $template, 'private function about_context()', $home_ctx_start );
$home_ctx       = false !== $home_ctx_start && false !== $home_ctx_end ? substr( $template, $home_ctx_start, $home_ctx_end - $home_ctx_start ) : '';
home_must( false !== strpos( $home_ctx, "\$hero['mode'] = 'video_only';" ), 'Home uses video-only hero mode' );
home_must( false !== strpos( $home, 'gloskin_ui1_render_hero' ), 'Home template retains one shared hero renderer' );

home_must( false !== strpos( $kernel, "const VERSION = '0.7.194'" ) && false !== strpos( $bootstrap, 'Version: 0.7.194' ), 'release owners are synchronized at 0.7.194' );

echo "home-readiness-contract.php: OK (Home cover hero, Promo stacked carousel contract, footer CTA routes, version 0.7.194)\n";
