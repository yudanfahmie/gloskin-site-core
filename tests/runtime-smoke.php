<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

class WP_Error {}
class WP_Term {
	public $slug;
	public function __construct( $slug = '' ) { $this->slug = $slug; }
}
class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_title;
	public $post_name;
	public $post_parent;
	public $post_content;
	public $post_excerpt;
	public function __construct( array $data ) {
		foreach ( $data as $key => $value ) {
			$this->{$key} = $value;
		}
	}
}

class GL_Test_Product {
	private $id;
	public function __construct( $id ) { $this->id = $id; }
	public function get_id() { return $this->id; }
	public function get_name() { return 'Test Product'; }
	public function get_image_id() { return 0; }
	public function get_price_html() { return '<span class="amount">100</span>'; }
	public function get_short_description() { return 'Test product description'; }
	public function get_sku() { return 'TEST-001'; }
	public function add_to_cart_url() { return '/?add-to-cart=' . $this->id; }
	public function add_to_cart_text() { return 'Add to cart'; }
	public function is_purchasable() { return true; }
	public function is_in_stock() { return true; }
	public function get_attribute( $name ) {
		$values = array( 'bpom' => 'NA00000000000', 'composition' => 'Test composition', 'usage' => 'Test usage' );
		return $values[ $name ] ?? '';
	}
	public function get_meta( $key, $single = true ) { return ''; }
}

class WP_Query {
	public $posts = array();
	public $max_num_pages = 1;
	public function __construct( $args = array() ) {
		$this->posts = get_posts( $args );
		$this->max_num_pages = 1;
	}
}

$GLOBALS['gl_hooks'] = array();
$GLOBALS['gl_filters'] = array();
$GLOBALS['gl_posts'] = array();
$GLOBALS['gl_meta'] = array();
$GLOBALS['gl_options'] = array();
$GLOBALS['gl_post_types'] = array();
$GLOBALS['gl_registered_meta'] = array();
$GLOBALS['gl_next_id'] = 1;
$GLOBALS['gl_flushes'] = 0;
$GLOBALS['gl_query_vars'] = array();
$GLOBALS['gl_route'] = array( 'front' => false, 'page' => false, 'singular' => '', 'object' => null );
$GLOBALS['gl_is_admin'] = getenv( 'GL_TEST_ADMIN' ) === '1';
$GLOBALS['gl_woo_late'] = getenv( 'GL_TEST_WOO_LATE' ) === '1';
$GLOBALS['gl_woo'] = getenv( 'GL_TEST_WOO' ) === '1' || $GLOBALS['gl_woo_late'];
$GLOBALS['gl_shortcodes'] = array();
$GLOBALS['gl_loop_consumed'] = false;

/**
 * Woo class/function stubs, extracted into a callable so the load-order
 * regression test below can define them either before or after the plugin
 * boots -- proving Gloskin_Site_Core_WooCommerce_Adapter::is_available()
 * resolves correctly regardless of when WooCommerce actually finished
 * loading relative to Gloskin's own plugin-load pass.
 *
 * @return void
 */
function gl_define_woo_stubs() {
	class WooCommerce {}
	class GL_Test_Cart {
		public function get_cart_contents_count() { return 2; }
		public function get_cart_subtotal() { return '<span>Rp 200.000</span>'; }
		public function get_cart() {
			$product_post = get_page_by_path( 'test-product', OBJECT, 'product' );
			if ( ! $product_post ) {
				return array();
			}
			return array(
				'gl_test_cart_item_key' => array(
					'product_id' => $product_post->ID,
					'quantity'   => 2,
					'variation'  => array( 'attribute_pa_ukuran' => '30ml' ),
					'data'       => new GL_Test_Product( $product_post->ID ),
				),
			);
		}
		public function get_product_price( $p ) { return '<span>Rp 100.000</span>'; }
		public function get_product_subtotal( $p, $q ) { return '<span>Rp 200.000</span>'; }
	}
	$GLOBALS['gl_woo_instance'] = new stdClass();
	$GLOBALS['gl_woo_instance']->cart = new GL_Test_Cart();
	function WC() { return $GLOBALS['gl_woo_instance']; }
	function wc_get_page_id( $page ) {
		$account_page = get_page_by_path( 'shop', OBJECT, 'page' );
		return $account_page ? $account_page->ID : 0;
	}
	function wc_get_cart_url() { return 'https://example.test/cart/'; }
	function wc_get_checkout_url() { return 'https://example.test/checkout/'; }
	function wc_get_product( $id ) {
		$post = get_post( $id );
		return ( $post && $post->post_type === 'product' ) ? new GL_Test_Product( $id ) : null;
	}
	function wc_get_cart_remove_url( $key ) { return '?remove_item=' . $key; }
}

if ( $GLOBALS['gl_woo'] && ! $GLOBALS['gl_woo_late'] ) {
	gl_define_woo_stubs();
}
$GLOBALS['gl_activation'] = null;
$GLOBALS['gl_deactivation'] = null;

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['gl_hooks'][ $hook ][] = array( $priority, $callback, $accepted_args );
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['gl_filters'][ $hook ][] = array( $priority, $callback, $accepted_args );
}
function remove_action( $hook, $callback, $priority = 10 ) {
	if ( empty( $GLOBALS['gl_hooks'][ $hook ] ) ) {
		return false;
	}
	$GLOBALS['gl_hooks'][ $hook ] = array_values(
		array_filter(
			$GLOBALS['gl_hooks'][ $hook ],
			static function ( $item ) use ( $callback, $priority ) {
				return ! ( $item[0] === $priority && $item[1] === $callback );
			}
		)
	);
	return true;
}
function do_action( $hook, ...$args ) {
	$items = $GLOBALS['gl_hooks'][ $hook ] ?? array();
	usort( $items, static fn( $a, $b ) => $a[0] <=> $b[0] );
	foreach ( $items as $item ) {
		call_user_func_array( $item[1], array_slice( $args, 0, $item[2] ) );
	}
}
function apply_filters( $hook, $value, ...$args ) {
	$items = $GLOBALS['gl_filters'][ $hook ] ?? array();
	usort( $items, static fn( $a, $b ) => $a[0] <=> $b[0] );
	foreach ( $items as $item ) {
		$all = array_merge( array( $value ), array_slice( $args, 0, max( 0, $item[2] - 1 ) ) );
		$value = call_user_func_array( $item[1], $all );
	}
	return $value;
}
/* WordPress core registers this unconditionally, independent of any plugin. */
add_action( 'wp_head', 'wp_site_icon', 99 );
function register_activation_hook( $file, $callback ) { $GLOBALS['gl_activation'] = $callback; }
function register_deactivation_hook( $file, $callback ) { $GLOBALS['gl_deactivation'] = $callback; }
function is_admin() { return (bool) $GLOBALS['gl_is_admin']; }
function register_post_type( $type, $args ) { $GLOBALS['gl_post_types'][ $type ] = $args; }
function register_post_meta( $type, $key, $args ) { $GLOBALS['gl_registered_meta'][ $type ][ $key ] = $args; }
function register_nav_menus( $menus ) {}
function has_nav_menu( $location ) { return false; }
function get_nav_menu_locations() { return array(); }
function wp_get_nav_menu_items( $id ) { return array(); }
function add_options_page() {}
function register_setting() {}
function add_meta_box() {}
function wp_enqueue_media() {}
function language_attributes() { echo 'lang="en"'; }
function bloginfo( $show ) { if ( 'charset' === $show ) { echo 'UTF-8'; } else { echo 'Gloskin'; } }
function wp_head() { do_action( 'wp_head' ); echo '<meta name="gloskin-test" content="1">'; }
function has_site_icon() { return (bool) ( $GLOBALS['gl_site_icon'] ?? false ); }
/* Mirrors real WordPress core: always registered on wp_head at priority 99
 * (see wp-includes/default-filters.php), only echoes when a Site Icon is
 * actually configured. Used to prove Gloskin's favicon owner unhooks it. */
function wp_site_icon() {
	if ( has_site_icon() ) {
		echo '<link rel="icon" href="stale-wp-site-icon.png" sizes="32x32" />';
	}
}
function body_class( $classes = array() ) { echo 'class="' . esc_attr( implode( ' ', (array) $classes ) ) . '"'; }
function wp_body_open() {}
function wp_footer() {}
function paginate_links() { return ''; }
function get_current_screen() { return null; }
function wp_register_style() {}
function wp_enqueue_style() {}
function wp_register_script() {}
function wp_enqueue_script() {}
function plugins_url( $src, $file ) { return 'https://example.test/plugins/gloskin/' . ltrim( $src, '/' ); }
function current_user_can( $cap, $id = null ) { return true; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['gl_options'] ) ? $GLOBALS['gl_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['gl_options'][ $key ] = $value; return true; }
function flush_rewrite_rules( $hard = true ) { $GLOBALS['gl_flushes']++; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_title( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = preg_replace( '/[^a-z0-9]+/', '-', $value );
	return trim( (string) $value, '-' );
}
function sanitize_html_class( $value ) { return sanitize_key( $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function esc_url_raw( $value, $schemes = null ) {
	$value = trim( (string) $value );
	if ( '' === $value ) { return ''; }
	$scheme = parse_url( $value, PHP_URL_SCHEME );
	if ( is_array( $schemes ) && $scheme && ! in_array( strtolower( $scheme ), $schemes, true ) ) { return ''; }
	return $value;
}
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_kses_post( $value ) { return (string) $value; }
function wp_unslash( $value ) { return $value; }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_trim_words( $text, $num_words = 55 ) {
	$words = preg_split( '/\s+/', trim( (string) $text ) );
	return implode( ' ', array_slice( $words ?: array(), 0, $num_words ) );
}
function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_attr__( $text, $domain = null ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_textarea( $text ) { return esc_html( $text ); }
function selected( $a, $b, $echo = true ) { $out = $a == $b ? 'selected="selected"' : ''; if ( $echo ) { echo $out; } return $out; }
function home_url( $path = '/' ) { return 'https://example.test' . ( '/' === $path[0] ? $path : '/' . $path ); }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/' ); }
function trailingslashit( $value ) { return rtrim( (string) $value, '/' ) . '/'; }
function get_page_uri( $id ) {
	$post = get_post( $id );
	if ( ! $post ) { return ''; }
	$parts = array( $post->post_name );
	while ( $post->post_parent ) {
		$post = get_post( $post->post_parent );
		if ( ! $post ) { break; }
		array_unshift( $parts, $post->post_name );
	}
	return implode( '/', $parts );
}
function wp_insert_post( $data, $wp_error = false ) {
	$id = $GLOBALS['gl_next_id']++;
	$post = new WP_Post(
		array(
			'ID'           => $id,
			'post_type'    => $data['post_type'] ?? 'post',
			'post_status'  => $data['post_status'] ?? 'draft',
			'post_title'   => $data['post_title'] ?? '',
			'post_name'    => $data['post_name'] ?? sanitize_title( $data['post_title'] ?? '' ),
			'post_parent'  => (int) ( $data['post_parent'] ?? 0 ),
			'post_content' => $data['post_content'] ?? '',
			'post_excerpt' => $data['post_excerpt'] ?? '',
		)
	);
	$GLOBALS['gl_posts'][ $id ] = $post;
	return $id;
}
function get_post( $id ) { return $GLOBALS['gl_posts'][ (int) $id ] ?? null; }
function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) {
	$path = trim( (string) $path, '/' );
	foreach ( $GLOBALS['gl_posts'] as $post ) {
		if ( $post->post_type !== $post_type ) { continue; }
		if ( 'page' === $post_type ) {
			if ( get_page_uri( $post->ID ) === $path ) { return $post; }
		} elseif ( $post->post_name === $path ) {
			return $post;
		}
	}
	return null;
}
function update_post_meta( $id, $key, $value ) {
	if ( isset( $GLOBALS['gl_registered_meta'][ get_post_type( $id ) ][ $key ]['sanitize_callback'] ) ) {
		$value = call_user_func( $GLOBALS['gl_registered_meta'][ get_post_type( $id ) ][ $key ]['sanitize_callback'], $value );
	}
	$GLOBALS['gl_meta'][ (int) $id ][ $key ] = $value;
	return true;
}
function get_post_meta( $id, $key, $single = false ) {
	$value = $GLOBALS['gl_meta'][ (int) $id ][ $key ] ?? ( $single ? '' : array() );
	return $value;
}
function get_post_type( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_type : false;
}
function wp_attachment_is_image( $id ) { return get_post_type( $id ) === 'attachment'; }
function get_posts( $args = array() ) {
	$posts = array_values( array_filter( $GLOBALS['gl_posts'], static function ( $post ) use ( $args ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) { return false; }
		if ( isset( $args['post_status'] ) ) {
			$statuses = (array) $args['post_status'];
			if ( ! in_array( $post->post_status, $statuses, true ) ) { return false; }
		}
		if ( isset( $args['post__in'] ) && ! in_array( $post->ID, (array) $args['post__in'], true ) ) { return false; }
		return true;
	} ) );
	usort( $posts, static fn( $a, $b ) => strcasecmp( $a->post_title, $b->post_title ) );
	$limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : ( isset( $args['numberposts'] ) ? (int) $args['numberposts'] : 5 );
	$posts = $limit < 0 ? $posts : array_slice( $posts, 0, $limit );
	return 'ids' === ( $args['fields'] ?? '' ) ? array_map( static fn( $post ) => $post->ID, $posts ) : $posts;
}
function wp_count_posts( $post_type ) {
	$count = 0;
	foreach ( $GLOBALS['gl_posts'] as $post ) { if ( $post->post_type === $post_type && $post->post_status === 'publish' ) { $count++; } }
	return (object) array( 'publish' => $count );
}
function get_permalink( $post_or_id ) {
	$post = $post_or_id instanceof WP_Post ? $post_or_id : get_post( $post_or_id );
	if ( ! $post ) { return ''; }
	if ( 'page' === $post->post_type ) { return home_url( '/' . get_page_uri( $post->ID ) . '/' ); }
	$base = array( 'gloskin_clinic' => 'clinics', 'gloskin_doctor' => 'doctors', 'gloskin_treatment' => 'treatments' )[ $post->post_type ] ?? '';
	return home_url( '/' . $base . '/' . $post->post_name . '/' );
}
function get_the_title( $post_or_id = 0 ) { $post = $post_or_id instanceof WP_Post ? $post_or_id : get_post( $post_or_id ); return $post ? $post->post_title : ''; }
function has_excerpt( $post ) { return $post instanceof WP_Post && '' !== trim( $post->post_excerpt ); }
function get_the_excerpt( $post ) { return $post instanceof WP_Post ? $post->post_excerpt : ''; }
function get_post_thumbnail_id( $id ) { return (int) ( $GLOBALS['gl_meta'][ (int) $id ]['_thumbnail_id'] ?? 0 ); }
function wp_get_attachment_image() { return '<img alt="">'; }
function set_query_var( $key, $value ) { $GLOBALS['gl_query_vars'][ $key ] = $value; }
function get_query_var( $key, $default = '' ) { return $GLOBALS['gl_query_vars'][ $key ] ?? $default; }
function get_queried_object() { return $GLOBALS['gl_route']['object']; }
function is_front_page() { return (bool) $GLOBALS['gl_route']['front']; }
function is_page() { return (bool) $GLOBALS['gl_route']['page']; }
function is_singular( $type = '' ) { return $GLOBALS['gl_route']['singular'] === $type; }
function is_shop() { return ! empty( $GLOBALS['gl_route']['shop'] ); }
function is_woocommerce() { return ! empty( $GLOBALS['gl_route']['woo'] ); }
function is_cart() { return ! empty( $GLOBALS['gl_route']['cart'] ); }
function is_checkout() { return ! empty( $GLOBALS['gl_route']['checkout'] ); }
function is_account_page() { return ! empty( $GLOBALS['gl_route']['account'] ); }
function wc_get_products( $args = array() ) {
	if ( ! $GLOBALS['gl_woo'] ) { return ! empty( $args['paginate'] ) ? (object) array( 'products' => array(), 'total' => 0, 'max_num_pages' => 1 ) : array(); }
	$product_post = get_page_by_path( 'test-product', OBJECT, 'product' );
	$products = $product_post ? array( new GL_Test_Product( $product_post->ID ) ) : array();
	if ( ! empty( $args['paginate'] ) ) {
		return (object) array( 'products' => $products, 'total' => count( $products ), 'max_num_pages' => 1 );
	}
	return $products;
}
function get_term_by( $field, $value, $taxonomy ) { return ( $GLOBALS['gl_woo'] && 'product_cat' === $taxonomy ) ? new WP_Term( (string) $value ) : false; }
function get_term_link( $term ) { return $term instanceof WP_Term ? home_url( '/product-category/' . $term->slug . '/' ) : new WP_Error(); }
function shortcode_exists( $tag ) { return in_array( $tag, $GLOBALS['gl_shortcodes'], true ); }
function do_shortcode( $value ) { return '<form data-test-form>' . esc_html( $value ) . '</form>'; }
function have_posts() { return ! $GLOBALS['gl_loop_consumed'] && $GLOBALS['gl_route']['object'] instanceof WP_Post; }
function the_post() { $GLOBALS['gl_loop_consumed'] = true; }
function the_content() {
	$kind = is_cart() ? 'cart' : ( is_checkout() ? 'checkout' : ( is_account_page() ? 'account' : 'page' ) );
	echo '<div data-test-native-commerce-content="' . esc_attr( $kind ) . '"></div>';
}
function woocommerce_content() { echo '<div data-test-native-commerce-content="product"></div>'; }
function wp_is_post_revision() { return false; }
function wp_verify_nonce() { return true; }
function wp_nonce_field() {}
function wp_date( $format ) { return '2026'; }
function register_rest_route() {}
function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( $path, '/' ); }
function rest_ensure_response( $data ) { return $data; }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_create_nonce( $action = '' ) { return 'test_nonce_' . $action; }
function is_user_logged_in() { return false; }
if ( ! function_exists( 'mb_strlen' ) ) { function mb_strlen( $str ) { return strlen( $str ); } }

$plugin = dirname( __DIR__ ) . '/plugin/gloskin-site-core/gloskin-site-core.php';
require $plugin;

do_action( 'init' );

if ( ! isset( $GLOBALS['gl_post_types']['gloskin_treatment'], $GLOBALS['gl_post_types']['gloskin_clinic'], $GLOBALS['gl_post_types']['gloskin_doctor'] ) ) {
	fwrite( STDERR, "CPT registration failed\n" );
	exit( 1 );
}

if ( $GLOBALS['gl_is_admin'] ) {
	do_action( 'admin_init' );
} else {
	call_user_func( $GLOBALS['gl_activation'] );
}

$pages = array_filter( $GLOBALS['gl_posts'], static fn( $post ) => $post->post_type === 'page' );
$clinics = array_filter( $GLOBALS['gl_posts'], static fn( $post ) => $post->post_type === 'gloskin_clinic' );
$treatments = array_filter( $GLOBALS['gl_posts'], static fn( $post ) => $post->post_type === 'gloskin_treatment' );
$doctors = array_filter( $GLOBALS['gl_posts'], static fn( $post ) => $post->post_type === 'gloskin_doctor' );

if ( count( $pages ) !== 16 ) {
	fwrite( STDERR, 'Expected 16 provisioned pages, got ' . count( $pages ) . "\n" );
	exit( 1 );
}
if ( count( $clinics ) !== 9 ) {
	fwrite( STDERR, 'Expected 9 provisioned clinics, got ' . count( $clinics ) . "\n" );
	exit( 1 );
}
if ( count( $treatments ) !== 0 || count( $doctors ) !== 0 ) {
	fwrite( STDERR, "Unapproved treatment/doctor records were fabricated\n" );
	exit( 1 );
}

foreach ( array_keys( Gloskin_Site_Core_Content_Service::skincare_definitions() ) as $slug ) {
	$page = get_page_by_path( 'skincare/' . $slug, OBJECT, 'page' );
	if ( ! $page || get_post_meta( $page->ID, 'gloskin_woo_category_slug', true ) !== $slug ) {
		fwrite( STDERR, "Skincare mapping missing for {$slug}\n" );
		exit( 1 );
	}
}

$required_meta = array(
	'gloskin_treatment' => array( 'gloskin_summary', 'gloskin_benefits', 'gloskin_contraindications', 'gloskin_clinic_ids', 'gloskin_doctor_ids' ),
	'gloskin_clinic'    => array( 'gloskin_address', 'gloskin_whatsapp_number', 'gloskin_operating_hours', 'gloskin_map_embed', 'gloskin_gallery_image_ids' ),
	'gloskin_doctor'    => array( 'gloskin_degree_title', 'gloskin_specialization', 'gloskin_branch_ids', 'gloskin_sip_number', 'gloskin_schedule' ),
	'page'              => array( 'gloskin_woo_category_slug', 'gloskin_about_vision', 'gloskin_hero_heading', 'gloskin_hero_media_id' ),
);
foreach ( $required_meta as $type => $keys ) {
	foreach ( $keys as $key ) {
		if ( empty( $GLOBALS['gl_registered_meta'][ $type ][ $key ] ) ) {
			fwrite( STDERR, "Required metadata not registered: {$type}/{$key}\n" ); exit( 1 );
		}
	}
}

if ( ! $GLOBALS['gl_is_admin'] ) {
	$home_before = get_page_by_path( 'home', OBJECT, 'page' );
	$home_before->post_content = 'Approved editor content';
	call_user_func( $GLOBALS['gl_deactivation'] );
	call_user_func( $GLOBALS['gl_activation'] );
	$pages_after = array_filter( $GLOBALS['gl_posts'], static fn( $post ) => $post->post_type === 'page' );
	$clinics_after = array_filter( $GLOBALS['gl_posts'], static fn( $post ) => $post->post_type === 'gloskin_clinic' );
	if ( count( $pages_after ) !== 16 || count( $clinics_after ) !== 9 || get_page_by_path( 'home', OBJECT, 'page' )->post_content !== 'Approved editor content' ) {
		fwrite( STDERR, "Deactivate/reactivate idempotency or editor-content preservation failed\n" ); exit( 1 );
	}
}

if ( $GLOBALS['gl_woo_late'] ) {
	gl_define_woo_stubs();
}

if ( ! $GLOBALS['gl_is_admin'] ) {
	$home = get_page_by_path( 'home', OBJECT, 'page' );
	$GLOBALS['gl_route'] = array( 'front' => true, 'page' => true, 'singular' => '', 'object' => $home );
	$template = apply_filters( 'template_include', '/theme/index.php' );
	if ( substr( $template, -20 ) !== '/templates/shell.php' ) {
		fwrite( STDERR, "Home template resolution failed: {$template}\n" );
		exit( 1 );
	}
	$context = get_query_var( 'gloskin_context', array() );
	if ( ( $context['view'] ?? '' ) !== 'home' || count( $context['clinics'] ?? array() ) !== 9 || count( $context['skincare'] ?? array() ) !== 7 ) {
		fwrite( STDERR, "Home context failed\n" );
		exit( 1 );
	}
	if ( ! isset( $context['commerce'] ) || ! is_array( $context['commerce'] ) ) {
		fwrite( STDERR, "Commerce header context missing from template context\n" );
		exit( 1 );
	}
	if ( ! $GLOBALS['gl_woo'] && ! empty( $context['commerce']['available'] ) ) {
		fwrite( STDERR, "Commerce should be unavailable without Woo\n" );
		exit( 1 );
	}
	if ( $GLOBALS['gl_woo_late'] && empty( $context['commerce']['available'] ) ) {
		fwrite( STDERR, "Load-order bug: Woo adapter constructed before WooCommerce loaded stayed permanently unavailable\n" );
		exit( 1 );
	}
	if ( empty( $context['logo_url'] ) || false === strpos( $context['logo_url'], 'gloskin-logotext.svg' ) ) {
		fwrite( STDERR, "Canonical logo URL missing from template context\n" );
		exit( 1 );
	}

	// Favicon: Gloskin's branded set is the sole canonical owner regardless
	// of any WordPress Site Icon, and WordPress's own wp_site_icon() output
	// must always be unhooked so the two never both render (no duplicate/
	// stale <link rel="icon"> tags), whether or not a Site Icon is set.
	foreach ( array( false, true ) as $site_icon_configured ) {
		$GLOBALS['gl_site_icon'] = $site_icon_configured;
		ob_start();
		do_action( 'wp_head' );
		$head = ob_get_clean();
		if ( false === strpos( $head, 'favicon.ico' )
			|| false === strpos( $head, 'favicon-16x16.png' )
			|| false === strpos( $head, 'favicon-32x32.png' )
			|| false === strpos( $head, 'icon-192.png' )
			|| false === strpos( $head, 'icon-512.png' )
			|| false === strpos( $head, 'apple-touch-icon.png' ) ) {
			fwrite( STDERR, "Gloskin favicon did not render (Site Icon configured: " . ( $site_icon_configured ? 'yes' : 'no' ) . "): {$head}\n" ); exit( 1 );
		}
		if ( false !== strpos( $head, 'stale-wp-site-icon.png' ) ) {
			fwrite( STDERR, "WordPress's native wp_site_icon() was not unhooked -- duplicate/stale icon tag rendered alongside Gloskin's own\n" ); exit( 1 );
		}
	}
	$GLOBALS['gl_site_icon'] = false;
}

$clinic = get_page_by_path( 'kebayoran-baru', OBJECT, Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE );
$attachment_id = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'Test Image', 'post_name' => 'test-image' ), true );
update_post_meta( $clinic->ID, 'gloskin_map_embed', 'https://evil.example/maps/embed?pb=1' );
if ( '' !== get_post_meta( $clinic->ID, 'gloskin_map_embed', true ) ) {
	fwrite( STDERR, "Unsafe map embed was accepted\n" ); exit( 1 );
}
update_post_meta( $clinic->ID, 'gloskin_map_embed', 'https://www.google.com/maps/embed?pb=1' );
if ( 'https://www.google.com/maps/embed?pb=1' !== get_post_meta( $clinic->ID, 'gloskin_map_embed', true ) ) {
	fwrite( STDERR, "Safe map embed was rejected\n" ); exit( 1 );
}
update_post_meta( $clinic->ID, 'gloskin_gallery_image_ids', array( $attachment_id, $clinic->ID ) );
if ( get_post_meta( $clinic->ID, 'gloskin_gallery_image_ids', true ) !== array( $attachment_id ) ) {
	fwrite( STDERR, "Gallery attachment validation failed\n" ); exit( 1 );
}

$treatment_id = wp_insert_post( array( 'post_type' => Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 'post_status' => 'publish', 'post_title' => 'Fixture Treatment', 'post_name' => 'fixture-treatment' ), true );
$doctor_id = wp_insert_post( array( 'post_type' => Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE, 'post_status' => 'publish', 'post_title' => 'Fixture Doctor', 'post_name' => 'fixture-doctor' ), true );
if ( get_permalink( $clinic ) !== 'https://example.test/clinics/kebayoran-baru/'
	|| get_permalink( $treatment_id ) !== 'https://example.test/treatments/fixture-treatment/'
	|| get_permalink( $doctor_id ) !== 'https://example.test/doctors/fixture-doctor/'
	|| get_permalink( get_page_by_path( 'skincare/facial-wash', OBJECT, 'page' ) ) !== 'https://example.test/skincare/facial-wash/' ) {
	fwrite( STDERR, "Native permalink contract failed\n" ); exit( 1 );
}
update_post_meta( $treatment_id, 'gloskin_clinic_ids', array( $clinic->ID, $doctor_id ) );
if ( get_post_meta( $treatment_id, 'gloskin_clinic_ids', true ) !== array( $clinic->ID ) ) {
	fwrite( STDERR, "Relationship sanitization failed\n" ); exit( 1 );
}

if ( ! $GLOBALS['gl_is_admin'] ) {
	$route_cases = array(
		array( 'home', get_page_by_path( 'home', OBJECT, 'page' ), array( 'front' => true, 'page' => true ) ),
		array( 'about', get_page_by_path( 'about', OBJECT, 'page' ), array( 'page' => true ) ),
		array( 'treatments', get_page_by_path( 'treatments', OBJECT, 'page' ), array( 'page' => true ) ),
		array( 'treatment', get_post( $treatment_id ), array( 'singular' => Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE ) ),
		array( 'clinics', get_page_by_path( 'clinics', OBJECT, 'page' ), array( 'page' => true ) ),
		array( 'clinic', $clinic, array( 'singular' => Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE ) ),
		array( 'doctors', get_page_by_path( 'doctors', OBJECT, 'page' ), array( 'page' => true ) ),
		array( 'doctor', get_post( $doctor_id ), array( 'singular' => Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE ) ),
		array( 'skincare', get_page_by_path( 'skincare', OBJECT, 'page' ), array( 'page' => true ) ),
		array( 'skincare-category', get_page_by_path( 'skincare/facial-wash', OBJECT, 'page' ), array( 'page' => true ) ),
		array( 'insights', get_page_by_path( 'insights', OBJECT, 'page' ), array( 'page' => true ) ),
		array( 'contact', get_page_by_path( 'contact', OBJECT, 'page' ), array( 'page' => true ) ),
		array( 'shop', get_page_by_path( 'shop', OBJECT, 'page' ), array( 'page' => true ) ),
	);
	foreach ( $route_cases as $case ) {
		$GLOBALS['gl_route'] = array_merge( array( 'front' => false, 'page' => false, 'singular' => '', 'object' => $case[1] ), $case[2] );
		apply_filters( 'template_include', '/theme/index.php' );
		$context = get_query_var( 'gloskin_context', array() );
		if ( ( $context['view'] ?? '' ) !== $case[0] ) {
			fwrite( STDERR, 'Route context failed for ' . $case[0] . "\n" ); exit( 1 );
		}
		if ( 'doctor' === $case[0] && ( ! empty( $context['sip_number'] ) || ! empty( $context['schedule'] ) ) ) {
			fwrite( STDERR, "Doctor missing-data fallback failed\n" ); exit( 1 );
		}
		if ( 'treatment' === $case[0] && ! empty( $context['doctors'] ) ) {
			fwrite( STDERR, "Treatment optional-related-doctor fallback failed\n" ); exit( 1 );
		}
	}

	$empty_clinic = get_page_by_path( 'tebet', OBJECT, Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE );
	$GLOBALS['gl_route'] = array( 'front' => false, 'page' => false, 'singular' => Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE, 'object' => $empty_clinic );
	apply_filters( 'template_include', '/theme/index.php' );
	$empty_context = get_query_var( 'gloskin_context', array() );
	if ( ! empty( $empty_context['gallery_ids'] ) || ! empty( $empty_context['map_embed'] ) || ! empty( $empty_context['map_url'] ) || ! empty( $empty_context['whatsapp_url'] ) ) {
		fwrite( STDERR, "Clinic missing-data fallback failed\n" ); exit( 1 );
	}

	$form_adapter = new Gloskin_Site_Core_Form_Adapter();
	if ( false === strpos( $form_adapter->render(), 'not configured yet' ) ) {
		fwrite( STDERR, "Missing-form fallback failed\n" ); exit( 1 );
	}
	$GLOBALS['gl_options'][ Gloskin_Site_Core_Form_Adapter::SETTINGS_OPTION ] = array( 'design_variant' => 'medical', 'form_shortcode' => '[test_form]' );
	$GLOBALS['gl_shortcodes'][] = 'test_form';
	if ( false === strpos( $form_adapter->render(), 'data-test-form' ) ) {
		fwrite( STDERR, "Configured form render failed\n" ); exit( 1 );
	}

	if ( $GLOBALS['gl_woo'] ) {
		$product_id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Test Product', 'post_name' => 'test-product' ), true );
		$shop = get_page_by_path( 'shop', OBJECT, 'page' );
		$GLOBALS['gl_route'] = array( 'front' => false, 'page' => true, 'singular' => '', 'object' => $shop );
		apply_filters( 'template_include', '/theme/index.php' );
		$shop_context = get_query_var( 'gloskin_context', array() );
		if ( empty( $shop_context['woo_ready'] ) || count( $shop_context['products'] ?? array() ) !== 1 ) {
			fwrite( STDERR, "Woo shop presentation failed\n" ); exit( 1 );
		}
		if ( empty( $shop_context['commerce']['available'] ) ) {
			fwrite( STDERR, "Commerce should be available with Woo\n" ); exit( 1 );
		}
		if ( $shop_context['commerce']['cart_count'] !== 2 ) {
			fwrite( STDERR, "Cart count should reflect Woo state\n" ); exit( 1 );
		}
		if ( $shop_context['commerce']['cart_url'] !== 'https://example.test/cart/' ) {
			fwrite( STDERR, "Cart URL should use Woo canonical URL\n" ); exit( 1 );
		}

		$mini_cart = $shop_context['commerce']['mini_cart'];
		if ( false === strpos( $mini_cart, 'gloskin-ui1-cart-sheet__item-media' )
			|| false === strpos( $mini_cart, 'Test Product' )
			|| false === strpos( $mini_cart, 'Ukuran: 30ml' )
			|| false === strpos( $mini_cart, 'class="remove remove_from_cart_button gloskin-ui1-cart-sheet__item-remove"' )
			|| false === strpos( $mini_cart, 'data-cart_item_key="gl_test_cart_item_key"' ) ) {
			fwrite( STDERR, "Mini-cart item rendering incomplete: {$mini_cart}\n" ); exit( 1 );
		}

		if ( ! empty( $GLOBALS['gl_hooks']['get_header'] ) || ! empty( $GLOBALS['gl_hooks']['get_footer'] ) ) {
			fwrite( STDERR, "Native Woo chrome must not be injected through get_header/get_footer\n" ); exit( 1 );
		}

		$native_routes = array(
			array( 'product', array( 'woo' => true, 'object' => get_post( $product_id ) ), 'woocommerce' ),
			array( 'cart', array( 'cart' => true, 'page' => true, 'object' => $shop ), 'page' ),
			array( 'checkout', array( 'checkout' => true, 'page' => true, 'object' => $shop ), 'page' ),
			array( 'account', array( 'account' => true, 'page' => true, 'object' => $shop ), 'page' ),
		);
		$native_shell_rendered = '1' === getenv( 'GL_TEST_FIXTURE_BOOTSTRAP' );
		foreach ( $native_routes as $native_case ) {
			$GLOBALS['gl_route'] = array_merge(
				array( 'front' => false, 'page' => false, 'singular' => '', 'object' => null ),
				$native_case[1]
			);
			$native = apply_filters( 'template_include', '/theme/woocommerce.php' );
			if ( substr( $native, -20 ) !== '/templates/shell.php' ) {
				fwrite( STDERR, 'Native Woo shell ownership failed for ' . $native_case[0] . "\n" ); exit( 1 );
			}
			$native_context = get_query_var( 'gloskin_context', array() );
			if ( ( $native_context['view'] ?? '' ) !== 'commerce-native'
				|| empty( $native_context['commerce_native'] )
				|| ( $native_context['commerce_render_mode'] ?? '' ) !== $native_case[2] ) {
				fwrite( STDERR, 'Native Woo context failed for ' . $native_case[0] . "\n" ); exit( 1 );
			}
			$classes = apply_filters( 'body_class', array() );
			if ( ! in_array( 'gloskin-ui1', $classes, true ) ) {
				fwrite( STDERR, 'Woo presentation body class missing for ' . $native_case[0] . "\n" ); exit( 1 );
			}
			if ( $native_shell_rendered ) {
				continue;
			}
			$native_shell_rendered = true;
			$GLOBALS['gl_loop_consumed'] = false;
			ob_start();
			require $native;
			$native_html = ob_get_clean();
			if ( 1 !== substr_count( $native_html, '<header class="gloskin-ui1-header">' )
				|| 1 !== substr_count( $native_html, '<footer class="gloskin-ui1-footer">' ) ) {
				fwrite( STDERR, 'Native Woo duplicate/missing site chrome for ' . $native_case[0] . "\n" ); exit( 1 );
			}
			if ( false === strpos( $native_html, 'data-test-native-commerce-content="' . $native_case[0] . '"' ) ) {
				fwrite( STDERR, 'Woo-owned native content missing for ' . $native_case[0] . "\n" ); exit( 1 );
			}
		}

		global $product;
		$product = new GL_Test_Product( $product_id );
		$woo_adapter = new Gloskin_Site_Core_WooCommerce_Adapter();
		ob_start();
		$woo_adapter->render_wishlist_toggle();
		$toggle_html = ob_get_clean();
		$product = null;
		if ( false === strpos( $toggle_html, 'data-gloskin-wishlist-toggle="' . $product_id . '"' )
			|| false === strpos( $toggle_html, 'aria-pressed="false"' )
			|| false === strpos( $toggle_html, 'data-label-add=' )
			|| false === strpos( $toggle_html, 'data-label-remove=' ) ) {
			fwrite( STDERR, "Wishlist toggle markup incomplete: {$toggle_html}\n" ); exit( 1 );
		}
	}
}

if ( $GLOBALS['gl_flushes'] < 1 ) {
	fwrite( STDERR, "Lifecycle rewrite flush not exercised\n" );
	exit( 1 );
}

echo 'runtime smoke passed (' . ( $GLOBALS['gl_is_admin'] ? 'admin-upgrade' : 'frontend-activation' ) . ")\n";
