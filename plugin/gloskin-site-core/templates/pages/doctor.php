<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$post = $gloskin_context['post'];
$has_details = gloskin_ui1_has_content( $post ) || $gloskin_context['profile'] || $gloskin_context['credentials'] || $gloskin_context['sip_number'] || $gloskin_context['schedule'] || $gloskin_context['booking_target'];
$has_related = ! empty( $gloskin_context['branches'] ) || ! empty( $gloskin_context['treatments'] );
?>
<section class="gloskin-ui1-detail-hero">
	<div class="gloskin-ui1-container gloskin-ui1-detail-hero__grid">
		<div>
			<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Doctor', 'gloskin-site-core' ); ?></p>
			<h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>
			<?php if ( $gloskin_context['degree_title'] ) : ?><p class="gloskin-ui1-lead"><?php echo esc_html( $gloskin_context['degree_title'] ); ?></p><?php endif; ?>
			<?php if ( $gloskin_context['specialization'] ) : ?><p><?php echo esc_html( $gloskin_context['specialization'] ); ?></p><?php endif; ?>
		</div>
		<?php if ( $gloskin_context['image_id'] ) : ?><div><?php echo wp_get_attachment_image( $gloskin_context['image_id'], 'large', false, array( 'class' => 'gloskin-ui1-detail-image' ) ); ?></div><?php endif; ?>
	</div>
</section>
<?php if ( $has_details ) : ?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container gloskin-ui1-container--narrow">
		<?php gloskin_ui1_render_page_content( $post ); ?>
		<?php if ( $gloskin_context['profile'] ) : ?><div class="gloskin-ui1-detail-block"><h2><?php echo esc_html__( 'Profile', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['profile'] ); ?></div></div><?php endif; ?>
		<?php if ( $gloskin_context['credentials'] ) : ?><div class="gloskin-ui1-detail-block"><h2><?php echo esc_html__( 'Credentials', 'gloskin-site-core' ); ?></h2><div class="gloskin-ui1-prose"><?php echo wp_kses_post( $gloskin_context['credentials'] ); ?></div></div><?php endif; ?>
		<?php if ( $gloskin_context['sip_number'] ) : ?><p><strong><?php echo esc_html__( 'SIP', 'gloskin-site-core' ); ?>:</strong> <?php echo esc_html( $gloskin_context['sip_number'] ); ?></p><?php endif; ?>
		<?php if ( $gloskin_context['schedule'] ) : ?><div class="gloskin-ui1-detail-block"><h2><?php echo esc_html__( 'Schedule', 'gloskin-site-core' ); ?></h2><p><?php echo nl2br( esc_html( $gloskin_context['schedule'] ) ); ?></p></div><?php endif; ?>
		<?php if ( $gloskin_context['booking_target'] ) : ?><p><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( $gloskin_context['booking_target'] ); ?>"><?php echo esc_html__( 'Book / Contact', 'gloskin-site-core' ); ?></a></p><?php endif; ?>
	</div>
</section>
<?php endif; ?>
<?php if ( $gloskin_context['branches'] ) : ?><section class="gloskin-ui1-section gloskin-ui1-section--soft"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Practice branches', 'gloskin-site-core' ) ); gloskin_ui1_render_card_grid( $gloskin_context['branches'], 'clinic' ); ?></div></section><?php endif; ?>
<?php if ( $gloskin_context['treatments'] ) : ?><section class="gloskin-ui1-section"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Related treatments', 'gloskin-site-core' ) ); gloskin_ui1_render_card_grid( $gloskin_context['treatments'], 'treatment' ); ?></div></section><?php endif; ?>

<?php if ( ! $has_details && ! $has_related ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--tight"><div class="gloskin-ui1-container gloskin-ui1-container--narrow"><?php gloskin_ui1_render_discovery_panel( __( 'Find a Gloskin clinic', 'gloskin-site-core' ), __( 'Explore clinic locations and available contact information.', 'gloskin-site-core' ), __( 'View clinics', 'gloskin-site-core' ), home_url( '/clinics/' ) ); ?></div></section>
<?php endif; ?>
