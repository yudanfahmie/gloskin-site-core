<?php
/**
 * Bounded first-party editorial media bundle importer for the final migration.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Gloskin_Site_Core_Editorial_Media_Bundle {
	const BUNDLE_ID = 'gloskin-editorial-media-v1';
	const BUNDLE_DIR = 'gloskin-editorial-media-v1';
	const OPTION = 'gloskin_site_core_editorial_media_v1';
	const KEY_META = '_gloskin_editorial_media_key';
	const SHA_META = '_gloskin_editorial_media_sha256';
	const SOURCE_META = '_gloskin_editorial_media_source_url';
	const SOURCE_PAGE_META = '_gloskin_editorial_media_source_page';
	const REVISION_META = '_gloskin_editorial_media_revision';
	const REVISION = '2026-08-19-final';
	/** @var string */ private $dir;

	/** @param string $plugin_file */
	public function __construct( $plugin_file ) {
		$this->dir = trailingslashit( plugin_dir_path( $plugin_file ) ) . 'migration-runtime/' . self::BUNDLE_DIR;
	}

	/** @return array<string,mixed> */
	public function preflight() {
		$manifest = $this->manifest();
		$required = array( 'home_why', 'home_brand_story', 'treatment_discovery', 'treatment_clinical', 'skincare_editorial', 'about_story' );
		$seen = array();
		foreach ( $manifest['items'] as $item ) {
			$key = sanitize_key( (string) ( $item['key'] ?? '' ) );
			$file = basename( (string) ( $item['file'] ?? '' ) );
			$sha = strtolower( (string) ( $item['sha256'] ?? '' ) );
			$path = $this->dir . '/' . $file;
			if ( '' === $key || '' === $file || 64 !== strlen( $sha ) || ! is_readable( $path ) ) {
				throw new RuntimeException( 'bundle_invalid: Editorial media entry is incomplete: ' . $key );
			}
			$actual = hash_file( 'sha256', $path );
			if ( ! is_string( $actual ) || ! hash_equals( $sha, strtolower( $actual ) ) ) {
				throw new RuntimeException( 'bundle_invalid: Editorial media SHA mismatch: ' . $key );
			}
			$image_info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- validation handles false explicitly.
			$width = (int) ( $item['width'] ?? 0 ); $height = (int) ( $item['height'] ?? 0 ); $mime = (string) ( $item['mime'] ?? '' );
			if ( false === $image_info || $width < 1 || $height < 1 || $width !== (int) $image_info[0] || $height !== (int) $image_info[1] || $mime !== (string) ( $image_info['mime'] ?? '' ) ) {
				throw new RuntimeException( 'bundle_invalid: Editorial media dimensions/mime mismatch: ' . $key );
			}
			if ( empty( $item['semantic_role'] ) || empty( $item['source_page'] ) || empty( $item['source_asset_url'] ) || 'first-party-gloskin' !== (string) ( $item['source_type'] ?? '' ) || ! array_key_exists( 'decorative', $item ) || ! array_key_exists( 'alt', $item ) ) {
				throw new RuntimeException( 'bundle_invalid: Editorial media semantic/provenance metadata incomplete: ' . $key );
			}
			if ( empty( $item['decorative'] ) && '' === trim( (string) $item['alt'] ) ) {
				throw new RuntimeException( 'bundle_invalid: Meaningful editorial media requires concise alt text: ' . $key );
			}
			if ( isset( $seen[ $key ] ) ) { throw new RuntimeException( 'bundle_invalid: Duplicate editorial media key: ' . $key ); }
			$seen[ $key ] = true;
		}
		foreach ( $required as $key ) {
			if ( empty( $seen[ $key ] ) ) { throw new RuntimeException( 'bundle_invalid: Required editorial media key missing: ' . $key ); }
		}
		return $manifest;
	}

	/** @return array<string,mixed> */
	public function import() {
		$manifest = $this->preflight();
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) || empty( $upload['path'] ) || ! is_dir( $upload['path'] ) || ! is_writable( $upload['path'] ) ) {
			throw new RuntimeException( 'upload_unavailable: Editorial media upload directory is not writable.' );
		}
		$catalog = array();
		$audit = array( 'created' => array(), 'reused' => array() );
		foreach ( $manifest['items'] as $item ) {
			$key = sanitize_key( (string) $item['key'] );
			$sha = strtolower( (string) $item['sha256'] );
			$attachment_id = $this->find_attachment( $key, $sha );
			$bucket = 'reused';
			if ( ! $attachment_id ) {
				$attachment_id = $this->import_one( $item, $upload );
				$bucket = 'created';
			}
			$catalog[ $key ] = array(
				'attachment_id' => $attachment_id,
				'kind' => sanitize_key( (string) ( $item['kind'] ?? 'editorial' ) ),
				'role' => sanitize_text_field( (string) ( $item['role'] ?? '' ) ),
				'sha256' => $sha,
				'source_page' => esc_url_raw( (string) ( $item['source_page'] ?? '' ) ),
				'source_asset_url' => esc_url_raw( (string) ( $item['source_asset_url'] ?? '' ) ),
				'width' => absint( $item['width'] ?? 0 ), 'height' => absint( $item['height'] ?? 0 ),
				'mime' => sanitize_mime_type( (string) ( $item['mime'] ?? '' ) ),
				'semantic_role' => sanitize_text_field( (string) ( $item['semantic_role'] ?? '' ) ),
				'source_type' => sanitize_key( (string) ( $item['source_type'] ?? '' ) ),
				'decorative' => ! empty( $item['decorative'] ),
				'alt' => sanitize_text_field( (string) ( $item['alt'] ?? '' ) ),
			);
			$audit[ $bucket ][] = array( 'key' => $key, 'attachment_id' => $attachment_id, 'sha256' => $sha );
		}
		update_option( self::OPTION, $catalog, false );
		$audit['catalog'] = $catalog;
		return $audit;
	}

	/** @param array<string,mixed> $audit @return void */
	public function verify( array $audit ) {
		$catalog = get_option( self::OPTION, array() );
		$catalog = is_array( $catalog ) ? $catalog : array();
		$required = array( 'home_why', 'home_brand_story', 'treatment_discovery', 'treatment_clinical', 'skincare_editorial', 'about_story' );
		foreach ( $required as $key ) {
			$entry = isset( $catalog[ $key ] ) && is_array( $catalog[ $key ] ) ? $catalog[ $key ] : array();
			$attachment_id = absint( $entry['attachment_id'] ?? 0 );
			if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
				throw new RuntimeException( 'verification_failed: Editorial media attachment missing: ' . $key );
			}
			$file = get_attached_file( $attachment_id );
			if ( ! is_string( $file ) || '' === $file || ! is_readable( $file ) ) {
				throw new RuntimeException( 'verification_failed: Editorial media is not locally hosted: ' . $key );
			}
			$expected_sha = strtolower( (string) ( $entry['sha256'] ?? '' ) );
			$stored_sha = strtolower( (string) get_post_meta( $attachment_id, self::SHA_META, true ) );
			if ( 64 !== strlen( $expected_sha ) || ! hash_equals( $expected_sha, $stored_sha ) ) {
				throw new RuntimeException( 'verification_failed: Editorial media provenance SHA mismatch: ' . $key );
			}
			if ( '' === (string) get_post_meta( $attachment_id, self::SOURCE_META, true ) || '' === (string) get_post_meta( $attachment_id, self::SOURCE_PAGE_META, true ) ) {
				throw new RuntimeException( 'verification_failed: Editorial media provenance missing: ' . $key );
			}
		}
		$audited = count( (array) ( $audit['created'] ?? array() ) ) + count( (array) ( $audit['reused'] ?? array() ) );
		if ( $audited < count( $required ) ) { throw new RuntimeException( 'verification_failed: Editorial media audit is incomplete.' ); }
	}

	/** @return array<string,mixed> */
	private function manifest() {
		$path = $this->dir . '/manifest.json';
		if ( ! is_readable( $path ) ) { throw new RuntimeException( 'bundle_unavailable: Editorial media manifest missing.' ); }
		$manifest = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $manifest ) || self::BUNDLE_ID !== (string) ( $manifest['bundle_id'] ?? '' ) || ! isset( $manifest['items'] ) || ! is_array( $manifest['items'] ) ) {
			throw new RuntimeException( 'bundle_invalid: Editorial media manifest invalid.' );
		}
		return $manifest;
	}

	/** @return int */
	private function find_attachment( $key, $sha ) {
		$ids = get_posts( array(
			'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids',
			'meta_query' => array( 'relation' => 'AND', array( 'key' => self::KEY_META, 'value' => $key ), array( 'key' => self::SHA_META, 'value' => $sha ) ),
		) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		return ! empty( $ids ) ? absint( $ids[0] ) : 0;
	}

	/** @param array<string,mixed> $item @param array<string,mixed> $upload @return int */
	private function import_one( array $item, array $upload ) {
		$key = sanitize_key( (string) $item['key'] );
		$source_file = $this->dir . '/' . basename( (string) $item['file'] );
		$dest_name = wp_unique_filename( (string) $upload['path'], basename( (string) $item['file'] ) );
		$dest_path = trailingslashit( (string) $upload['path'] ) . $dest_name;
		if ( ! copy( $source_file, $dest_path ) ) { throw new RuntimeException( 'upload_unavailable: Failed to copy editorial media ' . $key ); }
		$filetype = wp_check_filetype( $dest_name, null );
		$attachment_id = wp_insert_attachment( array(
			'post_mime_type' => ! empty( $filetype['type'] ) ? (string) $filetype['type'] : 'image/webp',
			'post_title' => sanitize_text_field( (string) ( $item['role'] ?? $key ) ),
			'post_content' => '', 'post_status' => 'inherit',
		), $dest_path );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			@unlink( $dest_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			throw new RuntimeException( 'upload_unavailable: Failed to register editorial media ' . $key );
		}
		$attachment_id = absint( $attachment_id );
		$metadata = wp_generate_attachment_metadata( $attachment_id, $dest_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		update_post_meta( $attachment_id, self::KEY_META, $key );
		update_post_meta( $attachment_id, self::SHA_META, strtolower( (string) $item['sha256'] ) );
		update_post_meta( $attachment_id, self::SOURCE_META, esc_url_raw( (string) ( $item['source_asset_url'] ?? '' ) ) );
		update_post_meta( $attachment_id, self::SOURCE_PAGE_META, esc_url_raw( (string) ( $item['source_page'] ?? '' ) ) );
		update_post_meta( $attachment_id, self::REVISION_META, self::REVISION );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', ! empty( $item['decorative'] ) ? '' : sanitize_text_field( (string) ( $item['alt'] ?? '' ) ) );
		return $attachment_id;
	}
}
