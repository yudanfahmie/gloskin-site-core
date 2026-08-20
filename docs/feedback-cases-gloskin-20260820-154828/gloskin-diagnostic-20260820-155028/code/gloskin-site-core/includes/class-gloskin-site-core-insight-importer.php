<?php
/**
 * Resumable one-shot coordinator for Gloskin Insights v1.
 *
 * WordPress post/category/media APIs remain the sole content mutation owners.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/class-gloskin-site-core-insight-bundle.php';

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Gloskin_Site_Core_Insight_Importer {
	const STATE_OPTION = 'gloskin_site_core_insights_v1_state';
	const LOCK_OPTION = 'gloskin_site_core_insights_v1_lock';
	const LOCK_TTL = 900;

	const SOURCE_META = '_gloskin_insight_source_id';
	const MEDIA_SOURCE_META = '_gloskin_insight_media_source_id';
	const BUNDLE_META = '_gloskin_insight_bundle_id';
	const SEED_META = '_gloskin_insight_seed';
	const MEDIA_URL_META = '_gloskin_insight_media_source_url';
	const MEDIA_PAGE_META = '_gloskin_insight_media_source_page';
	const MEDIA_AUTHOR_META = '_gloskin_insight_media_author';
	const MEDIA_LICENSE_META = '_gloskin_insight_media_license';

	/** @var Gloskin_Site_Core_Insight_Bundle */
	private $bundle;

	public function __construct( $plugin_file ) {
		$this->bundle = new Gloskin_Site_Core_Insight_Bundle( $plugin_file );
	}

	public function runtime_dir() { return $this->bundle->runtime_dir(); }

	public function get_summary() {
		$state = $this->get_state();
		if ( 'consumed' === $state['status'] ) {
			return array_merge( $state, array( 'detection' => 'consumed' ) );
		}
		$manifest = $this->bundle->read_header();
		if ( is_wp_error( $manifest ) ) {
			return array_merge( $state, array( 'detection' => 'failed', 'last_error' => $manifest->get_error_message() ) );
		}
		if ( empty( $manifest ) ) {
			return array_merge( $state, array( 'detection' => 'none' ) );
		}
		$detection = isset( $state['status'] ) ? (string) $state['status'] : 'pending';
		if ( in_array( $detection, array( 'validating','running','verifying' ), true ) && ! $this->lock_is_active() ) {
			$detection = 'failed';
		}
		return array_merge( $state, array(
			'detection' => $detection,
			'bundle_id' => (string) $manifest['bundle_id'],
			'source_version' => (string) $manifest['source_version'],
			'expected_posts' => (int) $manifest['expected_posts'],
			'expected_media' => (int) $manifest['expected_media'],
			'expected_categories' => (int) $manifest['expected_categories'],
		) );
	}

	public function should_show_menu() {
		$summary = $this->get_summary();
		return in_array( $summary['detection'], array( 'pending','failed','validating','running','verifying' ), true );
	}

	/**
	 * start = validate + initialize only. continue = max one post/media pair.
	 */
	public function advance( $mode ) {
		$mode = sanitize_key( (string) $mode );
		$state = $this->get_state();
		if ( 'consumed' === $state['status'] ) {
			throw new RuntimeException( 'Bundle Insight sudah dikonsumsi dan tidak dapat dijalankan kembali.' );
		}
		if ( ! in_array( $mode, array( 'start','continue' ), true ) ) {
			throw new RuntimeException( 'Mode checkpoint Insight tidak valid.' );
		}
		$token = $this->acquire_lock();
		if ( '' === $token ) {
			throw new RuntimeException( 'Import Insight sedang berjalan pada request lain.' );
		}

		try {
			if ( 'start' === $mode ) {
				$state['status'] = 'validating';
				$state['last_error'] = '';
				$this->save_state( $state );
			}
			$validated = $this->bundle->validate();
			$manifest = $validated['manifest'];

			if ( 'start' === $mode ) {
				$state = $this->initialize_or_resume_state( $state, $manifest );
				$this->save_state( $state );
				$this->release_lock( $token );
				return $this->response_state( $state );
			}

			$state = $this->normalize_state_for_bundle( $state, $manifest );
			if ( (int) $state['next_post_index'] < count( $validated['posts'] ) ) {
				$index = (int) $state['next_post_index'];
				$post_record = $validated['posts'][ $index ];
				$media_record = $validated['media_by_source'][ $post_record['media_source_id'] ];
				$result = $this->import_pair( $post_record, $media_record, (string) $manifest['bundle_id'] );

				$state['processed_posts'] = (int) $state['processed_posts'] + 1;
				$state['next_post_index'] = $index + 1;
				if ( 'created' === $result['post_action'] ) { $state['created_posts']++; } else { $state['updated_posts']++; }
				if ( $result['media_reused'] ) { $state['reused_media']++; } else { $state['imported_media']++; }
				$state['status'] = $state['next_post_index'] >= count( $validated['posts'] ) ? 'verifying' : 'running';
				$state['last_error'] = '';
				$this->save_state( $state );
				$this->release_lock( $token );
				return $this->response_state( $state );
			}

			$state['status'] = 'verifying';
			$this->save_state( $state );
			$this->verify_all( $validated );

			// Logical consumption is authoritative and MUST precede filesystem cleanup.
			$state['status'] = 'consumed';
			$state['cleanup'] = 'pending';
			$state['last_error'] = '';
			$this->save_state( $state );
			$this->release_lock( $token );

			$cleanup = $this->bundle->cleanup( $manifest );
			$consumed = $this->get_state();
			$consumed['cleanup'] = $cleanup['ok'] ? 'complete' : 'failed';
			$consumed['cleanup_error'] = (string) $cleanup['message'];
			$this->save_state( $consumed );
			return $this->response_state( $consumed );
		} catch ( Throwable $error ) {
			$this->release_lock( $token );
			$failed = $this->get_state();
			if ( 'consumed' !== $failed['status'] ) {
				$failed['status'] = 'failed';
				$failed['last_error'] = $error->getMessage();
				$this->save_state( $failed );
			}
			throw new RuntimeException( $error->getMessage(), 0, $error );
		}
	}

	public function get_state() {
		$state = get_option( self::STATE_OPTION, array() );
		if ( ! is_array( $state ) ) { $state = array(); }
		return array_merge( $this->state_defaults(), $state );
	}

	private function state_defaults() {
		return array(
			'status' => 'pending','bundle_id' => '','source_version' => '','bundle_fingerprint' => '',
			'next_post_index' => 0,'expected_posts' => 13,'processed_posts' => 0,'created_posts' => 0,'updated_posts' => 0,
			'expected_media' => 13,'imported_media' => 0,'reused_media' => 0,'expected_categories' => 5,
			'cleanup' => 'pending','cleanup_error' => '','last_error' => '','updated_at' => 0,
		);
	}

	private function initialize_or_resume_state( array $state, array $manifest ) {
		$same = (string) $state['bundle_id'] === (string) $manifest['bundle_id']
			&& (string) $state['source_version'] === (string) $manifest['source_version'];
		$fingerprint = $this->bundle_fingerprint( $manifest );
		if ( $same ) {
			$stored = (string) $state['bundle_fingerprint'];
			if ( ( '' !== $stored && ! hash_equals( $stored, $fingerprint ) )
				|| ( '' === $stored && ! empty( $state['processed_posts'] ) ) ) {
				throw new RuntimeException( 'Bundle Insight berubah setelah import dimulai.' );
			}
		} else {
			$state = $this->state_defaults();
		}
		$state['status'] = (int) $state['next_post_index'] >= 13 ? 'verifying' : 'running';
		$state['bundle_id'] = (string) $manifest['bundle_id'];
		$state['source_version'] = (string) $manifest['source_version'];
		$state['bundle_fingerprint'] = $fingerprint;
		$state['expected_posts'] = (int) $manifest['expected_posts'];
		$state['expected_media'] = (int) $manifest['expected_media'];
		$state['expected_categories'] = (int) $manifest['expected_categories'];
		$state['last_error'] = '';
		return $state;
	}

	private function normalize_state_for_bundle( array $state, array $manifest ) {
		if ( (string) $state['bundle_id'] !== (string) $manifest['bundle_id']
			|| (string) $state['source_version'] !== (string) $manifest['source_version'] ) {
			throw new RuntimeException( 'Checkpoint Insight tidak cocok dengan bundle runtime. Jalankan validasi awal kembali.' );
		}
		$fingerprint = $this->bundle_fingerprint( $manifest );
		if ( '' === (string) $state['bundle_fingerprint'] || ! hash_equals( (string) $state['bundle_fingerprint'], $fingerprint ) ) {
			throw new RuntimeException( 'Bundle Insight berubah setelah import dimulai.' );
		}
		if ( ! in_array( $state['status'], array( 'running','failed','verifying','validating' ), true ) ) {
			throw new RuntimeException( 'Workflow Insight belum dimulai secara eksplisit.' );
		}
		$state['status'] = (int) $state['next_post_index'] >= (int) $manifest['expected_posts'] ? 'verifying' : 'running';
		return $state;
	}

	private function bundle_fingerprint( array $manifest ) {
		$checksums = (array) $manifest['checksums'];
		return hash( 'sha256', strtolower( (string) $checksums['posts.json'] ) . ':' . strtolower( (string) $checksums['media.json'] ) );
	}

	private function import_pair( array $record, array $media_record, $bundle_id ) {
		$ids = $this->find_post_ids( $record['source_id'] );
		if ( count( $ids ) > 1 ) { throw new RuntimeException( 'Post source identity collision: ' . $record['source_id'] ); }
		$existing_id = $ids ? (int) $ids[0] : 0;
		if ( $existing_id && 'post' !== get_post_type( $existing_id ) ) {
			throw new RuntimeException( 'Source identity Insight dipakai object non-post: ' . $record['source_id'] );
		}

		$slug_post = get_page_by_path( $record['slug'], OBJECT, 'post' );
		if ( $slug_post instanceof WP_Post && (int) $slug_post->ID !== $existing_id ) {
			$owner = (string) get_post_meta( $slug_post->ID, self::SOURCE_META, true );
			throw new RuntimeException( '' === $owner
				? 'Slug Insight sudah dipakai post yang tidak dimiliki bundle: ' . $record['slug']
				: 'Slug Insight sudah dipakai source identity lain: ' . $record['slug'] );
		}

		$category_id = $this->resolve_category( $record['category_slug'] );
		$author_id = get_current_user_id();
		if ( ! $author_id ) { throw new RuntimeException( 'User administrator untuk author seed Insight tidak tersedia.' ); }

		$postarr = array(
			'post_type' => 'post',
			'post_name' => $record['slug'],
			'post_title' => $record['title'],
			'post_excerpt' => $record['excerpt'],
			'post_content' => wp_kses_post( $record['content_html'] ),
			'post_date' => $record['post_date'],
			'post_date_gmt' => get_gmt_from_date( $record['post_date'] ),
			'post_author' => $author_id,
			'comment_status' => 'closed',
			'ping_status' => 'closed',
		);
		if ( $existing_id ) {
			$postarr['ID'] = $existing_id;
			$current = get_post( $existing_id );
			$postarr['post_status'] = $current instanceof WP_Post ? (string) $current->post_status : 'draft';
			$result = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$postarr['post_status'] = 'draft';
			$result = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $result ) || ! $result ) {
			throw new RuntimeException( 'Gagal menyimpan draft Insight: ' . $record['source_id'] . ( is_wp_error( $result ) ? ' — ' . $result->get_error_message() : '' ) );
		}
		$post_id = (int) $result;
		update_post_meta( $post_id, self::SOURCE_META, $record['source_id'] );
		update_post_meta( $post_id, self::BUNDLE_META, $bundle_id );
		update_post_meta( $post_id, self::SEED_META, 1 );

		$term_result = wp_set_post_categories( $post_id, array( $category_id ), false );
		if ( is_wp_error( $term_result ) ) {
			throw new RuntimeException( 'Gagal menetapkan kategori Insight: ' . $record['source_id'] );
		}

		$media = $this->import_media( $media_record, $post_id, $bundle_id );
		set_post_thumbnail( $post_id, (int) $media['id'] );
		if ( (int) get_post_thumbnail_id( $post_id ) !== (int) $media['id'] ) {
			throw new RuntimeException( 'Gagal menetapkan featured image Insight: ' . $record['source_id'] );
		}
		$this->verify_record( $record, $media_record, $post_id, $bundle_id, false );

		$publish = wp_update_post( array(
			'ID' => $post_id,
			'post_status' => 'publish',
			'post_date' => $record['post_date'],
			'post_date_gmt' => get_gmt_from_date( $record['post_date'] ),
		), true );
		if ( is_wp_error( $publish ) || ! $publish ) {
			throw new RuntimeException( 'Draft Insight lengkap tetapi gagal dipublikasikan: ' . $record['source_id'] );
		}
		$this->verify_record( $record, $media_record, $post_id, $bundle_id, true );

		return array(
			'post_action' => $existing_id ? 'updated' : 'created',
			'media_reused' => ! empty( $media['reused'] ),
		);
	}

	private function resolve_category( $slug ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( $term && ! is_wp_error( $term ) ) { return (int) $term->term_id; }
		$names = array(
			'perawatan' => 'Perawatan','skincare' => 'Skincare','kesehatan-kulit' => 'Kesehatan Kulit',
			'anti-aging' => 'Anti-Aging','rambut' => 'Rambut',
		);
		if ( ! isset( $names[ $slug ] ) ) { throw new RuntimeException( 'Kategori Insight di luar allowlist: ' . $slug ); }
		$created = wp_insert_term( $names[ $slug ], 'category', array( 'slug' => $slug ) );
		if ( is_wp_error( $created ) ) {
			$existing = get_term_by( 'slug', $slug, 'category' );
			if ( $existing && ! is_wp_error( $existing ) ) { return (int) $existing->term_id; }
			throw new RuntimeException( 'Gagal membuat kategori native Insight: ' . $slug );
		}
		$term_id = (int) $created['term_id'];
		if ( function_exists( 'update_term_meta' ) ) {
			update_term_meta( $term_id, '_gloskin_insight_bundle_created', 'gloskin-insights-v1' );
		}
		return $term_id;
	}

	private function import_media( array $record, $post_id, $bundle_id ) {
		$ids = $this->find_media_ids( $record['source_id'] );
		if ( count( $ids ) > 1 ) { throw new RuntimeException( 'Media source identity collision: ' . $record['source_id'] ); }
		if ( 1 === count( $ids ) ) {
			$attachment_id = (int) $ids[0];
			if ( 'attachment' !== get_post_type( $attachment_id ) ) {
				throw new RuntimeException( 'Media source identity tidak menunjuk attachment: ' . $record['source_id'] );
			}
			$this->assert_media_ownership( $attachment_id, $record, $bundle_id );
			$this->apply_media_meta( $attachment_id, $record, $bundle_id );
			$this->assert_local_attachment( $attachment_id );
			return array( 'id' => $attachment_id, 'reused' => true );
		}

		$this->load_media_dependencies();
		$tmp = download_url( $record['source_url'], 60 );
		if ( is_wp_error( $tmp ) ) {
			throw new RuntimeException( 'Gagal mengunduh media Insight ' . $record['source_id'] . ': ' . $tmp->get_error_message() );
		}
		$file = array( 'name' => $record['filename'], 'tmp_name' => $tmp );
		$attachment_id = media_handle_sideload( $file, $post_id, '' );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			throw new RuntimeException( 'Gagal menyimpan media Insight ' . $record['source_id'] . ': ' . $attachment_id->get_error_message() );
		}
		$attachment_id = (int) $attachment_id;
		$this->apply_media_meta( $attachment_id, $record, $bundle_id );
		$this->assert_local_attachment( $attachment_id );
		return array( 'id' => $attachment_id, 'reused' => false );
	}

	private function apply_media_meta( $attachment_id, array $record, $bundle_id ) {
		update_post_meta( $attachment_id, self::MEDIA_SOURCE_META, $record['source_id'] );
		update_post_meta( $attachment_id, self::BUNDLE_META, $bundle_id );
		update_post_meta( $attachment_id, self::SEED_META, 1 );
		update_post_meta( $attachment_id, self::MEDIA_URL_META, esc_url_raw( $record['source_url'] ) );
		update_post_meta( $attachment_id, self::MEDIA_PAGE_META, esc_url_raw( $record['source_page_url'] ) );
		update_post_meta( $attachment_id, self::MEDIA_AUTHOR_META, sanitize_text_field( $record['author'] ) );
		update_post_meta( $attachment_id, self::MEDIA_LICENSE_META, sanitize_text_field( $record['license_note'] ) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $record['alt'] ) );
	}

	private function assert_media_ownership( $attachment_id, array $record, $bundle_id ) {
		if ( (string) get_post_meta( $attachment_id, self::MEDIA_SOURCE_META, true ) !== $record['source_id']
			|| (string) get_post_meta( $attachment_id, self::BUNDLE_META, true ) !== $bundle_id
			|| '1' !== (string) get_post_meta( $attachment_id, self::SEED_META, true ) ) {
			throw new RuntimeException( 'Attachment Insight existing tidak memiliki ownership bundle yang cocok: ' . $record['source_id'] );
		}
	}

	private function assert_local_attachment( $attachment_id ) {
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url || false !== stripos( (string) $url, 'pexels.com' ) ) {
			throw new RuntimeException( 'Featured media Insight belum menjadi attachment lokal.' );
		}
		if ( function_exists( 'get_attached_file' ) ) {
			$file = get_attached_file( $attachment_id );
			if ( ! $file || ! is_file( $file ) ) { throw new RuntimeException( 'File attachment lokal Insight tidak tersedia.' ); }
		}
	}

	private function verify_record( array $record, array $media_record, $post_id, $bundle_id, $require_publish ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type
			|| ( $require_publish && 'publish' !== $post->post_status )
			|| (string) get_post_meta( $post_id, self::SOURCE_META, true ) !== $record['source_id']
			|| (string) get_post_meta( $post_id, self::BUNDLE_META, true ) !== $bundle_id
			|| '1' !== (string) get_post_meta( $post_id, self::SEED_META, true )
			|| (string) $post->post_name !== $record['slug']
			|| '' === trim( (string) $post->post_title )
			|| '' === trim( (string) $post->post_excerpt )
			|| '' === trim( (string) $post->post_content ) ) {
			throw new RuntimeException( 'Verifikasi post Insight gagal: ' . $record['source_id'] );
		}
		$terms = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) || array( $record['category_slug'] ) !== array_values( $terms ) ) {
			throw new RuntimeException( 'Verifikasi kategori Insight gagal: ' . $record['source_id'] );
		}
		$thumbnail_id = (int) get_post_thumbnail_id( $post_id );
		$media_ids = $this->find_media_ids( $media_record['source_id'] );
		if ( 1 !== count( $media_ids ) || $thumbnail_id !== (int) $media_ids[0] ) {
			throw new RuntimeException( 'Verifikasi featured media Insight gagal: ' . $record['source_id'] );
		}
		$this->assert_media_ownership( $thumbnail_id, $media_record, $bundle_id );
		if ( '' === trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) )
			|| '' === trim( (string) get_post_meta( $thumbnail_id, self::MEDIA_URL_META, true ) )
			|| '' === trim( (string) get_post_meta( $thumbnail_id, self::MEDIA_PAGE_META, true ) )
			|| '' === trim( (string) get_post_meta( $thumbnail_id, self::MEDIA_AUTHOR_META, true ) )
			|| '' === trim( (string) get_post_meta( $thumbnail_id, self::MEDIA_LICENSE_META, true ) ) ) {
			throw new RuntimeException( 'Verifikasi provenance media Insight gagal: ' . $media_record['source_id'] );
		}
		$this->assert_local_attachment( $thumbnail_id );
	}

	private function verify_all( array $validated ) {
		$bundle_id = (string) $validated['manifest']['bundle_id'];
		$post_ids = get_posts( array(
			'post_type' => 'post','post_status' => 'publish','meta_key' => self::BUNDLE_META,'meta_value' => $bundle_id,
			'fields' => 'ids','numberposts' => -1,'no_found_rows' => true,
		) );
		$attachment_ids = get_posts( array(
			'post_type' => 'attachment','post_status' => 'any','meta_key' => self::BUNDLE_META,'meta_value' => $bundle_id,
			'fields' => 'ids','numberposts' => -1,'no_found_rows' => true,
		) );
		if ( 13 !== count( (array) $post_ids ) || 13 !== count( (array) $attachment_ids ) ) {
			throw new RuntimeException( 'Verifikasi total object Insight/media tidak cocok dengan manifest.' );
		}
		foreach ( $validated['categories'] as $category ) {
			$term = get_term_by( 'slug', $category['slug'], 'category' );
			if ( ! $term || is_wp_error( $term ) ) {
				throw new RuntimeException( 'Verifikasi kategori native Insight gagal: ' . $category['slug'] );
			}
		}
		foreach ( $validated['posts'] as $record ) {
			$ids = $this->find_post_ids( $record['source_id'] );
			if ( 1 !== count( $ids ) ) { throw new RuntimeException( 'Verifikasi final source post gagal: ' . $record['source_id'] ); }
			$media_record = $validated['media_by_source'][ $record['media_source_id'] ];
			$this->verify_record( $record, $media_record, (int) $ids[0], $bundle_id, true );
			$permalink = get_permalink( (int) $ids[0] );
			if ( ! $permalink ) { throw new RuntimeException( 'Permalink native post Insight tidak tersedia: ' . $record['source_id'] ); }
		}
		foreach ( $validated['media'] as $record ) {
			if ( 1 !== count( $this->find_media_ids( $record['source_id'] ) ) ) {
				throw new RuntimeException( 'Verifikasi final source media gagal: ' . $record['source_id'] );
			}
		}
	}

	private function find_post_ids( $source_id ) {
		return array_map( 'intval', get_posts( array(
			'post_type' => 'post','post_status' => 'any','meta_key' => self::SOURCE_META,'meta_value' => $source_id,
			'fields' => 'ids','numberposts' => 2,'no_found_rows' => true,
		) ) );
	}

	private function find_media_ids( $source_id ) {
		return array_map( 'intval', get_posts( array(
			'post_type' => 'attachment','post_status' => 'any','meta_key' => self::MEDIA_SOURCE_META,'meta_value' => $source_id,
			'fields' => 'ids','numberposts' => 2,'no_found_rows' => true,
		) ) );
	}

	private function load_media_dependencies() {
		if ( ! function_exists( 'download_url' ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; }
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	private function response_state( array $state ) {
		$expected = max( 1, (int) $state['expected_posts'] );
		$state['progress_percent'] = min( 100, (int) floor( ( (int) $state['processed_posts'] / $expected ) * 100 ) );
		if ( in_array( $state['status'], array( 'verifying','consumed' ), true ) ) { $state['progress_percent'] = 100; }
		return $state;
	}

	private function acquire_lock() {
		$existing = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $existing ) && ! empty( $existing['created_at'] ) && ( time() - (int) $existing['created_at'] ) > self::LOCK_TTL ) {
			delete_option( self::LOCK_OPTION );
		}
		$token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'gloskin-insight-', true );
		return add_option( self::LOCK_OPTION, array( 'token' => $token, 'created_at' => time() ), '', false ) ? $token : '';
	}

	private function lock_is_active() {
		$lock = get_option( self::LOCK_OPTION, array() );
		return is_array( $lock ) && ! empty( $lock['created_at'] ) && ( time() - (int) $lock['created_at'] ) <= self::LOCK_TTL;
	}

	private function release_lock( $token ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}

	private function save_state( array $state ) {
		$state['updated_at'] = time();
		update_option( self::STATE_OPTION, $state, false );
	}
}
// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
