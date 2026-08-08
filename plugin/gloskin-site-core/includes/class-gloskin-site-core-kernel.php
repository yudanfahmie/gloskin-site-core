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
	const VERSION = '0.1.0';

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
	 * Register the services required by the current request profile.
	 *
	 * @return void
	 */
	public function boot() {
		require_once __DIR__ . '/class-gloskin-site-core-content-service.php';

		$content = new Gloskin_Site_Core_Content_Service();
		$content->register();
		$this->services[] = $content;

		if ( ! is_admin() ) {
			require_once __DIR__ . '/class-gloskin-site-core-asset-service.php';

			$assets = new Gloskin_Site_Core_Asset_Service( $this->plugin_file, self::VERSION );
			$assets->register();
			$this->services[] = $assets;
		}
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
