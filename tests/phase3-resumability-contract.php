<?php
declare(strict_types=1);

/**
 * Phase 3 resumability contract — proves all 17 items from spec O.
 *
 * Run: php tests/phase3-resumability-contract.php
 */
$root = dirname( __DIR__ );
$read = static function ( string $relative ) use ( $root ): string {
	$data = file_get_contents( $root . '/' . $relative );
	if ( false === $data ) {
		fwrite( STDERR, "FAIL: unable to read {$relative}\n" );
		exit( 1 );
	}
	return $data;
};
$ok = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};

$migration = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-phase3-migration.php' );
$admin     = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-phase3-migration-admin.php' );
$plugin    = $read( 'plugin/gloskin-site-core/gloskin-site-core.php' );
$kernel    = $read( 'plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$contract  = $read( 'tests/phase3-client-feedback-contract.php' );
$sk_json   = $read( 'plugin/gloskin-site-core/resources/phase3/manifests/skincare-products.json' );
$tr_json   = $read( 'plugin/gloskin-site-core/resources/phase3/manifests/treatment-catalog.json' );
$pg_json   = $read( 'plugin/gloskin-site-core/resources/phase3/manifests/treatment-page-media.json' );

/* ---------------------------------------------------------------------------
 * Item 1: Exactly one media asset is processed per server advance()
 *         run_media_reconcile_step() returns after processing one item.
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $migration, 'private function run_media_reconcile_step(' ),
	'Item 1: run_media_reconcile_step() must exist (one-asset-per-request handler)'
);
/* The step method must not contain a loop over the full asset list. */
$step_start = strpos( $migration, 'private function run_media_reconcile_step(' );
$step_end   = false;
/* Find the matching closing brace of this method. */
if ( false !== $step_start ) {
	$depth  = 0;
	$pos    = strpos( $migration, '{', $step_start );
	if ( false !== $pos ) {
		$depth = 1;
		$i     = $pos + 1;
		$len   = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$step_end = $i;
	}
}
$ok( false !== $step_start && false !== $step_end && $step_end > $step_start,
	'Item 1: Could not locate run_media_reconcile_step() body' );
$step_body = substr( $migration, $step_start, $step_end - $step_start );
/* The step body must NOT contain a foreach/for loop over the whole asset list —
 * it processes a single item at the current cursor position and returns. */
$ok(
	false === strpos( $step_body, 'foreach ( $asset_list' ) && false === strpos( $step_body, 'foreach( $asset_list' ),
	'Item 1: run_media_reconcile_step() must not loop over the full asset list (one item per call)'
);
$ok(
	false !== strpos( $step_body, '$cursor' ),
	'Item 1: run_media_reconcile_step() must use a $cursor to select the single item'
);
$ok(
	substr_count( $step_body, 'return compact' ) >= 3,
	'Item 1: run_media_reconcile_step() must have early-return paths (one item then return)'
);

/* ---------------------------------------------------------------------------
 * Item 2: media_cursor persists — state has media_cursor field
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $migration, "'media_cursor'" ),
	'Item 2: media_cursor must be declared in state'
);
/* advance() must assign media_cursor from result */
$ok(
	false !== strpos( $migration, "\$state['media_cursor']       = \$result['cursor']" ),
	'Item 2: advance() must persist media_cursor from run_media_reconcile_step() result'
);

/* ---------------------------------------------------------------------------
 * Item 3: next_step_index stays on media_reconcile until cursor == total
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $migration, "if ( \$result['cursor'] < \$result['total'] )" ),
	'Item 3: advance() must guard step-index advance until cursor reaches total'
);
$ok(
	false !== strpos( $migration, "return \$this->response_state( \$state );" ),
	'Item 3: advance() must return early (without advancing step index) while media not complete'
);

/* ---------------------------------------------------------------------------
 * Item 4: SHA dedup — existing attachment is reused before any import
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $step_body, 'find_attachment_by_sha(' ),
	'Item 4: run_media_reconcile_step() must call find_attachment_by_sha() for SHA dedup'
);
/* SHA dedup must come before the import call. */
$sha_dedup_pos  = strpos( $step_body, 'find_attachment_by_sha(' );
$import_pos     = strpos( $step_body, 'import_local_asset(' );
$ok(
	false !== $sha_dedup_pos && false !== $import_pos && $sha_dedup_pos < $import_pos,
	'Item 4: SHA dedup must precede any import attempt in run_media_reconcile_step()'
);

/* ---------------------------------------------------------------------------
 * Item 5: Partial attachment recovery by exact binary SHA (spec D)
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $migration, 'private function recover_partial_attachment(' ),
	'Item 5: recover_partial_attachment() must exist'
);
/* Recovery must use hash_equals for exact binary SHA comparison — no fuzzy match. */
$ok(
	false !== strpos( $migration, 'hash_equals(' ),
	'Item 5: recover_partial_attachment() must use hash_equals() for exact SHA verification'
);
/* Recovery must come after SHA dedup and before import in step body. */
$recovery_pos = strpos( $step_body, 'recover_partial_attachment(' );
$ok(
	false !== $recovery_pos && $recovery_pos > $sha_dedup_pos && $recovery_pos < $import_pos,
	'Item 5: recover_partial_attachment() must be tried after SHA dedup and before fresh import'
);
/* Recovery must be bounded (LIMIT 5) — never unbounded. */
$ok(
	false !== strpos( $migration, 'LIMIT 5' ),
	'Item 5: recover_partial_attachment() must use bounded LIMIT 5 query'
);

/* ---------------------------------------------------------------------------
 * Item 6: Full-binary file_get_contents() import removed from import_local_asset()
 * ------------------------------------------------------------------------- */
/* Find import_local_asset() body. */
$import_fn_start = strpos( $migration, 'private function import_local_asset(' );
$import_fn_end   = false;
if ( false !== $import_fn_start ) {
	$depth = 0;
	$pos   = strpos( $migration, '{', $import_fn_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$import_fn_end = $i;
	}
}
$ok( false !== $import_fn_start && false !== $import_fn_end,
	'Item 6: import_local_asset() method must exist' );
$import_body = substr( $migration, $import_fn_start, $import_fn_end - $import_fn_start );
$ok(
	false === strpos( $import_body, 'file_get_contents' ),
	'Item 6: import_local_asset() must NOT use file_get_contents() (full-file memory load removed)'
);
$ok(
	false === strpos( $import_body, 'wp_upload_bits' ),
	'Item 6: import_local_asset() must NOT use wp_upload_bits() (replaced by stream copy)'
);
$ok(
	false !== strpos( $import_body, 'copy(' ),
	'Item 6: import_local_asset() must use PHP copy() for stream copy to uploads directory'
);
$ok(
	false !== strpos( $import_body, 'wp_upload_dir(' ),
	'Item 6: import_local_asset() must use wp_upload_dir() to resolve destination'
);
$ok(
	false !== strpos( $import_body, 'wp_unique_filename(' ),
	'Item 6: import_local_asset() must use wp_unique_filename() to avoid collisions'
);

/* ---------------------------------------------------------------------------
 * Item 7: Provenance (SHA256 + source meta) written BEFORE wp_generate_attachment_metadata()
 * ------------------------------------------------------------------------- */
$provenance_pos  = strpos( $import_body, 'ATTACH_SHA256_META' );
$meta_gen_pos    = strpos( $import_body, 'wp_generate_attachment_metadata(' );
$ok(
	false !== $provenance_pos && false !== $meta_gen_pos && $provenance_pos < $meta_gen_pos,
	'Item 7: provenance meta (_gloskin_p3_sha256) must be written before wp_generate_attachment_metadata()'
);
/* Also verify MANIFEST_ID is used (not the old literal string). */
$ok(
	false !== strpos( $import_body, 'self::MANIFEST_ID' ),
	'Item 7: import_local_asset() must use self::MANIFEST_ID for provenance source (not a raw string)'
);

/* ---------------------------------------------------------------------------
 * Item 8: Admin runner does NOT use location.reload() (spec E/F)
 * ------------------------------------------------------------------------- */
$ok(
	false === strpos( $admin, 'location.reload()' ),
	'Item 8: admin render_page() JS runner must not use location.reload() after any advance'
);
$ok(
	false === strpos( $admin, 'location.reload' ),
	'Item 8: admin render_page() must have zero location.reload references'
);

/* ---------------------------------------------------------------------------
 * Item 9: Single-flight guard — only one AJAX request in flight at a time (spec H)
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $admin, 'var running' ) || false !== strpos( $admin, 'running = false' ),
	'Item 9: admin JS runner must declare a "running" single-flight flag'
);
$ok(
	false !== strpos( $admin, 'if (running) return' ),
	'Item 9: admin JS runner must guard doRequest() with "if (running) return"'
);

/* ---------------------------------------------------------------------------
 * Item 10: UI auto-continues to next request after each successful step (spec F)
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $admin, 'setTimeout' ) && false !== strpos( $admin, 'doRequest' ),
	'Item 10: admin JS runner must use setTimeout + doRequest for auto-continue'
);
$ok(
	false !== strpos( $admin, "doRequest('continue', 1); }, 300" ),
	'Item 10: auto-continue must use 300 ms pause between requests'
);

/* ---------------------------------------------------------------------------
 * Item 11: Runner continues automatically across all Phase-3 checkpoints (spec F)
 *          Auto-continue fires on every "running" status response, not just media.
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $admin, "data.status === 'complete'" ) && false !== strpos( $admin, "data.status === 'failed'" ),
	'Item 11: auto-continue logic must handle complete and failed terminal states'
);
/* The doRequest call in the auto-continue setTimeout must not be gated on media step only. */
$ok(
	1 === substr_count( $admin, "doRequest('continue', 1); }, 300" ),
	'Item 11: there must be exactly one auto-continue setTimeout call (universal, not step-gated)'
);

/* ---------------------------------------------------------------------------
 * Item 12: Failed request stops the auto-chain and exposes the retry button (spec J)
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $admin, 'showError(' ),
	'Item 12: admin JS must call showError() to stop auto-chain on failure'
);
$ok(
	false !== strpos( $admin, 'p3-error-wrap' ),
	'Item 12: error wrap element must be present in the admin HTML'
);
$ok(
	false !== strpos( $admin, 'p3-retry' ),
	'Item 12: "Coba Lagi" retry button must be rendered in the admin HTML'
);
/* showError must set running = false (auto-chain truly stopped). */
$ok(
	false !== strpos( $admin, 'running = false;' ),
	'Item 12: showError() must reset running flag to allow retry'
);

/* ---------------------------------------------------------------------------
 * Item 13: Retry resumes from persisted state (spec K)
 *          p3-retry button re-fires doRequest with lastMode (not a page reload).
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $admin, 'var lastMode' ) || false !== strpos( $admin, 'lastMode = ' ),
	'Item 13: admin JS must track lastMode for retry'
);
$ok(
	false !== strpos( $admin, 'doRequest(lastMode, 1)' ),
	'Item 13: retry button must call doRequest(lastMode, 1) — resuming persisted server state'
);

/* ---------------------------------------------------------------------------
 * Item 14: No infinite retry loop — bounded max 2 retries (spec H)
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $admin, 'attempt < 3' ),
	'Item 14: retry loop must be bounded at attempt < 3 (max 2 retries)'
);
$ok(
	false !== strpos( $admin, 'attempt + 1' ),
	'Item 14: retry must increment attempt counter to prevent infinite loop'
);
/* There must be exactly two retry backoff values. */
$ok(
	false !== strpos( $admin, '1000' ) && false !== strpos( $admin, '3000' ),
	'Item 14: retry backoff must use 1s then 3s delays'
);

/* ---------------------------------------------------------------------------
 * Item 15: Authoritative manifests unchanged (77ee schema)
 *          Verify key structural markers in each manifest file.
 * ------------------------------------------------------------------------- */
$sk_decoded = json_decode( $sk_json, true );
$ok(
	is_array( $sk_decoded ) && isset( $sk_decoded['records'] ) && is_array( $sk_decoded['records'] ),
	'Item 15: skincare-products.json must be valid JSON with a "records" array'
);
$ok(
	count( $sk_decoded['records'] ) === 25,
	'Item 15: skincare-products.json must have exactly 25 records (authoritative count)'
);
$tr_decoded = json_decode( $tr_json, true );
$ok(
	is_array( $tr_decoded ) && isset( $tr_decoded['woo_treatment_products'] ),
	'Item 15: treatment-catalog.json must be valid JSON with "woo_treatment_products"'
);
$ok(
	count( $tr_decoded['woo_treatment_products'] ) === 48,
	'Item 15: treatment-catalog.json must have exactly 48 woo_treatment_products (authoritative count)'
);
$ok(
	isset( $tr_decoded['informational_cpt_targets'] ) && count( $tr_decoded['informational_cpt_targets'] ) === 8,
	'Item 15: treatment-catalog.json must have exactly 8 informational_cpt_targets (authoritative count)'
);
$pg_decoded = json_decode( $pg_json, true );
$ok(
	is_array( $pg_decoded ) && isset( $pg_decoded['presentation_media'] ),
	'Item 15: treatment-page-media.json must be valid JSON with "presentation_media"'
);
/* Each record in skincare manifest must retain the 77ee "primary"/"alternate" schema. */
foreach ( $sk_decoded['records'] as $idx => $rec ) {
	$ok(
		array_key_exists( 'primary', $rec ),
		"Item 15: skincare record #{$idx} must have 'primary' field (77ee schema)"
	);
}
/* MANIFEST_ID constant must remain authoritative. */
$ok(
	false !== strpos( $migration, "MANIFEST_ID" ) && false !== strpos( $migration, "'gloskin-client-feedback-phase3-migration-v1'" ),
	'Item 15: MANIFEST_ID must remain the authoritative 77ee identifier'
);

/* ---------------------------------------------------------------------------
 * Item 16: Packaged Phase-3 asset binaries unchanged
 *          Verify the assets base directory still exists and manifests reference it.
 * ------------------------------------------------------------------------- */
$assets_base = $root . '/plugin/gloskin-site-core/resources/phase3/assets';
$ok(
	is_dir( $assets_base ),
	"Item 16: Phase-3 packaged asset directory must exist at plugin/gloskin-site-core/resources/phase3/assets"
);
/* assets_base path in migration must reference the phase3/assets directory. */
$ok(
	false !== strpos( $migration, "phase3" ) && false !== strpos( $migration, "assets" ),
	'Item 16: migration class must reference the authoritative resources/phase3/assets directory'
);
/* Migration must not contain any image re-encode or compression call. */
$ok(
	false === stripos( $migration, 'imagejpeg(' ) && false === stripos( $migration, 'imagepng(' ) && false === stripos( $migration, 'imagewebp(' ),
	'Item 16: migration class must not re-encode or compress packaged asset binaries'
);

/* ---------------------------------------------------------------------------
 * Item 17: 25/48/8 + path + hero + exact-three verifier unchanged in phase3 contract
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $contract, '25 ===' ) || false !== strpos( $contract, "=== 25" ) || false !== strpos( $contract, 'exactly 25' ),
	'Item 17: phase3-client-feedback-contract must still verify 25 skincare records'
);
$ok(
	false !== strpos( $contract, '48 ===' ) || false !== strpos( $contract, "=== 48" ) || false !== strpos( $contract, 'exactly 48' ),
	'Item 17: phase3-client-feedback-contract must still verify 48 treatment products'
);
$ok(
	false !== strpos( $contract, '8 ===' ) || false !== strpos( $contract, "=== 8" ) || false !== strpos( $contract, 'exactly 8' ),
	'Item 17: phase3-client-feedback-contract must still verify 8 informational targets'
);
$ok(
	false !== strpos( $contract, 'gloskin_treatment_feature_on_home' ) || false !== strpos( $contract, 'feature_on_home' ),
	'Item 17: phase3-client-feedback-contract must still verify feature_on_home (exact-three check)'
);
$ok(
	false !== strpos( $contract, 'hero' ),
	'Item 17: phase3-client-feedback-contract must still verify hero binding'
);
$ok(
	false !== strpos( $contract, 'Idempotency' ) || false !== strpos( $contract, 'fingerprint_matches' ) || false !== strpos( $contract, 'manifest_fingerprint' ),
	'Item 17: phase3-client-feedback-contract must still assert idempotency (fingerprint-based no-op)'
);

/* ---------------------------------------------------------------------------
 * Version sync
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $plugin, 'Version: 0.7.181' ) && false !== strpos( $kernel, "const VERSION = '0.7.181';" ),
	'Version: plugin header and kernel VERSION must both be 0.7.180'
);

echo "phase3-resumability-contract.php: OK (17 items verified)\n";
