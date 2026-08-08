<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$hero = $gloskin_context['hero'];
if ( empty( $hero['copy'] ) ) { $hero['copy'] = __( 'Explore Gloskin insights and updates.', 'gloskin-site-core' ); }
gloskin_ui1_render_hero( $hero );
?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) || $gloskin_context['insights'] ) : ?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
		<?php if ( $gloskin_context['insights'] ) : ?>
			<?php gloskin_ui1_render_card_grid( $gloskin_context['insights'], 'insight' ); ?>
			<?php if ( $gloskin_context['total_pages'] > 1 ) : ?>
				<nav class="gloskin-ui1-pagination" aria-label="<?php echo esc_attr__( 'Insights pagination', 'gloskin-site-core' ); ?>">
					<?php echo wp_kses_post( paginate_links( array( 'current' => $gloskin_context['current_page'], 'total' => $gloskin_context['total_pages'], 'type' => 'list' ) ) ); ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>
