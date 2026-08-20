<?php
/**
 * Scoped Gloskin Contact mail transport.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Contact_Mailer {
	/** @var array<string,mixed> */
	private $settings = array();

	/** @var WP_Error|null */
	private $last_error = null;

	/** @var bool */
	private $site_wide_registered = false;

	/**
	 * @param array<string,mixed> $settings Contact settings.
	 */
	public function __construct( $settings ) {
		$this->settings = is_array( $settings ) ? $settings : array();
	}

	/**
	 * Apply SMTP globally only when an administrator explicitly selected the
	 * site-wide scope. The default contact-only path never registers this.
	 *
	 * @return void
	 */
	public function register_site_wide_scope() {
		if ( $this->site_wide_registered || empty( $this->settings['smtp_enabled'] ) || 'site_wide' !== ( isset( $this->settings['smtp_scope'] ) ? $this->settings['smtp_scope'] : 'gloskin_contact' ) ) {
			return;
		}
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 20 );
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ), 20 );
		add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ), 20 );
		$this->site_wide_registered = true;
	}

	/**
	 * Send through the exact same owner used by Contact notifications and the
	 * admin test-email action. A true return means accepted by wp_mail's
	 * transport layer, never guaranteed inbox delivery.
	 *
	 * @param string|array<int,string> $to Recipient(s).
	 * @param string                   $subject Subject.
	 * @param string                   $body Plain-text body.
	 * @param string                   $reply_to Optional visitor Reply-To.
	 * @return array{accepted:bool,error_code:string,error_message:string}
	 */
	public function send( $to, $subject, $body, $reply_to = '' ) {
		$this->last_error = null;
		$temporary = ! $this->site_wide_registered && ! empty( $this->settings['smtp_enabled'] );
		if ( $temporary ) {
			add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 20 );
			add_filter( 'wp_mail_from', array( $this, 'mail_from' ), 20 );
			add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ), 20 );
		}
		add_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ), 10, 1 );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$accepted = (bool) wp_mail( $to, $subject, $body, $headers );

		remove_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ), 10 );
		if ( $temporary ) {
			remove_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 20 );
			remove_filter( 'wp_mail_from', array( $this, 'mail_from' ), 20 );
			remove_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ), 20 );
		}

		$error_code = '';
		$error_message = '';
		if ( ! $accepted && $this->last_error instanceof WP_Error ) {
			$error_code = sanitize_key( (string) $this->last_error->get_error_code() );
			$error_message = sanitize_text_field( (string) $this->last_error->get_error_message() );
			$error_message = mb_substr( $error_message, 0, 300 );
		}
		if ( ! $accepted && '' === $error_code ) {
			$error_code = 'wp_mail_failed';
			$error_message = __( 'Mail transport did not accept the message.', 'gloskin-site-core' );
		}
		return array(
			'accepted'      => $accepted,
			'error_code'    => $error_code,
			'error_message' => $error_message,
		);
	}

	/** @param WP_Error $error Error from wp_mail_failed. @return void */
	public function capture_mail_error( $error ) {
		if ( $error instanceof WP_Error ) {
			$this->last_error = $error;
		}
	}

	/**
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer WordPress PHPMailer.
	 * @return void
	 */
	public function configure_phpmailer( $phpmailer ) {
		if ( empty( $this->settings['smtp_enabled'] ) || ! is_object( $phpmailer ) || ! method_exists( $phpmailer, 'isSMTP' ) ) {
			return;
		}
		$host = isset( $this->settings['smtp_host'] ) ? trim( (string) $this->settings['smtp_host'] ) : '';
		$port = isset( $this->settings['smtp_port'] ) ? absint( $this->settings['smtp_port'] ) : 0;
		if ( '' === $host || $port < 1 || $port > 65535 ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host = $host;
		$phpmailer->Port = $port;
		$encryption = isset( $this->settings['smtp_encryption'] ) ? (string) $this->settings['smtp_encryption'] : 'tls';
		$phpmailer->SMTPSecure = in_array( $encryption, array( 'tls', 'ssl' ), true ) ? $encryption : '';
		$phpmailer->SMTPAutoTLS = 'none' !== $encryption;
		$auth = ! empty( $this->settings['smtp_auth_enabled'] );
		$phpmailer->SMTPAuth = $auth;
		if ( $auth ) {
			$phpmailer->Username = isset( $this->settings['smtp_username'] ) ? (string) $this->settings['smtp_username'] : '';
			$phpmailer->Password = $this->smtp_password();
		}
		$phpmailer->SMTPDebug = 0;
	}

	/** @param string $from Existing From. @return string */
	public function mail_from( $from ) {
		$candidate = isset( $this->settings['from_email'] ) ? sanitize_email( (string) $this->settings['from_email'] ) : '';
		return is_email( $candidate ) ? $candidate : $from;
	}

	/** @param string $name Existing From name. @return string */
	public function mail_from_name( $name ) {
		$candidate = isset( $this->settings['from_name'] ) ? sanitize_text_field( (string) $this->settings['from_name'] ) : '';
		return '' !== $candidate ? mb_substr( $candidate, 0, 120 ) : $name;
	}

	/** @return string */
	private function smtp_password() {
		if ( defined( 'GLOSKIN_SMTP_PASSWORD' ) ) {
			return (string) constant( 'GLOSKIN_SMTP_PASSWORD' );
		}
		return isset( $this->settings['smtp_password'] ) ? (string) $this->settings['smtp_password'] : '';
	}
}
