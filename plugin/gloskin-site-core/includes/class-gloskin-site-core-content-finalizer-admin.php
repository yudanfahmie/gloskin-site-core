<?php
/**
 * Canonical content resolver.
 *
 * Operator-triggered only. This class owns one durable WordPress/WooCommerce
 * reconciliation pass: canonical product copy + native product_cat, image-ready
 * Promo/Piagam replacements, and Trash-only cleanup for explicit legacy/demo
 * Gloskin records. It never deletes attachments or hard-deletes posts.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Content_Finalizer_Admin {
	const SLUG                 = 'gloskin-content-finalizer';
	const POST_ACTION          = 'gloskin_content_finalize';
	const NONCE_KEY            = 'gloskin_content_finalize_nonce';
	const CAPABILITY           = 'manage_options';
	/* Persisted DB keys — never rename these after first deploy. */
	const STATE_OPTION         = 'gloskin_site_core_phase4_finalizer_v1_state';
	const IDENTITY_META        = '_gloskin_phase4_identity';
	const PLACEHOLDER_META     = '_gloskin_phase4_placeholder';
	const ASSET_META           = '_gloskin_phase4_asset_key';
	const CONTENT_SOURCE_META  = '_gloskin_phase4_content_source';
	const CONTENT_VERSION_META = '_gloskin_phase4_content_version';
	const CONTENT_SOURCE       = 'product-content-research.md:conservative-fallback';
	const CONTENT_VERSION      = '2026-08-21.2';

	/** @return bool */
	public static function is_complete() {
		$state = get_option( self::STATE_OPTION, array() );
		return is_array( $state ) && isset( $state['status'] ) && 'complete' === $state['status'];
	}

	/** @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_' . self::POST_ACTION, array( $this, 'handle_finalize' ) );
	}

	/** @return void */
	public function register_page() {
		add_submenu_page(
			Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG,
			'Finalisasi Konten',
			'Finalisasi Konten',
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/** @return void */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$state  = get_option( self::STATE_OPTION, array() );
		$status = is_array( $state ) && ! empty( $state['status'] ) ? (string) $state['status'] : 'not_started';
		?>
		<div class="wrap">
			<h1>Finalisasi Konten</h1>
			<p>Resolver satu kali untuk konten produk canonical, kategori WooCommerce, media Promo/Piagam, dan cleanup legacy/demo ke Trash. Tidak ada hard delete post atau penghapusan Media Library.</p>
			<p><strong>Status:</strong> <code><?php echo esc_html( $status ); ?></code></p>
			<?php if ( 'complete' === $status ) : ?>
				<div class="notice notice-success inline"><p>Finalisasi konten sudah lengkap. Run berikutnya adalah no-op.</p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>">
					<?php wp_nonce_field( self::POST_ACTION, self::NONCE_KEY ); ?>
					<button type="submit" class="button button-primary">Jalankan Finalisasi Konten</button>
				</form>
			<?php endif; ?>
			<?php if ( is_array( $state ) && ! empty( $state['last_error'] ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( (string) $state['last_error'] ); ?></p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @return void */
	public function handle_finalize() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'Unauthorized', 403 );
		}
		check_admin_referer( self::POST_ACTION, self::NONCE_KEY );
		$this->run();
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	/**
	 * One explicit operator-triggered reconciliation pass.
	 *
	 * @return array<string,mixed>
	 */
	public function run() {
		if ( self::is_complete() ) {
			return array( 'status' => 'already_complete', 'mutations' => 0 );
		}

		$state = array(
			'status'                  => 'running',
			'updated_at'              => time(),
			'last_error'              => '',
			'mutations'               => 0,
			'legacy_products_trashed' => 0,
			'promos_trashed'          => 0,
			'piagam_trashed'          => 0,
			'unrelated_woo_mutations' => 0,
			'hard_deleted_posts'      => 0,
			'media_deletions'         => 0,
		);
		update_option( self::STATE_OPTION, $state, false );

		try {
			$canonical  = $this->resolve_canonical_products();
			$content    = $this->reconcile_product_content( $canonical );
			$commerce   = $this->apply_woo_categories( $canonical );
			$promo_ids  = $this->prepare_promos();
			$piagam_ids = $this->prepare_piagam();

			$this->assert_canonical_scope( $canonical );
			$this->assert_content_ready( $content );
			$this->assert_commerce_ready( $commerce );
			$this->assert_replacements_ready( $promo_ids, $piagam_ids );

			$canonical_ids = array_merge( array_values( $canonical['skincare'] ), array_values( $canonical['treatment'] ) );
			$state['legacy_products_trashed'] = $this->trash_explicit_legacy_products( $canonical_ids );
			$state['promos_trashed'] = $this->trash_obsolete_records(
				Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
				$promo_ids
			);
			$state['piagam_trashed'] = $this->trash_obsolete_records(
				Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE,
				$piagam_ids
			);

			$final_content  = $this->verify_product_content( $canonical );
			$final_commerce = $this->verify_woo_categories( $canonical );
			$legacy_active  = $this->explicit_legacy_product_ids( $canonical_ids );

			$this->assert_content_ready( $final_content );
			$this->assert_commerce_ready( $final_commerce );
			$this->assert_replacements_ready( $promo_ids, $piagam_ids );
			$this->assert_only_replacement_records_active( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, $promo_ids );
			$this->assert_only_replacement_records_active( Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE, $piagam_ids );
			if ( $legacy_active ) {
				throw new RuntimeException( 'Explicit active Gloskin demo/legacy Woo products remain after finalization.' );
			}

			$state['status']                      = 'complete';
			$state['updated_at']                  = time();
			$state['canonical_products']          = 73;
			$state['active_legacy_products']      = 0;
			$state['skincare_short_description']  = $final_content['skincare_short'];
			$state['skincare_full_description']   = $final_content['skincare_full'];
			$state['treatment_short_description'] = $final_content['treatment_short'];
			$state['treatment_full_description']  = $final_content['treatment_full'];
			$state['skincare_product_cat']        = $final_commerce['skincare'];
			$state['treatment_product_cat']       = $final_commerce['treatment'];
			$state['canonical_uncategorized']     = $final_commerce['uncategorized'];
			$state['promo_replacements']          = count( $promo_ids );
			$state['piagam_replacements']         = count( $piagam_ids );
			update_option( self::STATE_OPTION, $state, false );
			return $state;
		} catch ( Throwable $e ) {
			$state['status']     = 'failed';
			$state['updated_at'] = time();
			$state['last_error'] = $e->getMessage();
			update_option( self::STATE_OPTION, $state, false );
			return $state;
		}
	}

	/** @return array{skincare:array<string,int>,treatment:array<string,int>} */
	private function resolve_canonical_products() {
		if ( ! post_type_exists( 'product' ) || ! taxonomy_exists( Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY ) ) {
			throw new RuntimeException( 'Canonical Woo product schema is unavailable.' );
		}

		$out = array( 'skincare' => array(), 'treatment' => array() );
		foreach ( array_keys( self::skincare_category_map() ) as $slug ) {
			$out['skincare'][ $slug ] = $this->canonical_product_id( $slug, Gloskin_Site_Core_Content_Service::FAMILY_SKINCARE );
		}
		foreach ( self::treatment_product_slugs() as $slug ) {
			$out['treatment'][ $slug ] = $this->canonical_product_id( $slug, Gloskin_Site_Core_Content_Service::FAMILY_TREATMENT );
		}
		return $out;
	}

	/** @param array<string,mixed> $canonical Canonical IDs. @return void */
	private function assert_canonical_scope( $canonical ) {
		if ( 25 !== count( $canonical['skincare'] ?? array() ) || 48 !== count( $canonical['treatment'] ?? array() ) ) {
			throw new RuntimeException( 'Canonical Woo scope must resolve exactly 25 Skincare + 48 Treatment products.' );
		}
		$ids = array_merge( array_values( $canonical['skincare'] ), array_values( $canonical['treatment'] ) );
		if ( 73 !== count( array_unique( array_map( 'absint', $ids ) ) ) ) {
			throw new RuntimeException( 'Canonical Woo identities are duplicated or incomplete.' );
		}
	}

	/**
	 * Write canonical product copy to Woo post_excerpt and post_content.
	 *
	 * post_content (full description) is the durable backend companion: it is
	 * written here by the one-shot resolver but intentionally not rendered in
	 * the current Woo tab strip (all tabs are removed; the Short Description
	 * is the PDP primary body). The live merge in product-description-boundary.php
	 * bridges the two fields until all canonical products are fully consolidated.
	 *
	 * @param array<string,mixed> $canonical Canonical IDs.
	 * @return array<string,int>
	 */
	private function reconcile_product_content( $canonical ) {
		foreach ( $canonical['skincare'] as $slug => $product_id ) {
			$copy = $this->skincare_copy( $product_id, self::skincare_category_map()[ $slug ], $slug );
			$this->persist_product_copy( $product_id, $copy );
		}
		foreach ( $canonical['treatment'] as $slug => $product_id ) {
			$copy = $this->treatment_copy( $product_id, $slug );
			$this->persist_product_copy( $product_id, $copy );
		}
		return $this->verify_product_content( $canonical );
	}

	/**
	 * @param int    $product_id    Product ID (used for title in fallback).
	 * @param string $category_slug Canonical skincare category slug.
	 * @param string $slug          Canonical product slug for per-product lookup.
	 * @return array<string,string>
	 */
	private function skincare_copy( $product_id, $category_slug, $slug = '' ) {
		$specific = self::skincare_product_copy_map();
		if ( '' !== $slug && isset( $specific[ $slug ] ) ) {
			return $specific[ $slug ];
		}
		/* Conservative category-level fallback for products without specific research copy. */
		$title = trim( (string) get_the_title( $product_id ) );
		$labels = array(
			'facial-wash'         => 'pembersih wajah',
			'day-cream-sunscreen' => 'perawatan siang dan perlindungan kulit',
			'toner'               => 'toner',
			'serum'               => 'serum',
			'produk-penunjang'    => 'produk penunjang',
		);
		$label = isset( $labels[ $category_slug ] ) ? $labels[ $category_slug ] : 'skincare';
		$short = sprintf(
			'%1$s adalah bagian dari rangkaian %2$s GLOSKIN untuk melengkapi rutinitas perawatan kulit. Gunakan sesuai kebutuhan kulit dan petunjuk penggunaan produk.',
			$title,
			$label
		);
		$full = sprintf(
			'<p>%1$s adalah bagian dari rangkaian %2$s GLOSKIN yang digunakan sebagai bagian dari rutinitas perawatan kulit.</p><p>Pemilihan rangkaian sebaiknya menyesuaikan kondisi dan kebutuhan kulit. Ikuti petunjuk penggunaan pada produk dan konsultasikan dengan tim Gloskin bila membutuhkan rekomendasi kombinasi perawatan yang sesuai.</p>',
			esc_html( $title ),
			esc_html( $label )
		);
		return array( 'post_excerpt' => $short, 'post_content' => $full );
	}

	/**
	 * @param int    $product_id Product ID (used for title in fallback).
	 * @param string $slug       Canonical product slug for per-product lookup.
	 * @return array<string,string>
	 */
	private function treatment_copy( $product_id, $slug = '' ) {
		$specific = self::treatment_product_copy_map();
		if ( '' !== $slug && isset( $specific[ $slug ] ) ) {
			return $specific[ $slug ];
		}
		/* Conservative concern-based fallback for treatments without specific research copy. */
		$title = trim( (string) get_the_title( $product_id ) );
		$concerns = wp_get_object_terms(
			$product_id,
			Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY,
			array( 'fields' => 'names' )
		);
		if ( is_wp_error( $concerns ) || ! is_array( $concerns ) || ! $concerns ) {
			$focus = 'kebutuhan perawatan yang relevan dengan kondisi individu';
		} else {
			$concerns = array_values( array_filter( array_map( 'sanitize_text_field', array_slice( $concerns, 0, 3 ) ) ) );
			$focus = $concerns ? implode( ', ', $concerns ) : 'kebutuhan perawatan yang relevan dengan kondisi individu';
		}
		$short = sprintf(
			'%1$s merupakan pilihan perawatan Gloskin untuk membantu membahas kebutuhan terkait %2$s. Kesesuaian tindakan ditentukan setelah evaluasi atau konsultasi tenaga medis.',
			$title,
			$focus
		);
		$full = sprintf(
			'<p>%1$s merupakan salah satu pilihan perawatan Gloskin untuk kebutuhan terkait %2$s. Rekomendasi tindakan disesuaikan dengan kondisi dan tujuan perawatan setiap pasien.</p><p>Pemilihan tindakan, kesesuaian pasien, jumlah sesi, dan ekspektasi hasil ditentukan setelah evaluasi atau konsultasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			esc_html( $title ),
			esc_html( $focus )
		);
		return array( 'post_excerpt' => $short, 'post_content' => $full );
	}

	/** @param int $product_id Product ID. @param array<string,string> $copy Desired copy. @return void */
	private function persist_product_copy( $product_id, $copy ) {
		$post = get_post( absint( $product_id ) );
		if ( ! ( $post instanceof WP_Post ) ) {
			throw new RuntimeException( 'Canonical product disappeared during content reconciliation.' );
		}

		$source_meta  = get_post_meta( $post->ID, self::CONTENT_SOURCE_META, true );
		$version_meta = get_post_meta( $post->ID, self::CONTENT_VERSION_META, true );
		$source_meta  = is_array( $source_meta ) ? $source_meta : array();
		$version_meta = is_array( $version_meta ) ? $version_meta : array();
		$update       = array( 'ID' => $post->ID );
		$owned        = array();

		foreach ( array( 'post_excerpt', 'post_content' ) as $field ) {
			$current = isset( $post->$field ) ? (string) $post->$field : '';
			if ( ! $this->should_write_product_field( $post->ID, $field, $current, $source_meta ) ) {
				continue;
			}
			$update[ $field ] = (string) $copy[ $field ];
			$owned[] = $field;
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				throw new RuntimeException( $result->get_error_message() );
			}
			foreach ( $owned as $field ) {
				$source_meta[ $field ]  = self::CONTENT_SOURCE;
				$version_meta[ $field ] = self::CONTENT_VERSION;
			}
			update_post_meta( $post->ID, self::CONTENT_SOURCE_META, $source_meta );
			update_post_meta( $post->ID, self::CONTENT_VERSION_META, $version_meta );
		}
	}

	/** @param int $product_id Product ID. @param string $field Field. @param string $current Current content. @param array<string,mixed> $sources Ownership sources. @return bool */
	private function should_write_product_field( $product_id, $field, $current, $sources ) {
		if ( '' === trim( wp_strip_all_tags( $current ) ) ) {
			return true;
		}
		if ( isset( $sources[ $field ] ) && self::CONTENT_SOURCE === (string) $sources[ $field ] ) {
			return true;
		}
		if ( $this->product_has_explicit_demo_marker( $product_id ) ) {
			return true;
		}
		$plain = strtolower( wp_strip_all_tags( $current ) );
		foreach ( array( 'lorem ipsum', 'placeholder', 'synthetic staging/demo', 'test product', 'content pending', 'coming soon' ) as $needle ) {
			if ( false !== strpos( $plain, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $canonical Canonical IDs. @return array<string,int> */
	private function verify_product_content( $canonical ) {
		$out = array(
			'skincare_short'  => 0,
			'skincare_full'   => 0,
			'treatment_short' => 0,
			'treatment_full'  => 0,
		);
		foreach ( $canonical['skincare'] as $product_id ) {
			if ( '' !== trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $product_id, 'raw' ) ) ) ) {
				$out['skincare_short']++;
			}
			if ( '' !== trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $product_id, 'raw' ) ) ) ) {
				$out['skincare_full']++;
			}
		}
		foreach ( $canonical['treatment'] as $product_id ) {
			if ( '' !== trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $product_id, 'raw' ) ) ) ) {
				$out['treatment_short']++;
			}
			if ( '' !== trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $product_id, 'raw' ) ) ) ) {
				$out['treatment_full']++;
			}
		}
		return $out;
	}

	/** @param array<string,int> $content Verification result. @return void */
	private function assert_content_ready( $content ) {
		if (
			25 !== absint( $content['skincare_short'] ?? 0 ) ||
			25 !== absint( $content['skincare_full'] ?? 0 ) ||
			48 !== absint( $content['treatment_short'] ?? 0 ) ||
			48 !== absint( $content['treatment_full'] ?? 0 )
		) {
			throw new RuntimeException( 'Canonical product description verification failed.' );
		}
	}

	/** @param array<string,mixed> $canonical Canonical IDs. @return array<string,int> */
	private function apply_woo_categories( $canonical ) {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			throw new RuntimeException( 'WooCommerce product categories are unavailable.' );
		}
		$terms = array(
			'facial-wash'         => $this->ensure_product_cat( 'facial-wash', 'Facial Wash' ),
			'day-cream-sunscreen' => $this->ensure_product_cat( 'day-cream-sunscreen', 'Day Cream / Sunscreen' ),
			'toner'               => $this->ensure_product_cat( 'toner', 'Toner' ),
			'serum'               => $this->ensure_product_cat( 'serum', 'Serum' ),
			'produk-penunjang'    => $this->ensure_product_cat( 'produk-penunjang', 'Produk Penunjang' ),
			'perawatan'           => $this->ensure_product_cat( 'perawatan', 'Perawatan' ),
		);

		foreach ( self::skincare_category_map() as $slug => $category_slug ) {
			$product_id = $canonical['skincare'][ $slug ];
			$this->append_product_cat( $product_id, $terms[ $category_slug ] );
			$this->remove_uncategorized_after_valid_category( $product_id );
		}
		foreach ( $canonical['treatment'] as $product_id ) {
			$this->append_product_cat( $product_id, $terms['perawatan'] );
			$this->remove_uncategorized_after_valid_category( $product_id );
		}
		return $this->verify_woo_categories( $canonical );
	}

	/** @param array<string,mixed> $canonical Canonical IDs. @return array<string,int> */
	private function verify_woo_categories( $canonical ) {
		$skincare_ok = 0;
		foreach ( self::skincare_category_map() as $slug => $category_slug ) {
			$term = get_term_by( 'slug', $category_slug, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) && has_term( (int) $term->term_id, 'product_cat', $canonical['skincare'][ $slug ] ) ) {
				$skincare_ok++;
			}
		}
		$treatment_ok = 0;
		$perawatan = get_term_by( 'slug', 'perawatan', 'product_cat' );
		foreach ( $canonical['treatment'] as $product_id ) {
			if ( $perawatan && ! is_wp_error( $perawatan ) && has_term( (int) $perawatan->term_id, 'product_cat', $product_id ) ) {
				$treatment_ok++;
			}
		}
		return array(
			'skincare'      => $skincare_ok,
			'treatment'     => $treatment_ok,
			'uncategorized' => $this->canonical_uncategorized_count( $canonical ),
		);
	}

	/** @param array<string,int> $commerce Verification result. @return void */
	private function assert_commerce_ready( $commerce ) {
		if (
			25 !== absint( $commerce['skincare'] ?? 0 ) ||
			48 !== absint( $commerce['treatment'] ?? 0 ) ||
			0 !== absint( $commerce['uncategorized'] ?? -1 )
		) {
			throw new RuntimeException( 'Canonical Woo product_cat verification failed.' );
		}
	}

	/** @param string $slug Term slug. @param string $name Term name. @return int */
	private function ensure_product_cat( $slug, $name ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
		if ( is_wp_error( $created ) ) {
			throw new RuntimeException( $created->get_error_message() );
		}
		return absint( $created['term_id'] );
	}

	/** @param int $product_id Product ID. @param int $term_id Term ID. @return void */
	private function append_product_cat( $product_id, $term_id ) {
		$result = wp_set_object_terms( absint( $product_id ), array( absint( $term_id ) ), 'product_cat', true );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
	}

	/** @param int $product_id Product ID. @return void */
	private function remove_uncategorized_after_valid_category( $product_id ) {
		$assigned = wp_get_object_terms( absint( $product_id ), 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $assigned ) || ! $assigned ) {
			throw new RuntimeException( 'Cannot remove Uncategorized without a valid replacement category.' );
		}
		$uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );
		if (
			$uncategorized &&
			! is_wp_error( $uncategorized ) &&
			in_array( (int) $uncategorized->term_id, array_map( 'intval', $assigned ), true ) &&
			count( $assigned ) > 1
		) {
			$result = wp_remove_object_terms( absint( $product_id ), array( (int) $uncategorized->term_id ), 'product_cat' );
			if ( is_wp_error( $result ) ) {
				throw new RuntimeException( $result->get_error_message() );
			}
		}
	}

	/** @param array<string,mixed> $canonical Canonical IDs. @return int */
	private function canonical_uncategorized_count( $canonical ) {
		$uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );
		if ( ! $uncategorized || is_wp_error( $uncategorized ) ) {
			return 0;
		}
		$count = 0;
		foreach ( array_merge( array_values( $canonical['skincare'] ), array_values( $canonical['treatment'] ) ) as $product_id ) {
			if ( has_term( (int) $uncategorized->term_id, 'product_cat', $product_id ) ) {
				$count++;
			}
		}
		return $count;
	}

	/** @return array<int,int> */
	private function prepare_promos() {
		$definitions = array(
			array( 'identity' => 'phase4-promo-01', 'title' => 'Promo Gloskin 01', 'order' => 1, 'asset' => 'promo-01.png' ),
			array( 'identity' => 'phase4-promo-02', 'title' => 'Promo Gloskin 02', 'order' => 2, 'asset' => 'promo-02.png' ),
			array( 'identity' => 'phase4-promo-03', 'title' => 'Promo Gloskin 03', 'order' => 3, 'asset' => 'promo-03.png' ),
		);
		$ids = array();
		foreach ( $definitions as $definition ) {
			$asset_id = $this->ensure_local_artwork_attachment( $definition['identity'], $definition['asset'], 'promo' );
			$post_id = $this->ensure_replacement_post(
				Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE,
				$definition['identity'],
				$definition['title']
			);
			set_post_thumbnail( $post_id, $asset_id );
			update_post_meta( $post_id, self::PLACEHOLDER_META, 'branded-local-v1' );
			update_post_meta( $post_id, 'gloskin_promo_active', '1' );
			update_post_meta( $post_id, 'gloskin_promo_order', (string) $definition['order'] );
			update_post_meta( $post_id, 'gloskin_promo_eyebrow', '' );
			update_post_meta( $post_id, 'gloskin_promo_summary', '' );
			update_post_meta( $post_id, 'gloskin_promo_cta_label', '' );
			update_post_meta( $post_id, 'gloskin_promo_cta_url', '' );
			$this->publish_ready_post( $post_id );
			$ids[] = $post_id;
		}
		return $ids;
	}

	/** @return array<int,int> */
	private function prepare_piagam() {
		$definitions = array(
			array( 'identity' => 'phase4-piagam-01', 'title' => 'Piagam Gloskin 01', 'order' => 1, 'asset' => 'piagam-01.png' ),
			array( 'identity' => 'phase4-piagam-02', 'title' => 'Piagam Gloskin 02', 'order' => 2, 'asset' => 'piagam-02.png' ),
			array( 'identity' => 'phase4-piagam-03', 'title' => 'Piagam Gloskin 03', 'order' => 3, 'asset' => 'piagam-03.png' ),
			array( 'identity' => 'phase4-piagam-04', 'title' => 'Piagam Gloskin 04', 'order' => 4, 'asset' => 'piagam-04.png' ),
		);
		$ids = array();
		foreach ( $definitions as $definition ) {
			$asset_id = $this->ensure_local_artwork_attachment( $definition['identity'], $definition['asset'], 'piagam' );
			$post_id = $this->ensure_replacement_post(
				Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE,
				$definition['identity'],
				$definition['title']
			);
			set_post_thumbnail( $post_id, $asset_id );
			update_post_meta( $post_id, self::PLACEHOLDER_META, 'branded-local-v1' );
			update_post_meta( $post_id, 'gloskin_achievement_active', '1' );
			update_post_meta( $post_id, 'gloskin_achievement_feature_on_home', '1' );
			update_post_meta( $post_id, 'gloskin_achievement_order', (string) $definition['order'] );
			update_post_meta( $post_id, 'gloskin_achievement_issuer', '' );
			update_post_meta( $post_id, 'gloskin_achievement_year', '' );
			$this->publish_ready_post( $post_id );
			$ids[] = $post_id;
		}
		return $ids;
	}

	/** @param string $identity Stable asset identity. @param string $filename Committed filename. @param string $kind promo|piagam. @return int */
	private function ensure_local_artwork_attachment( $identity, $filename, $kind ) {
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => self::ASSET_META, 'value' => $identity, 'compare' => '=' ),
				),
			)
		);
		if ( $existing ) {
			$attachment_id = absint( $existing[0] );
			if ( ! $this->attachment_is_usable_image( $attachment_id ) ) {
				throw new RuntimeException( 'Existing artwork attachment is unusable: ' . $identity );
			}
			return $attachment_id;
		}

		$path = dirname( __DIR__ ) . '/assets/images/content-replacements/' . basename( $filename );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new RuntimeException( 'Committed artwork is missing: ' . $filename );
		}
		$bytes = file_get_contents( $path );
		if ( false === $bytes || '' === $bytes ) {
			throw new RuntimeException( 'Committed artwork cannot be read: ' . $filename );
		}
		$upload = wp_upload_bits( sanitize_file_name( 'gloskin-' . $identity . '.png' ), null, $bytes );
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			throw new RuntimeException( 'Unable to write artwork into Media Library.' );
		}
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => ucwords( str_replace( '-', ' ', $identity ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			throw new RuntimeException( 'Unable to create artwork attachment.' );
		}
		$attachment_id = absint( $attachment_id );
		update_post_meta( $attachment_id, self::ASSET_META, $identity );
		update_post_meta( $attachment_id, self::PLACEHOLDER_META, 'branded-local-v1' );
		update_post_meta( $attachment_id, '_gloskin_phase4_asset_kind', sanitize_key( $kind ) );
		update_post_meta( $attachment_id, '_gloskin_phase4_asset_source', 'committed-local-artwork' );

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
		if ( ! $this->attachment_is_usable_image( $attachment_id ) ) {
			throw new RuntimeException( 'New artwork attachment is unusable.' );
		}
		return $attachment_id;
	}

	/** @param string $post_type Managed CPT. @param string $identity Identity. @param string $title Title. @return int */
	private function ensure_replacement_post( $post_type, $identity, $title ) {
		$existing = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => self::IDENTITY_META, 'value' => $identity, 'compare' => '=' ),
				),
			)
		);
		if ( $existing ) {
			$post_id = absint( $existing[0] );
			if ( 'trash' === get_post_status( $post_id ) ) {
				wp_untrash_post( $post_id );
			}
			$result = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_title'   => $title,
					'post_status'  => 'draft',
					'post_excerpt' => '',
					'post_content' => '',
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				throw new RuntimeException( $result->get_error_message() );
			}
			return $post_id;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_excerpt' => '',
				'post_content' => '',
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			throw new RuntimeException( 'Unable to create replacement post: ' . $identity );
		}
		$post_id = absint( $post_id );
		update_post_meta( $post_id, self::IDENTITY_META, $identity );
		return $post_id;
	}

	/** @param int $post_id Post ID. @return void */
	private function publish_ready_post( $post_id ) {
		if ( ! $this->attachment_is_usable_image( get_post_thumbnail_id( $post_id ) ) ) {
			throw new RuntimeException( 'Replacement cannot publish without usable image.' );
		}
		$result = wp_update_post( array( 'ID' => absint( $post_id ), 'post_status' => 'publish' ), true );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
	}

	/** @param int $attachment_id Attachment ID. @return bool */
	private function attachment_is_usable_image( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}
		$file = get_attached_file( $attachment_id );
		return is_string( $file ) && '' !== $file && file_exists( $file ) && filesize( $file ) > 0;
	}

	/** @param array<int,int> $promo_ids Promo IDs. @param array<int,int> $piagam_ids Piagam IDs. @return void */
	private function assert_replacements_ready( $promo_ids, $piagam_ids ) {
		if ( 3 !== count( $promo_ids ) || 4 !== count( $piagam_ids ) ) {
			throw new RuntimeException( 'Replacement cardinality mismatch.' );
		}
		foreach ( array_merge( $promo_ids, $piagam_ids ) as $post_id ) {
			if ( 'publish' !== get_post_status( $post_id ) || ! $this->attachment_is_usable_image( get_post_thumbnail_id( $post_id ) ) ) {
				throw new RuntimeException( 'Replacement readiness verification failed.' );
			}
		}
	}

	/** @param array<int,int> $canonical_ids Canonical IDs to protect. @return int */
	private function trash_explicit_legacy_products( $canonical_ids ) {
		$ids = $this->explicit_legacy_product_ids( $canonical_ids );
		$trashed = 0;
		foreach ( $ids as $product_id ) {
			if ( wp_trash_post( $product_id ) ) {
				$trashed++;
			}
		}
		return $trashed;
	}

	/**
	 * Resolve only products carrying explicit historical demo/sample provenance.
	 * Canonical family membership alone is intentionally not disposal evidence.
	 *
	 * @param array<int,int> $canonical_ids Canonical IDs.
	 * @return array<int,int>
	 */
	private function explicit_legacy_product_ids( $canonical_ids ) {
		$canonical_ids = array_map( 'absint', $canonical_ids );
		$candidates = array();

		foreach ( array( '_gloskin_sample_source_id', '_gloskin_sample_data', '_gloskin_sample_bundle_id', '_gloskin_demo_identity', '_gloskin_demo_revision' ) as $meta_key ) {
			$ids = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array( 'key' => $meta_key, 'compare' => 'EXISTS' ),
					),
				)
			);
			foreach ( $ids as $id ) {
				$candidates[] = absint( $id );
			}
		}

		$candidates = array_values( array_unique( array_filter( $candidates ) ) );
		return array_values(
			array_filter(
				$candidates,
				static function ( $id ) use ( $canonical_ids ) {
					return ! in_array( absint( $id ), $canonical_ids, true );
				}
			)
		);
	}

	/** @param int $product_id Product ID. @return bool */
	private function product_has_explicit_demo_marker( $product_id ) {
		foreach ( array( '_gloskin_sample_source_id', '_gloskin_sample_data', '_gloskin_sample_bundle_id', '_gloskin_demo_identity', '_gloskin_demo_revision' ) as $meta_key ) {
			if ( metadata_exists( 'post', absint( $product_id ), $meta_key ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Auto-clean only records with explicit ownership/provenance evidence.
	 * Unknown/manual content is preserved and the final verifier fails closed.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function managed_record_has_explicit_obsolete_evidence( $post_id ) {
		$post_id = absint( $post_id );
		foreach (
			array(
				self::IDENTITY_META,
				Gloskin_Site_Core_Content_Service::DEMO_IDENTITY_META,
				Gloskin_Site_Core_Content_Service::DEMO_REVISION_META,
				'_gloskin_sample_source_id',
				'_gloskin_sample_data',
				'_gloskin_sample_bundle_id',
			) as $meta_key
		) {
			if ( metadata_exists( 'post', $post_id, $meta_key ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param string $post_type Managed CPT. @param array<int,int> $keep_ids Replacement IDs. @return int */
	private function trash_obsolete_records( $post_type, $keep_ids ) {
		$ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$keep_ids = array_map( 'absint', $keep_ids );
		$trashed = 0;
		foreach ( $ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( in_array( $post_id, $keep_ids, true ) ) {
				continue;
			}
			if ( ! $this->managed_record_has_explicit_obsolete_evidence( $post_id ) ) {
				continue;
			}
			if ( wp_trash_post( $post_id ) ) {
				$trashed++;
			}
		}
		return $trashed;
	}

	/** @param string $post_type CPT. @param array<int,int> $keep_ids Expected published IDs. @return void */
	private function assert_only_replacement_records_active( $post_type, $keep_ids ) {
		$ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$ids = array_map( 'absint', $ids );
		$expected = array_map( 'absint', $keep_ids );
		$unexpected = array_values( array_diff( $ids, $expected ) );
		if ( $unexpected ) {
			$records = array();
			foreach ( $unexpected as $post_id ) {
				$records[] = sprintf( '#%d "%s"', $post_id, sanitize_text_field( (string) get_the_title( $post_id ) ) );
			}
			throw new RuntimeException( 'Unknown active ' . $post_type . ' records were preserved; manual review required: ' . implode( ', ', $records ) );
		}
		sort( $ids, SORT_NUMERIC );
		sort( $expected, SORT_NUMERIC );
		if ( $ids !== $expected ) {
			throw new RuntimeException( 'Replacement record verification failed.' );
		}
	}

	/** @param string $slug Product slug. @param string $family Canonical family. @return int */
	private function canonical_product_id( $slug, $family ) {
		$post = get_page_by_path( $slug, OBJECT, 'product' );
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== get_post_status( $post->ID ) ) {
			throw new RuntimeException( 'Published canonical Woo product missing: ' . $slug );
		}
		if ( ! has_term( $family, Gloskin_Site_Core_Content_Service::FAMILY_TAXONOMY, $post->ID ) ) {
			throw new RuntimeException( 'Canonical Woo family mismatch: ' . $slug );
		}
		return (int) $post->ID;
	}

	/** @return array<string,string> */
	public static function skincare_category_map() {
		return array(
			'acne-day-protection-cream' => 'day-cream-sunscreen',
			'glam-air-cushion' => 'produk-penunjang',
			'bio-calmskin' => 'produk-penunjang',
			'brightening-face-wash' => 'facial-wash',
			'c-power-silk-gel' => 'produk-penunjang',
			'clear-xpert-serum' => 'serum',
			'essence-bio-moisturizer' => 'produk-penunjang',
			'gloskin-glow-face-tonic' => 'produk-penunjang',
			'glow-face-tonic-pads' => 'produk-penunjang',
			'glowing-facial-wash' => 'facial-wash',
			'glowing-white-sunscreen' => 'day-cream-sunscreen',
			'hydra-xpert-serum' => 'serum',
			'hydro-fresh-foaming' => 'facial-wash',
			'brightening-loose-powder' => 'produk-penunjang',
			'skin-fresh-toner' => 'toner',
			'sense-cleansing-milk' => 'produk-penunjang',
			'transforming-night-cream' => 'produk-penunjang',
			'acne-facial-cleanser' => 'facial-wash',
			'acne-prone-gel' => 'produk-penunjang',
			'cysteamine-advance-plus' => 'produk-penunjang',
			'day-protection-cream' => 'day-cream-sunscreen',
			'flawless-high-defences-50' => 'day-cream-sunscreen',
			'rejuve-xpert-serum' => 'serum',
			'ultimate-whitening-cream' => 'produk-penunjang',
			'whitening-sunscreen' => 'day-cream-sunscreen',
		);
	}

	/** @return array<int,string> */
	public static function treatment_product_slugs() {
		return array(
			'botox', 'hollywood-body-sculpting', 'lymphatic-body', 'we-go-slim',
			'brow-lift', 'buccal-contour', 'eyebag-removal', 'smas-lift', 'upper-lower-eyelid', 'lipoma-removal',
			'derma-oxy-facial-therapy', 'glowing-face-therapy', 'hydra-glowing-luxury-facial-therapy', 'lhala-peel', 'luxury-face-therapy', 'lymphatic-face-therapy', 'oxy-jet-light', 'skin-barrier-facial-therapy', 'triple-glowing',
			'laser-4g', 'mesoglow', 'pico-laser', 'xelarederm',
			'exxohair', 'prp-hair', 'hair-transplant',
			'acne-advance-peeling', 'acne-spot-injection', 'cautery', 'injeksi-keloid', 'korean-comedo-glowing-peel', 'premium-prp', 'rejuran-hb', 'sylfirm-x', 'vip-light',
			'hollywood-face-sculpting', 'juvederm', 'thread-lift', 'ultralift',
			'5gf-glo-booster', 'croma-rich', 'ellanse', 'exxoskin', 'juvelook', 'nucleofil', 'profhilo', 'glowing-salmon-dna', 'skinvive',
		);
	}

	/**
	 * Per-product copy for canonical Skincare products with prepared research evidence.
	 * Products not in this map fall through to the category-level conservative fallback.
	 *
	 * Source: docs/client-feedback-phase-4/product-content-research.md
	 *
	 * @return array<string,array{post_excerpt:string,post_content:string}>
	 */
	public static function skincare_product_copy_map() {
		return array(
			'acne-day-protection-cream' => array(
				'post_excerpt' => 'Acne Day Protection Cream adalah krim perlindungan siang GLOSKIN yang diformulasikan untuk kulit berjerawat, membantu menjaga kulit terlindungi sekaligus merawat kondisi jerawat.',
				'post_content' => '<p>Acne Day Protection Cream adalah krim perawatan siang dari rangkaian day cream &amp; sunscreen GLOSKIN yang diformulasikan khusus untuk kulit bertipe acne-prone.</p><p>Digunakan sebagai bagian dari rutinitas pagi hari. Konsultasikan penggunaan dengan tim Gloskin untuk rekomendasi kombinasi perawatan yang sesuai kondisi kulit Anda.</p>',
			),
			'whitening-sunscreen' => array(
				'post_excerpt' => 'Whitening Sunscreen adalah tabir surya GLOSKIN dengan manfaat pencerah untuk mendukung rutinitas brightening dan perlindungan harian.',
				'post_content' => '<p>Whitening Sunscreen adalah produk perlindungan dan pencerah dari rangkaian day cream &amp; sunscreen GLOSKIN yang mendukung perawatan brightening harian.</p><p>Digunakan sebagai langkah perawatan pagi. Ikuti petunjuk penggunaan dan konsultasikan dengan tim Gloskin untuk kombinasi perawatan terbaik bagi kulit Anda.</p>',
			),
			'glowing-white-sunscreen' => array(
				'post_excerpt' => 'Glowing White Sunscreen adalah tabir surya GLOSKIN dengan sentuhan brightening untuk tampilan kulit lebih cerah dan terlindungi sepanjang hari.',
				'post_content' => '<p>Glowing White Sunscreen merupakan bagian dari rangkaian day cream &amp; sunscreen GLOSKIN yang menggabungkan perlindungan harian dengan manfaat pencerah kulit.</p><p>Gunakan sesuai petunjuk penggunaan sebagai bagian dari rutinitas siang hari. Konsultasikan dengan tim Gloskin bila ingin mengombinasikannya dengan rangkaian perawatan lain.</p>',
			),
			'day-protection-cream' => array(
				'post_excerpt' => 'Day Protection Cream adalah krim perlindungan siang GLOSKIN untuk mendukung kesehatan dan kenyamanan kulit selama beraktivitas.',
				'post_content' => '<p>Day Protection Cream adalah krim perawatan siang dari rangkaian GLOSKIN yang dirancang untuk memberikan perlindungan saat beraktivitas sehari-hari.</p><p>Digunakan sebagai bagian dari rutinitas perawatan siang hari. Konsultasikan dengan tim Gloskin untuk rekomendasi rangkaian yang sesuai kondisi dan kebutuhan kulit Anda.</p>',
			),
			'flawless-high-defences-50' => array(
				'post_excerpt' => 'Flawless High Defences 50 adalah produk perlindungan tinggi dari rangkaian day cream &amp; sunscreen GLOSKIN untuk kulit yang membutuhkan perlindungan ekstra.',
				'post_content' => '<p>Flawless High Defences 50 merupakan bagian dari rangkaian day cream &amp; sunscreen GLOSKIN dengan tingkat perlindungan yang lebih tinggi, ditujukan untuk kebutuhan perlindungan ekstra sehari-hari.</p><p>Gunakan sesuai petunjuk penggunaan produk. Konsultasikan dengan tim Gloskin bila membutuhkan rekomendasi kombinasi yang sesuai kondisi kulit.</p>',
			),
			'brightening-face-wash' => array(
				'post_excerpt' => 'Brightening Face Wash adalah pembersih wajah GLOSKIN dengan manfaat pencerah untuk mendukung rutinitas brightening harian sejak tahap pembersihan.',
				'post_content' => '<p>Brightening Face Wash adalah pembersih wajah dari rangkaian GLOSKIN yang dirancang untuk membersihkan sekaligus memberikan manfaat pencerah pada kulit.</p><p>Digunakan sebagai langkah pembersihan dalam rutinitas brightening harian. Konsultasikan dengan tim Gloskin bila membutuhkan rekomendasi kombinasi produk yang sesuai kondisi kulit.</p>',
			),
			'acne-facial-cleanser' => array(
				'post_excerpt' => 'Acne Facial Cleanser adalah pembersih wajah GLOSKIN yang diformulasikan untuk kulit berjerawat, membantu membersihkan dan merawat kondisi jerawat secara bersamaan.',
				'post_content' => '<p>Acne Facial Cleanser adalah pembersih wajah dari rangkaian GLOSKIN yang diformulasikan untuk kebutuhan kulit acne-prone.</p><p>Digunakan sebagai langkah pembersihan harian dalam rutinitas perawatan kulit berjerawat. Konsultasikan dengan tim Gloskin bila membutuhkan rekomendasi kombinasi perawatan yang tepat untuk kondisi kulit Anda.</p>',
			),
			'hydro-fresh-foaming' => array(
				'post_excerpt' => 'Hydro Fresh Foaming adalah sabun muka berbusa GLOSKIN yang memberikan sensasi segar sekaligus membersihkan kulit secara lembut.',
				'post_content' => '<p>Hydro Fresh Foaming adalah pembersih wajah berbusa dari rangkaian GLOSKIN yang diformulasikan untuk membersihkan kulit dengan lembut sambil memberikan sensasi segar dan nyaman.</p><p>Gunakan sesuai petunjuk penggunaan sebagai bagian dari rutinitas pembersihan harian. Konsultasikan dengan tim Gloskin bila membutuhkan rekomendasi rangkaian yang sesuai kondisi kulit.</p>',
			),
			'glowing-facial-wash' => array(
				'post_excerpt' => 'Glowing Facial Wash adalah pembersih wajah GLOSKIN dengan manfaat brightening untuk tampilan kulit lebih cerah sejak tahap pembersihan.',
				'post_content' => '<p>Glowing Facial Wash adalah pembersih wajah dari rangkaian GLOSKIN yang dirancang untuk memberikan manfaat brightening sekaligus membersihkan kulit secara efektif.</p><p>Digunakan sebagai bagian dari rutinitas pembersihan harian. Konsultasikan dengan tim Gloskin bila ingin mengombinasikan dengan produk lain dalam rangkaian perawatan.</p>',
			),
			'clear-xpert-serum' => array(
				'post_excerpt' => 'Clear Xpert Serum adalah serum GLOSKIN yang diformulasikan untuk membantu mengatasi jerawat dan ketidakmerataan warna kulit sebagai bagian dari rutinitas perawatan.',
				'post_content' => '<p>Clear Xpert Serum adalah serum dari rangkaian GLOSKIN yang diformulasikan untuk membantu menangani kebutuhan kulit terkait kondisi jerawat dan ketidakrataan tekstur.</p><p>Digunakan sebagai bagian dari rutinitas serum harian. Konsultasikan penggunaan dengan tim Gloskin untuk rekomendasi kombinasi produk yang tepat bagi kondisi kulit Anda.</p>',
			),
			'rejuve-xpert-serum' => array(
				'post_excerpt' => 'Rejuve Xpert Serum adalah serum anti-aging GLOSKIN yang mendukung kebutuhan peremajaan dan perawatan kulit dewasa dalam rutinitas harian.',
				'post_content' => '<p>Rejuve Xpert Serum adalah serum dari rangkaian GLOSKIN yang ditujukan untuk membantu memenuhi kebutuhan kulit dalam program perawatan anti-aging dan peremajaan.</p><p>Digunakan sebagai bagian dari rutinitas serum harian untuk kulit dewasa. Konsultasikan dengan tim Gloskin untuk rekomendasi kombinasi yang sesuai kondisi kulit.</p>',
			),
			'hydra-xpert-serum' => array(
				'post_excerpt' => 'Hydra Xpert Serum adalah serum hidrasi GLOSKIN yang diformulasikan untuk membantu memenuhi kebutuhan kelembapan kulit dalam rutinitas perawatan harian.',
				'post_content' => '<p>Hydra Xpert Serum adalah serum dari rangkaian GLOSKIN yang ditujukan untuk mendukung hidrasi dan kelembapan optimal kulit.</p><p>Digunakan sebagai bagian dari rutinitas serum dalam perawatan kulit. Konsultasikan dengan tim Gloskin bila membutuhkan rekomendasi kombinasi produk yang sesuai kondisi dan kebutuhan kulit Anda.</p>',
			),
			'skin-fresh-toner' => array(
				'post_excerpt' => 'Skin Fresh Toner adalah toner GLOSKIN yang menyegarkan kulit setelah pembersihan dan mempersiapkan kulit untuk menyerap produk perawatan berikutnya.',
				'post_content' => '<p>Skin Fresh Toner adalah toner dari rangkaian GLOSKIN yang dirancang untuk menyegarkan kulit setelah pembersihan dan mengoptimalkan penyerapan produk perawatan selanjutnya.</p><p>Digunakan setelah tahap pembersihan dalam rutinitas perawatan harian. Konsultasikan dengan tim Gloskin bila membutuhkan rekomendasi rangkaian produk yang sesuai.</p>',
			),
			'bio-calmskin' => array(
				'post_excerpt' => 'Bio Calmskin adalah produk penunjang GLOSKIN yang diformulasikan untuk membantu menenangkan kulit sensitif dan memberikan kenyamanan ekstra pada kulit.',
				'post_content' => '<p>Bio Calmskin adalah produk penunjang dari rangkaian GLOSKIN yang ditujukan untuk kulit yang membutuhkan perawatan calming dan meredakan respons iritasi ringan.</p><p>Digunakan sebagai bagian dari rutinitas perawatan kulit sensitif. Konsultasikan dengan tim Gloskin bila ingin mengombinasikannya dengan produk lain sesuai kondisi kulit Anda.</p>',
			),
			'essence-bio-moisturizer' => array(
				'post_excerpt' => 'Essence Bio Moisturizer adalah esens pelembap GLOSKIN yang membantu menjaga kelembapan kulit secara berkelanjutan sebagai bagian dari rutinitas perawatan harian.',
				'post_content' => '<p>Essence Bio Moisturizer adalah produk penunjang dari rangkaian GLOSKIN yang menggabungkan fungsi esens dan moisturizer untuk mendukung kelembapan kulit sepanjang hari.</p><p>Digunakan sebagai bagian dari rutinitas perawatan harian. Konsultasikan dengan tim Gloskin bila membutuhkan rekomendasi kombinasi produk yang sesuai kondisi kulit.</p>',
			),
			'sense-cleansing-milk' => array(
				'post_excerpt' => 'Sense Cleansing Milk adalah cleansing milk GLOSKIN yang membersihkan kulit secara lembut sambil menjaga kelembapan alami kulit.',
				'post_content' => '<p>Sense Cleansing Milk adalah cleansing milk dari rangkaian produk penunjang GLOSKIN yang diformulasikan untuk pembersihan lembut, cocok sebagai langkah pertama pembersihan atau sebagai pembersih mandiri.</p><p>Gunakan sesuai petunjuk penggunaan produk. Konsultasikan dengan tim Gloskin bila ingin mengintegrasikannya ke dalam rangkaian perawatan kulit yang lebih lengkap.</p>',
			),
			'ultimate-whitening-cream' => array(
				'post_excerpt' => 'Ultimate Whitening Cream adalah krim pencerah GLOSKIN yang mendukung program perawatan kulit untuk mencerahkan dan meratakan warna kulit.',
				'post_content' => '<p>Ultimate Whitening Cream adalah produk penunjang dari rangkaian GLOSKIN yang ditujukan untuk mendukung kebutuhan perawatan pencerah dan perataan warna kulit.</p><p>Digunakan sebagai bagian dari rutinitas perawatan brightening. Konsultasikan dengan tim Gloskin untuk rekomendasi kombinasi produk yang sesuai kondisi dan tujuan perawatan kulit Anda.</p>',
			),
			'transforming-night-cream' => array(
				'post_excerpt' => 'Transforming Night Cream adalah krim malam GLOSKIN yang mendukung regenerasi dan perawatan kulit selama tidur sebagai bagian dari rutinitas perawatan malam.',
				'post_content' => '<p>Transforming Night Cream adalah krim malam dari rangkaian produk penunjang GLOSKIN yang ditujukan untuk mendukung proses perawatan dan regenerasi kulit selama istirahat malam.</p><p>Digunakan sebagai langkah penutup rutinitas perawatan malam. Konsultasikan dengan tim Gloskin bila membutuhkan rekomendasi kombinasi perawatan malam yang sesuai kondisi kulit Anda.</p>',
			),
			'c-power-silk-gel' => array(
				'post_excerpt' => 'C Power Silk Gel adalah gel perawatan kulit GLOSKIN yang mendukung program brightening dan menjaga kondisi kulit dalam rutinitas harian.',
				'post_content' => '<p>C Power Silk Gel adalah produk penunjang dari rangkaian GLOSKIN yang hadir dalam tekstur gel untuk mendukung program perawatan brightening dan penjagaan kulit sehari-hari.</p><p>Digunakan sebagai bagian dari rutinitas perawatan harian. Konsultasikan dengan tim Gloskin bila ingin mengombinasikan produk ini dengan rangkaian perawatan yang lebih lengkap.</p>',
			),
			'acne-prone-gel' => array(
				'post_excerpt' => 'Acne Prone Gel adalah gel perawatan GLOSKIN berformula ringan yang diformulasikan untuk membantu merawat kulit bertipe acne-prone sehari-hari.',
				'post_content' => '<p>Acne Prone Gel adalah produk penunjang dari rangkaian GLOSKIN yang hadir dalam tekstur gel ringan, ditujukan untuk membantu merawat dan menjaga kulit yang rentan berjerawat.</p><p>Digunakan sebagai bagian dari rutinitas perawatan kulit berjerawat. Konsultasikan dengan tim Gloskin bila membutuhkan rekomendasi rangkaian yang tepat untuk kondisi jerawat Anda.</p>',
			),
			'cysteamine-advance-plus' => array(
				'post_excerpt' => 'Cysteamine Advance Plus adalah produk perawatan GLOSKIN yang ditujukan untuk membantu menangani hiperpigmentasi dan meratakan warna kulit.',
				'post_content' => '<p>Cysteamine Advance Plus adalah produk penunjang dari rangkaian GLOSKIN yang ditujukan untuk membantu menangani kebutuhan perawatan hiperpigmentasi dan perataan warna kulit.</p><p>Digunakan sebagai bagian dari program perawatan brightening atau anti-pigmentasi. Konsultasikan dengan tim Gloskin untuk rekomendasi penggunaan yang tepat sesuai kondisi kulit Anda.</p>',
			),
			'glam-air-cushion' => array(
				'post_excerpt' => 'Glam Air Cushion adalah cushion GLOSKIN yang memadukan tampilan riasan natural dengan manfaat perawatan kulit untuk penggunaan harian.',
				'post_content' => '<p>Glam Air Cushion adalah produk penunjang dari rangkaian GLOSKIN yang hadir dalam format cushion praktis untuk penggunaan harian dengan hasil tampilan natural.</p><p>Digunakan sebagai bagian dari rutinitas makeup dan perawatan. Konsultasikan dengan tim Gloskin bila ingin mengintegrasikan produk ini ke dalam program perawatan kulit yang lebih lengkap.</p>',
			),
			'gloskin-glow-face-tonic' => array(
				'post_excerpt' => 'Gloskin Glow Face Tonic adalah face tonic GLOSKIN yang membantu mencerahkan dan menyegarkan kulit sebagai bagian dari rutinitas perawatan harian.',
				'post_content' => '<p>Gloskin Glow Face Tonic adalah produk penunjang dari rangkaian GLOSKIN yang diformulasikan untuk memberikan manfaat pencerah dan menyegarkan pada kulit melalui penggunaan rutin.</p><p>Digunakan sebagai bagian dari rutinitas perawatan harian. Konsultasikan dengan tim Gloskin bila ingin mengombinasikan dengan produk lain dalam rangkaian brightening.</p>',
			),
		);
	}

	/**
	 * Per-product copy for canonical Treatment products with prepared research evidence.
	 * Products not in this map fall through to the concern-based conservative fallback.
	 *
	 * Source: docs/client-feedback-phase-4/product-content-research.md
	 *
	 * @return array<string,array{post_excerpt:string,post_content:string}>
	 */
	public static function treatment_product_copy_map() {
		return array(
			'glowing-face-therapy' => array(
				'post_excerpt' => 'Glowing Face Therapy adalah perawatan facial GLOSKIN yang dirancang untuk membantu mencerahkan dan menyegarkan kulit melalui rangkaian prosedur facial khusus.',
				'post_content' => '<p>Glowing Face Therapy adalah perawatan facial dari rangkaian GLOSKIN yang ditujukan untuk mendukung kecerahan dan vitalitas kulit.</p><p>Kesesuaian perawatan, protokol, dan jumlah sesi ditentukan bersama tenaga medis setelah konsultasi. Informasi ini tidak menggantikan evaluasi klinis individual.</p>',
			),
			'derma-oxy-facial-therapy' => array(
				'post_excerpt' => 'Derma Oxy Facial Therapy adalah perawatan facial GLOSKIN berbasis oksigen yang membantu merevitalisasi dan menyegarkan kulit.',
				'post_content' => '<p>Derma Oxy Facial Therapy adalah perawatan facial dari rangkaian GLOSKIN yang memanfaatkan teknologi oksigen untuk membantu merevitalisasi dan memperbaiki kondisi kulit.</p><p>Kesesuaian perawatan, protokol, dan jumlah sesi ditentukan setelah konsultasi tenaga medis. Informasi ini tidak menggantikan evaluasi klinis individual.</p>',
			),
			'lymphatic-face-therapy' => array(
				'post_excerpt' => 'Lymphatic Face Therapy adalah perawatan facial GLOSKIN yang mendukung sirkulasi limfatik area wajah untuk kulit lebih segar dan tampak lebih sehat.',
				'post_content' => '<p>Lymphatic Face Therapy adalah perawatan facial dari rangkaian GLOSKIN yang diarahkan untuk mendukung sirkulasi limfatik dan meredakan pembengkakan ringan pada area wajah.</p><p>Kesesuaian perawatan dan jumlah sesi ditentukan setelah evaluasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'skin-barrier-facial-therapy' => array(
				'post_excerpt' => 'Skin Barrier Facial Therapy adalah perawatan facial GLOSKIN yang berfokus pada pemulihan dan penguatan skin barrier untuk kulit lebih sehat dan terlindungi.',
				'post_content' => '<p>Skin Barrier Facial Therapy adalah perawatan facial dari rangkaian GLOSKIN yang ditujukan untuk membantu memulihkan dan memperkuat skin barrier, khususnya bagi kulit sensitif atau yang mengalami gangguan barrier.</p><p>Kesesuaian perawatan ditentukan berdasarkan evaluasi kondisi kulit bersama tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'oxy-jet-light' => array(
				'post_excerpt' => 'Oxy Jet Light adalah perawatan GLOSKIN yang mengombinasikan infusi oksigen dan energi cahaya untuk membantu meremajakan dan mencerahkan kulit.',
				'post_content' => '<p>Oxy Jet Light adalah perawatan dari rangkaian GLOSKIN yang memanfaatkan kombinasi infusi oksigen dan energi cahaya untuk mendukung peremajaan dan kecerahan kulit.</p><p>Kesesuaian perawatan, protokol sesi, dan harapan hasil ditentukan setelah konsultasi tenaga medis. Informasi ini tidak menggantikan evaluasi klinis individual.</p>',
			),
			'hydra-glowing-luxury-facial-therapy' => array(
				'post_excerpt' => 'Hydra Glowing Luxury Facial Therapy adalah perawatan facial premium GLOSKIN yang menggabungkan hidrasi intensif dan brightening untuk pengalaman perawatan menyeluruh.',
				'post_content' => '<p>Hydra Glowing Luxury Facial Therapy adalah perawatan facial premium dari rangkaian GLOSKIN yang menawarkan kombinasi hidrasi intensif dan manfaat brightening.</p><p>Kesesuaian dan protokol perawatan ditentukan setelah konsultasi tenaga medis. Informasi ini tidak menggantikan evaluasi klinis individual.</p>',
			),
			'korean-comedo-glowing-peel' => array(
				'post_excerpt' => 'Korean Comedo Glowing Peel adalah perawatan eksfoliasi GLOSKIN untuk membantu membersihkan komedo dan memperbaiki tekstur kulit.',
				'post_content' => '<p>Korean Comedo Glowing Peel adalah perawatan peeling dari rangkaian GLOSKIN yang ditujukan untuk membantu mengatasi komedo dan memperbaiki tekstur kulit melalui proses eksfoliasi terkontrol.</p><p>Kesesuaian perawatan, protokol, dan jumlah sesi ditentukan setelah evaluasi kondisi kulit bersama tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'acne-advance-peeling' => array(
				'post_excerpt' => 'Acne Advance Peeling adalah perawatan peeling GLOSKIN yang diarahkan untuk membantu menangani kondisi jerawat aktif dan bekas jerawat.',
				'post_content' => '<p>Acne Advance Peeling adalah perawatan peeling dari rangkaian GLOSKIN yang dirancang untuk membantu mengelola kondisi jerawat dan memperbaiki tampilan bekas jerawat melalui eksfoliasi terkontrol.</p><p>Kesesuaian tindakan, jumlah sesi, dan ekspektasi hasil ditentukan setelah evaluasi kondisi kulit bersama tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'pico-laser' => array(
				'post_excerpt' => 'Pico Laser adalah perawatan laser GLOSKIN untuk membantu mengatasi masalah pigmentasi, bintik hitam, dan ketidakmerataan warna kulit.',
				'post_content' => '<p>Pico Laser adalah perawatan laser dari rangkaian GLOSKIN yang ditujukan untuk membantu mengatasi masalah pigmentasi, bintik hitam, dan memperbaiki tampilan kulit.</p><p>Kesesuaian tindakan, jumlah sesi, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'laser-4g' => array(
				'post_excerpt' => 'Laser 4G adalah perawatan laser GLOSKIN yang ditujukan untuk membantu memperbaiki tampilan kulit dan mengatasi berbagai masalah pigmentasi.',
				'post_content' => '<p>Laser 4G adalah perawatan laser dari rangkaian GLOSKIN yang ditujukan untuk membantu membahas kebutuhan perawatan kulit terkait pigmentasi dan tekstur.</p><p>Kesesuaian tindakan, protokol, dan jumlah sesi ditentukan setelah evaluasi dan konsultasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'glowing-salmon-dna' => array(
				'post_excerpt' => 'Glowing Salmon DNA adalah perawatan skin booster GLOSKIN berbasis salmon DNA untuk mendukung regenerasi kulit dan tampilan lebih cerah.',
				'post_content' => '<p>Glowing Salmon DNA adalah perawatan skin booster dari rangkaian GLOSKIN yang menggunakan bahan aktif salmon DNA untuk mendukung regenerasi dan hidrasi kulit.</p><p>Kesesuaian tindakan, jumlah sesi, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'xelarederm' => array(
				'post_excerpt' => 'Xelarederm adalah perawatan skin booster GLOSKIN untuk mendukung kelembapan, elastisitas, dan revitalisasi kulit dari dalam.',
				'post_content' => '<p>Xelarederm adalah perawatan dari rangkaian GLOSKIN berupa skin booster yang ditujukan untuk mendukung hidrasi, elastisitas, dan revitalisasi kulit.</p><p>Kesesuaian tindakan, jumlah sesi, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'profhilo' => array(
				'post_excerpt' => 'Profhilo adalah perawatan injectable GLOSKIN untuk mendukung kualitas, hidrasi, dan tampilan keremajaan kulit melalui prosedur skin booster.',
				'post_content' => '<p>Profhilo adalah perawatan injectable dari rangkaian GLOSKIN yang ditujukan untuk mendukung bio-remodeling dan hidrasi kulit, membantu tampilan kulit lebih kenyal dan segar.</p><p>Kesesuaian tindakan, jumlah sesi, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'rejuran-hb' => array(
				'post_excerpt' => 'Rejuran HB adalah perawatan skin booster GLOSKIN untuk mendukung regenerasi kulit dan memperbaiki kualitas serta tampilan kulit.',
				'post_content' => '<p>Rejuran HB adalah perawatan dari rangkaian GLOSKIN yang ditujukan untuk mendukung regenerasi dan pemulihan kulit melalui prosedur skin booster.</p><p>Kesesuaian tindakan, jumlah sesi, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'croma-rich' => array(
				'post_excerpt' => 'Croma Rich adalah perawatan injectable GLOSKIN untuk membantu meningkatkan hidrasi, elastisitas, dan kualitas kulit dari dalam.',
				'post_content' => '<p>Croma Rich adalah perawatan injectable dari rangkaian GLOSKIN yang ditujukan untuk mendukung hidrasi mendalam dan perbaikan kualitas kulit.</p><p>Kesesuaian tindakan, jumlah sesi, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'premium-prp' => array(
				'post_excerpt' => 'Premium PRP adalah perawatan GLOSKIN berbasis platelet-rich plasma yang memanfaatkan faktor pertumbuhan alami tubuh untuk mendukung regenerasi kulit.',
				'post_content' => '<p>Premium PRP adalah perawatan dari rangkaian GLOSKIN yang menggunakan plasma kaya platelet — diambil dari darah pasien sendiri — untuk mendukung regenerasi dan peremajaan kulit secara alami.</p><p>Kesesuaian tindakan, protokol, dan jumlah sesi ditentukan setelah evaluasi dan konsultasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'juvelook' => array(
				'post_excerpt' => 'Juvelook adalah perawatan injectable GLOSKIN untuk mendukung regenerasi kulit dan memperbaiki volume serta kualitas kulit.',
				'post_content' => '<p>Juvelook adalah perawatan injectable dari rangkaian GLOSKIN yang ditujukan untuk mendukung regenerasi dan kualitas kulit melalui prosedur skin booster.</p><p>Kesesuaian tindakan, jumlah sesi, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'sylfirm-x' => array(
				'post_excerpt' => 'Sylfirm X adalah perawatan GLOSKIN yang membantu mengatasi masalah vaskular, hiperpigmentasi, dan kondisi kulit tertentu melalui energi radiofrequency.',
				'post_content' => '<p>Sylfirm X adalah perawatan dari rangkaian GLOSKIN yang menggunakan energi radiofrequency untuk membantu menangani masalah vaskular, pigmentasi, dan memperbaiki kondisi kulit.</p><p>Kesesuaian tindakan, protokol, dan jumlah sesi ditentukan setelah evaluasi dan konsultasi tenaga medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'botox' => array(
				'post_excerpt' => 'Botox adalah prosedur injectable GLOSKIN untuk membantu mengurangi tampilan garis halus dan kerutan melalui relaksasi otot wajah. Kesesuaian tindakan ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>Botox adalah prosedur injectable dari rangkaian GLOSKIN yang ditujukan untuk membantu mengurangi tampilan garis halus dan kerutan dengan merelaksasi otot wajah untuk sementara.</p><p>Prosedur ini dilakukan oleh dokter. Kesesuaian tindakan dan area injeksi ditentukan setelah evaluasi dan konsultasi medis. Hasilnya bersifat sementara dan informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'juvederm' => array(
				'post_excerpt' => 'Juvederm adalah perawatan filler GLOSKIN untuk membantu menambah volume dan membentuk kontur wajah. Kesesuaian tindakan ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>Juvederm adalah perawatan filler dari rangkaian Face Contour GLOSKIN yang ditujukan untuk membantu menambah volume dan membentuk kontur wajah sesuai kebutuhan individual.</p><p>Prosedur ini dilakukan oleh dokter. Kesesuaian tindakan, area, dan jumlah sesi ditentukan setelah evaluasi dan konsultasi medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'thread-lift' => array(
				'post_excerpt' => 'Thread Lift adalah prosedur GLOSKIN untuk membantu mengencangkan jaringan wajah menggunakan benang yang dapat terserap. Kesesuaian ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>Thread Lift adalah prosedur dari rangkaian GLOSKIN yang menggunakan benang dapat terserap untuk membantu mengencangkan jaringan wajah dan memberikan efek pengangkatan.</p><p>Prosedur ini dilakukan oleh dokter. Kesesuaian tindakan, jenis benang, dan area ditentukan setelah evaluasi dan konsultasi medis. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'hollywood-face-sculpting' => array(
				'post_excerpt' => 'Hollywood Face Sculpting adalah prosedur GLOSKIN untuk membantu membentuk kontur dan memperbaiki proporsi wajah. Kesesuaian ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>Hollywood Face Sculpting adalah prosedur sculpting wajah dari rangkaian GLOSKIN yang ditujukan untuk membantu membentuk kontur dan memperbaiki keseimbangan proporsi wajah.</p><p>Jenis prosedur, protokol, dan kesesuaian tindakan ditentukan setelah evaluasi dan konsultasi dokter. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'ultralift' => array(
				'post_excerpt' => 'Ultralift adalah perawatan non-bedah GLOSKIN untuk membantu mengencangkan dan mengangkat kulit wajah. Kesesuaian ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>Ultralift adalah perawatan non-bedah dari rangkaian GLOSKIN yang menggunakan energi untuk membantu mengencangkan jaringan dan memberikan efek pengangkatan pada kulit wajah.</p><p>Kesesuaian tindakan, protokol, dan jumlah sesi ditentukan setelah evaluasi dan konsultasi dokter. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'hair-transplant' => array(
				'post_excerpt' => 'Hair Transplant adalah prosedur GLOSKIN untuk memindahkan folikel rambut ke area yang mengalami kerontokan. Kesesuaian kandidat dan prosedur ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>Hair Transplant adalah prosedur dari rangkaian perawatan rambut GLOSKIN yang ditujukan untuk memindahkan folikel rambut ke area yang mengalami penipisan atau kerontokan permanen.</p><p>Kandidat yang sesuai, teknik, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi dokter. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'prp-hair' => array(
				'post_excerpt' => 'PRP Hair adalah perawatan rambut GLOSKIN berbasis platelet-rich plasma untuk mendukung kesehatan rambut dan mengurangi kerontokan. Kesesuaian ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>PRP Hair adalah perawatan rambut dari rangkaian GLOSKIN yang menggunakan plasma kaya platelet dari darah pasien sendiri untuk mendukung kesehatan folikel rambut dan mengurangi kerontokan.</p><p>Kesesuaian tindakan, protokol, dan jumlah sesi ditentukan setelah evaluasi dan konsultasi dokter. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'buccal-contour' => array(
				'post_excerpt' => 'Buccal Contour adalah prosedur GLOSKIN untuk membantu membentuk dan mempertajam kontur wajah bagian bawah. Kesesuaian kandidat ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>Buccal Contour adalah prosedur dari rangkaian GLOSKIN yang ditujukan untuk membantu membentuk dan mempertegas kontur area pipi dan rahang.</p><p>Kesesuaian kandidat, teknik, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi dokter. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'eyebag-removal' => array(
				'post_excerpt' => 'Eyebag Removal adalah prosedur GLOSKIN untuk membantu mengurangi atau menghilangkan kantong mata yang mengganggu. Kesesuaian kandidat ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>Eyebag Removal adalah prosedur dari rangkaian GLOSKIN yang ditujukan untuk membantu mengurangi atau menghilangkan kantong mata melalui pendekatan yang disesuaikan dengan kondisi pasien.</p><p>Kesesuaian kandidat, teknik, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi dokter. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'upper-lower-eyelid' => array(
				'post_excerpt' => 'Upper/Lower Eyelid adalah prosedur GLOSKIN untuk memperbaiki tampilan kelopak mata atas dan/atau bawah. Kesesuaian kandidat dan prosedur ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>Upper/Lower Eyelid adalah prosedur dari rangkaian GLOSKIN yang ditujukan untuk membantu memperbaiki tampilan kelopak mata atas dan/atau bawah melalui pendekatan yang disesuaikan dengan kondisi pasien.</p><p>Kesesuaian kandidat, teknik, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi dokter. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'lipoma-removal' => array(
				'post_excerpt' => 'Lipoma Removal adalah prosedur GLOSKIN untuk mengangkat lipoma (benjolan lemak jinak) yang berada di bawah kulit. Kesesuaian tindakan ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>Lipoma Removal adalah prosedur dari rangkaian GLOSKIN yang ditujukan untuk mengangkat lipoma — benjolan lemak jinak — yang berada di bawah kulit.</p><p>Kesesuaian kandidat, teknik, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi dokter. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
			'smas-lift' => array(
				'post_excerpt' => 'SMAS Lift adalah prosedur facelift GLOSKIN untuk membantu mengencangkan dan meremajakan jaringan wajah secara menyeluruh. Kesesuaian kandidat ditentukan dokter setelah evaluasi.',
				'post_content' => '<p>SMAS Lift adalah prosedur facelift dari rangkaian GLOSKIN yang ditujukan untuk membantu mengencangkan dan meremajakan jaringan wajah melalui pendekatan bedah pada lapisan SMAS.</p><p>Kesesuaian kandidat, teknik, dan ekspektasi hasil ditentukan setelah evaluasi dan konsultasi dokter. Informasi ini tidak menggantikan penilaian klinis individual.</p>',
			),
		);
	}
}
