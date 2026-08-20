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

	const MANIFEST_ID    = 'gloskin-phase3-v1';
	const STATE_OPTION   = 'gloskin_site_core_phase3_v1_state';
	const LOCK_OPTION    = 'gloskin_site_core_phase3_v1_lock';
	const LOCK_TTL       = 300;

	/* Attachment provenance meta — SHA-256 dedup. */
	const ATTACH_SHA256_META  = '_gloskin_p3_sha256';
	const ATTACH_SOURCE_META  = '_gloskin_p3_source';

	/* Product/post provenance meta. */
	const POST_SOURCE_META    = '_gloskin_p3_source';
	const HOME_FEATURE_META   = '_gloskin_treatment_home_feature';

	/* Sample product provenance keys (inherited from existing bundle). */
	const SAMPLE_META         = '_gloskin_sample_data';

	/** @var string Absolute path to manifest JSON directory. */
	private $manifests_dir;

	/** @var string Absolute path to client assets bundle base. */
	private $assets_base;

	public function __construct() {
		$abspath = rtrim( ABSPATH, '/\\' );
		$sep     = DIRECTORY_SEPARATOR;
		$this->manifests_dir = $abspath . $sep . 'docs' . $sep . 'client-feedback-phase-3' . $sep . 'manifests';
		$this->assets_base   = $abspath . $sep . 'docs' . $sep . 'feedback-cases-gloskin-20260820-154828';
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
				$state['status']     = 'complete';
				$state['updated_at'] = time();
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
					$result = $this->run_media_reconcile();
					$state['audit']['media']    = $result['audit'];
					$state['sha_to_id']         = $result['sha_to_id'];
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
			'FB-989360-treatment-page/FOTO TREATMENT/JERAWAT & BEKAS JERAWAT/SYLFIRM X/SYLFIRM.jpg',
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

		foreach ( (array) ( $sk_manifest['products'] ?? array() ) as $product ) {
			$action = $this->resolve_product_action( $product );
			$plan[] = array(
				'id'     => $product['id'],
				'name'   => $product['name'],
				'action' => $action,
			);
		}

		foreach ( (array) ( $tr_manifest['treatment_products'] ?? array() ) as $product ) {
			$action = $this->resolve_product_action( $product );
			$plan[] = array(
				'id'     => $product['id'],
				'name'   => $product['name'],
				'action' => $action,
			);
		}

		foreach ( (array) ( $tr_manifest['treatment_records'] ?? array() ) as $record ) {
			$existing = get_page_by_path( $record['slug'], OBJECT, Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE );
			$plan[]   = array(
				'id'     => $record['id'],
				'name'   => $record['name'],
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
		/* 1. Explicit provenance. */
		$by_provenance = $this->find_product_by_provenance( (string) $product['id'] );
		if ( $by_provenance ) {
			return 'REUSE';
		}

		/* 2. Exact SKU. */
		if ( ! empty( $product['sku'] ) && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$sku_id = (int) wc_get_product_id_by_sku( $product['sku'] );
			if ( $sku_id ) {
				$existing = wc_get_product( $sku_id );
				if ( $existing ) {
					/* Provenance: is this a sample (synthetic) product? */
					if ( '1' === (string) $existing->get_meta( self::SAMPLE_META, true ) ) {
						return 'SUPERSEDE';
					}
					return 'UPDATE';
				}
			}
		}

		/* 3. Exact slug. */
		if ( ! empty( $product['slug'] ) ) {
			$by_slug = get_page_by_path( $product['slug'], OBJECT, 'product' );
			if ( $by_slug ) {
				return 'UPDATE';
			}
		}

		return 'CREATE';
	}

	/* -----------------------------------------------------------------
	 * CHECKPOINT: MEDIA RECONCILE
	 * ----------------------------------------------------------------- */

	/**
	 * @return array{audit:array<string,int>,sha_to_id:array<string,int>}
	 */
	private function run_media_reconcile() {
		$this->load_media_functions();

		$sk_manifest  = $this->load_json( 'skincare-products.json' );
		$tr_manifest  = $this->load_json( 'treatment-catalog.json' );
		$page_manifest = $this->load_json( 'treatment-page-media.json' );

		/* Collect all unique relative asset paths. */
		$asset_paths = array();

		foreach ( (array) ( $sk_manifest['products'] ?? array() ) as $p ) {
			$this->collect_assets( $asset_paths, $p );
		}
		foreach ( (array) ( $tr_manifest['treatment_products'] ?? array() ) as $p ) {
			$this->collect_assets( $asset_paths, $p );
		}
		foreach ( (array) ( $tr_manifest['treatment_records'] ?? array() ) as $r ) {
			if ( ! empty( $r['client_asset'] ) ) {
				$asset_paths[ $r['client_asset'] ] = true;
			}
		}
		foreach ( (array) ( $page_manifest['paths'] ?? array() ) as $path ) {
			if ( ! empty( $path['client_asset'] ) ) {
				$asset_paths[ $path['client_asset'] ] = true;
			}
		}
		if ( ! empty( $page_manifest['treatments_page_hero']['client_asset'] ) ) {
			$asset_paths[ $page_manifest['treatments_page_hero']['client_asset'] ] = true;
		}

		$sha_to_id      = array();
		$imported       = 0;
		$reused         = 0;
		$skipped        = 0;
		$skipped_assets = array();

		foreach ( array_keys( $asset_paths ) as $rel_path ) {
			$abs_path = $this->asset_path( $rel_path );

			/* Skip non-readable or .psd assets. */
			if ( 'psd' === strtolower( pathinfo( $rel_path, PATHINFO_EXTENSION ) ) ) {
				$skipped++;
				$skipped_assets[] = $rel_path;
				continue;
			}
			/* Asset might be a directory path (no specific file). */
			if ( ! is_file( $abs_path ) ) {
				/* Try to find first image in directory. */
				$found = $this->find_first_image_in( $abs_path );
				if ( null === $found ) {
					$skipped++;
					$skipped_assets[] = $rel_path . ' (no readable file)';
					continue;
				}
				$abs_path = $found;
				$rel_path = ltrim( str_replace( $this->assets_base, '', $abs_path ), '/\\' );
			}
			if ( ! is_readable( $abs_path ) ) {
				$skipped++;
				$skipped_assets[] = $rel_path;
				continue;
			}

			$sha = hash_file( 'sha256', $abs_path );
			if ( false === $sha ) {
				$skipped++;
				continue;
			}

			/* Check existing dedup by SHA. */
			$existing_id = $this->find_attachment_by_sha( $sha );
			if ( $existing_id ) {
				$sha_to_id[ $sha ] = $existing_id;
				$reused++;
				continue;
			}

			/* Import as new attachment. */
			$new_id = $this->import_local_asset( $abs_path, $sha );
			if ( $new_id > 0 ) {
				$sha_to_id[ $sha ] = $new_id;
				$imported++;
			} else {
				$skipped++;
				$skipped_assets[] = $rel_path . ' (import failed)';
			}
		}

		return array(
			'audit'     => array(
				'imported'        => $imported,
				'reused'          => $reused,
				'skipped'         => $skipped,
				'skipped_assets'  => $skipped_assets,
				'total_processed' => $imported + $reused + $skipped,
			),
			'sha_to_id' => $sha_to_id,
		);
	}

	/**
	 * @param array<string,true> $asset_paths Asset accumulator.
	 * @param array<string,mixed> $product Product entry.
	 * @return void
	 */
	private function collect_assets( array &$asset_paths, array $product ) {
		if ( ! empty( $product['client_asset'] ) ) {
			$asset_paths[ $product['client_asset'] ] = true;
		}
		foreach ( (array) ( $product['gallery_assets'] ?? array() ) as $ga ) {
			if ( '' !== $ga ) {
				$asset_paths[ $ga ] = true;
			}
		}
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

		foreach ( (array) ( $manifest['products'] ?? array() ) as $product ) {
			try {
				$result = $this->reconcile_woo_product( $product, 'skincare', $sha_to_id );
				$audit[ $result['action'] ]++;
				if ( $result['woo_id'] > 0 ) {
					$audit['id_map'][ $product['id'] ] = $result['woo_id'];
				}
			} catch ( Throwable $e ) {
				/* Unresolved item must NOT fail the entire migration. */
				$audit['skipped']++;
				$audit['id_map'][ $product['id'] ] = 'SKIPPED:' . $e->getMessage();
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
		$new_concerns = (array) ( $tr_manifest['concern_terms']['phase3_new'] ?? array() );
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

		/* Update four existing path terms. */
		$page_manifest   = $this->load_json( 'treatment-page-media.json' );
		$stable_slugs    = array( 'acne-focus', 'brightening-focus', 'anti-aging-focus', 'skin-health-focus' );

		foreach ( (array) ( $page_manifest['paths'] ?? array() ) as $path_def ) {
			$slug = sanitize_key( (string) ( $path_def['slug'] ?? '' ) );
			if ( ! in_array( $slug, $stable_slugs, true ) ) {
				/* Never create additional consultation paths. */
				continue;
			}

			$term = get_term_by( 'slug', $slug, Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY );
			if ( ! $term instanceof WP_Term ) {
				/* Path term not yet created — create it now. */
				$insert_result = wp_insert_term(
					sanitize_text_field( (string) $path_def['display_label'] ),
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
				'name' => sanitize_text_field( (string) $path_def['display_label'] ),
			) );

			/* Update baseline concerns term meta. */
			$concern_ids = array();
			foreach ( (array) ( $path_def['baseline_concern_slugs'] ?? array() ) as $c_slug ) {
				$c_term = get_term_by( 'slug', $c_slug, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY );
				if ( $c_term instanceof WP_Term ) {
					$concern_ids[] = (int) $c_term->term_id;
				}
			}
			update_term_meta( $term_id, Gloskin_Site_Core_Content_Service::PATH_META_BASELINE, $concern_ids );

			/* Update path image from SHA map. */
			$asset_rel = (string) ( $path_def['client_asset'] ?? '' );
			$attach_id = $this->resolve_attachment_from_asset( $asset_rel, $sha_to_id );
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

		foreach ( (array) ( $manifest['treatment_products'] ?? array() ) as $product ) {
			try {
				$result = $this->reconcile_woo_product( $product, 'treatment', $sha_to_id );
				$action = $result['action'];
				if ( isset( $audit[ $action ] ) ) {
					$audit[ $action ]++;
				}
				if ( $result['woo_id'] > 0 ) {
					$audit['id_map'][ $product['id'] ] = $result['woo_id'];
				}
			} catch ( Throwable $e ) {
				$audit['skipped']++;
				$audit['id_map'][ $product['id'] ] = 'SKIPPED:' . $e->getMessage();
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

		foreach ( (array) ( $manifest['treatment_records'] ?? array() ) as $record ) {
			try {
				$slug     = sanitize_title( (string) ( $record['slug'] ?? '' ) );
				$name     = sanitize_text_field( (string) ( $record['name'] ?? '' ) );
				$summary  = sanitize_text_field( (string) ( $record['summary'] ?? '' ) );
				$feature  = ! empty( $record['feature_on_home'] );
				$source   = sanitize_key( (string) ( $record['id'] ?? '' ) );

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
						'post_type'   => Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE,
						'post_title'  => $name,
						'post_name'   => $slug,
						'post_status' => 'publish',
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

				/* Set featured image from SHA map. */
				$asset_rel = (string) ( $record['client_asset'] ?? '' );
				$attach_id = $this->resolve_attachment_from_asset( $asset_rel, $sha_to_id );
				if ( $attach_id > 0 ) {
					set_post_thumbnail( $post_id, $attach_id );
				}

				$audit['id_map'][ $record['id'] ] = $post_id;

			} catch ( Throwable $e ) {
				$audit['skipped']++;
				$audit['id_map'][ $record['id'] ?? 'unknown' ] = 'SKIPPED:' . $e->getMessage();
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

		/* Bind Treatments page hero. */
		$hero_def   = $manifest['treatments_page_hero'] ?? array();
		$page_slug  = sanitize_key( (string) ( $hero_def['page_slug'] ?? 'treatments' ) );
		$meta_key   = sanitize_key( (string) ( $hero_def['meta_key'] ?? 'gloskin_hero_media_id' ) );
		$asset_rel  = (string) ( $hero_def['client_asset'] ?? '' );

		$treatments_page = Gloskin_Site_Core_Page_Lookup::find( $page_slug );
		$attach_id       = $this->resolve_attachment_from_asset( $asset_rel, $sha_to_id );

		if ( $treatments_page instanceof WP_Post && $attach_id > 0 ) {
			update_post_meta( $treatments_page->ID, $meta_key, $attach_id );
			$audit['hero_bound'] = true;
		} else {
			$audit['skipped']++;
		}

		/* Bind path term images (already updated labels/baseline in concerns_paths step,
		 * but ensure image binding here as well for resumability). */
		$stable_slugs = array( 'acne-focus', 'brightening-focus', 'anti-aging-focus', 'skin-health-focus' );
		foreach ( (array) ( $manifest['paths'] ?? array() ) as $path_def ) {
			$slug = sanitize_key( (string) ( $path_def['slug'] ?? '' ) );
			if ( ! in_array( $slug, $stable_slugs, true ) ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY );
			if ( ! $term instanceof WP_Term ) {
				$audit['skipped']++;
				continue;
			}
			$asset_rel = (string) ( $path_def['client_asset'] ?? '' );
			$pid       = $this->resolve_attachment_from_asset( $asset_rel, $sha_to_id );
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

		/* Verify skincare product count in audit. */
		$sk_audit = (array) ( $state['audit']['skincare'] ?? array() );
		$sk_reconciled = (int) ( $sk_audit['created'] ?? 0 ) + (int) ( $sk_audit['updated'] ?? 0 ) + (int) ( $sk_audit['reused'] ?? 0 );
		if ( $sk_reconciled < 1 ) {
			$errors[] = 'Skincare reconcile tidak menghasilkan produk yang diproses.';
		}

		/* Verify treatment product count in audit. */
		$tr_audit = (array) ( $state['audit']['treatment_products'] ?? array() );
		$tr_reconciled = (int) ( $tr_audit['created'] ?? 0 ) + (int) ( $tr_audit['updated'] ?? 0 ) + (int) ( $tr_audit['reused'] ?? 0 );
		if ( $tr_reconciled < 1 ) {
			$errors[] = 'Treatment product reconcile tidak menghasilkan produk yang diproses.';
		}

		/* Verify treatment record count. */
		$rec_audit  = (array) ( $state['audit']['treatment_records'] ?? array() );
		$rec_total  = (int) ( $rec_audit['created'] ?? 0 ) + (int) ( $rec_audit['updated'] ?? 0 ) + (int) ( $rec_audit['reused'] ?? 0 );
		if ( $rec_total < 1 ) {
			$errors[] = 'Treatment record reconcile tidak menghasilkan record yang diproses.';
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
		$source_id = sanitize_key( (string) ( $product['id'] ?? '' ) );
		$name      = sanitize_text_field( (string) ( $product['name'] ?? '' ) );
		$slug      = sanitize_title( (string) ( $product['slug'] ?? '' ) );
		$sku       = sanitize_text_field( (string) ( $product['sku'] ?? '' ) );
		$copy      = wp_kses_post( (string) ( $product['copy_short'] ?? '' ) );

		if ( '' === $name ) {
			throw new RuntimeException( 'Product name kosong untuk: ' . $source_id );
		}

		$action     = 'created';
		$woo_id     = 0;
		$superseded = false;

		/* 1. Provenance lookup. */
		$existing_id = $this->find_product_by_provenance( $source_id );

		/* 2. SKU lookup. */
		if ( ! $existing_id && '' !== $sku ) {
			$sku_id = function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( $sku ) : 0;
			if ( $sku_id > 0 ) {
				$existing_id = $sku_id;
			}
		}

		/* 3. Slug lookup. */
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
			$action      = $superseded ? 'created' : 'created';
		}

		/* Apply fields — NEVER mutate verified real SKU/price/stock. */
		$woo_product->set_name( $name );
		if ( '' !== $slug ) {
			$woo_product->set_slug( $slug );
		}
		if ( '' !== $copy ) {
			$woo_product->set_short_description( $copy );
		}

		/* Only set SKU if it's not already taken by another product. */
		if ( '' !== $sku ) {
			$sku_holder = function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( $sku ) : 0;
			if ( 0 === $sku_holder || $sku_holder === (int) $woo_product->get_id() ) {
				$woo_product->set_sku( $sku );
			}
		}

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

		/* Set featured image from SHA map. */
		$asset_rel = (string) ( $product['client_asset'] ?? '' );
		$attach_id = $this->resolve_attachment_from_asset( $asset_rel, $sha_to_id );
		if ( $attach_id > 0 ) {
			$woo_product = wc_get_product( $woo_id );
			if ( $woo_product ) {
				$woo_product->set_image_id( $attach_id );

				/* Set gallery. */
				$gallery_ids = array();
				foreach ( (array) ( $product['gallery_assets'] ?? array() ) as $ga ) {
					$ga_id = $this->resolve_attachment_from_asset( (string) $ga, $sha_to_id );
					if ( $ga_id > 0 ) {
						$gallery_ids[] = $ga_id;
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
	private function import_local_asset( $abs_path, $sha ) {
		$this->load_media_functions();

		$filename = basename( $abs_path );
		$upload   = wp_upload_bits( $filename, null, file_get_contents( $abs_path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local admin-only migration, bounded file
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$file_path = $upload['file'];
		$file_type = wp_check_filetype( basename( $file_path ), null );
		$attachment = array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $file_path, 0, true );
		if ( is_wp_error( $attach_id ) ) {
			return 0;
		}
		$attach_id = (int) $attach_id;

		if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
			$metadata = wp_generate_attachment_metadata( $attach_id, $file_path );
			if ( $metadata ) {
				wp_update_attachment_metadata( $attach_id, $metadata );
			}
		}

		update_post_meta( $attach_id, self::ATTACH_SHA256_META, $sha );
		update_post_meta( $attach_id, self::ATTACH_SOURCE_META, 'gloskin-phase3-v1' );

		return $attach_id;
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

		$abs_path = $this->asset_path( $rel_path );

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

	/* -----------------------------------------------------------------
	 * STATE / LOCK
	 * ----------------------------------------------------------------- */

	/** @return array<string,mixed> */
	private function default_state() {
		return array(
			'status'               => 'pending',
			'next_step_index'      => 0,
			'current_step'        => 'Siap dijalankan',
			'manifest_fingerprint' => '',
			'sha_to_id'            => array(),
			'audit'                => array(
				'inventory'          => array(),
				'dry_run'            => array(),
				'media'              => array(),
				'skincare'           => array(),
				'concerns_paths'     => array(),
				'treatment_products' => array(),
				'treatment_records'  => array(),
				'page_media'         => array(),
			),
			'last_error'          => '',
			'updated_at'          => 0,
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
		$processed = min( $total, (int) ( $state['next_step_index'] ?? 0 ) );
		$state['progress_percent'] = 'complete' === $state['status']
			? 100
			: (int) floor( ( $processed / max( 1, $total ) ) * 100 );
		$state['total_steps'] = $total;
		return $state;
	}
}
// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
