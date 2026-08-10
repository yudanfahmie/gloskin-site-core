<?php
/**
 * Narrow transport compatibility for temporary sample-media sources.
 *
 * Keeps the immutable migration bundle/fingerprint untouched while repairing
 * an upstream Pexels asset whose canonical photo page is still live but whose
 * previously recorded .jpeg delivery path now returns 404. The corrected URL
 * is the same Pexels photo ID and is only substituted for that exact request.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Sample_Media_Compatibility {
	const BROKEN_URL  = 'https://images.pexels.com/photos/7817511/pexels-photo-7817511.jpeg';
	const WORKING_URL = 'https://images.pexels.com/photos/7817511/pexels-photo-7817511.png?cs=srgb&dl=pexels-elena-druzhinina-54874780-7817511.jpg&fm=jpg';

	/** @return void */
	public function register() {
		add_filter( 'pre_http_request', array( $this, 'reroute_known_source' ), 10, 3 );
	}

	/**
	 * Preserve WordPress/Woo importer ownership and only repair the exact stale
	 * transport URL. Passing the original request args through retains
	 * download_url()'s timeout/stream/filename contract.
	 *
	 * @param false|array|WP_Error $preempt Existing preempted response.
	 * @param array<string,mixed>   $args HTTP request args.
	 * @param string                $url Requested URL.
	 * @return false|array|WP_Error
	 */
	public function reroute_known_source( $preempt, $args, $url ) {
		if ( false !== $preempt || self::BROKEN_URL !== (string) $url ) {
			return $preempt;
		}

		return wp_safe_remote_get( self::WORKING_URL, $args );
	}
}
