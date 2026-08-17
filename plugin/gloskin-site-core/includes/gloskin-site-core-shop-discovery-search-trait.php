<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Smart-search helpers for the Shop Discovery service.
 *
 * Provides:
 *   - normalize_q_tokens()         — tokenise a raw query into ≤5 clean tokens.
 *   - searchable_product_taxonomies() — product_cat, product_tag, pa_*, product_brand.
 *   - search_token_sql()            — per-token SQL template (title/excerpt/content/SKU/meta/taxonomy).
 *   - build_relevance_sql()         — fully-prepared relevance CASE expression for SELECT.
 *   - get_price_bounds()            — single aggregate MIN/MAX from wc_product_meta_lookup.
 *
 * All SQL that touches user input goes through $wpdb->prepare().
 * Internal Gloskin system meta is NEVER included in the searchable surfaces.
 * Taxonomy search is bounded to product_cat, product_tag, and registered pa_* attributes.
 *
 * @package GloskinSiteCore
 */
trait Gloskin_Site_Core_Shop_Discovery_Search_Trait {

	/**
	 * Normalise a raw q string into up to 5 meaningful whitespace-separated tokens.
	 *
	 * Steps:
	 *   1. Strip non-letter/non-digit/non-whitespace characters.
	 *   2. Collapse internal whitespace.
	 *   3. Split on whitespace, discard empty tokens.
	 *   4. Cap at 5 tokens.
	 *
	 * @param string $q Already sanitize_text_field()'d and trimmed query.
	 * @return string[]
	 */
	private function normalize_q_tokens( $q ) {
		$q      = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', (string) $q );
		$q      = preg_replace( '/\s+/', ' ', trim( (string) $q ) );
		$tokens = array_filter( explode( ' ', $q ), static function ( $t ) { return '' !== $t; } );
		return array_slice( array_values( $tokens ), 0, 5 );
	}

	/**
	 * Return the product taxonomy slugs eligible for search:
	 *   product_cat, product_tag, and all registered pa_* attribute taxonomies.
	 *   product_brand is included only when the taxonomy is registered (plugin-supplied).
	 *
	 * Never returns arbitrary or internal taxonomies.
	 *
	 * @return string[]
	 */
	private function searchable_product_taxonomies() {
		$taxos = array( 'product_cat', 'product_tag' );
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( (array) wc_get_attribute_taxonomies() as $attr ) {
				if ( ! empty( $attr->attribute_name ) ) {
					$taxos[] = function_exists( 'wc_attribute_taxonomy_name' )
						? wc_attribute_taxonomy_name( (string) $attr->attribute_name )
						: 'pa_' . $attr->attribute_name;
				}
			}
		}
		if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'product_brand' ) ) {
			$taxos[] = 'product_brand';
		}
		return array_unique( $taxos );
	}

	/**
	 * Build a raw SQL template fragment that requires a single token to match
	 * at least ONE of the following surfaces:
	 *
	 *   HIGH   — post_title
	 *   HIGH   — SKU via wc_product_meta_lookup (non-empty only)
	 *   MED    — product taxonomy term name/slug (product_cat, product_tag, pa_*)
	 *   MED    — post_excerpt
	 *   LOWER  — post_content
	 *   LOWER  — _global_unique_id postmeta (explicit allowlist, single key)
	 *
	 * Returns ['sql' => template string with %s placeholders, 'params' => flat array].
	 * IMPORTANT: do NOT pass the returned 'sql' through $wpdb->prepare() before
	 * collecting all sibling params — use the single-prepare pattern in callers.
	 *
	 * @param string   $token      Single normalised token.
	 * @param string[] $taxo_names Searchable taxonomy slugs.
	 * @param string   $post_alias SQL table alias for posts rows. Default: $wpdb->posts.
	 * @return array{sql:string,params:array<int,mixed>}
	 */
	private function search_token_sql( $token, $taxo_names, $post_alias = '' ) {
		global $wpdb;
		if ( '' === $post_alias ) {
			$post_alias = $wpdb->posts;
		}
		$lookup  = $wpdb->prefix . 'wc_product_meta_lookup';
		$like    = '%' . $wpdb->esc_like( $token ) . '%';
		$taxo_ph = implode( ', ', array_fill( 0, count( $taxo_names ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $post_alias/$lookup are table names only.
		$sql = "({$post_alias}.post_title LIKE %s
			OR {$post_alias}.post_excerpt LIKE %s
			OR {$post_alias}.post_content LIKE %s
			OR EXISTS (
				SELECT 1 FROM {$lookup} gl_sku
				WHERE gl_sku.product_id = {$post_alias}.ID
				AND gl_sku.sku LIKE %s AND gl_sku.sku != ''
			)
			OR EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} gl_meta
				WHERE gl_meta.post_id = {$post_alias}.ID
				AND gl_meta.meta_key = '_global_unique_id'
				AND gl_meta.meta_value LIKE %s
			)
			OR EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} gl_tr
				INNER JOIN {$wpdb->term_taxonomy} gl_tt
					ON gl_tr.term_taxonomy_id = gl_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} gl_t ON gl_tt.term_id = gl_t.term_id
				WHERE gl_tr.object_id = {$post_alias}.ID
				AND gl_tt.taxonomy IN ({$taxo_ph})
				AND (gl_t.name LIKE %s OR gl_t.slug LIKE %s)
			))";
		// phpcs:enable

		$params = array( $like, $like, $like, $like, $like );
		foreach ( $taxo_names as $t ) {
			$params[] = (string) $t;
		}
		$params[] = $like;
		$params[] = $like;

		return array( 'sql' => $sql, 'params' => $params );
	}

	/**
	 * Build a fully-prepared SQL expression that computes a deterministic
	 * relevance score for each product row.  Caller appends to $clauses['fields'].
	 *
	 * Score components (additive):
	 *   Exact title (case-insensitive via LOWER)    → +100
	 *   Title starts with full q                   → +80
	 *   SKU exact match                            → +75
	 *   SKU starts with q                          → +65
	 *   Per token: title contains token            → +50 each
	 *   Per token: excerpt contains token          → +20 each
	 *
	 * Returns a ready-to-embed SQL string aliased as gloskin_relevance.
	 *
	 * @param string   $q      Full normalised query string.
	 * @param string[] $tokens Individual tokens (from normalize_q_tokens).
	 * @return string Fully prepared SQL ready for appending to $clauses['fields'].
	 */
	private function build_relevance_sql( $q, $tokens ) {
		global $wpdb;
		$lookup   = $wpdb->prefix . 'wc_product_meta_lookup';
		$q_lower  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $q, 'UTF-8' ) : strtolower( $q );
		$q_starts = $wpdb->esc_like( $q ) . '%';

		$parts  = array();
		$params = array();

		// Exact title (LOWER comparison for case-insensitive).
		$parts[]  = "(CASE WHEN LOWER({$wpdb->posts}.post_title) = %s THEN 100 ELSE 0 END)";
		$params[] = $q_lower;

		// Title starts with full q.
		$parts[]  = "(CASE WHEN {$wpdb->posts}.post_title LIKE %s THEN 80 ELSE 0 END)";
		$params[] = $q_starts;

		// SKU exact.
		$parts[]  = "(CASE WHEN EXISTS (SELECT 1 FROM {$lookup} rel_a WHERE rel_a.product_id = {$wpdb->posts}.ID AND LOWER(rel_a.sku) = %s AND rel_a.sku != '') THEN 75 ELSE 0 END)";
		$params[] = $q_lower;

		// SKU starts with.
		$parts[]  = "(CASE WHEN EXISTS (SELECT 1 FROM {$lookup} rel_b WHERE rel_b.product_id = {$wpdb->posts}.ID AND rel_b.sku LIKE %s AND rel_b.sku != '') THEN 65 ELSE 0 END)";
		$params[] = $q_starts;

		// Per-token: title and excerpt.
		foreach ( $tokens as $token ) {
			$tok_like = '%' . $wpdb->esc_like( $token ) . '%';
			$parts[]  = "(CASE WHEN {$wpdb->posts}.post_title LIKE %s THEN 50 ELSE 0 END)";
			$params[] = $tok_like;
			$parts[]  = "(CASE WHEN {$wpdb->posts}.post_excerpt LIKE %s THEN 20 ELSE 0 END)";
			$params[] = $tok_like;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- composed entirely from prepare()-safe CASE fragments.
		return $wpdb->prepare(
			'(' . implode( ' + ', $parts ) . ') AS gloskin_relevance',
			$params
		);
		// phpcs:enable
	}

	/**
	 * Compute available price bounds for products that match category + q
	 * (but ignoring the current min/max price filter).
	 *
	 * Uses a SINGLE aggregate SQL query against wc_product_meta_lookup.
	 * No product hydration, no posts_per_page=-1.
	 *
	 * @param string   $category Category slug, or '' for all categories.
	 * @param string[] $tokens   Normalised q tokens (may be empty).
	 * @return array{min:float,max:float}
	 */
	private function get_price_bounds( $category, $tokens ) {
		global $wpdb;
		$lookup      = $wpdb->prefix . 'wc_product_meta_lookup';
		$where_parts = array( "p.post_type = 'product'", "p.post_status = 'publish'" );
		$all_params  = array();

		// Exclude catalog-hidden products using Woo's existing visibility term.
		if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			$vis  = wc_get_product_visibility_term_ids();
			$excl = ! empty( $vis['exclude-from-catalog'] ) ? absint( $vis['exclude-from-catalog'] ) : 0;
			if ( $excl ) {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$where_parts[] = "NOT EXISTS (
					SELECT 1 FROM {$wpdb->term_relationships} vis_tr
					INNER JOIN {$wpdb->term_taxonomy} vis_tt
						ON vis_tr.term_taxonomy_id = vis_tt.term_taxonomy_id
					WHERE vis_tr.object_id = p.ID
					AND vis_tt.term_taxonomy_id = %d
				)";
				// phpcs:enable
				$all_params[] = $excl;
			}
		}

		// Category filter.
		if ( '' !== $category ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$where_parts[] = "EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} cat_tr
				INNER JOIN {$wpdb->term_taxonomy} cat_tt
					ON cat_tr.term_taxonomy_id = cat_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} cat_t ON cat_tt.term_id = cat_t.term_id
				WHERE cat_tr.object_id = p.ID
				AND cat_tt.taxonomy = 'product_cat'
				AND cat_t.slug = %s
			)";
			// phpcs:enable
			$all_params[] = (string) $category;
		}

		// Token filters: same searchable surfaces as the main query.
		// Collect sql templates + params as a flat list for the single prepare() below.
		if ( ! empty( $tokens ) ) {
			$taxo_names = $this->searchable_product_taxonomies();
			foreach ( $tokens as $token ) {
				$tok = $this->search_token_sql( $token, $taxo_names, 'p' );
				$where_parts[] = $tok['sql']; // raw template; NOT yet passed through prepare().
				foreach ( $tok['params'] as $p ) {
					$all_params[] = $p;
				}
			}
		}

		$where = implode( ' AND ', $where_parts );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT MIN(l.min_price) AS avail_min, MAX(l.max_price) AS avail_max
		        FROM {$lookup} l
		        WHERE l.product_id IN (
		            SELECT DISTINCT p.ID FROM {$wpdb->posts} p WHERE {$where}
		        )";
		$row = empty( $all_params )
			? $wpdb->get_row( $sql, ARRAY_A )
			: $wpdb->get_row( $wpdb->prepare( $sql, $all_params ), ARRAY_A );
		// phpcs:enable

		$min = ( $row && null !== $row['avail_min'] ) ? (float) $row['avail_min'] : 0.0;
		$max = ( $row && null !== $row['avail_max'] ) ? (float) $row['avail_max'] : 5000000.0;
		if ( $max <= $min || $max <= 0.0 ) {
			$max = 5000000.0;
		}
		return array( 'min' => $min, 'max' => $max );
	}
}
