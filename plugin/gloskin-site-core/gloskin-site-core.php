<?php
/**
 * Plugin Name: Gloskin Site Core
 * Description: Owns the Gloskin front-end shell, navigation, content structures, and WooCommerce/form integrations without rebuilding WordPress or WooCommerce primitives.
 * Version: 0.7.223
 * Author: Gloskin
 * Requires PHP: 7.4
 * Text Domain: gloskin-site-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-gloskin-site-core-kernel.php';

/**
 * Bootstrap the plugin after all plugins are available so integration checks can run.
 */
function gloskin_site_core_bootstrap() {
	$kernel = new Gloskin_Site_Core_Kernel( __FILE__ );
	$kernel->boot();
}
add_action( 'plugins_loaded', 'gloskin_site_core_bootstrap', 20 );
