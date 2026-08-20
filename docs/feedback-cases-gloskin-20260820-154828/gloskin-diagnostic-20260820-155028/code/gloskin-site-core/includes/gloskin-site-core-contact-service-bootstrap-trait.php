<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Service_Bootstrap_Trait {
	/** @var string */
	private $plugin_file;

	/** @var Gloskin_Site_Core_Contact_Mailer */
	private $mailer;

	/**
	 * @param string $plugin_file Main plugin file.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = (string) $plugin_file;
		$this->mailer = new Gloskin_Site_Core_Contact_Mailer( self::settings() );
	}

	/** @return void */
	public function register() {
		add_action( 'init', array( $this, 'migrate_message_post_type' ), 5 );
		add_action( 'init', array( $this, 'register_message_post_type' ), 6 );
		add_action( 'init', array( $this, 'register_shortcode' ), 8 );
		add_filter( 'option_gloskin_site_core_settings', array( $this, 'own_contact_runtime_shortcode' ), 50, 2 );
		add_action( 'admin_post_' . self::FORM_ACTION, array( $this, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::FORM_ACTION, array( $this, 'handle_submit' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_recaptcha' ), 45 );
		add_action( 'admin_init', array( $this, 'maybe_prune_retention' ), 60 );
		$this->mailer->register_site_wide_scope();
	}

	/**
	 * One-time compatibility migration from the historical invalid (>20 chars)
	 * post type key. Existing inbox records are preserved and normalized before
	 * the valid post type is registered.
	 *
	 * @return void
	 */
	public function migrate_message_post_type() {
		$option = 'gloskin_contact_post_type_migrated_v1';
		if ( get_option( $option ) === '1' ) {
			return;
		}

		global $wpdb;
		if ( ! isset( $wpdb->posts ) ) {
			return;
		}

		$wpdb->update(
			$wpdb->posts,
			array( 'post_type' => self::MESSAGE_POST_TYPE ),
			array( 'post_type' => self::LEGACY_MESSAGE_POST_TYPE ),
			array( '%s' ),
			array( '%s' )
		);
		update_option( $option, '1', false );
	}

	/**
	 * Private WordPress-owned inbox record. No public query, rewrite or REST.
	 *
	 * @return void
	 */
	public function register_message_post_type() {
		register_post_type(
			self::MESSAGE_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Contact Messages', 'gloskin-site-core' ),
					'singular_name' => __( 'Contact Message', 'gloskin-site-core' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/** @return void */
	public function register_shortcode() {
		add_shortcode( 'gloskin_contact_native', array( $this, 'render_form' ) );
	}

	/**
	 * Form_Adapter remains a compatibility shell, but on frontend reads the
	 * first-party shortcode as the canonical Contact owner. Stored legacy
	 * shortcode values are never echoed or co-rendered.
	 *
	 * @param mixed  $value Existing option value.
	 * @param string $option Option name.
	 * @return mixed
	 */
	public function own_contact_runtime_shortcode( $value, $option = '' ) {
		unset( $option );
		if ( is_admin() ) {
			return $value;
		}
		$value = is_array( $value ) ? $value : array();
		$settings = self::settings();
		$value['form_shortcode'] = ! empty( $settings['form_enabled'] ) ? '[gloskin_contact_native]' : '';
		return $value;
	}
}
