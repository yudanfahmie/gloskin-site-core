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
$gloskin_variant          = isset( $gloskin_context['design_variant'] ) ? sanitize_key( $gloskin_context['design_variant'] ) : 'medical';
$gloskin_view_file        = __DIR__ . '/pages/' . $gloskin_view . '.php';
$gloskin_commerce_native  = ! empty( $gloskin_context['commerce_native'] );
$gloskin_commerce_render  = isset( $gloskin_context['commerce_render_mode'] ) ? sanitize_key( $gloskin_context['commerce_render_mode'] ) : '';

require __DIR__ . '/parts/template-helpers.php';
require __DIR__ . '/parts/readiness-helpers.php';
require __DIR__ . '/parts/composition-helpers.php';
require __DIR__ . '/parts/product-description-boundary.php';
$gloskin_body_classes = array( 'gloskin-ui1', 'gloskin-ui1--' . $gloskin_variant );
if ( 'home' === $gloskin_view ) {
	/* Home-only header entrance owner (see gloskin-ui1-core-base.css). No
	 * second view/body-class owner is introduced -- $gloskin_view is the
	 * same canonical value gloskin_ui1_render_breadcrumbs() already reads. */
	$gloskin_body_classes[] = 'gloskin-ui1--home';
}
?><!doctype html>
<html lang="id">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( $gloskin_body_classes ); ?>>
<?php wp_body_open(); ?>
<?php require __DIR__ . '/parts/header.php'; ?>
<main id="gloskin-main" class="gloskin-ui1-main">
	<?php gloskin_ui1_render_breadcrumbs( $gloskin_context ); ?>
	<?php
	if ( $gloskin_commerce_native ) {
		/* The shell/provider breadcrumb is the visible owner on Gloskin-owned
		 * Woo requests. Suppress Woo's classic visible breadcrumb only for this
		 * request; Woo remains untouched outside this shell. */
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
			echo '<svg class="gloskin-ui1-commerce-handoff__defs" xmlns="http://www.w3.org/2000/svg" width="0" height="0" aria-hidden="true" focusable="false"><defs><filter id="gloskin-ui1-commerce-handoff-goo" x="-80%" y="-80%" width="260%" height="260%"><feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur"/><feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -10" result="goo"/></filter></defs></svg>';
			echo '<div class="gloskin-ui1-commerce-handoff__goo"><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span><span class="gloskin-ui1-commerce-handoff__blob"></span></div>';
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
