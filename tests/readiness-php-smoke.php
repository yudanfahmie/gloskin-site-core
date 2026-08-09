<?php
/* Isolated presentation-helper regression: no WordPress bootstrap required. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['front'] = false;
$GLOBALS['title'] = 'Detail';
$GLOBALS['woo_case'] = '';
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $v ) ); }
function __( $v ) { return $v; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function esc_url( $v ) { return (string) $v; }
function home_url( $path = '/' ) { return 'https://gloskin.test' . $path; }
function is_front_page() { return (bool) $GLOBALS['front']; }
function get_queried_object_id() { return 42; }
function get_the_title() { return $GLOBALS['title']; }
function is_product() { return 'product' === $GLOBALS['woo_case']; }
function is_cart() { return 'cart' === $GLOBALS['woo_case']; }
function is_checkout() { return 'checkout' === $GLOBALS['woo_case']; }
function is_account_page() { return 'account' === $GLOBALS['woo_case']; }
function wc_get_page_permalink() { return 'https://gloskin.test/shop/'; }
require dirname( __DIR__ ) . '/plugin/gloskin-site-core/templates/parts/readiness-helpers.php';

function assert_true( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}
function render_crumb( $view ) {
    ob_start();
    gloskin_ui1_render_breadcrumbs( array( 'view' => $view ) );
    return ob_get_clean();
}

$GLOBALS['front'] = true;
assert_true( '' === render_crumb( 'home' ), 'home must not render breadcrumb' );
$GLOBALS['front'] = false;
$views = array( 'about', 'treatments', 'treatment', 'skincare', 'skincare-category', 'clinics', 'clinic', 'doctors', 'doctor', 'insights', 'shop', 'contact' );
foreach ( $views as $view ) {
    $GLOBALS['title'] = ucfirst( $view ) . ' Detail';
    $html = render_crumb( $view );
    assert_true( 1 === substr_count( $html, 'data-gloskin-breadcrumb-owner=' ), $view . ' must have exactly one owner' );
    assert_true( false !== strpos( $html, 'aria-label="Breadcrumb"' ), $view . ' fallback nav must be named' );
    assert_true( false !== strpos( $html, 'href="https://gloskin.test/"' ), $view . ' must link Home' );
    assert_true( false !== strpos( $html, 'aria-current="page"' ), $view . ' current page must be identified' );
}
foreach ( array( 'product', 'cart', 'checkout', 'account' ) as $case ) {
    $GLOBALS['woo_case'] = $case;
    $GLOBALS['title'] = 'Woo Detail';
    $html = render_crumb( 'commerce-native' );
    assert_true( 1 === substr_count( $html, 'data-gloskin-breadcrumb-owner=' ), $case . ' must have exactly one owner' );
    if ( 'product' === $case ) { assert_true( false !== strpos( $html, 'https://gloskin.test/shop/' ), 'product must link shop hub' ); }
}
$GLOBALS['woo_case'] = 'account';
ob_start(); gloskin_ui1_render_commerce_page_heading(); $heading = ob_get_clean();
assert_true( 1 === substr_count( $heading, '<h1>' ) && false !== strpos( $heading, 'Akun' ), 'account must have one shell H1' );
$empty = gloskin_ui1_empty_state_html( 'search', 'Kosong', 'Coba lagi', 'Beranda', home_url( '/' ) );
assert_true( false !== strpos( $empty, 'aria-hidden="true" focusable="false"' ), 'empty SVG must be decorative' );
assert_true( false !== strpos( $empty, 'Coba lagi' ) && false !== strpos( $empty, 'https://gloskin.test/' ), 'empty state copy/action missing' );
ob_start(); gloskin_ui1_render_native_cart_empty_state(); $cart_empty = ob_get_clean();
assert_true( false !== strpos( $cart_empty, 'Keranjang Anda masih kosong' ) && false !== strpos( $cart_empty, 'https://gloskin.test/shop/' ), 'native cart empty state missing shared copy/action' );

/* Define provider only after fallback assertions; provider must then be sole owner. */
eval( 'function rank_math_the_breadcrumbs(){ echo "<nav class=\"rank-math-breadcrumb\" aria-label=\"Breadcrumb\"><p>Provider</p></nav>"; }' );
$GLOBALS['woo_case'] = '';
$provider = render_crumb( 'about' );
assert_true( 1 === substr_count( $provider, 'data-gloskin-breadcrumb-owner=' ), 'provider fixture must have one owner' );
assert_true( false !== strpos( $provider, 'data-gloskin-breadcrumb-owner="rank-math"' ), 'Rank Math must own provider fixture' );
assert_true( false === strpos( $provider, 'data-gloskin-breadcrumb-owner="gloskin"' ), 'fallback must not render beside provider' );
assert_true( false === strpos( $provider, 'application/ld+json' ), 'helper must not emit breadcrumb JSON-LD' );
echo "readiness-php-smoke: OK\n";
