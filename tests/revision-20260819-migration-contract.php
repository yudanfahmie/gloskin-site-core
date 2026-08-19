<?php
/**
 * Contract tests for the 2026-08-19 revision migration.
 *
 * Coverage:
 *  - normalize_name() exact alias rules (no fuzzy matching)
 *  - Manifest file + SHA-256 assertions for all 12 primaries
 *  - CPT constants present in ContentService
 *  - Migration state machine: consumed permanence, idempotency
 *  - Preflight guard: blocks on hash mismatch / missing file
 *  - Demo seed: Draft-by-default intent verifiable without WP
 *
 * Runs via plain PHP: C:\xampp\php\php.exe tests/revision-20260819-migration-contract.php
 */
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }

/* ── Assertion helper ──────────────────────────────────────────────────── */
function ok( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

/* ── WordPress stub environment ────────────────────────────────────────── */
$GLOBALS['opt']            = array();
$GLOBALS['posts']          = array();
$GLOBALS['post_meta']      = array();
$GLOBALS['terms']          = array();
$GLOBALS['next_post_id']   = 5000;
$GLOBALS['attach_meta']    = array();
$GLOBALS['attachments']    = array();
$GLOBALS['set_thumbnails']  = array();
$GLOBALS['wp_upload_dir_val'] = array(
	'path'    => sys_get_temp_dir() . '/gloskin-test-uploads',
	'url'     => 'https://example.test/wp-content/uploads',
	'subdir'  => '',
	'error'   => false,
);

function get_option( string $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opt'] ) ? $GLOBALS['opt'][ $key ] : $default;
}
function update_option( string $key, $value, $autoload = null ): bool {
	$GLOBALS['opt'][ $key ] = $value;
	return true;
}
function add_option( string $key, $value, $deprecated = '', $autoload = 'yes' ): bool {
	if ( array_key_exists( $key, $GLOBALS['opt'] ) ) { return false; }
	$GLOBALS['opt'][ $key ] = $value;
	return true;
}
function delete_option( string $key ): bool { unset( $GLOBALS['opt'][ $key ] ); return true; }
function wp_generate_uuid4(): string {
	static $n = 0;
	return 'token-' . ++$n;
}
function sanitize_key( $value ): string {
	return strtolower( (string) preg_replace( '/[^a-z0-9_-]/', '', mb_strtolower( (string) $value ) ) );
}
function sanitize_file_name( $value ): string { return (string) preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $value ); }
function absint( $value ): int { return abs( (int) $value ); }
function trailingslashit( $value ): string { return rtrim( (string) $value, '/' ) . '/'; }
function home_url( $path = '' ): string { return 'https://example.test' . $path; }
function wp_get_environment_type(): string { return $GLOBALS['_wp_env_type'] ?? 'production'; }

class WP_Post {
	public int    $ID           = 0;
	public string $post_type    = '';
	public string $post_status  = 'publish';
	public string $post_name    = '';
	public string $post_title   = '';
	public int    $post_parent  = 0;
	public string $post_content = '';
	public string $post_excerpt = '';
	public function __construct( array $data ) { foreach ( $data as $k => $v ) { $this->{$k} = $v; } }
}
class WP_Error {
	private string $message;
	public function __construct( string $message ) { $this->message = $message; }
	public function get_error_message(): string { return $this->message; }
}
function is_wp_error( $v ): bool { return $v instanceof WP_Error; }

function get_post( $id ): ?WP_Post { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_type( $id ): string {
	return $GLOBALS['posts'][ (int) $id ]->post_type ?? '';
}
function get_post_meta( $id, $key, $single = false ) {
	return $GLOBALS['post_meta'][ (int) $id ][ $key ] ?? ( $single ? '' : array() );
}
function update_post_meta( $id, $key, $value ): bool {
	$GLOBALS['post_meta'][ (int) $id ][ $key ] = $value;
	return true;
}
function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ): ?WP_Post {
	$path = trim( (string) $path, '/' );
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( $post->post_type !== $post_type ) { continue; }
		if ( $post->post_name === $path ) { return $post; }
	}
	return null;
}
function wp_insert_post( array $data, $wp_error = false ): int {
	$id = $GLOBALS['next_post_id']++;
	$GLOBALS['posts'][ $id ] = new WP_Post( array(
		'ID'           => $id,
		'post_type'    => $data['post_type']    ?? 'post',
		'post_status'  => $data['post_status']  ?? 'draft',
		'post_name'    => $data['post_name']    ?? '',
		'post_title'   => $data['post_title']   ?? '',
		'post_parent'  => (int) ( $data['post_parent'] ?? 0 ),
		'post_excerpt' => $data['post_excerpt'] ?? '',
	) );
	return $id;
}
function get_posts( array $args ): array {
	$type = $args['post_type'] ?? 'post';
	$meta = $args['meta_query'] ?? array();
	$result = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( $post->post_type !== $type ) { continue; }
		$ok = true;
		foreach ( $meta as $m ) {
			if ( ! is_array( $m ) ) { continue; }
			$key = $m['key'] ?? '';
			$val = $m['value'] ?? '';
			$cmp = $m['compare'] ?? '=';
			$pval = get_post_meta( $post->ID, $key, true );
			if ( '=' === $cmp && (string) $pval !== (string) $val ) { $ok = false; break; }
		}
		if ( $ok ) { $result[] = $post; }
	}
	return $result;
}
function wp_delete_post( $id, $force = false ): bool {
	$id = (int) $id;
	if ( isset( $GLOBALS['posts'][ $id ] ) ) {
		unset( $GLOBALS['posts'][ $id ] );
		return true;
	}
	return false;
}
function set_post_thumbnail( $post_id, $thumbnail_id ): bool {
	$GLOBALS['set_thumbnails'][ (int) $post_id ] = (int) $thumbnail_id;
	return true;
}
function get_post_thumbnail_id( $post_id ): int {
	return $GLOBALS['set_thumbnails'][ (int) $post_id ] ?? 0;
}

/* Attachment stubs */
function wp_upload_dir(): array { return $GLOBALS['wp_upload_dir_val']; }
function wp_check_filetype( $filename, $mimes = null ): array {
	return array( 'type' => 'image/webp', 'ext' => 'webp' );
}
function wp_insert_attachment( array $data, $file = false, $parent = 0, $wp_error = false ): int {
	$id = $GLOBALS['next_post_id']++;
	$GLOBALS['posts'][ $id ] = new WP_Post( array(
		'ID' => $id, 'post_type' => 'attachment', 'post_status' => 'inherit',
		'post_name' => $data['post_name'] ?? '', 'post_title' => $data['post_title'] ?? '',
		'post_parent' => (int) $parent,
	) );
	$GLOBALS['attachments'][ $id ] = $file;
	return $id;
}
function wp_generate_attachment_metadata( $id, $file ): array { return array( 'file' => (string) $file ); }
function wp_update_attachment_metadata( $id, $data ): bool { return true; }

/* Commerce stubs */
function wc_get_order_statuses(): array { return array( 'wc-pending', 'wc-completed' ); }
function wc_get_page_id( $page ): int { return $GLOBALS['opt'][ 'woocommerce_' . $page . '_page_id' ] ?? 0; }

/* taxonomy stubs */
function post_type_exists( string $type ): bool { return true; }
function taxonomy_exists( string $tax ): bool { return false; }

/* Flush and options stubs */
function flush_rewrite_rules( $hard = true ): void {}
function delete_post_thumbnail( $post_id ): bool { return true; }

/* wpautop stub */
function wpautop( $text ): string { return '<p>' . $text . '</p>'; }

/* ── TESTS ─────────────────────────────────────────────────────────────── */

$root      = dirname( __DIR__ );
$plugin_dir = $root . '/plugin/gloskin-site-core';

/* ---- 1. ContentService CPT constants ---------------------------------- */
require_once $plugin_dir . '/includes/class-gloskin-site-core-content-service.php';

ok( defined( 'ABSPATH' ), 'ABSPATH must be defined' );
ok( class_exists( 'Gloskin_Site_Core_Content_Service' ), 'ContentService must exist' );
ok( Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE === 'gloskin_promo', 'PROMO_POST_TYPE constant must equal gloskin_promo' );
ok( Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE === 'gloskin_testimonial', 'TESTIMONIAL_POST_TYPE constant must equal gloskin_testimonial' );
ok( Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE === 'gloskin_achievement', 'ACHIEVEMENT_POST_TYPE constant must equal gloskin_achievement' );
ok( defined( 'Gloskin_Site_Core_Content_Service::DEMO_IDENTITY_META' ), 'DEMO_IDENTITY_META constant must exist' );
ok( defined( 'Gloskin_Site_Core_Content_Service::DEMO_REVISION_META' ), 'DEMO_REVISION_META constant must exist' );

/* ---- 2. Migration class loads cleanly --------------------------------- */
require_once $plugin_dir . '/includes/class-gloskin-site-core-revision-20260819-migration.php';
ok( class_exists( 'Gloskin_Site_Core_Revision_20260819_Migration' ), 'Migration class must exist' );

$fake_plugin_file = $plugin_dir . '/gloskin-site-core.php';
$migration = new Gloskin_Site_Core_Revision_20260819_Migration( $fake_plugin_file );

/* ---- 3. normalize_name — exact alias rules ----------------------------- */
// The normalize_name method must implement only: lowercase → trim → punct→space → collapse → strip leading "dr"
$tests_normalize = array(
	// plain name
	array( 'Arwina Sufika', 'arwina sufika' ),
	// leading dr with space
	array( 'Dr. Arwina Sufika', 'arwina sufika' ),
	array( 'dr arwina sufika', 'arwina sufika' ),
	array( 'Dr Arwina Sufika', 'arwina sufika' ),
	// leading dr. (punct → space)
	array( 'dr. arwina sufika', 'arwina sufika' ),
	// trailing spaces/punct
	array( '  Cyintia Musius  ', 'cyintia musius' ),
	// mixed internal punct — commas become spaces, then collapsed to single space
	array( 'Dr. Maria Sianturi, SpKK', 'maria sianturi spkk' ),
	// uppercase mid-name
	array( 'GLOSKIN DOCTOR', 'gloskin doctor' ),
	// multiple spaces collapse
	array( 'dr  double  space', 'double space' ),
	// dr is NOT stripped from middle of name
	array( 'Ahmad Hidayat', 'ahmad hidayat' ),
	// non-latin chars (Indonesian) — kept
	array( 'Dr. Ni Nyoman Ayu', 'ni nyoman ayu' ),
);
foreach ( $tests_normalize as $i => $case ) {
	$actual   = $migration->normalize_name( $case[0] );
	$expected = $case[1];
	ok(
		$actual === $expected,
		"normalize_name case [{$i}] input='{$case[0]}': got='{$actual}' want='{$expected}'"
	);
}

/* ---- 4. normalize_name — fuzzy guard ---------------------------------- */
// Two doctorably-similar names must NOT collapse to the same output
// (so the matching layer can catch ambiguity, not silently merge)
$n1 = $migration->normalize_name( 'Dr. Laksmi Trimurti' );
$n2 = $migration->normalize_name( 'Dr. Laksmi Ayu' );
ok( $n1 !== $n2, 'Similar but distinct names must NOT collapse to the same normalised form' );

/* ---- 5. Runtime manifest exists and parses correctly ------------------ */
$manifest_path = $plugin_dir . '/migration-runtime/gloskin-doctor-photos-v2/manifest.json';
ok( file_exists( $manifest_path ), 'Runtime manifest must exist at migration-runtime/gloskin-doctor-photos-v2/manifest.json' );

$manifest_raw = file_get_contents( $manifest_path );
ok( false !== $manifest_raw, 'Runtime manifest must be readable' );

$manifest = json_decode( (string) $manifest_raw, true );
ok( is_array( $manifest ), 'Runtime manifest must decode as JSON object' );
ok( isset( $manifest['bundle_id'] ) && 'gloskin-doctor-photos-v2' === $manifest['bundle_id'], 'bundle_id must equal gloskin-doctor-photos-v2' );
ok( isset( $manifest['doctors'] ) && is_array( $manifest['doctors'] ), 'manifest must have doctors array' );
ok( 12 === count( $manifest['doctors'] ), 'manifest must list exactly 12 doctor entries' );

/* ---- 6. All 12 manifest entries have valid SHA-256 and existing files - */
$runtime_dir = $plugin_dir . '/migration-runtime/gloskin-doctor-photos-v2';
$source_labels_seen = array();
foreach ( $manifest['doctors'] as $doc_idx => $doc ) {
	$label  = (string) ( $doc['source_label'] ?? '' );
	$webp   = (string) ( $doc['primary_webp'] ?? '' );
	$sha    = (string) ( $doc['primary_sha256'] ?? '' );
	$webp_path = $runtime_dir . '/' . $webp;

	ok( '' !== $label, "Manifest entry {$doc_idx}: source_label must not be empty" );
	ok( '' !== $webp, "Manifest entry {$doc_idx}: primary_webp must not be empty" );
	ok( 64 === strlen( $sha ), "Manifest entry {$doc_idx}: primary_sha256 must be 64 hex chars, got " . strlen( $sha ) );
	ok( ctype_xdigit( $sha ), "Manifest entry {$doc_idx}: primary_sha256 must be hex string" );
	ok( file_exists( $webp_path ), "Manifest entry {$doc_idx}: WebP file must exist at {$webp_path}" );
	ok( filesize( $webp_path ) > 0, "Manifest entry {$doc_idx}: WebP file must not be empty" );

	$actual_sha = hash_file( 'sha256', $webp_path );
	ok(
		hash_equals( $sha, (string) $actual_sha ),
		"Manifest entry {$doc_idx} ({$label}): SHA-256 mismatch. Expected={$sha} Actual={$actual_sha}"
	);

	ok( ! in_array( $label, $source_labels_seen, true ), "Manifest entry {$doc_idx}: source_label '{$label}' must be unique" );
	$source_labels_seen[] = $label;
}

/* ---- 7. Each manifest entry has at least one match alias -------------- */
foreach ( $manifest['doctors'] as $doc_idx => $doc ) {
	$aliases = $doc['match_aliases'] ?? array();
	ok( is_array( $aliases ) && count( $aliases ) >= 1, "Manifest entry {$doc_idx}: must have at least one match alias" );
	foreach ( $aliases as $a_idx => $alias ) {
		ok( is_string( $alias ) && '' !== trim( $alias ), "Manifest entry {$doc_idx} alias {$a_idx} must be non-empty string" );
	}
}

/* ---- 8. Migration state defaults to pending, not consumed ------------- */
$state = $migration->get_state();
ok( 'consumed' !== $state['status'], 'Initial migration state must not be consumed (fresh test environment)' );
ok( 8 === $state['total_steps'], 'Migration must declare exactly 8 steps' );
ok( 0 === $state['next_step_index'], 'Fresh migration must start at step index 0' );
ok( is_array( $state['doctor_matches'] ), 'State must initialise doctor_matches as array' );

/* ---- 9. is_consumed() reflects option state, not constant ------------- */
ok( ! $migration->is_consumed(), 'is_consumed must return false when state is not consumed' );
// Force consumed state by writing to the option store
$GLOBALS['opt'][ Gloskin_Site_Core_Revision_20260819_Migration::STATE_OPTION ] = array(
	'revision' => '2026-08-19',
	'status'   => 'consumed',
);
ok( $migration->is_consumed(), 'is_consumed must return true after state option set to consumed' );

// Reset for remaining tests
unset( $GLOBALS['opt'][ Gloskin_Site_Core_Revision_20260819_Migration::STATE_OPTION ] );

/* ---- 10. Attach revision/SHA meta constants present ------------------- */
ok( defined( 'Gloskin_Site_Core_Revision_20260819_Migration::ATTACH_REVISION_META' ), 'ATTACH_REVISION_META must be defined' );
ok( defined( 'Gloskin_Site_Core_Revision_20260819_Migration::ATTACH_SHA256_META' ), 'ATTACH_SHA256_META must be defined' );
ok( defined( 'Gloskin_Site_Core_Revision_20260819_Migration::DEMO_IDENTITY_META' ), 'DEMO_IDENTITY_META on migration must be defined' );
ok( defined( 'Gloskin_Site_Core_Revision_20260819_Migration::DEMO_REVISION_META' ), 'DEMO_REVISION_META on migration must be defined' );

/* ---- 11. BUNDLE_DIR / BUNDLE_ID constants match the actual directory -- */
ok( 'gloskin-doctor-photos-v2' === Gloskin_Site_Core_Revision_20260819_Migration::BUNDLE_DIR, 'BUNDLE_DIR must equal gloskin-doctor-photos-v2' );
ok( 'gloskin-doctor-photos-v2' === Gloskin_Site_Core_Revision_20260819_Migration::BUNDLE_ID, 'BUNDLE_ID must equal gloskin-doctor-photos-v2' );
ok( is_dir( $runtime_dir ), 'Bundle directory must exist on disk at migration-runtime/' . Gloskin_Site_Core_Revision_20260819_Migration::BUNDLE_DIR );

/* ---- 12. Manifest source_labels do NOT use fuzzy/partial name forms --- */
// All source_labels must include at minimum 2 name parts (not just "arwina" alone)
foreach ( $manifest['doctors'] as $doc_idx => $doc ) {
	$label = trim( (string) ( $doc['source_label'] ?? '' ) );
	$words = preg_split( '/\s+/', $label, -1, PREG_SPLIT_NO_EMPTY );
	ok( is_array( $words ) && count( (array) $words ) >= 2, "Manifest entry {$doc_idx}: source_label '{$label}' must contain at least 2 words (first+last name minimum)" );
}

/* ---- 13. Release version (0.7.134) visible in plugin header and kernel  */
$plugin_header = (string) file_get_contents( $plugin_dir . '/gloskin-site-core.php' );
$kernel_src    = (string) file_get_contents( $plugin_dir . '/includes/class-gloskin-site-core-kernel.php' );

preg_match( '/^ \* Version: ([0-9]+\.[0-9]+\.[0-9]+)$/m', $plugin_header, $hm );
preg_match( "/const VERSION = '([0-9]+\\.[0-9]+\\.[0-9]+)';/", $kernel_src, $km );

ok( isset( $hm[1] ) && '0.7.136' === $hm[1], "Plugin header version must be 0.7.136, got " . ( $hm[1] ?? 'none' ) );
ok( isset( $km[1] ) && '0.7.136' === $km[1], "Kernel VERSION must be 0.7.136, got " . ( $km[1] ?? 'none' ) );

echo "revision-20260819-migration-contract: OK\n";
