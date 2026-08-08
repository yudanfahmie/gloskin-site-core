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
	<?php
	if ( $commerce_native ) {
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
		gloskin_ui1_empty( __( 'Halaman Gloskin ini tidak tersedia.', 'gloskin-site-core' ) );
	}
	?>
</main>
<?php require __DIR__ . '/parts/footer.php'; ?>
<?php wp_footer(); ?>
</body>
</html>
