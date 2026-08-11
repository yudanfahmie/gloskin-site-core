<?php
/**
 * SP-001 behavioral proof (narrowed 2026-08-11 hotfix): Gloskin_Site_Core_
 * WooCommerce_Adapter::guard_single_product_description_content() strips
 * a *self-referencing* Woo single-product block/[product_page] embed --
 * one that targets the current product's own ID -- and leaves every other
 * kind of content, including legitimate cross-sell/editorial Woo
 * shortcodes and single-product embeds of a *different* product,
 * completely untouched.
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

class WP_Post {
	public $ID;
	public $post_type;
	public function __construct($id, $post_type) { $this->ID = $id; $this->post_type = $post_type; }
}

$GLOBALS['gl_stub_is_product'] = true;
$GLOBALS['gl_stub_in_the_loop'] = true;
$GLOBALS['gl_stub_post'] = new WP_Post(501, 'product');

function is_product() { return $GLOBALS['gl_stub_is_product']; }
function in_the_loop() { return $GLOBALS['gl_stub_in_the_loop']; }
function get_post() { return $GLOBALS['gl_stub_post']; }
function absint($value) { return abs((int) $value); }

// Everything else this class file needs at parse time (none of it is
// exercised by this focused contract) is defined defensively so the
// require below succeeds standalone, matching this repo's other
// single-class PHP contract harnesses.
function add_action() {}
function add_filter() {}

require dirname(__DIR__) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php';

function ok($condition, $message) {
	if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();

// A. Ordinary description content (matching the canonical sample bundle's
// real "Gloskin Fresh Gel Facial Wash" description shape -- plain
// heading/paragraph HTML, no Woo block/shortcode of any kind) passes
// through completely unchanged.
$normal = '<h3>Karakter produk</h3><p>Pembersih wajah berformat gel yang dirancang sebagai produk demo serbaguna untuk kebutuhan katalog staging.</p>';
ok($adapter->guard_single_product_description_content($normal) === $normal, 'A: ordinary product description must be returned byte-identical');

// B. A Woo single-product block embedding THIS SAME product (id 501) is
// true self-recursion -- stripped. This is the exact mechanism that
// would reproduce a second full product stack inside the Description tab.
$self_block = '<p>Deskripsi asli.</p><!-- wp:woocommerce/single-product {"productId":501} --><div>fake nested product</div><!-- /wp:woocommerce/single-product --><p>Setelah blok.</p>';
$cleaned = $adapter->guard_single_product_description_content($self_block);
ok(strpos($cleaned, 'wp:woocommerce/single-product') === false, 'B: self-referencing single-product block markers must be stripped');
ok(strpos($cleaned, 'fake nested product') === false, 'B: self-referencing single-product block content must be stripped');
ok(strpos($cleaned, 'Deskripsi asli.') !== false && strpos($cleaned, 'Setelah blok.') !== false, 'B: surrounding legitimate content must survive the strip');

// C. A Woo single-product block embedding a DIFFERENT product (id 777,
// not this product's own 501) is legitimate cross-sell content, not
// recursion -- must be preserved untouched.
$other_block = '<p>Lihat juga:</p><!-- wp:woocommerce/single-product {"productId":777} --><div>other product markup</div><!-- /wp:woocommerce/single-product -->';
ok($adapter->guard_single_product_description_content($other_block) === $other_block, 'C: single-product block referencing a different product must be preserved');

// D. [product_page id="501"] self-referencing the current product is
// stripped; [product_page id="777"] for another product is preserved.
$self_shortcode = '<p>Deskripsi.</p>[product_page id="501"]';
ok(strpos($adapter->guard_single_product_description_content($self_shortcode), '[product_page') === false, 'D1: self-referencing [product_page] shortcode must be stripped');
$other_shortcode = '<p>Deskripsi.</p>[product_page id="777"]';
ok($adapter->guard_single_product_description_content($other_shortcode) === $other_shortcode, 'D2: [product_page] referencing a different product must be preserved');

// E. Genuine editorial/cross-sell Woo shortcodes must never be touched --
// none of these render a nested .product single-page root, and blanket-
// stripping them was unjustified overreach removed in this hotfix.
$legitimate = '<p>Produk terkait:</p>[products limit="3" category="serum"] [product_category category="toner"] [product id="42"] [add_to_cart id="42"]';
ok($adapter->guard_single_product_description_content($legitimate) === $legitimate, 'E: legitimate cross-sell Woo shortcodes must be preserved untouched');

// F. Scoping guard: never touches content when not the product's own
// singular render (e.g. a different post type, or outside the loop).
$GLOBALS['gl_stub_is_product'] = false;
ok($adapter->guard_single_product_description_content($self_block) === $self_block, 'F1: must no-op when is_product() is false');
$GLOBALS['gl_stub_is_product'] = true;
$GLOBALS['gl_stub_in_the_loop'] = false;
ok($adapter->guard_single_product_description_content($self_block) === $self_block, 'F2: must no-op outside in_the_loop()');
$GLOBALS['gl_stub_in_the_loop'] = true;
$GLOBALS['gl_stub_post'] = new WP_Post(9, 'post');
ok($adapter->guard_single_product_description_content($self_block) === $self_block, 'F3: must no-op when the loop post is not a product');
$GLOBALS['gl_stub_post'] = new WP_Post(501, 'product');

echo "single-product SP-001 content guard contract: OK\n";
