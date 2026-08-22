<?php
/**
 * In-place image optimization extension for the Media Cleanup resolver.
 *
 * This trait is implementation-only: state, locking, batching and attachment
 * discovery remain owned by Gloskin_Site_Core_Media_Cleanup_Resolver.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Media_Cleanup_Optimizer_Trait {
	/** @return array<string,mixed> */
	private function optimizer_default_state() {
		return array(
			'revision'          => '2026-08-22-image-optimizer-v1',
			'status'            => 'pending',
			'max_attachment_id' => 0,
			'cursor'            => 0,
			'total'             => 0,
			'processed'         => 0,
			'optimized'         => 0,
			'skipped'           => 0,
			'failed'            => 0,
			'bytes_before'      => 0,
			'bytes_after'       => 0,
			'bytes_saved'       => 0,
			'current_file'      => '',
			'last_error'        => '',
			'started_at'        => 0,
			'updated_at'        => 0,
			'completed_at'      => 0,
		);
	}

	/** @param mixed $stored @return array<string,mixed> */
	private function normalize_optimizer_state( $stored ) {
		$defaults = $this->optimizer_default_state();
		$stored   = is_array( $stored ) ? $stored : array();
		if ( isset( $stored['revision'] ) && (string) $stored['revision'] !== (string) $defaults['revision'] ) {
			return $defaults;
		}
		return array_merge( $defaults, $stored );
	}

	/** @param array<string,mixed> $state @return void */
	private function assert_optimizer_not_running( array $state ) {
		$optimization = $this->normalize_optimizer_state( isset( $state['optimization'] ) ? $state['optimization'] : array() );
		if ( 'running' === (string) $optimization['status'] ) {
			throw new RuntimeException( 'invalid_state: Image Optimization sedang berjalan; selesaikan operasi tersebut sebelum mengubah state cleanup.' );
		}
	}

	/**
	 * Optimize one resumable batch of retained images.
	 *
	 * The cleanup lifecycle must already be complete. A restart opens a fresh
	 * frozen attachment boundary, while unchanged attachments are skipped via
	 * the post-optimization file-set fingerprint.
	 *
	 * @param bool $restart Start a new optimization pass after completion.
	 * @return array<string,mixed>
	 */
	public function optimize_batch( $restart = false ) {
		$state = $this->get_state();
		if ( 'complete' !== (string) $state['status'] ) {
			throw new RuntimeException( 'invalid_state: Selesaikan cleanup dan verifikasi sebelum mengoptimalkan retained images.' );
		}

		$optimization = $this->normalize_optimizer_state( isset( $state['optimization'] ) ? $state['optimization'] : array() );
		if ( 'complete' === (string) $optimization['status'] && ! $restart ) {
			return $this->summary();
		}

		$token = $this->acquire_lock();
		if ( '' === $token ) {
			throw new RuntimeException( 'resolver_locked: Resolver sedang diproses oleh request atau tab lain.' );
		}

		try {
			/* Re-read after lock acquisition so a second tab cannot process a stale cursor. */
			$state = $this->get_state();
			if ( 'complete' !== (string) $state['status'] ) {
				throw new RuntimeException( 'invalid_state: Cleanup tidak lagi berada pada state stabil.' );
			}
			$optimization = $this->normalize_optimizer_state( isset( $state['optimization'] ) ? $state['optimization'] : array() );

			if ( $restart && 'complete' === (string) $optimization['status'] ) {
				$optimization = $this->optimizer_default_state();
			}
			if ( 'failed' === (string) $optimization['status'] ) {
				$optimization['status']     = 'running';
				$optimization['last_error'] = '';
			}
			if ( ! in_array( (string) $optimization['status'], array( 'pending', 'running' ), true ) ) {
				$this->release_lock( $token );
				return $this->summary();
			}

			if ( 'pending' === (string) $optimization['status'] ) {
				$optimization['max_attachment_id'] = $this->max_image_attachment_id();
				$optimization['total']             = $this->image_attachment_count_in_boundary( (int) $optimization['max_attachment_id'] );
				$optimization['status']            = 'running';
				$optimization['started_at']        = time();
				$optimization['last_error']        = '';
			}

			$state['optimization'] = $optimization;
			$this->save_state( $state );

			$ids = $this->next_image_ids(
				(int) $optimization['cursor'],
				self::BATCH_SIZE,
				(int) $optimization['max_attachment_id']
			);
			$batch_started = microtime( true );
			$completed     = 0;

			foreach ( $ids as $id ) {
				if ( $completed > 0 && microtime( true ) - $batch_started >= self::REQUEST_BUDGET_SECONDS ) {
					break;
				}
				$result = $this->optimize_attachment_files( $id );
				$optimization['cursor']       = $id;
				$optimization['processed']++;
				$optimization['current_file'] = (string) $result['filename'];
				$optimization['bytes_before'] += (int) $result['bytes_before'];
				$optimization['bytes_after']  += (int) $result['bytes_after'];
				$optimization['bytes_saved']  += (int) $result['bytes_saved'];
				if ( 'optimized' === (string) $result['status'] ) {
					$optimization['optimized']++;
				} elseif ( 'failed' === (string) $result['status'] ) {
					$optimization['failed']++;
				} else {
					$optimization['skipped']++;
				}
				$completed++;
			}

			if ( $completed === count( $ids ) && count( $ids ) < self::BATCH_SIZE ) {
				$optimization['status']       = 'complete';
				$optimization['completed_at'] = time();
				$optimization['current_file'] = '';
			}
			$optimization['last_error'] = '';
			$optimization['updated_at'] = time();
			$state['optimization']      = $optimization;
			$this->save_state( $state );
			$this->release_lock( $token );
			return $this->summary();
		} catch ( Throwable $error ) {
			$this->release_lock( $token );
			$fresh = $this->get_state();
			$optimization = $this->normalize_optimizer_state( isset( $fresh['optimization'] ) ? $fresh['optimization'] : array() );
			$optimization['status']     = 'failed';
			$optimization['last_error'] = (string) $error->getMessage();
			$optimization['updated_at'] = time();
			$fresh['optimization']      = $optimization;
			$this->save_state( $fresh );
			if ( 0 === strpos( (string) $error->getMessage(), 'invalid_state:' ) ) {
				throw new RuntimeException( $error->getMessage(), 0, $error );
			}
			throw new RuntimeException( 'optimization_failed: Optimizer berhenti secara aman; file asli tetap dipertahankan pada kegagalan aktif.', 0, $error );
		}
	}

	/** @param int $id Attachment ID. @return array<string,mixed> */
	private function optimize_attachment_files( $id ) {
		$result = array(
			'filename'     => '',
			'status'       => 'skipped',
			'bytes_before' => 0,
			'bytes_after'  => 0,
			'bytes_saved'  => 0,
		);
		$post = get_post( $id );
		if ( ! ( $post instanceof WP_Post ) || 'attachment' !== (string) $post->post_type || 0 !== strpos( (string) $post->post_mime_type, 'image/' ) ) {
			return $result;
		}

		$file               = (string) get_attached_file( $id );
		$result['filename'] = basename( $file );
		$metadata           = wp_get_attachment_metadata( $id );
		if ( ! is_array( $metadata ) || empty( $metadata['file'] ) ) {
			$result['status'] = 'failed';
			return $result;
		}
		$files = $this->attachment_files( $file, $metadata );
		if ( ! $files ) {
			$result['status'] = 'failed';
			return $result;
		}
		foreach ( $files as $candidate ) {
			if ( ! $this->is_current_upload_file( $candidate ) || ! is_file( $candidate ) || ! is_readable( $candidate ) || ! is_writable( $candidate ) || ! is_writable( dirname( $candidate ) ) ) {
				$result['status'] = 'failed';
				return $result;
			}
			$result['bytes_before'] += max( 0, (int) filesize( $candidate ) );
		}
		$result['bytes_after'] = (int) $result['bytes_before'];

		$fingerprint = $this->optimizer_file_set_fingerprint( $files );
		$marker      = get_post_meta( $id, '_gloskin_media_optimizer_state', true );
		if ( is_array( $marker )
			&& isset( $marker['revision'], $marker['fingerprint'] )
			&& '2026-08-22-image-optimizer-v1' === (string) $marker['revision']
			&& '' !== $fingerprint
			&& hash_equals( (string) $marker['fingerprint'], $fingerprint ) ) {
			return $result;
		}

		$optimized_files = 0;
		$failed_files    = 0;
		$after_total     = 0;
		foreach ( $files as $candidate ) {
			$file_result = $this->optimize_single_image_file( $candidate );
			if ( 'optimized' === (string) $file_result['status'] ) {
				$optimized_files++;
			} elseif ( 'failed' === (string) $file_result['status'] ) {
				$failed_files++;
			}
			$after_total += max( 0, (int) $file_result['bytes_after'] );
		}
		$result['bytes_after'] = $after_total;
		$result['bytes_saved'] = max( 0, (int) $result['bytes_before'] - $after_total );
		if ( $optimized_files > 0 ) {
			$result['status'] = 'optimized';
		} elseif ( $failed_files > 0 ) {
			$result['status'] = 'failed';
		}

		if ( $optimized_files > 0 ) {
			$this->refresh_optimizer_metadata_filesizes( $id, $file, $metadata );
		}
		$post_fingerprint = $this->optimizer_file_set_fingerprint( $files );
		if ( '' !== $post_fingerprint ) {
			update_post_meta(
				$id,
				'_gloskin_media_optimizer_state',
				array(
					'revision'     => '2026-08-22-image-optimizer-v1',
					'fingerprint'  => $post_fingerprint,
					'optimized_at' => time(),
				)
			);
		}
		return $result;
	}

	/** @param string $file Absolute image path. @return array<string,mixed> */
	private function optimize_single_image_file( $file ) {
		$before = is_file( $file ) ? max( 0, (int) filesize( $file ) ) : 0;
		$result = array( 'status' => 'skipped', 'bytes_before' => $before, 'bytes_after' => $before );
		$mime   = function_exists( 'wp_get_image_mime' ) ? (string) wp_get_image_mime( $file ) : '';
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ), true ) ) {
			return $result;
		}
		if ( ! function_exists( 'wp_get_image_editor' ) || ! function_exists( 'wp_image_editor_supports' ) || ! wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) {
			return $result;
		}

		$source = $this->optimizer_image_info( $file, $mime );
		if ( ! is_array( $source ) || empty( $source['width'] ) || empty( $source['height'] ) || empty( $source['mime'] ) ) {
			$result['status'] = 'failed';
			return $result;
		}
		/* Animated raster files are left untouched unless preservation is provable. */
		if ( (int) $source['frames'] !== 1 ) {
			return $result;
		}
		/* Avoid re-encoding orientation-dependent JPEGs where EXIF removal could alter presentation. */
		if ( 'image/jpeg' === $mime && (int) $source['orientation'] > 1 ) {
			return $result;
		}

		$extension = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( '' === $extension ) {
			return $result;
		}
		$temp = dirname( $file ) . DIRECTORY_SEPARATOR . '.' . basename( $file ) . '.gloskin-opt-' . str_replace( '-', '', wp_generate_uuid4() ) . '.' . $extension;

		try {
			$editor = wp_get_image_editor( $file );
			if ( is_wp_error( $editor ) ) {
				return $result;
			}
			/* AVIF is enabled only when the selected editor really is Imagick-backed. */
			if ( 'image/avif' === $mime && false === stripos( get_class( $editor ), 'imagick' ) ) {
				return $result;
			}
			if ( in_array( $mime, array( 'image/jpeg', 'image/webp', 'image/avif' ), true ) ) {
				$quality = $editor->set_quality( 92 );
				if ( is_wp_error( $quality ) ) {
					return $result;
				}
			}
			$saved = $editor->save( $temp, $mime );
			if ( is_wp_error( $saved ) || ! is_file( $temp ) || ! is_readable( $temp ) ) {
				$result['status'] = 'failed';
				return $result;
			}
			clearstatcache( true, $temp );
			$after = max( 0, (int) filesize( $temp ) );
			if ( $after <= 0 || $after >= $before ) {
				return $result;
			}

			$candidate = $this->optimizer_image_info( $temp, $mime );
			if ( ! is_array( $candidate )
				|| (string) $candidate['mime'] !== (string) $source['mime']
				|| (int) $candidate['width'] !== (int) $source['width']
				|| (int) $candidate['height'] !== (int) $source['height']
				|| (bool) $candidate['alpha'] !== (bool) $source['alpha']
				|| (int) $candidate['frames'] !== (int) $source['frames']
				|| ( ! empty( $source['icc'] ) && empty( $candidate['icc'] ) ) ) {
				$result['status'] = 'failed';
				return $result;
			}

			$permissions = @fileperms( $file );
			if ( false !== $permissions ) {
				@chmod( $temp, $permissions & 0777 );
			}
			/* Same-directory rename is atomic on normal POSIX filesystems. Never unlink source first. */
			if ( ! @rename( $temp, $file ) ) {
				$result['status'] = 'failed';
				return $result;
			}
			clearstatcache( true, $file );
			$result['status']      = 'optimized';
			$result['bytes_after'] = max( 0, (int) filesize( $file ) );
			return $result;
		} finally {
			if ( is_file( $temp ) ) {
				@unlink( $temp );
			}
		}
	}

	/** @param string $file @param string $expected_mime @return array<string,mixed>|false */
	private function optimizer_image_info( $file, $expected_mime ) {
		$dimensions = @getimagesize( $file );
		$mime       = function_exists( 'wp_get_image_mime' ) ? (string) wp_get_image_mime( $file ) : '';
		if ( ! is_array( $dimensions ) || ! isset( $dimensions[0], $dimensions[1] ) || '' === $mime || $mime !== $expected_mime ) {
			return false;
		}
		$alpha       = false;
		$frames      = 1;
		$icc         = false;
		$orientation = 1;

		if ( class_exists( 'Imagick' ) ) {
			try {
				$image = new Imagick( $file );
				$frames = max( 1, (int) $image->getNumberImages() );
				if ( method_exists( $image, 'getImageAlphaChannel' ) ) {
					$alpha = (bool) $image->getImageAlphaChannel();
				}
				$profiles = $image->getImageProfiles( 'icc', false );
				$icc      = ! empty( $profiles );
				$image->clear();
				$image->destroy();
			} catch ( Throwable $ignored ) {
				if ( 'image/avif' === $expected_mime ) {
					return false;
				}
			}
		} else {
			$probe  = $this->optimizer_file_probe( $file, 1048576 );
			$frames = $this->optimizer_probe_frames( $probe, $expected_mime );
			$alpha  = $this->optimizer_probe_alpha( $probe, $expected_mime );
			$icc    = $this->optimizer_probe_icc( $probe, $expected_mime );
			if ( 'image/avif' === $expected_mime ) {
				return false;
			}
		}

		if ( 'image/jpeg' === $expected_mime ) {
			if ( ! function_exists( 'wp_read_image_metadata' ) && defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/image.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			if ( function_exists( 'wp_read_image_metadata' ) ) {
				$image_meta = wp_read_image_metadata( $file );
				if ( is_array( $image_meta ) && ! empty( $image_meta['orientation'] ) ) {
					$orientation = absint( $image_meta['orientation'] );
				}
			}
		}

		return array(
			'mime'        => $mime,
			'width'       => absint( $dimensions[0] ),
			'height'      => absint( $dimensions[1] ),
			'alpha'       => $alpha,
			'frames'      => $frames,
			'icc'         => $icc,
			'orientation' => $orientation,
		);
	}

	/** @param string $file @param int $limit @return string */
	private function optimizer_file_probe( $file, $limit ) {
		$handle = @fopen( $file, 'rb' );
		if ( ! is_resource( $handle ) ) { return ''; }
		$data = (string) fread( $handle, max( 1, (int) $limit ) );
		fclose( $handle );
		return $data;
	}

	/** @param string $probe @param string $mime @return int */
	private function optimizer_probe_frames( $probe, $mime ) {
		if ( 'image/png' === $mime && false !== strpos( $probe, 'acTL' ) ) { return 2; }
		if ( 'image/webp' === $mime && ( false !== strpos( $probe, 'ANIM' ) || false !== strpos( $probe, 'ANMF' ) ) ) { return 2; }
		return 1;
	}

	/** @param string $probe @param string $mime @return bool */
	private function optimizer_probe_alpha( $probe, $mime ) {
		if ( 'image/png' === $mime ) {
			$color_type = strlen( $probe ) > 25 ? ord( $probe[25] ) : -1;
			return in_array( $color_type, array( 4, 6 ), true ) || false !== strpos( $probe, 'tRNS' );
		}
		if ( 'image/webp' === $mime ) {
			if ( false !== strpos( $probe, 'ALPH' ) ) { return true; }
			$vp8x = strpos( $probe, 'VP8X' );
			return false !== $vp8x && isset( $probe[ $vp8x + 8 ] ) && ( ord( $probe[ $vp8x + 8 ] ) & 0x10 ) !== 0;
		}
		return false;
	}

	/** @param string $probe @param string $mime @return bool */
	private function optimizer_probe_icc( $probe, $mime ) {
		if ( 'image/jpeg' === $mime ) { return false !== strpos( $probe, 'ICC_PROFILE' ); }
		if ( 'image/png' === $mime ) { return false !== strpos( $probe, 'iCCP' ); }
		if ( 'image/webp' === $mime ) { return false !== strpos( $probe, 'ICCP' ); }
		return false;
	}

	/** @param array<int,string> $files @return string */
	private function optimizer_file_set_fingerprint( array $files ) {
		$files = array_values( array_unique( $files ) );
		sort( $files, SORT_STRING );
		$entries = array();
		foreach ( $files as $file ) {
			if ( ! is_file( $file ) || ! is_readable( $file ) ) { return ''; }
			$size = max( 0, (int) filesize( $file ) );
			$entries[] = array(
				'name'   => basename( $file ),
				'size'   => $size,
				'mtime'  => max( 0, (int) filemtime( $file ) ),
				'sample' => $this->optimizer_sample_hash( $file, $size ),
			);
		}
		return hash( 'sha256', wp_json_encode( $entries ) );
	}

	/** @param string $file @param int $size @return string */
	private function optimizer_sample_hash( $file, $size ) {
		$handle = @fopen( $file, 'rb' );
		if ( ! is_resource( $handle ) ) { return ''; }
		$chunk = 65536;
		$first = (string) fread( $handle, $chunk );
		$last  = '';
		if ( $size > $chunk ) {
			@fseek( $handle, max( 0, $size - $chunk ) );
			$last = (string) fread( $handle, $chunk );
		}
		fclose( $handle );
		return hash( 'sha256', $first . "\0" . $last );
	}

	/** @param int $id @param string $file @param array<string,mixed> $metadata @return void */
	private function refresh_optimizer_metadata_filesizes( $id, $file, array $metadata ) {
		$changed = false;
		if ( array_key_exists( 'filesize', $metadata ) && is_file( $file ) ) {
			$new_size = max( 0, (int) filesize( $file ) );
			if ( (int) $metadata['filesize'] !== $new_size ) {
				$metadata['filesize'] = $new_size;
				$changed = true;
			}
		}
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$dir = dirname( $file );
			foreach ( $metadata['sizes'] as $name => $size ) {
				if ( ! is_array( $size ) || empty( $size['file'] ) || ! array_key_exists( 'filesize', $size ) ) { continue; }
				$size_file = $dir . DIRECTORY_SEPARATOR . basename( (string) $size['file'] );
				if ( ! is_file( $size_file ) ) { continue; }
				$new_size = max( 0, (int) filesize( $size_file ) );
				if ( (int) $metadata['sizes'][ $name ]['filesize'] !== $new_size ) {
					$metadata['sizes'][ $name ]['filesize'] = $new_size;
					$changed = true;
				}
			}
		}
		if ( $changed ) {
			wp_update_attachment_metadata( $id, $metadata );
		}
	}
}
