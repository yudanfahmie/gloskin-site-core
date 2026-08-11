<?php
/**
 * Stable single-product description boundary for Gloskin-owned Woo pages.
 *
 * WooCommerce remains the product/content authority. This helper only
 * replaces Woo's Description-tab callback so the product description is
 * formatted without executing the global `the_content` filter chain, which
 * may contain unrelated theme/plugin callbacks capable of re-entering a full
 * single-product render.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the boundary only for a real Woo single-product request.
 *
 * @return void
 */
function gloskin_ui1_register_product_description_boundary() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	add_filter( 'woocommerce_product_tabs', 'gloskin_ui1_product_tabs_description_boundary', PHP_INT_MAX );
}

/**
 * Keep Woo's tabs and tab visibility rules; replace only Description output.
 *
 * @param array<string,mixed> $tabs Woo product tabs.
 * @return array<string,mixed>
 */
function gloskin_ui1_product_tabs_description_boundary( $tabs ) {
	if ( ! is_array( $tabs ) || ! isset( $tabs['description'] ) || ! is_array( $tabs['description'] ) ) {
		return $tabs;
	}
	$tabs['description']['callback'] = 'gloskin_ui1_render_product_description';
	return $tabs;
}

/**
 * Render Woo's own product description through a small, request-local
 * presentation pipeline. No product/cart/stock state is created here.
 *
 * @return void
 */
function gloskin_ui1_render_product_description( $key = '', $tab = array() ) {
	global $product;

	if ( ! is_object( $product ) || ! method_exists( $product, 'get_description' ) ) {
		return;
	}

	$heading = apply_filters( 'woocommerce_product_description_heading', __( 'Description', 'woocommerce' ) );
	if ( $heading ) {
		echo '<h2>' . esc_html( $heading ) . '</h2>';
	}

	$content = (string) $product->get_description();
	if ( '' === trim( $content ) ) {
		return;
	}

	echo gloskin_ui1_format_product_description( $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted editor content formatted through WordPress/Woo presentation functions below.
}

/**
 * Format product description without global `the_content` callbacks.
 *
 * Full single-product embeds are intentionally unsupported inside a product
 * Description: Woo already owns the surrounding single-product page and the
 * Related Products surface. Blocking that one renderer prevents accidental
 * recursive product trees while preserving ordinary blocks, HTML and other
 * shortcodes.
 *
 * @param string $content Raw Woo product description.
 * @return string
 */
function gloskin_ui1_format_product_description( $content ) {
	if ( ! is_string( $content ) || '' === $content ) {
		return '';
	}

	// A full Woo single-product block inside a single-product description is
	// structurally recursive. Remove only that block family before do_blocks().
	$content = (string) preg_replace(
		'#<!--\s*wp:woocommerce/single-product\b[^>]*-->.*?<!--\s*/wp:woocommerce/single-product\s*-->#is',
		'',
		$content
	);
	$content = (string) preg_replace(
		'#<!--\s*wp:woocommerce/single-product\b[^>]*/-->#is',
		'',
		$content
	);

	$fallback_content = $content;

	global $shortcode_tags;
	$had_registry          = is_array( $shortcode_tags );
	$had_product_page      = $had_registry && array_key_exists( 'product_page', $shortcode_tags );
	$product_page_callback = $had_product_page ? $shortcode_tags['product_page'] : null;
	if ( ! $had_registry ) {
		$shortcode_tags = array();
	}

	// Activate the blocker before do_blocks(): WordPress's core/shortcode
	// block may execute shortcodes during block rendering, not only during
	// the later classic-shortcode pass.
	$shortcode_tags['product_page'] = 'gloskin_ui1_block_product_page_shortcode';

	try {
		if ( function_exists( 'do_blocks' ) ) {
			$content = do_blocks( $content );
		}
		if ( function_exists( 'wptexturize' ) ) {
			$content = wptexturize( $content );
		}
		if ( function_exists( 'wpautop' ) ) {
			$content = wpautop( $content );
		}
		if ( function_exists( 'shortcode_unautop' ) ) {
			$content = shortcode_unautop( $content );
		}
		if ( function_exists( 'do_shortcode' ) ) {
			$content = do_shortcode( $content );
		}
	} catch ( Throwable $error ) {
		// Content extensions are presentation-only here. A broken third-party
		// block/shortcode must not crash the commerce page; preserve readable
		// text from the authoritative Woo description instead.
		$content = function_exists( 'wpautop' ) ? wpautop( $fallback_content ) : $fallback_content;
	} finally {
		if ( $had_product_page ) {
			$shortcode_tags['product_page'] = $product_page_callback;
		} else {
			unset( $shortcode_tags['product_page'] );
		}
	}

	if ( function_exists( 'wp_filter_content_tags' ) ) {
		$content = wp_filter_content_tags( $content );
	}

	return $content;
}

/**
 * Request-local replacement for Woo's [product_page] shortcode while a
 * product Description is being formatted.
 *
 * @return string
 */
function gloskin_ui1_block_product_page_shortcode() {
	return '';
}
