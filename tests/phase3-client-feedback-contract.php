<?php
/**
 * Phase-3 Client Feedback Contract — Static PHP source analysis.
 *
 * Validates against the 77ee authoritative manifests (commit 77eeeca84396c3aa5a6c8be4a4481da060da6e5b).
 * The runtime adapts to the manifests — manifests do NOT adapt to the runtime.
 *
 * Tickets: FB-989354 (Skincare), FB-989360 (Treatment).
 *
 * Phase 4.1 retirement: migration source files deleted; docs/manifests remain
 * as historical record. Sections 7–21 (PHP source content assertions) removed.
 * Section 7 replaced by retirement-state assertions.
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
 * 0. Read source files (migration class files retired — not read here)
 * ------------------------------------------------------------------------- */
$plugin_php  = (string) file_get_contents( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );
$kernel_php  = (string) file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php' );
$manifest_mm = (string) file_get_contents( $root . '/docs/client-feedback-phase-3/manifests/migration-manifest.json' );
$manifest_sk = (string) file_get_contents( $root . '/docs/client-feedback-phase-3/manifests/skincare-products.json' );
$manifest_tr = (string) file_get_contents( $root . '/docs/client-feedback-phase-3/manifests/treatment-catalog.json' );
$manifest_pm = (string) file_get_contents( $root . '/docs/client-feedback-phase-3/manifests/treatment-page-media.json' );
$manifest_ur = (string) file_get_contents( $root . '/docs/client-feedback-phase-3/manifests/unresolved.json' );

foreach ( array(
	'plugin_php', 'kernel_php',
	'manifest_mm', 'manifest_sk', 'manifest_tr', 'manifest_pm', 'manifest_ur',
) as $var ) {
	$ok( '' !== $$var, "Source file must be readable: {$var}" );
}

/* ---------------------------------------------------------------------------
 * 1. Version sync at 0.7.181
 * ------------------------------------------------------------------------- */
$ok(
	false !== strpos( $plugin_php, 'Version: 0.7.181' ) && false !== strpos( $kernel_php, "const VERSION = '0.7.181';" ),
	'Plugin/Kernel version must be synchronized at 0.7.181'
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
 * 7. Retirement state — Phase-3 migration source fully retired (Phase 4.1)
 * ------------------------------------------------------------------------- */
$ok(
	! is_file( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-phase3-migration.php' ),
	'Phase-3 migration class must be deleted from source tree (retired Phase 4.1)'
);
$ok(
	! is_file( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-phase3-migration-admin.php' ),
	'Phase-3 migration admin class must be deleted from source tree (retired Phase 4.1)'
);
$ok(
	false === strpos( $kernel_php, 'class-gloskin-site-core-phase3-migration.php' ),
	'Kernel must NOT require class-gloskin-site-core-phase3-migration.php (source retired)'
);
$ok(
	false === strpos( $kernel_php, 'class-gloskin-site-core-phase3-migration-admin.php' ),
	'Kernel must NOT require class-gloskin-site-core-phase3-migration-admin.php (source retired)'
);
$ok(
	false === strpos( $kernel_php, 'Gloskin_Site_Core_Phase3_Migration_Admin' ),
	'Kernel must NOT instantiate Gloskin_Site_Core_Phase3_Migration_Admin (source retired)'
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
