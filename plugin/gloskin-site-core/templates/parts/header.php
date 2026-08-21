<?php
/** Global Gloskin header. @package GloskinSiteCore */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$gloskin_navigation = isset( $gloskin_context['navigation'] ) && is_array( $gloskin_context['navigation'] ) ? $gloskin_context['navigation'] : array();
$gloskin_commerce   = isset( $gloskin_context['commerce'] ) && is_array( $gloskin_context['commerce'] ) ? $gloskin_context['commerce'] : array( 'available' => false );
$gloskin_woo        = ! empty( $gloskin_commerce['available'] );
$gloskin_logo_url   = isset( $gloskin_context['logo_url'] ) ? (string) $gloskin_context['logo_url'] : '';
$gloskin_quick_auth = ! empty( $gloskin_commerce['quick_auth'] );
$gloskin_auth_attrs = $gloskin_quick_auth ? ' data-gloskin-auth-open aria-controls="gloskin-auth-overlay" aria-expanded="false"' : '';
$gloskin_language   = class_exists( 'Gloskin_Site_Core_Language' ) ? Gloskin_Site_Core_Language::language() : 'id';
$gloskin_language_urls = array(
	'id' => class_exists( 'Gloskin_Site_Core_Language' ) ? Gloskin_Site_Core_Language::switch_url( 'id' ) : add_query_arg( 'gloskin_lang', 'id' ),
	'en' => class_exists( 'Gloskin_Site_Core_Language' ) ? Gloskin_Site_Core_Language::switch_url( 'en' ) : add_query_arg( 'gloskin_lang', 'en' ),
);
$gloskin_render_language_flag = static function ( $language ) {
	if ( 'id' === $language ) {
		echo '<svg class="gloskin-ui1-lang-switcher__flag" viewBox="0 0 18 12" aria-hidden="true" focusable="false"><path fill="#e70011" d="M0 0h18v6H0z"/><path fill="#fff" d="M0 6h18v6H0z"/></svg>';
		return;
	}
	echo '<svg class="gloskin-ui1-lang-switcher__flag" viewBox="0 0 18 12" aria-hidden="true" focusable="false"><path fill="#012169" d="M0 0h18v12H0z"/><path fill="#fff" d="m0 0 18 12m0-12L0 12" stroke="#fff" stroke-width="2.4"/><path fill="#c8102e" d="m0 0 18 12m0-12L0 12" stroke="#c8102e" stroke-width="1.2"/><path fill="#fff" d="M7.2 0h3.6v12H7.2zM0 4.2h18v3.6H0z"/><path fill="#c8102e" d="M7.9 0h2.2v12H7.9zM0 4.9h18v2.2H0z"/></svg>';
};

/* The 2026-08-18 prototype is the sole public presentation. Historical
 * presentation settings may remain stored for compatibility, but this renderer
 * deliberately has one markup path and never reads those settings. */
if ( ! function_exists( 'gloskin_ui1_render_nav_tree' ) ) {
	function gloskin_ui1_render_nav_tree( $items, $scope ) {
		echo '<ul class="gloskin-ui1-nav__list">';
		foreach ( $items as $index => $item ) {
			$children = isset( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : array();
			$label = isset( $item['label'] ) ? (string) $item['label'] : '';
			$label = __( $label, 'gloskin-site-core' );
			$url = isset( $item['url'] ) ? (string) $item['url'] : '';
			$active = ! empty( $item['active'] );
			$submenu = 'gloskin-submenu-' . sanitize_html_class( $scope ) . '-' . absint( $index );
			echo '<li class="gloskin-ui1-nav__item' . ( $active ? ' is-active' : '' ) . '"><div class="gloskin-ui1-nav__row">';
			echo '<a class="gloskin-ui1-nav__link" href="' . esc_url( $url ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
			if ( $children ) {
				echo '<button class="gloskin-ui1-nav__toggle" type="button" data-gloskin-submenu-toggle aria-expanded="false" aria-controls="' . esc_attr( $submenu ) . '">';
				/* translators: %s: the parent navigation item's label whose submenu this button expands. */
				echo '<span class="screen-reader-text">' . esc_html( sprintf( __( 'Buka submenu %s', 'gloskin-site-core' ), $label ) ) . '</span>';
				echo '<svg class="gloskin-ui1-nav__chevron" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4 6.25 8 10l4-3.75" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
			}
			echo '</div>';
			if ( $children ) {
				echo '<div class="gloskin-ui1-nav__submenu" id="' . esc_attr( $submenu ) . '" hidden>';
				gloskin_ui1_render_nav_tree( $children, $scope . '-' . $index );
				echo '</div>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}
?>
<a class="gloskin-ui1-skip-link" href="#gloskin-main"><?php echo esc_html__( 'Lewati ke konten utama', 'gloskin-site-core' ); ?></a>
<header class="gloskin-ui1-header" data-gloskin-header="header-2">
	<div class="gloskin-ui1-container gloskin-ui1-header__inner">
		<a class="gloskin-ui1-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr__( 'Beranda Gloskin', 'gloskin-site-core' ); ?>"><?php gloskin_ui1_render_brand_logo( $gloskin_logo_url ); ?></a>
		<nav class="gloskin-ui1-nav gloskin-ui1-nav--desktop" aria-label="<?php echo esc_attr__( 'Navigasi utama', 'gloskin-site-core' ); ?>"><span class="gloskin-ui1-nav__bubble" aria-hidden="true"></span><?php gloskin_ui1_render_nav_tree( $gloskin_navigation, 'desktop' ); ?></nav>
		<div class="gloskin-ui1-header__zone gloskin-ui1-header__zone--end">
			<button class="gloskin-ui1-utility-btn" type="button" data-gloskin-search-open aria-expanded="false" aria-controls="gloskin-search-overlay" aria-label="<?php echo esc_attr__( 'Cari', 'gloskin-site-core' ); ?>"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.5"/><path d="m13 13 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></button>
			<?php if ( $gloskin_woo && ! empty( $gloskin_commerce['account_url'] ) ) : ?><a class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--account" href="<?php echo esc_url( $gloskin_commerce['account_url'] ); ?>"<?php echo $gloskin_auth_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed constant attribute string, not user data. ?> aria-label="<?php echo esc_attr( is_user_logged_in() ? __( 'Akun saya', 'gloskin-site-core' ) : __( 'Masuk', 'gloskin-site-core' ) ); ?>"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><circle cx="10" cy="7.5" r="3.5" stroke="currentColor" stroke-width="1.4"/><path d="M3.5 17.5c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></a><?php endif; ?>
			<?php if ( $gloskin_woo ) : ?><button class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--wishlist" type="button" data-gloskin-wishlist-open aria-expanded="false" aria-controls="gloskin-wishlist-sheet" aria-label="<?php echo esc_attr__( 'Produk favorit', 'gloskin-site-core' ); ?>"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M10 16.8C8.4 15.5 3 11.4 3 7.8 3 5.6 4.8 3.5 7.2 3.5c1.3 0 2.2.7 2.8 1.3.6-.6 1.5-1.3 2.8-1.3C15.2 3.5 17 5.6 17 7.8c0 3.6-5.4 7.7-7 9z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg><span class="gloskin-ui1-badge" data-gloskin-wishlist-count aria-hidden="true">0</span><span class="screen-reader-text" data-gloskin-wishlist-count-sr aria-live="polite"><?php echo esc_html( sprintf( __( '%d produk favorit', 'gloskin-site-core' ), 0 ) ); ?></span></button><?php endif; ?>
			<?php if ( $gloskin_woo ) :
				/* translators: %d: number of items currently in the cart. */
				$gloskin_cart_count_label = sprintf( __( '%d item di keranjang', 'gloskin-site-core' ), $gloskin_commerce['cart_count'] );
			?><button class="gloskin-ui1-utility-btn gloskin-ui1-utility-btn--cart" type="button" data-gloskin-cart-open aria-expanded="false" aria-controls="gloskin-cart-sheet" aria-live="polite" aria-label="<?php echo esc_attr__( 'Keranjang', 'gloskin-site-core' ); ?>"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M5 6h10l.8 10H4.2L5 6z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7.5 6V5a2.5 2.5 0 0 1 5 0v1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg><span class="gloskin-ui1-badge<?php echo $gloskin_commerce['cart_count'] > 0 ? ' is-active' : ''; ?>" data-gloskin-cart-count aria-hidden="true"><?php echo esc_html( $gloskin_commerce['cart_count'] ); ?></span><span class="screen-reader-text" data-gloskin-cart-count-sr><?php echo esc_html( $gloskin_cart_count_label ); ?></span></button><?php endif; ?>
			<details class="gloskin-ui1-lang-switcher">
				<summary class="gloskin-ui1-lang-switcher__summary" aria-label="<?php echo esc_attr( 'id' === $gloskin_language ? __( 'Bahasa Indonesia', 'gloskin-site-core' ) : 'English' ); ?>">
					<span class="gloskin-ui1-lang-switcher__option gloskin-ui1-lang-switcher__option--current" aria-current="true" lang="<?php echo esc_attr( $gloskin_language ); ?>"><?php $gloskin_render_language_flag( $gloskin_language ); ?><span><?php echo esc_html( strtoupper( $gloskin_language ) ); ?></span></span>
					<svg class="gloskin-ui1-lang-switcher__chevron" viewBox="0 0 12 8" aria-hidden="true" focusable="false"><path d="M1.5 2 6 6l4.5-4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</summary>
				<div class="gloskin-ui1-lang-switcher__menu">
					<?php if ( 'id' === $gloskin_language ) : ?>
						<a class="gloskin-ui1-lang-switcher__option" href="<?php echo esc_url( $gloskin_language_urls['en'] ); ?>" hreflang="en" aria-label="English" lang="en"><?php $gloskin_render_language_flag( 'en' ); ?><span>EN</span></a>
					<?php else : ?>
						<a class="gloskin-ui1-lang-switcher__option" href="<?php echo esc_url( $gloskin_language_urls['id'] ); ?>" hreflang="id" aria-label="<?php echo esc_attr__( 'Bahasa Indonesia', 'gloskin-site-core' ); ?>" lang="id"><?php $gloskin_render_language_flag( 'id' ); ?><span>ID</span></a>
					<?php endif; ?>
				</div>
			</details>
			<button class="gloskin-ui1-drawer-toggle" type="button" data-gloskin-drawer-open aria-expanded="false" aria-controls="gloskin-mobile-drawer">
				<span class="screen-reader-text"><?php echo esc_html__( 'Buka navigasi', 'gloskin-site-core' ); ?></span>
				<svg width="24" height="24" viewBox="0 0 22 22" aria-hidden="true" focusable="false">
					<circle class="gloskin-ui1-drawer-toggle__dot" cx="6" cy="7" r="1.7" style="--gloskin-dot-x:5px;--gloskin-dot-y:4px" fill="currentColor"/>
					<circle class="gloskin-ui1-drawer-toggle__dot" cx="11" cy="7" r="1.7" style="--gloskin-dot-x:0px;--gloskin-dot-y:4px" fill="currentColor"/>
					<circle class="gloskin-ui1-drawer-toggle__dot" cx="16" cy="7" r="1.7" style="--gloskin-dot-x:-5px;--gloskin-dot-y:4px" fill="currentColor"/>
					<circle class="gloskin-ui1-drawer-toggle__dot" cx="6" cy="15" r="1.7" style="--gloskin-dot-x:5px;--gloskin-dot-y:-4px" fill="currentColor"/>
					<circle class="gloskin-ui1-drawer-toggle__dot" cx="11" cy="15" r="1.7" style="--gloskin-dot-x:0px;--gloskin-dot-y:-4px" fill="currentColor"/>
					<circle class="gloskin-ui1-drawer-toggle__dot" cx="16" cy="15" r="1.7" style="--gloskin-dot-x:-5px;--gloskin-dot-y:-4px" fill="currentColor"/>
				</svg>
			</button>
		</div>
	</div>
</header>
<div class="gloskin-ui1-search-overlay" id="gloskin-search-overlay" data-gloskin-overlay="search" aria-hidden="true" hidden>
	<div class="gloskin-ui1-search-overlay__backdrop" data-gloskin-overlay-close></div>
	<div class="gloskin-ui1-search-overlay__canvas" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'Pencarian Gloskin', 'gloskin-site-core' ); ?>">
		<div class="gloskin-ui1-search-overlay__head">
			<div class="gloskin-ui1-search-overlay__field">
				<svg class="gloskin-ui1-search-overlay__icon" width="22" height="22" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.5"/><path d="m13 13 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
				<input class="gloskin-ui1-search-overlay__input" type="search" data-gloskin-search-input placeholder="<?php echo esc_attr__( 'Cari perawatan, klinik, dokter, atau produk', 'gloskin-site-core' ); ?>" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
				<button class="gloskin-ui1-search-overlay__clear" type="button" data-gloskin-search-clear hidden aria-label="<?php echo esc_attr__( 'Hapus pencarian', 'gloskin-site-core' ); ?>">&times;</button>
			</div>
			<button class="gloskin-ui1-search-overlay__close" type="button" data-gloskin-overlay-close aria-label="<?php echo esc_attr__( 'Tutup pencarian', 'gloskin-site-core' ); ?>"><?php echo esc_html__( 'Batal', 'gloskin-site-core' ); ?></button>
		</div>
		<div class="gloskin-ui1-search-overlay__body" data-gloskin-search-results aria-live="polite"></div>
		<noscript><form class="gloskin-ui1-search-overlay__fallback" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get"><input type="search" name="s" placeholder="<?php echo esc_attr__( 'Cari...', 'gloskin-site-core' ); ?>"><button type="submit" class="gloskin-ui1-button gloskin-ui1-button--primary"><?php echo esc_html__( 'Cari', 'gloskin-site-core' ); ?></button></form></noscript>
	</div>
</div>
<?php if ( $gloskin_woo ) : ?>
<div class="gloskin-ui1-sheet" id="gloskin-cart-sheet" data-gloskin-overlay="cart" aria-hidden="true" hidden>
	<button class="gloskin-ui1-sheet__backdrop" type="button" data-gloskin-overlay-close aria-label="<?php echo esc_attr__( 'Tutup keranjang', 'gloskin-site-core' ); ?>"></button>
	<div class="gloskin-ui1-sheet__panel gloskin-ui1-cart-sheet" role="dialog" aria-modal="true" aria-labelledby="gloskin-cart-title">
		<div class="gloskin-ui1-sheet__head"><strong id="gloskin-cart-title"><?php echo esc_html__( 'Keranjang', 'gloskin-site-core' ); ?></strong><button class="gloskin-ui1-sheet__close" type="button" data-gloskin-overlay-close aria-label="<?php echo esc_attr__( 'Tutup keranjang', 'gloskin-site-core' ); ?>"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false"><path d="M4 4l10 10M14 4 4 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></button></div>
		<div class="gloskin-ui1-cart-sheet__body"><?php
			/*
			 * Trusted-renderer contract: $gloskin_commerce['mini_cart'] is produced only by
			 * Gloskin_Site_Core_WooCommerce_Adapter::render_mini_cart_body(), the sole
			 * mini-cart HTML renderer. Every dynamic value it interpolates (name, url,
			 * variation, price/subtotal, remove link, image) is esc_html()/esc_url()/
			 * esc_attr()/wp_kses_post()-ed or produced by core WordPress/Woo output
			 * helpers inside that method -- see class-gloskin-site-core-woocommerce-adapter.php.
			 * Header never echoes arbitrary user/meta HTML here, only that renderer's output.
			 */
			echo $gloskin_commerce['mini_cart']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted renderer output, escaped at source; see comment above.
		?></div>
	</div>
</div>
<div class="gloskin-ui1-sheet" id="gloskin-wishlist-sheet" data-gloskin-overlay="wishlist" aria-hidden="true" hidden>
	<button class="gloskin-ui1-sheet__backdrop" type="button" data-gloskin-overlay-close aria-label="<?php echo esc_attr__( 'Tutup favorit', 'gloskin-site-core' ); ?>"></button>
	<div class="gloskin-ui1-sheet__panel gloskin-ui1-wishlist-sheet" role="dialog" aria-modal="true" aria-labelledby="gloskin-wishlist-title">
		<div class="gloskin-ui1-sheet__head"><strong id="gloskin-wishlist-title"><?php echo esc_html__( 'Produk Favorit', 'gloskin-site-core' ); ?></strong><button class="gloskin-ui1-sheet__close" type="button" data-gloskin-overlay-close aria-label="<?php echo esc_attr__( 'Tutup favorit', 'gloskin-site-core' ); ?>"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false"><path d="M4 4l10 10M14 4 4 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div>
		<div class="gloskin-ui1-wishlist-sheet__body" data-gloskin-wishlist-body></div>
	</div>
</div>
<?php require __DIR__ . '/quick-add.php'; ?>
<?php endif; ?>
<script>var gloskinData=<?php echo wp_json_encode( array(
	'restUrl'        => esc_url_raw( rest_url( 'gloskin/v1/' ) ),
	'searchFallback' => esc_url( home_url( '/?s=' ) ),
	'woo'            => $gloskin_woo,
	'cartUrl'        => $gloskin_woo ? esc_url( $gloskin_commerce['cart_url'] ) : '',
	'checkoutUrl'    => $gloskin_woo ? esc_url( $gloskin_commerce['checkout_url'] ) : '',
	'addToCartAjaxUrl' => $gloskin_woo && ! empty( $gloskin_commerce['add_to_cart_ajax_url'] ) ? esc_url_raw( $gloskin_commerce['add_to_cart_ajax_url'] ) : '',
	'cartCtaLabel'   => $gloskin_woo && ! empty( $gloskin_commerce['cart_cta_label'] ) ? (string) $gloskin_commerce['cart_cta_label'] : '',
) ); ?>;</script>
<?php require __DIR__ . '/mobile-drawer.php'; ?>