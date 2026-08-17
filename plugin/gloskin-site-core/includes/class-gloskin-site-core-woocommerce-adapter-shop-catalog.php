<?php
/**
 * Internal Shop catalog query component owned by the WooCommerce Adapter.
 *
 * Keeps every Shop product-query concern (category, search, price overlap and
 * pagination) on the Woo adapter side of the architecture. Shop Discovery is
 * only an HTTP/request/presentation orchestrator and never installs product
 * SQL filters or constructs a product WP_Query itself.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_WooCommerce_Adapter_Shop_Catalog {
	/** @var Gloskin_Site_Core_WooCommerce_Adapter */
	private $adapter;

	/** @var bool */
	private $query_scope_active = false;

	/** @var array<string,mixed> */
	private $query_filters = array();

	/**
	 * @param Gloskin_Site_Core_WooCommerce_Adapter $adapter Canonical Woo adapter.
	 */
	public function __construct( $adapter ) {
		$this->adapter = $adapter;
	}

	/**
	 * One Shop catalog API for category, q, price range and pagination.
	 *
	 * The historical all-ID compatibility fallback remains reachable only
	 * when category/q/min/max are all empty, through the existing adapter's
	 * products_paginated() method. q/min/max always use the bounded scoped
	 * query below and never fetch the full product ID set.
	 *
	 * @param int                 $page Current 1-based page.
	 * @param int                 $per_page Requested page size; filtered Shop is capped at 12.
	 * @param array<string,mixed> $filters category, q, min_price, max_price.
	 * @return array{products:array<int,array<string,mixed>>,total:int,page:int,max_pages:int}
	 */
	public function products_paginated_filtered( $page = 1, $per_page = 12, $filters = array() ) {
		$empty = array( 'products' => array(), 'total' => 0, 'page' => 1, 'max_pages' => 1 );
		if ( ! $this->adapter->available() ) {
			return $empty;
		}

		$page       = max( 1, absint( $page ) );
		$per_page   = max( 1, min( 12, absint( $per_page ) ) );
		$category   = isset( $filters['category'] ) ? sanitize_title( (string) $filters['category'] ) : '';
		$q          = isset( $filters['q'] ) ? trim( sanitize_text_field( (string) $filters['q'] ) ) : '';
		$min_price  = array_key_exists( 'min_price', $filters ) ? $filters['min_price'] : null;
		$max_price  = array_key_exists( 'max_price', $filters ) ? $filters['max_price'] : null;
		$has_search_or_price = '' !== $q || null !== $min_price || null !== $max_price;

		if ( '' !== $category && ! $this->adapter->category_exists( $category ) ) {
			return $empty;
		}

		if ( ! $has_search_or_price ) {
			return $this->adapter->products_paginated( $page, $per_page, $category );
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $empty;
		}

		$this->query_scope_active = true;
		$this->query_filters      = array(
			'category'  => $category,
			'q'         => $q,
			'min_price' => $min_price,
			'max_price' => $max_price,
		);
		add_filter( 'posts_clauses', array( $this, 'filter_shop_product_query_clauses' ), 20, 2 );

		try {
			$query = new WP_Query(
				array(
					'post_type'                   => 'product',
					'post_status'                 => 'publish',
					'posts_per_page'              => $per_page,
					'paged'                       => $page,
					'fields'                      => 'ids',
					'orderby'                     => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
					'no_found_rows'               => false,
					'tax_query'                   => $this->catalog_tax_query( $category ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Woo visibility/category taxonomy on one bounded Shop query.
					'_gloskin_shop_adapter_query' => 1,
				)
			);
		} finally {
			remove_filter( 'posts_clauses', array( $this, 'filter_shop_product_query_clauses' ), 20 );
			$this->query_scope_active = false;
			$this->query_filters      = array();
		}

		$products = array();
		foreach ( (array) $query->posts as $id ) {
			$product = wc_get_product( absint( $id ) );
			if ( $product ) {
				$products[] = $product;
			}
		}
		$max_pages = max( 1, absint( $query->max_num_pages ) );
		$page      = min( $page, $max_pages );

		return array(
			'products'  => $this->normalize_products( $products ),
			'total'     => absint( $query->found_posts ),
			'page'      => $page,
			'max_pages' => $max_pages,
		);
	}

	/**
	 * Scoped SQL extension for exactly the bounded adapter-owned Shop query.
	 *
	 * @param array<string,string> $clauses SQL clauses.
	 * @param WP_Query             $query Current query.
	 * @return array<string,string>
	 */
	public function filter_shop_product_query_clauses( $clauses, $query ) {
		if ( ! $this->query_scope_active
			|| ! $query instanceof WP_Query
			|| 1 !== absint( $query->get( '_gloskin_shop_adapter_query' ) )
			|| 'product' !== $query->get( 'post_type' )
		) {
			return $clauses;
		}

		global $wpdb;
		$q = isset( $this->query_filters['q'] ) ? trim( (string) $this->query_filters['q'] ) : '';
		if ( '' !== $q ) {
			$tokens = $this->normalize_q_tokens( $q );
			if ( $tokens ) {
				$taxonomies = $this->searchable_product_taxonomies();
				foreach ( $tokens as $token ) {
					$token_sql = $this->search_token_sql( $token, $taxonomies );
					// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- template and params are produced together by the adapter-owned helper.
					$clauses['where'] .= ' AND ' . $wpdb->prepare( $token_sql['sql'], $token_sql['params'] );
					// phpcs:enable
				}
				$clauses['fields'] .= ', ' . $this->build_relevance_sql( $q, $tokens );
				$clauses['orderby'] = "gloskin_relevance DESC, {$wpdb->posts}.menu_order ASC, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
			}
		}

		$min = array_key_exists( 'min_price', $this->query_filters ) ? $this->query_filters['min_price'] : null;
		$max = array_key_exists( 'max_price', $this->query_filters ) ? $this->query_filters['max_price'] : null;
		if ( null !== $min || null !== $max ) {
			$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
			if ( false === strpos( $clauses['join'], 'gloskin_price_lookup' ) ) {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Woo lookup table and core posts table names only.
				$clauses['join'] .= " INNER JOIN {$lookup} gloskin_price_lookup ON {$wpdb->posts}.ID = gloskin_price_lookup.product_id ";
				// phpcs:enable
			}
			/* Variable-product range overlap: product max >= requested min AND
			 * product min <= requested max. */
			if ( null !== $min ) {
				$clauses['where'] .= $wpdb->prepare( ' AND gloskin_price_lookup.max_price >= %f', (float) $min );
			}
			if ( null !== $max ) {
				$clauses['where'] .= $wpdb->prepare( ' AND gloskin_price_lookup.min_price <= %f', (float) $max );
			}
		}

		return $clauses;
	}

	/**
	 * Dynamic available price range for category + q, ignoring active price limits.
	 *
	 * @param string $category Product category slug.
	 * @param string $q Search query.
	 * @return array{min:float,max:float}
	 */
	public function price_bounds( $category = '', $q = '' ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array( 'min' => 0.0, 'max' => 5000000.0 );
		}

		$category    = sanitize_title( (string) $category );
		$tokens      = $this->normalize_q_tokens( trim( sanitize_text_field( (string) $q ) ) );
		$lookup      = $wpdb->prefix . 'wc_product_meta_lookup';
		$where_parts = array( "p.post_type = 'product'", "p.post_status = 'publish'" );
		$params      = array();

		if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			$visibility = wc_get_product_visibility_term_ids();
			$exclude    = ! empty( $visibility['exclude-from-catalog'] ) ? absint( $visibility['exclude-from-catalog'] ) : 0;
			if ( $exclude ) {
				$where_parts[] = "NOT EXISTS (
					SELECT 1 FROM {$wpdb->term_relationships} vis_tr
					INNER JOIN {$wpdb->term_taxonomy} vis_tt ON vis_tr.term_taxonomy_id = vis_tt.term_taxonomy_id
					WHERE vis_tr.object_id = p.ID AND vis_tt.term_taxonomy_id = %d
				)";
				$params[] = $exclude;
			}
		}

		if ( '' !== $category ) {
			$where_parts[] = "EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} cat_tr
				INNER JOIN {$wpdb->term_taxonomy} cat_tt ON cat_tr.term_taxonomy_id = cat_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} cat_t ON cat_tt.term_id = cat_t.term_id
				WHERE cat_tr.object_id = p.ID AND cat_tt.taxonomy = 'product_cat' AND cat_t.slug = %s
			)";
			$params[] = $category;
		}

		if ( $tokens ) {
			$taxonomies = $this->searchable_product_taxonomies();
			foreach ( $tokens as $token ) {
				$token_sql     = $this->search_token_sql( $token, $taxonomies, 'p' );
				$where_parts[] = $token_sql['sql'];
				foreach ( $token_sql['params'] as $param ) {
					$params[] = $param;
				}
			}
		}

		$where = implode( ' AND ', $where_parts );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are core/Woo-owned; all dynamic values are prepared below.
		$sql = "SELECT MIN(l.min_price) AS avail_min, MAX(l.max_price) AS avail_max
			FROM {$lookup} l
			WHERE l.product_id IN (
				SELECT DISTINCT p.ID FROM {$wpdb->posts} p WHERE {$where}
			)";
		$row = $params ? $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_row( $sql, ARRAY_A );
		// phpcs:enable

		$min = ( $row && null !== $row['avail_min'] ) ? (float) $row['avail_min'] : 0.0;
		$max = ( $row && null !== $row['avail_max'] ) ? (float) $row['avail_max'] : 5000000.0;
		if ( $max <= $min || $max <= 0.0 ) {
			$max = 5000000.0;
		}
		return array( 'min' => $min, 'max' => $max );
	}

	/** @param string $q Query. @return array<int,string> */
	private function normalize_q_tokens( $q ) {
		$q      = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', (string) $q );
		$q      = preg_replace( '/\s+/', ' ', trim( (string) $q ) );
		$tokens = array_filter( explode( ' ', $q ), static function ( $token ) { return '' !== $token; } );
		return array_slice( array_values( $tokens ), 0, 5 );
	}

	/** @return array<int,string> */
	private function searchable_product_taxonomies() {
		$taxonomies = array( 'product_cat', 'product_tag' );
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
				if ( ! empty( $attribute->attribute_name ) ) {
					$taxonomies[] = function_exists( 'wc_attribute_taxonomy_name' )
						? wc_attribute_taxonomy_name( (string) $attribute->attribute_name )
						: 'pa_' . $attribute->attribute_name;
				}
			}
		}
		if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'product_brand' ) ) {
			$taxonomies[] = 'product_brand';
		}
		return array_values( array_unique( $taxonomies ) );
	}

	/**
	 * @param string            $token Token.
	 * @param array<int,string> $taxonomies Searchable taxonomies.
	 * @param string            $post_alias Posts table alias.
	 * @return array{sql:string,params:array<int,mixed>}
	 */
	private function search_token_sql( $token, $taxonomies, $post_alias = '' ) {
		global $wpdb;
		$post_alias = '' === $post_alias ? $wpdb->posts : $post_alias;
		$lookup     = $wpdb->prefix . 'wc_product_meta_lookup';
		$like       = '%' . $wpdb->esc_like( $token ) . '%';
		$taxo_ph    = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- aliases/tables are internal; values are returned as params.
		$sql = "({$post_alias}.post_title LIKE %s
			OR {$post_alias}.post_excerpt LIKE %s
			OR {$post_alias}.post_content LIKE %s
			OR EXISTS (SELECT 1 FROM {$lookup} gl_sku WHERE gl_sku.product_id = {$post_alias}.ID AND gl_sku.sku LIKE %s AND gl_sku.sku != '')
			OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} gl_meta WHERE gl_meta.post_id = {$post_alias}.ID AND gl_meta.meta_key = '_global_unique_id' AND gl_meta.meta_value LIKE %s)
			OR EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} gl_tr
				INNER JOIN {$wpdb->term_taxonomy} gl_tt ON gl_tr.term_taxonomy_id = gl_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} gl_t ON gl_tt.term_id = gl_t.term_id
				WHERE gl_tr.object_id = {$post_alias}.ID
				AND gl_tt.taxonomy IN ({$taxo_ph})
				AND (gl_t.name LIKE %s OR gl_t.slug LIKE %s)
			))";
		// phpcs:enable

		$params = array( $like, $like, $like, $like, $like );
		foreach ( $taxonomies as $taxonomy ) {
			$params[] = (string) $taxonomy;
		}
		$params[] = $like;
		$params[] = $like;
		return array( 'sql' => $sql, 'params' => $params );
	}

	/** @param string $q Query. @param array<int,string> $tokens Tokens. @return string */
	private function build_relevance_sql( $q, $tokens ) {
		global $wpdb;
		$lookup   = $wpdb->prefix . 'wc_product_meta_lookup';
		$q_lower  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $q, 'UTF-8' ) : strtolower( $q );
		$q_starts = $wpdb->esc_like( $q ) . '%';
		$parts    = array();
		$params   = array();

		$parts[]  = "(CASE WHEN LOWER({$wpdb->posts}.post_title) = %s THEN 100 ELSE 0 END)";
		$params[] = $q_lower;
		$parts[]  = "(CASE WHEN {$wpdb->posts}.post_title LIKE %s THEN 80 ELSE 0 END)";
		$params[] = $q_starts;
		$parts[]  = "(CASE WHEN EXISTS (SELECT 1 FROM {$lookup} rel_a WHERE rel_a.product_id = {$wpdb->posts}.ID AND LOWER(rel_a.sku) = %s AND rel_a.sku != '') THEN 75 ELSE 0 END)";
		$params[] = $q_lower;
		$parts[]  = "(CASE WHEN EXISTS (SELECT 1 FROM {$lookup} rel_b WHERE rel_b.product_id = {$wpdb->posts}.ID AND rel_b.sku LIKE %s AND rel_b.sku != '') THEN 65 ELSE 0 END)";
		$params[] = $q_starts;
		foreach ( $tokens as $token ) {
			$like      = '%' . $wpdb->esc_like( $token ) . '%';
			$parts[]   = "(CASE WHEN {$wpdb->posts}.post_title LIKE %s THEN 50 ELSE 0 END)";
			$params[]  = $like;
			$parts[]   = "(CASE WHEN {$wpdb->posts}.post_excerpt LIKE %s THEN 20 ELSE 0 END)";
			$params[]  = $like;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- CASE fragments contain fixed placeholders only.
		return $wpdb->prepare( '(' . implode( ' + ', $parts ) . ') AS gloskin_relevance', $params );
		// phpcs:enable
	}

	/** @param string $category Category. @return array<int,array<string,mixed>> */
	private function catalog_tax_query( $category ) {
		$query = array();
		if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			$terms   = wc_get_product_visibility_term_ids();
			$exclude = isset( $terms['exclude-from-catalog'] ) ? absint( $terms['exclude-from-catalog'] ) : 0;
			if ( $exclude ) {
				$query[] = array( 'taxonomy' => 'product_visibility', 'field' => 'term_taxonomy_id', 'terms' => array( $exclude ), 'operator' => 'NOT IN' );
			}
		}
		if ( '' !== $category ) {
			$query[] = array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array( $category ) );
		}
		return $query;
	}

	/** @param array<int,mixed> $products Woo product objects. @return array<int,array<string,mixed>> */
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
			$purchasable  = method_exists( $product, 'is_purchasable' ) && $product->is_purchasable();
			$in_stock     = method_exists( $product, 'is_in_stock' ) && $product->is_in_stock();
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
				'add_to_cart_text'        => $this->adapter->direct_cart_cta_label(),
				'add_to_cart_description' => method_exists( $product, 'add_to_cart_description' ) ? wp_strip_all_tags( (string) $product->add_to_cart_description() ) : '',
				'purchasable'             => (bool) $purchasable,
				'in_stock'                => (bool) $in_stock,
				'ajax_add_to_cart'        => (bool) ( $purchasable && $in_stock && $supports_ajax ),
			);
		}
		return $normalized;
	}
}
