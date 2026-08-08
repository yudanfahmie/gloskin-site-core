<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) || $gloskin_context['products'] ) : ?><section class="gloskin-ui1-section"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?><?php if ( $gloskin_context['products'] ) : ?><div class="gloskin-ui1-grid gloskin-ui1-grid--cards"><?php foreach ( $gloskin_context['products'] as $product ) { gloskin_ui1_render_product_card( $product ); } ?></div><?php endif; ?></div></section><?php endif; ?>
<?php if ( ! $gloskin_context['products'] ) : ?><section class="gloskin-ui1-section gloskin-ui1-section--soft"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_editorial_split( __( 'Skincare Gloskin', 'gloskin-site-core' ), __( 'Lanjutkan ke kategori skincare lainnya.', 'gloskin-site-core' ), __( 'Jelajahi tujuh kategori skincare Gloskin untuk melihat pilihan yang ditampilkan pada situs.', 'gloskin-site-core' ), __( 'Jelajahi Skincare', 'gloskin-site-core' ), home_url( '/skincare/' ), 'skincare', true ); ?></div></section><?php endif; ?>
