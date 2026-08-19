<?php
declare(strict_types=1);

/**
 * final-migration-batch-contract.php
 *
 * Static contract: doctor photo batch processing and cursor-based resume for
 * the v0.7.141 hotfix.
 *
 * Asserts:
 *   1.  BATCH_SIZE constant is defined and equals 3.
 *   2.  doctor_cursor key exists in state defaults (default 0).
 *   3.  run_doctor_photos_batch() private method exists.
 *   4.  Batch method reads cursor from state ('doctor_cursor').
 *   5.  Batch method accumulates results from prior batches (merges applied/reused).
 *   6.  Batch method returns a 'complete' boolean.
 *   7.  Batch method returns 'cursor' and 'total' for progress display.
 *   8.  advance() case doctor_photos calls run_doctor_photos_batch().
 *   9.  $step_complete flag set to false when batch is not complete.
 *   10. next_step_index gated on $step_complete (not unconditional).
 *   11. doctor_cursor reset to 0 in the preflight case (on re-run).
 *   12. doctor_audit reset to array() in the preflight case (on re-run).
 *   13. upload_unavailable: error thrown if wp_upload_dir() returns error.
 *   14. run_to_completion() loop limit > count(steps) + 3 (batch headroom).
 *   15. Plugin / Kernel version synchronized at 0.7.141.
 */

$root   = dirname( __DIR__ );
$passed = 0;
$failed = 0;

function ok_batch( bool $cond, string $msg ): void {
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
$kernel    = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$plugin_h  = file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );

if ( false === $migration ) { fwrite( STDERR, "Cannot read final migration file\n" ); exit( 1 ); }
if ( false === $kernel    ) { fwrite( STDERR, "Cannot read kernel file\n" ); exit( 1 ); }
if ( false === $plugin_h  ) { fwrite( STDERR, "Cannot read plugin header file\n" ); exit( 1 ); }

/* 1. BATCH_SIZE = 3 */
ok_batch(
	(bool) preg_match( '/const BATCH_SIZE\s*=\s*3\s*;/', $migration ),
	'BATCH_SIZE constant must equal 3'
);

/* 2. doctor_cursor in state defaults */
ok_batch(
	str_contains( $migration, "'doctor_cursor'" ),
	"State defaults must include 'doctor_cursor' key"
);

/* 3. run_doctor_photos_batch() exists */
ok_batch(
	str_contains( $migration, 'function run_doctor_photos_batch' ),
	'run_doctor_photos_batch() method must be defined'
);

/* 4. Batch method reads doctor_cursor from state */
ok_batch(
	str_contains( $migration, "'doctor_cursor'" ) &&
	(bool) preg_match( "/run_doctor_photos_batch.*?doctor_cursor/s", $migration ),
	"run_doctor_photos_batch() must read doctor_cursor from \$state"
);

/* 5. Batch method merges applied/reused from prior batches */
ok_batch(
	str_contains( $migration, "'applied'" ) && str_contains( $migration, "'reused'" ),
	"run_doctor_photos_batch() must merge 'applied' and 'reused' from existing audit"
);

/* 6. Batch returns 'complete' boolean */
ok_batch(
	str_contains( $migration, "'complete'" ),
	"run_doctor_photos_batch() must return 'complete' boolean"
);

/* 7. Batch returns cursor and total */
ok_batch(
	str_contains( $migration, "'cursor'" ) && str_contains( $migration, "'total'" ),
	"run_doctor_photos_batch() must return 'cursor' and 'total' for progress display"
);

/* 8. advance() doctor_photos case calls run_doctor_photos_batch */
ok_batch(
	(bool) preg_match( "/case 'doctor_photos'.*?run_doctor_photos_batch/s", $migration ),
	"advance() case 'doctor_photos' must call run_doctor_photos_batch()"
);

/* 9. $step_complete set to false when batch incomplete */
ok_batch(
	str_contains( $migration, '$step_complete         = false' ) ||
	str_contains( $migration, '$step_complete = false' ),
	"\$step_complete must be set to false when batch is not complete"
);

/* 10. next_step_index gated on $step_complete */
ok_batch(
	str_contains( $migration, 'if ( $step_complete )' ) &&
	(bool) preg_match( "/if \( \\\$step_complete \).*?next_step_index/s", $migration ),
	'next_step_index increment must be gated on $step_complete'
);

/* 11. doctor_cursor reset to 0 in preflight case */
ok_batch(
	str_contains( $migration, "'doctor_cursor']       = 0" ) ||
	str_contains( $migration, "'doctor_cursor'] = 0" ),
	"preflight case must reset doctor_cursor to 0"
);

/* 12. doctor_audit reset in preflight case */
ok_batch(
	str_contains( $migration, "'doctor_audit']        = array()" ) ||
	str_contains( $migration, "'doctor_audit'] = array()" ),
	"preflight case must reset doctor_audit to empty array"
);

/* 13. upload_unavailable: thrown when wp_upload_dir() returns error */
ok_batch(
	str_contains( $migration, 'upload_unavailable:' ),
	"run_doctor_photos_batch() must throw 'upload_unavailable:' error when uploads unavailable"
);

/* 14. run_to_completion() loop limit has batch headroom */
$has_headroom = (bool) preg_match( '/\$limit\s*=\s*count\(\s*\$this->steps\(\)\s*\)\s*\+\s*(\d+)/', $migration, $m )
	&& (int) $m[1] > 3;
ok_batch(
	$has_headroom,
	'run_to_completion() loop limit must have batch headroom (addend > 3)'
);

/* 15. Version sync */
ok_batch( (bool) preg_match( "/const VERSION\s*=\s*'0\.7\.141'/", $kernel ), 'Kernel VERSION must be 0.7.141' );
ok_batch( (bool) preg_match( '/^ \* Version: 0\.7\.141$/m', $plugin_h ), 'Plugin header Version must be 0.7.141' );

echo "\nfinal-migration-batch-contract.php: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
