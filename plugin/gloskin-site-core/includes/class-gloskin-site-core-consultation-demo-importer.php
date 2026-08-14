<?php
/**
 * One-shot, deterministic, idempotent staging/demo bundle for the Treatment
 * Consultation feature (docs/task-treatment-consultation-commerce-discovery.md
 * sections 11-12). A small non-service helper, not a bootable service and
 * not registered in Kernel -- AdminService owns the one admin-post trigger
 * and screen this is invoked from. WooCommerce remains the sole commerce
 * authority: the 8 demo products this creates are ordinary Woo simple
 * virtual products, never a shadow catalog.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Consultation_Demo_Importer {
	const STATE_OPTION  = 'gloskin_site_core_consultation_demo';
	const BUNDLE_ID     = 'gloskin-treatment-consultation-demo-v1';
	const SOURCE_PREFIX = 'gloskin-consultation-demo:v1:';
	const NOTICE        = 'Synthetic staging/demo consultation bundle — not verified commercial product or clinical truth.';

	/**
	 * @return array{status:string,processed:int,paths:int,concerns:int,questions:int,products:int,last_error:string}
	 */
	public static function state() {
		$defaults = array(
			'status'     => 'pending',
			'processed'  => 0,
			'paths'      => 0,
			'concerns'   => 0,
			'questions'  => 0,
			'products'   => 0,
			'last_error' => '',
		);
		$stored = get_option( self::STATE_OPTION, array() );
		return array_merge( $defaults, is_array( $stored ) ? $stored : array() );
	}

	/**
	 * wp_get_environment_type() defaults to 'production' whenever the host
	 * has not explicitly configured WP_ENVIRONMENT_TYPE -- the safe default
	 * refuses import unless an environment has been explicitly declared
	 * local/development/staging (section 12.1's hard requirement).
	 *
	 * @return bool
	 */
	public static function is_environment_allowed() {
		$type = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		return in_array( $type, array( 'local', 'development', 'staging' ), true );
	}

	/**
	 * @return void
	 * @throws RuntimeException On environment refusal or a real data error.
	 */
	public static function run() {
		if ( ! self::is_environment_allowed() ) {
			throw new RuntimeException( 'Import demo konsultasi ditolak: environment ini bukan local/development/staging.' );
		}
		$state = self::state();
		if ( 'consumed' === $state['status'] ) {
			throw new RuntimeException( 'Bundle demo konsultasi sudah dikonsumsi.' );
		}
		if ( ! function_exists( 'wc_get_products' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			throw new RuntimeException( 'WooCommerce tidak aktif atau belum siap.' );
		}
		if ( ! taxonomy_exists( Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY )
			|| ! taxonomy_exists( Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY )
			|| ! taxonomy_exists( Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY )
			|| ! post_type_exists( Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE ) ) {
			throw new RuntimeException( 'Skema konsultasi belum terdaftar; muat ulang halaman lalu coba lagi.' );
		}

		try {
			Gloskin_Site_Core_Content_Service::ensure_family_terms();

			$path_ids    = self::upsert_paths();
			$concern_ids = self::upsert_concerns();
			$question_count = self::upsert_questions( $path_ids, $concern_ids );
			$product_count  = self::upsert_products( $concern_ids );

			self::verify( $path_ids, $concern_ids, $question_count, $product_count );

			update_option(
				self::STATE_OPTION,
				array(
					'status'     => 'consumed',
					'processed'  => count( $path_ids ) + count( $concern_ids ) + $question_count + $product_count,
					'paths'      => count( $path_ids ),
					'concerns'   => count( $concern_ids ),
					'questions'  => $question_count,
					'products'   => $product_count,
					'last_error' => '',
				),
				false
			);
		} catch ( Throwable $error ) {
			update_option(
				self::STATE_OPTION,
				array_merge( self::state(), array( 'status' => 'pending', 'last_error' => $error->getMessage() ) ),
				false
			);
			throw $error;
		}
	}

	/* -----------------------------------------------------------------
	 * Phase data (section 11). Deterministic slugs/source IDs throughout
	 * so a rerun after partial failure converges instead of duplicating.
	 * ----------------------------------------------------------------- */

	/** @return array<string,array{label:string,concerns:array<int,string>}> */
	private static function path_definitions() {
		return array(
			'acne-focus' => array(
				'label'    => 'Jerawat',
				'concerns' => array( 'active-acne', 'acne-marks', 'large-pores', 'oily-skin' ),
			),
			'brightening-focus' => array(
				'label'    => 'Brightening',
				'concerns' => array( 'dullness', 'pigmentation', 'uneven-texture' ),
			),
			'anti-aging-focus' => array(
				'label'    => 'Anti-Aging',
				'concerns' => array( 'fine-lines', 'uneven-texture', 'dry-dehydrated' ),
			),
			'skin-health-focus' => array(
				'label'    => 'Skin Health',
				'concerns' => array( 'dry-dehydrated', 'sensitivity-redness', 'oily-skin', 'large-pores' ),
			),
		);
	}

	/** @return array<string,string> slug => label */
	private static function concern_definitions() {
		return array(
			'active-acne'         => 'Jerawat Aktif',
			'acne-marks'          => 'Bekas Jerawat',
			'dullness'            => 'Kulit Kusam',
			'pigmentation'        => 'Flek & Pigmentasi',
			'large-pores'         => 'Pori Besar',
			'oily-skin'           => 'Kulit Berminyak',
			'dry-dehydrated'      => 'Kulit Kering / Dehidrasi',
			'sensitivity-redness' => 'Sensitif / Kemerahan',
			'fine-lines'          => 'Garis Halus',
			'uneven-texture'      => 'Tekstur Tidak Merata',
		);
	}

	/**
	 * 13 questions, each with 2-6 answer options mapping to demo concern
	 * slugs with weight 1..3. A neutral option (empty concerns array)
	 * contributes zero score, matching section 11.3.
	 *
	 * @return array<int,array{slug:string,title:string,path:string,answers:array<int,array{label:string,concern:string,weight:int}>}>
	 */
	private static function question_definitions() {
		return array(
			array(
				'slug'  => 'fokus-utama',
				'title' => 'Apa keluhan utama yang paling ingin Anda fokuskan?',
				'path'  => '',
				'answers' => array(
					array( 'label' => 'Jerawat & bekasnya', 'concern' => 'active-acne', 'weight' => 3 ),
					array( 'label' => 'Kulit kusam & flek', 'concern' => 'dullness', 'weight' => 3 ),
					array( 'label' => 'Tanda penuaan', 'concern' => 'fine-lines', 'weight' => 3 ),
					array( 'label' => 'Kesehatan kulit secara umum', 'concern' => 'sensitivity-redness', 'weight' => 2 ),
				),
			),
			array(
				'slug'  => 'kondisi-minyak',
				'title' => 'Bagaimana kondisi minyak kulit Anda sepanjang hari?',
				'path'  => 'acne-focus',
				'answers' => array(
					array( 'label' => 'Sangat berminyak', 'concern' => 'oily-skin', 'weight' => 3 ),
					array( 'label' => 'Berminyak di area T-zone', 'concern' => 'oily-skin', 'weight' => 2 ),
					array( 'label' => 'Cenderung normal', 'concern' => '', 'weight' => 1 ),
					array( 'label' => 'Cenderung kering', 'concern' => 'dry-dehydrated', 'weight' => 2 ),
				),
			),
			array(
				'slug'  => 'komedo-pori',
				'title' => 'Seberapa sering komedo atau sumbatan pori menjadi perhatian?',
				'path'  => 'acne-focus',
				'answers' => array(
					array( 'label' => 'Sangat sering', 'concern' => 'large-pores', 'weight' => 3 ),
					array( 'label' => 'Kadang-kadang', 'concern' => 'large-pores', 'weight' => 2 ),
					array( 'label' => 'Jarang', 'concern' => '', 'weight' => 1 ),
				),
			),
			array(
				'slug'  => 'jerawat-aktif',
				'title' => 'Seberapa sering jerawat aktif muncul?',
				'path'  => 'acne-focus',
				'answers' => array(
					array( 'label' => 'Hampir selalu ada', 'concern' => 'active-acne', 'weight' => 3 ),
					array( 'label' => 'Muncul sesekali', 'concern' => 'active-acne', 'weight' => 2 ),
					array( 'label' => 'Sangat jarang', 'concern' => '', 'weight' => 1 ),
				),
			),
			array(
				'slug'  => 'bekas-jerawat',
				'title' => 'Seberapa besar bekas jerawat menjadi perhatian Anda?',
				'path'  => 'acne-focus',
				'answers' => array(
					array( 'label' => 'Sangat mengganggu', 'concern' => 'acne-marks', 'weight' => 3 ),
					array( 'label' => 'Cukup terlihat', 'concern' => 'acne-marks', 'weight' => 2 ),
					array( 'label' => 'Tidak terlalu', 'concern' => '', 'weight' => 1 ),
				),
			),
			array(
				'slug'  => 'kecerahan-kulit',
				'title' => 'Bagaimana tampilan kecerahan warna kulit Anda saat ini?',
				'path'  => 'brightening-focus',
				'answers' => array(
					array( 'label' => 'Terasa kusam', 'concern' => 'dullness', 'weight' => 3 ),
					array( 'label' => 'Kadang kusam', 'concern' => 'dullness', 'weight' => 2 ),
					array( 'label' => 'Cukup cerah', 'concern' => '', 'weight' => 1 ),
				),
			),
			array(
				'slug'  => 'flek-noda',
				'title' => 'Apakah flek atau noda gelap menjadi perhatian?',
				'path'  => 'brightening-focus',
				'answers' => array(
					array( 'label' => 'Ya, cukup terlihat', 'concern' => 'pigmentation', 'weight' => 3 ),
					array( 'label' => 'Ada sedikit', 'concern' => 'pigmentation', 'weight' => 2 ),
					array( 'label' => 'Tidak ada', 'concern' => '', 'weight' => 1 ),
				),
			),
			array(
				'slug'  => 'tekstur-kulit',
				'title' => 'Bagaimana kondisi tekstur permukaan kulit Anda?',
				'path'  => 'brightening-focus',
				'answers' => array(
					array( 'label' => 'Tidak merata / kasar', 'concern' => 'uneven-texture', 'weight' => 3 ),
					array( 'label' => 'Sedikit tidak merata', 'concern' => 'uneven-texture', 'weight' => 2 ),
					array( 'label' => 'Cukup halus', 'concern' => '', 'weight' => 1 ),
				),
			),
			array(
				'slug'  => 'ukuran-pori',
				'title' => 'Seberapa terlihat pori-pori pada area wajah Anda?',
				'path'  => 'skin-health-focus',
				'answers' => array(
					array( 'label' => 'Sangat terlihat', 'concern' => 'large-pores', 'weight' => 3 ),
					array( 'label' => 'Cukup terlihat', 'concern' => 'large-pores', 'weight' => 2 ),
					array( 'label' => 'Hampir tidak terlihat', 'concern' => '', 'weight' => 1 ),
				),
			),
			array(
				'slug'  => 'kering-tertarik',
				'title' => 'Seberapa sering kulit terasa kering atau tertarik?',
				'path'  => 'skin-health-focus',
				'answers' => array(
					array( 'label' => 'Sering', 'concern' => 'dry-dehydrated', 'weight' => 3 ),
					array( 'label' => 'Kadang-kadang', 'concern' => 'dry-dehydrated', 'weight' => 2 ),
					array( 'label' => 'Jarang', 'concern' => '', 'weight' => 1 ),
				),
			),
			array(
				'slug'  => 'sensitif-kemerahan',
				'title' => 'Seberapa mudah kulit terasa sensitif atau tampak kemerahan?',
				'path'  => 'skin-health-focus',
				'answers' => array(
					array( 'label' => 'Sangat mudah', 'concern' => 'sensitivity-redness', 'weight' => 3 ),
					array( 'label' => 'Kadang-kadang', 'concern' => 'sensitivity-redness', 'weight' => 2 ),
					array( 'label' => 'Jarang', 'concern' => '', 'weight' => 1 ),
				),
			),
			array(
				'slug'  => 'garis-halus',
				'title' => 'Apakah garis halus menjadi perhatian Anda saat ini?',
				'path'  => 'anti-aging-focus',
				'answers' => array(
					array( 'label' => 'Ya, cukup terlihat', 'concern' => 'fine-lines', 'weight' => 3 ),
					array( 'label' => 'Mulai terlihat', 'concern' => 'fine-lines', 'weight' => 2 ),
					array( 'label' => 'Belum terlihat', 'concern' => '', 'weight' => 1 ),
				),
			),
			array(
				'slug'  => 'hasil-prioritas',
				'title' => 'Hasil perawatan apa yang paling ingin Anda prioritaskan?',
				'path'  => '',
				'answers' => array(
					array( 'label' => 'Kulit bersih dari jerawat', 'concern' => 'active-acne', 'weight' => 2 ),
					array( 'label' => 'Warna kulit lebih cerah merata', 'concern' => 'dullness', 'weight' => 2 ),
					array( 'label' => 'Kulit terasa lebih kenyal & halus', 'concern' => 'fine-lines', 'weight' => 2 ),
					array( 'label' => 'Kulit terasa lebih nyaman & sehat', 'concern' => 'sensitivity-redness', 'weight' => 2 ),
				),
			),
		);
	}

	/**
	 * @return array<int,array{sku:string,name:string,concerns:array<int,string>,price:string}>
	 */
	private static function product_definitions() {
		return array(
			array( 'sku' => 'GLS-TRT-001', 'name' => 'Acne Clarifying Facial', 'concerns' => array( 'active-acne', 'oily-skin' ), 'price' => '350000' ),
			array( 'sku' => 'GLS-TRT-002', 'name' => 'Deep Pore Facial', 'concerns' => array( 'large-pores', 'oily-skin', 'uneven-texture' ), 'price' => '380000' ),
			array( 'sku' => 'GLS-TRT-003', 'name' => 'Brightening Facial', 'concerns' => array( 'dullness', 'pigmentation' ), 'price' => '420000' ),
			array( 'sku' => 'GLS-TRT-004', 'name' => 'Pigmentation Care Treatment', 'concerns' => array( 'pigmentation', 'dullness' ), 'price' => '480000' ),
			array( 'sku' => 'GLS-TRT-005', 'name' => 'Rejuvenation Facial', 'concerns' => array( 'fine-lines', 'uneven-texture' ), 'price' => '550000' ),
			array( 'sku' => 'GLS-TRT-006', 'name' => 'Hydration Booster', 'concerns' => array( 'dry-dehydrated', 'sensitivity-redness' ), 'price' => '400000' ),
			array( 'sku' => 'GLS-TRT-007', 'name' => 'Skin Barrier Therapy', 'concerns' => array( 'sensitivity-redness', 'dry-dehydrated' ), 'price' => '460000' ),
			array( 'sku' => 'GLS-TRT-008', 'name' => 'Texture Renewal Treatment', 'concerns' => array( 'uneven-texture', 'acne-marks', 'large-pores' ), 'price' => '500000' ),
		);
	}

	/* -----------------------------------------------------------------
	 * Upsert phases (section 12.3). Each phase is idempotent on its own --
	 * a rerun after a partial failure converges rather than duplicating.
	 * ----------------------------------------------------------------- */

	/** @return array<string,int> slug => term_id */
	private static function upsert_paths() {
		$ids   = array();
		$order = 0;
		foreach ( self::path_definitions() as $slug => $definition ) {
			$order++;
			$term = get_term_by( 'slug', $slug, Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY );
			if ( $term instanceof WP_Term ) {
				$term_id = (int) $term->term_id;
			} else {
				$inserted = wp_insert_term( $definition['label'], Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY, array( 'slug' => $slug ) );
				if ( is_wp_error( $inserted ) ) {
					throw new RuntimeException( 'Gagal membuat jalur konsultasi: ' . $slug );
				}
				$term_id = (int) $inserted['term_id'];
			}
			update_term_meta( $term_id, 'gloskin_demo_source', self::SOURCE_PREFIX . 'path:' . $slug );
			update_term_meta( $term_id, Gloskin_Site_Core_Content_Service::PATH_META_ORDER, $order );
			$ids[ $slug ] = $term_id;
		}
		/* Baseline concern IDs need concern term IDs, which are only
		 * assigned in upsert_concerns() -- resolved by the caller after
		 * both phases have run (see upsert_questions()' use of $ids). */
		return $ids;
	}

	/** @return array<string,int> slug => term_id */
	private static function upsert_concerns() {
		$ids = array();
		foreach ( self::concern_definitions() as $slug => $label ) {
			$term = get_term_by( 'slug', $slug, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY );
			if ( $term instanceof WP_Term ) {
				$term_id = (int) $term->term_id;
			} else {
				$inserted = wp_insert_term( $label, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, array( 'slug' => $slug ) );
				if ( is_wp_error( $inserted ) ) {
					throw new RuntimeException( 'Gagal membuat keluhan: ' . $slug );
				}
				$term_id = (int) $inserted['term_id'];
			}
			update_term_meta( $term_id, 'gloskin_demo_source', self::SOURCE_PREFIX . 'concern:' . $slug );
			$ids[ $slug ] = $term_id;
		}
		return $ids;
	}

	/**
	 * @param array<string,int> $path_ids Path slug => term_id.
	 * @param array<string,int> $concern_ids Concern slug => term_id.
	 * @return int Published question count created/verified.
	 */
	private static function upsert_questions( array $path_ids, array $concern_ids ) {
		/* Baseline concerns per path, resolved now that concern term IDs
		 * exist. */
		foreach ( self::path_definitions() as $slug => $definition ) {
			if ( ! isset( $path_ids[ $slug ] ) ) {
				continue;
			}
			$baseline = array();
			foreach ( $definition['concerns'] as $concern_slug ) {
				if ( isset( $concern_ids[ $concern_slug ] ) ) {
					$baseline[] = $concern_ids[ $concern_slug ];
				}
			}
			update_term_meta( $path_ids[ $slug ], Gloskin_Site_Core_Content_Service::PATH_META_BASELINE, $baseline );
		}

		$count = 0;
		foreach ( self::question_definitions() as $definition ) {
			$source_id = self::SOURCE_PREFIX . 'question:' . $definition['slug'];
			$existing  = get_posts(
				array(
					'post_type'      => Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE,
					'post_status'    => array( 'publish', 'draft' ),
					'meta_key'       => 'gloskin_demo_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded (13-row) demo dataset lookup, not a live catalog query.
					'meta_value'     => $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( $existing ) {
				$post_id = (int) $existing[0];
				wp_update_post( array( 'ID' => $post_id, 'post_title' => $definition['title'], 'post_status' => 'publish' ) );
			} else {
				$post_id = wp_insert_post(
					array(
						'post_type'   => Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE,
						'post_title'  => $definition['title'],
						'post_status' => 'publish',
					),
					true
				);
				if ( is_wp_error( $post_id ) ) {
					throw new RuntimeException( 'Gagal membuat pertanyaan: ' . $definition['slug'] );
				}
				$post_id = (int) $post_id;
			}
			update_post_meta( $post_id, 'gloskin_demo_source', $source_id );

			$answers = array();
			foreach ( $definition['answers'] as $answer ) {
				$concern_id = '' !== $answer['concern'] && isset( $concern_ids[ $answer['concern'] ] ) ? $concern_ids[ $answer['concern'] ] : 0;
				if ( ! $concern_id ) {
					continue; // Neutral option: contributes zero score, per section 11.3.
				}
				$answers[] = array( 'label' => $answer['label'], 'concern_id' => $concern_id, 'weight' => $answer['weight'] );
			}
			update_post_meta( $post_id, Gloskin_Site_Core_Content_Service::ANSWER_META_KEY, $answers );

			if ( '' !== $definition['path'] && isset( $path_ids[ $definition['path'] ] ) ) {
				wp_set_object_terms( $post_id, array( $path_ids[ $definition['path'] ] ), Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY, false );
			}
			$count++;
		}
		return $count;
	}

	/**
	 * Woo simple virtual products (section 11.4/9). catalog_visibility is
	 * set to 'hidden' so the demo catalog does not pollute the regular
	 * Shop -- purchasable via the consultation grid/PDP, per section 9.
	 * SKU is the deterministic collision check: an existing product with
	 * the same SKU that is NOT this importer's own (no gloskin_demo_source
	 * meta match) stops the run with a clear error rather than silently
	 * taking it over.
	 *
	 * @param array<string,int> $concern_ids Concern slug => term_id.
	 * @return int Product count created/verified.
	 */
	private static function upsert_products( array $concern_ids ) {
		$count = 0;
		foreach ( self::product_definitions() as $definition ) {
			$source_id = self::SOURCE_PREFIX . 'product:' . $definition['sku'];
			$existing_id = function_exists( 'wc_get_product_id_by_sku' ) ? wc_get_product_id_by_sku( $definition['sku'] ) : 0;

			if ( $existing_id ) {
				$owner = get_post_meta( $existing_id, 'gloskin_demo_source', true );
				if ( $owner !== $source_id ) {
					throw new RuntimeException( 'SKU produk bentrok dengan data non-demo: ' . $definition['sku'] );
				}
				$product = wc_get_product( $existing_id );
			} else {
				$product = new WC_Product_Simple();
			}
			if ( ! $product instanceof WC_Product ) {
				throw new RuntimeException( 'Gagal memuat/membuat produk perawatan: ' . $definition['sku'] );
			}

			$product->set_name( $definition['name'] );
			$product->set_sku( $definition['sku'] );
			$product->set_regular_price( $definition['price'] );
			$product->set_virtual( true );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_short_description( self::NOTICE . ' Contoh perawatan demo untuk keperluan staging.' );
			$product->set_description( self::NOTICE . ' Produk ini adalah data sintetis untuk mendemonstrasikan alur konsultasi dan tidak merepresentasikan harga atau ketersediaan aktual.' );
			$product_id = $product->save();
			if ( ! $product_id ) {
				throw new RuntimeException( 'Gagal menyimpan produk perawatan: ' . $definition['sku'] );
			}

			update_post_meta( $product_id, 'gloskin_demo_source', $source_id );
			wp_set_object_terms( $product_id, Gloskin_Site_Core_Content_Service::FAMILY_TREATMENT, Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY, false );

			$mapped_concerns = array();
			foreach ( $definition['concerns'] as $slug ) {
				if ( isset( $concern_ids[ $slug ] ) ) {
					$mapped_concerns[] = $concern_ids[ $slug ];
				}
			}
			wp_set_object_terms( $product_id, $mapped_concerns, Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY, false );
			$count++;
		}
		return $count;
	}

	/**
	 * Phase 9 (section 12.3): verify expected counts/relationships/
	 * purchasability before marking the bundle consumed. A verification
	 * failure leaves the run's status 'pending' (not 'consumed'), so a
	 * retry is still possible.
	 *
	 * @param array<string,int> $path_ids Path term IDs.
	 * @param array<string,int> $concern_ids Concern term IDs.
	 * @param int                $question_count Questions created.
	 * @param int                $product_count Products created.
	 * @return void
	 * @throws RuntimeException On any verification mismatch.
	 */
	private static function verify( array $path_ids, array $concern_ids, $question_count, $product_count ) {
		if ( 4 !== count( $path_ids ) ) {
			throw new RuntimeException( 'Verifikasi gagal: jumlah jalur konsultasi tidak sesuai.' );
		}
		if ( count( $concern_ids ) < 10 ) {
			throw new RuntimeException( 'Verifikasi gagal: jumlah keluhan kurang dari 10.' );
		}
		if ( $question_count < Gloskin_Site_Core_Content_Service::QUESTION_MIN_PUBLISHED ) {
			throw new RuntimeException( 'Verifikasi gagal: jumlah pertanyaan terpublikasi kurang dari ' . Gloskin_Site_Core_Content_Service::QUESTION_MIN_PUBLISHED . '.' );
		}
		if ( 8 !== $product_count ) {
			throw new RuntimeException( 'Verifikasi gagal: jumlah produk perawatan tidak sesuai.' );
		}
		foreach ( self::product_definitions() as $definition ) {
			$product_id = function_exists( 'wc_get_product_id_by_sku' ) ? wc_get_product_id_by_sku( $definition['sku'] ) : 0;
			$product    = $product_id ? wc_get_product( $product_id ) : null;
			if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
				throw new RuntimeException( 'Verifikasi gagal: produk tidak purchasable: ' . $definition['sku'] );
			}
		}
	}
}
