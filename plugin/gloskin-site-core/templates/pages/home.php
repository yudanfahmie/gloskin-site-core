<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
	</div>
</section>

<section class="gloskin-ui1-section gloskin-ui1-section--soft">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Treatments', 'gloskin-site-core' ), __( 'Browse treatment categories published with approved Gloskin content.', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_context['treatments'] ) : ?>
			<?php gloskin_ui1_render_card_grid( $gloskin_context['treatments'], 'treatment' ); ?>
		<?php else : ?>
			<?php gloskin_ui1_empty( __( 'No approved treatment categories are published yet.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
		<p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/treatments/' ) ); ?>"><?php echo esc_html__( 'View treatments', 'gloskin-site-core' ); ?> →</a></p>
	</div>
</section>

<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Clinic network', 'gloskin-site-core' ), __( 'Explore the nine Gloskin branch identities documented for the website.', 'gloskin-site-core' ) ); ?>
		<?php gloskin_ui1_render_card_grid( $gloskin_context['clinics'], 'clinic' ); ?>
	</div>
</section>

<section class="gloskin-ui1-section gloskin-ui1-section--contrast">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Doctors', 'gloskin-site-core' ), __( 'Doctor profiles appear when approved identity and professional information is published.', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_context['doctors'] ) : ?>
			<?php gloskin_ui1_render_card_grid( $gloskin_context['doctors'], 'doctor' ); ?>
		<?php else : ?>
			<?php gloskin_ui1_empty( __( 'No approved doctor profiles are published yet.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
		<p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/doctors/' ) ); ?>"><?php echo esc_html__( 'View doctors', 'gloskin-site-core' ); ?> →</a></p>
	</div>
</section>

<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Skincare', 'gloskin-site-core' ), __( 'Explore the seven documented skincare landing groups connected to WooCommerce categories.', 'gloskin-site-core' ) ); ?>
		<div class="gloskin-ui1-grid gloskin-ui1-grid--categories">
			<?php foreach ( $gloskin_context['skincare'] as $mapping ) : ?>
				<a class="gloskin-ui1-category-link" href="<?php echo esc_url( (string) $mapping['url'] ); ?>">
					<span><?php echo esc_html( (string) $mapping['label'] ); ?></span><span aria-hidden="true">→</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="gloskin-ui1-section gloskin-ui1-section--soft">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Shop', 'gloskin-site-core' ) ); ?>
		<?php if ( ! $gloskin_context['woo_ready'] ) : ?>
			<?php gloskin_ui1_empty( __( 'WooCommerce product data is currently unavailable.', 'gloskin-site-core' ) ); ?>
		<?php elseif ( $gloskin_context['products'] ) : ?>
			<div class="gloskin-ui1-grid gloskin-ui1-grid--cards">
				<?php foreach ( $gloskin_context['products'] as $product ) { gloskin_ui1_render_product_card( $product ); } ?>
			</div>
		<?php else : ?>
			<?php gloskin_ui1_empty( __( 'No published WooCommerce products are available yet.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
		<p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Visit shop', 'gloskin-site-core' ); ?> →</a></p>
	</div>
</section>

<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Insights', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_context['insights'] ) : ?>
			<?php gloskin_ui1_render_card_grid( $gloskin_context['insights'], 'insight' ); ?>
		<?php else : ?>
			<?php gloskin_ui1_empty( __( 'No Insights posts are published yet.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>

<section class="gloskin-ui1-section gloskin-ui1-section--cta">
	<div class="gloskin-ui1-container gloskin-ui1-cta">
		<div>
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Contact', 'gloskin-site-core' ); ?></p>
			<h2><?php echo esc_html__( 'Choose a Gloskin clinic.', 'gloskin-site-core' ); ?></h2>
		</div>
		<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Contact Gloskin', 'gloskin-site-core' ); ?></a>
	</div>
</section>
