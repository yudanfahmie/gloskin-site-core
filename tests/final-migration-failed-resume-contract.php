<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$src  = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php' );
if ( false === $src ) {
	fwrite( STDERR, "FAIL: migration source unreadable\n" );
	exit( 1 );
}

$required = array(
	"const REVISION       = '2026-08-19-final';",
	"const STATE_OPTION   = 'gloskin_site_core_revision_20260819f_state';",
	"'next_step_index'     => 0",
	"if ( self::REVISION !== (string) \$state['revision'] )",
	"case 'preflight':",
	'run_preflight()',
	"\$index = (int) \$state['next_step_index'];",
);
foreach ( $required as $needle ) {
	if ( false === strpos( $src, $needle ) ) {
		fwrite( STDERR, "FAIL: missing resume contract: {$needle}\n" );
		exit( 1 );
	}
}

$start_pos      = strpos( $src, "if ( 'start' === \$mode )" );
$continue_guard = false !== $start_pos ? strpos( $src, "if ( ! in_array( \$state['status']", $start_pos ) : false;
if ( false === $start_pos || false === $continue_guard ) {
	fwrite( STDERR, "FAIL: cannot isolate start handshake\n" );
	exit( 1 );
}
$start_block = substr( $src, $start_pos, $continue_guard - $start_pos );
$compact_start = (string) preg_replace( '/\s+/', ' ', $start_block );
foreach ( array( 'next_step_index', 'doctor_cursor', 'doctor_audit', 'doctor_all_snapshot' ) as $key ) {
	$assignment = '$state[\'' . $key . '\'] =';
	if ( false !== strpos( $compact_start, $assignment ) ) {
		fwrite( STDERR, "FAIL: start resets {$key}\n" );
		exit( 1 );
	}
}

$state = array( 'revision' => '2026-08-19-final', 'status' => 'failed', 'next_step_index' => 0 );
$after_start = $state;
$after_start['status'] = 'running';
if ( 0 !== $after_start['next_step_index'] || '2026-08-19-final' !== $after_start['revision'] ) {
	fwrite( STDERR, "FAIL: failed-state handshake would reset authoritative state\n" );
	exit( 1 );
}
$after_start['next_step_index'] = 1;
if ( 1 !== $after_start['next_step_index'] ) {
	fwrite( STDERR, "FAIL: corrected preflight cannot progress\n" );
	exit( 1 );
}

echo "final-migration-failed-resume-contract.php: OK\n";
