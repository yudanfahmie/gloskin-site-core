<?php
/**
 * Single Gloskin first-party frontend asset owner.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Asset_Service {
	/** @var string */
	private $plugin_file;

	/** @var string */
	private $version;

	/** @var array<string, array<string, array<string, mixed>>>|null */
	private $registry = null;

	/**
	 * @param string $plugin_file Main plugin file.
	 * @param string $version Plugin release version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_file = $plugin_file;
		$this->version     = $version;
	}

	/**
	 * Register frontend enqueue ownership.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	/**
	 * Register and enqueue declared Gloskin assets.
	 *
	 * @return void
	 */
	public function enqueue() {
		$registry = $this->registry();

		foreach ( $registry['styles'] as $handle => $asset ) {
			wp_register_style(
				$handle,
				plugins_url( $asset['src'], $this->plugin_file ),
				$asset['deps'],
				$this->version,
				$asset['media']
			);
			wp_enqueue_style( $handle );
		}

		foreach ( $registry['scripts'] as $handle => $asset ) {
			wp_register_script(
				$handle,
				plugins_url( $asset['src'], $this->plugin_file ),
				$asset['deps'],
				$this->version,
				$asset['in_footer']
			);
			wp_enqueue_script( $handle );
		}
	}

	/**
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function registry() {
		if ( null === $this->registry ) {
			$registry = require dirname( __DIR__ ) . '/config/assets.php';
			$this->registry = is_array( $registry ) ? $registry : array( 'styles' => array(), 'scripts' => array() );
		}

		return $this->registry;
	}
}
