<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['gl_filters'] = array();
$GLOBALS['gl_actions'] = array();
$GLOBALS['gl_filter_sequence'] = 0;

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['gl_filters'][ $hook ][] = array( $callback, (int) $priority, (int) $accepted_args, $GLOBALS['gl_filter_sequence']++ );
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['gl_actions'][ $hook ][] = array( $callback, (int) $priority, (int) $accepted_args );
}
function apply_filters( $hook, $value, ...$args ) {
	$callbacks = isset( $GLOBALS['gl_filters'][ $hook ] ) ? $GLOBALS['gl_filters'][ $hook ] : array();
	usort(
		$callbacks,
		static function ( $left, $right ) {
			if ( $left[1] === $right[1] ) {
				return $left[3] <=> $right[3];
			}
			return $left[1] <=> $right[1];
		}
	);
	foreach ( $callbacks as $entry ) {
		$call_args = array_merge( array( $value ), $args );
		$value = call_user_func_array( $entry[0], array_slice( $call_args, 0, max( 1, $entry[2] ) ) );
	}
	return $value;
}

require dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php';

function fail_contract( $message ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
function assert_contract( $condition, $message ) { if ( ! $condition ) { fail_contract( $message ); } }

$adapter = new Gloskin_Site_Core_WooCommerce_Adapter();
$adapter->register();

assert_contract( isset( $GLOBALS['gl_filters']['wc_add_to_cart_message_html'] ), 'Add-to-cart success must be suppressed at Woo source filter' );
assert_contract( isset( $GLOBALS['gl_filters']['woocommerce_cart_item_removed_notice_type'] ), 'Cart-remove success must use exact operation notice-type hook' );
assert_contract( isset( $GLOBALS['gl_filters']['woocommerce_add_message'] ), 'Cart-remove one-shot must bridge only through Woo success-message filter' );
assert_contract( ! isset( $GLOBALS['gl_filters']['woocommerce_add_error'] ) && ! isset( $GLOBALS['gl_filters']['woocommerce_add_info'] ), 'Error/info Woo notices must remain untouched' );

$gloskin_remove_priorities = array();
foreach ( $GLOBALS['gl_filters']['woocommerce_cart_item_removed_notice_type'] as $entry ) {
	if ( is_array( $entry[0] ) && isset( $entry[0][0], $entry[0][1] ) && $entry[0][0] === $adapter && 'arm_cart_item_removed_success_suppression' === $entry[0][1] ) {
		$gloskin_remove_priorities[] = $entry[1];
	}
}
assert_contract( array( PHP_INT_MAX ) === $gloskin_remove_priorities, 'Cart-remove type observer must run at PHP_INT_MAX to see the final effective type' );

// E. Add-to-cart suppression stays at Woo's narrow source hook.
assert_contract( '' === apply_filters( 'wc_add_to_cart_message_html', '<a>View cart</a> Added', array( 12 => 1 ), true ), 'Add success was not suppressed at source' );

// C. A normal unrelated success without remove context survives byte-identical.
$account_success = '<strong>Account details changed successfully.</strong>';
assert_contract( $account_success === apply_filters( 'woocommerce_add_message', $account_success ), 'Unrelated/account success must pass byte-identical' );

// A. Final remove type remains success: suppress exactly that success, then reset immediately.
$remove_type = apply_filters( 'woocommerce_cart_item_removed_notice_type', 'success' );
assert_contract( 'success' === $remove_type, 'Remove success type must remain unchanged' );
assert_contract( '' === apply_filters( 'woocommerce_add_message', 'opaque removed success payload' ), 'Exact remove success context was not suppressed' );
$next_success = 'Profile saved.';
assert_contract( $next_success === apply_filters( 'woocommerce_add_message', $next_success ), 'Remove one-shot flag was not reset immediately' );

// B. An extension at priority 1000 converts success -> notice before Gloskin's
// PHP_INT_MAX observer. Gloskin must see the effective notice type, not arm,
// and a later unrelated success must survive unchanged.
add_filter(
	'woocommerce_cart_item_removed_notice_type',
	static function ( $notice_type ) {
		return 'success' === $notice_type ? 'notice' : $notice_type;
	},
	1000
);
$converted_type = apply_filters( 'woocommerce_cart_item_removed_notice_type', 'success' );
assert_contract( 'notice' === $converted_type, 'External remove-type conversion must be preserved' );
$after_converted_remove = '<strong>Password changed successfully.</strong>';
assert_contract( $after_converted_remove === apply_filters( 'woocommerce_add_message', $after_converted_remove ), 'Converted remove notice must not leave suppression armed for unrelated success' );

// D. Error/info pipelines remain entirely untouched.
assert_contract( ! isset( $GLOBALS['gl_filters']['woocommerce_add_error'] ) && ! isset( $GLOBALS['gl_filters']['woocommerce_add_info'] ), 'Error/info Woo hooks must remain untouched' );

$source = file_get_contents( dirname( __DIR__ ) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php' );
if ( false === $source ) { fail_contract( 'Unable to read WooCommerceAdapter source' ); }
assert_contract( false === strpos( $source, 'wc_clear_notices(' ), 'Global wc_clear_notices() is forbidden' );
assert_contract( false === strpos( $source, 'MutationObserver' ), 'DOM notice cleanup via MutationObserver is forbidden' );
assert_contract( false === strpos( $source, 'Undo?' ) && false === strpos( $source, 'has been added to your cart' ), 'Woo success suppression must not text-match English notice copy' );
assert_contract( 1 === preg_match_all( "/add_filter\(\s*'wc_add_to_cart_message_html'/", $source ), 'Expected one canonical add-success suppression hook owner' );
assert_contract( 1 === preg_match_all( "/add_filter\(\s*'woocommerce_cart_item_removed_notice_type'/", $source ), 'Expected one exact remove-success arming hook owner' );
assert_contract( 1 === preg_match_all( "/add_filter\(\s*'woocommerce_add_message'/", $source ), 'Expected one request-local success-message bridge' );
assert_contract( 1 === preg_match_all( "/add_filter\(\s*'woocommerce_cart_item_removed_notice_type'[^;]*PHP_INT_MAX/s", $source ), 'Remove-type observer must be registered at PHP_INT_MAX' );

echo "woo-cart-notice-contract.php: OK\n";
