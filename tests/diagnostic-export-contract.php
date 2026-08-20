<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$admin = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php' );
$exporter = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-diagnostic-exporter.php' );
$asset = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php' );
$registry = (string) file_get_contents( $root . '/plugin/gloskin-site-core/config/assets.php' );
$javascript = (string) file_get_contents( $root . '/plugin/gloskin-site-core/assets/js/gloskin-ui1-diagnostic.js' );
$kernel = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$plugin = (string) file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );

function diagnostic_ok( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

diagnostic_ok( str_contains( $admin, "const DIAGNOSTIC_USER_LOGIN      = 'namaste';" ), 'exact namaste user_login owner missing' );
diagnostic_ok( str_contains( $admin, "const DIAGNOSTIC_CAPABILITY      = 'manage_options';" ), 'manage_options gate missing' );
diagnostic_ok( str_contains( $admin, "self::DIAGNOSTIC_USER_LOGIN === (string) \$user->user_login" ), 'case-sensitive user_login check missing' );
diagnostic_ok( str_contains( $admin, "if ( \$this->current_user_may_download_diagnostic() )" ) && str_contains( $admin, "add_submenu_page( \$parent, __( 'Download Diagnostic'" ), 'menu must be conditionally registered under Gloskin Content' );
diagnostic_ok( str_contains( $admin, "add_action( 'wp_ajax_' . self::DIAGNOSTIC_ACTION" ) && str_contains( $admin, "add_action( 'admin_post_' . self::DIAGNOSTIC_ACTION" ), 'authenticated AJAX and admin-post fallback missing' );
diagnostic_ok( ! str_contains( $admin, "wp_ajax_nopriv_' . self::DIAGNOSTIC_ACTION" ) && ! str_contains( $admin, "admin_post_nopriv_' . self::DIAGNOSTIC_ACTION" ), 'public diagnostic action must not exist' );
diagnostic_ok( str_contains( $admin, "wp_verify_nonce( \$nonce, self::DIAGNOSTIC_NONCE )" ), 'nonce verification missing' );
diagnostic_ok( strpos( $admin, 'current_user_may_download_diagnostic()' ) < strpos( $admin, '$exporter = $this->diagnostic_exporter();' ), 'authorization must happen before exporter initialization' );
diagnostic_ok( strpos( $admin, 'wp_verify_nonce( $nonce, self::DIAGNOSTIC_NONCE )' ) < strpos( $admin, '$exporter = $this->diagnostic_exporter();' ), 'nonce must be verified before exporter initialization' );
diagnostic_ok( str_contains( $admin, "require_once __DIR__ . '/class-gloskin-site-core-diagnostic-exporter.php';" ), 'exporter must load lazily in AdminService' );
diagnostic_ok( ! str_contains( $kernel, 'class-gloskin-site-core-diagnostic-exporter.php' ), 'exporter must not load in the Kernel/public branch' );

foreach ( array( 'wp_tempnam(', 'ZipArchive', 'register_shutdown_function(', '@unlink(', 'MAX_SOURCE_FILE_BYTES', 'MAX_SOURCE_TOTAL_BYTES', 'MAX_ARCHIVE_BYTES', 'MAX_ROUTE_CHECKS', 'realpath(', 'isLink()', 'manifest.json', 'promo-diagnostic.json', 'migration-state.json', 'woocommerce-boundary.json', 'media-manifest.json', 'code-manifest.json', 'runtime-health.json', 'route-checks.json' ) as $needle ) {
	diagnostic_ok( str_contains( $exporter, $needle ), "exporter safety/schema owner missing: {$needle}" );
}
diagnostic_ok( str_contains( $admin, '} finally {' ) && str_contains( $admin, '@unlink( $path )' ), 'streaming success/failure cleanup must use finally' );
diagnostic_ok( ! str_contains( $exporter, 'wc_get_orders(' ) && ! str_contains( $exporter, 'get_users(' ) && ! str_contains( $exporter, '$wpdb->users' ) && ! str_contains( $exporter, '$wpdb->usermeta' ), 'private user/order collection API detected' );
diagnostic_ok( str_contains( $exporter, "'cookies' => array()" ) && str_contains( $exporter, "'redirection' => 0" ), 'route checks must omit auth cookies and external redirect following' );
diagnostic_ok( str_contains( $asset, 'enqueue_admin_diagnostic' ) && str_contains( $registry, "'gloskin-ui1-diagnostic'" ), 'diagnostic assets must use AssetService/registry' );

foreach ( array( "var attempts = 3", 'data-gloskin-diagnostic-spinner', 'response.status >= 500', '429 === response.status', '800 * Math.pow( 2, attempt - 1 )', 'credentials: \'same-origin\'', 'window.URL.createObjectURL', 'window.URL.revokeObjectURL' ) as $needle ) {
	diagnostic_ok( str_contains( $javascript, $needle ), "AJAX loading/retry contract missing: {$needle}" );
}

diagnostic_ok( str_contains( $plugin, 'Version: 0.7.157' ) && str_contains( $kernel, "const VERSION = '0.7.157';" ), 'version must be synchronized at 0.7.157' );
echo "diagnostic-export-contract.php: OK (0.7.157)\n";
