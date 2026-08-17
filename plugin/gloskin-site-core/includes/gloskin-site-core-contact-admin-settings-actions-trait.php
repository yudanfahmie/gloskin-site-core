<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Admin_Settings_Actions_Trait {
	/** @return void */
	public function handle_settings_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Capability manage_options diperlukan.', 'gloskin-site-core' ) );
		}
		check_admin_referer( self::SETTINGS_NONCE );
		$current = Gloskin_Site_Core_Contact_Service::settings();
		$recipients_raw = isset( $_POST['recipient_emails'] ) ? (string) wp_unslash( $_POST['recipient_emails'] ) : '';
		$recipients = preg_split( '/[\r\n,;]+/', $recipients_raw );
		$recipients = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_email', (array) $recipients ), 'is_email' ) ) ), 0, 5 );
		$encryption = isset( $_POST['smtp_encryption'] ) ? sanitize_key( wp_unslash( $_POST['smtp_encryption'] ) ) : 'tls';
		$scope = isset( $_POST['smtp_scope'] ) ? sanitize_key( wp_unslash( $_POST['smtp_scope'] ) ) : 'gloskin_contact';
		$site_key = defined( 'GLOSKIN_RECAPTCHA_SITE_KEY' ) ? (string) $current['recaptcha_site_key'] : ( isset( $_POST['recaptcha_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_site_key'] ) ) : '' );
		$smtp_password = isset( $current['smtp_password'] ) ? (string) $current['smtp_password'] : '';
		if ( ! defined( 'GLOSKIN_SMTP_PASSWORD' ) && isset( $_POST['smtp_password'] ) && '' !== (string) wp_unslash( $_POST['smtp_password'] ) ) {
			$smtp_password = mb_substr( sanitize_text_field( wp_unslash( $_POST['smtp_password'] ) ), 0, 500 );
		}
		$recaptcha_secret = isset( $current['recaptcha_secret_key'] ) ? (string) $current['recaptcha_secret_key'] : '';
		if ( ! defined( 'GLOSKIN_RECAPTCHA_SECRET_KEY' ) && isset( $_POST['recaptcha_secret_key'] ) && '' !== (string) wp_unslash( $_POST['recaptcha_secret_key'] ) ) {
			$recaptcha_secret = mb_substr( sanitize_text_field( wp_unslash( $_POST['recaptcha_secret_key'] ) ), 0, 500 );
		}
		$settings = array_merge(
			Gloskin_Site_Core_Contact_Service::defaults(),
			array(
				'form_enabled'         => isset( $_POST['form_enabled'] ) ? 1 : 0,
				'recipient_emails'     => $recipients,
				'reply_to_behavior'    => 'visitor_email',
				'retention_days'       => max( 30, min( 730, absint( isset( $_POST['retention_days'] ) ? $_POST['retention_days'] : 180 ) ) ),
				'smtp_enabled'         => isset( $_POST['smtp_enabled'] ) ? 1 : 0,
				'smtp_host'            => mb_substr( sanitize_text_field( isset( $_POST['smtp_host'] ) ? wp_unslash( $_POST['smtp_host'] ) : '' ), 0, 190 ),
				'smtp_port'            => max( 1, min( 65535, absint( isset( $_POST['smtp_port'] ) ? $_POST['smtp_port'] : 587 ) ) ),
				'smtp_encryption'      => in_array( $encryption, array( 'tls', 'ssl', 'none' ), true ) ? $encryption : 'tls',
				'smtp_auth_enabled'    => isset( $_POST['smtp_auth_enabled'] ) ? 1 : 0,
				'smtp_username'        => mb_substr( sanitize_text_field( isset( $_POST['smtp_username'] ) ? wp_unslash( $_POST['smtp_username'] ) : '' ), 0, 190 ),
				'smtp_password'        => $smtp_password,
				'from_email'           => sanitize_email( isset( $_POST['from_email'] ) ? wp_unslash( $_POST['from_email'] ) : '' ),
				'from_name'            => mb_substr( sanitize_text_field( isset( $_POST['from_name'] ) ? wp_unslash( $_POST['from_name'] ) : '' ), 0, 120 ),
				'smtp_scope'           => in_array( $scope, array( 'gloskin_contact', 'site_wide' ), true ) ? $scope : 'gloskin_contact',
				'autoreply_enabled'    => isset( $_POST['autoreply_enabled'] ) ? 1 : 0,
				'autoreply_subject'    => mb_substr( sanitize_text_field( isset( $_POST['autoreply_subject'] ) ? wp_unslash( $_POST['autoreply_subject'] ) : '' ), 0, 180 ),
				'autoreply_body'       => mb_substr( sanitize_textarea_field( isset( $_POST['autoreply_body'] ) ? wp_unslash( $_POST['autoreply_body'] ) : '' ), 0, 5000 ),
				'recaptcha_enabled'    => isset( $_POST['recaptcha_enabled'] ) ? 1 : 0,
				'recaptcha_site_key'   => mb_substr( $site_key, 0, 250 ),
				'recaptcha_secret_key' => $recaptcha_secret,
				'last_test_status'     => isset( $current['last_test_status'] ) ? $current['last_test_status'] : 'never',
				'last_test_at'         => isset( $current['last_test_at'] ) ? absint( $current['last_test_at'] ) : 0,
				'last_test_error_code' => isset( $current['last_test_error_code'] ) ? sanitize_key( $current['last_test_error_code'] ) : '',
			)
		);
		if ( ! is_email( $settings['from_email'] ) ) {
			$settings['from_email'] = sanitize_email( (string) get_option( 'admin_email' ) );
		}
		$this->persist_settings( $settings );
		$this->redirect_settings( 'saved' );
	}

	/** @param array<string,mixed> $settings Settings. @return void */
	private function persist_settings( $settings ) {
		if ( false === get_option( Gloskin_Site_Core_Contact_Service::SETTINGS_OPTION, false ) ) {
			add_option( Gloskin_Site_Core_Contact_Service::SETTINGS_OPTION, $settings, '', 'no' );
			return;
		}
		update_option( Gloskin_Site_Core_Contact_Service::SETTINGS_OPTION, $settings, false );
	}

	/** @param string $notice Notice. @return void */
	private function redirect_settings( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => Gloskin_Site_Core_Admin_Service::SETTINGS_SLUG, 'section' => 'contact', 'gloskin_contact_settings' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
