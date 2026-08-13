<?php
/**
 * Behavioral regression for the exact description-consolidation bootstrap
 * bug: the Kernel's is_admin() request path never require_once's
 * class-gloskin-site-core-woocommerce-adapter.php (that require only lives
 * on the frontend/template bootstrap path), so the admin-post handler for
 * "Consolidate Product Descriptions" must explicitly load that one pure
 * static helper itself -- and must NEVER record completed_at (which gates
 * retiring the main product content editor) unless the audit/migration
 * loop genuinely executed.
 *
 * Because each scenario needs a different stub behavior for
 * wc_get_products()/wc_get_product() (PHP cannot redefine a function once
 * declared), this file runs as several separate process invocations
 * selected by the GL_TEST_MODE environment variable -- see
 * tests/check-runtime.sh for the full set of invocations.
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$MODE = getenv('GL_TEST_MODE') ?: 'success';

function ok($condition, $message) {
    global $MODE;
    if (!$condition) {
        fwrite(STDERR, "FAIL [{$MODE}]: {$message}\n");
        exit(1);
    }
}

// --- Minimal native WordPress stub surface ------------------------------
$GLOBALS['gl_options'] = array();
$GLOBALS['gl_deleted_options'] = array();
$GLOBALS['gl_removed_supports'] = array();

function current_user_can($cap) { return true; }
function check_admin_referer($action) { return true; }
function wp_die($msg) { throw new RuntimeException('wp_die: ' . (is_string($msg) ? $msg : 'died')); }
function __($text, $domain = null) { return $text; }
function esc_html__($text, $domain = null) { return $text; }
function esc_html($text) { return (string) $text; }
function esc_attr($text) { return (string) $text; }
function esc_url($text) { return (string) $text; }
function admin_url($path = '') { return 'http://example.test/wp-admin/' . $path; }
function wp_strip_all_tags($value) { return trim(strip_tags((string) $value)); }
function get_option($name, $default = false) { return array_key_exists($name, $GLOBALS['gl_options']) ? $GLOBALS['gl_options'][$name] : $default; }
function update_option($name, $value) { $GLOBALS['gl_options'][$name] = $value; return true; }
function delete_option($name) { unset($GLOBALS['gl_options'][$name]); $GLOBALS['gl_deleted_options'][] = $name; return true; }
function remove_post_type_support($type, $feature) { $GLOBALS['gl_removed_supports'][] = "{$type}:{$feature}"; }

class GL_Test_Redirect extends Exception {}
function wp_safe_redirect($url) { throw new GL_Test_Redirect($url); }

class GL_Test_Product {
    private $id;
    private $short;
    private $desc;
    public function __construct($id, $short, $desc) { $this->id = $id; $this->short = $short; $this->desc = $desc; }
    public function get_short_description() { return $this->short; }
    public function get_description() { return $this->desc; }
    public function set_short_description($v) { $this->short = $v; }
    public function save() { /* in-memory only, no real persistence needed for this contract */ }
}

// wc_get_products()/wc_get_product() only exist at all in modes that need
// WooCommerce to appear available -- 'missing_woo' deliberately leaves
// them undefined, reproducing the exact real condition (WooCommerce not
// loaded/ready) the bug report describes.
if ('missing_woo' !== $MODE) {
    $GLOBALS['gl_products'] = array(
        101 => new GL_Test_Product(101, 'Teaser.', '<p>Teaser.</p><p>Extra body content.</p>'),
        102 => new GL_Test_Product(102, '<p>Already complete body.</p>', '<p>Already complete body.</p>'),
    );
    function wc_get_products($args) {
        if (isset($args['limit']) && 1 === $args['limit']) {
            // The self-heal check in descriptions_consolidated() only asks
            // "does at least one product exist" -- simulate a real catalog.
            return array(101);
        }
        if ('failing_query' === $GLOBALS['MODE']) {
            return false;
        }
        return array_keys($GLOBALS['gl_products']);
    }
    function wc_get_product($id) { return isset($GLOBALS['gl_products'][$id]) ? $GLOBALS['gl_products'][$id] : null; }
}
$GLOBALS['MODE'] = $MODE;

require dirname(__DIR__) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php';

// The real bug condition, reproduced: admin-service.php alone (matching
// the real Kernel is_admin() path) must NOT bring the adapter class along
// with it.
ok(!class_exists('Gloskin_Site_Core_WooCommerce_Adapter'), 'test setup invalid: the adapter class must not already be loaded merely by loading admin-service.php');

$admin = new Gloskin_Site_Core_Admin_Service(null, null, '');

if (in_array($MODE, array('success', 'idempotent_second_run', 'missing_woo', 'failing_query'), true)) {
    $redirect_to = null;
    try {
        $admin->handle_consolidate_descriptions();
        ok(false, 'handle_consolidate_descriptions() must always redirect+exit, never return normally');
    } catch (GL_Test_Redirect $e) {
        $redirect_to = $e->getMessage();
    }

    if ('success' === $MODE) {
        ok(class_exists('Gloskin_Site_Core_WooCommerce_Adapter'), 'the bootstrap fix must explicitly load the adapter class on this exact admin-post path');
        $summary = isset($GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_OPTION]) ? $GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_OPTION] : null;
        ok(is_array($summary) && !empty($summary['completed_at']), 'a genuinely successful execution must record completed_at');
        ok(2 === $summary['audited'], 'every product returned by wc_get_products() must be audited, expected 2 got ' . var_export($summary['audited'] ?? null, true));
        ok(1 === $summary['migrated'], 'only the product genuinely missing content must be migrated, expected 1 got ' . var_export($summary['migrated'] ?? null, true));
        ok($GLOBALS['gl_products'][101]->get_short_description() !== 'Teaser.', 'the migrated product\'s short description must actually change');
        ok(strpos($GLOBALS['gl_products'][101]->get_short_description(), 'Teaser.') === 0, 'the original short description must be preserved at the start of the merge, never discarded');
        ok(false === strpos($redirect_to, 'error'), 'a genuine success must not redirect with an error flag');
        ok(!isset($GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_ERROR_OPTION]), 'a genuine success must clear any prior error state');
    }

    if ('idempotent_second_run' === $MODE) {
        // Run once to migrate, then run again in the same process (the
        // wc_get_products()/wc_get_product() stub returns the SAME
        // in-memory objects, so the second pass sees the already-merged
        // short description) and confirm nothing migrates twice.
        $redirect_to2 = null;
        try {
            $admin->handle_consolidate_descriptions();
            ok(false, 'second run must also redirect+exit');
        } catch (GL_Test_Redirect $e) {
            $redirect_to2 = $e->getMessage();
        }
        $summary2 = $GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_OPTION];
        ok(0 === $summary2['migrated'], 'a second consecutive run must migrate ZERO products (idempotent), got ' . var_export($summary2['migrated'], true));
        ok(2 === $summary2['audited'], 'a second run must still audit every product');
    }

    if ('missing_woo' === $MODE) {
        ok(!isset($GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_OPTION]), 'must NEVER write completed_at when WooCommerce functions are unavailable -- this is the exact false-0/0-success bug');
        $err = isset($GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_ERROR_OPTION]) ? $GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_ERROR_OPTION] : null;
        ok(is_array($err) && !empty($err['failed_at']) && '' !== $err['message'], 'must record a truthful, non-empty error instead of a silent success');
        ok(strpos($redirect_to, 'gloskin_consolidation=error') !== false, 'must redirect with a visible error flag');
    }

    if ('failing_query' === $MODE) {
        ok(!isset($GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_OPTION]), 'must NEVER write completed_at when the product query itself fails');
        $err = isset($GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_ERROR_OPTION]) ? $GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_ERROR_OPTION] : null;
        ok(is_array($err) && !empty($err['failed_at']), 'a failed product query must record a truthful error');
    }
}

if ('false_complete_selfheal' === $MODE) {
    // Reproduce the exact residue the pre-fix bug could have left behind:
    // completed_at recorded with audited=0/migrated=0 while WooCommerce
    // genuinely has products.
    $GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_OPTION] = array(
        'audited' => 0,
        'migrated' => 0,
        'completed_at' => time() - 3600,
    );
    $admin->maybe_simplify_product_editor();
    ok(array() === $GLOBALS['gl_removed_supports'], 'a false 0/0-complete state must NEVER retire the main editor, got: ' . implode(',', $GLOBALS['gl_removed_supports']));
    ok(in_array(Gloskin_Site_Core_Admin_Service::CONSOLIDATION_OPTION, $GLOBALS['gl_deleted_options'], true), 'the invalid false-complete state must be cleared so the admin card reports "not yet run" again');
    ok(!isset($GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_OPTION]), 'the stale option itself must no longer be present after the self-heal');
}

if ('real_complete_gate' === $MODE) {
    // A genuinely proven consolidation (audited > 0) must still be allowed
    // to retire the editor -- the self-heal must not become a blanket
    // block.
    $GLOBALS['gl_options'][Gloskin_Site_Core_Admin_Service::CONSOLIDATION_OPTION] = array(
        'audited' => 2,
        'migrated' => 1,
        'completed_at' => time() - 3600,
    );
    $admin->maybe_simplify_product_editor();
    ok(in_array('product:editor', $GLOBALS['gl_removed_supports'], true), 'a genuinely proven consolidation must still retire the main editor');
}

echo "description-consolidation-bootstrap-contract [{$MODE}]: OK\n";
