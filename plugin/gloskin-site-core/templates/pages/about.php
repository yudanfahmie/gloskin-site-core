<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$hero = $gloskin_context['hero'];
$legacy_copy = __( 'Learn about the Gloskin clinic network and team information that has been approved for publication.', 'gloskin-site-core' );
if ( isset( $hero['copy'] ) && $legacy_copy === $hero['copy'] ) {
	$hero['copy'] = __( 'Explore the Gloskin clinic network and information about the team.', 'gloskin-site-core' );
}
gloskin_ui1_render_hero( $hero );
$has_principles = $gloskin_context['vision'] || $gloskin_context['mission'] || $gloskin_context['values'];
?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) || $has_principles ) : ?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container gloskin-ui1-container--narrow">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
		<?php if ( $has_principles ) : ?>
			<div class="gloskin-ui1-grid gloskin-ui1-grid--three gloskin-ui1-about-principles">
				<?php if ( $gloskin_context['vision'] ) : ?><article><h2><?php echo esc_html__( 'Vision', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['vision'] ); ?></div></article><?php endif; ?>
				<?php if ( $gloskin_context['mission'] ) : ?><article><h2><?php echo esc_html__( 'Mission', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['mission'] ); ?></div></article><?php endif; ?>
				<?php if ( $gloskin_context['values'] ) : ?><article><h2><?php echo esc_html__( 'Values', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['values'] ); ?></div></article><?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Clinic network', 'gloskin-site-core' ), __( 'Explore Gloskin clinic locations.', 'gloskin-site-core' ) ); ?>
		<?php gloskin_ui1_render_card_grid( $gloskin_context['clinics'], 'clinic' ); ?>
	</div>
</section>
<?php if ( $gloskin_context['doctors'] ) : ?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Medical team', 'gloskin-site-core' ) ); ?>
		<?php gloskin_ui1_render_card_grid( $gloskin_context['doctors'], 'doctor' ); ?>
	</div>
</section>
<?php endif; ?>
