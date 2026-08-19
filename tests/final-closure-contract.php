<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$fail = 0;
function gl_final_ok( bool $ok, string $message ): void { global $fail; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n"; if ( ! $ok ) { $fail++; } }
$migration = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php' );
$ia = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-final-ia-normalizer.php' );
$media = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-editorial-media-bundle.php' );
$helpers = file_get_contents( $root . '/plugin/gloskin-site-core/templates/parts/template-helpers.php' );
$home = file_get_contents( $root . '/plugin/gloskin-site-core/templates/pages/home.php' );
$home_why = file_get_contents( $root . '/plugin/gloskin-site-core/templates/parts/home-why-local-media.php' );
$manifest = json_decode( (string) file_get_contents( $root . '/plugin/gloskin-site-core/migration-runtime/gloskin-editorial-media-v1/manifest.json' ), true );
$steps = array( 'preflight', 'managed_content', 'demo_seed', 'doctor_photos', 'normalize', 'cleanup', 'verify', 'finalize' );
$positions = array(); foreach ( $steps as $step ) { $positions[] = strpos( $migration, "'key' => '" . $step . "'" ); }
gl_final_ok( ! in_array( false, $positions, true ) && $positions === array_values( $positions ) && $positions === ( $sorted = ( function( $p ){ sort( $p ); return $p; } )( $positions ) ), 'final migration keeps the original eight checkpoint order' );
$first_step = min( $positions ); $last_step = max( $positions ); $step_slice = substr( $migration, $first_step, ( $last_step - $first_step ) + strlen( "'key' => 'finalize'" ) ); gl_final_ok( substr_count( $step_slice, "'key' => '" ) === 8, 'no migration checkpoint added/reordered' );
gl_final_ok( str_contains( $migration, 'editorial_media_service()->preflight()' ) && str_contains( $migration, "['editorial_audit'] = \$this->run_managed_content()" ), 'existing managed_content checkpoint owns editorial bundle work' );
gl_final_ok( str_contains( $migration, "['ia_audit'] = \$this->run_normalize()" ) && str_contains( $migration, 'final_ia_normalizer()->verify' ), 'existing normalize/verify checkpoints own stored IA' );
gl_final_ok( str_contains( $migration, 'reconcile_resume_checkpoint' ), 'same revision failed state has bounded catch-up resume logic' );
$finalize_pos = strrpos( $migration, 'private function run_finalize' );
$flush_pos = strpos( $migration, 'flush_rewrite_rules( false )', $finalize_pos );
$schema_pos = strpos( $migration, 'Gloskin_Site_Core_Lifecycle_Service::SCHEMA_VERSION', $finalize_pos );
gl_final_ok( $finalize_pos !== false && $schema_pos !== false && $flush_pos !== false && $schema_pos < $flush_pos, 'schema 0.3.0 closes before rewrite flush and consumption' );
gl_final_ok( str_contains( $ia, "'Perawatan', '/treatments/'" ) && str_contains( $ia, "'Tentang Gloskin', '/about/'" ), 'stored primary menu verification has exact labels and paths' );
gl_final_ok( str_contains( $ia, 'PRESERVED_MENU_NAME' ) && str_contains( $ia, 'preserve_snapshot' ), 'editor primary menu snapshot is preserved idempotently' );
gl_final_ok( str_contains( $ia, 'Canonical Home safe-stop' ), 'editor alternate Home triggers safe stop' );
gl_final_ok( substr_count( $ia, 'wp_delete_post(' ) === 1 && str_contains( $ia, 'wp_delete_post( $item_id, true )' ), 'IA normalizer deletes only obsolete nav-menu items, never supporting pages' );
gl_final_ok( str_contains( $media, "const OPTION = 'gloskin_site_core_editorial_media_v1'" ) && str_contains( $media, 'SOURCE_PAGE_META' ), 'editorial media has local catalog and provenance metadata' );
gl_final_ok( ! str_contains( $media, 'set_post_thumbnail' ), 'editorial bundle never overwrites editor-selected featured media' );
gl_final_ok( str_contains( $helpers, "array( 'doctor', 'clinic', 'product' )" ), 'doctor/product/clinic factual media safety remains strict' );
gl_final_ok( is_array( $manifest ) && count( $manifest['items'] ?? array() ) === 6, 'bounded editorial media manifest contains six sourced assets' );
foreach ( (array) ( $manifest['items'] ?? array() ) as $item ) {
	$file = $root . '/plugin/gloskin-site-core/migration-runtime/gloskin-editorial-media-v1/' . basename( (string) $item['file'] );
	gl_final_ok( is_file( $file ) && hash_file( 'sha256', $file ) === (string) $item['sha256'], 'bundle SHA valid: ' . (string) $item['key'] );
}
gl_final_ok( ! str_contains( $home, 'home-orientation' ), 'early home-orientation is removed' );
gl_final_ok( is_string( $home_why ) && str_contains( $home_why, 'gloskin_ui1_render_why_gloskin' ) && str_contains( $home_why, "'home_why'" ), 'Why slot preserves approved composition while resolving local editorial media' );
$order = array_map( static fn( $needle ) => strpos( $home, $needle ), array( 'home-why-local-media.php', 'home-treatments', 'render_managed_promo_carousel', 'home-discovery', 'render_testimonials', 'home-brand-story', 'render_achievements', 'home-closing' ) );
$sorted_order = $order; sort( $sorted_order );
gl_final_ok( ! in_array( false, $order, true ) && $order === $sorted_order, 'Home order matches approved prototype hierarchy' );
gl_final_ok( substr_count( $home, 'data-gloskin-product-grid' ) === 1, 'unified discovery reuses one supplied product collection without a duplicate Woo query' );
exit( $fail ? 1 : 0 );
