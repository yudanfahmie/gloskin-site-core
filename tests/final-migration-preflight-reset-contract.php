<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$src  = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php' );
if ( false === $src ) {
	fwrite( STDERR, "FAIL: migration source unreadable\n" );
	exit( 1 );
}
$case = strpos( $src, "case 'preflight':" );
$next = false !== $case ? strpos( $src, "case 'managed_content':", $case ) : false;
if ( false === $case || false === $next ) {
	fwrite( STDERR, "FAIL: cannot isolate preflight checkpoint\n" );
	exit( 1 );
}
$block   = substr( $src, $case, $next - $case );
$compact = (string) preg_replace( '/\s+/', ' ', $block );

$checks = array(
	'index zero guard' => false !== strpos( $block, '0 !== $index' ),
	'cursor mutation guard' => false !== strpos( $compact, "(int) \$state['doctor_cursor'] > 0" ),
	'audit mutation guard' => false !== strpos( $compact, "\$this->doctor_audit_count( \$state['doctor_audit'] ) > 0" ),
	'cursor reset remains in genuine preflight' => false !== strpos( $compact, '$state[\'doctor_cursor\'] = 0;' ),
	'audit reset remains in genuine preflight' => false !== strpos( $compact, '$state[\'doctor_audit\'] = array();' ),
);
foreach ( $checks as $label => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		exit( 1 );
	}
}

echo "final-migration-preflight-reset-contract.php: OK\n";
