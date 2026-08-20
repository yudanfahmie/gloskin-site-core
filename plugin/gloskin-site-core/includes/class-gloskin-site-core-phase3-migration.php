<?php
/**
 * Bounded Phase-3 migration coordinator — FB-989354 & FB-989360.
 *
 * Reconciles client product assets/data for Skincare and Treatment:
 *   • 25 resolved Skincare Woo products
 *   • 48 Woo Treatment Products (gloskin_product_family=treatment)
 *   • 8 informational gloskin_treatment CPT records
 *   • 4 existing gloskin_consultation_path terms (updated, not recreated)
 *   • Treatment landing hero media binding
 *
 * Safety contract:
 *   — Zero SQL content mutations: WP/Woo APIs only.
 *   — No price invention: new Treatment products are created as draft/unpriced.
 *   — No hard-deletes: supersede = wp_trash_post().
 *   — SHA-256 media deduplication prevents duplicate attachment imports.
 *   — Idempotent: a second run after COMPLETE is a no-op.
 *   — Resumable: each checkpoint advances exactly one step.
 *   — start/preflight perform zero content mutations.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Gloskin_Site_Core_Phase3_Migration {

	const MANIFEST_ID    = 'gloskin-client-feedback-phase3-migration-v1';
	const STATE_OPTION   = 'gloskin_site_core_client_feedback_phase3_v1_state';
	const LOCK_OPTION    = 'gloskin_site_core_client_feedback_phase3_v1_lock';
	const LOCK_TTL       = 300;

	/* Attachment provenance meta — SHA-256 dedup. */
	const ATTACH_SHA256_META  = '_gloskin_p3_sha256';
	const ATTACH_SOURCE_META  = '_gloskin_p3_source';

	/* Product/post provenance meta. */
	const POST_SOURCE_META    = '_gloskin_p3_source';
	const HOME_FEATURE_META   = 'gloskin_treatment_feature_on_home';

	/* Sample product provenance keys (inherited from existing bundle). */
	const SAMPLE_META         = '_gloskin_sample_data';

	/** @var string Absolute path to manifest JSON directory. */
	private $manifests_dir;

	/** @var string Absolute path to client assets bundle base. */
	private $assets_base;

	public function __construct() {
		$plugin_root = rtrim( plugin_dir_path( dirname( __FILE__ ) ), '/\\' );
		$sep         = DIRECTORY_SEPARATOR;
		$this->manifests_dir = $plugin_root . $sep . 'resources' . $sep . 'phase3' . $sep . 'manifests';
		$this->assets_base   = $plugin_root . $sep . 'resources' . $sep . 'phase3' . $sep . 'assets';
	}

	/* -----------------------------------------------------------------
	 * PUBLIC API
	 * ----------------------------------------------------------------- */

	/** @return bool */
	public function is_complete() {
		$state = $this->get_state();
		return 'complete' === $state['status'] && $this->fingerprint_matches( $state );
	}

	/** @return array<string,mixed> */
	public function get_state() {
		$stored = get_option( self::STATE_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array_merge( $this->default_state(), $stored );
	}

	/**
	 * Advance one checkpoint.
	 *
	 * @param string $mode start|continue.
	 * @return array<string,mixed>
	 * @throws RuntimeException On failure.
	 */
	public function advance( $mode ) {
		$mode = sanitize_key( (string) $mode );
		if ( ! in_array( $mode, array( 'start', 'continue' ), true ) ) {
			throw new RuntimeException( 'Mode Phase 3 tidak valid.' );
		}

		$state = $this->get_state();

		if ( 'complete' === $state['status'] && $this->fingerprint_matches( $state ) ) {
			/* Idempotent second run — no mutations. */
			return $this->response_state( $state );
		}

		$token = $this->acquire_lock();
		if ( '' === $token ) {
			throw new RuntimeException( 'Migrasi Phase 3 sedang berjalan pada request lain.' );
		}

		try {
			if ( 'start' === $mode ) {
				/* Preflight MUST perform zero content mutations. */
				$state['status']       = 'running';
				$state['last_error']   = '';
				$state['current_step'] = $this->step_label( 0 );
				$state['updated_at']   = time();
				$this->save_state( $state );
				$this->release_lock( $token );
				return $this->response_state( $state );
			}

			if ( ! in_array( $state['status'], array( 'running', 'failed' ), true ) ) {
				throw new RuntimeException( 'Migrasi Phase 3 belum dimulai. Jalankan start terlebih dahulu.' );
			}

			$index = (int) $state['next_step_index'];
			$steps = $this->steps();

			if ( $index >= count( $steps ) ) {
				$this->run_verify( $state );
				$state['manifest_fingerprint'] = $this->compute_fingerprint();
				$state['status']               = 'complete';
				$state['updated_at']           = time();
				$this->save_state( $state );
				$this->release_lock( $token );
				return $this->response_state( $state );
			}

			$step     = $steps[ $index ];
			$step_key = $step['key'];
			$state['current_step']     = $step['label'];
			$state['current_step_key'] = $step_key; /* Fix A: persist stable machine key at step start. */
			$this->save_state( $state );

			switch ( $step_key ) {
				case 'preflight':
					$this->run_preflight();
					break;

				case 'inventory':
					$state['audit']['inventory'] = $this->run_inventory();
					break;

				case 'dry_run':
					$state['audit']['dry_run'] = $this->run_dry_run();
					break;

				case 'media_reconcile':
					$result = $this->run_media_reconcile_step( $state );
					$state['sha_to_id']         = $result['sha_to_id'];
					$state['audit']['media']     = $result['audit'];
					$state['media_cursor']       = $result['cursor'];
					$state['media_total']        = $result['total'];
					$state['media_last_action']  = $result['last_action'];

					/* Cursor not yet exhausted — save and return without advancing step index. */
					if ( $result['cursor'] < $result['total'] ) {
						$state['status']     = 'running';
						$state['last_error'] = '';
						$state['updated_at'] = time();
						$this->save_state( $state );
						$this->release_lock( $token );
						return $this->response_state( $state );
					}
					/* All assets processed — fall through to advance step index. */
					break;

				case 'skincare_reconcile':
					$state['audit']['skincare'] = $this->run_skincare_reconcile( $state );
					break;

				case 'concerns_paths':
					$state['audit']['concerns_paths'] = $this->run_concerns_paths( $state );
					break;

				case 'treatment_products':
					$state['audit']['treatment_products'] = $this->run_treatment_products( $state );
					break;

				case 'treatment_records':
					$state['audit']['treatment_records'] = $this->run_treatment_records( $state );
					break;

				case 'page_media':
					$state['audit']['page_media'] = $this->run_page_media( $state );
					break;

				case 'pre_cleanup_gate':
					$this->run_pre_cleanup_gate( $state );
					break;

				case 'legacy_cleanup':
					$state['audit']['legacy_cleanup'] = $this->run_legacy_cleanup();
					break;

				case 'verify':
					$this->run_verify( $state );
					break;

				case 'complete':
					$this->run_verify( $state );
					$state['manifest_fingerprint'] = $this->compute_fingerprint();
					$state['status']               = 'complete';
					if ( function_exists( 'flush_rewrite_rules' ) ) {
						flush_rewrite_rules( false );
					}
					break;
			}

			if ( 'complete' !== $state['status'] ) {
				$state['status'] = 'running';
			}
			$state['next_step_index'] = $index + 1;
			$state['last_error']      = '';
			$state['updated_at']      = time();
			$this->save_state( $state );
			$this->release_lock( $token );
			return $this->response_state( $state );

		} catch ( Throwable $error ) {
			$this->release_lock( $token );
			$failed                = $this->get_state();
			$failed['status']      = 'failed';
			$failed['last_error']  = $error->getMessage();
			$failed['updated_at']  = time();
			$this->save_state( $failed );
			throw new RuntimeException( $error->getMessage(), 0, $error );
		}
	}

	/* -----------------------------------------------------------------
	 * STEP DEFINITIONS
	 * ----------------------------------------------------------------- */

	/** @return array<int,array{key:string,label:string}> */
	private function steps() {
		return array(
			array( 'key' => 'preflight',          'label' => 'Preflight — memeriksa manifest dan ketersediaan aset klien' ),
			array( 'key' => 'inventory',          'label' => 'Inventarisasi status Woo, Treatment CPT, dan taksonomi saat ini' ),
			array( 'key' => 'dry_run',            'label' => 'Rencana dry-run: CURRENT → TARGET → ACTION (tanpa mutasi)' ),
			array( 'key' => 'media_reconcile',    'label' => 'Mendeduplikasi dan mengimpor aset media klien (SHA-256)' ),
			array( 'key' => 'skincare_reconcile', 'label' => 'Merekonsiliasi 25 produk Skincare yang diselesaikan' ),
			array( 'key' => 'concerns_paths',     'label' => 'Memperbarui concern taxonomy dan empat path term (slugs dipertahankan)' ),
			array( 'key' => 'treatment_products', 'label' => 'Merekonsiliasi 48 Produk Treatment Woo' ),
			array( 'key' => 'treatment_records',  'label' => 'Merekonsiliasi 8 record informasional gloskin_treatment' ),
			array( 'key' => 'page_media',         'label' => 'Mengikat media halaman Treatment dan path term' ),
			array( 'key' => 'pre_cleanup_gate',   'label' => 'Verifikasi pre-cleanup: semua canonical reconcile valid sebelum cleanup' ),
			array( 'key' => 'legacy_cleanup',     'label' => 'Membersihkan Treatment produk/post/path/concern legacy' ),
			array( 'key' => 'verify',             'label' => 'Memverifikasi keamanan dan integritas pasca-tulis (state database aktual)' ),
			array( 'key' => 'complete',           'label' => 'Menyelesaikan dan mengunci migrasi Phase 3' ),
		);
	}

	/** @param int $index Step index. @return string */
	private function step_label( $index ) {
		$steps = $this->steps();
		return isset( $steps[ $index ] ) ? $steps[ $index ]['label'] : 'Siap';
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: PREFLIGHT (zero mutations)
	 * ----------------------------------------------------------------- */

	/**
	 * @return void
	 * @throws RuntimeException On any preflight failure.
	 */
	private function run_preflight() {
		$errors = array();

		/* Verify manifest files. */
		$manifest_files = array(
			'migration-manifest.json',
			'skincare-products.json',
			'treatment-catalog.json',
			'treatment-page-media.json',
			'unresolved.json',
		);
		foreach ( $manifest_files as $file ) {
			$path = $this->manifests_dir . DIRECTORY_SEPARATOR . $file;
			if ( ! is_readable( $path ) ) {
				$errors[] = 'Manifest tidak dapat dibaca: ' . $file . ' (expected: ' . $path . ')';
			}
		}

		/* Verify manifest IDs. */
		if ( empty( $errors ) ) {
			$mm = $this->load_json( 'migration-manifest.json' );
			if ( self::MANIFEST_ID !== ( $mm['manifest_id'] ?? '' ) ) {
				$errors[] = 'Manifest ID tidak cocok: expected ' . self::MANIFEST_ID;
			}
		}

		/* Verify WooCommerce availability. */
		if ( ! function_exists( 'wc_get_product' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			$errors[] = 'WooCommerce tidak tersedia. Aktifkan WooCommerce terlebih dahulu.';
		}

		/* Verify taxonomy registration. */
		if ( ! taxonomy_exists( Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY ) ) {
			$errors[] = 'Taksonomi gloskin_product_family belum terdaftar.';
		}
		if ( ! taxonomy_exists( Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY ) ) {
			$errors[] = 'Taksonomi gloskin_concern belum terdaftar.';
		}
		if ( ! taxonomy_exists( Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY ) ) {
			$errors[] = 'Taksonomi gloskin_consultation_path belum terdaftar.';
		}
		if ( ! post_type_exists( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE ) ) {
			$errors[] = 'CPT gloskin_treatment belum terdaftar.';
		}

		/* Sample a few critical client asset paths. */
		$sample_assets = array(
			'FB-989360-treatment-page/FOTO TREATMENT/AGING - KERUTAN/BOTOX/BTX.png',
			'FB-989360-treatment-page/FOTO TREATMENT/JERAWAT & BEKAS JERAWAT/SYLFIRM X/SYLFIRM.png',
			'FB-989354-skincare-page/FOTO PRODUCT PNG/BRIGHTENING FACE WASH.png',
		);
		$missing_assets = array();
		foreach ( $sample_assets as $rel ) {
			$full = $this->asset_path( $rel );
			if ( ! is_readable( $full ) ) {
				$missing_assets[] = $rel;
			}
		}
		if ( $missing_assets ) {
			$errors[] = 'Aset klien tidak dapat diakses (base: ' . $this->assets_base . '): ' . implode( '; ', $missing_assets );
		}

		if ( $errors ) {
			throw new RuntimeException( 'Phase 3 preflight gagal. ' . implode( ' | ', $errors ) );
		}
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: INVENTORY (zero mutations)
	 * ----------------------------------------------------------------- */

	/** @return array<string,mixed> */
	private function run_inventory() {
		$woo_treatment = 0;
		$woo_skincare  = 0;
		if ( function_exists( 'wc_get_products' ) ) {
			/* Bounded admin-only inventory scan, see WPPC-011. */
			$treatment_term = get_term_by( 'slug', 'treatment', Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY );
			$skincare_term  = get_term_by( 'slug', 'skincare', Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY );
			if ( $treatment_term instanceof WP_Term ) {
				$products = wc_get_products( array(
					'status'  => 'any',
					'limit'   => -1,
					'return'  => 'ids',
					'tax_query' => array( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded admin-only inventory
						'taxonomy' => Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY,
						'field'    => 'slug',
						'terms'    => 'treatment',
					) ),
				) );
				$woo_treatment = is_array( $products ) ? count( $products ) : 0;
			}
			if ( $skincare_term instanceof WP_Term ) {
				$products = wc_get_products( array(
					'status'  => 'any',
					'limit'   => -1,
					'return'  => 'ids',
					'tax_query' => array( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded admin-only inventory
						'taxonomy' => Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY,
						'field'    => 'slug',
						'terms'    => 'skincare',
					) ),
				) );
				$woo_skincare = is_array( $products ) ? count( $products ) : 0;
			}
		}

		$treatment_posts = (int) wp_count_posts( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE )->publish;
		$concern_count   = (int) wp_count_terms( Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, array( 'hide_empty' => false ) );
		$path_count      = (int) wp_count_terms( Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY, array( 'hide_empty' => false ) );

		return array(
			'woo_treatment_products' => $woo_treatment,
			'woo_skincare_products'  => $woo_skincare,
			'gloskin_treatment_posts' => $treatment_posts,
			'concern_terms'          => $concern_count,
			'path_terms'             => $path_count,
		);
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: DRY-RUN (zero mutations)
	 * ----------------------------------------------------------------- */

	/** @return array<string,mixed> */
	private function run_dry_run() {
		$plan        = array();
		$sk_manifest = $this->load_json( 'skincare-products.json' );
		$tr_manifest = $this->load_json( 'treatment-catalog.json' );

		foreach ( (array) ( $sk_manifest['records'] ?? array() ) as $product ) {
			$action = $this->resolve_product_action( $product );
			$plan[] = array(
				'id'     => $product['slug'],
				'name'   => $product['title'],
				'action' => $action,
			);
		}

		foreach ( (array) ( $tr_manifest['woo_treatment_products'] ?? array() ) as $product ) {
			$action = $this->resolve_product_action( $product );
			$plan[] = array(
				'id'     => $product['slug'],
				'name'   => $product['title'],
				'action' => $action,
			);
		}

		foreach ( (array) ( $tr_manifest['informational_cpt_targets'] ?? array() ) as $record ) {
			$existing = get_page_by_path( $record['slug'], OBJECT, Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE );
			$plan[]   = array(
				'id'     => $record['slug'],
				'name'   => $record['title'],
				'action' => $existing ? 'UPDATE' : 'CREATE',
			);
		}

		return array( 'plan' => $plan, 'total' => count( $plan ) );
	}

	/**
	 * Determine the action for a manifest product WITHOUT mutating anything.
	 *
	 * @param array<string,mixed> $product Manifest product entry.
	 * @return string REUSE|UPDATE|CREATE|SUPERSEDE
	 */
	private function resolve_product_action( array $product ) {
		/* 1. Explicit provenance (slug used as source ID in 77ee manifests). */
		$by_provenance = $this->find_product_by_provenance( (string) ( $product['slug'] ?? '' ) );
		if ( $by_provenance ) {
			return 'REUSE';
		}

		/* 2. Exact slug. */
		if ( ! empty( $product['slug'] ) ) {
			$by_slug = get_page_by_path( $product['slug'], OBJECT, 'product' );
			if ( $by_slug ) {
				return 'UPDATE';
			}
		}

		return 'CREATE';
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: MEDIA RECONCILE — cursor-based, one asset per advance()
	 * ----------------------------------------------------------------- */

	/**
	 * Build the same deterministic ordered list of unique absolute asset paths on every call.
	 * Order and dedup are stable across requests (manifests are immutable).
	 *
	 * @return string[] Ordered absolute paths.
	 */
	private function build_media_asset_list() {
		$sk_manifest   = $this->load_json( 'skincare-products.json' );
		$tr_manifest   = $this->load_json( 'treatment-catalog.json' );
		$page_manifest = $this->load_json( 'treatment-page-media.json' );

		$seen  = array(); /* keyed by absolute path → true, for inline dedup */
		$order = array(); /* ordered list of unique absolute paths */

		foreach ( (array) ( $sk_manifest['records'] ?? array() ) as $p ) {
			if ( ! empty( $p['primary'] ) ) {
				$abs = $this->sk_asset( $p['primary'] );
				if ( ! isset( $seen[ $abs ] ) ) { $seen[ $abs ] = true; $order[] = $abs; }
			}
			foreach ( (array) ( $p['alternate'] ?? array() ) as $alt ) {
				if ( '' !== $alt ) {
					$abs = $this->sk_asset( $alt );
					if ( ! isset( $seen[ $abs ] ) ) { $seen[ $abs ] = true; $order[] = $abs; }
				}
			}
		}
		foreach ( (array) ( $tr_manifest['woo_treatment_products'] ?? array() ) as $p ) {
			if ( ! empty( $p['primary'] ) ) {
				$abs = $this->tr_asset( $p['primary'] );
				if ( ! isset( $seen[ $abs ] ) ) { $seen[ $abs ] = true; $order[] = $abs; }
			}
		}
		foreach ( (array) ( $tr_manifest['informational_cpt_targets'] ?? array() ) as $r ) {
			if ( ! empty( $r['featured_asset'] ) ) {
				$abs = $this->tr_asset( $r['featured_asset'] );
				if ( ! isset( $seen[ $abs ] ) ) { $seen[ $abs ] = true; $order[] = $abs; }
			}
		}
		foreach ( (array) ( $page_manifest['presentation_media'] ?? array() ) as $item ) {
			if ( ! empty( $item['asset'] ) ) {
				$abs = $this->page_asset( $item['asset'] );
				if ( ! isset( $seen[ $abs ] ) ) { $seen[ $abs ] = true; $order[] = $abs; }
			}
		}

		return $order;
	}

	/**
	 * Process exactly ONE media asset at the current cursor position.
	 * Cursor incremented only after safe resolution; state saved by caller.
	 *
	 * @param array<string,mixed> $state Current migration state.
	 * @return array{sha_to_id:array<string,int>,audit:array<string,mixed>,cursor:int,total:int,last_action:string}
	 */
	private function run_media_reconcile_step( array $state ) {
		$this->load_media_functions();

		$asset_list = $this->build_media_asset_list();
		$total      = count( $asset_list );
		$cursor     = (int) ( $state['media_cursor'] ?? 0 );
		$sha_to_id  = (array) ( $state['sha_to_id'] ?? array() );
		$audit      = array_merge(
			array(
				'imported'        => 0,
				'reused'          => 0,
				'recovered'       => 0,
				'skipped'         => 0,
				'skipped_assets'  => array(),
				'total_processed' => 0,
			),
			(array) ( $state['audit']['media'] ?? array() )
		);
		$last_action = '';

		if ( $cursor >= $total ) {
			/* Already complete — idempotent no-op. */
			return array(
				'sha_to_id'   => $sha_to_id,
				'audit'       => $audit,
				'cursor'      => $cursor,
				'total'       => $total,
				'last_action' => 'Selesai',
			);
		}

		$abs_path = $asset_list[ $cursor ];
		$rel_path = ltrim( str_replace( $this->assets_base, '', $abs_path ), '/\\' );

		/* Skip .psd — not importable. */
		if ( 'psd' === strtolower( pathinfo( $abs_path, PATHINFO_EXTENSION ) ) ) {
			$audit['skipped']++;
			$audit['skipped_assets'][] = $rel_path;
			$last_action = 'Skipped (psd): ' . basename( $abs_path );
			$cursor++;
			$audit['total_processed'] = $audit['imported'] + $audit['reused'] + $audit['recovered'] + $audit['skipped'];
			return compact( 'sha_to_id', 'audit', 'cursor', 'total', 'last_action' );
		}

		/* Resolve directory to first image file.
		 * Fix B: non-PSD source failures throw RuntimeException so retry hits the same cursor item. */
		if ( ! is_file( $abs_path ) ) {
			$found = $this->find_first_image_in( $abs_path );
			if ( null === $found ) {
				throw new RuntimeException( 'Aset media wajib tidak terbaca/tidak ditemukan: ' . $rel_path );
			}
			$abs_path = $found;
			$rel_path = ltrim( str_replace( $this->assets_base, '', $abs_path ), '/\\' );
		}

		if ( ! is_readable( $abs_path ) ) {
			throw new RuntimeException( 'Aset media wajib tidak dapat dibaca: ' . $rel_path );
		}

		$sha = hash_file( 'sha256', $abs_path );
		if ( false === $sha ) {
			throw new RuntimeException( 'SHA-256 gagal dihitung untuk aset wajib: ' . $rel_path );
		}

		/* 1. SHA dedup — reuse existing attachment if already imported.
		 *    Fix C: repair incomplete metadata before classifying as REUSED. */
		$existing_id = $this->find_attachment_by_sha( $sha );
		if ( $existing_id ) {
			$this->ensure_attachment_metadata( $existing_id );
			$sha_to_id[ $sha ] = $existing_id;
			$audit['reused']++;
			$last_action = 'Reused: ' . basename( $abs_path );
			$cursor++;
			$audit['total_processed'] = $audit['imported'] + $audit['reused'] + $audit['recovered'] + $audit['skipped'];
			return compact( 'sha_to_id', 'audit', 'cursor', 'total', 'last_action' );
		}

		/* 2. Recovery — bounded title lookup + exact binary SHA verification (spec D).
		 *    Handles partial attachments left by any prior failed attempt. */
		$recovered_id = $this->recover_partial_attachment( $abs_path, $sha );
		if ( $recovered_id > 0 ) {
			$sha_to_id[ $sha ] = $recovered_id;
			$audit['recovered']++;
			$last_action = 'Recovered: ' . basename( $abs_path );
			$cursor++;
			$audit['total_processed'] = $audit['imported'] + $audit['reused'] + $audit['recovered'] + $audit['skipped'];
			return compact( 'sha_to_id', 'audit', 'cursor', 'total', 'last_action' );
		}

		/* 3. Import as new attachment — stream copy, no full-file PHP memory load. */
		$new_id = $this->import_local_asset( $abs_path, $sha );
		if ( $new_id > 0 ) {
			$sha_to_id[ $sha ] = $new_id;
			$audit['imported']++;
			$last_action = 'Imported: ' . basename( $abs_path );
		} else {
			throw new RuntimeException( 'Import aset wajib gagal (upload/copy/insert): ' . $rel_path );
		}

		$cursor++;
		$audit['total_processed'] = $audit['imported'] + $audit['reused'] + $audit['recovered'] + $audit['skipped'];
		return compact( 'sha_to_id', 'audit', 'cursor', 'total', 'last_action' );
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: SKINCARE RECONCILE
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $state Current migration state.
	 * @return array<string,mixed>
	 */
	private function run_skincare_reconcile( array $state ) {
		$manifest  = $this->load_json( 'skincare-products.json' );
		$sha_to_id = (array) ( $state['sha_to_id'] ?? array() );
		$audit     = array( 'created' => 0, 'updated' => 0, 'reused' => 0, 'skipped' => 0, 'id_map' => array() );

		Gloskin_Site_Core_Content_Service::ensure_family_terms();

		foreach ( (array) ( $manifest['records'] ?? array() ) as $product ) {
			try {
				$result = $this->reconcile_woo_product( $product, 'skincare', $sha_to_id );
				$audit[ $result['action'] ]++;
				if ( $result['woo_id'] > 0 ) {
					$audit['id_map'][ $product['slug'] ] = $result['woo_id'];
				}
			} catch ( Throwable $e ) {
				/* Unresolved item must NOT fail the entire migration. */
				$audit['skipped']++;
				$audit['id_map'][ $product['slug'] ?? 'unknown' ] = 'SKIPPED:' . $e->getMessage();
			}
		}

		return $audit;
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: CONCERNS + PATHS
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $state Current migration state.
	 * @return array<string,mixed>
	 */
	private function run_concerns_paths( array $state ) {
		$sha_to_id = (array) ( $state['sha_to_id'] ?? array() );
		$audit     = array( 'concern_terms_created' => 0, 'paths_updated' => 0 );

		Gloskin_Site_Core_Content_Service::ensure_family_terms();

		/* Create new Phase 3 concern terms. */
		$tr_manifest  = $this->load_json( 'treatment-catalog.json' );
		$new_concerns = (array) ( $tr_manifest['new_concerns_to_upsert'] ?? array() );
		foreach ( $new_concerns as $c ) {
			$slug  = sanitize_key( (string) $c['slug'] );
			$label = sanitize_text_field( (string) $c['label'] );
			if ( '' === $slug || '' === $label ) {
				continue;
			}
			if ( ! term_exists( $slug, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY ) ) {
				$result = wp_insert_term( $label, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, array( 'slug' => $slug ) );
				if ( ! is_wp_error( $result ) ) {
					$audit['concern_terms_created']++;
				}
			}
		}

		/* Update four existing path terms (extracted from presentation_media). */
		$page_manifest = $this->load_json( 'treatment-page-media.json' );
		$stable_slugs  = array( 'acne-focus', 'brightening-focus', 'anti-aging-focus', 'skin-health-focus' );
		$path_items    = array_filter(
			(array) ( $page_manifest['presentation_media'] ?? array() ),
			function ( $item ) {
				return isset( $item['slot'] ) && 0 === strpos( (string) $item['slot'], 'consultation_path:' );
			}
		);

		foreach ( $path_items as $path_def ) {
			$slug = sanitize_key( (string) ( $path_def['stable_slug'] ?? '' ) );
			if ( ! in_array( $slug, $stable_slugs, true ) ) {
				/* Never create additional consultation paths. */
				continue;
			}

			$term = get_term_by( 'slug', $slug, Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY );
			if ( ! $term instanceof WP_Term ) {
				/* Path term not yet created — create it now. */
				$insert_result = wp_insert_term(
					sanitize_text_field( (string) $path_def['label'] ),
					Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY,
					array( 'slug' => $slug )
				);
				if ( is_wp_error( $insert_result ) ) {
					continue;
				}
				$term = get_term( (int) $insert_result['term_id'], Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY );
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
			}

			$term_id = (int) $term->term_id;

			/* Update display label. */
			wp_update_term( $term_id, Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY, array(
				'name' => sanitize_text_field( (string) $path_def['label'] ),
			) );

			/* Update baseline concerns term meta. */
			$concern_ids = array();
			foreach ( (array) ( $path_def['baseline_concerns'] ?? array() ) as $c_slug ) {
				$c_term = get_term_by( 'slug', $c_slug, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY );
				if ( $c_term instanceof WP_Term ) {
					$concern_ids[] = (int) $c_term->term_id;
				}
			}
			update_term_meta( $term_id, Gloskin_Site_Core_Content_Service::PATH_META_BASELINE, $concern_ids );

			/* Update path image from SHA map. */
			$abs_asset = $this->page_asset( (string) ( $path_def['asset'] ?? '' ) );
			$attach_id = $this->resolve_attachment_from_asset( $abs_asset, $sha_to_id );
			if ( $attach_id > 0 ) {
				update_term_meta( $term_id, Gloskin_Site_Core_Content_Service::PATH_META_IMAGE_ID, $attach_id );
			}

			$audit['paths_updated']++;
		}

		return $audit;
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: TREATMENT PRODUCTS
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $state Current migration state.
	 * @return array<string,mixed>
	 */
	private function run_treatment_products( array $state ) {
		$manifest  = $this->load_json( 'treatment-catalog.json' );
		$sha_to_id = (array) ( $state['sha_to_id'] ?? array() );
		$audit     = array( 'created' => 0, 'updated' => 0, 'reused' => 0, 'trashed' => 0, 'skipped' => 0, 'id_map' => array() );

		foreach ( (array) ( $manifest['woo_treatment_products'] ?? array() ) as $product ) {
			try {
				$result = $this->reconcile_woo_product( $product, 'treatment', $sha_to_id );
				$action = $result['action'];
				if ( isset( $audit[ $action ] ) ) {
					$audit[ $action ]++;
				}
				if ( $result['woo_id'] > 0 ) {
					$audit['id_map'][ $product['slug'] ] = $result['woo_id'];
				}
			} catch ( Throwable $e ) {
				$audit['skipped']++;
				$audit['id_map'][ $product['slug'] ?? 'unknown' ] = 'SKIPPED:' . $e->getMessage();
			}
		}

		return $audit;
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: TREATMENT RECORDS
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $state Current migration state.
	 * @return array<string,mixed>
	 */
	private function run_treatment_records( array $state ) {
		$manifest  = $this->load_json( 'treatment-catalog.json' );
		$sha_to_id = (array) ( $state['sha_to_id'] ?? array() );
		$audit     = array( 'created' => 0, 'updated' => 0, 'reused' => 0, 'skipped' => 0, 'id_map' => array() );

		$home_feature_count = 0; /* Only 3 records should have feature_on_home=true. */

		foreach ( (array) ( $manifest['informational_cpt_targets'] ?? array() ) as $record ) {
			try {
				$slug     = sanitize_title( (string) ( $record['slug'] ?? '' ) );
				$name     = sanitize_text_field( (string) ( $record['title'] ?? '' ) );
				$summary  = sanitize_text_field( (string) ( $record['summary'] ?? '' ) );
				$feature  = ! empty( $record['feature_on_home'] );
				$source   = sanitize_key( (string) ( $record['slug'] ?? '' ) );

				if ( '' === $slug || '' === $name ) {
					$audit['skipped']++;
					continue;
				}

				/* Resolve by provenance → slug. */
				$existing = $this->find_treatment_record_by_provenance( $source );
				if ( ! $existing ) {
					$existing = get_page_by_path( $slug, OBJECT, Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE );
				}

				if ( $existing instanceof WP_Post ) {
					$post_id = (int) $existing->ID;
					wp_update_post( array(
						'ID'          => $post_id,
						'post_title'  => $name,
						'post_status' => 'publish',
						'post_name'   => $slug,
					) );
					update_post_meta( $post_id, self::POST_SOURCE_META, $source );
					$audit['updated']++;
				} else {
					$post_id = (int) wp_insert_post( array(
						'post_type'    => Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE,
						'post_title'   => $name,
						'post_name'    => $slug,
						'post_status'  => 'publish',
						'post_excerpt' => $summary,
					) );
					if ( $post_id > 0 ) {
						update_post_meta( $post_id, self::POST_SOURCE_META, $source );
						$audit['created']++;
					} else {
						$audit['skipped']++;
						continue;
					}
				}

				/* Set excerpt/summary. */
				if ( '' !== $summary ) {
					wp_update_post( array( 'ID' => $post_id, 'post_excerpt' => $summary ) );
				}

				/* Feature on home — only the 3 manifest-marked records. */
				if ( $feature && $home_feature_count < 3 ) {
					update_post_meta( $post_id, self::HOME_FEATURE_META, 1 );
					$home_feature_count++;
				} else {
					delete_post_meta( $post_id, self::HOME_FEATURE_META );
				}

				/* Set featured image from SHA map (77ee: featured_asset field). */
				$abs_asset = $this->tr_asset( (string) ( $record['featured_asset'] ?? '' ) );
				$attach_id = $this->resolve_attachment_from_asset( $abs_asset, $sha_to_id );
				if ( $attach_id > 0 ) {
					set_post_thumbnail( $post_id, $attach_id );
				}

				$audit['id_map'][ $record['slug'] ] = $post_id;

			} catch ( Throwable $e ) {
				$audit['skipped']++;
				$audit['id_map'][ $record['slug'] ?? 'unknown' ] = 'SKIPPED:' . $e->getMessage();
			}
		}

		return $audit;
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: PAGE MEDIA
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $state Current migration state.
	 * @return array<string,mixed>
	 */
	private function run_page_media( array $state ) {
		$manifest  = $this->load_json( 'treatment-page-media.json' );
		$sha_to_id = (array) ( $state['sha_to_id'] ?? array() );
		$audit     = array( 'hero_bound' => false, 'paths_bound' => 0, 'skipped' => 0 );

		$presentation_media = (array) ( $manifest['presentation_media'] ?? array() );

		/* Bind Treatments page hero (77ee: slot = 'treatments.hero'). */
		$hero_items = array_values( array_filter(
			$presentation_media,
			function ( $item ) { return isset( $item['slot'] ) && 'treatments.hero' === (string) $item['slot']; }
		) );
		$hero_def  = $hero_items[0] ?? array();
		$page_slug = 'treatments'; /* Derived from manifest page field '/treatments/'. */
		$meta_key  = 'gloskin_hero_media_id'; /* Hardcoded canonical key. */

		$treatments_page = Gloskin_Site_Core_Page_Lookup::find( $page_slug );
		$abs_hero        = $this->page_asset( (string) ( $hero_def['asset'] ?? '' ) );
		$attach_id       = $this->resolve_attachment_from_asset( $abs_hero, $sha_to_id );

		if ( $treatments_page instanceof WP_Post && $attach_id > 0 ) {
			update_post_meta( $treatments_page->ID, $meta_key, $attach_id );
			$audit['hero_bound'] = true;
		} else {
			$audit['skipped']++;
		}

		/* Bind path term images from presentation_media consultation_path items. */
		$stable_slugs = array( 'acne-focus', 'brightening-focus', 'anti-aging-focus', 'skin-health-focus' );
		$path_items   = array_filter(
			$presentation_media,
			function ( $item ) { return isset( $item['slot'] ) && 0 === strpos( (string) $item['slot'], 'consultation_path:' ); }
		);

		foreach ( $path_items as $path_def ) {
			$slug = sanitize_key( (string) ( $path_def['stable_slug'] ?? '' ) );
			if ( ! in_array( $slug, $stable_slugs, true ) ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY );
			if ( ! $term instanceof WP_Term ) {
				$audit['skipped']++;
				continue;
			}
			$abs_asset = $this->page_asset( (string) ( $path_def['asset'] ?? '' ) );
			$pid       = $this->resolve_attachment_from_asset( $abs_asset, $sha_to_id );
			if ( $pid > 0 ) {
				update_term_meta( (int) $term->term_id, Gloskin_Site_Core_Content_Service::PATH_META_IMAGE_ID, $pid );
				$audit['paths_bound']++;
			}
		}

		return $audit;
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: PRE-CLEANUP GATE
	 * ----------------------------------------------------------------- */

	/**
	 * Verify all canonical reconcile results are valid before any legacy cleanup.
	 * On any failure, throws RuntimeException — legacy cleanup is blocked.
	 *
	 * @param array<string,mixed> $state Current migration state.
	 * @return void
	 * @throws RuntimeException If canonical gate conditions are not met.
	 */
	private function run_pre_cleanup_gate( array $state ) {
		$errors = array();

		/* 1. Canonical reconcile counts from audit. */
		$sk_audit      = (array) ( $state['audit']['skincare'] ?? array() );
		$sk_reconciled = (int) ( $sk_audit['created'] ?? 0 ) + (int) ( $sk_audit['updated'] ?? 0 ) + (int) ( $sk_audit['reused'] ?? 0 );
		if ( 25 !== $sk_reconciled ) {
			$errors[] = 'Skincare reconcile belum 25 (ditemukan ' . $sk_reconciled . ').';
		}
		$tr_audit      = (array) ( $state['audit']['treatment_products'] ?? array() );
		$tr_reconciled = (int) ( $tr_audit['created'] ?? 0 ) + (int) ( $tr_audit['updated'] ?? 0 ) + (int) ( $tr_audit['reused'] ?? 0 );
		if ( 48 !== $tr_reconciled ) {
			$errors[] = 'Treatment product reconcile belum 48 (ditemukan ' . $tr_reconciled . ').';
		}
		$rec_audit = (array) ( $state['audit']['treatment_records'] ?? array() );
		$rec_total = (int) ( $rec_audit['created'] ?? 0 ) + (int) ( $rec_audit['updated'] ?? 0 ) + (int) ( $rec_audit['reused'] ?? 0 );
		if ( 8 !== $rec_total ) {
			$errors[] = 'Treatment record reconcile belum 8 (ditemukan ' . $rec_total . ').';
		}
		$paths_audit   = (array) ( $state['audit']['concerns_paths'] ?? array() );
		$paths_updated = (int) ( $paths_audit['paths_updated'] ?? 0 );
		if ( 4 !== $paths_updated ) {
			$errors[] = 'Consultation path update belum 4 (ditemukan ' . $paths_updated . ').';
		}
		$page_audit  = (array) ( $state['audit']['page_media'] ?? array() );
		$paths_bound = (int) ( $page_audit['paths_bound'] ?? 0 );
		if ( 4 !== $paths_bound ) {
			$errors[] = 'Path media binding belum 4 (ditemukan ' . $paths_bound . ').';
		}
		if ( true !== (bool) ( $page_audit['hero_bound'] ?? false ) ) {
			$errors[] = 'Treatment hero belum terikat.';
		}

		/* 2. Required skips must be 0. */
		$media_audit    = (array) ( $state['audit']['media'] ?? array() );
		$required_skips = (int) ( $media_audit['skipped'] ?? 0 )
			+ (int) ( $sk_audit['skipped'] ?? 0 )
			+ (int) ( $tr_audit['skipped'] ?? 0 )
			+ (int) ( $rec_audit['skipped'] ?? 0 )
			+ (int) ( $page_audit['skipped'] ?? 0 );
		if ( 0 !== $required_skips ) {
			$errors[] = 'Required skips bukan 0 (ditemukan ' . $required_skips . ').';
		}

		/* 3. Family taxonomy: spot-check actual DB counts.
		 *    (Full set verification is done in run_verify after cleanup.) */
		$skincare_term  = get_term_by( 'slug', 'skincare', Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY );
		$treatment_term = get_term_by( 'slug', 'treatment', Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY );

		if ( $skincare_term instanceof WP_Term ) {
			$sk_ids = wc_get_products( array(
				'status'    => array( 'publish', 'draft', 'private' ),
				'limit'     => -1,
				'return'    => 'ids',
				'tax_query' => array( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded admin-only gate check
					'taxonomy' => Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY,
					'field'    => 'slug',
					'terms'    => 'skincare',
				) ),
			) );
			if ( is_array( $sk_ids ) && count( $sk_ids ) < 25 ) {
				$errors[] = 'family=skincare produk di DB kurang dari 25 (ditemukan ' . count( $sk_ids ) . ').';
			}
		} else {
			$errors[] = 'Term family=skincare tidak ditemukan.';
		}

		if ( $treatment_term instanceof WP_Term ) {
			$tr_ids = wc_get_products( array(
				'status'    => array( 'publish', 'draft', 'private' ),
				'limit'     => -1,
				'return'    => 'ids',
				'tax_query' => array( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded admin-only gate check
					'taxonomy' => Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY,
					'field'    => 'slug',
					'terms'    => 'treatment',
				) ),
			) );
			if ( is_array( $tr_ids ) && count( $tr_ids ) < 48 ) {
				$errors[] = 'family=treatment produk di DB kurang dari 48 (ditemukan ' . count( $tr_ids ) . ').';
			}
		} else {
			$errors[] = 'Term family=treatment tidak ditemukan.';
		}

		/* 4. All canonical Woo products must have usable numeric price. */
		$tr_manifest      = $this->load_json( 'treatment-catalog.json' );
		$sk_manifest      = $this->load_json( 'skincare-products.json' );
		$enrichment       = $this->load_enrichment_prices();
		$unpriced_slugs   = array();
		$all_canon_slugs  = array_merge(
			array_column( (array) ( $sk_manifest['records'] ?? array() ), 'slug' ),
			array_column( (array) ( $tr_manifest['woo_treatment_products'] ?? array() ), 'slug' )
		);
		foreach ( $all_canon_slugs as $cslug ) {
			$canon_post = get_page_by_path( $cslug, OBJECT, 'product' );
			if ( ! $canon_post instanceof WP_Post ) {
				continue; /* Will be caught in run_verify. */
			}
			$canon_product = wc_get_product( (int) $canon_post->ID );
			if ( ! $canon_product ) {
				continue;
			}
			$price = (string) $canon_product->get_regular_price();
			if ( '' === $price || '0' === $price || ! is_numeric( $price ) || (float) $price <= 0 ) {
				$unpriced_slugs[] = $cslug;
			}
		}
		if ( ! empty( $unpriced_slugs ) ) {
			$errors[] = 'Produk canonical tanpa harga valid: ' . implode( ', ', array_slice( $unpriced_slugs, 0, 5 ) )
				. ( count( $unpriced_slugs ) > 5 ? ' (dan ' . ( count( $unpriced_slugs ) - 5 ) . ' lagi)' : '' );
		}

		if ( $errors ) {
			throw new RuntimeException( 'Pre-cleanup gate gagal — cleanup diblokir. ' . implode( ' | ', $errors ) );
		}
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: LEGACY CLEANUP
	 * ----------------------------------------------------------------- */

	/**
	 * Trash legacy Treatment Woo products and CPT records outside the authoritative 77ee allowlists.
	 * Delete extra consultation path terms and concern terms outside the authoritative 18.
	 * Idempotent: already-trashed/deleted objects are simply absent.
	 * Media Library attachments are NOT deleted here.
	 *
	 * @return array<string,int> Audit counts.
	 */
	private function run_legacy_cleanup() {
		$audit = array(
			'treatment_products_trashed' => 0,
			'treatment_records_trashed'  => 0,
			'paths_deleted'              => 0,
			'concerns_deleted'           => 0,
		);

		$tr_manifest      = $this->load_json( 'treatment-catalog.json' );
		$allowed_woo      = array_flip( array_column( (array) ( $tr_manifest['woo_treatment_products'] ?? array() ), 'slug' ) );
		$allowed_cpt      = array_flip( array_column( (array) ( $tr_manifest['informational_cpt_targets'] ?? array() ), 'slug' ) );
		$allowed_paths    = array_flip( array( 'acne-focus', 'brightening-focus', 'anti-aging-focus', 'skin-health-focus' ) );

		/* Authoritative 18 concern slugs: 10 existing + 8 new from manifest. */
		$existing_concerns   = (array) ( $tr_manifest['existing_concern_slugs'] ?? array() );
		$new_concerns_data   = (array) ( $tr_manifest['new_concerns_to_upsert'] ?? array() );
		$new_concern_slugs   = array_column( $new_concerns_data, 'slug' );
		$allowed_concerns    = array_flip( array_merge( $existing_concerns, $new_concern_slugs ) );

		/* A. Trash non-allowed Woo Treatment products (family=treatment, not in allowlist). */
		$all_treatment_ids = wc_get_products( array(
			'status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'limit'     => -1,
			'return'    => 'ids',
			'tax_query' => array( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded admin-only cleanup
				'taxonomy' => Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY,
				'field'    => 'slug',
				'terms'    => 'treatment',
			) ),
		) );
		foreach ( (array) $all_treatment_ids as $pid ) {
			$pid     = (int) $pid;
			$p_slug  = get_post_field( 'post_name', $pid );
			if ( ! isset( $allowed_woo[ $p_slug ] ) ) {
				wp_trash_post( $pid );
				$audit['treatment_products_trashed']++;
			}
		}

		/* B. Trash non-allowed gloskin_treatment CPT records. */
		$all_cpt_ids = get_posts( array(
			'post_type'      => Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'fields'         => 'ids',
			'numberposts'    => -1,
		) );
		foreach ( (array) $all_cpt_ids as $cid ) {
			$cid    = (int) $cid;
			$c_slug = get_post_field( 'post_name', $cid );
			if ( ! isset( $allowed_cpt[ $c_slug ] ) ) {
				wp_trash_post( $cid );
				$audit['treatment_records_trashed']++;
			}
		}

		/* C. Delete extra gloskin_consultation_path terms. */
		$all_path_terms = get_terms( array(
			'taxonomy'   => Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY,
			'hide_empty' => false,
		) );
		foreach ( (array) $all_path_terms as $path_term ) {
			if ( ! $path_term instanceof WP_Term ) {
				continue;
			}
			if ( ! isset( $allowed_paths[ $path_term->slug ] ) ) {
				wp_delete_term( (int) $path_term->term_id, Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY );
				$audit['paths_deleted']++;
			}
		}

		/* D. Delete concern terms outside the authoritative 18 slugs. */
		$all_concern_terms = get_terms( array(
			'taxonomy'   => Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY,
			'hide_empty' => false,
		) );
		foreach ( (array) $all_concern_terms as $concern_term ) {
			if ( ! $concern_term instanceof WP_Term ) {
				continue;
			}
			if ( ! isset( $allowed_concerns[ $concern_term->slug ] ) ) {
				wp_delete_term( (int) $concern_term->term_id, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY );
				$audit['concerns_deleted']++;
			}
		}

		return $audit;
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: VERIFY
	 * ----------------------------------------------------------------- */

	/**
	 * Final verifier — checks ACTUAL current database state, not only historical audit counters.
	 * Only allows COMPLETE when all state is confirmed correct.
	 *
	 * @param array<string,mixed> $state Current migration state.
	 * @return void
	 * @throws RuntimeException On verification failure.
	 */
	private function run_verify( array $state ) {
		$errors = array();

		$tr_manifest = $this->load_json( 'treatment-catalog.json' );
		$sk_manifest = $this->load_json( 'skincare-products.json' );

		/* ---- Audit counter checks (preserved from spec) ---- */
		$sk_audit      = (array) ( $state['audit']['skincare'] ?? array() );
		$sk_reconciled = (int) ( $sk_audit['created'] ?? 0 ) + (int) ( $sk_audit['updated'] ?? 0 ) + (int) ( $sk_audit['reused'] ?? 0 );
		if ( 25 !== $sk_reconciled ) {
			$errors[] = 'Skincare reconcile harus tepat 25; ditemukan ' . $sk_reconciled . '.';
		}

		$tr_audit      = (array) ( $state['audit']['treatment_products'] ?? array() );
		$tr_reconciled = (int) ( $tr_audit['created'] ?? 0 ) + (int) ( $tr_audit['updated'] ?? 0 ) + (int) ( $tr_audit['reused'] ?? 0 );
		if ( 48 !== $tr_reconciled ) {
			$errors[] = 'Treatment product reconcile harus tepat 48; ditemukan ' . $tr_reconciled . '.';
		}

		$rec_audit = (array) ( $state['audit']['treatment_records'] ?? array() );
		$rec_total = (int) ( $rec_audit['created'] ?? 0 ) + (int) ( $rec_audit['updated'] ?? 0 ) + (int) ( $rec_audit['reused'] ?? 0 );
		if ( 8 !== $rec_total ) {
			$errors[] = 'Treatment record reconcile harus tepat 8; ditemukan ' . $rec_total . '.';
		}

		$paths_audit   = (array) ( $state['audit']['concerns_paths'] ?? array() );
		$paths_updated = (int) ( $paths_audit['paths_updated'] ?? 0 );
		if ( 4 !== $paths_updated ) {
			$errors[] = 'Consultation path update harus tepat 4; ditemukan ' . $paths_updated . '.';
		}

		$page_audit  = (array) ( $state['audit']['page_media'] ?? array() );
		$paths_bound = (int) ( $page_audit['paths_bound'] ?? 0 );
		if ( 4 !== $paths_bound ) {
			$errors[] = 'Consultation path media binding harus tepat 4; ditemukan ' . $paths_bound . '.';
		}
		if ( true !== (bool) ( $page_audit['hero_bound'] ?? false ) ) {
			$errors[] = 'Treatment hero media wajib terikat.';
		}

		$media_audit    = (array) ( $state['audit']['media'] ?? array() );
		$required_skips = (int) ( $media_audit['skipped'] ?? 0 )
			+ (int) ( $sk_audit['skipped'] ?? 0 )
			+ (int) ( $tr_audit['skipped'] ?? 0 )
			+ (int) ( $rec_audit['skipped'] ?? 0 )
			+ (int) ( $page_audit['skipped'] ?? 0 );
		if ( 0 !== $required_skips ) {
			$errors[] = 'Target resolved wajib tidak memiliki skip; ditemukan ' . $required_skips . '.';
		}

		$home_feature_ids = get_posts( array(
			'post_type'   => Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE,
			'post_status' => 'any',
			'meta_key'    => self::HOME_FEATURE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded Phase-3 verifier
			'meta_value'  => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- bounded Phase-3 verifier
			'fields'      => 'ids',
			'numberposts' => -1,
		) );
		$home_feature_count = is_array( $home_feature_ids ) ? count( array_unique( array_map( 'intval', $home_feature_ids ) ) ) : 0;
		if ( 3 !== $home_feature_count ) {
			$errors[] = 'Informational Treatment dengan ' . self::HOME_FEATURE_META . '=true harus tepat 3; ditemukan ' . $home_feature_count . '.';
		}

		/* ---- Actual database state checks ---- */

		/* 48 NON-TRASHED Woo family=treatment, slug set exact. */
		$auth_tr_slugs   = array_column( (array) ( $tr_manifest['woo_treatment_products'] ?? array() ), 'slug' );
		$live_tr_ids     = wc_get_products( array(
			'status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'limit'     => -1,
			'return'    => 'ids',
			'tax_query' => array( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded Phase-3 verifier
				'taxonomy' => Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY,
				'field'    => 'slug',
				'terms'    => 'treatment',
			) ),
		) );
		$live_tr_slugs = array();
		foreach ( (array) $live_tr_ids as $tid ) {
			$live_tr_slugs[] = get_post_field( 'post_name', (int) $tid );
		}
		sort( $live_tr_slugs );
		$auth_tr_sorted = $auth_tr_slugs;
		sort( $auth_tr_sorted );
		if ( 48 !== count( $live_tr_slugs ) ) {
			$errors[] = 'Woo family=treatment non-trashed harus tepat 48 di DB; ditemukan ' . count( $live_tr_slugs ) . '.';
		} elseif ( $live_tr_slugs !== $auth_tr_sorted ) {
			$diff = array_diff( $auth_tr_sorted, $live_tr_slugs );
			$errors[] = 'Slug set Treatment Woo tidak cocok dengan authoritative 48; hilang: ' . implode( ',', array_slice( $diff, 0, 5 ) );
		}

		/* 8 NON-TRASHED gloskin_treatment, slug set exact. */
		$auth_cpt_slugs = array_column( (array) ( $tr_manifest['informational_cpt_targets'] ?? array() ), 'slug' );
		$live_cpt_posts = get_posts( array(
			'post_type'   => Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE,
			'post_status' => array( 'publish', 'draft', 'private', 'pending' ),
			'fields'      => 'ids',
			'numberposts' => -1,
		) );
		$live_cpt_slugs = array();
		foreach ( (array) $live_cpt_posts as $cid ) {
			$live_cpt_slugs[] = get_post_field( 'post_name', (int) $cid );
		}
		sort( $live_cpt_slugs );
		$auth_cpt_sorted = $auth_cpt_slugs;
		sort( $auth_cpt_sorted );
		if ( 8 !== count( $live_cpt_slugs ) ) {
			$errors[] = 'gloskin_treatment non-trashed harus tepat 8; ditemukan ' . count( $live_cpt_slugs ) . '.';
		} elseif ( $live_cpt_slugs !== $auth_cpt_sorted ) {
			$errors[] = 'Slug set CPT Treatment tidak cocok dengan authoritative 8.';
		}

		/* 4 consultation path terms, exact slugs. */
		$stable_slugs      = array( 'acne-focus', 'brightening-focus', 'anti-aging-focus', 'skin-health-focus' );
		$live_path_terms   = get_terms( array(
			'taxonomy'   => Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY,
			'hide_empty' => false,
		) );
		$live_path_slugs = array();
		foreach ( (array) $live_path_terms as $pt ) {
			if ( $pt instanceof WP_Term ) {
				$live_path_slugs[] = $pt->slug;
			}
		}
		sort( $live_path_slugs );
		$stable_sorted = $stable_slugs;
		sort( $stable_sorted );
		if ( 4 !== count( $live_path_slugs ) ) {
			$errors[] = 'Consultation path terms harus tepat 4; ditemukan ' . count( $live_path_slugs ) . '.';
		} elseif ( $live_path_slugs !== $stable_sorted ) {
			$errors[] = 'Path term slugs tidak cocok dengan empat slug authoritative.';
		}

		/* 18 concern terms, exact authoritative slugs. */
		$existing_concern_slugs = (array) ( $tr_manifest['existing_concern_slugs'] ?? array() );
		$new_concern_slugs      = array_column( (array) ( $tr_manifest['new_concerns_to_upsert'] ?? array() ), 'slug' );
		$auth_concern_slugs     = array_merge( $existing_concern_slugs, $new_concern_slugs );
		sort( $auth_concern_slugs );
		$live_concern_terms = get_terms( array(
			'taxonomy'   => Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY,
			'hide_empty' => false,
		) );
		$live_concern_slugs = array();
		foreach ( (array) $live_concern_terms as $ct ) {
			if ( $ct instanceof WP_Term ) {
				$live_concern_slugs[] = $ct->slug;
			}
		}
		sort( $live_concern_slugs );
		if ( 18 !== count( $live_concern_slugs ) ) {
			$errors[] = 'Concern terms harus tepat 18; ditemukan ' . count( $live_concern_slugs ) . '.';
		} elseif ( $live_concern_slugs !== $auth_concern_slugs ) {
			$diff = array_diff( $auth_concern_slugs, $live_concern_slugs );
			$errors[] = 'Concern slug set tidak cocok dengan authoritative 18; hilang: ' . implode( ',', $diff );
		}

		/* 25 skincare family=skincare. */
		$live_sk_ids = wc_get_products( array(
			'status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'limit'     => -1,
			'return'    => 'ids',
			'tax_query' => array( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded Phase-3 verifier
				'taxonomy' => Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY,
				'field'    => 'slug',
				'terms'    => 'skincare',
			) ),
		) );
		if ( 25 !== count( (array) $live_sk_ids ) ) {
			$errors[] = 'Woo family=skincare non-trashed harus tepat 25; ditemukan ' . count( (array) $live_sk_ids ) . '.';
		}

		/* All canonical Woo prices must be numeric > 0. */
		$unpriced = array();
		$all_canon = array_merge(
			array_column( (array) ( $sk_manifest['records'] ?? array() ), 'slug' ),
			$auth_tr_slugs
		);
		foreach ( $all_canon as $cslug ) {
			$cp = get_page_by_path( $cslug, OBJECT, 'product' );
			if ( ! $cp instanceof WP_Post ) {
				continue;
			}
			$cprod = wc_get_product( (int) $cp->ID );
			if ( ! $cprod ) {
				continue;
			}
			$cprice = (string) $cprod->get_regular_price();
			if ( '' === $cprice || ! is_numeric( $cprice ) || (float) $cprice <= 0 ) {
				$unpriced[] = $cslug;
			}
		}
		if ( ! empty( $unpriced ) ) {
			$errors[] = 'Produk canonical tanpa harga valid (> 0): ' . implode( ',', array_slice( $unpriced, 0, 5 ) );
		}

		/* Concern mappings for Treatment products match manifest. */
		foreach ( (array) ( $tr_manifest['woo_treatment_products'] ?? array() ) as $tp ) {
			$tp_slug = (string) ( $tp['slug'] ?? '' );
			$tp_post = get_page_by_path( $tp_slug, OBJECT, 'product' );
			if ( ! $tp_post instanceof WP_Post ) {
				continue;
			}
			$assigned_terms  = wp_get_object_terms( (int) $tp_post->ID, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, array( 'fields' => 'slugs' ) );
			$manifest_conc   = (array) ( $tp['concerns'] ?? array() );
			$assigned_sorted = is_array( $assigned_terms ) ? $assigned_terms : array();
			sort( $assigned_sorted );
			sort( $manifest_conc );
			if ( $assigned_sorted !== $manifest_conc ) {
				$errors[] = 'Concern mismatch pada ' . $tp_slug . ': expected [' . implode( ',', $manifest_conc ) . '] got [' . implode( ',', $assigned_sorted ) . '].';
			}
		}

		/* Zero active legacy Treatment products. */
		$legacy_tr = wc_get_products( array(
			'status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'limit'     => -1,
			'return'    => 'ids',
			'tax_query' => array( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded Phase-3 verifier
				'taxonomy' => Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY,
				'field'    => 'slug',
				'terms'    => 'treatment',
			) ),
		) );
		$allowed_woo_flip = array_flip( array_column( (array) ( $tr_manifest['woo_treatment_products'] ?? array() ), 'slug' ) );
		$legacy_active    = 0;
		foreach ( (array) $legacy_tr as $lid ) {
			$lslug = get_post_field( 'post_name', (int) $lid );
			if ( ! isset( $allowed_woo_flip[ $lslug ] ) ) {
				$legacy_active++;
			}
		}
		if ( $legacy_active > 0 ) {
			$errors[] = 'Masih ada ' . $legacy_active . ' Treatment Woo produk legacy aktif.';
		}

		if ( $errors ) {
			throw new RuntimeException( 'Phase 3 verify gagal. ' . implode( ' | ', $errors ) );
		}
	}

	/* -----------------------------------------------------------------
	 * WOO PRODUCT RECONCILE (shared by skincare + treatment)
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $product Manifest product.
	 * @param string $family 'skincare'|'treatment'.
	 * @param array<string,int> $sha_to_id SHA-256 → attachment ID map.
	 * @return array{action:string,woo_id:int}
	 * @throws RuntimeException On hard failure.
	 */
	private function reconcile_woo_product( array $product, $family, array $sha_to_id ) {
		/* 77ee manifests use slug as source ID, title as display name; no sku field. */
		$source_id = sanitize_key( (string) ( $product['slug'] ?? '' ) );
		$name      = sanitize_text_field( (string) ( $product['title'] ?? '' ) );
		$slug      = sanitize_title( (string) ( $product['slug'] ?? '' ) );

		if ( '' === $name ) {
			throw new RuntimeException( 'Product title kosong untuk: ' . $source_id );
		}

		$action     = 'created';
		$woo_id     = 0;
		$superseded = false;

		/* 1. Provenance lookup. */
		$existing_id = $this->find_product_by_provenance( $source_id );

		/* 2. Slug lookup (no SKU in 77ee manifests — preserve existing real SKU untouched). */
		if ( ! $existing_id && '' !== $slug ) {
			$by_slug = get_page_by_path( $slug, OBJECT, 'product' );
			if ( $by_slug instanceof WP_Post ) {
				$existing_id = (int) $by_slug->ID;
			}
		}

		/* Safety: is the existing a synthetic sample that can be superseded? */
		if ( $existing_id ) {
			$existing_product = wc_get_product( $existing_id );
			if ( $existing_product && '1' === (string) $existing_product->get_meta( self::SAMPLE_META, true ) ) {
				/* Supersede: trash the synthetic, create fresh. */
				wp_trash_post( $existing_id );
				$existing_id = 0;
				$superseded  = true;
				$action      = 'trashed';
			}
		}

		/* Open or create Woo product. */
		if ( $existing_id ) {
			$woo_product = wc_get_product( $existing_id );
			if ( ! $woo_product ) {
				throw new RuntimeException( 'Produk Woo tidak dapat dibuka: ' . $source_id );
			}
			$action = 'updated';
		} else {
			$woo_product = new WC_Product_Simple();
			$action      = 'created';
		}

		/* Apply fields — NEVER mutate verified real SKU/price/stock. */
		$woo_product->set_name( $name );
		if ( '' !== $slug ) {
			$woo_product->set_slug( $slug );
		}
		/* No copy_short in 77ee manifests; never invent a short description. */

		/* Commerce enrichment — apply prices from supplemental commerce-enrichment.json.
		 * Fix C: fail-fast price logic.
		 * — Existing product with legitimate regular_price > 0: preserve it, no mutation.
		 * — Otherwise (new product or existing with no/zero price): require enrichment price;
		 *   throw RuntimeException before counting reconciled if price unavailable.
		 * No SKU, no stock quantity — never fabricated (77ee global_rules). */
		$enrichment_prices = $this->load_enrichment_prices();
		$enrich_price      = isset( $enrichment_prices[ $slug ] ) ? $enrichment_prices[ $slug ] : '';
		$is_new_product    = ( 0 === $existing_id );

		if ( $is_new_product ) {
			/* New canonical product: enrichment price is required. */
			if ( '' === $enrich_price || ! is_numeric( $enrich_price ) || (float) $enrich_price <= 0 ) {
				throw new RuntimeException(
					'Harga enrichment wajib tidak tersedia untuk produk canonical baru (slug: ' . $slug . ').'
				);
			}
			$woo_product->set_regular_price( $enrich_price );
			$woo_product->set_price( $enrich_price );
		} else {
			/* Existing product: preserve legitimate price > 0; require enrichment when price absent/zero. */
			$current_price   = (string) $woo_product->get_regular_price();
			$has_legit_price = '' !== $current_price && is_numeric( $current_price ) && (float) $current_price > 0;
			if ( $has_legit_price ) {
				/* Preserve existing legitimate regular price — do not overwrite. */
			} else {
				if ( '' === $enrich_price || ! is_numeric( $enrich_price ) || (float) $enrich_price <= 0 ) {
					throw new RuntimeException(
						'Harga enrichment wajib tidak tersedia untuk produk canonical yang ada tanpa harga valid (slug: ' . $slug . ').'
					);
				}
				$woo_product->set_regular_price( $enrich_price );
				$woo_product->set_price( $enrich_price );
			}
		}

		/* Status: new products publish only after valid price; existing status preserved. */
		if ( $is_new_product ) {
			$has_price = '' !== (string) $woo_product->get_regular_price()
				&& '0' !== (string) $woo_product->get_regular_price();
			$woo_product->set_status( $has_price ? 'publish' : 'draft' );
			/* manage_stock=false for new canonical products (no fabricated stock quantity). */
			$woo_product->set_manage_stock( false );
		}

		/* Save. */
		$saved_id = (int) $woo_product->save();
		if ( ! $saved_id ) {
			throw new RuntimeException( 'WooCommerce gagal menyimpan produk: ' . $source_id );
		}
		$woo_id = $saved_id;

		/* Set provenance. */
		update_post_meta( $woo_id, self::POST_SOURCE_META, $source_id );

		/* Assign product family taxonomy. */
		$family_term = get_term_by( 'slug', $family, Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY );
		if ( $family_term instanceof WP_Term ) {
			wp_set_object_terms( $woo_id, (int) $family_term->term_id, Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY );
		}

		/* Assign concern taxonomy (treatment only). */
		if ( 'treatment' === $family && ! empty( $product['concerns'] ) ) {
			$concern_ids = array();
			foreach ( (array) $product['concerns'] as $c_slug ) {
				$c_term = get_term_by( 'slug', sanitize_key( $c_slug ), Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY );
				if ( $c_term instanceof WP_Term ) {
					$concern_ids[] = (int) $c_term->term_id;
				}
			}
			if ( $concern_ids ) {
				wp_set_object_terms( $woo_id, $concern_ids, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY );
			}
		}

		/* Set featured image from SHA map.
		 * 77ee: skincare uses 'primary' → sk_asset(); treatment uses 'primary' → tr_asset(). */
		$primary_rel = (string) ( $product['primary'] ?? '' );
		$abs_primary = 'skincare' === $family ? $this->sk_asset( $primary_rel ) : $this->tr_asset( $primary_rel );
		$attach_id   = '' !== $primary_rel ? $this->resolve_attachment_from_asset( $abs_primary, $sha_to_id ) : 0;
		if ( $attach_id > 0 ) {
			$woo_product = wc_get_product( $woo_id );
			if ( $woo_product ) {
				$woo_product->set_image_id( $attach_id );

				/* Set gallery — 77ee: skincare only, from 'alternate' field. */
				$gallery_ids = array();
				if ( 'skincare' === $family ) {
					foreach ( (array) ( $product['alternate'] ?? array() ) as $ga ) {
						if ( '' !== (string) $ga ) {
							$ga_id = $this->resolve_attachment_from_asset( $this->sk_asset( (string) $ga ), $sha_to_id );
							if ( $ga_id > 0 ) {
								$gallery_ids[] = $ga_id;
							}
						}
					}
				}
				if ( $gallery_ids ) {
					$woo_product->set_gallery_image_ids( $gallery_ids );
				}
				$woo_product->save();
			}
		}

		/* For already-reused (provenance found), report as reused. */
		if ( $existing_id && 'updated' === $action ) {
			$stored_source = get_post_meta( $existing_id, self::POST_SOURCE_META, true );
			if ( (string) $stored_source === $source_id && $existing_id === $woo_id ) {
				$action = 'reused';
			}
		}

		return array( 'action' => $action, 'woo_id' => $woo_id );
	}

	/* -----------------------------------------------------------------
	 * MEDIA IMPORT HELPERS
	 * ----------------------------------------------------------------- */

	/**
	 * @param string $abs_path Absolute path to local file.
	 * @param string $sha SHA-256 of the file.
	 * @return int Attachment ID, 0 on failure.
	 */
	/**
	 * Import a packaged asset using stream copy — no full-file PHP memory load (spec B).
	 * Writes provenance BEFORE wp_generate_attachment_metadata (spec C).
	 *
	 * @param string $abs_path Absolute source path.
	 * @param string $sha      SHA-256 hex digest of source file.
	 * @return int Attachment ID, 0 on failure.
	 */
	private function import_local_asset( $abs_path, $sha ) {
		$this->load_media_functions();

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return 0;
		}

		$dest_dir  = rtrim( $upload_dir['path'], '/\\' );
		$dest_url  = rtrim( $upload_dir['url'], '/' );
		$filename  = basename( $abs_path );
		$unique    = wp_unique_filename( $dest_dir, $filename );
		$dest_path = $dest_dir . DIRECTORY_SEPARATOR . $unique;

		/* Stream copy — exact binary, no re-encode, no full-file PHP memory load. */
		if ( ! copy( $abs_path, $dest_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.copy_copy -- stream copy to uploads, no WP equivalent
			return 0;
		}

		$file_type = wp_check_filetype( $unique, null );
		if ( empty( $file_type['type'] ) ) {
			@unlink( $dest_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return 0;
		}

		$attachment = array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $dest_url . '/' . $unique,
		);
		$attach_id = wp_insert_attachment( $attachment, $dest_path, 0, true );
		if ( is_wp_error( $attach_id ) || ! (int) $attach_id ) {
			@unlink( $dest_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return 0;
		}
		$attach_id = (int) $attach_id;

		/* Provenance BEFORE metadata — if metadata generation kills request,
		 * provenance is already durable so next advance() SHA-deduplicates (spec C). */
		update_post_meta( $attach_id, self::ATTACH_SHA256_META, $sha );
		update_post_meta( $attach_id, self::ATTACH_SOURCE_META, self::MANIFEST_ID );

		if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
			$metadata = wp_generate_attachment_metadata( $attach_id, $dest_path );
			if ( $metadata ) {
				wp_update_attachment_metadata( $attach_id, $metadata );
			}
		}

		/* Fail-closed: verify metadata is usable after generation.
		 * Provenance already written above — SHA dedup finds this attachment on retry,
		 * so no duplicate is created if this throws (fix B). */
		$this->ensure_attachment_metadata( $attach_id );

		return $attach_id;
	}

	/**
	 * Recover a partial attachment left by a prior failed attempt (spec D).
	 * Bounded title lookup (max 5 candidates) + mandatory exact binary SHA-256 verification.
	 * Never creates a duplicate; returns 0 if no exact-SHA match.
	 *
	 * @param string $abs_path Absolute source path (authoritative binary).
	 * @param string $sha      SHA-256 hex digest of source file (authoritative hash).
	 * @return int Attachment ID if recovered, 0 otherwise.
	 */
	private function recover_partial_attachment( $abs_path, $sha ) {
		global $wpdb;
		$title         = sanitize_file_name( basename( $abs_path ) );
		$candidate_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded admin-only recovery SELECT
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status IN ('inherit','private','trash') AND post_title = %s ORDER BY ID DESC LIMIT 5",
				$title
			)
		);
		if ( empty( $candidate_ids ) ) {
			return 0;
		}

		foreach ( $candidate_ids as $candidate_id ) {
			$candidate_id   = (int) $candidate_id;
			$candidate_file = get_attached_file( $candidate_id );
			if ( ! $candidate_file || ! is_readable( $candidate_file ) ) {
				continue;
			}
			$candidate_sha = hash_file( 'sha256', $candidate_file );
			/* Reject immediately if SHA cannot be computed or does not match — no fuzzy matching. */
			if ( false === $candidate_sha || ! hash_equals( $sha, $candidate_sha ) ) {
				continue;
			}
			/* Exact SHA match — write provenance and repair metadata via shared helper (fix C). */
			update_post_meta( $candidate_id, self::ATTACH_SHA256_META, $sha );
			update_post_meta( $candidate_id, self::ATTACH_SOURCE_META, self::MANIFEST_ID );
			$this->ensure_attachment_metadata( $candidate_id );
			return $candidate_id;
		}

		return 0;
	}

	/**
	 * @param string $sha SHA-256.
	 * @return int 0 if not found.
	 */
	private function find_attachment_by_sha( $sha ) {
		// Bounded admin-only migration dedup query, see WPPC-011.
		$results = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'meta_key'       => self::ATTACH_SHA256_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded admin-only migration dedup
			'meta_value'     => $sha, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- bounded admin-only migration dedup
			'fields'         => 'ids',
			'numberposts'    => 1,
		) );
		return is_array( $results ) && ! empty( $results ) ? (int) $results[0] : 0;
	}

	/**
	 * @param string $rel_path Relative asset path (may be a directory or a file path).
	 * @param array<string,int> $sha_to_id SHA → ID map.
	 * @return int Attachment ID, 0 if not resolved.
	 */
	private function resolve_attachment_from_asset( $rel_path, array $sha_to_id ) {
		if ( '' === $rel_path ) {
			return 0;
		}

		/* Accept both absolute paths (from sk_asset/tr_asset/page_asset helpers) and relative paths. */
		$abs_path = ( 0 === strpos( $rel_path, $this->assets_base ) )
			? $rel_path
			: $this->asset_path( $rel_path );

		/* If path is a directory, find first image file. */
		if ( ! is_file( $abs_path ) && is_dir( $abs_path ) ) {
			$found = $this->find_first_image_in( $abs_path );
			if ( null !== $found ) {
				$abs_path = $found;
			} else {
				return 0;
			}
		}

		if ( ! is_readable( $abs_path ) ) {
			return 0;
		}

		$sha = hash_file( 'sha256', $abs_path );
		if ( false === $sha ) {
			return 0;
		}

		return (int) ( $sha_to_id[ $sha ] ?? $this->find_attachment_by_sha( $sha ) );
	}

	/**
	 * Find first readable image in a directory.
	 *
	 * @param string $dir_path Absolute directory path.
	 * @return string|null Absolute file path or null.
	 */
	private function find_first_image_in( $dir_path ) {
		if ( ! is_dir( $dir_path ) ) {
			return null;
		}
		$extensions = array( 'jpg', 'jpeg', 'png', 'webp' );
		foreach ( (array) glob( $dir_path . DIRECTORY_SEPARATOR . '*' ) as $file ) {
			$ext = strtolower( pathinfo( (string) $file, PATHINFO_EXTENSION ) );
			if ( in_array( $ext, $extensions, true ) && is_readable( (string) $file ) ) {
				return (string) $file;
			}
		}
		return null;
	}

	/** @return void */
	private function load_media_functions() {
		if ( ! function_exists( 'wp_insert_attachment' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	/**
	 * Ensure attachment metadata is complete — regenerate only when missing/incomplete (fix B).
	 * Fail-closed: throws RuntimeException if metadata cannot be made usable.
	 * Used after SHA reuse, partial-attachment recovery, and new import so a prior fatal
	 * (provenance written but metadata generation killed the request) is self-healed;
	 * unrecoverable attachments are surfaced as errors rather than silently accepted.
	 *
	 * On failure the cursor is NOT advanced (caller throws before cursor++),
	 * and no duplicate attachment is created (provenance is already written for SHA dedup).
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 * @throws RuntimeException When metadata cannot be made usable.
	 */
	private function ensure_attachment_metadata( $attachment_id ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata ) && ! empty( $metadata['file'] ) ) {
			return; /* Already usable — accept. */
		}
		/* Cannot silently accept: must regenerate and confirm, or throw (fix B). */
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_readable( $file ) ) {
			throw new RuntimeException(
				'ensure_attachment_metadata: file sumber tidak terbaca untuk attachment #'
				. (int) $attachment_id . '. Metadata tidak dapat dipulihkan.'
			);
		}
		$this->load_media_functions();
		$new_meta = wp_generate_attachment_metadata( $attachment_id, $file );
		if ( ! $new_meta || empty( $new_meta['file'] ) ) {
			throw new RuntimeException(
				'ensure_attachment_metadata: wp_generate_attachment_metadata gagal untuk attachment #'
				. (int) $attachment_id . '.'
			);
		}
		wp_update_attachment_metadata( $attachment_id, $new_meta );
	}

	/* -----------------------------------------------------------------
	 * PROVENANCE HELPERS
	 * ----------------------------------------------------------------- */

	/**
	 * @param string $source_id P3 source identity string.
	 * @return int 0 if not found.
	 */
	private function find_product_by_provenance( $source_id ) {
		// Bounded admin-only migration identity lookup, see WPPC-011.
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'meta_key'       => self::POST_SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
			'numberposts'    => 1,
		) );
		return is_array( $ids ) && ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	/**
	 * @param string $source_id P3 source identity string.
	 * @return WP_Post|null
	 */
	private function find_treatment_record_by_provenance( $source_id ) {
		// Bounded admin-only migration identity lookup, see WPPC-011.
		$ids = get_posts( array(
			'post_type'      => Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE,
			'post_status'    => 'any',
			'meta_key'       => self::POST_SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
			'numberposts'    => 1,
		) );
		if ( is_array( $ids ) && ! empty( $ids ) ) {
			return get_post( (int) $ids[0] );
		}
		return null;
	}

	/* -----------------------------------------------------------------
	 * MANIFEST HELPERS
	 * ----------------------------------------------------------------- */

	/**
	 * @param string $filename Manifest filename (e.g. 'skincare-products.json').
	 * @return array<string,mixed>
	 * @throws RuntimeException If unreadable or invalid JSON.
	 */
	private function load_json( $filename ) {
		$path = $this->manifests_dir . DIRECTORY_SEPARATOR . $filename;
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local manifest read
		if ( false === $raw ) {
			throw new RuntimeException( 'Manifest tidak dapat dibaca: ' . $filename );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( 'Manifest JSON tidak valid: ' . $filename );
		}
		return $data;
	}

	/**
	 * Load commerce-enrichment.json once and cache per request.
	 * Returns slug → numeric price (string) map for both skincare and treatment.
	 * Build-time only — zero external web requests.
	 *
	 * Fail-closed (fix C): throws RuntimeException when the file is missing, JSON-invalid,
	 * has fewer than 25 skincare or 48 treatment entries, or any price is non-numeric / ≤ 0.
	 *
	 * @return array<string,string> slug → price string
	 * @throws RuntimeException When enrichment file is missing, malformed, or has invalid prices.
	 */
	private function load_enrichment_prices() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		/* Fix C: fail-closed — commerce-enrichment.json is required for canonical pricing. */
		$path = $this->manifests_dir . DIRECTORY_SEPARATOR . 'commerce-enrichment.json';
		if ( ! is_readable( $path ) ) {
			throw new RuntimeException( 'commerce-enrichment.json tidak dapat dibaca: ' . $path );
		}
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local supplemental manifest read
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( 'commerce-enrichment.json JSON tidak valid.' );
		}

		/* Validate coverage: at least 25 skincare + 48 treatment entries required. */
		$sk_count = count( (array) ( $data['skincare'] ?? array() ) );
		$tr_count = count( (array) ( $data['treatment'] ?? array() ) );
		if ( $sk_count < 25 ) {
			throw new RuntimeException( 'commerce-enrichment.json: butuh ≥25 entri skincare; ditemukan ' . $sk_count . '.' );
		}
		if ( $tr_count < 48 ) {
			throw new RuntimeException( 'commerce-enrichment.json: butuh ≥48 entri treatment; ditemukan ' . $tr_count . '.' );
		}

		/* Build slug→price map; collect invalid entries rather than silently skipping. */
		$map     = array();
		$invalid = array();
		foreach ( array( 'skincare', 'treatment' ) as $section ) {
			foreach ( (array) ( $data[ $section ] ?? array() ) as $entry ) {
				$slug  = (string) ( $entry['slug'] ?? '' );
				$price = (string) ( $entry['price'] ?? '' );
				if ( '' === $slug ) {
					continue;
				}
				if ( '' === $price || ! is_numeric( $price ) || (float) $price <= 0 ) {
					$invalid[] = $section . ':' . $slug;
				} else {
					$map[ $slug ] = $price;
				}
			}
		}
		if ( ! empty( $invalid ) ) {
			throw new RuntimeException(
				'commerce-enrichment.json: harga tidak valid (harus numerik > 0) untuk: '
				. implode( ', ', array_slice( $invalid, 0, 5 ) )
				. ( count( $invalid ) > 5 ? ' (dan ' . ( count( $invalid ) - 5 ) . ' lagi)' : '' )
			);
		}

		$cache = $map;
		return $cache;
	}

	/** @return string */
	private function compute_fingerprint() {
		$hashes = array();
		foreach ( array( 'migration-manifest.json', 'skincare-products.json', 'treatment-catalog.json', 'treatment-page-media.json', 'unresolved.json' ) as $file ) {
			$path = $this->manifests_dir . DIRECTORY_SEPARATOR . $file;
			if ( is_readable( $path ) ) {
				$hashes[] = hash_file( 'sha256', $path );
			}
		}
		return hash( 'sha256', implode( ':', $hashes ) );
	}

	/**
	 * @param array<string,mixed> $state State to check.
	 * @return bool
	 */
	private function fingerprint_matches( array $state ) {
		$stored = (string) ( $state['manifest_fingerprint'] ?? '' );
		if ( '' === $stored ) {
			return false;
		}
		try {
			return hash_equals( $stored, $this->compute_fingerprint() );
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * @param string $rel_path Relative path from assets base.
	 * @return string Absolute path.
	 */
	private function asset_path( $rel_path ) {
		return $this->assets_base . DIRECTORY_SEPARATOR . ltrim( str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $rel_path ), DIRECTORY_SEPARATOR );
	}

	/**
	 * Resolve a skincare-bundle relative path (relative to skincare FOTO PRODUCT PNG/ folder).
	 *
	 * @param string $rel Relative path from 77ee skincare manifest 'primary'/'alternate' fields.
	 * @return string Absolute path.
	 */
	private function sk_asset( $rel ) {
		return $this->asset_path( 'FB-989354-skincare-page/FOTO PRODUCT PNG/' . ltrim( $rel, '/' ) );
	}

	/**
	 * Resolve a treatment-bundle relative path (relative to FOTO TREATMENT/ folder).
	 *
	 * @param string $rel Relative path from 77ee treatment manifest 'primary'/'featured_asset' fields.
	 * @return string Absolute path.
	 */
	private function tr_asset( $rel ) {
		return $this->asset_path( 'FB-989360-treatment-page/FOTO TREATMENT/' . ltrim( $rel, '/' ) );
	}

	/**
	 * Resolve a page-media asset path (77ee stores full repo-relative paths; strip the bundle prefix).
	 *
	 * @param string $full_path Full path from 77ee page-media manifest 'asset' fields.
	 * @return string Absolute path.
	 */
	private function page_asset( $full_path ) {
		$bundle_prefix = 'docs/feedback-cases-gloskin-20260820-154828/';
		$rel = ltrim( str_replace( $bundle_prefix, '', $full_path ), '/' );
		return $this->asset_path( $rel );
	}

	/* -----------------------------------------------------------------
	 * STATE / LOCK
	 * ----------------------------------------------------------------- */

	/** @return array<string,mixed> */
	private function default_state() {
		return array(
			'status'               => 'pending',
			'next_step_index'      => 0,
			'current_step'         => 'Siap dijalankan',
			'current_step_key'     => '',
			'manifest_fingerprint' => '',
			'sha_to_id'            => array(),
			'media_cursor'         => 0,
			'media_total'          => 0,
			'media_last_action'    => '',
			'audit'                => array(
				'inventory'          => array(),
				'dry_run'            => array(),
				'media'              => array(
					'imported'        => 0,
					'reused'          => 0,
					'recovered'       => 0,
					'skipped'         => 0,
					'skipped_assets'  => array(),
					'total_processed' => 0,
				),
				'skincare'           => array(),
				'concerns_paths'     => array(),
				'treatment_products' => array(),
				'treatment_records'  => array(),
				'page_media'         => array(),
				'legacy_cleanup'     => array(
					'treatment_products_trashed' => 0,
					'treatment_records_trashed'  => 0,
					'paths_deleted'              => 0,
					'concerns_deleted'           => 0,
				),
			),
			'last_error'           => '',
			'updated_at'           => 0,
		);
	}

	/** @return string Lock token, empty if failed to acquire. */
	private function acquire_lock() {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && ! empty( $lock['created_at'] ) && ( time() - (int) $lock['created_at'] ) > self::LOCK_TTL ) {
			delete_option( self::LOCK_OPTION );
		}
		$token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'gloskin-p3-', true );
		return add_option( self::LOCK_OPTION, array( 'token' => $token, 'created_at' => time() ), '', false ) ? $token : '';
	}

	/** @param string $token Lock token. @return void */
	private function release_lock( $token ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}

	/** @param array<string,mixed> $state State. @return void */
	private function save_state( array $state ) {
		$state['updated_at'] = time();
		update_option( self::STATE_OPTION, $state, false );
	}

	/** @param array<string,mixed> $state State. @return array<string,mixed> */
	private function response_state( array $state ) {
		$steps     = $this->steps();
		$total     = count( $steps );
		$index     = (int) ( $state['next_step_index'] ?? 0 );
		$processed = min( $total, $index );

		$state['progress_percent']  = 'complete' === $state['status']
			? 100
			: (int) floor( ( $processed / max( 1, $total ) ) * 100 );
		$state['total_steps']       = $total;
		$state['step_number']       = $processed + 1;

		/* current_step_key — stable machine key persisted by advance() at step start (fix A).
		 * Never re-derive from index arithmetic: the key must stay 'media_reconcile'
		 * for every partial-media advance() while cursor < total. */
		if ( '' === (string) ( $state['current_step_key'] ?? '' ) ) {
			$current_index    = max( 0, $index - 1 );
			$current_step_def = $steps[ $current_index ] ?? array();
			$state['current_step_key'] = (string) ( $current_step_def['key'] ?? '' );
		}

		/* Media reconcile fields — always present so JS runner can render counters. */
		$state['media_cursor']      = (int) ( $state['media_cursor'] ?? 0 );
		$state['media_total']       = (int) ( $state['media_total'] ?? 0 );
		$state['media_last_action'] = (string) ( $state['media_last_action'] ?? '' );

		return $state;
	}
}
// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
