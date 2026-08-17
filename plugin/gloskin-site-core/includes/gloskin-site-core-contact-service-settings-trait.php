<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Service_Settings_Trait {
	/** @return array<string,string> */
	public static function topics() {
		return array(
			'consultation' => __( 'Konsultasi / Perawatan', 'gloskin-site-core' ),
			'skincare'     => __( 'Skincare / Produk', 'gloskin-site-core' ),
			'clinic'       => __( 'Klinik / Janji Temu', 'gloskin-site-core' ),
			'partnership'  => __( 'Kerja Sama', 'gloskin-site-core' ),
			'other'        => __( 'Masukan / Lainnya', 'gloskin-site-core' ),
		);
	}

	/** @return array<string,mixed> */
	public static function defaults() {
		$site_name = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : 'Gloskin';
		$admin_email = function_exists( 'get_option' ) ? sanitize_email( (string) get_option( 'admin_email' ) ) : '';
		return array(
			'form_enabled'          => 1,
			'recipient_emails'      => is_email( $admin_email ) ? array( $admin_email ) : array(),
			'reply_to_behavior'     => 'visitor_email',
			'retention_days'        => 180,
			'smtp_enabled'          => 0,
			'smtp_host'             => '',
			'smtp_port'             => 587,
			'smtp_encryption'       => 'tls',
			'smtp_auth_enabled'     => 1,
			'smtp_username'         => '',
			'smtp_password'         => '',
			'from_email'            => $admin_email,
			'from_name'             => '' !== $site_name ? $site_name : 'Gloskin',
			'smtp_scope'            => 'gloskin_contact',
			'autoreply_enabled'     => 1,
			'autoreply_subject'     => 'Pesan Anda telah diterima oleh {site_name}',
			'autoreply_body'        => "Halo {name},\n\nTerima kasih telah menghubungi {site_name}. Pesan Anda dengan topik \"{topic}\" sudah kami terima dengan nomor referensi #{message_id}. Tim Gloskin akan meninjau pesan tersebut dan menindaklanjutinya melalui kanal kontak yang sesuai.\n\nFormulir ini adalah kanal kontak umum dan tidak menggantikan pemeriksaan atau konsultasi dokter. Untuk keadaan medis yang mendesak, hubungi klinik atau layanan kesehatan yang sesuai.\n\nSalam,\n{site_name}",
			'recaptcha_enabled'     => 0,
			'recaptcha_site_key'    => '',
			'recaptcha_secret_key'  => '',
			'last_test_status'      => 'never',
			'last_test_at'          => 0,
			'last_test_error_code'  => '',
		);
	}

	/** @return array<string,mixed> */
	public static function settings() {
		$defaults = self::defaults();
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	/** @return string */
	public static function recaptcha_secret() {
		if ( defined( 'GLOSKIN_RECAPTCHA_SECRET_KEY' ) ) {
			return (string) constant( 'GLOSKIN_RECAPTCHA_SECRET_KEY' );
		}
		$settings = self::settings();
		return isset( $settings['recaptcha_secret_key'] ) ? (string) $settings['recaptcha_secret_key'] : '';
	}

	/** @return string */
	public static function recaptcha_site_key() {
		if ( defined( 'GLOSKIN_RECAPTCHA_SITE_KEY' ) ) {
			return (string) constant( 'GLOSKIN_RECAPTCHA_SITE_KEY' );
		}
		$settings = self::settings();
		return isset( $settings['recaptcha_site_key'] ) ? (string) $settings['recaptcha_site_key'] : '';
	}

	/** @return bool */
	public static function recaptcha_ready() {
		$settings = self::settings();
		return empty( $settings['recaptcha_enabled'] ) || ( '' !== trim( self::recaptcha_site_key() ) && '' !== trim( self::recaptcha_secret() ) );
	}

	/** @return void */
	public function enqueue_recaptcha() {
		if ( ! $this->is_contact_request() ) {
			return;
		}
		$settings = self::settings();
		if ( empty( $settings['recaptcha_enabled'] ) || ! self::recaptcha_ready() ) {
			return;
		}
		wp_enqueue_script( 'gloskin-recaptcha-v2', 'https://www.google.com/recaptcha/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google-hosted API endpoint versioned by provider.
	}

	/** @return bool */
	private function is_contact_request() {
		if ( function_exists( 'is_page' ) && is_page( 'contact' ) ) {
			return true;
		}
		$context = function_exists( 'get_query_var' ) ? get_query_var( 'gloskin_context', array() ) : array();
		return is_array( $context ) && 'contact' === ( isset( $context['view'] ) ? $context['view'] : '' );
	}
}
