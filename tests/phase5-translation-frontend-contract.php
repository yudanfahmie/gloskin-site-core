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
 *  - Native WordPress/Woo locale is request-scoped through the existing Language owner.
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
p5must( false !== strpos( $kernel, "const VERSION = '0.7.219'" ) && false !== strpos( $plugin, 'Version: 0.7.219' ), 'release owners synchronized at 0.7.219' );

/* ── One Language registration, one Projection registration ──────── */
p5must( 1 === substr_count( $kernel, 'register_frontend' ), 'exactly one frontend Language registration in Kernel' );
p5must( false !== strpos( $kernel, 'Gloskin_Site_Core_Language' ), 'Language class registered in Kernel' );
p5must( 1 === substr_count( $kernel, '$language_projection->register();' ), 'exactly one Projection registration in Kernel' );
p5must( false === strpos( $projection, 'register_admin' ), 'Language_Projection has no register_admin (correctly retired)' );

/* ── Native request-locale bridge stays inside Language ───────────── */
p5must( 1 === substr_count( $language, "add_filter( 'pre_determine_locale', array( \$this, 'request_locale' ), 1 );" ), 'one early pre_determine_locale bridge owner' );
p5must( 1 === substr_count( $language, "add_filter( 'locale', array( \$this, 'request_locale' ), 1 );" ), 'one canonical locale compatibility bridge owner' );
p5must( false !== strpos( $language, 'private static function requested_language()' ), 'one shared language-preference resolver exists in Language' );
p5must( false !== strpos( $language, "return 'en' === self::requested_language() ? 'en_US' : \$locale;" ), 'EN maps to en_US while ID/no override preserves incoming site locale' );
p5must( false !== strpos( $language, "isset( \$_GET['gloskin_lang'] )" ) && false !== strpos( $language, "isset( \$_COOKIE[ self::COOKIE ] )" ), 'shared preference resolver owns GET and cookie inputs' );
p5must( false === strpos( $language, "'id_ID'" ), 'ID does not force a second hard-coded installation locale' );
p5must( false === strpos( $language, 'switch_to_locale(' ) && false === strpos( $language, 'WPLANG') && false === strpos( $language, 'update_option(' ), 'frontend locale bridge does not mutate WordPress/admin locale state' );
p5must( false === strpos( $language, "'woocommerce' === \$domain" ) && false === strpos( $language, "\$domain === 'woocommerce'" ), 'Language does not become a Woo gettext string-map owner' );
foreach ( array( 'Dashboard', 'Orders', 'Downloads', 'Addresses', 'Account details', 'Logout' ) as $woo_native_copy ) {
	p5must( false === strpos( $language, "'{$woo_native_copy}'" ), 'native Woo copy is not hard-coded into Language: ' . $woo_native_copy );
}

/* ── Single canonical resolver: interface_lookup() ───────────────── */
p5must( false !== strpos( $translation, 'public static function interface_lookup()' ), 'Translation owns interface_lookup() — the single resolver' );
p5must( false !== strpos( $translation, 'self::$interface_lookup_cache' ), 'interface_lookup() uses a request-local static cache' );
p5must( false !== strpos( $language, 'interface_lookup()' ), 'Language::interface_text delegates to the shared interface_lookup()' );
p5must( false !== strpos( $projection, 'interface_lookup()' ), 'Language_Projection::translate_text_segment delegates to the shared interface_lookup()' );

/* ── Request-local caches (no per-call reconstruction) ───────────── */
p5must( false !== strpos( $translation, 'self::$registry_cache' ), 'registry() uses request-local static cache' );
p5must( false !== strpos( $translation, 'self::$interface_registry_cache' ), 'interface_registry() uses request-local static cache' );
p5must( false !== strpos( $translation, 'self::$interface_translations_cache' ), 'interface_translations() uses request-local static cache' );
p5must( false === strpos( $projection, 'foreach ( Gloskin_Site_Core_Translation::interface_registry()' ), 'Projection has no per-text-node foreach over interface_registry' );
p5must( false === strpos( $projection, '::interface_translations()' ), 'Projection has no direct ::interface_translations() call (delegates to has_interface_translations or lookup)' );

/* ── Exact-node-only interface translation ───────────────────────── */
p5must( false !== strpos( $projection, 'translate_interface_html' ), 'HTML output buffer exists' );
p5must( false === strpos( $projection, 'str_replace( array_keys(' ), 'no substring array-replace in projection' );

/* ── Idempotency and re-entrancy guards ──────────────────────────── */
p5must( false !== strpos( $projection, 'static $started = false' ), 'output buffer has idempotency guard (static $started)' );
p5must( false !== strpos( $language, 'static $in_lookup = false' ), 'interface_text has re-entrancy guard (static $in_lookup)' );

/* ── Defense-in-depth: memory guard + zero-cost empty path ──────── */
p5must( false !== strpos( $language, 'private static function within_memory_budget()' ), 'Language has within_memory_budget() circuit-breaker helper' );
p5must( false !== strpos( $language, 'self::within_memory_budget()' ), 'language() calls within_memory_budget() before preference resolution' );
p5must( false !== strpos( $language, 'static $tripped = false, $limit = null' ), 'within_memory_budget() caches limit parse and trip flag in static locals' );
p5must( false !== strpos( $translation, 'public static function has_interface_translations()' ), 'Translation has zero-cost empty-translations guard method' );
p5must( false !== strpos( $projection, 'has_interface_translations()' ), 'Projection uses has_interface_translations() for zero-cost empty guard' );
p5must( false === strpos( $projection, '::interface_translations()' ), 'Projection has no direct ::interface_translations() call' );
p5must( false !== strpos( $projection, 'memory_get_usage()' ), 'HTML buffer has fail-open memory budget guard' );
p5must( false !== strpos( $projection, 'parse_memory_limit' ), 'memory budget guard uses portable limit parser' );
p5must( false !== strpos( $translation, "wp_cache_get( 'gloskin_interface_lookup'" ), 'interface_lookup seeds WP object cache for cross-request persistence' );
p5must( false !== strpos( $translation, "wp_cache_set( 'gloskin_interface_lookup'" ), 'interface_lookup populates WP object cache after build' );
p5must( false !== strpos( $translation, "wp_cache_delete( 'gloskin_interface_lookup'" ), 'ajax_save invalidates object cache on translation update' );

/* ── Raw canonical freshness source ──────────────────────────────── */
p5must( false !== strpos( $language, '$raw_source' ), 'saved_post_field uses raw_source from get_post(), not $fallback' );
p5must( false !== strpos( $language, 'fresh_post_value( $post->ID, $field, $raw_source )' ), 'freshness hashing uses raw canonical source' );

/* ── Woo structural page body remains canonical ──────────────────── */
p5must( false !== strpos( $language, 'private static function is_woocommerce_structural_page(' ), 'Language owns one semantic Woo structural-page guard' );
foreach ( array( "'myaccount'", "'cart'", "'checkout'" ) as $woo_page_key ) {
	p5must( false !== strpos( $language, $woo_page_key ), 'Woo structural guard includes ' . $woo_page_key );
}
p5must( false !== strpos( $language, "'post_content' === \$field && self::is_woocommerce_structural_page( \$post->ID )" ), 'Woo special-page post_content never substitutes saved editorial body' );

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

/* ── Content-ownership durable rules ─────────────────────────────── */
p5must( false === strpos( $language, "add_filter( 'the_content'" ) && false === strpos( $projection, "add_filter( 'the_content'" ), 'no global the_content filter in language or projection' );
p5must( false === strpos( $language, "add_filter( 'post_content'" ) && false === strpos( $projection, "add_filter( 'post_content'" ), 'no global post_content filter hook in language or projection' );
p5must( false !== strpos( $projection, 'public function start_interface_buffer()' ), 'Projection defines start_interface_buffer() as whole-content output buffer owner' );
p5must( 1 === substr_count( $projection, "array( \$this, 'start_interface_buffer' )" ), 'Projection registers start_interface_buffer exactly once on template_redirect' );
p5must( false === strpos( $language . $projection, 'woocommerce_before_template_part' ), 'no rendered-Woo HTML replacement owner' );

echo "phase5-translation-frontend-contract.php: OK (native locale bridge + single resolver + cached registries + circuit breaker + structural Woo guard + content ownership + version 0.7.219)\n";
