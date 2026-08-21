<?php
/**
 * Gloskin wp-admin navigation presentation only.
 *
 * Data/admin feature owners continue to register their own screens. This
 * service runs after those registrations to give the Gloskin-owned screens one
 * stable parent, icon and deterministic submenu order without duplicating any
 * business handler or storage.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Admin_Navigation_Service {
	const MENU_PRIORITY = 999;

	/** @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'normalize_menu' ), self::MENU_PRIORITY );
		add_filter( 'parent_file', array( $this, 'parent_file' ) );
		add_filter( 'submenu_file', array( $this, 'submenu_file' ) );
	}

	/** @return void */
	public function normalize_menu() {
		$this->apply_gloskin_icon();
		$this->reparent_consultation();
		$this->order_gloskin_submenu();
	}

	/** @return void */
	private function apply_gloskin_icon() {
		global $menu;
		if ( ! is_array( $menu ) ) {
			return;
		}
		$slug = Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG;
		foreach ( $menu as $index => $item ) {
			if ( ! isset( $item[2] ) || $slug !== $item[2] ) {
				continue;
			}
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M10 2a8 8 0 1 0 6.1 13.2v-5.1H10v2.4h3.7v1.6A5.5 5.5 0 1 1 15 8.1l2.2-1A8 8 0 0 0 10 2Z"/></svg>';
			$menu[ $index ][6] = 'data:image/svg+xml;base64,' . base64_encode( $svg );
			break;
		}
	}

	/**
	 * Re-register the existing consultation callback under Gloskin Content,
	 * then hide only its old Woo Products menu entry. The original callback,
	 * capabilities, save handlers and legacy direct URL stay untouched.
	 *
	 * @return void
	 */
	private function reparent_consultation() {
		global $submenu;
		$old_parent = 'edit.php?post_type=product';
		$new_parent = Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG;
		$slug       = Gloskin_Site_Core_Admin_Service::CONSULTATION_SLUG;
		if ( empty( $submenu[ $old_parent ] ) || ! is_array( $submenu[ $old_parent ] ) ) {
			return;
		}

		$source = null;
		foreach ( $submenu[ $old_parent ] as $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				$source = $item;
				break;
			}
		}
		if ( ! is_array( $source ) ) {
			return;
		}

		$old_hook = function_exists( 'get_plugin_page_hookname' ) ? get_plugin_page_hookname( $slug, $old_parent ) : '';
		$callback = $this->registered_page_callback( $old_hook );
		if ( ! is_callable( $callback ) ) {
			return;
		}

		$page_title = isset( $source[3] ) ? wp_strip_all_tags( (string) $source[3] ) : __( 'Konsultasi Perawatan', 'gloskin-site-core' );
		$menu_title = isset( $source[0] ) ? (string) $source[0] : __( 'Konsultasi Perawatan', 'gloskin-site-core' );
		$capability = isset( $source[1] ) ? (string) $source[1] : Gloskin_Site_Core_Admin_Service::CONSULTATION_CAPABILITY;
		$new_hook   = add_submenu_page( $new_parent, $page_title, $menu_title, $capability, $slug, $callback );
		if ( false !== $new_hook ) {
			remove_submenu_page( $old_parent, $slug );
		}
	}

	/** @param string $hook Page hook. @return callable|null */
	private function registered_page_callback( $hook ) {
		if ( '' === $hook || empty( $GLOBALS['wp_filter'][ $hook ] ) ) {
			return null;
		}
		$wp_hook = $GLOBALS['wp_filter'][ $hook ];
		if ( ! is_object( $wp_hook ) || ! isset( $wp_hook->callbacks ) || ! is_array( $wp_hook->callbacks ) ) {
			return null;
		}
		ksort( $wp_hook->callbacks, SORT_NUMERIC );
		foreach ( $wp_hook->callbacks as $callbacks ) {
			foreach ( (array) $callbacks as $callback ) {
				if ( isset( $callback['function'] ) && is_callable( $callback['function'] ) ) {
					return $callback['function'];
				}
		}
		return null;
	}

	/** @return void */
	private function order_gloskin_submenu() {
		global $submenu;
		$parent = Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG;
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}
		$order = array(
			$parent => 0,
			'edit.php?post_type=' . Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE => 10,
			'edit.php?post_type=' . Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE => 20,
			'edit.php?post_type=' . Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE => 30,
			'edit.php?post_type=' . Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE => 40,
			'edit.php?post_type=' . Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE => 50,
			'edit.php?post_type=' . Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE => 60,
			Gloskin_Site_Core_Admin_Service::CONSULTATION_SLUG => 70,
			Gloskin_Site_Core_Translation::ADMIN_SLUG => 80,
			'gloskin-contact-inbox' => 90,
			Gloskin_Site_Core_Admin_Service::SETTINGS_SLUG => 100,
			'gloskin-content-finalizer' => 110,
			'gloskin-media-cleanup-resolver' => 120,
			Gloskin_Site_Core_Admin_Service::DIAGNOSTIC_SLUG => 130,
			Gloskin_Site_Core_Admin_Service::MIGRATION_SLUG => 140,
		);
		$decorated = array();
		foreach ( array_values( $submenu[ $parent ] ) as $index => $entry ) {
			$slug = isset( $entry[2] ) ? (string) $entry[2] : '';
			$decorated[] = array(
				'entry'  => $entry,
				'weight' => isset( $order[ $slug ] ) ? $order[ $slug ] : 1000,
				'index'  => $index,
			);
		}
		usort(
			$decorated,
			static function ( $left, $right ) {
				if ( $left['weight'] === $right['weight'] ) {
					return $left['index'] <=> $right['index'];
				}
				return $left['weight'] <=> $right['weight'];
			}
		);
		$submenu[ $parent ] = array_values( wp_list_pluck( $decorated, 'entry' ) );
	}

	/** @param string $parent_file Current parent file. @return string */
	public function parent_file( $parent_file ) {
		global $plugin_page;
		if ( Gloskin_Site_Core_Admin_Service::CONSULTATION_SLUG === $plugin_page ) {
			return Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG;
		}
		return $parent_file;
	}

	/** @param string|null $submenu_file Current submenu file. @return string|null */
	public function submenu_file( $submenu_file ) {
		global $plugin_page;
		if ( Gloskin_Site_Core_Admin_Service::CONSULTATION_SLUG === $plugin_page ) {
			return Gloskin_Site_Core_Admin_Service::CONSULTATION_SLUG;
		}
		return $submenu_file;
	}
}
