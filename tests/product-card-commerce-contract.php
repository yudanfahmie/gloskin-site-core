<?php
declare(strict_types=1);

/**
 * Focused product-card commerce contract: one disciplined footer action while
 * preserving WooCommerce's native loop add-to-cart compatibility and real
 * product-detail fallbacks.
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
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) { return '<img class="' . esc_attr( (string) ( $attr['class'] ?? 'gloskin-ui1-card__image' ) ) . '" src="stub.jpg" alt="">'; }
function wp_kses_post( $html ) { return (string) $html; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_trim_words( $text, $words = 55 ) { return (string) $text; }

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/templates/parts/template-helpers.php';

function render_card( array $product, $variant = 'catalog' ) {
	ob_start();
	gloskin_ui1_render_product_card( $product, $variant );
	return (string) ob_get_clean();
}

function footer_markup( $html ) {
	if ( preg_match( '/<div class="gloskin-ui1-card__actions">(.*?)<\/div>/s', $html, $matches ) ) {
		return $matches[1];
	}
	return '';
}

/* -----------------------------------------------------------------
 * A. Simple, AJAX-eligible product: full native Woo contract present,
 * with exactly one footer action and no competing detail text CTA.
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
$footer = footer_markup( $html );
ok( false !== strpos( $footer, 'button' ), 'simple: native Woo button class present' );
ok( false !== strpos( $footer, 'add_to_cart_button' ), 'simple: add_to_cart_button class present' );
ok( false !== strpos( $footer, 'ajax_add_to_cart' ), 'simple: ajax_add_to_cart class present only when Woo supports it' );
ok( false !== strpos( $footer, 'product_type_simple' ), 'simple: product_type_<type> class present' );
ok( false !== strpos( $footer, 'data-product_id="101"' ), 'simple: data-product_id present' );
ok( false !== strpos( $footer, 'data-product_sku="GLS-001"' ), 'simple: data-product_sku present' );
ok( false !== strpos( $footer, 'data-quantity="1"' ), 'simple: data-quantity present' );
ok( false !== strpos( $footer, 'rel="nofollow"' ), 'simple: rel=nofollow present' );
ok( 1 === substr_count( $footer, '<a ' ), 'simple: exactly one footer CTA' );
ok( false === strpos( $footer, 'Lihat Produk' ), 'simple: duplicate Lihat Produk footer CTA removed' );
ok( 1 === preg_match( '/href="([^"]+)"[^>]*class="[^"]*add_to_cart_button/', $footer, $m ) && '' !== trim( $m[1] ), 'simple: working native href retained' );

/* -----------------------------------------------------------------
 * B. Variable product: one Pilih Varian action, canonical href is retained
 * for no-JS fallback, and Quick Add attributes remain enhancement-only.
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
$footer = footer_markup( $html );
ok( false === strpos( $footer, 'ajax_add_to_cart' ), 'variable: no invented AJAX eligibility' );
ok( false !== strpos( $footer, 'add_to_cart_button' ), 'variable: Woo loop action class retained' );
ok( false !== strpos( $footer, 'product_type_variable' ), 'variable: product_type_variable class present' );
ok( false !== strpos( $footer, 'href="https://example.test/product/hydrating-serum/"' ), 'variable: canonical product-detail href retained' );
ok( false !== strpos( $footer, 'data-gloskin-quickadd-open' ), 'variable: Quick Add progressive-enhancement marker retained' );
ok( false !== strpos( $footer, 'data-gloskin-quickadd-product="202"' ), 'variable: Quick Add product id retained' );
ok( false !== strpos( $footer, 'aria-haspopup="dialog"' ), 'variable: dialog relationship retained' );
ok( false !== strpos( $footer, '>Pilih Varian</a>' ), 'variable: disciplined Pilih Varian commerce action rendered' );
ok( 1 === substr_count( $footer, '<a ' ), 'variable: exactly one footer CTA' );
ok( false === strpos( $footer, 'Lihat Produk' ), 'variable: competing Lihat Produk footer CTA removed' );

/* -----------------------------------------------------------------
 * C. Unavailable/non-purchasable/no-cart-url products never receive a fake
 * add-to-cart class. A single canonical detail action remains available.
 * ----------------------------------------------------------------- */
foreach (
	array(
		'out of stock'    => array_merge( $simple, array( 'in_stock' => false ) ),
		'not purchasable' => array_merge( $simple, array( 'purchasable' => false ) ),
		'no cart url'     => array_merge( $simple, array( 'add_to_cart_url' => '' ) ),
	) as $label => $fixture
) {
	$html = render_card( $fixture );
	$footer = footer_markup( $html );
	ok( false === strpos( $footer, 'add_to_cart_button' ), "unavailable: {$label} gets no fake add-to-cart" );
	ok( false !== strpos( $footer, 'href="https://example.test/product/gentle-cleanser/"' ), "unavailable: {$label} keeps canonical detail navigation" );
	ok( false !== strpos( $footer, '>Lihat Produk</a>' ), "unavailable: {$label} gets an intentional detail action" );
	ok( 1 === substr_count( $footer, '<a ' ), "unavailable: {$label} has exactly one footer CTA" );
}

/* -----------------------------------------------------------------
 * D. Consultation results are a strict detail-only variant: the whole card
 * is one canonical PDP link, with factual Woo price and no commerce affordance.
 * ----------------------------------------------------------------- */
$consultation = array_merge( $simple, array(
	'name'              => 'Acne Treatment',
	'url'               => 'https://example.test/product/acne-treatment/',
	'short_description' => 'A concise factual treatment summary.',
) );
$html = render_card( $consultation, 'consultation' );
ok( 1 === substr_count( $html, '<a ' ), 'consultation: exactly one anchor wraps the result card' );
ok( false !== strpos( $html, 'href="https://example.test/product/acne-treatment/"' ), 'consultation: canonical PDP href retained' );
ok( false !== strpos( $html, 'Lihat Detail' ), 'consultation: explicit detail affordance retained' );
ok( false !== strpos( $html, '<span class="amount">Rp150.000</span>' ), 'consultation: factual Woo price retained' );
ok( false !== strpos( $html, 'class="gloskin-ui1-consultation-card__image"' ), 'consultation: Woo featured image is preferred' );
foreach ( array( 'wishlist', 'add_to_cart', 'ajax_add_to_cart', 'product_type_', 'data-gloskin-quickadd', 'class="button' ) as $forbidden ) {
	ok( false === strpos( strtolower( $html ), strtolower( $forbidden ) ), "consultation: detail-only card excludes {$forbidden}" );
}

$fallback = render_card( array_merge( $consultation, array( 'image_id' => 0 ) ), 'consultation' );
ok( false !== strpos( $fallback, 'gloskin-ui1-card--text-first' ), 'consultation: missing Woo image degrades to text-first card' );
ok( false === strpos( $fallback, 'gloskin-ui1-consultation-card__media' ), 'consultation: missing Woo image renders no media shell' );

echo "product card commerce contract: OK\n";
