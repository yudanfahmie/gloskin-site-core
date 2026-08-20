<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Admin_Test_Trait {
	/** @param array<string,mixed> $settings Settings. @return void */
	private function render_email_test_card( $settings ) {
		$readiness = $this->readiness( $settings );
		?>
		<section class="gloskin-admin-card" style="margin-top:24px">
			<h2 class="gloskin-admin-card__title"><?php echo esc_html__( '5. Email Test & Readiness', 'gloskin-site-core' ); ?></h2>
			<ul>
				<li><?php echo esc_html__( 'Form destination:', 'gloskin-site-core' ); ?> <strong><?php echo esc_html( $readiness['destination'] ); ?></strong></li>
				<li><?php echo esc_html__( 'SMTP:', 'gloskin-site-core' ); ?> <strong><?php echo esc_html( $readiness['smtp'] ); ?></strong></li>
				<li><?php echo esc_html__( 'Auto reply:', 'gloskin-site-core' ); ?> <strong><?php echo esc_html( $readiness['autoreply'] ); ?></strong></li>
				<li><?php echo esc_html__( 'reCAPTCHA v2:', 'gloskin-site-core' ); ?> <strong><?php echo esc_html( $readiness['recaptcha'] ); ?></strong></li>
				<li><?php echo esc_html__( 'Last test:', 'gloskin-site-core' ); ?> <strong><?php echo esc_html( $readiness['last_test'] ); ?></strong></li>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::TEST_NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::TEST_ACTION ); ?>" />
				<p><label><?php echo esc_html__( 'Test destination email', 'gloskin-site-core' ); ?><br><input class="regular-text" type="email" name="test_email" required /></label></p>
				<button type="submit" class="button button-secondary"><?php echo esc_html__( 'Kirim Email Tes', 'gloskin-site-core' ); ?></button>
			</form>
			<p class="description"><?php echo esc_html__( 'Accepted berarti wp_mail/transport menerima upaya kirim; bukan jaminan Delivered ke inbox.', 'gloskin-site-core' ); ?></p>
		</section>
		<?php
	}

	/** @param array<string,mixed> $settings Settings. @return array<string,string> */
	private function readiness( $settings ) {
		$recipients = isset( $settings['recipient_emails'] ) && is_array( $settings['recipient_emails'] ) ? array_filter( $settings['recipient_emails'], 'is_email' ) : array();
		$smtp = empty( $settings['smtp_enabled'] ) ? 'Disabled' : ( '' !== trim( (string) $settings['smtp_host'] ) && absint( $settings['smtp_port'] ) > 0 ? 'Ready' : 'Invalid' );
		$recaptcha = empty( $settings['recaptcha_enabled'] ) ? 'Disabled' : ( Gloskin_Site_Core_Contact_Service::recaptcha_ready() ? 'Ready' : 'Invalid' );
		$last = isset( $settings['last_test_status'] ) ? (string) $settings['last_test_status'] : 'never';
		return array(
			'destination' => $recipients ? 'Ready' : 'Needs setup',
			'smtp'        => $smtp,
			'autoreply'   => ! empty( $settings['autoreply_enabled'] ) ? 'Enabled' : 'Disabled',
			'recaptcha'   => $recaptcha,
			'last_test'   => 'accepted' === $last ? 'Accepted' : ( 'failed' === $last ? 'Failed' : 'Never' ),
		);
	}

	/** @return void */
	public function handle_email_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Capability manage_options diperlukan.', 'gloskin-site-core' ) );
		}
		check_admin_referer( self::TEST_NONCE );
		$email = sanitize_email( isset( $_POST['test_email'] ) ? wp_unslash( $_POST['test_email'] ) : '' );
		if ( ! is_email( $email ) ) {
			$this->redirect_settings( 'test-failed' );
		}
		$settings = Gloskin_Site_Core_Contact_Service::settings();
		$mailer = new Gloskin_Site_Core_Contact_Mailer( $settings );
		$mailer->register_site_wide_scope();
		$result = $mailer->send( $email, __( 'Gloskin Contact — Email Test', 'gloskin-site-core' ), __( 'Ini adalah email tes dari transport Gloskin Contact. Status Accepted hanya berarti transport menerima upaya kirim.', 'gloskin-site-core' ) );
		$settings['last_test_status'] = ! empty( $result['accepted'] ) ? 'accepted' : 'failed';
		$settings['last_test_at'] = time();
		$settings['last_test_error_code'] = ! empty( $result['accepted'] ) ? '' : sanitize_key( isset( $result['error_code'] ) ? $result['error_code'] : 'failed' );
		$this->persist_settings( $settings );
		$this->redirect_settings( ! empty( $result['accepted'] ) ? 'test-accepted' : 'test-failed' );
	}
}
