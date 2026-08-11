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
	'font_preload' => array(
		'assets/fonts/Marcellus-Regular.woff2',
		'assets/fonts/Mulish-Variable.woff2',
	),
	'styles' => array(
		'gloskin-ui1-fonts' => array(
			'src'   => 'assets/css/gloskin-ui1-fonts.css',
			'deps'  => array(),
			'media' => 'all',
		),
		'gloskin-ui1-core-base' => array(
			'src'   => 'assets/css/gloskin-ui1-core-base.css',
			'deps'  => array( 'gloskin-ui1-fonts' ),
			'media' => 'all',
		),
		'gloskin-ui1-core' => array(
			'src'   => 'assets/css/gloskin-ui1-core.css',
			'deps'  => array( 'gloskin-ui1-core-base' ),
			'media' => 'all',
		),
		'gloskin-ui1-single-product-geometry' => array(
			'src'   => 'assets/css/gloskin-ui1-single-product-geometry.css',
			'deps'  => array( 'gloskin-ui1-core' ),
			'media' => 'all',
		),
		'gloskin-ui1-readiness' => array(
			'src'   => 'assets/css/gloskin-ui1-readiness.css',
			'deps'  => array( 'gloskin-ui1-single-product-geometry' ),
			'media' => 'all',
		),
		'gloskin-ui1-production' => array(
			'src'   => 'assets/css/gloskin-ui1-production.css',
			'deps'  => array( 'gloskin-ui1-readiness' ),
			'media' => 'all',
		),
	),
	'scripts' => array(
		'gloskin-ui1-core' => array(
			'src'       => 'assets/js/gloskin-ui1-core.js',
			'deps'      => array(),
			'in_footer' => true,
		),
		/* Core is already the broad interaction owner. Keep the bounded PDP
		 * viewport controller separate and tiny so its observer/measurement
		 * lifecycle cannot inflate that cross-site module; AssetService still
		 * remains the sole enqueue owner and this script no-ops off PDPs. */
		'gloskin-ui1-purchase-dock' => array(
			'src'       => 'assets/js/gloskin-ui1-purchase-dock.js',
			'deps'      => array( 'gloskin-ui1-core' ),
			'in_footer' => true,
		),
	),
	'admin_scripts' => array(
		'gloskin-ui1-admin' => array(
			'src'  => 'assets/js/gloskin-ui1-admin.js',
			'deps' => array(),
		),
		'gloskin-ui1-sample-import' => array(
			'src'  => 'assets/js/gloskin-ui1-sample-product-import.js',
			'deps' => array(),
		),
	),
);
