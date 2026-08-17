<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Service_Security_Trait {
	/** @param array<string,string> $raw Raw fields. @return bool */
	private function verify_intention_and_abuse_controls( $raw ) {
		$nonce = sanitize_text_field( $raw['nonce'] );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::FORM_NONCE ) ) {
			return false;
		}
		if ( '' !== trim( $raw['honeypot'] ) ) {
			return false;
		}
		$started = absint( $raw['started_at'] );
		$expected = wp_hash( 'gloskin-contact|' . $started );
		if ( ! $started || ! hash_equals( $expected, (string) $raw['started_sig'] ) ) {
			return false;
		}
		$elapsed = time() - $started;
		if ( $elapsed < self::MIN_FILL_SECONDS || $elapsed > DAY_IN_SECONDS ) {
			return false;
		}
		return $this->pass_rate_guard();
	}

	/** @return bool */
	private function pass_rate_guard() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		$fingerprint = hash_hmac( 'sha256', $ip . '|' . mb_substr( $ua, 0, 300 ), wp_salt( 'nonce' ) );
		$key = 'gloskin_contact_rate_' . substr( $fingerprint, 0, 32 );
		$count = absint( get_transient( $key ) );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, self::RATE_TTL );
		return true;
	}

	/** @param string $token reCAPTCHA response token. @return true|WP_Error */
	private function verify_recaptcha( $token ) {
		$settings = self::settings();
		if ( empty( $settings['recaptcha_enabled'] ) ) {
			return true;
		}
		if ( ! self::recaptcha_ready() || '' === trim( $token ) || mb_strlen( $token ) > 4096 ) {
			return new WP_Error( 'recaptcha_invalid' );
		}
		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 5,
				'body'    => array(
					'secret'   => self::recaptcha_secret(),
					'response' => $token,
				),
			)
		);
		$token = ''; // Explicitly discard request token before any persistence/logging path.
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'recaptcha_unreachable' );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['success'] ) ) {
			return new WP_Error( 'recaptcha_failed' );
		}
		$hostname = isset( $body['hostname'] ) ? strtolower( trim( (string) $body['hostname'] ) ) : '';
		$expected_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' !== $hostname && '' !== $expected_host && $hostname !== $expected_host ) {
			return new WP_Error( 'recaptcha_hostname' );
		}
		return true;
	}
}
