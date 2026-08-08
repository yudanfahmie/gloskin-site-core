<?php
/** Mobile navigation drawer. @package GloskinSiteCore */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="gloskin-ui1-drawer" id="gloskin-mobile-drawer" data-gloskin-drawer aria-hidden="true" hidden>
	<button class="gloskin-ui1-drawer__backdrop" type="button" data-gloskin-drawer-close aria-label="<?php echo esc_attr__( 'Tutup navigasi', 'gloskin-site-core' ); ?>"></button>
	<div class="gloskin-ui1-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="gloskin-mobile-nav-title">
		<div class="gloskin-ui1-drawer__head"><strong id="gloskin-mobile-nav-title">Gloskin</strong><button class="gloskin-ui1-drawer__close" type="button" data-gloskin-drawer-close><span class="screen-reader-text"><?php echo esc_html__( 'Tutup navigasi', 'gloskin-site-core' ); ?></span><span aria-hidden="true">×</span></button></div>
		<nav class="gloskin-ui1-nav gloskin-ui1-nav--mobile" aria-label="<?php echo esc_attr__( 'Navigasi seluler', 'gloskin-site-core' ); ?>"><?php gloskin_ui1_render_nav_tree( $navigation, 'mobile' ); ?></nav>
		<div class="gloskin-ui1-drawer__cta"><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Hubungi Gloskin', 'gloskin-site-core' ); ?></a></div>
	</div>
</div>
