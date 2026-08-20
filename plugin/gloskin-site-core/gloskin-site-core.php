<?php
/**
 * Plugin Name: Gloskin Site Core
 * Description: Gloskin website presentation, content and integration runtime.
<<<<<<< HEAD
 * Version: 0.7.155
=======
 * Version: 0.7.156
>>>>>>> cd8b8a3e43a2fde30f37702b31ab3cea5f3a617b
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
