<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$helpers = $root . '/plugin/gloskin-site-core/templates/parts/readiness-helpers.php';
$css_path = $root . '/plugin/gloskin-site-core/assets/css/gloskin-ui1-commerce-polish.css';
$asset_path = $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php';
$registry_path = $root . '/plugin/gloskin-site-core/config/assets.php';
$journey_js_path = $root . '/plugin/gloskin-site-core/assets/js/gloskin-ui1-commerce-journey.js';

function fail_contract( string $message ): void {
	fwrite( STDERR, "commerce progress heading: {$message}\n" );
	exit( 1 );
}

foreach ( array( $helpers, $css_path, $asset_path, $registry_path, $journey_js_path ) as $path ) {
	if ( ! is_file( $path ) ) {
		fail_contract( "missing file {$path}" );
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}
$GLOBALS['gloskin_test_route'] = 'cart';
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $text, $domain = null ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
function is_cart() { return 'cart' === $GLOBALS['gloskin_test_route']; }
function is_checkout() { return 'checkout' === $GLOBALS['gloskin_test_route']; }
function is_account_page() { return 'account' === $GLOBALS['gloskin_test_route']; }
function wc_get_cart_url() { return 'https://example.test/cart/'; }
function wc_get_checkout_url() { return 'https://example.test/checkout/'; }

require $helpers;

function rendered_heading( string $route ): string {
	$GLOBALS['gloskin_test_route'] = $route;
	ob_start();
	gloskin_ui1_render_commerce_page_heading();
	return (string) ob_get_clean();
}

$cart = rendered_heading( 'cart' );
if ( 1 !== substr_count( $cart, '<h1' ) ) { fail_contract( 'Cart must render exactly one H1' ); }
if ( false === strpos( $cart, '>Keranjang</h1>' ) ) { fail_contract( 'Cart H1 must be Keranjang' ); }
if ( false === strpos( $cart, 'href="https://example.test/checkout/"' ) || false === strpos( $cart, '>Checkout</a>' ) ) {
	fail_contract( 'Cart must link Checkout through wc_get_checkout_url()' );
}
if ( false !== strpos( $cart, '<button' ) ) { fail_contract( 'journey titles must not be buttons' ); }
if ( false === strpos( $cart, 'aria-label="Tahapan belanja"' ) || false === strpos( $cart, 'aria-current="page"' ) ) {
	fail_contract( 'Cart semantics missing navigation label/current state' );
}
if ( false === strpos( $cart, 'gloskin-ui1-commerce-progress__connector" aria-hidden="true"' ) ) {
	fail_contract( 'connector must remain decorative' );
}

$checkout = rendered_heading( 'checkout' );
if ( 1 !== substr_count( $checkout, '<h1' ) ) { fail_contract( 'Checkout must render exactly one H1' ); }
if ( false === strpos( $checkout, '>Checkout</h1>' ) ) { fail_contract( 'Checkout H1 must be Checkout' ); }
if ( false === strpos( $checkout, 'href="https://example.test/cart/"' ) || false === strpos( $checkout, '>Keranjang</a>' ) ) {
	fail_contract( 'Checkout must link Cart through wc_get_cart_url()' );
}

$account = rendered_heading( 'account' );
if ( 1 !== substr_count( $account, '<h1' ) || false === strpos( $account, '>Akun</h1>' ) ) {
	fail_contract( 'Account single-heading behavior regressed' );
}
if ( false !== strpos( $account, 'data-gloskin-commerce-progress' ) ) {
	fail_contract( 'journey heading must render only on Cart/Checkout' );
}

$source = (string) file_get_contents( $helpers );
if ( 1 !== substr_count( $source, "function gloskin_ui1_render_commerce_progress_heading( \$active )" ) ) {
	fail_contract( 'expected one shared commerce progress renderer' );
}
foreach ( array( 'wc_get_cart_url()', 'wc_get_checkout_url()' ) as $needle ) {
	if ( false === strpos( $source, $needle ) ) { fail_contract( "canonical Woo route missing: {$needle}" ); }
}

$css = (string) file_get_contents( $css_path );
$required_css = array(
	'grid-template-columns:max-content minmax(44px,1fr) max-content',
	'.gloskin-ui1-commerce-progress__step--cart{justify-self:start',
	'.gloskin-ui1-commerce-progress__step--checkout{justify-self:end',
	'font-size:clamp(2.7rem,5vw,4.6rem)',
	'@media (max-width:760px)',
	'font-size:clamp(1.75rem,8.2vw,2.8rem)',
	'@media (max-width:430px)',
	'font-size:clamp(1.4rem,7.3vw,2rem)',
	'@media (prefers-reduced-motion:reduce)',
);
foreach ( $required_css as $needle ) {
	if ( false === strpos( $css, $needle ) ) { fail_contract( "CSS contract missing: {$needle}" ); }
}
if ( ! preg_match( '/gloskin-ui1-commerce-progress__connector\{[^}]*width:100%;[^}]*min-width:0;[^}]*height:1px/s', $css ) ) {
	fail_contract( 'connector must auto-fill its grid track without a minimum width' );
}
if ( preg_match( '/gloskin-ui1-commerce-progress__connector\{[^}]*width:\s*[0-9]+px/s', $css ) ) {
	fail_contract( 'connector width must not be hard-coded in pixels' );
}
if ( false !== strpos( $css, '!important' ) ) { fail_contract( 'new commerce polish !important is forbidden' ); }
foreach ( array(
	'view-transition-name:',
	'::view-transition-old(',
	'::view-transition-new(',
	'gloskin-commerce-content',
	'gloskin-commerce-progress-out',
	'gloskin-commerce-progress-in',
) as $forbidden_transition ) {
	if ( false !== strpos( $css, $forbidden_transition ) ) {
		fail_contract( "cross-document transition ownership must be absent: {$forbidden_transition}" );
	}
}
if ( false === strpos( $css, 'html.gloskin-ui1-commerce-journey-arriving body.woocommerce-cart .gloskin-ui1-commerce-native' )
	|| false === strpos( $css, 'html.gloskin-ui1-commerce-journey-leaving body.woocommerce-cart .gloskin-ui1-commerce-native' ) ) {
	fail_contract( 'native Woo region must have only the scoped perceptual handoff mask' );
}

$asset = (string) file_get_contents( $asset_path );
$registry = (string) file_get_contents( $registry_path );
$journey_js = (string) file_get_contents( $journey_js_path );
foreach ( array( '@view-transition', 'maybe_enable_commerce_journey_view_transition', 'wp_add_inline_style' ) as $forbidden_transition ) {
	if ( false !== strpos( $asset . $css . $journey_js, $forbidden_transition ) ) {
		fail_contract( "native cross-document View Transition opt-in must be absent: {$forbidden_transition}" );
	}
}
if ( false === strpos( $asset, "wp_enqueue_script( 'gloskin-ui1-commerce-journey' )" )
	|| false === strpos( $asset, "function_exists( 'is_cart' ) && is_cart()" )
	|| false === strpos( $asset, "function_exists( 'is_checkout' ) && is_checkout()" ) ) {
	fail_contract( 'journey runtime must be enqueued only from Cart/Checkout-aware AssetService logic' );
}
if ( false === strpos( $registry, "'gloskin-ui1-commerce-journey' => array(" )
	|| false === strpos( $registry, "'src'       => 'assets/js/gloskin-ui1-commerce-journey.js'" )
	|| false === strpos( $registry, "'in_footer' => false" ) ) {
	fail_contract( 'journey runtime must be a head-loaded declarative first-party asset' );
}
if ( false === strpos( $journey_js, "[data-gloskin-commerce-progress] a[href]" )
	|| false === strpos( $journey_js, 'location.assign' )
	|| false === strpos( $journey_js, 'sessionStorage' ) ) {
	fail_contract( 'journey runtime must remain scoped, native-navigation, and presentation-marker based' );
}
foreach ( array( 'pushState', 'replaceState', 'MutationObserver', 'ResizeObserver', 'setInterval(', 'fetch(', 'XMLHttpRequest', 'DOMParser', '.innerHTML' ) as $forbidden ) {
	if ( false !== strpos( $journey_js, $forbidden ) ) {
		fail_contract( "forbidden fake-SPA journey mechanism present: {$forbidden}" );
	}
}
foreach ( array( 'view-transition-name:', '::view-transition-old(', '::view-transition-new(' ) as $forbidden_transition ) {
	if ( false !== strpos( $css . $journey_js, $forbidden_transition ) ) {
		fail_contract( "retired View Transition implementation returned: {$forbidden_transition}" );
	}
}

echo "commerce progress heading contract passed\n";
