<?php
$root = dirname(__DIR__);
$translation = file_get_contents($root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-translation.php');
$language = file_get_contents($root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-language.php');
$projection = file_get_contents($root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-language-projection.php');
$kernel = file_get_contents($root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php');
$page_lookup = file_get_contents($root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-page-lookup.php');
$plugin = file_get_contents($root . '/plugin/gloskin-site-core/gloskin-site-core.php');
$must = array(
    'strict language values' => "array( 'id', 'en' )",
    'cookie owner' => "const COOKIE = 'gloskin_lang'",
    'language request' => "gloskin_lang",
    'html lang' => "language_attributes",
    'post fallback resolver' => "saved_post_field",
    'page projection owner' => "translate_post_object",
    'term resolver' => "translate_term",
    'visible meta resolver' => "get_post_metadata",
    'woo title' => "woocommerce_product_get_name",
    'woo short description' => "woocommerce_product_get_short_description",
    'woo description' => "woocommerce_product_get_description",
    'interface resolver' => "translate_interface",
    'navigation resolver' => "nav_menu_item_title",
    'single existing switcher activation' => "gloskin-ui1-lang-switcher",
    'id switch URL' => "'gloskin_lang' => 'id'",
    'en switch URL' => "'gloskin_lang' => 'en'",
    'about vision' => "gloskin_about_vision",
    'about mission' => "gloskin_about_mission",
    'about values' => "gloskin_about_values",
    'founder role' => "gloskin_about_founder_role",
    'founder story' => "gloskin_about_founder_story",
    'consultation answer label projection' => "answer_label_",
    'get_posts projection bridge' => "suppress_filters",
    'hard-coded interface text bridge' => "translate_interface_html",
    'hero heading projection' => "gloskin_hero_heading",
    'hero copy projection' => "gloskin_hero_copy",
    'hero CTA projection' => "gloskin_hero_cta_label",
    'home Why heading projection' => "gloskin_why_heading",
    'home Why lead projection' => "gloskin_why_lead",
    'home Why title projection' => "gloskin_why_primary_title",
    'home Why copy projection' => "gloskin_why_primary_copy",
);
foreach ($must as $name => $needle) {
    if (strpos($language . $projection . $translation . $page_lookup, $needle) === false) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
}
if (strpos($kernel, "const VERSION = '0.7.182';") === false || strpos($plugin, 'Version: 0.7.182') === false) { fwrite(STDERR, "FAIL: version\n"); exit(1); }
if (substr_count($kernel, 'register_frontend') !== 1 || strpos($kernel, 'Gloskin_Site_Core_Language') === false) { fwrite(STDERR, "FAIL: frontend registration\n"); exit(1); }
if (substr_count($kernel, '$language_projection->register_admin();') !== 1 || substr_count($kernel, '$language_projection->register();') !== 1) { fwrite(STDERR, "FAIL: projection registration\n"); exit(1); }
if (strpos($language . $projection, '@huggingface/transformers') !== false || strpos($language . $projection, 'Xenova/opus-mt-id-en') !== false) { fwrite(STDERR, "FAIL: model leaked into frontend runtime\n"); exit(1); }
if (strpos($language, 'set_price') !== false || strpos($language, 'set_stock') !== false || strpos($language, 'set_sku') !== false) { fwrite(STDERR, "FAIL: Woo commercial state touched\n"); exit(1); }
if (strpos($kernel, 'phase3-migration.php') !== false || strpos($kernel, 'Phase3_Migration_Admin') !== false) { fwrite(STDERR, "FAIL: retired Phase 3 runtime resurrected\n"); exit(1); }
if (strpos($projection, 'str_replace( array_keys( $map )') !== false || strpos($projection, "(string) \$entry['source'] !== \$needle") === false) { fwrite(STDERR, "FAIL: interface resolver must be exact-node only\n"); exit(1); }
if (strpos($projection, "'gloskin_hero_cta_url'") !== false || strpos($projection, "'gloskin_hero_media_id'") !== false) { fwrite(STDERR, "FAIL: non-copy hero state entered translation projection\n"); exit(1); }
echo "Phase 5 translation frontend contract: PASS\n";
