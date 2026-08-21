<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Full-width video hero — mode is resolved in TemplateService::home_context(). */
gloskin_ui1_render_hero( isset( $gloskin_context['hero'] ) && is_array( $gloskin_context['hero'] ) ? $gloskin_context['hero'] : array() );

$gloskin_home_treatments = isset( $gloskin_context['treatments'] ) && is_array( $gloskin_context['treatments'] ) ? array_slice( $gloskin_context['treatments'], 0, 6 ) : array();
$gloskin_home_testimonials = isset( $gloskin_context['testimonials'] ) && is_array( $gloskin_context['testimonials'] ) ? array_values( array_filter( $gloskin_context['testimonials'], static function ( $testimonial ) {
	return is_array( $testimonial ) && '' !== trim( (string) ( $testimonial['excerpt'] ?? '' ) );
} ) ) : array();
$gloskin_home_testimonials = array_slice( $gloskin_home_testimonials, 0, 3 );
$gloskin_home_piagam = isset( $gloskin_context['achievements'] ) && is_array( $gloskin_context['achievements'] ) ? array_slice( $gloskin_context['achievements'], 0, 4 ) : array();
$gloskin_home_empty_copy = __( 'Detail tambahan belum tersedia untuk ditampilkan.', 'gloskin-site-core' );
?>
<section class="gloskin-ui1-section gloskin-home-why" data-gloskin-section="why-gloskin">
	<div class="gloskin-ui1-container gloskin-home-why__grid">
		<div class="gloskin-home-why__media" aria-hidden="true">
			<?php gloskin_ui1_render_editorial_media( 'editorial', 'home_why', 'gloskin-home-why__image' ); ?>
		</div>
		<div class="gloskin-home-why__copy">
			<h2><?php echo esc_html__( 'Kenapa Memilih GLOSKIN', 'gloskin-site-core' ); ?></h2>
			<ul>
				<li><?php echo esc_html__( 'Temukan pilihan perawatan berdasarkan keluhan dan kondisi kulit — bukan label generik.', 'gloskin-site-core' ); ?></li>
				<li><?php echo esc_html__( 'Perawatan klinik dan produk skincare Gloskin dirancang dalam satu ekosistem yang saling melengkapi.', 'gloskin-site-core' ); ?></li>
				<li><?php echo esc_html__( 'Tim dokter Gloskin tersedia di jaringan klinik untuk konsultasi dan perencanaan perawatan.', 'gloskin-site-core' ); ?></li>
			</ul>
		</div>
	</div>
</section>

<section class="gloskin-ui1-section gloskin-ui1-section--soft gloskin-home-treatments" data-gloskin-section="home-treatments">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Treatment Unggulan', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_home_treatments ) : ?>
			<div class="gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-home-treatments__grid">
				<?php foreach ( $gloskin_home_treatments as $gloskin_home_treatment ) { gloskin_ui1_render_card( $gloskin_home_treatment, 'treatment' ); } ?>
			</div>
		<?php else : ?>
			<?php gloskin_ui1_render_empty_state( 'treatment', __( 'Treatment Unggulan', 'gloskin-site-core' ), $gloskin_home_empty_copy ); ?>
		<?php endif; ?>
	</div>
</section>

<section class="gloskin-ui1-section gloskin-home-testimonials" data-gloskin-section="testimonials">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Testimoni', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_home_testimonials ) : ?>
			<div class="gloskin-home-testimonials__rows" data-gloskin-static-testimonials>
				<?php foreach ( $gloskin_home_testimonials as $gloskin_home_testimonial ) :
					$gloskin_home_quote = trim( (string) ( $gloskin_home_testimonial['excerpt'] ?? '' ) );
					$gloskin_home_attribution = (string) ( $gloskin_home_testimonial['meta']['attribution'] ?? '' );
					$gloskin_home_subtitle = (string) ( $gloskin_home_testimonial['meta']['subtitle'] ?? '' );
				?>
				<figure class="gloskin-home-testimonial">
					<?php if ( ! empty( $gloskin_home_testimonial['image_id'] ) ) : ?>
						<?php echo wp_get_attachment_image( absint( $gloskin_home_testimonial['image_id'] ), 'thumbnail', false, array( 'class' => 'gloskin-home-testimonial__avatar', 'loading' => 'lazy' ) ); ?>
					<?php endif; ?>
					<div class="gloskin-home-testimonial__body">
						<blockquote><p>"<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $gloskin_home_quote ), 48 ) ); ?>"</p></blockquote>
						<?php if ( '' !== $gloskin_home_attribution || '' !== $gloskin_home_subtitle ) : ?>
						<figcaption>
							<?php if ( '' !== $gloskin_home_attribution ) : ?><strong><?php echo esc_html( $gloskin_home_attribution ); ?></strong><?php endif; ?>
							<?php if ( '' !== $gloskin_home_subtitle ) : ?><span><?php echo esc_html( $gloskin_home_subtitle ); ?></span><?php endif; ?>
						</figcaption>
						<?php endif; ?>
					</div>
				</figure>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php gloskin_ui1_render_empty_state( 'generic', __( 'Testimoni', 'gloskin-site-core' ), $gloskin_home_empty_copy ); ?>
		<?php endif; ?>
	</div>
</section>

<section class="gloskin-ui1-section gloskin-home-piagam" data-gloskin-section="achievements">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Piagam', 'gloskin-site-core' ) ); ?>
		<?php if ( $gloskin_home_piagam ) : ?>
			<div class="gloskin-home-piagam__grid" data-gloskin-piagam>
				<?php foreach ( $gloskin_home_piagam as $gloskin_home_achievement ) : ?>
					<figure class="gloskin-home-piagam__card">
						<?php if ( ! empty( $gloskin_home_achievement['image_id'] ) ) : ?>
							<?php echo wp_get_attachment_image( absint( $gloskin_home_achievement['image_id'] ), 'medium_large', false, array( 'class' => 'gloskin-home-piagam__image', 'loading' => 'lazy', 'alt' => '' ) ); ?>
						<?php else : ?>
							<?php gloskin_ui1_render_presentation_media( 'editorial', 'piagam-' . absint( $gloskin_home_achievement['id'] ?? 0 ), 'gloskin-home-piagam__image gloskin-home-piagam__image--fallback' ); ?>
						<?php endif; ?>
					</figure>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php gloskin_ui1_render_empty_state( 'generic', __( 'Piagam', 'gloskin-site-core' ), $gloskin_home_empty_copy ); ?>
		<?php endif; ?>
	</div>
</section>
