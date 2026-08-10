<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

class WP_Error {
    private $message;
    public function __construct($code, $message) { unset($code); $this->message = (string) $message; }
    public function get_error_message() { return $this->message; }
}
class WP_Term {
    public $term_id;
    public $slug;
    public function __construct($id, $slug) { $this->term_id = (int) $id; $this->slug = (string) $slug; }
}

function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function wp_kses_post($value) { return (string) $value; }
function wp_list_pluck($list, $field) { $out = array(); foreach ($list as $row) { $out[] = isset($row[$field]) ? $row[$field] : null; } return $out; }
function wp_generate_uuid4() { static $n = 0; $n++; return sprintf('00000000-0000-4000-8000-%012d', $n); }
function trailingslashit($value) { return rtrim((string) $value, '/\\') . '/'; }
function plugin_dir_path($file) { return trailingslashit(dirname((string) $file)); }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function sanitize_title($value) { $value = strtolower(trim((string) $value)); $value = preg_replace('/[^a-z0-9]+/', '-', $value); return trim((string) $value, '-'); }
function wp_delete_file($file) { @unlink((string) $file); }

function reset_stub_state() {
    $GLOBALS['stub_options'] = array();
    $GLOBALS['stub_products'] = array();
    $GLOBALS['stub_post_types'] = array();
    $GLOBALS['stub_meta'] = array();
    $GLOBALS['stub_terms_by_slug'] = array();
    $GLOBALS['stub_terms_by_id'] = array();
    $GLOBALS['stub_next_id'] = 100;
    $GLOBALS['stub_next_term_id'] = 1;
    $GLOBALS['stub_sideload_calls'] = 0;
    $GLOBALS['stub_fault_parent'] = '';
    $GLOBALS['stub_fault_parent_used'] = false;
    $GLOBALS['stub_fault_media_call'] = 0;
    $GLOBALS['stub_fault_media_used'] = false;
    $GLOBALS['stub_fault_variation'] = '';
    $GLOBALS['stub_fault_variation_used'] = false;
    $GLOBALS['stub_events'] = array();
}

function get_option($name, $default = false) { return array_key_exists($name, $GLOBALS['stub_options']) ? $GLOBALS['stub_options'][$name] : $default; }
function update_option($name, $value, $autoload = null) {
    unset($autoload);
    $GLOBALS['stub_options'][$name] = $value;
    if (defined('Gloskin_Site_Core_Sample_Product_Importer::STATE_OPTION') && $name === Gloskin_Site_Core_Sample_Product_Importer::STATE_OPTION && is_array($value) && isset($value['status']) && $value['status'] === 'consumed' && (!isset($value['cleanup']) || $value['cleanup'] === 'pending')) {
        $GLOBALS['stub_events'][] = 'consumed';
    }
    return true;
}
function add_option($name, $value, $deprecated = '', $autoload = null) { unset($deprecated, $autoload); if (array_key_exists($name, $GLOBALS['stub_options'])) { return false; } $GLOBALS['stub_options'][$name] = $value; return true; }
function delete_option($name) {
    if (defined('Gloskin_Site_Core_Sample_Product_Importer::LOCK_OPTION') && $name === Gloskin_Site_Core_Sample_Product_Importer::LOCK_OPTION) { $GLOBALS['stub_events'][] = 'release'; }
    unset($GLOBALS['stub_options'][$name]);
    return true;
}

function update_post_meta($post_id, $key, $value) { if (!isset($GLOBALS['stub_meta'][$post_id])) { $GLOBALS['stub_meta'][$post_id] = array(); } $GLOBALS['stub_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { unset($single); return isset($GLOBALS['stub_meta'][$post_id][$key]) ? $GLOBALS['stub_meta'][$post_id][$key] : ''; }
function get_post_type($post_id) { return isset($GLOBALS['stub_post_types'][$post_id]) ? $GLOBALS['stub_post_types'][$post_id] : false; }

function get_posts($args) {
    $types = isset($args['post_type']) ? (array) $args['post_type'] : array();
    $key = isset($args['meta_key']) ? (string) $args['meta_key'] : '';
    $value = isset($args['meta_value']) ? (string) $args['meta_value'] : '';
    $limit = isset($args['numberposts']) ? (int) $args['numberposts'] : 5;
    $ids = array();
    foreach ($GLOBALS['stub_post_types'] as $id => $type) {
        if ($types && !in_array($type, $types, true)) { continue; }
        if ($key !== '' && (string) get_post_meta((int) $id, $key, true) !== $value) { continue; }
        $ids[] = (int) $id;
        if ($limit > 0 && count($ids) >= $limit) { break; }
    }
    return $ids;
}

function get_term_by($field, $value, $taxonomy) { unset($field, $taxonomy); return isset($GLOBALS['stub_terms_by_slug'][$value]) ? $GLOBALS['stub_terms_by_slug'][$value] : false; }
function wp_insert_term($label, $taxonomy, $args) {
    unset($label, $taxonomy);
    $slug = (string) $args['slug'];
    $id = $GLOBALS['stub_next_term_id']++;
    $term = new WP_Term($id, $slug);
    $GLOBALS['stub_terms_by_slug'][$slug] = $term;
    $GLOBALS['stub_terms_by_id'][$id] = $term;
    return array('term_id' => $id);
}
function wp_get_post_terms($post_id, $taxonomy, $args) {
    unset($taxonomy, $args);
    $product = wc_get_product((int) $post_id);
    if (!$product) { return array(); }
    $slugs = array();
    foreach ($product->get_category_ids() as $id) { if (isset($GLOBALS['stub_terms_by_id'][$id])) { $slugs[] = $GLOBALS['stub_terms_by_id'][$id]->slug; } }
    return $slugs;
}

function wc_get_product($id) { return isset($GLOBALS['stub_products'][$id]) ? $GLOBALS['stub_products'][$id] : false; }
function wc_get_product_id_by_sku($sku) { foreach ($GLOBALS['stub_products'] as $id => $product) { if ((string) $product->get_sku() === (string) $sku) { return (int) $id; } } return 0; }
function wc_delete_product_transients($id) { unset($id); }

function download_url($url, $timeout = 300) { unset($url, $timeout); $path = tempnam(sys_get_temp_dir(), 'gl-media-'); file_put_contents($path, 'stub'); return $path; }
function media_handle_sideload($file, $post_id, $desc = '') {
    unset($post_id, $desc);
    $GLOBALS['stub_sideload_calls']++;
    if ($GLOBALS['stub_fault_media_call'] > 0 && !$GLOBALS['stub_fault_media_used'] && $GLOBALS['stub_sideload_calls'] === $GLOBALS['stub_fault_media_call']) {
        $GLOBALS['stub_fault_media_used'] = true;
        @unlink($file['tmp_name']);
        return new WP_Error('media_fail', 'injected media failure');
    }
    $id = $GLOBALS['stub_next_id']++;
    $GLOBALS['stub_post_types'][$id] = 'attachment';
    $GLOBALS['stub_meta'][$id] = array();
    @unlink($file['tmp_name']);
    return $id;
}

abstract class Stub_WC_Product {
    protected $id = 0;
    protected $type = 'simple';
    protected $sku = '';
    protected $status = 'draft';
    protected $regular_price = '';
    protected $parent_id = 0;
    protected $attributes = array();
    protected $category_ids = array();
    protected $image_id = 0;
    protected $gallery_ids = array();
    protected $meta = array();
    public function is_type($type) { return $this->type === $type; }
    public function set_name($v) { unset($v); }
    public function set_slug($v) { unset($v); }
    public function set_status($v) { $this->status = (string) $v; }
    public function set_catalog_visibility($v) { unset($v); }
    public function set_short_description($v) { unset($v); }
    public function set_description($v) { unset($v); }
    public function set_sku($v) { $this->sku = (string) $v; }
    public function set_category_ids($v) { $this->category_ids = array_map('intval', (array) $v); }
    public function get_category_ids() { return $this->category_ids; }
    public function set_manage_stock($v) { unset($v); }
    public function set_stock_status($v) { unset($v); }
    public function set_regular_price($v) { $this->regular_price = (string) $v; }
    public function set_attributes($v) { $this->attributes = (array) $v; }
    public function set_parent_id($v) { $this->parent_id = (int) $v; }
    public function set_image_id($v) { $this->image_id = (int) $v; }
    public function set_gallery_image_ids($v) { $this->gallery_ids = array_map('intval', (array) $v); }
    public function update_meta_data($k, $v) { $this->meta[$k] = $v; }
    public function get_meta($k, $single = true) { unset($single); return isset($this->meta[$k]) ? $this->meta[$k] : ''; }
    public function get_sku() { return $this->sku; }
    public function get_status() { return $this->status; }
    public function get_regular_price() { return $this->regular_price; }
    public function get_parent_id() { return $this->parent_id; }
    public function get_id() { return $this->id; }
    public function get_image_id() { return $this->image_id; }
    public function get_gallery_image_ids() { return $this->gallery_ids; }
    public function get_attribute($name) { return isset($this->attributes[$name]) ? (string) $this->attributes[$name] : ''; }
    public function save() {
        $source = isset($this->meta['_gloskin_sample_source_id']) ? (string) $this->meta['_gloskin_sample_source_id'] : '';
        if ($this->type === 'variation' && $GLOBALS['stub_fault_variation'] === $source && !$GLOBALS['stub_fault_variation_used']) {
            $GLOBALS['stub_fault_variation_used'] = true;
            return 0;
        }
        if (!$this->id) { $this->id = $GLOBALS['stub_next_id']++; }
        $GLOBALS['stub_products'][$this->id] = $this;
        $GLOBALS['stub_post_types'][$this->id] = $this->type === 'variation' ? 'product_variation' : 'product';
        if (!isset($GLOBALS['stub_meta'][$this->id])) { $GLOBALS['stub_meta'][$this->id] = array(); }
        foreach ($this->meta as $key => $value) { $GLOBALS['stub_meta'][$this->id][$key] = $value; }
        if ($this->type !== 'variation' && $GLOBALS['stub_fault_parent'] === $source && !$GLOBALS['stub_fault_parent_used']) {
            $GLOBALS['stub_fault_parent_used'] = true;
            return 0;
        }
        return $this->id;
    }
}
class WC_Product_Simple extends Stub_WC_Product { protected $type = 'simple'; }
class WC_Product_Variable extends Stub_WC_Product { protected $type = 'variable'; public static function sync($id) { unset($id); } }
class WC_Product_Variation extends Stub_WC_Product { protected $type = 'variation'; }
class WC_Product_Attribute {
    public function set_id($v) { unset($v); }
    public function set_name($v) { unset($v); }
    public function set_position($v) { unset($v); }
    public function set_visible($v) { unset($v); }
    public function set_options($v) { unset($v); }
    public function set_variation($v) { unset($v); }
}

final class Fake_Sample_Bundle {
    public $validated;
    public $cleanup_ok = true;
    public function __construct(array $validated) { $this->validated = $validated; }
    public function runtime_dir() { return '/tmp/redeployed-runtime'; }
    public function read_header() { return $this->validated['manifest']; }
    public function validate() { return $this->validated; }
    public function cleanup(array $manifest) { unset($manifest); $GLOBALS['stub_events'][] = 'cleanup'; return array('ok' => $this->cleanup_ok, 'message' => $this->cleanup_ok ? '' : 'injected cleanup failure'); }
}

function ok($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
function expect_failure(callable $fn, $needle, $message) {
    try { $fn(); } catch (RuntimeException $e) { ok(strpos($e->getMessage(), $needle) !== false, $message . ' message'); return; }
    ok(false, $message . ' did not fail');
}

function fixture_payload() {
    $categories = array('facial-wash','day-cream-sunscreen','toner','serum','acne-care','anti-aging','brightening-pigmentation-care');
    $media_counts = array(4,5,4,5,3,4,5,6,3,4,5,5,5);
    $products = array(); $media = array();
    for ($i = 1; $i <= 13; $i++) {
        $slug = sprintf('p%02d', $i); $source = 'gloskin-sample:v1:' . $slug; $variable = $i >= 9;
        $p = array(
            'source_id' => $source, 'name' => 'Product ' . $i, 'slug' => $slug,
            'category_slug' => $categories[($i - 1) % count($categories)], 'type' => $variable ? 'variable' : 'simple',
            'sku' => sprintf('GLS-SMP-%03d', $i), 'short_description' => 'Short', 'description' => 'Description',
            'usage' => 'Usage', 'media_count' => $media_counts[$i - 1], 'variations' => array(),
        );
        if ($variable) {
            $p['variations'][] = array('source_id' => $source . ':10ml', 'size' => '10 ml', 'sku' => sprintf('GLS-SMP-%03d-010', $i), 'regular_price' => '100000');
            $p['variations'][] = array('source_id' => $source . ':20ml', 'size' => '20 ml', 'sku' => sprintf('GLS-SMP-%03d-020', $i), 'regular_price' => '120000');
        } else { $p['size'] = '50 ml'; $p['regular_price'] = '90000'; }
        $products[] = $p;
        for ($j = 1; $j <= $media_counts[$i - 1]; $j++) {
            $media[] = array(
                'source_id' => sprintf('gloskin-sample-media:v1:%s:%02d', $slug, $j), 'product_source_id' => $source,
                'source_url' => 'https://example.test/' . $slug . '-' . $j . '.jpg', 'source_page_url' => '', 'author' => '',
                'license_note' => 'test fixture', 'filename' => $slug . '-' . $j . '.jpg', 'alt' => 'fixture',
                'role' => $j === 1 ? 'featured' : 'gallery', 'sort_order' => $j,
            );
        }
    }
    $by = array(); foreach ($products as $p) { $by[$p['source_id']] = array(); }
    foreach ($media as $m) { $by[$m['product_source_id']][] = $m; }
    return array(
        'manifest' => array('bundle_id'=>'gloskin-sample-products-v1','source_version'=>'v1','expected_products'=>13,'expected_simple'=>8,'expected_variable'=>5,'expected_variations'=>10,'expected_media'=>58),
        'products' => $products, 'media' => $media, 'media_by_product' => $by,
    );
}

function make_importer($cleanup_ok = true) {
    $payload = fixture_payload();
    $bundle = new Fake_Sample_Bundle($payload); $bundle->cleanup_ok = $cleanup_ok;
    $importer = new Gloskin_Site_Core_Sample_Product_Importer(__FILE__);
    $property = new ReflectionProperty(Gloskin_Site_Core_Sample_Product_Importer::class, 'bundle');
    $property->setAccessible(true); $property->setValue($importer, $bundle);
    return array($importer, $bundle, $payload);
}
function count_type($type) { $n = 0; foreach ($GLOBALS['stub_post_types'] as $t) { if ($t === $type) { $n++; } } return $n; }
function count_unique_meta($post_type, $key) { $values = array(); foreach ($GLOBALS['stub_post_types'] as $id => $type) { if ($type !== $post_type) { continue; } $v = (string) get_post_meta((int) $id, $key, true); if ($v !== '') { $values[] = $v; } } return count(array_unique($values)); }

require dirname(__DIR__) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-sample-product-importer.php';

reset_stub_state(); list($importer, $bundle, $payload) = make_importer();
$GLOBALS['stub_fault_parent'] = $payload['products'][0]['source_id'];
$GLOBALS['stub_fault_media_call'] = 3;
$GLOBALS['stub_fault_variation'] = $payload['products'][8]['variations'][1]['source_id'];
$importer->advance('start');
expect_failure(function() use ($importer) { $importer->advance('continue'); }, 'gagal menyimpan produk', 'partial parent failure');
$parent_ids = get_posts(array('post_type'=>'product','meta_key'=>Gloskin_Site_Core_Sample_Product_Importer::SOURCE_META,'meta_value'=>$payload['products'][0]['source_id'],'numberposts'=>2));
ok(count($parent_ids) === 1, 'failed parent persisted exactly once for reconciliation'); $first_parent_id = (int) $parent_ids[0];
expect_failure(function() use ($importer) { $importer->advance('continue'); }, 'Gagal menyimpan media', 'partial media failure');
ok(count_unique_meta('attachment', Gloskin_Site_Core_Sample_Product_Importer::MEDIA_SOURCE_META) === 2, 'two completed media identities survive failure');
$importer->advance('continue');
$parent_ids = get_posts(array('post_type'=>'product','meta_key'=>Gloskin_Site_Core_Sample_Product_Importer::SOURCE_META,'meta_value'=>$payload['products'][0]['source_id'],'numberposts'=>2));
ok(count($parent_ids) === 1 && (int) $parent_ids[0] === $first_parent_id, 'retry reused parent instead of creating duplicate');
for ($i = 1; $i < 8; $i++) { $importer->advance('continue'); }
expect_failure(function() use ($importer) { $importer->advance('continue'); }, 'gagal menyimpan variasi', 'variation failure');
$importer->advance('continue');
for ($i = 9; $i < 13; $i++) { $importer->advance('continue'); }
$final = $importer->advance('continue');
ok($final['status'] === 'consumed', 'fault/retry run consumed');
ok(count_type('product') === 13, 'exactly 13 parents after retries');
ok(count_type('product_variation') === 10, 'exactly 10 variations after retries');
ok(count_unique_meta('attachment', Gloskin_Site_Core_Sample_Product_Importer::MEDIA_SOURCE_META) === 58, 'exactly 58 unique media identities after retries');
$simple = 0; $variable = 0; foreach ($GLOBALS['stub_products'] as $p) { if ($p->is_type('simple')) { $simple++; } elseif ($p->is_type('variable')) { $variable++; } }
ok($simple === 8 && $variable === 5, 'exactly 8 simple and 5 variable parents');

reset_stub_state(); list($importer, $bundle, $payload) = make_importer(); $other = new WC_Product_Simple(); $other->set_sku($payload['products'][0]['sku']); $other->save(); $importer->advance('start');
expect_failure(function() use ($importer) { $importer->advance('continue'); }, 'SKU collision', 'unrelated SKU collision');

reset_stub_state(); list($importer, $bundle, $payload) = make_importer(); foreach (array(1,2) as $x) { $p = new WC_Product_Simple(); $p->set_sku('OTHER-'.$x); $p->update_meta_data(Gloskin_Site_Core_Sample_Product_Importer::SOURCE_META, $payload['products'][0]['source_id']); $p->save(); } $importer->advance('start');
expect_failure(function() use ($importer) { $importer->advance('continue'); }, 'source identity collision', 'product source collision');

reset_stub_state(); list($importer, $bundle, $payload) = make_importer(); $media_source = $payload['media_by_product'][$payload['products'][0]['source_id']][0]['source_id']; foreach (array(1,2) as $x) { $id = $GLOBALS['stub_next_id']++; $GLOBALS['stub_post_types'][$id] = 'attachment'; update_post_meta($id, Gloskin_Site_Core_Sample_Product_Importer::MEDIA_SOURCE_META, $media_source); } $importer->advance('start');
expect_failure(function() use ($importer) { $importer->advance('continue'); }, 'Media source identity collision', 'media source collision');

reset_stub_state(); list($importer, $bundle, $payload) = make_importer(); $GLOBALS['stub_options'][Gloskin_Site_Core_Sample_Product_Importer::LOCK_OPTION] = array('token'=>'active','created_at'=>time());
expect_failure(function() use ($importer) { $importer->advance('start'); }, 'sedang berjalan', 'active lock');

reset_stub_state(); list($importer, $bundle, $payload) = make_importer(); $importer->advance('start'); $importer->advance('continue'); $state = $importer->get_state(); $state['status'] = 'running'; $GLOBALS['stub_options'][Gloskin_Site_Core_Sample_Product_Importer::STATE_OPTION] = $state; $GLOBALS['stub_options'][Gloskin_Site_Core_Sample_Product_Importer::LOCK_OPTION] = array('token'=>'stale','created_at'=>time()-Gloskin_Site_Core_Sample_Product_Importer::LOCK_TTL-1); $resumed = $importer->advance('continue');
ok($resumed['processed_products'] === 2 && $resumed['next_product_index'] === 2, 'stale lock resumes checkpoint without restart');

reset_stub_state(); list($importer, $bundle, $payload) = make_importer(); $GLOBALS['stub_options'][Gloskin_Site_Core_Sample_Product_Importer::STATE_OPTION] = array('status'=>'consumed','bundle_id'=>'gloskin-sample-products-v1','source_version'=>'v1','cleanup'=>'complete'); $summary = $importer->get_summary();
ok($summary['detection'] === 'consumed' && !$importer->should_show_menu(), 'redeployed runtime cannot reopen consumed menu');
expect_failure(function() use ($importer) { $importer->advance('start'); }, 'sudah dikonsumsi', 'consumed rerun');

reset_stub_state(); list($importer, $bundle, $payload) = make_importer(false); $importer->advance('start'); for ($i = 0; $i < 13; $i++) { $importer->advance('continue'); } $result = $importer->advance('continue');
ok($result['status'] === 'consumed' && $result['cleanup'] === 'failed', 'cleanup failure keeps consumed state');
$consumed_pos = array_search('consumed', $GLOBALS['stub_events'], true); $release_keys = array_keys($GLOBALS['stub_events'], 'release', true); $release_pos = $release_keys ? end($release_keys) : false; $cleanup_pos = array_search('cleanup', $GLOBALS['stub_events'], true);
ok($consumed_pos !== false && $release_pos !== false && $cleanup_pos !== false && $consumed_pos < $release_pos && $release_pos < $cleanup_pos, 'consumed is saved before release and cleanup');
$summary = $importer->get_summary(); ok($summary['detection'] === 'consumed' && !$importer->should_show_menu(), 'cleanup failure does not reopen importer');
expect_failure(function() use ($importer) { $importer->advance('start'); }, 'sudah dikonsumsi', 'cleanup-failed consumed rerun');

echo "sample-product importer behavioral: OK\n";
