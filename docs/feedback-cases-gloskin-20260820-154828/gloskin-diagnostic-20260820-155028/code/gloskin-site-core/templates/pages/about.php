<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
$gloskin_has_principles = $gloskin_context['vision'] || $gloskin_context['mission'] || $gloskin_context['values'];
$gloskin_about_clinics  = gloskin_ui1_real_cards( $gloskin_context['clinics'] );
$gloskin_founder        = $gloskin_context['founder'];
?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?>
<section class="gloskin-ui1-section" data-gloskin-section="about-story"><div class="gloskin-ui1-container">
	<div class="gloskin-ui1-about-story">
		<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Cerita Gloskin', 'gloskin-site-core' ); ?></p>
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
	</div>
</div></section>
<?php endif; ?>
<?php if ( $gloskin_founder ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="about-founder"><div class="gloskin-ui1-container">
	<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Pendiri', 'gloskin-site-core' ); ?></p>
	<div class="gloskin-ui1-about-founder">
		<?php if ( $gloskin_founder['media_id'] ) : ?>
			<div class="gloskin-ui1-about-founder__media">
				<?php echo wp_get_attachment_image( $gloskin_founder['media_id'], 'large', false, array( 'class' => 'gloskin-ui1-about-founder__image', 'loading' => 'lazy' ) ); ?>
			</div>
		<?php endif; ?>
		<div class="gloskin-ui1-about-founder__copy">
			<h2 class="gloskin-ui1-about-founder__name"><?php echo esc_html( $gloskin_founder['name'] ); ?></h2>
			<p class="gloskin-ui1-about-founder__role"><?php echo esc_html( $gloskin_founder['role'] ); ?></p>
			<?php if ( '' !== $gloskin_founder['story'] ) : ?>
				<div class="gloskin-ui1-prose"><?php echo wp_kses_post( wpautop( $gloskin_founder['story'] ) ); ?></div>
			<?php endif; ?>
		</div>
	</div>
</div></section>
<?php endif; ?>
<?php if ( $gloskin_has_principles ) : ?>
<section class="gloskin-ui1-section" data-gloskin-section="about-principles"><div class="gloskin-ui1-container">
	<div class="gloskin-ui1-grid gloskin-ui1-grid--three gloskin-ui1-about-principles">
		<?php if ( $gloskin_context['vision'] ) : ?><article><h2><?php echo esc_html__( 'Visi', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['vision'] ); ?></div></article><?php endif; ?>
		<?php if ( $gloskin_context['mission'] ) : ?><article><h2><?php echo esc_html__( 'Misi', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['mission'] ); ?></div></article><?php endif; ?>
		<?php if ( $gloskin_context['values'] ) : ?><article><h2><?php echo esc_html__( 'Nilai', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['values'] ); ?></div></article><?php endif; ?>
	</div>
</div></section>
<?php endif; ?>
<?php if ( $gloskin_context['doctors'] ) : ?><section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="about-doctors"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Tim Dokter', 'gloskin-site-core' ), __( 'Profil yang tampil di sini berasal dari data dokter yang dipublikasikan Gloskin.', 'gloskin-site-core' ) ); gloskin_ui1_render_card_grid( $gloskin_context['doctors'], 'doctor' ); ?></div></section><?php endif; ?>
<?php if ( $gloskin_about_clinics ) : ?><section class="gloskin-ui1-section" data-gloskin-section="about-clinics"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Jaringan Klinik', 'gloskin-site-core' ), __( 'Pilih lokasi Gloskin dan buka halaman cabang untuk melihat informasi yang tersedia.', 'gloskin-site-core' ) ); gloskin_ui1_render_card_grid( $gloskin_about_clinics, 'clinic' ); ?></div></section><?php endif; ?>
<?php gloskin_ui1_render_achievements( $gloskin_context['achievements'], 'full' ); ?>
<section class="gloskin-ui1-section gloskin-ui1-section--cta" data-gloskin-section="about-closing"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_closing_cta( __( 'Langkah Berikutnya', 'gloskin-site-core' ), __( 'Pilih lokasi atau hubungi Gloskin saat Anda siap melanjutkan.', 'gloskin-site-core' ), __( 'Halaman klinik memuat kanal kontak dan informasi cabang yang tersedia.', 'gloskin-site-core' ), __( 'Pilih Klinik', 'gloskin-site-core' ), home_url( '/clinics/' ), __( 'Hubungi Gloskin', 'gloskin-site-core' ), home_url( '/contact/' ) ); ?></div></section>
