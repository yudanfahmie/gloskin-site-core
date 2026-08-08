<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
		<?php gloskin_ui1_render_section_heading( __( 'Skincare categories', 'gloskin-site-core' ) ); ?>
		<div class="gloskin-ui1-grid gloskin-ui1-grid--categories">
			<?php foreach ( $gloskin_context['mappings'] as $mapping ) : ?>
				<a class="gloskin-ui1-category-link" href="<?php echo esc_url( (string) $mapping['url'] ); ?>">
					<span><?php echo esc_html( (string) $mapping['label'] ); ?></span><span aria-hidden="true">→</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<section class="gloskin-ui1-section gloskin-ui1-section--soft">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Products', 'gloskin-site-core' ) ); ?>
		<?php if ( ! $gloskin_context['woo_ready'] ) : ?>
			<?php gloskin_ui1_empty( __( 'WooCommerce product data is currently unavailable.', 'gloskin-site-core' ) ); ?>
		<?php elseif ( $gloskin_context['products'] ) : ?>
			<div class="gloskin-ui1-grid gloskin-ui1-grid--cards"><?php foreach ( $gloskin_context['products'] as $product ) { gloskin_ui1_render_product_card( $product ); } ?></div>
		<?php else : ?>
			<?php gloskin_ui1_empty( __( 'No published WooCommerce products are available yet.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>
