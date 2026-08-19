<?php
declare(strict_types=1);

$root  = dirname( __DIR__ );
$admin = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration-admin.php' );
$js    = file_get_contents( $root . '/plugin/gloskin-site-core/assets/js/gloskin-ui1-final-migration.js' );

if ( false === $admin || false === $js ) {
	fwrite( STDERR, "FAIL: unable to read final migration DOM/AJAX sources\n" );
	exit( 1 );
}

$asset_service_file = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php';
$assets_file        = $root . '/plugin/gloskin-site-core/config/assets.php';
$asset_service      = is_readable( $asset_service_file ) ? file_get_contents( $asset_service_file ) : '';
$assets             = is_readable( $assets_file ) ? file_get_contents( $assets_file ) : '';

$selectors = array(
	'data-gloskin-final-migration',
	'data-gloskin-migration-form',
	'data-gloskin-migration-run',
	'data-gloskin-migration-progressbar',
	'data-gloskin-migration-step',
	'data-gloskin-migration-error',
);
foreach ( $selectors as $selector ) {
	if ( false === strpos( $admin, $selector ) || false === strpos( $js, $selector ) ) {
		fwrite( STDERR, "FAIL: selector contract mismatch: {$selector}\n" );
		exit( 1 );
	}
}

$checks = array(
	'admin calls dedicated final migration enqueue' => false !== strpos( $admin, 'enqueue_admin_final_migration' ),
	'form submit is intercepted' => false !== strpos( $js, "addEventListener( 'submit'" ) && false !== strpos( $js, 'preventDefault()' ),
	'processed_steps used' => false !== strpos( $js, 'processed_steps' ),
	'total_steps used' => false !== strpos( $js, 'total_steps' ),
	'root status synchronized' => false !== strpos( $js, "root.setAttribute( 'data-status'" ),
	'root processed synchronized' => false !== strpos( $js, "root.setAttribute( 'data-processed'" ),
	'root total synchronized' => false !== strpos( $js, "root.setAttribute( 'data-total'" ),
	'failure forces failed status' => false !== strpos( $js, "root.setAttribute( 'data-status', 'failed' )" ),
	'no processed_products assumption' => false === strpos( $js, 'processed_products' ) && false === strpos( $admin, 'processed_products' ),
	'no expected_products assumption' => false === strpos( $js, 'expected_products' ) && false === strpos( $admin, 'expected_products' ),
	'final admin does not call sample importer' => false === strpos( $admin, 'enqueue_admin_migration(' ) && false === strpos( $admin, 'gloskin-ui1-sample-import' ),
);

if ( '' !== $asset_service || '' !== $assets ) {
	$checks['dedicated asset method exists'] = false !== strpos( $asset_service, 'enqueue_admin_final_migration' );
	$checks['dedicated script registered/enqueued'] = false !== strpos( $asset_service, "wp_register_script( 'gloskin-ui1-final-migration'" )
		&& false !== strpos( $asset_service, "wp_enqueue_script( 'gloskin-ui1-final-migration'" )
		&& false !== strpos( $assets, "'gloskin-ui1-final-migration'" )
		&& false !== strpos( $assets, "'src'  => 'assets/js/gloskin-ui1-final-migration.js'" );
}

foreach ( $checks as $label => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		exit( 1 );
	}
}

echo "final-migration-dom-ajax-contract.php: OK\n";
