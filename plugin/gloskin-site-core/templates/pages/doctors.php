<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
gloskin_ui1_render_hero( $gloskin_context['hero'] );
?>
<section class="gloskin-ui1-section">
	<div class="gloskin-ui1-container">
		<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
		<?php if ( $gloskin_context['doctors'] ) : ?>
			<?php gloskin_ui1_render_card_grid( $gloskin_context['doctors'], 'doctor' ); ?>
		<?php else : ?>
			<?php gloskin_ui1_empty( __( 'The architecture supports thirteen doctor profiles, but approved doctor identity and professional data is still required before publishing them.', 'gloskin-site-core' ) ); ?>
		<?php endif; ?>
	</div>
</section>
