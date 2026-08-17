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
	const VERSION = '0.7.124';

	/** @var string */
	private $plugin_file;

	/** @var array<int, object> */
	private $services = array();

	/**
	 * @param string $plugin_file Main plugin file.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Register only the services needed by the current request profile.
	 *
	 * @return void
	 */
	public function boot() {
		// wp-login.php is neither the public site nor wp-admin. Do not let the
		// frontend service graph (content, WooCommerce, forms, templates, assets,
		// production batch) execute inside the authentication lifecycle.
		if ( $this->is_auth_request() ) {
			return;
		}

		$this->load_shared_classes();

		$content = new Gloskin_Site_Core_Content_Service();
		$content->register();
		$this->services[] = $content;

		if ( is_admin() ) {
			$assets = new Gloskin_Site_Core_Asset_Service( $this->plugin_file, self::VERSION );
			$assets->register();
			$this->services[] = $assets;

			require_once __DIR__ . '/class-gloskin-site-core-admin-service.php';
			require_once __DIR__ . '/class-gloskin-site-core-lifecycle-service.php';
			require_once __DIR__ . '/class-gloskin-site-core-sample-media-compatibility.php';
			require_once __DIR__ . '/class-gloskin-site-core-insight-migration-admin.php';

			$media_compatibility = new Gloskin_Site_Core_Sample_Media_Compatibility();
			$media_compatibility->register();

			$admin = new Gloskin_Site_Core_Admin_Service( $content, $assets, $this->plugin_file );
			$admin->register();

			$insight_migration = new Gloskin_Site_Core_Insight_Migration_Admin( $this->plugin_file );
			$insight_migration->register();

			$lifecycle = new Gloskin_Site_Core_Lifecycle_Service();
			$lifecycle->register_upgrade();

			$this->services[] = $media_compatibility;
			$this->services[] = $admin;
			$this->services[] = $insight_migration;
			$this->services[] = $lifecycle;
			$this->boot_production_batch();
			return;
		}

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

		$this->services[] = $navigation;
		$this->services[] = $assets;
		$this->services[] = $woocommerce;
		$this->services[] = $form;
		$this->services[] = $templates;
		$this->boot_production_batch();
	}

	/**
	 * Authentication is a separate WordPress document lifecycle. Keep this
	 * classifier dependency-free so it is safe during normal plugin loading.
	 *
	 * @return bool
	 */
	private function is_auth_request() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) && is_scalar( $_SERVER['REQUEST_URI'] )
			? (string) $_SERVER['REQUEST_URI']
			: '';
		if ( $uri === '' ) {
			return false;
		}

		$path = parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return false;
		}
		$path = '/' . ltrim( $path, '/' );
		$path = $path === '/' ? '/' : rtrim( $path, '/' );

		return basename( $path ) === 'wp-login.php'
			|| $path === '/masuk'
			|| substr( $path, -6 ) === '/masuk';
	}

	/**
	 * @return void
	 */
	private function load_shared_classes() {
		require_once __DIR__ . '/class-gloskin-site-core-content-service.php';
		require_once __DIR__ . '/class-gloskin-site-core-asset-service.php';
	}

	/**
	 * Kernel-owned bridge for the production batch added after the original
	 * service graph. Keeping this call after the existing branch registrations
	 * preserves the previous object/hook registration order.
	 *
	 * @return void
	 */
	private function boot_production_batch() {
		require_once __DIR__ . '/class-gloskin-site-core-production-batch.php';
		Gloskin_Site_Core_Production_Batch::boot( $this->plugin_file );
	}

	/**
	 * Activation entrypoint. LifecycleService owns lifecycle effects.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once __DIR__ . '/class-gloskin-site-core-content-service.php';
		require_once __DIR__ . '/class-gloskin-site-core-lifecycle-service.php';

		$lifecycle = new Gloskin_Site_Core_Lifecycle_Service();
		$lifecycle->activate();
	}

	/**
	 * Deactivation entrypoint. LifecycleService owns lifecycle effects.
	 *
	 * @return void
	 */
	public static function deactivate() {
		require_once __DIR__ . '/class-gloskin-site-core-lifecycle-service.php';

		$lifecycle = new Gloskin_Site_Core_Lifecycle_Service();
		$lifecycle->deactivate();
	}
}
