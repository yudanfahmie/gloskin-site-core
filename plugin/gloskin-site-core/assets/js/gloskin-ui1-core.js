(function () {
	'use strict';

	/* -----------------------------------------------------------------
	 * Shared DOM helpers
	 * ----------------------------------------------------------------- */

	function focusable(root) {
		return Array.prototype.slice.call(
			root.querySelectorAll(
				'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
			)
		).filter(function (node) {
			return !node.hasAttribute('hidden') && node.offsetParent !== null;
		});
	}

	function trapFocus(container, event) {
		if (event.key !== 'Tab') { return; }
		var nodes = focusable(container);
		if (!nodes.length) { return; }
		var first = nodes[0];
		var last = nodes[nodes.length - 1];
		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	/* -----------------------------------------------------------------
	 * Unified overlay system — one overlay may own focus at a time
	 * ----------------------------------------------------------------- */

	var overlay = (function () {
		var current = null;
		var previousFocus = null;

		function find(id) {
			return document.querySelector('[data-gloskin-overlay="' + id + '"]');
		}

		function open(id) {
			if (current === id) { return; }
			if (current) { close(); }
			var el = find(id);
			if (!el) { return; }
			previousFocus = document.activeElement;
			current = id;
			el.hidden = false;
			el.setAttribute('aria-hidden', 'false');
			document.documentElement.classList.add('gloskin-ui1-overlay-open');
			var trigger = document.querySelector('[data-gloskin-' + id + '-open]');
			if (trigger) { trigger.setAttribute('aria-expanded', 'true'); }
			var nodes = focusable(el);
			if (nodes.length) { nodes[0].focus(); }
		}

		function close() {
			if (!current) { return; }
			var el = find(current);
			var trigger = document.querySelector('[data-gloskin-' + current + '-open]');
			if (el) {
				el.setAttribute('aria-hidden', 'true');
				el.hidden = true;
			}
			if (trigger) { trigger.setAttribute('aria-expanded', 'false'); }
			document.documentElement.classList.remove('gloskin-ui1-overlay-open');
			current = null;
			if (previousFocus && typeof previousFocus.focus === 'function') {
				previousFocus.focus();
			}
			previousFocus = null;
		}

		function isOpen(id) { return current === id; }
		function active() { return current; }

		return { open: open, close: close, isOpen: isOpen, active: active };
	})();

	/* -----------------------------------------------------------------
	 * Mobile drawer (existing functionality, preserved)
	 * ----------------------------------------------------------------- */

	function initDrawer() {
		var opener = document.querySelector('[data-gloskin-drawer-open]');
		var drawer = document.querySelector('[data-gloskin-drawer]');
		if (!opener || !drawer) { return; }

		var dialog = drawer.querySelector('[role="dialog"]');
		var closers = drawer.querySelectorAll('[data-gloskin-drawer-close]');
		var previousFocus = null;

		function openDrawer() {
			if (overlay.active()) { overlay.close(); }
			previousFocus = document.activeElement;
			drawer.hidden = false;
			drawer.setAttribute('aria-hidden', 'false');
			opener.setAttribute('aria-expanded', 'true');
			document.documentElement.classList.add('gloskin-ui1-drawer-open');
			var nodes = focusable(dialog || drawer);
			if (nodes.length) { nodes[0].focus(); }
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
			if (dialog) { trapFocus(dialog, event); }
		});

		/* Wishlist trigger from within drawer — close drawer, then open wishlist */
		var wishlistFromDrawer = drawer.querySelector('[data-gloskin-wishlist-open-from-drawer]');
		if (wishlistFromDrawer) {
			wishlistFromDrawer.addEventListener('click', function () {
				closeDrawer();
				setTimeout(function () { overlay.open('wishlist'); }, 80);
			});
		}
	}

	/* -----------------------------------------------------------------
	 * Submenu disclosures
	 * ----------------------------------------------------------------- */

	function initDisclosures() {
		var toggles = document.querySelectorAll('[data-gloskin-submenu-toggle]');
		Array.prototype.forEach.call(toggles, function (toggle) {
			var targetId = toggle.getAttribute('aria-controls');
			var target = targetId ? document.getElementById(targetId) : null;
			if (!target) { return; }
			toggle.addEventListener('click', function () {
				var expanded = toggle.getAttribute('aria-expanded') === 'true';
				toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
				target.hidden = expanded;
			});
		});
	}

	/* -----------------------------------------------------------------
	 * Smart sticky header
	 * ----------------------------------------------------------------- */

	function initSmartHeader() {
		var header = document.querySelector('.gloskin-ui1-header');
		if (!header) { return; }

		var topGuard = Math.max(header.offsetHeight, 72);
		var previousY = Math.max(window.scrollY || 0, 0);
		var direction = 0;
		var directionDistance = 0;
		var scheduled = false;
		var hideThreshold = 10;
		var showThreshold = 4;

		function interactionActive() {
			if (header.contains(document.activeElement)) { return true; }
			if (header.querySelector('[data-gloskin-submenu-toggle][aria-expanded="true"]')) { return true; }
			if (document.documentElement.classList.contains('gloskin-ui1-drawer-open')) { return true; }
			if (document.documentElement.classList.contains('gloskin-ui1-overlay-open')) { return true; }
			var drawerOpener = header.querySelector('[data-gloskin-drawer-open]');
			return !!drawerOpener && drawerOpener.getAttribute('aria-expanded') === 'true';
		}

		function showHeader() { header.classList.remove('is-hidden'); }
		function hideHeader() { header.classList.add('is-hidden'); }
		function resetDirection(nextDirection) { direction = nextDirection || 0; directionDistance = 0; }

		function updateHeader() {
			var currentY = Math.max(window.scrollY || 0, 0);
			var delta = currentY - previousY;
			previousY = currentY;
			if (currentY <= topGuard) { showHeader(); resetDirection(0); scheduled = false; return; }
			if (interactionActive()) { showHeader(); resetDirection(0); scheduled = false; return; }
			if (0 === delta) { scheduled = false; return; }
			var nextDirection = delta > 0 ? 1 : -1;
			if (nextDirection !== direction) { direction = nextDirection; directionDistance = Math.abs(delta); }
			else { directionDistance += Math.abs(delta); }
			if (direction < 0 && directionDistance >= showThreshold) { showHeader(); directionDistance = 0; }
			else if (direction > 0 && directionDistance >= hideThreshold) { hideHeader(); directionDistance = 0; }
			scheduled = false;
		}

		window.addEventListener('scroll', function () {
			if (scheduled) { return; }
			scheduled = true;
			window.requestAnimationFrame(updateHeader);
		}, { passive: true });
		header.addEventListener('focusin', function () { showHeader(); resetDirection(0); });
	}

	/* -----------------------------------------------------------------
	 * Overlay close wiring (shared for all overlays)
	 * ----------------------------------------------------------------- */

	function initOverlayCloseButtons() {
		document.addEventListener('click', function (e) {
			if (e.target.closest('[data-gloskin-overlay-close]') && overlay.active()) {
				overlay.close();
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && overlay.active()) {
				e.preventDefault();
				overlay.close();
			}
		});
		/* Focus trap for active overlay */
		document.addEventListener('keydown', function (e) {
			var id = overlay.active();
			if (!id) { return; }
			var el = document.querySelector('[data-gloskin-overlay="' + id + '"]');
			if (!el) { return; }
			var panel = el.querySelector('[role="dialog"]') || el;
			trapFocus(panel, e);
		});
	}

	/* -----------------------------------------------------------------
	 * Search overlay
	 * ----------------------------------------------------------------- */

	function initSearch() {
		var triggers = document.querySelectorAll('[data-gloskin-search-open]');
		var input = document.querySelector('[data-gloskin-search-input]');
		var clearBtn = document.querySelector('[data-gloskin-search-clear]');
		var resultsContainer = document.querySelector('[data-gloskin-search-results]');
		if (!input || !resultsContainer) { return; }

		var debounceTimer = null;
		var abortController = null;
		var config = window.gloskinData || {};

		Array.prototype.forEach.call(triggers, function (trigger) {
			trigger.addEventListener('click', function () {
				overlay.open('search');
				setTimeout(function () { input.focus(); }, 60);
			});
		});

		function updateClear() {
			if (clearBtn) { clearBtn.hidden = !input.value; }
		}

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				input.value = '';
				updateClear();
				resultsContainer.innerHTML = '';
				input.focus();
			});
		}

		input.addEventListener('input', function () {
			updateClear();
			var query = input.value.trim();
			if (query.length < 2) {
				resultsContainer.innerHTML = '';
				return;
			}
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(function () { doSearch(query); }, 220);
		});

		/* Native form fallback */
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				var q = input.value.trim();
				if (q.length >= 2 && config.searchFallback) {
					window.location.href = config.searchFallback + encodeURIComponent(q);
				}
			}
		});

		function doSearch(query) {
			if (abortController) { abortController.abort(); }
			abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
			resultsContainer.innerHTML = '<div class="gloskin-ui1-search-overlay__loading"><span></span></div>';

			var url = (config.restUrl || '/wp-json/gloskin/v1/') + 'search?q=' + encodeURIComponent(query);
			var fetchOpts = { headers: { 'X-WP-Nonce': config.restNonce || '' } };
			if (abortController) { fetchOpts.signal = abortController.signal; }

			fetch(url, fetchOpts)
				.then(function (res) { return res.json(); })
				.then(function (data) { renderResults(data.groups || []); })
				.catch(function (err) {
					if (err.name === 'AbortError') { return; }
					resultsContainer.innerHTML = '<p class="gloskin-ui1-search-overlay__error">' +
						'Pencarian tidak tersedia saat ini.' + '</p>';
				});
		}

		function renderResults(groups) {
			if (!groups.length) {
				resultsContainer.innerHTML = '<p class="gloskin-ui1-search-overlay__empty">' +
					'Tidak ada hasil ditemukan.' + '</p>';
				return;
			}
			var html = '';
			for (var g = 0; g < groups.length; g++) {
				var group = groups[g];
				html += '<div class="gloskin-ui1-search-results__group">';
				html += '<h3 class="gloskin-ui1-search-results__label">' + escapeHtml(group.label) + '</h3>';
				html += '<ul class="gloskin-ui1-search-results__list">';
				for (var i = 0; i < group.items.length; i++) {
					var item = group.items[i];
					html += '<li><a class="gloskin-ui1-search-results__item" href="' + escapeHtml(item.url) + '">';
					html += '<span class="gloskin-ui1-search-results__title">' + escapeHtml(item.title) + '</span>';
					if (item.excerpt) {
						html += '<span class="gloskin-ui1-search-results__excerpt">' + escapeHtml(item.excerpt) + '</span>';
					}
					if (item.price_html) {
						html += '<span class="gloskin-ui1-search-results__price">' + item.price_html + '</span>';
					}
					html += '</a></li>';
				}
				html += '</ul></div>';
			}
			resultsContainer.innerHTML = html;
		}
	}

	/* -----------------------------------------------------------------
	 * Cart sheet
	 * ----------------------------------------------------------------- */

	function initCart() {
		var config = window.gloskinData || {};
		if (!config.woo) { return; }

		var triggers = document.querySelectorAll('[data-gloskin-cart-open]');
		Array.prototype.forEach.call(triggers, function (trigger) {
			trigger.addEventListener('click', function () { overlay.open('cart'); });
		});

		/* Listen for WooCommerce AJAX add-to-cart (jQuery event) */
		if (window.jQuery) {
			window.jQuery(document.body).on('added_to_cart', function () {
				overlay.open('cart');
			});
		}
	}

	/* -----------------------------------------------------------------
	 * Wishlist (localStorage for all users)
	 * ----------------------------------------------------------------- */

	function initWishlist() {
		var config = window.gloskinData || {};
		if (!config.woo) { return; }

		var STORAGE_KEY = 'gloskin_wishlist';
		var MAX_ITEMS = 50;

		function getIds() {
			try {
				var raw = localStorage.getItem(STORAGE_KEY);
				if (!raw) { return []; }
				var ids = JSON.parse(raw);
				return Array.isArray(ids) ? ids.filter(function (v) { return typeof v === 'number' && v > 0; }).slice(0, MAX_ITEMS) : [];
			} catch (e) { return []; }
		}

		function saveIds(ids) {
			try { localStorage.setItem(STORAGE_KEY, JSON.stringify(ids.slice(0, MAX_ITEMS))); } catch (e) {}
		}

		function toggle(productId) {
			productId = parseInt(productId, 10);
			if (!productId) { return false; }
			var ids = getIds();
			var index = ids.indexOf(productId);
			if (index !== -1) {
				ids.splice(index, 1);
				saveIds(ids);
				return false;
			}
			ids.push(productId);
			saveIds(ids);
			return true;
		}

		function isWished(productId) {
			return getIds().indexOf(parseInt(productId, 10)) !== -1;
		}

		/* Open wishlist sheet */
		var triggers = document.querySelectorAll('[data-gloskin-wishlist-open]');
		Array.prototype.forEach.call(triggers, function (trigger) {
			trigger.addEventListener('click', function () {
				overlay.open('wishlist');
				renderWishlistBody();
			});
		});

		/* Wishlist toggle on product cards */
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-gloskin-wishlist-toggle]');
			if (!btn) { return; }
			var productId = parseInt(btn.getAttribute('data-gloskin-wishlist-toggle'), 10);
			if (!productId) { return; }
			var active = toggle(productId);
			btn.classList.toggle('is-wished', active);
			btn.setAttribute('aria-pressed', active ? 'true' : 'false');
			updateBadges();
		});

		/* Sync toggle button states on load */
		function syncToggles() {
			var btns = document.querySelectorAll('[data-gloskin-wishlist-toggle]');
			Array.prototype.forEach.call(btns, function (btn) {
				var id = parseInt(btn.getAttribute('data-gloskin-wishlist-toggle'), 10);
				var active = isWished(id);
				btn.classList.toggle('is-wished', active);
				btn.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
			updateBadges();
		}

		function updateBadges() {
			var count = getIds().length;
			var badges = document.querySelectorAll('[data-gloskin-wishlist-count]');
			Array.prototype.forEach.call(badges, function (badge) {
				badge.textContent = count;
				badge.classList.toggle('is-active', count > 0);
			});
		}

		function renderWishlistBody() {
			var body = document.querySelector('[data-gloskin-wishlist-body]');
			if (!body) { return; }
			var ids = getIds();
			if (!ids.length) {
				body.innerHTML = '<div class="gloskin-ui1-wishlist-sheet__empty">' +
					'<p>Belum ada produk favorit.</p>' +
					'<a class="gloskin-ui1-text-link" href="' + escapeHtml(config.cartUrl ? config.cartUrl.replace(/\/cart\/$/, '/skincare/') : '/skincare/') + '">Lihat Skincare</a>' +
					'</div>';
				return;
			}
			body.innerHTML = '<div class="gloskin-ui1-search-overlay__loading"><span></span></div>';

			var url = (config.restUrl || '/wp-json/gloskin/v1/') + 'products/resolve?ids=' + ids.join(',');
			fetch(url, { headers: { 'X-WP-Nonce': config.restNonce || '' } })
				.then(function (res) { return res.json(); })
				.then(function (data) {
					var products = data.products || [];
					/* Remove stale IDs */
					var validIds = products.map(function (p) { return p.id; });
					var currentIds = getIds().filter(function (id) { return validIds.indexOf(id) !== -1; });
					saveIds(currentIds);

					if (!products.length) {
						body.innerHTML = '<div class="gloskin-ui1-wishlist-sheet__empty">' +
							'<p>Belum ada produk favorit.</p></div>';
						return;
					}
					var html = '<ul class="gloskin-ui1-wishlist-sheet__list">';
					for (var i = 0; i < products.length; i++) {
						var p = products[i];
						html += '<li class="gloskin-ui1-wishlist-sheet__item">';
						html += '<a class="gloskin-ui1-wishlist-sheet__item-link" href="' + escapeHtml(p.url) + '">';
						html += '<span class="gloskin-ui1-wishlist-sheet__item-name">' + escapeHtml(p.name) + '</span>';
						if (p.price_html) { html += '<span class="gloskin-ui1-wishlist-sheet__item-price">' + p.price_html + '</span>'; }
						html += '</a>';
						html += '<button class="gloskin-ui1-wishlist-sheet__item-remove" type="button" data-gloskin-wishlist-toggle="' + p.id + '" aria-label="Hapus ' + escapeHtml(p.name) + '">&times;</button>';
						html += '</li>';
					}
					html += '</ul>';
					body.innerHTML = html;
					updateBadges();
				})
				.catch(function () {
					body.innerHTML = '<p class="gloskin-ui1-wishlist-sheet__error">Tidak dapat memuat produk favorit.</p>';
				});
		}

		syncToggles();
	}

	/* -----------------------------------------------------------------
	 * Utility
	 * ----------------------------------------------------------------- */

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str || ''));
		return div.innerHTML;
	}

	/* -----------------------------------------------------------------
	 * Init
	 * ----------------------------------------------------------------- */

	function init() {
		initOverlayCloseButtons();
		initDrawer();
		initDisclosures();
		initSmartHeader();
		initSearch();
		initCart();
		initWishlist();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
