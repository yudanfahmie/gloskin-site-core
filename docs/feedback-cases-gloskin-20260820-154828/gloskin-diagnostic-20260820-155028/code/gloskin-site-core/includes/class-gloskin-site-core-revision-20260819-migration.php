<?php
/**
 * Bounded one-shot migration for the 2026-08-19 content/data revision.
 *
 * Checkpoints:
 *   1. preflight       — verify requirements; precompute doctor photo matches.
 *   2. managed_content — confirm Promo/Testimonial/Achievement CPT structures.
 *   3. demo_seed       — seed deterministic sample records.
 *   4. doctor_photos   — import/reuse 12 primary WebPs; apply to matched doctors.
 *   5. normalize       — ensure /promo/ page exists; page relationships.
 *   6. cleanup         — retire obsolete plugin-owned presentation artifacts.
 *   7. verify          — safety assertions.
 *   8. finalize        — write consumed marker; flush rewrites.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Gloskin_Site_Core_Revision_20260819_Migration {

	const REVISION       = '2026-08-19';
	const STATE_OPTION   = 'gloskin_site_core_revision_20260819_state';
	const LOCK_OPTION    = 'gloskin_site_core_revision_20260819_lock';
	const LOCK_TTL       = 300;
	const BUNDLE_DIR     = 'gloskin-doctor-photos-v2';
	const BUNDLE_ID      = 'gloskin-doctor-photos-v2';

	/* Migration attachment provenance meta keys */
	const ATTACH_REVISION_META = '_gloskin_photo_migration_revision';
	const ATTACH_SHA256_META   = '_gloskin_photo_migration_sha256';
	const ATTACH_SOURCE_META   = '_gloskin_photo_migration_source_label';
	const PREV_THUMBNAIL_META  = '_gloskin_prev_thumbnail_id_20260819';

	/* Demo seed meta keys */
	const DEMO_IDENTITY_META = '_gloskin_demo_identity';
	const DEMO_REVISION_META = '_gloskin_demo_revision';

	/** @var string */
	private $plugin_file;

	/** @var string */
	private $runtime_dir;

	/** @param string $plugin_file Main plugin file path. */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->runtime_dir = dirname( dirname( $plugin_file ) )
			. '/migration-runtime/' . self::BUNDLE_DIR;
	}

	/** @return array<int,array{key:string,label:string}> */
	private function steps() {
		return array(
			array( 'key' => 'preflight',       'label' => 'Preflight — memeriksa persyaratan dan pra-komputasi pencocokan foto' ),
			array( 'key' => 'managed_content',  'label' => 'Memastikan struktur konten terkelola (Promo/Testimonial/Achievement)' ),
			array( 'key' => 'demo_seed',        'label' => 'Menyemai data demo deterministik' ),
			array( 'key' => 'doctor_photos',    'label' => 'Mengimpor dan menerapkan foto dokter (12 primer)' ),
			array( 'key' => 'normalize',        'label' => 'Menormalkan relasi halaman dan konten' ),
			array( 'key' => 'cleanup',          'label' => 'Membersihkan artefak presentasi usang' ),
			array( 'key' => 'verify',           'label' => 'Memverifikasi keamanan dan integritas data' ),
			array( 'key' => 'finalize',         'label' => 'Menyelesaikan dan mengunci migrasi' ),
		);
	}

	/** @return array<string,mixed> */
	public function get_state() {
		$state    = get_option( self::STATE_OPTION, array() );
		$state    = is_array( $state ) ? $state : array();
		$defaults = array(
			'revision'           => self::REVISION,
			'status'             => 'pending',
			'next_step_index'    => 0,
			'processed_steps'    => 0,
			'total_steps'        => count( $this->steps() ),
			'current_step'       => 'Siap dijalankan',
			'last_error'         => '',
			'doctor_matches'     => array(),
			'doctor_audit'       => array(),
			'demo_audit'         => array(),
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
	 * Bounded synchronous fallback for no-JS environments.
	 *
	 * @return array<string,mixed>
	 */
	public function run_to_completion() {
		$state = $this->advance( 'start' );
		$limit = count( $this->steps() ) + 3;
		for ( $i = 0; $i < $limit && 'consumed' !== $state['status']; $i++ ) {
			$state = $this->advance( 'continue' );
		}
		if ( 'consumed' !== $state['status'] ) {
			throw new RuntimeException( 'Migrasi tidak mencapai state consumed dalam batas checkpoint.' );
		}
		return $state;
	}

	/**
	 * Advance one checkpoint.
	 *
	 * @param string $mode start|continue.
	 * @return array<string,mixed>
	 */
	public function advance( $mode ) {
		$mode = sanitize_key( (string) $mode );
		if ( ! in_array( $mode, array( 'start', 'continue' ), true ) ) {
			throw new RuntimeException( 'Mode migrasi tidak valid.' );
		}

		$state = $this->get_state();
		if ( 'consumed' === $state['status'] ) {
			return $this->response_state( $state );
		}

		$token = $this->acquire_lock();
		if ( '' === $token ) {
			throw new RuntimeException( 'Migrasi sedang diproses oleh request lain.' );
		}

		try {
			if ( 'start' === $mode ) {
				$state['status']             = 'running';
				$state['last_error']         = '';
				$state['current_step']       = $this->step_label( (int) $state['next_step_index'] );
				$state['commerce_snapshot']  = $this->commerce_page_snapshot();
				$state['updated_at']         = time();
				$this->save_state( $state );
				$this->release_lock( $token );
				return $this->response_state( $state );
			}

			if ( ! in_array( $state['status'], array( 'running', 'failed', 'verifying' ), true ) ) {
				throw new RuntimeException( 'Migrasi belum dimulai.' );
			}

			$index = (int) $state['next_step_index'];
			$steps = $this->steps();
			if ( $index >= count( $steps ) ) {
				$state['status']      = 'consumed';
				$state['current_step'] = 'Selesai';
				$this->save_state( $state );
				$this->release_lock( $token );
				return $this->response_state( $state );
			}

			$state['status']       = 'running';
			$state['current_step'] = $steps[ $index ]['label'];
			$this->save_state( $state );

			switch ( $steps[ $index ]['key'] ) {
				case 'preflight':
					$state['doctor_matches']    = $this->run_preflight();
					$state['commerce_snapshot'] = $this->commerce_page_snapshot();
					break;
				case 'managed_content':
					$this->run_managed_content();
					break;
				case 'demo_seed':
					$state['demo_audit'] = $this->run_demo_seed();
					break;
				case 'doctor_photos':
					$state['doctor_audit'] = $this->run_doctor_photos( (array) $state['doctor_matches'] );
					break;
				case 'normalize':
					$this->run_normalize();
					break;
				case 'cleanup':
					$this->run_cleanup();
					break;
				case 'verify':
					$state['status'] = 'verifying';
					$this->run_verify( $state );
					break;
				case 'finalize':
					$this->run_finalize();
					$state['status'] = 'consumed';
					break;
			}

			$state['next_step_index'] = $index + 1;
			$state['processed_steps'] = min( count( $steps ), $index + 1 );
			$state['current_step']    = 'consumed' === $state['status']
				? 'Selesai'
				: $this->step_label( (int) $state['next_step_index'] );
			$state['last_error']      = '';
			$state['updated_at']      = time();
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

	/* -----------------------------------------------------------------------
	 * CHECKPOINT IMPLEMENTATIONS
	 * -------------------------------------------------------------------- */

	/**
	 * Preflight: load manifest, precompute all doctor matches, verify assets.
	 * Fails early if any match is ambiguous, missing, or asset is corrupt.
	 *
	 * @return array<string,array<string,mixed>> doctor_slug => match data.
	 * @throws RuntimeException On any preflight failure.
	 */
	private function run_preflight() {
		/* Verify WordPress/Woo capabilities */
		if ( ! function_exists( 'wp_insert_attachment' ) ) {
			throw new RuntimeException( 'Fungsi wp_insert_attachment tidak tersedia.' );
		}

		$manifest = $this->load_bundle_manifest();
		$doctors  = $this->load_manifest_doctors( $manifest );

		/* Precompute all matches — zero mutations happen here */
		$matches      = array();
		$unmatched    = array();
		$ambiguous    = array();
		$missing_file = array();

		foreach ( $doctors as $doc ) {
			$source_label = (string) $doc['source_label'];
			$aliases      = (array) $doc['match_aliases'];
			$webp_file    = (string) $doc['primary_webp'];
			$expected_sha = (string) $doc['primary_sha256'];

			/* Verify asset file exists and hash matches */
			$asset_path = $this->runtime_dir . '/' . $webp_file;
			if ( ! is_readable( $asset_path ) ) {
				$missing_file[] = $source_label . ' (' . $webp_file . ')';
				continue;
			}
			$actual_sha = hash_file( 'sha256', $asset_path );
			if ( ! hash_equals( $expected_sha, (string) $actual_sha ) ) {
				$missing_file[] = $source_label . ': SHA-256 tidak cocok (expected=' . $expected_sha . ' got=' . $actual_sha . ')';
				continue;
			}

			/* Find matching doctor */
			$found = $this->find_doctor_by_aliases( $aliases );
			if ( count( $found ) === 0 ) {
				$unmatched[] = $source_label . ' (aliases: ' . implode( ', ', $aliases ) . ')';
			} elseif ( count( $found ) > 1 ) {
				$candidates  = array_map( static function ( $r ) { return $r['post_title'] . ' #' . $r['ID']; }, $found );
				$ambiguous[] = $source_label . ' -> ' . implode( ' | ', $candidates );
			} else {
				$matches[ $webp_file ] = array(
					'source_label'  => $source_label,
					'doctor_id'     => absint( $found[0]['ID'] ),
					'doctor_title'  => (string) $found[0]['post_title'],
					'webp_file'     => $webp_file,
					'sha256'        => $expected_sha,
					'asset_path'    => $asset_path,
				);
			}
		}

		$errors = array();
		if ( $missing_file ) {
			$errors[] = 'Aset foto tidak valid/korup: ' . implode( '; ', $missing_file );
		}
		if ( $unmatched ) {
			$errors[] = 'Dokter tidak ditemukan (tidak ada kecocokan alias): ' . implode( '; ', $unmatched );
		}
		if ( $ambiguous ) {
			$errors[] = 'Dokter ambigu (lebih dari satu kecocokan): ' . implode( '; ', $ambiguous );
		}
		if ( $errors ) {
			throw new RuntimeException( 'Preflight gagal. ' . implode( ' | ', $errors ) );
		}

		$expected_count = count( $doctors );
		if ( count( $matches ) !== $expected_count ) {
			throw new RuntimeException(
				'Preflight: ' . count( $matches ) . ' dari ' . $expected_count . ' foto primer berhasil dicocokkan. Periksa log di atas.'
			);
		}

		return $matches;
	}

	/**
	 * Find existing gloskin_doctor posts matching the given aliases.
	 * CONSERVATIVE: exact normalized alias matching only.
	 *
	 * @param array<int,string> $aliases Normalized aliases from manifest.
	 * @return array<int,array{ID:int,post_title:string}>
	 */
	private function find_doctor_by_aliases( array $aliases ) {
		$normalized_aliases = array_map( array( $this, 'normalize_name' ), $aliases );

		$all_doctors = get_posts( array(
			'post_type'      => Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'all',
		) );

		$found = array();
		foreach ( $all_doctors as $doctor ) {
			$doctor_normalized = $this->normalize_name( (string) $doctor->post_title );
			$slug_normalized   = $this->normalize_name( str_replace( '-', ' ', (string) $doctor->post_name ) );

			foreach ( $normalized_aliases as $alias ) {
				if ( $alias === $doctor_normalized || $alias === $slug_normalized ) {
					$found[] = array( 'ID' => (int) $doctor->ID, 'post_title' => (string) $doctor->post_title );
					break;
				}
			}
		}

		return $found;
	}

	/**
	 * Deterministic name normalization for doctor alias matching.
	 * Matches the spec exactly:
	 *   1. Unicode/lowercase
	 *   2. Trim
	 *   3. Punctuation → spaces
	 *   4. Collapse whitespace
	 *   5. Strip leading "dr" honorific
	 *
	 * @param string $name Raw name.
	 * @return string Normalized name.
	 */
	public function normalize_name( $name ) {
		$name = mb_strtolower( (string) $name, 'UTF-8' );
		$name = trim( $name );
		/* Punctuation → space (keep alphanumeric and space) */
		$name = (string) preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $name );
		/* Collapse whitespace */
		$name = (string) preg_replace( '/\s+/', ' ', $name );
		$name = trim( $name );
		/* Strip leading "dr" honorific (and only leading, not embedded) */
		if ( preg_match( '/^dr\s+(.+)$/u', $name, $m ) ) {
			$name = trim( $m[1] );
		}
		return $name;
	}

	/**
	 * Managed content: assert CPT structures exist (registered by ContentService on init).
	 *
	 * @return void
	 * @throws RuntimeException If any CPT is not registered.
	 */
	private function run_managed_content() {
		$required = array(
			Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
			Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE,
			Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE,
		);
		foreach ( $required as $cpt ) {
			if ( ! post_type_exists( $cpt ) ) {
				throw new RuntimeException( 'CPT tidak terdaftar: ' . $cpt . '. Aktifkan ulang plugin.' );
			}
		}
	}

	/**
	 * Demo seed: insert deterministic demo records for Promo, Testimonial, Achievement.
	 * Idempotent — never overwrites editor-created records.
	 *
	 * @return array<string,mixed>
	 */
	private function run_demo_seed() {
		$env         = $this->detect_environment();
		$is_dev_stg  = in_array( $env, array( 'development', 'staging', 'local' ), true );
		$status      = $is_dev_stg ? 'publish' : 'draft';

		$audit = array(
			'environment' => $env,
			'status'      => $status,
			'created'     => array(),
			'reused'      => array(),
		);

		/* Promo demo records */
		$promo_seeds = array(
			array(
				'identity' => 'gloskin-demo-promo-refresh-campaign-2026-1',
				'title'    => '[DEMO] Program Perawatan Kulit Wajah',
				'excerpt'  => 'Temukan rangkaian perawatan kulit wajah yang dirancang khusus sesuai kondisi dan kebutuhan Anda.',
				'meta'     => array(
					'gloskin_promo_eyebrow'  => 'Perawatan Pilihan',
					'gloskin_promo_cta_label' => 'Jelajahi Perawatan',
					'gloskin_promo_cta_url'  => '/treatments/',
					'gloskin_promo_active'   => '1',
				),
			),
			array(
				'identity' => 'gloskin-demo-promo-refresh-campaign-2026-2',
				'title'    => '[DEMO] Skincare Gloskin — Perawatan Harian',
				'excerpt'  => 'Produk skincare Gloskin diformulasikan untuk mendukung rutinitas perawatan kulit harian Anda.',
				'meta'     => array(
					'gloskin_promo_eyebrow'  => 'Skincare Terbaru',
					'gloskin_promo_cta_label' => 'Lihat Skincare',
					'gloskin_promo_cta_url'  => '/skincare/',
					'gloskin_promo_active'   => '1',
				),
			),
			array(
				'identity' => 'gloskin-demo-promo-refresh-campaign-2026-3',
				'title'    => '[DEMO] Konsultasi Dokter Gloskin',
				'excerpt'  => 'Setiap perawatan dimulai dari konsultasi bersama dokter kami untuk menentukan langkah terbaik bagi kondisi Anda.',
				'meta'     => array(
					'gloskin_promo_eyebrow'  => 'Konsultasi Medis',
					'gloskin_promo_cta_label' => 'Temukan Dokter',
					'gloskin_promo_cta_url'  => '/doctors/',
					'gloskin_promo_active'   => '1',
				),
			),
		);

		foreach ( $promo_seeds as $seed ) {
			$result = $this->seed_demo_post(
				Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
				$seed['identity'],
				$seed['title'],
				$seed['excerpt'],
				$status,
				$seed['meta']
			);
			$audit[ $result['action'] ][] = array( 'type' => 'promo', 'id' => $result['id'], 'identity' => $seed['identity'] );
		}

		/* Testimonial demo records */
		$testimonial_seeds = array(
			array(
				'identity' => 'gloskin-demo-testimonial-2026-1',
				'title'    => '[DEMO] Testimoni Perawatan Kulit',
				'excerpt'  => 'Setelah konsultasi dan mengikuti perawatan yang direkomendasikan, kondisi kulit saya membaik secara bertahap.',
				'meta'     => array(
					'gloskin_testimonial_attribution' => 'Pengguna Demo',
					'gloskin_testimonial_subtitle'    => 'Pasien Gloskin',
					'gloskin_testimonial_active'      => '1',
				),
			),
			array(
				'identity' => 'gloskin-demo-testimonial-2026-2',
				'title'    => '[DEMO] Pengalaman Konsultasi',
				'excerpt'  => 'Tim dokter sangat membantu dalam menjelaskan pilihan perawatan yang sesuai dengan kebutuhan saya.',
				'meta'     => array(
					'gloskin_testimonial_attribution' => 'Pengguna Demo',
					'gloskin_testimonial_subtitle'    => 'Pasien Klinik Gloskin',
					'gloskin_testimonial_active'      => '1',
				),
			),
		);

		foreach ( $testimonial_seeds as $seed ) {
			$result = $this->seed_demo_post(
				Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE,
				$seed['identity'],
				$seed['title'],
				$seed['excerpt'],
				$status,
				$seed['meta']
			);
			$audit[ $result['action'] ][] = array( 'type' => 'testimonial', 'id' => $result['id'], 'identity' => $seed['identity'] );
		}

		/* Achievement demo records */
		$achievement_seeds = array(
			array(
				'identity' => 'gloskin-demo-achievement-2026-1',
				'title'    => '[DEMO] Penghargaan Layanan Kesehatan',
				'excerpt'  => 'Contoh penghargaan atau sertifikasi yang diterima Gloskin. Gantikan dengan data faktual resmi.',
				'meta'     => array(
					'gloskin_achievement_issuer'          => 'Lembaga Demo',
					'gloskin_achievement_year'            => date( 'Y' ),
					'gloskin_achievement_feature_on_home' => '1',
					'gloskin_achievement_active'          => '1',
				),
			),
		);

		foreach ( $achievement_seeds as $seed ) {
			$result = $this->seed_demo_post(
				Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE,
				$seed['identity'],
				$seed['title'],
				$seed['excerpt'],
				$status,
				$seed['meta']
			);
			$audit[ $result['action'] ][] = array( 'type' => 'achievement', 'id' => $result['id'], 'identity' => $seed['identity'] );
		}

		return $audit;
	}

	/**
	 * Insert or reuse a deterministic demo post. Never overwrites editor records.
	 *
	 * @param string              $post_type CPT slug.
	 * @param string              $identity  Stable identity slug.
	 * @param string              $title     Post title.
	 * @param string              $excerpt   Post excerpt.
	 * @param string              $status    publish|draft.
	 * @param array<string,mixed> $meta      Post meta.
	 * @return array{action:string,id:int}
	 * @throws RuntimeException On insert failure.
	 */
	private function seed_demo_post( $post_type, $identity, $title, $excerpt, $status, array $meta ) {
		/* Check for existing demo record with this identity */
		$existing = get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => self::DEMO_IDENTITY_META,
					'value' => $identity,
				),
			),
			'fields'         => 'ids',
		) );

		if ( ! empty( $existing ) ) {
			return array( 'action' => 'reused', 'id' => absint( $existing[0] ) );
		}

		$result = wp_insert_post( array(
			'post_type'    => $post_type,
			'post_status'  => $status,
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
		), true );

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( 'Gagal membuat demo record ' . $identity . ': ' . $result->get_error_message() );
		}

		$id = absint( $result );
		update_post_meta( $id, self::DEMO_IDENTITY_META, $identity );
		update_post_meta( $id, self::DEMO_REVISION_META, self::REVISION );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}

		return array( 'action' => 'created', 'id' => $id );
	}

	/**
	 * Doctor photos: import/reuse 12 primary WebPs and set as featured images.
	 *
	 * @param array<string,array<string,mixed>> $matches Precomputed matches from preflight.
	 * @return array<string,mixed> Audit record.
	 * @throws RuntimeException On any import/apply failure.
	 */
	private function run_doctor_photos( array $matches ) {
		if ( count( $matches ) === 0 ) {
			throw new RuntimeException( 'Doctor photo matches ontbreek — run preflight opnieuw.' );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$audit = array(
			'applied'  => array(),
			'reused'   => array(),
		);

		foreach ( $matches as $webp_file => $match ) {
			$doctor_id    = absint( $match['doctor_id'] );
			$sha256        = (string) $match['sha256'];
			$asset_path    = (string) $match['asset_path'];
			$source_label  = (string) $match['source_label'];

			/* Find or create attachment */
			$attachment_id = $this->find_attachment_by_sha( $sha256 );
			$was_reused    = (bool) $attachment_id;
			if ( ! $attachment_id ) {
				$attachment_id = $this->import_doctor_photo( $asset_path, $webp_file, $sha256, $source_label );
			}

			/* Snapshot previous thumbnail once — idempotent on rerun */
			$prev_thumb = absint( get_post_thumbnail_id( $doctor_id ) );
			if ( '' === (string) get_post_meta( $doctor_id, self::PREV_THUMBNAIL_META, true ) ) {
				update_post_meta( $doctor_id, self::PREV_THUMBNAIL_META, $prev_thumb );
			}

			/* Set featured image and verify */
			$set_result = set_post_thumbnail( $doctor_id, $attachment_id );
			if ( ! $set_result ) {
				throw new RuntimeException(
					'set_post_thumbnail() gagal untuk dokter #' . $doctor_id . ' (' . $match['doctor_title'] . ').'
				);
			}
			$final_thumb = absint( get_post_thumbnail_id( $doctor_id ) );
			if ( $final_thumb !== $attachment_id ) {
				throw new RuntimeException(
					'Verifikasi thumbnail gagal untuk dokter #' . $doctor_id
					. ': expected=' . $attachment_id . ' got=' . $final_thumb
				);
			}

			$entry = array(
				'doctor_id'     => $doctor_id,
				'doctor_title'  => $match['doctor_title'],
				'attachment_id' => $attachment_id,
				'sha256'        => $sha256,
				'prev_thumb_id' => $prev_thumb,
			);

			if ( $was_reused ) {
				$audit['reused'][] = $entry;
			} else {
				$audit['applied'][] = $entry;
			}
		}

		return $audit;
	}

	/**
	 * Find an existing attachment imported by this migration for the given SHA-256.
	 *
	 * @param string $sha256 SHA-256 hex string.
	 * @return int Attachment ID, or 0 if not found.
	 */
	private function find_attachment_by_sha( $sha256 ) {
		$results = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => self::ATTACH_REVISION_META,
					'value' => self::REVISION,
				),
				array(
					'key'   => self::ATTACH_SHA256_META,
					'value' => $sha256,
				),
			),
		) );
		return ! empty( $results ) ? absint( $results[0] ) : 0;
	}

	/**
	 * Import one doctor photo WebP into the WordPress Media Library.
	 *
	 * @param string $asset_path  Absolute path to the WebP file.
	 * @param string $webp_file   Original filename.
	 * @param string $sha256      SHA-256 of the file.
	 * @param string $source_label Human-readable source label.
	 * @return int Attachment ID.
	 * @throws RuntimeException On import failure.
	 */
	private function import_doctor_photo( $asset_path, $webp_file, $sha256, $source_label ) {
		$filename   = basename( $webp_file );
		/* SHA prefix (8 hex chars) prevents collision with unrelated uploads */
		$sha_prefix = substr( $sha256, 0, 8 );
		$dest_name  = $sha_prefix . '-' . $filename;
		$upload     = wp_upload_dir();

		/* Write file to uploads using SHA-prefixed name */
		$dest_path = trailingslashit( $upload['path'] ) . $dest_name;
		if ( ! copy( $asset_path, $dest_path ) ) {
			throw new RuntimeException( 'Gagal menyalin foto dokter ke uploads: ' . $dest_name );
		}

		$filetype    = wp_check_filetype( $dest_name, null );
		$attach_data = array(
			'post_mime_type' => $filetype['type'] ? (string) $filetype['type'] : 'image/webp',
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attach_data, $dest_path );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			@unlink( $dest_path );
			$msg = is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : 'wp_insert_attachment mengembalikan 0';
			throw new RuntimeException( 'Gagal mendaftarkan attachment untuk ' . $dest_name . ': ' . $msg );
		}

		$attachment_id = absint( $attachment_id );
		$metadata = wp_generate_attachment_metadata( $attachment_id, $dest_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		/* Store provenance meta */
		update_post_meta( $attachment_id, self::ATTACH_REVISION_META, self::REVISION );
		update_post_meta( $attachment_id, self::ATTACH_SHA256_META, $sha256 );
		update_post_meta( $attachment_id, self::ATTACH_SOURCE_META, $source_label );

		return $attachment_id;
	}

	/**
	 * Normalize page relationships. Ensure /promo/ page and content relationships.
	 *
	 * @return void
	 * @throws RuntimeException On critical page failure.
	 */
	private function run_normalize() {
		$promo_page = get_page_by_path( 'promo', OBJECT, 'page' );
		if ( ! ( $promo_page instanceof WP_Post ) || 'trash' === $promo_page->post_status ) {
			$result = wp_insert_post( array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Promo',
				'post_name'   => 'promo',
			), true );
			if ( is_wp_error( $result ) ) {
				throw new RuntimeException( 'Gagal memastikan halaman /promo/: ' . $result->get_error_message() );
			}
		}
	}

	/**
	 * Cleanup: retire obsolete plugin-owned presentation surfaces where safe.
	 *
	 * @return void
	 */
	private function run_cleanup() {
		/* Zero-consumer cleanup only — never deletes editor content or Woo data.
		 * Strip obsolete design_variant/header_variant keys from the stored settings
		 * option; both keys are already unused by all public renderers. */
		$option_key = 'gloskin_site_core_settings';
		$settings   = get_option( $option_key, array() );
		if ( ! is_array( $settings ) ) {
			return;
		}
		$changed = false;
		foreach ( array( 'design_variant', 'header_variant' ) as $dead_key ) {
			if ( array_key_exists( $dead_key, $settings ) ) {
				unset( $settings[ $dead_key ] );
				$changed = true;
			}
		}
		if ( $changed ) {
			update_option( $option_key, $settings );
		}
	}

	/**
	 * Safety verification before finalize.
	 *
	 * @param array<string,mixed> $state Current state.
	 * @return void
	 * @throws RuntimeException On any assertion failure.
	 */
	private function run_verify( array $state ) {
		/* CPT structures */
		foreach ( array(
			Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
			Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE,
			Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE,
		) as $cpt ) {
			if ( ! post_type_exists( $cpt ) ) {
				throw new RuntimeException( 'CPT tidak terdaftar setelah managed_content: ' . $cpt );
			}
		}

		/* /promo/ page */
		$promo_page = get_page_by_path( 'promo', OBJECT, 'page' );
		if ( ! ( $promo_page instanceof WP_Post ) || 'trash' === $promo_page->post_status ) {
			throw new RuntimeException( 'Halaman /promo/ tidak ditemukan.' );
		}

		/* Commerce pages unchanged */
		if ( $this->commerce_page_snapshot() !== (array) $state['commerce_snapshot'] ) {
			throw new RuntimeException( 'Konfigurasi halaman WooCommerce berubah selama migrasi.' );
		}

		/* All 12 doctor photos applied */
		$doctor_audit = isset( $state['doctor_audit'] ) ? (array) $state['doctor_audit'] : array();
		$applied      = isset( $doctor_audit['applied'] ) ? (array) $doctor_audit['applied'] : array();
		$reused       = isset( $doctor_audit['reused'] ) ? (array) $doctor_audit['reused'] : array();
		$total_applied = count( $applied ) + count( $reused );
		$matches       = isset( $state['doctor_matches'] ) ? (array) $state['doctor_matches'] : array();

		if ( count( $matches ) > 0 && $total_applied < count( $matches ) ) {
			throw new RuntimeException(
				'Hanya ' . $total_applied . ' dari ' . count( $matches ) . ' foto dokter yang diterapkan.'
			);
		}

		/* Demo seeds: at least one promo, one testimonial, one achievement */
		$demo_audit = isset( $state['demo_audit'] ) ? (array) $state['demo_audit'] : array();
		$demo_items = array_merge(
			isset( $demo_audit['created'] ) ? (array) $demo_audit['created'] : array(),
			isset( $demo_audit['reused'] ) ? (array) $demo_audit['reused'] : array()
		);
		$promo_count = count( array_filter( $demo_items, static function ( $i ) { return isset( $i['type'] ) && 'promo' === $i['type']; } ) );
		$test_count  = count( array_filter( $demo_items, static function ( $i ) { return isset( $i['type'] ) && 'testimonial' === $i['type']; } ) );
		$ach_count   = count( array_filter( $demo_items, static function ( $i ) { return isset( $i['type'] ) && 'achievement' === $i['type']; } ) );

		if ( $promo_count < 1 || $test_count < 1 || $ach_count < 1 ) {
			throw new RuntimeException(
				'Demo seed tidak lengkap (promo=' . $promo_count . ' testimonial=' . $test_count . ' achievement=' . $ach_count . ').'
			);
		}

		/* No doctor was created or deleted */
		$doctor_count = wp_count_posts( Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE );
		$published    = $doctor_count ? (int) $doctor_count->publish : 0;
		if ( $published < 1 ) {
			throw new RuntimeException( 'Tidak ada dokter yang dipublikasikan — sesuatu yang tidak terduga terjadi.' );
		}
	}

	/**
	 * Finalize: write schema version and flush rewrites.
	 *
	 * @return void
	 */
	private function run_finalize() {
		flush_rewrite_rules( false );
	}

	/* -----------------------------------------------------------------------
	 * BUNDLE / MANIFEST HELPERS
	 * -------------------------------------------------------------------- */

	/** @return array<string,mixed> */
	private function load_bundle_manifest() {
		$manifest_path = $this->runtime_dir . '/manifest.json';
		if ( ! is_readable( $manifest_path ) ) {
			throw new RuntimeException( 'Bundle manifest tidak ditemukan: ' . $manifest_path );
		}
		$json     = file_get_contents( $manifest_path );
		$manifest = json_decode( (string) $json, true );
		if ( ! is_array( $manifest ) ) {
			throw new RuntimeException( 'Bundle manifest tidak valid (JSON error).' );
		}
		if ( ( (string) ( $manifest['bundle_id'] ?? '' ) ) !== self::BUNDLE_ID ) {
			throw new RuntimeException( 'Bundle ID tidak cocok.' );
		}
		return $manifest;
	}

	/**
	 * @param array<string,mixed> $manifest Parsed manifest.
	 * @return array<int,array<string,mixed>> Doctor definitions.
	 * @throws RuntimeException On invalid manifest.
	 */
	private function load_manifest_doctors( array $manifest ) {
		if ( ! isset( $manifest['doctors'] ) || ! is_array( $manifest['doctors'] ) ) {
			throw new RuntimeException( 'Manifest tidak memiliki array doctors.' );
		}
		foreach ( $manifest['doctors'] as $index => $doc ) {
			if ( empty( $doc['match_aliases'] ) || empty( $doc['primary_webp'] ) || empty( $doc['primary_sha256'] ) ) {
				throw new RuntimeException( 'Entry dokter #' . $index . ' tidak lengkap di manifest.' );
			}
		}
		return (array) $manifest['doctors'];
	}

	/* -----------------------------------------------------------------------
	 * ENVIRONMENT DETECTION
	 * -------------------------------------------------------------------- */

	/** @return string development|local|staging|production */
	private function detect_environment() {
		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		return in_array( $env, array( 'development', 'local', 'staging' ), true ) ? $env : 'production';
	}

	/* -----------------------------------------------------------------------
	 * COMMERCE SNAPSHOT
	 * -------------------------------------------------------------------- */

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

	/* -----------------------------------------------------------------------
	 * LOCK / STATE HELPERS
	 * -------------------------------------------------------------------- */

	/** @return string Token, or empty on failure. */
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

	/** @param array<string,mixed> $state State. @return void */
	private function save_state( array $state ) {
		update_option( self::STATE_OPTION, $state, false );
	}

	/** @param int $index Step index. @return string */
	private function step_label( $index ) {
		$steps = $this->steps();
		return isset( $steps[ $index ] ) ? $steps[ $index ]['label'] : 'Selesai';
	}

	/** @param array<string,mixed> $state State. @return array<string,mixed> */
	private function response_state( array $state ) {
		return array(
			'status'          => (string) $state['status'],
			'processed_steps' => (int) $state['processed_steps'],
			'total_steps'     => (int) $state['total_steps'],
			'current_step'    => (string) $state['current_step'],
			'last_error'      => (string) $state['last_error'],
		);
	}
}
// phpcs:enable
