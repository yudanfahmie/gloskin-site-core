<?php
/**
 * In-place image optimization extension for the Media Cleanup resolver.
 *
 * State, locking, attachment discovery and mutation ownership remain inside
 * the canonical Media Cleanup resolver. Files are never converted or renamed.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Media_Cleanup_Optimizer_Trait {
	/** @return array<string,mixed> */
	private function optimizer_default_state() {
		return array(
			'revision'             => self::OPTIMIZER_REVISION,
			'status'               => 'pending',
			'max_attachment_id'    => 0,
			'cursor'               => 0,
			'total'                => 0,
			'processed'            => 0,
			'optimized'            => 0,
			'skipped'              => 0,
			'failed'               => 0,
			'target_missed'        => 0,
			'bytes_before'         => 0,
			'bytes_after'          => 0,
			'bytes_saved'          => 0,
			'target_max_dimension' => self::OPTIMIZER_DEFAULT_MAX_DIMENSION,
			'target_max_bytes'     => self::OPTIMIZER_DEFAULT_MAX_BYTES,
			'current_file'         => '',
			'last_error'           => '',
			'started_at'           => 0,
			'updated_at'           => 0,
			'completed_at'         => 0,
		);
	}

	/** @param mixed $stored @return array<string,mixed> */
	private function normalize_optimizer_state( $stored ) {
		$defaults = $this->optimizer_default_state();
		$stored   = is_array( $stored ) ? $stored : array();
		if ( isset( $stored['revision'] ) && (string) $stored['revision'] !== self::OPTIMIZER_REVISION ) {
			return $defaults;
		}
		$state = array_merge( $defaults, $stored );
		$state['target_max_dimension'] = $this->optimizer_clamp_int( $state['target_max_dimension'], self::OPTIMIZER_MIN_DIMENSION, self::OPTIMIZER_MAX_DIMENSION, self::OPTIMIZER_DEFAULT_MAX_DIMENSION );
		$state['target_max_bytes']     = $this->optimizer_clamp_int( $state['target_max_bytes'], self::OPTIMIZER_MIN_BYTES, self::OPTIMIZER_MAX_BYTES, self::OPTIMIZER_DEFAULT_MAX_BYTES );
		return $state;
	}

	/** @param mixed $value @return int */
	private function optimizer_clamp_int( $value, $min, $max, $fallback ) {
		$value = absint( $value );
		if ( $value <= 0 ) { $value = absint( $fallback ); }
		return max( absint( $min ), min( absint( $max ), $value ) );
	}

	/** @param array<string,mixed> $settings @return array<string,int> */
	private function optimizer_sanitize_settings( array $settings ) {
		return array(
			'target_max_dimension' => $this->optimizer_clamp_int(
				isset( $settings['target_max_dimension'] ) ? $settings['target_max_dimension'] : self::OPTIMIZER_DEFAULT_MAX_DIMENSION,
				self::OPTIMIZER_MIN_DIMENSION,
				self::OPTIMIZER_MAX_DIMENSION,
				self::OPTIMIZER_DEFAULT_MAX_DIMENSION
			),
			'target_max_bytes' => $this->optimizer_clamp_int(
				isset( $settings['target_max_bytes'] ) ? $settings['target_max_bytes'] : self::OPTIMIZER_DEFAULT_MAX_BYTES,
				self::OPTIMIZER_MIN_BYTES,
				self::OPTIMIZER_MAX_BYTES,
				self::OPTIMIZER_DEFAULT_MAX_BYTES
			),
		);
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
	 * Settings are accepted only when a fresh pass starts. Once started, both
	 * the attachment boundary and optimization policy are frozen in state.
	 *
	 * @param bool                $restart  Start a new pass after completion.
	 * @param array<string,mixed> $settings Requested target settings.
	 * @return array<string,mixed>
	 */
	public function optimize_batch( $restart = false, array $settings = array() ) {
		$state = $this->get_state();
		if ( 'complete' !== (string) $state['status'] ) {
			throw new RuntimeException( 'invalid_state: Selesaikan cleanup sebelum mengoptimalkan retained images.' );
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
				$policy = $this->optimizer_sanitize_settings( $settings );
				$optimization['target_max_dimension'] = $policy['target_max_dimension'];
				$optimization['target_max_bytes']     = $policy['target_max_bytes'];
				$optimization['max_attachment_id']    = $this->max_image_attachment_id();
				$optimization['total']                = $this->image_attachment_count_in_boundary( (int) $optimization['max_attachment_id'] );
				$optimization['status']               = 'running';
				$optimization['started_at']           = time();
				$optimization['last_error']           = '';
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
				if ( $completed > 0 && microtime( true ) - $batch_started >= self::REQUEST_BUDGET_SECONDS ) { break; }
				$result = $this->optimize_attachment_files(
					$id,
					(int) $optimization['target_max_dimension'],
					(int) $optimization['target_max_bytes']
				);
				$optimization['cursor']        = $id;
				$optimization['processed']++;
				$optimization['current_file']  = (string) $result['filename'];
				$optimization['bytes_before'] += (int) $result['bytes_before'];
				$optimization['bytes_after']  += (int) $result['bytes_after'];
				$optimization['bytes_saved']  += (int) $result['bytes_saved'];
				if ( ! empty( $result['target_missed'] ) ) { $optimization['target_missed']++; }
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

	/** @return array<string,mixed> */
	private function optimize_attachment_files( $id, $target_max_dimension, $target_max_bytes ) {
		$result = array(
			'filename'      => '',
			'status'        => 'skipped',
			'bytes_before'  => 0,
			'bytes_after'   => 0,
			'bytes_saved'   => 0,
			'target_missed' => false,
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
			&& isset( $marker['revision'], $marker['fingerprint'], $marker['target_max_dimension'], $marker['target_max_bytes'] )
			&& self::OPTIMIZER_REVISION === (string) $marker['revision']
			&& (int) $marker['target_max_dimension'] === (int) $target_max_dimension
			&& (int) $marker['target_max_bytes'] === (int) $target_max_bytes
			&& '' !== $fingerprint
			&& hash_equals( (string) $marker['fingerprint'], $fingerprint ) ) {
			$result['target_missed'] = $this->optimizer_attachment_over_target( $files, $target_max_dimension, $target_max_bytes );
			return $result;
		}

		$optimized_files = 0;
		$failed_files    = 0;
		$after_total     = 0;
		foreach ( $files as $candidate ) {
			$file_result = $this->optimize_single_image_file( $candidate, $target_max_dimension, $target_max_bytes );
			if ( 'optimized' === (string) $file_result['status'] ) {
				$optimized_files++;
			} elseif ( 'failed' === (string) $file_result['status'] ) {
				$failed_files++;
			}
			$after_total += max( 0, (int) $file_result['bytes_after'] );
		}
		$result['bytes_after']   = $after_total;
		$result['bytes_saved']   = max( 0, (int) $result['bytes_before'] - $after_total );
		$result['target_missed'] = $this->optimizer_attachment_over_target( $files, $target_max_dimension, $target_max_bytes );
		if ( $optimized_files > 0 ) {
			$result['status'] = 'optimized';
		} elseif ( $failed_files > 0 ) {
			$result['status'] = 'failed';
		}

		if ( $optimized_files > 0 ) {
			$this->refresh_optimizer_attachment_metadata( $id, $file, $metadata );
		}

		/* Failed encodes are intentionally not marked idempotent: a later pass
		 * may succeed after server/editor capability changes. */
		$post_fingerprint = $this->optimizer_file_set_fingerprint( $files );
		if ( 0 === $failed_files && '' !== $post_fingerprint ) {
			update_post_meta(
				$id,
				'_gloskin_media_optimizer_state',
				array(
					'revision'             => self::OPTIMIZER_REVISION,
					'fingerprint'          => $post_fingerprint,
					'target_max_dimension' => (int) $target_max_dimension,
					'target_max_bytes'     => (int) $target_max_bytes,
					'optimized_at'         => time(),
				)
			);
		}
		return $result;
	}

	/** @return array<string,mixed> */
	private function optimize_single_image_file( $file, $target_max_dimension, $target_max_bytes ) {
		$before = is_file( $file ) ? max( 0, (int) filesize( $file ) ) : 0;
		$result = array( 'status' => 'skipped', 'bytes_before' => $before, 'bytes_after' => $before );
		$mime   = function_exists( 'wp_get_image_mime' ) ? (string) wp_get_image_mime( $file ) : '';
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ), true ) ) { return $result; }
		if ( ! function_exists( 'wp_get_image_editor' ) || ! function_exists( 'wp_image_editor_supports' ) || ! wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) { return $result; }

		$source = $this->optimizer_image_info( $file, $mime );
		if ( ! is_array( $source ) || empty( $source['width'] ) || empty( $source['height'] ) || empty( $source['mime'] ) ) {
			$result['status'] = 'failed';
			return $result;
		}
		if ( (int) $source['frames'] !== 1 ) { return $result; }
		if ( 'image/jpeg' === $mime && (int) $source['orientation'] > 1 ) { return $result; }

		$probe_editor = wp_get_image_editor( $file );
		if ( is_wp_error( $probe_editor ) ) { return $result; }
		$editor_class = get_class( $probe_editor );
		unset( $probe_editor );
		if ( 'image/avif' === $mime && false === stripos( $editor_class, 'imagick' ) ) { return $result; }
		if ( ! empty( $source['icc'] ) && false === stripos( $editor_class, 'imagick' ) ) {
			/* GD cannot reliably preserve an embedded ICC profile. */
			return $result;
		}

		$target_dimensions = $this->optimizer_target_dimensions( (int) $source['width'], (int) $source['height'], (int) $target_max_dimension );
		$target_width      = $target_dimensions[0];
		$target_height     = $target_dimensions[1];
		$resizing          = $target_width !== (int) $source['width'] || $target_height !== (int) $source['height'];

		$extension = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( '' === $extension ) { return $result; }
		$temp = dirname( $file ) . DIRECTORY_SEPARATOR . '.' . basename( $file ) . '.gloskin-opt-' . str_replace( '-', '', wp_generate_uuid4() ) . '.' . $extension;

		try {
			$qualities        = $this->optimizer_quality_plan( $mime, $before, $target_max_bytes, $resizing );
			$selected_quality = false;
			$candidate_ready  = false;
			foreach ( $qualities as $quality ) {
				$candidate_ready = false;
				if ( is_file( $temp ) ) { @unlink( $temp ); }
				if ( ! $this->optimizer_render_candidate( $file, $temp, $mime, $target_width, $target_height, $quality ) ) {
					continue;
				}
				if ( ! empty( $source['icc'] ) ) {
					$this->optimizer_restore_icc_profile( $file, $temp );
				}
				clearstatcache( true, $temp );
				$after     = is_file( $temp ) ? max( 0, (int) filesize( $temp ) ) : 0;
				$candidate = $this->optimizer_image_info( $temp, $mime );
				if ( $after <= 0 || $after >= $before || ! $this->optimizer_candidate_preserves( $source, $candidate, $target_max_dimension, $resizing ) ) {
					continue;
				}
				$selected_quality = $quality;
				$candidate_ready  = true;
				if ( $after <= (int) $target_max_bytes || ! in_array( $mime, array( 'image/jpeg', 'image/webp', 'image/avif' ), true ) ) {
					break;
				}
			}

			/* If a later quality attempt failed validation, rebuild the last known
			 * valid quality from the untouched source before replacing anything. */
			if ( ! $candidate_ready && false !== $selected_quality ) {
				if ( is_file( $temp ) ) { @unlink( $temp ); }
				if ( $this->optimizer_render_candidate( $file, $temp, $mime, $target_width, $target_height, $selected_quality ) ) {
					if ( ! empty( $source['icc'] ) ) { $this->optimizer_restore_icc_profile( $file, $temp ); }
					clearstatcache( true, $temp );
					$after     = is_file( $temp ) ? max( 0, (int) filesize( $temp ) ) : 0;
					$candidate = $this->optimizer_image_info( $temp, $mime );
					$candidate_ready = $after > 0 && $after < $before && $this->optimizer_candidate_preserves( $source, $candidate, $target_max_dimension, $resizing );
				}
			}

			if ( ! $candidate_ready || ! is_file( $temp ) ) { return $result; }
			$permissions = @fileperms( $file );
			if ( false !== $permissions ) { @chmod( $temp, $permissions & 0777 ); }
			/* Same-directory rename is atomic on normal POSIX filesystems. The
			 * original is never unlinked before the validated candidate exists. */
			if ( ! @rename( $temp, $file ) ) {
				$result['status'] = 'failed';
				return $result;
			}
			clearstatcache( true, $file );
			$result['status']      = 'optimized';
			$result['bytes_after'] = max( 0, (int) filesize( $file ) );
			return $result;
		} finally {
			if ( is_file( $temp ) ) { @unlink( $temp ); }
		}
	}

	/** @return array{0:int,1:int} */
	private function optimizer_target_dimensions( $width, $height, $max_dimension ) {
		$width         = max( 1, (int) $width );
		$height        = max( 1, (int) $height );
		$max_dimension = max( 1, (int) $max_dimension );
		$largest       = max( $width, $height );
		if ( $largest <= $max_dimension ) { return array( $width, $height ); }
		$ratio = $max_dimension / $largest;
		return array( max( 1, (int) round( $width * $ratio ) ), max( 1, (int) round( $height * $ratio ) ) );
	}

	/** @return array<int,int|null> */
	private function optimizer_quality_plan( $mime, $before, $target_max_bytes, $resizing ) {
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/webp', 'image/avif' ), true ) ) { return array( null ); }
		if ( ! $resizing && (int) $before <= (int) $target_max_bytes ) { return array( 92 ); }
		return array( 90, 86, 82 );
	}

	/** @return bool */
	private function optimizer_render_candidate( $source, $temp, $mime, $target_width, $target_height, $quality ) {
		$editor = wp_get_image_editor( $source );
		if ( is_wp_error( $editor ) ) { return false; }
		$size = $editor->get_size();
		if ( ! is_array( $size ) || empty( $size['width'] ) || empty( $size['height'] ) ) { return false; }
		if ( (int) $size['width'] !== (int) $target_width || (int) $size['height'] !== (int) $target_height ) {
			$resized = $editor->resize( (int) $target_width, (int) $target_height, false );
			if ( is_wp_error( $resized ) ) { return false; }
		}
		if ( null !== $quality ) {
			$quality_result = $editor->set_quality( (int) $quality );
			if ( is_wp_error( $quality_result ) ) { return false; }
		}
		$saved = $editor->save( $temp, $mime );
		return ! is_wp_error( $saved ) && is_file( $temp ) && is_readable( $temp );
	}

	/** @return bool */
	private function optimizer_candidate_preserves( array $source, $candidate, $max_dimension, $resizing ) {
		if ( ! is_array( $candidate ) || (string) $candidate['mime'] !== (string) $source['mime'] ) { return false; }
		if ( (bool) $candidate['alpha'] !== (bool) $source['alpha'] || (int) $candidate['frames'] !== (int) $source['frames'] ) { return false; }
		if ( ! empty( $source['icc'] ) && empty( $candidate['icc'] ) ) { return false; }

		if ( ! $resizing ) {
			return (int) $candidate['width'] === (int) $source['width'] && (int) $candidate['height'] === (int) $source['height'];
		}
		if ( max( (int) $candidate['width'], (int) $candidate['height'] ) > (int) $max_dimension ) { return false; }
		if ( (int) $candidate['width'] > (int) $source['width'] || (int) $candidate['height'] > (int) $source['height'] ) { return false; }
		$source_ratio    = (int) $source['width'] / max( 1, (int) $source['height'] );
		$candidate_ratio = (int) $candidate['width'] / max( 1, (int) $candidate['height'] );
		return abs( $source_ratio - $candidate_ratio ) <= 0.0025;
	}

	/** @return void */
	private function optimizer_restore_icc_profile( $source, $candidate ) {
		if ( ! class_exists( 'Imagick' ) ) { return; }
		try {
			$source_image = new Imagick( $source );
			$profile      = $source_image->getImageProfile( 'icc' );
			$source_image->clear();
			$source_image->destroy();
			if ( ! $profile ) { return; }
			$candidate_image = new Imagick( $candidate );
			$candidate_image->setImageProfile( 'icc', $profile );
			$candidate_image->writeImage( $candidate );
			$candidate_image->clear();
			$candidate_image->destroy();
		} catch ( Throwable $ignored ) {
			/* Validation below fails closed if the profile could not be restored. */
		}
	}

	/** @return bool */
	private function optimizer_attachment_over_target( array $files, $target_max_dimension, $target_max_bytes ) {
		foreach ( array_values( array_unique( $files ) ) as $file ) {
			if ( ! is_file( $file ) || ! is_readable( $file ) ) { return true; }
			if ( max( 0, (int) filesize( $file ) ) > (int) $target_max_bytes ) { return true; }
			$dimensions = @getimagesize( $file );
			if ( is_array( $dimensions ) && isset( $dimensions[0], $dimensions[1] ) && max( (int) $dimensions[0], (int) $dimensions[1] ) > (int) $target_max_dimension ) {
				return true;
			}
		}
		return false;
	}

	/** @param string $file @param string $expected_mime @return array<string,mixed>|false */
	private function optimizer_image_info( $file, $expected_mime ) {
		$dimensions = @getimagesize( $file );
		$mime       = function_exists( 'wp_get_image_mime' ) ? (string) wp_get_image_mime( $file ) : '';
		if ( ! is_array( $dimensions ) || ! isset( $dimensions[0], $dimensions[1] ) || '' === $mime || $mime !== $expected_mime ) { return false; }
		$alpha       = false;
		$frames      = 1;
		$icc         = false;
		$orientation = 1;

		if ( class_exists( 'Imagick' ) ) {
			try {
				$image  = new Imagick( $file );
				$frames = max( 1, (int) $image->getNumberImages() );
				if ( method_exists( $image, 'getImageAlphaChannel' ) ) { $alpha = (bool) $image->getImageAlphaChannel(); }
				$profiles = $image->getImageProfiles( 'icc', false );
				$icc      = ! empty( $profiles );
				$image->clear();
				$image->destroy();
			} catch ( Throwable $ignored ) {
				if ( 'image/avif' === $expected_mime ) { return false; }
			}
		} else {
			$probe  = $this->optimizer_file_probe( $file, 1048576 );
			$frames = $this->optimizer_probe_frames( $probe, $expected_mime );
			$alpha  = $this->optimizer_probe_alpha( $probe, $expected_mime );
			$icc    = $this->optimizer_probe_icc( $probe, $expected_mime );
			if ( 'image/avif' === $expected_mime ) { return false; }
		}

		if ( 'image/jpeg' === $expected_mime ) {
			if ( ! function_exists( 'wp_read_image_metadata' ) && defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/image.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			if ( function_exists( 'wp_read_image_metadata' ) ) {
				$image_meta = wp_read_image_metadata( $file );
				if ( is_array( $image_meta ) && ! empty( $image_meta['orientation'] ) ) { $orientation = absint( $image_meta['orientation'] ); }
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

	/** @return string */
	private function optimizer_file_probe( $file, $limit ) {
		$handle = @fopen( $file, 'rb' );
		if ( ! is_resource( $handle ) ) { return ''; }
		$data = (string) fread( $handle, max( 1, (int) $limit ) );
		fclose( $handle );
		return $data;
	}

	/** @return int */
	private function optimizer_probe_frames( $probe, $mime ) {
		if ( 'image/png' === $mime && false !== strpos( $probe, 'acTL' ) ) { return 2; }
		if ( 'image/webp' === $mime && ( false !== strpos( $probe, 'ANIM' ) || false !== strpos( $probe, 'ANMF' ) ) ) { return 2; }
		return 1;
	}

	/** @return bool */
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

	/** @return bool */
	private function optimizer_probe_icc( $probe, $mime ) {
		if ( 'image/jpeg' === $mime ) { return false !== strpos( $probe, 'ICC_PROFILE' ); }
		if ( 'image/png' === $mime ) { return false !== strpos( $probe, 'iCCP' ); }
		if ( 'image/webp' === $mime ) { return false !== strpos( $probe, 'ICCP' ); }
		return false;
	}

	/** @return string */
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

	/** @return string */
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

	/**
	 * Refresh only metadata for files that already exist. No image sizes are
	 * regenerated; width/height/filesize are synchronized to in-place results.
	 *
	 * @return void
	 */
	private function refresh_optimizer_attachment_metadata( $id, $file, array $metadata ) {
		$changed = false;
		if ( is_file( $file ) ) {
			$dimensions = @getimagesize( $file );
			if ( is_array( $dimensions ) && isset( $dimensions[0], $dimensions[1] ) ) {
				if ( (int) ( $metadata['width'] ?? 0 ) !== (int) $dimensions[0] ) { $metadata['width'] = (int) $dimensions[0]; $changed = true; }
				if ( (int) ( $metadata['height'] ?? 0 ) !== (int) $dimensions[1] ) { $metadata['height'] = (int) $dimensions[1]; $changed = true; }
			}
			$new_size = max( 0, (int) filesize( $file ) );
			if ( (int) ( $metadata['filesize'] ?? -1 ) !== $new_size ) { $metadata['filesize'] = $new_size; $changed = true; }
		}
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$dir = dirname( $file );
			foreach ( $metadata['sizes'] as $name => $size ) {
				if ( ! is_array( $size ) || empty( $size['file'] ) ) { continue; }
				$size_file = $dir . DIRECTORY_SEPARATOR . basename( (string) $size['file'] );
				if ( ! is_file( $size_file ) ) { continue; }
				$dimensions = @getimagesize( $size_file );
				if ( is_array( $dimensions ) && isset( $dimensions[0], $dimensions[1] ) ) {
					if ( (int) ( $metadata['sizes'][ $name ]['width'] ?? 0 ) !== (int) $dimensions[0] ) { $metadata['sizes'][ $name ]['width'] = (int) $dimensions[0]; $changed = true; }
					if ( (int) ( $metadata['sizes'][ $name ]['height'] ?? 0 ) !== (int) $dimensions[1] ) { $metadata['sizes'][ $name ]['height'] = (int) $dimensions[1]; $changed = true; }
				}
				$new_size = max( 0, (int) filesize( $size_file ) );
				if ( (int) ( $metadata['sizes'][ $name ]['filesize'] ?? -1 ) !== $new_size ) { $metadata['sizes'][ $name ]['filesize'] = $new_size; $changed = true; }
			}
		}
		if ( $changed ) { wp_update_attachment_metadata( $id, $metadata ); }
	}
}
