<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
trait Gloskin_Site_Core_Shop_Discovery_Query_Trait {
	/**
	 * Preserve the historical all-ID fallback only for the fully unfiltered
	 * path. q/min/max requests never enter that -1 query; they use a bounded
	 * 12-per-page query and hydrate only the current page.
	 *
	 * @param int                 $page    Page number.
	 * @param array<string,mixed> $filters Filters (category, q, min_price, max_price).
	 * @return array{products:array<int,array<string,mixed>>,total:int,page:int,max_pages:int}
	 */
	private function catalog( $page, $filters ) {
		$category         = isset( $filters['category'] ) ? (string) $filters['category'] : '';
		$q                = isset( $filters['q'] ) ? trim( (string) $filters['q'] ) : '';
		$has_query_filters = '' !== $q || null !== $filters['min_price'] || null !== $filters['max_price'];

		if ( ! $has_query_filters ) {
			if ( ! class_exists( 'Gloskin_Site_Core_WooCommerce_Adapter' ) ) {
				return array( 'products' => array(), 'total' => 0, 'page' => 1, 'max_pages' => 1 );
			}
			$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();
			return $adapter->products_paginated( $page, self::PER_PAGE, $category );
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return array( 'products' => array(), 'total' => 0, 'page' => 1, 'max_pages' => 1 );
		}

		$this->query_scope_active = true;
		$this->query_filters      = $filters;
		add_filter( 'posts_clauses', array( $this, 'filter_product_query_clauses' ), 20, 2 );

		$args  = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $page,
			'fields'         => 'ids',
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
			'no_found_rows'  => false,
			'tax_query'      => $this->catalog_tax_query( $category ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Woo-native product visibility/category taxonomy only.
		);
		$query = new WP_Query( $args );

		remove_filter( 'posts_clauses', array( $this, 'filter_product_query_clauses' ), 20 );
		$this->query_scope_active = false;
		$this->query_filters      = array();

		$products = array();
		foreach ( (array) $query->posts as $id ) {
			$product = wc_get_product( absint( $id ) );
			if ( $product ) {
				$products[] = $product;
			}
		}
		$max_pages = max( 1, absint( $query->max_num_pages ) );
		$page      = min( max( 1, $page ), $max_pages );
		return array(
			'products'  => $this->normalize_products( $products ),
			'total'     => absint( $query->found_posts ),
			'page'      => $page,
			'max_pages' => $max_pages,
		);
	}

	/**
	 * Scoped only around the bounded filtered query above.  Removed immediately
	 * after one query; never mutates global pre_get_posts.
	 *
	 * When q is present the filter:
	 *   1. Appends per-token WHERE fragments (title|excerpt|content|SKU|meta|taxonomy).
	 *   2. Injects a relevance score column into SELECT for ORDER BY.
	 *   3. Replaces orderby with relevance DESC / menu_order ASC / date DESC / ID DESC.
	 *
	 * When min/max price present the filter:
	 *   4. Joins wc_product_meta_lookup (1:1) and applies Woo-compatible range overlap.
	 *
	 * @param array<string,string> $clauses SQL clauses from WP_Query.
	 * @param WP_Query             $query   Current WP_Query instance.
	 * @return array<string,string>
	 */
	public function filter_product_query_clauses( $clauses, $query ) {
		if ( ! $this->query_scope_active
			|| ! $query instanceof WP_Query
			|| 'product' !== $query->get( 'post_type' )
		) {
			return $clauses;
		}

		global $wpdb;

		$q = isset( $this->query_filters['q'] ) ? trim( (string) $this->query_filters['q'] ) : '';

		// --- Smart search (q) ---
		if ( '' !== $q ) {
			$tokens = $this->normalize_q_tokens( $q );
			if ( ! empty( $tokens ) ) {
				$taxo_names = $this->searchable_product_taxonomies();

				// Each token must match at least one searchable surface.
				foreach ( $tokens as $token ) {
					$tok = $this->search_token_sql( $token, $taxo_names ); // uses $wpdb->posts alias.
					// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- each tok is a controlled template.
					$clauses['where'] .= ' AND ' . $wpdb->prepare( $tok['sql'], $tok['params'] );
					// phpcs:enable
				}

				// Inject relevance score into SELECT for ORDER BY.
				// $clauses['fields'] starts as "{$wpdb->posts}.ID" for fields=>'ids'.
				// $wpdb->get_col() returns only the first column (ID); relevance is
				// computed by MySQL for ordering only and never returned to PHP.
				$clauses['fields'] .= ', ' . $this->build_relevance_sql( $q, $tokens );

				// Override orderby: relevance first, then deterministic tie-breaker.
				$clauses['orderby'] = "gloskin_relevance DESC, {$wpdb->posts}.menu_order ASC, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
			}
		}

		// --- Price overlap filter (Woo-compatible) ---
		$min = isset( $this->query_filters['min_price'] ) ? $this->query_filters['min_price'] : null;
		$max = isset( $this->query_filters['max_price'] ) ? $this->query_filters['max_price'] : null;
		if ( null !== $min || null !== $max ) {
			$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
			if ( false === strpos( $clauses['join'], 'gloskin_price_lookup' ) ) {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names.
				$clauses['join'] .= " INNER JOIN {$lookup} gloskin_price_lookup ON {$wpdb->posts}.ID = gloskin_price_lookup.product_id ";
				// phpcs:enable
			}
			/* Woo-compatible range overlap: product.max >= requested.min AND
			 * product.min <= requested.max. Keeps variable products whose
			 * purchasable range intersects the requested interval. */
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
	 * Build the tax_query for catalog visibility and optional category filter.
	 *
	 * @param string $category Category slug, or '' for all.
	 * @return array<int,array<string,mixed>>
	 */
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
}
