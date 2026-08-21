<?php
/**
 * Phase 5 translation frontend contract.
 *
 * Asserts actual current architecture, not historical stubs. Describes intent:
 *
 *  - Exactly one Language registration on the frontend path.
 *  - Exactly one Language_Projection registration.
 *  - One canonical interface resolver (interface_lookup) shared by both transports.
 *  - Request-local static caches for registry, interface_registry, interface_translations,
 *    and interface_lookup — no per-text-node reconstruction.
 *  - Exact-node-only interface translation; no substring replacement.
 *  - Raw canonical post field used as freshness source (not already-projected EN value).
 *  - Idempotency guard on the output buffer so it cannot be started twice.
 *  - Re-entrancy guard on interface_text so recursive filter calls fail-open.
 *  - No frontend machine translation model.
 *  - No WooCommerce commercial state mutation.
 *  - No retired Phase-3 runtime.
 */
declare( strict_types=1 );

$root        = dirname( __DIR__ );
$translation = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-translation.php' );
$language    = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-language.php' );
$projection  = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-language-projection.php' );
$kernel      = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$page_lookup = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-page-lookup.php' );
$plugin      = (string) file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );
$header      = (string) file_get_contents( $root . '/plugin/gloskin-site-core/templates/parts/header.php' );

function p5fail( string $msg ): void {
	fwrite( STDERR, "phase5-translation-frontend-contract.php: FAIL: {$msg}\n" );
	exit( 1 );
}
function p5must( bool $cond, string $msg ): void {
	if ( ! $cond ) { p5fail( $msg ); }
}

/* ── Version ─────────────────────────────────────────────────────── */
p5must( false !== strpos( $kernel, "const VERSION = '0.7.194'" ) && false !== strpos( $plugin, 'Version: 0.7.194' ), 'release owners synchronized at 0.7.194' );

/* ── One Language registration, one Projection registration ──────── */
p5must( 1 === substr_count( $kernel, 'register_frontend' ), 'exactly one frontend Language registration in Kernel' );
p5must( false !== strpos( $kernel, 'Gloskin_Site_Core_Language' ), 'Language class registered in Kernel' );
p5must( 1 === substr_count( $kernel, '$language_projection->register();' ), 'exactly one Projection registration in Kernel' );
// register_admin() was retired from Language_Projection — assert it is absent.
p5must( false === strpos( $projection, 'register_admin' ), 'Language_Projection has no register_admin (correctly retired)' );

/* ── Single canonical resolver: interface_lookup() ───────────────── */
p5must( false !== strpos( $translation, 'public static function interface_lookup()' ), 'Translation owns interface_lookup() — the single resolver' );
p5must( false !== strpos( $translation, 'self::$interface_lookup_cache' ), 'interface_lookup() uses a request-local static cache' );
// Both transports must delegate to interface_lookup(), not maintain their own foreach scan.
p5must( false !== strpos( $language, 'interface_lookup()' ), 'Language::interface_text delegates to the shared interface_lookup()' );
p5must( false !== strpos( $projection, 'interface_lookup()' ), 'Language_Projection::translate_text_segment delegates to the shared interface_lookup()' );

/* ── Request-local caches (no per-call reconstruction) ───────────── */
p5must( false !== strpos( $translation, 'self::$registry_cache' ), 'registry() uses request-local static cache' );
p5must( false !== strpos( $translation, 'self::$interface_registry_cache' ), 'interface_registry() uses request-local static cache' );
p5must( false !== strpos( $translation, 'self::$interface_translations_cache' ), 'interface_translations() uses request-local static cache' );
// Projection must NOT contain a per-text-node foreach over interface_registry.
p5must( false === strpos( $projection, 'foreach ( Gloskin_Site_Core_Translation::interface_registry()' ), 'Projection has no per-text-node foreach over interface_registry' );
p5must( false === strpos( $projection, 'interface_translations()' ), 'Projection does not call interface_translations() directly (delegates to lookup)' );

/* ── Exact-node-only interface translation ───────────────────────── */
// translate_interface_html: must split on HTML tags; exact text-node comparison.
p5must( false !== strpos( $projection, 'translate_interface_html' ), 'HTML output buffer exists' );
// Substring replacement remains forbidden.
p5must( false === strpos( $projection, 'str_replace( array_keys(' ), 'no substring array-replace in projection' );

/* ── Idempotency and re-entrancy guards ──────────────────────────── */
p5must( false !== strpos( $projection, 'static $started = false' ), 'output buffer has idempotency guard (static $started)' );
p5must( false !== strpos( $language, 'static $in_lookup = false' ), 'interface_text has re-entrancy guard (static $in_lookup)' );

/* ── Raw canonical freshness source ──────────────────────────────── */
// saved_post_field must use raw post field from get_post(), not the $fallback
// which may already have passed through the_posts translation projection.
p5must( false !== strpos( $language, '$raw_source' ), 'saved_post_field uses raw_source from get_post(), not $fallback' );
p5must( false !== strpos( $language, 'fresh_post_value( $post->ID, $field, $raw_source )' ), 'freshness hashing uses raw canonical source' );

/* ── Essential resolver hooks remain ─────────────────────────────── */
$must_hooks = array(
	'strict language values'           => "array( 'id', 'en' )",
	'cookie owner'                     => "const COOKIE = 'gloskin_lang'",
	'language request'                 => 'gloskin_lang',
	'html lang'                        => 'language_attributes',
	'post fallback resolver'           => 'saved_post_field',
	'page projection owner'            => 'translate_post_object',
	'term resolver'                    => 'translate_term',
	'visible meta resolver'            => 'get_post_metadata',
	'woo title'                        => 'woocommerce_product_get_name',
	'woo short description'            => 'woocommerce_product_get_short_description',
	'woo description'                  => 'woocommerce_product_get_description',
	'interface resolver'               => 'translate_interface',
	'navigation resolver'              => 'nav_menu_item_title',
	'switch URL method'                => 'public static function switch_url(',
	'gloskin_lang query arg'           => 'gloskin_lang',
	'about vision'                     => 'gloskin_about_vision',
	'about mission'                    => 'gloskin_about_mission',
	'about values'                     => 'gloskin_about_values',
	'founder role'                     => 'gloskin_about_founder_role',
	'founder story'                    => 'gloskin_about_founder_story',
	'consultation answer label'        => 'answer_label_',
	'get_posts projection bridge'      => 'suppress_filters',
	'hard-coded interface text bridge' => 'translate_interface_html',
	'hero heading projection'          => 'gloskin_hero_heading',
	'hero copy projection'             => 'gloskin_hero_copy',
	'hero CTA projection'              => 'gloskin_hero_cta_label',
	'home Why heading projection'      => 'gloskin_why_heading',
	'home Why lead projection'         => 'gloskin_why_lead',
	'home Why title projection'        => 'gloskin_why_primary_title',
	'home Why copy projection'         => 'gloskin_why_primary_copy',
);
foreach ( $must_hooks as $name => $needle ) {
	p5must( false !== strpos( $language . $projection . $translation . $page_lookup, $needle ), "essential resolver hook: {$name}" );
}

/* ── Language switcher lives in the template (not PHP class files) ── */
p5must( false !== strpos( $header, 'gloskin-ui1-lang-switcher' ), 'language switcher element exists in header template' );

/* ── No frontend machine translation ─────────────────────────────── */
p5must( false === strpos( $language . $projection, '@huggingface/transformers' ), 'no HuggingFace transformers in frontend runtime' );
p5must( false === strpos( $language . $projection, 'Xenova/opus-mt-id-en' ), 'no MT model in frontend runtime' );

/* ── No Woo commercial mutation ──────────────────────────────────── */
p5must( false === strpos( $language, 'set_price' ), 'no Woo set_price in Language' );
p5must( false === strpos( $language, 'set_stock' ), 'no Woo set_stock in Language' );
p5must( false === strpos( $language, 'set_sku' ), 'no Woo set_sku in Language' );

/* ── No retired Phase-3 runtime ──────────────────────────────────── */
p5must( false === strpos( $kernel, 'phase3-migration.php' ), 'retired Phase-3 migration file not in Kernel' );
p5must( false === strpos( $kernel, 'Phase3_Migration_Admin' ), 'retired Phase3_Migration_Admin not in Kernel' );

/* ── Non-copy hero state not entered into translation projection ─── */
p5must( false === strpos( $projection, "'gloskin_hero_cta_url'" ), 'non-copy hero CTA URL not in projection' );
p5must( false === strpos( $projection, "'gloskin_hero_media_id'" ), 'non-copy hero media ID not in projection' );

echo "phase5-translation-frontend-contract.php: OK (single resolver + cached registries + idempotency + raw source + version 0.7.194)\n";
