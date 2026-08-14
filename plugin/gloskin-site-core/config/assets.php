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
		/* Treatment Consultation discovery (docs/task-treatment-
		 * consultation-commerce-discovery.md section 10): conditionally
		 * enqueued only on the Treatments Hub -- see
		 * AssetService::enqueue_frontend(), never loaded site-wide. */
		'gloskin-ui1-consultation' => array(
			'src'   => 'assets/css/gloskin-ui1-consultation.css',
			'deps'  => array( 'gloskin-ui1-production' ),
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
		/* One small feature runtime, Treatments Hub only (see
		 * AssetService::enqueue_frontend()). No frontend framework, no
		 * second cart owner -- reuses the native Add to Cart handlers
		 * wc-add-to-cart/gloskin-ui1-core already bind. */
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
		/* Presentation-only tab-panel switching for the Settings screen's
		 * horizontal tabs (gloskin-admin-tabs). Progressive enhancement: the
		 * server renders every panel visible, this only hides the inactive
		 * ones on load and wires click/keyboard switching. Owns no Gloskin
		 * business logic/state -- see assets/js/gloskin-admin.js. */
		'gloskin-admin' => array(
			'src'  => 'assets/js/gloskin-admin.js',
			'deps' => array(),
		),
		/* Konsultasi Perawatan -> Pemetaan Produk: progressive-enhancement
		 * drag-and-drop re-skin of the real checkbox matrix. Enqueued only
		 * on that one screen -- see AdminService::enqueue_consultation_admin_assets(). */
		'gloskin-ui1-consultation-admin' => array(
			'src'  => 'assets/js/gloskin-ui1-consultation-admin.js',
			'deps' => array(),
		),
	),
);
