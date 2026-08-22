<?php
declare(strict_types=1);

$root   = dirname( __DIR__ );
$plugin = $root . '/plugin/gloskin-site-core';

function p4fail( string $message ): void {
	fwrite( STDERR, "phase4-final-closure-contract.php: FAIL: {$message}\n" );
	exit( 1 );
}
function p4must( bool $condition, string $message ): void {
	if ( ! $condition ) {
		p4fail( $message );
	}
}
function p4text( string $path ): string {
	$value = @file_get_contents( $path );
	if ( false === $value ) {
		p4fail( 'cannot read ' . $path );
	}
	return $value;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! class_exists( 'Gloskin_Site_Core_Content_Service' ) ) {
	class Gloskin_Site_Core_Content_Service {
		public const ADMIN_MENU_SLUG = 'gloskin-content';
		public const PROMO_POST_TYPE = 'gloskin_promo';
		public const ACHIEVEMENT_POST_TYPE = 'gloskin_achievement';
		public const FAMILY_TAXONOMY = 'gloskin_product_family';
		public const CONCERN_TAXONOMY = 'gloskin_concern';
		public const FAMILY_SKINCARE = 'skincare';
		public const FAMILY_TREATMENT = 'treatment';
	}
}

$fpath = $plugin . '/includes/class-gloskin-site-core-content-finalizer-admin.php';
require_once $fpath;

$f          = p4text( $fpath );
$k          = p4text( $plugin . '/includes/class-gloskin-site-core-kernel.php' );
$b          = p4text( $plugin . '/gloskin-site-core.php' );
$t          = p4text( $plugin . '/includes/class-gloskin-site-core-translation.php' );
$cs         = p4text( $plugin . '/includes/class-gloskin-site-core-content-service.php' );
$ts         = p4text( $plugin . '/includes/class-gloskin-site-core-template-service.php' );
$h          = p4text( $plugin . '/templates/pages/home.php' );
$p          = p4text( $plugin . '/templates/pages/promo.php' );
$a          = p4text( $plugin . '/templates/pages/about.php' );
$helpers    = p4text( $plugin . '/templates/parts/readiness-helpers.php' );
$core_base  = p4text( $plugin . '/assets/css/gloskin-ui1-core-base.css' );
$readiness  = p4text( $plugin . '/assets/css/gloskin-ui1-readiness.css' );
$editorial  = p4text( $plugin . '/assets/css/gloskin-ui1-editorial.css' );

/* Durable Phase-4 product reconciliation remains hard. */
$skin  = Gloskin_Site_Core_Content_Finalizer_Admin::skincare_category_map();
$treat = Gloskin_Site_Core_Content_Finalizer_Admin::treatment_product_slugs();
p4must( 25 === count( $skin ), 'canonical Skincare scope is exactly 25' );
p4must( 48 === count( $treat ), 'canonical Treatment Woo scope is exactly 48' );
p4must( 73 === count( array_unique( array_merge( array_keys( $skin ), $treat ) ) ), 'canonical combined product scope is exactly 73' );
p4must( 25 === count( array_filter( $skin ) ), 'all Skincare products retain a native Woo category mapping' );
foreach ( array( 'resolve_canonical_products()', 'reconcile_product_content( $canonical )', 'apply_woo_categories( $canonical )', 'prepare_promos()', 'prepare_piagam()' ) as $needle ) {
	p4must( false !== strpos( $f, $needle ), 'durable resolver step remains: ' . $needle );
}
foreach ( array( "25 !== absint( \$content['skincare_short']", "25 !== absint( \$content['skincare_full']", "48 !== absint( \$content['treatment_short']", "48 !== absint( \$content['treatment_full']" ) as $needle ) {
	p4must( false !== strpos( $f, $needle ), 'description hard verifier remains: ' . $needle );
}

/* Product-specific copy remains differentiated, with conservative fallback. */
$skincare_map  = Gloskin_Site_Core_Content_Finalizer_Admin::skincare_product_copy_map();
$treatment_map = Gloskin_Site_Core_Content_Finalizer_Admin::treatment_product_copy_map();
p4must( count( $skincare_map ) >= 20, 'researched Skincare product copy map remains' );
p4must( count( $treatment_map ) >= 25, 'researched Treatment product copy map remains' );
p4must( false !== strpos( $f, 'Conservative' ) && false !== strpos( $f, 'category-level' ) && false !== strpos( $f, 'concern-based' ), 'conservative unsupported-product fallback remains' );

/* Safety invariants remain hard; this resilience pass adds no destructive path. */
foreach ( array( 'wp_delete_post', 'wp_delete_attachment', 'wp_delete_file', 'levenshtein', 'similar_text' ) as $forbidden ) {
	p4must( false === strpos( $f, $forbidden ), 'forbidden operation absent: ' . $forbidden );
}
foreach ( array( 'unrelated_woo_mutations', 'hard_deleted_posts', 'media_deletions' ) as $zero_invariant ) {
	p4must( (bool) preg_match( "/'" . preg_quote( $zero_invariant, '/' ) . "'\\s*=>\\s*0/", $f ), 'zero invariant remains: ' . $zero_invariant );
}
p4must( false !== strpos( $f, "'manage_options'" ) && false !== strpos( $f, 'check_admin_referer' ), 'Finalizer operator gate remains' );
p4must( false !== strpos( $f, "'status' => 'already_complete', 'mutations' => 0" ), 'second Finalizer run remains a no-op' );
p4must( false !== strpos( $f, 'catch ( Throwable $e )' ) && false !== strpos( $f, "['status']     = 'failed'" ), 'real resolver failures still fail closed' );

/* Resolver-owned replacement assets are still verified as its own durable output. */
$art = $plugin . '/assets/images/content-replacements';
foreach ( array( 'promo-01.png', 'promo-02.png', 'promo-03.png', 'piagam-01.png', 'piagam-02.png', 'piagam-03.png', 'piagam-04.png' ) as $asset ) {
	$path = $art . '/' . $asset;
	$info = @getimagesize( $path );
	p4must( is_file( $path ) && filesize( $path ) > 1000 && is_array( $info ) && IMAGETYPE_PNG === $info[2], 'replacement artwork remains valid: ' . $asset );
}
p4must( 3 === substr_count( $f, "'asset' => 'promo-" ) && 4 === substr_count( $f, "'asset' => 'piagam-" ), 'Finalizer still owns exactly 3 Promo + 4 Piagam replacement definitions' );
p4must( false !== strpos( $f, 'set_post_thumbnail' ) && false !== strpos( $f, 'attachment_is_usable_image' ), 'resolver-owned replacement image verification remains' );

/* Home data owner remains lean; frontend owns graceful presentation gaps. */
$home_ctx_start = strpos( $ts, 'private function home_context()' );
$home_ctx_end   = strpos( $ts, 'private function about_context()', $home_ctx_start );
$home_ctx       = false !== $home_ctx_start && false !== $home_ctx_end ? substr( $ts, $home_ctx_start, $home_ctx_end - $home_ctx_start ) : '';
p4must( false === strpos( $home_ctx, 'managed_promo_records' ), 'Home context does not reintroduce Promo' );
p4must( false === strpos( $home_ctx, 'skincare_mappings' ), 'Home context does not reintroduce Skincare' );
p4must( false === strpos( $home_ctx, "'products'" ), 'Home context does not reintroduce Products' );
p4must( false === strpos( $home_ctx, 'woo_ready' ), 'Home context does not reintroduce Woo readiness' );
p4must( false !== strpos( $home_ctx, 'curated_home_treatments' ) && false !== strpos( $home_ctx, 'testimonials' ) && false !== strpos( $home_ctx, 'achievements' ), 'Home keeps its three dynamic content owners' );
p4must( false !== strpos( $home_ctx, "\$hero['mode'] = 'video_only';" ), 'Home remains video-only hero mode' );

/* Contractual Home sections cannot silently collapse. */
foreach ( array( 'data-gloskin-section="home-treatments"', 'data-gloskin-section="testimonials"', 'data-gloskin-section="achievements"' ) as $section ) {
	p4must( 1 === substr_count( $h, $section ), 'persistent Home shell: ' . $section );
}
p4must( substr_count( $h, 'gloskin_ui1_render_empty_state(' ) >= 3, 'Home dynamic sections have shared empty-state fallbacks' );
p4must( false !== strpos( $h, "gloskin_ui1_render_empty_state( 'generic', __( 'Testimoni'" ), 'Testimoni empty state uses shared renderer' );
p4must( false !== strpos( $h, "gloskin_ui1_render_empty_state( 'generic', __( 'Piagam & Penghargaan'" ), 'Piagam empty state uses shared renderer' );
p4must( false !== strpos( $h, "trim( (string) ( \$gloskin_home_testimonial['excerpt'] ?? '' ) )" ), 'Testimonial uses factual excerpt' );
p4must( false === strpos( $h, "\$gloskin_home_testimonial['title'] ?? ''" ), 'no fabricated Testimonial quote from title' );

/* Editorial data sync: TemplateService decides records, templates only render. */
p4must( 1 === substr_count( $cs, 'public static function editorial_profile(' ), 'ContentService owns one declarative editorial display profile' );
p4must( false !== strpos( $ts, 'Gloskin_Site_Core_Content_Service::editorial_profile( $post_type )' ), 'TemplateService consumes canonical editorial profile' );
p4must( false === strpos( $ts, "'_gloskin_demo_identity'" ), 'migration demo identity is not a frontend display rule' );
p4must( false !== strpos( $ts, "'post_excerpt' === \$required" ), 'TemplateService owns testimonial quote eligibility' );
p4must( false === strpos( $h, 'array_filter( $gloskin_home_testimonials' ), 'Home does not re-filter testimonial eligibility' );
p4must( false === strpos( $h, 'array_slice( $gloskin_home_testimonials' ), 'Home does not own testimonial display limit' );
p4must( false === strpos( $h, 'array_slice( isset( $gloskin_context[\'achievements\']' ), 'Home does not own achievement display limit' );
p4must( false === strpos( $home_ctx, 'TESTIMONIAL_POST_TYPE, 6' ) && false === strpos( $home_ctx, 'ACHIEVEMENT_POST_TYPE, 8' ), 'TemplateService has no obsolete query-then-slice double limits' );
p4must( false !== strpos( $h, "absint( \$gloskin_home_achievement['image_id'] ?? 0 )" ), 'Home consumes canonical Achievement image_id' );
p4must( false !== strpos( $h, 'wp_get_attachment_image( $gloskin_home_achievement_image_id' ), 'Achievement Featured Image is the primary Home visual' );
$image_branch = strpos( $h, "if ( '' !== \$gloskin_home_achievement_image )" );
$fallback_g   = strpos( $h, '<div class="gloskin-home-piagam__icon" aria-hidden="true">G</div>' );
p4must( false !== $image_branch && false !== $fallback_g && $fallback_g > $image_branch, 'hard-coded G exists only after Featured Image branch as empty fallback' );

/* One generic empty-state owner plus scoped Home composition. */
p4must( false !== strpos( $helpers, 'function gloskin_ui1_render_empty_state(' ), 'shared generic empty-state renderer exists' );
p4must( false !== strpos( $core_base, '.gloskin-ui1-empty-state{' ), 'shared core foundation owns generic empty-state CSS' );
p4must( false === strpos( $readiness, '.gloskin-ui1-empty-state{' ), 'readiness layer no longer duplicates generic empty-state CSS' );
p4must( false !== strpos( $editorial, '.gloskin-home-why,.gloskin-home-treatments,.gloskin-home-testimonials,.gloskin-home-piagam{padding:clamp(80px,8vw,120px) 0}' ), 'Home vertical rhythm is restored and scoped' );
p4must( false !== strpos( $editorial, 'body.gloskin-ui1--home .gloskin-ui1-hero--video-only .gloskin-ui1-hero-bg-video__media{display:block' ) && false !== strpos( $editorial, 'object-fit:cover;object-position:center center' ), 'Home hero video uses cover (latest product decision)' );
p4must( false !== strpos( $editorial, '.gloskin-home-treatments .gloskin-ui1-section-heading,.gloskin-home-testimonials .gloskin-ui1-section-heading,.gloskin-home-piagam .gloskin-ui1-section-heading{max-width:760px;margin:0 auto 70px;text-align:center}' ), 'Home section heading cadence is restored' );
p4must( false === strpos( $editorial, '.gloskin-ui1-section{padding' ), 'no broad global section spacing patch' );

/* Promo remains structurally stable with shared empty/media fallbacks. */
p4must( false !== strpos( $p, "gloskin_ui1_render_empty_state( 'generic', __( 'Informasi promo belum tersedia.'" ), 'Promo empty state uses shared renderer' );
p4must( false !== strpos( $p, 'gloskin-promo__missing' ), 'Promo missing artwork uses bounded placeholder' );
p4must( false === strpos( $p, '<div class="gloskin-ui1-empty">' ), 'legacy Promo empty markup removed' );
p4must( false !== strpos( $editorial, '[data-gloskin-promo-enhanced] .gloskin-ui1-promo-carousel__stage{display:grid;grid-template-areas:"slide";overflow:hidden;width:100%;min-width:0' ), 'Promo carousel enhanced stage stacks slides in one grid area with overflow:hidden' );
p4must( false !== strpos( $editorial, '[data-gloskin-promo-enhanced] [data-gloskin-promo-slide]{grid-area:slide;min-width:0;transition:transform .52s cubic-bezier(.4,0,.2,1);will-change:transform}' ), 'Promo carousel enhanced slides use grid-area:slide with transition' );
$editorial_mgr = p4text( $plugin . '/includes/class-gloskin-site-core-editorial-manager.php' );
p4must( false !== strpos( $ts, "'limited_promos'" ) && false !== strpos( $ts, "'regular_promos'" ), 'TemplateService projects both limited_promos and regular_promos' );
p4must( false === strpos( $k, 'Editorial_Projection' ) && false === strpos( $k, 'editorial-projection' ), 'EditorialProjection is absent from Kernel' );
p4must( false !== strpos( $editorial_mgr, "'_gloskin_editorial_seed_identity'" ) && false !== strpos( $editorial_mgr, 'SEED_META' ), 'EditorialManager owns SEED_META identity' );
p4must( false !== strpos( $editorial_mgr, "'gloskin_promo_type', \$index <= 3 ? 'limited' : 'regular'" ), 'EditorialManager seeds split: index 1-3=limited, 4-6=regular' );
p4must( false === strpos( $ts, 'Editorial_Projection' ), 'TemplateService does not delegate to EditorialProjection' );

/* Footer owns universal dark CTA; page templates must not duplicate it. */
$footer    = p4text( $plugin . '/templates/parts/footer.php' );
$skincat   = p4text( $plugin . '/templates/pages/skincare-category.php' );
$doctors_t = p4text( $plugin . '/templates/pages/doctors.php' );
$clinic_t  = p4text( $plugin . '/templates/pages/clinic.php' );
p4must( false === strpos( $footer, 'gloskin_footer_cta_excluded_views' ), 'footer CTA exclusion array absent' );
p4must( false === strpos( $footer, 'gloskin_show_footer_cta' ), 'footer CTA gate absent' );
p4must( false !== strpos( $footer, 'gloskin-ui1-dark-consultation' ), 'footer owns universal dark consultation CTA' );
p4must( false === strpos( $skincat, 'gloskin-ui1-dark-consultation' ), 'skincare-category has no duplicate dark consultation' );
p4must( false === strpos( $doctors_t, 'gloskin-ui1-dark-consultation' ), 'doctors has no duplicate dark consultation' );
p4must( false === strpos( $clinic_t, 'gloskin-ui1-dark-consultation' ), 'clinic has no duplicate dark consultation' );

/* About final public structure is contractual: Header -> Story -> Founder ->
   Visi/Misi/Nilai -> END. Internal readiness/provenance state must never be
   rendered as visitor-facing debug copy. */
$about_ctx_start = strpos( $ts, 'private function about_context()' );
$about_ctx_end   = strpos( $ts, 'private function about_static_content', $about_ctx_start );
$about_ctx       = false !== $about_ctx_start && false !== $about_ctx_end ? substr( $ts, $about_ctx_start, $about_ctx_end - $about_ctx_start ) : '';
p4must( false === strpos( $about_ctx, 'hero_context' ) && false === strpos( $about_ctx, 'clinic_cards' ) && false === strpos( $about_ctx, 'all_published_doctor_cards' ) && false === strpos( $about_ctx, 'published_managed_records' ), 'About does not regain unused context owners' );
p4must( false !== strpos( $about_ctx, "'founder'" ) && false !== strpos( $about_ctx, "'vision'" ), 'About retains founder and principles data' );
$about_order = array(
	'data-gloskin-section="about-header"',
	'data-gloskin-section="about-story"',
	'data-gloskin-section="about-founder"',
	'data-gloskin-section="about-principles"',
);
$about_last = -1;
foreach ( $about_order as $section ) {
	$position = strpos( $a, $section );
	p4must( false !== $position && $position > $about_last, 'About final order: ' . $section );
	p4must( 1 === substr_count( $a, $section ), 'About section is unique: ' . $section );
	$about_last = $position;
}
$about_rest = substr( $a, $about_last + strlen( 'data-gloskin-section="about-principles"' ) );
preg_match_all( '/data-gloskin-section="([^"]+)"/', $about_rest, $about_extras );
$about_allowed_extras = array( 'about-philosophy', 'about-explore' );
foreach ( $about_extras[1] as $about_extra ) {
	p4must( in_array( $about_extra, $about_allowed_extras, true ), 'About continuation section must use a bounded identifier; found: ' . $about_extra );
}
foreach ( array( 'Operator:', 'gloskin_about_vision', 'gloskin_about_mission', 'gloskin_about_values', 'gloskin_about_founder_name', 'gloskin_about_founder_role' ) as $debug_copy ) {
	p4must( false === strpos( $a, $debug_copy ), 'About public template does not expose internal field/debug token: ' . $debug_copy );
}
p4must( false === strpos( $a, 'gloskin_ui1_render_empty_state(' ), 'About has no temporary public readiness cards' );
foreach ( array( 'render_doctor', 'render_clinic', 'render_achievements', 'about-team', 'about-network', 'about-achievements' ) as $removed_surface ) {
	p4must( false === strpos( $a, $removed_surface ), 'About removed surface stays absent: ' . $removed_surface );
}
p4must( false === strpos( $a, 'gloskin-ui1-footer__cta' ), 'About template has no generic closing CTA markup' );

/* About copy is now release-controlled static content. */
$lifecycle = p4text( $plugin . '/includes/class-gloskin-site-core-lifecycle-service.php' );
p4must( false === strpos( $lifecycle, 'register_about_reconciliation' ) && false === strpos( $lifecycle, 'maybe_reconcile_about_content' ), 'About reconciliation runtime is retired from lifecycle service' );
p4must( false === strpos( $lifecycle, 'wp_insert_attachment' ) && false === strpos( $lifecycle, 'wp_delete_attachment' ), 'lifecycle service never creates/deletes attachments' );
p4must( false !== strpos( $ts, 'private function about_static_content()' ), 'About copy is owned by about_static_content() in TemplateService' );
p4must( false !== strpos( $about_ctx, 'about_static_content()' ), 'about_context() delegates to static copy owner' );
p4must( false === strpos( $k, 'register_about_reconciliation' ), 'kernel does not register retired About reconciliation runtime' );

/* Shop Discovery CSS must load after the current last global style, not a retired handle. */
$shop_route = p4text( $plugin . '/includes/gloskin-site-core-shop-discovery-route-trait.php' );
p4must( false !== strpos( $shop_route, "array( 'gloskin-ui1-product-grid' )" ), 'Shop Discovery CSS depends on gloskin-ui1-product-grid (not retired prototype-refresh)' );
p4must( false === strpos( $shop_route, "'gloskin-ui1-prototype-refresh'" ), 'retired prototype-refresh dependency removed from Shop Discovery enqueue' );

/* Frontend completeness is not a Finalizer blocker anymore. */
p4must( ! is_file( $plugin . '/includes/class-gloskin-site-core-home-readiness-contract.php' ), 'obsolete Home Finalizer readiness guard removed' );
p4must( false === strpos( $k, 'Gloskin_Site_Core_Home_Readiness_Contract' ) && false === strpos( $k, 'class-gloskin-site-core-home-readiness-contract.php' ), 'Kernel no longer wires frontend completeness into Finalizer' );
p4must( false !== strpos( $k, 'Gloskin_Site_Core_Content_Finalizer_Admin' ), 'Content Finalizer remains registered as durable reconciliation owner' );
p4must( false === strpos( $k, 'Production_Batch' ) && false === strpos( $k, 'production-batch' ), 'retired ProductionBatch stays retired' );

/* Existing translation/interface owner covers reused visible copy. */
foreach ( array( 'Detail tambahan belum tersedia untuk ditampilkan.', 'Informasi promo belum tersedia.', 'Treatment Unggulan', 'Testimoni', 'Piagam' ) as $copy ) {
	p4must( false !== strpos( $t, $copy ), 'translation/interface owner contains: ' . $copy );
}

/* Testimonial prev/next buttons are bound inside initTestimonials() — one controller. */
$core_js = p4text( $plugin . '/assets/js/gloskin-ui1-core.js' );
p4must( false !== strpos( $core_js, "var prevBtn = root.querySelector('[data-gloskin-testimonial-prev]')" ), 'initTestimonials queries prevBtn' );
p4must( false !== strpos( $core_js, "var nextBtn = root.querySelector('[data-gloskin-testimonial-next]')" ), 'initTestimonials queries nextBtn' );
p4must( false !== strpos( $core_js, "prevBtn.addEventListener('click', function () { activate(current - 1); })" ), 'prevBtn click binds activate(current-1)' );
p4must( false !== strpos( $core_js, "nextBtn.addEventListener('click', function () { activate(current + 1); })" ), 'nextBtn click binds activate(current+1)' );
p4must( false === strpos( $core_js, 'initTestimonialArrows' ) && 1 === substr_count( $core_js, 'function initTestimonials()' ), 'one testimonial controller owns all nav — no split' );

p4must( false !== strpos( $k, "const VERSION = '0.7.220'" ) && false !== strpos( $b, 'Version: 0.7.220' ), 'release owners synchronized at 0.7.220' );

echo "phase4-final-closure-contract.php: OK (73 canonical hard integrity + editorial admin/frontend sync + Featured Image ownership + Home cover/video + Promo carousel JS-CSS contract + Promo arch durable + footer universal CTA + About static copy + bounded About continuation + Shop CSS dep + testimonial prev/next + version 0.7.220)\n";
