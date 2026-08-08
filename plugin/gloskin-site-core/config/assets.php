<?php
/**
 * Declarative Gloskin UI v1 asset registry.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'styles' => array(
		'gloskin-ui1-core' => array(
			'src'   => 'assets/css/gloskin-ui1-core.css',
			'deps'  => array(),
			'media' => 'all',
		),
	),
	'scripts' => array(
		'gloskin-ui1-core' => array(
			'src'       => 'assets/js/gloskin-ui1-core.js',
			'deps'      => array(),
			'in_footer' => true,
		),
	),
	'admin_scripts' => array(
		'gloskin-ui1-admin' => array(
			'src'  => 'assets/js/gloskin-ui1-admin.js',
			'deps' => array(),
		),
	),
);
