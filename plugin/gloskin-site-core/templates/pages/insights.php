<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
		<?php if ( $gloskin_context['insights'] ) : ?>
			<?php gloskin_ui1_render_card_grid( $gloskin_context['insights'], 'insight' ); ?>
			<?php if ( $gloskin_context['total_pages'] > 1 ) : ?>
				<nav class="gloskin-ui1-pagination" aria-label="<?php echo esc_attr__( 'Insights pagination', 'gloskin-site-core' ); ?>">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'current' => $gloskin_context['current_page'],
								'total'   => $gloskin_context['total_pages'],
								'type'    => 'list',
							)
						)
					);
					?>
				</nav>
			<?php endif; ?>
		<?php else : ?>
			<?php gloskin_ui1_empty( __( 'No Insights posts are published yet.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>
