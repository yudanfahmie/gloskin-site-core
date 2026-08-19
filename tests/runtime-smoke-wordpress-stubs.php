<?php
declare(strict_types=1);

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		$email = trim( (string) $email );
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email, $deprecated = false ) {
		unset( $deprecated );
		$email = trim( (string) $email );
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['gl_shortcodes'][ (string) $tag ] = $callback;
	}
}

if ( ! function_exists( 'is_404' ) ) {
	function is_404() {
		return false;
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone() {
		return new DateTimeZone( 'UTC' );
	}
}

if ( getenv( 'GL_TEST_DIAGNOSTICS' ) ) {
	register_shutdown_function(
		static function () {
			$context  = isset( $GLOBALS['gl_query_vars']['gloskin_context'] ) && is_array( $GLOBALS['gl_query_vars']['gloskin_context'] ) ? $GLOBALS['gl_query_vars']['gloskin_context'] : array();
			$view     = isset( $context['view'] ) ? (string) $context['view'] : '';
			$clinics  = isset( $context['clinics'] ) && is_array( $context['clinics'] ) ? count( $context['clinics'] ) : 0;
			$skincare = isset( $context['skincare'] ) && is_array( $context['skincare'] ) ? count( $context['skincare'] ) : 0;
			fwrite( STDERR, sprintf( "GL_RUNTIME_CONTEXT view=%s clinics=%d skincare=%d\n", $view, $clinics, $skincare ) );
		}
	);
}
