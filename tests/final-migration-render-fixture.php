<?php
declare(strict_types=1);

/**
 * Live-like Final Migration integration fixture.
 *
 * Reuses the canonical runtime-smoke WordPress model in-memory, stops that
 * bootstrap immediately after normal plugin activation, copies the three
 * immutable bundles to a disposable plugin root, runs the real Final Migration
 * to consumed, then renders the main routes from the resulting migrated state.
 * Tracked files are never mutated.
 */

$root = dirname( __DIR__ );
$tmp  = sys_get_temp_dir() . '/gloskin-final-render-' . getmypid() . '-' . bin2hex( random_bytes( 4 ) );
$upload_dir = $tmp . '/uploads';
$fake_plugin_root = $tmp . '/gloskin-site-core';
$fake_plugin = $fake_plugin_root . '/gloskin-site-core.php';

function gl_fixture_ok( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}
function gl_fixture_copy_dir( string $source, string $dest ): void {
	if ( ! is_dir( $dest ) && ! mkdir( $dest, 0777, true ) && ! is_dir( $dest ) ) { throw new RuntimeException( 'Cannot create fixture dir: ' . $dest ); }
	foreach ( scandir( $source ) ?: array() as $item ) {
		if ( '.' === $item || '..' === $item ) { continue; }
		$from = $source . '/' . $item; $to = $dest . '/' . $item;
		if ( is_dir( $from ) ) { gl_fixture_copy_dir( $from, $to ); }
		elseif ( ! copy( $from, $to ) ) { throw new RuntimeException( 'Cannot copy fixture file: ' . $from ); }
	}
}
function gl_fixture_remove_dir( string $dir ): void {
	if ( ! is_dir( $dir ) ) { return; }
	foreach ( scandir( $dir ) ?: array() as $item ) {
		if ( '.' === $item || '..' === $item ) { continue; }
		$path = $dir . '/' . $item;
		is_dir( $path ) ? gl_fixture_remove_dir( $path ) : @unlink( $path );
	}
	@rmdir( $dir );
}
register_shutdown_function( static function () use ( $tmp ): void { gl_fixture_remove_dir( $tmp ); } );

foreach ( array( $upload_dir, $fake_plugin_root . '/migration-runtime' ) as $dir ) {
	if ( ! mkdir( $dir, 0777, true ) && ! is_dir( $dir ) ) { throw new RuntimeException( 'Cannot create ' . $dir ); }
}
file_put_contents( $fake_plugin, "<?php\n" );
foreach ( array( 'gloskin-doctors-v1', 'gloskin-doctor-photos-v2', 'gloskin-editorial-media-v1' ) as $bundle ) {
	gl_fixture_copy_dir( $root . '/plugin/gloskin-site-core/migration-runtime/' . $bundle, $fake_plugin_root . '/migration-runtime/' . $bundle );
}

$runtime = file_get_contents( __DIR__ . '/runtime-smoke.php' );
gl_fixture_ok( is_string( $runtime ), 'runtime smoke source readable' );
$runtime = preg_replace( '/^<\?php\s*declare\(strict_types=1\);\s*/', '', $runtime, 1, $bootstrap_header_count );
gl_fixture_ok( 1 === $bootstrap_header_count && is_string( $runtime ), 'runtime bootstrap header normalized in memory' );
$runtime = str_replace( 'class WP_Error {}', 'class WP_Error { public function get_error_message() { return \'fixture error\'; } }', $runtime, $error_class_count );
gl_fixture_ok( 1 === $error_class_count, 'runtime WP_Error fixture enriched' );

/* Make all image markup self-contained for page.set_content browser proof. */
$runtime = str_replace(
	"function plugins_url( \$src, \$file ) { return 'https://example.test/plugins/gloskin/' . ltrim( \$src, '/' ); }",
	"function plugins_url( \$src, \$file ) { if ( preg_match( '/\\.(?:svg|png|ico|webp)$/i', (string) \$src ) ) { return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='; } return 'https://example.test/plugins/gloskin/' . ltrim( \$src, '/' ); }",
	$runtime,
	$plugin_url_count
);
gl_fixture_ok( 1 === $plugin_url_count, 'runtime image asset URLs made self-contained' );
$runtime = str_replace(
	"function wp_get_attachment_image() { return '<img alt=\"\">'; }",
	"function wp_get_attachment_image( \$id = 0, \$size = 'thumbnail', \$icon = false, \$attr = array() ) { unset( \$size, \$icon ); \$alt = array_key_exists( 'alt', \$attr ) ? (string) \$attr['alt'] : (string) get_post_meta( \$id, '_wp_attachment_image_alt', true ); \$class = (string) ( \$attr['class'] ?? 'gl-test-attachment' ); return '<img src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" width=\"1200\" height=\"800\" class=\"' . esc_attr( \$class ) . '\" alt=\"' . esc_attr( \$alt ) . '\" data-test-attachment-id=\"' . absint( \$id ) . '\">'; }",
	$runtime,
	$image_function_count
);
gl_fixture_ok( 1 === $image_function_count, 'runtime attachment image renderer enriched' );

/* Migration/importers require exact meta filtering, including post_status=any. */
$enhanced_get_posts = <<<'PHP'
function get_posts( $args = array() ) {
	$posts = array_values( array_filter( $GLOBALS['gl_posts'], static function ( $post ) use ( $args ) {
		if ( isset( $args['post_type'] ) && ! in_array( $post->post_type, (array) $args['post_type'], true ) ) { return false; }
		if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] && ! in_array( $post->post_status, (array) $args['post_status'], true ) ) { return false; }
		if ( isset( $args['post__in'] ) && ! in_array( $post->ID, (array) $args['post__in'], true ) ) { return false; }
		if ( isset( $args['title'] ) && (string) $post->post_title !== (string) $args['title'] ) { return false; }
		if ( isset( $args['meta_key'] ) ) {
			$key = (string) $args['meta_key']; $meta = $GLOBALS['gl_meta'][ $post->ID ] ?? array();
			if ( ! array_key_exists( $key, $meta ) ) { return false; }
			if ( array_key_exists( 'meta_value', $args ) && (string) $meta[$key] !== (string) $args['meta_value'] ) { return false; }
		}
		foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
			if ( ! is_array( $clause ) || empty( $clause['key'] ) ) { continue; }
			$key = (string) $clause['key']; $meta = $GLOBALS['gl_meta'][ $post->ID ] ?? array(); $exists = array_key_exists( $key, $meta );
			if ( 'EXISTS' === strtoupper( (string) ( $clause['compare'] ?? '' ) ) ) { if ( ! $exists ) { return false; } continue; }
			if ( ! $exists || ( array_key_exists( 'value', $clause ) && (string) $meta[$key] !== (string) $clause['value'] ) ) { return false; }
		}
		return true;
	} ) );
	usort( $posts, static function ( $a, $b ) { $cmp = strcasecmp( (string) $a->post_title, (string) $b->post_title ); return 0 !== $cmp ? $cmp : (int) $a->ID <=> (int) $b->ID; } );
	$limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : ( isset( $args['numberposts'] ) ? (int) $args['numberposts'] : 5 );
	$posts = $limit < 0 ? $posts : array_slice( $posts, 0, $limit );
	return 'ids' === ( $args['fields'] ?? '' ) ? array_map( static fn( $post ) => $post->ID, $posts ) : $posts;
}
PHP;
$runtime = preg_replace( '/function get_posts\( \$args = array\(\) \) \{.*?\n\}\nfunction wp_count_posts/s', $enhanced_get_posts . "\nfunction wp_count_posts", $runtime, 1, $get_posts_count );
gl_fixture_ok( 1 === $get_posts_count, 'runtime get_posts enriched for migration queries' );

/* Replace fixed no-menu stubs with a tiny real in-memory menu model. */
$runtime = str_replace(
	"function has_nav_menu( \$location ) { return false; }\nfunction get_nav_menu_locations() { return array(); }\nfunction wp_get_nav_menu_items( \$id ) { return array(); }",
	"function has_nav_menu( \$location ) { \$locations = get_nav_menu_locations(); return ! empty( \$locations[\$location] ); }\nfunction get_nav_menu_locations() { \$value = \$GLOBALS['gl_theme_mods']['nav_menu_locations'] ?? array(); return is_array( \$value ) ? \$value : array(); }\nfunction wp_get_nav_menu_items( \$id ) { \$items = array_values( \$GLOBALS['gl_menus'][(int) \$id] ?? array() ); usort( \$items, static fn( \$a, \$b ) => (int) ( \$a->menu_order ?? 0 ) <=> (int) ( \$b->menu_order ?? 0 ) ); return \$items; }",
	$runtime,
	$nav_stub_count
);
gl_fixture_ok( 1 === $nav_stub_count, 'runtime nav stubs enriched' );

$injection = <<<'PHP'
$GLOBALS['gl_theme_mods'] = array();
$GLOBALS['gl_menus'] = array(); $GLOBALS['gl_menu_names'] = array();
$GLOBALS['gl_next_menu_id'] = 8000; $GLOBALS['gl_next_menu_item_id'] = 9000;
$GLOBALS['gl_attached_files'] = array(); $GLOBALS['gl_uuid_counter'] = 0;
$GLOBALS['gl_fixture_upload_dir'] = '__UPLOAD_DIR__';
function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) { unset( $deprecated, $autoload ); if ( array_key_exists( $key, $GLOBALS['gl_options'] ) ) { return false; } $GLOBALS['gl_options'][$key] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['gl_options'][$key] ); return true; }
function wp_generate_uuid4() { return 'fixture-token-' . ++$GLOBALS['gl_uuid_counter']; }
function wp_get_environment_type() { return 'staging'; }
function wp_update_post( $data, $wp_error = false ) { unset( $wp_error ); $id = absint( $data['ID'] ?? 0 ); $post = get_post( $id ); if ( ! $post ) { return new WP_Error(); } foreach ( array( 'post_status','post_title','post_name','post_content','post_excerpt','post_parent' ) as $key ) { if ( array_key_exists( $key, $data ) ) { $post->{$key} = $data[$key]; } } return $id; }
function get_post_status( $id ) { $post = get_post( $id ); return $post ? $post->post_status : false; }
function sanitize_mime_type( $value ) { return strtolower( preg_replace( '/[^a-z0-9_+\-.\/]/i', '', (string) $value ) ); }
function sanitize_file_name( $value ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', basename( (string) $value ) ); }
function wp_upload_dir() { $path = $GLOBALS['gl_fixture_upload_dir']; return array( 'path'=>$path,'basedir'=>$path,'url'=>'https://example.test/uploads','baseurl'=>'https://example.test/uploads','subdir'=>'','error'=>false ); }
function wp_unique_filename( $dir, $filename ) { $candidate = basename( (string) $filename ); $stem = pathinfo( $candidate, PATHINFO_FILENAME ); $ext = pathinfo( $candidate, PATHINFO_EXTENSION ); $i=1; while ( file_exists( rtrim( (string) $dir, '/' ) . '/' . $candidate ) ) { $candidate = $stem . '-' . $i++ . ( '' !== $ext ? '.' . $ext : '' ); } return $candidate; }
function wp_check_filetype( $filename, $mimes = null ) { unset( $mimes ); $ext = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) ); $types=array('webp'=>'image/webp','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'); return array('ext'=>$ext,'type'=>$types[$ext]??'application/octet-stream'); }
function wp_insert_attachment( $data, $file = '', $parent = 0, $wp_error = false ) { unset( $parent, $wp_error ); $id = wp_insert_post( array( 'post_type'=>'attachment','post_status'=>$data['post_status']??'inherit','post_title'=>$data['post_title']??'','post_name'=>sanitize_title($data['post_title']??basename((string)$file)),'post_content'=>$data['post_content']??'' ), true ); if ( $id ) { $GLOBALS['gl_attached_files'][(int)$id]=(string)$file; } return $id; }
function wp_generate_attachment_metadata( $id, $file ) { unset( $id ); $info=@getimagesize($file); return array('file'=>basename((string)$file),'width'=>(int)($info[0]??0),'height'=>(int)($info[1]??0)); }
function wp_update_attachment_metadata( $id, $metadata ) { $GLOBALS['gl_meta'][(int)$id]['_wp_attachment_metadata']=$metadata; return true; }
function get_attached_file( $id ) { return $GLOBALS['gl_attached_files'][(int)$id]??''; }
function set_post_thumbnail( $post_id, $attachment_id ) { $GLOBALS['gl_meta'][(int)$post_id]['_thumbnail_id']=absint($attachment_id); return true; }
function get_theme_mod( $key, $default = false ) { return array_key_exists($key,$GLOBALS['gl_theme_mods'])?$GLOBALS['gl_theme_mods'][$key]:$default; }
function set_theme_mod( $key, $value ) { $GLOBALS['gl_theme_mods'][$key]=$value; return true; }
function wp_get_nav_menu_object( $id_or_name ) { if ( is_string($id_or_name) && !ctype_digit($id_or_name) ) { foreach($GLOBALS['gl_menu_names'] as $id=>$name){ if($name===$id_or_name){return (object)array('term_id'=>(int)$id,'name'=>$name);} } return false; } $id=(int)$id_or_name; return isset($GLOBALS['gl_menus'][$id])?(object)array('term_id'=>$id,'name'=>$GLOBALS['gl_menu_names'][$id]??''):false; }
function wp_create_nav_menu( $name ) { $id=$GLOBALS['gl_next_menu_id']++; $GLOBALS['gl_menus'][$id]=array(); $GLOBALS['gl_menu_names'][$id]=(string)$name; return $id; }
function wp_update_nav_menu_item( $menu_id, $item_id, $args ) { $menu_id=(int)$menu_id; if(!isset($GLOBALS['gl_menus'][$menu_id])){return new WP_Error();} $item_id=absint($item_id); if(!$item_id){$item_id=$GLOBALS['gl_next_menu_item_id']++;} $item=$GLOBALS['gl_posts'][$item_id]??new WP_Post(array('ID'=>$item_id,'post_type'=>'nav_menu_item','post_status'=>'publish','post_title'=>'','post_name'=>'menu-'.$item_id,'post_parent'=>0,'post_content'=>'','post_excerpt'=>'')); $item->title=(string)($args['menu-item-title']??''); $item->type=(string)($args['menu-item-type']??'custom'); $item->object=(string)($args['menu-item-object']??'custom'); $item->object_id=absint($args['menu-item-object-id']??0); $item->menu_item_parent=(string)absint($args['menu-item-parent-id']??0); $item->menu_order=(int)($args['menu-item-position']??0); $item->description=(string)($args['menu-item-description']??''); $item->attr_title=(string)($args['menu-item-attr-title']??''); $item->target=(string)($args['menu-item-target']??''); $item->classes=(array)($args['menu-item-classes']??array()); $item->xfn=(string)($args['menu-item-xfn']??''); $url=(string)($args['menu-item-url']??''); if('post_type'===$item->type&&'page'===$item->object&&$item->object_id){$page=get_post($item->object_id);$url=$page?home_url('/'.get_page_uri($page->ID).'/'):$url;} $item->url=$url; $GLOBALS['gl_posts'][$item_id]=$item; $GLOBALS['gl_menus'][$menu_id][$item_id]=$item; return $item_id; }
function wp_delete_post( $id, $force = false ) { unset($force); $id=absint($id); foreach($GLOBALS['gl_menus'] as $menu_id=>$items){unset($GLOBALS['gl_menus'][$menu_id][$id]);} unset($GLOBALS['gl_posts'][$id],$GLOBALS['gl_meta'][$id],$GLOBALS['gl_attached_files'][$id]); return true; }
PHP;
$injection = str_replace( '__UPLOAD_DIR__', addslashes( $upload_dir ), $injection );
$plugin_needle = "$plugin = dirname( __DIR__ ) . '/plugin/gloskin-site-core/gloskin-site-core.php';";
$runtime = str_replace( $plugin_needle, $injection . "\n\n" . $plugin_needle, $runtime, $injection_count );
gl_fixture_ok( 1 === $injection_count, 'migration WordPress primitives injected' );

/* Stop canonical runtime-smoke immediately after normal frontend activation. */
$activation_needle = "if ( \$GLOBALS['gl_is_admin'] ) {\n\tdo_action( 'admin_init' );\n} else {\n\tcall_user_func( \$GLOBALS['gl_activation'] );\n}";
$runtime = str_replace( $activation_needle, $activation_needle . "\nreturn; // integration fixture bootstrap boundary", $runtime, $activation_count );
gl_fixture_ok( 1 === $activation_count, 'runtime bootstrap boundary injected after activation' );

putenv( 'GL_TEST_WOO=1' );
ob_start();
eval( $runtime );
ob_end_clean();

/* Add realistic pre-final content that Final Migration must preserve. */
$treatment_id = wp_insert_post( array( 'post_type'=>Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE,'post_status'=>'publish','post_title'=>'Fixture Treatment','post_name'=>'fixture-treatment','post_content'=>'Editor treatment content' ), true );
$product_id = wp_insert_post( array( 'post_type'=>'product','post_status'=>'publish','post_title'=>'Test Product','post_name'=>'test-product','post_content'=>'Editor product content' ), true );
gl_fixture_ok( $treatment_id > 0 && $product_id > 0, 'pre-final treatment/product fixtures created' );

require_once $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php';
unset( $GLOBALS['gl_options']['gloskin_site_core_revision_20260819f_state'], $GLOBALS['gl_options']['gloskin_site_core_revision_20260819f_lock'] );
unset( $GLOBALS['gl_options']['gloskin_site_core_doctor_migration_v1'], $GLOBALS['gl_options']['gloskin_site_core_doctor_migration_v1_lock'] );
$migration = new Gloskin_Site_Core_Revision_20260819_Final_Migration( $fake_plugin );
$state = $migration->run_to_completion();
gl_fixture_ok( 'consumed' === (string) $state['status'], 'Final Migration consumed from fresh/pre-final state' );

/* Lazy-load importer now that Final Migration owns/reuses it. */
require_once $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-doctor-bundle.php';
foreach ( array( 'state','upsert','finalize','lock' ) as $part ) { require_once $root . '/plugin/gloskin-site-core/includes/gloskin-site-core-doctor-importer-' . $part . '-trait.php'; }
require_once $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-doctor-importer.php';
$roster_state = ( new Gloskin_Site_Core_Doctor_Importer( $fake_plugin ) )->state();
gl_fixture_ok( 'consumed' === (string) $roster_state['status'] && 13 === (int) $roster_state['index'], 'doctor roster consumed at exact 13-record cursor' );
gl_fixture_ok( 13 === count( array_unique( array_map( 'absint', (array) $roster_state['imported_ids'] ) ) ), 'doctor roster imported IDs remain unique' );

$catalog = get_option( Gloskin_Site_Core_Editorial_Media_Bundle::OPTION, array() );
gl_fixture_ok( is_array( $catalog ) && 6 === count( $catalog ), 'editorial catalog contains six local migrated assets' );
foreach ( array( 'home_why','home_brand_story','treatment_discovery','treatment_clinical','skincare_editorial','about_story' ) as $key ) {
	$id = absint( $catalog[$key]['attachment_id'] ?? 0 ); $file = $id ? get_attached_file( $id ) : '';
	gl_fixture_ok( $id > 0 && is_string( $file ) && is_readable( $file ), 'local editorial attachment readable: ' . $key );
}

$home = get_page_by_path( 'home', OBJECT, 'page' );
gl_fixture_ok( $home instanceof WP_Post && (int) get_option( 'page_on_front', 0 ) === (int) $home->ID, 'canonical Home stored as page_on_front' );
$locations = get_theme_mod( 'nav_menu_locations', array() ); $primary_id = absint( $locations['gloskin-primary'] ?? 0 );
$actual_menu = array_map( static fn( $item ) => array( (string)$item->title, (string)parse_url( (string)$item->url, PHP_URL_PATH ) ), wp_get_nav_menu_items( $primary_id ) );
gl_fixture_ok( array(array('Perawatan','/treatments/'),array('Promo','/promo/'),array('Skincare','/skincare/'),array('Tentang Gloskin','/about/')) === $actual_menu, 'stored primary IA exact after migration' );

$official_doctor = null;
foreach ( get_posts( array( 'post_type'=>Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE,'post_status'=>'publish','posts_per_page'=>-1 ) ) as $candidate ) {
	if ( '' !== (string)get_post_meta( $candidate->ID, Gloskin_Site_Core_Doctor_Importer::SOURCE_META, true ) && get_post_thumbnail_id( $candidate->ID ) ) { $official_doctor=$candidate; break; }
}
gl_fixture_ok( $official_doctor instanceof WP_Post, 'migrated doctor detail target has factual portrait' );
$clinic_detail = get_page_by_path( 'tebet', OBJECT, Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE );
$treatment_detail = get_page_by_path( 'fixture-treatment', OBJECT, Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE );
gl_fixture_ok( $clinic_detail instanceof WP_Post && $treatment_detail instanceof WP_Post, 'clinic/treatment detail targets available' );

$route_specs = array(
	'home'=>array($home,true,true,''),
	'treatments'=>array(get_page_by_path('treatments',OBJECT,'page'),false,true,''),
	'promo'=>array(get_page_by_path('promo',OBJECT,'page'),false,true,''),
	'skincare'=>array(get_page_by_path('skincare',OBJECT,'page'),false,true,''),
	'about'=>array(get_page_by_path('about',OBJECT,'page'),false,true,''),
	'doctors'=>array(get_page_by_path('doctors',OBJECT,'page'),false,true,''),
	'doctor-detail'=>array($official_doctor,false,false,Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE),
	'clinics'=>array(get_page_by_path('clinics',OBJECT,'page'),false,true,''),
	'clinic-detail'=>array($clinic_detail,false,false,Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE),
	'insights'=>array(get_page_by_path('insights',OBJECT,'page'),false,true,''),
	'treatment-detail'=>array($treatment_detail,false,false,Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE),
	'shop'=>array(get_page_by_path('shop',OBJECT,'page'),false,true,''),
);
$routes = array();
foreach ( $route_specs as $name=>$spec ) {
	gl_fixture_ok( $spec[0] instanceof WP_Post, 'route object available: ' . $name );
	$GLOBALS['gl_loop_consumed']=false; $GLOBALS['gl_query_vars']=array();
	$GLOBALS['gl_route']=array('front'=>$spec[1],'page'=>$spec[2],'singular'=>$spec[3],'object'=>$spec[0],'shop'=>false,'woo'=>false,'cart'=>false,'checkout'=>false,'account'=>false);
	$template = apply_filters( 'template_include', '/theme/index.php' );
	ob_start(); require $template; $html=(string)ob_get_clean();
	gl_fixture_ok( false !== strpos( $html, 'data-gloskin-drawer' ), 'migrated shell rendered: ' . $name );
	$routes[$name]=$html;
}

$result=array('final_status'=>(string)$state['status'],'roster_status'=>(string)$roster_state['status'],'roster_index'=>(int)$roster_state['index'],'catalog_keys'=>array_keys($catalog),'routes'=>$routes);
if ( '1' === getenv( 'GLOSKIN_MIGRATION_RENDER_JSON' ) ) { echo json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
else { echo 'final-migration-render-fixture.php: OK (consumed -> local catalog -> IA -> ' . count($routes) . " rendered routes)\n"; }
