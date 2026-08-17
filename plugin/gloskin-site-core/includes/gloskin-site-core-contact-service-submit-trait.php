<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Service_Submit_Trait {
	/**
	 * Submission order: validate -> intention/anti-spam/reCAPTCHA -> sanitize
	 * -> persist -> staff mail -> auto reply -> transport result persistence.
	 *
	 * @return void
	 */
	public function handle_submit() {
		$raw = $this->raw_submission();
		if ( is_wp_error( $this->validate_raw_submission( $raw ) ) ) {
			$this->redirect( 'error' );
		}
		if ( ! $this->verify_intention_and_abuse_controls( $raw ) ) {
			$this->redirect( 'error' );
		}
		$recaptcha = $this->verify_recaptcha( isset( $raw['recaptcha'] ) ? $raw['recaptcha'] : '' );
		unset( $raw['recaptcha'] );
		if ( isset( $_POST['g-recaptcha-response'] ) ) {
			unset( $_POST['g-recaptcha-response'] );
		}
		if ( is_wp_error( $recaptcha ) ) {
			$this->redirect( 'error' );
		}

		$payload = $this->sanitize_payload( $raw );
		$post_id = $this->persist_message( $payload );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$this->redirect( 'error' );
		}

		$settings = self::settings();
		$staff_result = $this->send_staff_mail( $post_id, $payload, $settings );
		$this->save_transport_result( $post_id, 'staff_mail', $staff_result );

		if ( ! empty( $settings['autoreply_enabled'] ) ) {
			$auto_result = $this->send_autoreply( $post_id, $payload, $settings );
			$this->save_transport_result( $post_id, 'autoreply', $auto_result );
		}

		$this->redirect( 'success' );
	}

	/** @return array<string,string> */
	private function raw_submission() {
		return array(
			'nonce'           => isset( $_POST['_gloskin_contact_nonce'] ) ? (string) wp_unslash( $_POST['_gloskin_contact_nonce'] ) : '',
			'full_name'       => isset( $_POST['full_name'] ) ? (string) wp_unslash( $_POST['full_name'] ) : '',
			'email'           => isset( $_POST['email'] ) ? (string) wp_unslash( $_POST['email'] ) : '',
			'phone'           => isset( $_POST['phone'] ) ? (string) wp_unslash( $_POST['phone'] ) : '',
			'topic'           => isset( $_POST['topic'] ) ? (string) wp_unslash( $_POST['topic'] ) : '',
			'clinic_id'       => isset( $_POST['clinic_id'] ) ? (string) wp_unslash( $_POST['clinic_id'] ) : '0',
			'message'         => isset( $_POST['message'] ) ? (string) wp_unslash( $_POST['message'] ) : '',
			'privacy_consent' => isset( $_POST['privacy_consent'] ) ? (string) wp_unslash( $_POST['privacy_consent'] ) : '',
			'honeypot'        => isset( $_POST['company_website'] ) ? (string) wp_unslash( $_POST['company_website'] ) : '',
			'started_at'      => isset( $_POST['contact_started_at'] ) ? (string) wp_unslash( $_POST['contact_started_at'] ) : '',
			'started_sig'     => isset( $_POST['contact_started_sig'] ) ? (string) wp_unslash( $_POST['contact_started_sig'] ) : '',
			'recaptcha'       => isset( $_POST['g-recaptcha-response'] ) ? (string) wp_unslash( $_POST['g-recaptcha-response'] ) : '',
		);
	}

	/** @param array<string,string> $raw Raw fields. @return true|WP_Error */
	private function validate_raw_submission( $raw ) {
		$limits = array( 'full_name' => 120, 'email' => 190, 'phone' => 32, 'topic' => 32, 'message' => 3000 );
		foreach ( $limits as $key => $limit ) {
			$value = isset( $raw[ $key ] ) ? trim( (string) $raw[ $key ] ) : '';
			if ( '' === $value || mb_strlen( $value ) > $limit ) {
				return new WP_Error( 'invalid_' . $key );
			}
		}
		if ( ! is_email( trim( $raw['email'] ) ) ) {
			return new WP_Error( 'invalid_email' );
		}
		if ( ! preg_match( '/^[0-9+().\-\s]{6,32}$/', trim( $raw['phone'] ) ) ) {
			return new WP_Error( 'invalid_phone' );
		}
		if ( ! isset( self::topics()[ sanitize_key( $raw['topic'] ) ] ) ) {
			return new WP_Error( 'invalid_topic' );
		}
		if ( '1' !== $raw['privacy_consent'] ) {
			return new WP_Error( 'consent_required' );
		}
		$clinic_id = absint( $raw['clinic_id'] );
		if ( $clinic_id ) {
			$clinic = get_post( $clinic_id );
			if ( ! $clinic instanceof WP_Post || Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE !== $clinic->post_type || 'publish' !== $clinic->post_status ) {
				return new WP_Error( 'invalid_clinic' );
			}
		}
		return true;
	}
}
