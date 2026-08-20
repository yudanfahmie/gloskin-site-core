<?php
/**
 * Projection helpers for saved English presentation.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Language_Projection {
	/** Register only on the public frontend. */
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

	/** Begin a lightweight visible-text interface resolver. */
	public function start_interface_buffer() {
		if ( 'en' !== Gloskin_Site_Core_Language::language() || is_admin() || is_feed() || wp_doing_ajax() ) { return; }
		ob_start( array( $this, 'translate_interface_html' ) );
	}

	/**
	 * Replace only text segments outside tags/comments/script/style/pre/code.
	 * This lets existing hard-coded Gloskin interface labels share the one
	 * interface registry without translating attributes, URLs or markup.
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

	/** @param string $text Visible text. @return string */
	private function translate_text_segment( $text ) {
		$saved = Gloskin_Site_Core_Translation::interface_translations();
		$map = array();
		foreach ( Gloskin_Site_Core_Translation::interface_registry() as $key => $entry ) {
			$source = (string) $entry['source'];
			if ( '' === $source ) { continue; }
			$map[ $source ] = isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] )
				? (string) $saved[ $key ]
				: (string) $entry['en'];
		}
		uksort( $map, static function ( $a, $b ) { return strlen( $b ) <=> strlen( $a ); } );
		return $map ? str_replace( array_keys( $map ), array_values( $map ), $text ) : $text;
	}
}
