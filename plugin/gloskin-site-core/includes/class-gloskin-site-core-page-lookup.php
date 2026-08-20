<?php
/**
 * Strict canonical Page lookup shared by public rendering and recovery tools.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Page_Lookup {
	/**
	 * Resolve one top-level Page by exact slug. Attachments and other post types
	 * can share a path-like slug in legacy data, but may never satisfy this API.
	 *
	 * @param string $slug Exact Page slug.
	 * @return WP_Post|null
	 */
	public static function find( $slug ) {
		$slug = trim( (string) $slug, '/' );
		if ( '' === $slug || false !== strpos( $slug, '/' ) ) {
			return null;
		}

		$page = get_page_by_path( $slug, OBJECT, array( 'page' ) );
		if ( ! ( $page instanceof WP_Post ) || 'page' !== (string) $page->post_type || $slug !== (string) $page->post_name ) {
			return null;
		}
		return $page;
	}
}
