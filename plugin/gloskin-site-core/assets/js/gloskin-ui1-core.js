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

	function initSmartHeader() {
		var header = document.querySelector('.gloskin-ui1-header');
		if (!header) {
			return;
		}

		var topGuard = Math.max(header.offsetHeight, 72);
		var previousY = Math.max(window.scrollY || 0, 0);
		var direction = 0;
		var directionDistance = 0;
		var scheduled = false;
		var hideThreshold = 10;
		var showThreshold = 4;

		function interactionActive() {
			if (header.contains(document.activeElement)) {
				return true;
			}
			if (header.querySelector('[data-gloskin-submenu-toggle][aria-expanded="true"]')) {
				return true;
			}
			if (document.documentElement.classList.contains('gloskin-ui1-drawer-open')) {
				return true;
			}
			var drawerOpener = header.querySelector('[data-gloskin-drawer-open]');
			return !!drawerOpener && drawerOpener.getAttribute('aria-expanded') === 'true';
		}

		function showHeader() {
			header.classList.remove('is-hidden');
		}

		function updateHeader() {
			var currentY = Math.max(window.scrollY || 0, 0);
			var delta = currentY - previousY;
			previousY = currentY;

			if (currentY <= topGuard) {
				showHeader();
				direction = 0;
				directionDistance = 0;
				scheduled = false;
				return;
			}

			if (0 === delta) {
				scheduled = false;
				return;
			}

			var nextDirection = delta > 0 ? 1 : -1;
			if (nextDirection !== direction) {
				direction = nextDirection;
				directionDistance = 0;
			}
			directionDistance += Math.abs(delta);

			if (interactionActive()) {
				showHeader();
				directionDistance = 0;
				scheduled = false;
				return;
			}

			if (direction > 0 && directionDistance >= hideThreshold) {
				header.classList.add('is-hidden');
				directionDistance = 0;
			} else if (direction < 0 && directionDistance >= showThreshold) {
				showHeader();
				directionDistance = 0;
			}
			scheduled = false;
		}

		function onScroll() {
			if (scheduled) {
				return;
			}
			scheduled = true;
			window.requestAnimationFrame(updateHeader);
		}

		window.addEventListener('scroll', onScroll, { passive: true });
		header.addEventListener('focusin', function () {
			showHeader();
			direction = 0;
			directionDistance = 0;
		});
	}

	function init() {
		initDrawer();
		initDisclosures();
		initSmartHeader();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
