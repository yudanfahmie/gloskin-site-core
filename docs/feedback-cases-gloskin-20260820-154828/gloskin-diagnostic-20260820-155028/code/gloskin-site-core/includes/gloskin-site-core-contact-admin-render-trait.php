<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
trait Gloskin_Site_Core_Contact_Admin_Render_Trait {
	/** @return void */
	private function render_contact_settings() {
		$s = Gloskin_Site_Core_Contact_Service::settings();
		$recipients = implode( "\n", (array) $s['recipient_emails'] );
		$smtp_external = defined( 'GLOSKIN_SMTP_PASSWORD' );
		$captcha_external = defined( 'GLOSKIN_RECAPTCHA_SECRET_KEY' );
		echo '<div class="wrap gloskin-contact-settings"><h1>' . esc_html__( 'Contact & Email', 'gloskin-site-core' ) . '</h1><p>' . esc_html__( 'Formulir, inbox, transport, auto reply, keamanan, dan readiness Contact.', 'gloskin-site-core' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::INBOX_SLUG ) ) . '">' . esc_html__( 'Buka Kotak Masuk', 'gloskin-site-core' ) . '</a></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::SETTINGS_NONCE ); echo '<input type="hidden" name="action" value="' . esc_attr( self::SETTINGS_ACTION ) . '">';
		$this->card_open( '1. Form & Destination' );
		echo '<p><label><input type="checkbox" name="form_enabled" value="1" ' . checked( ! empty( $s['form_enabled'] ), true, false ) . '> ' . esc_html__( 'Aktifkan form native Gloskin', 'gloskin-site-core' ) . '</label></p>';
		echo '<p><label>Recipient emails (maks. 5, satu per baris)<br><textarea class="large-text" rows="4" name="recipient_emails">' . esc_textarea( $recipients ) . '</textarea></label></p><input type="hidden" name="reply_to_behavior" value="visitor_email">';
		echo '<p><label>Retention (30–730 hari)<br><input type="number" min="30" max="730" name="retention_days" value="' . esc_attr( (string) absint( $s['retention_days'] ) ) . '"></label></p><p class="description">Visitor email = Reply-To, never From.</p>'; $this->card_close();
		$this->card_open( '2. SMTP Transport' );
		echo '<p><label><input type="checkbox" name="smtp_enabled" value="1" ' . checked( ! empty( $s['smtp_enabled'] ), true, false ) . '> SMTP</label></p>';
		foreach ( array( 'smtp_host'=>'Host', 'smtp_username'=>'Username', 'from_email'=>'From Email', 'from_name'=>'From Name' ) as $k=>$label ) { echo '<p><label>' . esc_html( $label ) . '<br><input class="regular-text" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $s[$k] ) . '"></label></p>'; }
		echo '<p><label>Port<br><input type="number" min="1" max="65535" name="smtp_port" value="' . esc_attr( (string) absint( $s['smtp_port'] ) ) . '"></label></p>';
		echo '<p><label>Encryption <select name="smtp_encryption">'; foreach ( array('tls','ssl','none') as $v ) { echo '<option value="' . esc_attr($v) . '" ' . selected($s['smtp_encryption'],$v,false) . '>' . esc_html(strtoupper($v)) . '</option>'; } echo '</select></label></p>';
		echo '<p><label><input type="checkbox" name="smtp_auth_enabled" value="1" ' . checked( ! empty($s['smtp_auth_enabled']), true, false ) . '> Authentication</label></p>';
		echo '<p><label>Secret / Password<br><input type="password" name="smtp_password" value="" autocomplete="new-password" ' . disabled($smtp_external,true,false) . '></label><br><span class="description">' . esc_html( $smtp_external ? 'Configured externally via GLOSKIN_SMTP_PASSWORD.' : 'Blank save preserves existing secret.' ) . '</span></p>';
		echo '<p><label>Scope <select name="smtp_scope"><option value="gloskin_contact" ' . selected($s['smtp_scope'],'gloskin_contact',false) . '>Gloskin Contact only (default)</option><option value="site_wide" ' . selected($s['smtp_scope'],'site_wide',false) . '>All WordPress mail (explicit)</option></select></label></p>'; $this->card_close();
		$this->card_open( '3. Auto Reply' ); echo '<p><label><input type="checkbox" name="autoreply_enabled" value="1" ' . checked(!empty($s['autoreply_enabled']),true,false) . '> Enabled</label></p><p><label>Subject<br><input class="large-text" name="autoreply_subject" maxlength="180" value="' . esc_attr((string)$s['autoreply_subject']) . '"></label></p><p><label>Body<br><textarea class="large-text" rows="7" name="autoreply_body" maxlength="5000">' . esc_textarea((string)$s['autoreply_body']) . '</textarea></label></p><p class="description">{name}, {topic}, {site_name}, {message_id}</p>'; $this->card_close();
		$this->card_open( '4. reCAPTCHA v2' ); echo '<p><label><input type="checkbox" name="recaptcha_enabled" value="1" ' . checked(!empty($s['recaptcha_enabled']),true,false) . '> Google reCAPTCHA v2 checkbox</label></p><p><label>Site Key<br><input class="large-text" name="recaptcha_site_key" maxlength="250" value="' . esc_attr( defined('GLOSKIN_RECAPTCHA_SITE_KEY') ? '' : (string)$s['recaptcha_site_key'] ) . '" ' . disabled(defined('GLOSKIN_RECAPTCHA_SITE_KEY'),true,false) . '></label></p><p><label>Secret Key<br><input type="password" name="recaptcha_secret_key" value="" autocomplete="new-password" ' . disabled($captcha_external,true,false) . '></label><br><span class="description">' . esc_html( $captcha_external ? 'Configured externally via GLOSKIN_RECAPTCHA_SECRET_KEY.' : 'Blank save preserves existing secret.' ) . '</span></p>'; if ( ! empty($s['recaptcha_enabled']) && ! Gloskin_Site_Core_Contact_Service::recaptcha_ready() ) { echo '<div class="notice notice-error inline"><p>Enabled but incomplete: public form fails closed.</p></div>'; } $this->card_close();
		submit_button( __( 'Simpan Contact & Email', 'gloskin-site-core' ) ); echo '</form>'; $this->render_email_test_card( $s ); echo '</div>';
	}

	/** @param string $title Card title. @return void */
	private function card_open( $title ) { echo '<section class="card" style="max-width:900px"><h2>' . esc_html( $title ) . '</h2>'; }
	/** @return void */ private function card_close() { echo '</section>'; }
}
