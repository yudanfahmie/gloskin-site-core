<?php
/**
 * Plugin Name: Gloskin Site Core
 * Description: Owns the Gloskin front-end shell, navigation, content structures, and WooCommerce/form integrations without rebuilding WordPress or WooCommerce primitives.
 * Version: 0.7.231
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
 * Keep Gloskin editorial list screens passive on page boot.
 *
 * EditorialManager still owns the modal and opens it from explicit Edit/Add
 * clicks. Legacy redirects may carry gloskin_edit/gloskin_add in the URL; those
 * are treated as one-shot navigation hints, not permission to surprise-open a
 * modal every time the list is refreshed or revisited.
 *
 * @return void
 */
function gloskin_site_core_guard_editorial_modal_autostart() {
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'edit' !== (string) $screen->base || ! in_array( (string) $screen->post_type, array( 'gloskin_promo', 'gloskin_testimonial' ), true ) ) {
		return;
	}
	if ( ! wp_script_is( 'gloskin-editorial-manager', 'enqueued' ) ) {
		return;
	}

	$script = <<<'JS'
(function () {
	'use strict';
	var config = window.GloskinEditorialManager;
	if (!config || (!config.editId && !config.addId)) { return; }

	config.editId = 0;
	config.addId = 0;

	var modal = document.querySelector('[data-gloskin-editorial-modal]');
	if (modal && !modal.hidden) {
		modal.hidden = true;
	}
	if (document.body) {
		document.body.classList.remove('gloskin-editorial-modal-open');
	}

	try {
		var url = new URL(window.location.href);
		var changed = false;
		['gloskin_edit', 'gloskin_add', 'gloskin_new'].forEach(function (key) {
			if (url.searchParams.has(key)) {
				url.searchParams.delete(key);
				changed = true;
			}
		});
		if (changed && window.history && typeof window.history.replaceState === 'function') {
			window.history.replaceState(window.history.state, document.title, url.pathname + (url.search || '') + (url.hash || ''));
		}
	} catch (ignore) {}
})();
JS;

	wp_add_inline_script( 'gloskin-editorial-manager', $script, 'after' );
}
add_action( 'admin_enqueue_scripts', 'gloskin_site_core_guard_editorial_modal_autostart', 100 );

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
