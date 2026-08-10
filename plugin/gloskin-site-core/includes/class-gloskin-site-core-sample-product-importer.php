<?php
/**
 * Narrow non-service coordinator for the temporary one-shot Woo sample import.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-gloskin-site-core-sample-product-bundle.php';

final class Gloskin_Site_Core_Sample_Product_Importer {
	const STATE_OPTION       = 'gloskin_site_core_sample_products_v1_state';
	const LOCK_OPTION        = 'gloskin_site_core_sample_products_v1_lock';
	const SOURCE_META        = '_gloskin_sample_source_id';
	const MEDIA_SOURCE_META  = '_gloskin_sample_media_source_id';
	const BUNDLE_META        = '_gloskin_sample_bundle_id';
	const SAMPLE_META        = '_gloskin_sample_data';
	const MEDIA_URL_META     = '_gloskin_sample_media_source_url';
	const MEDIA_PAGE_META    = '_gloskin_sample_media_source_page';
	const MEDIA_AUTHOR_META  = '_gloskin_sample_media_author';
	const MEDIA_LICENSE_META = '_gloskin_sample_media_license';
	const LOCK_TTL           = 900;

	/** @var Gloskin_Site_Core_Sample_Product_Bundle */
	private $bundle;

	/**
	 * @param string $plugin_file Main plugin file.
	 */
	public function __construct( $plugin_file ) {
		$this->bundle = new Gloskin_Site_Core_Sample_Product_Bundle( $plugin_file );
	}

	/** @return string */
	public function runtime_dir() {
		return $this->bundle->runtime_dir();
	}

	/**
	 * Cheap menu/status detection. Consumed state wins over a redeployed bundle.
	 *
	 * @return array<string,mixed>
	 */
	public function get_summary() {
		$state = $this->get_state();
		if ( 'consumed' === $state['status'] ) {
			return array_merge( $state, array( 'detection' => 'consumed' ) );
		}

		$manifest = $this->bundle->read_header();
		if ( is_wp_error( $manifest ) ) {
			return array_merge(
				$state,
				array(
					'detection'  => 'failed',
					'last_error' => $manifest->get_error_message(),
				)
			);
		}
		if ( empty( $manifest ) ) {
			return array_merge( $state, array( 'detection' => 'none' ) );
		}

		$detection = isset( $state['status'] ) ? (string) $state['status'] : 'pending';
		if ( in_array( $detection, array( 'validating', 'running', 'verifying' ), true ) && ! $this->lock_is_active() ) {
			$detection = 'failed';
		}

		return array_merge(
			$state,
			array(
				'detection'           => $detection,
				'bundle_id'           => (string) $manifest['bundle_id'],
				'source_version'      => (string) $manifest['source_version'],
				'expected_products'   => (int) $manifest['expected_products'],
				'expected_simple'     => (int) $manifest['expected_simple'],
				'expected_variable'   => (int) $manifest['expected_variable'],
				'expected_variations' => (int) $manifest['expected_variations'],
				'expected_media'      => (int) $manifest['expected_media'],
			)
		);
	}

	/** @return bool */
	public function should_show_menu() {
		$summary = $this->get_summary();
		return in_array( $summary['detection'], array( 'pending', 'failed', 'running', 'verifying' ), true );
	}

	/**
	 * Advance the explicitly-started workflow by one deterministic checkpoint.
	 *
	 * `start` performs full validation and initializes/resumes state but writes no
	 * Woo object. `continue` processes at most one parent, or performs final
	 * verification when all parents have already been checkpointed.
	 *
	 * @param string $mode start|continue.
	 * @return array<string,mixed>
	 * @throws RuntimeException Failure.
	 */
	public function advance( $mode ) {
		$mode  = sanitize_key( (string) $mode );
		$state = $this->get_state();
		if ( 'consumed' === $state['status'] ) {
			throw new RuntimeException( 'Bundle sample product sudah dikonsumsi dan tidak dapat dijalankan kembali.' );
		}
		if ( ! in_array( $mode, array( 'start', 'continue' ), true ) ) {
			throw new RuntimeException( 'Mode checkpoint sample product tidak valid.' );
		}

		$token = $this->acquire_lock();
		if ( '' === $token ) {
			throw new RuntimeException( 'Import sample product sedang berjalan pada request lain.' );
		}

		try {
			if ( 'start' === $mode ) {
				$validating               = $state;
				$validating['status']     = 'validating';
				$validating['last_error'] = '';
				$this->save_state( $validating );
			}

			$this->assert_woocommerce_available();
			$validated = $this->bundle->validate();
			$manifest  = $validated['manifest'];

			if ( 'start' === $mode ) {
				$state = $this->initialize_or_resume_state( $state, $manifest );
				$this->save_state( $state );
				$this->release_lock( $token );
				return $this->response_state( $state );
			}

			$state = $this->normalize_state_for_bundle( $state, $manifest );
			if ( (int) $state['next_product_index'] < count( $validated['products'] ) ) {
				$index  = (int) $state['next_product_index'];
				$result = $this->import_parent(
					$validated['products'][ $index ],
					$validated['media_by_product'][ $validated['products'][ $index ]['source_id'] ],
					(string) $manifest['bundle_id']
				);

				$state['processed_products'] = (int) $state['processed_products'] + 1;
				$state['next_product_index'] = $index + 1;
				if ( 'created' === $result['action'] ) {
					$state['created_products'] = (int) $state['created_products'] + 1;
				} else {
					$state['updated_products'] = (int) $state['updated_products'] + 1;
				}
				$state['imported_media'] = (int) $state['imported_media'] + (int) $result['imported_media'];
				$state['reused_media']   = (int) $state['reused_media'] + (int) $result['reused_media'];
				$state['status']         = $state['next_product_index'] >= count( $validated['products'] ) ? 'verifying' : 'running';
				$state['last_error']     = '';
				$this->save_state( $state );
				$this->release_lock( $token );
				return $this->response_state( $state );
			}

			$state['status'] = 'verifying';
			$this->save_state( $state );
			$this->verify_all( $validated );

			// Logical consumption is authoritative and MUST be persisted before filesystem cleanup.
			$state['status']     = 'consumed';
			$state['cleanup']    = 'pending';
			$state['last_error'] = '';
			$this->save_state( $state );
			$this->release_lock( $token );

			$cleanup                  = $this->bundle->cleanup( $manifest );
			$consumed                 = $this->get_state();
			$consumed['cleanup']      = $cleanup['ok'] ? 'complete' : 'failed';
			$consumed['cleanup_error'] = (string) $cleanup['message'];
			$this->save_state( $consumed );
			return $this->response_state( $consumed );
		} catch ( Throwable $error ) {
			$this->release_lock( $token );
			$failed = $this->get_state();
			if ( 'consumed' !== $failed['status'] ) {
				$failed['status']     = 'failed';
				$failed['last_error'] = $error->getMessage();
				$this->save_state( $failed );
			}
			throw new RuntimeException( $error->getMessage(), 0, $error );
		}
	}

	/** @return array<string,mixed> */
	public function get_state() {
		$state = get_option( self::STATE_OPTION, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		$defaults = array(
			'status'             => 'pending',
			'bundle_id'          => '',
			'source_version'     => '',
			'bundle_fingerprint' => '',
			'next_product_index' => 0,
			'expected_products'  => 13,
			'processed_products' => 0,
			'created_products'   => 0,
			'updated_products'   => 0,
			'imported_media'     => 0,
			'reused_media'       => 0,
			'expected_media'     => 58,
			'cleanup'            => 'pending',
			'cleanup_error'      => '',
			'last_error'         => '',
			'updated_at'         => 0,
		);
		return array_merge( $defaults, $state );
	}

	/**
	 * @param array<string,mixed> $state Prior state.
	 * @param array<string,mixed> $manifest Manifest.
	 * @return array<string,mixed>
	 */
	private function initialize_or_resume_state( array $state, array $manifest ) {
		$same_bundle = (string) $manifest['bundle_id'] === (string) $state['bundle_id']
			&& (string) $manifest['source_version'] === (string) $state['source_version'];
		$fingerprint = $this->bundle_fingerprint( $manifest );

		if ( $same_bundle ) {
			$stored_fingerprint = isset( $state['bundle_fingerprint'] ) ? (string) $state['bundle_fingerprint'] : '';
			if ( ( '' !== $stored_fingerprint && ! hash_equals( $stored_fingerprint, $fingerprint ) )
				|| ( '' === $stored_fingerprint && ! empty( $state['processed_products'] ) ) ) {
				throw new RuntimeException( 'Bundle sample product berubah setelah import dimulai. Selesaikan/reconcile bundle sebelum melanjutkan.' );
			}
		} else {
			$state = array();
		}
		$state = array_merge(
			$this->get_state_defaults(),
			$state,
			array(
				'status'             => 'running',
				'bundle_id'          => (string) $manifest['bundle_id'],
				'source_version'     => (string) $manifest['source_version'],
				'bundle_fingerprint' => $fingerprint,
				'expected_products'  => (int) $manifest['expected_products'],
				'expected_media'    => (int) $manifest['expected_media'],
				'last_error'        => '',
			)
		);
		if ( ! $same_bundle ) {
			$state['next_product_index'] = 0;
			$state['processed_products'] = 0;
			$state['created_products']   = 0;
			$state['updated_products']   = 0;
			$state['imported_media']     = 0;
			$state['reused_media']       = 0;
			$state['cleanup']            = 'pending';
			$state['cleanup_error']      = '';
		}
		return $state;
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @param array<string,mixed> $manifest Manifest.
	 * @return array<string,mixed>
	 */
	private function normalize_state_for_bundle( array $state, array $manifest ) {
		if ( (string) $state['bundle_id'] !== (string) $manifest['bundle_id']
			|| (string) $state['source_version'] !== (string) $manifest['source_version'] ) {
			throw new RuntimeException( 'Checkpoint tidak cocok dengan bundle runtime. Jalankan validasi awal kembali.' );
		}
		$fingerprint = $this->bundle_fingerprint( $manifest );
		$stored_fingerprint = isset( $state['bundle_fingerprint'] ) ? (string) $state['bundle_fingerprint'] : '';
		if ( ( '' !== $stored_fingerprint && ! hash_equals( $stored_fingerprint, $fingerprint ) )
			|| ( '' === $stored_fingerprint && ! empty( $state['processed_products'] ) ) ) {
			throw new RuntimeException( 'Bundle sample product berubah setelah import dimulai. Selesaikan/reconcile bundle sebelum melanjutkan.' );
		}
		$state['bundle_fingerprint'] = $fingerprint;
		if ( ! in_array( $state['status'], array( 'running', 'failed', 'verifying', 'validating' ), true ) ) {
			throw new RuntimeException( 'Workflow sample product belum dimulai secara eksplisit.' );
		}
		$state['status'] = (int) $state['next_product_index'] >= (int) $manifest['expected_products'] ? 'verifying' : 'running';
		return $state;
	}

	/** @return array<string,mixed> */
	private function get_state_defaults() {
		return array(
			'status'             => 'pending',
			'bundle_id'          => '',
			'source_version'     => '',
			'bundle_fingerprint' => '',
			'next_product_index' => 0,
			'expected_products'  => 13,
			'processed_products' => 0,
			'created_products'   => 0,
			'updated_products'   => 0,
			'imported_media'     => 0,
			'reused_media'       => 0,
			'expected_media'     => 58,
			'cleanup'            => 'pending',
			'cleanup_error'      => '',
			'last_error'         => '',
		);
	}

	/**
	 * Small deterministic fingerprint for the exact product/media payload.
	 *
	 * @param array<string,mixed> $manifest Manifest.
	 * @return string
	 */
	private function bundle_fingerprint( array $manifest ) {
		$checksums = isset( $manifest['checksums'] ) && is_array( $manifest['checksums'] ) ? $manifest['checksums'] : array();
		$products = isset( $checksums['products.json'] ) ? strtolower( trim( (string) $checksums['products.json'] ) ) : '';
		$media    = isset( $checksums['media.json'] ) ? strtolower( trim( (string) $checksums['media.json'] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $products ) || ! preg_match( '/^[a-f0-9]{64}$/', $media ) ) {
			// Production Bundle::validate() guarantees both checksums. The fallback keeps isolated test doubles compatible.
			return hash( 'sha256', (string) $manifest['bundle_id'] . ':' . (string) $manifest['source_version'] );
		}
		return hash( 'sha256', $products . ':' . $media );
	}

	/** @return void */
	private function assert_woocommerce_available() {
		$classes = array( 'WC_Product_Simple', 'WC_Product_Variable', 'WC_Product_Variation', 'WC_Product_Attribute' );
		foreach ( $classes as $class ) {
			if ( ! class_exists( $class ) ) {
				throw new RuntimeException( 'WooCommerce belum tersedia. Aktifkan WooCommerce sebelum menjalankan Sample Product Import.' );
			}
		}
		foreach ( array( 'wc_get_product', 'wc_get_product_id_by_sku' ) as $function ) {
			if ( ! function_exists( $function ) ) {
				throw new RuntimeException( 'WooCommerce CRUD belum tersedia. Aktifkan WooCommerce sebelum menjalankan Sample Product Import.' );
			}
		}
	}

	/**
	 * @param array<string,mixed> $record Product record.
	 * @param array<int,array<string,mixed>> $media_records Media records.
	 * @param string $bundle_id Bundle.
	 * @return array{action:string,imported_media:int,reused_media:int}
	 */
	private function import_parent( array $record, array $media_records, $bundle_id ) {
		$ids = $this->find_source_ids( $record['source_id'] );
		if ( count( $ids ) > 1 ) {
			throw new RuntimeException( 'Product source identity collision: ' . $record['source_id'] );
		}
		$existing_id = empty( $ids ) ? 0 : (int) $ids[0];
		$this->assert_sku_available( $record['sku'], $existing_id, $record['source_id'] );

		$product = $existing_id ? wc_get_product( $existing_id ) : null;
		if ( $existing_id && ( ! $product || ! is_object( $product ) ) ) {
			throw new RuntimeException( 'Produk Woo target tidak dapat dibuka: ' . $record['source_id'] );
		}
		if ( $product && method_exists( $product, 'is_type' ) && ! $product->is_type( $record['type'] ) ) {
			throw new RuntimeException( 'Tipe produk existing berbeda dari bundle: ' . $record['source_id'] );
		}
		if ( ! $product ) {
			$product = 'simple' === $record['type'] ? new WC_Product_Simple() : new WC_Product_Variable();
		}

		$category_id = $this->resolve_category( $record['category_slug'] );
		$this->apply_parent_fields( $product, $record, $category_id, $bundle_id );
		$product_id = (int) $product->save();
		if ( ! $product_id ) {
			throw new RuntimeException( 'WooCommerce gagal menyimpan produk: ' . $record['source_id'] );
		}

		$imported_media = 0;
		$reused_media   = 0;
		$attachment_ids = array();
		foreach ( $media_records as $media_record ) {
			$media_result = $this->import_media( $media_record, $product_id, $bundle_id );
			$attachment_ids[] = (int) $media_result['id'];
			if ( $media_result['reused'] ) {
				$reused_media++;
			} else {
				$imported_media++;
			}
		}
		if ( count( $attachment_ids ) !== (int) $record['media_count'] ) {
			throw new RuntimeException( 'Jumlah attachment hasil import tidak cocok: ' . $record['source_id'] );
		}
		$product->set_image_id( $attachment_ids[0] );
		$product->set_gallery_image_ids( array_slice( $attachment_ids, 1 ) );
		$product->save();

		if ( 'variable' === $record['type'] ) {
			$this->import_variations( $product_id, $record['variations'], $bundle_id );
			if ( method_exists( 'WC_Product_Variable', 'sync' ) ) {
				WC_Product_Variable::sync( $product_id );
			}
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $product_id );
			}
		}

		$this->verify_parent( $record, $media_records, $bundle_id );

		return array(
			'action'         => $existing_id ? 'updated' : 'created',
			'imported_media' => $imported_media,
			'reused_media'   => $reused_media,
		);
	}

	/**
	 * @param object $product Woo product.
	 * @param array<string,mixed> $record Product.
	 * @param int $category_id Term ID.
	 * @param string $bundle_id Bundle.
	 * @return void
	 */
	private function apply_parent_fields( $product, array $record, $category_id, $bundle_id ) {
		$product->set_name( $record['name'] );
		$product->set_slug( $record['slug'] );
		$product->set_status( 'draft' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_short_description( wp_kses_post( $record['short_description'] ) );
		$product->set_description( wp_kses_post( $record['description'] ) );
		$product->set_sku( $record['sku'] );
		$product->set_category_ids( array( (int) $category_id ) );
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );

		$attributes = array();
		$size       = new WC_Product_Attribute();
		$size->set_id( 0 );
		$size->set_name( 'Ukuran' );
		$size->set_position( 0 );
		$size->set_visible( true );

		if ( 'simple' === $record['type'] ) {
			$product->set_regular_price( $record['regular_price'] );
			$size->set_options( array( $record['size'] ) );
			$size->set_variation( false );
		} else {
			$size->set_options( array_values( wp_list_pluck( $record['variations'], 'size' ) ) );
			$size->set_variation( true );
		}
		$attributes[] = $size;

		$usage = new WC_Product_Attribute();
		$usage->set_id( 0 );
		$usage->set_name( 'Usage' );
		$usage->set_options( array( $record['usage'] ) );
		$usage->set_position( 1 );
		$usage->set_visible( false );
		$usage->set_variation( false );
		$attributes[] = $usage;
		$product->set_attributes( $attributes );

		$product->update_meta_data( self::SOURCE_META, $record['source_id'] );
		$product->update_meta_data( self::BUNDLE_META, $bundle_id );
		$product->update_meta_data( self::SAMPLE_META, 1 );
	}

	/**
	 * @param int $parent_id Parent ID.
	 * @param array<int,array<string,mixed>> $variations Variation records.
	 * @param string $bundle_id Bundle.
	 * @return void
	 */
	private function import_variations( $parent_id, array $variations, $bundle_id ) {
		foreach ( $variations as $record ) {
			$ids = $this->find_source_ids( $record['source_id'] );
			if ( count( $ids ) > 1 ) {
				throw new RuntimeException( 'Variation source identity collision: ' . $record['source_id'] );
			}
			$existing_id = empty( $ids ) ? 0 : (int) $ids[0];
			$this->assert_sku_available( $record['sku'], $existing_id, $record['source_id'] );

			$variation = $existing_id ? wc_get_product( $existing_id ) : new WC_Product_Variation();
			if ( ! $variation || ! is_object( $variation ) || ( method_exists( $variation, 'is_type' ) && ! $variation->is_type( 'variation' ) ) ) {
				throw new RuntimeException( 'Variasi Woo target tidak valid atau source identity dipakai tipe object lain: ' . $record['source_id'] );
			}
			if ( $existing_id && method_exists( $variation, 'get_parent_id' ) && (int) $variation->get_parent_id() !== (int) $parent_id ) {
				throw new RuntimeException( 'Variasi existing dimiliki parent lain: ' . $record['source_id'] );
			}

			$variation->set_parent_id( $parent_id );
			$variation->set_status( 'publish' );
			$variation->set_sku( $record['sku'] );
			$variation->set_regular_price( $record['regular_price'] );
			$variation->set_manage_stock( false );
			$variation->set_stock_status( 'instock' );
			$variation->set_attributes( array( 'ukuran' => $record['size'] ) );
			$variation->update_meta_data( self::SOURCE_META, $record['source_id'] );
			$variation->update_meta_data( self::BUNDLE_META, $bundle_id );
			$variation->update_meta_data( self::SAMPLE_META, 1 );
			if ( ! $variation->save() ) {
				throw new RuntimeException( 'WooCommerce gagal menyimpan variasi: ' . $record['source_id'] );
			}
		}
	}

	/**
	 * @param array<string,mixed> $record Media record.
	 * @param int $product_id Parent product ID.
	 * @param string $bundle_id Bundle.
	 * @return array{id:int,reused:bool}
	 */
	private function import_media( array $record, $product_id, $bundle_id ) {
		$ids = $this->find_media_ids( $record['source_id'] );
		if ( count( $ids ) > 1 ) {
			throw new RuntimeException( 'Media source identity collision: ' . $record['source_id'] );
		}
		if ( 1 === count( $ids ) ) {
			$attachment_id = (int) $ids[0];
			if ( 'attachment' !== get_post_type( $attachment_id ) ) {
				throw new RuntimeException( 'Media source identity tidak menunjuk attachment: ' . $record['source_id'] );
			}
			$this->assert_media_identity_immutable( $attachment_id, $record, $bundle_id );
			$this->assert_attachment_healthy( $attachment_id, $record );
			$this->apply_media_meta( $attachment_id, $record, $bundle_id );
			return array( 'id' => $attachment_id, 'reused' => true );
		}

		$this->load_media_dependencies();
		$tmp = download_url( $record['source_url'], 60 );
		if ( is_wp_error( $tmp ) ) {
			throw new RuntimeException( 'Gagal mengunduh media ' . $record['source_id'] . ': ' . $tmp->get_error_message() );
		}
		$file = array(
			'name'     => $record['filename'],
			'tmp_name' => $tmp,
		);
		$attachment_id = media_handle_sideload( $file, $product_id, '' );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			throw new RuntimeException( 'Gagal menyimpan media ' . $record['source_id'] . ': ' . $attachment_id->get_error_message() );
		}
		$attachment_id = (int) $attachment_id;
		$this->apply_media_meta( $attachment_id, $record, $bundle_id );

		return array( 'id' => $attachment_id, 'reused' => false );
	}

	/**
	 * Guard the immutable provenance of a reused attachment before it is touched.
	 *
	 * @param int $attachment_id Existing attachment matched by media source identity.
	 * @param array<string,mixed> $record Media record.
	 * @param string $bundle_id Bundle.
	 * @return void
	 */
	private function assert_media_identity_immutable( $attachment_id, array $record, $bundle_id ) {
		$existing_sample = (string) get_post_meta( $attachment_id, self::SAMPLE_META, true );
		if ( '1' !== $existing_sample ) {
			throw new RuntimeException( 'Media source identity sudah dipakai oleh attachment yang bukan Gloskin sample data. Import dihentikan untuk mencegah pengambilalihan diam-diam: ' . $record['source_id'] );
		}
		$existing_bundle = (string) get_post_meta( $attachment_id, self::BUNDLE_META, true );
		if ( '' !== $existing_bundle && $existing_bundle !== (string) $bundle_id ) {
			throw new RuntimeException( 'Media source identity dipakai oleh bundle lain (' . $existing_bundle . '): ' . $record['source_id'] );
		}
		$existing_url = (string) get_post_meta( $attachment_id, self::MEDIA_URL_META, true );
		if ( '' !== $existing_url && $existing_url !== (string) $record['source_url'] ) {
			throw new RuntimeException( 'Media source identity memiliki source URL berbeda. Import dihentikan untuk mencegah provenance drift.' );
		}
	}

	/**
	 * @param int $attachment_id Attachment.
	 * @param array<string,mixed> $record Media record.
	 * @return void
	 */
	private function assert_attachment_healthy( $attachment_id, array $record ) {
		$attached_file = function_exists( 'get_attached_file' ) ? get_attached_file( $attachment_id ) : null;
		if ( null === $attached_file ) {
			return;
		}
		$broken_message = 'File attachment lokal hilang atau rusak untuk ' . $record['source_id'] . '. Pulihkan atau hapus attachment tersebut sebelum Resume Import.';
		if ( ! is_string( $attached_file ) || '' === trim( $attached_file ) || ! is_file( $attached_file ) ) {
			throw new RuntimeException( $broken_message );
		}
		if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
			throw new RuntimeException( $broken_message );
		}
		if ( filesize( $attached_file ) <= 0 ) {
			throw new RuntimeException( $broken_message );
		}
	}

	/**
	 * @param int $attachment_id Attachment.
	 * @param array<string,mixed> $record Media record.
	 * @param string $bundle_id Bundle.
	 * @return void
	 */
	private function apply_media_meta( $attachment_id, array $record, $bundle_id ) {
		update_post_meta( $attachment_id, self::MEDIA_SOURCE_META, $record['source_id'] );
		update_post_meta( $attachment_id, self::BUNDLE_META, $bundle_id );
		update_post_meta( $attachment_id, self::SAMPLE_META, 1 );
		update_post_meta( $attachment_id, self::MEDIA_URL_META, $record['source_url'] );
		update_post_meta( $attachment_id, self::MEDIA_PAGE_META, $record['source_page_url'] );
		update_post_meta( $attachment_id, self::MEDIA_AUTHOR_META, $record['author'] );
		update_post_meta( $attachment_id, self::MEDIA_LICENSE_META, $record['license_note'] );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $record['alt'] );
	}

	/** @return void */
	private function load_media_dependencies() {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	/**
	 * @param string $slug Category slug.
	 * @return int
	 */
	private function resolve_category( $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term instanceof WP_Term ) {
			return (int) $term->term_id;
		}
		$result = wp_insert_term( $this->category_label( $slug ), 'product_cat', array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( 'Gagal membuat kategori Woo ' . $slug . ': ' . $result->get_error_message() );
		}
		return isset( $result['term_id'] ) ? (int) $result['term_id'] : 0;
	}

	/**
	 * Explicit display label for a newly-created category. Existing terms are reused
	 * unchanged and never renamed; this only applies when the slug must be created.
	 *
	 * @param string $slug Category slug.
	 * @return string
	 */
	private function category_label( $slug ) {
		$labels = array(
			'facial-wash'                   => 'Facial Wash',
			'day-cream-sunscreen'           => 'Day Cream / Sunscreen',
			'toner'                         => 'Toner',
			'serum'                         => 'Serum',
			'acne-care'                     => 'Acne Care',
			'anti-aging'                    => 'Anti-Aging',
			'brightening-pigmentation-care' => 'Brightening & Pigmentation Care',
		);
		return isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucwords( str_replace( array( '-', '&' ), array( ' ', '&' ), $slug ) );
	}

	/**
	 * @param string $sku SKU.
	 * @param int $expected_id Existing expected target, 0 for a create.
	 * @param string $source_id Source identity.
	 * @return void
	 */
	private function assert_sku_available( $sku, $expected_id, $source_id ) {
		$sku_id = (int) wc_get_product_id_by_sku( $sku );
		if ( $sku_id && ( ! $expected_id || $sku_id !== (int) $expected_id ) ) {
			throw new RuntimeException( 'SKU collision dengan produk Woo lain (' . $sku . ') untuk ' . $source_id );
		}
	}

	/**
	 * @param string $source_id Source ID.
	 * @return array<int,int>
	 */
	private function find_source_ids( $source_id ) {
		$ids = get_posts(
			array(
				'post_type'        => array( 'product', 'product_variation' ),
				'post_status'      => 'any',
				'meta_key'         => self::SOURCE_META,
				'meta_value'       => $source_id,
				'fields'           => 'ids',
				'numberposts'      => 2,
				'suppress_filters' => true,
			)
		);
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * @param string $source_id Media source.
	 * @return array<int,int>
	 */
	private function find_media_ids( $source_id ) {
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'meta_key'         => self::MEDIA_SOURCE_META,
				'meta_value'       => $source_id,
				'fields'           => 'ids',
				'numberposts'      => 2,
				'suppress_filters' => true,
			)
		);
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * Verify the parent just processed, including media and variations.
	 *
	 * @param array<string,mixed> $record Product.
	 * @param array<int,array<string,mixed>> $media_records Media.
	 * @param string $bundle_id Bundle.
	 * @return void
	 */
	private function verify_parent( array $record, array $media_records, $bundle_id ) {
		$ids = $this->find_source_ids( $record['source_id'] );
		if ( 1 !== count( $ids ) ) {
			throw new RuntimeException( 'Verifikasi product source identity gagal: ' . $record['source_id'] );
		}
		$product = wc_get_product( (int) $ids[0] );
		if ( ! $product
			|| ! $product->is_type( $record['type'] )
			|| (string) $product->get_sku() !== $record['sku']
			|| 'draft' !== (string) $product->get_status()
			|| (string) $product->get_meta( self::BUNDLE_META, true ) !== $bundle_id
			|| '1' !== (string) $product->get_meta( self::SAMPLE_META, true ) ) {
			throw new RuntimeException( 'Verifikasi produk Woo gagal: ' . $record['source_id'] );
		}

		$terms = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'slugs' ) );
		$term_slugs = is_wp_error( $terms ) ? array() : array_values( (array) $terms );
		sort( $term_slugs );
		if ( array( $record['category_slug'] ) !== $term_slugs ) {
			throw new RuntimeException( 'Verifikasi kategori produk gagal: ' . $record['source_id'] );
		}

		$expected_media = array();
		foreach ( $media_records as $media_record ) {
			$media_ids = $this->find_media_ids( $media_record['source_id'] );
			if ( 1 !== count( $media_ids ) ) {
				throw new RuntimeException( 'Verifikasi media source identity gagal: ' . $media_record['source_id'] );
			}
			$media_id = (int) $media_ids[0];
			if ( (string) get_post_meta( $media_id, self::BUNDLE_META, true ) !== $bundle_id
				|| '1' !== (string) get_post_meta( $media_id, self::SAMPLE_META, true ) ) {
				throw new RuntimeException( 'Verifikasi provenance media gagal: ' . $media_record['source_id'] );
			}
			$expected_media[] = $media_id;
		}
		if ( (int) $product->get_image_id() !== $expected_media[0]
			|| array_values( array_map( 'intval', $product->get_gallery_image_ids() ) ) !== array_slice( $expected_media, 1 ) ) {
			throw new RuntimeException( 'Verifikasi featured/gallery media gagal: ' . $record['source_id'] );
		}

		if ( 'simple' === $record['type'] ) {
			if ( (string) $product->get_regular_price() !== (string) $record['regular_price'] ) {
				throw new RuntimeException( 'Verifikasi harga produk simple gagal: ' . $record['source_id'] );
			}
			return;
		}

		foreach ( $record['variations'] as $variation_record ) {
			$variation_ids = $this->find_source_ids( $variation_record['source_id'] );
			if ( 1 !== count( $variation_ids ) ) {
				throw new RuntimeException( 'Verifikasi variation source identity gagal: ' . $variation_record['source_id'] );
			}
			$variation = wc_get_product( (int) $variation_ids[0] );
			if ( ! $variation
				|| (int) $variation->get_parent_id() !== (int) $product->get_id()
				|| (string) $variation->get_sku() !== $variation_record['sku']
				|| 'publish' !== (string) $variation->get_status()
				|| (string) $variation->get_regular_price() !== (string) $variation_record['regular_price']
				|| (string) $variation->get_attribute( 'ukuran' ) !== $variation_record['size']
				|| (string) $variation->get_meta( self::BUNDLE_META, true ) !== $bundle_id
				|| '1' !== (string) $variation->get_meta( self::SAMPLE_META, true ) ) {
				throw new RuntimeException( 'Verifikasi variasi Woo gagal: ' . $variation_record['source_id'] );
			}
		}
	}

	/**
	 * Full post-run verification before irreversible logical consumption.
	 *
	 * @param array<string,mixed> $validated Validated bundle.
	 * @return void
	 */
	private function verify_all( array $validated ) {
		$manifest = $validated['manifest'];
		$bundle_id = (string) $manifest['bundle_id'];

		$parent_ids = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'any',
				'meta_key'         => self::BUNDLE_META,
				'meta_value'       => $bundle_id,
				'fields'           => 'ids',
				'numberposts'      => -1,
				'suppress_filters' => true,
			)
		);
		$variation_ids = get_posts(
			array(
				'post_type'        => 'product_variation',
				'post_status'      => 'any',
				'meta_key'         => self::BUNDLE_META,
				'meta_value'       => $bundle_id,
				'fields'           => 'ids',
				'numberposts'      => -1,
				'suppress_filters' => true,
			)
		);
		$attachment_ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'meta_key'         => self::BUNDLE_META,
				'meta_value'       => $bundle_id,
				'fields'           => 'ids',
				'numberposts'      => -1,
				'suppress_filters' => true,
			)
		);
		if ( (int) $manifest['expected_products'] !== count( (array) $parent_ids )
			|| (int) $manifest['expected_variations'] !== count( (array) $variation_ids )
			|| (int) $manifest['expected_media'] !== count( (array) $attachment_ids ) ) {
			throw new RuntimeException( 'Verifikasi total object Woo/media tidak cocok dengan manifest.' );
		}

		$simple = 0;
		$variable = 0;
		foreach ( $validated['products'] as $record ) {
			$this->verify_parent( $record, $validated['media_by_product'][ $record['source_id'] ], $bundle_id );
			$ids = $this->find_source_ids( $record['source_id'] );
			$product = wc_get_product( (int) $ids[0] );
			if ( $product->is_type( 'simple' ) ) {
				$simple++;
			} elseif ( $product->is_type( 'variable' ) ) {
				$variable++;
			}
			if ( (int) wc_get_product_id_by_sku( $record['sku'] ) !== (int) $product->get_id() ) {
				throw new RuntimeException( 'Verifikasi SKU parent tidak unik: ' . $record['sku'] );
			}
			foreach ( $record['variations'] as $variation_record ) {
				$variation_source_ids = $this->find_source_ids( $variation_record['source_id'] );
				if ( 1 !== count( $variation_source_ids )
					|| (int) wc_get_product_id_by_sku( $variation_record['sku'] ) !== (int) $variation_source_ids[0] ) {
					throw new RuntimeException( 'Verifikasi SKU variasi tidak unik: ' . $variation_record['sku'] );
				}
			}
		}
		if ( (int) $manifest['expected_simple'] !== $simple || (int) $manifest['expected_variable'] !== $variable ) {
			throw new RuntimeException( 'Verifikasi jumlah simple/variable gagal.' );
		}

		foreach ( $validated['media'] as $media_record ) {
			if ( 1 !== count( $this->find_media_ids( $media_record['source_id'] ) ) ) {
				throw new RuntimeException( 'Verifikasi final media source identity gagal: ' . $media_record['source_id'] );
			}
		}
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>
	 */
	private function response_state( array $state ) {
		$expected  = max( 1, (int) $state['expected_products'] );
		$processed = min( $expected, (int) $state['processed_products'] );
		$state['progress_percent'] = (int) floor( ( $processed / $expected ) * 100 );
		if ( 'verifying' === $state['status'] ) {
			$state['progress_percent'] = 100;
		}
		if ( 'consumed' === $state['status'] ) {
			$state['progress_percent'] = 100;
		}
		return $state;
	}

	/** @return string */
	private function acquire_lock() {
		$existing = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $existing ) && ! empty( $existing['created_at'] ) && ( time() - (int) $existing['created_at'] ) > self::LOCK_TTL ) {
			delete_option( self::LOCK_OPTION );
		}
		$token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'gloskin-sample-', true );
		return add_option(
			self::LOCK_OPTION,
			array(
				'token'      => $token,
				'created_at' => time(),
			),
			'',
			false
		) ? $token : '';
	}

	/** @return bool */
	private function lock_is_active() {
		$lock = get_option( self::LOCK_OPTION, array() );
		return is_array( $lock )
			&& ! empty( $lock['created_at'] )
			&& ( time() - (int) $lock['created_at'] ) <= self::LOCK_TTL;
	}

	/**
	 * @param string $token Lock token.
	 * @return void
	 */
	private function release_lock( $token ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @return void
	 */
	private function save_state( array $state ) {
		$state['updated_at'] = time();
		update_option( self::STATE_OPTION, $state, false );
	}
}
