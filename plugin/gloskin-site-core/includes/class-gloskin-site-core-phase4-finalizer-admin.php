<?php
/**
 * Permanent Phase 4 presentation/content resolver.
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

final class Gloskin_Site_Core_Phase4_Finalizer_Admin {
	const SLUG                 = 'gloskin-phase4-finalizer';
	const POST_ACTION          = 'gloskin_phase4_finalize';
	const NONCE_KEY            = 'gloskin_phase4_finalize_nonce';
	const CAPABILITY           = 'manage_options';
	const STATE_OPTION         = 'gloskin_site_core_phase4_finalizer_v1_state';
	const IDENTITY_META        = '_gloskin_phase4_identity';
	const PLACEHOLDER_META     = '_gloskin_phase4_placeholder';
	const ASSET_META           = '_gloskin_phase4_asset_key';
	const CONTENT_SOURCE_META  = '_gloskin_phase4_content_source';
	const CONTENT_VERSION_META = '_gloskin_phase4_content_version';
	const CONTENT_SOURCE       = 'product-content-research.md:conservative-fallback';
	const CONTENT_VERSION      = '2026-08-21.1';

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
			'Finalisasi Phase 4',
			'Finalisasi Phase 4',
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
			<h1>Finalisasi Phase 4</h1>
			<p>Resolver satu kali untuk konten produk canonical, kategori WooCommerce, media Promo/Piagam, dan cleanup legacy/demo ke Trash. Tidak ada hard delete post atau penghapusan Media Library.</p>
			<p><strong>Status:</strong> <code><?php echo esc_html( $status ); ?></code></p>
			<?php if ( 'complete' === $status ) : ?>
				<div class="notice notice-success inline"><p>Phase 4 sudah lengkap. Run berikutnya adalah no-op.</p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>">
					<?php wp_nonce_field( self::POST_ACTION, self::NONCE_KEY ); ?>
					<button type="submit" class="button button-primary">Jalankan Finalisasi Phase 4</button>
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

	/** @param array<string,mixed> $canonical Canonical IDs. @return array<string,int> */
	private function reconcile_product_content( $canonical ) {
		foreach ( $canonical['skincare'] as $slug => $product_id ) {
			$copy = $this->skincare_copy( $product_id, self::skincare_category_map()[ $slug ] );
			$this->persist_product_copy( $product_id, $copy );
		}
		foreach ( $canonical['treatment'] as $product_id ) {
			$copy = $this->treatment_copy( $product_id );
			$this->persist_product_copy( $product_id, $copy );
		}
		return $this->verify_product_content( $canonical );
	}

	/** @param int $product_id Product ID. @param string $category_slug Canonical category. @return array<string,string> */
	private function skincare_copy( $product_id, $category_slug ) {
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

	/** @param int $product_id Product ID. @return array<string,string> */
	private function treatment_copy( $product_id ) {
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
			throw new RuntimeException( 'Phase 4 canonical product description verification failed.' );
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
			throw new RuntimeException( 'Phase 4 Woo product_cat verification failed.' );
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
				throw new RuntimeException( 'Existing Phase 4 artwork attachment is unusable: ' . $identity );
			}
			return $attachment_id;
		}

		$path = dirname( __DIR__ ) . '/assets/images/phase4/' . basename( $filename );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new RuntimeException( 'Committed Phase 4 artwork is missing: ' . $filename );
		}
		$bytes = file_get_contents( $path );
		if ( false === $bytes || '' === $bytes ) {
			throw new RuntimeException( 'Committed Phase 4 artwork cannot be read: ' . $filename );
		}
		$upload = wp_upload_bits( sanitize_file_name( 'gloskin-' . $identity . '.png' ), null, $bytes );
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			throw new RuntimeException( 'Unable to write Phase 4 artwork into Media Library.' );
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
			throw new RuntimeException( 'Unable to create Phase 4 artwork attachment.' );
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
			throw new RuntimeException( 'New Phase 4 artwork attachment is unusable.' );
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
			throw new RuntimeException( 'Unable to create Phase 4 replacement post: ' . $identity );
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
			throw new RuntimeException( 'Phase 4 replacement cardinality mismatch.' );
		}
		foreach ( array_merge( $promo_ids, $piagam_ids ) as $post_id ) {
			if ( 'publish' !== get_post_status( $post_id ) || ! $this->attachment_is_usable_image( get_post_thumbnail_id( $post_id ) ) ) {
				throw new RuntimeException( 'Phase 4 replacement readiness verification failed.' );
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
			throw new RuntimeException( 'Phase 4 replacement record verification failed.' );
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
}