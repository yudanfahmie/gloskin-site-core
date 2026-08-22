<?php
/**
 * Shared insights results partial.
 *
 * Accepted context variable: $gloskin_insights_data (array with keys:
 *   'insights'     => array of insight card arrays
 *   'current_page' => int
 *   'total_pages'  => int
 * )
 *
 * Used by:
 *   - templates/pages/insights.php   (server-rendered, inside data-gloskin-insights-results)
 *   - rest_insights()                (buffered output for AJAX response)
 *
 * IMPORTANT: this partial is the ONE server-rendered fragment owner for the
 * insights card list + pagination. Do not duplicate this logic elsewhere.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gloskin_insight_cards = isset( $gloskin_insights_data['insights'] ) && is_array( $gloskin_insights_data['insights'] )
	? $gloskin_insights_data['insights']
	: array();
$gloskin_current_page  = isset( $gloskin_insights_data['current_page'] ) ? (int) $gloskin_insights_data['current_page'] : 1;
$gloskin_total_pages   = isset( $gloskin_insights_data['total_pages'] )  ? (int) $gloskin_insights_data['total_pages']  : 1;
?>
<?php if ( $gloskin_insight_cards ) : ?>
	<?php
	$gloskin_insight_cards_copy = $gloskin_insight_cards;
	$gloskin_insight_card       = array_shift( $gloskin_insight_cards_copy );
	$gloskin_insight_lead       = true;
	require __DIR__ . '/insight-card.php';
	?>
	<?php if ( $gloskin_insight_cards_copy ) : ?>
		<div class="gloskin-ui1-insights-archive__grid">
			<?php foreach ( $gloskin_insight_cards_copy as $gloskin_insight_card ) : ?>
				<?php $gloskin_insight_lead = false; require __DIR__ . '/insight-card.php'; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php if ( $gloskin_total_pages > 1 ) : ?>
		<nav class="gloskin-ui1-pagination gloskin-ui1-insights-archive__pagination" aria-label="<?php echo esc_attr__( 'Navigasi halaman insight', 'gloskin-site-core' ); ?>">
			<?php
			$gloskin_pagination = paginate_links( array(
				'base'      => str_replace( PHP_INT_MAX, '%#%', esc_url( get_pagenum_link( PHP_INT_MAX ) ) ),
				'format'    => '?paged=%#%',
				'current'   => $gloskin_current_page,
				'total'     => $gloskin_total_pages,
				'type'      => 'list',
				'prev_text' => '<span class="screen-reader-text">' . esc_html__( 'Halaman sebelumnya', 'gloskin-site-core' ) . '</span>' . gloskin_ui1_arrow_icon( 'prev' ),
				'next_text' => '<span class="screen-reader-text">' . esc_html__( 'Halaman berikutnya', 'gloskin-site-core' ) . '</span>' . gloskin_ui1_arrow_icon(),
			) );
			$gloskin_pagination_allowed = array(
				'ul'   => array( 'class' => true ),
				'li'   => array( 'class' => true ),
				'a'    => array( 'class' => true, 'href' => true, 'aria-current' => true ),
				'span' => array( 'class' => true, 'aria-current' => true ),
				'svg'  => array( 'class' => true, 'width' => true, 'height' => true, 'viewBox' => true, 'fill' => true, 'aria-hidden' => true, 'focusable' => true, 'xmlns' => true ),
				'path' => array( 'd' => true, 'fill' => true, 'transform' => true ),
			);
			echo wp_kses( $gloskin_pagination, $gloskin_pagination_allowed );
			?>
		</nav>
	<?php endif; ?>
<?php else : ?>
	<?php gloskin_ui1_render_empty_state( 'insight', __( 'Belum ada artikel yang dipublikasikan', 'gloskin-site-core' ), __( 'Artikel dan pembaruan Gloskin akan tampil di sini setelah tersedia.', 'gloskin-site-core' ), __( 'Buka Perawatan', 'gloskin-site-core' ), home_url( '/treatments/' ) ); ?>
<?php endif; ?>
