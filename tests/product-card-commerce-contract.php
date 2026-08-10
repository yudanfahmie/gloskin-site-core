<?php
declare(strict_types=1);

/**
 * Proves the Gloskin product-card add-to-cart link mirrors WooCommerce's own
 * native loop add-to-cart contract (classes/data-attributes) so Woo's own
 * wc-add-to-cart.js can bind to it exactly as it would to Woo's native
 * archive markup -- and that eligibility is never hand-invented: it is read
 * straight from the values Gloskin_Site_Core_WooCommerce_Adapter::
 * normalize_products() computes from WC_Product (see task: "Gloskin Commerce
 * Interaction Bridge v1").
 */

define( 'ABSPATH', __DIR__ . '/' );

function ok( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function __( $text, $domain = 'default' ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_html_class( $value, $fallback = '' ) {
	$sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value );
	return '' !== $sanitized ? $sanitized : $fallback;
}
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) { return '<img class="gloskin-ui1-card__image" src="stub.jpg" alt="">'; }
function wp_kses_post( $html ) { return (string) $html; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_trim_words( $text, $words = 55 ) { return (string) $text; }

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/templates/parts/template-helpers.php';

function render_card( array $product ) {
	ob_start();
	gloskin_ui1_render_product_card( $product );
	return (string) ob_get_clean();
}

/* -----------------------------------------------------------------
 * A. Simple, AJAX-eligible product: full native Woo contract present.
 * ----------------------------------------------------------------- */
$simple = array(
	'id'                      => 101,
	'name'                    => 'Gentle Cleanser',
	'url'                     => 'https://example.test/product/gentle-cleanser/',
	'image_id'                => 5,
	'price_html'              => '<span class="amount">Rp150.000</span>',
	'sku'                     => 'GLS-001',
	'type'                    => 'simple',
	'add_to_cart_url'         => 'https://example.test/?add-to-cart=101',
	'add_to_cart_text'        => 'Tambah ke keranjang',
	'add_to_cart_description' => 'Add &#8220;Gentle Cleanser&#8221; to your cart',
	'purchasable'             => true,
	'in_stock'                => true,
	'ajax_add_to_cart'        => true,
);
$html = render_card( $simple );
ok( false !== strpos( $html, 'add_to_cart_button' ), 'simple: add_to_cart_button class present' );
ok( false !== strpos( $html, 'ajax_add_to_cart' ), 'simple: ajax_add_to_cart class present when Woo supports it' );
ok( false !== strpos( $html, 'product_type_simple' ), 'simple: product_type_<type> class present' );
ok( false !== strpos( $html, 'data-product_id="101"' ), 'simple: data-product_id present' );
ok( false !== strpos( $html, 'data-product_sku="GLS-001"' ), 'simple: data-product_sku present' );
ok( false !== strpos( $html, 'data-quantity="1"' ), 'simple: data-quantity present' );
ok( false !== strpos( $html, 'rel="nofollow"' ), 'simple: rel=nofollow present' );
ok( false !== strpos( $html, 'gloskin-ui1-button' ) && false !== strpos( $html, 'gloskin-ui1-button--small' ), 'simple: Gloskin visual classes retained' );
ok( 1 === preg_match( '/href="([^"]+)"[^>]*class="[^"]*add_to_cart_button/', $html, $m ) && '' !== trim( $m[1] ), 'simple: working href retained for JS-disabled fallback' );

/* -----------------------------------------------------------------
 * B. Variable product: purchasable/in-stock (so still gets a working
 * link to the product page for option selection) but NOT eligible for
 * archive AJAX -- Woo's own contract, never a Gloskin shortcut.
 * ----------------------------------------------------------------- */
$variable = array(
	'id'                      => 202,
	'name'                    => 'Hydrating Serum',
	'url'                     => 'https://example.test/product/hydrating-serum/',
	'image_id'                => 6,
	'price_html'              => '<span class="amount">Rp250.000</span>',
	'sku'                     => 'GLS-002',
	'type'                    => 'variable',
	'add_to_cart_url'         => 'https://example.test/product/hydrating-serum/',
	'add_to_cart_text'        => 'Tambah ke keranjang',
	'add_to_cart_description' => 'Select options for &#8220;Hydrating Serum&#8221;',
	'purchasable'             => true,
	'in_stock'                => true,
	'ajax_add_to_cart'        => false,
);
$html = render_card( $variable );
ok( false === strpos( $html, 'ajax_add_to_cart' ), 'variable: ajax_add_to_cart class absent when Woo does not support it' );
ok( false !== strpos( $html, 'add_to_cart_button' ), 'variable: still gets a purchasable/in-stock working link' );
ok( false !== strpos( $html, 'product_type_variable' ), 'variable: product_type_variable class present' );
ok( false !== strpos( $html, 'href="https://example.test/product/hydrating-serum/"' ), 'variable: link still navigates to product page (Select options), not blind AJAX' );

/* -----------------------------------------------------------------
 * Not purchasable / out of stock / missing URL: no add-to-cart link at
 * all (existing gate preserved; nothing new invented here).
 * ----------------------------------------------------------------- */
foreach (
	array(
		'out of stock'    => array_merge( $simple, array( 'in_stock' => false ) ),
		'not purchasable' => array_merge( $simple, array( 'purchasable' => false ) ),
		'no url'          => array_merge( $simple, array( 'add_to_cart_url' => '' ) ),
	) as $label => $fixture
) {
	$html = render_card( $fixture );
	ok( false === strpos( $html, 'add_to_cart_button' ), "gate preserved: {$label}" );
}

echo "product card commerce contract: OK\n";
