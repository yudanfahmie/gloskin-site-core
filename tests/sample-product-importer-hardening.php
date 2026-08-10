<?php
declare(strict_types=1);

$GLOBALS['gl_hardening_attachment_file'] = tempnam(sys_get_temp_dir(), 'gl-media-present-');
$GLOBALS['gl_hardening_broken_attachments'] = array();
file_put_contents($GLOBALS['gl_hardening_attachment_file'], 'present');

function get_attached_file($attachment_id) {
    if (in_array((int) $attachment_id, $GLOBALS['gl_hardening_broken_attachments'], true)) {
        return sys_get_temp_dir() . '/gloskin-missing-attachment-' . (int) $attachment_id . '.jpg';
    }
    return $GLOBALS['gl_hardening_attachment_file'];
}

require __DIR__ . '/sample-product-importer-behavior.php';

reset_stub_state();
list($importer, $bundle, $payload) = make_importer();
$existing = new WP_Term(77, 'facial-wash');
$GLOBALS['stub_terms_by_slug']['facial-wash'] = $existing;
$GLOBALS['stub_terms_by_id'][77] = $existing;
$started = $importer->advance('start');
ok($started['processed_products'] === 0 && count_type('product') === 0, 'start validates but writes no parent');
$one = $importer->advance('continue');
ok($one['processed_products'] === 1 && count_type('product') === 1, 'one continuation writes exactly one parent');
$first_ids = get_posts(array('post_type'=>'product','meta_key'=>Gloskin_Site_Core_Sample_Product_Importer::SOURCE_META,'meta_value'=>$payload['products'][0]['source_id'],'numberposts'=>2));
$first = wc_get_product((int) $first_ids[0]);
ok($first && $first->get_status() === 'draft', 'parent stays draft');
ok($first->get_category_ids() === array(77), 'existing exact category slug reused');
$importer->advance('continue');
ok(isset($GLOBALS['stub_terms_by_slug']['day-cream-sunscreen']), 'missing category slug created automatically');
ok($GLOBALS['stub_terms_by_slug']['facial-wash']->term_id === 77, 'existing category remains unchanged');

for ($i = 2; $i < 13; $i++) { $importer->advance('continue'); }
$done = $importer->advance('continue');
ok($done['status'] === 'consumed', 'hardening run consumed');
foreach ($GLOBALS['stub_products'] as $product) {
    if ($product->is_type('variation')) { ok($product->get_status() === 'publish', 'every child variation is publish'); }
}

reset_stub_state();
list($importer, $bundle, $payload) = make_importer();
$importer->advance('start');
$GLOBALS['stub_fault_media_call'] = 2;
expect_failure(function() use ($importer) { $importer->advance('continue'); }, 'Gagal menyimpan media', 'seed partial media retry');
$media_ids = get_posts(array('post_type'=>'attachment','meta_key'=>Gloskin_Site_Core_Sample_Product_Importer::MEDIA_SOURCE_META,'meta_value'=>$payload['media'][0]['source_id'],'numberposts'=>2));
ok(count($media_ids) === 1, 'partial media seed exists');
$GLOBALS['gl_hardening_broken_attachments'][] = (int) $media_ids[0];
expect_failure(function() use ($importer) { $importer->advance('continue'); }, 'hilang atau rusak', 'broken reused attachment fails actionable');

reset_stub_state();
list($importer, $bundle, $payload) = make_importer();
$bundle->validated['manifest']['checksums'] = array('products.json'=>str_repeat('a',64),'media.json'=>str_repeat('b',64));
$importer->advance('start');
$importer->advance('continue');
$bundle->validated['manifest']['checksums']['media.json'] = str_repeat('c',64);
expect_failure(
    function() use ($importer) { $importer->advance('continue'); },
    'Bundle sample product berubah setelah import dimulai. Selesaikan/reconcile bundle sebelum melanjutkan.',
    'payload fingerprint mismatch'
);

@unlink($GLOBALS['gl_hardening_attachment_file']);
echo "sample-product importer hardening: OK\n";
