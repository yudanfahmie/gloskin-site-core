<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$hero = $gloskin_context['hero'];
if ( empty( $hero['copy'] ) ) { $hero['copy'] = __( 'Browse Gloskin skincare categories.', 'gloskin-site-core' ); }
gloskin_ui1_render_hero( $hero );
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
<?php if ( $gloskin_context['products'] ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Products', 'gloskin-site-core' ) ); ?>
		<div class="gloskin-ui1-grid gloskin-ui1-grid--cards"><?php foreach ( $gloskin_context['products'] as $product ) { gloskin_ui1_render_product_card( $product ); } ?></div>
	</div>
</section>
<?php endif; ?>
