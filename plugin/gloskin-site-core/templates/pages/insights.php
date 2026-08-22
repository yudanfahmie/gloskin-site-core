<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
$gloskin_insight_cards = isset( $gloskin_context['insights'] ) && is_array( $gloskin_context['insights'] ) ? $gloskin_context['insights'] : array();
?>
<section class="gloskin-ui1-section gloskin-ui1-insights-archive" data-gloskin-section="insights-list">
	<div class="gloskin-ui1-container">
		<?php if ( $gloskin_insight_cards ) : ?>
			<?php
			$gloskin_insight_card = array_shift( $gloskin_insight_cards );
			$gloskin_insight_lead = true;
			require __DIR__ . '/../parts/insight-card.php';
			?>
			<?php if ( $gloskin_insight_cards ) : ?>
				<div class="gloskin-ui1-insights-archive__grid">
					<?php foreach ( $gloskin_insight_cards as $gloskin_insight_card ) : ?>
						<?php $gloskin_insight_lead = false; require __DIR__ . '/../parts/insight-card.php'; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( $gloskin_context['total_pages'] > 1 ) : ?>
				<nav class="gloskin-ui1-pagination gloskin-ui1-insights-archive__pagination" aria-label="<?php echo esc_attr__( 'Navigasi halaman insight', 'gloskin-site-core' ); ?>">
					<?php
					$gloskin_pagination = paginate_links( array(
						'current'   => $gloskin_context['current_page'],
						'total'     => $gloskin_context['total_pages'],
						'type'      => 'list',
						'prev_text' => '<span class="screen-reader-text">' . esc_html__( 'Halaman sebelumnya', 'gloskin-site-core' ) . '</span>' . gloskin_ui1_arrow_icon( 'prev' ),
						'next_text' => '<span class="screen-reader-text">' . esc_html__( 'Halaman berikutnya', 'gloskin-site-core' ) . '</span>' . gloskin_ui1_arrow_icon(),
					) );
					$gloskin_pagination_allowed = array(
						'ul'   => array( 'class' => true ),
						'li'   => array( 'class' => true ),
						'a'    => array( 'class' => true, 'href' => true, 'aria-current' => true ),
						'span' => array( 'class' => true, 'aria-current' => true ),
						'svg'  => array( 'class' => true, 'viewBox' => true, 'fill' => true, 'aria-hidden' => true, 'focusable' => true, 'xmlns' => true ),
						'path' => array( 'd' => true, 'fill' => true ),
					);
					echo wp_kses( $gloskin_pagination, $gloskin_pagination_allowed );
					?>
				</nav>
			<?php endif; ?>
		<?php else : ?>
			<?php gloskin_ui1_render_empty_state( 'insight', __( 'Belum ada artikel yang dipublikasikan', 'gloskin-site-core' ), __( 'Artikel dan pembaruan Gloskin akan tampil di sini setelah tersedia.', 'gloskin-site-core' ), __( 'Buka Perawatan', 'gloskin-site-core' ), home_url( '/treatments/' ) ); ?>
		<?php endif; ?>
	</div>
</section>
