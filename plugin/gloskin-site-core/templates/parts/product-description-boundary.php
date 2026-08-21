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
 * PDP simplification: Woo's own Description/Additional Information/Reviews
 * tab strip is removed entirely (Woo's own woocommerce_output_product_data_tabs()
 * template already no-ops when the tabs array is empty -- no template fork
 * needed). The Short Description is now the one canonical PDP body field,
 * rendered natively by Woo's own woocommerce_template_single_excerpt() in
 * the primary summary; this boundary keeps its safety-formatting pipeline
 * alive by rerouting it onto that field via woocommerce_short_description
 * instead, so a self-referencing/recursive [product_page] or
 * wp:woocommerce/single-product embed can never re-enter a full
 * single-product render from there either.
 *
 * @return void
 */
function gloskin_ui1_register_product_description_boundary() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	add_filter( 'woocommerce_product_tabs', 'gloskin_ui1_product_tabs_description_boundary', PHP_INT_MAX );
	add_filter( 'woocommerce_short_description', 'gloskin_ui1_render_primary_pdp_description' );
}

/**
 * Render the primary PDP short description through the canonical format pipeline.
 *
 * Live merge contract: when post_content (the durable full description written
 * by the Content Finalizer) contains blocks not already present in post_excerpt
 * (the short description shown on the PDP), those blocks are appended once so
 * the full content is visible without requiring the one-time admin action to
 * have run first. For fully consolidated products this is a no-op (no duplication).
 * The full description remains the authoritative durable companion field written
 * by the Content Finalizer; this function only bridges presentation until
 * consolidation is confirmed on every canonical product.
 *
 * @param string $content Woo's own short-description value for the current product.
 * @return string
 */
function gloskin_ui1_render_primary_pdp_description( $content ) {
	global $product;
	if ( is_object( $product ) && method_exists( $product, 'get_description' ) ) {
		$full_description = (string) $product->get_description();
		if ( '' !== trim( wp_strip_all_tags( $full_description ) ) ) {
			$merged  = Gloskin_Site_Core_WooCommerce_Adapter::consolidate_description_content( $content, $full_description );
			$content = $merged['result'];
		}
	}
	return gloskin_ui1_format_product_description( $content );
}

/**
 * Remove every Woo product tab. Content that used to live in
 * Description/Additional Information/Reviews now lives in the Short
 * Description primary summary and native product facts instead.
 *
 * @param array<string,mixed> $tabs Woo product tabs.
 * @return array<string,mixed>
 */
function gloskin_ui1_product_tabs_description_boundary( $tabs ) {
	return array();
}

/**
 * Render Woo's own product description through a small, request-local
 * presentation pipeline. No product/cart/stock state is created here.
 *
 * Currently unreachable via woocommerce_product_tabs (all tabs are removed
 * -- see gloskin_ui1_product_tabs_description_boundary() above), kept as
 * the Description tab's own would-be renderer in case tabs are ever
 * reintroduced. The active safety pipeline for the now-primary Short
 * Description field is gloskin_ui1_format_product_description() below,
 * reused directly on woocommerce_short_description.
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
