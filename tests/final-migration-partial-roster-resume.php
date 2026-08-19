<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }

class WP_Error { public function get_error_message() { return 'fixture error'; } }
class WP_Post {
	public $ID; public $post_type; public $post_status; public $post_title; public $post_name; public $post_content;
	public function __construct( array $data ) { foreach ( $data as $key => $value ) { $this->{$key} = $value; } }
}
class Gloskin_Site_Core_Content_Service { const DOCTOR_POST_TYPE = 'gloskin_doctor'; }
/* Presence of the real Final Migration owner is what scopes A->B->C validation
 * inside the reusable importer. The importer does not depend on its methods. */
class Gloskin_Site_Core_Revision_20260819_Final_Migration {}

$GLOBALS['pr_options'] = array();
$GLOBALS['pr_posts'] = array();
$GLOBALS['pr_meta'] = array();
$GLOBALS['pr_next_id'] = 100;
$GLOBALS['pr_uuid'] = 0;

function pr_ok( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}
function trailingslashit( $value ) { return rtrim( (string) $value, '/' ) . '/'; }
function plugin_dir_path( $file ) { return trailingslashit( dirname( (string) $file ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function __( $text, $domain = null ) { unset( $domain ); return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['pr_options'] ) ? $GLOBALS['pr_options'][$key] : $default; }
function update_option( $key, $value, $autoload = null ) { unset( $autoload ); $GLOBALS['pr_options'][$key] = $value; return true; }
function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) { unset( $deprecated, $autoload ); if ( array_key_exists( $key, $GLOBALS['pr_options'] ) ) { return false; } $GLOBALS['pr_options'][$key] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['pr_options'][$key] ); return true; }
function wp_generate_uuid4() { return 'partial-token-' . ++$GLOBALS['pr_uuid']; }
function get_post( $id ) { return $GLOBALS['pr_posts'][(int) $id] ?? null; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['pr_meta'][(int) $id][$key] ?? ( $single ? '' : array() ); }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pr_meta'][(int) $id][$key] = $value; return true; }
function wp_insert_post( $data, $wp_error = false ) {
	unset( $wp_error ); $id = $GLOBALS['pr_next_id']++;
	$GLOBALS['pr_posts'][$id] = new WP_Post( array(
		'ID' => $id, 'post_type' => $data['post_type'] ?? 'post', 'post_status' => $data['post_status'] ?? 'draft',
		'post_title' => $data['post_title'] ?? '', 'post_name' => $data['post_name'] ?? '', 'post_content' => $data['post_content'] ?? '',
	) );
	return $id;
}
function wp_update_post( $data, $wp_error = false ) {
	unset( $wp_error ); $id = absint( $data['ID'] ?? 0 ); $post = get_post( $id ); if ( ! $post ) { return new WP_Error(); }
	foreach ( array( 'post_title', 'post_name', 'post_status', 'post_content' ) as $key ) { if ( array_key_exists( $key, $data ) ) { $post->{$key} = $data[$key]; } }
	return $id;
}
function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) {
	unset( $output ); foreach ( $GLOBALS['pr_posts'] as $post ) { if ( $post->post_type === $post_type && $post->post_name === trim( (string) $path, '/' ) ) { return $post; } } return null;
}
function get_posts( $args = array() ) {
	$posts = array_values( array_filter( $GLOBALS['pr_posts'], static function ( $post ) use ( $args ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) { return false; }
		if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] && ! in_array( $post->post_status, (array) $args['post_status'], true ) ) { return false; }
		if ( isset( $args['title'] ) && (string) $post->post_title !== (string) $args['title'] ) { return false; }
		if ( isset( $args['meta_key'] ) ) {
			$key = (string) $args['meta_key']; $meta = $GLOBALS['pr_meta'][$post->ID] ?? array();
			if ( ! array_key_exists( $key, $meta ) ) { return false; }
			if ( array_key_exists( 'meta_value', $args ) && (string) $meta[$key] !== (string) $args['meta_value'] ) { return false; }
		}
		return true;
	} ) );
	usort( $posts, static function ( $a, $b ) { return (int) $a->ID <=> (int) $b->ID; } );
	$limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 5;
	if ( $limit >= 0 ) { $posts = array_slice( $posts, 0, $limit ); }
	return 'ids' === ( $args['fields'] ?? '' ) ? array_map( static fn( $p ) => $p->ID, $posts ) : $posts;
}
function esc_url_raw( $value ) { return (string) $value; }
function sanitize_mime_type( $value ) { return (string) $value; }

function pr_copy_dir( string $from, string $to ): void {
	if ( ! is_dir( $to ) && ! mkdir( $to, 0777, true ) && ! is_dir( $to ) ) { throw new RuntimeException( 'mkdir failed' ); }
	foreach ( scandir( $from ) ?: array() as $item ) {
		if ( '.' === $item || '..' === $item ) { continue; }
		$src = $from . '/' . $item; $dst = $to . '/' . $item;
		if ( is_dir( $src ) ) { pr_copy_dir( $src, $dst ); } else { copy( $src, $dst ); }
	}
}
function pr_remove_dir( string $dir ): void {
	if ( ! is_dir( $dir ) ) { return; }
	foreach ( scandir( $dir ) ?: array() as $item ) { if ( '.' === $item || '..' === $item ) { continue; } $path = $dir . '/' . $item; is_dir( $path ) ? pr_remove_dir( $path ) : @unlink( $path ); }
	@rmdir( $dir );
}

$root = dirname( __DIR__ );
$tmp = sys_get_temp_dir() . '/gloskin-partial-roster-' . getmypid() . '-' . bin2hex( random_bytes( 3 ) );
$plugin_root = $tmp . '/gloskin-site-core';
$plugin_file = $plugin_root . '/gloskin-site-core.php';
mkdir( $plugin_root . '/migration-runtime', 0777, true ); file_put_contents( $plugin_file, "<?php\n" );
register_shutdown_function( static function () use ( $tmp ): void { pr_remove_dir( $tmp ); } );
foreach ( array( 'gloskin-doctors-v1', 'gloskin-doctor-photos-v2', 'gloskin-editorial-media-v1' ) as $bundle ) {
	pr_copy_dir( $root . '/plugin/gloskin-site-core/migration-runtime/' . $bundle, $plugin_root . '/migration-runtime/' . $bundle );
}

require $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-doctor-bundle.php';
foreach ( array( 'state', 'upsert', 'finalize', 'lock' ) as $part ) { require $root . '/plugin/gloskin-site-core/includes/gloskin-site-core-doctor-importer-' . $part . '-trait.php'; }
require $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-doctor-importer.php';

$payload = json_decode( (string) file_get_contents( $plugin_root . '/migration-runtime/gloskin-doctors-v1/doctors.json' ), true );
pr_ok( is_array( $payload ) && 13 === count( $payload['doctors'] ?? array() ), 'fixture roster has 13 doctors' );
$seed_ids = array();
foreach ( array_slice( $payload['doctors'], 0, 4 ) as $record ) {
	$id = wp_insert_post( array( 'post_type' => 'gloskin_doctor', 'post_status' => 'publish', 'post_title' => $record['post_title'], 'post_name' => $record['slug'] ), true );
	update_post_meta( $id, Gloskin_Site_Core_Doctor_Importer::SOURCE_META, $record['source_id'] );
	update_post_meta( $id, Gloskin_Site_Core_Doctor_Importer::BUNDLE_META, Gloskin_Site_Core_Doctor_Bundle::BUNDLE_ID );
	$seed_ids[] = $id;
}
update_option( Gloskin_Site_Core_Doctor_Importer::STATE_OPTION, array(
	'status' => 'failed', 'index' => 4, 'expected' => 13, 'imported_ids' => $seed_ids, 'last_error' => 'simulated prior request failure',
), false );

$importer = new Gloskin_Site_Core_Doctor_Importer( $plugin_file );
$photo_manifest = json_decode( (string) file_get_contents( $plugin_root . '/migration-runtime/gloskin-doctor-photos-v2/manifest.json' ), true );
$photo_file = (string) $photo_manifest['doctors'][0]['primary_webp'];
$photo_path = $plugin_root . '/migration-runtime/gloskin-doctor-photos-v2/' . $photo_file;
$original_photo = file_get_contents( $photo_path );
pr_ok( is_string( $original_photo ), 'photo corruption fixture readable' );
file_put_contents( $photo_path, $original_photo . 'corrupt' );
$before_posts = count( get_posts( array( 'post_type' => 'gloskin_doctor', 'post_status' => 'any', 'posts_per_page' => -1 ) ) );
$before_state = $importer->state();
$failed_validation = false;
try { $importer->advance( 'continue' ); } catch ( Throwable $error ) { $failed_validation = false !== strpos( $error->getMessage(), 'bundle_invalid' ); }
pr_ok( $failed_validation, 'corrupt immutable photo package blocks partial roster continuation' );
pr_ok( $before_posts === count( get_posts( array( 'post_type' => 'gloskin_doctor', 'post_status' => 'any', 'posts_per_page' => -1 ) ) ), 'package failure causes zero doctor mutation' );
$after_failed = $importer->state();
pr_ok( 4 === (int) $after_failed['index'] && 4 === (int) $before_state['index'], 'package failure preserves partial roster cursor at 4' );
file_put_contents( $photo_path, $original_photo );

$next = $importer->advance( 'continue' );
pr_ok( 5 === (int) $next['index'], 'partial failed roster resumes from cursor 4 to 5' );
foreach ( $seed_ids as $id ) { pr_ok( isset( $GLOBALS['pr_posts'][$id] ), 'pre-existing owned doctor remains intact' ); }
for ( $i = 0; $i < 20 && 'consumed' !== (string) $next['status']; $i++ ) { $next = $importer->advance( 'continue' ); }
pr_ok( 'consumed' === (string) $next['status'] && 13 === (int) $next['index'], 'partial roster continues to consumed without restart' );
$all = get_posts( array( 'post_type' => 'gloskin_doctor', 'post_status' => 'any', 'posts_per_page' => -1 ) );
$source_ids = array();
foreach ( $all as $post ) { $source = (string) get_post_meta( $post->ID, Gloskin_Site_Core_Doctor_Importer::SOURCE_META, true ); if ( '' !== $source ) { $source_ids[] = $source; } }
pr_ok( 13 === count( $source_ids ) && 13 === count( array_unique( $source_ids ) ), 'consumed roster has exactly 13 unique source identities' );
pr_ok( 13 === count( array_unique( array_map( 'absint', (array) $next['imported_ids'] ) ) ), 'imported_ids remain unique after resume' );

echo "final-migration-partial-roster-resume.php: OK (invalid B blocks at 4; resume 4->5->13; no duplicates)\n";
