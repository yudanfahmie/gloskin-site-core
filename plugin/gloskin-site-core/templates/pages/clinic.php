<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_post             = $gloskin_context['post'];
$gloskin_clinic_title     = get_the_title( $gloskin_post );
$gloskin_short_location   = trim( (string) ( $gloskin_context['short_location'] ?? '' ) );
$gloskin_gallery_ids      = isset( $gloskin_context['gallery_ids'] ) && is_array( $gloskin_context['gallery_ids'] ) ? $gloskin_context['gallery_ids'] : array();
$gloskin_primary_media_id = $gloskin_gallery_ids ? absint( $gloskin_gallery_ids[0] ) : 0;
$gloskin_whatsapp_url     = trim( (string) ( $gloskin_context['whatsapp_url'] ?? '' ) );
$gloskin_map_url          = trim( (string) ( $gloskin_context['map_url'] ?? '' ) );
$gloskin_map_embed        = trim( (string) ( $gloskin_context['map_embed'] ?? '' ) );
$gloskin_contact_url      = $gloskin_whatsapp_url ? $gloskin_whatsapp_url : home_url( '/contact/' );
$gloskin_contact_label    = $gloskin_whatsapp_url ? __( 'WhatsApp Klinik', 'gloskin-site-core' ) : __( 'Hubungi Gloskin', 'gloskin-site-core' );
$gloskin_has_content      = gloskin_ui1_has_content( $gloskin_post );
$gloskin_has_facts        = $gloskin_has_content
	|| trim( (string) ( $gloskin_context['address'] ?? '' ) )
	|| trim( (string) ( $gloskin_context['phone_display'] ?? '' ) )
	|| trim( (string) ( $gloskin_context['operating_hours'] ?? '' ) )
	|| $gloskin_whatsapp_url
	|| $gloskin_map_url
	|| $gloskin_map_embed;
/* translators: %s: clinic name for the embedded map title. */
$gloskin_map_title = sprintf( __( 'Peta %s', 'gloskin-site-core' ), $gloskin_clinic_title );
?>
<div class="gloskin-clinic-page">
	<section class="gloskin-clinic-hero" data-gloskin-section="clinic-hero">
		<div class="gloskin-ui1-container">
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Klinik Gloskin', 'gloskin-site-core' ); ?></p>
			<h1><?php echo esc_html( $gloskin_clinic_title ); ?></h1>
			<p class="gloskin-clinic-hero__copy"><?php echo esc_html( $gloskin_short_location ? $gloskin_short_location : __( 'Lihat informasi cabang dan kanal kontak yang tersedia.', 'gloskin-site-core' ) ); ?></p>
		</div>
	</section>

	<section class="gloskin-clinic-detail" data-gloskin-section="clinic-detail">
		<div class="gloskin-ui1-container gloskin-clinic-detail__overlap">
			<div class="gloskin-clinic-detail__card">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Informasi Cabang', 'gloskin-site-core' ); ?></p>
				<?php if ( $gloskin_has_facts ) : ?>
					<h2><?php echo esc_html__( 'Informasi Klinik', 'gloskin-site-core' ); ?></h2>
					<?php if ( $gloskin_has_content ) : ?><div class="gloskin-clinic-detail__prose"><?php gloskin_ui1_render_page_content( $gloskin_post ); ?></div><?php endif; ?>
					<div class="gloskin-clinic-detail__facts">
						<?php if ( ! empty( $gloskin_context['address'] ) ) : ?><p><strong><?php echo esc_html__( 'Alamat', 'gloskin-site-core' ); ?></strong><span><?php echo nl2br( esc_html( (string) $gloskin_context['address'] ) ); ?></span></p><?php endif; ?>
						<?php if ( ! empty( $gloskin_context['phone_display'] ) ) : ?><p><strong><?php echo esc_html__( 'Telepon', 'gloskin-site-core' ); ?></strong><span><?php if ( ! empty( $gloskin_context['phone_url'] ) ) : ?><a href="<?php echo esc_url( (string) $gloskin_context['phone_url'] ); ?>"><?php echo esc_html( (string) $gloskin_context['phone_display'] ); ?></a><?php else : ?><?php echo esc_html( (string) $gloskin_context['phone_display'] ); ?><?php endif; ?></span></p><?php endif; ?>
						<?php if ( ! empty( $gloskin_context['operating_hours'] ) ) : ?><p><strong><?php echo esc_html__( 'Jam Operasional', 'gloskin-site-core' ); ?></strong><span><?php echo nl2br( esc_html( (string) $gloskin_context['operating_hours'] ) ); ?></span></p><?php endif; ?>
					</div>
					<?php if ( $gloskin_whatsapp_url || $gloskin_map_url ) : ?>
						<div class="gloskin-clinic-detail__actions">
							<?php if ( $gloskin_whatsapp_url ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( $gloskin_whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Hubungi via WhatsApp', 'gloskin-site-core' ); ?></a><?php endif; ?>
							<?php if ( $gloskin_map_url ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--ghost" href="<?php echo esc_url( $gloskin_map_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Buka Peta', 'gloskin-site-core' ); ?></a><?php endif; ?>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<h2><?php echo esc_html__( 'Detail cabang belum tersedia untuk ditampilkan.', 'gloskin-site-core' ); ?></h2>
					<p class="gloskin-clinic-detail__sparse-copy"><?php echo esc_html__( 'Anda dapat kembali ke jaringan klinik atau menggunakan halaman kontak untuk mendapatkan informasi lebih lanjut.', 'gloskin-site-core' ); ?></p>
					<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( home_url( '/clinics/' ) ); ?>"><?php echo esc_html__( 'Semua Klinik', 'gloskin-site-core' ); ?></a>
				<?php endif; ?>
			</div>

			<div class="gloskin-clinic-detail__media">
				<?php if ( $gloskin_primary_media_id ) : ?>
					<?php echo wp_get_attachment_image( $gloskin_primary_media_id, 'large', false, array( 'class' => 'gloskin-clinic-detail__image', 'alt' => $gloskin_clinic_title, 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?>
				<?php elseif ( $gloskin_map_embed ) : ?>
					<iframe title="<?php echo esc_attr( $gloskin_map_title ); ?>" src="<?php echo esc_url( $gloskin_map_embed ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
				<?php elseif ( $gloskin_map_url ) : ?>
					<a class="gloskin-clinic-detail__map-fallback" href="<?php echo esc_url( $gloskin_map_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Buka lokasi klinik', 'gloskin-site-core' ); ?> <span aria-hidden="true">→</span></a>
				<?php else : ?>
					<span class="gloskin-clinic-detail__media-empty" aria-hidden="true"></span>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="gloskin-clinic-contact" data-gloskin-section="clinic-contact">
		<div class="gloskin-ui1-container gloskin-clinic-contact__inner">
			<div class="gloskin-clinic-contact__copy">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Hubungi Cabang', 'gloskin-site-core' ); ?></p>
				<h2><?php echo esc_html__( 'Gunakan kanal yang tersedia untuk melanjutkan.', 'gloskin-site-core' ); ?></h2>
				<p><?php echo esc_html__( 'Hubungi cabang melalui WhatsApp bila tersedia, atau gunakan halaman kontak Gloskin untuk pertanyaan lebih lanjut.', 'gloskin-site-core' ); ?></p>
			</div>
			<a class="gloskin-clinic-contact__action" href="<?php echo esc_url( $gloskin_contact_url ); ?>"<?php echo $gloskin_whatsapp_url ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $gloskin_contact_label ); ?> <span aria-hidden="true">→</span></a>
		</div>
	</section>

	<section class="gloskin-ui1-dark-consultation" data-gloskin-section="clinic-consultation">
		<div class="gloskin-ui1-container">
			<div class="gloskin-ui1-dark-consultation__inner">
				<div class="gloskin-ui1-dark-consultation__copy">
					<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Konsultasi', 'gloskin-site-core' ); ?></p>
					<h2><?php echo esc_html__( 'Siap Membicarakan Kebutuhan Kulit Anda?', 'gloskin-site-core' ); ?></h2>
					<p><?php echo esc_html__( 'Pilih klinik Gloskin terdekat atau hubungi tim kami untuk menjadwalkan konsultasi.', 'gloskin-site-core' ); ?></p>
				</div>
				<div class="gloskin-ui1-dark-consultation__actions">
					<a class="gloskin-ui1-dark-consultation__button gloskin-ui1-dark-consultation__button--primary" href="<?php echo esc_url( home_url( '/clinics/' ) ); ?>"><?php echo esc_html__( 'Pilih Klinik', 'gloskin-site-core' ); ?></a>
					<a class="gloskin-ui1-dark-consultation__button gloskin-ui1-dark-consultation__button--secondary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Hubungi Kami', 'gloskin-site-core' ); ?></a>
				</div>
			</div>
		</div>
	</section>
</div>
