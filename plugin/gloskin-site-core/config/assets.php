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
		/* Only the two most-critical faces are preloaded. Other Graphik weights
		 * remain demand-loaded by the browser to avoid unused round-trips. */
		'assets/fonts/Graphik-Regular.woff',
		'assets/fonts/Felixti.woff2',
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
		'gloskin-ui1-quickadd-polish' => array(
			'src'   => 'assets/css/gloskin-ui1-quickadd-polish.css',
			'deps'  => array( 'gloskin-ui1-production' ),
			'media' => 'all',
		),
		'gloskin-ui1-commerce-polish' => array(
			'src'   => 'assets/css/gloskin-ui1-commerce-polish.css',
			'deps'  => array( 'gloskin-ui1-quickadd-polish' ),
			'media' => 'all',
		),
		'gloskin-ui1-loader-system' => array(
			'src'   => 'assets/css/gloskin-ui1-loader-system.css',
			'deps'  => array( 'gloskin-ui1-commerce-polish' ),
			'media' => 'all',
		),
		'gloskin-ui1-brand-purchase-polish' => array(
			'src'   => 'assets/css/gloskin-ui1-brand-purchase-polish.css',
			'deps'  => array( 'gloskin-ui1-loader-system' ),
			'media' => 'all',
		),
		'gloskin-ui1-editorial' => array(
			'src'   => 'assets/css/gloskin-ui1-editorial.css',
			'deps'  => array( 'gloskin-ui1-brand-purchase-polish' ),
			'media' => 'all',
		),
		'gloskin-ui1-product-grid' => array(
			'src'   => 'assets/css/gloskin-ui1-product-grid.css',
			'deps'  => array( 'gloskin-ui1-editorial' ),
			'media' => 'all',
		),
		'gloskin-ui1-consultation' => array(
			'src'   => 'assets/css/gloskin-ui1-consultation.css',
			'deps'  => array( 'gloskin-ui1-product-grid' ),
			'media' => 'all',
		),
	),
	'scripts' => array(
		'gloskin-ui1-commerce-motion' => array(
			'src'       => 'assets/js/gloskin-ui1-commerce-motion.js',
			'deps'      => array(),
			'in_footer' => true,
		),
		'gloskin-ui1-core' => array(
			'src'       => 'assets/js/gloskin-ui1-core.js',
			'deps'      => array( 'gloskin-ui1-commerce-motion' ),
			'in_footer' => true,
		),
		'gloskin-ui1-commerce-journey' => array(
			'src'       => 'assets/js/gloskin-ui1-commerce-journey.js',
			'deps'      => array(),
			'in_footer' => false,
		),
		'gloskin-ui1-purchase-dock' => array(
			'src'       => 'assets/js/gloskin-ui1-purchase-dock.js',
			'deps'      => array( 'gloskin-ui1-core' ),
			'in_footer' => true,
		),
		'gloskin-ui1-consultation' => array(
			'src'       => 'assets/js/gloskin-ui1-consultation.js',
			'deps'      => array( 'gloskin-ui1-core' ),
			'in_footer' => true,
		),
	),
	'admin_styles' => array(
		'gloskin-admin' => array(
			'src'   => 'assets/css/gloskin-admin.css',
			'deps'  => array(),
			'media' => 'all',
		),
		'gloskin-ui1-consultation-admin' => array(
			'src'   => 'assets/css/gloskin-ui1-consultation-admin.css',
			'deps'  => array(),
			'media' => 'all',
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
		'gloskin-ui1-final-migration' => array(
			'src'  => 'assets/js/gloskin-ui1-final-migration.js',
			'deps' => array(),
		),
		'gloskin-ui1-media-cleanup' => array(
			'src'  => 'assets/js/gloskin-ui1-media-cleanup.js',
			'deps' => array(),
		),
		'gloskin-ui1-diagnostic' => array(
			'src'  => 'assets/js/gloskin-ui1-diagnostic.js',
			'deps' => array(),
		),
		'gloskin-admin' => array(
			'src'  => 'assets/js/gloskin-admin.js',
			'deps' => array(),
		),
		'gloskin-ui1-consultation-admin' => array(
			'src'  => 'assets/js/gloskin-ui1-consultation-admin.js',
			'deps' => array(),
		),
	),
);
