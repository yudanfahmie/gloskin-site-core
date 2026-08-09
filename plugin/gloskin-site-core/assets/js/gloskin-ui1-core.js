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

	function holdStickyNav() {
		document.dispatchEvent(new CustomEvent('gloskin:sticky-nav-hold'));
	}


	function emptyStateIcon(kind) {
		var paths = {
			search: '<circle cx="24" cy="24" r="10"/><path d="m31.5 31.5 8 8"/><path d="M18 24h12"/>',
			wishlist: '<path d="M28 43S12 33 12 21.5C12 15.7 16.4 12 21 12c3.1 0 5.4 1.6 7 3.7 1.6-2.1 3.9-3.7 7-3.7 4.6 0 9 3.7 9 9.5C44 33 28 43 28 43Z"/>',
			generic: '<circle cx="28" cy="28" r="16"/><path d="M22 28h12M28 22v12"/>'
		};
		var path = paths[kind] || paths.generic;
		return '<svg viewBox="0 0 56 56" width="56" height="56" fill="none" aria-hidden="true" focusable="false"><g stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' + path + '</g><circle class="gloskin-ui1-empty-state__accent" cx="44" cy="13" r="3" fill="currentColor" stroke="none"/></svg>';
	}

	function emptyStateMarkup(kind, title, copy, actionLabel, actionUrl) {
		var html = '<div class="gloskin-ui1-empty-state gloskin-ui1-empty-state--' + escapeHtml(kind || 'generic') + '">';
		html += '<span class="gloskin-ui1-empty-state__visual">' + emptyStateIcon(kind) + '</span>';
		html += '<strong class="gloskin-ui1-empty-state__title">' + escapeHtml(title) + '</strong>';
		if (copy) { html += '<p class="gloskin-ui1-empty-state__copy">' + escapeHtml(copy) + '</p>'; }
		if (actionLabel && actionUrl) {
			html += '<a class="gloskin-ui1-text-link gloskin-ui1-empty-state__action" href="' + escapeHtml(actionUrl) + '">' + escapeHtml(actionLabel) + '</a>';
		}
		return html + '</div>';
	}

	/* -----------------------------------------------------------------
	 * Unified overlay system — one overlay may own focus at a time
	 * ----------------------------------------------------------------- */

	var overlay = (function () {
		var current = null;
		var previousFocus = null;
		var pending = {}; /* id -> { timer, handler, el } -- in-flight close finalizations */

		function find(id) {
			return document.querySelector('[data-gloskin-overlay="' + id + '"]');
		}

		/* A trigger can exist twice (full-size header row + compact sticky
		 * toolbar); keep aria-expanded in sync on every copy, not just the
		 * first one found, regardless of which is currently visible. */
		function setTriggersExpanded(id, expanded) {
			var triggers = document.querySelectorAll('[data-gloskin-' + id + '-open]');
			Array.prototype.forEach.call(triggers, function (trigger) {
				trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			});
		}

		function reducedMotion() {
			return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		}

		function cancelPending(id) {
			var p = pending[id];
			if (!p) { return; }
			if (p.timer) { clearTimeout(p.timer); }
			if (p.handler && p.el) { p.el.removeEventListener('transitionend', p.handler); }
			delete pending[id];
		}

		function open(id) {
			if (current === id) { return; }
			if (current) { close(); }
			var el = find(id);
			if (!el) { return; }
			/* A previous close() on this same overlay may still have a
			 * pending finalize (transitionend/timeout) queued; cancel it so
			 * a rapid close -> open doesn't get its hidden state clobbered
			 * out from under the reopened overlay. */
			cancelPending(id);
			previousFocus = document.activeElement;
			current = id;
			el.hidden = false;
			/* Force a layout so the browser commits the closed (opacity:0)
			 * frame before flipping to the open state on the next frame --
			 * otherwise both attribute changes collapse into a single frame
			 * and the CSS transition never runs. */
			void el.offsetWidth;
			window.requestAnimationFrame(function () {
				el.setAttribute('aria-hidden', 'false');
			});
			document.documentElement.classList.add('gloskin-ui1-overlay-open');
			holdStickyNav();
			setTriggersExpanded(id, true);
			var nodes = focusable(el);
			if (nodes.length) { nodes[0].focus(); }
		}

		function close() {
			if (!current) { return; }
			var id = current;
			var el = find(id);
			setTriggersExpanded(id, false);
			document.documentElement.classList.remove('gloskin-ui1-overlay-open');
			current = null;

			if (el) {
				el.setAttribute('aria-hidden', 'true');
				cancelPending(id);
				var finalize = function () {
					el.hidden = true;
					cancelPending(id);
				};
				if (reducedMotion()) {
					finalize();
				} else {
					var handler = function (event) {
						if (event.target !== el) { return; }
						finalize();
					};
					var timer = setTimeout(finalize, 320);
					el.addEventListener('transitionend', handler);
					pending[id] = { timer: timer, handler: handler, el: el };
				}
			}

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
			holdStickyNav();
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

		/* Logged-out Account reuses the same overlay state owner as Search/Cart/Wishlist. */
		var authFromDrawer = drawer.querySelector('[data-gloskin-auth-open-from-drawer]');
		if (authFromDrawer) {
			authFromDrawer.addEventListener('click', function (event) {
				event.preventDefault();
				closeDrawer();
				setTimeout(function () { overlay.open('auth'); }, 80);
			});
		}

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
				if (!expanded) { holdStickyNav(); }
			});
		});
	}

	/* -----------------------------------------------------------------
	 * Smart sticky navigation row
	 * ----------------------------------------------------------------- */

	function initSmartHeader() {
		var header = document.querySelector('.gloskin-ui1-header');
		var navRow = document.querySelector('.gloskin-ui1-header__nav-row');
		if (!header || !navRow) { return; }

		var previousY = Math.max(window.scrollY || 0, 0);
		var downDistance = 0;
		var scheduled = false;
		var hideThreshold = 10;

		function topGuard() {
			return Math.max(header.offsetHeight + navRow.offsetHeight, 0);
		}

		function interactionActive() {
			if (navRow.contains(document.activeElement)) { return true; }
			if (navRow.querySelector('[data-gloskin-submenu-toggle][aria-expanded="true"]')) { return true; }
			if (document.documentElement.classList.contains('gloskin-ui1-drawer-open')) { return true; }
			if (document.documentElement.classList.contains('gloskin-ui1-overlay-open')) { return true; }
			return false;
		}

		function showNav() {
			navRow.classList.remove('is-hidden');
			downDistance = 0;
		}
		function hideNav() { navRow.classList.add('is-hidden'); }

		function updateNav() {
			var currentY = Math.max(window.scrollY || 0, 0);
			var delta = currentY - previousY;
			previousY = currentY;
			if (currentY <= topGuard()) { showNav(); scheduled = false; return; }
			if (interactionActive()) { showNav(); scheduled = false; return; }
			if (delta < 0) { showNav(); scheduled = false; return; }
			if (delta > 0) {
				downDistance += delta;
				if (downDistance >= hideThreshold) { hideNav(); downDistance = 0; }
			}
			scheduled = false;
		}

		window.addEventListener('scroll', function () {
			if (scheduled) { return; }
			scheduled = true;
			window.requestAnimationFrame(updateNav);
		}, { passive: true });
		navRow.addEventListener('focusin', showNav);
		document.addEventListener('gloskin:sticky-nav-hold', showNav);
	}

	/* -----------------------------------------------------------------
	 * Compact branded sticky-nav state -- once the full brand/utilities
	 * row has fully scrolled out of view, the nav row grows a small
	 * logo + compact utility cluster alongside the still-centered nav.
	 * Reuses the exact same search/account/wishlist/cart triggers and
	 * overlay system (no duplicated Woo state or overlay handlers).
	 * ----------------------------------------------------------------- */

	function initCompactSticky() {
		var header = document.querySelector('.gloskin-ui1-header');
		var navRow = document.querySelector('.gloskin-ui1-header__nav-row');
		var compactBrand = document.querySelector('.gloskin-ui1-compact-brand');
		var compactZone = document.querySelector('.gloskin-ui1-header__zone--compact');
		if (!header || !navRow || typeof IntersectionObserver === 'undefined') { return; }

		function setCompact(active) {
			navRow.classList.toggle('is-compact-sticky', active);
			/* inert keeps the collapsed (opacity:0, zero max-width) copy out of
			 * tab order and away from screen readers until it is actually the
			 * visible one -- mirrors the CSS visibility state exactly. */
			if (compactBrand) { compactBrand.inert = !active; }
			if (compactZone) { compactZone.inert = !active; }
		}

		var observer = new IntersectionObserver(function (entries) {
			var entry = entries[entries.length - 1];
			setCompact(!entry.isIntersecting);
		}, { threshold: 0 });
		observer.observe(header);
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


		function clearResults() {
			clearTimeout(debounceTimer);
			if (abortController) { abortController.abort(); abortController = null; }
			resultsContainer.innerHTML = '';
		}

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				input.value = '';
				updateClear();
				clearResults();
				input.focus();
			});
		}

		input.addEventListener('input', function () {
			updateClear();
			var query = input.value.trim();
			if (query.length < 2) {
				clearResults();
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
			resultsContainer.innerHTML = '<div class="gloskin-ui1-search-overlay__loading" aria-label="Memuat hasil"><span aria-hidden="true"></span></div>';

			var url = (config.restUrl || '/wp-json/gloskin/v1/') + 'search?q=' + encodeURIComponent(query);
			var fetchOpts = { headers: { 'X-WP-Nonce': config.restNonce || '' } };
			if (abortController) { fetchOpts.signal = abortController.signal; }

			fetch(url, fetchOpts)
				.then(function (res) {
					if (!res.ok) { throw new Error('search'); }
					return res.json();
				})
				.then(function (data) { renderResults(data.groups || []); })
				.catch(function (err) {
					if (err.name === 'AbortError') { return; }
					var fallback = config.searchFallback ? config.searchFallback + encodeURIComponent(query) : '';
					resultsContainer.innerHTML = emptyStateMarkup(
						'search',
						'Pencarian belum dapat dimuat',
						'Silakan coba kembali atau lanjutkan melalui pencarian biasa.',
						fallback ? 'Buka pencarian biasa' : '',
						fallback
					);
				});
		}

		function renderResults(groups) {
			if (!groups.length) {
				resultsContainer.innerHTML = emptyStateMarkup(
					'search',
					'Tidak menemukan hasil yang sesuai',
					'Coba kata lain atau gunakan istilah yang lebih singkat.'
				);
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
	 * Quick Account auth overlay — native Woo forms, normal POST handling
	 * ----------------------------------------------------------------- */

	function initAuth() {
		var auth = document.querySelector('[data-gloskin-overlay="auth"]');
		if (!auth) { return; }
		/* Server rendering already marks the Account trigger(s) with
		 * data-gloskin-auth-open (see Gloskin_Site_Core_WooCommerce_Adapter::
		 * should_render_quick_auth() and header.php) whenever this overlay
		 * exists, so binding reads that canonical intent instead of
		 * re-discovering and mutating every Account anchor on the page. */
		var triggers = document.querySelectorAll('[data-gloskin-auth-open]');
		var forms = auth.querySelector('[data-gloskin-auth-forms]');
		var tabs = auth.querySelectorAll('[data-gloskin-auth-tab]');

		Array.prototype.forEach.call(triggers, function (trigger) {
			trigger.addEventListener('click', function (event) {
				event.preventDefault();
				overlay.open('auth');
				setTimeout(function () {
					var username = auth.querySelector('#username');
					if (username) { username.focus(); }
				}, 60);
			});
		});

		if (!forms || !tabs.length) { return; }
		var loginColumn = forms.querySelector('#customer_login .u-column1');
		var registerColumn = forms.querySelector('#customer_login .u-column2');
		if (!loginColumn || !registerColumn) { return; }
		forms.classList.add('is-enhanced');

		function show(which, focusField) {
			var login = which !== 'register';
			loginColumn.hidden = !login;
			registerColumn.hidden = login;
			Array.prototype.forEach.call(tabs, function (tab) {
				var active = tab.getAttribute('data-gloskin-auth-tab') === which;
				tab.classList.toggle('is-active', active);
				tab.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
			if (focusField) {
				var field = login ? loginColumn.querySelector('#username') : registerColumn.querySelector('#reg_email');
				if (field) { field.focus(); }
			}
		}

		show('login', false);
		Array.prototype.forEach.call(tabs, function (tab) {
			tab.addEventListener('click', function () { show(tab.getAttribute('data-gloskin-auth-tab'), true); });
		});
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

		/* Swap the accessible label between "add" and "remove" phrasing so
		 * screen readers announce the action the button will take next,
		 * not a static label frozen at page load. */
		function applyState(btn, active) {
			btn.classList.toggle('is-wished', active);
			btn.setAttribute('aria-pressed', active ? 'true' : 'false');
			var addLabel = btn.getAttribute('data-label-add');
			var removeLabel = btn.getAttribute('data-label-remove');
			if (addLabel && removeLabel) {
				btn.setAttribute('aria-label', active ? removeLabel : addLabel);
			}
		}

		/* Wishlist toggle on product cards */
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-gloskin-wishlist-toggle]');
			if (!btn) { return; }
			var productId = parseInt(btn.getAttribute('data-gloskin-wishlist-toggle'), 10);
			if (!productId) { return; }
			var active = toggle(productId);
			applyState(btn, active);
			updateBadges();
		});

		/* Sync toggle button states on load */
		function syncToggles() {
			var btns = document.querySelectorAll('[data-gloskin-wishlist-toggle]');
			Array.prototype.forEach.call(btns, function (btn) {
				var id = parseInt(btn.getAttribute('data-gloskin-wishlist-toggle'), 10);
				applyState(btn, isWished(id));
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
				body.innerHTML = emptyStateMarkup(
					'wishlist',
					'Belum ada produk favorit',
					'Produk yang Anda simpan akan muncul di sini agar mudah ditemukan kembali.',
					'Lihat Skincare',
					config.cartUrl ? config.cartUrl.replace(/\/cart\/$/, '/skincare/') : '/skincare/'
				);
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
						body.innerHTML = emptyStateMarkup(
							'wishlist',
							'Belum ada produk favorit',
							'Produk yang Anda simpan akan muncul di sini agar mudah ditemukan kembali.',
							'Lihat Skincare',
							config.cartUrl ? config.cartUrl.replace(/\/cart\/$/, '/skincare/') : '/skincare/'
						);
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
					body.innerHTML = emptyStateMarkup(
						'wishlist',
						'Favorit belum dapat dimuat',
						'Produk yang tersimpan tetap aman di perangkat ini. Silakan coba lagi.',
						'Lihat Skincare',
						config.cartUrl ? config.cartUrl.replace(/\/cart\/$/, '/skincare/') : '/skincare/'
					);
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
		initCompactSticky();
		initSearch();
		initAuth();
		initCart();
		initWishlist();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
