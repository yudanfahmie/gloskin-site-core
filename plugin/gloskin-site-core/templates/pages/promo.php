<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

gloskin_ui1_render_promo_campaign( (array) $gloskin_context['campaign'], 'h1', false );
?>
<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?>
<section class="gloskin-ui1-section" data-gloskin-section="promo-content"><div class="gloskin-ui1-container gloskin-ui1-container--narrow">
	<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
</div></section>
<?php endif; ?>
<section class="gloskin-ui1-section gloskin-ui1-section--cta" data-gloskin-section="promo-closing"><div class="gloskin-ui1-container">
	<?php gloskin_ui1_render_closing_cta( __( 'Konsultasi', 'gloskin-site-core' ), __( 'Perlu bantuan sebelum memilih perawatan?', 'gloskin-site-core' ), __( 'Tim Gloskin dapat membantu Anda menentukan langkah konsultasi melalui kanal resmi yang tersedia.', 'gloskin-site-core' ), __( 'Jelajahi Perawatan', 'gloskin-site-core' ), home_url( '/treatments/' ), __( 'Hubungi Kami', 'gloskin-site-core' ), home_url( '/contact/' ) ); ?>
</div></section>
