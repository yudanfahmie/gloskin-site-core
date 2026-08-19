<?php
declare(strict_types=1);

/**
 * final-migration-path-contract.php
 *
 * Static contract: v0.7.139 hotfix — bundle path and batch state.
 *
 * Asserts:
 *   1.  Constructor uses plugin_dir_path() not dirname/dirname.
 *   2.  dirname(dirname($plugin_file)) pattern is ABSENT from constructor.
 *   3.  trailingslashit() applied to plugin_root before appending bundle dir.
 *   4.  BATCH_SIZE constant is defined.
 *   5.  BATCH_SIZE value is a positive integer (>= 1).
 *   6.  doctor_cursor key is in state defaults.
 *   7.  run_doctor_photos_batch() method exists.
 *   8.  run_to_completion() loop limit exceeds count(steps) alone (batch headroom).
 *   9.  advance() case doctor_photos calls run_doctor_photos_batch() not run_doctor_photos() directly.
 *   10. $step_complete flag is used to conditionally advance next_step_index.
 *   11. doctor_cursor is reset to 0 in the preflight case of advance().
 *   12. Plugin / Kernel version synchronized at 0.7.139.
 */

$root   = dirname( __DIR__ );
$passed = 0;
$failed = 0;

function ok_139path( bool $cond, string $msg ): void {
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

/* 1. plugin_dir_path() used in constructor */
ok_139path(
	str_contains( $migration, 'plugin_dir_path( $plugin_file )' ),
	'Constructor must call plugin_dir_path( \$plugin_file )'
);

/* 2. dirname(dirname()) pattern absent from constructor */
ok_139path(
	! (bool) preg_match( '/public function __construct.*?dirname\s*\(\s*dirname/s', $migration ),
	'dirname(dirname()) must NOT appear inside __construct()'
);

/* 3. trailingslashit() applied to plugin root */
ok_139path(
	str_contains( $migration, 'trailingslashit( $plugin_root )' ),
	'trailingslashit( \$plugin_root ) must be used before appending bundle dir'
);

/* 4. BATCH_SIZE constant defined */
ok_139path(
	str_contains( $migration, "const BATCH_SIZE" ),
	'BATCH_SIZE constant must be defined'
);

/* 5. BATCH_SIZE >= 1 */
ok_139path(
	(bool) preg_match( '/const BATCH_SIZE\s*=\s*([1-9][0-9]*)/', $migration ),
	'BATCH_SIZE must be a positive integer'
);

/* 6. doctor_cursor in state defaults */
ok_139path(
	str_contains( $migration, "'doctor_cursor'" ),
	"State defaults must include 'doctor_cursor' key"
);

/* 7. run_doctor_photos_batch() method exists */
ok_139path(
	str_contains( $migration, 'function run_doctor_photos_batch' ),
	'run_doctor_photos_batch() method must exist'
);

/* 8. Loop limit in run_to_completion() has batch headroom (> count(steps)+3) */
ok_139path(
	(bool) preg_match( '/\$limit\s*=\s*count\(\s*\$this->steps\(\)\s*\)\s*\+\s*(\d+)/', $migration, $m ) && (int) $m[1] > 3,
	'run_to_completion() loop limit must have batch headroom (addend > 3)'
);

/* 9. doctor_photos case calls run_doctor_photos_batch() not run_doctor_photos() */
ok_139path(
	str_contains( $migration, 'run_doctor_photos_batch( $state )' ) &&
	! (bool) preg_match( "/case 'doctor_photos'.*?run_doctor_photos\s*\(/s", $migration ),
	"advance() case 'doctor_photos' must call run_doctor_photos_batch() not run_doctor_photos()"
);

/* 10. $step_complete flag used to gate next_step_index increment */
ok_139path(
	str_contains( $migration, '$step_complete' ) &&
	str_contains( $migration, 'if ( $step_complete )' ),
	'$step_complete flag must be used to conditionally advance next_step_index'
);

/* 11. doctor_cursor reset in preflight case */
ok_139path(
	str_contains( $migration, "'doctor_cursor']       = 0" ) ||
	str_contains( $migration, "'doctor_cursor'] = 0" ),
	'doctor_cursor must be reset to 0 inside the preflight case of advance()'
);

/* 12. Version sync */
ok_139path( (bool) preg_match( "/const VERSION\s*=\s*'0\.7\.139'/", $kernel ), 'Kernel VERSION must be 0.7.139' );
ok_139path( (bool) preg_match( '/^ \* Version: 0\.7\.139$/m', $plugin_h ), 'Plugin header Version must be 0.7.139' );

echo "\nfinal-migration-path-contract.php: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
