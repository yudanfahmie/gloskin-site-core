<?php
/**
 * Read-only WooCommerce presentation adapter.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_WooCommerce_Adapter {
	/** @var bool */
	private $available;

	public function __construct() {
		$this->available = class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' );
	}

	/**
	 * Register presentation-only Woo hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! $this->available ) {
			return;
		}

		add_action( 'woocommerce_product_meta_end', array( $this, 'render_product_facts' ), 20 );
		add_filter( 'body_class', array( $this, 'body_classes' ) );
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
		if ( ! $this->available ) {
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
		return $this->available && function_exists( 'is_shop' ) && is_shop();
	}

	/**
	 * @return bool
	 */
	public function available() {
		return $this->available;
	}

	/**
	 * @param int $limit Maximum records.
	 * @return array<int, array<string, mixed>>
	 */
	public function products( $limit = 8 ) {
		if ( ! $this->available ) {
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
		if ( ! $this->available || '' === $category_slug || ! $this->category_exists( $category_slug ) ) {
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
		if ( ! $this->available ) {
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
		if ( ! $this->available ) {
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
				'add_to_cart_text'  => (string) $product->add_to_cart_text(),
				'purchasable'       => (bool) $product->is_purchasable(),
				'in_stock'          => (bool) $product->is_in_stock(),
			);
		}

		return $normalized;
	}

	/**
	 * Presentation-only support for approved BPOM/composition/usage data when
	 * already stored as WooCommerce product attributes or product meta.
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
			'Composition' => $this->first_product_value( $product, array( 'composition', 'pa_composition' ), array( 'composition' ) ),
			'Usage'       => $this->first_product_value( $product, array( 'usage', 'usage-instructions', 'pa_usage' ), array( 'usage', 'usage_instructions' ) ),
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
