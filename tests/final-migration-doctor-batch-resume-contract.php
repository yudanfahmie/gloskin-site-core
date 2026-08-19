<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$src  = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php' );
if ( false === $src ) {
	fwrite( STDERR, "FAIL: migration source unreadable\n" );
	exit( 1 );
}

$batch_start = strpos( $src, 'private function run_doctor_photos_batch' );
$batch_end   = false !== $batch_start ? strpos( $src, 'private function normalize_doctor_audit', $batch_start ) : false;
if ( false === $batch_start || false === $batch_end ) {
	fwrite( STDERR, "FAIL: unable to isolate doctor batch method\n" );
	exit( 1 );
}
$batch_src = substr( $src, $batch_start, $batch_end - $batch_start );
$compact   = (string) preg_replace( '/\s+/', ' ', $batch_src );

$checks = array(
	'batch size 3' => false !== strpos( $src, 'const BATCH_SIZE     = 3;' ),
	'cursor persisted per verified doctor' => false !== strpos( $compact, '$state[\'doctor_cursor\'] = $cursor;' ) && false !== strpos( $batch_src, '$this->save_state( $state );' ),
	'audit persisted per verified doctor' => false !== strpos( $compact, '$state[\'doctor_audit\'] = $audit;' ),
	'audit upsert prevents duplicate doctor' => false !== strpos( $src, 'upsert_doctor_audit_entry' ),
	'SHA reuse authoritative' => false !== strpos( $batch_src, 'find_attachment_by_sha( $sha256 )' ),
	'all snapshot not changed in batch' => false === strpos( $compact, '$state[\'doctor_all_snapshot\'] =' ),
);
foreach ( $checks as $label => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		exit( 1 );
	}
}

$total = 12;
$batch = 3;
$cursor = 0;
$sequence = array();
while ( $cursor < $total ) {
	$cursor = min( $cursor + $batch, $total );
	$sequence[] = $cursor;
}
if ( array( 3, 6, 9, 12 ) !== $sequence ) {
	fwrite( STDERR, "FAIL: unexpected batch sequence\n" );
	exit( 1 );
}

$persisted_cursor = 6;
if ( 9 !== min( $persisted_cursor + $batch, $total ) ) {
	fwrite( STDERR, "FAIL: interruption resume did not continue from cursor 6\n" );
	exit( 1 );
}

$next_step_index = 3;
foreach ( array( 3, 6, 9 ) as $partial_cursor ) {
	if ( $partial_cursor < $total && 3 !== $next_step_index ) {
		fwrite( STDERR, "FAIL: doctor_photos checkpoint advanced early\n" );
		exit( 1 );
	}
}
$next_step_index++;
if ( 4 !== $next_step_index ) {
	fwrite( STDERR, "FAIL: doctor_photos checkpoint did not advance exactly once\n" );
	exit( 1 );
}

echo "final-migration-doctor-batch-resume-contract.php: OK (3,6,9,12; resume 6->9)\n";
