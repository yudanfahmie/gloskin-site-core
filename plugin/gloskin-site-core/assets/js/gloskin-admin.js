/*
 * Gloskin Settings screen -- presentation-only horizontal tab switching.
 *
 * Progressive enhancement: the server renders every panel visible (see
 * Gloskin_Site_Core_Admin_Service::render_settings_page()), so this only
 * hides the inactive ones on load and wires click/keyboard switching. Owns
 * no Gloskin business logic, settings state or persistence -- the real
 * <input type="radio"> controls inside each panel remain the sole
 * canonical form state, submitted natively via the existing options.php
 * flow regardless of which tab happens to be visible.
 *
 * @package GloskinSiteCore
 */
(function () {
	'use strict';

	function init() {
		var tabGroups = document.querySelectorAll('[data-gloskin-admin-tabs]');
		Array.prototype.forEach.call(tabGroups, function (group) {
			var tabs = Array.prototype.slice.call(group.querySelectorAll('[data-gloskin-admin-tab]'));
			if (!tabs.length) { return; }
			var panels = tabs.map(function (tab) {
				return document.querySelector('[data-gloskin-admin-panel="' + tab.getAttribute('data-gloskin-admin-tab') + '"]');
			});

			/* Standard WAI-ARIA tabs roving-tabindex pattern: exactly one tab
			 * is ever in the Tab order (tabIndex 0); the rest are -1 and
			 * reachable only via arrow/Home/End once a tab already has
			 * focus. Focus always follows activation. */
			function activate(index) {
				tabs.forEach(function (tab, i) {
					var active = i === index;
					tab.classList.toggle('is-active', active);
					tab.setAttribute('aria-selected', active ? 'true' : 'false');
					tab.tabIndex = active ? 0 : -1;
					if (panels[i]) { panels[i].hidden = !active; }
				});
				tabs[index].focus();
			}

			tabs.forEach(function (tab, index) {
				tab.addEventListener('click', function () { activate(index); });
				tab.addEventListener('keydown', function (event) {
					var next = null;
					if (event.key === 'ArrowRight') { next = (index + 1) % tabs.length; }
					else if (event.key === 'ArrowLeft') { next = (index - 1 + tabs.length) % tabs.length; }
					else if (event.key === 'Home') { next = 0; }
					else if (event.key === 'End') { next = tabs.length - 1; }
					if (next === null) { return; }
					event.preventDefault();
					activate(next);
				});
			});

			var activeIndex = 0;
			tabs.forEach(function (tab, i) {
				if (tab.classList.contains('is-active')) { activeIndex = i; }
			});
			/* Sync tabIndex/panel visibility to whichever tab the server marked
			 * active, without calling activate() -- that would also steal
			 * focus on ordinary page load, which no tab should do unasked. */
			tabs.forEach(function (tab, i) { tab.tabIndex = i === activeIndex ? 0 : -1; });
			panels.forEach(function (panel, i) {
				if (panel) { panel.hidden = i !== activeIndex; }
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
