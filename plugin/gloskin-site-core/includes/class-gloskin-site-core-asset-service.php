<?php
/**
 * Single Gloskin first-party asset owner.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Asset_Service {
	/** @var string */
	private $plugin_file;

	/** @var string */
	private $version;

	/** @var array<string, array<string, array<string, mixed>>>|null */
	private $registry = null;

	/** @var callable|null */
	private $commerce_request_callback;

	/**
	 * @param string        $plugin_file Main plugin file.
	 * @param string        $version Plugin release version.
	 * @param callable|null $commerce_request_callback Native Woo presentation request check.
	 */
	public function __construct( $plugin_file, $version, $commerce_request_callback = null ) {
		$this->plugin_file               = $plugin_file;
		$this->version                   = $version;
		$this->commerce_request_callback = is_callable( $commerce_request_callback ) ? $commerce_request_callback : null;
	}

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ), 20 );
		/* Unconditional registration (matches the rest of this codebase's
		 * hook convention): wp_head never fires in wp-admin at all, and
		 * print_font_preload() re-checks the same eligible-frontend gate
		 * enqueue_frontend() uses, so this is inert everywhere it should be. */
		add_action( 'wp_head', array( $this, 'print_font_preload' ), 1 );
	}

	/**
	 * @return void
	 */
	/**
	 * Registry handles that are always registered but only conditionally
	 * enqueued below (never part of the unconditional loop every other
	 * Gloskin frontend request gets). Commerce journey and consultation each
	 * have one small route-aware enqueue gate in this same AssetService.
	 *
	 * @var array<int,string>
	 */
	const CONDITIONAL_HANDLES = array( 'gloskin-ui1-consultation', 'gloskin-ui1-commerce-journey' );

	public function enqueue_frontend() {
		if ( ! $this->should_enqueue_frontend() ) {
			return;
		}

		$registry = $this->registry();
		foreach ( $registry['styles'] as $handle => $asset ) {
			$src  = ! empty( $asset['external'] )
				? (string) $asset['src']
				: plugins_url( $asset['src'], $this->plugin_file );
			$deps = $asset['deps'];
			if ( 'gloskin-ui1-production' === $handle ) {
				$deps = array_merge( $deps, $this->registered_woo_style_deps() );
			}
			wp_register_style( $handle, $src, $deps, $this->version, $asset['media'] );
			if ( in_array( $handle, self::CONDITIONAL_HANDLES, true ) ) {
				continue;
			}
			wp_enqueue_style( $handle );
		}
		foreach ( $registry['scripts'] as $handle => $asset ) {
			$src = ! empty( $asset['external'] )
				? (string) $asset['src']
				: plugins_url( $asset['src'], $this->plugin_file );
			wp_register_script( $handle, $src, $asset['deps'], $this->version, $asset['in_footer'] );
			if ( in_array( $handle, self::CONDITIONAL_HANDLES, true ) ) {
				continue;
			}
			wp_enqueue_script( $handle );
		}

		$this->enqueue_native_commerce_scripts();
		$this->maybe_enqueue_commerce_journey();
		$this->maybe_enqueue_treatment_consultation();
	}

	/**
	 * Cart <-> Checkout perceptual handoff only. The script is deliberately
	 * head-loaded so an incoming sessionStorage presentation marker can hide
	 * only the dynamic Woo region before paint. Navigation itself remains a
	 * full native Woo document request; no HTML swap, History API router, or
	 * Woo Blocks/payment lifecycle is owned here.
	 *
	 * @return void
	 */
	private function maybe_enqueue_commerce_journey() {
		$is_cart     = function_exists( 'is_cart' ) && is_cart();
		$is_checkout = function_exists( 'is_checkout' ) && is_checkout();
		if ( ! $is_cart && ! $is_checkout ) {
			return;
		}
		wp_enqueue_script( 'gloskin-ui1-commerce-journey' );
	}

	/**
	 * Treatment Consultation runtime (docs/task-treatment-consultation-
	 * commerce-discovery.md section 10): "Treatment consultation JS only on
	 * Treatments Hub" -- both handles are already registered above (so
	 * their dependency graph is known), just conditionally enqueued here
	 * rather than unconditionally on every Gloskin frontend request.
	 *
	 * @return void
	 */
	private function maybe_enqueue_treatment_consultation() {
		$context = function_exists( 'get_query_var' ) ? get_query_var( 'gloskin_context', array() ) : array();
		if ( ! is_array( $context ) || 'treatments' !== ( isset( $context['view'] ) ? $context['view'] : '' ) ) {
			return;
		}
		wp_enqueue_style( 'gloskin-ui1-consultation' );
		wp_enqueue_script( 'gloskin-ui1-consultation' );
	}

	/**
	 * Guarantee WooCommerce's own native add-to-cart/cart-fragment frontend
	 * handles are available on every surface Gloskin renders. Gloskin's
	 * product-card/cart-sheet markup does not live on Woo's native shop/
	 * archive/cart templates, so Woo's own conditional script loader
	 * (WC_Frontend_Scripts::load_scripts()) cannot always detect it should
	 * enqueue them there. This only ever enqueues an already Woo-registered
	 * handle -- wp_enqueue_script() is idempotent by handle, WooCommerce
	 * keeps sole ownership of the script's src/deps/localized cart params,
	 * and nothing here registers, forks or replaces a Woo asset.
	 *
	 * Availability hardening note (architecture audit, hotfix): this
	 * deliberately does not also gate on class_exists('WooCommerce'). The
	 * per-handle wp_script_is(..., 'registered') checks below are already
	 * sufficient and strictly narrower -- when WooCommerce is inactive,
	 * Woo never registers these handles at all, so every branch here is
	 * already a no-op without a redundant second gate. WooCommerce
	 * availability itself stays owned by the one canonical adapter
	 * (Gloskin_Site_Core_WooCommerce_Adapter::is_available()).
	 *
	 * @return void
	 */
	private function enqueue_native_commerce_scripts() {
		if ( ! function_exists( 'wp_script_is' ) ) {
			return;
		}
		if ( wp_script_is( 'wc-cart-fragments', 'registered' ) ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}
		/* wc-add-to-cart (add-to-cart.js) also owns the .remove_from_cart_button
		 * delegated AJAX removal handler the Gloskin cart sheet's remove links
		 * depend on (see render_mini_cart_body()). That sheet is rendered
		 * site-wide in header.php whenever WooCommerce is available -- the
		 * SAME condition already covered by the wp_script_is() check below --
		 * independent of woocommerce_enable_ajax_add_to_cart, which only
		 * controls whether Woo's *own* catalog-loop templates mark their Add
		 * to Cart buttons ajax_add_to_cart; it never gates whether this script
		 * itself may load. Gating the enqueue on that unrelated option left
		 * cart-sheet removal falling back to a full navigation whenever it was
		 * off (or its default, which is off). */
		if ( wp_script_is( 'wc-add-to-cart', 'registered' ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
		}
		if ( $this->variation_form_may_render() && wp_script_is( 'wc-add-to-cart-variation', 'registered' ) ) {
			wp_enqueue_script( 'wc-add-to-cart-variation' );
		}
	}

	/**
	 * Presentation load-order only: when WooCommerce's own public style
	 * handles are already registered (classic frontend styles, Select2, or
	 * WooCommerce Blocks' style bundles), make the final Gloskin form-
	 * presentation layer (gloskin-ui1-production, last in the registry
	 * dependency chain) depend on them. This guarantees the cascade always
	 * resolves Gloskin-after-Woo through the dependency graph itself,
	 * rather than relying on both plugins keeping the same hook priority.
	 * Never registers, forks, dequeues or replaces a Woo stylesheet -- a
	 * read-only handle check, same pattern as
	 * enqueue_native_commerce_scripts() below.
	 *
	 * @return array<int,string>
	 */
	private function registered_woo_style_deps() {
		if ( ! function_exists( 'wp_style_is' ) ) {
			return array();
		}
		$candidates = array(
			'woocommerce-general',
			'woocommerce-layout',
			'woocommerce-smallscreen',
			'select2',
			'wc-blocks-style',
			'wc-blocks-vendors-style',
			'wc-blocks-checkout-style',
			'wc-blocks-cart-style',
		);
		$deps = array();
		foreach ( $candidates as $candidate ) {
			if ( wp_style_is( $candidate, 'registered' ) ) {
				$deps[] = $candidate;
			}
		}
		return $deps;
	}

	/**
	 * Whether a Woo native variation form can genuinely appear on this
	 * request: the single-product page itself (Woo's own conditional
	 * loader already covers this case too, but is_product() is cheap and
	 * explicit here), or a Gloskin catalog view whose cards can open the
	 * SP-004 Quick Add modal (shop/skincare/skincare-category/home all
	 * render gloskin_ui1_render_product_card()). Never enqueued site-wide.
	 *
	 * @return bool
	 */
	private function variation_form_may_render() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}
		$context = function_exists( 'get_query_var' ) ? get_query_var( 'gloskin_context', array() ) : array();
		$view    = is_array( $context ) && isset( $context['view'] ) ? (string) $context['view'] : '';
		return in_array( $view, array( 'home', 'shop', 'skincare', 'skincare-category' ), true );
	}

	/**
	 * Preload only the critical self-hosted Graphik/Felix font files declared
	 * in config/assets.php, and only on the same eligible Gloskin frontend
	 * requests enqueue_frontend() already restricts styles/scripts to.
	 * Never prints in wp-admin (wp_head does not fire there).
	 *
	 * @return void
	 */
	public function print_font_preload() {
		if ( ! $this->should_enqueue_frontend() ) {
			return;
		}
		$registry = $this->registry();
		if ( empty( $registry['font_preload'] ) || ! is_array( $registry['font_preload'] ) ) {
			return;
		}
		foreach ( $registry['font_preload'] as $relative ) {
			$extension = strtolower( pathinfo( (string) $relative, PATHINFO_EXTENSION ) );
			$type      = 'woff' === $extension ? 'font/woff' : ( 'woff2' === $extension ? 'font/woff2' : '' );
			if ( '' === $type ) {
				continue;
			}
			$url = plugins_url( (string) $relative, $this->plugin_file );
			echo '<link rel="preload" href="' . esc_url( $url ) . '" as="font" type="' . $type . '" crossorigin>' . "\n";
		}
	}

	/**
	 * TemplateService marks Gloskin shell requests in the shared query context
	 * before wp_head runs. Woo requests use the adapter-owned commerce decision.
	 * This keeps assets off unrelated WordPress routes without duplicating routes.
	 *
	 * @return bool
	 */
	private function should_enqueue_frontend() {
		$context = function_exists( 'get_query_var' ) ? get_query_var( 'gloskin_context', array() ) : array();
		if ( is_array( $context ) && ! empty( $context['view'] ) ) {
			return true;
		}

		return null !== $this->commerce_request_callback
			&& (bool) call_user_func( $this->commerce_request_callback );
	}

	/**
	 * Load the tiny Media Library helper only on relevant Gloskin edit screens.
	 *
	 * @param string $hook_suffix Admin screen hook.
	 * @return void
	 */
	public function enqueue_admin( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array(
			$screen->post_type,
			array( Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE, 'page' ),
			true
		) ) {
			return;
		}

		$registry = $this->registry();
		if ( empty( $registry['admin_scripts']['gloskin-ui1-admin'] ) ) {
			return;
		}

		$asset = $registry['admin_scripts']['gloskin-ui1-admin'];
		wp_enqueue_media();
		wp_register_script( 'gloskin-ui1-admin', plugins_url( $asset['src'], $this->plugin_file ), $asset['deps'], $this->version, true );
		wp_enqueue_script( 'gloskin-ui1-admin' );
	}

	/**
	 * The Gloskin Settings screen's own small presentation shell (scoped
	 * beneath #gloskin-admin-root). Gloskin_Site_Core_Admin_Service already
	 * gates the call to this method to that exact screen's hook -- this
	 * method only registers/enqueues, keeping AssetService the sole asset
	 * registry/enqueue owner. No global wp-admin CSS leakage.
	 *
	 * wp_enqueue_media() is called here (never globally in wp-admin) so the
	 * Hero tab's "Background video" field can open the native WordPress
	 * Media Library modal, scoped to a video library -- see the
	 * data-gloskin-video-picker/data-gloskin-video-remove wiring in
	 * assets/js/gloskin-admin.js.
	 *
	 * @return void
	 */
	public function enqueue_admin_settings() {
		wp_enqueue_media();
		$registry = $this->registry();
		if ( ! empty( $registry['admin_styles']['gloskin-admin'] ) ) {
			$asset = $registry['admin_styles']['gloskin-admin'];
			wp_register_style( 'gloskin-admin', plugins_url( $asset['src'], $this->plugin_file ), $asset['deps'], $this->version, $asset['media'] );
			wp_enqueue_style( 'gloskin-admin' );
		}
		if ( ! empty( $registry['admin_scripts']['gloskin-admin'] ) ) {
			$asset = $registry['admin_scripts']['gloskin-admin'];
			wp_register_script( 'gloskin-admin', plugins_url( $asset['src'], $this->plugin_file ), $asset['deps'], $this->version, true );
			wp_enqueue_script( 'gloskin-admin' );
		}
	}

	/**
	 * The Konsultasi Perawatan mapping matrix's scoped presentation assets.
	 * Gloskin_Site_Core_Admin_Service already gates the call to this method
	 * to that exact screen's hook; this method only registers/enqueues the
	 * feature CSS and JS through the one canonical AssetService owner.
	 *
	 * @return void
	 */
	public function enqueue_consultation_admin() {
		$registry = $this->registry();
		if ( ! empty( $registry['admin_styles']['gloskin-ui1-consultation-admin'] ) ) {
			$asset = $registry['admin_styles']['gloskin-ui1-consultation-admin'];
			wp_register_style( 'gloskin-ui1-consultation-admin', plugins_url( $asset['src'], $this->plugin_file ), $asset['deps'], $this->version, $asset['media'] );
			wp_enqueue_style( 'gloskin-ui1-consultation-admin' );
		}
		if ( ! empty( $registry['admin_scripts']['gloskin-ui1-consultation-admin'] ) ) {
			$asset = $registry['admin_scripts']['gloskin-ui1-consultation-admin'];
			wp_register_script( 'gloskin-ui1-consultation-admin', plugins_url( $asset['src'], $this->plugin_file ), $asset['deps'], $this->version, true );
			wp_enqueue_script( 'gloskin-ui1-consultation-admin' );
		}
	}

	/**
	 * Enqueue the temporary importer controller only after AdminService has
	 * proven the exact migration screen and capability.
	 *
	 * @param string $action Authenticated AJAX action.
	 * @param string $nonce Nonce.
	 * @return void
	 */
	public function enqueue_admin_migration( $action, $nonce ) {
		$registry = $this->registry();
		if ( empty( $registry['admin_scripts']['gloskin-ui1-sample-import'] ) ) { return; }
		$asset = $registry['admin_scripts']['gloskin-ui1-sample-import'];
		wp_register_script( 'gloskin-ui1-sample-import', plugins_url( $asset['src'], $this->plugin_file ), $asset['deps'], $this->version, true );
		wp_localize_script( 'gloskin-ui1-sample-import', 'GloskinSampleImport', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action' => (string) $action,
			'nonce' => (string) $nonce,
		) );
		wp_enqueue_script( 'gloskin-ui1-sample-import' );
	}

	/**
	 * Enqueue the final-migration AJAX controller.
	 * Reads action/nonce from data-* attributes on the root DOM element — no
	 * wp_localize_script() needed.
	 *
	 * @param string $action Authenticated AJAX action (baked into admin render).
	 * @param string $nonce  Nonce value (baked into admin render).
	 * @return void
	 */
	public function enqueue_admin_final_migration( $action, $nonce ) {
		$registry = $this->registry();
		if ( empty( $registry['admin_scripts']['gloskin-ui1-final-migration'] ) ) { return; }
		$asset = $registry['admin_scripts']['gloskin-ui1-final-migration'];
		wp_register_script( 'gloskin-ui1-final-migration', plugins_url( $asset['src'], $this->plugin_file ), $asset['deps'], $this->version, true );
		wp_enqueue_script( 'gloskin-ui1-final-migration' );
		// action/nonce are read from data-* on the root DOM element; nothing to localize.
		unset( $action, $nonce ); // accepted for API symmetry
	}

	/**
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function registry() {
		if ( null === $this->registry ) {
			$registry       = require dirname( __DIR__ ) . '/config/assets.php';
			$this->registry = is_array( $registry )
				? $registry
				: array( 'font_preload' => array(), 'styles' => array(), 'scripts' => array(), 'admin_styles' => array(), 'admin_scripts' => array() );
		}
		return $this->registry;
	}
}