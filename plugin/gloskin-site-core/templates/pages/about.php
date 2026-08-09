<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
$has_principles = $gloskin_context['vision'] || $gloskin_context['mission'] || $gloskin_context['values'];
$about_clinics  = gloskin_ui1_real_cards( $gloskin_context['clinics'] );
?>
<section class="gloskin-ui1-section" data-gloskin-section="about-story"><div class="gloskin-ui1-container">
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) || $has_principles ) : ?>
	<div class="gloskin-ui1-container--narrow"><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?></div>
	<?php if ( $has_principles ) : ?><div class="gloskin-ui1-grid gloskin-ui1-grid--three gloskin-ui1-about-principles"><?php if ( $gloskin_context['vision'] ) : ?><article><h2><?php echo esc_html__( 'Visi', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['vision'] ); ?></div></article><?php endif; ?><?php if ( $gloskin_context['mission'] ) : ?><article><h2><?php echo esc_html__( 'Misi', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['mission'] ); ?></div></article><?php endif; ?><?php if ( $gloskin_context['values'] ) : ?><article><h2><?php echo esc_html__( 'Nilai', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['values'] ); ?></div></article><?php endif; ?></div><?php endif; ?>
<?php else : ?>
	<?php gloskin_ui1_render_editorial_split( __( 'Tentang Gloskin', 'gloskin-site-core' ), __( 'Kenali Gloskin melalui jaringan klinik dan layanan yang tersedia.', 'gloskin-site-core' ), __( 'Dari sini Anda dapat melanjutkan ke lokasi klinik, profil dokter, informasi perawatan, atau kategori skincare.', 'gloskin-site-core' ), __( 'Lihat Jaringan Klinik', 'gloskin-site-core' ), home_url( '/clinics/' ), 'editorial' ); ?>
<?php endif; ?>
</div></section>
<?php if ( $about_clinics ) : ?><section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="about-clinics"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Jaringan Klinik', 'gloskin-site-core' ), __( 'Pilih lokasi Gloskin dan buka halaman cabang untuk melihat informasi yang tersedia.', 'gloskin-site-core' ) ); gloskin_ui1_render_card_grid( $about_clinics, 'clinic' ); ?></div></section><?php endif; ?>
<?php if ( $gloskin_context['doctors'] ) : ?><section class="gloskin-ui1-section" data-gloskin-section="about-doctors"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Profil Profesional', 'gloskin-site-core' ), __( 'Kenali dokter melalui profil profesional yang tersedia.', 'gloskin-site-core' ) ); gloskin_ui1_render_card_grid( $gloskin_context['doctors'], 'doctor' ); ?></div></section><?php endif; ?>
<section class="gloskin-ui1-section gloskin-ui1-section--cta" data-gloskin-section="about-closing"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_closing_cta( __( 'Langkah Berikutnya', 'gloskin-site-core' ), __( 'Pilih lokasi atau hubungi Gloskin saat Anda siap melanjutkan.', 'gloskin-site-core' ), __( 'Halaman klinik memuat kanal kontak dan informasi cabang yang tersedia.', 'gloskin-site-core' ), __( 'Pilih Klinik', 'gloskin-site-core' ), home_url( '/clinics/' ), __( 'Hubungi Gloskin', 'gloskin-site-core' ), home_url( '/contact/' ) ); ?></div></section>
