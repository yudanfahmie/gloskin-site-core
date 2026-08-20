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
			$state['current_step'] = $step['label'];
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
			array( 'key' => 'verify',             'label' => 'Memverifikasi keamanan dan integritas pasca-tulis' ),
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

		/* Resolve directory to first image file. */
		if ( ! is_file( $abs_path ) ) {
			$found = $this->find_first_image_in( $abs_path );
			if ( null === $found ) {
				$audit['skipped']++;
				$audit['skipped_assets'][] = $rel_path . ' (no readable file)';
				$last_action = 'Skipped: ' . basename( $abs_path );
				$cursor++;
				$audit['total_processed'] = $audit['imported'] + $audit['reused'] + $audit['recovered'] + $audit['skipped'];
				return compact( 'sha_to_id', 'audit', 'cursor', 'total', 'last_action' );
			}
			$abs_path = $found;
			$rel_path = ltrim( str_replace( $this->assets_base, '', $abs_path ), '/\\' );
		}

		if ( ! is_readable( $abs_path ) ) {
			$audit['skipped']++;
			$audit['skipped_assets'][] = $rel_path . ' (not readable)';
			$last_action = 'Skipped: ' . basename( $abs_path );
			$cursor++;
			$audit['total_processed'] = $audit['imported'] + $audit['reused'] + $audit['recovered'] + $audit['skipped'];
			return compact( 'sha_to_id', 'audit', 'cursor', 'total', 'last_action' );
		}

		$sha = hash_file( 'sha256', $abs_path );
		if ( false === $sha ) {
			$audit['skipped']++;
			$last_action = 'Skipped (hash error): ' . basename( $abs_path );
			$cursor++;
			$audit['total_processed'] = $audit['imported'] + $audit['reused'] + $audit['recovered'] + $audit['skipped'];
			return compact( 'sha_to_id', 'audit', 'cursor', 'total', 'last_action' );
		}

		/* 1. SHA dedup — reuse existing attachment if already imported. */
		$existing_id = $this->find_attachment_by_sha( $sha );
		if ( $existing_id ) {
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
			$audit['skipped']++;
			$audit['skipped_assets'][] = $rel_path . ' (import failed)';
			$last_action = 'Failed: ' . basename( $abs_path );
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
	 * CHECKPOINT: VERIFY
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $state Current migration state.
	 * @return void
	 * @throws RuntimeException On verification failure.
	 */
	private function run_verify( array $state ) {
		$errors = array();

		/* Fail closed against the authoritative 77ee resolved-target contract. */
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

		/* Verify four path slugs still exist. */
		$stable_slugs = array( 'acne-focus', 'brightening-focus', 'anti-aging-focus', 'skin-health-focus' );
		foreach ( $stable_slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY );
			if ( ! $term instanceof WP_Term ) {
				$errors[] = 'Path term hilang setelah migrasi: ' . $slug;
			}
		}

		/* No direct SQL mutations — this is a code-level contract verified by tests. */

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

		/* Treatment products: new products are draft/unpriced. Never invent a price. */
		if ( 'treatment' === $family && ! $existing_id ) {
			$woo_product->set_status( 'draft' );
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
			/* Exact SHA match — write provenance and ensure metadata is present. */
			update_post_meta( $candidate_id, self::ATTACH_SHA256_META, $sha );
			update_post_meta( $candidate_id, self::ATTACH_SOURCE_META, self::MANIFEST_ID );
			$metadata = wp_get_attachment_metadata( $candidate_id );
			if ( empty( $metadata ) || empty( $metadata['file'] ) ) {
				$this->load_media_functions();
				$new_meta = wp_generate_attachment_metadata( $candidate_id, $candidate_file );
				if ( $new_meta ) {
					wp_update_attachment_metadata( $candidate_id, $new_meta );
				}
			}
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

		/* Media reconcile fields — always present so JS runner can render counters. */
		$state['media_cursor']      = (int) ( $state['media_cursor'] ?? 0 );
		$state['media_total']       = (int) ( $state['media_total'] ?? 0 );
		$state['media_last_action'] = (string) ( $state['media_last_action'] ?? '' );

		return $state;
	}
}
// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
