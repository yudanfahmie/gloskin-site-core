<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<section class="gloskin-ui1-section"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?><?php gloskin_ui1_render_section_heading( __( 'Pilih Lokasi Gloskin', 'gloskin-site-core' ), __( 'Buka halaman klinik untuk melihat informasi yang tersedia pada setiap lokasi.', 'gloskin-site-core' ) ); ?><?php gloskin_ui1_render_card_grid( $gloskin_context['clinics'], 'clinic' ); ?></div></section>
