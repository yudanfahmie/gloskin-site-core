<?php
declare(strict_types=1);

define( 'ABSPATH', sys_get_temp_dir() . '/gloskin-test-wp/' );
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		public function __construct( string $code = '', string $message = '' ) { $this->code = $code; $this->message = $message; }
		public function get_error_message(): string { return $this->message; }
	}
}
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function trailingslashit( $value ): string { return rtrim( (string) $value, "/\\" ) . '/'; }
function plugin_dir_path( $file ): string { return trailingslashit( dirname( (string) $file ) ); }
function sanitize_title( $value ): string {
	$value = strtolower( trim( preg_replace( '/[^a-zA-Z0-9]+/', '-', (string) $value ), '-' ) );
	return $value;
}
function sanitize_key( $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function wp_strip_all_tags( $value ): string { return trim( strip_tags( (string) $value ) ); }
function wp_kses_post( $value ): string { return (string) $value; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function wp_delete_file( $path ): void { if ( is_file( $path ) ) { unlink( $path ); } }

$root = dirname( __DIR__ );
require_once $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-insight-bundle.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
};
$copy_bundle = static function ( string $destination ) use ( $root ): string {
	$source = $root . '/plugin/gloskin-site-core/migration-runtime/gloskin-insights-v1';
	@mkdir( $destination . '/migration-runtime/gloskin-insights-v1', 0777, true );
	foreach ( array( 'manifest.json','posts.json','media.json' ) as $file ) {
		copy( $source . '/' . $file, $destination . '/migration-runtime/gloskin-insights-v1/' . $file );
	}
	file_put_contents( $destination . '/gloskin-site-core.php', "<?php\n" );
	return $destination . '/gloskin-site-core.php';
};

$source_dir = $root . '/migration-source/gloskin-insights-v1';
$runtime_dir = $root . '/plugin/gloskin-site-core/migration-runtime/gloskin-insights-v1';
foreach ( array( 'manifest.json','posts.json','media.json' ) as $file ) {
	$assert( hash_file( 'sha256', $source_dir . '/' . $file ) === hash_file( 'sha256', $runtime_dir . '/' . $file ), "source/runtime {$file} diverged" );
}

$bundle = new Gloskin_Site_Core_Insight_Bundle( $root . '/plugin/gloskin-site-core/gloskin-site-core.php' );
$validated = $bundle->validate();
$assert( 13 === count( $validated['posts'] ), 'seed posts must be exactly 13' );
$assert( 13 === count( $validated['media'] ), 'featured media must be exactly 13' );
$assert( 5 === count( $validated['categories'] ), 'native category declarations must be exactly 5' );
$assert( 13 === count( array_unique( array_column( $validated['posts'], 'source_id' ) ) ), 'post source IDs must be unique' );
$assert( 13 === count( array_unique( array_column( $validated['media'], 'source_id' ) ) ), 'media source IDs must be unique' );
$assert( 13 === count( array_unique( array_column( $validated['media'], 'source_url' ) ) ), 'featured media sources must be unique' );
$assert( array( 'anti-aging','kesehatan-kulit','perawatan','rambut','skincare' ) === ( static function ( $rows ) { $slugs = array_column( $rows, 'slug' ); sort( $slugs ); return $slugs; } )( $validated['categories'] ), 'native category allowlist mismatch' );

$posts_payload = json_decode( file_get_contents( $runtime_dir . '/posts.json' ), true );
foreach ( $posts_payload['posts'] as $post ) {
	$text = wp_strip_all_tags( $post['content_html'] );
	$assert( strlen( $text ) >= 1800, 'editorial article is a stub: ' . $post['slug'] );
	$assert( substr_count( $post['content_html'], '<h2>' ) >= 4, 'editorial structure too shallow: ' . $post['slug'] );
	foreach ( array( 'lorem ipsum','pasien mengatakan','dokter gloskin mengatakan','% berhasil' ) as $forbidden ) {
		$assert( false === stripos( $text, $forbidden ), 'forbidden fabricated/stub pattern in ' . $post['slug'] );
	}
}

$tmp = sys_get_temp_dir() . '/gloskin-insight-bundle-' . bin2hex( random_bytes( 4 ) );
$plugin_file = $copy_bundle( $tmp );
$dup_posts_path = $tmp . '/migration-runtime/gloskin-insights-v1/posts.json';
$dup_manifest_path = $tmp . '/migration-runtime/gloskin-insights-v1/manifest.json';
$dup_posts = json_decode( file_get_contents( $dup_posts_path ), true );
$dup_posts['posts'][1]['source_id'] = $dup_posts['posts'][0]['source_id'];
$dup_posts['posts'][1]['slug'] = $dup_posts['posts'][0]['slug'];
$dup_posts['posts'][1]['media_source_id'] = $dup_posts['posts'][0]['media_source_id'];
$raw = json_encode( $dup_posts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
file_put_contents( $dup_posts_path, $raw );
$manifest = json_decode( file_get_contents( $dup_manifest_path ), true );
$manifest['checksums']['posts.json'] = hash( 'sha256', $raw );
file_put_contents( $dup_manifest_path, json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
$threw = false;
try { ( new Gloskin_Site_Core_Insight_Bundle( $plugin_file ) )->validate(); } catch ( RuntimeException $error ) { $threw = false !== stripos( $error->getMessage(), 'duplikat' ); }
$assert( $threw, 'duplicate source IDs must be rejected after checksum-valid payload mutation' );

$tmp2 = sys_get_temp_dir() . '/gloskin-insight-bundle-' . bin2hex( random_bytes( 4 ) );
$plugin2 = $copy_bundle( $tmp2 );
file_put_contents( $tmp2 . '/migration-runtime/gloskin-insights-v1/rogue.txt', 'do not touch' );
$threw = false;
try { ( new Gloskin_Site_Core_Insight_Bundle( $plugin2 ) )->validate(); } catch ( RuntimeException $error ) { $threw = false !== stripos( $error->getMessage(), 'tidak dideklarasikan' ); }
$assert( $threw, 'unexpected runtime file must reject validation' );

$tmp3 = sys_get_temp_dir() . '/gloskin-insight-bundle-' . bin2hex( random_bytes( 4 ) );
$plugin3 = $copy_bundle( $tmp3 );
$outside = $tmp3 . '/unrelated-content.txt';
file_put_contents( $outside, 'keep' );
$bundle3 = new Gloskin_Site_Core_Insight_Bundle( $plugin3 );
$valid3 = $bundle3->validate();
$cleanup = $bundle3->cleanup( $valid3['manifest'] );
$assert( true === $cleanup['ok'], 'valid runtime cleanup should complete' );
$assert( is_file( $outside ), 'cleanup must never delete unrelated files' );
$assert( ! is_dir( $tmp3 . '/migration-runtime/gloskin-insights-v1' ), 'fixed runtime directory should be removed when empty' );

echo "insight-bundle-contract.php: OK\n";
