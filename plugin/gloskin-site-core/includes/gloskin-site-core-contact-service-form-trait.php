<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Gloskin_Site_Core_Contact_Service_Form_Trait {
	/** @return string */
	public function render_form() {
		$settings = self::settings();
		if ( empty( $settings['form_enabled'] ) ) {
			return '';
		}
		$started = time();
		$signature = wp_hash( 'gloskin-contact|' . $started );
		$status = isset( $_GET['gloskin_contact'] ) ? sanitize_key( wp_unslash( $_GET['gloskin_contact'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- PRG presentation flag only.
		$clinics = get_posts(
			array(
				'post_type'      => Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		ob_start();
		?>
		<div class="gloskin-contact-native" data-gloskin-contact-native>
			<?php if ( 'success' === $status ) : ?><div class="gloskin-contact-native__notice" role="status"><?php echo esc_html__( 'Pesan Anda sudah tersimpan. Tim Gloskin akan meninjaunya.', 'gloskin-site-core' ); ?></div><?php endif; ?>
			<?php if ( 'error' === $status ) : ?><div class="gloskin-contact-native__notice gloskin-contact-native__notice--error" role="alert"><?php echo esc_html__( 'Pesan belum dapat diproses. Periksa kembali isian lalu coba lagi.', 'gloskin-site-core' ); ?></div><?php endif; ?>
			<p class="gloskin-contact-native__intro"><?php echo esc_html__( 'Gunakan formulir ini untuk pertanyaan umum. Jangan kirim riwayat medis, diagnosis, NIK, resep, foto medis, atau data klinis sensitif. Formulir ini bukan layanan darurat dan tidak menggantikan konsultasi dokter.', 'gloskin-site-core' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gloskin-contact-native__form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::FORM_ACTION ); ?>" />
				<?php wp_nonce_field( self::FORM_NONCE, '_gloskin_contact_nonce' ); ?>
				<input type="hidden" name="contact_started_at" value="<?php echo esc_attr( (string) $started ); ?>" />
				<input type="hidden" name="contact_started_sig" value="<?php echo esc_attr( $signature ); ?>" />
				<div class="gloskin-contact-native__honeypot" aria-hidden="true" hidden><label>Website<input type="text" name="company_website" value="" tabindex="-1" autocomplete="off" /></label></div>
				<div class="gloskin-contact-native__grid">
					<label><span><?php echo esc_html__( 'Nama lengkap', 'gloskin-site-core' ); ?> *</span><input type="text" name="full_name" maxlength="120" autocomplete="name" required /></label>
					<label><span><?php echo esc_html__( 'Email', 'gloskin-site-core' ); ?> *</span><input type="email" name="email" maxlength="190" autocomplete="email" required /></label>
					<label><span><?php echo esc_html__( 'WhatsApp / telepon', 'gloskin-site-core' ); ?> *</span><input type="tel" name="phone" maxlength="32" autocomplete="tel" required /></label>
					<label><span><?php echo esc_html__( 'Topik', 'gloskin-site-core' ); ?> *</span><select name="topic" required><option value=""><?php echo esc_html__( 'Pilih topik', 'gloskin-site-core' ); ?></option><?php foreach ( self::topics() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
					<label><span><?php echo esc_html__( 'Klinik pilihan (opsional)', 'gloskin-site-core' ); ?></span><select name="clinic_id"><option value="0"><?php echo esc_html__( 'Tidak memilih klinik', 'gloskin-site-core' ); ?></option><?php foreach ( $clinics as $clinic ) : ?><option value="<?php echo esc_attr( (string) $clinic->ID ); ?>"><?php echo esc_html( get_the_title( $clinic ) ); ?></option><?php endforeach; ?></select></label>
				</div>
				<label class="gloskin-contact-native__message"><span><?php echo esc_html__( 'Pesan', 'gloskin-site-core' ); ?> *</span><textarea name="message" rows="7" maxlength="3000" required></textarea></label>
				<label class="gloskin-contact-native__consent"><input type="checkbox" name="privacy_consent" value="1" required /> <span><?php echo esc_html__( 'Saya menyetujui penggunaan data kontak ini untuk menanggapi pesan saya sesuai kebutuhan operasional Gloskin.', 'gloskin-site-core' ); ?></span></label>
				<?php if ( ! empty( $settings['recaptcha_enabled'] ) ) : ?>
					<?php if ( self::recaptcha_ready() ) : ?><div class="g-recaptcha" data-sitekey="<?php echo esc_attr( self::recaptcha_site_key() ); ?>"></div><?php else : ?><p class="gloskin-contact-native__config-error" role="alert"><?php echo esc_html__( 'Formulir sedang tidak tersedia karena konfigurasi keamanan belum lengkap.', 'gloskin-site-core' ); ?></p><?php endif; ?>
				<?php endif; ?>
				<button type="submit" class="gloskin-ui1-button"<?php echo ! self::recaptcha_ready() ? ' disabled' : ''; ?>><?php echo esc_html__( 'Kirim Pesan', 'gloskin-site-core' ); ?></button>
			</form>
		</div>
		<?php
		return trim( (string) ob_get_clean() );
	}
}
