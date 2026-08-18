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
	const REVISION            = '2026-08-18';
	const STATE_OPTION        = 'gloskin_site_core_prototype_ia_20260818_state';
	const LOCK_OPTION         = 'gloskin_site_core_prototype_ia_20260818_lock';
	const MENU_LOCATION       = 'gloskin-primary';
	const MENU_NAME           = 'Gloskin Primary';
	const PRESERVED_MENU_NAME = 'Gloskin Primary Preserved 2026-08-18';
	const PRESERVED_SOURCE_META = '_gloskin_preserved_source_menu_item';
	const PRESERVED_REVISION_META = '_gloskin_preserved_menu_revision';
	const LOCK_TTL            = 300;

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
			'revision'             => self::REVISION,
			'status'               => 'pending',
			'next_step_index'      => 0,
			'processed_products'   => 0,
			'expected_products'    => count( $this->steps() ),
			'current_step'         => 'Siap dijalankan',
			'last_error'           => '',
			'page_ids'             => array(),
			'menu_id'              => 0,
			'preserved_menu_id'    => 0,
			'preserved_item_count' => 0,
			'commerce_snapshot'    => array(),
			'updated_at'           => 0,
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
					$menu_result = $this->normalize_primary_menu( (array) $state['page_ids'] );
					$state['menu_id']              = absint( $menu_result['menu_id'] );
					$state['preserved_menu_id']    = absint( $menu_result['preserved_menu_id'] );
					$state['preserved_item_count'] = absint( $menu_result['preserved_item_count'] );
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

		$this->normalize_front_page( absint( $ids['home'] ) );
		return $ids;
	}

	/**
	 * Normalize only when ownership is provable. An unset, invalid or trashed
	 * front-page configuration is safely pointed at canonical Home. A valid
	 * non-Home Page is editor-owned/ambiguous from the migration's perspective,
	 * so it is preserved and the run stops with an actionable warning instead
	 * of guessing or silently replacing it.
	 *
	 * @param int $home_id Canonical Home Page ID.
	 * @return void
	 */
	private function normalize_front_page( $home_id ) {
		$front_id = (int) get_option( 'page_on_front', 0 );
		$front    = $front_id > 0 ? get_post( $front_id ) : null;

		if ( $front_id === $home_id ) {
			if ( 'page' !== (string) get_option( 'show_on_front', 'posts' ) ) {
				update_option( 'show_on_front', 'page' );
			}
			return;
		}

		if ( ! ( $front instanceof WP_Post ) || 'page' !== $front->post_type || 'trash' === $front->post_status ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_id );
			return;
		}

		throw new RuntimeException(
			'Halaman depan saat ini adalah "' . (string) $front->post_title . '" (#' . absint( $front->ID ) . '). Kepemilikannya tidak dapat dibuktikan sebagai canonical Home Gloskin, sehingga konfigurasi dipertahankan. Review page_on_front lalu lanjutkan migrasi.'
		);
	}

	/**
	 * Normalize the assigned primary menu to exactly the four approved top-level
	 * destinations. Before any original nav_menu_item is removed from primary,
	 * the complete old menu snapshot (including editor items and hierarchy) is
	 * copied idempotently into one deterministic unassigned preservation menu.
	 * Pages are never deleted by this operation.
	 *
	 * @param array<string,int> $page_ids Approved page IDs.
	 * @return array{menu_id:int,preserved_menu_id:int,preserved_item_count:int}
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
		$preserved_menu_id = $this->preserve_primary_menu_snapshot( $items );

		$target = array(
			'treatments' => array( 'label' => 'Perawatan', 'path' => '/treatments/' ),
			'promo'      => array( 'label' => 'Promo', 'path' => '/promo/' ),
			'skincare'   => array( 'label' => 'Skincare', 'path' => '/skincare/' ),
			'about'      => array( 'label' => 'Tentang Gloskin', 'path' => '/about/' ),
		);
		$target_existing = array();
		foreach ( $items as $item ) {
			$key = $this->target_key_for_path( $this->menu_path( (string) $item->url ) );
			if ( '' !== $key && ! isset( $target_existing[ $key ] ) ) {
				$target_existing[ $key ] = absint( $item->ID );
			}
		}

		$position      = 1;
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

		foreach ( $items as $item ) {
			$item_id = absint( $item->ID );
			if ( in_array( $item_id, $canonical_ids, true ) ) {
				continue;
			}
			if ( 'nav_menu_item' === get_post_type( $item_id ) ) {
				wp_delete_post( $item_id, true );
			}
		}

		$locations[ self::MENU_LOCATION ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		return array(
			'menu_id'              => $menu_id,
			'preserved_menu_id'    => $preserved_menu_id,
			'preserved_item_count' => count( $items ),
		);
	}

	/**
	 * @param array<int,WP_Post> $items Original primary-menu items.
	 * @return int Preservation menu ID, or 0 when there was nothing to preserve.
	 */
	private function preserve_primary_menu_snapshot( array $items ) {
		if ( ! $items ) {
			return 0;
		}

		$preserved = wp_get_nav_menu_object( self::PRESERVED_MENU_NAME );
		if ( ! $preserved ) {
			$created = wp_create_nav_menu( self::PRESERVED_MENU_NAME );
			if ( is_wp_error( $created ) ) {
				throw new RuntimeException( 'Gagal membuat menu preservasi: ' . $created->get_error_message() );
			}
			$preserved = wp_get_nav_menu_object( absint( $created ) );
		}
		if ( ! $preserved ) {
			throw new RuntimeException( 'Menu preservasi tidak dapat diverifikasi.' );
		}
		$preserved_menu_id = absint( $preserved->term_id );

		$existing = wp_get_nav_menu_items( $preserved_menu_id );
		$existing = is_array( $existing ) ? $existing : array();
		$source_to_copy = array();
		foreach ( $existing as $copy ) {
			$source_id = absint( get_post_meta( $copy->ID, self::PRESERVED_SOURCE_META, true ) );
			$revision  = (string) get_post_meta( $copy->ID, self::PRESERVED_REVISION_META, true );
			if ( $source_id && self::REVISION === $revision ) {
				$source_to_copy[ $source_id ] = absint( $copy->ID );
			}
		}

		$position = 1;
		foreach ( $items as $item ) {
			$source_id = absint( $item->ID );
			$copy_id   = isset( $source_to_copy[ $source_id ] ) ? absint( $source_to_copy[ $source_id ] ) : 0;
			$result    = wp_update_nav_menu_item(
				$preserved_menu_id,
				$copy_id,
				$this->menu_item_copy_args( $item, 0, $position )
			);
			if ( is_wp_error( $result ) || ! $result ) {
				throw new RuntimeException( 'Gagal mempreservasi item menu editor: ' . (string) $item->title . '.' );
			}
			$copy_id = absint( $result );
			$source_to_copy[ $source_id ] = $copy_id;
			update_post_meta( $copy_id, self::PRESERVED_SOURCE_META, $source_id );
			update_post_meta( $copy_id, self::PRESERVED_REVISION_META, self::REVISION );
			$position++;
		}

		$position = 1;
		foreach ( $items as $item ) {
			$source_id   = absint( $item->ID );
			$source_parent = absint( $item->menu_item_parent );
			$copy_id     = absint( $source_to_copy[ $source_id ] ?? 0 );
			$copy_parent = $source_parent && isset( $source_to_copy[ $source_parent ] ) ? absint( $source_to_copy[ $source_parent ] ) : 0;
			$result = wp_update_nav_menu_item(
				$preserved_menu_id,
				$copy_id,
				$this->menu_item_copy_args( $item, $copy_parent, $position )
			);
			if ( is_wp_error( $result ) || ! $result ) {
				throw new RuntimeException( 'Gagal mempertahankan hierarki menu editor: ' . (string) $item->title . '.' );
			}
			$position++;
		}

		return $preserved_menu_id;
	}

	/**
	 * @param WP_Post $item Source menu item.
	 * @param int     $parent_id Copied parent ID.
	 * @param int     $position Menu position.
	 * @return array<string,mixed>
	 */
	private function menu_item_copy_args( $item, $parent_id, $position ) {
		return array(
			'menu-item-title'       => (string) $item->title,
			'menu-item-url'         => (string) $item->url,
			'menu-item-description' => isset( $item->description ) ? (string) $item->description : '',
			'menu-item-attr-title'  => isset( $item->attr_title ) ? (string) $item->attr_title : '',
			'menu-item-target'      => isset( $item->target ) ? (string) $item->target : '',
			'menu-item-classes'     => isset( $item->classes ) ? (array) $item->classes : array(),
			'menu-item-xfn'         => isset( $item->xfn ) ? (string) $item->xfn : '',
			'menu-item-type'        => isset( $item->type ) && '' !== (string) $item->type ? (string) $item->type : 'custom',
			'menu-item-object'      => isset( $item->object ) ? (string) $item->object : 'custom',
			'menu-item-object-id'   => isset( $item->object_id ) ? absint( $item->object_id ) : 0,
			'menu-item-status'      => 'publish',
			'menu-item-parent-id'   => absint( $parent_id ),
			'menu-item-position'    => absint( $position ),
		);
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
				throw new RuntimeException( 'Primary navigation masih memiliki submenu tak terduga setelah normalisasi.' );
			}
			$path = $this->menu_path( (string) $item->url );
			if ( '' === $this->target_key_for_path( $path ) ) {
				throw new RuntimeException( 'Primary navigation masih memiliki item di luar empat destinasi approved: ' . (string) $item->title . '.' );
			}
			$top[] = $path;
		}
		if ( array( '/treatments/', '/promo/', '/skincare/', '/about/' ) !== $top ) {
			throw new RuntimeException( 'Primary navigation harus tepat Perawatan, Promo, Skincare, Tentang Gloskin tanpa item tambahan.' );
		}

		$preserved_count = absint( $state['preserved_item_count'] ?? 0 );
		if ( $preserved_count > 0 ) {
			$preserved_menu_id = absint( $state['preserved_menu_id'] ?? 0 );
			$preserved_items   = $preserved_menu_id ? wp_get_nav_menu_items( $preserved_menu_id ) : array();
			$preserved_items   = is_array( $preserved_items ) ? $preserved_items : array();
			if ( count( $preserved_items ) < $preserved_count ) {
				throw new RuntimeException( 'Snapshot menu editor tidak lengkap; migrasi dihentikan sebelum finalize.' );
			}
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
			'preserved_menu_id'  => absint( $state['preserved_menu_id'] ?? 0 ),
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
