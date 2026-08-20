<?php
declare(strict_types=1);

$root   = dirname( __DIR__ );
$passed = 0;
$failed = 0;

function ok( bool $cond, string $msg ): void {
	global $passed, $failed;
	if ( $cond ) { echo "ok: {$msg}\n"; $passed++; } else { echo "FAIL: {$msg}\n"; $failed++; }
}

$migration = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php' );
$admin     = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration-admin.php' );
$kernel    = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$plugin_h  = file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );
if ( false === $migration || false === $admin || false === $kernel || false === $plugin_h ) { fwrite( STDERR, "Cannot read final migration contract sources\n" ); exit( 1 ); }

ok( (bool) preg_match( "/const REVISION\s*=\s*'2026-08-19-final'/", $migration ), "REVISION must remain 2026-08-19-final" );
ok( str_contains( $migration, 'gloskin_site_core_revision_20260819f_state' ), 'STATE_OPTION remains authoritative' );
ok( ! str_contains( $migration, 'gloskin_site_core_revision_20260819_state' ), 'prior STATE_OPTION is not reused' );
ok( str_contains( $migration, 'gloskin_site_core_revision_20260819f_lock' ), 'LOCK_OPTION remains final-revision scoped' );
ok( str_contains( $migration, 'wp_unique_filename' ), 'wp_unique_filename remains upload collision owner' );
ok( str_contains( $migration, 'find_attachment_by_sha' ) && str_contains( $migration, 'ATTACH_SHA256_META' ), 'SHA-based attachment reuse remains authoritative' );
ok( str_contains( $migration, '$seen_shas' ) && str_contains( $migration, 'get_post_thumbnail_id' ), 'verify checks duplicate SHA and per-doctor thumbnail' );
ok( str_contains( $admin, 'Finalisasi Prototype & Data' ), 'admin title preserved' );
ok( str_contains( $admin, 'ADMIN_MENU_SLUG' ) && ( str_contains( $admin, 'wp_safe_redirect' ) || str_contains( $admin, 'wp_redirect' ) ), 'fallback redirects to Content Overview' );
ok( str_contains( $kernel, 'class-gloskin-site-core-revision-20260819-final-migration-admin.php' ), 'kernel registers final migration admin' );
ok( ! str_contains( $kernel, 'class-gloskin-site-core-revision-20260819-migration-admin.php' ), 'old migration admin not registered' );
ok( (bool) preg_match( "/const VERSION\s*=\s*'0\.7\.145'/", $kernel ), 'Kernel VERSION must be 0.7.145' );
ok( (bool) preg_match( '/^ \* Version: 0\.7\.145$/m', $plugin_h ), 'Plugin header Version must be 0.7.145' );
ok( ! preg_match( '/\blevenshtein\s*\(|\bsoundex\s*\(|\bsimilar_text\s*\(/', $migration ), 'no fuzzy doctor matching' );
ok( ! preg_match( "/wp_delete_post\s*\([^)]*product/", $migration ), 'no Woo product deletion' );
ok( str_contains( $migration, 'detect_environment' ) && str_contains( $migration, "'draft'" ), 'production demo status remains draft' );
ok( str_contains( $migration, 'const BATCH_SIZE     = 3;' ), 'doctor batch size exactly 3' );
ok( str_contains( $migration, 'assert_upload_ready' ) && str_contains( $migration, 'is_writable' ), 'upload readiness includes writability' );

echo "\nfinal-migration-contract.php: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
