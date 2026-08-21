<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );
$kernel = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
if ( false === $plugin || false === $kernel ) {
	fwrite( STDERR, "Unable to read version owners\n" );
	exit( 1 );
}
if ( ! preg_match( '/^ \* Version: ([0-9]+\.[0-9]+\.[0-9]+)$/m', $plugin, $plugin_match ) ) {
	fwrite( STDERR, "Plugin header version missing\n" );
	exit( 1 );
}
if ( ! preg_match( "/const VERSION = '([0-9]+\\.[0-9]+\\.[0-9]+)';/", $kernel, $kernel_match ) ) {
	fwrite( STDERR, "Kernel VERSION missing\n" );
	exit( 1 );
}
$expected = '0.7.192';
if ( $plugin_match[1] !== $expected || $kernel_match[1] !== $expected ) {
	fwrite( STDERR, 'Release version mismatch: header=' . $plugin_match[1] . ', kernel=' . $kernel_match[1] . ', expected=' . $expected . "\n" );
	exit( 1 );
}

echo "release-version-contract.php: OK ({$expected})\n";
