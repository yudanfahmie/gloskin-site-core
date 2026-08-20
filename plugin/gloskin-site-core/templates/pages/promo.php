<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_phase4_promos = isset( $gloskin_context['promos'] ) && is_array( $gloskin_context['promos'] ) ? array_values( $gloskin_context['promos'] ) : array();

/* Two independent Phase-4 visual instances consume the same managed Promo
 * collection. The existing gloskin-ui1-core promo controller owns prev/next,
 * dots, keyboard state and hidden-slide synchronization for every root. */
$gloskin_phase4_render_promo_carousel = static function ( $heading, $heading_tag, $instance ) use ( $gloskin_phase4_promos ) {
	$heading_tag = in_array( $heading_tag, array( 'h1', 'h2' ), true ) ? $heading_tag : 'h2';
	$count       = count( $gloskin_phase4_promos );
	?>
	<section class="gloskin-ui1-section gloskin-phase4-promo gloskin-phase4-promo--<?php echo esc_attr( $instance ); ?>" data-gloskin-phase4-promo="<?php echo esc_attr( $instance ); ?>">
		<div class="gloskin-ui1-container">
			<<?php echo esc_attr( $heading_tag ); ?> class="gloskin-phase4-promo__heading"><?php echo esc_html( $heading ); ?></<?php echo esc_attr( $heading_tag ); ?>>
			<?php if ( 0 === $count ) : ?>
				<div class="gloskin-ui1-empty"><?php echo esc_html__( 'Informasi promo belum tersedia.', 'gloskin-site-core' ); ?></div>
			<?php else : ?>
			<div class="gloskin-ui1-promo-carousel gloskin-ui1-promo-carousel--page gloskin-phase4-promo__carousel" data-gloskin-promo-carousel aria-label="<?php echo esc_attr( $heading ); ?>">
				<div class="gloskin-ui1-promo-carousel__live" aria-live="polite" aria-atomic="true" data-gloskin-promo-live></div>
				<div class="gloskin-ui1-promo-carousel__stage" role="region" aria-label="<?php echo esc_attr( $heading ); ?>">
					<?php foreach ( $gloskin_phase4_promos as $gloskin_phase4_index => $gloskin_phase4_promo ) :
						$gloskin_phase4_first = 0 === $gloskin_phase4_index;
						$gloskin_phase4_image = absint( $gloskin_phase4_promo['image_id'] ?? 0 );
						$gloskin_phase4_title = trim( (string) ( $gloskin_phase4_promo['title'] ?? '' ) );
					?>
					<div class="gloskin-ui1-promo-carousel__slide gloskin-phase4-promo__slide<?php echo $gloskin_phase4_first ? ' is-active' : ''; ?>" data-gloskin-promo-slide="<?php echo esc_attr( (string) $gloskin_phase4_index ); ?>"<?php echo $gloskin_phase4_first ? '' : ' hidden'; ?> aria-label="<?php echo esc_attr( sprintf( __( 'Promo %1$d dari %2$d', 'gloskin-site-core' ), $gloskin_phase4_index + 1, $count ) ); ?>">
						<div class="gloskin-phase4-promo__media">
							<?php if ( $gloskin_phase4_image ) : ?>
								<?php echo wp_get_attachment_image( $gloskin_phase4_image, 'large', false, array( 'class' => 'gloskin-phase4-promo__image', 'loading' => $gloskin_phase4_first ? 'eager' : 'lazy', 'alt' => '' ) ); ?>
							<?php else : ?>
								<div class="gloskin-phase4-promo__missing" role="img" aria-label="<?php echo esc_attr( $gloskin_phase4_title ); ?>"></div>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				<?php if ( $count > 1 ) : ?>
				<div class="gloskin-ui1-promo-carousel__controls gloskin-phase4-promo__controls" role="group" aria-label="<?php echo esc_attr__( 'Navigasi promo', 'gloskin-site-core' ); ?>">
					<button type="button" class="gloskin-ui1-promo-carousel__prev" data-gloskin-promo-prev aria-label="<?php echo esc_attr__( 'Promo sebelumnya', 'gloskin-site-core' ); ?>">&larr;</button>
					<div class="gloskin-ui1-promo-carousel__dots" role="tablist" aria-label="<?php echo esc_attr__( 'Pilih promo', 'gloskin-site-core' ); ?>">
						<?php foreach ( $gloskin_phase4_promos as $gloskin_phase4_dot_index => $_gloskin_phase4_promo ) : ?>
						<button type="button" class="gloskin-ui1-promo-carousel__dot<?php echo 0 === $gloskin_phase4_dot_index ? ' is-active' : ''; ?>" role="tab" data-gloskin-promo-dot="<?php echo esc_attr( (string) $gloskin_phase4_dot_index ); ?>" aria-selected="<?php echo 0 === $gloskin_phase4_dot_index ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $gloskin_phase4_dot_index ? '0' : '-1'; ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Promo %d', 'gloskin-site-core' ), $gloskin_phase4_dot_index + 1 ) ); ?>"></button>
						<?php endforeach; ?>
					</div>
					<button type="button" class="gloskin-ui1-promo-carousel__next" data-gloskin-promo-next aria-label="<?php echo esc_attr__( 'Promo berikutnya', 'gloskin-site-core' ); ?>">&rarr;</button>
				</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
};

$gloskin_phase4_render_promo_carousel( __( 'Promo Terbatas', 'gloskin-site-core' ), 'h1', 'featured' );
$gloskin_phase4_render_promo_carousel( __( 'Promo Poster', 'gloskin-site-core' ), 'h2', 'poster' );
unset( $gloskin_phase4_render_promo_carousel, $gloskin_phase4_promos );
