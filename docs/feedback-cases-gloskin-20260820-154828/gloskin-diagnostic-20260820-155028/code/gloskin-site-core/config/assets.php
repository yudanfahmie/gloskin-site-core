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
		/* Bounded Quick Add presentation refinement. Every selector is rooted
		 * in the existing Quick Add form; no Woo markup/state owner is added. */
		'gloskin-ui1-quickadd-polish' => array(
			'src'   => 'assets/css/gloskin-ui1-quickadd-polish.css',
			'deps'  => array( 'gloskin-ui1-production' ),
			'media' => 'all',
		),
		/* Shared destructive commerce action skin + header badge delta motion.
		 * This is presentation only and deliberately loads after the existing
		 * modal/action kit so the danger modifier can reuse its exact geometry. */
		'gloskin-ui1-commerce-polish' => array(
			'src'   => 'assets/css/gloskin-ui1-commerce-polish.css',
			'deps'  => array( 'gloskin-ui1-quickadd-polish' ),
			'media' => 'all',
		),
		/* Shared goo-loader + semantic remove-control convergence. Loaded last
		 * across Gloskin frontend surfaces so Home/Shop/modal/commerce states
		 * consume one presentation primitive without changing their state owners. */
		'gloskin-ui1-loader-system' => array(
			'src'   => 'assets/css/gloskin-ui1-loader-system.css',
			'deps'  => array( 'gloskin-ui1-commerce-polish' ),
			'media' => 'all',
		),
		/* Purchase action convergence + canonical brand shimmer. This final
		 * presentation-only layer adds no commerce or animation state owner. */
		'gloskin-ui1-brand-purchase-polish' => array(
			'src'   => 'assets/css/gloskin-ui1-brand-purchase-polish.css',
			'deps'  => array( 'gloskin-ui1-loader-system' ),
			'media' => 'all',
		),
		/* Native post editorial surfaces + branded 404. Scoped selectors only;
		 * WordPress keeps all content/taxonomy/media/status ownership. */
		'gloskin-ui1-editorial' => array(
			'src'   => 'assets/css/gloskin-ui1-editorial.css',
			'deps'  => array( 'gloskin-ui1-brand-purchase-polish' ),
			'media' => 'all',
		),
		/* Shared first-party product-grid geometry. Home products, Shop SSR/AJAX
		 * products and Shop skeletons opt into this one scoped column matrix;
		 * generic editorial/card grids remain on their existing owner. */
		'gloskin-ui1-product-grid' => array(
			'src'   => 'assets/css/gloskin-ui1-product-grid.css',
			'deps'  => array( 'gloskin-ui1-editorial' ),
			'media' => 'all',
		),
		/* Approved 2026 prototype refresh. This is the one final brand/page
		 * convergence layer: it changes presentation only and deliberately
		 * consumes the existing shell, component, Woo and data owners above. */
		'gloskin-ui1-prototype-refresh' => array(
			'src'   => 'assets/css/gloskin-ui1-prototype-refresh.css',
			'deps'  => array( 'gloskin-ui1-product-grid' ),
			'media' => 'all',
		),
		/* Treatment Consultation discovery (docs/task-treatment-
		 * consultation-commerce-discovery.md section 10): conditionally
		 * enqueued only on the Treatments Hub -- see
		 * AssetService::enqueue_frontend(), never loaded site-wide. */
		'gloskin-ui1-consultation' => array(
			'src'   => 'assets/css/gloskin-ui1-consultation.css',
			'deps'  => array( 'gloskin-ui1-prototype-refresh' ),
			'media' => 'all',
		),
	),
	'scripts' => array(
		/* Shared confirmed-success Cart/Wishlist motion helper. Presentation
		 * only: captures action geometry, animates one decorative orb, and
		 * impacts the visible header badge after success. Core remains the
		 * success-boundary owner and Woo/Wishlist retain all state writes. */
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
		/* Cart <-> Checkout perceptual handoff. Head-loaded only on those
		 * native Woo routes so an incoming sessionStorage presentation marker can mask the
		 * dynamic Woo region before it paints. It never fetches/swaps markup,
		 * changes history, or initializes Woo/Blocks/payment state. */
		'gloskin-ui1-commerce-journey' => array(
			'src'       => 'assets/js/gloskin-ui1-commerce-journey.js',
			'deps'      => array(),
			'in_footer' => false,
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
		/* Konsultasi Perawatan -> Pemetaan Produk presentation only.
		 * AssetService enqueues it together with the matching controller on
		 * that one screen; it is never part of global wp-admin styling. */
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
