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

			function activate(index) {
				tabs.forEach(function (tab, i) {
					var active = i === index;
					tab.classList.toggle('is-active', active);
					tab.setAttribute('aria-selected', active ? 'true' : 'false');
					if (panels[i]) { panels[i].hidden = !active; }
				});
				tabs[index].focus();
			}

			tabs.forEach(function (tab, index) {
				tab.addEventListener('click', function () { activate(index); });
				tab.addEventListener('keydown', function (event) {
					if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') { return; }
					event.preventDefault();
					var next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : (index - 1 + tabs.length) % tabs.length;
					activate(next);
				});
			});

			var activeIndex = 0;
			tabs.forEach(function (tab, i) {
				if (tab.classList.contains('is-active')) { activeIndex = i; }
			});
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
