<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
trait Gloskin_Site_Core_Doctor_Importer_Finalize_Trait {
	/** @param array<string,mixed> $state State. @param array<string,mixed> $payload Payload. @return array<string,mixed> */
	private function finalize( $state, $payload ) {
		$verified_ids = array();
		foreach ( $payload['doctors'] as $record ) {
			$ids = get_posts(
				array(
					'post_type'      => Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
					'posts_per_page' => 2,
					'fields'         => 'ids',
					'meta_key'       => self::SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- final migration identity verification.
					'meta_value'     => $record['source_id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- final migration identity verification.
				)
			);
			if ( 1 !== count( $ids ) ) {
				throw new RuntimeException( __( 'Final doctor verification gagal: source ID tidak resolve tepat satu record.', 'gloskin-site-core' ) );
			}
			$verified_ids[] = absint( $ids[0] );
		}
		if ( Gloskin_Site_Core_Doctor_Bundle::EXPECTED_DOCTORS !== count( array_unique( $verified_ids ) ) ) {
			throw new RuntimeException( __( 'Final doctor verification gagal: jumlah record tidak tepat 13.', 'gloskin-site-core' ) );
		}
		$state['status'] = 'consumed';
		$state['index'] = Gloskin_Site_Core_Doctor_Bundle::EXPECTED_DOCTORS;
		$state['imported_ids'] = array_values( array_unique( $verified_ids ) );
		$state['consumed_at'] = time();
		$state['last_error'] = '';
		$state['cleanup'] = 'pending';
		$state['cleanup_error'] = '';
		$this->save_state( $state ); // Persist consumed BEFORE filesystem cleanup.

		try {
			$this->cleanup_runtime( $payload['manifest'] );
			$state['cleanup'] = 'done';
		} catch ( Throwable $error ) {
			$state['cleanup'] = 'failed';
			$state['cleanup_error'] = mb_substr( sanitize_text_field( $error->getMessage() ), 0, 500 );
		}
		$this->save_state( $state );
		return $state;
	}

	/** @param array<string,mixed> $manifest Manifest. @return void */
	private function cleanup_runtime( $manifest ) {
		$allowed = isset( $manifest['cleanup_files'] ) && is_array( $manifest['cleanup_files'] ) ? $manifest['cleanup_files'] : array();
		if ( $allowed !== array( 'doctors.json', 'manifest.json' ) ) {
			throw new RuntimeException( __( 'Cleanup allowlist tidak valid.', 'gloskin-site-core' ) );
		}
		$dir = $this->bundle->runtime_dir();
		foreach ( $allowed as $filename ) {
			if ( ! in_array( $filename, array( 'doctors.json', 'manifest.json' ), true ) ) {
				throw new RuntimeException( __( 'Cleanup mencoba file di luar manifest.', 'gloskin-site-core' ) );
			}
			$path = $dir . '/' . $filename;
			if ( file_exists( $path ) && ! @unlink( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- failure is persisted and never reopens consumed state.
				throw new RuntimeException( sprintf( /* translators: %s: filename. */ __( 'Gagal menghapus runtime payload %s.', 'gloskin-site-core' ), $filename ) );
			}
		}
		if ( is_dir( $dir ) ) { @rmdir( $dir ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- directory removal is best-effort after declared files only.
	}
}
