<?php
/**
 * Plugin Name: Gloskin Site Core
 * Description: Owns the Gloskin front-end shell, navigation, content structures, and WooCommerce/form integrations without rebuilding WordPress or WooCommerce primitives.
 * Version: 0.7.232
 * Author: Gloskin
 * Requires PHP: 7.4
 * Text Domain: gloskin-site-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-gloskin-site-core-kernel.php';
require_once __DIR__ . '/includes/class-gloskin-site-core-insight-discussion-service.php';

/**
 * Disable WordPress' editor welcome modal on Gloskin-owned content screens.
 *
 * This is scoped to Gloskin CPTs only and uses the native preference stores.
 * It prevents the block-editor welcome dialog from becoming another
 * every-visit modal while leaving normal editor notices and validation intact.
 *
 * @return void
 */
function gloskin_site_core_disable_editor_welcome_modal() {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return;
	}

	$screen = get_current_screen();
	$types  = array(
		'gloskin_treatment',
		'gloskin_clinic',
		'gloskin_doctor',
		'gloskin_promo',
		'gloskin_testimonial',
		'gloskin_achievement',
	);
	if ( ! $screen || ! in_array( (string) $screen->post_type, $types, true ) || ! wp_script_is( 'wp-edit-post', 'registered' ) ) {
		return;
	}

	$script = <<<'JS'
(function (wp) {
	'use strict';
	if (!wp || !wp.data || typeof wp.data.dispatch !== 'function') { return; }
	try {
		var preferences = wp.data.dispatch('core/preferences');
		if (preferences && typeof preferences.set === 'function') {
			preferences.set('core/edit-post', 'welcomeGuide', false);
		}
		var editPost = wp.data.dispatch('core/edit-post');
		var editPostSelect = typeof wp.data.select === 'function' ? wp.data.select('core/edit-post') : null;
		if (
			editPost &&
			typeof editPost.toggleFeature === 'function' &&
			editPostSelect &&
			typeof editPostSelect.isFeatureActive === 'function' &&
			editPostSelect.isFeatureActive('welcomeGuide')
		) {
			editPost.toggleFeature('welcomeGuide');
		}
	} catch (ignore) {}
})(window.wp);
JS;

	wp_add_inline_script( 'wp-edit-post', $script, 'after' );
}
add_action( 'enqueue_block_editor_assets', 'gloskin_site_core_disable_editor_welcome_modal', 100 );

/**
 * Bootstrap the plugin after all plugins are available so integration checks can run.
 */
function gloskin_site_core_bootstrap() {
	$insight_discussion = new Gloskin_Site_Core_Insight_Discussion_Service();
	$insight_discussion->register();

	$kernel = new Gloskin_Site_Core_Kernel( __FILE__ );
	$kernel->boot();
}
add_action( 'plugins_loaded', 'gloskin_site_core_bootstrap', 20 );
