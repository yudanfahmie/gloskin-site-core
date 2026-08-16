<?php
declare(strict_types=1);

define( 'ABSPATH', sys_get_temp_dir() . '/gloskin-test-wp/' );
define( 'OBJECT', 'OBJECT' );

class WP_Error {
	private string $code;
	private string $message;
	public function __construct( string $code = '', string $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_message(): string { return $this->message; }
}
class WP_Post {
	public function __construct( array $data = array() ) { foreach ( $data as $key => $value ) { $this->{$key} = $value; } }
}

$GLOBALS['fake_options'] = array();
$GLOBALS['fake_posts'] = array();
$GLOBALS['fake_meta'] = array();
$GLOBALS['fake_terms'] = array();
$GLOBALS['fake_post_categories'] = array();
$GLOBALS['fake_thumbnails'] = array();
$GLOBALS['fake_files'] = array();
$GLOBALS['next_post_id'] = 1;
$GLOBALS['next_term_id'] = 1;
$GLOBALS['fail_download_once'] = false;
$GLOBALS['fail_cleanup'] = false;
$GLOBALS['deleted_post_ids'] = array();

function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function trailingslashit( $value ): string { return rtrim( (string) $value, "/\\" ) . '/'; }
function plugin_dir_path( $file ): string { return trailingslashit( dirname( (string) $file ) ); }
function sanitize_title( $value ): string { return strtolower( trim( preg_replace( '/[^a-zA-Z0-9]+/', '-', (string) $value ), '-' ) ); }
function sanitize_key( $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ): string { return trim( strip_tags( (string) $value ) ); }
function wp_strip_all_tags( $value ): string { return trim( strip_tags( (string) $value ) ); }
function wp_kses_post( $value ): string { return (string) $value; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function wp_slash( $value ) { return $value; }
function esc_url_raw( $value ): string { return (string) $value; }
function absint( $value ): int { return abs( (int) $value ); }
function get_gmt_from_date( $value ): string { return (string) $value; }
function get_current_user_id(): int { return 7; }
function wp_generate_uuid4(): string { return bin2hex( random_bytes( 16 ) ); }
function get_permalink( $id ): string { return isset( $GLOBALS['fake_posts'][ (int) $id ] ) ? 'https://example.test/' . $GLOBALS['fake_posts'][ (int) $id ]->post_name . '/' : ''; }

function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['fake_options'] ) ? $GLOBALS['fake_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ): bool { $GLOBALS['fake_options'][ $key ] = $value; return true; }
function add_option( $key, $value, $deprecated = '', $autoload = null ): bool {
	if ( array_key_exists( $key, $GLOBALS['fake_options'] ) ) { return false; }
	$GLOBALS['fake_options'][ $key ] = $value; return true;
}
function delete_option( $key ): bool { unset( $GLOBALS['fake_options'][ $key ] ); return true; }

function update_post_meta( $id, $key, $value ): bool { $GLOBALS['fake_meta'][ (int) $id ][ $key ] = $value; return true; }
function get_post_meta( $id, $key = '', $single = false ) {
	$id = (int) $id;
	if ( '' === $key ) { return $GLOBALS['fake_meta'][ $id ] ?? array(); }
	return $GLOBALS['fake_meta'][ $id ][ $key ] ?? '';
}
function get_post( $id ) { return $GLOBALS['fake_posts'][ (int) $id ] ?? null; }
function get_post_type( $id ): string { $post = get_post( $id ); return $post instanceof WP_Post ? (string) $post->post_type : ''; }

function wp_insert_post( $data, $wp_error = false ) {
	$id = $GLOBALS['next_post_id']++;
	$defaults = array(
		'ID'=>$id,'post_type'=>'post','post_status'=>'draft','post_name'=>'','post_title'=>'','post_excerpt'=>'','post_content'=>'',
		'post_date'=>'','post_date_gmt'=>'','post_author'=>0,'comment_status'=>'open','ping_status'=>'open'
	);
	$GLOBALS['fake_posts'][ $id ] = new WP_Post( array_merge( $defaults, $data, array( 'ID'=>$id ) ) );
	return $id;
}
function wp_update_post( $data, $wp_error = false ) {
	$id = isset( $data['ID'] ) ? (int) $data['ID'] : 0;
	if ( ! $id || ! isset( $GLOBALS['fake_posts'][ $id ] ) ) { return $wp_error ? new WP_Error( 'missing', 'post missing' ) : 0; }
	foreach ( $data as $key=>$value ) { if ( 'ID' !== $key ) { $GLOBALS['fake_posts'][ $id ]->{$key} = $value; } }
	return $id;
}
function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) {
	foreach ( $GLOBALS['fake_posts'] as $post ) {
		if ( $post->post_type === $post_type && $post->post_name === $slug ) { return $post; }
	}
	return null;
}
function get_posts( $args = array() ): array {
	$out = array();
	foreach ( $GLOBALS['fake_posts'] as $id => $post ) {
		$type = $args['post_type'] ?? 'post';
		if ( is_array( $type ) ? ! in_array( $post->post_type, $type, true ) : $post->post_type !== $type ) { continue; }
		$status = $args['post_status'] ?? 'publish';
		if ( 'any' !== $status && $post->post_status !== $status ) { continue; }
		if ( isset( $args['meta_key'] ) && (string) get_post_meta( $id, $args['meta_key'], true ) !== (string) ( $args['meta_value'] ?? '' ) ) { continue; }
		$out[] = ! empty( $args['fields'] ) && 'ids' === $args['fields'] ? (int) $id : $post;
	}
	$limit = isset( $args['numberposts'] ) ? (int) $args['numberposts'] : ( isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 5 );
	if ( $limit >= 0 ) { $out = array_slice( $out, 0, $limit ); }
	return $out;
}

function get_term_by( $field, $value, $taxonomy ) {
	foreach ( $GLOBALS['fake_terms'] as $term ) {
		if ( $taxonomy === $term->taxonomy && (string) $term->{$field} === (string) $value ) { return $term; }
	}
	return false;
}
function wp_insert_term( $name, $taxonomy, $args = array() ) {
	$slug = $args['slug'] ?? sanitize_title( $name );
	if ( get_term_by( 'slug', $slug, $taxonomy ) ) { return new WP_Error( 'term_exists', 'term exists' ); }
	$id = $GLOBALS['next_term_id']++;
	$GLOBALS['fake_terms'][ $id ] = (object) array( 'term_id'=>$id,'name'=>$name,'slug'=>$slug,'taxonomy'=>$taxonomy );
	return array( 'term_id'=>$id );
}
function update_term_meta( $id, $key, $value ): bool { return true; }
function wp_set_post_categories( $post_id, $ids, $append = false ) {
	$GLOBALS['fake_post_categories'][ (int) $post_id ] = array_map( 'intval', $ids );
	return $ids;
}
function wp_get_post_categories( $post_id ): array { return $GLOBALS['fake_post_categories'][ (int) $post_id ] ?? array(); }
function wp_get_post_terms( $post_id, $taxonomy, $args = array() ) {
	$ids = wp_get_post_categories( $post_id );
	$slugs = array();
	foreach ( $ids as $id ) { if ( isset( $GLOBALS['fake_terms'][ $id ] ) ) { $slugs[] = $GLOBALS['fake_terms'][ $id ]->slug; } }
	return ( $args['fields'] ?? '' ) === 'slugs' ? $slugs : array_map( fn($slug)=>(object)array('slug'=>$slug), $slugs );
}

function download_url( $url, $timeout = 300 ) {
	if ( $GLOBALS['fail_download_once'] ) { $GLOBALS['fail_download_once'] = false; return new WP_Error( 'download', 'injected download failure' ); }
	$tmp = tempnam( sys_get_temp_dir(), 'gloskin-media-' );
	file_put_contents( $tmp, 'fake image bytes ' . $url );
	return $tmp;
}
function media_handle_sideload( $file, $post_id, $desc = '' ) {
	$upload_dir = sys_get_temp_dir() . '/gloskin-fake-uploads';
	@mkdir( $upload_dir, 0777, true );
	$dest = $upload_dir . '/' . basename( $file['name'] );
	if ( ! @rename( $file['tmp_name'], $dest ) ) { copy( $file['tmp_name'], $dest ); unlink( $file['tmp_name'] ); }
	$id = $GLOBALS['next_post_id']++;
	$GLOBALS['fake_posts'][ $id ] = new WP_Post( array(
		'ID'=>$id,'post_type'=>'attachment','post_status'=>'inherit','post_name'=>sanitize_title( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
		'post_title'=>'','post_excerpt'=>'','post_content'=>'','post_parent'=>(int)$post_id
	) );
	$GLOBALS['fake_files'][ $id ] = $dest;
	return $id;
}
function wp_get_attachment_url( $id ) {
	return isset( $GLOBALS['fake_files'][ (int) $id ] ) ? 'https://example.test/wp-content/uploads/' . basename( $GLOBALS['fake_files'][ (int) $id ] ) : false;
}
function get_attached_file( $id ) { return $GLOBALS['fake_files'][ (int) $id ] ?? false; }
function set_post_thumbnail( $post_id, $attachment_id ): bool { $GLOBALS['fake_thumbnails'][ (int) $post_id ] = (int) $attachment_id; return true; }
function get_post_thumbnail_id( $post_id ): int { return $GLOBALS['fake_thumbnails'][ (int) $post_id ] ?? 0; }
function wp_delete_file( $path ): void {
	if ( $GLOBALS['fail_cleanup'] && false !== strpos( (string) $path, 'migration-runtime/gloskin-insights-v1' ) ) { return; }
	if ( is_file( $path ) ) { unlink( $path ); }
}

$root = dirname( __DIR__ );
require_once $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-insight-importer.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
};
$copy_runtime = static function ( string $plugin_root ) use ( $root ): string {
	$src = $root . '/plugin/gloskin-site-core/migration-runtime/gloskin-insights-v1';
	$dst = $plugin_root . '/migration-runtime/gloskin-insights-v1';
	@mkdir( $dst, 0777, true );
	foreach ( array( 'manifest.json','posts.json','media.json' ) as $file ) { copy( $src . '/' . $file, $dst . '/' . $file ); }
	file_put_contents( $plugin_root . '/gloskin-site-core.php', "<?php\n" );
	return $plugin_root . '/gloskin-site-core.php';
};
$reset = static function (): void {
	foreach ( $GLOBALS['fake_files'] as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
	$GLOBALS['fake_options']=array(); $GLOBALS['fake_posts']=array(); $GLOBALS['fake_meta']=array(); $GLOBALS['fake_terms']=array();
	$GLOBALS['fake_post_categories']=array(); $GLOBALS['fake_thumbnails']=array(); $GLOBALS['fake_files']=array();
	$GLOBALS['next_post_id']=1; $GLOBALS['next_term_id']=1; $GLOBALS['fail_download_once']=false; $GLOBALS['fail_cleanup']=false; $GLOBALS['deleted_post_ids']=array();
};
$count_bundle = static function ( string $type ): int {
	$count = 0;
	foreach ( $GLOBALS['fake_posts'] as $id=>$post ) {
		if ( $post->post_type === $type && 'gloskin-insights-v1' === (string) get_post_meta( $id, Gloskin_Site_Core_Insight_Importer::BUNDLE_META, true ) ) { $count++; }
	}
	return $count;
};

$reset();
$unrelated_post = wp_insert_post( array( 'post_type'=>'post','post_status'=>'publish','post_name'=>'unrelated','post_title'=>'Unrelated','post_excerpt'=>'keep','post_content'=>'keep' ), true );
$unrelated_attachment = wp_insert_post( array( 'post_type'=>'attachment','post_status'=>'inherit','post_name'=>'unrelated-media' ), true );
$unrelated_file = tempnam( sys_get_temp_dir(), 'unrelated-media-' ); file_put_contents( $unrelated_file, 'keep' ); $GLOBALS['fake_files'][ $unrelated_attachment ] = $unrelated_file;

$tmp = sys_get_temp_dir() . '/gloskin-insight-import-' . bin2hex( random_bytes( 4 ) );
@mkdir( $tmp, 0777, true );
$plugin_file = $copy_runtime( $tmp );
$importer = new Gloskin_Site_Core_Insight_Importer( $plugin_file );

$start = $importer->advance( 'start' );
$assert( 'running' === $start['status'] && 0 === $start['processed_posts'], 'start checkpoint should validate and initialize only' );
$assert( 0 === $count_bundle( 'post' ) && 0 === $count_bundle( 'attachment' ) && 0 === count( $GLOBALS['fake_terms'] ), 'start checkpoint mutated WordPress content' );

$GLOBALS['fail_download_once'] = true;
$failed = false;
try { $importer->advance( 'continue' ); } catch ( RuntimeException $error ) { $failed = false !== strpos( $error->getMessage(), 'injected download failure' ); }
$assert( $failed, 'injected partial media failure was not surfaced' );
$state = $importer->get_state();
$assert( 'failed' === $state['status'] && 0 === (int) $state['next_post_index'], 'partial failure advanced checkpoint incorrectly' );
$assert( 1 === $count_bundle( 'post' ) && 0 === $count_bundle( 'attachment' ), 'partial failure should leave one owned draft and no media' );
$drafts = get_posts( array( 'post_type'=>'post','post_status'=>'any','meta_key'=>Gloskin_Site_Core_Insight_Importer::BUNDLE_META,'meta_value'=>'gloskin-insights-v1','fields'=>'ids','numberposts'=>-1 ) );
$assert( 'draft' === get_post( $drafts[0] )->post_status, 'incomplete seed post was published' );

$first = $importer->advance( 'continue' );
$assert( 1 === (int) $first['processed_posts'] && 1 === (int) $first['next_post_index'], 'retry did not resume the same checkpoint' );
$assert( 1 === $count_bundle( 'post' ) && 1 === $count_bundle( 'attachment' ), 'retry duplicated first post/media pair' );
$assert( 'publish' === get_post( $drafts[0] )->post_status, 'complete first post was not published' );

for ( $i = 0; $i < 12; $i++ ) {
	$before_posts = $count_bundle( 'post' );
	$before_media = $count_bundle( 'attachment' );
	$step = $importer->advance( 'continue' );
	$assert( $count_bundle( 'post' ) - $before_posts <= 1, 'continuation created more than one article' );
	$assert( $count_bundle( 'attachment' ) - $before_media <= 1, 'continuation created more than one media object' );
}
$preverify = $importer->get_state();
$assert( 'verifying' === $preverify['status'] && 13 === (int) $preverify['next_post_index'], '13th pair should enter verifying checkpoint' );
$done = $importer->advance( 'continue' );
$assert( 'consumed' === $done['status'], 'final 13-record verification did not consume migration' );
$assert( 'complete' === $done['cleanup'], 'runtime cleanup should complete after consumed' );
$assert( 13 === $count_bundle( 'post' ) && 13 === $count_bundle( 'attachment' ), 'final object counts are not 13/13' );
$assert( 5 === count( $GLOBALS['fake_terms'] ), 'five native categories were not seeded/reused' );
$assert( isset( $GLOBALS['fake_posts'][ $unrelated_post ], $GLOBALS['fake_posts'][ $unrelated_attachment ] ), 'unrelated WordPress content was deleted' );
$assert( is_file( $unrelated_file ), 'unrelated media file was deleted' );
$assert( array() === $GLOBALS['deleted_post_ids'], 'importer invoked unrelated post deletion' );
foreach ( get_posts( array( 'post_type'=>'post','post_status'=>'publish','meta_key'=>Gloskin_Site_Core_Insight_Importer::BUNDLE_META,'meta_value'=>'gloskin-insights-v1','fields'=>'ids','numberposts'=>-1 ) ) as $post_id ) {
	$thumb = get_post_thumbnail_id( $post_id );
	$assert( $thumb > 0 && false === strpos( (string) wp_get_attachment_url( $thumb ), 'pexels.com' ), 'post does not use a local featured attachment' );
	$assert( '' !== (string) get_post_meta( $thumb, Gloskin_Site_Core_Insight_Importer::MEDIA_PAGE_META, true ), 'media provenance missing' );
}
$counts_before = array( $count_bundle( 'post' ), $count_bundle( 'attachment' ) );
$blocked = false;
try { $importer->advance( 'continue' ); } catch ( RuntimeException $error ) { $blocked = false !== strpos( $error->getMessage(), 'dikonsumsi' ); }
$assert( $blocked && $counts_before === array( $count_bundle( 'post' ), $count_bundle( 'attachment' ) ), 'double import after consumed was not blocked' );

// Consumed must stay authoritative even if manifest-confined cleanup fails.
$copy_runtime( $tmp );
$state = $importer->get_state();
$state['status'] = 'verifying'; $state['cleanup'] = 'pending'; $state['cleanup_error'] = '';
update_option( Gloskin_Site_Core_Insight_Importer::STATE_OPTION, $state, false );
$GLOBALS['fail_cleanup'] = true;
$cleanup_failed = $importer->advance( 'continue' );
$assert( 'consumed' === $cleanup_failed['status'] && 'failed' === $cleanup_failed['cleanup'], 'cleanup failure reopened or failed logical consumption' );
$still_blocked = false;
try { $importer->advance( 'continue' ); } catch ( RuntimeException $error ) { $still_blocked = true; }
$assert( $still_blocked, 'consumed migration reopened after cleanup failure' );
$GLOBALS['fail_cleanup'] = false;

// An unowned existing post occupying an incoming slug must fail safely.
$reset();
$tmp2 = sys_get_temp_dir() . '/gloskin-insight-import-' . bin2hex( random_bytes( 4 ) );
@mkdir( $tmp2, 0777, true );
$plugin_file2 = $copy_runtime( $tmp2 );
$collision_id = wp_insert_post( array(
	'post_type'=>'post','post_status'=>'publish','post_name'=>'memahami-skin-barrier-dan-cara-menjaganya',
	'post_title'=>'Existing Human Post','post_excerpt'=>'owner','post_content'=>'owner'
), true );
$importer2 = new Gloskin_Site_Core_Insight_Importer( $plugin_file2 );
$importer2->advance( 'start' );
$collision = false;
try { $importer2->advance( 'continue' ); } catch ( RuntimeException $error ) { $collision = false !== strpos( $error->getMessage(), 'tidak dimiliki bundle' ); }
$assert( $collision, 'unowned slug collision did not fail safely' );
$assert( 'Existing Human Post' === get_post( $collision_id )->post_title, 'unowned slug collision overwrote existing content' );
$assert( '' === (string) get_post_meta( $collision_id, Gloskin_Site_Core_Insight_Importer::SOURCE_META, true ), 'unowned collision was silently claimed' );

echo "insight-importer-hardening.php: OK\n";
