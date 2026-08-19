<?php
/**
 * Pure immutable-package validation used before Final Migration roster mutation.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Final_Package_Validator {
	const PHOTO_BUNDLE_ID       = 'gloskin-doctor-photos-v2';
	const PHOTO_BUNDLE_REVISION = '2026-08-19-remastered';
	const PHOTO_EXPECTED        = 12;

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
	 * validated roster package A. Cross-package aliases are also proven to map
	 * exactly one photo to exactly one roster identity. No WordPress mutation.
	 *
	 * @param array<string,mixed> $roster_payload Validated doctors-v1 payload.
	 * @return array<string,mixed>
	 */
	public function validate_after_roster_bundle( array $roster_payload ) {
		$photos = $this->validate_doctor_photos();
		$this->validate_roster_photo_compatibility( $roster_payload, $photos );
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
		if ( ! is_array( $manifest )
			|| self::PHOTO_BUNDLE_ID !== (string) ( $manifest['bundle_id'] ?? '' )
			|| self::PHOTO_BUNDLE_REVISION !== (string) ( $manifest['bundle_revision'] ?? '' ) ) {
			throw new RuntimeException( 'bundle_invalid: Doctor photo manifest identity/revision invalid.' );
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
			$file    = (string) ( $doctor['primary_webp'] ?? '' );
			$sha     = strtolower( (string) ( $doctor['primary_sha256'] ?? '' ) );
			$label   = trim( (string) ( $doctor['source_label'] ?? '' ) );
			$aliases = isset( $doctor['match_aliases'] ) && is_array( $doctor['match_aliases'] ) ? $doctor['match_aliases'] : array();
			$width   = absint( $doctor['width'] ?? 0 );
			$height  = absint( $doctor['height'] ?? 0 );
			$bytes   = absint( $doctor['bytes'] ?? 0 );
			if ( '' === $label || ! $aliases || '' === $file || basename( $file ) !== $file || ! preg_match( '/^[a-f0-9]{64}$/', $sha ) || $width < 1 || $height < 1 || $bytes < 1 ) {
				throw new RuntimeException( 'bundle_invalid: Doctor photo entry #' . $index . ' incomplete.' );
			}
			if ( isset( $seen_files[ $file ] ) || isset( $seen_shas[ $sha ] ) ) {
				throw new RuntimeException( 'bundle_invalid: Doctor photo primary file/SHA duplicated.' );
			}
			$path = $this->photo_dir . '/' . $file;
			if ( ! is_readable( $path ) ) {
				throw new RuntimeException( 'bundle_invalid: Doctor photo primary file unreadable: ' . $file );
			}
			$actual_size = filesize( $path );
			$actual_info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- false is handled as invalid input.
			if ( false === $actual_size || $bytes !== (int) $actual_size || false === $actual_info
				|| $width !== (int) $actual_info[0] || $height !== (int) $actual_info[1]
				|| 'image/webp' !== (string) ( $actual_info['mime'] ?? '' ) ) {
				throw new RuntimeException( 'bundle_invalid: Doctor photo primary dimensions/bytes/mime mismatch: ' . $file );
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

	/**
	 * Prove exact roster/photo referential compatibility using package data only.
	 * This intentionally performs no DB query and no fuzzy/similarity matching.
	 *
	 * @param array<string,mixed> $roster_payload Validated roster bundle.
	 * @param array<string,mixed> $photo_manifest Validated photo manifest.
	 * @return void
	 */
	private function validate_roster_photo_compatibility( array $roster_payload, array $photo_manifest ) {
		$roster = isset( $roster_payload['doctors'] ) && is_array( $roster_payload['doctors'] ) ? array_values( $roster_payload['doctors'] ) : array();
		$photos = isset( $photo_manifest['doctors'] ) && is_array( $photo_manifest['doctors'] ) ? array_values( $photo_manifest['doctors'] ) : array();
		$identity_map = array();
		foreach ( $roster as $doctor ) {
			if ( ! is_array( $doctor ) ) { continue; }
			$source_id = (string) ( $doctor['source_id'] ?? '' );
			foreach ( array( (string) ( $doctor['post_title'] ?? '' ), str_replace( '-', ' ', (string) ( $doctor['slug'] ?? '' ) ) ) as $candidate ) {
				$normalized = $this->normalize_identity( $candidate );
				if ( '' !== $normalized && '' !== $source_id ) { $identity_map[ $normalized ][ $source_id ] = true; }
			}
		}

		$used_roster_ids = array();
		foreach ( $photos as $photo ) {
			$label   = (string) ( $photo['source_label'] ?? '' );
			$aliases = isset( $photo['match_aliases'] ) && is_array( $photo['match_aliases'] ) ? $photo['match_aliases'] : array();
			$matched = array();
			foreach ( $aliases as $alias ) {
				$normalized = $this->normalize_identity( (string) $alias );
				foreach ( array_keys( $identity_map[ $normalized ] ?? array() ) as $source_id ) { $matched[ $source_id ] = true; }
			}
			if ( 1 !== count( $matched ) ) {
				throw new RuntimeException( 'bundle_invalid: Doctor photo alias must resolve exactly one doctors-v1 identity before mutation: ' . $label );
			}
			$source_id = (string) array_key_first( $matched );
			if ( isset( $used_roster_ids[ $source_id ] ) ) {
				throw new RuntimeException( 'bundle_invalid: Multiple doctor photos resolve to one doctors-v1 identity before mutation: ' . $source_id );
			}
			$used_roster_ids[ $source_id ] = true;
		}
	}

	/** @param string $name @return string */
	private function normalize_identity( $name ) {
		$name = mb_strtolower( (string) $name, 'UTF-8' );
		$name = trim( $name );
		$name = (string) preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $name );
		$name = (string) preg_replace( '/\s+/', ' ', $name );
		$name = trim( $name );
		if ( preg_match( '/^dr\s+(.+)$/u', $name, $matches ) ) { $name = trim( $matches[1] ); }
		return $name;
	}
}
