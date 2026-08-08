<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) || $gloskin_context['products'] ) : ?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
		<?php if ( $gloskin_context['products'] ) : ?>
			<div class="gloskin-ui1-grid gloskin-ui1-grid--cards"><?php foreach ( $gloskin_context['products'] as $product ) { gloskin_ui1_render_product_card( $product ); } ?></div>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>
<?php if ( ! $gloskin_context['products'] ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft">
	<div class="gloskin-ui1-container gloskin-ui1-container--narrow">
		<?php gloskin_ui1_render_discovery_panel( __( 'Explore skincare', 'gloskin-site-core' ), __( 'Browse all Gloskin skincare categories.', 'gloskin-site-core' ), __( 'View skincare', 'gloskin-site-core' ), home_url( '/skincare/' ) ); ?>
	</div>
</section>
<?php endif; ?>
