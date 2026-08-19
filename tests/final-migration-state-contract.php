<?php
declare(strict_types=1);

/**
 * final-migration-state-contract.php
 *
 * Static contract: response-state keys, error classification, and failed-state
 * resume path for the v0.7.142 hotfix.
 *
 * Asserts:
 *   1.  response_state() returns 'processed_steps' (not 'processed_products').
 *   2.  response_state() returns 'total_steps' (not 'expected_products').
 *   3.  response_state() returns 'status', 'current_step', 'last_error'.
 *   4.  'failed' is in the allowed-status list for mode=continue in advance().
 *   5.  ajax_advance() returns structured error with 'code', 'message', 'step', 'retryable'.
 *   6.  classify_error() method exists in admin class.
 *   7.  bundle_unavailable code is detectable by classify_error().
 *   8.  bundle_invalid code is detectable.
 *   9.  doctor_unmatched code is detectable.
 *   10. doctor_ambiguous code is detectable.
 *   11. upload_unavailable code is detectable.
 *   12. migration_locked code is detectable from 'sedang diproses' message text.
 *   13. 'processed_products' string is absent from the new JS file.
 *   14. JS reads 'processed_steps' from state response.
 *   15. Plugin / Kernel version synchronized at 0.7.139.
 */

$root    = dirname( __DIR__ );
$passed  = 0;
$failed  = 0;

function ok_state( bool $cond, string $msg ): void {
	global $passed, $failed;
	if ( $cond ) {
		echo "ok: {$msg}\n";
		$passed++;
	} else {
		echo "FAIL: {$msg}\n";
		$failed++;
	}
}

$migration  = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php' );
$admin      = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration-admin.php' );
$js         = file_get_contents( $root . '/plugin/gloskin-site-core/assets/js/gloskin-ui1-final-migration.js' );
$kernel     = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$plugin_h   = file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );

foreach ( array( 'migration' => $migration, 'admin' => $admin, 'js' => $js, 'kernel' => $kernel, 'plugin_h' => $plugin_h ) as $name => $content ) {
	if ( false === $content ) { fwrite( STDERR, "Cannot read {$name}\n" ); exit( 1 ); }
}

/* 1. response_state() uses processed_steps */
ok_state(
	str_contains( $migration, "'processed_steps'" ),
	"response_state() must return 'processed_steps'"
);

/* 2. response_state() uses total_steps */
ok_state(
	str_contains( $migration, "'total_steps'" ),
	"response_state() must return 'total_steps'"
);

/* 3. response_state() returns status, current_step, last_error */
ok_state(
	str_contains( $migration, "'status'" ) &&
	str_contains( $migration, "'current_step'" ) &&
	str_contains( $migration, "'last_error'" ),
	"response_state() must return status, current_step, last_error"
);

/* 4. 'failed' allowed in advance() for mode=continue */
ok_state(
	(bool) preg_match( "/'failed'/", $migration ) &&
	(bool) preg_match( "/in_array\(\s*\\\$state\['status'\]\s*,\s*array\([^)]*'failed'/", $migration ),
	"advance() must allow 'failed' status for mode=continue"
);

/* 5. ajax_advance() returns structured error with code, message, step, retryable */
ok_state(
	str_contains( $admin, "'code'" ) &&
	str_contains( $admin, "'message'" ) &&
	str_contains( $admin, "'step'" ) &&
	str_contains( $admin, "'retryable'" ),
	"ajax_advance() must return structured error with code, message, step, retryable"
);

/* 6. classify_error() method exists */
ok_state(
	str_contains( $admin, 'function classify_error' ),
	"Admin class must define classify_error() method"
);

/* 7-11. Typed error codes recognised by classify_error() */
foreach ( array( 'bundle_unavailable', 'bundle_invalid', 'doctor_unmatched', 'doctor_ambiguous', 'upload_unavailable' ) as $code ) {
	ok_state(
		str_contains( $admin, "'$code'" ) || str_contains( $admin, "\"$code\"" ),
		"classify_error() must recognise error code: $code"
	);
}

/* 12. migration_locked detected from 'sedang diproses' */
ok_state(
	str_contains( $admin, 'sedang diproses' ) && str_contains( $admin, 'migration_locked' ),
	"classify_error() must map 'sedang diproses' to migration_locked"
);

/* 13. 'processed_products' absent from new JS */
ok_state(
	! str_contains( $js, 'processed_products' ),
	"New JS must not reference processed_products (wrong key from old import JS)"
);

/* 14. JS reads processed_steps from state response */
ok_state(
	str_contains( $js, 'processed_steps' ),
	"New JS must read processed_steps from AJAX state response"
);

/* 15. Version sync */
ok_state( (bool) preg_match( "/const VERSION\s*=\s*'0\.7\.142'/", $kernel ), 'Kernel VERSION must be 0.7.142' );
ok_state( (bool) preg_match( '/^ \* Version: 0\.7\.142$/m', $plugin_h ), 'Plugin header Version must be 0.7.142' );

echo "\nfinal-migration-state-contract.php: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
