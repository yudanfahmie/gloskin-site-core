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

/* Home data owner remains lean; frontend now owns graceful presentation gaps. */
$home_ctx_start = strpos( $ts, 'private function home_context()' );
$home_ctx_end   = strpos( $ts, 'private function about_context()', $home_ctx_start );
$home_ctx       = false !== $home_ctx_start && false !== $home_ctx_end ? substr( $ts, $home_ctx_start, $home_ctx_end - $home_ctx_start ) : '';
p4must( false === strpos( $home_ctx, 'managed_promo_records' ), 'Home context does not reintroduce Promo' );
p4must( false === strpos( $home_ctx, 'skincare_mappings' ), 'Home context does not reintroduce Skincare' );
p4must( false === strpos( $home_ctx, "'products'" ), 'Home context does not reintroduce Products' );
p4must( false === strpos( $home_ctx, 'woo_ready' ), 'Home context does not reintroduce Woo readiness' );
p4must( false !== strpos( $home_ctx, 'curated_home_treatments' ) && false !== strpos( $home_ctx, 'testimonials' ) && false !== strpos( $home_ctx, 'achievements' ), 'Home keeps its three dynamic content owners' );

/* Contractual Home sections cannot silently collapse. */
foreach ( array( 'data-gloskin-section="home-treatments"', 'data-gloskin-section="testimonials"', 'data-gloskin-section="achievements"' ) as $section ) {
	p4must( 1 === substr_count( $h, $section ), 'persistent Home shell: ' . $section );
}
p4must( substr_count( $h, 'gloskin_ui1_render_empty_state(' ) >= 3, 'Home dynamic sections have shared empty-state fallbacks' );
p4must( false !== strpos( $h, "gloskin_ui1_render_empty_state( 'generic', __( 'Testimoni'" ), 'Testimoni empty state uses shared renderer' );
p4must( false !== strpos( $h, "gloskin_ui1_render_empty_state( 'generic', __( 'Piagam'" ), 'Piagam empty state uses shared renderer' );
p4must( false !== strpos( $h, "gloskin_ui1_render_presentation_media( 'editorial', 'piagam-'" ), 'missing Piagam image uses shared media fallback' );
p4must( false !== strpos( $h, "trim( (string) ( \$gloskin_home_testimonial['excerpt'] ?? '' ) )" ), 'Testimonial uses factual excerpt' );
p4must( false === strpos( $h, "\$gloskin_home_testimonial['title'] ?? ''" ), 'no fabricated Testimonial quote from title' );

/* One generic empty-state presentation owner. */
p4must( false !== strpos( $helpers, 'function gloskin_ui1_render_empty_state(' ), 'shared generic empty-state renderer exists' );
p4must( false !== strpos( $core_base, '.gloskin-ui1-empty-state{' ), 'shared core foundation owns generic empty-state CSS' );
p4must( false === strpos( $readiness, '.gloskin-ui1-empty-state{' ), 'readiness layer no longer duplicates generic empty-state CSS' );
p4must( false !== strpos( $editorial, '.gloskin-home-why,.gloskin-home-treatments,.gloskin-home-testimonials,.gloskin-home-piagam{padding-block:min(var(--gloskin-section),5.5rem)}' ), 'Home vertical rhythm is scoped and token based' );
p4must( false === strpos( $editorial, '.gloskin-ui1-section{padding' ), 'no broad global section spacing patch' );

/* Promo remains structurally stable with shared empty/media fallbacks. */
p4must( false !== strpos( $p, "gloskin_ui1_render_empty_state( 'generic', __( 'Informasi promo belum tersedia.'" ), 'Promo empty state uses shared renderer' );
p4must( false !== strpos( $p, "gloskin_ui1_render_presentation_media( 'editorial', 'promo-'" ), 'Promo missing artwork uses shared media renderer' );
p4must( false === strpos( $p, '<div class="gloskin-ui1-empty">' ), 'legacy Promo empty markup removed' );

/* About remains deliberately optional where absence legitimately means no section. */
$about_ctx_start = strpos( $ts, 'private function about_context()' );
$about_ctx_end   = strpos( $ts, 'private function about_founder_context', $about_ctx_start );
$about_ctx       = false !== $about_ctx_start && false !== $about_ctx_end ? substr( $ts, $about_ctx_start, $about_ctx_end - $about_ctx_start ) : '';
p4must( false === strpos( $about_ctx, 'hero_context' ) && false === strpos( $about_ctx, 'clinic_cards' ) && false === strpos( $about_ctx, 'all_published_doctor_cards' ) && false === strpos( $about_ctx, 'published_managed_records' ), 'About does not regain unused context owners' );
p4must( false !== strpos( $about_ctx, "'founder'" ) && false !== strpos( $about_ctx, "'vision'" ), 'About retains founder and principles data' );
foreach ( array( 'about-header', 'about-story', 'about-founder', 'about-principles' ) as $class ) {
	p4must( false !== strpos( $a, $class ), 'About structure preserved: ' . $class );
}

/* Frontend completeness is not a Finalizer blocker anymore. */
p4must( ! is_file( $plugin . '/includes/class-gloskin-site-core-home-readiness-contract.php' ), 'obsolete Home Finalizer readiness guard removed' );
p4must( false === strpos( $k, 'Gloskin_Site_Core_Home_Readiness_Contract' ) && false === strpos( $k, 'class-gloskin-site-core-home-readiness-contract.php' ), 'Kernel no longer wires frontend completeness into Finalizer' );
p4must( false !== strpos( $k, 'Gloskin_Site_Core_Content_Finalizer_Admin' ), 'Content Finalizer remains registered as durable reconciliation owner' );
p4must( false === strpos( $k, 'Production_Batch' ) && false === strpos( $k, 'production-batch' ), 'retired ProductionBatch stays retired' );

/* Existing translation/interface owner covers all newly reused visible copy. */
foreach ( array( 'Detail tambahan belum tersedia untuk ditampilkan.', 'Informasi promo belum tersedia.', 'Treatment Unggulan', 'Testimoni', 'Piagam' ) as $copy ) {
	p4must( false !== strpos( $t, $copy ), 'translation/interface owner contains: ' . $copy );
}

p4must( false !== strpos( $k, "const VERSION = '0.7.189'" ) && false !== strpos( $b, 'Version: 0.7.189' ), 'release owners synchronized at 0.7.189' );

echo "phase4-final-closure-contract.php: OK (73 canonical hard integrity + graceful Home/Promo empty-state resilience + Finalizer separation + version 0.7.189)\n";
