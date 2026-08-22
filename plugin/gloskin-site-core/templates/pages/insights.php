<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
$gloskin_insights_data = array(
	'insights'     => isset( $gloskin_context['insights'] ) && is_array( $gloskin_context['insights'] ) ? $gloskin_context['insights'] : array(),
	'current_page' => isset( $gloskin_context['current_page'] ) ? (int) $gloskin_context['current_page'] : 1,
	'total_pages'  => isset( $gloskin_context['total_pages'] )  ? (int) $gloskin_context['total_pages']  : 1,
);
?>
<section class="gloskin-ui1-section gloskin-ui1-insights-archive" data-gloskin-section="insights-list">
	<div class="gloskin-ui1-container">
		<div class="screen-reader-text" data-gloskin-insights-status aria-live="polite" aria-atomic="true"></div>
		<div data-gloskin-insights-results>
			<?php require __DIR__ . '/../parts/insights-results.php'; ?>
		</div>
	</div>
</section>
