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
$expected = '0.7.138';
if ( $plugin_match[1] !== $expected || $kernel_match[1] !== $expected ) {
	fwrite( STDERR, 'Release version mismatch: header=' . $plugin_match[1] . ', kernel=' . $kernel_match[1] . ', expected=' . $expected . "\n" );
	exit( 1 );
}

$patterns = array(
	'/(?:===|==)\s*[\'\"](0\.7\.\d+)[\'\"]/',
	'/const VERSION\s*=\s*[\'\"](0\.7\.\d+)[\'\"]/',
	'/\$expected\s*=\s*[\'\"](0\.7\.\d+)[\'\"]/',
);
$tests = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/tests', FilesystemIterator::SKIP_DOTS ) );
foreach ( $tests as $file ) {
	if ( ! $file->isFile() ) { continue; }
	$contents = file_get_contents( $file->getPathname() );
	if ( false === $contents ) { fwrite( STDERR, 'Unable to read test file: ' . $file->getPathname() . "\n" ); exit( 1 ); }
	foreach ( $patterns as $pattern ) {
		if ( ! preg_match_all( $pattern, $contents, $matches ) ) { continue; }
		foreach ( array_unique( $matches[1] ) as $version ) {
			if ( $version !== $expected ) {
				fwrite( STDERR, 'Stale active release assertion in ' . $file->getPathname() . ': ' . $version . "\n" );
				exit( 1 );
			}
		}
	}
}

echo "release-version-contract.php: OK ({$expected})\n";
