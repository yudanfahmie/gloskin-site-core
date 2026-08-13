<?php
/**
 * Behavioral + static contract for the one-canonical-product-description
 * consolidation (Goal 4/5): the pure merge function itself, plus the
 * static-ownership guards (admin-triggered, gated editor retirement, no
 * duplicate description meta, native post_excerpt save path).
 *
 * The audited/migrated counts this prints are computed by running the
 * REAL merge function against the REAL sample-product bundle corpus
 * (migration-source/gloskin-sample-products-v1/products.json) -- the one
 * concrete product-content corpus available in this repo/environment (no
 * live WordPress/WooCommerce database access exists here). The admin
 * action wired up in class-gloskin-site-core-admin-service.php is what
 * performs the equivalent migration against a real store's actual
 * products whenever a site admin runs it.
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

function wp_strip_all_tags($value) { return trim(strip_tags((string) $value)); }

function ok($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require dirname(__DIR__) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php';

$merge = ['class' => 'Gloskin_Site_Core_WooCommerce_Adapter', 'method' => 'consolidate_description_content'];

// 1. Empty description: short description untouched, nothing to migrate.
$r = Gloskin_Site_Core_WooCommerce_Adapter::consolidate_description_content('Teaser.', '');
ok($r['result'] === 'Teaser.' && $r['changed'] === false, 'empty description must leave short description untouched');

// 2. Empty short description, real description: description becomes the
// short description outright (it is the only content that exists).
$r = Gloskin_Site_Core_WooCommerce_Adapter::consolidate_description_content('', '<p>Body copy.</p>');
ok($r['result'] === '<p>Body copy.</p>' && $r['changed'] === true, 'empty short description must adopt the full description');

// 3. Short description already contains every block of the description
// (case-insensitive/whitespace-tolerant containment): no duplication.
$r = Gloskin_Site_Core_WooCommerce_Adapter::consolidate_description_content('<p>Body copy.</p>', '<p>Body copy.</p>');
ok($r['changed'] === false, 'already-consolidated content must not be re-migrated/duplicated');

// 4. Short description is a brief teaser; description has genuinely new
// blocks -- those (and only those) must be appended, once, in order.
$short = 'Pembersih wajah lembut untuk langkah awal rutinitas.';
$desc  = '<h3>Karakter produk</h3><p>Pembersih wajah harian dengan karakter sederhana.</p><h3>Tekstur</h3><p>Tekstur cair-gel ringan.</p>';
$r = Gloskin_Site_Core_WooCommerce_Adapter::consolidate_description_content($short, $desc);
ok($r['changed'] === true, 'a teaser missing the richer body content must be migrated');
ok(strpos($r['result'], $short) === 0, 'the existing short description must be preserved, not discarded, at the start of the merge');
ok(strpos($r['result'], 'Karakter produk') !== false, 'missing description blocks must be appended');
ok(strpos($r['result'], 'Tekstur cair-gel ringan.') !== false, 'every missing description block must be appended, not just the first');

// 5. Re-running the merge on its own already-merged output must be a
// stable no-op (idempotent) -- running the admin action twice must never
// keep duplicating content.
$r2 = Gloskin_Site_Core_WooCommerce_Adapter::consolidate_description_content($r['result'], $desc);
ok($r2['changed'] === false, 'merge must be idempotent: re-running on already-merged content must change nothing');

// 6. post_content itself is never an input the function can mutate --
// it only ever returns a merged short-description string. (Static proof:
// the function signature/body never calls set_description()/update meta
// on $description, only reads it.)
$adapter_src = file_get_contents(dirname(__DIR__) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php');
ok(strpos($adapter_src, 'function consolidate_description_content') !== false, 'consolidation merge function missing');
ok(strpos($adapter_src, 'set_description(') === false, 'consolidation must never call set_description() -- post_content must never be mutated by this pass');

// -----------------------------------------------------------------------
// Real audit against the one available product-content corpus: the
// sample-product bundle (13 products; short_description/description both
// always non-empty per its own manifest contract).
// -----------------------------------------------------------------------
$products = json_decode(file_get_contents(dirname(__DIR__) . '/migration-source/gloskin-sample-products-v1/products.json'), true)['products'];
$audited = 0;
$requiring_migration = 0;
foreach ($products as $p) {
    $audited++;
    $merged = Gloskin_Site_Core_WooCommerce_Adapter::consolidate_description_content($p['short_description'], $p['description']);
    if ($merged['changed']) {
        $requiring_migration++;
    }
    // Every migrated result must still literally contain the original
    // short description text -- never a discard, never a bare overwrite.
    ok(strpos($merged['result'], trim($p['short_description'])) !== false || $p['short_description'] === '', 'sample bundle: migration must never discard the existing short description for ' . $p['source_id']);
}
ok($audited === 13, 'expected to audit all 13 sample bundle products');

fwrite(STDOUT, "description-consolidation: products audited={$audited} requiring_migration={$requiring_migration}\n");

// -----------------------------------------------------------------------
// Admin-service static ownership guards: reuses the EXISTING Gloskin
// Admin Service (no new Product Admin framework), gates editor retirement
// on proven consolidation, moves (never duplicates) the native
// postexcerpt box, and never DOM-hacks the block-based Product Editor.
// -----------------------------------------------------------------------
$admin_src = file_get_contents(dirname(__DIR__) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php');
ok(strpos($admin_src, 'function handle_consolidate_descriptions') !== false, 'admin-triggered consolidation action missing');
ok(strpos($admin_src, "current_user_can( self::MIGRATION_CAPABILITY )") !== false, 'consolidation action must reuse the existing manage_woocommerce capability gate');
ok(strpos($admin_src, 'check_admin_referer( self::CONSOLIDATION_NONCE )') !== false, 'consolidation action must be nonce-protected');
ok(strpos($admin_src, "\$product->set_short_description(") !== false, 'consolidation must write through Woo\'s own set_short_description() API');
ok(strpos($admin_src, "\$product->save();") !== false, 'consolidation must persist through Woo\'s own product save API');
ok(strpos($admin_src, 'function maybe_simplify_product_editor') !== false, 'gated editor-retirement method missing');
ok(strpos($admin_src, 'function descriptions_consolidated') !== false, 'editor retirement must check a real consolidated-proof gate');
$editor_fn = substr($admin_src, strpos($admin_src, 'function maybe_simplify_product_editor'));
$editor_fn = substr($editor_fn, 0, strpos($editor_fn, "\n\t}"));
ok(strpos($editor_fn, 'descriptions_consolidated()') !== false, 'editor removal must check the consolidated-proof gate');
ok(strpos($editor_fn, 'remove_post_type_support') !== false, 'editor removal must actually call remove_post_type_support()');
ok(strpos($editor_fn, 'descriptions_consolidated()') < strpos($editor_fn, 'remove_post_type_support'), 'editor removal must be gated behind proven consolidation, never unconditional');
ok(strpos($admin_src, "remove_meta_box( 'postexcerpt', 'product', 'normal' )") !== false, 'native short-description box must be moved (not duplicated)');
ok(strpos($admin_src, "add_meta_box( 'postexcerpt',") !== false && strpos($admin_src, "'post_excerpt_meta_box'") !== false, 'short-description box must stay WordPress\'s own postexcerpt box/callback -- must still save to Woo\'s canonical post_excerpt');
ok(substr_count($admin_src, "add_meta_box( 'postexcerpt'") === 1, 'must not register a second/duplicate description meta box');
if (preg_match('/gloskin[_-](product|description)[_-]meta/i', $admin_src)) {
    fwrite(STDERR, "FAIL: a custom duplicate description meta field was introduced\n");
    exit(1);
}

// -----------------------------------------------------------------------
// Bootstrap-bug regression (found live on staging): the Kernel's is_admin()
// request path -- which the admin-post consolidation handler runs on --
// never require_once's the WooCommerce adapter class; only the frontend/
// template bootstrap path does. The handler must explicitly load its own
// dependency rather than assume Kernel composition provides it, and must
// never write completed_at except on a genuinely executed migration pass.
// Full behavioral proof (both branches actually exercised) lives in
// tests/description-consolidation-bootstrap-contract.php.
// -----------------------------------------------------------------------
$kernel_src = file_get_contents(dirname(__DIR__) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php');
$admin_branch = substr($kernel_src, strpos($kernel_src, 'if ( is_admin() ) {'));
$admin_branch = substr($admin_branch, 0, strpos($admin_branch, "\n\t\t}\n"));
ok(strpos($admin_branch, 'class-gloskin-site-core-woocommerce-adapter.php') === false, 'test/kernel assumption stale: the is_admin() bootstrap path now loads the adapter directly -- the explicit require_once in handle_consolidate_descriptions() may be redundant, re-check this guard');
ok(strpos($admin_src, "require_once __DIR__ . '/class-gloskin-site-core-woocommerce-adapter.php';") !== false, 'the admin-post consolidation handler must explicitly load its own adapter dependency, since the is_admin() Kernel path never does');
$handler_fn = substr($admin_src, strpos($admin_src, 'function handle_consolidate_descriptions'));
$handler_fn = substr($handler_fn, 0, strpos($handler_fn, "\n\t}\n"));
ok(strpos($handler_fn, 'try {') !== false && strpos($handler_fn, 'catch ( Throwable') !== false, 'consolidation execution must be wrapped so any failure is caught, never a silent skip');
ok(strpos($handler_fn, '$executed = true;') !== false, 'a genuine-execution flag must exist, distinct from the audited/migrated counts themselves');
ok(strpos($handler_fn, 'CONSOLIDATION_ERROR_OPTION') !== false, 'a failed run must record a separate, truthful error state');
// The completed_at-writing update_option() call must be reachable only
// through the $executed-gated success branch -- i.e. it must appear AFTER
// the "if ( ! $executed )" early-exit, not before/unconditionally.
$not_executed_pos = strpos($handler_fn, 'if ( ! $executed )');
$completed_write_pos = strpos($handler_fn, "'completed_at' => time()");
ok(false !== $not_executed_pos && false !== $completed_write_pos && $not_executed_pos < $completed_write_pos, 'completed_at must only be written after the $executed early-exit guard, never unconditionally');

echo "description-consolidation-contract: OK\n";
