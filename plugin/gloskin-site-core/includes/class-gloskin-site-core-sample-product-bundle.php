<?php
/**
 * Fixed-path validator and cleanup helper for the temporary sample catalog.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Sample_Product_Bundle {
	const RUNTIME_RELATIVE_PATH = 'migration-runtime/gloskin-sample-products-v1';
	const MANIFEST_FILE         = 'manifest.json';
	const MAX_MANIFEST_BYTES    = 65536;
	const MAX_PRODUCTS_BYTES    = 524288;
	const MAX_MEDIA_BYTES       = 1048576;
	const NOTICE                = 'Synthetic staging/demo catalog — not verified commercial product truth.';

	const CATEGORY_SLUGS = array(
		'facial-wash',
		'day-cream-sunscreen',
		'toner',
		'serum',
		'acne-care',
		'anti-aging',
		'brightening-pigmentation-care',
	);

	/** @var string */
	private $runtime_dir;

	/**
	 * @param string $plugin_file Main plugin file.
	 */
	public function __construct( $plugin_file ) {
		$this->runtime_dir = trailingslashit( plugin_dir_path( $plugin_file ) ) . self::RUNTIME_RELATIVE_PATH;
	}

	/** @return string */
	public function runtime_dir() {
		return $this->runtime_dir;
	}

	/**
	 * Cheap status-time manifest read. No payload hashing or decoding happens here.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function read_header() {
		$path = $this->runtime_dir . '/' . self::MANIFEST_FILE;
		if ( ! is_file( $path ) ) {
			return array();
		}
		$size = filesize( $path );
		if ( false === $size || $size < 2 || $size > self::MAX_MANIFEST_BYTES ) {
			return new WP_Error( 'gloskin_sample_manifest_size', 'Manifest sample product melebihi batas aman.' );
		}
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			return new WP_Error( 'gloskin_sample_manifest_read', 'Manifest sample product tidak dapat dibaca.' );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'gloskin_sample_manifest_json', 'Manifest sample product bukan JSON yang valid.' );
		}
		return $this->validate_manifest_structure( $data );
	}

	/**
	 * Full authorized-action validation.
	 *
	 * @return array<string,mixed>
	 * @throws RuntimeException Invalid bundle.
	 */
	public function validate() {
		$manifest = $this->decode_json_file(
			$this->runtime_dir . '/' . self::MANIFEST_FILE,
			self::MAX_MANIFEST_BYTES,
			'manifest'
		);
		$manifest_check = $this->validate_manifest_structure( $manifest );
		if ( is_wp_error( $manifest_check ) ) {
			throw new RuntimeException( $manifest_check->get_error_message() );
		}

		$this->reject_unexpected_runtime_files( $manifest );

		foreach ( $manifest['files'] as $relative ) {
			$path     = $this->confined_file( $relative );
			$actual   = hash_file( 'sha256', $path );
			$expected = strtolower( trim( (string) $manifest['checksums'][ $relative ] ) );
			if ( ! is_string( $actual ) || ! hash_equals( $expected, strtolower( $actual ) ) ) {
				throw new RuntimeException( 'Checksum ' . $relative . ' tidak cocok.' );
			}
		}

		$product_data = $this->decode_json_file(
			$this->confined_file( 'products.json' ),
			self::MAX_PRODUCTS_BYTES,
			'products'
		);
		$media_data   = $this->decode_json_file(
			$this->confined_file( 'media.json' ),
			self::MAX_MEDIA_BYTES,
			'media'
		);

		if ( self::NOTICE !== ( isset( $product_data['notice'] ) ? (string) $product_data['notice'] : '' )
			|| self::NOTICE !== ( isset( $media_data['notice'] ) ? (string) $media_data['notice'] : '' ) ) {
			throw new RuntimeException( 'Penanda synthetic staging/demo wajib ada pada seluruh payload bundle.' );
		}
		if ( ! isset( $product_data['products'] ) || ! is_array( $product_data['products'] ) ) {
			throw new RuntimeException( 'products.json tidak memiliki array products.' );
		}
		if ( ! isset( $media_data['media'] ) || ! is_array( $media_data['media'] ) ) {
			throw new RuntimeException( 'media.json tidak memiliki array media.' );
		}

		$products = array();
		$product_ids = array();
		$skus = array();
		$simple = 0;
		$variable = 0;
		$variation_count = 0;

		foreach ( $product_data['products'] as $record ) {
			if ( ! is_array( $record ) ) {
				throw new RuntimeException( 'Record produk bundle tidak valid.' );
			}
			$product = $this->normalize_product( $record );
			if ( isset( $product_ids[ $product['source_id'] ] ) ) {
				throw new RuntimeException( 'Source identity produk duplikat: ' . $product['source_id'] );
			}
			$product_ids[ $product['source_id'] ] = true;
			$this->assert_unique_sku( $product['sku'], $skus );

			if ( 'simple' === $product['type'] ) {
				$simple++;
			} else {
				$variable++;
				foreach ( $product['variations'] as $variation ) {
					if ( isset( $product_ids[ $variation['source_id'] ] ) ) {
						throw new RuntimeException( 'Source identity variasi duplikat: ' . $variation['source_id'] );
					}
					$product_ids[ $variation['source_id'] ] = true;
					$this->assert_unique_sku( $variation['sku'], $skus );
					$variation_count++;
				}
			}
			$products[] = $product;
		}

		if ( (int) $manifest['expected_products'] !== count( $products )
			|| (int) $manifest['expected_simple'] !== $simple
			|| (int) $manifest['expected_variable'] !== $variable
			|| (int) $manifest['expected_variations'] !== $variation_count ) {
			throw new RuntimeException( 'Jumlah produk/tipe/variasi tidak cocok dengan manifest.' );
		}

		$media = array();
		$media_ids = array();
		$media_by_product = array();
		foreach ( $media_data['media'] as $record ) {
			if ( ! is_array( $record ) ) {
				throw new RuntimeException( 'Record media bundle tidak valid.' );
			}
			$item = $this->normalize_media( $record );
			if ( isset( $media_ids[ $item['source_id'] ] ) ) {
				throw new RuntimeException( 'Source identity media duplikat: ' . $item['source_id'] );
			}
			if ( ! isset( $product_ids[ $item['product_source_id'] ] ) ) {
				throw new RuntimeException( 'Media mengacu pada product_source_id yang tidak dikenal: ' . $item['source_id'] );
			}
			$media_ids[ $item['source_id'] ] = true;
			$media[] = $item;
			if ( ! isset( $media_by_product[ $item['product_source_id'] ] ) ) {
				$media_by_product[ $item['product_source_id'] ] = array();
			}
			$media_by_product[ $item['product_source_id'] ][] = $item;
		}

		if ( (int) $manifest['expected_media'] !== count( $media ) ) {
			throw new RuntimeException( 'Jumlah media tidak cocok dengan expected_media pada manifest.' );
		}

		foreach ( $products as $product ) {
			$items = isset( $media_by_product[ $product['source_id'] ] ) ? $media_by_product[ $product['source_id'] ] : array();
			if ( count( $items ) !== (int) $product['media_count'] || count( $items ) < 3 || count( $items ) > 6 ) {
				throw new RuntimeException( 'Jumlah media produk tidak sesuai deklarasi: ' . $product['source_id'] );
			}
			usort(
				$items,
				static function ( $a, $b ) {
					return (int) $a['sort_order'] <=> (int) $b['sort_order'];
				}
			);
			$featured = 0;
			$orders   = array();
			foreach ( $items as $item ) {
				if ( isset( $orders[ $item['sort_order'] ] ) ) {
					throw new RuntimeException( 'sort_order media duplikat pada ' . $product['source_id'] );
				}
				$orders[ $item['sort_order'] ] = true;
				if ( 'featured' === $item['role'] ) {
					$featured++;
				}
			}
			if ( 1 !== $featured || 'featured' !== $items[0]['role'] ) {
				throw new RuntimeException( 'Setiap produk wajib memiliki tepat satu featured media pada urutan pertama.' );
			}
			$media_by_product[ $product['source_id'] ] = $items;
		}

		return array(
			'manifest'         => $manifest,
			'products'         => $products,
			'media'            => $media,
			'media_by_product' => $media_by_product,
		);
	}

	/**
	 * Remove only manifest-declared files, then the fixed runtime directory.
	 *
	 * @param array<string,mixed> $manifest Valid manifest.
	 * @return array{ok:bool,message:string}
	 */
	public function cleanup( array $manifest ) {
		$errors = array();
		foreach ( (array) $manifest['files'] as $relative ) {
			try {
				$path = $this->confined_file( (string) $relative );
				if ( is_file( $path ) && ! @unlink( $path ) ) {
					$errors[] = 'Gagal menghapus ' . basename( $path ) . '.';
				}
			} catch ( Throwable $error ) {
				$errors[] = $error->getMessage();
			}
		}
		$manifest_path = $this->runtime_dir . '/' . self::MANIFEST_FILE;
		if ( is_file( $manifest_path ) && ! @unlink( $manifest_path ) ) {
			$errors[] = 'Gagal menghapus manifest runtime.';
		}
		if ( is_dir( $this->runtime_dir ) && ! @rmdir( $this->runtime_dir ) ) {
			$errors[] = 'Folder runtime tidak dapat dihapus; mungkin ada file yang tidak dideklarasikan.';
		}
		return array(
			'ok'      => empty( $errors ),
			'message' => implode( ' ', $errors ),
		);
	}

	/**
	 * @param array<string,mixed> $data Manifest.
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_manifest_structure( array $data ) {
		$required = array(
			'bundle_id',
			'schema_version',
			'source_version',
			'migration_type',
			'notice',
			'expected_products',
			'expected_simple',
			'expected_variable',
			'expected_variations',
			'expected_media',
			'files',
			'checksums',
		);
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				return new WP_Error( 'gloskin_sample_manifest_field', 'Manifest sample product belum lengkap.' );
			}
		}
		if ( 'gloskin-sample-products-v1' !== (string) $data['bundle_id']
			|| '1' !== (string) $data['schema_version']
			|| 'v1' !== (string) $data['source_version']
			|| 'gloskin-sample-woo-products' !== (string) $data['migration_type']
			|| self::NOTICE !== (string) $data['notice'] ) {
			return new WP_Error( 'gloskin_sample_manifest_contract', 'Tipe/schema/identitas bundle sample product tidak didukung.' );
		}
		$expected = array(
			'expected_products'   => 13,
			'expected_simple'     => 8,
			'expected_variable'   => 5,
			'expected_variations' => 10,
			'expected_media'      => 58,
		);
		foreach ( $expected as $field => $value ) {
			if ( $value !== (int) $data[ $field ] ) {
				return new WP_Error( 'gloskin_sample_manifest_count', 'Counter manifest sample product tidak sesuai kontrak.' );
			}
		}
		if ( ! is_array( $data['files'] ) || array( 'products.json', 'media.json' ) !== array_values( $data['files'] ) ) {
			return new WP_Error( 'gloskin_sample_manifest_files', 'Daftar file bundle sample product tidak valid.' );
		}
		if ( ! is_array( $data['checksums'] ) ) {
			return new WP_Error( 'gloskin_sample_manifest_checksums', 'Checksum bundle sample product tidak tersedia.' );
		}
		foreach ( $data['files'] as $relative ) {
			$checksum = isset( $data['checksums'][ $relative ] ) ? strtolower( trim( (string) $data['checksums'][ $relative ] ) ) : '';
			if ( ! preg_match( '/^[a-f0-9]{64}$/', $checksum ) ) {
				return new WP_Error( 'gloskin_sample_manifest_checksum', 'Checksum manifest tidak valid untuk ' . $relative . '.' );
			}
		}
		return $data;
	}

	/**
	 * @param array<string,mixed> $record Raw record.
	 * @return array<string,mixed>
	 */
	private function normalize_product( array $record ) {
		$product = array(
			'source_id'         => isset( $record['source_id'] ) ? trim( (string) $record['source_id'] ) : '',
			'name'              => isset( $record['name'] ) ? trim( wp_strip_all_tags( (string) $record['name'] ) ) : '',
			'slug'              => isset( $record['slug'] ) ? sanitize_title( (string) $record['slug'] ) : '',
			'category_slug'     => isset( $record['category_slug'] ) ? sanitize_title( (string) $record['category_slug'] ) : '',
			'type'              => isset( $record['type'] ) ? sanitize_key( (string) $record['type'] ) : '',
			'sku'               => isset( $record['sku'] ) ? trim( (string) $record['sku'] ) : '',
			'short_description' => isset( $record['short_description'] ) ? (string) $record['short_description'] : '',
			'description'       => isset( $record['description'] ) ? (string) $record['description'] : '',
			'usage'             => isset( $record['usage'] ) ? trim( (string) $record['usage'] ) : '',
			'bpom'              => isset( $record['bpom'] ) ? trim( (string) $record['bpom'] ) : '',
			'composition'       => isset( $record['composition'] ) ? trim( (string) $record['composition'] ) : '',
			'stock_status'      => isset( $record['stock_status'] ) ? sanitize_key( (string) $record['stock_status'] ) : '',
			'status'            => isset( $record['status'] ) ? sanitize_key( (string) $record['status'] ) : '',
			'media_count'       => isset( $record['media_count'] ) ? (int) $record['media_count'] : 0,
			'variations'        => array(),
		);
		if ( ! preg_match( '/^gloskin-sample:v1:[a-z0-9-]+$/', $product['source_id'] )
			|| '' === $product['name']
			|| '' === $product['slug']
			|| ! in_array( $product['category_slug'], self::CATEGORY_SLUGS, true )
			|| ! in_array( $product['type'], array( 'simple', 'variable' ), true )
			|| ! preg_match( '/^GLS-SMP-\d{3}$/', $product['sku'] )
			|| 'draft' !== $product['status']
			|| 'instock' !== $product['stock_status']
			|| $product['media_count'] < 3
			|| $product['media_count'] > 6
			|| '' !== $product['bpom']
			|| '' !== $product['composition']
			|| '' === $product['short_description']
			|| '' === $product['description']
			|| '' === $product['usage'] ) {
			throw new RuntimeException( 'Record produk bundle tidak memenuhi kontrak: ' . $product['source_id'] );
		}
		if ( substr( $product['source_id'], strlen( 'gloskin-sample:v1:' ) ) !== $product['slug'] ) {
			throw new RuntimeException( 'Source identity produk tidak cocok dengan slug: ' . $product['source_id'] );
		}

		if ( 'simple' === $product['type'] ) {
			$product['size']          = isset( $record['size'] ) ? trim( (string) $record['size'] ) : '';
			$product['regular_price'] = isset( $record['regular_price'] ) ? trim( (string) $record['regular_price'] ) : '';
			if ( '' === $product['size'] || ! preg_match( '/^\d+$/', $product['regular_price'] ) || ! empty( $record['variations'] ) ) {
				throw new RuntimeException( 'Produk simple tidak memiliki size/price yang valid: ' . $product['source_id'] );
			}
		} else {
			if ( 'Ukuran' !== ( isset( $record['attribute_name'] ) ? (string) $record['attribute_name'] : '' )
				|| ! isset( $record['variations'] )
				|| ! is_array( $record['variations'] )
				|| 2 !== count( $record['variations'] ) ) {
				throw new RuntimeException( 'Produk variable wajib memiliki atribut Ukuran dan dua variasi: ' . $product['source_id'] );
			}
			foreach ( $record['variations'] as $variation ) {
				if ( ! is_array( $variation ) ) {
					throw new RuntimeException( 'Variasi produk tidak valid: ' . $product['source_id'] );
				}
				$normalized = array(
					'source_id'    => isset( $variation['source_id'] ) ? trim( (string) $variation['source_id'] ) : '',
					'size'         => isset( $variation['size'] ) ? trim( (string) $variation['size'] ) : '',
					'sku'          => isset( $variation['sku'] ) ? trim( (string) $variation['sku'] ) : '',
					'regular_price'=> isset( $variation['regular_price'] ) ? trim( (string) $variation['regular_price'] ) : '',
					'status'       => isset( $variation['status'] ) ? sanitize_key( (string) $variation['status'] ) : '',
					'stock_status' => isset( $variation['stock_status'] ) ? sanitize_key( (string) $variation['stock_status'] ) : '',
				);
				$expected_token = preg_replace( '/[^a-z0-9]+/', '', strtolower( $normalized['size'] ) );
				if ( '' === $normalized['size']
					|| ! preg_match( '/^GLS-SMP-\d{3}-\d{3}$/', $normalized['sku'] )
					|| ! preg_match( '/^\d+$/', $normalized['regular_price'] )
					|| 'draft' !== $normalized['status']
					|| 'instock' !== $normalized['stock_status']
					|| $product['source_id'] . ':' . $expected_token !== $normalized['source_id'] ) {
					throw new RuntimeException( 'Variasi produk tidak memenuhi kontrak: ' . $normalized['source_id'] );
				}
				$product['variations'][] = $normalized;
			}
		}
		return $product;
	}

	/**
	 * @param array<string,mixed> $record Raw media record.
	 * @return array<string,mixed>
	 */
	private function normalize_media( array $record ) {
		$item = array(
			'source_id'         => isset( $record['source_id'] ) ? trim( (string) $record['source_id'] ) : '',
			'product_source_id' => isset( $record['product_source_id'] ) ? trim( (string) $record['product_source_id'] ) : '',
			'source_url'        => isset( $record['source_url'] ) ? trim( (string) $record['source_url'] ) : '',
			'source_page_url'   => isset( $record['source_page_url'] ) ? trim( (string) $record['source_page_url'] ) : '',
			'author'            => isset( $record['author'] ) ? trim( (string) $record['author'] ) : '',
			'license_note'      => isset( $record['license_note'] ) ? trim( (string) $record['license_note'] ) : '',
			'filename'          => isset( $record['filename'] ) ? trim( (string) $record['filename'] ) : '',
			'alt'               => isset( $record['alt'] ) ? trim( (string) $record['alt'] ) : '',
			'role'              => isset( $record['role'] ) ? sanitize_key( (string) $record['role'] ) : '',
			'sort_order'        => isset( $record['sort_order'] ) ? (int) $record['sort_order'] : 0,
		);
		if ( ! preg_match( '/^gloskin-sample-media:v1:[a-z0-9-]+:\d{2}$/', $item['source_id'] )
			|| ! preg_match( '/^gloskin-sample:v1:[a-z0-9-]+$/', $item['product_source_id'] )
			|| ! in_array( $item['role'], array( 'featured', 'gallery' ), true )
			|| $item['sort_order'] < 1
			|| '' === $item['alt']
			|| '' === $item['license_note']
			|| basename( $item['filename'] ) !== $item['filename']
			|| ! preg_match( '/\.(?:jpe?g|png|webp)$/i', $item['filename'] )
			|| 'https' !== strtolower( (string) parse_url( $item['source_url'], PHP_URL_SCHEME ) )
			|| ( '' !== $item['source_page_url'] && 'https' !== strtolower( (string) parse_url( $item['source_page_url'], PHP_URL_SCHEME ) ) ) ) {
			throw new RuntimeException( 'Record media bundle tidak memenuhi kontrak: ' . $item['source_id'] );
		}
		$product_slug = substr( $item['product_source_id'], strlen( 'gloskin-sample:v1:' ) );
		if ( 0 !== strpos( $item['source_id'], 'gloskin-sample-media:v1:' . $product_slug . ':' ) ) {
			throw new RuntimeException( 'Source identity media tidak cocok dengan product source identity: ' . $item['source_id'] );
		}
		return $item;
	}

	/**
	 * @param string              $sku SKU.
	 * @param array<string,bool>  $seen Seen map.
	 * @return void
	 */
	private function assert_unique_sku( $sku, &$seen ) {
		if ( isset( $seen[ $sku ] ) ) {
			throw new RuntimeException( 'SKU duplikat di dalam bundle: ' . $sku );
		}
		$seen[ $sku ] = true;
	}

	/**
	 * @param array<string,mixed> $manifest Manifest.
	 * @return void
	 */
	private function reject_unexpected_runtime_files( array $manifest ) {
		if ( ! is_dir( $this->runtime_dir ) ) {
			throw new RuntimeException( 'Runtime bundle sample product tidak ditemukan.' );
		}
		$entries = scandir( $this->runtime_dir );
		if ( false === $entries ) {
			throw new RuntimeException( 'Runtime bundle sample product tidak dapat dibaca.' );
		}
		$allowed = array_merge( array( self::MANIFEST_FILE ), array_values( $manifest['files'] ) );
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			if ( ! in_array( $entry, $allowed, true ) || ! is_file( $this->runtime_dir . '/' . $entry ) ) {
				throw new RuntimeException( 'Runtime bundle memiliki file/entry yang tidak dideklarasikan: ' . $entry );
			}
		}
	}

	/**
	 * @param string $path Path.
	 * @param int    $max_bytes Max bytes.
	 * @param string $label Label.
	 * @return array<string,mixed>
	 */
	private function decode_json_file( $path, $max_bytes, $label ) {
		if ( ! is_file( $path ) ) {
			throw new RuntimeException( 'File ' . $label . ' tidak ditemukan.' );
		}
		$size = filesize( $path );
		if ( false === $size || $size < 2 || $size > $max_bytes ) {
			throw new RuntimeException( 'Ukuran file ' . $label . ' tidak valid.' );
		}
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			throw new RuntimeException( 'File ' . $label . ' tidak dapat dibaca.' );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || JSON_ERROR_NONE !== json_last_error() ) {
			throw new RuntimeException( 'File ' . $label . ' bukan JSON yang valid.' );
		}
		return $data;
	}

	/**
	 * @param string $relative Relative file.
	 * @return string
	 */
	private function confined_file( $relative ) {
		$relative = (string) $relative;
		if ( '' === $relative
			|| false !== strpos( $relative, '..' )
			|| '/' === substr( $relative, 0, 1 )
			|| false !== strpos( $relative, '\\' )
			|| false !== strpos( $relative, ':' )
			|| basename( $relative ) !== $relative ) {
			throw new RuntimeException( 'Path bundle tidak aman.' );
		}
		$root = realpath( $this->runtime_dir );
		$path = realpath( $this->runtime_dir . '/' . $relative );
		if ( false === $root || false === $path || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR ) ) {
			throw new RuntimeException( 'File bundle berada di luar runtime bundle yang diizinkan.' );
		}
		return $path;
	}
}
