<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_contact_hero    = isset( $gloskin_context['hero'] )  && is_array( $gloskin_context['hero'] )  ? $gloskin_context['hero']  : array();
$gloskin_contact_clinics = isset( $gloskin_context['clinics'] ) && is_array( $gloskin_context['clinics'] ) ? $gloskin_context['clinics'] : array();
$gloskin_form_html       = trim( (string) ( isset( $gloskin_context['form_html'] ) ? $gloskin_context['form_html'] : '' ) );
$gloskin_form_available  = '' !== $gloskin_form_html && false === strpos( $gloskin_form_html, 'gloskin-ui1-empty--form' );
$gloskin_real_clinics    = gloskin_ui1_real_cards( $gloskin_contact_clinics );
$gloskin_contact_img_id  = isset( $gloskin_contact_hero['media_id'] ) ? absint( $gloskin_contact_hero['media_id'] ) : 0;
$gloskin_contact_heading = isset( $gloskin_contact_hero['heading'] ) ? (string) $gloskin_contact_hero['heading'] : __( 'Kontak Gloskin', 'gloskin-site-core' );
$gloskin_contact_copy    = isset( $gloskin_contact_hero['copy'] )    ? (string) $gloskin_contact_hero['copy']    : __( 'Pilih klinik Gloskin untuk melihat detail lokasi dan kanal kontak yang tersedia.', 'gloskin-site-core' );
?>

<?php /* ─── Split blush hero ─────────────────────────────────────────────────── */ ?>
<header class="gloskin-contact-hero" aria-label="<?php echo esc_attr__( 'Kontak', 'gloskin-site-core' ); ?>">
	<div class="gloskin-ui1-container gloskin-contact-hero__inner">
		<div class="gloskin-contact-hero__copy">
			<p class="gloskin-contact-hero__eyebrow"><?php echo esc_html__( 'GLOSKIN', 'gloskin-site-core' ); ?></p>
			<h1 class="gloskin-contact-hero__heading"><?php echo esc_html( $gloskin_contact_heading ); ?></h1>
			<?php if ( '' !== $gloskin_contact_copy ) : ?>
				<p class="gloskin-contact-hero__description"><?php echo esc_html( $gloskin_contact_copy ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $gloskin_contact_img_id > 0 ) : ?>
			<div class="gloskin-contact-hero__media" aria-hidden="true">
				<?php echo wp_get_attachment_image( $gloskin_contact_img_id, 'large', false, array(
					'class'   => 'gloskin-contact-hero__image',
					'loading' => 'eager',
					'fetchpriority' => 'high',
				) ); ?>
			</div>
		<?php endif; ?>
	</div>
</header>

<?php /* ─── Clinic grid ──────────────────────────────────────────────────────── */ ?>
<section class="gloskin-ui1-section gloskin-contact-clinics" data-gloskin-section="contact-clinics">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading(
			__( 'Pilih Lokasi yang Ingin Dihubungi', 'gloskin-site-core' ),
			__( 'Buka detail cabang untuk melihat kanal kontak yang sudah tersedia.', 'gloskin-site-core' )
		); ?>
		<?php if ( $gloskin_real_clinics ) : ?>
			<div class="gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-contact-clinics__grid">
				<?php foreach ( $gloskin_real_clinics as $gloskin_clinic ) : ?>
					<article class="gloskin-ui1-card gloskin-ui1-card--contact">
						<a class="gloskin-ui1-card__media" href="<?php echo esc_url( (string) $gloskin_clinic['url'] ); ?>" tabindex="-1" aria-hidden="true">
							<?php if ( ! empty( $gloskin_clinic['image_id'] ) ) : ?>
								<?php echo wp_get_attachment_image( absint( $gloskin_clinic['image_id'] ), 'medium_large', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-card__image' ) ); ?>
							<?php else : ?>
								<?php gloskin_ui1_render_presentation_media( 'clinic', (string) $gloskin_clinic['title'], 'gloskin-ui1-card__abstract' ); ?>
							<?php endif; ?>
						</a>
						<div class="gloskin-ui1-card__body">
							<h3 class="gloskin-ui1-card__title">
								<a href="<?php echo esc_url( (string) $gloskin_clinic['url'] ); ?>"><?php echo esc_html( (string) $gloskin_clinic['title'] ); ?></a>
							</h3>
							<?php if ( ! empty( $gloskin_clinic['phone_display'] ) ) : ?>
								<p><?php echo esc_html( (string) $gloskin_clinic['phone_display'] ); ?></p>
							<?php endif; ?>
							<div class="gloskin-ui1-card__actions">
								<a class="gloskin-ui1-text-link" href="<?php echo esc_url( (string) $gloskin_clinic['url'] ); ?>"><?php echo esc_html__( 'Lihat Klinik', 'gloskin-site-core' ); ?></a>
								<?php if ( ! empty( $gloskin_clinic['whatsapp_url'] ) ) : ?>
									<a class="gloskin-ui1-button gloskin-ui1-button--small" href="<?php echo esc_url( (string) $gloskin_clinic['whatsapp_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Hubungi via WhatsApp', 'gloskin-site-core' ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php gloskin_ui1_render_empty_state( 'clinic', __( 'Belum ada detail klinik yang dapat ditampilkan', 'gloskin-site-core' ), __( 'Anda tetap dapat menggunakan formulir kontak di bawah bila tersedia.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>

<?php /* ─── Centered form ─────────────────────────────────────────────────────── */ ?>
<?php if ( $gloskin_form_available ) : ?>
	<section class="gloskin-ui1-section gloskin-ui1-section--soft gloskin-contact-form" data-gloskin-section="contact-form">
		<div class="gloskin-ui1-container gloskin-ui1-container--narrow">
			<?php gloskin_ui1_render_section_heading(
				__( 'Kirim Pesan', 'gloskin-site-core' ),
				__( 'Gunakan formulir ini untuk mengirim pertanyaan kepada Gloskin.', 'gloskin-site-core' )
			); ?>
			<div class="gloskin-ui1-form">
				<?php echo $gloskin_form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- configured provider output. ?>
			</div>
		</div>
	</section>
<?php endif; ?>
