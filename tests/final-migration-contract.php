<?php
declare(strict_types=1);

/**
 * final-migration-contract.php
 *
 * Static contract test for the 2026-08-19-final one-shot migration.
 *
 * Asserts:
 *   1.  Migration class REVISION constant = '2026-08-19-final'.
 *   2.  Migration uses own STATE_OPTION (not shared with prior revision).
 *   3.  Migration uses own LOCK_OPTION (not shared with prior revision).
 *   4.  import_doctor_photo() uses wp_unique_filename() — not bare SHA prefix + copy.
 *   5.  find_attachment_by_sha() searches by ATTACH_SHA256_META.
 *   6.  run_verify() has per-doctor thumbnail loop (exact-set assertions).
 *   7.  run_verify() checks for duplicate SHA in audit.
 *   8.  Admin class title is "Finalisasi Prototype & Data".
 *   9.  Admin handle_fallback() redirects to Content Overview on success.
 *   10. Admin SLUG is distinct from prior revision's slug.
 *   11. Admin POST_ACTION is distinct from prior revision's action.
 *   12. Kernel registers the final migration admin (not the old one).
 *   13. Kernel VERSION = 0.7.137.
 *   14. Plugin header Version = 0.7.137.
 *   15. CONSERVATIVE matching: no fuzzy/Levenshtein/AI in migration class.
 *   16. No WooCommerce data touch: migration does not call wp_delete_post on products.
 *   17. Demo records default to draft on production (environment check present).
 */

$root   = dirname( __DIR__ );
$passed = 0;
$failed = 0;

function ok( bool $cond, string $msg ): void {
	global $passed, $failed;
	if ( $cond ) {
		echo "ok: {$msg}\n";
		$passed++;
	} else {
		echo "FAIL: {$msg}\n";
		$failed++;
	}
}

$migration = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php' );
$admin     = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration-admin.php' );
$kernel    = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$plugin_h  = file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );

if ( false === $migration ) { fwrite( STDERR, "Cannot read final migration file\n" ); exit( 1 ); }
if ( false === $admin     ) { fwrite( STDERR, "Cannot read final migration admin file\n" ); exit( 1 ); }
if ( false === $kernel    ) { fwrite( STDERR, "Cannot read kernel file\n" ); exit( 1 ); }
if ( false === $plugin_h  ) { fwrite( STDERR, "Cannot read plugin header file\n" ); exit( 1 ); }

/* 1. REVISION constant */
ok( (bool) preg_match( "/const REVISION\s*=\s*'2026-08-19-final'/", $migration ), "REVISION must be '2026-08-19-final'" );

/* 2. Own STATE_OPTION (not shared with prior revision — must differ) */
ok( str_contains( $migration, 'gloskin_site_core_revision_20260819f_state' ), 'STATE_OPTION must use _20260819f_ suffix (own, not shared)' );
ok( ! str_contains( $migration, 'gloskin_site_core_revision_20260819_state' ), 'Final migration must not reuse prior STATE_OPTION' );

/* 3. Own LOCK_OPTION */
ok( str_contains( $migration, 'gloskin_site_core_revision_20260819f_lock' ), 'LOCK_OPTION must use _20260819f_ suffix' );
ok( ! str_contains( $migration, 'gloskin_site_core_revision_20260819_lock' ), 'Final migration must not reuse prior LOCK_OPTION' );

/* 4. wp_unique_filename() used in import_doctor_photo */
ok( str_contains( $migration, 'wp_unique_filename' ), 'import_doctor_photo() must use wp_unique_filename()' );

/* 5. find_attachment_by_sha searches ATTACH_SHA256_META */
ok( str_contains( $migration, 'ATTACH_SHA256_META' ), 'find_attachment_by_sha() must query by ATTACH_SHA256_META' );

/* 6. Per-doctor thumbnail loop in run_verify */
ok( str_contains( $migration, 'get_post_thumbnail_id' ) && str_contains( $migration, '$doctor_id' ),
	'run_verify() must check per-doctor thumbnail (get_post_thumbnail_id per doctor)' );

/* 7. Duplicate SHA check in run_verify */
ok( str_contains( $migration, 'SHA-256 duplikat' ) || str_contains( $migration, 'duplicate' ) || str_contains( $migration, '$seen_shas' ),
	'run_verify() must assert no duplicate SHA in audit set' );

/* 8. Admin title */
ok( str_contains( $admin, 'Finalisasi Prototype & Data' ), "Admin page title must be 'Finalisasi Prototype & Data'" );

/* 9. Admin redirects to Content Overview on success */
ok( str_contains( $admin, 'ADMIN_MENU_SLUG' ) && str_contains( $admin, 'wp_redirect' ),
	'Admin handle_fallback() must redirect to Content Overview (ADMIN_MENU_SLUG) on success' );

/* 10. Admin SLUG distinct from prior revision */
ok( str_contains( $admin, 'gloskin-revision-20260819-final-migration' ), 'Admin SLUG must use -final- suffix' );
ok( ! preg_match( "/const SLUG\s*=\s*'gloskin-revision-20260819-migration'/", $admin ), 'Admin SLUG must differ from prior revision' );

/* 11. Admin POST_ACTION distinct */
ok( str_contains( $admin, 'gloskin_site_core_revision_20260819_final_migration_fallback' ), 'Admin POST_ACTION must use final suffix' );

/* 12. Kernel registers final migration admin */
ok( str_contains( $kernel, 'class-gloskin-site-core-revision-20260819-final-migration-admin.php' ), 'Kernel must require final migration admin file' );
ok( str_contains( $kernel, 'Gloskin_Site_Core_Revision_20260819_Final_Migration_Admin' ), 'Kernel must instantiate Final_Migration_Admin' );
ok( ! str_contains( $kernel, 'class-gloskin-site-core-revision-20260819-migration-admin.php' ), 'Kernel must not require the old (non-final) migration admin' );

/* 13. Kernel VERSION */
ok( (bool) preg_match( "/const VERSION\s*=\s*'0\.7\.137'/", $kernel ), "Kernel VERSION must be 0.7.137" );

/* 14. Plugin header */
ok( (bool) preg_match( '/^ \* Version: 0\.7\.137$/m', $plugin_h ), "Plugin header Version must be 0.7.137" );

/* 15. CONSERVATIVE matching (no fuzzy function calls: levenshtein(), soundex(), similar_text()) */
ok( ! preg_match( '/\blevenshtein\s*\(/', $migration ) && ! preg_match( '/\bsoundex\s*\(/', $migration ) && ! preg_match( '/\bsimilar_text\s*\(/', $migration ),
	'Migration must not call fuzzy matching functions (levenshtein/soundex/similar_text)' );

/* 16. No product deletion */
ok( ! preg_match( "/wp_delete_post\s*\([^)]*product/", $migration ), 'Migration must not delete WooCommerce products' );

/* 17. Environment check for demo status */
ok( str_contains( $migration, 'detect_environment' ) && str_contains( $migration, "'draft'" ),
	"Demo records must default to draft on production (environment detection present)" );

/* Summary */
echo "\nfinal-migration-contract.php: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
