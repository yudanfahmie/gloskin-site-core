<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Admin_Readiness_Trait {
	/** @return void */
	public function recaptcha_readiness_notice() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$settings = Gloskin_Site_Core_Contact_Service::settings();
		if ( empty( $settings['recaptcha_enabled'] ) || Gloskin_Site_Core_Contact_Service::recaptcha_ready() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Gloskin Contact:', 'gloskin-site-core' ) . '</strong> ' . esc_html__( 'reCAPTCHA v2 aktif tetapi site key/secret belum lengkap. Form publik fail closed sampai konfigurasi diperbaiki.', 'gloskin-site-core' ) . '</p></div>';
	}
}
