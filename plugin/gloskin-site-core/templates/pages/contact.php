<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_contact_hero    = isset( $gloskin_context['hero'] ) && is_array( $gloskin_context['hero'] ) ? $gloskin_context['hero'] : array();
$gloskin_contact_clinics = isset( $gloskin_context['clinics'] ) && is_array( $gloskin_context['clinics'] ) ? $gloskin_context['clinics'] : array();
$gloskin_form_html       = trim( (string) ( isset( $gloskin_context['form_html'] ) ? $gloskin_context['form_html'] : '' ) );
$gloskin_form_available  = '' !== $gloskin_form_html && false === strpos( $gloskin_form_html, 'gloskin-ui1-empty--form' );
$gloskin_real_clinics    = gloskin_ui1_real_cards( $gloskin_contact_clinics );
$gloskin_contact_img_id  = isset( $gloskin_contact_hero['media_id'] ) ? absint( $gloskin_contact_hero['media_id'] ) : 0;
$gloskin_contact_copy    = isset( $gloskin_contact_hero['copy'] ) ? (string) $gloskin_contact_hero['copy'] : __( 'Pilih klinik Gloskin untuk melihat detail lokasi dan kanal kontak yang tersedia.', 'gloskin-site-core' );
$gloskin_contact_has_img = $gloskin_contact_img_id > 0;
?>

<?php /* ─── Split blush hero ─────────────────────────────────────────────────── */ ?>
<header class="gloskin-contact-hero" aria-label="<?php echo esc_attr__( 'Kontak', 'gloskin-site-core' ); ?>">
	<div class="gloskin-ui1-container">
		<div class="gloskin-contact-hero__inner<?php echo $gloskin_contact_has_img ? '' : ' gloskin-contact-hero__inner--no-media'; ?>">
			<div class="gloskin-contact-hero__copy">
				<p class="gloskin-contact-hero__eyebrow"><?php echo esc_html__( 'GLOSKIN', 'gloskin-site-core' ); ?></p>
				<h1 class="gloskin-contact-hero__heading">
					<span><?php echo esc_html__( 'KONTAK', 'gloskin-site-core' ); ?></span>
					<span><?php echo esc_html__( 'GLOSKIN', 'gloskin-site-core' ); ?></span>
				</h1>
				<?php if ( '' !== $gloskin_contact_copy ) : ?>
					<p class="gloskin-contact-hero__description"><?php echo esc_html( $gloskin_contact_copy ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $gloskin_contact_has_img ) : ?>
				<div class="gloskin-contact-hero__media" aria-hidden="true">
					<?php echo wp_get_attachment_image( $gloskin_contact_img_id, 'large', false, array(
						'class'         => 'gloskin-contact-hero__image',
						'loading'       => 'eager',
						'fetchpriority' => 'high',
					) ); ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</header>

<?php /* ─── Location card grid ──────────────────────────────────────────────── */ ?>
<section class="gloskin-ui1-section gloskin-contact-clinics" data-gloskin-section="contact-clinics">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading(
			__( 'Pilih Lokasi yang Ingin Dihubungi', 'gloskin-site-core' ),
			__( 'Buka detail cabang untuk melihat kanal kontak yang sudah tersedia.', 'gloskin-site-core' )
		); ?>
		<?php if ( $gloskin_real_clinics ) : ?>
			<div class="gloskin-contact-location-grid">
				<?php foreach ( $gloskin_real_clinics as $gloskin_clinic ) : ?>
					<article class="gloskin-contact-location-card">
						<a class="gloskin-contact-location-card__inner" href="<?php echo esc_url( (string) $gloskin_clinic['url'] ); ?>">
							<h3 class="gloskin-contact-location-card__name"><?php echo esc_html( (string) $gloskin_clinic['title'] ); ?></h3>
							<span class="gloskin-contact-location-card__cta">
								<?php echo esc_html__( 'Lihat Klinik', 'gloskin-site-core' ); ?>
								<?php echo gloskin_ui1_arrow_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted internal SVG ?>
							</span>
						</a>
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
		<div class="gloskin-ui1-container">
			<div class="gloskin-contact-form__inner">
				<?php gloskin_ui1_render_section_heading(
					__( 'Kirim Pesan', 'gloskin-site-core' ),
					__( 'Formulir ini hanya untuk pertanyaan umum. Jangan kirim riwayat medis, diagnosis, resep, foto medis, atau data klinis sensitif. Formulir ini bukan layanan darurat dan tidak menggantikan konsultasi dengan dokter.', 'gloskin-site-core' )
				); ?>
				<div class="gloskin-ui1-form">
					<?php echo $gloskin_form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- configured provider output. ?>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>
