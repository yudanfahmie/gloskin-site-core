<?php
declare(strict_types=1);

$root  = dirname( __DIR__ );
$mig   = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php' );
$admin = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration-admin.php' );
if ( false === $mig || false === $admin ) {
	fwrite( STDERR, "FAIL: error contract sources unreadable\n" );
	exit( 1 );
}

$codes = array(
	'bundle_unavailable',
	'bundle_invalid',
	'doctor_unmatched',
	'doctor_ambiguous',
	'upload_unavailable',
	'normalize_failed',
	'verification_failed',
	'migration_locked',
	'unexpected_error',
);
foreach ( $codes as $code ) {
	if ( false === strpos( $admin, "'{$code}'" ) ) {
		fwrite( STDERR, "FAIL: missing admin error classification {$code}\n" );
		exit( 1 );
	}
}
foreach ( array( 'bundle_unavailable', 'bundle_invalid', 'doctor_unmatched', 'doctor_ambiguous', 'upload_unavailable', 'verification_failed', 'migration_locked' ) as $code ) {
	if ( false === strpos( $mig, $code . ':' ) ) {
		fwrite( STDERR, "FAIL: migration never emits {$code}\n" );
		exit( 1 );
	}
}
if ( false === strpos( $admin, "'retryable' => \$this->is_retryable_error( \$code )" ) ) {
	fwrite( STDERR, "FAIL: retryable not structured\n" );
	exit( 1 );
}
if ( false === strpos( $admin, "'step'      => isset( \$state['current_step'] )" ) ) {
	fwrite( STDERR, "FAIL: error step is not current checkpoint\n" );
	exit( 1 );
}
if ( false !== strpos( $admin, "'message'   => \$error->getMessage()" ) ) {
	fwrite( STDERR, "FAIL: raw exception message exposed to AJAX admin\n" );
	exit( 1 );
}
if ( false === strpos( $admin, 'Paket foto dokter belum tersedia di instalasi plugin.' ) ) {
	fwrite( STDERR, "FAIL: safe bundle_unavailable message missing\n" );
	exit( 1 );
}
if ( false === strpos( $admin, 'Finalisasi sedang diproses oleh request lain.' ) ) {
	fwrite( STDERR, "FAIL: safe migration_locked message missing\n" );
	exit( 1 );
}

echo "final-migration-error-contract.php: OK\n";
