<?php
/**
 * External form presentation adapter.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Form_Adapter {
	const SETTINGS_OPTION = 'gloskin_site_core_settings';

	/**
	 * Render only a configured shortcode whose provider is currently registered.
	 *
	 * @return string
	 */
	public function render() {
		$settings  = get_option( self::SETTINGS_OPTION, array() );
		$shortcode = isset( $settings['form_shortcode'] ) && is_string( $settings['form_shortcode'] )
			? trim( $settings['form_shortcode'] )
			: '';

		if ( '' === $shortcode ) {
			return $this->fallback();
		}

		$tag = $this->shortcode_tag( $shortcode );
		if ( '' === $tag || ! shortcode_exists( $tag ) ) {
			return $this->fallback();
		}

		return (string) do_shortcode( $shortcode );
	}

	/**
	 * @param string $number Normalized international number.
	 * @param string $message Optional prefilled message.
	 * @return string
	 */
	public function whatsapp_url( $number, $message = '' ) {
		$digits = preg_replace( '/\D+/', '', (string) $number );
		if ( ! is_string( $digits ) || '' === $digits ) {
			return '';
		}

		$url = 'https://wa.me/' . rawurlencode( $digits );
		if ( '' !== trim( (string) $message ) ) {
			$url .= '?text=' . rawurlencode( (string) $message );
		}

		return esc_url_raw( $url, array( 'https' ) );
	}

	/**
	 * @param string $number Normalized phone.
	 * @return string
	 */
	public function phone_url( $number ) {
		$number = preg_replace( '/[^0-9+]/', '', (string) $number );
		if ( ! is_string( $number ) || '' === $number ) {
			return '';
		}
		return 'tel:' . $number;
	}

	/**
	 * @return string
	 */
	private function fallback() {
		return '<div class="gloskin-ui1-empty gloskin-ui1-empty--form">'
			. esc_html__( 'The online contact form is not configured yet. Please use an available clinic contact method.', 'gloskin-site-core' )
			. '</div>';
	}

	/**
	 * @param string $shortcode Configured shortcode.
	 * @return string
	 */
	private function shortcode_tag( $shortcode ) {
		if ( ! preg_match( '/^\s*\[([A-Za-z0-9_-]+)/', $shortcode, $matches ) ) {
			return '';
		}
		return isset( $matches[1] ) ? sanitize_key( $matches[1] ) : '';
	}
}
