<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
function read_contract_file( $root, $path ) {
	$data = file_get_contents( $root . '/' . $path );
	if ( false === $data ) { fwrite( STDERR, "Unable to read {$path}\n" ); exit( 1 ); }
	return $data;
}
function require_contract( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$core = read_contract_file( $root, 'plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js' );
$shop_owner = read_contract_file( $root, 'plugin/gloskin-site-core/assets/js/gloskin-ui1-shop-discovery.js' );
$shop_template = read_contract_file( $root, 'plugin/gloskin-site-core/templates/pages/shop.php' );
$main = read_contract_file( $root, 'plugin/gloskin-site-core/gloskin-site-core.php' );
$kernel = read_contract_file( $root, 'plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$batch = read_contract_file( $root, 'plugin/gloskin-site-core/includes/class-gloskin-site-core-production-batch.php' );
$route = read_contract_file( $root, 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-route-trait.php' );
$query = read_contract_file( $root, 'plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-query-trait.php' );
$contact = read_contract_file( $root, 'plugin/gloskin-site-core/includes/gloskin-site-core-contact-service-submit-trait.php' );
$mailer = read_contract_file( $root, 'plugin/gloskin-site-core/includes/class-gloskin-site-core-contact-mailer.php' );
$contact_security = read_contract_file( $root, 'plugin/gloskin-site-core/includes/gloskin-site-core-contact-service-security-trait.php' );
$contact_settings = read_contract_file( $root, 'plugin/gloskin-site-core/includes/gloskin-site-core-contact-service-settings-trait.php' );
$contact_settings_actions = read_contract_file( $root, 'plugin/gloskin-site-core/includes/gloskin-site-core-contact-admin-settings-actions-trait.php' );
$doctor_state = read_contract_file( $root, 'plugin/gloskin-site-core/includes/gloskin-site-core-doctor-importer-state-trait.php' );
$doctor_upsert = read_contract_file( $root, 'plugin/gloskin-site-core/includes/gloskin-site-core-doctor-importer-upsert-trait.php' );
$doctor_finalize = read_contract_file( $root, 'plugin/gloskin-site-core/includes/gloskin-site-core-doctor-importer-finalize-trait.php' );
$doctor_raw = read_contract_file( $root, 'migration-source/gloskin-doctors-v1/doctors.json' );
$doctor_json = json_decode( $doctor_raw, true );
$doctor_manifest = json_decode( read_contract_file( $root, 'migration-source/gloskin-doctors-v1/manifest.json' ), true );

/* Shop: one active state/request owner, no browser primitive interception. */
require_contract( false !== strpos( $shop_template, 'data-gloskin-shop-catalog-owner' ), 'Shop canonical owner marker missing' );
require_contract( 0 === preg_match( '/\sdata-gloskin-shop-catalog(?:\s|>)/', $shop_template ), 'legacy core Shop root marker must be inactive' );
require_contract( false !== strpos( $shop_owner, "document.querySelector('[data-gloskin-shop-catalog-owner]')" ), 'Shop owner must bind the dedicated marker' );
require_contract( false !== strpos( $shop_owner, "category: String(state.category || '')" ) && false !== strpos( $shop_owner, "q: String(state.q || '')" ) && false !== strpos( $shop_owner, "min_price: String(state.min_price || '')" ) && false !== strpos( $shop_owner, "max_price: String(state.max_price || '')" ), 'category/q/min/max must share canonical Shop state' );
require_contract( 1 === substr_count( $shop_owner, 'function buildShopCatalogRequestUrl(' ), 'one Shop URL builder expected' );
require_contract( 1 === substr_count( $shop_owner, 'function requestCatalog(' ), 'one Shop request owner expected' );
require_contract( 1 === substr_count( $shop_owner, 'return window.fetch(' ), 'one logical Shop fetch path expected' );
require_contract( false !== strpos( $shop_owner, "'shop/catalog?'" ), 'Shop must retain existing catalog endpoint' );
require_contract( false !== strpos( $shop_owner, 'new window.AbortController()' ) && false !== strpos( $shop_owner, 'sequence !== requestSequence' ), 'Shop AbortController/stale-response guard missing' );
require_contract( false !== strpos( $shop_owner, 'currentState = nextState;' ), 'Shop canonical state must transfer before request' );
require_contract( false !== strpos( $shop_owner, 'nextState.page = 1;' ), 'Shop filter changes must reset page 1' );
require_contract( false !== strpos( $shop_owner, 'nextPageState = normalizeShopCatalogState(currentState, 1);' ), 'pagination must preserve current filters' );
require_contract( false !== strpos( $shop_owner, "window.addEventListener('popstate'" ) && false !== strpos( $shop_owner, 'syncControls(state);' ), 'popstate must restore controls/state' );
require_contract( ! preg_match( '/window\.fetch\s*=(?!=)/', $shop_owner . $core ), 'window.fetch monkeypatch forbidden' );
require_contract( ! preg_match( '/(?:window\.)?history\.pushState\s*=(?!=)/', $shop_owner . $core ), 'pushState monkeypatch forbidden' );
require_contract( ! preg_match( '/(?:window\.)?history\.replaceState\s*=(?!=)/', $shop_owner . $core ), 'replaceState monkeypatch forbidden' );
require_contract( false === strpos( $shop_owner, 'originalFetch' ) && false === strpos( $shop_owner, 'originalPushState' ) && false === strpos( $shop_owner, 'originalReplaceState' ), 'legacy Shop decorator code must be retired' );
require_contract( false !== strpos( $route, 'gloskin-ui1-shop-discovery.js' ) && false !== strpos( $route, 'gloskin-ui1-shop-discovery.css' ), 'Shop owner/CSS assets must stay scoped to Shop' );
require_contract( false !== strpos( $query, 'products_paginated( $page, self::PER_PAGE, $category )' ), 'historical unfiltered Woo fallback must remain' );
require_contract( false !== strpos( $query, "'posts_per_page' => self::PER_PAGE" ), 'filtered query must stay bounded' );
require_contract( false === strpos( $query, "'posts_per_page' => -1" ), 'q/price must not add another all-product scan' );
require_contract( false === strpos( $query, "add_action( 'pre_get_posts'" ) && false === strpos( $query, "add_filter( 'pre_get_posts'" ), 'global pre_get_posts owner forbidden' );
require_contract( false !== strpos( $query, 'gloskin_price_lookup.max_price >= %f' ) && false !== strpos( $query, 'gloskin_price_lookup.min_price <= %f' ), 'Woo-compatible variable price overlap semantics missing' );

/* Bootstrap: main -> Kernel -> production module, with duplicate boot guarded. */
require_contract( 1 === substr_count( $main, '$gloskin_site_core_kernel->boot();' ), 'main must boot Kernel exactly once' );
require_contract( false === strpos( $main, 'Gloskin_Site_Core_Production_Batch' ), 'main must not boot Production_Batch independently' );
require_contract( 1 === substr_count( $kernel, 'Gloskin_Site_Core_Production_Batch::boot( $this->plugin_file );' ), 'Kernel must own Production_Batch boot' );
require_contract( false !== strpos( $kernel, 'private function boot_production_batch()' ), 'Kernel production bridge missing' );
require_contract( false !== strpos( $batch, 'private static $booted = false;' ) && false !== strpos( $batch, 'if ( self::$booted )' ) && false !== strpos( $batch, 'self::$booted = true;' ), 'Production_Batch duplicate-registration guard missing' );
require_contract( false !== strpos( $kernel, 'Gloskin_Site_Core_Insight_Migration_Admin' ), 'existing Insights admin registration must remain in Kernel' );

/* Contact: persist first, scoped transport, secret/token safety unchanged. */
$persist = strpos( $contact, '$post_id = $this->persist_message( $payload );' );
$mail = strpos( $contact, '$staff_result = $this->send_staff_mail' );
require_contract( false !== $persist && false !== $mail && $persist < $mail, 'Contact persistence must precede staff mail' );
require_contract( false !== strpos( $contact, "unset( \$raw['recaptcha'] )" ) && false !== strpos( $contact, "unset( \$_POST['g-recaptcha-response'] )" ), 'reCAPTCHA token must be dropped before persistence' );
require_contract( false !== strpos( $contact_settings, "'smtp_scope'" ) && false !== strpos( $contact_settings, "'gloskin_contact'" ), 'Contact-only SMTP must remain default scope' );
require_contract( false !== strpos( $mailer, "'site_wide' !==" ) && false !== strpos( $mailer, "'gloskin_contact'" ), 'site-wide SMTP must remain explicit opt-in' );
require_contract( false !== strpos( $mailer, '$temporary = ! $this->site_wide_registered' ) && false !== strpos( $mailer, "remove_action( 'phpmailer_init'" ), 'Contact temporary SMTP hooks must stay bounded' );
require_contract( false !== strpos( $mailer, "'Reply-To: ' . \$reply_to" ), 'visitor Reply-To must remain' );
require_contract( false !== strpos( $mailer, "defined( 'GLOSKIN_SMTP_PASSWORD' )" ) && false !== strpos( $contact_settings, "defined( 'GLOSKIN_RECAPTCHA_SECRET_KEY' )" ), 'external secret constants must remain supported' );
require_contract( false !== strpos( $contact_settings_actions, "isset( \$current['smtp_password'] )" ) && false !== strpos( $contact_settings_actions, "'' !== (string) wp_unslash( \$_POST['smtp_password'] )" ), 'blank SMTP password save must preserve current secret' );
require_contract( false !== strpos( $contact_settings_actions, "isset( \$current['recaptcha_secret_key'] )" ) && false !== strpos( $contact_settings_actions, "'' !== (string) wp_unslash( \$_POST['recaptcha_secret_key'] )" ), 'blank reCAPTCHA secret save must preserve current secret' );
require_contract( false !== strpos( $contact_security, 'https://www.google.com/recaptcha/api/siteverify' ) && false !== strpos( $contact_security, '! self::recaptcha_ready()' ), 'reCAPTCHA v2 verification/fail-closed config must remain' );
require_contract( false === strpos( $core . $main, 'GLOSKIN_SMTP_PASSWORD' ) && false === strpos( $core . $main, 'GLOSKIN_RECAPTCHA_SECRET_KEY' ), 'Contact secrets must not enter public JS/plugin bootstrap output' );
foreach ( array( $contact, $contact_security ) as $contact_security_source ) {
	require_contract( false === strpos( $contact_security_source, 'error_log(' ) && false === strpos( $contact_security_source, 'var_dump(' ) && false === strpos( $contact_security_source, 'print_r(' ), 'Contact token/security path must not log raw values' );
}

/* Doctor migration: exact conservative payload and safety lifecycle unchanged. */
require_contract( is_array( $doctor_json ) && isset( $doctor_json['doctors'] ) && 13 === count( $doctor_json['doctors'] ), 'doctor payload must remain 13 records' );
require_contract( is_array( $doctor_manifest ) && 13 === (int) $doctor_manifest['expected_doctors'], 'doctor manifest expected count must remain 13' );
require_contract( strlen( $doctor_raw ) === (int) $doctor_manifest['files']['doctors.json']['bytes'], 'doctor manifest byte count mismatch' );
require_contract( hash( 'sha256', $doctor_raw ) === (string) $doctor_manifest['files']['doctors.json']['sha256'], 'doctor manifest checksum mismatch' );
$forbidden = array( 'sip', 'schedule', 'clinic', 'clinic_slug', 'specialization', 'portrait', 'image', 'photo' );
foreach ( $doctor_json['doctors'] as $doctor ) {
	foreach ( $forbidden as $field ) { require_contract( ! array_key_exists( $field, $doctor ), 'invented doctor field forbidden: ' . $field ); }
}
require_contract( false !== strpos( $doctor_state, '$this->acquire_lock()' ) && (bool) preg_match( "/\\\\?\\\$state\['index'\]\\s*=\\s*\\\\?\\\$index\\s*\\+\\s*1/", $doctor_state ), 'doctor lock/checkpoint semantics must remain' );
require_contract( false !== strpos( $doctor_upsert, 'self::SOURCE_META' ) && false !== strpos( $doctor_upsert, 'Unowned doctor collision' ), 'doctor idempotent source ownership/collision guard must remain' );
$consumed = strpos( $doctor_finalize, "\$state['status'] = 'consumed'" );
$cleanup = strpos( $doctor_finalize, '$this->cleanup_runtime' );
require_contract( false !== $consumed && false !== $cleanup && $consumed < $cleanup, 'doctor consumed state must precede cleanup' );
require_contract( false !== strpos( $doctor_finalize, "array( 'doctors.json', 'manifest.json' )" ), 'doctor cleanup must remain manifest-confined' );

echo "shop-contact-doctor-production-contract.php: OK\n";
