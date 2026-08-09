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

$view             = isset( $gloskin_context['view'] ) ? sanitize_key( $gloskin_context['view'] ) : '';
$variant          = isset( $gloskin_context['design_variant'] ) ? sanitize_key( $gloskin_context['design_variant'] ) : 'medical';
$view_file        = __DIR__ . '/pages/' . $view . '.php';
$commerce_native  = ! empty( $gloskin_context['commerce_native'] );
$commerce_render  = isset( $gloskin_context['commerce_render_mode'] ) ? sanitize_key( $gloskin_context['commerce_render_mode'] ) : '';

require __DIR__ . '/parts/template-helpers.php';
require __DIR__ . '/parts/readiness-helpers.php';
require __DIR__ . '/parts/composition-helpers.php';
?><!doctype html>
<html lang="id">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( array( 'gloskin-ui1', 'gloskin-ui1--' . $variant ) ); ?>>
<?php wp_body_open(); ?>
<?php require __DIR__ . '/parts/header.php'; ?>
<main id="gloskin-main" class="gloskin-ui1-main">
	<?php gloskin_ui1_render_breadcrumbs( $gloskin_context ); ?>
	<?php
	if ( $commerce_native ) {
		/* The shell/provider breadcrumb is the visible owner on Gloskin-owned
		 * Woo requests. Suppress Woo's classic visible breadcrumb only for this
		 * request; Woo remains untouched outside this shell. */
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		}
		if ( 'page' === $commerce_render ) {
			gloskin_ui1_render_commerce_page_heading();
		}
		/* Replace only Woo's classic empty-cart message inside this Gloskin
		 * shell request; Woo still owns cart state, routing and form handling. */
		if ( function_exists( 'is_cart' ) && is_cart() && function_exists( 'remove_action' ) ) {
			remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
			add_action( 'woocommerce_cart_is_empty', 'gloskin_ui1_render_native_cart_empty_state', 10 );
		}
		echo '<div class="woocommerce gloskin-ui1-commerce-native">';
		if ( 'woocommerce' === $commerce_render && function_exists( 'woocommerce_content' ) ) {
			woocommerce_content();
		} elseif ( function_exists( 'have_posts' ) ) {
			while ( have_posts() ) {
				the_post();
				the_content();
			}
		}
		echo '</div>';
	} elseif ( is_readable( $view_file ) ) {
		require $view_file;
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
