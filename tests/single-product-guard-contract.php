<?php
/**
 * SP-001 behavioral proof: Gloskin_Site_Core_WooCommerce_Adapter::
 * guard_single_product_description_content() actually strips a nested
 * Woo single-product block/shortcode embed from a product's own
 * description content, and leaves normal content completely untouched
 * everywhere it should not act.
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

// A. Ordinary description content passes through completely unchanged.
$normal = '<p>Pembersih wajah lembut untuk kulit sensitif, digunakan pagi dan malam.</p>';
ok($adapter->guard_single_product_description_content($normal) === $normal, 'A: ordinary product description must be returned byte-identical');

// B. A nested Woo single-product Gutenberg block embedded in the
// description is stripped -- this is the exact mechanism that would
// otherwise render a second full product stack inside the Description
// tab (SP-001).
$with_block = '<p>Deskripsi asli.</p><!-- wp:woocommerce/single-product {"productId":501} --><div>fake nested product</div><!-- /wp:woocommerce/single-product --><p>Setelah blok.</p>';
$cleaned = $adapter->guard_single_product_description_content($with_block);
ok(strpos($cleaned, 'wp:woocommerce/single-product') === false, 'B: nested Woo single-product block markers must be stripped');
ok(strpos($cleaned, 'fake nested product') === false, 'B: nested Woo single-product block content must be stripped');
ok(strpos($cleaned, 'Deskripsi asli.') !== false && strpos($cleaned, 'Setelah blok.') !== false, 'B: surrounding legitimate content must survive the strip');

// C. A self-referencing product shortcode is stripped.
$with_shortcode = '<p>Deskripsi.</p>[product_page id="501"]';
$cleaned_shortcode = $adapter->guard_single_product_description_content($with_shortcode);
ok(strpos($cleaned_shortcode, '[product_page') === false, 'C: self-referencing [product_page] shortcode must be stripped');

// D. Scoping guard: never touches content when not the product's own
// singular render (e.g. a different post type, or outside the loop) --
// proves this can never affect unrelated content anywhere else on the
// site (catalog cards/related products never call the_content() at all).
$GLOBALS['gl_stub_is_product'] = false;
ok($adapter->guard_single_product_description_content($with_block) === $with_block, 'D1: must no-op when is_product() is false');
$GLOBALS['gl_stub_is_product'] = true;
$GLOBALS['gl_stub_in_the_loop'] = false;
ok($adapter->guard_single_product_description_content($with_block) === $with_block, 'D2: must no-op outside in_the_loop()');
$GLOBALS['gl_stub_in_the_loop'] = true;
$GLOBALS['gl_stub_post'] = new WP_Post(9, 'post');
ok($adapter->guard_single_product_description_content($with_block) === $with_block, 'D3: must no-op when the loop post is not a product');
$GLOBALS['gl_stub_post'] = new WP_Post(501, 'product');

echo "single-product SP-001 content guard contract: OK\n";
