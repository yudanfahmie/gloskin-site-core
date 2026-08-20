<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Admin_Setup_Trait {
	/** @var string */
	private $plugin_file;

	/** @var array<int,callable> */
	private $settings_callbacks = array();

	/** @param string $plugin_file Main plugin file. */
	public function __construct( $plugin_file ) {
		$this->plugin_file = (string) $plugin_file;
	}

	/** @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_inbox_menu' ), 10 );
		add_action( 'admin_menu', array( $this, 'wrap_settings_owner' ), 99 );
		add_action( 'admin_post_' . self::SETTINGS_ACTION, array( $this, 'handle_settings_save' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( $this, 'handle_email_test' ) );
		add_action( 'admin_post_' . self::STATUS_ACTION, array( $this, 'handle_status_action' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( $this, 'handle_delete_action' ) );
		add_action( 'admin_notices', array( $this, 'recaptcha_readiness_notice' ) );
	}

	/** @return void */
	public function register_inbox_menu() {
		$parent = Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG;
		$count = $this->new_count();
		$label = __( 'Kotak Masuk', 'gloskin-site-core' );
		if ( $count > 0 ) {
			$label .= ' <span class="awaiting-mod count-' . absint( $count ) . '"><span class="pending-count">' . absint( $count ) . '</span></span>';
		}
		add_submenu_page(
			$parent,
			__( 'Kotak Masuk Gloskin', 'gloskin-site-core' ),
			$label,
			self::INBOX_CAPABILITY,
			self::INBOX_SLUG,
			array( $this, 'render_inbox' )
		);
	}

	/**
	 * Keep the existing Gloskin Settings screen as the owner. We capture its
	 * registered callback and wrap the same page hook with two high-level
	 * sections: General (the existing callback, untouched) and Contact & Email.
	 *
	 * @return void
	 */
	public function wrap_settings_owner() {
		$parent = Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG;
		$hook = function_exists( 'get_plugin_page_hookname' ) ? get_plugin_page_hookname( Gloskin_Site_Core_Admin_Service::SETTINGS_SLUG, $parent ) : '';
		if ( '' === $hook || empty( $GLOBALS['wp_filter'][ $hook ] ) || ! is_object( $GLOBALS['wp_filter'][ $hook ] ) ) {
			return;
		}
		$wp_hook = $GLOBALS['wp_filter'][ $hook ];
		if ( ! isset( $wp_hook->callbacks ) || ! is_array( $wp_hook->callbacks ) ) {
			return;
		}
		foreach ( $wp_hook->callbacks as $callbacks ) {
			foreach ( (array) $callbacks as $callback ) {
				if ( isset( $callback['function'] ) && is_callable( $callback['function'] ) ) {
					$this->settings_callbacks[] = $callback['function'];
				}
			}
		}
		if ( ! $this->settings_callbacks ) {
			return;
		}
		remove_all_actions( $hook );
		add_action( $hook, array( $this, 'render_settings_owner' ) );
	}

	/** @return void */
	public function render_settings_owner() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
		if ( ! in_array( $section, array( 'general', 'contact' ), true ) ) {
			$section = 'general';
		}
		$base = admin_url( 'admin.php?page=' . Gloskin_Site_Core_Admin_Service::SETTINGS_SLUG );
		echo '<div class="wrap gloskin-contact-settings-owner">';
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Gloskin Settings sections', 'gloskin-site-core' ) . '">';
		echo '<a class="nav-tab' . ( 'general' === $section ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'section', 'general', $base ) ) . '">' . esc_html__( 'General', 'gloskin-site-core' ) . '</a>';
		echo '<a class="nav-tab' . ( 'contact' === $section ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'section', 'contact', $base ) ) . '">' . esc_html__( 'Contact & Email', 'gloskin-site-core' ) . '</a>';
		echo '</nav></div>';
		if ( 'general' === $section ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Catatan: field Contact form shortcode lama dipertahankan hanya untuk kompatibilitas. Runtime Contact canonical sekarang memakai formulir native Gloskin dan tidak merender shortcode eksternal secara bersamaan.', 'gloskin-site-core' ) . '</p></div>';
			foreach ( $this->settings_callbacks as $callback ) {
				call_user_func( $callback );
			}
			return;
		}
		$this->render_contact_settings();
	}
}
