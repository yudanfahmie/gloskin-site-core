<?php
declare(strict_types=1);

/** Browser-only Phase-2 component fixture. Production helpers stay unmodified. */
define( 'ABSPATH', __DIR__ . '/' );
function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $text, $domain = 'default' ) { return esc_html( $text ); }
function esc_attr__( $text, $domain = 'default' ) { return esc_attr( $text ); }
function __( $text, $domain = 'default' ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_html_class( $value, $fallback = '' ) {
	$value = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value );
	return '' !== $value ? $value : $fallback;
}
function wp_kses_post( $html ) { return (string) $html; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_trim_words( $text, $words = 55 ) {
	$parts = preg_split( '/\s+/', trim( strip_tags( (string) $text ) ) );
	return implode( ' ', array_slice( $parts ?: array(), 0, $words ) );
}
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) {
	$class = isset( $attr['class'] ) ? (string) $attr['class'] : '';
	$alt   = isset( $attr['alt'] ) ? (string) $attr['alt'] : '';
	$svg   = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800"><rect width="800" height="800" fill="%23fff2eb"/><rect x="310" y="120" width="180" height="560" rx="28" fill="%23f6d179"/><rect x="338" y="210" width="124" height="190" rx="8" fill="%23ffffff"/></svg>';
	return '<img src="data:image/svg+xml,' . rawurlencode( $svg ) . '" class="' . esc_attr( $class ) . '" alt="' . esc_attr( $alt ) . '" data-fixture-image="' . esc_attr( (string) $id ) . '">';
}

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/templates/parts/template-helpers.php';

$product = array(
	'id' => 501,
	'name' => 'Gentle Balance Facial Cleanser',
	'url' => 'https://example.test/product/gentle-balance/',
	'image_id' => 50,
	'price_html' => '<span class="amount">Rp165.000</span>',
	'sku' => 'GLS-501',
	'type' => 'simple',
	'add_to_cart_url' => 'https://example.test/?add-to-cart=501',
	'add_to_cart_text' => 'Beli Sekarang',
	'add_to_cart_description' => 'Beli Gentle Balance Facial Cleanser',
	'purchasable' => true,
	'in_stock' => true,
	'ajax_add_to_cart' => true,
	'short_description' => 'Dense catalogue copy should not appear in the Skincare variant.',
);
$promos = array();
foreach ( array( 'Promo A', 'Promo B', 'Promo C' ) as $index => $title ) {
	$promos[] = array(
		'id' => 700 + $index,
		'title' => $title,
		'eyebrow' => 'Gloskin',
		'summary' => 'Informasi kampanye yang dikelola dari record promo.',
		'excerpt' => '',
		'cta_label' => 'Lihat Detail',
		'cta_url' => 'https://example.test/promo/' . ( $index + 1 ) . '/',
		'image_id' => 80 + $index,
	);
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="gloskin-ui1"><main>
<section class="gloskin-ui1-section" data-gloskin-section="skincare-products"><div class="gloskin-ui1-container"><div class="gloskin-ui1-product-grid" data-gloskin-product-grid><div data-gloskin-product-card data-category-slugs="cleanser"><?php gloskin_ui1_render_product_card( $product, 'skincare' ); ?></div></div></div></section>
<?php gloskin_ui1_render_managed_promo_carousel( $promos, 'h1', false ); ?>
</main></body></html>
