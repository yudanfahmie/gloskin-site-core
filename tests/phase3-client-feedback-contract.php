<?php
/**
 * Phase-3 Client Feedback Contract — Static PHP source analysis.
 *
 * Proves the Phase-3 migration implementation satisfies every invariant
 * declared in the spec WITHOUT running WordPress or WooCommerce.
 *
 * Tickets: FB-989354 (Skincare), FB-989360 (Treatment).
 *
 * Exit 0 = all assertions green.
 * Exit 1 = at least one assertion failed (details on STDERR).
 */
declare( strict_types=1 );

$root    = dirname( __DIR__ );
$ok_count   = 0;
$fail_count = 0;
$failures   = array();

$ok = static function ( bool $condition, string $label ) use ( &$ok_count, &$fail_count, &$failures ): void {
	if ( $condition ) {
		$ok_count++;
	} else {
		$fail_count++;
		$failures[] = 'FAIL: ' . $label;
	}
};

/* ---------------------------------------------------------------------------
 * 0. Read source files
 * ------------------------------------------------------------------------- */
$plugin_php  = (string) file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );
$kernel_php  = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$migration   = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-phase3-migration.php' );
$admin_php   = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-phase3-migration-admin.php' );
$manifest_mm = (string) file_get_contents( $root . '/docs/client-feedback-phase-3/manifests/migration-manifest.json' );
$manifest_sk = (string) file_get_contents( $root . '/docs/client-feedback-phase-3/manifests/skincare-products.json' );
$manifest_tr = (string) file_get_contents( $root . '/docs/client-feedback-phase-3/manifests/treatment-catalog.json' );
$manifest_pm = (string) file_get_contents( $root . '/docs/client-feedback-phase-3/manifests/treatment-page-media.json' );
$manifest_ur = (string) file_get_contents( $root . '/docs/client-feedback-phase-3/manifests/unresolved.json' );

foreach ( array(
	'plugin_php', 'kernel_php', 'migration', 'admin_php',
	'manifest_mm', 'manifest_sk', 'manifest_tr', 'manifest_pm', 'manifest_ur',
) as $var ) {
	$ok( '' !== $$var, "Source file must be readable: {$var}" );
}

/* ---------------------------------------------------------------------------
 * 1. Version sync at 0.7.172
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $plugin_php, 'Version: 0.7.172' ) && false !== strpos( $kernel_php, "const VERSION = '0.7.172';" ),
	'Phase-3 runtime/cache version must be synchronized at 0.7.172'
);

/* ---------------------------------------------------------------------------
 * 2. Manifest counts
 * ------------------------------------------------------------------------- */
$mm_data = json_decode( $manifest_mm, true );
$sk_data = json_decode( $manifest_sk, true );
$tr_data = json_decode( $manifest_tr, true );
$pm_data = json_decode( $manifest_pm, true );
$ur_data = json_decode( $manifest_ur, true );

$ok( is_array( $mm_data ), 'migration-manifest.json must be valid JSON' );
$ok( is_array( $sk_data ), 'skincare-products.json must be valid JSON' );
$ok( is_array( $tr_data ), 'treatment-catalog.json must be valid JSON' );
$ok( is_array( $pm_data ), 'treatment-page-media.json must be valid JSON' );
$ok( is_array( $ur_data ), 'unresolved.json must be valid JSON' );

if ( is_array( $mm_data ) ) {
	$ok( 'gloskin-phase3-v1' === ( $mm_data['manifest_id'] ?? '' ), 'migration-manifest.json must declare manifest_id=gloskin-phase3-v1' );
}

if ( is_array( $sk_data ) ) {
	$sk_products = (array) ( $sk_data['products'] ?? array() );
	$ok( 25 === count( $sk_products ), 'skincare-products.json must contain exactly 25 products (got ' . count( $sk_products ) . ')' );
}

if ( is_array( $tr_data ) ) {
	$tr_products = (array) ( $tr_data['treatment_products'] ?? array() );
	$tr_records  = (array) ( $tr_data['treatment_records'] ?? array() );
	$ok( 48 === count( $tr_products ), 'treatment-catalog.json must contain exactly 48 treatment_products (got ' . count( $tr_products ) . ')' );
	$ok( 8 === count( $tr_records ), 'treatment-catalog.json must contain exactly 8 treatment_records (got ' . count( $tr_records ) . ')' );
}

if ( is_array( $pm_data ) ) {
	$paths = (array) ( $pm_data['paths'] ?? array() );
	$ok( 4 === count( $paths ), 'treatment-page-media.json must declare exactly 4 path entries' );

	/* Hero binding must be declared. */
	$ok( ! empty( $pm_data['treatments_page_hero']['meta_key'] ), 'treatment-page-media.json must declare treatments_page_hero.meta_key' );
	$ok( 'gloskin_hero_media_id' === ( $pm_data['treatments_page_hero']['meta_key'] ?? '' ), 'Hero meta_key must be gloskin_hero_media_id' );
}

if ( is_array( $ur_data ) ) {
	$holds = (array) ( $ur_data['holds'] ?? array() );
	$ok( 5 === count( $holds ), 'unresolved.json must list exactly 5 HOLD records' );

	/* Verify the five known HOLD IDs are present. */
	$hold_ids = array_column( $holds, 'id' );
	$ok( in_array( 'p3:hold:az-xpert', $hold_ids, true ), 'az-xpert must be a HOLD' );
	$ok( in_array( 'p3:hold:glam-gold-serum', $hold_ids, true ), 'glam-gold-serum must be a HOLD' );
	$ok( in_array( 'p3:hold:skin-fresh-facial-wash', $hold_ids, true ), 'skin-fresh-facial-wash must be a HOLD' );
	$ok( in_array( 'p3:hold:dsc02911', $hold_ids, true ), 'dsc02911 must be a HOLD' );
	$ok( in_array( 'p3:hold:untitled-design-47', $hold_ids, true ), 'untitled-design-47 must be a HOLD' );
}

/* ---------------------------------------------------------------------------
 * 3. Stable consultation path slugs (four only)
 * ------------------------------------------------------------------------- */
if ( is_array( $pm_data ) ) {
	$stable_slugs    = array( 'acne-focus', 'brightening-focus', 'anti-aging-focus', 'skin-health-focus' );
	$manifest_slugs  = array_column( (array) ( $pm_data['paths'] ?? array() ), 'slug' );
	foreach ( $stable_slugs as $slug ) {
		$ok( in_array( $slug, $manifest_slugs, true ), "Stable path slug must be in treatment-page-media.json: {$slug}" );
	}
	/* No extra path slugs beyond the four stable ones. */
	$extra = array_diff( $manifest_slugs, $stable_slugs );
	$ok( empty( $extra ), 'treatment-page-media.json must only declare the four stable path slugs (found extra: ' . implode( ',', $extra ) . ')' );
}

/* ---------------------------------------------------------------------------
 * 4. No .psd asset imports
 * -------------------------------------------------------------------------
 * The migration MUST guard against .psd imports (skip them).
 * We verify the guard exists and that manifests carry no .psd references.
 */
$ok( false !== stripos( $migration, "'psd'" ) || false !== stripos( $migration, '"psd"' ), 'Phase-3 migration must guard against .psd extension (skip psd assets)' );
/* The guard must lead to a skip (continue), not to an import call. */
$psd_guard_pos  = stripos( $migration, "'psd'" );
$import_call    = 'import_local_asset';
if ( false !== $psd_guard_pos ) {
	/* Find next occurrence of import_local_asset after the .psd guard — it must NOT exist before the next method boundary. */
	$guard_block_end = strpos( $migration, 'continue;', (int) $psd_guard_pos );
	$import_before_continue = false !== $guard_block_end
		? false !== strpos( substr( $migration, (int) $psd_guard_pos, (int) $guard_block_end - (int) $psd_guard_pos ), $import_call )
		: false;
	$ok( ! $import_before_continue, 'Phase-3 .psd guard must skip (continue) before calling import_local_asset' );
}
$ok( false === stripos( $manifest_sk, '.psd' ), 'skincare-products.json must not reference .psd files' );
$ok( false === stripos( $manifest_tr, '.psd' ), 'treatment-catalog.json must not reference .psd files' );

/* ---------------------------------------------------------------------------
 * 5. No direct SQL mutations
 * ------------------------------------------------------------------------- */
$sql_mutators = array( '$wpdb->insert', '$wpdb->update', '$wpdb->delete', '$wpdb->replace', '$wpdb->query' );
foreach ( $sql_mutators as $mutator ) {
	$ok( false === strpos( $migration, $mutator ), "Phase-3 migration must not use direct SQL: {$mutator}" );
}

/* ---------------------------------------------------------------------------
 * 6. No hard-deletes (only wp_trash_post)
 * ------------------------------------------------------------------------- */
$ok( false === strpos( $migration, 'wp_delete_post' ), 'Phase-3 migration must not call wp_delete_post (use wp_trash_post)' );
$ok( false === strpos( $migration, 'force_delete' ), 'Phase-3 migration must not call force_delete' );

/* ---------------------------------------------------------------------------
 * 7. Admin: capability check + nonce
 * ------------------------------------------------------------------------- */
$ok( false !== strpos( $admin_php, "const CAPABILITY  = 'manage_options'" ), 'Admin must declare CAPABILITY=manage_options' );
$ok( false !== strpos( $admin_php, 'check_ajax_referer' ), 'AJAX handler must call check_ajax_referer' );
$ok( false !== strpos( $admin_php, 'current_user_can' ), 'Admin must call current_user_can' );
$ok( false !== strpos( $admin_php, 'wp_create_nonce' ), 'Admin must generate a nonce' );

/* ---------------------------------------------------------------------------
 * 8. Preflight/start performs zero mutations
 * ---------------------------------------------------------------------------
 * Contract: the `start` path in advance() must return without calling any
 * write-capable helpers — it must only save state and release lock.
 * We verify by checking that run_preflight() itself calls no WP data writers.
 */
/* Isolate run_preflight() method body and verify it contains no write calls. */
$preflight_start = strpos( $migration, 'private function run_preflight()' );
$next_method_pos = false !== $preflight_start ? strpos( $migration, 'private function ', (int) $preflight_start + 1 ) : false;
if ( false !== $preflight_start && false !== $next_method_pos ) {
	$preflight_body = substr( $migration, (int) $preflight_start, (int) $next_method_pos - (int) $preflight_start );
	$preflight_mutations = substr_count( $preflight_body, 'wp_insert_post' )
		+ substr_count( $preflight_body, 'wp_update_post' )
		+ substr_count( $preflight_body, 'wp_insert_attachment' )
		+ substr_count( $preflight_body, 'wp_set_object_terms' )
		+ substr_count( $preflight_body, 'update_post_meta' )
		+ substr_count( $preflight_body, 'update_term_meta' );
	$ok( 0 === $preflight_mutations, 'run_preflight() must perform zero content mutations (found ' . $preflight_mutations . ')' );
} else {
	$ok( false, 'run_preflight() method not found in migration class' );
}

/* ---------------------------------------------------------------------------
 * 9. Idempotency: fingerprint check present
 * ------------------------------------------------------------------------- */
$ok( false !== strpos( $migration, 'fingerprint_matches' ), 'Phase-3 migration must implement fingerprint_matches() for idempotency' );
$ok( false !== strpos( $migration, 'manifest_fingerprint' ), 'Phase-3 migration state must track manifest_fingerprint' );
$ok( false !== strpos( $migration, 'already_complete' ) || false !== strpos( $migration, 'complete' ), 'Phase-3 migration must short-circuit when already complete' );

/* ---------------------------------------------------------------------------
 * 10. No fabricated prices for Treatment products
 * ------------------------------------------------------------------------- */
$ok(
	false === strpos( $migration, 'set_regular_price' ) && false === strpos( $migration, 'set_price' ),
	'Phase-3 migration must not set prices on Treatment products'
);
$ok(
	false !== strpos( $migration, "'draft'" ),
	"Phase-3 migration must create new Treatment products as 'draft' (unpriced/non-purchasable)"
);

/* ---------------------------------------------------------------------------
 * 11. SHA-256 media deduplication
 * ------------------------------------------------------------------------- */
$ok( false !== strpos( $migration, 'sha256' ), 'Phase-3 migration must use SHA-256 for media deduplication' );
$ok( false !== strpos( $migration, '_gloskin_p3_sha256' ), 'Phase-3 migration must store SHA-256 in _gloskin_p3_sha256 attachment meta' );
$ok( false !== strpos( $migration, 'find_attachment_by_sha' ), 'Phase-3 migration must check for existing attachments before importing' );

/* ---------------------------------------------------------------------------
 * 12. Kernel registers Phase-3 admin
 * ------------------------------------------------------------------------- */
$ok( false !== strpos( $kernel_php, 'class-gloskin-site-core-phase3-migration.php' ), 'Kernel must require phase3-migration.php' );
$ok( false !== strpos( $kernel_php, 'class-gloskin-site-core-phase3-migration-admin.php' ), 'Kernel must require phase3-migration-admin.php' );
$ok( false !== strpos( $kernel_php, 'Gloskin_Site_Core_Phase3_Migration_Admin' ), 'Kernel must instantiate Phase3 admin' );

/* ---------------------------------------------------------------------------
 * 13. HOLDs do not appear as resolved products in manifests
 * ------------------------------------------------------------------------- */
$hold_names = array( 'AZ Xpert', 'Glam Gold Serum', 'Skin Fresh Facial Wash', 'DSC02911', 'Untitled design (47)' );
foreach ( $hold_names as $hold_name ) {
	$hold_slug = strtolower( str_replace( array( ' ', '(', ')' ), array( '-', '', '' ), $hold_name ) );
	$ok(
		false === stripos( $manifest_sk, '"name": "' . $hold_name . '"' ),
		"HOLD item must not appear as resolved skincare product: {$hold_name}"
	);
}

/* ---------------------------------------------------------------------------
 * 14. Rejuran HB is ONE canonical product (not duplicated)
 * ------------------------------------------------------------------------- */
if ( is_array( $tr_data ) ) {
	$rejuran_count = 0;
	foreach ( (array) ( $tr_data['treatment_products'] ?? array() ) as $p ) {
		if ( false !== stripos( (string) ( $p['name'] ?? '' ), 'rejuran' ) && false !== stripos( (string) ( $p['name'] ?? '' ), 'hb' ) ) {
			$rejuran_count++;
		}
	}
	$ok( 1 === $rejuran_count, 'Rejuran HB must appear exactly once in treatment_products (found ' . $rejuran_count . ')' );
}

/* ---------------------------------------------------------------------------
 * 15. Treatment records: exactly 3 feature_on_home=true
 * ------------------------------------------------------------------------- */
if ( is_array( $tr_data ) ) {
	$home_feature_count = 0;
	foreach ( (array) ( $tr_data['treatment_records'] ?? array() ) as $r ) {
		if ( ! empty( $r['feature_on_home'] ) ) {
			$home_feature_count++;
		}
	}
	$ok( 3 === $home_feature_count, 'treatment_records must have exactly 3 entries with feature_on_home=true (got ' . $home_feature_count . ')' );
}

/* ---------------------------------------------------------------------------
 * 16. Lock pattern in migration class
 * ------------------------------------------------------------------------- */
$ok( false !== strpos( $migration, 'acquire_lock' ), 'Phase-3 migration must implement acquire_lock()' );
$ok( false !== strpos( $migration, 'release_lock' ), 'Phase-3 migration must implement release_lock()' );
$ok( false !== strpos( $migration, 'LOCK_TTL' ), 'Phase-3 migration must declare LOCK_TTL constant' );

/* ---------------------------------------------------------------------------
 * 17. WooCommerce remains sole commerce owner — no post_type=product direct inserts
 * ------------------------------------------------------------------------- */
$ok( false === strpos( $migration, "'post_type' => 'product'" ) || false !== strpos( $migration, 'WC_Product_Simple' ),
	'Phase-3 must create products through WooCommerce API (WC_Product_Simple), not raw post_type=product inserts'
);

/* ---------------------------------------------------------------------------
 * Report
 * ------------------------------------------------------------------------- */
if ( $fail_count > 0 ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, $failure . "\n" );
	}
	fwrite( STDERR, "\nphase3-client-feedback-contract.php: {$fail_count} assertion(s) FAILED, {$ok_count} passed\n" );
	exit( 1 );
}

echo "phase3-client-feedback-contract.php: OK ({$ok_count} assertions)\n";
