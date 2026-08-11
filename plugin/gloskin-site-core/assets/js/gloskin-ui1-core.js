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
	 * Top-level desktop nav: single liquid bubble that glides between the
	 * hovered/focused item and rests on the active one, replacing the old
	 * per-link ::before top rail. The whole top-level row (link + optional
	 * chevron) owns the interaction hit area, while the link remains the
	 * bubble geometry/foreground target. CSS owns the link's no-JS fallback.
	 *
	 * Movement is two-phase so the shape genuinely deforms while travelling
	 * instead of sliding as a fixed rectangle: it first bridges (stretches
	 * across) both the old and new item on a fast transition, then settles
	 * onto just the target on a slower spring-out one. Leaving with nothing
	 * to rest on collapses through a centered circle before shrinking to
	 * 0x0 rather than fading out at its last size.
	 * ----------------------------------------------------------------- */

	function initNavBubble() {
		var nav = document.querySelector('.gloskin-ui1-nav--desktop');
		var bubble = nav ? nav.querySelector('.gloskin-ui1-nav__bubble') : null;
		var list = nav ? nav.querySelector('.gloskin-ui1-nav__list') : null;
		if (!nav || !bubble || !list) { return; }

		var targets = Array.prototype.filter.call(list.children, function (item) {
			return item.classList.contains('gloskin-ui1-nav__item');
		}).map(function (item) {
			var row = item.querySelector(':scope > .gloskin-ui1-nav__row');
			var link = row ? row.querySelector(':scope > .gloskin-ui1-nav__link') : null;
			return row && link ? { row: row, link: link } : null;
		}).filter(Boolean);
		var links = targets.map(function (target) { return target.link; });

		var BRIDGE_MS = 170; /* matches .gloskin-ui1-nav__bubble's default (fast) transition duration */
		var DOT = 14; /* circle diameter for the collapse-to-nothing exit */
		var bubbled = null;
		var current = null; /* last painted rect, relative to nav */
		var settleTimer = null;

		function setBubbled(link) {
			if (bubbled && bubbled !== link) { bubbled.classList.remove('is-bubbled'); }
			bubbled = link;
			if (link) { link.classList.add('is-bubbled'); }
		}

		function rectFor(link) {
			var navRect = nav.getBoundingClientRect();
			var linkRect = link.getBoundingClientRect();
			return { left: linkRect.left - navRect.left, top: linkRect.top - navRect.top, width: linkRect.width, height: linkRect.height };
		}

		function paint(rect) {
			bubble.style.width = rect.width + 'px';
			bubble.style.height = rect.height + 'px';
			bubble.style.transform = 'translate(' + rect.left + 'px,' + rect.top + 'px)';
		}

		function clearSettle() {
			if (settleTimer) { clearTimeout(settleTimer); settleTimer = null; }
		}

		function moveTo(link) {
			clearSettle();
			if (!link) { hide(); return; }
			var target = rectFor(link);
			bubble.classList.add('is-visible');
			bubble.classList.remove('is-settling');
			if (current) {
				paint({
					left: Math.min(current.left, target.left),
					top: Math.min(current.top, target.top),
					width: Math.max(current.left + current.width, target.left + target.width) - Math.min(current.left, target.left),
					height: Math.max(current.height, target.height)
				});
				settleTimer = setTimeout(function () {
					bubble.classList.add('is-settling');
					paint(target);
					settleTimer = null;
				}, BRIDGE_MS);
			} else {
				paint(target);
			}
			current = target;
			setBubbled(link);
		}

		function hide() {
			clearSettle();
			setBubbled(null);
			if (!current) { bubble.classList.remove('is-visible'); return; }
			var cx = current.left + current.width / 2;
			var cy = current.top + current.height / 2;
			bubble.classList.remove('is-settling');
			paint({ left: cx - DOT / 2, top: cy - DOT / 2, width: DOT, height: DOT });
			settleTimer = setTimeout(function () {
				paint({ left: cx, top: cy, width: 0, height: 0 });
				bubble.classList.remove('is-visible');
				current = null;
				settleTimer = null;
			}, BRIDGE_MS);
		}

		function activeLink() {
			return links.filter(function (link) {
				return link.closest('.gloskin-ui1-nav__item').classList.contains('is-active');
			})[0] || null;
		}

		function restToActive() { moveTo(activeLink()); }

		targets.forEach(function (target) {
			target.row.addEventListener('mouseenter', function () { moveTo(target.link); });
			target.row.addEventListener('focusin', function () { moveTo(target.link); });
		});
		nav.addEventListener('mouseleave', restToActive);
		list.addEventListener('focusout', function (event) {
			if (!list.contains(event.relatedTarget)) { restToActive(); }
		});
		window.addEventListener('resize', function () {
			/* Layout may have reflowed; snap to the fresh rect directly
			 * instead of bridging from a now-stale cached one. */
			current = null;
			restToActive();
		});

		restToActive();
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

		/* Busy presentation only -- WooCommerce's own wc-add-to-cart.js owns
		 * the actual AJAX request/cart mutation. This just mirrors it as
		 * aria-busy for screen readers; the safety-net timeout guarantees the
		 * button never stays stuck busy if Woo's own request errors out
		 * (Woo has no dedicated body-level "add to cart failed" event to
		 * listen for, but its own `complete` handler always clears the
		 * `loading` class it applies, success or failure). */
		document.body.addEventListener('click', function (event) {
			var button = event.target.closest && event.target.closest('.ajax_add_to_cart');
			if (!button) { return; }
			button.setAttribute('aria-busy', 'true');
			window.setTimeout(function () {
				if (button.getAttribute('aria-busy') === 'true') { button.setAttribute('aria-busy', 'false'); }
			}, 12000);
		}, true);

		/* Listen for WooCommerce AJAX add-to-cart (jQuery event). Woo passes
		 * the source button as the third argument -- used only to clear its
		 * busy state, never to mutate cart state ourselves. The existing
		 * [data-gloskin-cart-count-sr] fragment (see
		 * Gloskin_Site_Core_WooCommerce_Adapter::cart_fragments()) carries
		 * aria-live in header.php, so this update is announced without a
		 * separate toast/notification system. */
		if (window.jQuery) {
			window.jQuery(document.body).on('added_to_cart', function (event, fragments, cartHash, $button) {
				if ($button && $button.length) { $button.attr('aria-busy', 'false'); }
				overlay.open('cart');
			});
		}
	}

	/* -----------------------------------------------------------------
	 * SP-003/SP-004 -- Woo-owned AJAX add-to-cart bridge.
	 *
	 * WooCommerce remains the sole cart/session/validation authority.
	 * This submits to Woo's own documented wc-ajax=add_to_cart endpoint
	 * (URL supplied server-side by WooCommerce_Adapter::add_to_cart_ajax_url(),
	 * WC_AJAX::get_endpoint('add_to_cart')) with the *entire* serialized
	 * form.cart, exactly like Woo's own wc-add-to-cart.js does for its
	 * loop buttons -- so variation_id, attribute selections, quantity, nonce fields
	 * Woo itself rendered travel unmodified. No custom Gloskin cart
	 * mutation endpoint exists anywhere in this bridge.
	 *
	 * On success it dispatches the same `added_to_cart` jQuery event
	 * Woo's own script fires, so the existing initCart() listener (added
	 * long before this task) opens the cart sheet -- this never
	 * duplicates that logic. On any failure it falls back to a genuine
	 * native form submit, so Woo's own server-rendered validation/stock
	 * notices take over exactly as they would with JS disabled.
	 * ----------------------------------------------------------------- */

	function ajaxAddToCart(form, button) {
		var config = window.gloskinData || {};
		if (!config.addToCartAjaxUrl || typeof fetch === 'undefined' || typeof FormData === 'undefined') {
			return false;
		}

		var formData = new FormData(form);
		var productIdField = form.querySelector('input[name="add-to-cart"]');
		if (productIdField && !formData.get('product_id')) {
			formData.set('product_id', productIdField.value);
		}

		if (button) { button.setAttribute('aria-busy', 'true'); }

		fetch(config.addToCartAjaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
			.then(function (res) {
				if (!res.ok) { throw new Error('add_to_cart_http'); }
				return res.json();
			})
			.then(function (response) {
				if (!response || response.error) { throw new Error('add_to_cart_error'); }
				if (button) { button.setAttribute('aria-busy', 'false'); }
				if (window.jQuery) {
					window.jQuery(document.body).trigger('added_to_cart', [
						response.fragments,
						response.cart_hash,
						button ? window.jQuery(button) : window.jQuery()
					]);
				} else {
					overlay.open('cart');
				}
			})
			.catch(function () {
				/* Restore button state and fall back to a real native POST --
				 * never invent Gloskin-only error copy here; Woo's own
				 * server-rendered notices are the authoritative error UX. */
				if (button) { button.removeAttribute('aria-busy'); }
				form.submit();
			});

		return true;
	}

	/* -----------------------------------------------------------------
	 * SP-003 -- Single product page: progressive AJAX add-to-cart for
	 * both the simple form and the variable form's already-selected
	 * variation. The page itself owns the one native variation form; this
	 * never opens the Quick Add modal here and never guesses a variation.
	 * ----------------------------------------------------------------- */

	function initSingleProductAjax() {
		if (!document.body.classList.contains('single-product')) { return; }
		var form = document.querySelector('div.product form.cart');
		if (!form) { return; }

		form.addEventListener('submit', function (event) {
			var button = form.querySelector('.single_add_to_cart_button');
			if (!button || button.disabled || button.classList.contains('disabled')) {
				/* Woo's own variation script owns this disabled state until a
				 * valid, purchasable, in-stock variation is selected. */
				return;
			}
			if (form.classList.contains('variations_form')) {
				var variationId = form.querySelector('input.variation_id, input[name="variation_id"]');
				if (!variationId || !parseInt(variationId.value, 10)) {
					/* No Woo-selected variation yet -- never AJAX-add the
					 * variable parent blindly; let native submit/validation run. */
					return;
				}
			}
			event.preventDefault();
			ajaxAddToCart(form, button);
		});
	}

	/* -----------------------------------------------------------------
	 * SP-004 -- Gloskin Quick Add modal for variable catalog cards.
	 *
	 * Lazy-loads the minimum purchasing projection only when opened (never
	 * preloaded per-card), renders Woo's own native variations_form markup
	 * captured server-side, binds Woo's own wc_variation_form() plugin so
	 * selection/availability/price logic is 100% Woo's, then reuses the
	 * exact same ajaxAddToCart() bridge as the single-product page.
	 * ----------------------------------------------------------------- */

	function initQuickAdd() {
		var config = window.gloskinData || {};
		if (!config.woo) { return; }
		var modal = document.querySelector('[data-gloskin-overlay="quickadd"]');
		var body = modal ? modal.querySelector('[data-gloskin-quickadd-body]') : null;
		if (!modal || !body) { return; }

		var cache = {};
		var currentId = null;

		function renderLoading() {
			body.innerHTML = '<div class="gloskin-ui1-quickadd__loading"><span>' + escapeHtml('Memuat produk…') + '</span></div>';
		}

		function renderError() {
			body.innerHTML = '<p class="gloskin-ui1-quickadd__error">' + escapeHtml('Produk belum dapat dimuat. Silakan buka halaman produk untuk memilih varian.') + '</p>';
		}

		function bindForm(form) {
			/* Use Woo's own variation-form plugin -- never a Gloskin variation
			 * resolver. If the script has not finished registering yet (very
			 * unlikely by the time this async render runs), the form still
			 * degrades to native select/submit behavior, never a crash. */
			if (window.jQuery && typeof window.jQuery.fn.wc_variation_form === 'function') {
				window.jQuery(form).wc_variation_form();
			}
			form.addEventListener('submit', function (event) {
				var button = form.querySelector('.single_add_to_cart_button');
				if (!button || button.disabled || button.classList.contains('disabled')) { return; }
				if (form.classList.contains('variations_form')) {
					var variationId = form.querySelector('input.variation_id, input[name="variation_id"]');
					if (!variationId || !parseInt(variationId.value, 10)) { return; }
				}
				event.preventDefault();
				ajaxAddToCart(form, button);
				/* Close the quick-add modal as the cart sheet takes over --
				 * one overlay owner at a time, via the existing controller. */
				overlay.close();
			});
		}

		function render(data) {
			var html = '<div class="gloskin-ui1-quickadd__product">';
			html += data.image_html || '';
			html += '<div><strong>' + escapeHtml(data.name || '') + '</strong>';
			if (data.price_html) { html += '<div class="gloskin-ui1-product-price">' + data.price_html + '</div>'; }
			html += '</div></div>';
			html += '<div class="gloskin-ui1-quickadd__form">' + (data.form_html || '') + '</div>';
			body.innerHTML = html;
			var form = body.querySelector('form.cart');
			if (form) { bindForm(form); }
		}

		function open(productId) {
			currentId = productId;
			overlay.open('quickadd');
			if (cache[productId]) { render(cache[productId]); return; }
			renderLoading();
			var url = (config.restUrl || '/wp-json/gloskin/v1/') + 'products/quick-add?id=' + encodeURIComponent(productId);
			fetch(url, { headers: { 'X-WP-Nonce': config.restNonce || '' } })
				.then(function (res) { return res.json(); })
				.then(function (data) {
					if (!data || !data.found) { throw new Error('quickadd_not_found'); }
					cache[productId] = data;
					if (currentId === productId) { render(data); }
				})
				.catch(function () {
					if (currentId === productId) { renderError(); }
				});
		}

		document.addEventListener('click', function (event) {
			var trigger = event.target.closest && event.target.closest('[data-gloskin-quickadd-open]');
			if (!trigger) { return; }
			var productId = trigger.getAttribute('data-gloskin-quickadd-product');
			if (!productId) { return; }
			event.preventDefault();
			open(productId);
		});
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
		initNavBubble();
		initSmartHeader();
		initCompactSticky();
		initSearch();
		initAuth();
		initCart();
		initSingleProductAjax();
		initQuickAdd();
		initWishlist();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
