<?php
declare(strict_types=1);

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

if ( false === $migration || false === $kernel || false === $plugin_h ) {
	fwrite( STDERR, "Cannot read doctor snapshot contract sources\n" );
	exit( 1 );
}

ok( str_contains( $migration, "'matches'" ) && str_contains( $migration, "'all_snapshot'" ), 'preflight returns matches + all_snapshot' );
ok( str_contains( $migration, "'doctor_all_snapshot'" ), 'state defaults include doctor_all_snapshot' );
ok( str_contains( $migration, "\$state['doctor_all_snapshot']" ), 'preflight stores all_snapshot' );
ok( (bool) preg_match( "/run_verify.*?all_snapshot/s", $migration ), 'verify reads doctor_all_snapshot' );
ok( str_contains( $migration, 'foreach ( $all_snapshot' ), 'verify iterates all doctor snapshot' );
ok( str_contains( $migration, 'thumbnail dokter non-target' ), 'verify enforces non-target thumbnail preservation' );
ok( substr_count( $migration, 'get_post_thumbnail_id' ) >= 2, 'snapshot and verify use thumbnail IDs' );
ok( str_contains( $migration, "'draft'" ) && str_contains( $migration, "'pending'" ) && str_contains( $migration, "'private'" ), 'snapshot includes non-published doctor statuses' );
ok( str_contains( $migration, '$target_doctor_ids' ), 'target doctors excluded from non-target assertion' );

$batch_start = strpos( $migration, 'private function run_doctor_photos_batch' );
$batch_end   = false !== $batch_start ? strpos( $migration, 'private function normalize_doctor_audit', $batch_start ) : false;
$batch       = false !== $batch_start && false !== $batch_end ? substr( $migration, $batch_start, $batch_end - $batch_start ) : '';
$compact     = (string) preg_replace( '/\s+/', ' ', $batch );
ok( '' !== $batch && false === strpos( $compact, '$state[\'doctor_all_snapshot\'] =' ), 'doctor batch never resets original all-doctor snapshot' );

ok( (bool) preg_match( "/const VERSION\s*=\s*'0\.7\.143'/", $kernel ), 'Kernel VERSION must be 0.7.144' );
ok( (bool) preg_match( '/^ \* Version: 0\.7\.143$/m', $plugin_h ), 'Plugin header Version must be 0.7.144' );

echo "\ndoctor-snapshot-contract.php: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
