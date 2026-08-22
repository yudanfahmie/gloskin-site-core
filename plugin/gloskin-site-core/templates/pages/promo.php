<?php
/**
 * Promo page.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gloskin_limited = isset( $gloskin_context['limited_promos'] ) && is_array( $gloskin_context['limited_promos'] ) ? $gloskin_context['limited_promos'] : array();
$gloskin_regular = isset( $gloskin_context['regular_promos'] ) && is_array( $gloskin_context['regular_promos'] ) ? $gloskin_context['regular_promos'] : array();
$gloskin_render_promo_section = static function ( $title, $items, $section_class ) {
	?>
	<section class="gloskin-promo <?php echo esc_attr( $section_class ); ?>">
		<div class="gloskin-ui1-container">
			<h2 class="gloskin-promo__heading"><?php echo esc_html( $title ); ?></h2>
			<?php if ( $items ) : ?>
				<div class="gloskin-ui1-promo-carousel" data-gloskin-promo-carousel>
					<div class="gloskin-ui1-promo-carousel__viewport" data-gloskin-promo-viewport>
						<?php foreach ( $items as $gloskin_index => $gloskin_promo ) : ?>
							<article class="gloskin-ui1-promo-carousel__slide<?php echo 0 === $gloskin_index ? ' is-active' : ''; ?>" data-gloskin-promo-slide aria-label="<?php echo esc_attr( sprintf( __( 'Promo %1$d dari %2$d', 'gloskin-site-core' ), $gloskin_index + 1, count( $items ) ) ); ?>"<?php echo 0 === $gloskin_index ? '' : ' aria-hidden="true"'; ?>>
								<?php if ( ! empty( $gloskin_promo['image_id'] ) ) : ?>
									<div class="gloskin-promo__media">
										<?php echo wp_get_attachment_image( absint( $gloskin_promo['image_id'] ), 'large', false, array( 'class' => 'gloskin-promo__image', 'loading' => 0 === $gloskin_index ? 'eager' : 'lazy', 'decoding' => 'async' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
					<?php if ( count( $items ) > 1 ) : ?>
						<div class="gloskin-promo__controls">
							<button class="gloskin-ui1-promo-carousel__prev" type="button" data-gloskin-promo-prev aria-label="<?php echo esc_attr__( 'Promo sebelumnya', 'gloskin-site-core' ); ?>"><?php echo gloskin_ui1_arrow_icon( 'left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
							<div class="gloskin-ui1-promo-carousel__dots" data-gloskin-promo-dots role="tablist" aria-label="<?php echo esc_attr__( 'Pilih promo', 'gloskin-site-core' ); ?>">
								<?php foreach ( $items as $gloskin_index => $gloskin_promo ) : ?>
									<button class="gloskin-ui1-promo-carousel__dot<?php echo 0 === $gloskin_index ? ' is-active' : ''; ?>" type="button" data-gloskin-promo-dot="<?php echo esc_attr( (string) $gloskin_index ); ?>" role="tab" aria-selected="<?php echo 0 === $gloskin_index ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Buka promo %d', 'gloskin-site-core' ), $gloskin_index + 1 ) ); ?>"></button>
								<?php endforeach; ?>
							</div>
							<button class="gloskin-ui1-promo-carousel__next" type="button" data-gloskin-promo-next aria-label="<?php echo esc_attr__( 'Promo berikutnya', 'gloskin-site-core' ); ?>"><?php echo gloskin_ui1_arrow_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						</div>
					<?php endif; ?>
					<div class="gloskin-ui1-promo-carousel__live screen-reader-text" aria-live="polite" aria-atomic="true" data-gloskin-promo-live></div>
				</div>
			<?php else : ?>
				<p class="gloskin-ui1-empty-state"><?php echo esc_html__( 'Belum ada promo aktif pada bagian ini.', 'gloskin-site-core' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<?php
};

$gloskin_render_promo_section( __( 'PROMO TERBATAS', 'gloskin-site-core' ), $gloskin_limited, 'gloskin-promo--limited' );
$gloskin_render_promo_section( __( 'PROMO LAINNYA', 'gloskin-site-core' ), $gloskin_regular, 'gloskin-promo--regular' );
