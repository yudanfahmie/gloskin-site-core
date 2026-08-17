<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
trait Gloskin_Site_Core_Shop_Discovery_Normalize_Trait {
	/** @param array<int,mixed> $products Woo products. @return array<int,array<string,mixed>> */
	private function normalize_products( $products ) {
		$normalized = array();
		foreach ( (array) $products as $product ) {
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) { continue; }
			$id = absint( $product->get_id() );
			if ( ! $id ) { continue; }
			$purchasable = method_exists( $product, 'is_purchasable' ) && $product->is_purchasable();
			$in_stock = method_exists( $product, 'is_in_stock' ) && $product->is_in_stock();
			$supports_ajax = method_exists( $product, 'supports' ) && $product->supports( 'ajax_add_to_cart' );
			$normalized[] = array(
				'id'                     => $id,
				'name'                  => (string) $product->get_name(),
				'url'                   => (string) get_permalink( $id ),
				'image_id'              => absint( $product->get_image_id() ),
				'price_html'            => (string) $product->get_price_html(),
				'short_description'    => wp_strip_all_tags( (string) $product->get_short_description() ),
				'sku'                   => (string) $product->get_sku(),
				'type'                  => method_exists( $product, 'get_type' ) ? (string) $product->get_type() : 'simple',
				'add_to_cart_url'       => (string) $product->add_to_cart_url(),
				'add_to_cart_text'      => class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter' ) ? ( new Gloskin_Site_Core_WooCommerce_Adapter() )->direct_cart_cta_label() : __( 'Keranjang', 'gloskin-site-core' ),
				'add_to_cart_description' => method_exists( $product, 'add_to_cart_description' ) ? wp_strip_all_tags( (string) $product->add_to_cart_description() ) : '',
				'purchasable'          => (bool) $purchasable,
				'in_stock'             => (bool) $in_stock,
				'ajax_add_to_cart'     => (bool) ( $purchasable && $in_stock && $supports_ajax ),
			);
		}
		return $normalized;
	}
}
