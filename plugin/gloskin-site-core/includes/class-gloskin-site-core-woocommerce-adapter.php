<?php
/**
 * WooCommerce presentation and commerce-header adapter.
 *
 * Owns Woo availability resolution, normalized presentation access for
 * products/cart/account/checkout, cart-fragment updates, product search,
 * and wishlist product resolution. Templates never depend on raw Woo
 * globals; they consume the normalized data this adapter provides.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_WooCommerce_Adapter {
	/**
	 * Register Woo presentation hooks.
	 *
	 * The Kernel constructs this adapter during Gloskin's own plugin-load
	 * pass, which can run before or after WooCommerce finishes loading
	 * depending on plugin activation order (WordPress loads active plugins
	 * in the order stored in the `active_plugins` option, not a guaranteed
	 * dependency order). Hook registration therefore never gates on Woo
	 * availability here -- every hook below is inert when WooCommerce never
	 * fires it, so registering unconditionally is safe and avoids caching
	 * a load-order-sensitive decision at construction time.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'woocommerce_product_meta_end', array( $this, 'render_product_facts' ), 20 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_wishlist_toggle' ), 31 );
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragments' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'gloskin_site_core_shell_footer', array( $this, 'render_quick_auth_overlay' ), 10 );
		// Priority 1: run before core's do_blocks (9)/do_shortcode (11) so a
		// stripped nested embed never executes. See SP-001.
		add_filter( 'the_content', array( $this, 'guard_single_product_description_content' ), 1 );
		// SP-005: HD main image on the single-product gallery only -- Woo's
		// own display-size filter, never a global image-size override.
		add_filter( 'woocommerce_gallery_image_size', array( $this, 'single_product_gallery_image_size' ) );
	}

	/**
	 * SP-005: high-resolution *main* single-product gallery image only.
	 *
	 * Scoped to Woo's own `woocommerce_gallery_image_size` filter, which Woo
	 * only ever applies while rendering the single-product gallery itself
	 * (wc_get_gallery_image_html()) -- so this can never affect catalog
	 * cards, thumbnails, related products, cart or mini-cart images, all of
	 * which use their own separate, already-optimized sizes elsewhere. The
	 * thumbnail rail keeps `woocommerce_gallery_thumbnail_size` untouched,
	 * and the zoom/lightbox full image already defaults to Woo's own 'full'
	 * via `woocommerce_gallery_full_size`; responsive srcset/sizes markup is
	 * WordPress core behavior on any registered/full image size, so it is
	 * preserved automatically.
	 *
	 * @return string
	 */
	public function single_product_gallery_image_size() {
		return 'full';
	}

	/**
	 * Resolve Woo availability at point of use rather than caching it at
	 * construction time. class_exists()/function_exists() are cheap hash
	 * lookups, and by the time any adapter method actually runs (template
	 * rendering, a REST callback, a Woo hook firing) every plugin has
	 * finished loading regardless of the order Gloskin and WooCommerce were
	 * activated in. This is the fix for the load-order bug: a
	 * constructor-time snapshot would stay permanently false for the whole
	 * request if Gloskin's plugin file happened to load before WooCommerce's.
	 *
	 * @return bool
	 */
	private function is_available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' );
	}


	/**
	 * Keep native Woo templates while applying the shared Gloskin presentation tokens.
	 *
	 * @param array<int,string> $classes Body classes.
	 * @return array<int,string>
	 */
	public function body_classes( $classes ) {
		if ( ! $this->is_commerce_request() ) {
			return $classes;
		}

		$settings = get_option( Gloskin_Site_Core_Form_Adapter::SETTINGS_OPTION, array() );
		$variant  = isset( $settings['design_variant'] ) ? sanitize_key( $settings['design_variant'] ) : 'medical';
		if ( ! in_array( $variant, array( 'medical', 'modern', 'luxury' ), true ) ) {
			$variant = 'medical';
		}

		$classes[] = 'gloskin-ui1';
		$classes[] = 'gloskin-ui1--' . $variant;
		return array_values( array_unique( $classes ) );
	}

	/**
	 * @return bool
	 */
	public function is_commerce_request() {
		if ( ! $this->is_available() ) {
			return false;
		}

		$checks = array( 'is_woocommerce', 'is_cart', 'is_checkout', 'is_account_page' );
		foreach ( $checks as $function ) {
			if ( function_exists( $function ) && call_user_func( $function ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return bool
	 */
	public function is_shop_request() {
		return $this->is_available() && function_exists( 'is_shop' ) && is_shop();
	}

	/**
	 * @return bool
	 */
	public function available() {
		return $this->is_available();
	}

	/* -----------------------------------------------------------------
	 * Commerce header context
	 * ----------------------------------------------------------------- */

	/**
	 * SP-003/SP-004: WooCommerce's own documented AJAX add-to-cart endpoint
	 * URL (WC_AJAX::get_endpoint('add_to_cart')). Reading this here rather
	 * than depending on Woo's own wc-add-to-cart script being enqueued
	 * (which Gloskin only enqueues when the unrelated
	 * woocommerce_enable_ajax_add_to_cart catalog-loop setting is on)
	 * means the single-product page and Quick Add modal can always reach
	 * Woo's real mutation endpoint. This never mutates cart/session state
	 * itself -- it only returns the URL Woo itself documents for that
	 * purpose.
	 *
	 * @return string
	 */
	public function add_to_cart_ajax_url() {
		if ( ! $this->is_available() || ! class_exists( 'WC_AJAX' ) ) {
			return '';
		}
		return (string) WC_AJAX::get_endpoint( 'add_to_cart' );
	}

	/**
	 * Canonical Woo My Account URL, or empty when Woo is absent.
	 *
	 * @return string
	 */
	public function account_url() {
		if ( ! $this->is_available() || ! function_exists( 'wc_get_page_id' ) ) {
			return '';
		}
		$page_id = wc_get_page_id( 'myaccount' );
		if ( $page_id <= 0 ) {
			return '';
		}
		$url = get_permalink( $page_id );
		return $url ? (string) $url : '';
	}

	/**
	 * @return string
	 */
	public function cart_url() {
		if ( ! $this->is_available() || ! function_exists( 'wc_get_cart_url' ) ) {
			return '';
		}
		return (string) wc_get_cart_url();
	}

	/**
	 * @return string
	 */
	public function checkout_url() {
		if ( ! $this->is_available() || ! function_exists( 'wc_get_checkout_url' ) ) {
			return '';
		}
		return (string) wc_get_checkout_url();
	}

	/**
	 * @return int
	 */
	public function cart_count() {
		if ( ! $this->is_available() || ! function_exists( 'WC' ) ) {
			return 0;
		}
		$wc = WC();
		if ( ! $wc || ! isset( $wc->cart ) || ! is_object( $wc->cart ) ) {
			return 0;
		}
		return absint( $wc->cart->get_cart_contents_count() );
	}

	/**
	 * @return string
	 */
	public function cart_subtotal() {
		if ( ! $this->is_available() || ! function_exists( 'WC' ) ) {
			return '';
		}
		$wc = WC();
		if ( ! $wc || ! isset( $wc->cart ) || ! is_object( $wc->cart ) ) {
			return '';
		}
		return (string) $wc->cart->get_cart_subtotal();
	}


	/**
	 * Whether the quick auth overlay may exist on this request.
	 * Native My Account remains the sole auth form owner on the account page.
	 *
	 * @return bool
	 */
	public function should_render_quick_auth() {
		if ( ! $this->is_available() || is_user_logged_in() || '' === $this->account_url() ) {
			return false;
		}
		return ! ( function_exists( 'is_account_page' ) && is_account_page() );
	}

	/**
	 * Render Woo's native login/register template inside the existing Gloskin
	 * overlay system. Credentials are never read or submitted by custom JS.
	 *
	 * @return void
	 */
	public function render_quick_auth_overlay() {
		if ( ! $this->should_render_quick_auth() || ! function_exists( 'wc_get_template' ) ) {
			return;
		}

		$account_url = $this->account_url();
		ob_start();
		wc_get_template( 'myaccount/form-login.php' );
		$form_html = trim( (string) ob_get_clean() );
		if ( '' === $form_html ) {
			return;
		}

		$form_html            = $this->route_auth_forms_to_account( $form_html, $account_url );
		$registration_enabled = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
		?>
		<div class="gloskin-ui1-auth-overlay" id="gloskin-auth-overlay" data-gloskin-overlay="auth" aria-hidden="true" hidden>
			<button class="gloskin-ui1-auth-overlay__backdrop" type="button" data-gloskin-overlay-close aria-label="<?php echo esc_attr__( 'Tutup akun', 'gloskin-site-core' ); ?>"></button>
			<section class="gloskin-ui1-auth-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="gloskin-auth-title">
				<div class="gloskin-ui1-auth-overlay__head">
					<div><p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Akun Gloskin', 'gloskin-site-core' ); ?></p><h2 id="gloskin-auth-title"><?php echo esc_html__( 'Masuk untuk melanjutkan', 'gloskin-site-core' ); ?></h2><p><?php echo esc_html__( 'Akses pesanan dan detail akun Anda dengan aman.', 'gloskin-site-core' ); ?></p></div>
					<button class="gloskin-ui1-sheet__close" type="button" data-gloskin-overlay-close aria-label="<?php echo esc_attr__( 'Tutup akun', 'gloskin-site-core' ); ?>"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false"><path d="M4 4l10 10M14 4 4 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></button>
				</div>
				<?php if ( $registration_enabled ) : ?>
					<div class="gloskin-ui1-auth-switch" aria-label="<?php echo esc_attr__( 'Pilih autentikasi', 'gloskin-site-core' ); ?>">
						<button type="button" class="is-active" data-gloskin-auth-tab="login" aria-pressed="true"><?php echo esc_html__( 'Masuk', 'gloskin-site-core' ); ?></button>
						<button type="button" data-gloskin-auth-tab="register" aria-pressed="false"><?php echo esc_html__( 'Buat Akun', 'gloskin-site-core' ); ?></button>
					</div>
				<?php endif; ?>
				<div class="gloskin-ui1-auth-forms" data-gloskin-auth-forms data-registration-enabled="<?php echo $registration_enabled ? 'yes' : 'no'; ?>">
					<?php echo $form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- native Woo template output. ?>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Point only the captured native login/register forms at canonical My Account.
	 * This preserves normal Woo form handling while ensuring validation failures
	 * render on the authoritative account surface.
	 *
	 * @param string $html Native Woo form HTML.
	 * @param string $account_url Canonical account URL.
	 * @return string
	 */
	private function route_auth_forms_to_account( $html, $account_url ) {
		if ( '' === $account_url ) {
			return $html;
		}

		$result = preg_replace_callback(
			'/<form\b([^>]*)>/i',
			static function ( $matches ) use ( $account_url ) {
				$attributes = isset( $matches[1] ) ? (string) $matches[1] : '';
				$is_auth    = false !== stripos( $attributes, 'woocommerce-form-login' ) || false !== stripos( $attributes, 'woocommerce-form-register' );
				if ( ! $is_auth || preg_match( '/\saction\s*=/i', $attributes ) ) {
					return $matches[0];
				}
				return '<form action="' . esc_url( $account_url ) . '"' . $attributes . '>';
			},
			$html
		);

		return is_string( $result ) ? $result : $html;
	}

	/**
	 * Normalized cart items for the mini-cart sheet.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function mini_cart_items() {
		if ( ! $this->is_available() || ! function_exists( 'WC' ) ) {
			return array();
		}
		$wc = WC();
		if ( ! $wc || ! isset( $wc->cart ) || ! is_object( $wc->cart ) || ! method_exists( $wc->cart, 'get_cart' ) ) {
			return array();
		}
		$items = array();
		foreach ( $wc->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_name' ) ) {
				continue;
			}
			$remove_url = function_exists( 'wc_get_cart_remove_url' ) ? wc_get_cart_remove_url( $cart_item_key ) : '';
			$items[]    = array(
				'key'           => (string) $cart_item_key,
				'product_id'    => absint( $cart_item['product_id'] ),
				'name'          => (string) $product->get_name(),
				'quantity'      => absint( $cart_item['quantity'] ),
				'variation'     => $this->format_variation( isset( $cart_item['variation'] ) ? $cart_item['variation'] : array() ),
				'price_html'    => (string) $wc->cart->get_product_price( $product ),
				'subtotal_html' => (string) $wc->cart->get_product_subtotal( $product, $cart_item['quantity'] ),
				'image_id'      => method_exists( $product, 'get_image_id' ) ? absint( $product->get_image_id() ) : 0,
				'url'           => (string) get_permalink( $cart_item['product_id'] ),
				'remove_url'    => (string) $remove_url,
			);
		}
		return $items;
	}

	/**
	 * Format a Woo cart-item variation array ("attribute_pa_size" => "30ml")
	 * into a short human-readable summary for the mini-cart.
	 *
	 * @param array<string,mixed> $variation Woo variation attribute map.
	 * @return string
	 */
	private function format_variation( $variation ) {
		if ( ! is_array( $variation ) || ! $variation ) {
			return '';
		}
		$parts = array();
		foreach ( $variation as $attribute => $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			$label   = str_replace( array( 'attribute_', 'pa_', '-', '_' ), array( '', '', ' ', ' ' ), (string) $attribute );
			$label   = trim( ucwords( $label ) );
			$parts[] = '' !== $label ? $label . ': ' . $value : $value;
		}
		return implode( ', ', $parts );
	}

	/**
	 * Return mini-cart body HTML for both initial render and AJAX fragments.
	 *
	 * @return string
	 */
	public function render_mini_cart_body() {
		if ( ! $this->is_available() ) {
			return '';
		}
		$items    = $this->mini_cart_items();
		$cart_url = $this->cart_url();
		$checkout = $this->checkout_url();

		ob_start();
		if ( $items ) {
			echo '<ul class="gloskin-ui1-cart-sheet__list">';
			foreach ( $items as $item ) {
				echo '<li class="gloskin-ui1-cart-sheet__item">';
				echo '<span class="gloskin-ui1-cart-sheet__item-media">';
				if ( $item['image_id'] ) {
					echo wp_get_attachment_image( $item['image_id'], 'thumbnail', false, array( 'class' => 'gloskin-ui1-cart-sheet__item-image', 'loading' => 'lazy', 'alt' => '' ) );
				}
				echo '</span>';
				echo '<span class="gloskin-ui1-cart-sheet__item-details">';
				echo '<a class="gloskin-ui1-cart-sheet__item-link" href="' . esc_url( $item['url'] ) . '">';
				echo '<span class="gloskin-ui1-cart-sheet__item-name">' . esc_html( $item['name'] ) . '</span>';
				echo '</a>';
				if ( '' !== $item['variation'] ) {
					echo '<span class="gloskin-ui1-cart-sheet__item-variation">' . esc_html( $item['variation'] ) . '</span>';
				}
				echo '<span class="gloskin-ui1-cart-sheet__item-meta">';
				echo '<span class="gloskin-ui1-cart-sheet__item-qty">' . esc_html( $item['quantity'] ) . '&times;</span> ';
				echo '<span class="gloskin-ui1-cart-sheet__item-price">' . wp_kses_post( $item['price_html'] ) . '</span>';
				echo '</span>';
				echo '</span>';
				if ( $item['remove_url'] ) {
					/* Woo-native remove markup: wc-cart-fragments.js already binds AJAX
					 * remove + fragment refresh to `a.remove` links carrying these data
					 * attributes, so no custom cart JS is needed here. */
					/* translators: %s: cart line item's product name, used in the remove link's accessible label. */
					$item_remove_label = sprintf( __( 'Hapus %s', 'gloskin-site-core' ), $item['name'] );
					echo '<a href="' . esc_url( $item['remove_url'] ) . '" class="remove gloskin-ui1-cart-sheet__item-remove" aria-label="' . esc_attr( $item_remove_label ) . '" data-product_id="' . esc_attr( $item['product_id'] ) . '" data-cart_item_key="' . esc_attr( $item['key'] ) . '" data-product_sku="">&times;</a>';
				}
				echo '</li>';
			}
			echo '</ul>';
			echo '<div class="gloskin-ui1-cart-sheet__summary">';
			echo '<span>' . esc_html__( 'Subtotal', 'gloskin-site-core' ) . '</span>';
			echo '<span>' . wp_kses_post( $this->cart_subtotal() ) . '</span>';
			echo '</div>';
			echo '<div class="gloskin-ui1-cart-sheet__actions">';
			if ( $checkout ) {
				echo '<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="' . esc_url( $checkout ) . '">' . esc_html__( 'Lanjut ke Checkout', 'gloskin-site-core' ) . '</a>';
			}
			if ( $cart_url ) {
				echo '<a class="gloskin-ui1-button gloskin-ui1-button--ghost" href="' . esc_url( $cart_url ) . '">' . esc_html__( 'Lihat Keranjang', 'gloskin-site-core' ) . '</a>';
			}
			echo '</div>';
		} else {
			$helper_file = dirname( __DIR__ ) . '/templates/parts/readiness-helpers.php';
			if ( is_readable( $helper_file ) ) {
				require_once $helper_file;
			}
			if ( function_exists( 'gloskin_ui1_render_empty_state' ) ) {
				gloskin_ui1_render_empty_state( 'cart', __( 'Keranjang Anda masih kosong', 'gloskin-site-core' ), __( 'Produk yang Anda tambahkan akan tampil di sini.', 'gloskin-site-core' ), __( 'Lihat Skincare', 'gloskin-site-core' ), home_url( '/skincare/' ) );
			} else {
				echo '<p>' . esc_html__( 'Keranjang Anda masih kosong.', 'gloskin-site-core' ) . '</p>';
			}
		}
		return ob_get_clean();
	}

	/**
	 * Woo AJAX add-to-cart fragment updates for cart badge and mini-cart.
	 *
	 * @param array<string,string> $fragments Woo fragments.
	 * @return array<string,string>
	 */
	public function cart_fragments( $fragments ) {
		$count = $this->cart_count();
		$fragments['.gloskin-ui1-badge[data-gloskin-cart-count]'] =
			'<span class="gloskin-ui1-badge' . ( $count > 0 ? ' is-active' : '' ) . '" data-gloskin-cart-count aria-hidden="true">' . esc_html( $count ) . '</span>';
		/* translators: %d: number of items currently in the cart. */
		$count_label = sprintf( __( '%d item di keranjang', 'gloskin-site-core' ), $count );
		$fragments['[data-gloskin-cart-count-sr]'] =
			'<span class="screen-reader-text" data-gloskin-cart-count-sr>' . esc_html( $count_label ) . '</span>';
		$fragments['.gloskin-ui1-cart-sheet__body'] =
			'<div class="gloskin-ui1-cart-sheet__body">' . $this->render_mini_cart_body() . '</div>';
		return $fragments;
	}

	/* -----------------------------------------------------------------
	 * REST API: product resolution for wishlist
	 * ----------------------------------------------------------------- */

	/**
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route( 'gloskin/v1', '/products/resolve', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_resolve_products' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'ids' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );
		register_rest_route( 'gloskin/v1', '/products/quick-add', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_quick_add_projection' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			),
		) );
	}

	/**
	 * Resolve product IDs to published product data. Used by the wishlist
	 * to display current product information from IDs stored in localStorage.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_resolve_products( $request ) {
		$raw = (string) $request->get_param( 'ids' );
		if ( ! preg_match( '/^[\d,]+$/', $raw ) ) {
			return rest_ensure_response( array( 'products' => array() ) );
		}
		$ids = array_map( 'absint', explode( ',', $raw ) );
		$ids = array_filter( array_unique( $ids ) );
		$ids = array_slice( $ids, 0, 50 );

		if ( ! function_exists( 'wc_get_product' ) ) {
			return rest_ensure_response( array( 'products' => array() ) );
		}

		$products = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'get_status' ) ) {
				continue;
			}
			if ( 'publish' !== $product->get_status() ) {
				continue;
			}
			$products[] = array(
				'id'         => (int) $product->get_id(),
				'name'       => (string) $product->get_name(),
				'url'        => (string) get_permalink( $product->get_id() ),
				'price_html' => (string) $product->get_price_html(),
				'image_id'   => absint( $product->get_image_id() ),
			);
		}
		return rest_ensure_response( array( 'products' => $products ) );
	}

	/**
	 * SP-004 read-only Quick Add projection for a single variable product.
	 *
	 * Returns normalized identity/price data plus the *native* Woo
	 * variations_form markup, captured by calling Woo's own
	 * woocommerce_template_single_add_to_cart() with the product's own
	 * WC_Product wired into the $product global -- the exact same
	 * function/template chain Woo's single-product page itself uses
	 * (single-product/add-to-cart/variable.php). This never hand-rolls a
	 * variation resolver, never reads/writes the cart, and never mutates
	 * any state: it is a presentation read only, matching the existing
	 * wishlist products/resolve endpoint's contract.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_quick_add_projection( $request ) {
		$empty = array( 'found' => false );
		if ( ! $this->is_available() || ! function_exists( 'wc_get_product' ) ) {
			return rest_ensure_response( $empty );
		}

		$id                = absint( $request->get_param( 'id' ) );
		$quick_add_product = $id ? wc_get_product( $id ) : false;
		if ( ! is_object( $quick_add_product ) || ! method_exists( $quick_add_product, 'get_status' )
			|| 'publish' !== $quick_add_product->get_status()
			|| ! method_exists( $quick_add_product, 'get_type' ) || 'variable' !== $quick_add_product->get_type()
			|| ! $quick_add_product->is_purchasable()
			|| $this->is_excluded_from_catalog( $id ) ) {
			/* Hardening: align this public projection with the same
			 * catalog-visibility policy products()/products_paginated()
			 * already enforce, so a product explicitly marked "Search
			 * results only" or "Hidden" cannot be pulled into the catalog
			 * Quick Add surface merely by guessing its ID. This is a
			 * read-only consistency check, not an auth system. */
			return rest_ensure_response( $empty );
		}

		$form_html = '';
		if ( function_exists( 'woocommerce_template_single_add_to_cart' ) ) {
			/*
			 * Woo's own single-product add-to-cart templates read the
			 * $product global by documented convention --
			 * woocommerce_template_single_add_to_cart() dispatches to
			 * woocommerce_variable_add_to_cart(), which renders
			 * single-product/add-to-cart/variable.php using it. Swap it in
			 * only to capture this one native render, then restore whatever
			 * was there before; no global state is left mutated afterward,
			 * and nothing here reads or writes cart/session state.
			 */
			$previous_global = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
			$GLOBALS['product'] = $quick_add_product;
			ob_start();
			woocommerce_template_single_add_to_cart();
			$form_html = trim( (string) ob_get_clean() );
			if ( null === $previous_global ) {
				unset( $GLOBALS['product'] );
			} else {
				$GLOBALS['product'] = $previous_global;
			}
		}

		if ( '' === $form_html ) {
			return rest_ensure_response( $empty );
		}

		return rest_ensure_response( array(
			'found'      => true,
			'id'         => (int) $id,
			'name'       => (string) $quick_add_product->get_name(),
			'url'        => (string) get_permalink( $id ),
			'price_html' => (string) $quick_add_product->get_price_html(),
			'image_html' => (string) wp_get_attachment_image( absint( $quick_add_product->get_image_id() ), 'medium', false, array( 'class' => 'gloskin-ui1-quickadd__image', 'loading' => 'lazy', 'alt' => '' ) ),
			'form_html'  => $form_html,
		) );
	}

	/* -----------------------------------------------------------------
	 * Product search for live search overlay
	 * ----------------------------------------------------------------- */

	/**
	 * Search published products by title/content, respecting Woo's native
	 * search visibility term without inheriting catalog-only semantics.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Max results.
	 * @return array<int,array<string,mixed>>
	 */
	public function search_products( $query, $limit = 3 ) {
		if ( ! $this->is_available() ) {
			return array();
		}
		$query = sanitize_text_field( $query );
		if ( mb_strlen( $query ) < 2 ) {
			return array();
		}
		$posts = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( absint( $limit ), 6 ) ),
			's'              => $query,
			'tax_query'      => $this->search_visibility_tax_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Woo's native product_visibility taxonomy on a bounded live-search query; no custom SQL.
		) );
		$results = array();
		foreach ( $posts as $post ) {
			$product   = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
			$results[] = array(
				'id'         => (int) $post->ID,
				'title'      => get_the_title( $post ),
				'url'        => (string) get_permalink( $post ),
				'excerpt'    => wp_trim_words( has_excerpt( $post ) ? get_the_excerpt( $post ) : $post->post_content, 12 ),
				'image_id'   => absint( get_post_thumbnail_id( $post->ID ) ),
				'price_html' => is_object( $product ) && method_exists( $product, 'get_price_html' ) ? (string) $product->get_price_html() : '',
				'type'       => 'produk',
			);
		}
		return $results;
	}

	/* -----------------------------------------------------------------
	 * Existing product catalog methods
	 * ----------------------------------------------------------------- */

	/**
	 * @param int $limit Maximum records.
	 * @return array<int, array<string, mixed>>
	 */
	public function products( $limit = 8 ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'status'    => 'publish',
				'limit'     => max( 1, min( 20, absint( $limit ) ) ),
				'orderby'   => 'date',
				'order'     => 'DESC',
				'tax_query' => $this->catalog_visibility_tax_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Woo's own supported wc_get_products() extension point, not hand-rolled SQL.
			)
		);

		return $this->normalize_products( $products );
	}

	/**
	 * @param string $category_slug Woo product category slug.
	 * @param int    $limit Maximum records.
	 * @return array<int, array<string, mixed>>
	 */
	public function products_for_category( $category_slug, $limit = 20 ) {
		$category_slug = sanitize_title( $category_slug );
		if ( ! $this->is_available() || '' === $category_slug || ! $this->category_exists( $category_slug ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'status'    => 'publish',
				'limit'     => max( 1, min( 20, absint( $limit ) ) ),
				'category'  => array( $category_slug ),
				'orderby'   => 'menu_order',
				'order'     => 'ASC',
				'tax_query' => $this->catalog_visibility_tax_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Woo's own supported wc_get_products() extension point, not hand-rolled SQL.
			)
		);

		return $this->normalize_products( $products );
	}

	/**
	 * Small paginated public catalog projection for /shop/. WooCommerce
	 * remains the sole query owner -- this only normalizes wc_get_products()'s
	 * own limit/page/paginate=true contract so templates never depend on raw
	 * WC_Product objects or Woo pagination internals. Optionally scoped to one
	 * Woo category, reusing the exact same catalog-visibility guard.
	 *
	 * @param int    $page Current 1-based page.
	 * @param int    $per_page Products per page.
	 * @param string $category_slug Optional Woo product_cat slug filter.
	 * @return array{products:array<int,array<string,mixed>>,total:int,page:int,max_pages:int}
	 */
	public function products_paginated( $page = 1, $per_page = 12, $category_slug = '' ) {
		$empty = array( 'products' => array(), 'total' => 0, 'page' => 1, 'max_pages' => 1 );
		if ( ! $this->is_available() ) {
			return $empty;
		}

		$category_slug = sanitize_title( $category_slug );
		if ( '' !== $category_slug && ! $this->category_exists( $category_slug ) ) {
			return $empty;
		}

		$args = array(
			'status'    => 'publish',
			'limit'     => max( 1, min( 48, absint( $per_page ) ) ),
			'page'      => max( 1, absint( $page ) ),
			'paginate'  => true,
			'orderby'   => 'date',
			'order'     => 'DESC',
			'tax_query' => $this->catalog_visibility_tax_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Woo's own supported wc_get_products() extension point, not hand-rolled SQL.
		);
		if ( '' !== $category_slug ) {
			$args['category'] = array( $category_slug );
			$args['orderby']  = 'menu_order';
			$args['order']    = 'ASC';
		}

		$result = wc_get_products( $args );
		if ( ! is_object( $result ) || ! isset( $result->products ) ) {
			return $empty;
		}

		return array(
			'products'  => $this->normalize_products( $result->products ),
			'total'     => absint( $result->total ),
			'page'      => max( 1, absint( $page ) ),
			'max_pages' => max( 1, absint( $result->max_num_pages ) ),
		);
	}

	/**
	 * Woo-native catalog-visibility filter for public catalog projections:
	 * excludes products explicitly marked "Search results only" or "Hidden"
	 * (catalog_visibility), using Woo's own documented product_visibility
	 * term resolver -- never a hand-rolled taxonomy query. Mirrors exactly
	 * what WC_Query applies on Woo's own native shop/category archives.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	/**
	 * Single-product companion to catalog_visibility_tax_query(): the same
	 * Woo-native "exclude-from-catalog" term, checked against one already-
	 * resolved product ID rather than added to a wc_get_products() query.
	 * Used by rest_quick_add_projection() so a product hidden from the
	 * catalog cannot be exposed there merely by knowing/guessing its ID.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function is_excluded_from_catalog( $product_id ) {
		if ( ! function_exists( 'wc_get_product_visibility_term_ids' ) || ! function_exists( 'has_term' ) ) {
			return false;
		}
		$terms = wc_get_product_visibility_term_ids();
		$exclude_from_catalog = isset( $terms['exclude-from-catalog'] ) ? absint( $terms['exclude-from-catalog'] ) : 0;
		if ( ! $exclude_from_catalog ) {
			return false;
		}
		return (bool) has_term( $exclude_from_catalog, 'product_visibility', absint( $product_id ) );
	}

	/**
	 * Woo-native search-visibility filter for live search. Search-only
	 * products remain eligible; only products carrying Woo's own
	 * exclude-from-search term (Catalog only or Hidden) are excluded.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function search_visibility_tax_query() {
		if ( ! function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			return array();
		}
		$terms = wc_get_product_visibility_term_ids();
		$exclude_from_search = isset( $terms['exclude-from-search'] ) ? absint( $terms['exclude-from-search'] ) : 0;
		if ( ! $exclude_from_search ) {
			return array();
		}
		return array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array( $exclude_from_search ),
				'operator' => 'NOT IN',
			),
		);
	}

	private function catalog_visibility_tax_query() {
		if ( ! function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			return array();
		}
		$terms = wc_get_product_visibility_term_ids();
		$exclude_from_catalog = isset( $terms['exclude-from-catalog'] ) ? absint( $terms['exclude-from-catalog'] ) : 0;
		if ( ! $exclude_from_catalog ) {
			return array();
		}
		return array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array( $exclude_from_catalog ),
				'operator' => 'NOT IN',
			),
		);
	}

	/**
	 * @param string $category_slug Woo product category slug.
	 * @return bool
	 */
	public function category_exists( $category_slug ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$term = get_term_by( 'slug', sanitize_title( $category_slug ), 'product_cat' );
		return $term instanceof WP_Term;
	}

	/**
	 * @param string $category_slug Woo category slug.
	 * @return string
	 */
	public function category_url( $category_slug ) {
		if ( ! $this->is_available() ) {
			return '';
		}

		$term = get_term_by( 'slug', sanitize_title( $category_slug ), 'product_cat' );
		if ( ! $term instanceof WP_Term ) {
			return '';
		}

		$url = get_term_link( $term );
		return is_wp_error( $url ) ? '' : (string) $url;
	}

	/**
	 * Normalize Woo objects so templates do not depend on Woo globals.
	 *
	 * @param array<int, mixed> $products Woo product objects.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_products( $products ) {
		$normalized = array();

		foreach ( (array) $products as $product ) {
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
				continue;
			}

			$id = absint( $product->get_id() );
			if ( ! $id ) {
				continue;
			}

			/* Mirror WooCommerce's own loop add-to-cart eligibility exactly
			 * (woocommerce_template_loop_add_to_cart()/wc-template-functions.php):
			 * ajax_add_to_cart only applies when the product type supports it
			 * AND is purchasable AND in stock. This is never hand-invented --
			 * it is read straight from WC_Product so archive/card AJAX only
			 * ever fires where Woo's own native contract already allows it. */
			$purchasable = (bool) $product->is_purchasable();
			$in_stock    = (bool) $product->is_in_stock();
			$supports_ajax = method_exists( $product, 'supports' ) && $product->supports( 'ajax_add_to_cart' );

			$normalized[] = array(
				'id'                      => $id,
				'name'                    => (string) $product->get_name(),
				'url'                     => (string) get_permalink( $id ),
				'image_id'                => absint( $product->get_image_id() ),
				'price_html'              => (string) $product->get_price_html(),
				'short_description'       => wp_strip_all_tags( (string) $product->get_short_description() ),
				'sku'                     => (string) $product->get_sku(),
				'type'                    => method_exists( $product, 'get_type' ) ? (string) $product->get_type() : 'simple',
				'add_to_cart_url'         => (string) $product->add_to_cart_url(),
				'add_to_cart_text'        => __( 'Tambah ke keranjang', 'gloskin-site-core' ),
				'add_to_cart_description' => method_exists( $product, 'add_to_cart_description' ) ? wp_strip_all_tags( (string) $product->add_to_cart_description() ) : '',
				'purchasable'             => $purchasable,
				'in_stock'                => $in_stock,
				'ajax_add_to_cart'        => $purchasable && $in_stock && $supports_ajax,
			);
		}

		return $normalized;
	}

	/**
	 * SP-001 content-integrity guard for the single product's own render --
	 * narrowed after verification (see the 2026-08-11 hotfix addendum in
	 * docs/audits/single-product-commerce-remediation-2026-08-11.md).
	 *
	 * WooCommerce's own single-product tabs template calls the core
	 * the_content() template tag on the product's own post_content, which
	 * runs it through the full `the_content` filter chain (Gutenberg block
	 * rendering, shortcode execution, wpautop, embeds). The canonical
	 * sample bundle's own "Gloskin Fresh Gel Facial Wash" description
	 * (plugin/gloskin-site-core/migration-runtime/gloskin-sample-products-v1/products.json)
	 * was inspected directly and contains only plain heading/paragraph
	 * HTML -- no Woo block or shortcode of any kind -- so no evidence
	 * supports treating a broad shortcode family as the confirmed root
	 * cause. This repo has no live WordPress/WooCommerce/browser runtime,
	 * so the exact staging post_content and active callback chain could
	 * not be inspected live; that remains explicitly PENDING, not claimed
	 * VERIFIED (see the audit doc).
	 *
	 * What *is* true regardless of the unconfirmed trigger: a product's
	 * own description can never legitimately embed a live copy of its own
	 * single-product page -- that is true self-recursion, not editorial
	 * content, and is the one mechanism that would reproduce SP-001's
	 * reported symptom (a second full gallery/summary/tabs/add-to-cart
	 * stack nested inside the Description tab) verbatim. This guard is
	 * narrowed to strip *only* a `woocommerce/single-product` Gutenberg
	 * block or a legacy `[product_page]` shortcode whose own target ID
	 * equals the current product's own ID. Genuine editorial/cross-sell
	 * Woo content -- [products], [product_category], [product],
	 * [add_to_cart], a single-product block/shortcode referencing a
	 * *different* product, or any other woocommerce/* block -- is left
	 * completely untouched; none of those render a nested `.product` root
	 * matching the reported symptom, and stripping them was unjustified
	 * overreach not supported by any verified evidence.
	 *
	 * Scoped strictly to the product's own singular render (is_product() +
	 * in_the_loop() + the current loop post literally being that product),
	 * so it never touches product content rendered anywhere else --
	 * catalog/related-product cards and REST/search results use
	 * title/excerpt fields, never the_content(). It never disables
	 * the_content filtering globally, never forks a Woo template, and
	 * never hides anything with CSS: a genuinely self-referencing embed is
	 * removed from the content itself, before block/shortcode execution
	 * runs (priority 1, ahead of core's do_blocks/do_shortcode).
	 *
	 * @param string $content Content being filtered.
	 * @return string
	 */
	public function guard_single_product_description_content( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! in_the_loop() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return $content;
		}
		$current_id = absint( $post->ID );

		// Paired block, e.g. <!-- wp:woocommerce/single-product {"productId":501} --> ... <!-- /wp:woocommerce/single-product -->.
		$content = (string) preg_replace_callback(
			'#<!--\s*wp:woocommerce/single-product(\s+(\{[^}]*\}))?\s*-->.*?<!--\s*/wp:woocommerce/single-product\s*-->#is',
			function ( $matches ) use ( $current_id ) {
				return $this->strip_if_self_referencing_single_product( $matches, $current_id );
			},
			$content
		);
		// Self-closing block, e.g. <!-- wp:woocommerce/single-product {"productId":501} /-->.
		$content = (string) preg_replace_callback(
			'#<!--\s*wp:woocommerce/single-product(\s+(\{[^}]*\}))?\s*/-->#is',
			function ( $matches ) use ( $current_id ) {
				return $this->strip_if_self_referencing_single_product( $matches, $current_id );
			},
			$content
		);
		// Legacy [product_page id="..."] shortcode, self-referencing only.
		$content = (string) preg_replace_callback(
			'/\[product_page\b([^\]]*)\]/i',
			function ( $matches ) use ( $current_id ) {
				if ( preg_match( '/\bid\s*=\s*["\']?(\d+)/', (string) $matches[1], $id_match )
					&& absint( $id_match[1] ) === $current_id ) {
					return '';
				}
				return $matches[0];
			},
			$content
		);

		return $content;
	}

	/**
	 * @param array<int,string> $matches Regex match set; index 2 (if present) is the block's raw JSON attrs.
	 * @param int                $current_id Current product's own post ID.
	 * @return string
	 */
	private function strip_if_self_referencing_single_product( array $matches, $current_id ) {
		$json      = isset( $matches[2] ) ? $matches[2] : '';
		$attrs     = '' !== $json ? json_decode( $json, true ) : null;
		$target_id = is_array( $attrs ) && isset( $attrs['productId'] ) ? absint( $attrs['productId'] ) : 0;
		return ( $target_id && $target_id === $current_id ) ? '' : $matches[0];
	}

	/**
	 * Presentation-only support for approved BPOM/composition/usage data when
	 * already stored as WooCommerce product attributes.
	 *
	 * @return void
	 */
	public function render_product_facts() {
		global $product;

		if ( ! is_object( $product ) || ! method_exists( $product, 'get_attribute' ) ) {
			return;
		}

		$facts = array(
			'BPOM'        => $this->first_product_value( $product, array( 'bpom', 'pa_bpom' ), array( 'bpom' ) ),
			'Komposisi'   => $this->first_product_value( $product, array( 'composition', 'pa_composition' ), array( 'composition' ) ),
			'Cara Penggunaan' => $this->first_product_value( $product, array( 'usage', 'usage-instructions', 'pa_usage' ), array( 'usage', 'usage_instructions' ) ),
		);

		$facts = apply_filters( 'gloskin_site_core_product_facts', $facts, $product );
		$facts = array_filter(
			(array) $facts,
			static function ( $value ) {
				return is_scalar( $value ) && '' !== trim( (string) $value );
			}
		);

		if ( ! $facts ) {
			return;
		}

		echo '<dl class="gloskin-ui1-product-facts">';
		foreach ( $facts as $label => $value ) {
			echo '<div class="gloskin-ui1-product-facts__row">';
			echo '<dt>' . esc_html( (string) $label ) . '</dt>';
			echo '<dd>' . esc_html( (string) $value ) . '</dd>';
			echo '</div>';
		}
		echo '</dl>';
	}

	/**
	 * Render the wishlist toggle on the Woo single-product page. Wishlist
	 * only applies to real Woo products, so this renders whenever Woo's own
	 * hook fires with a valid product in scope -- no availability check is
	 * needed since the hook itself only exists when Woo is active.
	 *
	 * @return void
	 */
	public function render_wishlist_toggle() {
		global $product;

		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return;
		}

		$id   = absint( $product->get_id() );
		$name = method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';
		if ( ! $id ) {
			return;
		}

		/* Self-contained markup (not shared with the product-card helper in
		 * template-helpers.php) -- this keeps the adapter free of a
		 * dependency on the stateless presentation-helper layer, matching
		 * the one-canonical-owner-per-file convention already used across
		 * the codebase. The few lines of SVG/button markup are small enough
		 * that duplicating them is cheaper than adding cross-layer coupling. */
		/* translators: %s: product name, used in the wishlist "add" toggle's accessible label. */
		$add_label    = sprintf( __( 'Simpan %s ke favorit', 'gloskin-site-core' ), $name );
		/* translators: %s: product name, used in the wishlist "remove" toggle's accessible label. */
		$remove_label = sprintf( __( 'Hapus %s dari favorit', 'gloskin-site-core' ), $name );

		echo '<button type="button" class="gloskin-ui1-wishlist-toggle gloskin-ui1-wishlist-toggle--detail" data-gloskin-wishlist-toggle="' . esc_attr( $id ) . '" aria-pressed="false" data-label-add="' . esc_attr( $add_label ) . '" data-label-remove="' . esc_attr( $remove_label ) . '" aria-label="' . esc_attr( $add_label ) . '">';
		echo '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M10 16.8C8.4 15.5 3 11.4 3 7.8 3 5.6 4.8 3.5 7.2 3.5c1.3 0 2.2.7 2.8 1.3.6-.6 1.5-1.3 2.8-1.3C15.2 3.5 17 5.6 17 7.8c0 3.6-5.4 7.7-7 9z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>';
		echo '</button>';
	}

	/**
	 * @param object            $product Woo product.
	 * @param array<int,string> $names Candidate attribute names.
	 * @return string
	 */
	private function first_attribute( $product, $names ) {
		foreach ( $names as $name ) {
			$value = trim( (string) $product->get_attribute( $name ) );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * Read presentation facts from Woo attributes first, then simple Woo product meta.
	 * Gloskin never writes these values.
	 *
	 * @param object            $product Woo product.
	 * @param array<int,string> $attributes Candidate attribute names.
	 * @param array<int,string> $meta_keys Candidate product meta keys.
	 * @return string
	 */
	private function first_product_value( $product, $attributes, $meta_keys ) {
		$value = $this->first_attribute( $product, $attributes );
		if ( '' !== $value || ! method_exists( $product, 'get_meta' ) ) {
			return $value;
		}

		foreach ( $meta_keys as $key ) {
			$value = $product->get_meta( $key, true );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}

		return '';
	}
}
