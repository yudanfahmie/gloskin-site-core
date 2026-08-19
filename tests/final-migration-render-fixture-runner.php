<?php
declare(strict_types=1);

/* The integration fixture builds an in-memory copy of runtime-smoke.php and
 * searches for its literal `$plugin = ...` bootstrap line. Seed the local
 * variable with that literal so the fixture's double-quoted search string does
 * not interpolate to an empty value. This runner mutates no repository state. */
$plugin = '$plugin';

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $value ) {
		return filter_var( (string) $value, FILTER_SANITIZE_EMAIL );
	}
}
if ( ! function_exists( 'is_email' ) ) {
	function is_email( $value ) {
		return false !== filter_var( (string) $value, FILTER_VALIDATE_EMAIL );
	}
}

require __DIR__ . '/final-migration-render-fixture.php';
