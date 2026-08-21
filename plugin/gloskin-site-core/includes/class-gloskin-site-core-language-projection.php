<?php
/**
 * Projection helpers for saved English presentation.
 *
 * Translation registry/storage/admin/save ownership belongs exclusively to
 * Gloskin_Site_Core_Translation. This helper remains frontend-only.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Language_Projection {
	/** Register public projection helpers. */
	public function register() {
		add_action( 'pre_get_posts', array( $this, 'enable_registered_post_filters' ), 999 );
		add_action( 'template_redirect', array( $this, 'start_interface_buffer' ), 0 );
	}

	/**
	 * get_posts() suppresses filters by default. For EN requests only, opt the
	 * explicitly registered translation post types back into the normal result
	 * filters so existing Template Service projections receive translated clones.
	 * Query shape, IDs, relationships and ordering are unchanged.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public function enable_registered_post_filters( $query ) {
		if ( 'en' !== Gloskin_Site_Core_Language::language() || ! ( $query instanceof WP_Query ) ) { return; }
		$registry = Gloskin_Site_Core_Translation::registry();
		$registered = array_keys( $registry['post_types'] );
		$post_type = $query->get( 'post_type' );
		$types = is_array( $post_type ) ? array_map( 'strval', $post_type ) : array( (string) $post_type );
		if ( array_intersect( $registered, $types ) ) {
			$query->set( 'suppress_filters', false );
		}
	}

	/**
	 * Begin the visible-text interface resolver output buffer.
	 *
	 * A request-local static flag prevents the buffer from being started twice
	 * in one request (idempotency guard), which would nest ob_start callbacks
	 * and could double-translate text that passes through the outer buffer.
	 */
	public function start_interface_buffer() {
		static $started = false;
		if ( $started || 'en' !== Gloskin_Site_Core_Language::language() || is_admin() || is_feed() || wp_doing_ajax() ) { return; }
		$started = true;
		ob_start( array( $this, 'translate_interface_html' ) );
	}

	/**
	 * Replace only complete visible text nodes outside tags/comments/script/
	 * style/pre/code. Substring replacement is deliberately forbidden.
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	public function translate_interface_html( $html ) {
		if ( '' === $html || false === strpos( $html, '<' ) ) { return $this->translate_text_segment( $html ); }
		$parts = preg_split(
			'/(<!--[\s\S]*?-->|<script\b[^>]*>[\s\S]*?<\/script\s*>|<style\b[^>]*>[\s\S]*?<\/style\s*>|<pre\b[^>]*>[\s\S]*?<\/pre\s*>|<code\b[^>]*>[\s\S]*?<\/code\s*>|<[^>]+>)/i',
			$html,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		if ( ! is_array( $parts ) ) { return $html; }
		foreach ( $parts as $index => $part ) {
			if ( '' === $part || '<' === $part[0] ) { continue; }
			$parts[ $index ] = $this->translate_text_segment( $part );
		}
		return implode( '', $parts );
	}

	/**
	 * Translate one complete visible text node using the shared O(1) lookup.
	 *
	 * Exact-node matching is preserved — only a complete trimmed text node that
	 * exactly equals a canonical source string is translated.  Substring
	 * replacement is deliberately forbidden.  Delegates to
	 * Translation::interface_lookup() so this transport and the gettext filter
	 * share one resolver and one build per request.
	 *
	 * @param string $text Visible text node.
	 * @return string
	 */
	private function translate_text_segment( $text ) {
		$needle = trim( (string) $text );
		if ( '' === $needle ) { return $text; }
		// O(1) lookup via the shared canonical resolver — no per-node foreach scan.
		$lookup = Gloskin_Site_Core_Translation::interface_lookup();
		if ( ! isset( $lookup[ $needle ] ) ) { return $text; }
		$replacement = $lookup[ $needle ];
		$leading  = substr( $text, 0, strlen( $text ) - strlen( ltrim( $text ) ) );
		$trailing = substr( $text, strlen( rtrim( $text ) ) );
		return $leading . $replacement . $trailing;
	}
}
