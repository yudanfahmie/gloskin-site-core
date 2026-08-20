<?php
/**
 * Phase-3 Client Feedback Contract — Static PHP source analysis.
 *
 * Validates against the 77ee authoritative manifests (commit 77eeeca84396c3aa5a6c8be4a4481da060da6e5b).
 * The runtime adapts to the manifests — manifests do NOT adapt to the runtime.
 *
 * Tickets: FB-989354 (Skincare), FB-989360 (Treatment).
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
 * 1. Version sync at 0.7.179
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $plugin_php, 'Version: 0.7.180' ) && false !== strpos( $kernel_php, "const VERSION = '0.7.180';" ),
	'Phase-3 runtime/cache version must be synchronized at 0.7.180'
);

/* ---------------------------------------------------------------------------
 * 2. 77ee Manifest — manifest_id
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
	$ok(
		'gloskin-client-feedback-phase3-migration-v1' === ( $mm_data['manifest_id'] ?? '' ),
		'migration-manifest.json must declare manifest_id=gloskin-client-feedback-phase3-migration-v1 (77ee authoritative)'
	);
}

/* ---------------------------------------------------------------------------
 * 3. Skincare: 25 records (77ee uses 'records', not 'products')
 * ------------------------------------------------------------------------- */
if ( is_array( $sk_data ) ) {
	$sk_records = (array) ( $sk_data['records'] ?? array() );
	$ok( 25 === count( $sk_records ), 'skincare-products.json must contain exactly 25 records (77ee key) (got ' . count( $sk_records ) . ')' );

	/* 77ee skincare records have NO SKU field. */
	$sku_found = false;
	foreach ( $sk_records as $rec ) {
		if ( ! empty( $rec['sku'] ) ) {
			$sku_found = true;
			break;
		}
	}
	$ok( ! $sku_found, 'skincare records must have no sku field (77ee authoritative: no fabricated SKU)' );

	/* No GS-SK-* pattern in skincare manifest. */
	$ok( false === strpos( $manifest_sk, 'GS-SK-' ), 'skincare-products.json must contain no GS-SK-* SKUs (fabricated SKUs must be absent)' );
}

/* ---------------------------------------------------------------------------
 * 4. Treatment catalog: 48 woo_treatment_products (77ee key), 8 informational_cpt_targets
 * ------------------------------------------------------------------------- */
if ( is_array( $tr_data ) ) {
	$tr_products = (array) ( $tr_data['woo_treatment_products'] ?? array() );
	$tr_records  = (array) ( $tr_data['informational_cpt_targets'] ?? array() );

	$ok( 48 === count( $tr_products ), 'treatment-catalog.json must contain exactly 48 woo_treatment_products (77ee key) (got ' . count( $tr_products ) . ')' );
	$ok( 8 === count( $tr_records ),  'treatment-catalog.json must contain exactly 8 informational_cpt_targets (77ee key) (got ' . count( $tr_records ) . ')' );

	/* No fabricated GS-TRT-* SKUs in treatment products. */
	$ok( false === strpos( $manifest_tr, 'GS-TRT-' ), 'treatment-catalog.json must contain no GS-TRT-* SKUs (fabricated SKUs must be absent)' );

	/* 77ee treatment products have no sku field. */
	$tr_sku_found = false;
	foreach ( $tr_products as $p ) {
		if ( ! empty( $p['sku'] ) ) {
			$tr_sku_found = true;
			break;
		}
	}
	$ok( ! $tr_sku_found, 'woo_treatment_products must have no sku field (77ee authoritative: no fabricated SKU)' );

	/* Exactly 3 CPT records with feature_on_home=true. */
	$home_feature_count = 0;
	foreach ( $tr_records as $r ) {
		if ( ! empty( $r['feature_on_home'] ) ) {
			$home_feature_count++;
		}
	}
	$ok( 3 === $home_feature_count, 'informational_cpt_targets must have exactly 3 entries with feature_on_home=true (got ' . $home_feature_count . ')' );

	/* 8 exact approved concern slugs from new_concerns_to_upsert (77ee key). */
	$approved_slugs = array( 'facial-laxity', 'facial-contour', 'under-eye', 'skin-lesions', 'scars-keloid', 'hair-loss', 'hair-density', 'body-contour' );
	$manifest_new_concerns = (array) ( $tr_data['new_concerns_to_upsert'] ?? array() );
	$ok( 8 === count( $manifest_new_concerns ), 'treatment-catalog.json must contain exactly 8 new_concerns_to_upsert (77ee key) (got ' . count( $manifest_new_concerns ) . ')' );
	$manifest_concern_slugs = array_column( $manifest_new_concerns, 'slug' );
	foreach ( $approved_slugs as $slug ) {
		$ok( in_array( $slug, $manifest_concern_slugs, true ), "Approved concern slug must be present in new_concerns_to_upsert: {$slug}" );
	}
	/* No extra concern slugs beyond the 8 approved. */
	$extra_slugs = array_diff( $manifest_concern_slugs, $approved_slugs );
	$ok( empty( $extra_slugs ), 'new_concerns_to_upsert must contain only the 8 approved slugs (found extra: ' . implode( ',', $extra_slugs ) . ')' );

	/* Rejuran HB dedup — must appear exactly once. */
	$rejuran_count = 0;
	foreach ( $tr_products as $p ) {
		if ( false !== stripos( (string) ( $p['title'] ?? '' ), 'rejuran' ) && false !== stripos( (string) ( $p['title'] ?? '' ), 'hb' ) ) {
			$rejuran_count++;
		}
	}
	$ok( 1 === $rejuran_count, 'Rejuran HB must appear exactly once in woo_treatment_products (found ' . $rejuran_count . ')' );
}

/* ---------------------------------------------------------------------------
 * 5. Page media: presentation_media (77ee key)
 * ------------------------------------------------------------------------- */
if ( is_array( $pm_data ) ) {
	$presentation_media = (array) ( $pm_data['presentation_media'] ?? array() );

	/* 4 consultation_path items + 1 hero = 5 total. */
	$path_items = array_filter( $presentation_media, function ( $item ) {
		return isset( $item['slot'] ) && 0 === strpos( (string) $item['slot'], 'consultation_path:' );
	} );
	$hero_items = array_filter( $presentation_media, function ( $item ) {
		return isset( $item['slot'] ) && 'treatments.hero' === (string) $item['slot'];
	} );

	$ok( 4 === count( $path_items ), 'treatment-page-media.json must contain exactly 4 consultation_path: items (got ' . count( $path_items ) . ')' );
	$ok( 1 === count( $hero_items ), 'treatment-page-media.json must contain exactly 1 treatments.hero item' );

	/* Verify four stable path stable_slugs. */
	$stable_slugs   = array( 'acne-focus', 'brightening-focus', 'anti-aging-focus', 'skin-health-focus' );
	$manifest_slugs = array_column( array_values( $path_items ), 'stable_slug' );
	foreach ( $stable_slugs as $slug ) {
		$ok( in_array( $slug, $manifest_slugs, true ), "Stable path stable_slug must be present in presentation_media: {$slug}" );
	}
	$extra = array_diff( $manifest_slugs, $stable_slugs );
	$ok( empty( $extra ), 'presentation_media must only contain the four stable path slugs (found extra: ' . implode( ',', $extra ) . ')' );
}

/* ---------------------------------------------------------------------------
 * 6. Unresolved: items list with 5 HOLD status (77ee uses 'items', not 'holds')
 * ------------------------------------------------------------------------- */
if ( is_array( $ur_data ) ) {
	$items      = (array) ( $ur_data['items'] ?? array() );
	$hold_items = array_filter( $items, function ( $item ) { return isset( $item['status'] ) && 'HOLD' === (string) $item['status']; } );
	$ok( 5 === count( $hold_items ), 'unresolved.json must list exactly 5 HOLD status items (77ee schema) (got ' . count( $hold_items ) . ')' );

	/* Verify five known HOLD keys (77ee uses 'key', not 'id'). */
	$hold_keys = array_column( array_values( $hold_items ), 'key' );
	foreach ( array( 'az-xpert', 'glam-gold-serum', 'skin-fresh-facial-wash', 'opaque-dsc02911', 'opaque-untitled-47' ) as $expected_key ) {
		$ok( in_array( $expected_key, $hold_keys, true ), "HOLD key must be present in unresolved.json items: {$expected_key}" );
	}
}

/* ---------------------------------------------------------------------------
 * 7. Canonical home feature meta in migration class
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $migration, "HOME_FEATURE_META   = 'gloskin_treatment_feature_on_home'" ),
	"Migration class must declare HOME_FEATURE_META = 'gloskin_treatment_feature_on_home' (canonical public meta key)"
);
$ok(
	false === strpos( $migration, "'_gloskin_treatment_home_feature'" ),
	"Migration class must not use private underscore-prefixed meta key _gloskin_treatment_home_feature"
);

/* ---------------------------------------------------------------------------
 * 8. Authoritative manifest_id in migration class
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $migration, "MANIFEST_ID    = 'gloskin-client-feedback-phase3-migration-v1'" ),
	"Migration class MANIFEST_ID must match 77ee: 'gloskin-client-feedback-phase3-migration-v1'"
);

/* ---------------------------------------------------------------------------
 * 9. No fabricated SKU in migration runtime
 * ------------------------------------------------------------------------- */
$ok(
	false === strpos( $migration, 'set_sku(' ),
	'Migration class must not call set_sku() (no fabricated SKU)'
);
$ok(
	false === strpos( $migration, 'wc_get_product_id_by_sku' ),
	'Migration class must not look up products by SKU (no SKU in 77ee manifests)'
);

/* ---------------------------------------------------------------------------
 * 10. No .psd asset imports
 * ------------------------------------------------------------------------- */
$ok( false !== stripos( $migration, "'psd'" ) || false !== stripos( $migration, '"psd"' ), 'Phase-3 migration must guard against .psd extension (skip psd assets)' );
/* Check asset fields only (not informational notes) for .psd references. */
$sk_psd_in_assets = false;
if ( is_array( $sk_data ) ) {
	foreach ( (array) ( $sk_data['records'] ?? array() ) as $rec ) {
		if ( false !== stripos( (string) ( $rec['primary'] ?? '' ), '.psd' ) ) {
			$sk_psd_in_assets = true; break;
		}
		foreach ( (array) ( $rec['alternate'] ?? array() ) as $alt ) {
			if ( false !== stripos( (string) $alt, '.psd' ) ) {
				$sk_psd_in_assets = true; break 2;
			}
		}
	}
}
$ok( ! $sk_psd_in_assets, 'skincare-products.json primary/alternate asset fields must not reference .psd files' );
$ok( false === stripos( $manifest_tr, '.psd' ), 'treatment-catalog.json must not reference .psd files' );

/* ---------------------------------------------------------------------------
 * 11. No direct SQL mutations
 * ------------------------------------------------------------------------- */
$sql_mutators = array( '$wpdb->insert', '$wpdb->update', '$wpdb->delete', '$wpdb->replace', '$wpdb->query' );
foreach ( $sql_mutators as $mutator ) {
	$ok( false === strpos( $migration, $mutator ), "Phase-3 migration must not use direct SQL: {$mutator}" );
}

/* ---------------------------------------------------------------------------
 * 12. No hard-deletes (only wp_trash_post)
 * ------------------------------------------------------------------------- */
$ok( false === strpos( $migration, 'wp_delete_post' ), 'Phase-3 migration must not call wp_delete_post (use wp_trash_post)' );
$ok( false === strpos( $migration, 'force_delete' ), 'Phase-3 migration must not call force_delete' );

/* ---------------------------------------------------------------------------
 * 13. Commerce enrichment: prices from supplemental file, no fabricated SKU/stock (0.7.179)
 * ------------------------------------------------------------------------- */
/* Prices are now applied from supplemental commerce-enrichment.json — NOT invented inline.
 * New products with enrichment price are published; without price they remain draft.
 * SKU and stock quantity are never fabricated. */
$enrichment_path = $root . '/plugin/gloskin-site-core/resources/phase3/manifests/commerce-enrichment.json';
$ok(
	is_file( $enrichment_path ),
	'commerce-enrichment.json must exist as a supplemental file separate from 77ee manifests'
);
$enrichment_raw  = is_file( $enrichment_path ) ? (string) file_get_contents( $enrichment_path ) : '';
$enrichment_data = $enrichment_raw ? json_decode( $enrichment_raw, true ) : null;
$ok(
	is_array( $enrichment_data ) && ! empty( $enrichment_data['supplemental'] ),
	'commerce-enrichment.json must declare supplemental:true'
);
$ok(
	is_array( $enrichment_data ) && 25 === count( (array) ( $enrichment_data['skincare'] ?? array() ) ),
	'commerce-enrichment.json must contain exactly 25 skincare entries'
);
$ok(
	is_array( $enrichment_data ) && 48 === count( (array) ( $enrichment_data['treatment'] ?? array() ) ),
	'commerce-enrichment.json must contain exactly 48 treatment entries'
);
/* All enrichment prices must be numeric strings > 0; no SKU or stock_quantity fields. */
$bad_prices = false;
$has_sku_in_enrichment = false;
$has_stock_in_enrichment = false;
if ( is_array( $enrichment_data ) ) {
	foreach ( array_merge( (array) ( $enrichment_data['skincare'] ?? array() ), (array) ( $enrichment_data['treatment'] ?? array() ) ) as $entry ) {
		$p = (string) ( $entry['price'] ?? '' );
		if ( '' === $p || ! is_numeric( $p ) || (float) $p <= 0 ) {
			$bad_prices = true;
		}
		if ( isset( $entry['sku'] ) || isset( $entry['stock_quantity'] ) ) {
			$has_sku_in_enrichment = true;
		}
	}
}
$ok( ! $bad_prices, 'All commerce-enrichment.json prices must be numeric and > 0' );
$ok( ! $has_sku_in_enrichment, 'commerce-enrichment.json must not contain sku or stock_quantity fields' );
/* Migration must not fabricate SKU or stock. */
$ok(
	false === strpos( $migration, 'set_sku(' ),
	'Phase-3 migration must not call set_sku() (no fabricated SKU)'
);
$ok(
	false === strpos( $migration, 'set_stock_quantity(' ),
	'Phase-3 migration must not call set_stock_quantity() (no fabricated stock)'
);
/* Draft fallback still exists for products without enrichment price. */
$ok(
	false !== strpos( $migration, "'draft'" ),
	"Phase-3 migration must still have 'draft' status fallback for products without valid price"
);

/* ---------------------------------------------------------------------------
 * 14. SHA-256 media deduplication
 * ------------------------------------------------------------------------- */
$ok( false !== strpos( $migration, 'sha256' ), 'Phase-3 migration must use SHA-256 for media deduplication' );
$ok( false !== strpos( $migration, '_gloskin_p3_sha256' ), 'Phase-3 migration must store SHA-256 in _gloskin_p3_sha256 attachment meta' );
$ok( false !== strpos( $migration, 'find_attachment_by_sha' ), 'Phase-3 migration must check for existing attachments before importing' );

/* ---------------------------------------------------------------------------
 * 15. Idempotency: fingerprint check present
 * ------------------------------------------------------------------------- */
$ok( false !== strpos( $migration, 'fingerprint_matches' ), 'Phase-3 migration must implement fingerprint_matches() for idempotency' );
$ok( false !== strpos( $migration, 'manifest_fingerprint' ), 'Phase-3 migration state must track manifest_fingerprint' );

/* ---------------------------------------------------------------------------
 * 16. Lock pattern in migration class
 * ------------------------------------------------------------------------- */
$ok( false !== strpos( $migration, 'acquire_lock' ), 'Phase-3 migration must implement acquire_lock()' );
$ok( false !== strpos( $migration, 'release_lock' ), 'Phase-3 migration must implement release_lock()' );
$ok( false !== strpos( $migration, 'LOCK_TTL' ), 'Phase-3 migration must declare LOCK_TTL constant' );

/* ---------------------------------------------------------------------------
 * 17. Preflight/start performs zero mutations
 * ------------------------------------------------------------------------- */
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
 * 18. Admin: capability check + nonce
 * ------------------------------------------------------------------------- */
$ok( false !== strpos( $admin_php, "const CAPABILITY  = 'manage_options'" ), 'Admin must declare CAPABILITY=manage_options' );
$ok( false !== strpos( $admin_php, 'check_ajax_referer' ), 'AJAX handler must call check_ajax_referer' );
$ok( false !== strpos( $admin_php, 'current_user_can' ), 'Admin must call current_user_can' );
$ok( false !== strpos( $admin_php, 'wp_create_nonce' ), 'Admin must generate a nonce' );

/* ---------------------------------------------------------------------------
 * 19. Kernel registers Phase-3 admin
 * ------------------------------------------------------------------------- */
$ok( false !== strpos( $kernel_php, 'class-gloskin-site-core-phase3-migration.php' ), 'Kernel must require phase3-migration.php' );
$ok( false !== strpos( $kernel_php, 'class-gloskin-site-core-phase3-migration-admin.php' ), 'Kernel must require phase3-migration-admin.php' );
$ok( false !== strpos( $kernel_php, 'Gloskin_Site_Core_Phase3_Migration_Admin' ), 'Kernel must instantiate Phase3 admin' );

/* ---------------------------------------------------------------------------
 * 20. Fail-closed production verifier + authoritative state owners
 * ------------------------------------------------------------------------- */
$verify_start = strpos( $migration, 'private function run_verify( array $state )' );
$verify_end   = false !== $verify_start ? strpos( $migration, 'private function ', (int) $verify_start + 1 ) : false;
$verify_body  = ( false !== $verify_start && false !== $verify_end )
	? substr( $migration, (int) $verify_start, (int) $verify_end - (int) $verify_start )
	: '';
$ok( '' !== $verify_body, 'run_verify() must be present for production verification' );
$ok( false !== strpos( $verify_body, '25 !== $sk_reconciled' ), 'run_verify() must require exactly 25 Skincare reconciled' );
$ok( false !== strpos( $verify_body, '48 !== $tr_reconciled' ), 'run_verify() must require exactly 48 Woo Treatment Products reconciled' );
$ok( false !== strpos( $verify_body, '8 !== $rec_total' ), 'run_verify() must require exactly 8 informational Treatments reconciled' );
$ok( false !== strpos( $verify_body, '4 !== $paths_updated' ), 'run_verify() must require exactly 4 consultation paths updated' );
$ok( false !== strpos( $verify_body, '4 !== $paths_bound' ), 'run_verify() must require exactly 4 consultation path media bindings' );
$ok(
	false !== strpos( $verify_body, "true !== (bool) ( \$page_audit['hero_bound'] ?? false )" ),
	'run_verify() must require Treatment hero media binding success'
);
$ok(
	false !== strpos( $verify_body, '$required_skips' )
		&& false !== strpos( $verify_body, "\$media_audit['skipped']" )
		&& false !== strpos( $verify_body, "\$sk_audit['skipped']" )
		&& false !== strpos( $verify_body, "\$tr_audit['skipped']" )
		&& false !== strpos( $verify_body, "\$rec_audit['skipped']" )
		&& false !== strpos( $verify_body, "\$page_audit['skipped']" )
		&& false !== strpos( $verify_body, '0 !== $required_skips' ),
	'run_verify() must fail on any required resolved-target skip'
);
$ok(
	false !== strpos( $verify_body, 'self::HOME_FEATURE_META' )
		&& false !== strpos( $verify_body, "'numberposts' => -1" )
		&& false !== strpos( $verify_body, '3 !== $home_feature_count' ),
	'run_verify() must require exactly 3 informational Treatments with Home-feature meta'
);
$ok(
	false !== strpos( $migration, "STATE_OPTION   = 'gloskin_site_core_client_feedback_phase3_v1_state'" )
		&& false !== strpos( $migration, "LOCK_OPTION    = 'gloskin_site_core_client_feedback_phase3_v1_lock'" )
		&& false === strpos( $migration, "'gloskin_site_core_phase3_v1_state'" )
		&& false === strpos( $migration, "'gloskin_site_core_phase3_v1_lock'" ),
	'Phase-3 STATE_OPTION / LOCK_OPTION must match authoritative 77ee owners with no alternate state owner'
);

/* ---------------------------------------------------------------------------
 * 21. Production package owns all Phase-3 runtime dependencies
 * ------------------------------------------------------------------------- */
$constructor_start = strpos( $migration, 'public function __construct()' );
$constructor_end   = false !== $constructor_start ? strpos( $migration, '/* -----------------------------------------------------------------', (int) $constructor_start ) : false;
$constructor_body  = ( false !== $constructor_start && false !== $constructor_end )
	? substr( $migration, (int) $constructor_start, (int) $constructor_end - (int) $constructor_start )
	: '';

$packaged_manifest_dir = $root . '/plugin/gloskin-site-core/resources/phase3/manifests';
$packaged_assets_base  = $root . '/plugin/gloskin-site-core/resources/phase3/assets';
$packaged_manifest_ok  = true;
foreach ( array( 'migration-manifest.json', 'skincare-products.json', 'treatment-catalog.json', 'treatment-page-media.json', 'unresolved.json' ) as $manifest_name ) {
	$source_path   = $root . '/docs/client-feedback-phase-3/manifests/' . $manifest_name;
	$packaged_path = $packaged_manifest_dir . '/' . $manifest_name;
	$packaged_manifest_ok = $packaged_manifest_ok
		&& is_file( $source_path )
		&& is_file( $packaged_path )
		&& hash_file( 'sha256', $source_path ) === hash_file( 'sha256', $packaged_path );
}

$required_packaged_assets = array();
if ( is_array( $sk_data ) ) {
	foreach ( (array) ( $sk_data['records'] ?? array() ) as $record ) {
		if ( ! empty( $record['primary'] ) ) {
			$required_packaged_assets[] = 'FB-989354-skincare-page/FOTO PRODUCT PNG/' . $record['primary'];
		}
		foreach ( (array) ( $record['alternate'] ?? array() ) as $alternate ) {
			if ( '' !== (string) $alternate ) {
				$required_packaged_assets[] = 'FB-989354-skincare-page/FOTO PRODUCT PNG/' . $alternate;
			}
		}
	}
}
if ( is_array( $tr_data ) ) {
	foreach ( (array) ( $tr_data['woo_treatment_products'] ?? array() ) as $record ) {
		if ( ! empty( $record['primary'] ) ) {
			$required_packaged_assets[] = 'FB-989360-treatment-page/FOTO TREATMENT/' . $record['primary'];
		}
	}
	foreach ( (array) ( $tr_data['informational_cpt_targets'] ?? array() ) as $record ) {
		if ( ! empty( $record['featured_asset'] ) ) {
			$required_packaged_assets[] = 'FB-989360-treatment-page/FOTO TREATMENT/' . $record['featured_asset'];
		}
	}
}
if ( is_array( $pm_data ) ) {
	foreach ( (array) ( $pm_data['presentation_media'] ?? array() ) as $record ) {
		if ( ! empty( $record['asset'] ) ) {
			$required_packaged_assets[] = ltrim( str_replace( 'docs/feedback-cases-gloskin-20260820-154828/', '', (string) $record['asset'] ), '/' );
		}
	}
}
$required_packaged_assets = array_values( array_unique( $required_packaged_assets ) );
$packaged_assets_ok = true;
foreach ( $required_packaged_assets as $asset_rel ) {
	if ( ! is_file( $packaged_assets_base . '/' . $asset_rel ) ) {
		$packaged_assets_ok = false;
		break;
	}
}

$ok(
	'' !== $constructor_body
		&& false === strpos( $constructor_body, 'ABSPATH' )
		&& false !== strpos( $constructor_body, 'plugin_dir_path( dirname( __FILE__ ) )' )
		&& false !== strpos( $constructor_body, "'resources' . \$sep . 'phase3' . \$sep . 'manifests'" )
		&& false !== strpos( $constructor_body, "'resources' . \$sep . 'phase3' . \$sep . 'assets'" )
		&& $packaged_manifest_ok
		&& $packaged_assets_ok,
	'Phase-3 runtime must resolve byte-identical packaged manifests/assets from the installed plugin and never from ABSPATH/docs'
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
