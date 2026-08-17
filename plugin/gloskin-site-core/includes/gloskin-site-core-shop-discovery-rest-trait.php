<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
trait Gloskin_Site_Core_Shop_Discovery_Rest_Trait {
	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_catalog( $request ) {
		$page = max( 1, min( 1000, absint( $request->get_param( 'page' ) ) ) );
		$category = sanitize_title( (string) $request->get_param( 'category' ) );
		$q = trim( sanitize_text_field( (string) $request->get_param( 'q' ) ) );
		if ( mb_strlen( $q ) > 100 ) {
			return new WP_Error( 'gloskin_shop_search', __( 'Pencarian terlalu panjang.', 'gloskin-site-core' ), array( 'status' => 400 ) );
		}
		$min = $this->parse_price( $request->get_param( 'min_price' ) );
		$max = $this->parse_price( $request->get_param( 'max_price' ) );
		if ( is_wp_error( $min ) || is_wp_error( $max ) ) {
			return new WP_Error( 'gloskin_shop_price', __( 'Rentang harga tidak valid.', 'gloskin-site-core' ), array( 'status' => 400 ) );
		}
		if ( null !== $min && null !== $max && $min > $max ) {
			return new WP_Error( 'gloskin_shop_price_range', __( 'Harga minimum tidak boleh lebih besar dari harga maksimum.', 'gloskin-site-core' ), array( 'status' => 400 ) );
		}
		$mappings = $this->mapped_categories();
		if ( '' !== $category && ! isset( $mappings[ $category ] ) ) {
			return new WP_Error( 'gloskin_shop_category', __( 'Kategori produk tidak tersedia.', 'gloskin-site-core' ), array( 'status' => 400 ) );
		}

		$filters = array( 'category' => $category, 'q' => $q, 'min_price' => $min, 'max_price' => $max );
		$catalog = $this->catalog( $page, $filters );
		$bounds  = $this->catalog_price_bounds( $category, $q );

		$results = array(
			'products'            => $catalog['products'],
			'total'               => $catalog['total'],
			'page'                => $catalog['page'],
			'max_pages'           => $catalog['max_pages'],
			'category'            => $category,
			'category_label'      => '' !== $category && isset( $mappings[ $category ] ) ? $mappings[ $category ] : '',
			'woo_ready'           => class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter' ) ? ( new Gloskin_Site_Core_WooCommerce_Adapter() )->available() : function_exists( 'wc_get_product' ),
			'filtered'            => '' !== $category || '' !== $q || null !== $min || null !== $max,
			'q'                   => $q,
			'min_price'           => $min,
			'max_price'           => $max,
			'available_min_price' => $bounds['min'],
			'available_max_price' => $bounds['max'],
		);
		$html = $this->render_results( $results );
		if ( '' === $html ) {
			return new WP_Error( 'gloskin_shop_render', __( 'Katalog belum dapat dirender.', 'gloskin-site-core' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response(
			array(
				'html'                => $html,
				'category'            => $category,
				'q'                   => $q,
				'min_price'           => null === $min ? '' : (string) $min,
				'max_price'           => null === $max ? '' : (string) $max,
				'page'                => (int) $catalog['page'],
				'total'               => (int) $catalog['total'],
				'max_pages'           => (int) $catalog['max_pages'],
				'available_min_price' => (float) $bounds['min'],
				'available_max_price' => (float) $bounds['max'],
			)
		);
	}

	/** @param mixed $value Raw price. @return float|null|WP_Error */
	private function parse_price( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		if ( mb_strlen( $value ) > 16 || ! preg_match( '/^\d{1,9}(?:\.\d{1,2})?$/', $value ) ) {
			return new WP_Error( 'invalid_price' );
		}
		$number = (float) $value;
		if ( $number < 0 || $number > self::MAX_PRICE ) {
			return new WP_Error( 'invalid_price' );
		}
		return $number;
	}

	/** @return array<string,string> */
	private function mapped_categories() {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'meta_key'       => 'gloskin_woo_category_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded mapping projection already used by Gloskin pages.
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);
		$result = array();
		foreach ( $pages as $page ) {
			$slug = sanitize_title( (string) get_post_meta( $page->ID, 'gloskin_woo_category_slug', true ) );
			if ( '' !== $slug ) {
				$result[ $slug ] = get_the_title( $page );
			}
		}
		return $result;
	}

	/** @param array<string,mixed> $results Result context. @return string */
	private function render_results( $results ) {
		$partial = dirname( __DIR__ ) . '/templates/parts/shop-results.php';
		if ( ! is_readable( $partial ) ) { return ''; }
		$gloskin_shop_results = $results;
		ob_start(); include $partial; return trim( (string) ob_get_clean() );
	}
}
