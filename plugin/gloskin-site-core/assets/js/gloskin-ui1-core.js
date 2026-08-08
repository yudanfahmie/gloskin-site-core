(function () {
	'use strict';

	function focusable(root) {
		return Array.prototype.slice.call(
			root.querySelectorAll(
				'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
			)
		).filter(function (node) {
			return !node.hasAttribute('hidden') && node.offsetParent !== null;
		});
	}

	function initDrawer() {
		var opener = document.querySelector('[data-gloskin-drawer-open]');
		var drawer = document.querySelector('[data-gloskin-drawer]');
		if (!opener || !drawer) {
			return;
		}

		var dialog = drawer.querySelector('[role="dialog"]');
		var closers = drawer.querySelectorAll('[data-gloskin-drawer-close]');
		var previousFocus = null;

		function openDrawer() {
			previousFocus = document.activeElement;
			drawer.hidden = false;
			drawer.setAttribute('aria-hidden', 'false');
			opener.setAttribute('aria-expanded', 'true');
			document.documentElement.classList.add('gloskin-ui1-drawer-open');
			var nodes = focusable(dialog || drawer);
			if (nodes.length) {
				nodes[0].focus();
			}
		}

		function closeDrawer() {
			drawer.setAttribute('aria-hidden', 'true');
			drawer.hidden = true;
			opener.setAttribute('aria-expanded', 'false');
			document.documentElement.classList.remove('gloskin-ui1-drawer-open');
			if (previousFocus && typeof previousFocus.focus === 'function') {
				previousFocus.focus();
			}
		}

		opener.addEventListener('click', openDrawer);
		Array.prototype.forEach.call(closers, function (closer) {
			closer.addEventListener('click', closeDrawer);
		});

		drawer.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				closeDrawer();
				return;
			}
			if (event.key !== 'Tab' || !dialog) {
				return;
			}
			var nodes = focusable(dialog);
			if (!nodes.length) {
				return;
			}
			var first = nodes[0];
			var last = nodes[nodes.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});
	}

	function initDisclosures() {
		var toggles = document.querySelectorAll('[data-gloskin-submenu-toggle]');
		Array.prototype.forEach.call(toggles, function (toggle) {
			var targetId = toggle.getAttribute('aria-controls');
			var target = targetId ? document.getElementById(targetId) : null;
			if (!target) {
				return;
			}
			toggle.addEventListener('click', function () {
				var expanded = toggle.getAttribute('aria-expanded') === 'true';
				toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
				target.hidden = expanded;
			});
		});
	}

	function init() {
		initDrawer();
		initDisclosures();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
