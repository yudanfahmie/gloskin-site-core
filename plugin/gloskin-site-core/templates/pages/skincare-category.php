<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
		<?php if ( ! $gloskin_context['woo_ready'] ) : ?>
			<?php gloskin_ui1_empty( __( 'WooCommerce product data is currently unavailable.', 'gloskin-site-core' ) ); ?>
		<?php elseif ( ! $gloskin_context['category_exists'] ) : ?>
			<?php gloskin_ui1_empty( __( 'The mapped WooCommerce category has not been configured on this site yet.', 'gloskin-site-core' ) ); ?>
		<?php elseif ( $gloskin_context['products'] ) : ?>
			<div class="gloskin-ui1-grid gloskin-ui1-grid--cards"><?php foreach ( $gloskin_context['products'] as $product ) { gloskin_ui1_render_product_card( $product ); } ?></div>
		<?php else : ?>
			<?php gloskin_ui1_empty( __( 'No published products are available in this category.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>
