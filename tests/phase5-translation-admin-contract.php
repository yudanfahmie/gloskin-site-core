<?php
$root = dirname(__DIR__);
$php = file_get_contents($root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-translation.php');
$kernel = file_get_contents($root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php');
$admin = file_get_contents($root . '/plugin/gloskin-site-core/assets/js/gloskin-translation-admin.js');
$worker = file_get_contents($root . '/plugin/gloskin-site-core/assets/js/gloskin-translation-worker.js');
$checks = array(
    'companion post meta' => "_gloskin_translation_en",
    'single submenu' => "add_submenu_page",
    'capability' => "manage_options",
    'nonce' => "check_ajax_referer",
    'dynamic post discovery' => "get_posts(",
    'dynamic term discovery' => "get_terms(",
    'products' => "'product' =>",
    'visible taxonomies' => "'product_cat'",
    'consultation questions' => "QUESTION_POST_TYPE",
    'answer labels' => "answer_label_",
    'interface records' => "interface_registry",
    'admin-only boot' => "register_admin",
);
foreach ($checks as $name => $needle) {
    if (strpos($php . $kernel, $needle) === false) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
}
if (strpos($admin, "if (!force && String(field.en || '').trim()) return false") === false) { fwrite(STDERR, "FAIL: Generate Missing overwrite guard\n"); exit(1); }
if (strpos($admin, 'translateRich') === false || strpos($admin, '<!--[\\s\\S]*?-->') === false) { fwrite(STDERR, "FAIL: rich text preservation\n"); exit(1); }
if (strpos($worker, '@huggingface/transformers') === false || strpos($worker, 'Xenova/opus-mt-id-en') === false) { fwrite(STDERR, "FAIL: OPUS-MT browser generator\n"); exit(1); }
if (strpos($worker, "createTranslator('webgpu')") === false || strpos($worker, "createTranslator('wasm')") === false) { fwrite(STDERR, "FAIL: browser device fallback\n"); exit(1); }
if (substr_count($kernel, "class-gloskin-site-core-translation.php") !== 1 || strpos($kernel, "if ( is_admin() )") > strpos($kernel, "class-gloskin-site-core-translation.php")) { fwrite(STDERR, "FAIL: model/admin service must stay admin branch in part 1\n"); exit(1); }
echo "Phase 5 translation admin contract: PASS\n";
