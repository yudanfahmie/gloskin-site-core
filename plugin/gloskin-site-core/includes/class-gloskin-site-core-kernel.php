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
	const VERSION = '0.7.198';

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

			require_once __DIR__ . '/class-gloskin-site-core-admin-service.php';
			require_once __DIR__ . '/class-gloskin-site-core-admin-navigation-service.php';
			require_once __DIR__ . '/class-gloskin-site-core-lifecycle-service.php';
			require_once __DIR__ . '/class-gloskin-site-core-sample-media-compatibility.php';
			require_once __DIR__ . '/class-gloskin-site-core-media-cleanup-admin.php';
			require_once __DIR__ . '/class-gloskin-site-core-content-finalizer-admin.php';

			$media_compatibility = new Gloskin_Site_Core_Sample_Media_Compatibility();
			$media_compatibility->register();

			$admin = new Gloskin_Site_Core_Admin_Service( $content, $assets, $this->plugin_file );
			$admin->register();

			$admin_navigation = new Gloskin_Site_Core_Admin_Navigation_Service();
			$admin_navigation->register();

			$translation = new Gloskin_Site_Core_Translation( $this->plugin_file, self::VERSION );
			$translation->register_admin();

			$lifecycle = new Gloskin_Site_Core_Lifecycle_Service();
			$lifecycle->register_upgrade();
			$lifecycle->register_historical_upgrade_admins( $assets, $this->plugin_file );

			$media_cleanup = new Gloskin_Site_Core_Media_Cleanup_Admin( $assets );
			$media_cleanup->register();

			$content_finalizer = new Gloskin_Site_Core_Content_Finalizer_Admin();
			$content_finalizer->register();

			/* Contact admin (inlined from ProductionBatch). */
			require_once __DIR__ . '/class-gloskin-site-core-contact-mailer.php';
			foreach ( array( 'bootstrap', 'settings', 'form', 'submit', 'security', 'persist', 'mail' ) as $_gsk_part ) {
				require_once __DIR__ . '/gloskin-site-core-contact-service-' . $_gsk_part . '-trait.php';
			}
			require_once __DIR__ . '/class-gloskin-site-core-contact-service.php';
			$contact = new Gloskin_Site_Core_Contact_Service( $this->plugin_file );
			$contact->register();
			foreach ( array( 'setup', 'render', 'settings-actions', 'test', 'inbox-list', 'inbox-actions', 'readiness' ) as $_gsk_part ) {
				require_once __DIR__ . '/gloskin-site-core-contact-admin-' . $_gsk_part . '-trait.php';
			}
			require_once __DIR__ . '/class-gloskin-site-core-contact-admin.php';
			require_once __DIR__ . '/class-gloskin-site-core-doctor-bundle.php';
			foreach ( array( 'state', 'upsert', 'finalize', 'lock' ) as $_gsk_part ) {
				require_once __DIR__ . '/gloskin-site-core-doctor-importer-' . $_gsk_part . '-trait.php';
			}
			require_once __DIR__ . '/class-gloskin-site-core-doctor-importer.php';
			$contact_admin = new Gloskin_Site_Core_Contact_Admin( $this->plugin_file );
			$contact_admin->register();
			unset( $_gsk_part );

			$this->services[] = $media_compatibility;
			$this->services[] = $admin;
			$this->services[] = $admin_navigation;
			$this->services[] = $translation;
			$this->services[] = $lifecycle;
			$this->services[] = $media_cleanup;
			$this->services[] = $content_finalizer;
			$this->services[] = $contact;
			$this->services[] = $contact_admin;
			return;
		}

		$language = new Gloskin_Site_Core_Language( $this->plugin_file );
		$language->register_frontend();
		$language_projection = new Gloskin_Site_Core_Language_Projection();
		$language_projection->register();
		$this->services[] = $language;
		$this->services[] = $language_projection;

		require_once __DIR__ . '/class-gloskin-site-core-navigation-service.php';
		require_once __DIR__ . '/class-gloskin-site-core-woocommerce-adapter.php';
		require_once __DIR__ . '/class-gloskin-site-core-form-adapter.php';
		require_once __DIR__ . '/class-gloskin-site-core-template-service.php';

		$navigation = new Gloskin_Site_Core_Navigation_Service();
		$navigation->register();

		$woocommerce = new Gloskin_Site_Core_WooCommerce_Adapter();
		$woocommerce->register();

		$form = new Gloskin_Site_Core_Form_Adapter();

		$templates = new Gloskin_Site_Core_Template_Service(
			dirname( __DIR__ ),
			$navigation,
			$woocommerce,
			$form
		);
		$templates->register();

		$assets = new Gloskin_Site_Core_Asset_Service(
			$this->plugin_file,
			self::VERSION,
			array( $woocommerce, 'is_commerce_request' )
		);
		$assets->register();

		/* Contact service and Shop discovery (inlined from ProductionBatch). */
		require_once __DIR__ . '/class-gloskin-site-core-contact-mailer.php';
		foreach ( array( 'bootstrap', 'settings', 'form', 'submit', 'security', 'persist', 'mail' ) as $_gsk_part ) {
			require_once __DIR__ . '/gloskin-site-core-contact-service-' . $_gsk_part . '-trait.php';
		}
		require_once __DIR__ . '/class-gloskin-site-core-contact-service.php';
		$contact = new Gloskin_Site_Core_Contact_Service( $this->plugin_file );
		$contact->register();

		require_once __DIR__ . '/class-gloskin-site-core-woocommerce-adapter-shop-catalog.php';
		foreach ( array( 'route', 'rest', 'query' ) as $_gsk_part ) {
			require_once __DIR__ . '/gloskin-site-core-shop-discovery-' . $_gsk_part . '-trait.php';
		}
		require_once __DIR__ . '/class-gloskin-site-core-shop-discovery.php';
		$shop = new Gloskin_Site_Core_Shop_Discovery( $this->plugin_file );
		$shop->register();
		unset( $_gsk_part );

		$this->services[] = $navigation;
		$this->services[] = $assets;
		$this->services[] = $woocommerce;
		$this->services[] = $form;
		$this->services[] = $templates;
		$this->services[] = $contact;
		$this->services[] = $shop;
	}

	private function load_shared_classes() {
		require_once __DIR__ . '/class-gloskin-site-core-content-service.php';
		require_once __DIR__ . '/class-gloskin-site-core-asset-service.php';
		require_once __DIR__ . '/class-gloskin-site-core-page-lookup.php';
		require_once __DIR__ . '/class-gloskin-site-core-translation.php';
		require_once __DIR__ . '/class-gloskin-site-core-language.php';
		require_once __DIR__ . '/class-gloskin-site-core-language-projection.php';
	}

	public static function activate() {
		require_once __DIR__ . '/class-gloskin-site-core-content-service.php';
		require_once __DIR__ . '/class-gloskin-site-core-lifecycle-service.php';
		$lifecycle = new Gloskin_Site_Core_Lifecycle_Service();
		$lifecycle->activate();
	}

	public static function deactivate() {
		require_once __DIR__ . '/class-gloskin-site-core-lifecycle-service.php';
		$lifecycle = new Gloskin_Site_Core_Lifecycle_Service();
		$lifecycle->deactivate();
	}
}