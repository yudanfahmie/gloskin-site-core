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
				self::LOCATION => __( 'Navigasi Utama Gloskin', 'gloskin-site-core' ),
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
				'label'    => $this->public_label_for_url( (string) $item->title, (string) $item->url ),
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
		/* Kontak is intentionally absent here: the global header's persistent
		 * "Hubungi Kami" CTA and the footer's "Kontak" link already own that
		 * path, so the primary nav no longer needs a redundant third entry
		 * point. Belanja sits right after Skincare -- /shop/ already exists
		 * but was missing from the fallback tree, a real discovery gap. This
		 * only edits the no-menu-assigned fallback; an editor-owned WordPress
		 * menu at gloskin-primary (see tree()) is never touched. */
		return array(
			$this->fallback_item( 'Tentang Gloskin', '/about/' ),
			$this->fallback_item( 'Perawatan', '/treatments/' ),
			$this->fallback_item( 'Skincare', '/skincare/', $this->skincare_children() ),
			$this->fallback_item( 'Belanja', '/shop/' ),
			$this->fallback_item( 'Klinik', '/clinics/', $this->clinic_children() ),
			$this->fallback_item( 'Dokter', '/doctors/' ),
			$this->fallback_item( 'Insight', '/insights/' ),
		);
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
	 * Keep canonical IA labels Indonesian even when an older native menu still
	 * carries the initial English labels. Custom/non-Gloskin menu items remain
	 * editor-owned and are not rewritten.
	 *
	 * @param string $label Existing menu label.
	 * @param string $url Menu URL.
	 * @return string
	 */
	private function public_label_for_url( $label, $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = is_string( $path ) ? trailingslashit( '/' . ltrim( $path, '/' ) ) : '';
		$labels = array(
			'/'             => 'Beranda',
			'/about/'       => 'Tentang Gloskin',
			'/treatments/'  => 'Perawatan',
			'/skincare/'    => 'Skincare',
			'/clinics/'     => 'Klinik',
			'/doctors/'     => 'Dokter',
			'/insights/'    => 'Insight',
			'/shop/'        => 'Belanja',
			'/contact/'     => 'Kontak',
		);

		return isset( $labels[ $path ] ) ? $labels[ $path ] : $label;
	}

	/**
	 * @param string $path Site-relative path.
	 * @return bool
	 */
	private function path_is_active( $path ) {
		// Unslash then sanitize before any comparison; this value is never echoed, only compared.
		$current = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$current = is_string( $current ) ? strtok( $current, '?' ) : '/';
		$target  = wp_parse_url( home_url( $path ), PHP_URL_PATH );

		if ( '/' === $path ) {
			return '/' === $current;
		}

		return is_string( $target ) && 0 === strpos( (string) $current, untrailingslashit( $target ) );
	}
}
