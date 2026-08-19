<?php
declare(strict_types=1);

/**
 * doctor-snapshot-contract.php
 *
 * Static contract test for the all-doctor thumbnail snapshot in the final migration.
 *
 * Asserts:
 *   1.  run_preflight() returns a structured result with 'matches' and 'all_snapshot' keys.
 *   2.  'doctor_all_snapshot' key is in state defaults.
 *   3.  Preflight stores the all_snapshot into state.doctor_all_snapshot.
 *   4.  run_verify() reads doctor_all_snapshot from state.
 *   5.  run_verify() iterates non-target doctors from the snapshot.
 *   6.  run_verify() asserts non-target thumbnail unchanged (before vs after).
 *   7.  Snapshot uses get_post_thumbnail_id() per doctor.
 *   8.  Snapshot fetches ALL doctors (publish + draft + pending + private).
 *   9.  Target doctors are excluded from non-target check.
 *   10. Verify throws RuntimeException when a non-target thumbnail changes.
 *   11. Snapshot is stored before any mutation (in preflight, not in doctor_photos step).
 *   12. Plugin / Kernel version synchronized at 0.7.138.
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
$kernel    = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$plugin_h  = file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );

if ( false === $migration ) { fwrite( STDERR, "Cannot read final migration file\n" ); exit( 1 ); }
if ( false === $kernel    ) { fwrite( STDERR, "Cannot read kernel file\n" ); exit( 1 ); }
if ( false === $plugin_h  ) { fwrite( STDERR, "Cannot read plugin header file\n" ); exit( 1 ); }

/* 1. run_preflight() returns structured result with matches + all_snapshot */
ok(
	str_contains( $migration, "'matches'" ) && str_contains( $migration, "'all_snapshot'" ),
	"run_preflight() must return array with 'matches' and 'all_snapshot' keys"
);

/* 2. doctor_all_snapshot in state defaults */
ok(
	str_contains( $migration, "'doctor_all_snapshot'" ),
	"State defaults must include 'doctor_all_snapshot' key"
);

/* 3. Preflight result stored into state.doctor_all_snapshot */
ok(
	str_contains( $migration, "state['doctor_all_snapshot']" ),
	"Preflight result must be stored as \$state['doctor_all_snapshot']"
);

/* 4. run_verify() reads doctor_all_snapshot from state */
ok(
	(bool) preg_match( "/run_verify.*?all_snapshot/s", $migration ),
	"run_verify() must read doctor_all_snapshot from state"
);

/* 5. run_verify() iterates non-target doctors from snapshot */
ok(
	str_contains( $migration, 'foreach ( $all_snapshot' ) || str_contains( $migration, 'foreach ($all_snapshot' ),
	"run_verify() must iterate over all_snapshot to check non-target doctors"
);

/* 6. Non-target thumbnail unchanged assertion */
ok(
	str_contains( $migration, 'non-target' ) || str_contains( $migration, 'non_target' ),
	"run_verify() must assert non-target doctor thumbnails are unchanged"
);

/* 7. Uses get_post_thumbnail_id per doctor in snapshot */
ok(
	substr_count( $migration, 'get_post_thumbnail_id' ) >= 2,
	"Migration must call get_post_thumbnail_id() at least twice (preflight snapshot + verify)"
);

/* 8. Snapshot fetches ALL doctors (publish + draft + pending + private) */
ok(
	str_contains( $migration, "'draft'" ) && str_contains( $migration, "'pending'" ) && str_contains( $migration, "'private'" ),
	"All-doctor query must include draft, pending, and private post statuses"
);

/* 9. Target doctors excluded from non-target check */
ok(
	str_contains( $migration, '$target_doctor_ids' ),
	"Non-target check must build \$target_doctor_ids set and skip them"
);

/* 10. Throws RuntimeException when non-target thumbnail changes */
ok(
	str_contains( $migration, 'thumbnail dokter non-target' ) ||
	( str_contains( $migration, 'non-target' ) && str_contains( $migration, 'RuntimeException' ) ),
	"run_verify() must throw RuntimeException when non-target thumbnail changes"
);

/* 11. Snapshot stored in preflight (not in doctor_photos step) */
ok(
	(bool) preg_match( "/run_preflight.*?all_snapshot/s", $migration ),
	"all_snapshot must be collected inside run_preflight() before any mutation"
);

/* 12. Version sync */
ok( (bool) preg_match( "/const VERSION\s*=\s*'0\.7\.138'/", $kernel ), "Kernel VERSION must be 0.7.138" );
ok( (bool) preg_match( '/^ \* Version: 0\.7\.138$/m', $plugin_h ), "Plugin header Version must be 0.7.138" );

/* Summary */
echo "\ndoctor-snapshot-contract.php: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
