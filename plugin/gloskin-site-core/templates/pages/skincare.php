<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<section class="gloskin-ui1-section"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?><?php gloskin_ui1_render_section_heading( __( 'Kategori Skincare', 'gloskin-site-core' ), __( 'Temukan kategori sesuai kebutuhan perawatan harian.', 'gloskin-site-core' ) ); ?><div class="gloskin-ui1-grid gloskin-ui1-grid--categories"><?php foreach ( $gloskin_context['mappings'] as $mapping ) { gloskin_ui1_render_category_link( $mapping ); } ?></div></div></section>
<?php if ( $gloskin_context['products'] ) : ?><section class="gloskin-ui1-section gloskin-ui1-section--soft"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Produk', 'gloskin-site-core' ) ); ?><div class="gloskin-ui1-grid gloskin-ui1-grid--cards"><?php foreach ( $gloskin_context['products'] as $product ) { gloskin_ui1_render_product_card( $product ); } ?></div></div></section><?php endif; ?>
