<?php
/**
 * Plugin Name: Gloskin Site Core
 * Description: Gloskin website presentation, content and integration runtime.
 * Version: 0.7.133
 * Requires PHP: 7.4
 * Text Domain: gloskin-site-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-gloskin-site-core-kernel.php';

register_activation_hook( __FILE__, array( 'Gloskin_Site_Core_Kernel', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Gloskin_Site_Core_Kernel', 'deactivate' ) );

$gloskin_site_core_kernel = new Gloskin_Site_Core_Kernel( __FILE__ );
$gloskin_site_core_kernel->boot();
