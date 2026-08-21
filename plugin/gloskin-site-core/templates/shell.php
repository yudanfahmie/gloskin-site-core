<?php
/**
 * Gloskin UI v1 global shell.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gloskin_context = get_query_var( 'gloskin_context', array() );
if ( ! is_array( $gloskin_context ) ) {
	$gloskin_context = array();
}

$gloskin_view             = isset( $gloskin_context['view'] ) ? sanitize_key( $gloskin_context['view'] ) : '';
$gloskin_view_file        = __DIR__ . '/pages/' . $gloskin_view . '.php';
$gloskin_commerce_native  = ! empty( $gloskin_context['commerce_native'] );
$gloskin_commerce_render  = isset( $gloskin_context['commerce_render_mode'] ) ? sanitize_key( $gloskin_context['commerce_render_mode'] ) : '';

require_once __DIR__ . '/parts/template-helpers.php';
require_once __DIR__ . '/parts/readiness-helpers.php';
require_once __DIR__ . '/parts/composition-helpers.php';
require_once __DIR__ . '/parts/product-description-boundary.php';
/* The approved prototype is now the only public presentation. Historical
 * presentation settings may remain stored for backward compatibility, but the
 * public shell intentionally never reads or projects them into CSS. */
$gloskin_body_classes = array( 'gloskin-ui1' );
if ( 'home' === $gloskin_view ) {
	/* Home-only header entrance owner (see gloskin-ui1-core-base.css). The
	 * canonical query-context view remains the sole route presentation input. */
	$gloskin_body_classes[] = 'gloskin-ui1--home';
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( $gloskin_body_classes ); ?>>
<?php wp_body_open(); ?>
<?php /* Page transition overlay. JS (initPageTransitions in gloskin-ui1-core.js) owns
       * the is-active class lifecycle. Static markup only — no state or data attributes.
       * G path is the canonical first letterform from assets/images/gloskin-logotext.svg. */ ?>
<div class="gloskin-ui1-page-transition" data-gloskin-page-transition aria-hidden="true">
	<div class="gloskin-ui1-page-transition__blob">
		<svg class="gloskin-ui1-page-transition__g" viewBox="82 74 185 232" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M647 271H415V239H528V120C528 102 527 88 523 80C520 69 501 56 466 44C428 29 392 21 357 22C300 23 255 53 221 112C187 172 170 249 170 345C170 555 235 665 365 673C481 679 530 624 539 510H569V667C531 682 506 690 495 692C462 700 417 704 360 704C275 704 204 672 145 607C86 539 56 453 55 345C54 246 83 161 142 90C204 21 275 -14 360 -14C419 -14 468 -8 509 2C544 13 578 25 613 35V239H647Z" fill="#fff" transform="translate(65,300) scale(0.3117268,-0.32)"/></svg>
	</div>
</div>
<svg class="gloskin-ui1-goo-loader-defs" xmlns="http://www.w3.org/2000/svg" width="0" height="0" aria-hidden="true" focusable="false"><defs><filter id="gloskin-ui1-commerce-handoff-goo" x="-80%" y="-80%" width="260%" height="260%"><feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur"/><feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -10" result="goo"/></filter></defs></svg>
<?php require __DIR__ . '/parts/header.php'; ?>
<main id="gloskin-main" class="gloskin-ui1-main">
	<?php
	if ( $gloskin_commerce_native ) {
		/* Gloskin intentionally renders no visible breadcrumb. Keep suppressing
		 * WooCommerce's classic breadcrumb on Gloskin-owned commerce requests so
		 * removing the shell breadcrumb cannot reveal a second native owner. */
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		}
		if ( 'page' === $gloskin_commerce_render ) {
			gloskin_ui1_render_commerce_page_heading();
		}
		/* Replace only Woo's classic empty-cart message inside this Gloskin
		 * shell request; Woo still owns cart state, routing and form handling. */
		if ( function_exists( 'is_cart' ) && is_cart() && function_exists( 'remove_action' ) ) {
			remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
			add_action( 'woocommerce_cart_is_empty', 'gloskin_ui1_render_native_cart_empty_state', 10 );
		}
		/* Keep Woo's native single-product renderer, but isolate the Description
		 * tab from unrelated global the_content callbacks that can recursively
		 * render another complete product tree. */
		gloskin_ui1_register_product_description_boundary();

		/* Cart <-> Checkout handoff loader. Static decorative markup only: the
		 * journey runtime owns when its state classes appear, while Woo remains
		 * the sole owner of navigation, document markup and commerce lifecycle. */
		$gloskin_commerce_journey_loader = ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() );
		if ( $gloskin_commerce_journey_loader ) {
			echo '<div class="gloskin-ui1-commerce-handoff" data-gloskin-commerce-handoff aria-hidden="true">';
			echo '<div class="gloskin-ui1-commerce-handoff__goo"><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span></div>';
			echo '<svg class="gloskin-ui1-commerce-handoff__g" viewBox="82 74 185 232" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M647 271H415V239H528V120C528 102 527 88 523 80C520 69 501 56 466 44C428 29 392 21 357 22C300 23 255 53 221 112C187 172 170 249 170 345C170 555 235 665 365 673C481 679 530 624 539 510H569V667C531 682 506 690 495 692C462 700 417 704 360 704C275 704 204 672 145 607C86 539 56 453 55 345C54 246 83 161 142 90C204 21 275 -14 360 -14C419 -14 468 -8 509 2C544 13 578 25 613 35V239H647Z" fill="#fff" transform="translate(65,300) scale(0.3117268,-0.32)"/></svg>';
			echo '</div>';
		}

		echo '<div class="woocommerce gloskin-ui1-commerce-native">';
		if ( 'woocommerce' === $gloskin_commerce_render && function_exists( 'woocommerce_content' ) ) {
			woocommerce_content();
		} elseif ( function_exists( 'have_posts' ) ) {
			while ( have_posts() ) {
				the_post();
				the_content();
			}
		}
		echo '</div>';
	} elseif ( is_readable( $gloskin_view_file ) ) {
		require $gloskin_view_file;
	} else {
		gloskin_ui1_render_empty_state(
			'generic',
			__( 'Halaman Gloskin ini tidak tersedia', 'gloskin-site-core' ),
			__( 'Gunakan navigasi utama untuk melanjutkan ke halaman lain.', 'gloskin-site-core' ),
			__( 'Kembali ke Beranda', 'gloskin-site-core' ),
			home_url( '/' )
		);
	}
	?>
</main>
<?php require __DIR__ . '/parts/footer.php'; ?>
<?php do_action( 'gloskin_site_core_shell_footer' ); ?>
<?php wp_footer(); ?>
</body>
</html>
