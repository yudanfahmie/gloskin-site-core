<?php
/**
 * Single Gloskin first-party asset owner.
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
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ), 20 );
	}

	/**
	 * @return void
	 */
	public function enqueue_frontend() {
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
	 * Load the tiny Media Library helper only on relevant Gloskin edit screens.
	 *
	 * @param string $hook_suffix Admin screen hook.
	 * @return void
	 */
	public function enqueue_admin( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array(
			$screen->post_type,
			array(
				Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE,
				'page',
			),
			true
		) ) {
			return;
		}

		$registry = $this->registry();
		if ( empty( $registry['admin_scripts']['gloskin-ui1-admin'] ) ) {
			return;
		}

		$asset = $registry['admin_scripts']['gloskin-ui1-admin'];
		wp_enqueue_media();
		wp_register_script(
			'gloskin-ui1-admin',
			plugins_url( $asset['src'], $this->plugin_file ),
			$asset['deps'],
			$this->version,
			true
		);
		wp_enqueue_script( 'gloskin-ui1-admin' );
	}

	/**
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function registry() {
		if ( null === $this->registry ) {
			$registry       = require dirname( __DIR__ ) . '/config/assets.php';
			$this->registry = is_array( $registry )
				? $registry
				: array( 'styles' => array(), 'scripts' => array(), 'admin_scripts' => array() );
		}

		return $this->registry;
	}
}
