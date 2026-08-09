<?php
/**
 * Render the real Gloskin shell against a tiny WordPress/Woo fixture.
 *
 * This regression intentionally uses templates/shell.php and lets the shell
 * fire both its integration hook and wp_footer(). It verifies that quick auth
 * is present exactly once only on the intended logged-out, non-account path.
 */
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

class WooCommerce {}
class WP_Term {}
class Gloskin_Site_Core_Form_Adapter {
	const SETTINGS_OPTION = 'gloskin_site_core_settings';
}

$GLOBALS['gl_fixture_hooks']      = array();
$GLOBALS['gl_fixture_filters']    = array();
$GLOBALS['gl_fixture_query_vars'] = array();
$GLOBALS['gl_fixture_logged_in']  = getenv( 'GL_TEST_LOGGED_IN' ) === '1';
$GLOBALS['gl_fixture_account']    = getenv( 'GL_TEST_ACCOUNT' ) === '1';
$GLOBALS['gl_fixture_emit_html']  = getenv( 'GL_TEST_EMIT_HTML' ) === '1';
$GLOBALS['gl_fixture_looped']     = false;
$GLOBALS['gl_fixture_footer_runs'] = 0;

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['gl_fixture_hooks'][ $hook ][] = array( $priority, $callback, $accepted_args );
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['gl_fixture_filters'][ $hook ][] = array( $priority, $callback, $accepted_args );
}
function remove_action( $hook, $callback, $priority = 10 ) {
	if ( empty( $GLOBALS['gl_fixture_hooks'][ $hook ] ) ) {
		return false;
	}
	$GLOBALS['gl_fixture_hooks'][ $hook ] = array_values(
		array_filter(
			$GLOBALS['gl_fixture_hooks'][ $hook ],
			static function ( $item ) use ( $callback, $priority ) {
				return ! ( $item[0] === $priority && $item[1] === $callback );
			}
		)
	);
	return true;
}
function do_action( $hook, ...$args ) {
	$items = $GLOBALS['gl_fixture_hooks'][ $hook ] ?? array();
	usort( $items, static function ( $a, $b ) { return $a[0] <=> $b[0]; } );
	foreach ( $items as $item ) {
		call_user_func_array( $item[1], array_slice( $args, 0, $item[2] ) );
	}
}
function apply_filters( $hook, $value, ...$args ) {
	$items = $GLOBALS['gl_fixture_filters'][ $hook ] ?? array();
	usort( $items, static function ( $a, $b ) { return $a[0] <=> $b[0]; } );
	foreach ( $items as $item ) {
		$all   = array_merge( array( $value ), array_slice( $args, 0, max( 0, $item[2] - 1 ) ) );
		$value = call_user_func_array( $item[1], $all );
	}
	return $value;
}

function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ); }
function sanitize_html_class( $value ) { return sanitize_key( $value ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $text, $domain = null ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $text ) { return (string) $text; }
function wp_kses_post( $text ) { return (string) $text; }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function wp_trim_words( $text, $limit = 55 ) { return (string) $text; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function home_url( $path = '/' ) { return 'https://example.test' . ( '/' === substr( $path, 0, 1 ) ? $path : '/' . $path ); }
function rest_url( $path = '' ) { return home_url( '/wp-json/' . ltrim( $path, '/' ) ); }
function wp_create_nonce( $action = '' ) { return 'fixture-' . $action; }
function wp_date( $format ) { return '2026'; }
function bloginfo( $show ) { if ( 'charset' === $show ) { echo 'UTF-8'; } else { echo 'Gloskin'; } }
function get_option( $key, $default = false ) {
	if ( 'woocommerce_enable_myaccount_registration' === $key ) {
		return 'yes';
	}
	return $default;
}
function get_query_var( $key, $default = '' ) { return $GLOBALS['gl_fixture_query_vars'][ $key ] ?? $default; }
function set_query_var( $key, $value ) { $GLOBALS['gl_fixture_query_vars'][ $key ] = $value; }
function get_queried_object_id() { return 1; }
function get_the_title( $id = 0 ) { return 'Fixture Page'; }
function get_permalink( $id ) { return 99 === (int) $id ? home_url( '/my-account/' ) : home_url( '/fixture/' ); }
function has_excerpt( $post ) { return false; }
function get_the_excerpt( $post ) { return ''; }
function get_post_thumbnail_id( $id ) { return 0; }
function wp_get_attachment_image() { return '<img alt="">'; }
function get_posts() { return array(); }
function get_term_by() { return false; }
function get_term_link() { return ''; }
function is_wp_error() { return false; }

function is_user_logged_in() { return (bool) $GLOBALS['gl_fixture_logged_in']; }
function is_front_page() { return false; }
function is_product() { return false; }
function is_shop() { return false; }
function is_woocommerce() { return false; }
function is_cart() { return false; }
function is_checkout() { return false; }
function is_account_page() { return (bool) $GLOBALS['gl_fixture_account']; }
function wc_get_products( $args = array() ) { return array(); }
function wc_get_page_id( $page ) { return 'myaccount' === $page ? 99 : 0; }
function wc_get_page_permalink( $page ) { return home_url( '/shop/' ); }
function wc_get_cart_url() { return home_url( '/cart/' ); }
function wc_get_checkout_url() { return home_url( '/checkout/' ); }

function wc_get_template( $template ) {
	if ( 'myaccount/form-login.php' !== $template ) {
		return;
	}
	?>
	<div class="u-columns col2-set" id="customer_login">
		<div class="u-column1 col-1">
			<h2>Masuk</h2>
			<form class="woocommerce-form woocommerce-form-login login" method="post">
				<p class="form-row"><label for="username">Email</label><input class="input-text" type="text" name="username" id="username"></p>
				<input type="hidden" name="woocommerce-login-nonce" value="login-nonce">
				<button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="Masuk">Masuk</button>
			</form>
		</div>
		<?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>
		<div class="u-column2 col-2">
			<h2>Daftar</h2>
			<form method="post" class="woocommerce-form woocommerce-form-register register">
				<p class="form-row"><label for="reg_email">Email</label><input type="email" class="input-text" name="email" id="reg_email"></p>
				<input type="hidden" name="woocommerce-register-nonce" value="register-nonce">
				<button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="Daftar">Daftar</button>
			</form>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

function wp_head() { do_action( 'wp_head' ); }
function wp_body_open() {}
function wp_footer() {
	$GLOBALS['gl_fixture_footer_runs']++;
	do_action( 'wp_footer' );
	echo '<span data-test-wp-footer hidden></span>';
}
function body_class( $classes = array() ) {
	if ( is_user_logged_in() ) {
		$classes[] = 'logged-in';
		$classes[] = 'admin-bar';
	}
	$classes = apply_filters( 'body_class', $classes );
	echo 'class="' . esc_attr( implode( ' ', array_values( array_unique( $classes ) ) ) ) . '"';
}
function have_posts() { return is_account_page() && ! $GLOBALS['gl_fixture_looped']; }
function the_post() { $GLOBALS['gl_fixture_looped'] = true; }
function the_content() {
	echo '<div data-test-native-account-content></div>';
	if ( is_account_page() && ! is_user_logged_in() ) {
		wc_get_template( 'myaccount/form-login.php' );
	}
}
function woocommerce_content() {}

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php';

$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();
$adapter->register();

$navigation = array(
	array( 'label' => 'Tentang', 'url' => home_url( '/about/' ), 'active' => true, 'children' => array() ),
	array(
		'label' => 'Perawatan',
		'url' => home_url( '/treatments/' ),
		'active' => false,
		'children' => array(
			array( 'label' => 'Wajah', 'url' => home_url( '/treatments/face/' ), 'active' => false, 'children' => array() ),
		),
	),
	array( 'label' => 'Skincare', 'url' => home_url( '/skincare/' ), 'active' => false, 'children' => array() ),
	array( 'label' => 'Klinik', 'url' => home_url( '/clinics/' ), 'active' => false, 'children' => array() ),
	array( 'label' => 'Dokter', 'url' => home_url( '/doctors/' ), 'active' => false, 'children' => array() ),
	array( 'label' => 'Belanja', 'url' => home_url( '/shop/' ), 'active' => false, 'children' => array() ),
	array( 'label' => 'Insight', 'url' => home_url( '/insights/' ), 'active' => false, 'children' => array() ),
	array( 'label' => 'Kontak', 'url' => home_url( '/contact/' ), 'active' => false, 'children' => array() ),
);
$commerce = array(
	'available'    => true,
	'account_url'  => home_url( '/my-account/' ),
	'cart_url'     => home_url( '/cart/' ),
	'checkout_url' => home_url( '/checkout/' ),
	'cart_count'   => 0,
	'mini_cart'    => '<p>Keranjang kosong.</p>',
	'quick_auth'   => $adapter->should_render_quick_auth(),
);
$context = array(
	'view'           => is_account_page() ? 'commerce-native' : 'fixture-page',
	'navigation'     => $navigation,
	'design_variant' => 'medical',
	'clinic_links'   => array(
		array( 'label' => 'Klinik Selatan', 'url' => home_url( '/clinics/selatan/' ) ),
		array( 'label' => 'Klinik Barat', 'url' => home_url( '/clinics/barat/' ) ),
	),
	'site_name'      => 'Gloskin',
	'commerce'       => $commerce,
	'logo_url'       => 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%2265%22%3E%3C/svg%3E',
);
if ( is_account_page() ) {
	$context['commerce_native']      = true;
	$context['commerce_render_mode'] = 'page';
}
set_query_var( 'gloskin_context', $context );

ob_start();
require dirname( __DIR__ ) . '/plugin/gloskin-site-core/templates/shell.php';
$html = (string) ob_get_clean();

if ( $GLOBALS['gl_fixture_emit_html'] ) {
	echo $html;
	exit( 0 );
}

function fixture_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

fixture_assert( 1 === $GLOBALS['gl_fixture_footer_runs'], 'wp_footer must run exactly once through the real shell' );

if ( is_account_page() ) {
	fixture_assert( 0 === substr_count( $html, 'id="gloskin-auth-overlay"' ), 'My Account must not render quick auth overlay' );
	fixture_assert( 0 === substr_count( $html, 'data-gloskin-auth-open aria-controls="gloskin-auth-overlay"' ), 'My Account Account anchor must not carry quick-auth intent' );
	if ( ! is_user_logged_in() ) {
		fixture_assert( 1 === substr_count( $html, 'class="woocommerce-form woocommerce-form-login login"' ), 'My Account must contain one native login form' );
		fixture_assert( 1 === substr_count( $html, 'class="woocommerce-form woocommerce-form-register register"' ), 'My Account must contain one native register form' );
		fixture_assert( 1 === substr_count( $html, 'name="woocommerce-login-nonce"' ), 'My Account login nonce duplicated/missing' );
		fixture_assert( 1 === substr_count( $html, 'name="woocommerce-register-nonce"' ), 'My Account register nonce duplicated/missing' );
	}
	echo "rendered-shell-auth-smoke: OK (account)\n";
	exit( 0 );
}

if ( is_user_logged_in() ) {
	fixture_assert( 0 === substr_count( $html, 'id="gloskin-auth-overlay"' ), 'logged-in pages must not render quick auth overlay' );
	fixture_assert( 0 === substr_count( $html, 'data-gloskin-auth-open aria-controls="gloskin-auth-overlay"' ), 'logged-in Account anchor must not carry quick-auth intent' );
	fixture_assert( false !== strpos( $html, 'href="https://example.test/my-account/"' ), 'logged-in Account must keep canonical My Account href' );
	fixture_assert( false !== strpos( $html, 'aria-label="Akun saya"' ), 'logged-in Account label missing' );
	echo "rendered-shell-auth-smoke: OK (logged-in)\n";
	exit( 0 );
}

fixture_assert( 1 === substr_count( $html, 'id="gloskin-auth-overlay"' ), 'logged-out shell must render exactly one quick auth overlay' );
fixture_assert( 2 === substr_count( $html, 'data-gloskin-auth-open aria-controls="gloskin-auth-overlay" aria-expanded="false"' ), 'logged-out Account anchors (full + compact) must carry quick-auth intent server-side' );
fixture_assert( 1 === substr_count( $html, 'class="woocommerce-form woocommerce-form-login login"' ), 'quick auth must contain one native login form' );
fixture_assert( 1 === substr_count( $html, 'class="woocommerce-form woocommerce-form-register register"' ), 'quick auth must contain one native register form' );
fixture_assert( 1 === substr_count( $html, 'name="woocommerce-login-nonce"' ), 'quick auth login nonce duplicated/missing' );
fixture_assert( 1 === substr_count( $html, 'name="woocommerce-register-nonce"' ), 'quick auth register nonce duplicated/missing' );
fixture_assert( false !== strpos( $html, 'action="https://example.test/my-account/" class="woocommerce-form woocommerce-form-login login"' ), 'quick auth login must post to canonical My Account' );
fixture_assert( false !== strpos( $html, 'action="https://example.test/my-account/" method="post" class="woocommerce-form woocommerce-form-register register"' ), 'quick auth register must post to canonical My Account' );
fixture_assert( false !== strpos( $html, 'data-test-wp-footer' ), 'real wp_footer marker missing' );

echo "rendered-shell-auth-smoke: OK (logged-out)\n";
