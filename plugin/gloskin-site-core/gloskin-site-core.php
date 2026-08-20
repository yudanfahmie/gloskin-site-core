<?php
/**
 * Plugin Name: Gloskin Site Core
 * Description: Gloskin website presentation, content and integration runtime.
 * Version: 0.7.182
 * Requires PHP: 7.4
 * Text Domain: gloskin-site-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GLOSKIN_SITE_CORE_FILE', __FILE__ );

define( 'GLOSKIN_SITE_CORE_PATH', plugin_dir_path( __FILE__ ) );

require_once GLOSKIN_SITE_CORE_PATH . 'includes/class-gloskin-site-core-kernel.php';

$gloskin_site_core = new Gloskin_Site_Core_Kernel( GLOSKIN_SITE_CORE_FILE );
$gloskin_site_core->boot();

register_activation_hook( __FILE__, array( 'Gloskin_Site_Core_Kernel', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Gloskin_Site_Core_Kernel', 'deactivate' ) );
