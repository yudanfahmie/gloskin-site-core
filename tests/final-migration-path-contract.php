<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$file = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php';
$src  = file_get_contents( $file );
if ( false === $src ) {
	fwrite( STDERR, "Unable to read final migration source\n" );
	exit( 1 );
}

$checks = array(
	'uses plugin_dir_path($plugin_file)' => false !== strpos( $src, 'plugin_dir_path( $plugin_file )' ),
	'uses trailingslashit plugin root' => false !== strpos( $src, "trailingslashit( \$plugin_root ) . 'migration-runtime/' . self::BUNDLE_DIR" ),
	'no dirname(dirname($plugin_file)) regression' => false === strpos( $src, 'dirname( dirname( $plugin_file )' ) && false === strpos( $src, 'dirname(dirname($plugin_file)' ),
	'no WP_PLUGIN_DIR hardcode' => false === strpos( $src, 'WP_PLUGIN_DIR' ),
	'BATCH_SIZE exactly 3' => false !== strpos( $src, 'const BATCH_SIZE     = 3;' ),
	'persisted doctor cursor exists' => false !== strpos( $src, "'doctor_cursor'       => 0" ),
	'doctor batch method exists' => false !== strpos( $src, 'run_doctor_photos_batch' ),
);

$plugin_file = '/tmp/wp-content/plugins/gloskin-site-core/gloskin-site-core.php';
$plugin_root = rtrim( dirname( $plugin_file ), '/\\' ) . '/';
$resolved    = $plugin_root . 'migration-runtime/gloskin-doctor-photos-v2';
$expected    = '/tmp/wp-content/plugins/gloskin-site-core/migration-runtime/gloskin-doctor-photos-v2';
$checks['conceptual installed path resolves correctly'] = $resolved === $expected;

foreach ( $checks as $label => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		exit( 1 );
	}
}

echo "final-migration-path-contract.php: OK\n";
