<?php
/**
 * Pure immutable-package validation used before Final Migration roster mutation.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Final_Package_Validator {
	const PHOTO_BUNDLE_ID = 'gloskin-doctor-photos-v2';
	const PHOTO_EXPECTED   = 12;

	/** @var string */
	private $plugin_file;

	/** @var string */
	private $photo_dir;

	/** @param string $plugin_file Main plugin file. */
	public function __construct( $plugin_file ) {
		$this->plugin_file = (string) $plugin_file;
		$this->photo_dir   = trailingslashit( plugin_dir_path( $plugin_file ) ) . 'migration-runtime/' . self::PHOTO_BUNDLE_ID;
	}

	/**
	 * Validate immutable packages B and C after the importer has already
	 * validated roster package A. This method performs no WordPress mutation.
	 *
	 * @return array<string,mixed>
	 */
	public function validate_after_roster_bundle() {
		$photos = $this->validate_doctor_photos();
		require_once __DIR__ . '/class-gloskin-site-core-editorial-media-bundle.php';
		$editorial = ( new Gloskin_Site_Core_Editorial_Media_Bundle( $this->plugin_file ) )->preflight();
		return array(
			'photo_bundle'     => $photos,
			'editorial_bundle' => $editorial,
		);
	}

	/**
	 * Validate the immutable 12-photo primary payload without matching or DB IO.
	 *
	 * @return array<string,mixed>
	 */
	public function validate_doctor_photos() {
		$manifest_path = $this->photo_dir . '/manifest.json';
		if ( ! is_readable( $manifest_path ) ) {
			throw new RuntimeException( 'bundle_unavailable: Doctor photo manifest missing.' );
		}
		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( ! is_array( $manifest ) || self::PHOTO_BUNDLE_ID !== (string) ( $manifest['bundle_id'] ?? '' ) ) {
			throw new RuntimeException( 'bundle_invalid: Doctor photo manifest invalid.' );
		}
		$doctors = isset( $manifest['doctors'] ) && is_array( $manifest['doctors'] ) ? array_values( $manifest['doctors'] ) : array();
		if ( self::PHOTO_EXPECTED !== count( $doctors ) ) {
			throw new RuntimeException( 'bundle_invalid: Doctor photo manifest must contain exactly 12 doctors.' );
		}

		$seen_files = array();
		$seen_shas  = array();
		foreach ( $doctors as $index => $doctor ) {
			if ( ! is_array( $doctor ) ) {
				throw new RuntimeException( 'bundle_invalid: Doctor photo entry #' . $index . ' invalid.' );
			}
			$file = (string) ( $doctor['primary_webp'] ?? '' );
			$sha  = strtolower( (string) ( $doctor['primary_sha256'] ?? '' ) );
			$label = trim( (string) ( $doctor['source_label'] ?? '' ) );
			$aliases = isset( $doctor['match_aliases'] ) && is_array( $doctor['match_aliases'] ) ? $doctor['match_aliases'] : array();
			if ( '' === $label || ! $aliases || '' === $file || basename( $file ) !== $file || ! preg_match( '/^[a-f0-9]{64}$/', $sha ) ) {
				throw new RuntimeException( 'bundle_invalid: Doctor photo entry #' . $index . ' incomplete.' );
			}
			if ( isset( $seen_files[ $file ] ) || isset( $seen_shas[ $sha ] ) ) {
				throw new RuntimeException( 'bundle_invalid: Doctor photo primary file/SHA duplicated.' );
			}
			$path = $this->photo_dir . '/' . $file;
			if ( ! is_readable( $path ) ) {
				throw new RuntimeException( 'bundle_invalid: Doctor photo primary file unreadable: ' . $file );
			}
			$actual = hash_file( 'sha256', $path );
			if ( ! is_string( $actual ) || ! hash_equals( $sha, strtolower( $actual ) ) ) {
				throw new RuntimeException( 'bundle_invalid: Doctor photo SHA mismatch: ' . $file );
			}
			$seen_files[ $file ] = true;
			$seen_shas[ $sha ] = true;
		}
		return $manifest;
	}
}
