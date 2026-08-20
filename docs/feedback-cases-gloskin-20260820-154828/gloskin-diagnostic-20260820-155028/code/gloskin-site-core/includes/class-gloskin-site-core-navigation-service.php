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

	/** @return void */
	public function register() {
		add_action( 'init', array( $this, 'register_menu_location' ), 20 );
	}

	/** @return void */
	public function register_menu_location() {
		register_nav_menus(
			array(
				self::LOCATION => __( 'Navigasi Utama Gloskin', 'gloskin-site-core' ),
			)
		);
	}

	/**
	 * Return one normalized tree consumed by both desktop and mobile navigation.
	 * The rendered top level is always the four client-approved destinations.
	 * Unknown/editor-owned top-level items are never promoted into the public
	 * primary header while the bounded migration is pending or interrupted.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function tree() {
		if ( has_nav_menu( self::LOCATION ) ) {
			$locations = get_nav_menu_locations();
			$menu_id   = isset( $locations[ self::LOCATION ] ) ? absint( $locations[ self::LOCATION ] ) : 0;
			$items     = $menu_id ? wp_get_nav_menu_items( $menu_id ) : array();
			if ( is_array( $items ) && $items ) {
				return $this->approved_primary_tree( $this->normalize_items( $items ) );
			}
		}

		return $this->fallback_tree();
	}

	/**
	 * @param array<int,WP_Post> $items Native menu items.
	 * @return array<int,array<string,mixed>>
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
	 * Project an editor menu onto the approved top-level IA without moving or
	 * inventing editor content. Only already-top-level canonical destinations
	 * may supply native children. Missing canonical items use the deterministic
	 * fallback; unknown top-level items are intentionally absent from primary.
	 *
	 * @param array<int,array<string,mixed>> $tree Native normalized tree.
	 * @return array<int,array<string,mixed>>
	 */
	private function approved_primary_tree( array $tree ) {
		$targets = array(
			'/treatments/' => array( 'label' => 'Perawatan', 'path' => '/treatments/' ),
			'/promo/'      => array( 'label' => 'Promo', 'path' => '/promo/' ),
			'/skincare/'   => array( 'label' => 'Skincare', 'path' => '/skincare/' ),
			'/about/'      => array( 'label' => 'Tentang Gloskin', 'path' => '/about/' ),
		);
		$native = array();
		foreach ( $tree as $node ) {
			$path = $this->site_path( isset( $node['url'] ) ? (string) $node['url'] : '' );
			if ( isset( $targets[ $path ] ) && ! isset( $native[ $path ] ) ) {
				$native[ $path ] = $node;
			}
		}

		$approved = array();
		foreach ( $targets as $path => $definition ) {
			if ( isset( $native[ $path ] ) ) {
				$node          = $native[ $path ];
				$node['parent'] = 0;
				$node['label']  = $definition['label'];
				$approved[]     = $node;
				continue;
			}
			$approved[] = $this->fallback_item( $definition['label'], $definition['path'] );
		}
		return $approved;
	}

	/**
	 * Latest client-approved primary editorial IA.
	 *
	 * Supporting destinations (Shop, Clinics, Doctors, Insights, Contact)
	 * remain live and discoverable contextually/footer/commerce utility, but
	 * no longer occupy the primary navigation. The logo owns Home.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function fallback_tree() {
		return array(
			$this->fallback_item( 'Perawatan', '/treatments/' ),
			$this->fallback_item( 'Promo', '/promo/' ),
			$this->fallback_item( 'Skincare', '/skincare/' ),
			$this->fallback_item( 'Tentang Gloskin', '/about/' ),
		);
	}

	/**
	 * @param string $label Label.
	 * @param string $path Site-relative path.
	 * @param array<int,array<string,mixed>> $children Child nodes.
	 * @return array<string,mixed>
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
	 * Keep canonical Gloskin labels normalized for same-site destinations.
	 * External/custom menu labels remain fully editor-owned in their source
	 * menu even though they are not part of the approved primary projection.
	 *
	 * @param string $label Existing menu label.
	 * @param string $url Menu URL.
	 * @return string
	 */
	private function public_label_for_url( $label, $url ) {
		$path = $this->site_path( $url );
		$labels = array(
			'/'            => 'Beranda',
			'/about/'      => 'Tentang Gloskin',
			'/treatments/' => 'Perawatan',
			'/promo/'      => 'Promo',
			'/skincare/'   => 'Skincare',
			'/clinics/'    => 'Klinik',
			'/doctors/'    => 'Dokter',
			'/insights/'   => 'Insight',
			'/shop/'       => 'Belanja',
			'/contact/'    => 'Kontak',
		);

		return isset( $labels[ $path ] ) ? $labels[ $path ] : $label;
	}

	/** @param string $url URL. @return string */
	private function site_path( $url ) {
		$host      = wp_parse_url( $url, PHP_URL_HOST );
		$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host && is_string( $site_host ) && '' !== $site_host && strtolower( $host ) !== strtolower( $site_host ) ) {
			return '';
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return is_string( $path ) ? trailingslashit( '/' . ltrim( $path, '/' ) ) : '';
	}

	/**
	 * @param string $path Site-relative path.
	 * @return bool
	 */
	private function path_is_active( $path ) {
		$current = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$current = is_string( $current ) ? strtok( $current, '?' ) : '/';
		$target  = wp_parse_url( home_url( $path ), PHP_URL_PATH );

		if ( '/' === $path ) {
			return '/' === $current;
		}

		return is_string( $target ) && 0 === strpos( (string) $current, untrailingslashit( $target ) );
	}
}
