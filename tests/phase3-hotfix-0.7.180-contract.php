<?php
/**
 * Phase-3 Hotfix 0.7.180 Regression Contract
 *
 * Proves all three recovery hardening fixes introduced in 0.7.180:
 *
 *   Fix A — current_step_key persisted by advance() on step start.
 *            response_state() preserves the persisted key (no index-arithmetic overwrite).
 *            Result: partial media advance always returns current_step_key='media_reconcile'.
 *
 *   Fix B — ensure_attachment_metadata() fail-closed: throws RuntimeException instead of
 *            silently returning on unreadable file or failed metadata generation.
 *            Applied to SHA-reuse, partial recovery, and newly imported attachments.
 *            Result: cursor stays on same asset; no duplicate attachments created.
 *
 *   Fix C — load_enrichment_prices() validates and throws when file is missing, JSON-invalid,
 *            coverage < 25 skincare + 48 treatment, or any price is non-numeric / ≤ 0.
 *            reconcile_woo_product() preserves legitimate existing price > 0 and throws
 *            when enrichment is the only path to a valid price but is unavailable.
 *
 * Also verifies:
 *   — 48/8/4/18 final set verifier in run_verify() intact
 *   — 77ee authoritative manifests byte-identical (unchanged)
 *   — Phase-3 packaged asset directory still present
 *   — Version bumped to 0.7.180
 *
 * Exit 0 = all assertions green.
 * Exit 1 = at least one assertion failed (details on STDERR).
 */
declare( strict_types=1 );

$root       = dirname( __DIR__ );
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

$migration = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-phase3-migration.php' );
$admin     = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-phase3-migration-admin.php' );
$plugin    = (string) file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );
$kernel    = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );

$ok( '' !== $migration, 'migration class must be readable' );
$ok( '' !== $admin,     'admin class must be readable' );
$ok( '' !== $plugin,    'plugin header must be readable' );
$ok( '' !== $kernel,    'kernel must be readable' );

/* ---------------------------------------------------------------------------
 * 0. Version bumped to 0.7.180
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $plugin, 'Version: 0.7.180' ),
	'Plugin header Version must be 0.7.180'
);
$ok(
	false !== strpos( $kernel, "const VERSION = '0.7.180';" ),
	'Kernel const VERSION must be 0.7.180'
);

/* ---------------------------------------------------------------------------
 * Fix A — current_step_key persisted by advance() on step start
 * ------------------------------------------------------------------------- */

/* 1. advance() persists current_step_key at step start (before save_state). */
$advance_start = strpos( $migration, 'public function advance(' );
$advance_end   = false;
if ( false !== $advance_start ) {
	$depth = 0;
	$pos   = strpos( $migration, '{', $advance_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$advance_end = $i;
	}
}
$advance_body = ( false !== $advance_start && false !== $advance_end )
	? substr( $migration, $advance_start, $advance_end - $advance_start )
	: '';

$ok( '' !== $advance_body, 'Fix A: advance() body must be locatable' );

/* The assignment must appear in the step-dispatch block (before the switch). */
$ok(
	false !== strpos( $advance_body, "\$state['current_step_key'] = \$step_key;" ),
	"Fix A: advance() must set \$state['current_step_key'] = \$step_key before dispatching the step"
);

/* The assignment must precede the switch dispatch in advance().
 * (save_state appears multiple times; use the switch as the natural anchor.) */
$key_assign_pos  = strpos( $advance_body, "\$state['current_step_key'] = \$step_key;" );
$switch_pos      = strpos( $advance_body, 'switch ( $step_key )' );
$ok(
	false !== $key_assign_pos && false !== $switch_pos && $key_assign_pos < $switch_pos,
	'Fix A: current_step_key assignment must precede switch ( $step_key ) dispatch in advance()'
);

/* 2. response_state() does NOT unconditionally overwrite the persisted key via index-1 arithmetic. */
$response_start = strpos( $migration, 'private function response_state(' );
$response_end   = false;
if ( false !== $response_start ) {
	$depth = 0;
	$pos   = strpos( $migration, '{', $response_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$response_end = $i;
	}
}
$response_body = ( false !== $response_start && false !== $response_end )
	? substr( $migration, $response_start, $response_end - $response_start )
	: '';

$ok( '' !== $response_body, 'Fix A: response_state() body must be locatable' );

/* response_state() still sets current_step_key in state (required by contract). */
$ok(
	false !== strpos( $response_body, "\$state['current_step_key']" ),
	"Fix A: response_state() must still assign \$state['current_step_key']"
);

/* The assignment in response_state() must be guarded by an emptiness check
 * (not unconditional arithmetic derivation). */
$ok(
	false !== strpos( $response_body, "'' === (string) ( \$state['current_step_key'] ?? '' )" ),
	"Fix A: response_state() must guard the fallback with an emptiness check to preserve persisted key"
);

/* 3. Admin JS uses stepKey for media row — stable machine key, not label. */
$ok(
	false !== strpos( $admin, "stepKey === 'media_reconcile'" ),
	"Fix A: admin JS must gate media row on stepKey === 'media_reconcile'"
);
$ok(
	false !== strpos( $admin, 'data.current_step_key' ),
	'Fix A: admin JS must read current_step_key from response data'
);

/* 4. Admin PHP media row uses current_step_key for initial visibility. */
$ok(
	false !== strpos( $admin, 'current_step_key' ),
	'Fix A: admin PHP must reference current_step_key for media row visibility'
);

/* ---------------------------------------------------------------------------
 * Fix B — ensure_attachment_metadata() fail-closed
 * ------------------------------------------------------------------------- */

/* 5. ensure_attachment_metadata() exists. */
$ok(
	false !== strpos( $migration, 'private function ensure_attachment_metadata(' ),
	'Fix B: ensure_attachment_metadata() helper must exist'
);

/* Locate the method body. */
$ema_start = strpos( $migration, 'private function ensure_attachment_metadata(' );
$ema_end   = false;
if ( false !== $ema_start ) {
	$depth = 0;
	$pos   = strpos( $migration, '{', $ema_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$ema_end = $i;
	}
}
$ema_body = ( false !== $ema_start && false !== $ema_end )
	? substr( $migration, $ema_start, $ema_end - $ema_start )
	: '';

$ok( '' !== $ema_body, 'Fix B: ensure_attachment_metadata() body must be locatable' );

/* The method must throw on unreadable file (no silent return). */
$ok(
	false !== strpos( $ema_body, 'throw new RuntimeException' ),
	'Fix B: ensure_attachment_metadata() must throw RuntimeException (not silently return on failure)'
);

/* Must not have a bare "return;" after the file-not-readable check (the silent-success bug). */
$file_check_pos  = strpos( $ema_body, '! is_readable( $file )' );
$next_return_pos = false !== $file_check_pos ? strpos( $ema_body, 'return;', $file_check_pos ) : false;
$next_throw_pos  = false !== $file_check_pos ? strpos( $ema_body, 'throw new RuntimeException', $file_check_pos ) : false;
$ok(
	false === $next_return_pos
		|| ( false !== $next_throw_pos && $next_throw_pos < $next_return_pos ),
	'Fix B: ensure_attachment_metadata() must throw (not bare return) when file is not readable'
);

/* Must throw when wp_generate_attachment_metadata returns empty. */
$ok(
	false !== strpos( $ema_body, "! \$new_meta || empty( \$new_meta['file'] )" ),
	"Fix B: ensure_attachment_metadata() must throw when wp_generate_attachment_metadata returns empty/no 'file'"
);

/* Must check metadata is usable before accepting (not just checking $metadata). */
$ok(
	false !== strpos( $ema_body, "! empty( \$metadata['file'] )" ),
	"Fix B: ensure_attachment_metadata() must verify metadata['file'] exists, not just metadata"
);

/* 6. ensure_attachment_metadata() called from SHA-reuse path (cursor not incremented before call). */
$step_start = strpos( $migration, 'private function run_media_reconcile_step(' );
$step_end   = false;
if ( false !== $step_start ) {
	$depth = 0;
	$pos   = strpos( $migration, '{', $step_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$step_end = $i;
	}
}
$step_body = ( false !== $step_start && false !== $step_end )
	? substr( $migration, $step_start, $step_end - $step_start )
	: '';

$sha_reuse_pos  = strpos( $step_body, 'find_attachment_by_sha( $sha )' );
$ensure_after_sha = strpos( $step_body, 'ensure_attachment_metadata( $existing_id )' );
$ok(
	false !== $sha_reuse_pos && false !== $ensure_after_sha && $ensure_after_sha > $sha_reuse_pos,
	'Fix B: ensure_attachment_metadata() must be called after SHA-dedup in run_media_reconcile_step()'
);

/* cursor++ must follow ensure_attachment_metadata() in the SHA-reuse block.
 * (The PSD-skip block has an earlier $cursor++ that is intentionally before ensure — that is
 * correct because PSD assets are never ensured.  Search from ensure_after_sha onwards.) */
$cursor_after_ensure = false !== $ensure_after_sha
	? strpos( $step_body, '$cursor++', $ensure_after_sha )
	: false;
$ok(
	false !== $ensure_after_sha && false !== $cursor_after_ensure,
	'Fix B: $cursor++ must follow ensure_attachment_metadata( $existing_id ) in SHA-reuse path'
);

/* 7. ensure_attachment_metadata() called from recover_partial_attachment(). */
$ok(
	false !== strpos( $migration, 'private function recover_partial_attachment(' ),
	'Fix B: recover_partial_attachment() must exist'
);
$rec_start = strpos( $migration, 'private function recover_partial_attachment(' );
$rec_end   = false;
if ( false !== $rec_start ) {
	$depth = 0;
	$pos   = strpos( $migration, '{', $rec_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$rec_end = $i;
	}
}
$rec_body = ( false !== $rec_start && false !== $rec_end )
	? substr( $migration, $rec_start, $rec_end - $rec_start )
	: '';

$ok(
	false !== strpos( $rec_body, 'ensure_attachment_metadata(' ),
	'Fix B: ensure_attachment_metadata() must be called in recover_partial_attachment()'
);

/* 8. ensure_attachment_metadata() called from import_local_asset() — newly imported. */
$import_start = strpos( $migration, 'private function import_local_asset(' );
$import_end   = false;
if ( false !== $import_start ) {
	$depth = 0;
	$pos   = strpos( $migration, '{', $import_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$import_end = $i;
	}
}
$import_body = ( false !== $import_start && false !== $import_end )
	? substr( $migration, $import_start, $import_end - $import_start )
	: '';

$ok(
	false !== strpos( $import_body, 'ensure_attachment_metadata( $attach_id )' ),
	'Fix B: ensure_attachment_metadata() must be called in import_local_asset() for newly imported attachments'
);

/* Provenance must still be written BEFORE metadata generation. */
$provenance_pos = strpos( $import_body, 'ATTACH_SHA256_META' );
$meta_gen_pos   = strpos( $import_body, 'wp_generate_attachment_metadata(' );
$ok(
	false !== $provenance_pos && false !== $meta_gen_pos && $provenance_pos < $meta_gen_pos,
	'Fix B: provenance (ATTACH_SHA256_META) must still be written before wp_generate_attachment_metadata() in import_local_asset()'
);

/* ensure_attachment_metadata() must come AFTER wp_generate_attachment_metadata() (verify after store). */
$ensure_import_pos = strpos( $import_body, 'ensure_attachment_metadata( $attach_id )' );
$ok(
	false !== $ensure_import_pos && false !== $meta_gen_pos && $ensure_import_pos > $meta_gen_pos,
	'Fix B: ensure_attachment_metadata() must come after wp_generate_attachment_metadata() in import_local_asset()'
);

/* Total ensure_attachment_metadata() calls: SHA-reuse + recovery + import = at least 3. */
$ok(
	substr_count( $migration, 'ensure_attachment_metadata(' ) >= 3,
	'Fix B: ensure_attachment_metadata() must be used in at least 3 paths (SHA-reuse, recovery, import)'
);

/* ---------------------------------------------------------------------------
 * Fix C — load_enrichment_prices() validates and throws; reconcile preserves/enforces
 * ------------------------------------------------------------------------- */

/* 9. load_enrichment_prices() throws on unreadable file (not empty-array return). */
$lep_start = strpos( $migration, 'private function load_enrichment_prices()' );
$lep_end   = false;
if ( false !== $lep_start ) {
	$depth = 0;
	$pos   = strpos( $migration, '{', $lep_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$lep_end = $i;
	}
}
$lep_body = ( false !== $lep_start && false !== $lep_end )
	? substr( $migration, $lep_start, $lep_end - $lep_start )
	: '';

$ok( '' !== $lep_body, 'Fix C: load_enrichment_prices() body must be locatable' );

$ok(
	false !== strpos( $lep_body, 'throw new RuntimeException' ),
	'Fix C: load_enrichment_prices() must throw RuntimeException (not return empty array on failure)'
);

/* Must not have "return $cache" before a throw on the unreadable-file path. */
$unreadable_pos   = strpos( $lep_body, '! is_readable( $path )' );
$early_return_pos = false !== $unreadable_pos ? strpos( $lep_body, 'return $cache', $unreadable_pos ) : false;
$throw_pos_lep    = false !== $unreadable_pos ? strpos( $lep_body, 'throw new RuntimeException', $unreadable_pos ) : false;
$ok(
	false === $early_return_pos || ( false !== $throw_pos_lep && $throw_pos_lep < $early_return_pos ),
	'Fix C: load_enrichment_prices() must throw (not return empty) when enrichment file is not readable'
);

/* 10. Validates JSON. */
$ok(
	false !== strpos( $lep_body, 'throw new RuntimeException' ) && false !== strpos( $lep_body, 'JSON tidak valid' ),
	'Fix C: load_enrichment_prices() must throw on invalid JSON'
);

/* 11. Validates coverage: 25 skincare + 48 treatment. */
$ok(
	false !== strpos( $lep_body, '$sk_count < 25' ),
	'Fix C: load_enrichment_prices() must validate at least 25 skincare entries'
);
$ok(
	false !== strpos( $lep_body, '$tr_count < 48' ),
	'Fix C: load_enrichment_prices() must validate at least 48 treatment entries'
);

/* 12. Validates all prices numeric > 0; collects invalid entries rather than silently skipping. */
$ok(
	false !== strpos( $lep_body, '$invalid' ) && false !== strpos( $lep_body, '(float) $price <= 0' ),
	'Fix C: load_enrichment_prices() must detect invalid prices (non-numeric or ≤ 0)'
);
$ok(
	false !== strpos( $lep_body, '! empty( $invalid )' ),
	'Fix C: load_enrichment_prices() must throw when any invalid price found'
);

/* 13. reconcile_woo_product() preserves legitimate existing price > 0. */
$rwp_start = strpos( $migration, 'private function reconcile_woo_product(' );
$rwp_end   = false;
if ( false !== $rwp_start ) {
	$depth = 0;
	$pos   = strpos( $migration, '{', $rwp_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$rwp_end = $i;
	}
}
$rwp_body = ( false !== $rwp_start && false !== $rwp_end )
	? substr( $migration, $rwp_start, $rwp_end - $rwp_start )
	: '';

$ok( '' !== $rwp_body, 'Fix C: reconcile_woo_product() body must be locatable' );

$ok(
	false !== strpos( $rwp_body, '$has_legit_price' ),
	'Fix C: reconcile_woo_product() must declare $has_legit_price to detect legitimate existing price'
);
$ok(
	false !== strpos( $rwp_body, '(float) $current_price > 0' ),
	'Fix C: reconcile_woo_product() must check (float) $current_price > 0 for legitimate price'
);

/* 14. reconcile_woo_product() throws when no legitimate price AND no enrichment price. */
$ok(
	false !== strpos( $rwp_body, 'throw new RuntimeException' ),
	'Fix C: reconcile_woo_product() must throw RuntimeException when valid price is unavailable'
);
$ok(
	false !== strpos( $rwp_body, 'Harga enrichment wajib tidak tersedia' ),
	'Fix C: reconcile_woo_product() throw message must indicate enrichment price unavailable'
);

/* 15. New products: throw before saving if no enrichment price; status 'draft' still present (fallback code). */
$ok(
	false !== strpos( $migration, "'draft'" ),
	"Fix C: 'draft' status fallback string must remain in migration source (preserved from 77ee contract)"
);

/* 16. No SKU or stock fabrication. */
$ok(
	false === strpos( $migration, 'set_sku(' ),
	'Fix C: migration must not fabricate SKU (no set_sku call)'
);
$ok(
	false === strpos( $migration, 'set_stock_quantity(' ),
	'Fix C: migration must not fabricate stock quantity (no set_stock_quantity call)'
);

/* ---------------------------------------------------------------------------
 * Final 48/8/4/18 verifier intact in run_verify()
 * ------------------------------------------------------------------------- */
$verify_start = strpos( $migration, 'private function run_verify(' );
$verify_end   = false;
if ( false !== $verify_start ) {
	$depth = 0;
	$pos   = strpos( $migration, '{', $verify_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$verify_end = $i;
	}
}
$verify_body = ( false !== $verify_start && false !== $verify_end )
	? substr( $migration, $verify_start, $verify_end - $verify_start )
	: '';

$ok( '' !== $verify_body, 'Verifier: run_verify() body must be locatable' );

$ok(
	false !== strpos( $verify_body, '48 !== count( $live_tr_slugs )' ),
	'Verifier: run_verify() must check exact count of 48 non-trashed Treatment Woo products'
);
$ok(
	false !== strpos( $verify_body, '8 !== count( $live_cpt_slugs )' ),
	'Verifier: run_verify() must check exact count of 8 non-trashed gloskin_treatment records'
);
$ok(
	false !== strpos( $verify_body, '4 !== count( $live_path_slugs )' ),
	'Verifier: run_verify() must check exact count of 4 consultation path terms'
);
$ok(
	false !== strpos( $verify_body, '18 !== count( $live_concern_slugs )' ),
	'Verifier: run_verify() must check exact count of 18 concern terms'
);

/* ---------------------------------------------------------------------------
 * 77ee manifests byte-identical (not modified)
 * ------------------------------------------------------------------------- */
$manifest_names = array(
	'migration-manifest.json',
	'skincare-products.json',
	'treatment-catalog.json',
	'treatment-page-media.json',
	'unresolved.json',
);
$manifests_unchanged = true;
foreach ( $manifest_names as $mname ) {
	$source   = $root . '/docs/client-feedback-phase-3/manifests/' . $mname;
	$packaged = $root . '/plugin/gloskin-site-core/resources/phase3/manifests/' . $mname;
	if ( ! is_file( $source ) || ! is_file( $packaged ) ) {
		$manifests_unchanged = false;
		break;
	}
	if ( hash_file( 'sha256', $source ) !== hash_file( 'sha256', $packaged ) ) {
		$manifests_unchanged = false;
		break;
	}
}
$ok( $manifests_unchanged, '77ee manifests: source and packaged copies must remain byte-identical' );

/* ---------------------------------------------------------------------------
 * Phase-3 packaged asset directory present
 * ------------------------------------------------------------------------- */
$ok(
	is_dir( $root . '/plugin/gloskin-site-core/resources/phase3/assets' ),
	'Phase-3 asset binaries: packaged assets directory must still exist'
);

/* ---------------------------------------------------------------------------
 * Report
 * ------------------------------------------------------------------------- */
if ( $fail_count > 0 ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, $failure . "\n" );
	}
	fwrite( STDERR, "\nphase3-hotfix-0.7.180-contract.php: {$fail_count} assertion(s) FAILED, {$ok_count} passed\n" );
	exit( 1 );
}

echo "phase3-hotfix-0.7.180-contract.php: OK ({$ok_count} assertions)\n";
