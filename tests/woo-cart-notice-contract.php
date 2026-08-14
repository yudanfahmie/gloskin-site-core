<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['gl_filters'] = array();
$GLOBALS['gl_actions'] = array();
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['gl_filters'][ $hook ][] = array( $callback, $priority, $accepted_args ); }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['gl_actions'][ $hook ][] = array( $callback, $priority, $accepted_args ); }

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php';

function fail_contract( $message ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
function assert_contract( $condition, $message ) { if ( ! $condition ) { fail_contract( $message ); } }

$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();
$adapter->register();

assert_contract( isset( $GLOBALS['gl_filters']['wc_add_to_cart_message_html'] ), 'Add-to-cart success must be suppressed at Woo source filter' );
assert_contract( isset( $GLOBALS['gl_filters']['woocommerce_cart_item_removed_notice_type'] ), 'Cart-remove success must use exact operation notice-type hook' );
assert_contract( isset( $GLOBALS['gl_filters']['woocommerce_add_message'] ), 'Cart-remove one-shot must bridge only through Woo success-message filter' );
assert_contract( ! isset( $GLOBALS['gl_filters']['woocommerce_add_error'] ) && ! isset( $GLOBALS['gl_filters']['woocommerce_add_info'] ), 'Error/info Woo notices must remain untouched' );
assert_contract( '' === $adapter->suppress_add_to_cart_success_message( '<a>View cart</a> Added', array( 12 => 1 ), true ), 'Add success was not suppressed at source' );

$account_success = '<strong>Account details changed successfully.</strong>';
assert_contract( $account_success === $adapter->suppress_armed_cart_item_removed_success_message( $account_success ), 'Unrelated/account success must pass byte-identical' );
assert_contract( 'info' === $adapter->arm_cart_item_removed_success_suppression( 'info' ), 'Remove notice type must be returned unchanged' );
$info_converted_success = 'Plugin converted remove feedback';
assert_contract( $info_converted_success === $adapter->suppress_armed_cart_item_removed_success_message( $info_converted_success ), 'Non-success remove context must not arm suppression' );
assert_contract( 'success' === $adapter->arm_cart_item_removed_success_suppression( 'success' ), 'Remove success type must remain unchanged' );
assert_contract( '' === $adapter->suppress_armed_cart_item_removed_success_message( 'opaque removed success payload' ), 'Exact remove success context was not suppressed' );
$next_success = 'Profile saved.';
assert_contract( $next_success === $adapter->suppress_armed_cart_item_removed_success_message( $next_success ), 'Remove one-shot flag was not reset immediately' );

$source = file_get_contents( dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php' );
if ( false === $source ) { fail_contract( 'Unable to read WooCommerceAdapter source' ); }
assert_contract( false === strpos( $source, 'wc_clear_notices(' ), 'Global wc_clear_notices() is forbidden' );
assert_contract( false === strpos( $source, 'MutationObserver' ), 'DOM notice cleanup via MutationObserver is forbidden' );
assert_contract( false === strpos( $source, 'Undo?' ) && false === strpos( $source, 'has been added to your cart' ), 'Woo success suppression must not text-match English notice copy' );
assert_contract( 1 === preg_match_all( "/add_filter\(\s*'wc_add_to_cart_message_html'/", $source ), 'Expected one canonical add-success suppression hook owner' );
assert_contract( 1 === preg_match_all( "/add_filter\(\s*'woocommerce_cart_item_removed_notice_type'/", $source ), 'Expected one exact remove-success arming hook owner' );
assert_contract( 1 === preg_match_all( "/add_filter\(\s*'woocommerce_add_message'/", $source ), 'Expected one request-local success-message bridge' );

echo "woo-cart-notice-contract.php: OK\n";
