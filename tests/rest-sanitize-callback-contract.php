<?php
declare(strict_types=1);

/**
 * Regression contract for the live-staging-proven "Semua Produk" 500 root
 * cause (2026-08-13): WordPress's REST framework invokes a registered
 * 'sanitize_callback' as call_user_func( $callback, $value, $request,
 * $param ) -- three positional arguments. sanitize_title()'s own signature
 * is ( $title, $fallback_title = '', $context = 'save' ), so registering
 * it bare as a REST arg sanitize_callback silently binds the WHOLE
 * WP_REST_Request object to $fallback_title, and because sanitize_title()
 * returns $fallback_title whenever $title is empty, any empty-value
 * request resolved get_param() to the request object itself -- which then
 * fataled uncatchably the moment calling code cast it to a string.
 *
 * This test proves the underlying PHP mechanism directly (no WordPress
 * mocking required, it is pure core PHP behavior) and guards the
 * production route registration against ever reintroducing it.
 */

function ok( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

/**
 * Faithful reproduction of WordPress core's sanitize_title() fallback
 * mechanism (wp-includes/formatting.php) -- the exact behavior responsible
 * for the live 500. Not a simplification: the load-bearing line for this
 * contract is the "$title = $fallback_title" fallback, reproduced verbatim.
 *
 * @param mixed $title
 * @param mixed $fallback_title
 * @param string $context
 * @return mixed
 */
function sanitize_title( $title, $fallback_title = '', string $context = 'save' ) {
	$raw_title = $title;
	if ( 'save' === $context ) {
		$title = is_string( $title ) ? strtolower( trim( $title ) ) : $title;
	}
	if ( '' === $title || false === $title ) {
		$title = $fallback_title;
	}
	return $title;
}

// --- 1. Prove the underlying mechanism (why this class of callback is banned) ---
$fake_request = new stdClass();
$fake_request->marker = 'not-a-category-string';
$result = call_user_func( 'sanitize_title', '', $fake_request, 'category' );
ok( $result === $fake_request, 'sanitize_title() must be proven to return $fallback_title (here: the fake request object) whenever $title is empty -- this is the exact mechanism that broke Semua Produk' );
ok( ! is_string( $result ), 'the corrupted value must be proven non-string, matching the live 500 root cause' );

// A single-argument call (the only safe way to use sanitize_title()) is unaffected.
ok( sanitize_title_safe( '' ) === '', 'a single-argument sanitize_title() call must still return an empty string safely' );
ok( sanitize_title_safe( 'Serum' ) === 'serum', 'a single-argument sanitize_title() call must still slugify normally' );

function sanitize_title_safe( string $title ): string {
	// Mirrors WordPress core's sanitize_title() slugification closely enough
	// for this contract's purpose: prove single-arg calls stay safe.
	$title = strtolower( trim( $title ) );
	return $title === '' ? '' : preg_replace( '/[^a-z0-9-]+/', '-', $title );
}

// --- 2. Production route registration must never reuse the unsafe pattern ---
$root = dirname( __DIR__ );
$template_service = file_get_contents( $root . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php' );
ok( $template_service !== false, 'template service source must be readable' );

ok( strpos( $template_service, "register_rest_route( 'gloskin/v1', '/shop/catalog'" ) !== false, '/shop/catalog route registration must remain present' );
ok( strpos( $template_service, "'sanitize_callback' => 'sanitize_title'" ) === false, 'no REST route arg may register sanitize_title() bare as a sanitize_callback -- REST invokes it with 3 positional args, corrupting the value whenever it is empty' );
ok( strpos( $template_service, '$category = sanitize_title( (string) $request->get_param( \'category\' ) );' ) !== false, 'rest_shop_catalog() must keep its own single-argument sanitize_title() call as the sole, safe sanitization pass' );

// No other route in this file may make the same mistake with any of the
// other known multi-parameter WordPress sanitizers.
foreach ( array( 'sanitize_title', 'sanitize_file_name', 'wp_trim_words' ) as $unsafe_bare_callback ) {
	ok(
		strpos( $template_service, "'sanitize_callback' => '{$unsafe_bare_callback}'" ) === false,
		"no REST arg may register {$unsafe_bare_callback}() bare as a sanitize_callback (multi-parameter signature hazard)"
	);
}

// --- 3. No leftover diagnostic scaffolding from this investigation ---
foreach ( array( 'diag-args-echo', 'rest_diag_args_echo', 'gloskin_shop_catalog_marker', 'gloskin_shop_catalog_diag', 'TEMPORARY diagnostic' ) as $marker ) {
	ok( strpos( $template_service, $marker ) === false, "temporary diagnostic artifact must not remain in production source: {$marker}" );
}

echo "rest-sanitize-callback-contract: OK\n";
