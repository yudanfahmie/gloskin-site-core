<?php
/**
 * Bounded one-shot Promo Page and navigation recovery.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/class-gloskin-site-core-page-lookup.php';

final class Gloskin_Site_Core_Revision_20260820_Promo_Recovery {
	const REVISION     = '2026-08-20-promo-recovery-v1';
	const STATE_OPTION = 'gloskin_site_core_revision_20260820_promo_recovery_state';
	const LOCK_OPTION  = 'gloskin_site_core_revision_20260820_promo_recovery_lock';
	const LOCK_TTL     = 300;
	const MENU_LOCATION = 'gloskin-primary';
	const COLLISION_ID = 12314;

	/** @return array<int,array{key:string,label:string}> */
	private function steps() {
		return array(
			array( 'key' => 'preflight', 'label' => 'Preflight Promo — snapshot halaman, collision, menu, Promo, dan WooCommerce' ),
			array( 'key' => 'reconcile', 'label' => 'Memulihkan Page Promo dan mengikat ulang item navigasi' ),
			array( 'key' => 'verify', 'label' => 'Memverifikasi route, shell, Promo, collision, dan batas WooCommerce' ),
			array( 'key' => 'finalize', 'label' => 'Menyimpan audit dan menutup Finalisasi Tahap 2' ),
		);
	}

	/** @return array<string,mixed> */
	public function get_state() {
		$stored = get_option( self::STATE_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$defaults = array(
			'revision' => self::REVISION,
			'status' => 'pending',
			'next_step_index' => 0,
			'processed_steps' => 0,
			'total_steps' => count( $this->steps() ),
			'current_step' => 'Siap dijalankan',
			'last_error' => '',
			'preflight' => array(),
			'audit' => array(),
			'updated_at' => 0,
		);
		$state = array_merge( $defaults, $stored );
		return self::REVISION === (string) $state['revision'] ? $state : $defaults;
	}

	/** @return bool */
	public function is_consumed() { return 'consumed' === (string) $this->get_state()['status']; }

	/** @return array<string,mixed> */
	public function run_to_completion() {
		$state = $this->advance( 'start' );
		for ( $i = 0; $i < 10 && 'consumed' !== (string) $state['status']; $i++ ) { $state = $this->advance( 'continue' ); }
		if ( 'consumed' !== (string) $state['status'] ) { throw new RuntimeException( 'verification_pending: Finalisasi Tahap 2 belum dapat dikonsumsi.' ); }
		return $state;
	}

	/** @param string $mode start|continue. @return array<string,mixed> */
	public function advance( $mode ) {
		$mode = sanitize_key( (string) $mode );
		if ( ! in_array( $mode, array( 'start', 'continue' ), true ) ) { throw new RuntimeException( 'unexpected_error: Mode recovery tidak valid.' ); }
		$state = $this->get_state();
		if ( 'consumed' === (string) $state['status'] ) { return $state; }
		$token = $this->acquire_lock();
		if ( '' === $token ) { throw new RuntimeException( 'migration_locked: Finalisasi Tahap 2 sedang diproses.' ); }

		try {
			if ( 'start' === $mode ) {
				$state['status'] = 'running';
				$state['last_error'] = '';
				$state['current_step'] = $this->steps()[ (int) $state['next_step_index'] ]['label'];
				$state['updated_at'] = time();
				$this->save_state( $state );
				$this->release_lock( $token );
				return $state;
			}

			$index = (int) $state['next_step_index'];
			$steps = $this->steps();
			if ( ! isset( $steps[ $index ] ) ) { throw new RuntimeException( 'verification_failed: Checkpoint recovery tidak valid.' ); }
			$state['status'] = 'running';
			$state['current_step'] = $steps[ $index ]['label'];
			$state['last_error'] = '';
			$this->save_state( $state );

			switch ( $steps[ $index ]['key'] ) {
				case 'preflight':
					$state['preflight'] = $this->preflight();
					break;
				case 'reconcile':
					$state['audit'] = $this->reconcile( (array) $state['preflight'] );
					break;
				case 'verify':
					$state['audit']['verification'] = $this->verify( (array) $state['preflight'], (array) $state['audit'] );
					break;
				case 'finalize':
					$state['status'] = 'consumed';
					break;
			}

			$state['next_step_index'] = $index + 1;
			$state['processed_steps'] = min( count( $steps ), $index + 1 );
			$state['current_step'] = 'consumed' === (string) $state['status'] ? 'Selesai' : $steps[ $index + 1 ]['label'];
			$state['last_error'] = '';
			$state['updated_at'] = time();
			$this->save_state( $state );
			$this->release_lock( $token );
			return $state;
		} catch ( Throwable $error ) {
			$this->release_lock( $token );
			$failed = $this->get_state();
			$failed['status'] = 'failed';
			$failed['last_error'] = $error->getMessage();
			$failed['updated_at'] = time();
			$this->save_state( $failed );
			throw new RuntimeException( $error->getMessage(), 0, $error );
		}
	}

	/** @return array<string,mixed> */
	private function preflight() {
		$promo_page = Gloskin_Site_Core_Page_Lookup::find( 'promo' );
		$collision = get_post( self::COLLISION_ID );
		$promos = $this->promo_snapshot();
		return array(
			'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
			'promo_page' => $this->post_identity( $promo_page ),
			'promo_slug_objects' => $this->slug_objects( 'promo' ),
			'collision_12314' => $this->post_identity( $collision, true ),
			'navigation' => $this->navigation_snapshot(),
			'promos' => $promos['records'],
			'promo_fingerprints' => $promos['fingerprints'],
			'canonical_page_ids' => $this->canonical_page_ids(),
			'woo_page_ids' => $this->woo_page_ids(),
			'preserved_ids' => $this->preserved_ids(),
		);
	}

	/** @param array<string,mixed> $preflight @return array<string,mixed> */
	private function reconcile( array $preflight ) {
		$environment = isset( $preflight['environment'] ) ? (string) $preflight['environment'] : 'production';
		$page_result = $this->ensure_promo_page();
		$menu_result = $this->rebind_promo_menu_item( (int) $page_result['page_id'] );
		$promo_changes = 'production' === $environment ? array() : $this->promote_reset_demo_promos_if_required();
		$changed = ! empty( $page_result['changed'] ) || ! empty( $menu_result['changed'] );
		if ( $changed ) { flush_rewrite_rules( false ); }
		return array(
			'page_id' => (int) $page_result['page_id'],
			'page_action' => (string) $page_result['action'],
			'menu_id' => (int) $menu_result['menu_id'],
			'menu_item_id' => (int) $menu_result['menu_item_id'],
			'menu_rebound' => (bool) $menu_result['changed'],
			'promo_record_changes' => $promo_changes,
			'production_promo_mutations' => 'production' === $environment ? 0 : null,
			'rewrite_flushed' => $changed,
		);
	}

	/** @return array{page_id:int,action:string,changed:bool} */
	private function ensure_promo_page() {
		$page = Gloskin_Site_Core_Page_Lookup::find( 'promo' );
		if ( $page ) {
			if ( 'publish' === (string) $page->post_status ) { return array( 'page_id' => (int) $page->ID, 'action' => 'preserved', 'changed' => false ); }
			$result = wp_update_post( array( 'ID' => (int) $page->ID, 'post_status' => 'publish' ), true );
			if ( is_wp_error( $result ) ) { throw new RuntimeException( 'reconcile_failed: Page Promo tidak dapat dipublikasikan.' ); }
			return array( 'page_id' => (int) $page->ID, 'action' => 'published_existing', 'changed' => true );
		}

		$force_slug = static function ( $override, $slug, $post_id, $post_status, $post_type, $post_parent ) {
			unset( $post_id );
			return 'promo' === (string) $slug && 'page' === (string) $post_type && 0 === (int) $post_parent && 'publish' === (string) $post_status ? 'promo' : $override;
		};
		add_filter( 'pre_wp_unique_post_slug', $force_slug, 10, 6 );
		try {
			$result = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'promo', 'post_title' => 'Promo', 'post_content' => '' ), true );
		} finally {
			remove_filter( 'pre_wp_unique_post_slug', $force_slug, 10 );
		}
		if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'reconcile_failed: Page Promo baru tidak dapat dibuat.' ); }
		$page_id = absint( $result );
		update_post_meta( $page_id, '_gloskin_provisioned_revision', self::REVISION );
		$page = Gloskin_Site_Core_Page_Lookup::find( 'promo' );
		if ( ! $page || $page_id !== (int) $page->ID ) { throw new RuntimeException( 'reconcile_failed: Page Promo baru tidak memiliki slug kanonik.' ); }
		return array( 'page_id' => $page_id, 'action' => 'created', 'changed' => true );
	}

	/** @param int $page_id @return array{menu_id:int,menu_item_id:int,changed:bool} */
	private function rebind_promo_menu_item( $page_id ) {
		$navigation = $this->navigation_snapshot();
		$menu_id = (int) $navigation['menu_id'];
		$candidates = (array) $navigation['promo_items'];
		if ( ! $menu_id || 1 !== count( $candidates ) ) { throw new RuntimeException( 'reconcile_failed: Item navigasi Promo harus tersedia tepat satu.' ); }
		$item_id = (int) $candidates[0]['menu_item_id'];
		$items = wp_get_nav_menu_items( $menu_id );
		$item = null;
		foreach ( (array) $items as $candidate ) { if ( $item_id === (int) $candidate->ID ) { $item = $candidate; break; } }
		if ( ! $item ) { throw new RuntimeException( 'reconcile_failed: Item navigasi Promo tidak dapat dibaca ulang.' ); }
		if ( 'post_type' === (string) $item->type && 'page' === (string) $item->object && $page_id === (int) $item->object_id ) {
			return array( 'menu_id' => $menu_id, 'menu_item_id' => $item_id, 'changed' => false );
		}
		$args = array(
			'menu-item-title' => (string) $item->title,
			'menu-item-object-id' => $page_id,
			'menu-item-object' => 'page',
			'menu-item-type' => 'post_type',
			'menu-item-status' => 'publish',
			'menu-item-parent-id' => absint( $item->menu_item_parent ),
			'menu-item-position' => (int) $item->menu_order,
			'menu-item-description' => (string) $item->description,
			'menu-item-attr-title' => (string) $item->attr_title,
			'menu-item-target' => (string) $item->target,
			'menu-item-classes' => (array) $item->classes,
			'menu-item-xfn' => (string) $item->xfn,
		);
		$result = wp_update_nav_menu_item( $menu_id, $item_id, $args );
		if ( is_wp_error( $result ) || $item_id !== (int) $result ) { throw new RuntimeException( 'reconcile_failed: Item navigasi Promo gagal diikat ulang.' ); }
		return array( 'menu_id' => $menu_id, 'menu_item_id' => $item_id, 'changed' => true );
	}

	/** @return array<int,int> */
	private function promote_reset_demo_promos_if_required() {
		if ( $this->eligible_promo_ids() ) { return array(); }
		$allowed = array( 'gloskin-demo-r2-promo-brightening', 'gloskin-demo-r2-promo-konsultasi-gratis', 'gloskin-demo-r2-promo-acne-program' );
		$changed = array();
		foreach ( $allowed as $identity ) {
			$ids = get_posts( array( 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_gloskin_demo_identity', 'value' => $identity ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$id = $ids ? absint( $ids[0] ) : 0;
			if ( ! $id || '2026-08-19-final' === (string) get_post_meta( $id, '_gloskin_demo_revision', true ) ) { continue; }
			if ( 'publish' !== (string) get_post_status( $id ) ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) ); }
			if ( '1' !== (string) get_post_meta( $id, 'gloskin_promo_active', true ) ) { update_post_meta( $id, 'gloskin_promo_active', '1' ); }
			$changed[] = $id;
		}
		return $changed;
	}

	/** @param array<string,mixed> $preflight @param array<string,mixed> $audit @return array<string,mixed> */
	private function verify( array $preflight, array $audit ) {
		$page = Gloskin_Site_Core_Page_Lookup::find( 'promo' );
		if ( ! $page || 'publish' !== (string) $page->post_status || 'promo' !== (string) $page->post_name ) { throw new RuntimeException( 'verification_failed: Strict Page Promo belum valid.' ); }
		if ( self::COLLISION_ID === (int) $page->ID ) { throw new RuntimeException( 'verification_failed: Attachment collision tidak boleh menjadi Page Promo.' ); }
		$page_matches = array_values( array_filter( $this->slug_objects( 'promo' ), static function ( $object ) { return 'page' === (string) $object['type']; } ) );
		if ( 1 !== count( $page_matches ) || (int) $page_matches[0]['id'] !== (int) $page->ID ) { throw new RuntimeException( 'verification_failed: Page Promo kanonik harus tersedia tepat satu.' ); }
		if ( $this->post_identity( get_post( self::COLLISION_ID ), true ) !== (array) $preflight['collision_12314'] ) { throw new RuntimeException( 'verification_failed: Collision object #12314 berubah.' ); }
		if ( (int) url_to_postid( home_url( '/promo/' ) ) !== (int) $page->ID ) { throw new RuntimeException( 'verification_pending: Route /promo/ belum resolve ke Page kanonik; cache/rewrite mungkin belum segar.' ); }
		$navigation = $this->navigation_snapshot();
		if ( 1 !== count( $navigation['promo_items'] ) || (int) $navigation['promo_items'][0]['object_id'] !== (int) $page->ID ) { throw new RuntimeException( 'verification_failed: Navigasi Promo belum terikat tepat satu kali.' ); }
		if ( (array) $preflight['woo_page_ids'] !== $this->woo_page_ids() ) { throw new RuntimeException( 'verification_failed: WooCommerce Page IDs berubah.' ); }
		foreach ( (array) $preflight['preserved_ids'] as $id ) { if ( ! get_post( absint( $id ) ) ) { throw new RuntimeException( 'verification_failed: Record terhapus selama recovery.' ); } }
		if ( 'production' === (string) $preflight['environment'] && (array) $preflight['promo_fingerprints'] !== $this->promo_snapshot()['fingerprints'] ) { throw new RuntimeException( 'verification_failed: Promo records berubah di production.' ); }
		$eligible = $this->eligible_promo_ids();
		if ( ! $eligible ) { throw new RuntimeException( 'verification_failed: Tidak ada Promo published+active+date-eligible.' ); }
		$this->assert_no_promo_identity_duplicates();

		$response = wp_remote_get( home_url( '/promo/' ), array( 'timeout' => 8, 'redirection' => 0, 'cookies' => array() ) );
		if ( is_wp_error( $response ) ) { throw new RuntimeException( 'verification_pending: Route /promo/ belum dapat diperiksa.' ); }
		$status = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( 200 !== $status ) { throw new RuntimeException( 'verification_pending: Route /promo/ masih mengembalikan HTTP ' . $status . '.' ); }
		if ( false === strpos( $body, 'gloskin-ui1' ) || 1 !== preg_match_all( '/<h1\b/i', $body ) ) { throw new RuntimeException( 'verification_pending: Shell/H1 Promo belum terverifikasi.' ); }

		return array( 'page_id' => (int) $page->ID, 'route_status' => $status, 'eligible_promo_ids' => $eligible, 'menu_item_id' => (int) $audit['menu_item_id'], 'woo_page_ids_unchanged' => true, 'production_promo_records_unchanged' => 'production' !== (string) $preflight['environment'] || 0 === (int) $audit['production_promo_mutations'] );
	}

	/** @return array<string,mixed> */
	private function navigation_snapshot() {
		$locations = get_nav_menu_locations();
		$menu_id = absint( isset( $locations[ self::MENU_LOCATION ] ) ? $locations[ self::MENU_LOCATION ] : 0 );
		$matches = array();
		foreach ( $menu_id ? (array) wp_get_nav_menu_items( $menu_id ) : array() as $item ) {
			$path = wp_parse_url( (string) $item->url, PHP_URL_PATH );
			$is_promo_path = '/promo/' === trailingslashit( '/' . ltrim( (string) $path, '/' ) );
			if ( self::COLLISION_ID !== (int) $item->object_id && ! $is_promo_path && 'promo' !== strtolower( trim( (string) $item->title ) ) ) { continue; }
			$matches[] = array( 'menu_item_id' => (int) $item->ID, 'object_id' => (int) $item->object_id, 'object' => (string) $item->object, 'type' => (string) $item->type, 'title' => (string) $item->title, 'url' => (string) $item->url, 'order' => (int) $item->menu_order, 'parent' => (int) $item->menu_item_parent );
		}
		return array( 'menu_id' => $menu_id, 'promo_items' => $matches );
	}

	/** @return array<string,int> */
	private function canonical_page_ids() {
		$ids = array();
		foreach ( array( 'home', 'treatments', 'promo', 'skincare', 'about', 'clinics', 'doctors', 'contact' ) as $slug ) { $page = Gloskin_Site_Core_Page_Lookup::find( $slug ); $ids[ $slug ] = $page ? (int) $page->ID : 0; }
		return $ids;
	}

	/** @return array<string,int> */
	private function woo_page_ids() {
		$ids = array();
		foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $key ) { $ids[ $key ] = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( $key ) : 0; }
		return $ids;
	}

	/** @return array<int,int> */
	private function preserved_ids() {
		$ids = get_post( self::COLLISION_ID ) ? array( self::COLLISION_ID ) : array();
		foreach ( $this->slug_objects( 'promo' ) as $object ) { $ids[] = (int) $object['id']; }
		foreach ( $this->promo_snapshot()['records'] as $promo ) { $ids[] = (int) $promo['id']; }
		foreach ( $this->navigation_snapshot()['promo_items'] as $item ) { $ids[] = (int) $item['menu_item_id']; }
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/** @return array{records:array<int,array<string,mixed>>,fingerprints:array<int,string>} */
	private function promo_snapshot() {
		$records = array(); $fingerprints = array();
		$posts = get_posts( array( 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
		foreach ( $posts as $post ) {
			$id = (int) $post->ID;
			$records[] = array( 'id' => $id, 'status' => (string) $post->post_status, 'active' => (string) get_post_meta( $id, 'gloskin_promo_active', true ), 'start_date' => (string) get_post_meta( $id, 'gloskin_promo_start_date', true ), 'end_date' => (string) get_post_meta( $id, 'gloskin_promo_end_date', true ), 'identity' => (string) get_post_meta( $id, '_gloskin_demo_identity', true ), 'order' => (string) get_post_meta( $id, 'gloskin_promo_order', true ), 'eligible' => $this->promo_is_eligible( $post ) );
			$meta = get_post_meta( $id );
			ksort( $meta, SORT_STRING );
			$fingerprints[ $id ] = hash( 'sha256', wp_json_encode( array( get_object_vars( $post ), $meta ) ) );
		}
		ksort( $fingerprints, SORT_NUMERIC );
		return array( 'records' => $records, 'fingerprints' => $fingerprints );
	}

	/** @return void */
	private function assert_no_promo_identity_duplicates() {
		$seen = array();
		foreach ( $this->promo_snapshot()['records'] as $record ) {
			$identity = (string) $record['identity'];
			if ( '' === $identity ) { continue; }
			if ( isset( $seen[ $identity ] ) ) { throw new RuntimeException( 'verification_failed: Promo identity duplikat terdeteksi.' ); }
			$seen[ $identity ] = (int) $record['id'];
		}
	}

	/** @return array<int,int> */
	private function eligible_promo_ids() {
		$posts = get_posts( array( 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => array( array( 'key' => 'gloskin_promo_active', 'value' => '1', 'compare' => '=' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$posts = array_values( array_filter( $posts, array( $this, 'promo_is_eligible' ) ) );
		usort( $posts, array( $this, 'compare_promos' ) );
		return array_map( static function ( $post ) { return (int) $post->ID; }, $posts );
	}

	/** @param WP_Post $post @return bool */
	private function promo_is_eligible( $post ) {
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== (string) $post->post_status || '1' !== (string) get_post_meta( $post->ID, 'gloskin_promo_active', true ) ) { return false; }
		$now = function_exists( 'current_datetime' ) ? current_datetime() : new DateTimeImmutable( 'now', wp_timezone() );
		foreach ( array( 'start' => 'gloskin_promo_start_date', 'end' => 'gloskin_promo_end_date' ) as $side => $key ) {
			$value = trim( (string) get_post_meta( $post->ID, $key, true ) ); if ( '' === $value ) { continue; }
			try { $point = new DateTimeImmutable( $value . ( 'start' === $side ? ' 00:00:00' : ' 23:59:59' ), wp_timezone() ); } catch ( Exception $error ) { continue; }
			if ( 'start' === $side && $now < $point ) { return false; }
			if ( 'end' === $side && $now > $point ) { return false; }
		}
		return true;
	}

	/** @param WP_Post $a @param WP_Post $b @return int */
	public function compare_promos( $a, $b ) {
		$ao = (int) get_post_meta( $a->ID, 'gloskin_promo_order', true ); $bo = (int) get_post_meta( $b->ID, 'gloskin_promo_order', true );
		if ( $ao > 0 && $bo <= 0 ) { return -1; } if ( $ao <= 0 && $bo > 0 ) { return 1; } if ( $ao !== $bo ) { return $ao <=> $bo; }
		$title = strcmp( (string) $a->post_title, (string) $b->post_title ); return 0 !== $title ? $title : ( (int) $a->ID <=> (int) $b->ID );
	}

	/** @param string $slug @return array<int,array<string,mixed>> */
	private function slug_objects( $slug ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_status, post_parent, post_title, post_name FROM {$wpdb->posts} WHERE post_name = %s ORDER BY ID ASC LIMIT 20", $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = array();
		foreach ( (array) $rows as $row ) { $result[] = array( 'id' => (int) $row->ID, 'type' => (string) $row->post_type, 'status' => (string) $row->post_status, 'parent' => (int) $row->post_parent, 'title' => (string) $row->post_title, 'slug' => (string) $row->post_name ); }
		return $result;
	}

	/** @param WP_Post|null $post @param bool $fingerprint Include full record fingerprint. @return array<string,mixed> */
	private function post_identity( $post, $fingerprint = false ) {
		if ( ! ( $post instanceof WP_Post ) ) { return array( 'exists' => false ); }
		$identity = array( 'exists' => true, 'id' => (int) $post->ID, 'type' => (string) $post->post_type, 'status' => (string) $post->post_status, 'slug' => (string) $post->post_name, 'parent' => (int) $post->post_parent, 'title' => (string) $post->post_title, 'author' => (int) $post->post_author, 'date' => (string) $post->post_date, 'modified' => (string) $post->post_modified, 'mime_type' => (string) $post->post_mime_type, 'guid' => (string) $post->guid );
		if ( $fingerprint ) { $identity['fingerprint'] = hash( 'sha256', wp_json_encode( get_object_vars( $post ) ) ); }
		return $identity;
	}

	/** @return string */
	private function acquire_lock() {
		$current = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $current ) && ! empty( $current['created'] ) && time() - (int) $current['created'] < self::LOCK_TTL ) { return ''; }
		if ( $current ) { delete_option( self::LOCK_OPTION ); }
		$token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'gloskin-', true );
		return add_option( self::LOCK_OPTION, array( 'token' => $token, 'created' => time() ), '', 'no' ) ? $token : '';
	}

	/** @param string $token Token. @return void */
	private function release_lock( $token ) {
		$current = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], (string) $token ) ) { delete_option( self::LOCK_OPTION ); }
	}

	/** @param array<string,mixed> $state @return void */
	private function save_state( array $state ) { update_option( self::STATE_OPTION, $state, false ); }
}
