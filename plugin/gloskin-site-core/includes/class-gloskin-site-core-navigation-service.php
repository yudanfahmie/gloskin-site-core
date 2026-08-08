<?php
/**
 * Gloskin primary navigation owner.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Navigation_Service {
	const LOCATION = 'gloskin-primary';

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_menu_location' ), 20 );
	}

	/**
	 * @return void
	 */
	public function register_menu_location() {
		register_nav_menus(
			array(
				self::LOCATION => __( 'Gloskin Primary Navigation', 'gloskin-site-core' ),
			)
		);
	}

	/**
	 * Return one normalized tree consumed by both desktop and mobile navigation.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function tree() {
		if ( has_nav_menu( self::LOCATION ) ) {
			$locations = get_nav_menu_locations();
			$menu_id   = isset( $locations[ self::LOCATION ] ) ? absint( $locations[ self::LOCATION ] ) : 0;
			$items     = $menu_id ? wp_get_nav_menu_items( $menu_id ) : array();
			if ( is_array( $items ) && $items ) {
				return $this->normalize_items( $items );
			}
		}

		return $this->fallback_tree();
	}

	/**
	 * @param array<int, WP_Post> $items Native menu items.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_items( $items ) {
		$nodes = array();
		foreach ( $items as $item ) {
			if ( empty( $item->ID ) ) {
				continue;
			}
			$nodes[ (int) $item->ID ] = array(
				'id'       => (int) $item->ID,
				'parent'   => absint( $item->menu_item_parent ),
				'label'    => (string) $item->title,
				'url'      => (string) $item->url,
				'active'   => in_array( 'current-menu-item', (array) $item->classes, true )
					|| in_array( 'current-menu-ancestor', (array) $item->classes, true ),
				'children' => array(),
			);
		}

		$tree = array();
		foreach ( array_keys( $nodes ) as $id ) {
			$parent = $nodes[ $id ]['parent'];
			if ( $parent && isset( $nodes[ $parent ] ) ) {
				$nodes[ $parent ]['children'][] =& $nodes[ $id ];
			} else {
				$tree[] =& $nodes[ $id ];
			}
		}

		return $tree;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function fallback_tree() {
		$items = array(
			$this->fallback_item( 'About', '/about/' ),
			$this->fallback_item( 'Treatments', '/treatments/' ),
			$this->fallback_item( 'Skincare', '/skincare/', $this->skincare_children() ),
			$this->fallback_item( 'Clinics', '/clinics/', $this->clinic_children() ),
			$this->fallback_item( 'Doctors', '/doctors/' ),
			$this->fallback_item( 'Shop', '/shop/' ),
			$this->fallback_item( 'Insights', '/insights/' ),
			$this->fallback_item( 'Contact', '/contact/' ),
		);

		return $items;
	}

	/**
	 * @param string                   $label Label.
	 * @param string                   $path Site-relative path.
	 * @param array<int,array<string,mixed>> $children Child nodes.
	 * @return array<string, mixed>
	 */
	private function fallback_item( $label, $path, $children = array() ) {
		return array(
			'id'       => 'fallback-' . sanitize_title( $label ),
			'parent'   => 0,
			'label'    => $label,
			'url'      => home_url( $path ),
			'active'   => $this->path_is_active( $path ),
			'children' => $children,
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function skincare_children() {
		$children = array();
		foreach ( Gloskin_Site_Core_Content_Service::skincare_definitions() as $slug => $label ) {
			$children[] = $this->fallback_item( $label, '/skincare/' . $slug . '/' );
		}
		return $children;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function clinic_children() {
		$children = array();
		foreach ( Gloskin_Site_Core_Content_Service::clinic_definitions() as $slug => $label ) {
			$children[] = $this->fallback_item( $label, '/clinics/' . $slug . '/' );
		}
		return $children;
	}

	/**
	 * @param string $path Site-relative path.
	 * @return bool
	 */
	private function path_is_active( $path ) {
		$current = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$current = is_string( $current ) ? strtok( $current, '?' ) : '/';
		$target  = wp_parse_url( home_url( $path ), PHP_URL_PATH );

		if ( '/' === $path ) {
			return '/' === $current;
		}

		return is_string( $target ) && 0 === strpos( (string) $current, untrailingslashit( $target ) );
	}
}
