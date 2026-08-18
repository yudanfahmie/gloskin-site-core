<?php
/**
 * Bounded one-shot IA migration for the 2026-08-18 client-approved prototype revision.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- domain errors are escaped by the admin/AJAX boundary.
final class Gloskin_Site_Core_Prototype_IA_Migration {
	const REVISION      = '2026-08-18';
	const STATE_OPTION  = 'gloskin_site_core_prototype_ia_20260818_state';
	const LOCK_OPTION   = 'gloskin_site_core_prototype_ia_20260818_lock';
	const MENU_LOCATION = 'gloskin-primary';
	const MENU_NAME     = 'Gloskin Primary';
	const LOCK_TTL      = 300;

	/** @return array<int,array{key:string,label:string}> */
	private function steps() {
		return array(
			array( 'key' => 'pages', 'label' => 'Menyiapkan halaman IA terbaru' ),
			array( 'key' => 'menu', 'label' => 'Menormalkan navigasi utama' ),
			array( 'key' => 'verify', 'label' => 'Memverifikasi page/menu/commerce safety' ),
			array( 'key' => 'finalize', 'label' => 'Menyimpan consumed/schema state' ),
		);
	}

	/** @return array<string,mixed> */
	public function get_state() {
		$state = get_option( self::STATE_OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		$defaults = array(
			'revision'           => self::REVISION,
			'status'             => 'pending',
			'next_step_index'    => 0,
			'processed_products' => 0,
			'expected_products'  => count( $this->steps() ),
			'current_step'       => 'Siap dijalankan',
			'last_error'         => '',
			'page_ids'           => array(),
			'menu_id'            => 0,
			'commerce_snapshot'  => array(),
			'updated_at'         => 0,
		);
		$state = array_merge( $defaults, $state );
		if ( self::REVISION !== (string) $state['revision'] ) {
			return $defaults;
		}
		return $state;
	}

	/** @return bool */
	public function is_consumed() {
		return 'consumed' === (string) $this->get_state()['status'];
	}

	/**
	 * Bounded synchronous fallback for environments where JavaScript is
	 * unavailable. Normal admin UX uses advance() checkpoint chaining so the
	 * browser can paint real progress between server mutations.
	 *
	 * @return array<string,mixed>
	 */
	public function run_to_completion() {
		$state = $this->advance( 'start' );
		$limit = count( $this->steps() ) + 2;
		for ( $i = 0; $i < $limit && 'consumed' !== $state['status']; $i++ ) {
			$state = $this->advance( 'continue' );
		}
		if ( 'consumed' !== $state['status'] ) {
			throw new RuntimeException( 'Migrasi IA tidak mencapai state consumed dalam batas checkpoint.' );
		}
		return $state;
	}

	/**
	 * Advance exactly one deterministic checkpoint. The admin controller
	 * chains these requests automatically after one user action.
	 *
	 * @param string $mode start|continue.
	 * @return array<string,mixed>
	 */
	public function advance( $mode ) {
		$mode = sanitize_key( (string) $mode );
		if ( ! in_array( $mode, array( 'start', 'continue' ), true ) ) {
			throw new RuntimeException( 'Mode migrasi IA tidak valid.' );
		}

		$state = $this->get_state();
		if ( 'consumed' === $state['status'] ) {
			return $this->response_state( $state );
		}

		$token = $this->acquire_lock();
		if ( '' === $token ) {
			throw new RuntimeException( 'Migrasi IA sedang diproses oleh request lain.' );
		}

		try {
			if ( 'start' === $mode ) {
				$state['status']            = 'running';
				$state['last_error']        = '';
				$state['current_step']      = $this->step_label( (int) $state['next_step_index'] );
				$state['commerce_snapshot'] = $this->commerce_page_snapshot();
				$state['updated_at']        = time();
				$this->save_state( $state );
				$this->release_lock( $token );
				return $this->response_state( $state );
			}

			if ( ! in_array( $state['status'], array( 'running', 'failed', 'verifying' ), true ) ) {
				throw new RuntimeException( 'Migrasi IA belum dimulai.' );
			}

			$index = (int) $state['next_step_index'];
			$steps = $this->steps();
			if ( $index >= count( $steps ) ) {
				$state['status'] = 'consumed';
				$state['current_step'] = 'Selesai';
				$this->save_state( $state );
				$this->release_lock( $token );
				return $this->response_state( $state );
			}

			$state['status']       = 'running';
			$state['current_step'] = $steps[ $index ]['label'];
			$this->save_state( $state );

			switch ( $steps[ $index ]['key'] ) {
				case 'pages':
					$state['page_ids'] = $this->ensure_approved_pages();
					break;
				case 'menu':
					$state['menu_id'] = $this->normalize_primary_menu( (array) $state['page_ids'] );
					break;
				case 'verify':
					$state['status'] = 'verifying';
					$this->verify_result( $state );
					break;
				case 'finalize':
					update_option(
						Gloskin_Site_Core_Lifecycle_Service::VERSION_OPTION,
						Gloskin_Site_Core_Lifecycle_Service::SCHEMA_VERSION,
						false
					);
					$state['status'] = 'consumed';
					break;
			}

			$state['next_step_index']    = $index + 1;
			$state['processed_products'] = min( count( $steps ), $index + 1 );
			$state['current_step']       = 'consumed' === $state['status']
				? 'Selesai'
				: $this->step_label( (int) $state['next_step_index'] );
			$state['last_error']         = '';
			$state['updated_at']         = time();
			$this->save_state( $state );
			$this->release_lock( $token );
			return $this->response_state( $state );
		} catch ( Throwable $error ) {
			$this->release_lock( $token );
			$failed                 = $this->get_state();
			$failed['status']       = 'failed';
			$failed['last_error']   = $error->getMessage();
			$failed['current_step'] = $this->step_label( (int) $failed['next_step_index'] );
			$failed['updated_at']   = time();
			$this->save_state( $failed );
			throw new RuntimeException( $error->getMessage(), 0, $error );
		}
	}

	/** @return array<string,int> */
	private function ensure_approved_pages() {
		$definitions = array(
			'home'       => 'Beranda',
			'treatments' => 'Perawatan',
			'promo'      => 'Promo',
			'skincare'   => 'Skincare',
			'about'      => 'Tentang Gloskin',
		);
		$ids = array();
		foreach ( $definitions as $slug => $title ) {
			$ids[ $slug ] = $this->ensure_page( $slug, $title );
			if ( ! $ids[ $slug ] ) {
				throw new RuntimeException( 'Gagal memastikan halaman ' . $title . '.' );
			}
		}

		$front_id = (int) get_option( 'page_on_front', 0 );
		$front    = $front_id > 0 ? get_post( $front_id ) : null;
		if ( ! ( $front instanceof WP_Post ) || 'trash' === $front->post_status ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $ids['home'] );
		}
		return $ids;
	}

	/**
	 * Normalize only known same-site Gloskin top-level IA. Unknown editor-created
	 * or external items survive and are positioned after the four destinations.
	 *
	 * @param array<string,int> $page_ids Approved page IDs.
	 * @return int
	 */
	private function normalize_primary_menu( array $page_ids ) {
		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$locations = is_array( $locations ) ? $locations : array();
		$menu_id   = isset( $locations[ self::MENU_LOCATION ] ) ? absint( $locations[ self::MENU_LOCATION ] ) : 0;
		$menu      = $menu_id ? wp_get_nav_menu_object( $menu_id ) : false;
		if ( ! $menu ) {
			$created = wp_create_nav_menu( self::MENU_NAME );
			if ( is_wp_error( $created ) ) {
				throw new RuntimeException( $created->get_error_message() );
			}
			$menu_id = absint( $created );
			$locations[ self::MENU_LOCATION ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}

		$items = wp_get_nav_menu_items( $menu_id );
		$items = is_array( $items ) ? $items : array();
		$target = array(
			'treatments' => array( 'label' => 'Perawatan', 'path' => '/treatments/' ),
			'promo'      => array( 'label' => 'Promo', 'path' => '/promo/' ),
			'skincare'   => array( 'label' => 'Skincare', 'path' => '/skincare/' ),
			'about'      => array( 'label' => 'Tentang Gloskin', 'path' => '/about/' ),
		);
		$target_existing = array();
		$delete_ids      = array();

		foreach ( $items as $item ) {
			$path = $this->menu_path( (string) $item->url );
			$key  = $this->target_key_for_path( $path );
			if ( '' !== $key ) {
				if ( ! isset( $target_existing[ $key ] ) ) {
					$target_existing[ $key ] = absint( $item->ID );
				} else {
					$delete_ids[] = absint( $item->ID );
				}
				continue;
			}
			if ( $this->is_obsolete_primary_path( $path ) ) {
				$delete_ids[] = absint( $item->ID );
			}
		}

		$position = 1;
		$canonical_ids = array();
		foreach ( $target as $key => $definition ) {
			$item_id = isset( $target_existing[ $key ] ) ? absint( $target_existing[ $key ] ) : 0;
			$result = wp_update_nav_menu_item(
				$menu_id,
				$item_id,
				array(
					'menu-item-title'     => $definition['label'],
					'menu-item-object-id' => absint( $page_ids[ $key ] ?? 0 ),
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-parent-id' => 0,
					'menu-item-position'  => $position,
				)
			);
			if ( is_wp_error( $result ) || ! $result ) {
				throw new RuntimeException( 'Gagal menormalkan item menu ' . $definition['label'] . '.' );
			}
			$canonical_ids[] = absint( $result );
			$position++;
		}

		$delete_ids = array_values( array_unique( array_filter( $delete_ids ) ) );
		foreach ( $delete_ids as $delete_id ) {
			if ( 'nav_menu_item' === get_post_type( $delete_id ) ) {
				wp_delete_post( $delete_id, true );
			}
		}

		$remaining = wp_get_nav_menu_items( $menu_id );
		$remaining = is_array( $remaining ) ? $remaining : array();
		foreach ( $remaining as $item ) {
			$item_id = absint( $item->ID );
			if ( in_array( $item_id, $canonical_ids, true ) ) {
				continue;
			}
			$parent = absint( $item->menu_item_parent );
			if ( $parent && in_array( $parent, $delete_ids, true ) ) {
				$parent = 0;
			}
			$preserved = wp_update_nav_menu_item(
				$menu_id,
				$item_id,
				array(
					'menu-item-title'     => (string) $item->title,
					'menu-item-url'       => (string) $item->url,
					'menu-item-type'      => 'custom' === (string) $item->type ? 'custom' : (string) $item->type,
					'menu-item-object'    => (string) $item->object,
					'menu-item-object-id' => absint( $item->object_id ),
					'menu-item-status'    => 'publish',
					'menu-item-parent-id' => $parent,
					'menu-item-position'  => $position,
				)
			);
			if ( is_wp_error( $preserved ) || ! $preserved ) {
				throw new RuntimeException( 'Gagal mempertahankan item menu editor: ' . (string) $item->title . '.' );
			}
			$position++;
		}

		$locations[ self::MENU_LOCATION ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		return $menu_id;
	}

	/** @param array<string,mixed> $state State. @return void */
	private function verify_result( array $state ) {
		$page_ids = (array) $state['page_ids'];
		foreach ( array( 'home', 'treatments', 'promo', 'skincare', 'about' ) as $key ) {
			$page = ! empty( $page_ids[ $key ] ) ? get_post( absint( $page_ids[ $key ] ) ) : null;
			if ( ! ( $page instanceof WP_Post ) || 'trash' === $page->post_status ) {
				throw new RuntimeException( 'Verifikasi halaman IA gagal: ' . $key . '.' );
			}
		}

		$menu_id = absint( $state['menu_id'] );
		$items   = $menu_id ? wp_get_nav_menu_items( $menu_id ) : array();
		$items   = is_array( $items ) ? $items : array();
		$top     = array();
		foreach ( $items as $item ) {
			if ( 0 !== absint( $item->menu_item_parent ) ) {
				continue;
			}
			$path = $this->menu_path( (string) $item->url );
			if ( '' !== $this->target_key_for_path( $path ) ) {
				$top[] = $path;
			}
			if ( $this->is_obsolete_primary_path( $path ) ) {
				throw new RuntimeException( 'Menu lama masih berada di primary navigation: ' . $path );
			}
		}
		if ( array( '/treatments/', '/promo/', '/skincare/', '/about/' ) !== array_slice( $top, 0, 4 ) ) {
			throw new RuntimeException( 'Urutan primary navigation belum sesuai revisi client.' );
		}

		if ( $this->commerce_page_snapshot() !== (array) $state['commerce_snapshot'] ) {
			throw new RuntimeException( 'Konfigurasi halaman WooCommerce berubah selama migrasi; migrasi dihentikan untuk review.' );
		}
	}

	/** @return array<string,int> */
	private function commerce_page_snapshot() {
		$keys = array(
			'woocommerce_shop_page_id',
			'woocommerce_cart_page_id',
			'woocommerce_checkout_page_id',
			'woocommerce_myaccount_page_id',
		);
		$snapshot = array();
		foreach ( $keys as $key ) {
			$snapshot[ $key ] = (int) get_option( $key, 0 );
		}
		return $snapshot;
	}

	/** @param string $slug Slug. @param string $title Title. @return int */
	private function ensure_page( $slug, $title ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) {
			if ( 'trash' === $page->post_status ) {
				throw new RuntimeException( 'Halaman /' . $slug . '/ sudah ada di Trash; kepemilikan ambigu sehingga migrasi tidak membuat duplikat.' );
			}
			return (int) $page->ID;
		}
		$result = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
		$id = absint( $result );
		if ( $id ) {
			update_post_meta( $id, '_gloskin_provisioned_revision', self::REVISION );
		}
		return $id;
	}

	/**
	 * Return a same-site normalized path or an empty string for external URLs.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function menu_path( $url ) {
		$host      = wp_parse_url( $url, PHP_URL_HOST );
		$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host && is_string( $site_host ) && '' !== $site_host && strtolower( $host ) !== strtolower( $site_host ) ) {
			return '';
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return is_string( $path ) ? trailingslashit( '/' . ltrim( $path, '/' ) ) : '';
	}

	/** @param string $path Path. @return string */
	private function target_key_for_path( $path ) {
		$targets = array(
			'/treatments/' => 'treatments',
			'/promo/'      => 'promo',
			'/skincare/'   => 'skincare',
			'/about/'      => 'about',
		);
		return isset( $targets[ $path ] ) ? $targets[ $path ] : '';
	}

	/** @param string $path Path. @return bool */
	private function is_obsolete_primary_path( $path ) {
		return in_array(
			$path,
			array( '/', '/shop/', '/clinics/', '/doctors/', '/insights/', '/contact/' ),
			true
		);
	}

	/** @param int $index Step index. @return string */
	private function step_label( $index ) {
		$steps = $this->steps();
		return isset( $steps[ $index ] ) ? $steps[ $index ]['label'] : 'Selesai';
	}

	/** @param array<string,mixed> $state State. @return void */
	private function save_state( array $state ) {
		update_option( self::STATE_OPTION, $state, false );
	}

	/** @param array<string,mixed> $state State. @return array<string,mixed> */
	private function response_state( array $state ) {
		return array(
			'status'             => (string) $state['status'],
			'processed_products' => (int) $state['processed_products'],
			'expected_products'  => (int) $state['expected_products'],
			'current_step'       => (string) $state['current_step'],
			'last_error'         => (string) $state['last_error'],
			'menu_id'            => absint( $state['menu_id'] ),
		);
	}

	/** @return string */
	private function acquire_lock() {
		$now   = time();
		$token = wp_generate_uuid4();
		$lock  = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && ! empty( $lock['expires'] ) && (int) $lock['expires'] <= $now ) {
			delete_option( self::LOCK_OPTION );
		}
		if ( add_option( self::LOCK_OPTION, array( 'token' => $token, 'expires' => $now + self::LOCK_TTL ), '', false ) ) {
			return $token;
		}
		return '';
	}

	/** @param string $token Lock token. @return void */
	private function release_lock( $token ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}
}
// phpcs:enable
