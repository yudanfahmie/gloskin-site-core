<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$fail = 0;
function gh_ok( bool $ok, string $message ): void { global $fail; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n"; if ( ! $ok ) { $fail++; } }
function gh_read( string $rel ): string { global $root; return (string) file_get_contents( $root . '/' . $rel ); }
$migration = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php');
$ia = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-final-ia-normalizer.php');
$template = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php');
$helpers = gh_read('plugin/gloskin-site-core/templates/parts/template-helpers.php');
$home = gh_read('plugin/gloskin-site-core/templates/pages/home.php');
$prod = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-production-batch.php');
$insight = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-insight-migration-admin.php');
$kernel = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php');
$plugin = gh_read('plugin/gloskin-site-core/gloskin-site-core.php');
$manifest = json_decode( gh_read('plugin/gloskin-site-core/migration-runtime/gloskin-editorial-media-v1/manifest.json'), true );

gh_ok( str_contains( $kernel, "const VERSION = '0.7.146'" ) && str_contains( $plugin, 'Version: 0.7.146' ), 'version bumped to 0.7.146' );
gh_ok( str_contains( $migration, "const REVISION       = '2026-08-19-final'" ), 'REVISION unchanged' );
gh_ok( str_contains( $migration, "const STATE_OPTION   = 'gloskin_site_core_revision_20260819f_state'" ), 'STATE_OPTION unchanged' );
$steps = array( 'preflight','managed_content','demo_seed','doctor_photos','normalize','cleanup','verify','finalize' );
$positions = array_map( static fn($s) => strpos( $migration, "'key' => '" . $s . "'" ), $steps ); $sorted = $positions; sort($sorted);
gh_ok( ! in_array(false,$positions,true) && $positions === $sorted, 'eight final checkpoint order unchanged' );

gh_ok( ! str_contains( $migration, 'Pengguna Demo' ) && ! str_contains( $migration, 'kondisi kulit saya membaik' ), 'synthetic patient/outcome wording removed' );
gh_ok( str_contains( $migration, "'policy'      => 'engineering-fixture-non-public-v2'" ) && str_contains( $migration, "'post_status' => 'draft'" ), 'demo fixtures explicitly non-public' );
gh_ok( ! str_contains( $migration, 'Demo seed tidak lengkap' ) && str_contains( $migration, 'quarantine_owned_demo_records' ), 'verify no longer depends on fake testimonial/achievement completeness' );
gh_ok( substr_count( $template, "'_gloskin_demo_identity'" ) >= 2, 'public managed record queries exclude demo identities' );

gh_ok( str_contains( $ia, "'publish' === (string) \$page->post_status" ) && str_contains( $ia, "'_gloskin_provisioned_revision'" ), 'canonical page differentiates published and migration-provisioned ownership' );
gh_ok( str_contains( $ia, 'Canonical page safe-stop: editor-owned /' ) && str_contains( $ia, "'publish' !== \$page->post_status" ), 'editor-owned non-public canonical page safe-stops and verify requires publish' );

gh_ok( ! str_contains( $prod, 'Gloskin_Site_Core_Doctor_Migration_Admin' ) && str_contains( $migration, 'advance_doctor_roster' ) && str_contains( $migration, 'Gloskin_Site_Core_Doctor_Importer' ), 'Final Migration owns/reuses doctor roster importer; second admin retired' );
gh_ok( str_contains( $migration, 'legacy-final-preflight' ), 'existing >0 Final Migration states retain compatibility proof' );
gh_ok( str_contains( $insight, 'independen dari Finalisasi Prototype' ), 'Insight Migration ownership documented as independent' );

gh_ok( ! str_contains( $template, 'max( $limit * 4, 40 )' ) && substr_count( $template, "'posts_per_page' => -1" ) >= 2, 'managed CPT queries fetch all before sort/slice' );
gh_ok( str_contains( $template, 'compare_managed_posts' ) && str_contains( $template, '(int) $a->ID <=> (int) $b->ID' ), 'managed ordering has deterministic secondary ID key' );

gh_ok( str_contains( $helpers, "in_array( \$kind, array( 'doctor', 'clinic', 'product' ), true ) ) { return; }" ), 'abstract renderer hard-stops factual identity kinds' );
gh_ok( ! str_contains( $helpers, "gloskin_ui1_render_presentation_media( 'product'" ) && str_contains( $helpers, 'gloskin-ui1-card--text-first' ), 'product/doctor/clinic normal missing media path is text-first' );
gh_ok( str_contains( $helpers, "'alt' => \$title" ) && str_contains( $helpers, "'alt' => \$name" ), 'factual media alt uses exact factual entity/product name' );

gh_ok( substr_count( $home, 'data-gloskin-section="home-brand-story"' ) === 1 && str_contains( $home, "home_url( '/about/' )" ), 'Home brand story always exists with /about/ fallback CTA' );
$brand_pos = strpos($home,'home-brand-story'); $test_pos=strpos($home,'render_testimonials'); $ach_pos=strpos($home,'render_achievements');
gh_ok( $test_pos !== false && $brand_pos !== false && $ach_pos !== false && $test_pos < $brand_pos && $brand_pos < $ach_pos, 'Home brand story structural order preserved' );

gh_ok( is_array($manifest) && count($manifest['items'] ?? array()) === 6, 'six first-party editorial assets remain selected' );
foreach ( (array)($manifest['items'] ?? array()) as $item ) {
    $file = $root . '/plugin/gloskin-site-core/migration-runtime/gloskin-editorial-media-v1/' . basename((string)$item['file']);
    $ok = is_file($file) && hash_file('sha256',$file)===(string)$item['sha256']
        && (int)($item['width']??0)>0 && (int)($item['height']??0)>0 && !empty($item['mime'])
        && !empty($item['semantic_role']) && !empty($item['source_page']) && !empty($item['source_asset_url'])
        && 'first-party-gloskin'===(string)($item['source_type']??'') && array_key_exists('decorative',$item) && array_key_exists('alt',$item);
    gh_ok($ok, 'editorial manifest metadata/SHA valid: '.(string)($item['key']??''));
}
exit($fail ? 1 : 0);
