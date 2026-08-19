#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import hashlib
import json
import math
import re
import sys

ROOT = Path(__file__).resolve().parents[1]


def p(rel: str) -> Path:
    return ROOT / rel


def read(rel: str) -> str:
    return p(rel).read_text(encoding="utf-8")


def write(rel: str, text: str) -> None:
    p(rel).write_text(text, encoding="utf-8")


def replace_once(rel: str, old: str, new: str) -> None:
    text = read(rel)
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{rel}: expected exactly one replacement target, found {count}: {old[:100]!r}")
    write(rel, text.replace(old, new, 1))


def regex_once(rel: str, pattern: str, repl: str, flags: int = re.S) -> None:
    text = read(rel)
    new, count = re.subn(pattern, repl, text, count=1, flags=flags)
    if count != 1:
        raise SystemExit(f"{rel}: expected one regex replacement, found {count}: {pattern[:120]!r}")
    write(rel, new)


# ---------------------------------------------------------------------------
# Version: behavior changes in PHP/CSS -> 0.7.142. Revision/state stay frozen.
# ---------------------------------------------------------------------------
replace_once(
    "plugin/gloskin-site-core/gloskin-site-core.php",
    " * Version: 0.7.141",
    " * Version: 0.7.142",
)
replace_once(
    "plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php",
    "\tconst VERSION = '0.7.141';",
    "\tconst VERSION = '0.7.142';",
)

# Keep test expectations version-synchronized without touching production history.
for test in (ROOT / "tests").iterdir():
    if test.is_file() and test.suffix in {".php", ".py", ".sh", ".js"}:
        text = test.read_text(encoding="utf-8")
        if "0.7.141" in text:
            test.write_text(text.replace("0.7.141", "0.7.142"), encoding="utf-8")


# ---------------------------------------------------------------------------
# Doctor ownership: retire the separate admin surface. The deterministic
# importer stays compatibility code and becomes a Final Migration dependency.
# ---------------------------------------------------------------------------
prod = "plugin/gloskin-site-core/includes/class-gloskin-site-core-production-batch.php"
replace_once(
    prod,
    " * Kernel-owned module for Shop discovery, native Contact operations and doctor migration.",
    " * Kernel-owned module for Shop discovery, native Contact operations and final-migration dependencies.",
)
replace_once(
    prod,
    "\t\trequire_once __DIR__ . '/class-gloskin-site-core-doctor-migration-admin.php';\n",
    "\t\t/* Doctor roster importer remains available for Final Migration ownership.\n\t\t * No second doctor-migration admin action is registered here. */\n",
)
replace_once(
    prod,
    "\n\t\t$doctor_admin = new Gloskin_Site_Core_Doctor_Migration_Admin( $plugin_file );\n\t\t$doctor_admin->register();\n\t\tself::$services[] = $doctor_admin;",
    "",
)

# Make Insight ownership explicit without merging it into Final Migration.
insight_admin = "plugin/gloskin-site-core/includes/class-gloskin-site-core-insight-migration-admin.php"
text = read(insight_admin)
needle = "<p>"
if "independen dari Finalisasi Prototype" not in text:
    # Insert immediately after the wrap heading if possible; otherwise after first wrap opening.
    marker = "<div class=\"wrap\">"
    if marker in text:
        text = text.replace(
            marker,
            marker + "\n\t\t\t<p><strong>Ownership:</strong> Import Insight ini independen dari Finalisasi Prototype; paket ini hanya mengelola artikel WordPress, kategori, dan media editorial Insight.</p>",
            1,
        )
    else:
        # Static ownership note still documents the boundary if markup differs.
        text = text.replace(
            "final class Gloskin_Site_Core_Insight_Migration_Admin {",
            "/* Ownership: independen dari Finalisasi Prototype; hanya artikel/kategori/media editorial Insight. */\nfinal class Gloskin_Site_Core_Insight_Migration_Admin {",
            1,
        )
    write(insight_admin, text)


# ---------------------------------------------------------------------------
# Final Migration: same eight persisted checkpoints, but preflight now owns
# deterministic doctor roster preparation. Existing >0 checkpoint states are
# grandfathered by their already-computed exact photo-match snapshot.
# Demo fixtures become unmistakably non-public and are never verification facts.
# ---------------------------------------------------------------------------
fm = "plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php"
replace_once(
    fm,
    "\t\t\t'doctor_matches'      => array(),\n\t\t\t'doctor_audit'        => array(),",
    "\t\t\t'doctor_matches'      => array(),\n\t\t\t'doctor_audit'        => array(),\n\t\t\t'doctor_roster_audit' => array(),",
)
replace_once(
    fm,
    "\t\t$limit = count( $this->steps() ) + 20;",
    "\t\t$limit = count( $this->steps() ) + 40;",
)

old_preflight_case = """\t\t\t\tcase 'preflight':
\t\t\t\t\tif ( 0 !== $index || (int) $state['doctor_cursor'] > 0 || $this->doctor_audit_count( $state['doctor_audit'] ) > 0 ) {
\t\t\t\t\t\tthrow new RuntimeException( 'verification_failed: Preflight tidak boleh diulang setelah mutasi foto dokter dimulai.' );
\t\t\t\t\t}
\t\t\t\t\t$preflight_result             = $this->run_preflight();
\t\t\t\t\t$state['doctor_matches']      = $preflight_result['matches'];
\t\t\t\t\t$state['doctor_all_snapshot'] = $preflight_result['all_snapshot'];
\t\t\t\t\t$state['doctor_cursor']       = 0;
\t\t\t\t\t$state['doctor_audit']        = array();
\t\t\t\t\t$state['commerce_snapshot']   = $this->commerce_page_snapshot();
\t\t\t\t\tbreak;"""
new_preflight_case = """\t\t\t\tcase 'preflight':
\t\t\t\t\tif ( 0 !== $index || (int) $state['doctor_cursor'] > 0 || $this->doctor_audit_count( $state['doctor_audit'] ) > 0 ) {
\t\t\t\t\t\tthrow new RuntimeException( 'verification_failed: Preflight tidak boleh diulang setelah mutasi foto dokter dimulai.' );
\t\t\t\t\t}
\t\t\t\t\t$roster = $this->advance_doctor_roster();
\t\t\t\t\t$state['doctor_roster_audit'] = $roster;
\t\t\t\t\tif ( empty( $roster['complete'] ) ) {
\t\t\t\t\t\t$step_complete = false;
\t\t\t\t\t\t$state['current_step'] = 'Menyiapkan roster dokter (' . (int) $roster['index'] . '/' . (int) $roster['expected'] . ')';
\t\t\t\t\t\tbreak;
\t\t\t\t\t}
\t\t\t\t\t$preflight_result             = $this->run_preflight();
\t\t\t\t\t$state['doctor_matches']      = $preflight_result['matches'];
\t\t\t\t\t$state['doctor_all_snapshot'] = $preflight_result['all_snapshot'];
\t\t\t\t\t$state['doctor_cursor']       = 0;
\t\t\t\t\t$state['doctor_audit']        = array();
\t\t\t\t\t$state['commerce_snapshot']   = $this->commerce_page_snapshot();
\t\t\t\t\tbreak;"""
replace_once(fm, old_preflight_case, new_preflight_case)

# Insert doctor roster helper before run_preflight().
marker = "\n\t/** @return array{matches:array<string,array<string,mixed>>,all_snapshot:array<int,int>} */\n\tprivate function run_preflight() {"
helper = r'''

	/** @return array{status:string,index:int,expected:int,complete:bool,ownership:string} */
	private function advance_doctor_roster() {
		$importer = $this->doctor_roster_importer();
		$before   = $importer->state();
		if ( 'consumed' === (string) $before['status'] ) {
			return array(
				'status' => 'consumed', 'index' => (int) $before['index'], 'expected' => (int) $before['expected'],
				'complete' => true, 'ownership' => 'final-migration-reused-importer',
			);
		}
		$mode = ( (int) $before['index'] > 0 || in_array( (string) $before['status'], array( 'running', 'failed', 'verifying' ), true ) ) ? 'continue' : 'start';
		$after = $importer->advance( $mode );
		return array(
			'status' => (string) $after['status'], 'index' => (int) $after['index'], 'expected' => (int) $after['expected'],
			'complete' => 'consumed' === (string) $after['status'], 'ownership' => 'final-migration-reused-importer',
		);
	}

	/** @return Gloskin_Site_Core_Doctor_Importer */
	private function doctor_roster_importer() {
		require_once __DIR__ . '/class-gloskin-site-core-doctor-bundle.php';
		foreach ( array( 'state', 'upsert', 'finalize', 'lock' ) as $part ) {
			require_once __DIR__ . '/gloskin-site-core-doctor-importer-' . $part . '-trait.php';
		}
		require_once __DIR__ . '/class-gloskin-site-core-doctor-importer.php';
		return new Gloskin_Site_Core_Doctor_Importer( $this->plugin_file );
	}
'''
text = read(fm)
if marker not in text:
    raise SystemExit("final migration run_preflight marker missing")
write(fm, text.replace(marker, helper + marker, 1))

# Replace demo seed and seed_demo_post as one bounded region.
pattern = r"\n\t/\*\* @return array<string,mixed> \*/\n\tprivate function run_demo_seed\(\) \{.*?\n\t\}\n\n\t/\*\* @return array\{action:string,id:int\} \*/\n\tprivate function seed_demo_post\(.*?\n\t\}\n\n\t/\*\* @return array\{doctor_audit:"
replacement = r'''
	/** @return array<string,mixed> */
	private function run_demo_seed() {
		$env    = $this->detect_environment();
		$status = 'draft';
		$audit  = array(
			'environment' => $env,
			'status'      => $status,
			'policy'      => 'engineering-fixture-non-public-v2',
			'created'     => array(),
			'reused'      => array(),
		);

		$seeds = array(
			array( 'type' => 'promo', 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'identity' => 'gloskin-demo-promo-refresh-campaign-2026-1', 'title' => '[DEMO NON-PUBLIC] Promo fixture 1', 'excerpt' => 'Engineering fixture untuk validasi layout. Bukan materi presentasi publik.', 'meta' => array( 'gloskin_promo_eyebrow' => 'Fixture Non-Publik', 'gloskin_promo_cta_label' => 'Fixture', 'gloskin_promo_cta_url' => '/treatments/', 'gloskin_promo_active' => '0' ) ),
			array( 'type' => 'promo', 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'identity' => 'gloskin-demo-promo-refresh-campaign-2026-2', 'title' => '[DEMO NON-PUBLIC] Promo fixture 2', 'excerpt' => 'Engineering fixture untuk validasi layout. Bukan materi presentasi publik.', 'meta' => array( 'gloskin_promo_eyebrow' => 'Fixture Non-Publik', 'gloskin_promo_cta_label' => 'Fixture', 'gloskin_promo_cta_url' => '/skincare/', 'gloskin_promo_active' => '0' ) ),
			array( 'type' => 'promo', 'post_type' => Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE, 'identity' => 'gloskin-demo-promo-refresh-campaign-2026-3', 'title' => '[DEMO NON-PUBLIC] Promo fixture 3', 'excerpt' => 'Engineering fixture untuk validasi layout. Bukan materi presentasi publik.', 'meta' => array( 'gloskin_promo_eyebrow' => 'Fixture Non-Publik', 'gloskin_promo_cta_label' => 'Fixture', 'gloskin_promo_cta_url' => '/doctors/', 'gloskin_promo_active' => '0' ) ),
			array( 'type' => 'testimonial', 'post_type' => Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE, 'identity' => 'gloskin-demo-testimonial-2026-1', 'title' => '[DEMO NON-PUBLIC] Testimonial fixture 1', 'excerpt' => 'Engineering fixture non-publik; tidak memuat atau menyiratkan hasil pasien.', 'meta' => array( 'gloskin_testimonial_attribution' => 'Fixture Non-Publik', 'gloskin_testimonial_subtitle' => 'Engineering fixture', 'gloskin_testimonial_active' => '0' ) ),
			array( 'type' => 'testimonial', 'post_type' => Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE, 'identity' => 'gloskin-demo-testimonial-2026-2', 'title' => '[DEMO NON-PUBLIC] Testimonial fixture 2', 'excerpt' => 'Engineering fixture non-publik; tidak memuat atau menyiratkan hasil pasien.', 'meta' => array( 'gloskin_testimonial_attribution' => 'Fixture Non-Publik', 'gloskin_testimonial_subtitle' => 'Engineering fixture', 'gloskin_testimonial_active' => '0' ) ),
			array( 'type' => 'achievement', 'post_type' => Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE, 'identity' => 'gloskin-demo-achievement-2026-1', 'title' => '[DEMO NON-PUBLIC] Achievement fixture', 'excerpt' => 'Engineering fixture non-publik; bukan klaim penghargaan atau sertifikasi faktual.', 'meta' => array( 'gloskin_achievement_issuer' => 'Fixture Non-Publik', 'gloskin_achievement_year' => '', 'gloskin_achievement_feature_on_home' => '0', 'gloskin_achievement_active' => '0' ) ),
		);

		foreach ( $seeds as $seed ) {
			$result = $this->seed_demo_post( $seed['post_type'], $seed['identity'], $seed['title'], $seed['excerpt'], $status, $seed['meta'] );
			$audit[ $result['action'] ][] = array( 'type' => $seed['type'], 'id' => $result['id'], 'identity' => $seed['identity'] );
		}
		return $audit;
	}

	/** @return array{action:string,id:int} */
	private function seed_demo_post( $post_type, $identity, $title, $excerpt, $status, array $meta ) {
		$existing = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => self::DEMO_IDENTITY_META, 'value' => $identity ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$post_id = ! empty( $existing ) ? absint( $existing[0] ) : 0;
		$action  = $post_id ? 'reused' : 'created';
		$postarr = array( 'post_type' => $post_type, 'post_status' => 'draft', 'post_title' => $title, 'post_excerpt' => $excerpt );
		if ( $post_id ) { $postarr['ID'] = $post_id; }
		$result = $post_id ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );
		if ( is_wp_error( $result ) || ! $result ) {
			throw new RuntimeException( 'Gagal mengarantina demo record ' . $identity . ': ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'unknown error' ) );
		}
		$post_id = absint( $result );
		update_post_meta( $post_id, self::DEMO_IDENTITY_META, $identity );
		update_post_meta( $post_id, self::DEMO_REVISION_META, self::REVISION );
		foreach ( $meta as $key => $value ) { update_post_meta( $post_id, $key, $value ); }
		return array( 'action' => $action, 'id' => $post_id );
	}

	/** @return void */
	private function quarantine_owned_demo_records() {
		foreach ( array(
			Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE => array( 'gloskin_promo_active' ),
			Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE => array( 'gloskin_testimonial_active' ),
			Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE => array( 'gloskin_achievement_active', 'gloskin_achievement_feature_on_home' ),
		) as $post_type => $flags ) {
			$ids = get_posts( array(
				'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
				'meta_query' => array( array( 'key' => self::DEMO_IDENTITY_META, 'compare' => 'EXISTS' ) ),
			) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			foreach ( $ids as $post_id ) {
				$post_id = absint( $post_id );
				if ( '' === (string) get_post_meta( $post_id, self::DEMO_IDENTITY_META, true ) ) { continue; }
				if ( 'draft' !== get_post_status( $post_id ) ) {
					$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ), true );
					if ( is_wp_error( $result ) ) { throw new RuntimeException( 'verification_failed: Gagal mengarantina fixture demo #' . $post_id . '.' ); }
				}
				foreach ( $flags as $flag ) { update_post_meta( $post_id, $flag, '0' ); }
			}
		}
	}

	/** @return array{doctor_audit:'''
regex_once(fm, pattern, replacement)

# Harden run_verify demo semantics and page publish check.
replace_once(
    fm,
    "\tprivate function run_verify( array $state ) {\n\t\t$this->editorial_media_service()->verify( (array) ( $state['editorial_audit'] ?? array() ) );",
    "\tprivate function run_verify( array $state ) {\n\t\t$this->quarantine_owned_demo_records();\n\t\t$this->editorial_media_service()->verify( (array) ( $state['editorial_audit'] ?? array() ) );",
)
replace_once(
    fm,
    "\t\tif ( ! ( $promo_page instanceof WP_Post ) || 'trash' === $promo_page->post_status ) { throw new RuntimeException( 'verification_failed: Halaman /promo/ tidak ditemukan.' ); }",
    "\t\tif ( ! ( $promo_page instanceof WP_Post ) || 'publish' !== $promo_page->post_status ) { throw new RuntimeException( 'verification_failed: Halaman /promo/ harus published.' ); }",
)
regex_once(
    fm,
    r"\n\t\t\$demo_audit = \(array\) \( \$state\['demo_audit'\] \?\? array\(\) \);.*?\n\t\tif \( \$promo_count < 1 \|\| \$test_count < 1 \|\| \$ach_count < 1 \) \{ throw new RuntimeException\( 'verification_failed: Demo seed tidak lengkap\.' \); \}",
    r'''
		$demo_audit = (array) ( $state['demo_audit'] ?? array() );
		$demo_items = array_merge( (array) ( $demo_audit['created'] ?? array() ), (array) ( $demo_audit['reused'] ?? array() ) );
		foreach ( $demo_items as $item ) {
			$post_id = absint( $item['id'] ?? 0 );
			if ( ! $post_id || '' === (string) get_post_meta( $post_id, self::DEMO_IDENTITY_META, true ) || 'draft' !== get_post_status( $post_id ) ) {
				throw new RuntimeException( 'verification_failed: Engineering fixture harus dimiliki migrasi dan non-publik.' );
			}
		}''',
)
# Verify roster ownership proof for new runs or legacy exact preflight snapshots.
insert_before = "\t\t$matches     = (array) ( $state['doctor_matches'] ?? array() );"
roster_verify = """\t\t$roster_audit = (array) ( $state['doctor_roster_audit'] ?? array() );
\t\tif ( empty( $roster_audit['complete'] ) && 'legacy-final-preflight' !== (string) ( $roster_audit['ownership'] ?? '' ) ) {
\t\t\tthrow new RuntimeException( 'verification_failed: Doctor roster ownership belum terselesaikan.' );
\t\t}

"""
replace_once(fm, insert_before, roster_verify + insert_before)

# Resume compatibility: old >0 states retain their exact photo-match proof.
old_reconcile_head = """\tprivate function reconcile_resume_checkpoint( array $state ) {
\t\t$index = (int) ( $state['next_step_index'] ?? 0 );
\t\t$rewind = null;"""
new_reconcile_head = """\tprivate function reconcile_resume_checkpoint( array $state ) {
\t\t$index = (int) ( $state['next_step_index'] ?? 0 );
\t\t$rewind = null;
\t\tif ( $index > 0 && empty( $state['doctor_roster_audit'] ) ) {
\t\t\tif ( ! empty( $state['doctor_matches'] ) ) {
\t\t\t\t$state['doctor_roster_audit'] = array(
\t\t\t\t\t'status' => 'legacy-preflight-compatible', 'index' => count( (array) $state['doctor_matches'] ),
\t\t\t\t\t'expected' => count( (array) $state['doctor_matches'] ), 'complete' => true,
\t\t\t\t\t'ownership' => 'legacy-final-preflight',
\t\t\t\t);
\t\t\t} elseif ( 0 === (int) $state['doctor_cursor'] && 0 === $this->doctor_audit_count( $state['doctor_audit'] ?? array() ) ) {
\t\t\t\t$rewind = 0;
\t\t\t} else {
\t\t\t\tthrow new RuntimeException( 'verification_failed: Doctor roster ownership cannot be reconstructed after photo mutation; manual staging review required.' );
\t\t\t}
\t\t}"""
replace_once(fm, old_reconcile_head, new_reconcile_head)

# Include roster progress in response without changing persisted checkpoint indices.
replace_once(
    fm,
    "\t\t$audit = $this->normalize_doctor_audit( $state['doctor_audit'] ?? array() );\n\t\treturn array(",
    "\t\t$audit = $this->normalize_doctor_audit( $state['doctor_audit'] ?? array() );\n\t\t$roster = (array) ( $state['doctor_roster_audit'] ?? array() );\n\t\treturn array(",
)
replace_once(
    fm,
    "\t\t\t'doctor_reused' => count( $audit['reused'] ),",
    "\t\t\t'doctor_reused' => count( $audit['reused'] ),\n\t\t\t'doctor_roster_status' => (string) ( $roster['status'] ?? '' ),\n\t\t\t'doctor_roster_index' => (int) ( $roster['index'] ?? 0 ),\n\t\t\t'doctor_roster_expected' => (int) ( $roster['expected'] ?? 0 ),",
)


# ---------------------------------------------------------------------------
# Canonical page status: publish only migration-owned provisioned pages;
# editor-owned non-public canonical pages safe-stop. Verification requires publish.
# ---------------------------------------------------------------------------
ia = "plugin/gloskin-site-core/includes/class-gloskin-site-core-final-ia-normalizer.php"
replace_once(
    ia,
    "\t\t\tif ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type || 'trash' === $page->post_status ) {\n\t\t\t\tthrow new RuntimeException( 'verification_failed: IA page invalid: ' . $key . '.' );\n\t\t\t}",
    "\t\t\tif ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {\n\t\t\t\tthrow new RuntimeException( 'verification_failed: Canonical public IA page must be published: ' . $key . '.' );\n\t\t\t}",
)
old_existing = """\t\tif ( $page instanceof WP_Post ) {
\t\t\tif ( 'trash' === $page->post_status ) {
\t\t\t\tthrow new RuntimeException( 'IA page /' . $slug . '/ exists in Trash; ownership is ambiguous.' );
\t\t\t}
\t\t\treturn absint( $page->ID );
\t\t}"""
new_existing = """\t\tif ( $page instanceof WP_Post ) {
\t\t\tif ( 'publish' === (string) $page->post_status ) { return absint( $page->ID ); }
\t\t\t$provisioned = self::REVISION === (string) get_post_meta( $page->ID, '_gloskin_provisioned_revision', true );
\t\t\tif ( $provisioned ) {
\t\t\t\t$result = wp_update_post( array( 'ID' => absint( $page->ID ), 'post_status' => 'publish' ), true );
\t\t\t\tif ( is_wp_error( $result ) || 'publish' !== (string) get_post_status( $page->ID ) ) {
\t\t\t\t\tthrow new RuntimeException( 'Failed to publish migration-provisioned canonical page /' . $slug . '/.' );
\t\t\t\t}
\t\t\t\treturn absint( $page->ID );
\t\t\t}
\t\t\tthrow new RuntimeException( 'Canonical page safe-stop: editor-owned /' . $slug . '/ is ' . (string) $page->post_status . '. Publish it manually before Finalisasi Prototype; content was preserved.' );
\t\t}"""
replace_once(ia, old_existing, new_existing)


# ---------------------------------------------------------------------------
# Managed CPT ordering: query all eligible published records, exclude migration
# demo identities from public presentation, sort explicitly, then slice.
# ---------------------------------------------------------------------------
ts = "plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php"
text = read(ts)
text = text.replace("'posts_per_page' => max( $limit * 4, 40 ),", "'posts_per_page' => -1,")
if text.count("'posts_per_page' => -1,") < 2:
    raise SystemExit("template service: expected both managed CPT queries to be unbounded")
text = text.replace(
    "\t\t/* Fetch a generous pool so we can filter by date and apply explicit order */",
    "\t\t/* Managed CPT datasets are intentionally small: fetch all eligible published records, then filter/sort/slice. */",
    1,
)
# Promo filtering excludes any migration-owned fixture immediately, even before a resumed migration quarantines it.
old_filter = """\t\t$posts = array_values( array_filter( $posts, function ( $post ) use ( $now ) {
\t\t\treturn $this->is_promo_date_eligible( $post->ID, $now );
\t\t} ) );"""
new_filter = """\t\t$posts = array_values( array_filter( $posts, function ( $post ) use ( $now ) {
\t\t\tif ( '' !== (string) get_post_meta( $post->ID, '_gloskin_demo_identity', true ) ) { return false; }
\t\t\treturn $this->is_promo_date_eligible( $post->ID, $now );
\t\t} ) );"""
if old_filter not in text:
    raise SystemExit("promo filter target missing")
text = text.replace(old_filter, new_filter, 1)
# Replace promo comparator with shared deterministic comparator.
old_promo_sort = """\t\tusort( $posts, function ( $a, $b ) {
\t\t\t$ao = (int) get_post_meta( $a->ID, 'gloskin_promo_order', true );
\t\t\t$bo = (int) get_post_meta( $b->ID, 'gloskin_promo_order', true );
\t\t\t$ah = $ao > 0;
\t\t\t$bh = $bo > 0;
\t\t\tif ( $ah && ! $bh ) { return -1; }
\t\t\tif ( ! $ah && $bh ) { return 1; }
\t\t\tif ( $ao !== $bo ) { return $ao <=> $bo; }
\t\t\treturn strcmp( (string) $a->post_title, (string) $b->post_title );
\t\t} );"""
new_promo_sort = """\t\tusort( $posts, function ( $a, $b ) {
\t\t\treturn $this->compare_managed_posts( $a, $b, 'gloskin_promo_order' );
\t\t} );"""
if old_promo_sort not in text:
    raise SystemExit("promo sort target missing")
text = text.replace(old_promo_sort, new_promo_sort, 1)
# Published records: exclude migration demo after DB eligibility but before sort/slice.
needle = """\t\t$posts = get_posts( array(
\t\t\t'post_type'      => $post_type,
\t\t\t'post_status'    => 'publish',
\t\t\t'posts_per_page' => -1,
\t\t\t'orderby'        => 'ID',
\t\t\t'order'          => 'ASC',
\t\t\t'meta_query'     => $meta_query,
\t\t) );

\t\t/* Sort by explicit order meta — blank/zero sorts last */"""
replacement2 = """\t\t$posts = get_posts( array(
\t\t\t'post_type'      => $post_type,
\t\t\t'post_status'    => 'publish',
\t\t\t'posts_per_page' => -1,
\t\t\t'orderby'        => 'ID',
\t\t\t'order'          => 'ASC',
\t\t\t'meta_query'     => $meta_query,
\t\t) );
\t\t$posts = array_values( array_filter( $posts, static function ( $post ) {
\t\t\treturn '' === (string) get_post_meta( $post->ID, '_gloskin_demo_identity', true );
\t\t} ) );

\t\t/* Sort by explicit order meta — blank/zero sorts last */"""
if needle not in text:
    raise SystemExit("published managed query target missing")
text = text.replace(needle, replacement2, 1)
old_pub_sort = """\t\tif ( '' !== $order_meta_key ) {
\t\t\tusort( $posts, function ( $a, $b ) use ( $order_meta_key ) {
\t\t\t\t$ao = (int) get_post_meta( $a->ID, $order_meta_key, true );
\t\t\t\t$bo = (int) get_post_meta( $b->ID, $order_meta_key, true );
\t\t\t\t$ah = $ao > 0;
\t\t\t\t$bh = $bo > 0;
\t\t\t\tif ( $ah && ! $bh ) { return -1; }
\t\t\t\tif ( ! $ah && $bh ) { return 1; }
\t\t\t\tif ( $ao !== $bo ) { return $ao <=> $bo; }
\t\t\t\treturn strcmp( (string) $a->post_title, (string) $b->post_title );
\t\t\t} );
\t\t}"""
new_pub_sort = """\t\tusort( $posts, function ( $a, $b ) use ( $order_meta_key ) {
\t\t\treturn $this->compare_managed_posts( $a, $b, $order_meta_key );
\t\t} );"""
if old_pub_sort not in text:
    raise SystemExit("published managed sort target missing")
text = text.replace(old_pub_sort, new_pub_sort, 1)
# Add shared comparator before consultation_context.
marker = "\n\t/** @return array{paths:array<int,array<string,mixed>>,products:array<int,array<string,mixed>>,disclaimer:string} */\n\tprivate function consultation_context() {"
comparator = r'''

	/** @return int */
	private function compare_managed_posts( $a, $b, $order_meta_key ) {
		$ao = '' !== (string) $order_meta_key ? (int) get_post_meta( $a->ID, $order_meta_key, true ) : 0;
		$bo = '' !== (string) $order_meta_key ? (int) get_post_meta( $b->ID, $order_meta_key, true ) : 0;
		$ah = $ao > 0;
		$bh = $bo > 0;
		if ( $ah && ! $bh ) { return -1; }
		if ( ! $ah && $bh ) { return 1; }
		if ( $ao !== $bo ) { return $ao <=> $bo; }
		$title_cmp = strcmp( (string) $a->post_title, (string) $b->post_title );
		return 0 !== $title_cmp ? $title_cmp : ( (int) $a->ID <=> (int) $b->ID );
	}
'''
if marker not in text:
    raise SystemExit("consultation marker missing")
text = text.replace(marker, comparator + marker, 1)
write(ts, text)


# ---------------------------------------------------------------------------
# Factual zero-placeholder: doctor/clinic/product degrade to text-first.
# Generic editorial roles still use the local first-party catalog and retain
# abstract CSS only as catastrophic technical resilience.
# ---------------------------------------------------------------------------
helpers = "plugin/gloskin-site-core/templates/parts/template-helpers.php"
text = read(helpers)
text = text.replace(
    " * Render deterministic abstract Gloskin media for genuine factual empty states.\n *\n * This neutral composition must remain the fallback when a specific doctor,\n * clinic or WooCommerce product has no factual WordPress-owned image.",
    " * Render deterministic abstract Gloskin media only as catastrophic generic editorial resilience.\n *\n * Factual doctor, clinic and WooCommerce product identity states are text-first\n * when their WordPress/Woo-owned image is unavailable; they must never use this renderer.",
    1,
)
text = text.replace(
    "\t\t$kind    = in_array( $kind, $allowed, true ) ? $kind : 'editorial';",
    "\t\t$kind    = in_array( $kind, $allowed, true ) ? $kind : 'editorial';\n\t\tif ( in_array( $kind, array( 'doctor', 'clinic', 'product' ), true ) ) { return; }",
    1,
)
# Editorial renderer: explicit semantic alt from manifest; factual kinds return no media.
old_attrs = """\tfunction gloskin_ui1_render_editorial_media( $kind = 'editorial', $seed = 'gloskin', $class = '', $eager = false ) {
\t\t$resolved = gloskin_ui1_resolve_editorial_media( $kind, $seed );
\t\t$attachment_id = absint( $resolved['attachment_id'] ?? 0 );
\t\tif ( $attachment_id ) {
\t\t\t$attrs = array( 'class' => trim( (string) $class ), 'decoding' => 'async', 'loading' => $eager ? 'eager' : 'lazy' );
\t\t\tif ( $eager ) { $attrs['fetchpriority'] = 'high'; }
\t\t\t$image = wp_get_attachment_image( $attachment_id, 'large', false, $attrs );
\t\t\tif ( is_string( $image ) && '' !== $image ) { echo $image; return; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
\t\t}
\t\tgloskin_ui1_render_presentation_media( $kind, $seed, $class );
\t}"""
new_attrs = """\tfunction gloskin_ui1_render_editorial_media( $kind = 'editorial', $seed = 'gloskin', $class = '', $eager = false ) {
\t\tif ( in_array( (string) $kind, array( 'doctor', 'clinic', 'product' ), true ) ) { return; }
\t\t$resolved = gloskin_ui1_resolve_editorial_media( $kind, $seed );
\t\t$attachment_id = absint( $resolved['attachment_id'] ?? 0 );
\t\tif ( $attachment_id ) {
\t\t\t$decorative = ! empty( $resolved['decorative'] );
\t\t\t$alt = $decorative ? '' : trim( (string) ( $resolved['alt'] ?? '' ) );
\t\t\t$attrs = array( 'class' => trim( (string) $class ), 'decoding' => 'async', 'loading' => $eager ? 'eager' : 'lazy', 'alt' => $alt );
\t\t\tif ( $eager ) { $attrs['fetchpriority'] = 'high'; }
\t\t\t$image = wp_get_attachment_image( $attachment_id, 'large', false, $attrs );
\t\t\tif ( is_string( $image ) && '' !== $image ) { echo $image; return; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
\t\t}
\t\t/* Catastrophic editorial resilience only: successful migration verifies local catalog ownership. */
\t\tgloskin_ui1_render_presentation_media( $kind, $seed, $class );
\t}"""
if old_attrs not in text:
    raise SystemExit("editorial renderer target missing")
text = text.replace(old_attrs, new_attrs, 1)

# Generic card factual media block.
old_card_media = """\t\t?>
\t\t<article class=\"gloskin-ui1-card gloskin-ui1-card--<?php echo esc_attr( $kind ); ?>\">
\t\t\t<?php if ( '' !== $url ) : ?><a class=\"gloskin-ui1-card__media\" href=\"<?php echo esc_url( $url ); ?>\" tabindex=\"-1\" aria-hidden=\"true\"><?php endif; ?>
\t\t\t\t<?php if ( $image_id ) : ?>
\t\t\t\t\t<?php echo wp_get_attachment_image( $image_id, 'medium_large', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-card__image' ) ); ?>
\t\t\t\t<?php elseif ( in_array( $kind, array( 'clinic', 'doctor' ), true ) ) : ?>
\t\t\t\t\t<?php gloskin_ui1_render_presentation_media( $kind, $title, 'gloskin-ui1-card__abstract' ); ?>
\t\t\t\t<?php else : ?>
\t\t\t\t\t<?php gloskin_ui1_render_editorial_media( 'treatment' === $kind ? 'treatment' : 'insight', $title, 'gloskin-ui1-card__image gloskin-ui1-card__image--editorial' ); ?>
\t\t\t\t<?php endif; ?>
\t\t\t<?php if ( '' !== $url ) : ?></a><?php endif; ?>"""
new_card_media = """\t\t$identity_without_media = ! $image_id && in_array( $kind, array( 'clinic', 'doctor' ), true );
\t\t$article_classes = 'gloskin-ui1-card gloskin-ui1-card--' . $kind . ( $identity_without_media ? ' gloskin-ui1-card--text-first' : '' );
\t\t?>
\t\t<article class=\"<?php echo esc_attr( $article_classes ); ?>\">
\t\t\t<?php if ( $image_id || ! $identity_without_media ) : ?>
\t\t\t\t<?php if ( '' !== $url ) : ?><a class=\"gloskin-ui1-card__media\" href=\"<?php echo esc_url( $url ); ?>\" tabindex=\"-1\" aria-hidden=\"true\"><?php endif; ?>
\t\t\t\t\t<?php if ( $image_id ) : ?>
\t\t\t\t\t\t<?php echo wp_get_attachment_image( $image_id, 'medium_large', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-card__image', 'alt' => $title ) ); ?>
\t\t\t\t\t<?php else : ?>
\t\t\t\t\t\t<?php gloskin_ui1_render_editorial_media( 'treatment' === $kind ? 'treatment' : 'insight', $title, 'gloskin-ui1-card__image gloskin-ui1-card__image--editorial' ); ?>
\t\t\t\t\t<?php endif; ?>
\t\t\t\t<?php if ( '' !== $url ) : ?></a><?php endif; ?>
\t\t\t<?php endif; ?>"""
if old_card_media not in text:
    raise SystemExit("generic card media target missing")
text = text.replace(old_card_media, new_card_media, 1)

# Consultation product: only factual image, otherwise text-first with no media shell.
text = text.replace(
    '<article class="gloskin-ui1-card gloskin-ui1-card--product gloskin-ui1-card--consultation">',
    '<article class="gloskin-ui1-card gloskin-ui1-card--product gloskin-ui1-card--consultation<?php echo $image_id ? \'\' : \' gloskin-ui1-card--text-first\'; ?>">',
    1,
)
old_consult_media = """\t\t\t\t\t<span class=\"gloskin-ui1-consultation-card__main\">
\t\t\t\t\t\t<span class=\"gloskin-ui1-consultation-card__media\">
\t\t\t\t\t\t\t<?php if ( $image_id ) : ?>
\t\t\t\t\t\t\t\t<?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-consultation-card__image' ) ); ?>
\t\t\t\t\t\t\t<?php else : ?>
\t\t\t\t\t\t\t\t<?php gloskin_ui1_render_editorial_media( 'treatment', $name, 'gloskin-ui1-consultation-card__image gloskin-ui1-consultation-card__image--decorative' ); ?>
\t\t\t\t\t\t\t<?php endif; ?>
\t\t\t\t\t\t</span>"""
new_consult_media = """\t\t\t\t\t<span class=\"gloskin-ui1-consultation-card__main\">
\t\t\t\t\t\t<?php if ( $image_id ) : ?>
\t\t\t\t\t\t\t<span class=\"gloskin-ui1-consultation-card__media\"><?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-consultation-card__image', 'alt' => $name ) ); ?></span>
\t\t\t\t\t\t<?php endif; ?>"""
if old_consult_media not in text:
    raise SystemExit("consultation media target missing")
text = text.replace(old_consult_media, new_consult_media, 1)

# Catalog product media/wishlist.
text = text.replace(
    '<article class="gloskin-ui1-card gloskin-ui1-card--product">',
    '<article class="gloskin-ui1-card gloskin-ui1-card--product<?php echo $image_id ? \'\' : \' gloskin-ui1-card--text-first\'; ?>">',
    1,
)
old_product_media = """\t\t\t<div class=\"gloskin-ui1-card__media-wrap\">
\t\t\t\t<a class=\"gloskin-ui1-card__media\" href=\"<?php echo esc_url( $url ); ?>\" tabindex=\"-1\" aria-hidden=\"true\">
\t\t\t\t\t<?php if ( $image_id ) : ?>
\t\t\t\t\t\t<?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-card__image' ) ); ?>
\t\t\t\t\t<?php else : ?>
\t\t\t\t\t\t<?php gloskin_ui1_render_presentation_media( 'product', $name, 'gloskin-ui1-card__abstract' ); ?>
\t\t\t\t\t<?php endif; ?>
\t\t\t\t</a>
\t\t\t\t<?php gloskin_ui1_render_wishlist_toggle( $id, $name ); ?>
\t\t\t</div>"""
new_product_media = """\t\t\t<?php if ( $image_id ) : ?>
\t\t\t\t<div class=\"gloskin-ui1-card__media-wrap\">
\t\t\t\t\t<a class=\"gloskin-ui1-card__media\" href=\"<?php echo esc_url( $url ); ?>\" tabindex=\"-1\" aria-hidden=\"true\"><?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'class' => 'gloskin-ui1-card__image', 'alt' => $name ) ); ?></a>
\t\t\t\t\t<?php gloskin_ui1_render_wishlist_toggle( $id, $name ); ?>
\t\t\t\t</div>
\t\t\t<?php else : ?>
\t\t\t\t<div class=\"gloskin-ui1-card__text-first-utility\"><?php gloskin_ui1_render_wishlist_toggle( $id, $name ); ?></div>
\t\t\t<?php endif; ?>"""
if old_product_media not in text:
    raise SystemExit("product media target missing")
text = text.replace(old_product_media, new_product_media, 1)
write(helpers, text)

# Text-first geometry: small, scoped additions only; no Header/loader selectors touched.
core_css = "plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css"
text = read(core_css)
addition = """

/* Factual identity cards without factual media intentionally become text-first. */
.gloskin-ui1-card--text-first .gloskin-ui1-card__body{padding-top:clamp(20px,3vw,30px)}
.gloskin-ui1-card__text-first-utility{position:relative;min-height:56px}
"""
if "gloskin-ui1-card__text-first-utility" not in text:
    write(core_css, text.rstrip() + addition + "\n")
consult_css = "plugin/gloskin-site-core/assets/css/gloskin-ui1-consultation.css"
text = read(consult_css)
addition = "\n.gloskin-ui1-card--consultation.gloskin-ui1-card--text-first .gloskin-ui1-consultation-card__main{grid-template-columns:minmax(0,1fr)}\n"
if "gloskin-ui1-card--consultation.gloskin-ui1-card--text-first" not in text:
    write(consult_css, text.rstrip() + addition)


# ---------------------------------------------------------------------------
# Home brand story remains structurally present when Home post_content is blank.
# ---------------------------------------------------------------------------
home = "plugin/gloskin-site-core/templates/pages/home.php"
old_brand = """<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?>
<section class=\"gloskin-ui1-section gloskin-ui1-home-brand-story\" data-gloskin-section=\"home-brand-story\"><div class=\"gloskin-ui1-container gloskin-ui1-home-brand-story__grid\">
\t<div class=\"gloskin-ui1-home-brand-story__content\"><p class=\"gloskin-ui1-eyebrow\"><?php echo esc_html__( 'Tentang Gloskin', 'gloskin-site-core' ); ?></p><?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?></div>
\t<div class=\"gloskin-ui1-home-brand-story__media\"><?php gloskin_ui1_render_editorial_media( 'editorial', 'home_brand_story', 'gloskin-ui1-home-brand-story__image' ); ?></div>
</div></section>
<?php endif; ?>"""
new_brand = """<section class=\"gloskin-ui1-section gloskin-ui1-home-brand-story\" data-gloskin-section=\"home-brand-story\"><div class=\"gloskin-ui1-container gloskin-ui1-home-brand-story__grid\">
\t<div class=\"gloskin-ui1-home-brand-story__content\">
\t\t<p class=\"gloskin-ui1-eyebrow\"><?php echo esc_html__( 'Tentang Gloskin', 'gloskin-site-core' ); ?></p>
\t\t<?php if ( gloskin_ui1_has_content( $gloskin_context['page'] ) ) : ?>
\t\t\t<?php gloskin_ui1_render_page_content( $gloskin_context['page'] ); ?>
\t\t<?php else : ?>
\t\t\t<p><?php echo esc_html__( 'Kenali Gloskin melalui informasi perawatan, skincare, dokter, dan jaringan klinik yang tersedia di kanal resmi kami.', 'gloskin-site-core' ); ?></p>
\t\t\t<p><a class=\"gloskin-ui1-text-link\" href=\"<?php echo esc_url( home_url( '/about/' ) ); ?>\"><?php echo esc_html__( 'Tentang Gloskin', 'gloskin-site-core' ); ?> →</a></p>
\t\t<?php endif; ?>
\t</div>
\t<div class=\"gloskin-ui1-home-brand-story__media\"><?php gloskin_ui1_render_editorial_media( 'editorial', 'home_brand_story', 'gloskin-ui1-home-brand-story__image' ); ?></div>
</div></section>"""
replace_once(home, old_brand, new_brand)


# ---------------------------------------------------------------------------
# Editorial package hygiene + accessibility. Optimize only large assets where
# q82 WebP is substantially smaller AND a conservative PSNR gate passes.
# Dimensions are never changed/upscaled.
# ---------------------------------------------------------------------------
try:
    from PIL import Image, ImageChops
except Exception as exc:  # pragma: no cover - executor installs Pillow.
    raise SystemExit(f"Pillow unavailable: {exc}")

manifest_path = p("plugin/gloskin-site-core/migration-runtime/gloskin-editorial-media-v1/manifest.json")
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
media_dir = manifest_path.parent
alt_map = {
    "home_why": "Perawatan Gloskin",
    "home_brand_story": "Gloskin",
    "treatment_discovery": "Perawatan di Gloskin",
    "treatment_clinical": "Prosedur perawatan di Gloskin",
    "skincare_editorial": "Skincare Gloskin",
    "about_story": "Gloskin",
}

def rgb_psnr(a: Image.Image, b: Image.Image) -> float:
    aa = a.convert("RGB")
    bb = b.convert("RGB")
    if aa.size != bb.size:
        return 0.0
    diff = ImageChops.difference(aa, bb)
    hist = diff.histogram()
    pixels = aa.size[0] * aa.size[1] * 3
    sse = sum(((i % 256) ** 2) * n for i, n in enumerate(hist))
    if sse == 0:
        return 99.0
    mse = sse / pixels
    return 20.0 * math.log10(255.0 / math.sqrt(mse))

for item in manifest.get("items", []):
    key = str(item.get("key", ""))
    path = media_dir / Path(str(item["file"])).name
    if not path.is_file():
        raise SystemExit(f"editorial asset missing: {path}")
    original_bytes = path.stat().st_size
    with Image.open(path) as source:
        source.load()
        width, height = source.size
        fmt = (source.format or "").upper()
        mime = Image.MIME.get(fmt, "application/octet-stream")
        accepted = False
        psnr = None
        if original_bytes >= 350_000 and fmt in {"PNG", "JPEG"}:
            candidate = path.with_suffix(".webp")
            save_img = source.convert("RGBA") if "A" in source.getbands() else source.convert("RGB")
            save_img.save(candidate, "WEBP", quality=82, method=6)
            with Image.open(candidate) as encoded:
                encoded.load()
                psnr = rgb_psnr(source, encoded)
                same_size = encoded.size == source.size
            candidate_bytes = candidate.stat().st_size
            if same_size and psnr >= 40.0 and candidate_bytes <= int(original_bytes * 0.78):
                path.unlink()
                path = candidate
                accepted = True
                fmt = "WEBP"
                mime = "image/webp"
            else:
                candidate.unlink(missing_ok=True)
        item["file"] = path.name
        item["width"] = int(width)
        item["height"] = int(height)
        item["mime"] = mime
        item["bytes"] = path.stat().st_size
        item["semantic_role"] = str(item.get("role", ""))
        item["source_type"] = "first-party-gloskin"
        item["decorative"] = False
        item["alt"] = alt_map.get(key, "Gloskin")
        item["sha256"] = hashlib.sha256(path.read_bytes()).hexdigest()
        item["optimization"] = {
            "method": "webp-q82-psnr40" if accepted else "source-retained",
            "original_bytes": original_bytes,
            "optimized_bytes": path.stat().st_size,
            "psnr_db": round(psnr, 2) if accepted and psnr is not None else None,
            "dimensions_preserved": True,
        }
manifest["metadata_policy"] = "first-party-local; dimensions/mime/bytes/provenance/semantic-alt explicit"
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

# Editorial importer validates/package-projects the richer manifest contract.
media_php = "plugin/gloskin-site-core/includes/class-gloskin-site-core-editorial-media-bundle.php"
text = read(media_php)
needle = """\t\t\t$actual = hash_file( 'sha256', $path );
\t\t\tif ( ! is_string( $actual ) || ! hash_equals( $sha, strtolower( $actual ) ) ) {
\t\t\t\tthrow new RuntimeException( 'bundle_invalid: Editorial media SHA mismatch: ' . $key );
\t\t\t}
\t\t\tif ( isset( $seen[ $key ] ) ) { throw new RuntimeException( 'bundle_invalid: Duplicate editorial media key: ' . $key ); }"""
replacement_meta = """\t\t\t$actual = hash_file( 'sha256', $path );
\t\t\tif ( ! is_string( $actual ) || ! hash_equals( $sha, strtolower( $actual ) ) ) {
\t\t\t\tthrow new RuntimeException( 'bundle_invalid: Editorial media SHA mismatch: ' . $key );
\t\t\t}
\t\t\t$image_info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- validation handles false explicitly.
\t\t\t$width = (int) ( $item['width'] ?? 0 ); $height = (int) ( $item['height'] ?? 0 ); $mime = (string) ( $item['mime'] ?? '' );
\t\t\tif ( false === $image_info || $width < 1 || $height < 1 || $width !== (int) $image_info[0] || $height !== (int) $image_info[1] || $mime !== (string) ( $image_info['mime'] ?? '' ) ) {
\t\t\t\tthrow new RuntimeException( 'bundle_invalid: Editorial media dimensions/mime mismatch: ' . $key );
\t\t\t}
\t\t\tif ( empty( $item['semantic_role'] ) || empty( $item['source_page'] ) || empty( $item['source_asset_url'] ) || 'first-party-gloskin' !== (string) ( $item['source_type'] ?? '' ) || ! array_key_exists( 'decorative', $item ) || ! array_key_exists( 'alt', $item ) ) {
\t\t\t\tthrow new RuntimeException( 'bundle_invalid: Editorial media semantic/provenance metadata incomplete: ' . $key );
\t\t\t}
\t\t\tif ( empty( $item['decorative'] ) && '' === trim( (string) $item['alt'] ) ) {
\t\t\t\tthrow new RuntimeException( 'bundle_invalid: Meaningful editorial media requires concise alt text: ' . $key );
\t\t\t}
\t\t\tif ( isset( $seen[ $key ] ) ) { throw new RuntimeException( 'bundle_invalid: Duplicate editorial media key: ' . $key ); }"""
if needle not in text:
    raise SystemExit("editorial preflight SHA target missing")
text = text.replace(needle, replacement_meta, 1)
old_catalog_tail = """\t\t\t\t'source_page' => esc_url_raw( (string) ( $item['source_page'] ?? '' ) ),
\t\t\t\t'source_asset_url' => esc_url_raw( (string) ( $item['source_asset_url'] ?? '' ) ),
\t\t\t);"""
new_catalog_tail = """\t\t\t\t'source_page' => esc_url_raw( (string) ( $item['source_page'] ?? '' ) ),
\t\t\t\t'source_asset_url' => esc_url_raw( (string) ( $item['source_asset_url'] ?? '' ) ),
\t\t\t\t'width' => absint( $item['width'] ?? 0 ), 'height' => absint( $item['height'] ?? 0 ),
\t\t\t\t'mime' => sanitize_mime_type( (string) ( $item['mime'] ?? '' ) ),
\t\t\t\t'semantic_role' => sanitize_text_field( (string) ( $item['semantic_role'] ?? '' ) ),
\t\t\t\t'source_type' => sanitize_key( (string) ( $item['source_type'] ?? '' ) ),
\t\t\t\t'decorative' => ! empty( $item['decorative'] ),
\t\t\t\t'alt' => sanitize_text_field( (string) ( $item['alt'] ?? '' ) ),
\t\t\t);"""
if old_catalog_tail not in text:
    raise SystemExit("editorial catalog target missing")
text = text.replace(old_catalog_tail, new_catalog_tail, 1)
# Store semantic alt at attachment level too.
needle = "\t\tupdate_post_meta( $attachment_id, self::REVISION_META, self::REVISION );\n\t\treturn $attachment_id;"
new = "\t\tupdate_post_meta( $attachment_id, self::REVISION_META, self::REVISION );\n\t\tupdate_post_meta( $attachment_id, '_wp_attachment_image_alt', ! empty( $item['decorative'] ) ? '' : sanitize_text_field( (string) ( $item['alt'] ?? '' ) ) );\n\t\treturn $attachment_id;"
if needle not in text:
    raise SystemExit("editorial attachment alt target missing")
text = text.replace(needle, new, 1)
write(media_php, text)


# ---------------------------------------------------------------------------
# Update superseded presentation contract and add hardening regressions.
# ---------------------------------------------------------------------------
cp = "tests/check-presentation.sh"
text = read(cp)
old = """if ! grep -q \"array( 'clinic', 'doctor' )\" \"$helpers\" \\
  || ! grep -q \"gloskin_ui1_render_presentation_media( 'product'\" \"$helpers\" \\
  || ! grep -q \"gloskin_ui1_render_presentation_media( 'doctor'\" \"$templates/pages/doctor.php\" \\
  || ! grep -q \"gloskin_ui1_render_presentation_media( 'clinic'\" \"$templates/pages/clinic.php\"; then
  echo \"safe factual doctor/clinic/product empty-state boundary missing\" >&2
  exit 1
fi"""
new = """if ! grep -q \"gloskin-ui1-card--text-first\" \"$helpers\" \\
  || grep -q \"gloskin_ui1_render_presentation_media( 'product'\" \"$helpers\" \\
  || grep -q \"gloskin_ui1_render_presentation_media( 'doctor'\" \"$templates/pages/doctor.php\" \\
  || grep -q \"gloskin_ui1_render_presentation_media( 'clinic'\" \"$templates/pages/clinic.php\"; then
  echo \"factual doctor/clinic/product empty states must be text-first with no abstract placeholder\" >&2
  exit 1
fi"""
if old not in text:
    raise SystemExit("check-presentation factual fallback block missing")
text = text.replace(old, new, 1)
write(cp, text)

hardening_test = r'''<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$fail = 0;
function gh_ok( bool $ok, string $message ): void { global $fail; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n"; if ( ! $ok ) { $fail++; } }
function gh_read( string $rel ): string { global $root; return (string) file_get_contents( $root . '/' . $rel ); }
$migration = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php');
$ia = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-final-ia-normalizer.php');
$template = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php');
$helpers = gh_read('plugin/gloskin-site-core/templates/parts/template-helpers.php');
$home = gh_read('plugin/gloskin-site-core/templates/pages/home.php');
$prod = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-production-batch.php');
$insight = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-insight-migration-admin.php');
$kernel = gh_read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php');
$plugin = gh_read('plugin/gloskin-site-core/gloskin-site-core.php');
$manifest = json_decode( gh_read('plugin/gloskin-site-core/migration-runtime/gloskin-editorial-media-v1/manifest.json'), true );

gh_ok( str_contains( $kernel, "const VERSION = '0.7.142'" ) && str_contains( $plugin, 'Version: 0.7.142' ), 'version bumped to 0.7.142' );
gh_ok( str_contains( $migration, "const REVISION       = '2026-08-19-final'" ), 'REVISION unchanged' );
gh_ok( str_contains( $migration, "const STATE_OPTION   = 'gloskin_site_core_revision_20260819f_state'" ), 'STATE_OPTION unchanged' );
$steps = array( 'preflight','managed_content','demo_seed','doctor_photos','normalize','cleanup','verify','finalize' );
$positions = array_map( static fn($s) => strpos( $migration, "'key' => '" . $s . "'" ), $steps ); $sorted = $positions; sort($sorted);
gh_ok( ! in_array(false,$positions,true) && $positions === $sorted, 'eight final checkpoint order unchanged' );

gh_ok( ! str_contains( $migration, 'Pengguna Demo' ) && ! str_contains( $migration, 'kondisi kulit saya membaik' ), 'synthetic patient/outcome wording removed' );
gh_ok( str_contains( $migration, "'policy'      => 'engineering-fixture-non-public-v2'" ) && str_contains( $migration, "'post_status' => 'draft'" ), 'demo fixtures explicitly non-public' );
gh_ok( ! str_contains( $migration, 'Demo seed tidak lengkap' ) && str_contains( $migration, 'quarantine_owned_demo_records' ), 'verify no longer depends on fake testimonial/achievement completeness' );
gh_ok( substr_count( $template, "'_gloskin_demo_identity'" ) >= 2, 'public managed record queries exclude demo identities' );

gh_ok( str_contains( $ia, "'publish' === (string) $page->post_status" ) && str_contains( $ia, "'_gloskin_provisioned_revision'" ), 'canonical page differentiates published and migration-provisioned ownership' );
gh_ok( str_contains( $ia, 'Canonical page safe-stop: editor-owned /' ) && str_contains( $ia, "'publish' !== $page->post_status" ), 'editor-owned non-public canonical page safe-stops and verify requires publish' );

gh_ok( ! str_contains( $prod, 'Gloskin_Site_Core_Doctor_Migration_Admin' ) && str_contains( $migration, 'advance_doctor_roster' ) && str_contains( $migration, 'Gloskin_Site_Core_Doctor_Importer' ), 'Final Migration owns/reuses doctor roster importer; second admin retired' );
gh_ok( str_contains( $migration, 'legacy-final-preflight' ), 'existing >0 Final Migration states retain compatibility proof' );
gh_ok( str_contains( $insight, 'independen dari Finalisasi Prototype' ), 'Insight Migration ownership documented as independent' );

gh_ok( ! str_contains( $template, 'max( $limit * 4, 40 )' ) && substr_count( $template, "'posts_per_page' => -1" ) >= 2, 'managed CPT queries fetch all before sort/slice' );
gh_ok( str_contains( $template, 'compare_managed_posts' ) && str_contains( $template, '(int) $a->ID <=> (int) $b->ID' ), 'managed ordering has deterministic secondary ID key' );

gh_ok( str_contains( $helpers, "in_array( $kind, array( 'doctor', 'clinic', 'product' ), true ) ) { return; }" ), 'abstract renderer hard-stops factual identity kinds' );
gh_ok( ! str_contains( $helpers, "gloskin_ui1_render_presentation_media( 'product'" ) && str_contains( $helpers, 'gloskin-ui1-card--text-first' ), 'product/doctor/clinic normal missing media path is text-first' );
gh_ok( str_contains( $helpers, "'alt' => $title" ) && str_contains( $helpers, "'alt' => $name" ), 'factual media alt uses exact factual entity/product name' );

gh_ok( substr_count( $home, 'data-gloskin-section="home-brand-story"' ) === 1 && str_contains( $home, "home_url( '/about/' )" ), 'Home brand story always exists with /about/ fallback CTA' );
$brand_pos = strpos($home,'home-brand-story'); $test_pos=strpos($home,'render_testimonials'); $ach_pos=strpos($home,'render_achievements');
gh_ok( $test_pos !== false && $brand_pos !== false && $ach_pos !== false && $test_pos < $brand_pos && $brand_pos < $ach_pos, 'Home brand story structural order preserved' );

gh_ok( is_array($manifest) && count($manifest['items'] ?? array()) === 6, 'six first-party editorial assets remain selected' );
foreach ( (array)($manifest['items'] ?? array()) as $item ) {
    $file = $root . '/plugin/gloskin-site-core/migration-runtime/gloskin-editorial-media-v1/' . basename((string)$item['file']);
    $ok = is_file($file) && hash_file('sha256',$file)===(string)$item['sha256']
        && (int)($item['width']??0)>0 && (int)($item['height']??0)>0 && !empty($item['mime'])
        && !empty($item['semantic_role']) && !empty($item['source_page']) && !empty($item['source_asset_url'])
        && 'first-party-gloskin'===(string)($item['source_type']??'') && array_key_exists('decorative',$item) && array_key_exists('alt',$item);
    gh_ok($ok, 'editorial manifest metadata/SHA valid: '.(string)($item['key']??''));
}
exit($fail ? 1 : 0);
'''
write("tests/final-hardening-contract.php", hardening_test)

ordering_test = r'''<?php
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$GLOBALS['gh_order_meta'] = array();
function get_post_meta($id,$key,$single=true){ return $GLOBALS['gh_order_meta'][$id][$key] ?? ''; }
require dirname(__DIR__) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php';
$service = new Gloskin_Site_Core_Template_Service('', null, null, null);
$method = new ReflectionMethod($service, 'compare_managed_posts'); $method->setAccessible(true);
$posts = array();
for($i=1;$i<=55;$i++){ $o=new stdClass(); $o->ID=$i; $o->post_title=sprintf('Record %02d',$i); $posts[]=$o; $GLOBALS['gh_order_meta'][$i]['gl_order']=0; }
$GLOBALS['gh_order_meta'][55]['gl_order']=1;
usort($posts, static function($a,$b) use($method,$service){ return $method->invoke($service,$a,$b,'gl_order'); });
if((int)$posts[0]->ID!==55){ fwrite(STDERR,"FAIL: >40 high-priority record was not sorted first\n"); exit(1); }
$a=new stdClass(); $a->ID=7; $a->post_title='Same'; $b=new stdClass(); $b->ID=9; $b->post_title='Same';
$GLOBALS['gh_order_meta'][7]['gl_order']=2; $GLOBALS['gh_order_meta'][9]['gl_order']=2;
if($method->invoke($service,$a,$b,'gl_order')>=0){ fwrite(STDERR,"FAIL: ID tiebreaker is not deterministic\n"); exit(1); }
$source=(string)file_get_contents(dirname(__DIR__).'/plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php');
if(str_contains($source,'max( $limit * 4, 40 )') || substr_count($source,"'posts_per_page' => -1")<2){ fwrite(STDERR,"FAIL: managed queries still truncate before ordering\n"); exit(1); }
echo "managed-record-ordering-contract.php: OK (>40 deterministic)\n";
'''
write("tests/managed-record-ordering-contract.php", ordering_test)

zero_browser = r'''#!/usr/bin/env python3
from pathlib import Path
import os, subprocess
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
def fixture(view):
    env=dict(os.environ); env['GLOSKIN_FIXTURE_VIEW']=view
    return subprocess.check_output(['php',str(ROOT/'tests/render-fixture.php')],text=True,env=env)
with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True)
    page=browser.new_page(viewport={'width':1440,'height':900})
    for view in ('doctors','clinics','shop'):
        page.set_content(fixture(view),wait_until='domcontentloaded')
        leaks=page.locator('.gloskin-ui1-media--doctor,.gloskin-ui1-media--clinic,.gloskin-ui1-media--product').count()
        if leaks:
            raise SystemExit(f'{view}: factual abstract placeholder still rendered ({leaks})')
        # Text-first cards must not carry an empty normal media link/shell.
        empty=page.locator('.gloskin-ui1-card--text-first .gloskin-ui1-card__media:not(:has(img))').count()
        if empty:
            raise SystemExit(f'{view}: text-first card still has empty media shell ({empty})')
    browser.close()
print('zero-placeholder-browser-smoke.py: OK')
'''
write("tests/zero-placeholder-browser-smoke.py", zero_browser)

runtime = read("tests/check-runtime.sh")
insert = "php tests/final-hardening-contract.php\nphp tests/managed-record-ordering-contract.php\n"
if "final-hardening-contract.php" not in runtime:
    runtime = runtime.replace("php tests/final-closure-contract.php\n", "php tests/final-closure-contract.php\n" + insert, 1)
if "zero-placeholder-browser-smoke.py" not in runtime:
    runtime = runtime.replace("  python tests/browser-smoke.py\n", "  python tests/browser-smoke.py\n  python tests/zero-placeholder-browser-smoke.py\n", 1)
write("tests/check-runtime.sh", runtime)

print("final-hardening patch applied")
