<?php
/**
 * Plugin Name: Markas Auth Guard
 * Description: Early auth-only isolation for /masuk and wp-login.php.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	return;
}

if ( ! function_exists( 'markas_auth_guard_path' ) ) {
	function markas_auth_guard_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) && is_scalar( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path = $uri !== '' ? parse_url( $uri, PHP_URL_PATH ) : '';
		if ( ! is_string( $path ) ) {
			return '';
		}
		$path = '/' . ltrim( $path, '/' );
		return $path === '/' ? '/' : rtrim( $path, '/' );
	}
}

if ( ! function_exists( 'markas_auth_guard_is_core_path' ) ) {
	function markas_auth_guard_is_core_path() {
		$path = markas_auth_guard_path();
		return $path !== '' && basename( $path ) === 'wp-login.php';
	}
}

if ( ! function_exists( 'markas_auth_guard_is_entry_path' ) ) {
	function markas_auth_guard_is_entry_path() {
		$path = markas_auth_guard_path();
		return $path === '/masuk' || ( strlen( $path ) > 6 && substr( $path, -6 ) === '/masuk' );
	}
}

if ( ! function_exists( 'markas_auth_guard_action' ) ) {
	function markas_auth_guard_action() {
		$value = isset( $_REQUEST['action'] ) && is_scalar( $_REQUEST['action'] ) ? strtolower( trim( (string) $_REQUEST['action'] ) ) : '';
		return preg_replace( '/[^a-z0-9_-]/', '', $value );
	}
}

if ( ! function_exists( 'markas_auth_guard_normal_login' ) ) {
	function markas_auth_guard_normal_login() {
		$action = markas_auth_guard_action();
		return $action === '' || $action === 'login';
	}
}

if ( ! function_exists( 'markas_auth_guard_is_auth_request' ) ) {
	function markas_auth_guard_is_auth_request() {
		return markas_auth_guard_is_entry_path() || markas_auth_guard_is_core_path();
	}
}

if ( ! markas_auth_guard_is_auth_request() ) {
	return;
}

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}
if ( ! defined( 'WPCB_SAFE_MODE' ) ) {
	define( 'WPCB_SAFE_MODE', true );
}

if ( ! function_exists( 'markas_auth_guard_full_isolation' ) ) {
	function markas_auth_guard_full_isolation() {
		return markas_auth_guard_is_entry_path() || ( markas_auth_guard_is_core_path() && markas_auth_guard_normal_login() );
	}
}

if ( ! function_exists( 'markas_auth_guard_conflicting_plugin' ) ) {
	function markas_auth_guard_conflicting_plugin( $plugin ) {
		$plugin = strtolower( (string) $plugin );
		return strpos( $plugin, 'wpcodebox2/' ) !== false
			|| strpos( $plugin, 'wpcodebox_functionality_plugin/' ) !== false
			|| strpos( $plugin, 'gloskin-site-core/' ) !== false;
	}
}

/**
 * On the ordinary credential lane, WordPress Core + this MU guard are the only
 * auth owners. Special actions retain unrelated plugins (SMTP/2FA integrations)
 * but known conflicting site/runtime plugins are removed.
 */
add_filter( 'option_active_plugins', static function ( $plugins ) {
	$plugins = is_array( $plugins ) ? $plugins : array();
	if ( markas_auth_guard_full_isolation() ) {
		return array();
	}
	return array_values( array_filter( $plugins, static function ( $plugin ) {
		return ! markas_auth_guard_conflicting_plugin( $plugin );
	} ) );
}, -PHP_INT_MAX );

add_filter( 'site_option_active_sitewide_plugins', static function ( $plugins ) {
	$plugins = is_array( $plugins ) ? $plugins : array();
	if ( markas_auth_guard_full_isolation() ) {
		return array();
	}
	foreach ( array_keys( $plugins ) as $plugin ) {
		if ( markas_auth_guard_conflicting_plugin( $plugin ) ) {
			unset( $plugins[ $plugin ] );
		}
	}
	return $plugins;
}, -PHP_INT_MAX );

if ( ! function_exists( 'markas_auth_guard_https' ) ) {
	function markas_auth_guard_https() {
		if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && is_scalar( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
			$proto = strtolower( trim( explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] )[0] ) );
			if ( $proto === 'https' ) {
				$_SERVER['HTTPS'] = 'on';
				$_SERVER['SERVER_PORT'] = '443';
				return true;
			}
		}
		return ! empty( $_SERVER['HTTPS'] ) && strtolower( (string) $_SERVER['HTTPS'] ) !== 'off';
	}
}

markas_auth_guard_https();

/**
 * Direct GET wp-login.php must never load the ordinary plugin stack. Redirect
 * before active plugins are included, which eliminates the blank-page class of
 * failures caused by plugin bootstrap code.
 */
if ( markas_auth_guard_is_core_path()
	&& in_array( strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ), array( 'GET', 'HEAD' ), true )
	&& markas_auth_guard_normal_login() ) {
	$query = array();
	foreach ( array( 'redirect_to', 'reauth' ) as $key ) {
		if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) && trim( (string) $_GET[ $key ] ) !== '' ) {
			$query[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $_GET[ $key ] );
		}
	}
	$location = '/masuk' . ( $query ? '?' . implode( '&', $query ) : '' );
	if ( ! headers_sent() ) {
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true );
		header( 'Pragma: no-cache', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'X-Markas-Auth-Guard: mu-v1', true );
		header( 'Location: ' . $location, true, 302 );
		exit;
	}
}

if ( ! function_exists( 'markas_auth_guard_core_url' ) ) {
	function markas_auth_guard_core_url() {
		return site_url( 'wp-login.php', 'login_post' );
	}
}

if ( ! function_exists( 'markas_auth_guard_entry_url' ) ) {
	function markas_auth_guard_entry_url( $args = array() ) {
		$url = home_url( '/masuk' );
		return $args ? add_query_arg( $args, $url ) : $url;
	}
}

if ( ! function_exists( 'markas_auth_guard_safe_redirect_target' ) ) {
	function markas_auth_guard_safe_redirect_target( $candidate ) {
		$fallback = admin_url( '/' );
		$candidate = is_scalar( $candidate ) ? trim( (string) $candidate ) : '';
		return $candidate === '' ? $fallback : wp_validate_redirect( $candidate, $fallback );
	}
}

if ( ! function_exists( 'markas_auth_guard_set_host_cookie' ) ) {
	function markas_auth_guard_set_host_cookie( $name, $value, $expires, $path, $secure = true ) {
		if ( headers_sent() || $name === '' ) {
			return;
		}
		@setcookie( (string) $name, (string) $value, array(
			'expires'  => (int) $expires,
			'path'     => $path !== '' ? (string) $path : '/',
			'secure'   => (bool) $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		) );
	}
}

if ( ! function_exists( 'markas_auth_guard_issue_test_cookie' ) ) {
	function markas_auth_guard_issue_test_cookie() {
		$name = defined( 'TEST_COOKIE' ) ? (string) TEST_COOKIE : 'wordpress_test_cookie';
		markas_auth_guard_set_host_cookie( $name, 'WP Cookie check', 0, '/', markas_auth_guard_https() );
	}
}

if ( ! function_exists( 'markas_auth_guard_is_core_credential_post' ) ) {
	function markas_auth_guard_is_core_credential_post() {
		return markas_auth_guard_is_core_path()
			&& strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) === 'POST'
			&& markas_auth_guard_normal_login()
			&& isset( $_POST['log'], $_POST['pwd'] );
	}
}

if ( ! function_exists( 'markas_auth_guard_is_managed_post' ) ) {
	function markas_auth_guard_is_managed_post() {
		return markas_auth_guard_is_core_credential_post()
			&& isset( $_POST['markas_auth_login'] )
			&& is_scalar( $_POST['markas_auth_login'] )
			&& (string) $_POST['markas_auth_login'] === '1';
	}
}

if ( ! function_exists( 'markas_auth_guard_same_origin' ) ) {
	function markas_auth_guard_same_origin() {
		$expected = isset( $_SERVER['HTTP_HOST'] ) && is_scalar( $_SERVER['HTTP_HOST'] ) ? strtolower( preg_replace( '/:\d+$/', '', (string) $_SERVER['HTTP_HOST'] ) ) : '';
		if ( $expected === '' ) {
			return false;
		}
		foreach ( array( 'HTTP_ORIGIN', 'HTTP_REFERER' ) as $key ) {
			if ( empty( $_SERVER[ $key ] ) || ! is_scalar( $_SERVER[ $key ] ) ) {
				continue;
			}
			$host = strtolower( (string) wp_parse_url( (string) $_SERVER[ $key ], PHP_URL_HOST ) );
			if ( $host === '' || $host !== $expected ) {
				return false;
			}
		}
		return true;
	}
}

if ( ! function_exists( 'markas_auth_guard_rate_key' ) ) {
	function markas_auth_guard_rate_key( $username ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_scalar( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : 'unknown';
		return 'markas_auth_rl_v1_' . substr( hash( 'sha256', $ip . '|' . strtolower( trim( (string) $username ) ) ), 0, 40 );
	}
}

if ( ! function_exists( 'markas_auth_guard_record_failure' ) ) {
	function markas_auth_guard_record_failure( $username ) {
		$key = markas_auth_guard_rate_key( $username );
		$now = time();
		$state = get_transient( $key );
		$state = is_array( $state ) ? $state : array();
		$started = isset( $state['started'] ) ? (int) $state['started'] : 0;
		$count = isset( $state['count'] ) ? (int) $state['count'] : 0;
		if ( $started <= 0 || ( $now - $started ) > 600 ) {
			$started = $now;
			$count = 0;
		}
		$count++;
		set_transient( $key, array(
			'started'       => $started,
			'count'         => $count,
			'blocked_until' => $count >= 8 ? $now + 600 : 0,
		), 1200 );
	}
}

add_filter( 'login_url', static function ( $url, $redirect, $force_reauth ) {
	$args = array();
	if ( is_string( $redirect ) && trim( $redirect ) !== '' ) {
		$args['redirect_to'] = markas_auth_guard_safe_redirect_target( $redirect );
	}
	if ( $force_reauth ) {
		$args['reauth'] = '1';
	}
	return markas_auth_guard_entry_url( $args );
}, PHP_INT_MAX, 3 );

add_action( 'login_init', static function () {
	if ( ! markas_auth_guard_is_core_path() ) {
		return;
	}

	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true );
		header( 'Surrogate-Control: no-store', true );
		header( 'X-Accel-Expires: 0', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'X-Frame-Options: SAMEORIGIN', true );
		header( 'X-Markas-Auth-Guard: mu-v1', true );
	}

	if ( strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) === 'POST' && markas_auth_guard_normal_login() ) {
		unset( $_POST['testcookie'], $_REQUEST['testcookie'] );
	}
	markas_auth_guard_issue_test_cookie();

	if ( ! markas_auth_guard_is_managed_post() ) {
		return;
	}

	$nonce = isset( $_POST['markas_auth_nonce'] ) && is_scalar( $_POST['markas_auth_nonce'] ) ? (string) $_POST['markas_auth_nonce'] : '';
	if ( $nonce === '' || ! wp_verify_nonce( $nonce, 'markas_auth_login' ) || ! markas_auth_guard_same_origin() ) {
		wp_safe_redirect( markas_auth_guard_entry_url( array( 'login' => 'expired' ) ), 303, 'Markas Auth Guard' );
		exit;
	}
}, -PHP_INT_MAX );

add_filter( 'authenticate', static function ( $user, $username, $password ) {
	if ( ! markas_auth_guard_is_core_credential_post() || $user instanceof WP_Error ) {
		return $user;
	}
	$state = get_transient( markas_auth_guard_rate_key( $username ) );
	if ( is_array( $state ) && isset( $state['blocked_until'] ) && (int) $state['blocked_until'] > time() ) {
		return new WP_Error( 'markas_auth_rate_limited', 'Too many sign-in attempts. Please wait and try again.' );
	}
	return $user;
}, 5, 3 );

add_action( 'wp_login_failed', static function ( $username, $error ) {
	if ( ! markas_auth_guard_is_core_credential_post() ) {
		return;
	}
	$limited = $error instanceof WP_Error && in_array( 'markas_auth_rate_limited', $error->get_error_codes(), true );
	if ( ! $limited ) {
		markas_auth_guard_record_failure( $username );
	}
	if ( markas_auth_guard_is_managed_post() ) {
		wp_safe_redirect( markas_auth_guard_entry_url( array( 'login' => $limited ? 'limited' : 'failed' ) ), 303, 'Markas Auth Guard' );
		exit;
	}
}, PHP_INT_MAX, 2 );

add_action( 'wp_login', static function ( $username, $user ) {
	if ( markas_auth_guard_is_core_credential_post() ) {
		delete_transient( markas_auth_guard_rate_key( $username ) );
	}
}, PHP_INT_MAX, 2 );

add_action( 'set_auth_cookie', static function ( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
	if ( ! markas_auth_guard_is_core_credential_post() || headers_sent() || ! is_string( $cookie ) || $cookie === '' ) {
		return;
	}
	$name = '';
	if ( (string) $scheme === 'secure_auth' && defined( 'SECURE_AUTH_COOKIE' ) ) {
		$name = (string) SECURE_AUTH_COOKIE;
	} elseif ( defined( 'AUTH_COOKIE' ) ) {
		$name = (string) AUTH_COOKIE;
	}
	if ( $name === '' ) {
		return;
	}
	markas_auth_guard_set_host_cookie( $name, $cookie, (int) $expire, '/wp-admin/', markas_auth_guard_https() );
}, PHP_INT_MAX, 6 );

add_action( 'set_logged_in_cookie', static function ( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
	if ( ! markas_auth_guard_is_core_credential_post() || headers_sent() || ! defined( 'LOGGED_IN_COOKIE' ) || ! is_string( $cookie ) || $cookie === '' ) {
		return;
	}
	markas_auth_guard_set_host_cookie( (string) LOGGED_IN_COOKIE, $cookie, (int) $expire, '/', markas_auth_guard_https() );
}, PHP_INT_MAX, 6 );

add_action( 'plugins_loaded', static function () {
	if ( ! markas_auth_guard_is_entry_path() ) {
		return;
	}

	$method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		status_header( 405 );
		header( 'Allow: GET, HEAD', true );
		exit;
	}

	markas_auth_guard_issue_test_cookie();
	$redirect_to = isset( $_GET['redirect_to'] ) && is_scalar( $_GET['redirect_to'] ) ? markas_auth_guard_safe_redirect_target( $_GET['redirect_to'] ) : admin_url( '/' );
	$reauth = isset( $_GET['reauth'] ) && is_scalar( $_GET['reauth'] ) && (string) $_GET['reauth'] !== '';
	if ( is_user_logged_in() && ! $reauth ) {
		wp_safe_redirect( $redirect_to, 302, 'Markas Auth Guard' );
		exit;
	}

	if ( ! headers_sent() ) {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ), true );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true );
		header( 'Surrogate-Control: no-store', true );
		header( 'X-Accel-Expires: 0', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'X-Frame-Options: DENY', true );
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'", true );
		header( 'X-Markas-Auth-Guard: mu-v1', true );
	}
	if ( $method === 'HEAD' ) {
		exit;
	}

	$site = trim( (string) get_option( 'blogname' ) );
	$site = $site !== '' ? $site : 'WordPress';
	$state = isset( $_GET['login'] ) && is_scalar( $_GET['login'] ) ? sanitize_key( $_GET['login'] ) : '';
	$notice = '';
	if ( $state === 'failed' ) {
		$notice = 'The sign-in details are incorrect.';
	} elseif ( $state === 'limited' ) {
		$notice = 'Too many sign-in attempts. Please wait a few minutes and try again.';
	} elseif ( $state === 'expired' ) {
		$notice = 'The sign-in form expired. Reload and try again.';
	}

	echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Sign in — '
		. esc_html( $site ) . '</title><style>:root{color-scheme:dark;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#080d1a;color:#f8fafc}.box{width:min(100%,400px);padding:30px;border:1px solid #25324a;border-radius:18px;background:#111827;box-shadow:0 28px 80px #0008}h1{margin:0;font-size:27px}.meta{margin:7px 0 22px;color:#94a3b8}.msg{margin:0 0 16px;padding:11px 12px;border-radius:9px;background:#451a1a;color:#fecaca;font-size:14px}label{display:block;margin:14px 0 7px;font-size:14px;font-weight:700}input[type=text],input[type=password]{width:100%;min-height:48px;border:1px solid #475569;border-radius:10px;padding:11px 12px;background:#0b1220;color:#fff;font:inherit}input:focus{outline:3px solid #2563eb55;border-color:#60a5fa}.remember{display:flex;gap:9px;align-items:center;margin:17px 0}.remember label{margin:0}.remember input{width:17px;height:17px}button{width:100%;min-height:48px;border:0;border-radius:10px;background:#2563eb;color:#fff;font:inherit;font-weight:800;cursor:pointer}.links,.runtime{text-align:center;margin:19px 0 0;font-size:14px}.links a{color:#93c5fd}.runtime{font-size:11px;color:#64748b}</style></head><body><main class="box"><h1>Sign in</h1><p class="meta">'
		. esc_html( $site ) . '</p>'
		. ( $notice !== '' ? '<div class="msg" role="alert">' . esc_html( $notice ) . '</div>' : '' )
		. '<form method="post" action="' . esc_url( markas_auth_guard_core_url() ) . '" autocomplete="on"><input type="hidden" name="markas_auth_login" value="1"><input type="hidden" name="markas_auth_nonce" value="' . esc_attr( wp_create_nonce( 'markas_auth_login' ) ) . '"><input type="hidden" name="redirect_to" value="' . esc_attr( $redirect_to ) . '"><label for="user_login">Username or email</label><input id="user_login" name="log" type="text" autocomplete="username" autocapitalize="off" spellcheck="false" required autofocus><label for="user_pass">Password</label><input id="user_pass" name="pwd" type="password" autocomplete="current-password" required><div class="remember"><input id="rememberme" name="rememberme" type="checkbox" value="forever"><label for="rememberme">Remember me</label></div><button type="submit">Sign in securely</button></form><p class="links"><a href="' . esc_url( wp_lostpassword_url( $redirect_to ) ) . '">Lost your password?</a></p><p class="runtime">Protected sign-in · MU v1</p></main></body></html>';
	exit;
}, -PHP_INT_MAX );
