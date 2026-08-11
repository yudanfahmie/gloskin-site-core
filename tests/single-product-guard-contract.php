<?php
/**
 * SP-001 behavioral proof (root cause proven live on staging 2026-08-12):
 * Gloskin_Site_Core_WooCommerce_Adapter::guard_single_product_description_content()
 * strips a *self-referencing* Woo single-product block/[product_page] embed
 * -- one that targets the current product's own ID or own SKU -- and
 * leaves every other kind of content, including legitimate cross-sell/
 * editorial Woo shortcodes and single-product embeds of a *different*
 * product, completely untouched.
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
$GLOBALS['gl_stub_queried_object'] = new WP_Post(501, 'product');

function is_product() { return $GLOBALS['gl_stub_is_product']; }
function in_the_loop() { return $GLOBALS['gl_stub_in_the_loop']; }
function get_post() { return $GLOBALS['gl_stub_post']; }
function get_queried_object() { return $GLOBALS['gl_stub_queried_object']; }
function absint($value) { return abs((int) $value); }

// Everything else this class file needs at parse time (none of it is
// exercised by this focused contract) is defined defensively so the
// require below succeeds standalone, matching this repo's other
// single-class PHP contract harnesses.
function add_action() {}
function add_filter() {}

// SKU->post-ID resolution stub for the sku-targeted self-reference branch
// (the proven live root cause -- see cases G/H below). Mirrors Woo's own
// documented wc_get_product_id_by_sku() contract: known SKU -> post ID,
// unknown SKU -> 0.
$GLOBALS['gl_stub_sku_map'] = array('GLS-SMP-002' => 501, 'GLS-OTHER-999' => 777);
function wc_get_product_id_by_sku($sku) {
	return isset($GLOBALS['gl_stub_sku_map'][$sku]) ? $GLOBALS['gl_stub_sku_map'][$sku] : 0;
}

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

// G. [product_page sku="GLS-SMP-002"] self-referencing the current product
// (id 501) via its own SKU -- the exact live-proven root cause -- is
// stripped even though it carries no numeric id= attribute at all.
$self_sku_shortcode = '<p>Deskripsi.</p>[product_page sku="GLS-SMP-002"]';
ok(strpos($adapter->guard_single_product_description_content($self_sku_shortcode), '[product_page') === false, 'G: self-referencing [product_page sku] shortcode must be stripped');

// H. [product_page sku="GLS-OTHER-999"] resolves to a different product
// (id 777, not this product's own 501) -- legitimate cross-sell, preserved.
$other_sku_shortcode = '<p>Deskripsi.</p>[product_page sku="GLS-OTHER-999"]';
ok($adapter->guard_single_product_description_content($other_sku_shortcode) === $other_sku_shortcode, 'H1: [product_page sku] referencing a different product must be preserved');

// H2. An unknown SKU (resolves to no product at all) must never be treated
// as self-referencing.
$unknown_sku_shortcode = '<p>Deskripsi.</p>[product_page sku="NOT-A-REAL-SKU"]';
ok($adapter->guard_single_product_description_content($unknown_sku_shortcode) === $unknown_sku_shortcode, 'H2: [product_page sku] with an unresolvable SKU must be preserved');

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

// I. Purchase-dock one-shot guard (2026-08-12 release-gate finding, live-
// proven on staging): open_purchase_dock()/close_purchase_dock() must
// render the dock wrapper AT MOST ONCE per request even when this same
// product's own primary-product context (get_post() === get_queried_object(),
// both product 501) is observed a second time -- exactly what an external,
// same-product duplicate render looks like from inside this class, since
// is_primary_single_product_context() alone cannot tell that apart from
// the genuine primary render.
ob_start(); $adapter->open_purchase_dock(); $i1_open = ob_get_clean();
ok($i1_open === '<div class="gloskin-ui1-purchase-dock" data-gloskin-purchase-dock>', 'I1: first open_purchase_dock() in a primary context must render the wrapper');
ob_start(); $adapter->close_purchase_dock(); $i1_close = ob_get_clean();
ok($i1_close === '</div>', 'I2: matching close_purchase_dock() must render the closing tag');

// Simulate a second same-product primary-context pass (the live-proven
// duplicate render): still get_post() === get_queried_object(), same IDs.
ob_start(); $adapter->open_purchase_dock(); $i2_open = ob_get_clean();
ok($i2_open === '', 'I3: a second open_purchase_dock() this request must never render a second dock');
ob_start(); $adapter->close_purchase_dock(); $i2_close = ob_get_clean();
ok($i2_close === '', 'I4: close_purchase_dock() must stay balanced -- no stray closing tag for a dock that was never opened');

echo "single-product SP-001 content guard contract: OK\n";
