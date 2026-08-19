<?php
declare(strict_types=1);

$root   = dirname( __DIR__ );
$bundle = $root . '/plugin/gloskin-site-core/migration-runtime/gloskin-doctor-photos-v2';
$path   = $bundle . '/manifest.json';

if ( ! is_readable( $path ) ) {
	fwrite( STDERR, "FAIL: deploy manifest unreadable\n" );
	exit( 1 );
}
$manifest = json_decode( (string) file_get_contents( $path ), true );
if ( ! is_array( $manifest ) ) {
	fwrite( STDERR, "FAIL: deploy manifest invalid JSON\n" );
	exit( 1 );
}
if ( 'gloskin-doctor-photos-v2' !== (string) ( $manifest['bundle_id'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: bundle_id mismatch\n" );
	exit( 1 );
}
$doctors = $manifest['doctors'] ?? null;
if ( ! is_array( $doctors ) || 12 !== count( $doctors ) ) {
	fwrite( STDERR, "FAIL: expected exactly 12 doctor entries\n" );
	exit( 1 );
}
$seen = array();
foreach ( $doctors as $index => $doctor ) {
	$file = (string) ( $doctor['primary_webp'] ?? '' );
	$sha  = strtolower( (string) ( $doctor['primary_sha256'] ?? '' ) );
	if ( '' === $file || isset( $seen[ $file ] ) ) {
		fwrite( STDERR, "FAIL: missing/duplicate primary_webp at {$index}\n" );
		exit( 1 );
	}
	$seen[ $file ] = true;
	$asset = $bundle . '/' . $file;
	if ( ! is_file( $asset ) || ! is_readable( $asset ) ) {
		fwrite( STDERR, "FAIL: missing deploy WebP {$file}\n" );
		exit( 1 );
	}
	$actual = strtolower( (string) hash_file( 'sha256', $asset ) );
	if ( 64 !== strlen( $sha ) || ! hash_equals( $sha, $actual ) ) {
		fwrite( STDERR, "FAIL: SHA mismatch {$file}\n" );
		exit( 1 );
	}
}

echo "final-migration-package-integrity.php: OK (manifest + 12 WebPs + SHA-256)\n";
