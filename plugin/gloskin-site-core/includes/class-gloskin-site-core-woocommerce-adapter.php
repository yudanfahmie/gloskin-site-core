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
					echo '<a href="' . esc_url( $item['remove_url'] ) . '" class="remove gloskin-ui1-cart-sheet__item-remove" aria-label="' . esc_attr( sprintf( __( 'Hapus %s', 'gloskin-site-core' ), $item['name'] ) ) . '" data-product_id="' . esc_attr( $item['product_id'] ) . '" data-cart_item_key="' . esc_attr( $item['key'] ) . '" data-product_sku="">&times;</a>';
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
			echo '<div class="gloskin-ui1-cart-sheet__empty">';
			echo '<p>' . esc_html__( 'Keranjang Anda masih kosong.', 'gloskin-site-core' ) . '</p>';
			if ( $cart_url ) {
				echo '<a class="gloskin-ui1-text-link" href="' . esc_url( home_url( '/skincare/' ) ) . '">' . esc_html__( 'Lihat Skincare', 'gloskin-site-core' ) . '</a>';
			}
			echo '</div>';
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
		$fragments['[data-gloskin-cart-count-sr]'] =
			'<span class="screen-reader-text" data-gloskin-cart-count-sr>' . esc_html( sprintf( __( '%d item di keranjang', 'gloskin-site-core' ), $count ) ) . '</span>';
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

	/* -----------------------------------------------------------------
	 * Product search for live search overlay
	 * ----------------------------------------------------------------- */

	/**
	 * Search published products by title/content.
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
		) );
		$results = array();
		foreach ( $posts as $post ) {
			$results[] = array(
				'id'       => (int) $post->ID,
				'title'    => get_the_title( $post ),
				'url'      => (string) get_permalink( $post ),
				'excerpt'  => wp_trim_words( has_excerpt( $post ) ? get_the_excerpt( $post ) : $post->post_content, 12 ),
				'image_id' => absint( get_post_thumbnail_id( $post->ID ) ),
				'type'     => 'produk',
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
				'status'  => 'publish',
				'limit'   => max( 1, min( 20, absint( $limit ) ) ),
				'orderby' => 'date',
				'order'   => 'DESC',
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
				'status'   => 'publish',
				'limit'    => max( 1, min( 20, absint( $limit ) ) ),
				'category' => array( $category_slug ),
				'orderby'  => 'menu_order',
				'order'    => 'ASC',
			)
		);

		return $this->normalize_products( $products );
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

			$normalized[] = array(
				'id'                => $id,
				'name'              => (string) $product->get_name(),
				'url'               => (string) get_permalink( $id ),
				'image_id'          => absint( $product->get_image_id() ),
				'price_html'        => (string) $product->get_price_html(),
				'short_description' => wp_strip_all_tags( (string) $product->get_short_description() ),
				'sku'               => (string) $product->get_sku(),
				'add_to_cart_url'   => (string) $product->add_to_cart_url(),
				'add_to_cart_text'  => __( 'Tambah ke keranjang', 'gloskin-site-core' ),
				'purchasable'       => (bool) $product->is_purchasable(),
				'in_stock'          => (bool) $product->is_in_stock(),
			);
		}

		return $normalized;
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
		$add_label    = sprintf( __( 'Simpan %s ke favorit', 'gloskin-site-core' ), $name );
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
