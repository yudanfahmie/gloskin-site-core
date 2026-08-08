<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?><section class="gloskin-ui1-section"><div class="gloskin-ui1-container gloskin-ui1-container--narrow"><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?></div></section><?php endif; ?>
<?php if ( $gloskin_context['doctors'] ) : ?><section class="gloskin-ui1-section"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Profil Dokter', 'gloskin-site-core' ) ); gloskin_ui1_render_card_grid( $gloskin_context['doctors'], 'doctor' ); ?></div></section><?php else : ?><section class="gloskin-ui1-section"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_editorial_split( __( 'Sebelum berkonsultasi', 'gloskin-site-core' ), __( 'Mulai dari klinik yang ingin Anda kunjungi.', 'gloskin-site-core' ), __( 'Jelajahi jaringan klinik Gloskin untuk menemukan lokasi dan kanal kontak yang dapat digunakan untuk kebutuhan konsultasi.', 'gloskin-site-core' ), __( 'Temukan Klinik', 'gloskin-site-core' ), home_url( '/clinics/' ), 'doctor', true ); ?></div></section><?php endif; ?>
