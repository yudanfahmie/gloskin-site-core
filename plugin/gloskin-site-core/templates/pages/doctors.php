<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$hero = $gloskin_context['hero'];
if ( empty( $hero['copy'] ) ) { $hero['copy'] = __( 'Meet the Gloskin medical team.', 'gloskin-site-core' ); }
gloskin_ui1_render_hero( $hero );
?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) || $gloskin_context['doctors'] ) : ?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
		<?php gloskin_ui1_render_card_grid( $gloskin_context['doctors'], 'doctor' ); ?>
	</div>
</section>
<?php endif; ?>
