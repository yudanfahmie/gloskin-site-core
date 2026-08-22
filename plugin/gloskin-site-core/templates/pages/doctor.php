<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_post           = isset( $gloskin_context['post'] ) && $gloskin_context['post'] instanceof WP_Post ? $gloskin_context['post'] : null;
$gloskin_degree         = trim( (string) ( $gloskin_context['degree_title'] ?? '' ) );
$gloskin_specialization = trim( (string) ( $gloskin_context['specialization'] ?? '' ) );
$gloskin_profile        = trim( (string) ( $gloskin_context['profile'] ?? '' ) );
$gloskin_credentials    = trim( (string) ( $gloskin_context['credentials'] ?? '' ) );
$gloskin_sip            = trim( (string) ( $gloskin_context['sip_number'] ?? '' ) );
$gloskin_schedule       = trim( (string) ( $gloskin_context['schedule'] ?? '' ) );
$gloskin_image_id       = absint( $gloskin_context['image_id'] ?? 0 );
$gloskin_branches       = isset( $gloskin_context['branches'] ) && is_array( $gloskin_context['branches'] ) ? $gloskin_context['branches'] : array();
$gloskin_treatments     = isset( $gloskin_context['treatments'] ) && is_array( $gloskin_context['treatments'] ) ? $gloskin_context['treatments'] : array();
$gloskin_booking_url    = trim( (string) ( $gloskin_context['booking_target'] ?? '' ) );
$gloskin_booking_url    = '' !== $gloskin_booking_url ? $gloskin_booking_url : home_url( '/contact/' );
$gloskin_clinics_url    = home_url( '/clinics/' );
$gloskin_title          = $gloskin_post ? get_the_title( $gloskin_post ) : __( 'Dokter Gloskin', 'gloskin-site-core' );
$gloskin_has_content    = $gloskin_post && gloskin_ui1_has_content( $gloskin_post );
$gloskin_has_professional = $gloskin_has_content || '' !== $gloskin_profile || '' !== $gloskin_credentials || '' !== $gloskin_sip || '' !== $gloskin_schedule || $gloskin_branches || $gloskin_treatments;
?>
<div class="gloskin-doctor-single">
	<section class="gloskin-doctor-single__hero" data-gloskin-section="doctor-hero">
		<div class="gloskin-ui1-container gloskin-doctor-single__hero-grid">
			<div class="gloskin-doctor-single__hero-copy">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Dokter Gloskin', 'gloskin-site-core' ); ?></p>
				<h1><?php echo esc_html( $gloskin_title ); ?></h1>
				<?php if ( '' !== $gloskin_degree || '' !== $gloskin_specialization ) : ?>
					<div class="gloskin-doctor-single__hero-meta">
						<?php if ( '' !== $gloskin_degree ) : ?><p class="gloskin-doctor-single__degree"><?php echo esc_html( $gloskin_degree ); ?></p><?php endif; ?>
						<?php if ( '' !== $gloskin_specialization ) : ?><p><?php echo esc_html( $gloskin_specialization ); ?></p><?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="gloskin-doctor-single__hero-media">
				<?php if ( $gloskin_image_id ) : ?>
					<?php
					echo wp_get_attachment_image(
						$gloskin_image_id,
						'large',
						false,
						array(
							'class'         => 'gloskin-doctor-single__hero-image',
							'alt'           => $gloskin_title,
							'fetchpriority' => 'high',
							'decoding'      => 'async',
						)
					);
					?>
				<?php else : ?>
					<div class="gloskin-doctor-single__portrait-placeholder" aria-label="<?php echo esc_attr__( 'Foto dokter belum tersedia', 'gloskin-site-core' ); ?>">
						<?php echo gloskin_ui1_empty_state_icon( 'doctor' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical static SVG. ?>
						<span><?php echo esc_html__( 'Foto dokter belum tersedia.', 'gloskin-site-core' ); ?></span>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="gloskin-doctor-single__detail" data-gloskin-section="doctor-professional-detail">
		<div class="gloskin-ui1-container gloskin-doctor-single__detail-grid">
			<div class="gloskin-doctor-single__detail-copy">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Profil Profesional', 'gloskin-site-core' ); ?></p>

				<?php if ( $gloskin_has_professional ) : ?>
					<h2><?php echo esc_html__( 'Informasi Profesional', 'gloskin-site-core' ); ?></h2>

					<?php if ( $gloskin_has_content ) : ?>
						<div class="gloskin-doctor-single__content"><?php gloskin_ui1_render_page_content( $gloskin_post ); ?></div>
					<?php endif; ?>

					<?php if ( '' !== $gloskin_profile ) : ?>
						<div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_profile ); ?></div>
					<?php endif; ?>

					<?php if ( '' !== $gloskin_credentials || '' !== $gloskin_sip || '' !== $gloskin_schedule || $gloskin_branches || $gloskin_treatments ) : ?>
						<dl class="gloskin-doctor-single__facts">
							<?php if ( '' !== $gloskin_credentials ) : ?>
								<div class="gloskin-doctor-single__fact"><dt><?php echo esc_html__( 'Kredensial', 'gloskin-site-core' ); ?></dt><dd><?php echo wp_kses_post( $gloskin_credentials ); ?></dd></div>
							<?php endif; ?>
							<?php if ( '' !== $gloskin_sip ) : ?>
								<div class="gloskin-doctor-single__fact"><dt>SIP</dt><dd><?php echo esc_html( $gloskin_sip ); ?></dd></div>
							<?php endif; ?>
							<?php if ( '' !== $gloskin_schedule ) : ?>
								<div class="gloskin-doctor-single__fact"><dt><?php echo esc_html__( 'Jadwal', 'gloskin-site-core' ); ?></dt><dd><?php echo nl2br( esc_html( $gloskin_schedule ) ); ?></dd></div>
							<?php endif; ?>
							<?php if ( $gloskin_branches ) : ?>
								<div class="gloskin-doctor-single__fact">
									<dt><?php echo esc_html__( 'Lokasi Praktik', 'gloskin-site-core' ); ?></dt>
									<dd><ul class="gloskin-doctor-single__link-list">
										<?php foreach ( $gloskin_branches as $gloskin_branch ) : ?>
											<?php if ( is_array( $gloskin_branch ) && ! empty( $gloskin_branch['title'] ) && ! empty( $gloskin_branch['url'] ) ) : ?><li><a href="<?php echo esc_url( (string) $gloskin_branch['url'] ); ?>"><?php echo esc_html( (string) $gloskin_branch['title'] ); ?></a></li><?php endif; ?>
										<?php endforeach; ?>
									</ul></dd>
								</div>
							<?php endif; ?>
							<?php if ( $gloskin_treatments ) : ?>
								<div class="gloskin-doctor-single__fact">
									<dt><?php echo esc_html__( 'Perawatan Terkait', 'gloskin-site-core' ); ?></dt>
									<dd><ul class="gloskin-doctor-single__link-list">
										<?php foreach ( $gloskin_treatments as $gloskin_treatment ) : ?>
											<?php if ( is_array( $gloskin_treatment ) && ! empty( $gloskin_treatment['title'] ) && ! empty( $gloskin_treatment['url'] ) ) : ?><li><a href="<?php echo esc_url( (string) $gloskin_treatment['url'] ); ?>"><?php echo esc_html( (string) $gloskin_treatment['title'] ); ?></a></li><?php endif; ?>
										<?php endforeach; ?>
									</ul></dd>
								</div>
							<?php endif; ?>
						</dl>
					<?php endif; ?>
				<?php else : ?>
					<h2><?php echo esc_html__( 'Detail Tambahan Belum Tersedia untuk Ditampilkan.', 'gloskin-site-core' ); ?></h2>
					<p class="gloskin-doctor-single__sparse-copy"><?php echo esc_html__( 'Anda tetap dapat melihat jaringan klinik atau menggunakan halaman kontak untuk menentukan langkah berikutnya.', 'gloskin-site-core' ); ?></p>
				<?php endif; ?>

				<div class="gloskin-doctor-single__detail-action"><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( $gloskin_clinics_url ); ?>"><?php echo esc_html__( 'Lihat Klinik', 'gloskin-site-core' ); ?></a></div>
			</div>

			<div class="gloskin-doctor-single__detail-media" aria-hidden="true">
				<?php gloskin_ui1_render_editorial_media( 'treatment', 'treatment_clinical', 'gloskin-doctor-single__detail-image' ); ?>
			</div>
		</div>
	</section>

	<section class="gloskin-doctor-single__transition" data-gloskin-section="doctor-consultation-transition">
		<div class="gloskin-ui1-container gloskin-doctor-single__transition-inner">
			<div class="gloskin-doctor-single__transition-copy">
				<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Konsultasi', 'gloskin-site-core' ); ?></p>
				<h2><?php echo esc_html__( 'Lanjutkan Melalui Jalur Konsultasi yang Tersedia.', 'gloskin-site-core' ); ?></h2>
				<p><?php echo esc_html__( 'Buka tujuan konsultasi pada profil ini atau gunakan halaman kontak Gloskin untuk informasi lebih lanjut.', 'gloskin-site-core' ); ?></p>
			</div>
			<div class="gloskin-doctor-single__transition-action">
				<a class="gloskin-doctor-single__transition-link" href="<?php echo esc_url( $gloskin_booking_url ); ?>"><?php echo esc_html__( 'Lanjutkan Konsultasi', 'gloskin-site-core' ); ?><?php echo gloskin_ui1_arrow_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical static SVG. ?></a>
			</div>
		</div>
	</section>
	<?php /* The shared footer immediately following <main> owns the one canonical
	          dark consultation CTA; single Doctor deliberately does not duplicate it. */ ?>
</div>
