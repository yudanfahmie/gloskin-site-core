<?php
/**
 * Deterministic Gloskin doctor source-bundle validator.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Doctor_Bundle {
	const BUNDLE_ID = 'gloskin-doctors-v1';
	const EXPECTED_DOCTORS = 13;
	const MANIFEST_MAX_BYTES = 16384;

	/** @var string */
	private $runtime_dir;

	/** @param string $plugin_file Main plugin file. */
	public function __construct( $plugin_file ) {
		$this->runtime_dir = trailingslashit( dirname( (string) $plugin_file ) ) . 'migration-runtime/' . self::BUNDLE_ID;
	}

	/** @return string */
	public function runtime_dir() {
		return $this->runtime_dir;
	}

	/**
	 * Validate the entire bundle before any importer mutation.
	 *
	 * @return array{manifest:array<string,mixed>,doctors:array<int,array<string,string>>}
	 * @throws RuntimeException Invalid bundle.
	 */
	public function load() {
		$manifest_path = $this->runtime_dir . '/manifest.json';
		if ( ! is_readable( $manifest_path ) ) {
			throw new RuntimeException( __( 'Doctor migration manifest tidak tersedia.', 'gloskin-site-core' ) );
		}
		$manifest_size = filesize( $manifest_path );
		if ( false === $manifest_size || $manifest_size < 2 || $manifest_size > self::MANIFEST_MAX_BYTES ) {
			throw new RuntimeException( __( 'Doctor migration manifest melewati batas ukuran.', 'gloskin-site-core' ) );
		}
		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( ! is_array( $manifest ) || self::BUNDLE_ID !== ( isset( $manifest['bundle_id'] ) ? $manifest['bundle_id'] : '' ) || self::EXPECTED_DOCTORS !== absint( isset( $manifest['expected_doctors'] ) ? $manifest['expected_doctors'] : 0 ) ) {
			throw new RuntimeException( __( 'Doctor migration manifest tidak valid.', 'gloskin-site-core' ) );
		}
		$files = isset( $manifest['files'] ) && is_array( $manifest['files'] ) ? $manifest['files'] : array();
		if ( array_keys( $files ) !== array( 'doctors.json' ) ) {
			throw new RuntimeException( __( 'Doctor migration manifest file allowlist tidak valid.', 'gloskin-site-core' ) );
		}
		$this->validate_file( 'doctors.json', $files['doctors.json'] );

		$doctors = json_decode( (string) file_get_contents( $this->runtime_dir . '/doctors.json' ), true );
		if ( ! is_array( $doctors ) || self::BUNDLE_ID !== ( isset( $doctors['bundle_id'] ) ? $doctors['bundle_id'] : '' ) || empty( $doctors['doctors'] ) || ! is_array( $doctors['doctors'] ) || self::EXPECTED_DOCTORS !== count( $doctors['doctors'] ) ) {
			throw new RuntimeException( __( 'Doctor migration payload tidak valid.', 'gloskin-site-core' ) );
		}
		$seen_ids = array();
		$seen_slugs = array();
		$validated = array();
		foreach ( $doctors['doctors'] as $record ) {
			if ( ! is_array( $record ) ) {
				throw new RuntimeException( __( 'Doctor migration record tidak valid.', 'gloskin-site-core' ) );
			}
			$source_id = isset( $record['source_id'] ) ? sanitize_key( $record['source_id'] ) : '';
			$source_url = isset( $record['source_url'] ) ? esc_url_raw( $record['source_url'], array( 'https' ) ) : '';
			$source_label = isset( $record['source_label'] ) ? sanitize_text_field( $record['source_label'] ) : '';
			$checked_at = isset( $record['source_checked_at'] ) ? sanitize_text_field( $record['source_checked_at'] ) : '';
			$display_name = isset( $record['source_display_name'] ) ? sanitize_text_field( $record['source_display_name'] ) : '';
			$title = isset( $record['post_title'] ) ? sanitize_text_field( $record['post_title'] ) : '';
			$slug = isset( $record['slug'] ) ? sanitize_title( $record['slug'] ) : '';
			if ( '' === $source_id || '' === $source_url || '' === $source_label || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checked_at ) || '' === $display_name || '' === $title || '' === $slug ) {
				throw new RuntimeException( __( 'Doctor migration record kehilangan provenance/identity wajib.', 'gloskin-site-core' ) );
			}
			if ( isset( $seen_ids[ $source_id ] ) || isset( $seen_slugs[ $slug ] ) ) {
				throw new RuntimeException( __( 'Doctor migration source ID/slug duplikat.', 'gloskin-site-core' ) );
			}
			if ( $display_name !== $title ) {
				throw new RuntimeException( __( 'Doctor migration title harus sama dengan official display name.', 'gloskin-site-core' ) );
			}
			$seen_ids[ $source_id ] = true;
			$seen_slugs[ $slug ] = true;
			$validated[] = array(
				'source_id'           => $source_id,
				'source_url'          => $source_url,
				'source_label'        => $source_label,
				'source_checked_at'   => $checked_at,
				'source_display_name' => $display_name,
				'post_title'          => $title,
				'slug'                => $slug,
			);
		}
		$cleanup = isset( $manifest['cleanup_files'] ) && is_array( $manifest['cleanup_files'] ) ? array_values( $manifest['cleanup_files'] ) : array();
		if ( $cleanup !== array( 'doctors.json', 'manifest.json' ) ) {
			throw new RuntimeException( __( 'Doctor migration cleanup allowlist tidak valid.', 'gloskin-site-core' ) );
		}
		return array( 'manifest' => $manifest, 'doctors' => $validated );
	}

	/** @param string $name Filename. @param mixed $spec Manifest spec. @return void */
	private function validate_file( $name, $spec ) {
		if ( ! is_array( $spec ) || ! isset( $spec['bytes'], $spec['sha256'], $spec['max_bytes'] ) ) {
			throw new RuntimeException( __( 'Doctor migration checksum spec tidak valid.', 'gloskin-site-core' ) );
		}
		$path = $this->runtime_dir . '/' . $name;
		if ( ! is_readable( $path ) ) {
			throw new RuntimeException( __( 'Doctor migration payload tidak terbaca.', 'gloskin-site-core' ) );
		}
		$size = filesize( $path );
		$expected = absint( $spec['bytes'] );
		$max = absint( $spec['max_bytes'] );
		if ( false === $size || $size !== $expected || $size < 2 || $size > $max || $max > 262144 ) {
			throw new RuntimeException( __( 'Doctor migration payload size tidak valid.', 'gloskin-site-core' ) );
		}
		$actual_hash = hash_file( 'sha256', $path );
		$expected_hash = strtolower( (string) $spec['sha256'] );
		if ( ! is_string( $actual_hash ) || ! preg_match( '/^[a-f0-9]{64}$/', $expected_hash ) || ! hash_equals( $expected_hash, strtolower( $actual_hash ) ) ) {
			throw new RuntimeException( __( 'Doctor migration checksum gagal.', 'gloskin-site-core' ) );
		}
	}
}
