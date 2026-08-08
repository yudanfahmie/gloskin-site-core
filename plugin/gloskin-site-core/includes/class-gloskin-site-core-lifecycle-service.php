<?php
/**
 * Gloskin plugin lifecycle owner.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Lifecycle_Service {
	/**
	 * Register rewrite-owning content types and flush once on activation.
	 *
	 * @return void
	 */
	public function activate() {
		Gloskin_Site_Core_Content_Service::register_content_types();
		flush_rewrite_rules( false );
	}

	/**
	 * Remove persisted rewrite rules on deactivation.
	 *
	 * @return void
	 */
	public function deactivate() {
		flush_rewrite_rules( false );
	}
}
