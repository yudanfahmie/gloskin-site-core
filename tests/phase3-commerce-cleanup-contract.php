<?php
/**
 * Phase-3 Commerce + Cleanup Contract (0.7.179)
 *
 * Verifies:
 *   — current_step_key exposed and used for media-row visibility
 *   — required media failure keeps cursor on same item (no skip path for non-PSD)
 *   — SHA reuse calls ensure_attachment_metadata (metadata repair)
 *   — commerce-enrichment.json is supplemental, separate from 77ee manifests
 *   — no runtime external web calls in migration
 *   — price is numeric from enrichment; SKU and stock not fabricated
 *   — pre_cleanup_gate and legacy_cleanup steps added after page_media
 *   — cleanup cannot run before canonical gate
 *   — legacy_cleanup trashes (not hard-deletes) treatment products/posts
 *   — concern and path cleanup uses wp_delete_term (not hard SQL delete)
 *   — Media Library attachments NOT deleted in cleanup
 *   — run_verify checks actual DB state (not only audit counters)
 *   — 48/8/4/18 final set verifications present in run_verify
 *   — second-run idempotency guard still present
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

$ok( '' !== $migration, 'migration class must be readable' );
$ok( '' !== $admin,     'admin class must be readable' );

/* ---------------------------------------------------------------------------
 * 1. current_step_key — Fix A
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $migration, "'current_step_key'" ),
	'Fix A: migration must expose current_step_key in state'
);
$ok(
	false !== strpos( $migration, "\$state['current_step_key']" ),
	'Fix A: migration response_state() must populate current_step_key'
);
/* Admin must use current_step_key (not current_step label) for media row detection. */
$ok(
	false !== strpos( $admin, "current_step_key" ),
	'Fix A: admin must reference current_step_key'
);
$ok(
	false === strpos( $admin, "current_step_key' ) ) ? '' : 'display:none" ) && false !== strpos( $admin, "current_step_key" ),
	'Fix A: admin PHP media row visibility must use current_step_key'
);
$ok(
	false !== strpos( $admin, "stepKey === 'media_reconcile'" ),
	'Fix A: admin JS must use current_step_key (stepKey) for media row visibility'
);

/* ---------------------------------------------------------------------------
 * 2. Required media retries same cursor — Fix B
 * ------------------------------------------------------------------------- */
/* Non-PSD failures must throw RuntimeException, not skip (cursor must not increment). */
$ok(
	false !== strpos( $migration, 'RuntimeException' ),
	'Fix B: migration must use RuntimeException for required media failures'
);
/* The step body must throw on unreadable/missing source — check the message strings. */
$ok(
	false !== strpos( $migration, 'Aset media wajib tidak terbaca' ),
	'Fix B: migration must throw on unreadable/missing source asset'
);
$ok(
	false !== strpos( $migration, 'SHA-256 gagal dihitung' ),
	'Fix B: migration must throw on SHA computation failure'
);
$ok(
	false !== strpos( $migration, 'Import aset wajib gagal' ),
	'Fix B: migration must throw on import failure'
);
/* PSD skip still present — valid non-image format. */
$ok(
	false !== stripos( $migration, "'psd'" ),
	'Fix B: PSD skip path must remain for non-importable format'
);
/* After RuntimeException, the cursor variable must NOT be incremented before the throw. */
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
$step_body = ( false !== $step_start && false !== $step_end ) ? substr( $migration, $step_start, $step_end - $step_start ) : '';
$ok( '' !== $step_body, 'Fix B: run_media_reconcile_step() body must be locatable' );
/* The throw lines must not be preceded by $cursor++ on same logic path. */
$throw_pos    = strpos( $step_body, 'throw new RuntimeException( \'SHA-256 gagal' );
$cursor_before_throw = false !== $throw_pos ? strrpos( substr( $step_body, 0, $throw_pos ), '$cursor++' ) : false;
$ok(
	false === $cursor_before_throw || ( false !== $throw_pos && false !== $cursor_before_throw && $cursor_before_throw < (int) strpos( $step_body, '$sha = hash_file' ) ),
	'Fix B: cursor must not be incremented before RuntimeException throw'
);

/* ---------------------------------------------------------------------------
 * 3. SHA reuse repairs metadata — Fix C
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $migration, 'private function ensure_attachment_metadata(' ),
	'Fix C: ensure_attachment_metadata() helper must exist'
);
$ok(
	false !== strpos( $migration, 'wp_get_attachment_metadata' ) && false !== strpos( $migration, 'wp_generate_attachment_metadata' ),
	'Fix C: ensure_attachment_metadata() must check and regenerate metadata'
);
/* ensure_attachment_metadata must be called after find_attachment_by_sha returns a hit. */
$sha_reuse_pos  = strpos( $migration, 'find_attachment_by_sha( $sha )' );
$ensure_pos     = strpos( $migration, 'ensure_attachment_metadata( $existing_id )' );
$ok(
	false !== $sha_reuse_pos && false !== $ensure_pos && $ensure_pos > $sha_reuse_pos,
	'Fix C: ensure_attachment_metadata() must be called after find_attachment_by_sha() in step body'
);
/* Same helper used in recover_partial_attachment. */
$ok(
	substr_count( $migration, 'ensure_attachment_metadata(' ) >= 2,
	'Fix C: ensure_attachment_metadata() must be used in both SHA-reuse path and recovery path'
);

/* ---------------------------------------------------------------------------
 * 4. Commerce enrichment separate from 77ee manifests
 * ------------------------------------------------------------------------- */
$enrichment_path = $root . '/plugin/gloskin-site-core/resources/phase3/manifests/commerce-enrichment.json';
$ok(
	is_file( $enrichment_path ),
	'Commerce: commerce-enrichment.json must exist as a supplemental file'
);
$enrichment_raw  = is_file( $enrichment_path ) ? (string) file_get_contents( $enrichment_path ) : '';
$enrichment_data = $enrichment_raw ? json_decode( $enrichment_raw, true ) : null;
$ok(
	is_array( $enrichment_data ) && ! empty( $enrichment_data['supplemental'] ),
	'Commerce: enrichment file must declare supplemental:true to distinguish from 77ee manifests'
);
/* 77ee manifests unchanged — verify enrichment is NOT inline in 77ee manifests. */
$tr_manifest_raw = (string) file_get_contents( $root . '/plugin/gloskin-site-core/resources/phase3/manifests/treatment-catalog.json' );
$ok(
	false === strpos( $tr_manifest_raw, 'commerce-enrichment' ) && false === strpos( $tr_manifest_raw, '"price_basis"' ),
	'Commerce: treatment-catalog.json must not contain enrichment fields (77ee manifests unchanged)'
);

/* ---------------------------------------------------------------------------
 * 5. No runtime external web calls
 * ------------------------------------------------------------------------- */
$ok(
	false === strpos( $migration, 'wp_remote_get(' ) && false === strpos( $migration, 'wp_remote_post(' ),
	'Commerce: migration must make no runtime external web requests'
);
$ok(
	false === strpos( $migration, 'curl_exec(' ) && false === strpos( $migration, 'file_get_contents(\'http' ),
	'Commerce: migration must make no runtime external HTTP calls'
);

/* ---------------------------------------------------------------------------
 * 6. Price numeric; SKU and stock not fabricated
 * ------------------------------------------------------------------------- */
$ok(
	false === strpos( $migration, 'set_sku(' ),
	'Commerce: migration must not fabricate SKU'
);
$ok(
	false === strpos( $migration, 'set_stock_quantity(' ),
	'Commerce: migration must not fabricate stock quantity'
);
/* Price setter present but loaded from enrichment (not invented inline). */
$ok(
	false !== strpos( $migration, 'set_regular_price' ) && false !== strpos( $migration, 'load_enrichment_prices' ),
	'Commerce: prices must be set from load_enrichment_prices(), not invented inline'
);

/* ---------------------------------------------------------------------------
 * 7. pre_cleanup_gate and legacy_cleanup steps added
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $migration, "'pre_cleanup_gate'" ),
	'Cleanup: pre_cleanup_gate step key must be declared in steps()'
);
$ok(
	false !== strpos( $migration, "'legacy_cleanup'" ),
	'Cleanup: legacy_cleanup step key must be declared in steps()'
);
/* Steps order: pre_cleanup_gate must come before legacy_cleanup, and both after page_media. */
$page_media_pos  = strpos( $migration, "'page_media'" );
$gate_pos        = strpos( $migration, "'pre_cleanup_gate'" );
$cleanup_pos     = strpos( $migration, "'legacy_cleanup'" );
$verify_pos      = strpos( $migration, "'verify'" );
$ok(
	false !== $page_media_pos && false !== $gate_pos && false !== $cleanup_pos
		&& $page_media_pos < $gate_pos && $gate_pos < $cleanup_pos && $cleanup_pos < $verify_pos,
	'Cleanup: step order must be page_media → pre_cleanup_gate → legacy_cleanup → verify'
);

/* ---------------------------------------------------------------------------
 * 8. Cleanup cannot run before canonical gate
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $migration, 'private function run_pre_cleanup_gate(' ),
	'Cleanup: run_pre_cleanup_gate() method must exist'
);
$ok(
	false !== strpos( $migration, 'private function run_legacy_cleanup(' ),
	'Cleanup: run_legacy_cleanup() method must exist'
);
/* Gate must throw RuntimeException on failure. */
$gate_fn_start = strpos( $migration, 'private function run_pre_cleanup_gate(' );
$gate_fn_end   = false;
if ( false !== $gate_fn_start ) {
	$depth = 0; $pos = strpos( $migration, '{', $gate_fn_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$gate_fn_end = $i;
	}
}
$gate_body = ( false !== $gate_fn_start && false !== $gate_fn_end ) ? substr( $migration, $gate_fn_start, $gate_fn_end - $gate_fn_start ) : '';
$ok(
	'' !== $gate_body && false !== strpos( $gate_body, 'RuntimeException' ),
	'Cleanup: run_pre_cleanup_gate() must throw RuntimeException on failure to block cleanup'
);
$ok(
	false !== strpos( $gate_body, '25 !== $sk_reconciled' ),
	'Cleanup: gate must verify 25 Skincare reconciled'
);
$ok(
	false !== strpos( $gate_body, '48 !== $tr_reconciled' ),
	'Cleanup: gate must verify 48 Treatment products reconciled'
);
$ok(
	false !== strpos( $gate_body, '8 !== $rec_total' ),
	'Cleanup: gate must verify 8 Treatment records reconciled'
);

/* ---------------------------------------------------------------------------
 * 9. Legacy cleanup uses wp_trash_post (not hard-delete)
 * ------------------------------------------------------------------------- */
$cleanup_fn_start = strpos( $migration, 'private function run_legacy_cleanup(' );
$cleanup_fn_end   = false;
if ( false !== $cleanup_fn_start ) {
	$depth = 0; $pos = strpos( $migration, '{', $cleanup_fn_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$cleanup_fn_end = $i;
	}
}
$cleanup_body = ( false !== $cleanup_fn_start && false !== $cleanup_fn_end ) ? substr( $migration, $cleanup_fn_start, $cleanup_fn_end - $cleanup_fn_start ) : '';
$ok( '' !== $cleanup_body, 'Cleanup: run_legacy_cleanup() body must be locatable' );
$ok(
	false !== strpos( $cleanup_body, 'wp_trash_post(' ),
	'Cleanup: legacy_cleanup must use wp_trash_post() — no hard deletes'
);
$ok(
	false === strpos( $cleanup_body, 'wp_delete_post(' ),
	'Cleanup: legacy_cleanup must not use wp_delete_post()'
);
/* Concerns and paths deleted by wp_delete_term (taxonomy API), not SQL. */
$ok(
	false !== strpos( $cleanup_body, 'wp_delete_term(' ),
	'Cleanup: legacy_cleanup must use wp_delete_term() for taxonomy cleanup'
);
/* Media Library attachments NOT deleted in cleanup. */
$ok(
	false === strpos( $cleanup_body, "'attachment'" ),
	'Cleanup: legacy_cleanup must not touch Media Library attachments'
);
/* Audit contains the four counters. */
$ok(
	false !== strpos( $cleanup_body, "'treatment_products_trashed'" )
		&& false !== strpos( $cleanup_body, "'treatment_records_trashed'" )
		&& false !== strpos( $cleanup_body, "'paths_deleted'" )
		&& false !== strpos( $cleanup_body, "'concerns_deleted'" ),
	'Cleanup: legacy_cleanup audit must track all four counters'
);

/* ---------------------------------------------------------------------------
 * 10. run_verify checks actual database state (not only audit counters)
 * ------------------------------------------------------------------------- */
$verify_fn_start = strpos( $migration, 'private function run_verify(' );
$verify_fn_end   = false;
if ( false !== $verify_fn_start ) {
	$depth = 0; $pos = strpos( $migration, '{', $verify_fn_start );
	if ( false !== $pos ) {
		$depth = 1; $i = $pos + 1; $len = strlen( $migration );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $migration[ $i ] ) { $depth++; }
			if ( '}' === $migration[ $i ] ) { $depth--; }
			$i++;
		}
		$verify_fn_end = $i;
	}
}
$verify_body = ( false !== $verify_fn_start && false !== $verify_fn_end ) ? substr( $migration, $verify_fn_start, $verify_fn_end - $verify_fn_start ) : '';
$ok( '' !== $verify_body, 'Verify: run_verify() body must be locatable' );
/* Final 48/8/4/18 set checks. */
$ok(
	false !== strpos( $verify_body, '48 !== count( $live_tr_slugs )' ),
	'Verify: run_verify() must check actual count of 48 non-trashed Treatment Woo products'
);
$ok(
	false !== strpos( $verify_body, '8 !== count( $live_cpt_slugs )' ),
	'Verify: run_verify() must check actual count of 8 non-trashed gloskin_treatment records'
);
$ok(
	false !== strpos( $verify_body, '4 !== count( $live_path_slugs )' ),
	'Verify: run_verify() must check actual count of 4 consultation path terms'
);
$ok(
	false !== strpos( $verify_body, '18 !== count( $live_concern_slugs )' ),
	'Verify: run_verify() must check actual count of 18 concern terms'
);
/* Slug set exact match checks. */
$ok(
	false !== strpos( $verify_body, '$live_tr_slugs !== $auth_tr_sorted' ),
	'Verify: run_verify() must verify Treatment Woo slug set equals authoritative 48'
);
$ok(
	false !== strpos( $verify_body, '$live_cpt_slugs !== $auth_cpt_sorted' ),
	'Verify: run_verify() must verify CPT slug set equals authoritative 8'
);
$ok(
	false !== strpos( $verify_body, '$live_concern_slugs !== $auth_concern_slugs' ),
	'Verify: run_verify() must verify concern slug set equals authoritative 18'
);
/* Price > 0 for all canonical products. */
$ok(
	false !== strpos( $verify_body, '$unpriced' ) && false !== strpos( $verify_body, '(float) $cprice <= 0' ),
	'Verify: run_verify() must verify all canonical Woo products have price > 0'
);
/* Zero legacy active check. */
$ok(
	false !== strpos( $verify_body, '$legacy_active' ) && false !== strpos( $verify_body, '$legacy_active > 0' ),
	'Verify: run_verify() must check zero active legacy Treatment products'
);

/* ---------------------------------------------------------------------------
 * 11. Unrelated Woo data not touched (no blanket wc_get_products mutations)
 * ------------------------------------------------------------------------- */
/* Legacy cleanup only targets family=treatment products. */
$ok(
	false !== strpos( $cleanup_body, "'treatment'" ) && false === strpos( $cleanup_body, "'skincare'" ),
	'Cleanup: legacy_cleanup must target only family=treatment, not skincare or unrelated products'
);

/* ---------------------------------------------------------------------------
 * 12. Second-run no-op still in place
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $migration, 'fingerprint_matches' ) && false !== strpos( $migration, "Idempotent second run" ),
	'Idempotency: second-run no-op guard must remain in advance()'
);

/* ---------------------------------------------------------------------------
 * 13. Admin UI counters present
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $admin, 'p3-commerce-row' ),
	'Admin UI: commerce counter row must exist'
);
$ok(
	false !== strpos( $admin, 'p3-cleanup-row' ),
	'Admin UI: cleanup counter row must exist'
);
$ok(
	false !== strpos( $admin, 'p3-sk-priced' ) && false !== strpos( $admin, 'p3-tr-priced' ),
	'Admin UI: Skincare priced / Treatment priced counters must exist'
);
$ok(
	false !== strpos( $admin, 'p3-products-trashed' ) && false !== strpos( $admin, 'p3-records-trashed' ),
	'Admin UI: cleanup trashed counters must exist'
);

/* ---------------------------------------------------------------------------
 * Report
 * ------------------------------------------------------------------------- */
if ( $fail_count > 0 ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, $failure . "\n" );
	}
	fwrite( STDERR, "\nphase3-commerce-cleanup-contract.php: {$fail_count} assertion(s) FAILED, {$ok_count} passed\n" );
	exit( 1 );
}

echo "phase3-commerce-cleanup-contract.php: OK ({$ok_count} assertions)\n";
