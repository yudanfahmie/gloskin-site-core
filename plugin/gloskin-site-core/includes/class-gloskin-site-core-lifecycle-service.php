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
	const SCHEMA_VERSION = '0.2.0';
	const VERSION_OPTION = 'gloskin_site_core_schema_version';

	/**
	 * Register a narrowly scoped version upgrade for already-active installs.
	 *
	 * @return void
	 */
	public function register_upgrade() {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ), 5 );
	}

	/**
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::SCHEMA_VERSION === (string) get_option( self::VERSION_OPTION, '' ) ) {
			return;
		}

		$this->provision_approved_structure();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
		flush_rewrite_rules( false );
	}

	/**
	 * Register rewrites, populate approved structural content and flush once.
	 *
	 * @return void
	 */
	public function activate() {
		Gloskin_Site_Core_Content_Service::register_content_types();
		$this->provision_approved_structure();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
		flush_rewrite_rules( false );
	}

	/**
	 * @return void
	 */
	public function deactivate() {
		flush_rewrite_rules( false );
	}

	/**
	 * Create only factual records normalized in canonical Gloskin docs.
	 * Existing editor content is never overwritten.
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
		if ( $page instanceof WP_Post ) {
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
