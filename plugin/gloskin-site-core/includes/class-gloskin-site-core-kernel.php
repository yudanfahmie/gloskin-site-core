<?php
/**
 * Gloskin Site Core composition root.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Kernel {
	const VERSION = '0.7.225';

	/** @var string */
	private $plugin_file;

	/** @var array<int, object> */
	private $services = array();

	/** @param string $plugin_file Main plugin file. */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	public function boot() {
		$this->load_shared_classes();

		$content = new Gloskin_Site_Core_Content_Service();
		$content->register();
		$this->services[] = $content;

		if ( is_admin() ) {
			$assets = new Gloskin_Site_Core_Asset_Service( $this->plugin_file, self::VERSION );
			$assets->register();
			$this->services[] = $assets;

			$admin_menu = new Gloskin_Site_Core_Admin_Menu();
			$admin_menu->register();
			$this->services[] = $admin_menu;

			$editorial = new Gloskin_Site_Core_Editorial_Manager();
			$editorial->register();
			$this->services[] = $editorial;

			$consultation_admin = new Gloskin_Site_Core_Consultation_Admin();
			$consultation_admin->register();
			$this->services[] = $consultation_admin;

			$translation = new Gloskin_Site_Core_Translation();
			$translation->register_admin();
			$this->services[] = $translation;

			$media_cleanup = new Gloskin_Site_Core_Media_Cleanup_Admin();
			$media_cleanup->register();
			$this->services[] = $media_cleanup;

			$finalizer = new Gloskin_Site_Core_Content_Finalizer_Admin();
			$finalizer->register();
			$this->services[] = $finalizer;

			$language_projection = new Gloskin_Site_Core_Language_Projection();
			$language_projection->register_admin();
			$this->services[] = $language_projection;
		}

		$woo = new Gloskin_Site_Core_WooCommerce_Adapter();
		$woo->register();
		$this->services[] = $woo;

		$form = new Gloskin_Site_Core_Form_Adapter();
		$form->register();
		$this->services[] = $form;

		$language = new Gloskin_Site_Core_Language();
		$language->register_frontend();
		$this->services[] = $language;

		$language_projection = isset( $language_projection ) ? $language_projection : new Gloskin_Site_Core_Language_Projection();
		$language_projection->register();
		$this->services[] = $language_projection;

		$template = new Gloskin_Site_Core_Template_Service( $woo, $form );
		$template->register();
		$this->services[] = $template;

		$assets = isset( $assets ) ? $assets : new Gloskin_Site_Core_Asset_Service( $this->plugin_file, self::VERSION, array( $template, 'is_commerce_presentation_request' ) );
		if ( ! is_admin() ) {
			$assets->register();
		}
		$this->services[] = $assets;
	}

	private function load_shared_classes() {
		require_once __DIR__ . '/class-gloskin-site-core-content-service.php';
		require_once __DIR__ . '/class-gloskin-site-core-page-lookup.php';
		require_once __DIR__ . '/class-gloskin-site-core-template-service.php';
		require_once __DIR__ . '/class-gloskin-site-core-woocommerce-adapter.php';
		require_once __DIR__ . '/class-gloskin-site-core-form-adapter.php';
		require_once __DIR__ . '/class-gloskin-site-core-language.php';
		require_once __DIR__ . '/class-gloskin-site-core-language-projection.php';
		require_once __DIR__ . '/class-gloskin-site-core-translation.php';
		require_once __DIR__ . '/class-gloskin-site-core-asset-service.php';
		require_once __DIR__ . '/class-gloskin-site-core-admin-menu.php';
		require_once __DIR__ . '/class-gloskin-site-core-editorial-manager.php';
		require_once __DIR__ . '/class-gloskin-site-core-consultation-admin.php';
		require_once __DIR__ . '/class-gloskin-site-core-media-cleanup-admin.php';
		require_once __DIR__ . '/class-gloskin-site-core-content-finalizer-admin.php';
	}
}
