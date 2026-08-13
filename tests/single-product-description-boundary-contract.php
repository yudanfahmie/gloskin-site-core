<?php
/**
 * Behavioral contract for the Gloskin single-product Description boundary.
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['gl_filters'] = array();
$GLOBALS['gl_is_product'] = true;
$GLOBALS['gl_the_content_calls'] = 0;
$GLOBALS['shortcode_tags'] = array(
    'safe' => static function () { return '<strong>SAFE</strong>'; },
    'product_page' => static function () { return '<div class="single-product"><form class="cart"></form></div>'; },
);

function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['gl_filters'][$tag][] = array($callback, $priority, $accepted_args);
    return true;
}
function is_product() { return $GLOBALS['gl_is_product']; }
function __($text, $domain = null) { return $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function apply_filters($tag, $value) {
    if ('the_content' === $tag) {
        $GLOBALS['gl_the_content_calls']++;
    }
    return $value;
}
function do_blocks($content) {
    $content = preg_replace('/<!--\s*wp:paragraph\s*-->(.*?)<!--\s*\/wp:paragraph\s*-->/s', '$1', $content);
    return preg_replace_callback('/<!--\s*wp:shortcode\s*-->(.*?)<!--\s*\/wp:shortcode\s*-->/s', static function ($matches) {
        return do_shortcode($matches[1]);
    }, $content);
}
function wptexturize($content) { return $content; }
function wpautop($content) { return $content; }
function shortcode_unautop($content) { return $content; }
function wp_filter_content_tags($content) { return $content; }
function do_shortcode($content) {
    global $shortcode_tags;
    $content = preg_replace_callback('/\[safe\]/', static function () use (&$shortcode_tags) {
        return call_user_func($shortcode_tags['safe']);
    }, $content);
    $content = preg_replace_callback('/\[product_page\b[^\]]*\]/', static function () use (&$shortcode_tags) {
        return call_user_func($shortcode_tags['product_page']);
    }, $content);
    return $content;
}

function ok($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require dirname(__DIR__) . '/plugin/gloskin-site-core/templates/parts/product-description-boundary.php';

gloskin_ui1_register_product_description_boundary();
ok(isset($GLOBALS['gl_filters']['woocommerce_product_tabs']), 'Description boundary must register on woocommerce_product_tabs');
ok(isset($GLOBALS['gl_filters']['woocommerce_short_description']), 'Short description must be routed through the safety-formatting pipeline now that it is the one primary PDP body field');
ok(!isset($GLOBALS['gl_filters']['the_content']), 'Description boundary must never register another the_content filter');

$tabs = array(
    'description' => array('title' => 'Description', 'callback' => 'woocommerce_product_description_tab'),
    'additional_information' => array('title' => 'Additional information', 'callback' => 'woocommerce_product_additional_information_tab'),
);
$bounded = gloskin_ui1_product_tabs_description_boundary($tabs);
ok($bounded === array(), 'PDP simplification: every Woo product tab (Description/Additional Information/Reviews) must be removed, content now lives in the Short Description primary summary');

class GL_Test_Product {
    public function get_description() {
        return '<!-- wp:paragraph --><p>Editorial copy.</p><!-- /wp:paragraph -->[safe][product_page id="777"]<!-- wp:shortcode -->[product_page id="888"]<!-- /wp:shortcode --><!-- wp:woocommerce/single-product {"productId":777} --><div>BLOCK PRODUCT</div><!-- /wp:woocommerce/single-product -->';
    }
}
$GLOBALS['product'] = new GL_Test_Product();
$original_product_page = $GLOBALS['shortcode_tags']['product_page'];

ob_start();
gloskin_ui1_render_product_description();
$html = ob_get_clean();

ok(strpos($html, '<h2>Description</h2>') !== false, 'Woo Description heading must remain');
ok(strpos($html, 'Editorial copy.') !== false, 'ordinary product description content must render');
ok(strpos($html, '<strong>SAFE</strong>') !== false, 'ordinary shortcodes must still render');
ok(strpos($html, 'single-product') === false, 'product_page shortcode must never inject a nested product root, including through a Gutenberg Shortcode block');
ok(strpos($html, 'BLOCK PRODUCT') === false, 'single-product block must never inject a nested product root');
ok($GLOBALS['shortcode_tags']['product_page'] === $original_product_page, 'Woo product_page shortcode handler must be restored after rendering');
ok($GLOBALS['gl_the_content_calls'] === 0, 'safe Description renderer must never execute the global the_content filter chain');

// A broken third-party shortcode must fail soft instead of taking down the page.
$GLOBALS['shortcode_tags']['safe'] = static function () { throw new RuntimeException('broken shortcode'); };
ob_start();
gloskin_ui1_render_product_description();
$soft = ob_get_clean();
ok(strpos($soft, 'Editorial copy.') !== false, 'shortcode failure must preserve the editorial description');
ok($GLOBALS['gl_the_content_calls'] === 0, 'shortcode failure path must still avoid the_content');

// The Short Description primary-summary field must reuse the exact same
// self-reference guard as the old Description tab -- not a second,
// divergent copy -- since migrated post_content can carry the same risky
// embeds into post_excerpt.
$risky_short_description = '<p>Ringkasan.</p>[product_page id="777"]<!-- wp:woocommerce/single-product {"productId":777} --><div>BLOCK PRODUCT</div><!-- /wp:woocommerce/single-product -->';
$formatted_short_description = gloskin_ui1_format_product_description($risky_short_description);
ok(strpos($formatted_short_description, 'Ringkasan.') !== false, 'Short description own content must still render');
ok(strpos($formatted_short_description, 'single-product') === false, 'Short description must never render a nested product_page embed');
ok(strpos($formatted_short_description, 'BLOCK PRODUCT') === false, 'Short description must never render a nested single-product block');

echo "single-product-description-boundary: OK\n";
