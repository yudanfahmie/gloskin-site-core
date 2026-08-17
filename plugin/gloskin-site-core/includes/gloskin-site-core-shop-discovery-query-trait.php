<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
trait Gloskin_Site_Core_Shop_Discovery_Query_Trait {
	/**
	 * Shop Discovery delegates the complete catalog query to the adapter-owned
	 * Shop catalog component. No product query/SQL/filter state lives here.
	 *
	 * @param int                 $page Page number.
	 * @param array<string,mixed> $filters category, q, min_price, max_price.
	 * @return array{products:array<int,array<string,mixed>>,total:int,page:int,max_pages:int}
	 */
	private function catalog( $page, $filters ) {
		if ( ! class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter' )
			|| ! class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter_Shop_Catalog' )
		) {
			return array( 'products' => array(), 'total' => 0, 'page' => 1, 'max_pages' => 1 );
		}
		$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();
		$catalog = new Gloskin_Site_Core_WooCommerce_Adapter_Shop_Catalog( $adapter );
		return $catalog->products_paginated_filtered( $page, self::PER_PAGE, $filters );
	}

	/**
	 * Dynamic slider bounds share the same adapter-owned query implementation.
	 *
	 * @param string $category Product category slug.
	 * @param string $q Search query.
	 * @return array{min:float,max:float}
	 */
	private function catalog_price_bounds( $category, $q ) {
		if ( ! class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter' )
			|| ! class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter_Shop_Catalog' )
		) {
			return array( 'min' => 0.0, 'max' => 5000000.0 );
		}
		$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();
		$catalog = new Gloskin_Site_Core_WooCommerce_Adapter_Shop_Catalog( $adapter );
		return $catalog->price_bounds( $category, $q );
	}
}
