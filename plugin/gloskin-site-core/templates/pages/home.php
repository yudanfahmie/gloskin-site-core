<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once dirname( __DIR__ ) . '/parts/phase4-home-selection.php';

/* Phase 4 client-approved Home structure:
 * full-width video -> simple Why -> 6 Treatments -> 3 static testimonials
 * -> 4 image-only Piagam. Promo, brand-story, testimonial controls and the
 * closing CTA intentionally do not render on Home. */
$gloskin_home_hero              = isset( $gloskin_context['hero'] ) && is_array( $gloskin_context['hero'] ) ? $gloskin_context['hero'] : array();
$gloskin_home_hero['heading']   = '';
$gloskin_home_hero['copy']      = '';
$gloskin_home_hero['cta_label'] = '';
$gloskin_home_hero['cta_url']   = '';
?>
<div data-gloskin-phase4-home-hero>
	<?php gloskin_ui1_render_hero( $gloskin_home_hero ); ?>
</div>

<section class="gloskin-ui1-section gloskin-phase4-home-why" data-gloskin-section="why-gloskin">
	<div class="gloskin-ui1-container gloskin-phase4-home-why__grid">
		<div class="gloskin-phase4-home-why__media" aria-hidden="true">
			<?php gloskin_ui1_render_editorial_media( 'editorial', 'home_why', 'gloskin-phase4-home-why__image' ); ?>
		</div>
		<div class="gloskin-phase4-home-why__copy">
			<h2><?php echo esc_html__( 'Kenapa Memilih GLOSKIN', 'gloskin-site-core' ); ?></h2>
			<ul>
				<li><?php echo esc_html__( 'Temukan pilihan perawatan berdasarkan keluhan dan kondisi kulit — bukan label generik.', 'gloskin-site-core' ); ?></li>
				<li><?php echo esc_html__( 'Perawatan klinik dan produk skincare Gloskin dirancang dalam satu ekosistem yang saling melengkapi.', 'gloskin-site-core' ); ?></li>
				<li><?php echo esc_html__( 'Tim dokter Gloskin tersedia di jaringan klinik untuk konsultasi dan perencanaan perawatan.', 'gloskin-site-core' ); ?></li>
			</ul>
		</div>
	</div>
</section>

<?php $gloskin_home_treatments = gloskin_phase4_home_treatment_cards( isset( $gloskin_context['treatments'] ) ? $gloskin_context['treatments'] : array() ); ?>
<?php if ( $gloskin_home_treatments ) : ?>
<section class="gloskin-ui1-section gloskin-ui1-section--soft gloskin-phase4-home-treatments" data-gloskin-section="home-treatments">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Treatment Unggulan', 'gloskin-site-core' ) ); ?>
		<div class="gloskin-ui1-grid gloskin-ui1-grid--cards gloskin-phase4-home-treatments__grid">
			<?php foreach ( array_slice( $gloskin_home_treatments, 0, 6 ) as $gloskin_home_treatment ) { gloskin_ui1_render_card( $gloskin_home_treatment, 'treatment' ); } ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php $gloskin_home_testimonials = array_slice( isset( $gloskin_context['testimonials'] ) && is_array( $gloskin_context['testimonials'] ) ? $gloskin_context['testimonials'] : array(), 0, 3 ); ?>
<?php if ( $gloskin_home_testimonials ) : ?>
<section class="gloskin-ui1-section gloskin-phase4-home-testimonials" data-gloskin-section="testimonials">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Testimoni', 'gloskin-site-core' ) ); ?>
		<div class="gloskin-phase4-home-testimonials__rows" data-gloskin-phase4-static-testimonials>
			<?php foreach ( $gloskin_home_testimonials as $gloskin_home_testimonial ) :
				$gloskin_home_quote = '' !== trim( (string) ( $gloskin_home_testimonial['excerpt'] ?? '' ) ) ? (string) $gloskin_home_testimonial['excerpt'] : (string) ( $gloskin_home_testimonial['title'] ?? '' );
				$gloskin_home_attribution = (string) ( $gloskin_home_testimonial['meta']['attribution'] ?? '' );
				$gloskin_home_subtitle = (string) ( $gloskin_home_testimonial['meta']['subtitle'] ?? '' );
			?>
			<figure class="gloskin-phase4-home-testimonial">
				<?php if ( ! empty( $gloskin_home_testimonial['image_id'] ) ) : ?>
					<?php echo wp_get_attachment_image( absint( $gloskin_home_testimonial['image_id'] ), 'thumbnail', false, array( 'class' => 'gloskin-phase4-home-testimonial__avatar', 'loading' => 'lazy' ) ); ?>
				<?php endif; ?>
				<div class="gloskin-phase4-home-testimonial__body">
					<blockquote><p>“<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $gloskin_home_quote ), 48 ) ); ?>”</p></blockquote>
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
	</div>
</section>
<?php endif; ?>

<?php $gloskin_home_piagam = array_slice( isset( $gloskin_context['achievements'] ) && is_array( $gloskin_context['achievements'] ) ? $gloskin_context['achievements'] : array(), 0, 4 ); ?>
<?php if ( $gloskin_home_piagam ) : ?>
<section class="gloskin-ui1-section gloskin-phase4-home-piagam" data-gloskin-section="achievements">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_section_heading( __( 'Piagam', 'gloskin-site-core' ) ); ?>
		<div class="gloskin-phase4-home-piagam__grid" data-gloskin-phase4-piagam>
			<?php foreach ( $gloskin_home_piagam as $gloskin_home_achievement ) : ?>
				<?php if ( ! empty( $gloskin_home_achievement['image_id'] ) ) : ?>
					<figure class="gloskin-phase4-home-piagam__card"><?php echo wp_get_attachment_image( absint( $gloskin_home_achievement['image_id'] ), 'medium_large', false, array( 'class' => 'gloskin-phase4-home-piagam__image', 'loading' => 'lazy', 'alt' => '' ) ); ?></figure>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>
