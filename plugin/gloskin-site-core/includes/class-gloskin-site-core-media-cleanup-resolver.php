<?php
/**
 * Conservative, resumable image-attachment cleanup resolver.
 *
 * Simplifications vs v1:
 *  - Frozen scan boundary (scan_max_attachment_id): images uploaded during a
 *    scan never enter the current run.
 *  - Short batches (~4 s budget) with calm client-side timeout (not rAF).
 *  - Removed per-attachment recursive codebase scan (v1 approach).
 *  - Removed naked numeric-ID broad LIKE searches.
 *  - results[] stores only confirmed-unused candidates (not every attachment).
 *  - Candidate-scoped verification only.
 *  - Final status is 'complete'; tool remains available for Start New Scan.
 *  - No pause/resume complexity.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Media_Cleanup_Resolver {
	const REVISION         = '2026-08-20-media-cleanup-v2';
	const STATE_OPTION     = 'gloskin_site_core_media_cleanup_20260820_state';
	const LOCK_OPTION      = 'gloskin_site_core_media_cleanup_20260820_lock';
	const MANIFEST_OPTION  = 'gloskin_site_core_media_cleanup_20260820_manifest';
	const BATCH_SIZE       = 8;
	const LOCK_TTL         = 25;
	const RECENT_DAYS     = 30;
	const REVIEW_PAGE_SIZE = 25;
	const MAX_MATCHES_PER_STORE = 40;
	const REQUEST_BUDGET_SECONDS = 4.0;

	/* -----------------------------------------------------------------------
	 * Public API
	 * --------------------------------------------------------------------- */

	/** @return array<string,mixed> */
	public function get_state() {
		$stored   = get_option( self::STATE_OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = $this->default_state();
		$state    = array_merge( $defaults, $stored );
		return self::REVISION === (string) $state['revision'] ? $state : $defaults;
	}

	/**
	 * Whether the tool has completed its current scan run.
	 * Returns true only while status=complete; resets to false after reset_scan().
	 *
	 * @return bool
	 */
	public function is_complete() {
		return 'complete' === (string) $this->get_state()['status'];
	}

	/** @return array<string,mixed> */
	public function summary() {
		$state = $this->get_state();
		$state['candidate_count'] = count( (array) $state['results'] );
		unset( $state['results'] );
		$state['batch_size']    = self::BATCH_SIZE;
		$state['review_pages']  = max( 1, (int) ceil( max( 0, (int) $state['candidate_count'] ) / self::REVIEW_PAGE_SIZE ) );
		return $state;
	}

	/**
	 * Start a new scan: clear previous run state + manifest, freeze boundary.
	 * Must be called by admin layer after owner + nonce validation.
	 *
	 * @return array<string,mixed>
	 * @throws RuntimeException If lock cannot be acquired or manifest still exists.
	 */
	public function reset_scan() {
		$token = $this->acquire_lock();
		if ( '' === $token ) { throw new RuntimeException( 'resolver_locked: Resolver sedang diproses oleh request lain.' ); }
		try {
			delete_option( self::MANIFEST_OPTION );
			$new_state = $this->default_state();
			$new_state['status'] = 'pending';
			$this->save_state( $new_state );
			$this->release_lock( $token );
			return $this->summary();
		} catch ( Throwable $error ) {
			$this->release_lock( $token );
			throw new RuntimeException( $error->getMessage(), 0, $error );
		}
	}

	/**
	 * Dry-run indexing batch. This method never deletes.
	 *
	 * @return array<string,mixed>
	 * @throws RuntimeException On scan failure.
	 */
	public function index_batch() {
		$state = $this->get_state();
		if ( 'complete' === (string) $state['status'] ) { return $this->summary(); }
		if ( 'failed' === (string) $state['status'] && in_array( (string) $state['failed_from'], array( 'pending', 'indexing' ), true ) ) {
			$state['status'] = (string) $state['failed_from'];
		}
		if ( ! in_array( (string) $state['status'], array( 'pending', 'indexing' ), true ) ) {
			throw new RuntimeException( 'invalid_state: Indexing tidak tersedia pada state ini.' );
		}
		$token = $this->acquire_lock();
		if ( '' === $token ) { throw new RuntimeException( 'resolver_locked: Resolver sedang diproses oleh request lain.' ); }

		try {
			if ( 'pending' === (string) $state['status'] ) {
				if ( get_option( self::MANIFEST_OPTION, false ) ) {
					throw new RuntimeException( 'stale_manifest: Manifest lama terdeteksi; gunakan Reset Scan terlebih dahulu.' );
				}
				$state['scan_max_attachment_id'] = $this->max_image_attachment_id();
				$state['total']      = $this->image_attachment_count_in_boundary( (int) $state['scan_max_attachment_id'] );
				$state['status']     = 'indexing';
				$state['started_at'] = time();
				$state['last_error'] = '';
			}
			$state['status'] = 'indexing';
			$this->save_state( $state );

			$ids           = $this->next_image_ids( (int) $state['cursor'], self::BATCH_SIZE, (int) $state['scan_max_attachment_id'] );
			$batch_started = microtime( true );
			$completed     = 0;
			foreach ( $ids as $id ) {
				if ( $completed > 0 && microtime( true ) - $batch_started >= self::REQUEST_BUDGET_SECONDS ) { break; }
				$result = $this->classify_attachment( $id );
				if ( 'confirmed-unused' === (string) $result['classification'] ) {
					$state['results'][] = $result;
				}
				$classification = (string) $result['classification'];
				if ( isset( $state['counts'][ $classification ] ) ) { $state['counts'][ $classification ]++; }
				if ( 'confirmed-unused' === $classification ) { $state['estimated_bytes'] += (int) $result['bytes']; }
				$state['warnings'] = array_values( array_unique( array_merge( (array) $state['warnings'], (array) $result['warnings'] ) ) );
				$state['cursor']    = $id;
				$state['processed']++;
				$state['current_file'] = (string) $result['filename'];
				$completed++;
			}

			if ( $completed === count( $ids ) && count( $ids ) < self::BATCH_SIZE ) {
				$this->seal_manifest( $state );
				$state['status']       = 'review_ready';
				$state['current_file'] = '';
			}
			$state['last_error'] = '';
			$state['failed_from'] = '';
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
	 * Delete one immutable-manifest batch after immediate per-candidate revalidation.
	 *
	 * @param int    $client_cursor Server-issued cursor echoed by the client.
	 * @param string $manifest_token Server-issued opaque token.
	 * @param bool   $backup_confirmed Explicit permanent-deletion confirmation.
	 * @return array<string,mixed>
	 * @throws RuntimeException On safety failure.
	 */
	public function delete_batch( $client_cursor, $manifest_token, $backup_confirmed ) {
		$state = $this->get_state();
		if ( 'complete' === (string) $state['status'] ) { return $this->summary(); }
		if ( 'failed' === (string) $state['status'] && in_array( (string) $state['failed_from'], array( 'review_ready', 'deleting' ), true ) ) {
			$state['status'] = (string) $state['failed_from'];
		}
		if ( ! $backup_confirmed ) { throw new RuntimeException( 'confirmation_required: Konfirmasi backup wajib diberikan.' ); }
		if ( ! in_array( (string) $state['status'], array( 'review_ready', 'deleting' ), true ) ) {
			throw new RuntimeException( 'invalid_state: Penghapusan belum tersedia.' );
		}
		$manifest = $this->validated_manifest( $state, $manifest_token );
		if ( (int) $client_cursor !== (int) $state['deletion_cursor'] ) { return $this->summary(); }
		$token = $this->acquire_lock();
		if ( '' === $token ) { throw new RuntimeException( 'resolver_locked: Resolver sedang diproses oleh request lain.' ); }

		try {
			$state['status'] = 'deleting';
			$this->save_state( $state );
			$candidates  = (array) $manifest['candidates'];
			$batch       = array_slice( $candidates, (int) $state['deletion_cursor'], self::BATCH_SIZE );
			$batch_start = microtime( true );
			$completed   = 0;
			foreach ( $batch as $candidate ) {
				if ( $completed > 0 && microtime( true ) - $batch_start >= self::REQUEST_BUDGET_SECONDS ) { break; }
				$id = absint( $candidate['id'] );
				$state['current_file'] = (string) $candidate['filename'];
				$fresh = $this->classify_attachment( $id );
				if ( 'confirmed-unused' !== (string) $fresh['classification'] || ! hash_equals( (string) $candidate['fingerprint'], (string) $fresh['fingerprint'] ) ) {
					$state['skipped'][ $id ] = array( 'reason' => 'Referensi, metadata, atau file berubah setelah dry-run.', 'classification' => (string) $fresh['classification'] );
					$state['deletion_cursor']++;
					$completed++;
					continue;
				}
				$post = get_post( $id );
				if ( ! ( $post instanceof WP_Post ) || 'attachment' !== (string) $post->post_type || 0 !== strpos( (string) $post->post_mime_type, 'image/' ) || ! $this->is_current_upload_file( (string) get_attached_file( $id ) ) ) {
					$state['skipped'][ $id ] = array( 'reason' => 'Guard tipe/path attachment akhir gagal.' );
					$state['deletion_cursor']++;
					$completed++;
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
				$completed++;
			}
			if ( (int) $state['deletion_cursor'] >= count( $candidates ) ) {
				$state['status']              = 'verifying';
				$state['verification_cursor'] = 0;
				$state['current_file']        = '';
			}
			$state['last_error']  = '';
			$state['failed_from'] = '';
			$state['updated_at']  = time();
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
	 * Candidate-scoped verification batch.
	 * Verifies only manifest candidates (deleted and skipped), not the entire library.
	 *
	 * @return array<string,mixed>
	 * @throws RuntimeException On verification failure.
	 */
	public function verify_batch() {
		$state = $this->get_state();
		if ( 'complete' === (string) $state['status'] ) { return $this->summary(); }
		if ( 'failed' === (string) $state['status'] && 'verifying' === (string) $state['failed_from'] ) {
			$state['status'] = 'verifying';
		}
		if ( 'verifying' !== (string) $state['status'] ) {
			throw new RuntimeException( 'invalid_state: Verifikasi belum tersedia.' );
		}
		$manifest = $this->validated_manifest( $state, (string) $state['manifest_token'] );
		$token    = $this->acquire_lock();
		if ( '' === $token ) { throw new RuntimeException( 'resolver_locked: Resolver sedang diproses oleh request lain.' ); }

		try {
			$state['status'] = 'verifying';
			$this->save_state( $state );
			$candidates = (array) $manifest['candidates'];
			$batch      = array_slice( $candidates, (int) $state['verification_cursor'], self::BATCH_SIZE );
			$batch_start = microtime( true );
			$completed  = 0;
			foreach ( $batch as $candidate ) {
				if ( $completed > 0 && microtime( true ) - $batch_start >= self::REQUEST_BUDGET_SECONDS ) { break; }
				$id = absint( $candidate['id'] );
				if ( isset( $state['deleted'][ $id ] ) ) {
					if ( get_post( $id ) instanceof WP_Post ) {
						throw new RuntimeException( 'verification_failed: Attachment terhapus masih memiliki record: ' . $id );
					}
					foreach ( (array) $candidate['files'] as $f ) {
						if ( is_file( (string) $f ) ) {
							throw new RuntimeException( 'verification_failed: File attachment terhapus masih tersisa.' );
						}
					}
				} else {
					$post = get_post( $id );
					if ( ! ( $post instanceof WP_Post ) ) {
						throw new RuntimeException( 'verification_failed: Attachment non-kandidat hilang setelah run: ' . $id );
					}
				}
				$state['verification_cursor']++;
				$completed++;
			}
			if ( (int) $state['verification_cursor'] >= count( $candidates ) ) {
				$state['status']       = 'complete';
				$state['completed_at'] = time();
				$state['current_file'] = '';
			}
			$state['last_error']  = '';
			$state['failed_from'] = '';
			$state['updated_at']  = time();
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
	 * Return paginated candidates (confirmed-unused only) for the review table.
	 *
	 * @param int $page Page number.
	 * @return array<string,mixed>
	 */
	public function review_page( $page ) {
		$state   = $this->get_state();
		$status  = (string) $state['status'];
		if ( ! in_array( $status, array( 'review_ready', 'deleting', 'verifying', 'complete' ), true ) &&
		     ! ( 'failed' === $status && in_array( (string) $state['failed_from'], array( 'review_ready', 'deleting', 'verifying' ), true ) ) ) {
			return array( 'items' => array(), 'page' => 1, 'pages' => 1 );
		}
		$page     = max( 1, absint( $page ) );
		$results  = (array) $state['results'];
		$items    = array_slice( $results, ( $page - 1 ) * self::REVIEW_PAGE_SIZE, self::REVIEW_PAGE_SIZE );
		foreach ( $items as &$item ) { unset( $item['fingerprint'], $item['files'] ); }
		return array(
			'items' => array_values( $items ),
			'page'  => $page,
			'pages' => max( 1, (int) ceil( count( $results ) / self::REVIEW_PAGE_SIZE ) ),
		);
	}

	/** @return array<string,mixed> */
	public function export_data() {
		$state    = $this->get_state();
		$manifest = get_option( self::MANIFEST_OPTION, array() );
		unset( $state['manifest_token'] );
		foreach ( $state['results'] as &$result ) { unset( $result['fingerprint'], $result['files'] ); }
		if ( is_array( $manifest ) ) { unset( $manifest['token'] ); }
		if ( ! empty( $manifest['candidates'] ) ) { foreach ( $manifest['candidates'] as &$c ) { unset( $c['fingerprint'], $c['files'] ); } }
		return array( 'state' => $state, 'manifest' => $manifest );
	}

	/* -----------------------------------------------------------------------
	 * Attachment classification
	 * --------------------------------------------------------------------- */

	/** @param int $id Attachment ID. @return array<string,mixed> */
	private function classify_attachment( $id ) {
		$base = array( 'id' => absint( $id ), 'filename' => '', 'mime' => '', 'date' => '', 'dimensions' => '', 'bytes' => 0, 'classification' => 'ambiguous', 'reason' => '', 'references' => array(), 'warnings' => array(), 'fingerprint' => '', 'files' => array() );
		$post = get_post( $id );
		if ( ! ( $post instanceof WP_Post ) || 'attachment' !== (string) $post->post_type || 0 !== strpos( (string) $post->post_mime_type, 'image/' ) ) {
			$base['reason'] = 'Record bukan attachment image yang valid.';
			return $base;
		}
		$base['mime']        = (string) $post->post_mime_type;
		$base['date']        = (string) $post->post_date_gmt;
		$base['fingerprint'] = $this->attachment_fingerprint( $id );
		$file                = (string) get_attached_file( $id );
		$base['filename']    = basename( $file );
		if ( ! $this->is_current_upload_file( $file ) || ! is_file( $file ) || ! is_readable( $file ) || ! is_writable( $file ) || ! is_writable( dirname( $file ) ) ) {
			$base['reason'] = 'Path/file atau permission tidak aman di uploads site aktif.';
			return $base;
		}
		$metadata = wp_get_attachment_metadata( $id );
		if ( ! is_array( $metadata ) || empty( $metadata['file'] ) ) {
			$base['reason'] = 'Metadata attachment hilang atau malformed.';
			return $base;
		}
		$base['dimensions'] = isset( $metadata['width'], $metadata['height'] ) ? absint( $metadata['width'] ) . '×' . absint( $metadata['height'] ) : '';
		$files              = $this->attachment_files( $file, $metadata );
		$base['files']      = $files;
		foreach ( $files as $f ) {
			if ( ! $this->is_current_upload_file( $f ) || ! is_file( $f ) || ! is_readable( $f ) || ! is_writable( $f ) ) {
				$base['reason'] = 'Relasi file asli/generated size tidak konsisten atau permission tidak aman.';
				return $base;
			}
			$base['bytes'] += (int) filesize( $f );
		}
		$cutoff   = time() - ( self::RECENT_DAYS * DAY_IN_SECONDS );
		$uploaded = strtotime( (string) $post->post_date_gmt . ' UTC' );
		$modified = strtotime( (string) $post->post_modified_gmt . ' UTC' );
		if ( max( (int) $uploaded, (int) $modified ) >= $cutoff ) {
			$base['classification'] = 'protected';
			$base['reason'] = 'Diunggah atau diubah dalam 30 hari terakhir.';
			return $base;
		}
		$sys = $this->system_media_reason( $id );
		if ( '' !== $sys ) { $base['classification'] = 'protected'; $base['reason'] = $sys; return $base; }
		$scan               = $this->scan_references( $id, $file, $metadata );
		$base['references'] = $scan['hard'];
		$base['warnings']   = array_merge( $scan['soft_reasons'], $scan['warnings'] );
		if ( $scan['hard'] ) { $base['classification'] = 'used'; $base['reason'] = 'Referensi ditemukan.'; return $base; }
		if ( $scan['soft_reasons'] || $scan['warnings'] ) { $base['classification'] = 'ambiguous'; $base['reason'] = 'Referensi lemah atau pemindaian tidak lengkap; fail closed.'; return $base; }
		$base['classification'] = 'confirmed-unused';
		$base['reason']         = 'Lebih dari 30 hari dan tidak ditemukan referensi hard/registry/content/meta/WooCommerce.';
		return $base;
	}

	/* -----------------------------------------------------------------------
	 * Reference scan — deterministic, no per-attachment codebase rescan
	 * --------------------------------------------------------------------- */

	/**
	 * @param int    $id
	 * @param string $file
	 * @param array<string,mixed> $metadata
	 * @return array{hard:list<string>,soft_reasons:list<string>,warnings:list<string>}
	 */
	private function scan_references( $id, $file, array $metadata ) {
		global $wpdb;
		$hard         = array();
		$soft_reasons = array();
		$warnings     = array();
		$candidate_id = (string) absint( $id );

		/* 1a. Featured-image references: bind the candidate before LIMIT. */
		$thumbnail_rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key = '_thumbnail_id' AND meta_value = %s
			 LIMIT %d",
			$candidate_id,
			self::MAX_MATCHES_PER_STORE + 1
		) );
		if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'postmeta thumbnail query failed'; }
		if ( count( (array) $thumbnail_rows ) > self::MAX_MATCHES_PER_STORE ) { $warnings[] = 'postmeta thumbnail match limit exceeded'; }
		foreach ( array_slice( (array) $thumbnail_rows, 0, self::MAX_MATCHES_PER_STORE ) as $row ) {
			$hard[] = 'postmeta:' . absint( $row->post_id ) . ':_thumbnail_id';
		}

		/* 1b. Woo gallery references: exact CSV membership, candidate-bound before LIMIT. */
		$gallery_rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key = '_product_image_gallery'
			   AND FIND_IN_SET( %s, REPLACE( meta_value, ' ', '' ) ) > 0
			 LIMIT %d",
			$candidate_id,
			self::MAX_MATCHES_PER_STORE + 1
		) );
		if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'postmeta Woo gallery query failed'; }
		if ( count( (array) $gallery_rows ) > self::MAX_MATCHES_PER_STORE ) { $warnings[] = 'postmeta Woo gallery match limit exceeded'; }
		foreach ( array_slice( (array) $gallery_rows, 0, self::MAX_MATCHES_PER_STORE ) as $row ) {
			$hard[] = 'postmeta:' . absint( $row->post_id ) . ':_product_image_gallery';
		}

		/* 1c. Gloskin image/media/gallery structured meta: candidate-bound numeric token before LIMIT. */
		$id_boundary = '(^|[^0-9])' . preg_quote( $candidate_id, '/' ) . '([^0-9]|$)';
		$gloskin_rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			 WHERE (meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s)
			   AND meta_value REGEXP %s
			 LIMIT %d",
			'%gloskin%image%', '%gloskin%media%', '%gloskin%gallery%',
			$id_boundary,
			self::MAX_MATCHES_PER_STORE + 1
		) );
		if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'postmeta Gloskin structured query failed'; }
		if ( count( (array) $gloskin_rows ) > self::MAX_MATCHES_PER_STORE ) { $warnings[] = 'postmeta Gloskin structured match limit exceeded'; }
		foreach ( array_slice( (array) $gloskin_rows, 0, self::MAX_MATCHES_PER_STORE ) as $row ) {
			if ( $this->structured_value_contains_id( (string) $row->meta_value, $id ) ) {
				$hard[] = 'postmeta:' . absint( $row->post_id ) . ':' . sanitize_key( $row->meta_key );
			} else {
				/* Candidate-bound token exists but the structure is unknown: protect conservatively. */
				$warnings[] = 'postmeta Gloskin structured value ambiguous for candidate ' . $candidate_id;
			}
		}

		if ( $hard ) { return array( 'hard' => array_values( array_unique( $hard ) ), 'soft_reasons' => array(), 'warnings' => array_values( array_unique( $warnings ) ) ); }

		/* 2. URL and structured-token LIKE searches. Never naked bare numeric IDs. */
		$tokens = $this->attachment_reference_tokens( $id, $file, $metadata );
		foreach ( $tokens as $token_value => $strength ) {
			$like = '%' . $wpdb->esc_like( $token_value ) . '%';
			$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				"SELECT ID FROM {$wpdb->posts} WHERE ID <> %d AND (post_content LIKE %s OR post_excerpt LIKE %s) LIMIT %d",
				$id, $like, $like, self::MAX_MATCHES_PER_STORE + 1
			) );
			if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'content query failed'; }
			$this->append_matches( $rows, 'content', $strength, $hard, $soft_reasons, $warnings );
			$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				"SELECT meta_id AS ID FROM {$wpdb->postmeta} WHERE post_id <> %d AND meta_value LIKE %s LIMIT %d",
				$id, $like, self::MAX_MATCHES_PER_STORE + 1
			) );
			if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'postmeta broad query failed'; }
			$this->append_matches( $rows, 'postmeta', $strength, $hard, $soft_reasons, $warnings );
			$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				"SELECT meta_id AS ID FROM {$wpdb->termmeta} WHERE meta_value LIKE %s LIMIT %d",
				$like, self::MAX_MATCHES_PER_STORE + 1
			) );
			if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'termmeta query failed'; }
			$this->append_matches( $rows, 'termmeta', $strength, $hard, $soft_reasons, $warnings );
			$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				"SELECT option_id AS ID FROM {$wpdb->options} WHERE option_name NOT IN (%s,%s,%s) AND option_value LIKE %s LIMIT %d",
				self::STATE_OPTION, self::LOCK_OPTION, self::MANIFEST_OPTION, $like, self::MAX_MATCHES_PER_STORE + 1
			) );
			if ( '' !== (string) $wpdb->last_error ) { $warnings[] = 'option query failed'; }
			$this->append_matches( $rows, 'option', $strength, $hard, $soft_reasons, $warnings );
		}

		return array(
			'hard'         => array_values( array_unique( $hard ) ),
			'soft_reasons' => array_values( array_unique( $soft_reasons ) ),
			'warnings'     => array_values( array_unique( $warnings ) ),
		);
	}

	/** @param array<int,object> $rows @param string $location @param string $strength @param list<string> $hard @param list<string> $soft @param list<string> $warnings */
	private function append_matches( $rows, $location, $strength, array &$hard, array &$soft, array &$warnings ) {
		$rows = (array) $rows;
		if ( count( $rows ) > self::MAX_MATCHES_PER_STORE ) { $warnings[] = $location . ' match limit exceeded'; }
		foreach ( array_slice( $rows, 0, self::MAX_MATCHES_PER_STORE ) as $row ) {
			$entry = $location . ':' . absint( $row->ID );
			if ( 'hard' === $strength ) { $hard[] = $entry; } else { $soft[] = $entry; }
		}
	}

	/**
	 * Build reference tokens. Only structured, specific tokens — no naked bare numeric IDs.
	 *
	 * @param int    $id
	 * @param string $file
	 * @param array<string,mixed> $metadata
	 * @return array<string,string> token => 'hard'|'soft'
	 */
	private function attachment_reference_tokens( $id, $file, array $metadata ) {
		$tokens  = array();
		$url     = (string) wp_get_attachment_url( $id );
		$relative = isset( $metadata['file'] ) ? ltrim( (string) $metadata['file'], '/' ) : '';
		foreach ( array( $url, $relative, basename( $file ), 'wp-image-' . $id,
			'"id":' . $id, '"mediaId":' . $id, '"media_id":' . $id,
			'"ids":[' . $id,
			'ids="' . $id,
			'attachment_id";i:' . $id,
		) as $value ) {
			if ( '' !== $value ) { $tokens[ $value ] = 'hard'; }
		}
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( ! is_array( $size ) || empty( $size['file'] ) ) { continue; }
				$tokens[ (string) $size['file'] ] = 'hard';
				if ( '' !== $url ) { $tokens[ trailingslashit( dirname( $url ) ) . (string) $size['file'] ] = 'hard'; }
			}
		}
		if ( ! empty( $metadata['original_image'] ) ) {
			$tokens[ (string) $metadata['original_image'] ] = 'hard';
		}
		return $tokens;
	}

	/* -----------------------------------------------------------------------
	 * System / Gloskin / site-icon protection
	 * --------------------------------------------------------------------- */

	/** @param int $id @return string Non-empty means protected. */
	private function system_media_reason( $id ) {
		if ( $id === absint( get_option( 'site_icon' ) ) || $id === absint( get_theme_mod( 'custom_logo' ) ) ) {
			return 'Site icon, favicon, atau custom logo.';
		}
		foreach ( (array) get_theme_mods() as $mod_value ) {
			if ( is_numeric( $mod_value ) && (int) $mod_value === $id ) { return 'Digunakan sebagai theme mod (header image, dsb.).'; }
		}
		$meta = get_post_meta( $id );
		foreach ( array_keys( (array) $meta ) as $key ) {
			if ( 0 === strpos( (string) $key, '_gloskin_' ) || 0 === strpos( (string) $key, 'gloskin_' ) ) {
				return 'Attachment memiliki provenance/registry Gloskin atau Phase-3.';
			}
		}
		return '';
	}

	/* -----------------------------------------------------------------------
	 * Structured-value recursive ID check (for postmeta)
	 * --------------------------------------------------------------------- */

	/** @param string $value @param int $id @return bool */
	private function structured_value_contains_id( $value, $id ) {
		if ( (string) $id === trim( $value ) ) { return true; }
		$decoded = maybe_unserialize( $value );
		return $this->recursive_contains_id( $decoded, $id );
	}

	/** @param mixed $value @param int $id @return bool */
	private function recursive_contains_id( $value, $id ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			foreach ( (array) $value as $child ) { if ( $this->recursive_contains_id( $child, $id ) ) { return true; } }
			return false;
		}
		if ( is_string( $value ) && ( '{' === substr( trim( $value ), 0, 1 ) || '[' === substr( trim( $value ), 0, 1 ) ) ) {
			$json = json_decode( $value, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) { return false; }
			return $this->recursive_contains_id( $json, $id );
		}
		return is_numeric( $value ) && absint( $value ) === $id;
	}

	/* -----------------------------------------------------------------------
	 * File helpers
	 * --------------------------------------------------------------------- */

	/** @param string $file @param array<string,mixed> $metadata @return list<string> */
	private function attachment_files( $file, array $metadata ) {
		$files = array( $file );
		$dir   = dirname( $file );
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( is_array( $size ) && ! empty( $size['file'] ) ) { $files[] = $dir . DIRECTORY_SEPARATOR . basename( (string) $size['file'] ); }
			}
		}
		if ( ! empty( $metadata['original_image'] ) ) { $files[] = $dir . DIRECTORY_SEPARATOR . basename( (string) $metadata['original_image'] ); }
		return array_values( array_unique( $files ) );
	}

	/** @param string $file @return bool */
	private function is_current_upload_file( $file ) {
		$uploads  = wp_get_upload_dir();
		$base     = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		$resolved = realpath( $file );
		return $base && $resolved && 0 === strpos( $resolved . DIRECTORY_SEPARATOR, trailingslashit( $base ) ) && ! is_link( $file );
	}

	/* -----------------------------------------------------------------------
	 * Fingerprint
	 * --------------------------------------------------------------------- */

	/** @param int $id @return string */
	private function attachment_fingerprint( $id ) {
		$post = get_post( $id );
		return $post ? hash( 'sha256', wp_json_encode( array( 'post' => get_object_vars( $post ), 'meta' => get_post_meta( $id ) ) ) ) : '';
	}

	/* -----------------------------------------------------------------------
	 * Manifest (immutable, server-sealed)
	 * --------------------------------------------------------------------- */

	/** @param array<string,mixed> $state @return void */
	private function seal_manifest( array &$state ) {
		$candidates = array();
		foreach ( (array) $state['results'] as $result ) {
			if ( 'confirmed-unused' === (string) $result['classification'] ) {
				$candidates[] = array_intersect_key( $result, array_flip( array( 'id', 'filename', 'mime', 'date', 'dimensions', 'bytes', 'classification', 'reason', 'fingerprint', 'files' ) ) );
			}
		}
		$payload = array(
			'revision'    => self::REVISION,
			'blog_id'     => function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1,
			'created_at'  => time(),
			'candidates'  => $candidates,
		);
		$hash            = hash( 'sha256', wp_json_encode( $payload ) );
		$payload['hash'] = $hash;
		$payload['token'] = wp_generate_password( 48, false, false );
		if ( ! add_option( self::MANIFEST_OPTION, $payload, '', 'no' ) ) {
			throw new RuntimeException( 'manifest_failed: Immutable candidate manifest tidak dapat disimpan.' );
		}
		$state['manifest_hash']  = $hash;
		$state['manifest_token'] = $payload['token'];
	}

	/** @param array<string,mixed> $state @param string $token @return array<string,mixed> */
	private function validated_manifest( array $state, $token ) {
		$manifest = get_option( self::MANIFEST_OPTION, array() );
		if ( ! is_array( $manifest ) || ! isset( $manifest['revision'], $manifest['blog_id'], $manifest['created_at'], $manifest['candidates'], $manifest['hash'], $manifest['token'] ) || ! is_array( $manifest['candidates'] ) || self::REVISION !== (string) $manifest['revision'] || (int) $manifest['blog_id'] !== ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 ) || '' === (string) $manifest['token'] || ! hash_equals( (string) $manifest['token'], (string) $token ) || ! hash_equals( (string) $state['manifest_hash'], (string) $manifest['hash'] ) ) {
			throw new RuntimeException( 'manifest_invalid: Manifest/token/site tidak valid.' );
		}
		$check = array( 'revision' => $manifest['revision'], 'blog_id' => $manifest['blog_id'], 'created_at' => $manifest['created_at'], 'candidates' => $manifest['candidates'] );
		if ( ! hash_equals( (string) $manifest['hash'], hash( 'sha256', wp_json_encode( $check ) ) ) ) {
			throw new RuntimeException( 'manifest_invalid: Hash manifest berubah.' );
		}
		return $manifest;
	}

	/* -----------------------------------------------------------------------
	 * Database helpers — frozen-boundary aware
	 * --------------------------------------------------------------------- */

	/** @return int Maximum image attachment ID currently in DB. */
	private function max_image_attachment_id() {
		global $wpdb;
		$max = $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'scan_failed: Max attachment ID tidak dapat dibaca.' ); }
		return max( 0, (int) $max );
	}

	/** @param int $max_id @return int */
	private function image_attachment_count_in_boundary( $max_id ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%' AND ID <= %d", absint( $max_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'scan_failed: Jumlah attachment image tidak dapat dihitung.' ); }
		return (int) $count;
	}

	/**
	 * Fetch next batch of IDs within the frozen boundary.
	 *
	 * @param int $cursor Last processed ID.
	 * @param int $limit Batch size.
	 * @param int $max_id Frozen boundary.
	 * @return list<int>
	 */
	private function next_image_ids( $cursor, $limit, $max_id ) {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%' AND ID > %d AND ID <= %d ORDER BY ID ASC LIMIT %d", absint( $cursor ), absint( $max_id ), absint( $limit ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'scan_failed: Batch attachment image tidak lengkap.' ); }
		return array_map( 'absint', (array) $rows );
	}

	/* -----------------------------------------------------------------------
	 * State / lock helpers
	 * --------------------------------------------------------------------- */

	/** @return array<string,mixed> */
	private function default_state() {
		return array(
			'revision'               => self::REVISION,
			'blog_id'                => function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1,
			'status'                 => 'pending',
			'scan_max_attachment_id' => 0,
			'cursor'                 => 0,
			'processed'              => 0,
			'total'                  => 0,
			'current_file'           => '',
			'counts'                 => array( 'used' => 0, 'protected' => 0, 'ambiguous' => 0, 'confirmed-unused' => 0 ),
			'estimated_bytes'        => 0,
			'actual_bytes'           => 0,
			'deletion_cursor'        => 0,
			'verification_cursor'    => 0,
			'deleted'                => array(),
			'skipped'                => array(),
			'failed'                 => array(),
			'results'                => array(),
			'warnings'               => array(),
			'manifest_hash'          => '',
			'manifest_token'         => '',
			'failed_from'            => '',
			'last_error'             => '',
			'started_at'             => 0,
			'updated_at'             => 0,
			'completed_at'           => 0,
		);
	}

	/** @return string Lock token; empty string if locked by another request. */
	private function acquire_lock() {
		$current = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $current ) && ! empty( $current['created'] ) && time() - (int) $current['created'] < self::LOCK_TTL ) { return ''; }
		if ( $current ) { delete_option( self::LOCK_OPTION ); }
		$lock_token = wp_generate_uuid4();
		return add_option( self::LOCK_OPTION, array( 'token' => $lock_token, 'created' => time() ), '', 'no' ) ? $lock_token : '';
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
		$state                = $this->get_state();
		$state['failed_from'] = (string) $state['status'];
		$state['status']      = 'failed';
		$state['last_error']  = (string) $message;
		$state['updated_at']  = time();
		$this->save_state( $state );
	}
}
