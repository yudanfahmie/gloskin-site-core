<?php
/** Mobile navigation drawer. @package GloskinSiteCore */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$commerce = isset( $gloskin_context['commerce'] ) && is_array( $gloskin_context['commerce'] ) ? $gloskin_context['commerce'] : array( 'available' => false );
$woo      = ! empty( $commerce['available'] );
?>
<div class="gloskin-ui1-drawer" id="gloskin-mobile-drawer" data-gloskin-drawer aria-hidden="true" hidden>
	<button class="gloskin-ui1-drawer__backdrop" type="button" data-gloskin-drawer-close aria-label="<?php echo esc_attr__( 'Tutup navigasi', 'gloskin-site-core' ); ?>"></button>
	<div class="gloskin-ui1-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="gloskin-mobile-nav-title">
		<div class="gloskin-ui1-drawer__head"><strong id="gloskin-mobile-nav-title">Gloskin</strong><button class="gloskin-ui1-drawer__close" type="button" data-gloskin-drawer-close><span class="screen-reader-text"><?php echo esc_html__( 'Tutup navigasi', 'gloskin-site-core' ); ?></span><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false"><path d="M4 4l10 10M14 4 4 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></button></div>
		<nav class="gloskin-ui1-nav gloskin-ui1-nav--mobile" aria-label="<?php echo esc_attr__( 'Navigasi seluler', 'gloskin-site-core' ); ?>"><?php gloskin_ui1_render_nav_tree( $navigation, 'mobile' ); ?></nav>
		<?php if ( $woo ) : ?>
		<div class="gloskin-ui1-drawer__utilities">
			<?php if ( ! empty( $commerce['account_url'] ) ) : ?><a class="gloskin-ui1-drawer__utility-link" href="<?php echo esc_url( $commerce['account_url'] ); ?>"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><circle cx="10" cy="7.5" r="3.5" stroke="currentColor" stroke-width="1.4"/><path d="M3.5 17.5c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg><?php echo esc_html( is_user_logged_in() ? __( 'Akun saya', 'gloskin-site-core' ) : __( 'Masuk', 'gloskin-site-core' ) ); ?></a><?php endif; ?>
			<button class="gloskin-ui1-drawer__utility-link" type="button" data-gloskin-drawer-close data-gloskin-wishlist-open-from-drawer><svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M10 16.8C8.4 15.5 3 11.4 3 7.8 3 5.6 4.8 3.5 7.2 3.5c1.3 0 2.2.7 2.8 1.3.6-.6 1.5-1.3 2.8-1.3C15.2 3.5 17 5.6 17 7.8c0 3.6-5.4 7.7-7 9z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg><?php echo esc_html__( 'Produk Favorit', 'gloskin-site-core' ); ?></button>
		</div>
		<?php endif; ?>
		<div class="gloskin-ui1-drawer__cta"><a class="gloskin-ui1-button gloskin-ui1-button--primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Hubungi Gloskin', 'gloskin-site-core' ); ?></a></div>
	</div>
</div>
