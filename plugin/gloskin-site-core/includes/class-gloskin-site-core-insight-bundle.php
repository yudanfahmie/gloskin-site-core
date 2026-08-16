<?php
/**
 * Fixed-path validator and cleanup helper for the Gloskin Insights v1 seed.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Gloskin_Site_Core_Insight_Bundle {
	const RUNTIME_RELATIVE_PATH = 'migration-runtime/gloskin-insights-v1';
	const MANIFEST_FILE = 'manifest.json';
	const MAX_MANIFEST_BYTES = 65536;
	const MAX_POSTS_BYTES = 262144;
	const MAX_MEDIA_BYTES = 131072;
	const NOTICE = 'Initial Gloskin editorial seed; retain all media licensing and provenance metadata.';

	const CATEGORY_SLUGS = array( 'perawatan', 'skincare', 'kesehatan-kulit', 'anti-aging', 'rambut' );

	/** @var string */
	private $runtime_dir;

	public function __construct( $plugin_file ) {
		$this->runtime_dir = trailingslashit( plugin_dir_path( $plugin_file ) ) . self::RUNTIME_RELATIVE_PATH;
	}

	public function runtime_dir() { return $this->runtime_dir; }

	/** Cheap menu/status read; no payload hashing. */
	public function read_header() {
		$path = $this->runtime_dir . '/' . self::MANIFEST_FILE;
		if ( ! is_file( $path ) ) { return array(); }
		$size = filesize( $path );
		if ( false === $size || $size < 2 || $size > self::MAX_MANIFEST_BYTES ) {
			return new WP_Error( 'gloskin_insight_manifest_size', 'Manifest Insight melebihi batas aman.' );
		}
		$raw = file_get_contents( $path );
		if ( false === $raw ) { return new WP_Error( 'gloskin_insight_manifest_read', 'Manifest Insight tidak dapat dibaca.' ); }
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) { return new WP_Error( 'gloskin_insight_manifest_json', 'Manifest Insight bukan JSON yang valid.' ); }
		return $this->validate_manifest_structure( $data );
	}

	/**
	 * Validate the entire bundle before any WordPress mutation.
	 *
	 * @return array{manifest:array<string,mixed>,categories:array<int,array<string,string>>,posts:array<int,array<string,string>>,media:array<int,array<string,string>>,media_by_source:array<string,array<string,string>>}
	 */
	public function validate() {
		$manifest = $this->decode_json_file( $this->runtime_dir . '/' . self::MANIFEST_FILE, self::MAX_MANIFEST_BYTES, 'manifest' );
		$checked = $this->validate_manifest_structure( $manifest );
		if ( is_wp_error( $checked ) ) { throw new RuntimeException( $checked->get_error_message() ); }
		$manifest = $checked;
		$this->reject_unexpected_runtime_files( $manifest );

		foreach ( $manifest['files'] as $relative ) {
			$path = $this->confined_file( $relative );
			$actual = hash_file( 'sha256', $path );
			$expected = strtolower( trim( (string) $manifest['checksums'][ $relative ] ) );
			if ( ! is_string( $actual ) || ! hash_equals( $expected, strtolower( $actual ) ) ) {
				throw new RuntimeException( 'Checksum ' . $relative . ' tidak cocok.' );
			}
		}

		$post_data = $this->decode_json_file( $this->confined_file( 'posts.json' ), self::MAX_POSTS_BYTES, 'posts' );
		$media_data = $this->decode_json_file( $this->confined_file( 'media.json' ), self::MAX_MEDIA_BYTES, 'media' );
		if ( ! isset( $post_data['categories'], $post_data['posts'] ) || ! is_array( $post_data['categories'] ) || ! is_array( $post_data['posts'] ) ) {
			throw new RuntimeException( 'posts.json belum memiliki categories/posts yang valid.' );
		}
		if ( ! isset( $media_data['media'] ) || ! is_array( $media_data['media'] ) ) {
			throw new RuntimeException( 'media.json belum memiliki array media.' );
		}

		$categories = array();
		$category_slugs = array();
		foreach ( $post_data['categories'] as $record ) {
			if ( ! is_array( $record ) ) { throw new RuntimeException( 'Record kategori Insight tidak valid.' ); }
			$name = isset( $record['name'] ) ? trim( wp_strip_all_tags( (string) $record['name'] ) ) : '';
			$slug = isset( $record['slug'] ) ? sanitize_title( (string) $record['slug'] ) : '';
			if ( '' === $name || ! in_array( $slug, self::CATEGORY_SLUGS, true ) || isset( $category_slugs[ $slug ] ) ) {
				throw new RuntimeException( 'Kategori Insight tidak memenuhi kontrak: ' . $slug );
			}
			$category_slugs[ $slug ] = true;
			$categories[] = array( 'name' => $name, 'slug' => $slug );
		}
		$actual_category_slugs = array_keys( $category_slugs );
		sort( $actual_category_slugs );
		$expected_category_slugs = self::CATEGORY_SLUGS;
		sort( $expected_category_slugs );
		if ( (int) $manifest['expected_categories'] !== count( $categories ) || $expected_category_slugs !== $actual_category_slugs ) {
			throw new RuntimeException( 'Lima kategori native Insight tidak cocok dengan manifest.' );
		}

		$posts = array();
		$post_sources = array();
		$post_slugs = array();
		foreach ( $post_data['posts'] as $record ) {
			$post = $this->normalize_post( is_array( $record ) ? $record : array() );
			if ( isset( $post_sources[ $post['source_id'] ] ) ) {
				throw new RuntimeException( 'Source identity post duplikat: ' . $post['source_id'] );
			}
			if ( isset( $post_slugs[ $post['slug'] ] ) ) {
				throw new RuntimeException( 'Slug post duplikat: ' . $post['slug'] );
			}
			if ( ! isset( $category_slugs[ $post['category_slug'] ] ) ) {
				throw new RuntimeException( 'Post mengacu kategori yang tidak dikenal: ' . $post['source_id'] );
			}
			$post_sources[ $post['source_id'] ] = true;
			$post_slugs[ $post['slug'] ] = true;
			$posts[] = $post;
		}
		if ( (int) $manifest['expected_posts'] !== count( $posts ) || 13 !== count( $posts ) ) {
			throw new RuntimeException( 'Jumlah post Insight tidak cocok dengan manifest.' );
		}

		$media = array();
		$media_sources = array();
		$media_by_source = array();
		$media_by_post = array();
		foreach ( $media_data['media'] as $record ) {
			$item = $this->normalize_media( is_array( $record ) ? $record : array() );
			if ( isset( $media_sources[ $item['source_id'] ] ) ) {
				throw new RuntimeException( 'Source identity media duplikat: ' . $item['source_id'] );
			}
			if ( ! isset( $post_sources[ $item['post_source_id'] ] ) ) {
				throw new RuntimeException( 'Media mengacu post_source_id yang tidak dikenal: ' . $item['source_id'] );
			}
			if ( isset( $media_by_post[ $item['post_source_id'] ] ) ) {
				throw new RuntimeException( 'Setiap post hanya boleh memiliki satu featured media: ' . $item['post_source_id'] );
			}
			$media_sources[ $item['source_id'] ] = true;
			$media_by_post[ $item['post_source_id'] ] = $item['source_id'];
			$media_by_source[ $item['source_id'] ] = $item;
			$media[] = $item;
		}
		if ( (int) $manifest['expected_media'] !== count( $media ) || 13 !== count( $media ) ) {
			throw new RuntimeException( 'Jumlah featured media Insight tidak cocok dengan manifest.' );
		}
		foreach ( $posts as $post ) {
			if ( ! isset( $media_by_source[ $post['media_source_id'] ] )
				|| $media_by_source[ $post['media_source_id'] ]['post_source_id'] !== $post['source_id'] ) {
				throw new RuntimeException( 'Relasi post/media Insight tidak valid: ' . $post['source_id'] );
			}
		}

		return array(
			'manifest' => $manifest,
			'categories' => $categories,
			'posts' => $posts,
			'media' => $media,
			'media_by_source' => $media_by_source,
		);
	}

	/** Remove only files declared by a previously validated manifest. */
	public function cleanup( array $manifest ) {
		$errors = array();
		foreach ( (array) $manifest['files'] as $relative ) {
			try {
				$path = $this->confined_file( (string) $relative );
				if ( is_file( $path ) ) {
					wp_delete_file( $path );
					if ( is_file( $path ) ) { $errors[] = 'Gagal menghapus ' . basename( $path ) . '.'; }
				}
			} catch ( Throwable $error ) {
				$errors[] = $error->getMessage();
			}
		}
		$manifest_path = $this->runtime_dir . '/' . self::MANIFEST_FILE;
		if ( is_file( $manifest_path ) ) {
			wp_delete_file( $manifest_path );
			if ( is_file( $manifest_path ) ) { $errors[] = 'Gagal menghapus manifest runtime Insight.'; }
		}
		if ( is_dir( $this->runtime_dir ) && ! @rmdir( $this->runtime_dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- fixed confined runtime path after manifest-declared files are removed.
			$errors[] = 'Folder runtime Insight tidak dapat dihapus; mungkin ada file yang tidak dideklarasikan.';
		}
		return array( 'ok' => empty( $errors ), 'message' => implode( ' ', $errors ) );
	}

	private function validate_manifest_structure( array $data ) {
		$required = array( 'bundle_id','schema_version','source_version','migration_type','notice','expected_posts','expected_media','expected_categories','files','checksums' );
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				return new WP_Error( 'gloskin_insight_manifest_field', 'Manifest Insight belum lengkap.' );
			}
		}
		if ( 'gloskin-insights-v1' !== (string) $data['bundle_id']
			|| '1' !== (string) $data['schema_version']
			|| 'v1' !== (string) $data['source_version']
			|| 'gloskin-native-insight-posts' !== (string) $data['migration_type']
			|| self::NOTICE !== (string) $data['notice'] ) {
			return new WP_Error( 'gloskin_insight_manifest_contract', 'Identitas/schema bundle Insight tidak didukung.' );
		}
		if ( 13 !== (int) $data['expected_posts'] || 13 !== (int) $data['expected_media'] || 5 !== (int) $data['expected_categories'] ) {
			return new WP_Error( 'gloskin_insight_manifest_count', 'Counter manifest Insight tidak sesuai kontrak.' );
		}
		if ( ! is_array( $data['files'] ) || array( 'posts.json', 'media.json' ) !== array_values( $data['files'] ) || ! is_array( $data['checksums'] ) ) {
			return new WP_Error( 'gloskin_insight_manifest_files', 'Daftar file/checksum bundle Insight tidak valid.' );
		}
		foreach ( $data['files'] as $relative ) {
			$checksum = isset( $data['checksums'][ $relative ] ) ? strtolower( trim( (string) $data['checksums'][ $relative ] ) ) : '';
			if ( ! preg_match( '/^[a-f0-9]{64}$/', $checksum ) ) {
				return new WP_Error( 'gloskin_insight_manifest_checksum', 'Checksum manifest tidak valid untuk ' . $relative . '.' );
			}
		}
		return $data;
	}

	private function normalize_post( array $record ) {
		$post = array(
			'source_id' => isset( $record['source_id'] ) ? trim( (string) $record['source_id'] ) : '',
			'slug' => isset( $record['slug'] ) ? sanitize_title( (string) $record['slug'] ) : '',
			'title' => isset( $record['title'] ) ? trim( wp_strip_all_tags( (string) $record['title'] ) ) : '',
			'excerpt' => isset( $record['excerpt'] ) ? trim( wp_strip_all_tags( (string) $record['excerpt'] ) ) : '',
			'content_html' => isset( $record['content_html'] ) ? trim( (string) $record['content_html'] ) : '',
			'category_slug' => isset( $record['category_slug'] ) ? sanitize_title( (string) $record['category_slug'] ) : '',
			'post_date' => isset( $record['post_date'] ) ? trim( (string) $record['post_date'] ) : '',
			'media_source_id' => isset( $record['media_source_id'] ) ? trim( (string) $record['media_source_id'] ) : '',
			'status' => isset( $record['status'] ) ? sanitize_key( (string) $record['status'] ) : '',
		);
		if ( ! preg_match( '/^gloskin-insight:v1:[a-z0-9-]+$/', $post['source_id'] )
			|| '' === $post['slug']
			|| 'gloskin-insight:v1:' . $post['slug'] !== $post['source_id']
			|| '' === $post['title'] || '' === $post['excerpt'] || '' === $post['content_html']
			|| ! in_array( $post['category_slug'], self::CATEGORY_SLUGS, true )
			|| ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $post['post_date'] )
			|| 'gloskin-insight-media:v1:' . $post['slug'] . ':featured' !== $post['media_source_id']
			|| 'publish' !== $post['status'] ) {
			throw new RuntimeException( 'Record post Insight tidak memenuhi kontrak: ' . $post['source_id'] );
		}
		$dangerous = '/<(?:script|iframe|object|embed|form|input|button|style)\b|on[a-z]+\s*=|javascript\s*:/i';
		if ( preg_match( $dangerous, $post['content_html'] ) || wp_kses_post( $post['content_html'] ) !== $post['content_html'] ) {
			throw new RuntimeException( 'HTML post Insight mengandung markup yang tidak diizinkan: ' . $post['source_id'] );
		}
		if ( strlen( wp_strip_all_tags( $post['content_html'] ) ) < 1800 || substr_count( $post['content_html'], '<h2>' ) < 4 ) {
			throw new RuntimeException( 'Konten editorial Insight terlalu pendek/tidak lengkap: ' . $post['source_id'] );
		}
		return $post;
	}

	private function normalize_media( array $record ) {
		$item = array(
			'source_id' => isset( $record['source_id'] ) ? trim( (string) $record['source_id'] ) : '',
			'post_source_id' => isset( $record['post_source_id'] ) ? trim( (string) $record['post_source_id'] ) : '',
			'source_url' => isset( $record['source_url'] ) ? trim( (string) $record['source_url'] ) : '',
			'source_page_url' => isset( $record['source_page_url'] ) ? trim( (string) $record['source_page_url'] ) : '',
			'author' => isset( $record['author'] ) ? trim( wp_strip_all_tags( (string) $record['author'] ) ) : '',
			'license_note' => isset( $record['license_note'] ) ? trim( wp_strip_all_tags( (string) $record['license_note'] ) ) : '',
			'filename' => isset( $record['filename'] ) ? trim( (string) $record['filename'] ) : '',
			'alt' => isset( $record['alt'] ) ? trim( wp_strip_all_tags( (string) $record['alt'] ) ) : '',
			'role' => isset( $record['role'] ) ? sanitize_key( (string) $record['role'] ) : '',
		);
		if ( ! preg_match( '/^gloskin-insight-media:v1:[a-z0-9-]+:featured$/', $item['source_id'] )
			|| ! preg_match( '/^gloskin-insight:v1:[a-z0-9-]+$/', $item['post_source_id'] )
			|| 'featured' !== $item['role'] || '' === $item['author'] || '' === $item['license_note'] || '' === $item['alt']
			|| basename( $item['filename'] ) !== $item['filename'] || ! preg_match( '/\.jpe?g$/i', $item['filename'] )
			|| 'https' !== strtolower( (string) wp_parse_url( $item['source_url'], PHP_URL_SCHEME ) )
			|| 'images.pexels.com' !== strtolower( (string) wp_parse_url( $item['source_url'], PHP_URL_HOST ) )
			|| 'https' !== strtolower( (string) wp_parse_url( $item['source_page_url'], PHP_URL_SCHEME ) )
			|| 'www.pexels.com' !== strtolower( (string) wp_parse_url( $item['source_page_url'], PHP_URL_HOST ) ) ) {
			throw new RuntimeException( 'Record media Insight tidak memenuhi kontrak: ' . $item['source_id'] );
		}
		$post_slug = substr( $item['post_source_id'], strlen( 'gloskin-insight:v1:' ) );
		if ( 'gloskin-insight-media:v1:' . $post_slug . ':featured' !== $item['source_id'] ) {
			throw new RuntimeException( 'Source identity media tidak cocok dengan post identity: ' . $item['source_id'] );
		}
		return $item;
	}

	private function reject_unexpected_runtime_files( array $manifest ) {
		if ( ! is_dir( $this->runtime_dir ) ) { throw new RuntimeException( 'Runtime bundle Insight tidak ditemukan.' ); }
		$entries = scandir( $this->runtime_dir );
		if ( false === $entries ) { throw new RuntimeException( 'Runtime bundle Insight tidak dapat dibaca.' ); }
		$allowed = array_merge( array( self::MANIFEST_FILE ), array_values( $manifest['files'] ) );
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) { continue; }
			if ( ! in_array( $entry, $allowed, true ) || ! is_file( $this->runtime_dir . '/' . $entry ) ) {
				throw new RuntimeException( 'File runtime Insight tidak dideklarasikan manifest: ' . $entry );
			}
		}
	}

	private function decode_json_file( $path, $max_bytes, $label ) {
		if ( ! is_file( $path ) ) { throw new RuntimeException( 'File ' . $label . ' tidak ditemukan.' ); }
		$size = filesize( $path );
		if ( false === $size || $size < 2 || $size > $max_bytes ) { throw new RuntimeException( 'File ' . $label . ' melebihi batas aman.' ); }
		$raw = file_get_contents( $path );
		if ( false === $raw ) { throw new RuntimeException( 'File ' . $label . ' tidak dapat dibaca.' ); }
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) { throw new RuntimeException( 'File ' . $label . ' bukan JSON yang valid.' ); }
		return $data;
	}

	private function confined_file( $relative ) {
		$relative = (string) $relative;
		if ( basename( $relative ) !== $relative || ! in_array( $relative, array( 'posts.json', 'media.json' ), true ) ) {
			throw new RuntimeException( 'Path runtime Insight di luar allowlist.' );
		}
		$path = $this->runtime_dir . '/' . $relative;
		if ( ! is_file( $path ) ) { throw new RuntimeException( 'File runtime Insight tidak ditemukan: ' . $relative ); }
		return $path;
	}
}
// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
