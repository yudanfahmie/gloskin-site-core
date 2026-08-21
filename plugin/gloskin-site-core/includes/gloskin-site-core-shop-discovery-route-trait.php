<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
trait Gloskin_Site_Core_Shop_Discovery_Route_Trait {
	/** @var string */
	private $plugin_file;

	/** @param string $plugin_file Main plugin file. */
	public function __construct( $plugin_file ) {
		$this->plugin_file = (string) $plugin_file;
	}

	/** @return void */
	public function register() {
		add_filter( 'rest_endpoints', array( $this, 'extend_existing_endpoint' ), 1000 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_shop_assets' ), 15 );
	}

	/**
	 * Replace only the GET handler registered for the existing public route.
	 * No second endpoint is introduced.
	 *
	 * @param array<string,mixed> $endpoints REST endpoints.
	 * @return array<string,mixed>
	 */
	public function extend_existing_endpoint( $endpoints ) {
		$route = '/gloskin/v1/shop/catalog';
		if ( empty( $endpoints[ $route ] ) || ! is_array( $endpoints[ $route ] ) ) {
			return $endpoints;
		}
		foreach ( $endpoints[ $route ] as $index => $endpoint ) {
			$methods = isset( $endpoint['methods'] ) ? $endpoint['methods'] : '';
			$is_get = ( is_string( $methods ) && false !== strpos( $methods, 'GET' ) ) || ( is_array( $methods ) && ! empty( $methods['GET'] ) );
			if ( ! $is_get ) {
				continue;
			}
			$endpoints[ $route ][ $index ]['callback'] = array( $this, 'rest_catalog' );
			$endpoints[ $route ][ $index ]['args'] = array(
				'page'      => array( 'required' => false, 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
				'category'  => array( 'required' => false, 'type' => 'string', 'default' => '' ),
				'q'         => array( 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				'min_price' => array( 'required' => false, 'type' => 'string', 'default' => '' ),
				'max_price' => array( 'required' => false, 'type' => 'string', 'default' => '' ),
			);
		}
		return $endpoints;
	}

	/** @return void */
	public function enqueue_shop_assets() {
		$context = function_exists( 'get_query_var' ) ? get_query_var( 'gloskin_context', array() ) : array();
		$is_shop = is_array( $context ) && 'shop' === ( isset( $context['view'] ) ? $context['view'] : '' );
		if ( ! $is_shop && function_exists( 'is_shop' ) ) {
			$is_shop = is_shop();
		}
		if ( ! $is_shop ) {
			return;
		}
		$version = class_exists( 'Gloskin_Site_Core_Kernel' ) ? Gloskin_Site_Core_Kernel::VERSION : null;
		/* The Shop template exposes one dedicated catalog-owner marker, so this
		 * controller is the only active Shop request/state owner. It never
		 * intercepts fetch or History API primitives. */
		wp_enqueue_script( 'gloskin-ui1-shop-discovery', plugins_url( 'assets/js/gloskin-ui1-shop-discovery.js', $this->plugin_file ), array(), $version, false );
		wp_enqueue_style( 'gloskin-ui1-shop-discovery', plugins_url( 'assets/css/gloskin-ui1-shop-discovery.css', $this->plugin_file ), array( 'gloskin-ui1-product-grid' ), $version );
	}
}
