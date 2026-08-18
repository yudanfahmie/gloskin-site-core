<?php
/**
 * Gloskin plugin lifecycle owner.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Lifecycle_Service {
	const BASE_SCHEMA_VERSION = '0.2.2';
	const SCHEMA_VERSION      = '0.3.0';
	const VERSION_OPTION      = 'gloskin_site_core_schema_version';

	/**
	 * Register narrowly scoped upgrades for already-active installs.
	 *
	 * Schema 0.3.0 is intentionally completed by the bounded prototype IA
	 * migration runner. admin_init never continuously repairs the primary menu.
	 *
	 * @return void
	 */
	public function register_upgrade() {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ), 5 );
	}

	/** @return void */
	public function maybe_upgrade() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current = (string) get_option( self::VERSION_OPTION, '' );
		if ( '' === $current || version_compare( $current, self::BASE_SCHEMA_VERSION, '<' ) ) {
			$this->provision_approved_structure();
			update_option( self::VERSION_OPTION, self::BASE_SCHEMA_VERSION, false );
			flush_rewrite_rules( false );
			$current = self::BASE_SCHEMA_VERSION;
		}

		if ( version_compare( $current, self::SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		/*
		 * Do not auto-run schema 0.3.0 here. The client-approved IA revision
		 * changes a real editor-visible primary menu and provisions the new
		 * native Promo page, so one bounded admin workflow owns that mutation.
		 * Its consumed checkpoint writes SCHEMA_VERSION exactly once after
		 * page/menu/Woo safety verification.
		 */
	}

	/**
	 * Register rewrites, populate the pre-revision safe baseline structure and flush once.
	 *
	 * The 2026-08-18 IA Page/menu migration remains explicitly pending after activation
	 * so the same one-shot engine owns both upgraded and newly activated installs.
	 *
	 * @return void
	 */
	public function activate() {
		Gloskin_Site_Core_Content_Service::register_content_types();
		/* register_content_types() only registers post types. The family
		 * taxonomy ensure_family_terms() depends on below must also be
		 * registered here explicitly -- the normal init hook has not
		 * fired yet on this static register_activation_hook path. */
		Gloskin_Site_Core_Content_Service::register_taxonomies();
		$this->provision_approved_structure();
		update_option( self::VERSION_OPTION, self::BASE_SCHEMA_VERSION, false );
		flush_rewrite_rules( false );
	}

	/** @return void */
	public function deactivate() {
		flush_rewrite_rules( false );
	}

	/**
	 * Create only factual/structural records normalized in the pre-revision baseline.
	 * Existing editor content is never overwritten. The new `/promo/` Page is
	 * deliberately NOT owned here; Prototype_IA_Migration owns that revision.
	 *
	 * @return void
	 */
	private function provision_approved_structure() {
		$pages = array(
			'home'       => 'Beranda',
			'about'      => 'Tentang Gloskin',
			'treatments' => 'Perawatan',
			'skincare'   => 'Skincare',
			'clinics'    => 'Klinik',
			'contact'    => 'Kontak',
			'insights'   => 'Insight',
			'shop'       => 'Belanja',
			'doctors'    => 'Dokter',
		);

		$page_ids = array();
		foreach ( $pages as $slug => $title ) {
			$page_ids[ $slug ] = $this->ensure_page( $slug, $title, 0 );
		}

		$this->align_woo_shop_page( isset( $page_ids['shop'] ) ? absint( $page_ids['shop'] ) : 0 );

		$skincare_parent = isset( $page_ids['skincare'] ) ? absint( $page_ids['skincare'] ) : 0;
		foreach ( Gloskin_Site_Core_Content_Service::skincare_definitions() as $slug => $title ) {
			$page_id = $this->ensure_page( $slug, $title, $skincare_parent );
			if ( $page_id && '' === (string) get_post_meta( $page_id, 'gloskin_woo_category_slug', true ) ) {
				update_post_meta( $page_id, 'gloskin_woo_category_slug', $slug );
			}
		}

		foreach ( Gloskin_Site_Core_Content_Service::clinic_definitions() as $slug => $title ) {
			$this->ensure_post( Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE, $slug, $title );
		}

		Gloskin_Site_Core_Content_Service::ensure_family_terms();
	}

	/**
	 * WooCommerce, not Gloskin, decides which page is "the Shop page" via
	 * the woocommerce_shop_page_id option. If that setting is genuinely
	 * unconfigured (empty, the -1 "no page" sentinel, or points at a page
	 * that no longer exists/is trashed), safely point it at Gloskin's own
	 * provisioned /shop/ page so the two stay aligned. If a merchant has
	 * already configured a valid Shop page -- Gloskin's own or a different
	 * one -- that choice is preserved untouched.
	 *
	 * @param int $shop_page_id Gloskin's own provisioned /shop/ page ID.
	 * @return void
	 */
	private function align_woo_shop_page( $shop_page_id ) {
		if ( ! $shop_page_id || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$configured_id = (int) get_option( 'woocommerce_shop_page_id', 0 );
		if ( $configured_id > 0 ) {
			$configured_page = get_post( $configured_id );
			if ( $configured_page instanceof WP_Post && 'trash' !== $configured_page->post_status ) {
				return;
			}
		}

		update_option( 'woocommerce_shop_page_id', $shop_page_id );
	}

	/**
	 * @param string $slug Page slug.
	 * @param string $title Page title.
	 * @param int    $parent_id Parent page ID.
	 * @return int
	 */
	private function ensure_page( $slug, $title, $parent_id ) {
		$path = $parent_id ? get_page_uri( $parent_id ) . '/' . $slug : $slug;
		$page = get_page_by_path( $path, OBJECT, 'page' );
		if ( $page instanceof WP_Post && 'trash' !== $page->post_status ) {
			return (int) $page->ID;
		}

		$result = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_parent' => $parent_id,
			),
			true
		);

		return is_wp_error( $result ) ? 0 : absint( $result );
	}

	/**
	 * @param string $post_type Post type.
	 * @param string $slug Post slug.
	 * @param string $title Post title.
	 * @return int
	 */
	private function ensure_post( $post_type, $slug, $title ) {
		$post = get_page_by_path( $slug, OBJECT, $post_type );
		if ( $post instanceof WP_Post ) {
			return (int) $post->ID;
		}

		$result = wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
			),
			true
		);

		return is_wp_error( $result ) ? 0 : absint( $result );
	}
}
