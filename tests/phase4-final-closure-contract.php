<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$plugin = $root . '/plugin/gloskin-site-core';
function p4fail( string $m ): void { fwrite( STDERR, "phase4-final-closure-contract.php: FAIL: {$m}\n" ); exit( 1 ); }
function p4must( bool $ok, string $m ): void { if ( ! $ok ) { p4fail( $m ); } }
function p4text( string $p ): string { $v = @file_get_contents( $p ); if ( false === $v ) { p4fail( 'cannot read ' . $p ); } return $v; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/tmp/wordpress/' ); }
if ( ! class_exists( 'Gloskin_Site_Core_Content_Service' ) ) {
	class Gloskin_Site_Core_Content_Service {
		public const ADMIN_MENU_SLUG='gloskin-content', PROMO_POST_TYPE='gloskin_promo', ACHIEVEMENT_POST_TYPE='gloskin_achievement';
		public const FAMILY_TAXONOMY='gloskin_product_family', CONCERN_TAXONOMY='gloskin_concern', FAMILY_SKINCARE='skincare', FAMILY_TREATMENT='treatment';
	}
}
/* Renamed from class-gloskin-site-core-phase4-finalizer-admin.php */
$fpath = $plugin . '/includes/class-gloskin-site-core-content-finalizer-admin.php';
require_once $fpath;
$f = p4text( $fpath );
$r = p4text( $plugin . '/includes/class-gloskin-site-core-home-readiness-contract.php' );
$k = p4text( $plugin . '/includes/class-gloskin-site-core-kernel.php' );
$b = p4text( $plugin . '/gloskin-site-core.php' );
$t = p4text( $plugin . '/includes/class-gloskin-site-core-translation.php' );
$h = p4text( $plugin . '/templates/pages/home.php' );
$p = p4text( $plugin . '/templates/pages/promo.php' );
$a = p4text( $plugin . '/templates/pages/about.php' );
$css = p4text( $plugin . '/assets/css/gloskin-ui1-editorial.css' );

/* --- Canonical product scope --- */
$skin  = Gloskin_Site_Core_Content_Finalizer_Admin::skincare_category_map();
$treat = Gloskin_Site_Core_Content_Finalizer_Admin::treatment_product_slugs();
p4must( count( $skin ) === 25 && count( $treat ) === 48 && count( array_unique( array_merge( array_keys( $skin ), $treat ) ) ) === 73, 'canonical 25+48 scope' );
p4must( count( array_filter( $skin ) ) === 25 && strpos( $f, "'perawatan'" ) !== false && strpos( $f, "'uncategorized'" ) !== false, 'native Woo category contract' );

/* --- Content ownership meta and verifier --- */
foreach ( array( 'post_excerpt', 'post_content', '_gloskin_phase4_content_source', '_gloskin_phase4_content_version', 'should_write_product_field' ) as $n ) {
	p4must( strpos( $f, $n ) !== false, 'content owner ' . $n );
}
foreach ( array( "25 !== absint( \$content['skincare_short']", "25 !== absint( \$content['skincare_full']", "48 !== absint( \$content['treatment_short']", "48 !== absint( \$content['treatment_full']" ) as $n ) {
	p4must( strpos( $f, $n ) !== false, 'content verifier ' . $n );
}

/* --- Per-product copy maps: researched products are not all receiving the same template --- */
$skincare_map  = Gloskin_Site_Core_Content_Finalizer_Admin::skincare_product_copy_map();
$treatment_map = Gloskin_Site_Core_Content_Finalizer_Admin::treatment_product_copy_map();
p4must( count( $skincare_map ) >= 20, 'skincare per-product copy map has researched entries' );
p4must( count( $treatment_map ) >= 25, 'treatment per-product copy map has researched entries' );
/* Entries must be product-specific — different facial washes should not be identical */
$fw_slugs = array_keys( array_filter( $skin, static function ( $v ) { return 'facial-wash' === $v; } ) );
$fw_excepts = array_filter( array_map( static function ( $s ) use ( $skincare_map ) { return $skincare_map[ $s ]['post_excerpt'] ?? null; }, $fw_slugs ) );
p4must( count( array_unique( $fw_excepts ) ) === count( $fw_excepts ), 'facial-wash products each have a distinct short description' );
/* Conservative fallback still exists for products not in the map */
p4must( strpos( $f, 'Conservative' ) !== false && strpos( $f, 'category-level' ) !== false && strpos( $f, 'concern-based' ) !== false, 'conservative fallback is preserved for unsupported products' );

/* --- Legacy evidence and safety invariants --- */
foreach ( array( '_gloskin_sample_source_id', '_gloskin_sample_data', '_gloskin_sample_bundle_id', '_gloskin_demo_identity', 'FAMILY_TAXONOMY', 'wp_trash_post' ) as $n ) {
	p4must( strpos( $f, $n ) !== false, 'legacy evidence ' . $n );
}
foreach ( array( 'wp_delete_post', 'wp_delete_attachment', 'wp_delete_file', 'levenshtein', 'similar_text' ) as $n ) {
	p4must( strpos( $f, $n ) === false, 'forbidden ' . $n );
}
foreach ( array( 'unrelated_woo_mutations', 'hard_deleted_posts', 'media_deletions' ) as $n ) {
	p4must( (bool) preg_match( "/'". preg_quote( $n, '/' ) . "'\s*=>\s*0/", $f ), 'zero invariant ' . $n );
}
p4must( strpos( $f, "'manage_options'" ) !== false && strpos( $f, 'check_admin_referer' ) !== false, 'operator gate' );
p4must( strpos( $f, "'status' => 'already_complete', 'mutations' => 0" ) !== false, 'second-run no-op' );

/* --- Resolver step order --- */
$last = -1;
foreach ( array( 'resolve_canonical_products()', 'reconcile_product_content( $canonical )', 'apply_woo_categories( $canonical )', 'prepare_promos()', 'prepare_piagam()', 'trash_explicit_legacy_products( $canonical_ids )' ) as $n ) {
	$x = strpos( $f, $n );
	p4must( $x !== false && $x > $last, 'resolver order ' . $n );
	$last = $x;
}
$x = strpos( $f, "['status']", $last + 1 );
while ( $x !== false && strpos( substr( $f, $x, 120 ), "'complete'" ) === false ) { $x = strpos( $f, "['status']", $x + 1 ); }
p4must( $x !== false, 'complete only after final verify' );

/* --- Artwork assets (renamed from phase4 to content-replacements) --- */
$art = $plugin . '/assets/images/content-replacements';
foreach ( array( 'promo-01.png', 'promo-02.png', 'promo-03.png', 'piagam-01.png', 'piagam-02.png', 'piagam-03.png', 'piagam-04.png' ) as $n ) {
	$q = $art . '/' . $n;
	$z = @getimagesize( $q );
	p4must( is_file( $q ) && filesize( $q ) > 1000 && is_array( $z ) && $z[2] === IMAGETYPE_PNG, 'artwork ' . $n );
}
p4must( substr_count( $f, "'asset' => 'promo-" ) === 3 && substr_count( $f, "'asset' => 'piagam-" ) === 4, '3+4 replacements' );
p4must( strpos( $f, 'set_post_thumbnail' ) !== false && strpos( $f, 'attachment_is_usable_image' ) !== false, 'image binding' );

/* --- Home context cleanup: unused keys must not be prepared --- */
$ts = p4text( $plugin . '/includes/class-gloskin-site-core-template-service.php' );
$home_ctx_start = strpos( $ts, 'private function home_context()' );
$home_ctx_end   = strpos( $ts, 'private function about_context()', $home_ctx_start );
$home_ctx = substr( $ts, $home_ctx_start, $home_ctx_end - $home_ctx_start );
p4must( strpos( $home_ctx, 'managed_promo_records' ) === false, 'Home context no longer prepares promo' );
p4must( strpos( $home_ctx, 'skincare_mappings' ) === false, 'Home context no longer prepares skincare' );
p4must( strpos( $home_ctx, "'products'" ) === false, 'Home context no longer prepares products' );
p4must( strpos( $home_ctx, 'woo_ready' ) === false, 'Home context no longer prepares woo_ready' );
p4must( strpos( $home_ctx, 'curated_home_treatments' ) !== false, 'Home context still prepares treatments' );
p4must( strpos( $home_ctx, 'testimonials' ) !== false, 'Home context still prepares testimonials' );
p4must( strpos( $home_ctx, 'achievements' ) !== false, 'Home context still prepares achievements' );

/* --- Home presentation rhythm is scoped to the four final sections only --- */
p4must( strpos( $css, '.gloskin-home-why,.gloskin-home-treatments,.gloskin-home-testimonials,.gloskin-home-piagam{padding-block:clamp(3rem,6vw,5.5rem)}' ) !== false, 'Home section rhythm restored in editorial owner' );
p4must( strpos( $css, '.gloskin-ui1-section{padding' ) === false, 'no broad global section padding hotfix' );

/* --- Home readiness closes through the existing Content Finalizer state --- */
p4must( strpos( $r, "const STATE_OPTION = 'gloskin_site_core_phase4_finalizer_v1_state'" ) !== false, 'Home guard reuses existing Finalizer state option' );
p4must( strpos( $r, 'pre_update_option_' ) !== false && strpos( $r, 'guard_completion' ) !== false, 'Home guard intercepts Finalizer complete transition' );
p4must( strpos( $r, 'option_' ) !== false && strpos( $r, "['status']     = 'stale'" ) !== false, 'pre-contract complete state becomes stale until explicit rerun' );
p4must( strpos( $r, '3 !== count( $featured )' ) !== false && strpos( $r, '6 !== count( $selected_ids )' ) !== false, 'Treatment Home contract is exactly 3 featured + 3 additional = 6' );
p4must( strpos( $r, '3 !== count( $posts )' ) !== false && strpos( $r, 'gloskin_testimonial_active' ) !== false && strpos( $r, 'gloskin_testimonial_attribution' ) !== false, 'Testimonial Home contract is exactly 3 factual active records' );
p4must( strpos( $r, '4 !== count( $posts )' ) !== false && strpos( $r, 'gloskin_achievement_active' ) !== false && strpos( $r, 'gloskin_achievement_feature_on_home' ) !== false && strpos( $r, 'attachment_is_usable_image' ) !== false, 'Piagam Home contract is exactly 4 active featured usable images' );
p4must( strpos( $r, 'RuntimeException' ) !== false && strpos( $f, 'catch ( Throwable $e )' ) !== false && strpos( $f, "['status']     = 'failed'" ) !== false, 'Home readiness failures are caught by existing Finalizer and persist failed status' );
foreach ( array( 'wp_update_post', 'wp_insert_post', 'wp_trash_post', 'wp_delete_post', 'wp_delete_attachment', 'wp_delete_file' ) as $n ) {
	p4must( strpos( $r, $n ) === false, 'Home readiness guard is verification-only: ' . $n );
}
p4must( substr_count( $r, 'STATE_OPTION' ) >= 3 && strpos( $r, 'register_page' ) === false && strpos( $r, 'admin_post_' ) === false, 'Home readiness creates no second runner/admin flow' );

/* --- About context cleanup: unused keys must not be prepared --- */
$about_ctx_start = strpos( $ts, 'private function about_context()' );
$about_ctx_end   = strpos( $ts, 'private function about_founder_context', $about_ctx_start );
$about_ctx = substr( $ts, $about_ctx_start, $about_ctx_end - $about_ctx_start );
p4must( strpos( $about_ctx, 'hero_context' ) === false, 'About context no longer prepares hero' );
p4must( strpos( $about_ctx, 'clinic_cards' ) === false, 'About context no longer prepares clinics' );
p4must( strpos( $about_ctx, 'all_published_doctor_cards' ) === false, 'About context no longer prepares doctors' );
p4must( strpos( $about_ctx, 'published_managed_records' ) === false, 'About context no longer prepares achievements' );
p4must( strpos( $about_ctx, "'founder'" ) !== false, 'About context still prepares founder' );
p4must( strpos( $about_ctx, "'vision'" ) !== false, 'About context still prepares vision' );

/* --- Home template and about template structural checks --- */
p4must( strpos( $h, 'gloskin_ui1_render_hero' ) !== false && strpos( $h, 'video_only' ) === false, 'Home delegates hero to render_hero, mode in context' );
p4must( strpos( $h, "'treatments'" ) !== false && strpos( $h, "'testimonials'" ) !== false && strpos( $h, "'achievements'" ) !== false, 'Home uses treatments/testimonials/achievements context keys' );
p4must( strpos( $h, 'data-gloskin-phase4' ) === false, 'Home has no phase4 data attributes' );
p4must( strpos( $h, 'gloskin-phase4-' ) === false, 'Home has no phase4 CSS classes' );

foreach ( array( 'about-header', 'about-story', 'about-founder', 'about-principles', 'Tentang Kami', 'Tentang GLOSKIN', 'Visi · Misi · Nilai' ) as $n ) {
	p4must( strpos( $a, $n ) !== false, 'About ' . $n );
}
p4must( strpos( $a, 'gloskin-phase4-' ) === false, 'About has no phase4 CSS classes' );

/* --- Promo template --- */
p4must( strpos( $p, 'gloskin-phase4-' ) === false, 'Promo has no phase4 CSS classes' );
foreach ( array( 'promo-content', 'promo-closing', 'data-gloskin-promo-thumb' ) as $n ) {
	p4must( strpos( $p, $n ) === false, 'obsolete Promo ' . $n );
}

/* --- Kernel wiring: ContentFinalizer + Home readiness guard, no ProductionBatch --- */
p4must( strpos( $k, 'class-gloskin-site-core-content-finalizer-admin.php' ) !== false && strpos( $k, 'Gloskin_Site_Core_Content_Finalizer_Admin' ) !== false, 'Kernel uses content-finalizer (renamed)' );
p4must( strpos( $k, 'class-gloskin-site-core-home-readiness-contract.php' ) !== false && strpos( $k, 'Gloskin_Site_Core_Home_Readiness_Contract' ) !== false, 'Kernel wires Home readiness guard' );
p4must( strpos( $k, 'class-gloskin-site-core-phase4-finalizer-admin.php' ) === false, 'Kernel no longer references phase4 finalizer file' );
p4must( strpos( $k, 'Production_Batch' ) === false && strpos( $k, 'production-batch' ) === false, 'Kernel no longer references ProductionBatch' );
p4must( strpos( $k, "const VERSION = '0.7.188'" ) !== false && strpos( $b, 'Version: 0.7.188' ) !== false, 'version 0.7.188 sync' );

/* --- Phase-5 translation contract preserved --- */
foreach ( array( "'product' => array( 'label' => 'Product', 'fields' => \$base", 'Promo Poster', 'Kenapa Memilih GLOSKIN', 'Testimoni', 'Piagam', 'Tentang GLOSKIN', 'Visi · Misi · Nilai' ) as $n ) {
	p4must( strpos( $t, $n ) !== false, 'Phase5 translation ' . $n );
}

echo "phase4-final-closure-contract.php: OK (73 canonical, Home rhythm, Home readiness 6/3/4 fail-closed, 25+48 copy/category, context cleanup, 3+4 image-ready, Trash-only, Phase-5 preserved)\n";
