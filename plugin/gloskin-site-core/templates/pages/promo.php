<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_limited_promos = isset( $gloskin_context['limited_promos'] ) && is_array( $gloskin_context['limited_promos'] ) ? array_values( $gloskin_context['limited_promos'] ) : array();
$gloskin_regular_promos = isset( $gloskin_context['regular_promos'] ) && is_array( $gloskin_context['regular_promos'] ) ? array_values( $gloskin_context['regular_promos'] ) : array();

/* One shared renderer, two independent canonical collections. The existing
 * gloskin-ui1-core promo controller remains the single prev/next/dot/state
 * owner for every data-gloskin-promo-carousel root. */
$gloskin_render_promo_carousel = static function ( $promos, $heading, $heading_tag, $instance ) {
	$heading_tag = in_array( $heading_tag, array( 'h1', 'h2' ), true ) ? $heading_tag : 'h2';
	$count       = count( $promos );
	?>
	<section class="gloskin-ui1-section gloskin-promo gloskin-promo--<?php echo esc_attr( $instance ); ?>" data-gloskin-promo="<?php echo esc_attr( $instance ); ?>">
		<div class="gloskin-ui1-container">
			<<?php echo esc_attr( $heading_tag ); ?> class="gloskin-promo__heading"><?php echo esc_html( $heading ); ?></<?php echo esc_attr( $heading_tag ); ?>>
			<?php if ( 0 === $count ) : ?>
				<?php gloskin_ui1_render_empty_state( 'generic', __( 'Informasi promo belum tersedia.', 'gloskin-site-core' ) ); ?>
			<?php else : ?>
			<div class="gloskin-ui1-promo-carousel gloskin-ui1-promo-carousel--page gloskin-promo__carousel" data-gloskin-promo-carousel aria-label="<?php echo esc_attr( $heading ); ?>">
				<div class="gloskin-ui1-promo-carousel__live screen-reader-text" aria-live="polite" aria-atomic="true" data-gloskin-promo-live></div>
				<div class="gloskin-ui1-promo-carousel__stage" role="region" aria-label="<?php echo esc_attr( $heading ); ?>">
					<?php foreach ( $promos as $gloskin_promo_index => $gloskin_promo ) :
						$gloskin_promo_first   = 0 === $gloskin_promo_index;
						$gloskin_promo_id      = absint( $gloskin_promo['id'] ?? 0 );
						$gloskin_promo_image   = absint( $gloskin_promo['image_id'] ?? 0 );
						$gloskin_promo_focus_x = isset( $gloskin_promo['focus_x'] ) && is_numeric( $gloskin_promo['focus_x'] ) ? max( 0, min( 100, (float) $gloskin_promo['focus_x'] ) ) : 50;
						$gloskin_promo_focus_y = isset( $gloskin_promo['focus_y'] ) && is_numeric( $gloskin_promo['focus_y'] ) ? max( 0, min( 100, (float) $gloskin_promo['focus_y'] ) ) : 50;
						$gloskin_promo_zoom    = isset( $gloskin_promo['zoom'] ) && is_numeric( $gloskin_promo['zoom'] ) ? (float) $gloskin_promo['zoom'] : ( $gloskin_promo_id ? (float) get_post_meta( $gloskin_promo_id, 'gloskin_promo_crop_zoom', true ) : 100 );
						$gloskin_promo_zoom    = max( 100, min( 300, $gloskin_promo_zoom > 0 ? $gloskin_promo_zoom : 100 ) );
						$gloskin_promo_scale   = $gloskin_promo_zoom / 100;
					?>
					<div class="gloskin-ui1-promo-carousel__slide gloskin-promo__slide<?php echo $gloskin_promo_first ? ' is-active' : ''; ?>" data-gloskin-promo-slide="<?php echo esc_attr( (string) $gloskin_promo_index ); ?>"<?php echo $gloskin_promo_first ? '' : ' hidden'; ?> aria-label="<?php echo esc_attr( sprintf( __( 'Promo %1$d dari %2$d', 'gloskin-site-core' ), $gloskin_promo_index + 1, $count ) ); ?>">
						<div class="gloskin-promo__media" style="--gloskin-promo-focus-x:<?php echo esc_attr( (string) $gloskin_promo_focus_x ); ?>%;--gloskin-promo-focus-y:<?php echo esc_attr( (string) $gloskin_promo_focus_y ); ?>%;--gloskin-promo-scale:<?php echo esc_attr( (string) $gloskin_promo_scale ); ?>;">
							<?php if ( $gloskin_promo_image ) : ?>
								<?php echo wp_get_attachment_image( $gloskin_promo_image, 'full', false, array( 'class' => 'gloskin-promo__image', 'loading' => $gloskin_promo_first ? 'eager' : 'lazy', 'alt' => '' ) ); ?>
							<?php else : ?>
								<div class="gloskin-promo__missing" aria-hidden="true"></div>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				<?php if ( $count > 1 ) : ?>
				<div class="gloskin-ui1-promo-carousel__controls gloskin-promo__controls" role="group" aria-label="<?php echo esc_attr__( 'Navigasi promo', 'gloskin-site-core' ); ?>">
					<button type="button" class="gloskin-ui1-promo-carousel__prev" data-gloskin-promo-prev aria-label="<?php echo esc_attr__( 'Promo sebelumnya', 'gloskin-site-core' ); ?>"><?php echo gloskin_ui1_arrow_icon( 'prev' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static shared icon markup. ?></button>
					<div class="gloskin-ui1-promo-carousel__dots" role="tablist" aria-label="<?php echo esc_attr__( 'Pilih promo', 'gloskin-site-core' ); ?>">
						<?php foreach ( $promos as $gloskin_dot_index => $_gloskin_promo ) : ?>
						<button type="button" class="gloskin-ui1-promo-carousel__dot<?php echo 0 === $gloskin_dot_index ? ' is-active' : ''; ?>" role="tab" data-gloskin-promo-dot="<?php echo esc_attr( (string) $gloskin_dot_index ); ?>" aria-selected="<?php echo 0 === $gloskin_dot_index ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $gloskin_dot_index ? '0' : '-1'; ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Promo %d', 'gloskin-site-core' ), $gloskin_dot_index + 1 ) ); ?>"></button>
						<?php endforeach; ?>
					</div>
					<button type="button" class="gloskin-ui1-promo-carousel__next" data-gloskin-promo-next aria-label="<?php echo esc_attr__( 'Promo berikutnya', 'gloskin-site-core' ); ?>"><?php echo gloskin_ui1_arrow_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static shared icon markup. ?></button>
				</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
};

$gloskin_render_promo_carousel( $gloskin_limited_promos, __( 'Promo Terbatas', 'gloskin-site-core' ), 'h1', 'limited' );
$gloskin_render_promo_carousel( $gloskin_regular_promos, __( 'Promo Biasa', 'gloskin-site-core' ), 'h2', 'regular' );
unset( $gloskin_render_promo_carousel, $gloskin_limited_promos, $gloskin_regular_promos );