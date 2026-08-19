<?php
/**
 * Bounded one-shot migration — 2026-08-19-final closure pass.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Gloskin_Site_Core_Revision_20260819_Final_Migration {

	const REVISION       = '2026-08-19-final';
	const STATE_OPTION   = 'gloskin_site_core_revision_20260819f_state';
	const LOCK_OPTION    = 'gloskin_site_core_revision_20260819f_lock';
	const LOCK_TTL       = 300;
	const BUNDLE_DIR     = 'gloskin-doctor-photos-v2';
	const BUNDLE_ID      = 'gloskin-doctor-photos-v2';
	const BATCH_SIZE     = 3;

	const ATTACH_REVISION_META = '_gloskin_photo_migration_revision';
	const ATTACH_SHA256_META   = '_gloskin_photo_migration_sha256';
	const ATTACH_SOURCE_META   = '_gloskin_photo_migration_source_label';
	const PREV_THUMBNAIL_META  = '_gloskin_prev_thumbnail_id_20260819f';

	const DEMO_IDENTITY_META = '_gloskin_demo_identity';
	const DEMO_REVISION_META = '_gloskin_demo_revision';

	/** @var string */
	private $plugin_file;

	/** @var string */
	private $runtime_dir;

	/** @param string $plugin_file Main plugin file path. */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$plugin_root       = plugin_dir_path( $plugin_file );
		$this->runtime_dir = trailingslashit( $plugin_root ) . 'migration-runtime/' . self::BUNDLE_DIR;
	}

	/** @return array<int,array{key:string,label:string}> */
	private function steps() {
		return array(
			array( 'key' => 'preflight',       'label' => 'Preflight — memeriksa persyaratan dan pra-komputasi pencocokan foto' ),
			array( 'key' => 'managed_content', 'label' => 'Memastikan struktur CPT terkelola' ),
			array( 'key' => 'demo_seed',       'label' => 'Menyemai data demo deterministik' ),
			array( 'key' => 'doctor_photos',   'label' => 'Mengimpor dan menerapkan foto dokter' ),
			array( 'key' => 'normalize',       'label' => 'Menormalkan relasi halaman' ),
			array( 'key' => 'cleanup',         'label' => 'Membersihkan kunci opsi usang' ),
			array( 'key' => 'verify',          'label' => 'Memverifikasi thumbnail per-dokter dan integritas data' ),
			array( 'key' => 'finalize',        'label' => 'Menyelesaikan dan mengunci migrasi' ),
		);
	}

	/** @return array<string,mixed> */
	public function get_state() {
		$state    = get_option( self::STATE_OPTION, array() );
		$state    = is_array( $state ) ? $state : array();
		$defaults = array(
			'revision'            => self::REVISION,
			'status'              => 'pending',
			'next_step_index'     => 0,
			'processed_steps'     => 0,
			'total_steps'         => count( $this->steps() ),
			'current_step'        => 'Siap dijalankan',
			'last_error'          => '',
			'doctor_matches'      => array(),
			'doctor_audit'        => array(),
			'demo_audit'          => array(),
			'commerce_snapshot'   => array(),
			'doctor_all_snapshot' => array(),
			'doctor_cursor'       => 0,
			'updated_at'          => 0,
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
	 * Synchronous no-JS fallback. Bounded but intentionally leaves headroom
	 * for repeated doctor batches while honoring the persisted cursor/state.
	 *
	 * @return array<string,mixed>
	 */
	public function run_to_completion() {
		$state = $this->advance( 'start' );
		$limit = count( $this->steps() ) + 20;
		for ( $i = 0; $i < $limit && 'consumed' !== $state['status']; $i++ ) {
			$state = $this->advance( 'continue' );
		}
		if ( 'consumed' !== $state['status'] ) {
			throw new RuntimeException( 'verification_failed: Migrasi tidak mencapai state consumed dalam batas checkpoint.' );
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
			throw new RuntimeException( 'migration_locked: Finalisasi sedang diproses oleh request lain.' );
		}

		try {
			if ( 'start' === $mode ) {
				/* start is a handshake only; persisted checkpoint/cursor/audit remain authoritative. */
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
				throw new RuntimeException( 'Migrasi belum dimulai.' );
			}

			$index = (int) $state['next_step_index'];
			$steps = $this->steps();

			if ( $index >= count( $steps ) ) {
				$state['status']       = 'consumed';
				$state['current_step'] = 'Selesai';
				$state['last_error']   = '';
				$state['updated_at']   = time();
				$this->save_state( $state );
				$this->release_lock( $token );
				return $this->response_state( $state );
			}

			$state['status']       = 'running';
			$state['current_step'] = $steps[ $index ]['label'];
			$state['last_error']   = '';
			$state['updated_at']   = time();
			$this->save_state( $state );

			$step_complete = true;

			switch ( $steps[ $index ]['key'] ) {
				case 'preflight':
					if ( 0 !== $index || (int) $state['doctor_cursor'] > 0 || $this->doctor_audit_count( $state['doctor_audit'] ) > 0 ) {
						throw new RuntimeException( 'verification_failed: Preflight tidak boleh diulang setelah mutasi foto dokter dimulai.' );
					}
					$preflight_result             = $this->run_preflight();
					$state['doctor_matches']      = $preflight_result['matches'];
					$state['doctor_all_snapshot'] = $preflight_result['all_snapshot'];
					$state['doctor_cursor']       = 0;
					$state['doctor_audit']        = array();
					$state['commerce_snapshot']   = $this->commerce_page_snapshot();
					break;

				case 'managed_content':
					$this->run_managed_content();
					break;

				case 'demo_seed':
					$state['demo_audit'] = $this->run_demo_seed();
					break;

				case 'doctor_photos':
					$batch                  = $this->run_doctor_photos_batch( $state );
					$state['doctor_audit']  = $batch['doctor_audit'];
					$state['doctor_cursor'] = $batch['cursor'];
					if ( ! $batch['complete'] ) {
						$step_complete         = false;
						$state['current_step'] = 'Mengimpor foto dokter (' . $batch['cursor'] . '/' . $batch['total'] . ')';
					}
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

			if ( $step_complete ) {
				$state['next_step_index'] = $index + 1;
				$state['processed_steps'] = min( count( $steps ), $index + 1 );
				$state['current_step']    = 'consumed' === $state['status']
					? 'Selesai'
					: $this->step_label( (int) $state['next_step_index'] );
			}

			$state['last_error'] = '';
			$state['updated_at'] = time();
			$this->save_state( $state );
			$this->release_lock( $token );
			return $this->response_state( $state );

		} catch ( Throwable $error ) {
			/* The doctor batch persists each fully verified doctor before a later doctor can fail. */
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

	/** @return array{matches:array<string,array<string,mixed>>,all_snapshot:array<int,int>} */
	private function run_preflight() {
		if ( ! function_exists( 'wp_insert_attachment' ) ) {
			throw new RuntimeException( 'bundle_invalid: Fungsi wp_insert_attachment tidak tersedia.' );
		}

		$manifest = $this->load_bundle_manifest();
		$doctors  = $this->load_manifest_doctors( $manifest );

		$matches      = array();
		$unmatched    = array();
		$ambiguous    = array();
		$invalid_file = array();

		foreach ( $doctors as $doc ) {
			$source_label = (string) $doc['source_label'];
			$aliases      = (array) $doc['match_aliases'];
			$webp_file    = (string) $doc['primary_webp'];
			$expected_sha = strtolower( (string) $doc['primary_sha256'] );
			$asset_path   = $this->runtime_dir . '/' . $webp_file;

			if ( ! is_readable( $asset_path ) ) {
				$invalid_file[] = $source_label . ' (' . $webp_file . ')';
				continue;
			}

			$actual_sha = hash_file( 'sha256', $asset_path );
			if ( ! is_string( $actual_sha ) || ! hash_equals( $expected_sha, strtolower( $actual_sha ) ) ) {
				$invalid_file[] = $source_label . ' (' . $webp_file . ', sha mismatch)';
				continue;
			}

			$found = $this->find_doctor_by_aliases( $aliases );
			if ( 0 === count( $found ) ) {
				$unmatched[] = $source_label;
			} elseif ( count( $found ) > 1 ) {
				$ambiguous[] = $source_label;
			} else {
				$matches[ $webp_file ] = array(
					'source_label' => $source_label,
					'doctor_id'    => absint( $found[0]['ID'] ),
					'doctor_title' => (string) $found[0]['post_title'],
					'webp_file'    => $webp_file,
					'sha256'       => $expected_sha,
					'asset_path'   => $asset_path,
				);
			}
		}

		if ( $invalid_file ) {
			throw new RuntimeException( 'bundle_invalid: Aset foto tidak valid/korup: ' . implode( '; ', $invalid_file ) );
		}
		if ( $unmatched ) {
			throw new RuntimeException( 'doctor_unmatched: Dokter tidak ditemukan: ' . implode( '; ', $unmatched ) );
		}
		if ( $ambiguous ) {
			throw new RuntimeException( 'doctor_ambiguous: Dokter ambigu: ' . implode( '; ', $ambiguous ) );
		}

		if ( count( $matches ) !== count( $doctors ) ) {
			throw new RuntimeException( 'bundle_invalid: Jumlah foto yang berhasil dicocokkan tidak sesuai manifest.' );
		}

		$sha_map = array();
		foreach ( $matches as $file => $match ) {
			$sha = (string) $match['sha256'];
			if ( isset( $sha_map[ $sha ] ) ) {
				throw new RuntimeException( 'bundle_invalid: SHA-256 duplikat pada ' . $file . ' dan ' . $sha_map[ $sha ] . '.' );
			}
			$sha_map[ $sha ] = $file;
		}

		$all_doctor_posts = get_posts( array(
			'post_type'      => Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		$all_snapshot = array();
		foreach ( $all_doctor_posts as $doctor_id ) {
			$all_snapshot[ absint( $doctor_id ) ] = absint( get_post_thumbnail_id( $doctor_id ) );
		}

		return array(
			'matches'      => $matches,
			'all_snapshot' => $all_snapshot,
		);
	}

	/** @param array<int,string> $aliases @return array<int,array{ID:int,post_title:string}> */
	private function find_doctor_by_aliases( array $aliases ) {
		$normalized_aliases = array_map( array( $this, 'normalize_name' ), $aliases );
		$all_doctors        = get_posts( array(
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

	/** @param string $name @return string */
	public function normalize_name( $name ) {
		$name = mb_strtolower( (string) $name, 'UTF-8' );
		$name = trim( $name );
		$name = (string) preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $name );
		$name = (string) preg_replace( '/\s+/', ' ', $name );
		$name = trim( $name );
		if ( preg_match( '/^dr\s+(.+)$/u', $name, $matches ) ) {
			$name = trim( $matches[1] );
		}
		return $name;
	}

	/** @return void */
	private function run_managed_content() {
		foreach ( array(
			Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
			Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE,
			Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE,
		) as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				throw new RuntimeException( 'CPT tidak terdaftar: ' . $post_type . '.' );
			}
		}
	}

	/** @return array<string,mixed> */
	private function run_demo_seed() {
		$env        = $this->detect_environment();
		$is_dev_stg = in_array( $env, array( 'development', 'staging', 'local' ), true );
		$status     = $is_dev_stg ? 'publish' : 'draft';
		$audit      = array( 'environment' => $env, 'status' => $status, 'created' => array(), 'reused' => array() );

		$seeds = array(
			array( 'type' => 'promo', 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'identity' => 'gloskin-demo-promo-refresh-campaign-2026-1', 'title' => '[DEMO] Program Perawatan Kulit Wajah', 'excerpt' => 'Temukan rangkaian perawatan kulit wajah yang dirancang khusus sesuai kondisi dan kebutuhan Anda.', 'meta' => array( 'gloskin_promo_eyebrow' => 'Perawatan Pilihan', 'gloskin_promo_cta_label' => 'Jelajahi Perawatan', 'gloskin_promo_cta_url' => '/treatments/', 'gloskin_promo_active' => '1' ) ),
			array( 'type' => 'promo', 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'identity' => 'gloskin-demo-promo-refresh-campaign-2026-2', 'title' => '[DEMO] Skincare Gloskin — Perawatan Harian', 'excerpt' => 'Produk skincare Gloskin diformulasikan untuk mendukung rutinitas perawatan kulit harian Anda.', 'meta' => array( 'gloskin_promo_eyebrow' => 'Skincare Terbaru', 'gloskin_promo_cta_label' => 'Lihat Skincare', 'gloskin_promo_cta_url' => '/skincare/', 'gloskin_promo_active' => '1' ) ),
			array( 'type' => 'promo', 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'identity' => 'gloskin-demo-promo-refresh-campaign-2026-3', 'title' => '[DEMO] Konsultasi Dokter Gloskin', 'excerpt' => 'Setiap perawatan dimulai dari konsultasi bersama dokter kami untuk menentukan langkah terbaik bagi kondisi Anda.', 'meta' => array( 'gloskin_promo_eyebrow' => 'Konsultasi Medis', 'gloskin_promo_cta_label' => 'Temukan Dokter', 'gloskin_promo_cta_url' => '/doctors/', 'gloskin_promo_active' => '1' ) ),
			array( 'type' => 'testimonial', 'post_type' => Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE, 'identity' => 'gloskin-demo-testimonial-2026-1', 'title' => '[DEMO] Testimoni Perawatan Kulit', 'excerpt' => 'Setelah konsultasi dan mengikuti perawatan yang direkomendasikan, kondisi kulit saya membaik secara bertahap.', 'meta' => array( 'gloskin_testimonial_attribution' => 'Pengguna Demo', 'gloskin_testimonial_subtitle' => 'Pasien Gloskin', 'gloskin_testimonial_active' => '1' ) ),
			array( 'type' => 'testimonial', 'post_type' => Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE, 'identity' => 'gloskin-demo-testimonial-2026-2', 'title' => '[DEMO] Pengalaman Konsultasi', 'excerpt' => 'Tim dokter sangat membantu dalam menjelaskan pilihan perawatan yang sesuai dengan kebutuhan saya.', 'meta' => array( 'gloskin_testimonial_attribution' => 'Pengguna Demo', 'gloskin_testimonial_subtitle' => 'Pasien Klinik Gloskin', 'gloskin_testimonial_active' => '1' ) ),
			array( 'type' => 'achievement', 'post_type' => Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE, 'identity' => 'gloskin-demo-achievement-2026-1', 'title' => '[DEMO] Penghargaan Layanan Kesehatan', 'excerpt' => 'Contoh penghargaan atau sertifikasi yang diterima Gloskin. Gantikan dengan data faktual resmi.', 'meta' => array( 'gloskin_achievement_issuer' => 'Lembaga Demo', 'gloskin_achievement_year' => (string) gmdate( 'Y' ), 'gloskin_achievement_feature_on_home' => '1', 'gloskin_achievement_active' => '1' ) ),
		);

		foreach ( $seeds as $seed ) {
			$result = $this->seed_demo_post( $seed['post_type'], $seed['identity'], $seed['title'], $seed['excerpt'], $status, $seed['meta'] );
			$audit[ $result['action'] ][] = array( 'type' => $seed['type'], 'id' => $result['id'], 'identity' => $seed['identity'] );
		}
		return $audit;
	}

	/** @return array{action:string,id:int} */
	private function seed_demo_post( $post_type, $identity, $title, $excerpt, $status, array $meta ) {
		$existing = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => self::DEMO_IDENTITY_META, 'value' => $identity ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		if ( ! empty( $existing ) ) {
			return array( 'action' => 'reused', 'id' => absint( $existing[0] ) );
		}
		$result = wp_insert_post( array( 'post_type' => $post_type, 'post_status' => $status, 'post_title' => $title, 'post_excerpt' => $excerpt ), true );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( 'Gagal membuat demo record ' . $identity . ': ' . $result->get_error_message() );
		}
		$post_id = absint( $result );
		update_post_meta( $post_id, self::DEMO_IDENTITY_META, $identity );
		update_post_meta( $post_id, self::DEMO_REVISION_META, self::REVISION );
		foreach ( $meta as $key => $value ) { update_post_meta( $post_id, $key, $value ); }
		return array( 'action' => 'created', 'id' => $post_id );
	}

	/** @return array{doctor_audit:array<string,mixed>,cursor:int,total:int,complete:bool} */
	private function run_doctor_photos_batch( array $state ) {
		$matches = array_values( (array) $state['doctor_matches'] );
		$total   = count( $matches );
		if ( 0 === $total ) { throw new RuntimeException( 'verification_failed: Doctor photo matches kosong — jalankan ulang preflight.' ); }

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$this->assert_upload_ready();

		$cursor = max( 0, min( $total, (int) ( $state['doctor_cursor'] ?? 0 ) ) );
		$audit  = $this->normalize_doctor_audit( $state['doctor_audit'] ?? array() );
		$end    = min( $cursor + self::BATCH_SIZE, $total );

		for ( $i = $cursor; $i < $end; $i++ ) {
			$match        = $matches[ $i ];
			$doctor_id    = absint( $match['doctor_id'] );
			$sha256       = (string) $match['sha256'];
			$asset_path   = (string) $match['asset_path'];
			$webp_file    = (string) $match['webp_file'];
			$source_label = (string) $match['source_label'];

			$attachment_id = $this->find_attachment_by_sha( $sha256 );
			$was_reused    = false;
			if ( $attachment_id ) {
				$attachment_revision = (string) get_post_meta( $attachment_id, self::ATTACH_REVISION_META, true );
				$was_reused          = self::REVISION !== $attachment_revision;
			} else {
				$attachment_id = $this->import_doctor_photo( $asset_path, $webp_file, $sha256, $source_label );
			}

			if ( '' === (string) get_post_meta( $doctor_id, self::PREV_THUMBNAIL_META, true ) ) {
				update_post_meta( $doctor_id, self::PREV_THUMBNAIL_META, absint( get_post_thumbnail_id( $doctor_id ) ) );
			}

			$current_thumb = absint( get_post_thumbnail_id( $doctor_id ) );
			if ( $current_thumb !== $attachment_id ) {
				$set_result = set_post_thumbnail( $doctor_id, $attachment_id );
				if ( ! $set_result && absint( get_post_thumbnail_id( $doctor_id ) ) !== $attachment_id ) {
					throw new RuntimeException( 'verification_failed: set_post_thumbnail() gagal untuk dokter #' . $doctor_id . '.' );
				}
			}

			$final_thumb = absint( get_post_thumbnail_id( $doctor_id ) );
			if ( $final_thumb !== $attachment_id ) { throw new RuntimeException( 'verification_failed: Verifikasi thumbnail gagal untuk dokter #' . $doctor_id . '.' ); }
			$stored_sha = (string) get_post_meta( $attachment_id, self::ATTACH_SHA256_META, true );
			if ( ! hash_equals( $sha256, $stored_sha ) ) { throw new RuntimeException( 'verification_failed: SHA attachment dokter #' . $doctor_id . ' tidak cocok.' ); }

			$entry = array( 'doctor_id' => $doctor_id, 'doctor_title' => (string) $match['doctor_title'], 'attachment_id' => $attachment_id, 'sha256' => $sha256 );
			$audit = $this->upsert_doctor_audit_entry( $audit, $entry, $was_reused ? 'reused' : 'applied' );

			$cursor                 = $i + 1;
			$state['doctor_cursor'] = $cursor;
			$state['doctor_audit']  = $audit;
			$state['status']        = 'running';
			$state['current_step']  = 'Mengimpor foto dokter (' . $cursor . '/' . $total . ')';
			$state['last_error']    = '';
			$state['updated_at']    = time();
			$this->save_state( $state );
		}

		return array( 'doctor_audit' => $audit, 'cursor' => $cursor, 'total' => $total, 'complete' => $cursor >= $total );
	}

	/** @return array{applied:array<int,array<string,mixed>>,reused:array<int,array<string,mixed>>} */
	private function normalize_doctor_audit( $audit ) {
		$audit = is_array( $audit ) ? $audit : array();
		return array( 'applied' => isset( $audit['applied'] ) && is_array( $audit['applied'] ) ? array_values( $audit['applied'] ) : array(), 'reused' => isset( $audit['reused'] ) && is_array( $audit['reused'] ) ? array_values( $audit['reused'] ) : array() );
	}

	/** @return array<string,mixed> */
	private function upsert_doctor_audit_entry( array $audit, array $entry, $bucket ) {
		$doctor_id = absint( $entry['doctor_id'] );
		foreach ( array( 'applied', 'reused' ) as $name ) {
			$audit[ $name ] = array_values( array_filter( (array) $audit[ $name ], static function ( $existing ) use ( $doctor_id ) { return ! is_array( $existing ) || absint( $existing['doctor_id'] ?? 0 ) !== $doctor_id; } ) );
		}
		$audit[ $bucket ][] = $entry;
		return $audit;
	}

	/** @return int */
	private function doctor_audit_count( $audit ) {
		$normalized = $this->normalize_doctor_audit( $audit );
		return count( $normalized['applied'] ) + count( $normalized['reused'] );
	}

	/** @return int */
	private function find_attachment_by_sha( $sha256 ) {
		$results = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => self::ATTACH_SHA256_META, 'value' => $sha256 ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		return ! empty( $results ) ? absint( $results[0] ) : 0;
	}

	/** @return array<string,mixed> */
	private function assert_upload_ready() {
		$upload = wp_upload_dir();
		$error  = isset( $upload['error'] ) ? (string) $upload['error'] : '';
		$path   = isset( $upload['path'] ) ? (string) $upload['path'] : '';
		if ( '' !== $error || '' === $path || ! is_dir( $path ) || ! is_writable( $path ) ) {
			$context = '' !== $error ? $error : ( '' !== $path ? $path : 'missing upload path' );
			throw new RuntimeException( 'upload_unavailable: ' . $context );
		}
		return $upload;
	}

	/** @return int */
	private function import_doctor_photo( $asset_path, $webp_file, $sha256, $source_label ) {
		$upload    = $this->assert_upload_ready();
		$base_name = basename( $webp_file );
		$dest_name = wp_unique_filename( $upload['path'], $base_name );
		$dest_path = trailingslashit( $upload['path'] ) . $dest_name;
		if ( ! copy( $asset_path, $dest_path ) ) { throw new RuntimeException( 'upload_unavailable: Gagal menyalin foto dokter ke uploads: ' . $dest_name ); }

		$filetype = wp_check_filetype( $dest_name, null );
		$attach   = array( 'post_mime_type' => $filetype['type'] ? (string) $filetype['type'] : 'image/webp', 'post_title' => sanitize_file_name( pathinfo( $base_name, PATHINFO_FILENAME ) ), 'post_content' => '', 'post_status' => 'inherit' );
		$attachment_id = wp_insert_attachment( $attach, $dest_path );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			@unlink( $dest_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$message = is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : 'wp_insert_attachment mengembalikan 0';
			throw new RuntimeException( 'upload_unavailable: Gagal mendaftarkan attachment ' . $dest_name . ': ' . $message );
		}
		$attachment_id = absint( $attachment_id );
		$metadata      = wp_generate_attachment_metadata( $attachment_id, $dest_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		update_post_meta( $attachment_id, self::ATTACH_REVISION_META, self::REVISION );
		update_post_meta( $attachment_id, self::ATTACH_SHA256_META, $sha256 );
		update_post_meta( $attachment_id, self::ATTACH_SOURCE_META, $source_label );
		return $attachment_id;
	}

	/** @return void */
	private function run_normalize() {
		$promo_page = get_page_by_path( 'promo', OBJECT, 'page' );
		if ( ! ( $promo_page instanceof WP_Post ) || 'trash' === $promo_page->post_status ) {
			$result = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Promo', 'post_name' => 'promo' ), true );
			if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Gagal memastikan halaman /promo/: ' . $result->get_error_message() ); }
		}
	}

	/** @return void */
	private function run_cleanup() {
		$option_key = 'gloskin_site_core_settings';
		$settings   = get_option( $option_key, array() );
		if ( ! is_array( $settings ) ) { return; }
		$changed = false;
		foreach ( array( 'design_variant', 'header_variant' ) as $dead_key ) {
			if ( array_key_exists( $dead_key, $settings ) ) { unset( $settings[ $dead_key ] ); $changed = true; }
		}
		if ( $changed ) { update_option( $option_key, $settings ); }
	}

	/** @return void */
	private function run_verify( array $state ) {
		foreach ( array( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE, Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE ) as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) { throw new RuntimeException( 'verification_failed: CPT tidak terdaftar setelah managed_content: ' . $post_type ); }
		}
		$promo_page = get_page_by_path( 'promo', OBJECT, 'page' );
		if ( ! ( $promo_page instanceof WP_Post ) || 'trash' === $promo_page->post_status ) { throw new RuntimeException( 'verification_failed: Halaman /promo/ tidak ditemukan.' ); }
		if ( $this->commerce_page_snapshot() !== (array) $state['commerce_snapshot'] ) { throw new RuntimeException( 'verification_failed: Konfigurasi halaman WooCommerce berubah selama migrasi.' ); }

		$matches     = (array) ( $state['doctor_matches'] ?? array() );
		$audit       = $this->normalize_doctor_audit( $state['doctor_audit'] ?? array() );
		$all_entries = array_merge( $audit['applied'], $audit['reused'] );
		if ( count( $all_entries ) !== count( $matches ) ) { throw new RuntimeException( 'verification_failed: Jumlah audit foto tidak sama dengan target dokter.' ); }

		$seen_doctors = array();
		$seen_shas    = array();
		foreach ( $all_entries as $entry ) {
			$doctor_id = absint( $entry['doctor_id'] ?? 0 );
			$sha       = (string) ( $entry['sha256'] ?? '' );
			if ( isset( $seen_doctors[ $doctor_id ] ) || ( '' !== $sha && isset( $seen_shas[ $sha ] ) ) ) { throw new RuntimeException( 'verification_failed: Audit foto berisi entri dokter/SHA duplikat.' ); }
			$seen_doctors[ $doctor_id ] = true;
			if ( '' !== $sha ) { $seen_shas[ $sha ] = true; }
		}

		foreach ( $matches as $match ) {
			$doctor_id = absint( $match['doctor_id'] );
			$expected_att = 0;
			foreach ( $all_entries as $entry ) { if ( absint( $entry['doctor_id'] ?? 0 ) === $doctor_id ) { $expected_att = absint( $entry['attachment_id'] ?? 0 ); break; } }
			if ( ! $expected_att || absint( get_post_thumbnail_id( $doctor_id ) ) !== $expected_att ) { throw new RuntimeException( 'verification_failed: Thumbnail dokter #' . $doctor_id . ' tidak sesuai audit.' ); }
			$stored_sha = (string) get_post_meta( $expected_att, self::ATTACH_SHA256_META, true );
			if ( ! hash_equals( (string) $match['sha256'], $stored_sha ) ) { throw new RuntimeException( 'verification_failed: SHA thumbnail dokter #' . $doctor_id . ' tidak sesuai manifest.' ); }
		}

		$all_snapshot       = (array) ( $state['doctor_all_snapshot'] ?? array() );
		$target_doctor_ids  = array();
		foreach ( $matches as $match ) { $target_doctor_ids[] = absint( $match['doctor_id'] ); }
		foreach ( $all_snapshot as $doctor_id => $thumbnail_id ) {
			$doctor_id = absint( $doctor_id );
			if ( in_array( $doctor_id, $target_doctor_ids, true ) ) { continue; }
			if ( absint( get_post_thumbnail_id( $doctor_id ) ) !== absint( $thumbnail_id ) ) { throw new RuntimeException( 'verification_failed: thumbnail dokter non-target #' . $doctor_id . ' berubah.' ); }
		}

		$demo_audit = (array) ( $state['demo_audit'] ?? array() );
		$demo_items = array_merge( (array) ( $demo_audit['created'] ?? array() ), (array) ( $demo_audit['reused'] ?? array() ) );
		$promo_count = count( array_filter( $demo_items, static function ( $item ) { return isset( $item['type'] ) && 'promo' === $item['type']; } ) );
		$test_count  = count( array_filter( $demo_items, static function ( $item ) { return isset( $item['type'] ) && 'testimonial' === $item['type']; } ) );
		$ach_count   = count( array_filter( $demo_items, static function ( $item ) { return isset( $item['type'] ) && 'achievement' === $item['type']; } ) );
		if ( $promo_count < 1 || $test_count < 1 || $ach_count < 1 ) { throw new RuntimeException( 'verification_failed: Demo seed tidak lengkap.' ); }
		$doctor_count = wp_count_posts( Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE );
		$published    = $doctor_count ? (int) $doctor_count->publish : 0;
		if ( $published < 1 ) { throw new RuntimeException( 'verification_failed: Tidak ada dokter yang dipublikasikan.' ); }
	}

	/** @return void */
	private function run_finalize() { flush_rewrite_rules( false ); }

	/** @return array<string,mixed> */
	private function load_bundle_manifest() {
		$manifest_path = $this->runtime_dir . '/manifest.json';
		if ( ! is_readable( $manifest_path ) ) { throw new RuntimeException( 'bundle_unavailable: Bundle manifest tidak ditemukan di ' . $manifest_path ); }
		$json = file_get_contents( $manifest_path );
		$manifest = json_decode( (string) $json, true );
		if ( ! is_array( $manifest ) ) { throw new RuntimeException( 'bundle_invalid: Bundle manifest tidak valid (JSON error).' ); }
		if ( (string) ( $manifest['bundle_id'] ?? '' ) !== self::BUNDLE_ID ) { throw new RuntimeException( 'bundle_invalid: Bundle ID tidak cocok.' ); }
		return $manifest;
	}

	/** @return array<int,array<string,mixed>> */
	private function load_manifest_doctors( array $manifest ) {
		if ( ! isset( $manifest['doctors'] ) || ! is_array( $manifest['doctors'] ) ) { throw new RuntimeException( 'bundle_invalid: Manifest tidak memiliki array doctors.' ); }
		if ( 12 !== count( $manifest['doctors'] ) ) { throw new RuntimeException( 'bundle_invalid: Manifest harus berisi tepat 12 dokter.' ); }
		foreach ( $manifest['doctors'] as $index => $doc ) {
			if ( ! is_array( $doc ) || empty( $doc['source_label'] ) || empty( $doc['match_aliases'] ) || empty( $doc['primary_webp'] ) || empty( $doc['primary_sha256'] ) || 64 !== strlen( (string) $doc['primary_sha256'] ) ) {
				throw new RuntimeException( 'bundle_invalid: Entry dokter #' . $index . ' tidak lengkap.' );
			}
		}
		return array_values( $manifest['doctors'] );
	}

	/** @return string */
	private function detect_environment() {
		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		return in_array( $env, array( 'development', 'local', 'staging' ), true ) ? $env : 'production';
	}

	/** @return array<string,int> */
	private function commerce_page_snapshot() {
		$snapshot = array();
		foreach ( array( 'woocommerce_shop_page_id', 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id' ) as $key ) { $snapshot[ $key ] = (int) get_option( $key, 0 ); }
		return $snapshot;
	}

	/** @return string */
	private function acquire_lock() {
		$now = time(); $token = wp_generate_uuid4(); $lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && ! empty( $lock['expires'] ) && (int) $lock['expires'] <= $now ) { delete_option( self::LOCK_OPTION ); }
		if ( add_option( self::LOCK_OPTION, array( 'token' => $token, 'expires' => $now + self::LOCK_TTL ), '', false ) ) { return $token; }
		return '';
	}

	/** @return void */
	private function release_lock( $token ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) { delete_option( self::LOCK_OPTION ); }
	}

	/** @return void */
	private function save_state( array $state ) { update_option( self::STATE_OPTION, $state, false ); }

	/** @return string */
	private function step_label( $index ) { $steps = $this->steps(); return isset( $steps[ $index ] ) ? $steps[ $index ]['label'] : 'Selesai'; }

	/** @return array<string,mixed> */
	private function response_state( array $state ) {
		$audit = $this->normalize_doctor_audit( $state['doctor_audit'] ?? array() );
		return array(
			'status' => (string) $state['status'],
			'processed_steps' => (int) $state['processed_steps'],
			'total_steps' => (int) $state['total_steps'],
			'current_step' => (string) $state['current_step'],
			'last_error' => (string) $state['last_error'],
			'doctor_cursor' => (int) $state['doctor_cursor'],
			'doctor_total' => count( (array) $state['doctor_matches'] ),
			'doctor_applied' => count( $audit['applied'] ),
			'doctor_reused' => count( $audit['reused'] ),
		);
	}
}
// phpcs:enable
