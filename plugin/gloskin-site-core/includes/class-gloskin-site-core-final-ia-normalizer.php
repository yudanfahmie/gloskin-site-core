<?php
/**
 * Stored WordPress IA normalizer owned by the 2026-08-19 final migration.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Final_IA_Normalizer {
	const REVISION = '2026-08-19-final';
	const MENU_LOCATION = 'gloskin-primary';
	const MENU_NAME = 'Gloskin Primary';
	const PRESERVED_MENU_NAME = 'Gloskin Primary Preserved 2026-08-19-final';
	const PRESERVED_SOURCE_META = '_gloskin_final_preserved_source_menu_item';
	const PRESERVED_REVISION_META = '_gloskin_final_preserved_menu_revision';

	/** @return array<string,mixed> */
	public function normalize() {
		$page_ids = $this->ensure_pages();
		$menu = $this->normalize_primary_menu( $page_ids );
		return array_merge( array( 'page_ids' => $page_ids ), $menu );
	}

	/** @param array<string,mixed> $audit @return void */
	public function verify( array $audit ) {
		$page_ids = isset( $audit['page_ids'] ) && is_array( $audit['page_ids'] ) ? $audit['page_ids'] : array();
		foreach ( array( 'home', 'treatments', 'promo', 'skincare', 'about' ) as $key ) {
			$page = ! empty( $page_ids[ $key ] ) ? get_post( absint( $page_ids[ $key ] ) ) : null;
			if ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
				throw new RuntimeException( 'verification_failed: IA page invalid: ' . $key . '.' );
			}
		}
		$home_id = absint( $page_ids['home'] ?? 0 );
		if ( $home_id < 1 || 'page' !== (string) get_option( 'show_on_front', 'posts' ) || $home_id !== (int) get_option( 'page_on_front', 0 ) ) {
			throw new RuntimeException( 'verification_failed: Stored page_on_front is not canonical Home.' );
		}

		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$locations = is_array( $locations ) ? $locations : array();
		$stored_menu_id = absint( $locations[ self::MENU_LOCATION ] ?? 0 );
		$audit_menu_id = absint( $audit['menu_id'] ?? 0 );
		if ( ! $stored_menu_id || $stored_menu_id !== $audit_menu_id ) {
			throw new RuntimeException( 'verification_failed: Stored gloskin-primary assignment differs from migration audit.' );
		}
		$items = wp_get_nav_menu_items( $stored_menu_id );
		$items = is_array( $items ) ? $items : array();
		$actual = array();
		foreach ( $items as $item ) {
			if ( 0 !== absint( $item->menu_item_parent ) ) {
				throw new RuntimeException( 'verification_failed: gloskin-primary contains unexpected submenu.' );
			}
			$actual[] = array( (string) $item->title, $this->menu_path( (string) $item->url ) );
		}
		$expected = array(
			array( 'Perawatan', '/treatments/' ),
			array( 'Promo', '/promo/' ),
			array( 'Skincare', '/skincare/' ),
			array( 'Tentang Gloskin', '/about/' ),
		);
		if ( $expected !== $actual ) {
			throw new RuntimeException( 'verification_failed: Stored gloskin-primary must be exactly Perawatan, Promo, Skincare, Tentang Gloskin.' );
		}
		$preserved_count = absint( $audit['preserved_item_count'] ?? 0 );
		if ( $preserved_count > 0 ) {
			$preserved_id = absint( $audit['preserved_menu_id'] ?? 0 );
			$preserved = $preserved_id ? wp_get_nav_menu_items( $preserved_id ) : array();
			$preserved = is_array( $preserved ) ? $preserved : array();
			if ( count( $preserved ) < $preserved_count ) {
				throw new RuntimeException( 'verification_failed: Editor primary-menu snapshot is incomplete.' );
			}
		}
	}

	/** @return array<string,int> */
	private function ensure_pages() {
		$definitions = array(
			'home' => 'Beranda',
			'treatments' => 'Perawatan',
			'promo' => 'Promo',
			'skincare' => 'Skincare',
			'about' => 'Tentang Gloskin',
		);
		$ids = array();
		foreach ( $definitions as $slug => $title ) {
			$ids[ $slug ] = $this->ensure_page( $slug, $title );
		}
		$this->normalize_front_page( absint( $ids['home'] ) );
		return $ids;
	}

	/** @param string $slug @param string $title @return int */
	private function ensure_page( $slug, $title ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) {
			if ( 'trash' === $page->post_status ) {
				throw new RuntimeException( 'IA page /' . $slug . '/ exists in Trash; ownership is ambiguous.' );
			}
			return absint( $page->ID );
		}
		$result = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ), true );
		if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Failed to ensure /' . $slug . '/: ' . $result->get_error_message() ); }
		$id = absint( $result );
		update_post_meta( $id, '_gloskin_provisioned_revision', self::REVISION );
		return $id;
	}

	/** @param int $home_id @return void */
	private function normalize_front_page( $home_id ) {
		$front_id = (int) get_option( 'page_on_front', 0 );
		$front = $front_id > 0 ? get_post( $front_id ) : null;
		if ( $front_id === $home_id ) {
			if ( 'page' !== (string) get_option( 'show_on_front', 'posts' ) ) { update_option( 'show_on_front', 'page' ); }
			return;
		}
		if ( ! ( $front instanceof WP_Post ) || 'page' !== $front->post_type || 'trash' === $front->post_status ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_id );
			return;
		}
		throw new RuntimeException( 'Canonical Home safe-stop: current page_on_front is editor-owned "' . (string) $front->post_title . '" (#' . absint( $front->ID ) . '). Configuration was preserved.' );
	}

	/** @param array<string,int> $page_ids @return array<string,int> */
	private function normalize_primary_menu( array $page_ids ) {
		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$locations = is_array( $locations ) ? $locations : array();
		$menu_id = absint( $locations[ self::MENU_LOCATION ] ?? 0 );
		$menu = $menu_id ? wp_get_nav_menu_object( $menu_id ) : false;
		if ( ! $menu ) {
			$created = wp_create_nav_menu( self::MENU_NAME );
			if ( is_wp_error( $created ) ) { throw new RuntimeException( $created->get_error_message() ); }
			$menu_id = absint( $created );
			$locations[ self::MENU_LOCATION ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}
		$items = wp_get_nav_menu_items( $menu_id );
		$items = is_array( $items ) ? $items : array();
		$preserved_id = $this->preserve_snapshot( $items );
		$target = array(
			'treatments' => array( 'label' => 'Perawatan', 'path' => '/treatments/' ),
			'promo' => array( 'label' => 'Promo', 'path' => '/promo/' ),
			'skincare' => array( 'label' => 'Skincare', 'path' => '/skincare/' ),
			'about' => array( 'label' => 'Tentang Gloskin', 'path' => '/about/' ),
		);
		$existing = array();
		foreach ( $items as $item ) {
			$key = $this->target_key_for_path( $this->menu_path( (string) $item->url ) );
			if ( '' !== $key && ! isset( $existing[ $key ] ) ) { $existing[ $key ] = absint( $item->ID ); }
		}
		$canonical_ids = array();
		$position = 1;
		foreach ( $target as $key => $definition ) {
			$item_id = absint( $existing[ $key ] ?? 0 );
			$result = wp_update_nav_menu_item( $menu_id, $item_id, array(
				'menu-item-title' => $definition['label'],
				'menu-item-object-id' => absint( $page_ids[ $key ] ?? 0 ),
				'menu-item-object' => 'page',
				'menu-item-type' => 'post_type',
				'menu-item-status' => 'publish',
				'menu-item-parent-id' => 0,
				'menu-item-position' => $position,
			) );
			if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'Failed to normalize primary item ' . $definition['label'] . '.' ); }
			$canonical_ids[] = absint( $result );
			$position++;
		}
		foreach ( $items as $item ) {
			$item_id = absint( $item->ID );
			if ( in_array( $item_id, $canonical_ids, true ) ) { continue; }
			if ( 'nav_menu_item' === get_post_type( $item_id ) ) { wp_delete_post( $item_id, true ); }
		}
		$locations[ self::MENU_LOCATION ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		return array( 'menu_id' => $menu_id, 'preserved_menu_id' => $preserved_id, 'preserved_item_count' => count( $items ) );
	}

	/** @param array<int,WP_Post> $items @return int */
	private function preserve_snapshot( array $items ) {
		if ( ! $items ) { return 0; }
		$preserved = wp_get_nav_menu_object( self::PRESERVED_MENU_NAME );
		if ( ! $preserved ) {
			$created = wp_create_nav_menu( self::PRESERVED_MENU_NAME );
			if ( is_wp_error( $created ) ) { throw new RuntimeException( 'Failed to create editor-menu preservation snapshot.' ); }
			$preserved = wp_get_nav_menu_object( absint( $created ) );
		}
		if ( ! $preserved ) { throw new RuntimeException( 'Editor-menu preservation snapshot cannot be verified.' ); }
		$preserved_id = absint( $preserved->term_id );
		$copies = wp_get_nav_menu_items( $preserved_id );
		$copies = is_array( $copies ) ? $copies : array();
		$source_to_copy = array();
		foreach ( $copies as $copy ) {
			$source_id = absint( get_post_meta( $copy->ID, self::PRESERVED_SOURCE_META, true ) );
			$revision = (string) get_post_meta( $copy->ID, self::PRESERVED_REVISION_META, true );
			if ( $source_id && self::REVISION === $revision ) { $source_to_copy[ $source_id ] = absint( $copy->ID ); }
		}
		$position = 1;
		foreach ( $items as $item ) {
			$source_id = absint( $item->ID );
			$copy_id = absint( $source_to_copy[ $source_id ] ?? 0 );
			$result = wp_update_nav_menu_item( $preserved_id, $copy_id, $this->copy_args( $item, 0, $position ) );
			if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'Failed to preserve editor menu item: ' . (string) $item->title . '.' ); }
			$copy_id = absint( $result );
			$source_to_copy[ $source_id ] = $copy_id;
			update_post_meta( $copy_id, self::PRESERVED_SOURCE_META, $source_id );
			update_post_meta( $copy_id, self::PRESERVED_REVISION_META, self::REVISION );
			$position++;
		}
		$position = 1;
		foreach ( $items as $item ) {
			$source_id = absint( $item->ID );
			$parent_source = absint( $item->menu_item_parent );
			$copy_id = absint( $source_to_copy[ $source_id ] ?? 0 );
			$copy_parent = $parent_source && isset( $source_to_copy[ $parent_source ] ) ? absint( $source_to_copy[ $parent_source ] ) : 0;
			$result = wp_update_nav_menu_item( $preserved_id, $copy_id, $this->copy_args( $item, $copy_parent, $position ) );
			if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'Failed to preserve editor menu hierarchy.' ); }
			$position++;
		}
		return $preserved_id;
	}

	/** @return array<string,mixed> */
	private function copy_args( $item, $parent_id, $position ) {
		return array(
			'menu-item-title' => (string) $item->title,
			'menu-item-url' => (string) $item->url,
			'menu-item-description' => isset( $item->description ) ? (string) $item->description : '',
			'menu-item-attr-title' => isset( $item->attr_title ) ? (string) $item->attr_title : '',
			'menu-item-target' => isset( $item->target ) ? (string) $item->target : '',
			'menu-item-classes' => isset( $item->classes ) ? (array) $item->classes : array(),
			'menu-item-xfn' => isset( $item->xfn ) ? (string) $item->xfn : '',
			'menu-item-type' => isset( $item->type ) && '' !== (string) $item->type ? (string) $item->type : 'custom',
			'menu-item-object' => isset( $item->object ) ? (string) $item->object : 'custom',
			'menu-item-object-id' => isset( $item->object_id ) ? absint( $item->object_id ) : 0,
			'menu-item-status' => 'publish',
			'menu-item-parent-id' => absint( $parent_id ),
			'menu-item-position' => absint( $position ),
		);
	}

	/** @return string */
	private function menu_path( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host && is_string( $site_host ) && '' !== $site_host && strtolower( $host ) !== strtolower( $site_host ) ) { return ''; }
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return is_string( $path ) ? trailingslashit( '/' . ltrim( $path, '/' ) ) : '';
	}

	/** @return string */
	private function target_key_for_path( $path ) {
		$targets = array( '/treatments/' => 'treatments', '/promo/' => 'promo', '/skincare/' => 'skincare', '/about/' => 'about' );
		return isset( $targets[ $path ] ) ? $targets[ $path ] : '';
	}
}
