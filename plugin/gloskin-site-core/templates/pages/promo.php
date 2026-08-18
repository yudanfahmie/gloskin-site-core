<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<section class="gloskin-ui1-section" data-gloskin-section="promo-content"><div class="gloskin-ui1-container gloskin-ui1-container--narrow">
	<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?>
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
	<?php else : ?>
		<div class="gloskin-ui1-empty"><?php echo esc_html__( 'Informasi promo akan ditampilkan di halaman ini saat tersedia.', 'gloskin-site-core' ); ?></div>
	<?php endif; ?>
</div></section>
<section class="gloskin-ui1-section gloskin-ui1-section--soft" data-gloskin-section="promo-pathways"><div class="gloskin-ui1-container">
	<?php gloskin_ui1_render_pathway_grid( array(
		array( 'eyebrow' => __( 'Perawatan', 'gloskin-site-core' ), 'title' => __( 'Jelajahi pilihan perawatan', 'gloskin-site-core' ), 'copy' => __( 'Kenali informasi perawatan sebelum konsultasi.', 'gloskin-site-core' ), 'label' => __( 'Lihat Perawatan', 'gloskin-site-core' ), 'url' => home_url( '/treatments/' ) ),
		array( 'eyebrow' => __( 'Skincare', 'gloskin-site-core' ), 'title' => __( 'Temukan skincare Gloskin', 'gloskin-site-core' ), 'copy' => __( 'Jelajahi kategori dan produk yang tersedia.', 'gloskin-site-core' ), 'label' => __( 'Lihat Skincare', 'gloskin-site-core' ), 'url' => home_url( '/skincare/' ) ),
	) ); ?>
</div></section>
