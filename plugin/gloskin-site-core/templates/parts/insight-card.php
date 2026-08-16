<?php
/**
 * Insight-specific native post card. Expects $gloskin_insight_card.
 *
 * @package GloskinSiteCore
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$gloskin_insight_card = isset( $gloskin_insight_card ) && is_array( $gloskin_insight_card ) ? $gloskin_insight_card : array();
$gloskin_insight_lead = ! empty( $gloskin_insight_lead );
$gloskin_insight_title = isset( $gloskin_insight_card['title'] ) ? (string) $gloskin_insight_card['title'] : '';
$gloskin_insight_url = isset( $gloskin_insight_card['url'] ) ? (string) $gloskin_insight_card['url'] : '';
$gloskin_insight_image_id = isset( $gloskin_insight_card['image_id'] ) ? absint( $gloskin_insight_card['image_id'] ) : 0;
?>
<article class="gloskin-ui1-insights-archive__card<?php echo $gloskin_insight_lead ? ' gloskin-ui1-insights-archive__card--lead' : ''; ?>">
	<a class="gloskin-ui1-insights-archive__media" href="<?php echo esc_url( $gloskin_insight_url ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $gloskin_insight_image_id ) : ?>
			<?php echo wp_get_attachment_image( $gloskin_insight_image_id, $gloskin_insight_lead ? 'large' : 'medium_large', false, array( 'class' => 'gloskin-ui1-insights-archive__image', 'loading' => $gloskin_insight_lead ? 'eager' : 'lazy', 'decoding' => 'async' ) ); ?>
		<?php else : ?>
			<?php gloskin_ui1_render_editorial_media( 'insight', $gloskin_insight_title, 'gloskin-ui1-insights-archive__image gloskin-ui1-insights-archive__image--fallback', $gloskin_insight_lead ); ?>
		<?php endif; ?>
	</a>
	<div class="gloskin-ui1-insights-archive__body">
		<div class="gloskin-ui1-insights-archive__meta">
			<?php if ( ! empty( $gloskin_insight_card['category'] ) ) : ?><span class="gloskin-ui1-insights-archive__category"><?php echo esc_html( (string) $gloskin_insight_card['category'] ); ?></span><?php endif; ?>
			<?php if ( ! empty( $gloskin_insight_card['date'] ) ) : ?><time datetime="<?php echo esc_attr( (string) $gloskin_insight_card['date_iso'] ); ?>"><?php echo esc_html( (string) $gloskin_insight_card['date'] ); ?></time><?php endif; ?>
			<?php if ( ! empty( $gloskin_insight_card['reading_time'] ) ) : ?><span><?php echo esc_html( (string) $gloskin_insight_card['reading_time'] ); ?></span><?php endif; ?>
		</div>
		<h2 class="gloskin-ui1-insights-archive__title"><a href="<?php echo esc_url( $gloskin_insight_url ); ?>"><?php echo esc_html( $gloskin_insight_title ); ?></a></h2>
		<?php if ( ! empty( $gloskin_insight_card['excerpt'] ) ) : ?><p class="gloskin-ui1-insights-archive__excerpt"><?php echo esc_html( (string) $gloskin_insight_card['excerpt'] ); ?></p><?php endif; ?>
		<a class="gloskin-ui1-insights-archive__read" href="<?php echo esc_url( $gloskin_insight_url ); ?>"><?php echo esc_html__( 'Baca artikel', 'gloskin-site-core' ); ?><span aria-hidden="true"> →</span></a>
	</div>
</article>
