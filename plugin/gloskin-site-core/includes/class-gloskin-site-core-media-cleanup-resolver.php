<?php
/**
 * Conservative, resumable image-attachment cleanup resolver.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Media_Cleanup_Resolver {
	const REVISION        = '2026-08-20-media-cleanup-v1';
	const STATE_OPTION    = 'gloskin_site_core_media_cleanup_20260820_state';
	const LOCK_OPTION     = 'gloskin_site_core_media_cleanup_20260820_lock';
	const MANIFEST_OPTION = 'gloskin_site_core_media_cleanup_20260820_manifest';
	const BATCH_SIZE      = 15;
	const LOCK_TTL        = 90;
	const RECENT_DAYS     = 30;
	const REVIEW_PAGE_SIZE = 25;
	const MAX_MATCHES_PER_STORE = 40;
	const MAX_CODE_FILES  = 1200;
	const MAX_CODE_BYTES  = 2097152;
	const REQUEST_BUDGET_SECONDS = 12.0;

	/** @return array<string,mixed> */
	public function get_state() {
		$stored = get_option( self::STATE_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$defaults = array(
			'revision' => self::REVISION,
			'blog_id' => function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1,
			'status' => 'pending',
			'cursor' => 0,
			'processed' => 0,
			'total' => 0,
			'current_file' => '',
			'counts' => array( 'used' => 0, 'protected' => 0, 'ambiguous' => 0, 'confirmed-unused' => 0 ),
			'estimated_bytes' => 0,
			'actual_bytes' => 0,
			'deletion_cursor' => 0,
			'verification_cursor' => 0,
			'deleted' => array(),
			'skipped' => array(),
			'failed' => array(),
			'results' => array(),
			'warnings' => array(),
			'manifest_hash' => '',
			'manifest_token' => '',
			'protected_boundary' => '',
			'paused' => false,
			'resume_status' => '',
			'last_error' => '',
			'started_at' => 0,
			'updated_at' => 0,
			'consumed_at' => 0,
		);
		$state = array_merge( $defaults, $stored );
		return self::REVISION === (string) $state['revision'] ? $state : $defaults;
	}

	/** @return bool */
	public function is_consumed() { return 'consumed' === (string) $this->get_state()['status']; }

	/** @return array<string,mixed> */
	public function summary() {
		$state = $this->get_state();
		unset( $state['results'], $state['protected_boundary'] );
		$state['batch_size'] = self::BATCH_SIZE;
		$state['review_pages'] = max( 1, (int) ceil( max( 0, (int) $state['total'] ) / self::REVIEW_PAGE_SIZE ) );
		return $state;
	}

	/**
	 * Dry-run indexing batch. This method never deletes.
	 *
	 * @return array<string,mixed>
	 */
	public function index_batch() {
		$state = $this->get_state();
		if ( 'consumed' === (string) $state['status'] || 'review_ready' === (string) $state['status'] ) { return $this->summary(); }
		if ( 'failed' === (string) $state['status'] && in_array( (string) $state['resume_status'], array( 'pending', 'indexing' ), true ) ) { $state['status'] = (string) $state['resume_status']; }
		if ( ! in_array( (string) $state['status'], array( 'pending', 'indexing' ), true ) ) { throw new RuntimeException( 'invalid_state: Indexing tidak tersedia pada state ini.' ); }
		$token = $this->acquire_lock();
		if ( '' === $token ) { throw new RuntimeException( 'resolver_locked: Resolver sedang diproses oleh request lain.' ); }

		try {
			if ( 'pending' === (string) $state['status'] ) {
				if ( get_option( self::MANIFEST_OPTION, false ) ) { throw new RuntimeException( 'stale_manifest: Manifest lama terdeteksi; resolver menolak menimpanya.' ); }
				$state['status'] = 'indexing';
				$state['started_at'] = time();
				$state['last_error'] = '';
				$this->save_state( $state );
				$state['total'] = $this->image_attachment_count();
				$state['protected_boundary'] = $this->protected_boundary_fingerprint();
			}
			$state['status'] = 'indexing';
			$this->save_state( $state );

			$ids = $this->next_image_ids( (int) $state['cursor'], self::BATCH_SIZE );
			$batch_started = microtime( true );
			$completed_ids = 0;
			foreach ( $ids as $id ) {
				if ( $completed_ids > 0 && microtime( true ) - $batch_started >= self::REQUEST_BUDGET_SECONDS ) { break; }
				$result = $this->classify_attachment( $id );
				$state['results'][] = $result;
				$state['warnings'] = array_values( array_unique( array_merge( (array) $state['warnings'], (array) $result['warnings'] ) ) );
				$classification = (string) $result['classification'];
				if ( isset( $state['counts'][ $classification ] ) ) { $state['counts'][ $classification ]++; }
				if ( 'confirmed-unused' === $classification ) { $state['estimated_bytes'] += (int) $result['bytes']; }
				$state['cursor'] = $id;
				$state['processed']++;
				$state['current_file'] = (string) $result['filename'];
				$completed_ids++;
			}

			if ( $completed_ids === count( $ids ) && count( $ids ) < self::BATCH_SIZE ) {
				$this->seal_manifest( $state );
				$state['status'] = 'review_ready';
				$state['current_file'] = '';
			}
			$state['last_error'] = '';
			$state['resume_status'] = '';
			$state['updated_at'] = time();
			$this->save_state( $state );
			$this->release_lock( $token );
			return $this->summary();
		} catch ( Throwable $error ) {
			$this->release_lock( $token );
			$this->fail_state( $error->getMessage() );
			throw new RuntimeException( $error->getMessage(), 0, $error );
		}
	}

	/**
	 * Delete one immutable-manifest batch after immediate revalidation.
	 *
	 * @param int    $client_cursor Server-issued cursor echoed by the client.
	 * @param string $manifest_token Server-issued opaque token.
	 * @param bool   $backup_confirmed Explicit permanent-deletion confirmation.
	 * @return array<string,mixed>
	 */
	public function delete_batch( $client_cursor, $manifest_token, $backup_confirmed ) {
		$state = $this->get_state();
		if ( 'consumed' === (string) $state['status'] ) { return $this->summary(); }
		if ( 'failed' === (string) $state['status'] && in_array( (string) $state['resume_status'], array( 'review_ready', 'deleting' ), true ) ) { $state['status'] = (string) $state['resume_status']; }
		if ( ! $backup_confirmed ) { throw new RuntimeException( 'confirmation_required: Konfirmasi backup wajib diberikan.' ); }
		if ( ! in_array( (string) $state['status'], array( 'review_ready', 'deleting' ), true ) ) { throw new RuntimeException( 'invalid_state: Penghapusan belum tersedia.' ); }
		$manifest = $this->validated_manifest( $state, $manifest_token );
		if ( (int) $client_cursor !== (int) $state['deletion_cursor'] ) { return $this->summary(); }
		$token = $this->acquire_lock();
		if ( '' === $token ) { throw new RuntimeException( 'resolver_locked: Resolver sedang diproses oleh request lain.' ); }

		try {
			$state['status'] = 'deleting';
			$state['paused'] = false;
			$this->save_state( $state );
			$candidates = (array) $manifest['candidates'];
			$batch = array_slice( $candidates, (int) $state['deletion_cursor'], self::BATCH_SIZE );
			$batch_started = microtime( true );
			$completed_candidates = 0;
			foreach ( $batch as $candidate ) {
				if ( $completed_candidates > 0 && microtime( true ) - $batch_started >= self::REQUEST_BUDGET_SECONDS ) { break; }
				$id = absint( $candidate['id'] );
				$state['current_file'] = (string) $candidate['filename'];
				$fresh = $this->classify_attachment( $id );
				if ( 'confirmed-unused' !== (string) $fresh['classification'] || ! hash_equals( (string) $candidate['fingerprint'], (string) $fresh['fingerprint'] ) ) {
					$state['skipped'][ $id ] = array( 'reason' => 'Reference, metadata, atau file berubah setelah dry-run.', 'classification' => (string) $fresh['classification'] );
					$state['deletion_cursor']++;
					$completed_candidates++;
					continue;
				}
				$post = get_post( $id );
				if ( ! ( $post instanceof WP_Post ) || 'attachment' !== (string) $post->post_type || 0 !== strpos( (string) $post->post_mime_type, 'image/' ) || ! $this->is_current_upload_file( (string) get_attached_file( $id ) ) ) {
					$state['skipped'][ $id ] = array( 'reason' => 'Attachment gagal melewati guard tipe/path terakhir.' );
					$state['deletion_cursor']++;
					$completed_candidates++;
					continue;
				}
				$deleted = wp_delete_attachment( $id, true );
				if ( $deleted ) {
					$state['deleted'][ $id ] = array( 'filename' => (string) $candidate['filename'], 'bytes' => (int) $candidate['bytes'], 'deleted_at' => time() );
					$state['actual_bytes'] += (int) $candidate['bytes'];
				} else {
					$state['failed'][ $id ] = array( 'reason' => 'WordPress tidak mengonfirmasi penghapusan.' );
				}
				$state['deletion_cursor']++;
				$completed_candidates++;
			}
			if ( (int) $state['deletion_cursor'] >= count( $candidates ) ) {
				$state['status'] = 'verifying';
				$state['verification_cursor'] = 0;
				$state['current_file'] = '';
			}
			$state['last_error'] = '';
			$state['resume_status'] = '';
			$state['updated_at'] = time();
			$this->save_state( $state );
			$this->release_lock( $token );
			return $this->summary();
		} catch ( Throwable $error ) {
			$this->release_lock( $token );
			$this->fail_state( $error->getMessage() );
			throw new RuntimeException( $error->getMessage(), 0, $error );
		}
	}

	/** @return array<string,mixed> */
	public function verify_batch() {
		$state = $this->get_state();
		if ( 'consumed' === (string) $state['status'] ) { return $this->summary(); }
		if ( 'failed' === (string) $state['status'] && 'verifying' === (string) $state['resume_status'] ) { $state['status'] = 'verifying'; }
		if ( 'verifying' !== (string) $state['status'] ) { throw new RuntimeException( 'invalid_state: Verifikasi belum tersedia.' ); }
		$token = $this->acquire_lock();
		if ( '' === $token ) { throw new RuntimeException( 'resolver_locked: Resolver sedang diproses oleh request lain.' ); }
		try {
			$state['status'] = 'verifying';
			$this->save_state( $state );
			$results = (array) $state['results'];
			$batch = array_slice( $results, (int) $state['verification_cursor'], self::BATCH_SIZE );
			$batch_started = microtime( true );
			$completed_results = 0;
			foreach ( $batch as $result ) {
				if ( $completed_results > 0 && microtime( true ) - $batch_started >= self::REQUEST_BUDGET_SECONDS ) { break; }
				$id = absint( $result['id'] );
				if ( isset( $state['deleted'][ $id ] ) ) {
					if ( get_post( $id ) ) { throw new RuntimeException( 'verification_failed: Attachment terhapus masih memiliki record.' ); }
					foreach ( (array) $result['files'] as $deleted_file ) { if ( file_exists( $deleted_file ) ) { throw new RuntimeException( 'verification_failed: File attachment terhapus masih tersisa.' ); } }
				} else {
					if ( ! get_post( $id ) ) { throw new RuntimeException( 'verification_failed: Attachment non-kandidat ikut terhapus.' ); }
					if ( ! hash_equals( (string) $result['fingerprint'], $this->attachment_fingerprint( $id ) ) ) { throw new RuntimeException( 'verification_failed: Fingerprint attachment terlindungi berubah.' ); }
				}
				$state['verification_cursor']++;
				$completed_results++;
			}
			if ( (int) $state['verification_cursor'] >= count( $results ) ) {
				if ( ! hash_equals( (string) $state['protected_boundary'], $this->protected_boundary_fingerprint() ) ) { throw new RuntimeException( 'verification_failed: Registry media terlindungi berubah.' ); }
				$state['status'] = 'consumed';
				$state['consumed_at'] = time();
				$state['current_file'] = '';
			}
			$state['last_error'] = '';
			$state['resume_status'] = '';
			$state['updated_at'] = time();
			$this->save_state( $state );
			$this->release_lock( $token );
			return $this->summary();
		} catch ( Throwable $error ) {
			$this->release_lock( $token );
			$this->fail_state( $error->getMessage() );
			throw new RuntimeException( $error->getMessage(), 0, $error );
		}
	}

	/** @return array<string,mixed> */
	public function pause() {
		$state = $this->get_state();
		if ( 'consumed' !== (string) $state['status'] ) { $state['paused'] = true; $state['updated_at'] = time(); $this->save_state( $state ); }
		return $this->summary();
	}

	/** @return array<string,mixed> */
	public function resume() {
		$state = $this->get_state();
		if ( 'consumed' !== (string) $state['status'] ) { $state['paused'] = false; $state['updated_at'] = time(); $this->save_state( $state ); }
		return $this->summary();
	}

	/** @param int $page Page number. @return array<string,mixed> */
	public function review_page( $page ) {
		$state = $this->get_state();
		$review_status = 'failed' === (string) $state['status'] ? (string) $state['resume_status'] : (string) $state['status'];
		if ( ! in_array( $review_status, array( 'review_ready', 'deleting', 'verifying', 'consumed' ), true ) ) { return array( 'items' => array(), 'page' => 1, 'pages' => 1 ); }
		$page = max( 1, absint( $page ) );
		$results = (array) $state['results'];
		$items = array_slice( $results, ( $page - 1 ) * self::REVIEW_PAGE_SIZE, self::REVIEW_PAGE_SIZE );
		foreach ( $items as &$item ) { unset( $item['fingerprint'], $item['files'] ); }
		return array( 'items' => $items, 'page' => $page, 'pages' => max( 1, (int) ceil( count( $results ) / self::REVIEW_PAGE_SIZE ) ) );
	}

	/** @return array<string,mixed> */
	public function export_data() {
		$state = $this->get_state();
		$manifest = get_option( self::MANIFEST_OPTION, array() );
		unset( $state['manifest_token'], $state['protected_boundary'] );
		foreach ( $state['results'] as &$result ) { unset( $result['fingerprint'], $result['files'] ); }
		if ( is_array( $manifest ) ) { unset( $manifest['token'] ); }
		if ( ! empty( $manifest['candidates'] ) ) { foreach ( $manifest['candidates'] as &$candidate ) { unset( $candidate['fingerprint'], $candidate['files'] ); } }
		return array( 'state' => $state, 'manifest' => $manifest );
	}

	/** @param int $id Attachment ID. @return array<string,mixed> */
	private function classify_attachment( $id ) {
		$post = get_post( $id );
		$base = array( 'id' => absint( $id ), 'filename' => '', 'mime' => '', 'date' => '', 'dimensions' => '', 'bytes' => 0, 'classification' => 'ambiguous', 'reason' => '', 'references' => array(), 'warnings' => array(), 'fingerprint' => '', 'files' => array() );
		if ( ! ( $post instanceof WP_Post ) || 'attachment' !== (string) $post->post_type || 0 !== strpos( (string) $post->post_mime_type, 'image/' ) ) { $base['reason'] = 'Record bukan attachment image yang valid.'; return $base; }
		$base['mime'] = (string) $post->post_mime_type;
		$base['date'] = (string) $post->post_date_gmt;
		$base['fingerprint'] = $this->attachment_fingerprint( $id );
		$file = (string) get_attached_file( $id );
		$base['filename'] = basename( $file );
		if ( ! $this->is_current_upload_file( $file ) || ! is_file( $file ) || ! is_readable( $file ) || ! is_writable( $file ) || ! is_writable( dirname( $file ) ) ) { $base['reason'] = 'Path/file attachment atau permission tidak dapat dibuktikan aman di uploads site aktif.'; return $base; }
		$metadata = wp_get_attachment_metadata( $id );
		if ( ! is_array( $metadata ) || empty( $metadata['file'] ) ) { $base['reason'] = 'Metadata attachment hilang atau malformed.'; return $base; }
		$base['dimensions'] = isset( $metadata['width'], $metadata['height'] ) ? absint( $metadata['width'] ) . '×' . absint( $metadata['height'] ) : '';
		$files = $this->attachment_files( $file, $metadata );
		$base['files'] = $files;
		foreach ( $files as $candidate_file ) {
			if ( ! $this->is_current_upload_file( $candidate_file ) || ! is_file( $candidate_file ) || ! is_readable( $candidate_file ) || ! is_writable( $candidate_file ) ) { $base['reason'] = 'Relasi file asli/generated size tidak konsisten atau permission tidak aman.'; return $base; }
			$base['bytes'] += (int) filesize( $candidate_file );
		}
		$recent_cutoff = time() - ( self::RECENT_DAYS * DAY_IN_SECONDS );
		$uploaded = strtotime( (string) $post->post_date_gmt . ' UTC' );
		$modified = strtotime( (string) $post->post_modified_gmt . ' UTC' );
		if ( max( (int) $uploaded, (int) $modified ) >= $recent_cutoff ) { $base['classification'] = 'protected'; $base['reason'] = 'Diunggah atau diubah dalam 30 hari terakhir.'; return $base; }

		$system_reason = $this->system_media_reason( $id );
		if ( '' !== $system_reason ) { $base['classification'] = 'protected'; $base['reason'] = $system_reason; return $base; }
		$scan = $this->scan_references( $id, $file, $metadata );
		$base['references'] = $scan['hard'];
		$base['warnings'] = array_merge( $scan['soft'], $scan['warnings'] );
		if ( $scan['hard'] ) { $base['classification'] = 'used'; $base['reason'] = 'Referensi hard ditemukan.'; return $base; }
		if ( $scan['soft'] || $scan['warnings'] ) { $base['classification'] = 'ambiguous'; $base['reason'] = 'Referensi lemah atau pemindaian tidak lengkap; fail closed.'; return $base; }
		$base['classification'] = 'confirmed-unused';
		$base['reason'] = 'Lebih dari 30 hari dan tidak ditemukan referensi hard, soft, registry, content, option, meta, WooCommerce, atau codebase.';
		return $base;
	}

	/** @param int $id @param string $file @param array<string,mixed> $metadata @return array<string,array<int,string>> */
	private function scan_references( $id, $file, array $metadata ) {
		global $wpdb;
		$hard = array(); $soft = array(); $warnings = array();
		$known_meta = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id <> %d AND (meta_key = '_thumbnail_id' OR meta_key = '_product_image_gallery' OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s) AND meta_value LIKE %s LIMIT %d", $id, '%image%', '%gallery%', '%media%', '%' . $wpdb->esc_like( (string) $id ) . '%', self::MAX_MATCHES_PER_STORE + 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'postmeta registry query failed'; }
		foreach ( array_slice( (array) $known_meta, 0, self::MAX_MATCHES_PER_STORE ) as $row ) {
			if ( $this->structured_value_contains_id( (string) $row->meta_value, $id ) ) { $hard[] = 'postmeta:' . absint( $row->post_id ) . ':' . sanitize_key( $row->meta_key ); }
		}
		if ( count( (array) $known_meta ) > self::MAX_MATCHES_PER_STORE ) { $warnings[] = 'postmeta registry match limit exceeded'; }

		$tokens = $this->attachment_reference_tokens( $id, $file, $metadata );
		foreach ( $tokens as $token_value => $strength ) {
			$like = '%' . $wpdb->esc_like( $token_value ) . '%';
			$post_rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID <> %d AND (post_content LIKE %s OR post_excerpt LIKE %s) LIMIT %d", $id, $like, $like, self::MAX_MATCHES_PER_STORE + 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'content query failed'; }
			$this->append_matches( $post_rows, 'content', $strength, $hard, $soft, $warnings );
			$meta_rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id AS ID FROM {$wpdb->postmeta} WHERE post_id <> %d AND meta_value LIKE %s LIMIT %d", $id, $like, self::MAX_MATCHES_PER_STORE + 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'postmeta query failed'; }
			$this->append_matches( $meta_rows, 'postmeta', $strength, $hard, $soft, $warnings );
			$term_rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id AS ID FROM {$wpdb->termmeta} WHERE meta_value LIKE %s LIMIT %d", $like, self::MAX_MATCHES_PER_STORE + 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'termmeta query failed'; }
			$this->append_matches( $term_rows, 'termmeta', $strength, $hard, $soft, $warnings );
			$option_rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_id AS ID FROM {$wpdb->options} WHERE option_name NOT IN (%s,%s,%s) AND option_value LIKE %s LIMIT %d", self::STATE_OPTION, self::LOCK_OPTION, self::MANIFEST_OPTION, $like, self::MAX_MATCHES_PER_STORE + 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'option query failed'; }
			$this->append_matches( $option_rows, 'option', $strength, $hard, $soft, $warnings );
		}
		$code = $this->scan_active_code( array_keys( $tokens ) );
		$hard = array_merge( $hard, $code['matches'] );
		$warnings = array_merge( $warnings, $code['warnings'] );
		return array( 'hard' => array_values( array_unique( $hard ) ), 'soft' => array_values( array_unique( $soft ) ), 'warnings' => array_values( array_unique( $warnings ) ) );
	}

	/** @param array<int,object> $rows @param string $location @param string $strength @param array<int,string> $hard @param array<int,string> $soft @param array<int,string> $warnings @return void */
	private function append_matches( $rows, $location, $strength, array &$hard, array &$soft, array &$warnings ) {
		$rows = (array) $rows;
		if ( count( $rows ) > self::MAX_MATCHES_PER_STORE ) { $warnings[] = $location . ' match limit exceeded'; }
		foreach ( array_slice( $rows, 0, self::MAX_MATCHES_PER_STORE ) as $row ) {
			$entry = $location . ':' . absint( $row->ID );
			if ( 'hard' === $strength ) { $hard[] = $entry; } else { $soft[] = $entry; }
		}
	}

	/** @param int $id @param string $file @param array<string,mixed> $metadata @return array<string,string> */
	private function attachment_reference_tokens( $id, $file, array $metadata ) {
		$tokens = array();
		$url = (string) wp_get_attachment_url( $id );
		$relative = isset( $metadata['file'] ) ? ltrim( (string) $metadata['file'], '/' ) : '';
		foreach ( array( $url, $relative, basename( $file ), 'wp-image-' . $id, '"id":' . $id, '"mediaId":' . $id, '"media_id":' . $id, '"ids":[' . $id, ',' . $id . ']', ',' . $id . ',', 'ids="' . $id, 'attachment_id";i:' . $id ) as $value ) { if ( '' !== $value ) { $tokens[ $value ] = 'hard'; } }
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( ! is_array( $size ) || empty( $size['file'] ) ) { continue; }
				$tokens[ (string) $size['file'] ] = 'hard';
				if ( '' !== $url ) { $tokens[ trailingslashit( dirname( $url ) ) . (string) $size['file'] ] = 'hard'; }
			}
		}
		$tokens[ (string) $id ] = 'soft';
		return $tokens;
	}

	/** @param array<int,string> $tokens @return array{matches:array<int,string>,warnings:array<int,string>} */
	private function scan_active_code( array $tokens ) {
		$matches = array(); $warnings = array(); $count = 0;
		$root = realpath( dirname( __DIR__ ) );
		if ( ! $root || ! is_dir( $root ) || ! is_readable( $root ) ) { return array( 'matches' => array(), 'warnings' => array( 'active Gloskin code root unreadable' ) ); }
		try { $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ); }
		catch ( Throwable $error ) { return array( 'matches' => array(), 'warnings' => array( 'active Gloskin code scan unavailable' ) ); }
		foreach ( $iterator as $file_info ) {
			if ( ++$count > self::MAX_CODE_FILES ) { $warnings[] = 'active code file limit exceeded'; break; }
			if ( ! $file_info->isFile() || $file_info->isLink() || $file_info->getSize() > self::MAX_CODE_BYTES || ! preg_match( '/\.(?:php|css|js|json|html|txt|md)$/i', $file_info->getFilename() ) ) { continue; }
			$source = file_get_contents( $file_info->getPathname() );
			if ( false === $source ) { $warnings[] = 'unreadable active code file'; continue; }
			foreach ( $tokens as $token ) { if ( '' !== $token && false !== strpos( $source, $token ) ) { $matches[] = 'code:' . ltrim( str_replace( $root, '', $file_info->getPathname() ), DIRECTORY_SEPARATOR ); break; } }
		}
		return array( 'matches' => array_values( array_unique( $matches ) ), 'warnings' => array_values( array_unique( $warnings ) ) );
	}

	/** @param int $id @return string */
	private function system_media_reason( $id ) {
		if ( $id === absint( get_option( 'site_icon' ) ) || $id === absint( get_theme_mod( 'custom_logo' ) ) ) { return 'Site icon, favicon, atau custom logo.'; }
		$meta = get_post_meta( $id );
		foreach ( array_keys( (array) $meta ) as $key ) { if ( 0 === strpos( (string) $key, '_gloskin_' ) || 0 === strpos( (string) $key, 'gloskin_' ) ) { return 'Attachment memiliki provenance/registry Gloskin.'; } }
		return '';
	}

	/** @param string $value @param int $id @return bool */
	private function structured_value_contains_id( $value, $id ) {
		if ( (string) $id === trim( $value ) ) { return true; }
		$decoded = maybe_unserialize( $value );
		return $this->recursive_contains_id( $decoded, $id );
	}

	/** @param mixed $value @param int $id @return bool */
	private function recursive_contains_id( $value, $id ) {
		if ( is_array( $value ) || is_object( $value ) ) { foreach ( (array) $value as $child ) { if ( $this->recursive_contains_id( $child, $id ) ) { return true; } } return false; }
		if ( is_string( $value ) && ( '{' === substr( trim( $value ), 0, 1 ) || '[' === substr( trim( $value ), 0, 1 ) ) ) { $json = json_decode( $value, true ); if ( JSON_ERROR_NONE !== json_last_error() ) { return false; } return $this->recursive_contains_id( $json, $id ); }
		return is_numeric( $value ) && absint( $value ) === $id;
	}

	/** @param string $file @param array<string,mixed> $metadata @return array<int,string> */
	private function attachment_files( $file, array $metadata ) {
		$files = array( $file ); $dir = dirname( $file );
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) { foreach ( $metadata['sizes'] as $size ) { if ( is_array( $size ) && ! empty( $size['file'] ) ) { $files[] = $dir . DIRECTORY_SEPARATOR . basename( (string) $size['file'] ); } } }
		if ( ! empty( $metadata['original_image'] ) ) { $files[] = $dir . DIRECTORY_SEPARATOR . basename( (string) $metadata['original_image'] ); }
		return array_values( array_unique( $files ) );
	}

	/** @param string $file @return bool */
	private function is_current_upload_file( $file ) {
		$uploads = wp_get_upload_dir();
		$base = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		$resolved = realpath( $file );
		return $base && $resolved && 0 === strpos( $resolved . DIRECTORY_SEPARATOR, trailingslashit( $base ) ) && ! is_link( $file );
	}

	/** @param int $id @return string */
	private function attachment_fingerprint( $id ) {
		$post = get_post( $id );
		return $post ? hash( 'sha256', wp_json_encode( array( 'post' => get_object_vars( $post ), 'meta' => get_post_meta( $id ) ) ) ) : '';
	}

	/** @return string */
	private function protected_boundary_fingerprint() {
		global $wpdb;
		$options = array( 'site_icon' => get_option( 'site_icon' ), 'theme_mods' => get_theme_mods(), 'woocommerce_shop_page_id' => get_option( 'woocommerce_shop_page_id' ), 'woocommerce_cart_page_id' => get_option( 'woocommerce_cart_page_id' ), 'woocommerce_checkout_page_id' => get_option( 'woocommerce_checkout_page_id' ), 'woocommerce_myaccount_page_id' => get_option( 'woocommerce_myaccount_page_id' ) );
		$postmeta = $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ('_thumbnail_id','_product_image_gallery') OR meta_key LIKE '%gloskin%image%' OR meta_key LIKE '%gloskin%gallery%' ORDER BY post_id, meta_key", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'scan_failed: Protected postmeta fingerprint tidak lengkap.' ); }
		$termmeta = $wpdb->get_results( "SELECT term_id, meta_key, meta_value FROM {$wpdb->termmeta} WHERE meta_key LIKE '%image%' OR meta_key LIKE '%thumbnail%' OR meta_key LIKE '%gallery%' ORDER BY term_id, meta_key", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'scan_failed: Protected termmeta fingerprint tidak lengkap.' ); }
		return hash( 'sha256', wp_json_encode( array( 'options' => $options, 'postmeta' => $postmeta, 'termmeta' => $termmeta ) ) );
	}

	/** @param array<string,mixed> $state @return void */
	private function seal_manifest( array &$state ) {
		$candidates = array();
		foreach ( (array) $state['results'] as $result ) { if ( 'confirmed-unused' === (string) $result['classification'] ) { $candidates[] = array_intersect_key( $result, array_flip( array( 'id', 'filename', 'mime', 'date', 'dimensions', 'bytes', 'classification', 'reason', 'fingerprint', 'files' ) ) ); } }
		$payload = array( 'revision' => self::REVISION, 'blog_id' => function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1, 'created_at' => time(), 'candidates' => $candidates );
		$hash = hash( 'sha256', wp_json_encode( $payload ) );
		$payload['hash'] = $hash;
		$payload['token'] = wp_generate_password( 48, false, false );
		if ( ! add_option( self::MANIFEST_OPTION, $payload, '', 'no' ) ) { throw new RuntimeException( 'manifest_failed: Immutable candidate manifest tidak dapat disimpan.' ); }
		$state['manifest_hash'] = $hash;
		$state['manifest_token'] = $payload['token'];
	}

	/** @param array<string,mixed> $state @param string $token @return array<string,mixed> */
	private function validated_manifest( array $state, $token ) {
		$manifest = get_option( self::MANIFEST_OPTION, array() );
		if ( ! is_array( $manifest ) || ! isset( $manifest['revision'], $manifest['blog_id'], $manifest['created_at'], $manifest['candidates'], $manifest['hash'], $manifest['token'] ) || ! is_array( $manifest['candidates'] ) || self::REVISION !== (string) $manifest['revision'] || (int) $manifest['blog_id'] !== ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 ) || '' === (string) $manifest['token'] || ! hash_equals( (string) $manifest['token'], (string) $token ) || ! hash_equals( (string) $state['manifest_hash'], (string) $manifest['hash'] ) ) { throw new RuntimeException( 'manifest_invalid: Manifest/token/site tidak valid.' ); }
		$payload = array( 'revision' => $manifest['revision'], 'blog_id' => $manifest['blog_id'], 'created_at' => $manifest['created_at'], 'candidates' => $manifest['candidates'] );
		if ( ! hash_equals( (string) $manifest['hash'], hash( 'sha256', wp_json_encode( $payload ) ) ) ) { throw new RuntimeException( 'manifest_invalid: Hash manifest berubah.' ); }
		return $manifest;
	}

	/** @return int */
	private function image_attachment_count() {
		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'scan_failed: Daftar attachment image tidak dapat dihitung.' ); }
		return (int) $count;
	}

	/** @param int $cursor @param int $limit @return array<int,int> */
	private function next_image_ids( $cursor, $limit ) {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%' AND ID > %d ORDER BY ID ASC LIMIT %d", absint( $cursor ), absint( $limit ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'scan_failed: Batch attachment image tidak lengkap.' ); }
		return array_map( 'absint', (array) $rows );
	}

	/** @return string */
	private function acquire_lock() {
		$current = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $current ) && ! empty( $current['created'] ) && time() - (int) $current['created'] < self::LOCK_TTL ) { return ''; }
		if ( $current ) { delete_option( self::LOCK_OPTION ); }
		$token = wp_generate_uuid4();
		return add_option( self::LOCK_OPTION, array( 'token' => $token, 'created' => time() ), '', 'no' ) ? $token : '';
	}

	/** @param string $token @return void */
	private function release_lock( $token ) {
		$current = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], (string) $token ) ) { delete_option( self::LOCK_OPTION ); }
	}

	/** @param array<string,mixed> $state @return void */
	private function save_state( array $state ) { update_option( self::STATE_OPTION, $state, false ); }

	/** @param string $message @return void */
	private function fail_state( $message ) {
		$state = $this->get_state();
		$state['resume_status'] = (string) $state['status']; $state['status'] = 'failed'; $state['last_error'] = (string) $message; $state['updated_at'] = time();
		$this->save_state( $state );
	}
}
