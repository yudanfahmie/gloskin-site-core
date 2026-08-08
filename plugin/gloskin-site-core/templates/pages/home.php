<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container gloskin-ui1-container--narrow">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $gloskin_context['treatments'] ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Treatments', 'gloskin-site-core' ), __( 'Explore Gloskin treatment information by category.', 'gloskin-site-core' ) ); ?>
		<?php gloskin_ui1_render_card_grid( $gloskin_context['treatments'], 'treatment' ); ?>
		<p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/treatments/' ) ); ?>"><?php echo esc_html__( 'View treatments', 'gloskin-site-core' ); ?> →</a></p>
	</div>
</section>
<?php endif; ?>

<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Clinic network', 'gloskin-site-core' ), __( 'Explore Gloskin clinic locations.', 'gloskin-site-core' ) ); ?>
		<?php gloskin_ui1_render_card_grid( $gloskin_context['clinics'], 'clinic' ); ?>
	</div>
</section>

<?php if ( $gloskin_context['doctors'] ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--contrast">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Doctors', 'gloskin-site-core' ), __( 'Meet the Gloskin medical team.', 'gloskin-site-core' ) ); ?>
		<?php gloskin_ui1_render_card_grid( $gloskin_context['doctors'], 'doctor' ); ?>
		<p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/doctors/' ) ); ?>"><?php echo esc_html__( 'View doctors', 'gloskin-site-core' ); ?> →</a></p>
	</div>
</section>
<?php endif; ?>

<section class="gloskin-ui1-section gloskin-ui1-section--soft">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Skincare', 'gloskin-site-core' ), __( 'Browse skincare categories.', 'gloskin-site-core' ) ); ?>
		<div class="gloskin-ui1-grid gloskin-ui1-grid--categories">
			<?php foreach ( $gloskin_context['skincare'] as $mapping ) : ?>
				<a class="gloskin-ui1-category-link" href="<?php echo esc_url( (string) $mapping['url'] ); ?>">
					<span><?php echo esc_html( (string) $mapping['label'] ); ?></span><span aria-hidden="true">→</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php if ( $gloskin_context['products'] ) : ?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Shop', 'gloskin-site-core' ) ); ?>
		<div class="gloskin-ui1-grid gloskin-ui1-grid--cards">
			<?php foreach ( $gloskin_context['products'] as $product ) { gloskin_ui1_render_product_card( $product ); } ?>
		</div>
		<p class="gloskin-ui1-section__action"><a class="gloskin-ui1-text-link" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Visit shop', 'gloskin-site-core' ); ?> →</a></p>
	</div>
</section>
<?php endif; ?>

<?php if ( $gloskin_context['insights'] ) : ?>
<section class="gloskin-ui1-section<?php echo $gloskin_context['products'] ? ' gloskin-ui1-section--soft' : ''; ?>">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Insights', 'gloskin-site-core' ) ); ?>
		<?php gloskin_ui1_render_card_grid( $gloskin_context['insights'], 'insight' ); ?>
	</div>
</section>
<?php endif; ?>

<section class="gloskin-ui1-section gloskin-ui1-section--cta">
	<div class="gloskin-ui1-container gloskin-ui1-cta">
		<div>
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Contact', 'gloskin-site-core' ); ?></p>
			<h2><?php echo esc_html__( 'Find a Gloskin clinic.', 'gloskin-site-core' ); ?></h2>
		</div>
		<a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Explore clinic contacts', 'gloskin-site-core' ); ?></a>
	</div>
</section>
