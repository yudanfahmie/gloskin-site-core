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
					<div class="gloskin-clinic-detail__facts" style="gap:0;margin-bottom:34px">
						<?php if ( ! empty( $gloskin_context['address'] ) ) : ?>
							<p style="grid-template-columns:44px minmax(0,1fr);column-gap:16px;row-gap:6px;padding:20px 0;border-bottom:1px solid color-mix(in srgb,var(--gloskin-border) 68%,transparent)">
								<span aria-hidden="true" style="grid-row:1/3;display:grid;width:40px;height:40px;place-items:center;border:1px solid color-mix(in srgb,var(--gloskin-brand-champagne) 52%,var(--gloskin-border));border-radius:999px;background:color-mix(in srgb,var(--gloskin-refresh-blush) 72%,var(--gloskin-bg));color:var(--gloskin-accent-readable)">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/><circle cx="12" cy="10" r="2.25"/></svg>
								</span>
								<strong style="grid-column:2"><?php echo esc_html__( 'Alamat', 'gloskin-site-core' ); ?></strong>
								<span style="grid-column:2"><?php echo nl2br( esc_html( (string) $gloskin_context['address'] ) ); ?></span>
							</p>
						<?php endif; ?>
						<?php if ( ! empty( $gloskin_context['phone_display'] ) ) : ?>
							<p style="grid-template-columns:44px minmax(0,1fr);column-gap:16px;row-gap:6px;padding:20px 0;border-bottom:1px solid color-mix(in srgb,var(--gloskin-border) 68%,transparent)">
								<span aria-hidden="true" style="grid-row:1/3;display:grid;width:40px;height:40px;place-items:center;border:1px solid color-mix(in srgb,var(--gloskin-brand-champagne) 52%,var(--gloskin-border));border-radius:999px;background:color-mix(in srgb,var(--gloskin-refresh-blush) 72%,var(--gloskin-bg));color:var(--gloskin-accent-readable)">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M6.7 3.8 9.4 7c.4.5.4 1.2 0 1.7L8 10.2c1.2 2.4 3.3 4.5 5.8 5.8l1.5-1.4c.5-.4 1.2-.4 1.7 0l3.2 2.7c.6.5.7 1.3.3 2-1 1.5-2.7 2.2-4.4 1.7C9.8 19.3 4.7 14.2 3 7.9c-.5-1.7.2-3.4 1.7-4.4.7-.4 1.5-.3 2 .3Z"/></svg>
								</span>
								<strong style="grid-column:2"><?php echo esc_html__( 'Telepon', 'gloskin-site-core' ); ?></strong>
								<span style="grid-column:2"><?php if ( ! empty( $gloskin_context['phone_url'] ) ) : ?><a href="<?php echo esc_url( (string) $gloskin_context['phone_url'] ); ?>"><?php echo esc_html( (string) $gloskin_context['phone_display'] ); ?></a><?php else : ?><?php echo esc_html( (string) $gloskin_context['phone_display'] ); ?><?php endif; ?></span>
							</p>
						<?php endif; ?>
						<?php if ( ! empty( $gloskin_context['operating_hours'] ) ) : ?>
							<p style="grid-template-columns:44px minmax(0,1fr);column-gap:16px;row-gap:6px;padding:20px 0;border-bottom:1px solid color-mix(in srgb,var(--gloskin-border) 68%,transparent)">
								<span aria-hidden="true" style="grid-row:1/3;display:grid;width:40px;height:40px;place-items:center;border:1px solid color-mix(in srgb,var(--gloskin-brand-champagne) 52%,var(--gloskin-border));border-radius:999px;background:color-mix(in srgb,var(--gloskin-refresh-blush) 72%,var(--gloskin-bg));color:var(--gloskin-accent-readable)">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="12" cy="12" r="8"/><path d="M12 7.5V12l3 2"/></svg>
								</span>
								<strong style="grid-column:2"><?php echo esc_html__( 'Jam Operasional', 'gloskin-site-core' ); ?></strong>
								<span style="grid-column:2"><?php echo nl2br( esc_html( (string) $gloskin_context['operating_hours'] ) ); ?></span>
							</p>
						<?php endif; ?>
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

</div>
