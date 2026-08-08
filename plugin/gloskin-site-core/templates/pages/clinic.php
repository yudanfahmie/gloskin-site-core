<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$post = $gloskin_context['post'];
$has_contact = $gloskin_context['address'] || $gloskin_context['phone_display'] || $gloskin_context['operating_hours'] || $gloskin_context['whatsapp_url'];
$has_location = $gloskin_context['map_embed'] || $gloskin_context['map_url'];
$has_details = gloskin_ui1_has_content( $post ) || $has_contact || $has_location;
$has_related = ! empty( $gloskin_context['doctors'] ) || ! empty( $gloskin_context['treatments'] );
?>
<section class="gloskin-ui1-detail-hero">
	<div class="gloskin-ui1-container">
		<p class="gloskin-ui1-eyebrow"><?php echo esc_html__( 'Clinic', 'gloskin-site-core' ); ?></p>
		<h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>
		<?php if ( $gloskin_context['short_location'] ) : ?><p class="gloskin-ui1-lead"><?php echo esc_html( $gloskin_context['short_location'] ); ?></p><?php endif; ?>
	</div>
</section>
<?php if ( $gloskin_context['gallery_ids'] ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--tight"><div class="gloskin-ui1-container"><div class="gloskin-ui1-gallery">
<?php foreach ( $gloskin_context['gallery_ids'] as $image_id ) { echo wp_get_attachment_image( $image_id, 'large', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-gallery__image' ) ); } ?>
</div></div></section>
<?php endif; ?>
<?php if ( $has_details ) : ?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container<?php echo $has_location ? ' gloskin-ui1-detail-columns' : ' gloskin-ui1-container--narrow'; ?>">
		<div>
			<?php gloskin_ui1_render_page_content( $post ); ?>
			<?php if ( $has_contact ) : ?>
				<div class="gloskin-ui1-contact-panel">
					<h2><?php echo esc_html__( 'Clinic information', 'gloskin-site-core' ); ?></h2>
					<?php if ( $gloskin_context['address'] ) : ?><p><strong><?php echo esc_html__( 'Address', 'gloskin-site-core' ); ?></strong><br><?php echo nl2br( esc_html( $gloskin_context['address'] ) ); ?></p><?php endif; ?>
					<?php if ( $gloskin_context['phone_display'] ) : ?><p><strong><?php echo esc_html__( 'Phone', 'gloskin-site-core' ); ?></strong><br><?php if ( $gloskin_context['phone_url'] ) : ?><a href="<?php echo esc_url( $gloskin_context['phone_url'] ); ?>"><?php echo esc_html( $gloskin_context['phone_display'] ); ?></a><?php else : ?><?php echo esc_html( $gloskin_context['phone_display'] ); ?><?php endif; ?></p><?php endif; ?>
					<?php if ( $gloskin_context['operating_hours'] ) : ?><p><strong><?php echo esc_html__( 'Operating hours', 'gloskin-site-core' ); ?></strong><br><?php echo nl2br( esc_html( $gloskin_context['operating_hours'] ) ); ?></p><?php endif; ?>
					<?php if ( $gloskin_context['whatsapp_url'] ) : ?><p><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( $gloskin_context['whatsapp_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'WhatsApp', 'gloskin-site-core' ); ?></a></p><?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $has_location ) : ?><div>
			<?php if ( $gloskin_context['map_embed'] ) : ?>
				<div class="gloskin-ui1-map"><iframe title="<?php echo esc_attr( sprintf( __( 'Map for %s', 'gloskin-site-core' ), get_the_title( $post ) ) ); ?>" src="<?php echo esc_url( $gloskin_context['map_embed'] ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>
			<?php else : ?>
				<a class="gloskin-ui1-button gloskin-ui1-button--ghost" href="<?php echo esc_url( $gloskin_context['map_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open map', 'gloskin-site-core' ); ?></a>
			<?php endif; ?>
		</div><?php endif; ?>
	</div>
</section>
<?php endif; ?>
<?php if ( $gloskin_context['doctors'] ) : ?><section class="gloskin-ui1-section gloskin-ui1-section--soft"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Doctors at this clinic', 'gloskin-site-core' ) ); gloskin_ui1_render_card_grid( $gloskin_context['doctors'], 'doctor' ); ?></div></section><?php endif; ?>
<?php if ( $gloskin_context['treatments'] ) : ?><section class="gloskin-ui1-section"><div class="gloskin-ui1-container"><?php gloskin_ui1_render_section_heading( __( 'Related treatments', 'gloskin-site-core' ) ); gloskin_ui1_render_card_grid( $gloskin_context['treatments'], 'treatment' ); ?></div></section><?php endif; ?>

<?php if ( ! $gloskin_context['gallery_ids'] && ! $has_details && ! $has_related ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--tight"><div class="gloskin-ui1-container gloskin-ui1-container--narrow"><?php gloskin_ui1_render_discovery_panel( __( 'Explore our clinic network', 'gloskin-site-core' ), __( 'View all Gloskin clinic locations.', 'gloskin-site-core' ), __( 'View all clinics', 'gloskin-site-core' ), home_url( '/clinics/' ) ); ?></div></section>
<?php endif; ?>
