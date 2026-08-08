<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container gloskin-ui1-container--narrow">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
		<?php if ( $gloskin_context['vision'] || $gloskin_context['mission'] || $gloskin_context['values'] ) : ?>
			<div class="gloskin-ui1-grid gloskin-ui1-grid--three gloskin-ui1-about-principles">
				<?php if ( $gloskin_context['vision'] ) : ?><article><h2><?php echo esc_html__( 'Vision', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['vision'] ); ?></div></article><?php endif; ?>
				<?php if ( $gloskin_context['mission'] ) : ?><article><h2><?php echo esc_html__( 'Mission', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['mission'] ); ?></div></article><?php endif; ?>
				<?php if ( $gloskin_context['values'] ) : ?><article><h2><?php echo esc_html__( 'Values', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['values'] ); ?></div></article><?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
<section class="gloskin-ui1-section gloskin-ui1-section--soft">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Clinic network', 'gloskin-site-core' ) ); ?>
		<?php gloskin_ui1_render_card_grid( $gloskin_context['clinics'], 'clinic' ); ?>
	</div>
</section>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Doctor team', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_context['doctors'] ) : ?>
			<?php gloskin_ui1_render_card_grid( $gloskin_context['doctors'], 'doctor' ); ?>
		<?php else : ?>
			<?php gloskin_ui1_empty( __( 'Approved doctor profiles have not been published yet.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>
