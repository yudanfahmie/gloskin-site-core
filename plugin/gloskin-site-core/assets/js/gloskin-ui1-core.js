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

	/* Public Gloskin REST projections are deliberately guest-readable. Do not
	 * attach wp_rest nonces to these GETs: an expired/stale nonce turns a public
	 * route into a REST-cookie authentication failure before permission_callback
	 * can allow the request. Credentials stay same-origin for normal browser
	 * semantics, but these projections never depend on authenticated identity. */
	function publicRestGetOptions() {
		return { method: 'GET', credentials: 'same-origin' };
	}

	/* -----------------------------------------------------------------
	 * Shared confirmed-success feedback. Presentation only: callers invoke
	 * this after their existing state owner has completed a real mutation
	 * and reflected that state. No cart/wishlist count is written here.
	 * ----------------------------------------------------------------- */

	var SUCCESS_SOUND_URI = 'data:audio/wav;base64,UklGRuQDAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YcADAACAgICAgIB/f35+f3+AgIGAgICAf39+fn5+f4GCg4OCf317ent+goWGhYJ/fHp6fH6Bg4ODgoGAf359fHt7fYGEh4iGgXt2dHZ7gYiLi4eBenZ1d3yBhIaGhIOBf358enh4e3+FioyKhX11cHF2f4iOj4uEfHVzdHl/hIaHhoSCgH59e3l4en2DiIyLh4B3cnB0e4SMj42Hf3dzc3d9goaHhoSCgH99e3l4eXyBhouMiYN6c3ByeIGKjo6Jgnp0c3V7gIWHh4WDgYB+fHp4eHp/hIqMi4V9dnFxdX6HjY+MhXx2c3R5foOGh4aEgoB/fXt5eHl9goiMjIiAeHJwc3uEi4+NiH94dHN3fIKFh4aEgoF/fXt5eHl7gIaLjIqDe3RwcXeAiY6PioJ6dXN1eoCEh4eFg4GAfnx6eXh6foSJjIuGfnZxcHV9ho2PjIV9dnN0eH6DhoeGhIKAf317eXh5fYKHi4yIgXlycHN6g4uPjoiAeXRzdnyBhYeGhYOBf358enh5e4CFioyKhHx0cHF3f4iOj4uDe3VzdXp/hIaHhYOBgH58enl4en6DiYyLhn93cXB0fIWMj42GfndzdHh9goaHhoSCgH99e3l4eXyBh4uMiYJ6c3ByeYKKj46JgXl0c3Z7gYWHhoWDgX9+fHp4eHt/hYqMioV9dXBxdn+Ijo+LhHx1c3R5f4SGh4aEgoB+fXt5eHp9g4iMi4eAd3JwdHuEjI+Nh393c3N3fYKGh4aEgoB/fXt5eHl8gYaLjImDenNwcniBio6OiYJ6dHN1e4CFh4eFg4GAfnx6eHh6f4SKjIuFfXZxcXV+h42PjIV8dnN0eX6DhoeGhIKAf317eXh5fYKIjIyIgHhycHN7hIuPjYh/eHRzd3yChYeGhIKBf317eXh5e4CGioyJg3t0cHJ4gImOjoqCe3V0dnqAhIaGhYOBgH59e3l5e36DiIqJhX54c3N3fYWKjIqEfnh2dnp+goWFhIOBgH9+fHt6e32BhYiIhoF7dnR2e4KHiomFgHt4d3l9gYOEhIOBgH9+fXx7e32Ag4aHhoJ9eXd3en+EiIiGgn16eXp8f4KDg4OBgIB/fn18fH1/gYSFhYN/fHl5en6ChYaFgn98ent8f4GCgoKBgIB/f359fX1+gIKDhIOAfnt6e32Ag4SEgoB+fHx9foCBgYGBgIB/f39+fn5+f4GCgoKBf319fX5/gYKCgYB/fn5+f3+AgICAgICAf39/f39/f4CAgYCAgH9/f39/gICAgIB/f39/f3+AgIA=';
	var successAudio = null;
	var lastSuccessSoundAt = 0;
	var SUCCESS_SOUND_COOLDOWN_MS = 280;

	function feedbackReducedMotion(root) {
		return !!(root && root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function visibleFeedbackTargets(type, root) {
		var selector = type === 'cart'
			? '[data-gloskin-cart-open]'
			: '[data-gloskin-wishlist-open], [data-gloskin-wishlist-open-from-drawer]';
		return Array.prototype.filter.call(root.document.querySelectorAll(selector), function (node) {
			var rect = typeof node.getBoundingClientRect === 'function' ? node.getBoundingClientRect() : { width: 0, height: 0 };
			if (!rect.width || !rect.height) { return false; }
			if (!root.getComputedStyle) { return true; }
			var style = root.getComputedStyle(node);
			return style.display !== 'none' && style.visibility !== 'hidden';
		});
	}

	function successFeedback(type, runtime) {
		var root = runtimeWindow(runtime);
		if ((type !== 'cart' && type !== 'wishlist') || !root || !root.document) { return false; }

		if (!feedbackReducedMotion(root)) {
			visibleFeedbackTargets(type, root).forEach(function (node) {
				node.classList.remove('is-success-pulse');
				void node.offsetWidth;
				node.classList.add('is-success-pulse');
				root.setTimeout(function () { node.classList.remove('is-success-pulse'); }, 460);
			});
		}

		if (root.document.visibilityState !== 'visible' || typeof root.Audio !== 'function') { return true; }
		var now = Date.now();
		if (now - lastSuccessSoundAt < SUCCESS_SOUND_COOLDOWN_MS) { return true; }

		try {
			if (!successAudio) {
				successAudio = new root.Audio(SUCCESS_SOUND_URI);
				successAudio.preload = 'auto';
				successAudio.volume = 0.16;
			}
			lastSuccessSoundAt = now;
			successAudio.currentTime = 0;
			var playback = successAudio.play();
			if (playback && typeof playback.catch === 'function') { playback.catch(function () {}); }
		} catch (e) {}
		return true;
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
			/* Focus only meaningful dialog controls. Backdrops are intentionally
			 * outside role=dialog and must never win initial keyboard focus. */
			var panel = el.querySelector('[role="dialog"]');
			var nodes = focusable(panel || el);
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
	 * Top-level desktop nav bubble. Geometry snaps while invisible to the
	 * hovered/focused link's final box; the only visible entrance/exit is a
	 * center-origin scale + opacity transition. No translate/left/top/size
	 * property is animated, so there is no directional or diagonal travel.
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
		var bubbled = null;

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

		function place(link, force) {
			if (!link) { hide(); return; }
			if (!force && bubbled === link && bubble.classList.contains('is-visible')) { return; }
			var target = rectFor(link);
			bubble.classList.add('is-repositioning');
			bubble.classList.remove('is-visible');
			bubble.style.left = target.left + 'px';
			bubble.style.top = target.top + 'px';
			bubble.style.width = target.width + 'px';
			bubble.style.height = target.height + 'px';
			void bubble.offsetWidth;
			bubble.classList.remove('is-repositioning');
			bubble.classList.add('is-visible');
			setBubbled(link);
		}

		function hide() {
			bubble.classList.remove('is-visible');
			setBubbled(null);
		}

		function activeLink() {
			return links.filter(function (link) {
				return link.closest('.gloskin-ui1-nav__item').classList.contains('is-active');
			})[0] || null;
		}

		function restToActive() { place(activeLink(), false); }

		targets.forEach(function (target) {
			target.row.addEventListener('mouseenter', function () { place(target.link, false); });
			target.row.addEventListener('focusin', function () { place(target.link, false); });
		});
		nav.addEventListener('mouseleave', restToActive);
		list.addEventListener('focusout', function (event) {
			if (!list.contains(event.relatedTarget)) { restToActive(); }
		});
		window.addEventListener('resize', function () {
			var link = bubbled || activeLink();
			if (link) { place(link, true); }
		});

		restToActive();
	}

	/* -----------------------------------------------------------------
	 * Smart sticky navigation row
	 *
	 * One state owner, two possible target surfaces depending on which
	 * Header Type server-rendered: Header 1's separate sticky
	 * .gloskin-ui1-header__nav-row (below the non-sticky brand/utilities
	 * row), or Header 2's single self-contained sticky
	 * [data-gloskin-header="header-2"] row, which has no separate row to
	 * target -- it is its own surface. resolveSmartHeaderSurface() is the
	 * one place that decides which; everything below reads `surface`
	 * generically and never re-branches on the header variant itself.
	 * ----------------------------------------------------------------- */

	function resolveSmartHeaderSurface() {
		var navRow = document.querySelector('.gloskin-ui1-header__nav-row');
		if (navRow) {
			return { surface: navRow, header: document.querySelector('.gloskin-ui1-header'), selfContained: false };
		}
		var split = document.querySelector('[data-gloskin-header="header-2"]');
		if (split) {
			return { surface: split, header: split, selfContained: true };
		}
		return null;
	}

	function initSmartHeader() {
		var target = resolveSmartHeaderSurface();
		if (!target) { return; }
		var surface = target.surface;
		var header = target.header;
		var selfContained = target.selfContained;

		var previousY = Math.max(window.scrollY || 0, 0);
		var downDistance = 0;
		var scheduled = false;
		var hideThreshold = 10;
		/* Captured once, before any is-compact-sticky state exists. Header
		 * 2's compact state shrinks its own row height (see updateCompact()
		 * below); reading surface.offsetHeight live here instead would make
		 * topGuard() collapse the instant compact-sticky engages, giving it
		 * no stable "still effectively at the top" window at all and
		 * triggering hide on the very next tick. Header 1's navRow height
		 * never changes between its own compact/non-compact state, so this
		 * is a no-op difference for it. */
		var naturalSurfaceHeight = surface.offsetHeight;

		/* Header 1: must scroll past the non-sticky brand row *and* the nav
		 * row's own height before hide/reveal engages, otherwise it would
		 * hide while still naturally in view at the top of the page. Header
		 * 2 has no separate brand row -- it is sticky from y=0 -- so only
		 * its own natural height guards the same "still effectively at the
		 * top" case. */
		function topGuard() {
			return selfContained ? naturalSurfaceHeight : Math.max(header.offsetHeight + surface.offsetHeight, 0);
		}

		function interactionActive() {
			if (surface.contains(document.activeElement)) { return true; }
			if (surface.querySelector('[data-gloskin-submenu-toggle][aria-expanded="true"]')) { return true; }
			if (document.documentElement.classList.contains('gloskin-ui1-drawer-open')) { return true; }
			if (document.documentElement.classList.contains('gloskin-ui1-overlay-open')) { return true; }
			return false;
		}

		function showNav() {
			surface.classList.remove('is-hidden');
			downDistance = 0;
		}
		function hideNav() { surface.classList.add('is-hidden'); }

		/* Header 2 owns its compact-sticky state here too (same rAF-scheduled
		 * scroll tick, no second listener) since it has no separate row for
		 * initCompactSticky()'s IntersectionObserver to watch scroll past --
		 * see that function below, which stays exclusively Header 1's. */
		function updateCompact(currentY) {
			if (!selfContained) { return; }
			surface.classList.toggle('is-compact-sticky', currentY > naturalSurfaceHeight);
		}

		function updateNav() {
			var currentY = Math.max(window.scrollY || 0, 0);
			var delta = currentY - previousY;
			previousY = currentY;
			updateCompact(currentY);
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
		surface.addEventListener('focusin', showNav);
		document.addEventListener('gloskin:sticky-nav-hold', showNav);
	}

	/* -----------------------------------------------------------------
	 * Compact branded sticky-nav state -- Header 1 only. Once the full
	 * brand/utilities row has fully scrolled out of view, the nav row
	 * grows a small logo + compact utility cluster alongside the still-
	 * centered nav. Reuses the exact same search/account/wishlist/cart
	 * triggers and overlay system (no duplicated Woo state or overlay
	 * handlers). Header 2 has no separate row for this IntersectionObserver
	 * to watch scroll past -- its own compact-sticky state is owned by
	 * initSmartHeader() above instead, on the same scroll tick.
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
			var fetchOpts = publicRestGetOptions();
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

		/* Woo's confirmed added_to_cart lifecycle remains the only success
		 * signal. Fragments/state listeners run on this event first; the rAF
		 * queues decorative feedback after that real-state reflection without
		 * ever fabricating a cart count. */
		if (window.jQuery) {
			window.jQuery(document.body).on('added_to_cart', function (event, fragments, cartHash, $button) {
				if ($button && $button.length) { $button.attr('aria-busy', 'false'); }
				overlay.open('cart');
				window.requestAnimationFrame(function () { successFeedback('cart'); });
			});
		}

		/* Cart-row pending presentation only -- WooCommerce's own
		 * wc-add-to-cart.js (add-to-cart.js) owns the actual delegated
		 * .remove_from_cart_button AJAX request/mutation; this never
		 * intercepts or duplicates it. Marking the row before Woo's own
		 * handler resolves gives an immediate skeleton/pending row instead
		 * of a dead click. Woo's own fragment replacement (which swaps out
		 * .gloskin-ui1-cart-sheet__body entirely on a confirmed
		 * removed_from_cart) naturally clears this row along with it; the
		 * removed_from_cart listener below is only a defensive cleanup for
		 * any row fragments did not happen to replace. On a real AJAX/
		 * network failure Woo's own fallback navigates to the remove link's
		 * href, so no recovery timeout is needed here either. */
		document.body.addEventListener('click', function (event) {
			var removeButton = event.target.closest && event.target.closest('.remove_from_cart_button');
			if (!removeButton) { return; }
			var row = removeButton.closest('.gloskin-ui1-cart-sheet__item');
			if (!row) { return; }
			row.classList.add('is-removing');
			row.setAttribute('aria-busy', 'true');
		}, true);

		if (window.jQuery) {
			window.jQuery(document.body).on('removed_from_cart', function () {
				var pending = document.querySelectorAll('.gloskin-ui1-cart-sheet__item.is-removing');
				Array.prototype.forEach.call(pending, function (row) {
					row.classList.remove('is-removing');
					row.removeAttribute('aria-busy');
				});
			});
		}
	}

	/* -----------------------------------------------------------------
	 * SP-003/SP-004 -- Woo-owned AJAX add-to-cart bridge.
	 *
	 * WooCommerce remains the sole cart/session/validation authority.
	 * This submits to Woo's own documented wc-ajax=add_to_cart endpoint
	 * (URL supplied server-side by WooCommerce_Adapter::add_to_cart_ajax_url(),
	 * WC_AJAX::get_endpoint('add_to_cart')) using the browser's native
	 * FormData(form) successful-control serialization. Gloskin appends only
	 * the activated submitter (which FormData(form) intentionally omits) and
	 * normalizes Woo's required product_id. No custom cart mutation endpoint,
	 * fragment parser, or variation resolver exists here.
	 *
	 * AJAX is progressive enhancement only. It runs only while Woo's native
	 * jQuery `added_to_cart` + cart-fragment bridge is available; otherwise
	 * the real Woo form/link proceeds natively without interception. Once a
	 * mutation POST is dispatched, failures never replay that mutation: Woo's
	 * fragment runtime may only reconcile visible cart state non-destructively.
	 * ----------------------------------------------------------------- */

	function runtimeWindow(runtime) {
		if (runtime) { return runtime; }
		return typeof window !== 'undefined' ? window : null;
	}

	function hasWooAjaxBridge(runtime) {
		var root = runtimeWindow(runtime);
		var config = root && root.gloskinData ? root.gloskinData : {};
		return !!(
			root &&
			config.woo &&
			config.addToCartAjaxUrl &&
			typeof root.fetch === 'function' &&
			typeof root.FormData === 'function' &&
			typeof root.jQuery === 'function' &&
			typeof root.wc_cart_fragments_params !== 'undefined'
		);
	}

	function hasWooVariationRuntime(runtime) {
		var root = runtimeWindow(runtime);
		return !!(
			root &&
			typeof root.jQuery === 'function' &&
			root.jQuery.fn &&
			typeof root.jQuery.fn.wc_variation_form === 'function'
		);
	}

	/* Woo localizes wc_add_to_cart_params with its native add-to-cart
	 * runtime. When it is present, added_to_cart already hands Woo the
	 * response fragments, so Gloskin must not force a second refresh. */
	function hasWooNativeAddToCartRuntime(runtime) {
		var root = runtimeWindow(runtime);
		return !!(
			root &&
			typeof root.jQuery === 'function' &&
			typeof root.wc_add_to_cart_params !== 'undefined'
		);
	}

	function clearWooSubmitBusy(submitter) {
		if (submitter && typeof submitter.removeAttribute === 'function') {
			submitter.removeAttribute('aria-busy');
		}
	}

	function isWooSubmitBusy(submitter) {
		return !!(
			submitter &&
			typeof submitter.getAttribute === 'function' &&
			submitter.getAttribute('aria-busy') === 'true'
		);
	}

	function requestWooFragmentRefresh(runtime) {
		var root = runtimeWindow(runtime);
		if (
			!root ||
			typeof root.jQuery !== 'function' ||
			typeof root.wc_cart_fragments_params === 'undefined' ||
			!root.document ||
			!root.document.body
		) {
			return false;
		}
		root.jQuery(root.document.body).trigger('wc_fragment_refresh');
		return true;
	}

	function dispatchWooAddedToCart(response, submitter, runtime) {
		var root = runtimeWindow(runtime);
		if (!root || typeof root.jQuery !== 'function' || !root.document || !root.document.body) {
			return false;
		}
		var body = root.jQuery(root.document.body);
		body.trigger('added_to_cart', [
			response.fragments,
			response.cart_hash,
			submitter ? root.jQuery(submitter) : root.jQuery()
		]);
		if (!hasWooNativeAddToCartRuntime(root)) {
			body.trigger('wc_fragment_refresh');
		}
		return true;
	}

	function handleWooAddToCartResponse(response, submitter, runtime) {
		var root = runtimeWindow(runtime);
		var options = arguments.length > 3 && arguments[3] ? arguments[3] : {};
		var redirectOnError = options.redirectOnError !== false;
		clearWooSubmitBusy(submitter);
		if (!response) { return false; }
		if (response.error) {
			if (redirectOnError && response.product_url && root && root.location) {
				root.location.href = response.product_url;
				return false;
			}
			requestWooFragmentRefresh(root);
			return false;
		}
		return dispatchWooAddedToCart(response, submitter, root);
	}

	/**
	 * Resolve the control that actually triggered this submit. Prefer the
	 * browser's own SubmitEvent.submitter; fall back to Woo's canonical
	 * single_add_to_cart_button only for implicit/older submissions.
	 */
	function resolveWooSubmitter(form, event) {
		if (event && event.submitter) { return event.submitter; }
		return form.querySelector('.single_add_to_cart_button[type="submit"]') || form.querySelector('.single_add_to_cart_button');
	}

	/**
	 * Only Woo simple and variable single-product forms are supported by
	 * this enhancement. Stable Woo root classes own the type decision; no
	 * Gloskin product-type registry is introduced.
	 */
	function isSupportedSingleProductAjaxForm(form) {
		if (!form || typeof form.closest !== 'function') { return false; }
		var productRoot = form.closest('div.product');
		if (!productRoot || !productRoot.classList) { return false; }
		if (productRoot.classList.contains('product-type-simple')) {
			return !form.classList.contains('variations_form');
		}
		if (productRoot.classList.contains('product-type-variable')) {
			return form.classList.contains('variations_form');
		}
		return false;
	}

	/**
	 * Shared control/variation eligibility gate for the supported single
	 * product form and Quick Add's known variable form.
	 */
	function shouldInterceptWooSubmit(form, submitter) {
		if (!submitter || submitter.disabled || submitter.classList.contains('disabled')) {
			return false;
		}
		if (form.classList.contains('variations_form')) {
			var variationField = form.querySelector('input.variation_id, input[name="variation_id"]');
			var variationId = variationField ? parseInt(variationField.value, 10) : 0;
			if (!variationId) { return false; }
		}
		return true;
	}

	/**
	 * Normalize only fields WC_AJAX::add_to_cart() needs beyond the browser's
	 * native FormData(form) result. The activated submitter is appended because
	 * FormData(form) does not include it. A Woo-selected variation becomes the
	 * request product_id while variation_id itself remains untouched.
	 */
	function normalizeAddToCartPayload(formData, submitter) {
		if (submitter && submitter.name) {
			formData.append(submitter.name, submitter.value);
		}

		var variationId = parseInt(formData.get('variation_id'), 10) || 0;
		if (variationId > 0) {
			formData.set('product_id', String(variationId));
		} else if (!formData.get('product_id')) {
			var simpleProductId = submitter && submitter.name === 'add-to-cart' && submitter.value ? submitter.value : '';
			if (simpleProductId) { formData.set('product_id', simpleProductId); }
		}
		return formData;
	}

	function buildAddToCartPayload(form, submitter) {
		return normalizeAddToCartPayload(new FormData(form), submitter);
	}

	/**
	 * Native fallback: re-dispatch a genuine browser submission carrying
	 * the real submitter, so Woo's own form action/method, validation and
	 * listeners remain authoritative. It is only legal before any mutation
	 * request has been dispatched.
	 */
	function nativeFallbackSubmit(form, submitter) {
		if (typeof form.requestSubmit === 'function') {
			form.setAttribute('data-gloskin-ajax-bypass', '1');
			try {
				form.requestSubmit(submitter || undefined);
				return;
			} catch (e) {
				form.removeAttribute('data-gloskin-ajax-bypass');
			}
		}
		form.submit();
	}

	function ajaxAddToCart(form, submitter) {
		var lifecycle = arguments.length > 2 && arguments[2] ? arguments[2] : {};
		if (!hasWooAjaxBridge()) { return false; }
		var config = window.gloskinData || {};
		var formData;
		try {
			formData = buildAddToCartPayload(form, submitter);
		} catch (e) {
			return false;
		}
		if (!formData.get('product_id')) { return false; }

		if (submitter) { submitter.setAttribute('aria-busy', 'true'); }

		function notifyFailure(response, error) {
			if (typeof lifecycle.onFailure === 'function') {
				lifecycle.onFailure(response || null, error || null);
			}
		}

		try {
			window.fetch(config.addToCartAjaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
				.then(function (res) {
					if (!res.ok) { throw new Error('add_to_cart_http'); }
					return res.json();
				})
				.then(function (response) {
					if (!response) { throw new Error('add_to_cart_response'); }
					var succeeded = handleWooAddToCartResponse(
						response,
						submitter,
						undefined,
						{ redirectOnError: lifecycle.redirectOnError !== false }
					);
					if (succeeded) {
						if (typeof lifecycle.onSuccess === 'function') { lifecycle.onSuccess(response); }
						return;
					}
					notifyFailure(response, null);
				})
				.catch(function (error) {
					clearWooSubmitBusy(submitter);
					requestWooFragmentRefresh();
					notifyFailure(null, error);
				});
		} catch (e) {
			clearWooSubmitBusy(submitter);
			requestWooFragmentRefresh();
			notifyFailure(null, e);
		}

		return true;
	}

	/* -----------------------------------------------------------------
	 * SP-003 -- Single product page: progressive AJAX add-to-cart for
	 * simple products and Woo-selected variable products only.
	 * ----------------------------------------------------------------- */

	/**
	 * Post-success View Cart, single-product page only. WooCommerce's own
	 * wc-add-to-cart.js inserts a Woo-native `a.added_to_cart.wc-forward`
	 * link next to the button it bound -- but only for catalog-loop
	 * `.ajax_add_to_cart` buttons; it never binds to a single-product
	 * form.cart submit at all, so nothing creates that link here natively.
	 * This is the smallest idempotent equivalent: same class contract,
	 * canonical cart URL, inserted only after a confirmed successful Woo
	 * mutation (never on dispatch alone), and reusing/updating the same
	 * node on every repeat add rather than ever inserting a second one.
	 * No cart mutation logic lives here -- purely a success presentation.
	 */
	function renderSingleProductViewCartLink(submitter) {
		var config = window.gloskinData || {};
		var cartUrl = config.cartUrl;
		if (!submitter || !submitter.parentNode || !cartUrl) { return; }
		var link = submitter.parentNode.querySelector('a.added_to_cart.wc-forward');
		if (!link) {
			link = document.createElement('a');
			link.className = 'added_to_cart wc-forward';
			submitter.parentNode.insertBefore(link, submitter.nextSibling);
		}
		link.setAttribute('href', cartUrl);
		link.textContent = 'Lihat Keranjang';
	}

	function initSingleProductAjax() {
		if (!document.body.classList.contains('single-product')) { return; }
		/* Scoped to the canonical purchase form only: [data-gloskin-purchase-dock]
		 * is server-rendered by WooCommerce_Adapter::open_purchase_dock()/
		 * close_purchase_dock(), which only ever wrap the page's own primary
		 * product's form.cart (is_primary_single_product_context() -- see the
		 * adapter). A plain 'div.product form.cart' query would also match a
		 * legitimate different-product [product_page] embed's own form.cart if
		 * one ever preceded the primary form in document order; anchoring on
		 * the dock removes that ambiguity entirely rather than relying on
		 * incidental DOM order. */
		var form = document.querySelector('[data-gloskin-purchase-dock] form.cart');
		if (!form || !isSupportedSingleProductAjaxForm(form)) { return; }

		form.addEventListener('submit', function (event) {
			if (form.getAttribute('data-gloskin-ajax-bypass') === '1') {
				form.removeAttribute('data-gloskin-ajax-bypass');
				return;
			}
			var submitter = resolveWooSubmitter(form, event);
			if (isWooSubmitBusy(submitter)) {
				event.preventDefault();
				return;
			}
			if (!shouldInterceptWooSubmit(form, submitter) || !hasWooAjaxBridge()) {
				return;
			}
			event.preventDefault();
			if (!ajaxAddToCart(form, submitter, {
				onSuccess: function () { handleSingleProductAddToCartSuccess(submitter); }
			})) {
				nativeFallbackSubmit(form, submitter);
			}
		});
	}

	/**
	 * Success presentation for the single-product purchase dock: Buy Now
	 * (gloskin-ui1-purchase-dock.js) flags the SAME real submitter with a
	 * one-shot data-gloskin-buy-now-redirect attribute right before
	 * triggering its click, since it never submits a second form/opens a
	 * new mutation owner -- only the presentation branches here, after a
	 * confirmed successful Woo mutation. The normal Add to Cart path is
	 * completely unchanged (still just the existing View Cart link).
	 *
	 * @param {Element} submitter The real submit button that triggered this mutation.
	 * @return {void}
	 */
	function handleSingleProductAddToCartSuccess(submitter) {
		if (submitter && submitter.hasAttribute('data-gloskin-buy-now-redirect')) {
			submitter.removeAttribute('data-gloskin-buy-now-redirect');
			var cartUrl = (window.gloskinData || {}).cartUrl;
			if (cartUrl) {
				window.location.href = cartUrl;
				return;
			}
		}
		renderSingleProductViewCartLink(submitter);
	}

	/* -----------------------------------------------------------------
	 * SP-004 -- Gloskin Quick Add modal for variable catalog cards.
	 * ----------------------------------------------------------------- */

	function initQuickAdd() {
		var config = window.gloskinData || {};
		if (!config.woo) { return; }
		var modal = document.querySelector('[data-gloskin-overlay="quickadd"]');
		var body = modal ? modal.querySelector('[data-gloskin-quickadd-body]') : null;
		if (!modal || !body) { return; }

		var cache = {};
		var currentId = null;
		var currentUrl = '';

		function canOpenQuickAdd() {
			return hasWooVariationRuntime() && typeof fetch === 'function';
		}

		function recoveryMarkup(message, productUrl) {
			var html = '<div class="gloskin-ui1-quickadd__error" role="status">';
			html += '<p>' + escapeHtml(message) + '</p>';
			if (productUrl) {
				html += '<a class="gloskin-ui1-button gloskin-ui1-button--ghost gloskin-ui1-button--small" href="' + escapeHtml(productUrl) + '">' + escapeHtml('Lihat Produk') + '</a>';
			}
			return html + '</div>';
		}

		function renderLoading() {
			body.innerHTML = '<div class="gloskin-ui1-quickadd__loading"><span>' + escapeHtml('Memuat produk…') + '</span></div>';
		}

		function renderLoadError() {
			body.innerHTML = recoveryMarkup('Produk belum dapat dimuat. Silakan buka halaman produk untuk memilih varian.', currentUrl);
		}

		function clearMutationStatus() {
			var status = body.querySelector('[data-gloskin-quickadd-status]');
			if (status) { status.innerHTML = ''; }
		}

		function renderMutationError(response) {
			var status = body.querySelector('[data-gloskin-quickadd-status]');
			if (!status) { return; }
			var productUrl = response && response.product_url ? response.product_url : currentUrl;
			status.innerHTML = recoveryMarkup('Produk belum berhasil ditambahkan. Pilihan Anda tetap tersedia; silakan coba lagi atau buka halaman produk.', productUrl);
		}

		function bindForm(form) {
			if (!form.classList.contains('variations_form') || !hasWooVariationRuntime()) { return; }
			window.jQuery(form).wc_variation_form();
			form.addEventListener('submit', function (event) {
				if (form.getAttribute('data-gloskin-ajax-bypass') === '1') {
					form.removeAttribute('data-gloskin-ajax-bypass');
					return;
				}
				var submitter = resolveWooSubmitter(form, event);
				if (isWooSubmitBusy(submitter)) {
					event.preventDefault();
					return;
				}
				if (!shouldInterceptWooSubmit(form, submitter) || !hasWooAjaxBridge()) { return; }
				event.preventDefault();
				clearMutationStatus();
				/* Dispatch is not success. Keep Quick Add open until Woo emits
				 * added_to_cart; initCart then switches through the one existing
				 * overlay controller so Quick Add and Cart can never overlap. */
				if (!ajaxAddToCart(form, submitter, {
					redirectOnError: false,
					onFailure: function (response) { renderMutationError(response); }
				})) {
					nativeFallbackSubmit(form, submitter);
				}
			});
		}

		function render(data) {
			currentUrl = data.url || currentUrl;
			var html = '<div class="gloskin-ui1-quickadd__product">';
			html += data.image_html || '';
			html += '<div><strong>' + escapeHtml(data.name || '') + '</strong>';
			if (data.price_html) { html += '<div class="gloskin-ui1-product-price">' + data.price_html + '</div>'; }
			html += '</div></div>';
			html += '<div class="gloskin-ui1-quickadd__form gloskin-ui1-form">' + (data.form_html || '') + '</div>';
			html += '<div class="gloskin-ui1-quickadd__status" data-gloskin-quickadd-status aria-live="polite"></div>';
			body.innerHTML = html;
			var form = body.querySelector('form.cart');
			if (form) { bindForm(form); }
		}

		function open(productId, productUrl) {
			currentId = productId;
			currentUrl = productUrl || '';
			overlay.open('quickadd');
			if (cache[productId]) { render(cache[productId]); return; }
			renderLoading();
			var url = (config.restUrl || '/wp-json/gloskin/v1/') + 'products/quick-add?id=' + encodeURIComponent(productId);
			fetch(url, publicRestGetOptions())
				.then(function (res) {
					if (!res.ok) { throw new Error('quickadd_http'); }
					return res.json();
				})
				.then(function (data) {
					if (!data || !data.found) { throw new Error('quickadd_not_found'); }
					cache[productId] = data;
					if (currentId === productId) { render(data); }
				})
				.catch(function () {
					if (currentId === productId) { renderLoadError(); }
				});
		}

		document.addEventListener('click', function (event) {
			var trigger = event.target.closest && event.target.closest('[data-gloskin-quickadd-open]');
			if (!trigger || !canOpenQuickAdd()) { return; }
			var productId = trigger.getAttribute('data-gloskin-quickadd-product');
			if (!productId) { return; }
			event.preventDefault();
			open(productId, trigger.getAttribute('href') || '');
		});

		document.addEventListener('click', function (event) {
			var relatedTrigger = event.target.closest && event.target.closest(
				'body.single-product .related.products a.add_to_cart_button.product_type_variable[data-product_id]:not([data-gloskin-quickadd-open])'
			);
			if (!relatedTrigger || !canOpenQuickAdd()) { return; }
			var relatedProductId = relatedTrigger.getAttribute('data-product_id');
			if (!relatedProductId) { return; }
			event.preventDefault();
			open(relatedProductId, relatedTrigger.getAttribute('href') || '');
		});
	}

	/* -----------------------------------------------------------------
	 * Shop catalog -- SSR-first, read-only AJAX enhancement.
	 * ----------------------------------------------------------------- */

	function parseShopCatalogHash(hash, defaultPage) {
		var state = { category: '', page: Math.max(1, parseInt(defaultPage, 10) || 1) };
		var raw = String(hash || '').replace(/^#/, '');
		if (!raw) { return state; }
		state.page = 1;
		raw.split('&').forEach(function (pair) {
			var bits = pair.split('=');
			var key = decodeURIComponent(bits.shift() || '');
			var value = decodeURIComponent(bits.join('=') || '');
			if (key === 'category') { state.category = value; }
			if (key === 'page') { state.page = Math.max(1, parseInt(value, 10) || 1); }
		});
		return state;
	}

	function buildShopCatalogHash(category, page) {
		var parts = [];
		if (category) { parts.push('category=' + encodeURIComponent(category)); }
		page = Math.max(1, parseInt(page, 10) || 1);
		if (page > 1) { parts.push('page=' + page); }
		return parts.length ? '#' + parts.join('&') : '';
	}

	function initShopCatalog() {
		var root = document.querySelector('[data-gloskin-shop-catalog]');
		if (!root || typeof window.fetch !== 'function') { return; }
		var categories = root.querySelector('[data-gloskin-shop-categories]');
		var results = root.querySelector('[data-gloskin-shop-results]');
		if (!categories || !results) { return; }

		var config = window.gloskinData || {};
		var initialPage = Math.max(1, parseInt(root.getAttribute('data-gloskin-shop-initial-page'), 10) || 1);
		var initialUrl = window.location.href;
		var shopUrl = root.getAttribute('data-gloskin-shop-url') || '/shop/';
		var currentCategory = '';
		var currentPage = initialPage;
		var requestSequence = 0;
		var abortController = null;
		var retryRequest = null;

		function stateForLocation() {
			var defaultPage = window.location.href.split('#')[0] === initialUrl.split('#')[0] ? initialPage : 1;
			return parseShopCatalogHash(window.location.hash, defaultPage);
		}

		function categoryFallback(category) {
			var links = categories.querySelectorAll('[data-gloskin-shop-category]');
			for (var i = 0; i < links.length; i++) {
				if ((links[i].getAttribute('data-gloskin-shop-category') || '') === category) {
					return links[i].getAttribute('href') || shopUrl;
				}
			}
			return shopUrl;
		}

		function updateCategoryState(category) {
			var links = categories.querySelectorAll('[data-gloskin-shop-category]');
			Array.prototype.forEach.call(links, function (link) {
				var active = (link.getAttribute('data-gloskin-shop-category') || '') === category;
				if (active) { link.setAttribute('aria-current', 'page'); }
				else { link.removeAttribute('aria-current'); }
			});
		}

		function clearStatus() {
			var status = results.querySelector('[data-gloskin-shop-status]');
			if (status) { status.innerHTML = ''; }
		}

		function showCatalogFailure(fallbackHref) {
			var status = results.querySelector('[data-gloskin-shop-status]');
			if (!status) { return; }
			status.innerHTML = '';
			var copy = document.createElement('span');
			copy.textContent = 'Katalog belum dapat diperbarui. Hasil sebelumnya tetap ditampilkan.';
			var retry = document.createElement('button');
			retry.type = 'button';
			retry.className = 'gloskin-ui1-button gloskin-ui1-button--ghost gloskin-ui1-button--small';
			retry.setAttribute('data-gloskin-shop-retry', '');
			retry.textContent = 'Coba lagi';
			var fallback = document.createElement('a');
			fallback.className = 'gloskin-ui1-text-link';
			fallback.href = fallbackHref || shopUrl;
			fallback.textContent = 'Buka halaman biasa';
			status.appendChild(copy);
			status.appendChild(retry);
			status.appendChild(fallback);
		}

		var SKELETON_CARD_COUNT = 8;

		function skeletonMarkup() {
			var card = '<div class="gloskin-ui1-shop-skeleton__card"><div class="gloskin-ui1-shop-skeleton__media"></div><div class="gloskin-ui1-shop-skeleton__line gloskin-ui1-shop-skeleton__line--title"></div><div class="gloskin-ui1-shop-skeleton__line gloskin-ui1-shop-skeleton__line--price"></div></div>';
			var cards = '';
			for (var i = 0; i < SKELETON_CARD_COUNT; i += 1) { cards += card; }
			return '<div class="gloskin-ui1-shop-skeleton" data-gloskin-shop-skeleton aria-hidden="true"><div class="gloskin-ui1-shop-skeleton__grid">' + cards + '</div></div>';
		}

		/* Extends the existing aria-busy/is-loading presentation state -- no
		 * second loading controller. The skeleton is an overlay appended
		 * inside results, never a replacement of it: the previous grid stays
		 * in the DOM underneath (results.innerHTML is only ever reassigned by
		 * the existing success path in requestCatalog()), so on failure
		 * removing the skeleton simply reveals that same previous grid again.
		 * Height is locked before the skeleton is inserted so neither
		 * inserting nor removing it can shift the page. */
		function setBusy(busy) {
			results.setAttribute('aria-busy', busy ? 'true' : 'false');
			root.classList.toggle('is-loading', !!busy);
			var live = root.querySelector('[data-gloskin-shop-status-live]');
			if (busy) {
				if (!results.querySelector('[data-gloskin-shop-skeleton]')) {
					var height = results.getBoundingClientRect().height;
					if (height > 0) { results.style.minHeight = height + 'px'; }
					results.insertAdjacentHTML('beforeend', skeletonMarkup());
				}
				if (live) { live.textContent = 'Memuat produk'; }
			} else {
				var skeleton = results.querySelector('[data-gloskin-shop-skeleton]');
				if (skeleton) { skeleton.remove(); }
				results.style.removeProperty('min-height');
				if (live) { live.textContent = ''; }
			}
		}

		function historyTarget(category, page) {
			try {
				var target = new URL(shopUrl, window.location.href);
				target.hash = buildShopCatalogHash(category, page);
				return target.pathname + target.search + target.hash;
			} catch (e) {
				return shopUrl + buildShopCatalogHash(category, page);
			}
		}

		function updateHistory(category, page, mode) {
			if (!window.history || mode === 'none') { return; }
			var target = historyTarget(category, page);
			var state = { gloskinShop: true, category: category, page: page };
			if (mode === 'replace' && typeof window.history.replaceState === 'function') {
				window.history.replaceState(state, '', target);
			} else if (mode === 'push' && typeof window.history.pushState === 'function') {
				window.history.pushState(state, '', target);
			}
		}

		function revealPaginationContext() {
			var heading = results.querySelector('[data-gloskin-shop-results-heading]');
			if (!heading) { return; }
			try { heading.focus({ preventScroll: true }); } catch (e) { heading.focus(); }
			if (typeof heading.scrollIntoView === 'function') {
				var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
				heading.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
			}
		}

		function requestCatalog(category, page, options) {
			options = options || {};
			category = String(category || '');
			page = Math.max(1, parseInt(page, 10) || 1);
			var sequence = ++requestSequence;
			if (abortController) { abortController.abort(); }
			abortController = typeof window.AbortController !== 'undefined' ? new window.AbortController() : null;
			var fallbackHref = options.fallbackHref || categoryFallback(category);
			retryRequest = { category: category, page: page, options: options, fallbackHref: fallbackHref };
			clearStatus();
			setBusy(true);

			var endpoint = (config.restUrl || '/wp-json/gloskin/v1/') + 'shop/catalog?category=' + encodeURIComponent(category) + '&page=' + encodeURIComponent(page);
			var fetchOptions = publicRestGetOptions();
			if (abortController) { fetchOptions.signal = abortController.signal; }

			return window.fetch(endpoint, fetchOptions)
				.then(function (response) {
					if (!response.ok) { throw new Error('shop_catalog_http'); }
					return response.json();
				})
				.then(function (data) {
					if (sequence !== requestSequence) { return false; }
					if (!data || typeof data.html !== 'string') { throw new Error('shop_catalog_response'); }
					results.innerHTML = data.html;
					currentCategory = String(data.category || '');
					currentPage = Math.max(1, parseInt(data.page, 10) || 1);
					updateCategoryState(currentCategory);
					updateHistory(currentCategory, currentPage, options.historyMode || 'none');
					document.dispatchEvent(new CustomEvent('gloskin:catalog-updated', { detail: { category: currentCategory, page: currentPage } }));
					if (options.pagination) { revealPaginationContext(); }
					return true;
				})
				.catch(function (error) {
					if (error && error.name === 'AbortError') { return false; }
					if (sequence !== requestSequence) { return false; }
					showCatalogFailure(fallbackHref);
					return false;
				})
				.then(function (result) {
					if (sequence === requestSequence) {
						setBusy(false);
						abortController = null;
					}
					return result;
				});
		}

		root.addEventListener('click', function (event) {
			var categoryLink = event.target.closest && event.target.closest('[data-gloskin-shop-category]');
			if (categoryLink && categories.contains(categoryLink)) {
				event.preventDefault();
				var category = categoryLink.getAttribute('data-gloskin-shop-category') || '';
				requestCatalog(category, 1, { historyMode: 'push', fallbackHref: categoryLink.getAttribute('href') || shopUrl });
				return;
			}

			var pageLink = event.target.closest && event.target.closest('[data-gloskin-shop-page]');
			if (pageLink && results.contains(pageLink)) {
				var page = Math.max(1, parseInt(pageLink.getAttribute('data-gloskin-shop-page'), 10) || 1);
				event.preventDefault();
				requestCatalog(currentCategory, page, { historyMode: 'push', pagination: true, fallbackHref: pageLink.getAttribute('href') || shopUrl });
				return;
			}

			var retry = event.target.closest && event.target.closest('[data-gloskin-shop-retry]');
			if (retry && results.contains(retry) && retryRequest) {
				event.preventDefault();
				requestCatalog(retryRequest.category, retryRequest.page, {
					historyMode: retryRequest.options.historyMode || 'none',
					pagination: !!retryRequest.options.pagination,
					fallbackHref: retryRequest.fallbackHref
				});
			}
		});

		window.addEventListener('popstate', function () {
			var state = stateForLocation();
			requestCatalog(state.category, state.page, { historyMode: 'none', fallbackHref: categoryFallback(state.category) });
		});

		var initialState = stateForLocation();
		currentCategory = initialState.category;
		currentPage = initialState.page;
		if (window.location.hash) {
			requestCatalog(currentCategory, currentPage, { historyMode: 'replace', fallbackHref: categoryFallback(currentCategory) });
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
			try {
				localStorage.setItem(STORAGE_KEY, JSON.stringify(ids.slice(0, MAX_ITEMS)));
				return true;
			} catch (e) { return false; }
		}

		function toggle(productId) {
			productId = parseInt(productId, 10);
			if (!productId) { return false; }
			var ids = getIds();
			var index = ids.indexOf(productId);
			if (index !== -1) {
				ids.splice(index, 1);
				return saveIds(ids) ? false : true;
			}
			ids.push(productId);
			return saveIds(ids);
		}

		function isWished(productId) {
			return getIds().indexOf(parseInt(productId, 10)) !== -1;
		}

		var triggers = document.querySelectorAll('[data-gloskin-wishlist-open]');
		Array.prototype.forEach.call(triggers, function (trigger) {
			trigger.addEventListener('click', function () {
				overlay.open('wishlist');
				renderWishlistBody();
			});
		});

		function applyState(btn, active) {
			btn.classList.toggle('is-wished', active);
			btn.setAttribute('aria-pressed', active ? 'true' : 'false');
			var addLabel = btn.getAttribute('data-label-add');
			var removeLabel = btn.getAttribute('data-label-remove');
			if (addLabel && removeLabel) {
				btn.setAttribute('aria-label', active ? removeLabel : addLabel);
			}
		}

		function wishlistEmptyStateMarkup() {
			return emptyStateMarkup(
				'wishlist',
				'Belum ada produk favorit',
				'Produk yang Anda simpan akan muncul di sini agar mudah ditemukan kembali.',
				'Lihat Skincare',
				config.cartUrl ? config.cartUrl.replace(/\/cart\/$/, '/skincare/') : '/skincare/'
			);
		}

		/* Removing from inside the already-open sheet must never refetch/
		 * rebuild the whole body (that full-sheet loading-spinner rebuild is
		 * the real "reload" this replaces) -- only the closest list item is
		 * collapsed/removed in place. localStorage mutation itself has no
		 * meaningful wait, so this is a short opacity/transform transition,
		 * not a skeleton. */
		function removeSheetItem(item, btn) {
			var list = item.parentElement;
			var next = item.nextElementSibling;
			var prev = item.previousElementSibling;
			var focusTarget = (next && next.querySelector('[data-gloskin-wishlist-toggle]')) ||
				(prev && prev.querySelector('[data-gloskin-wishlist-toggle]'));
			if (!focusTarget) {
				var panel = item.closest('.gloskin-ui1-sheet__panel');
				focusTarget = panel && (panel.querySelector('.gloskin-ui1-sheet__close') || panel.querySelector('[data-gloskin-overlay-close]'));
			}
			if (focusTarget) { focusTarget.focus(); }

			item.classList.add('is-removing');
			item.setAttribute('aria-busy', 'true');
			btn.disabled = true;

			var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			window.setTimeout(function () {
				item.remove();
				if (list && !list.children.length) {
					var body = document.querySelector('[data-gloskin-wishlist-body]');
					if (body) { body.innerHTML = wishlistEmptyStateMarkup(); }
				}
			}, reduceMotion ? 0 : 170);
		}

		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-gloskin-wishlist-toggle]');
			if (!btn) { return; }
			var productId = parseInt(btn.getAttribute('data-gloskin-wishlist-toggle'), 10);
			if (!productId) { return; }
			var wasActive = isWished(productId);
			var active = toggle(productId);
			var sheetItem = btn.closest('.gloskin-ui1-wishlist-sheet__item');
			if (sheetItem) {
				removeSheetItem(sheetItem, btn);
			} else {
				applyState(btn, active);
			}
			updateBadges();
			if (!wasActive && active) { successFeedback('wishlist'); }
		});

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
				badge.hidden = count < 1;
			});
			var countLabels = document.querySelectorAll('[data-gloskin-wishlist-count-sr]');
			Array.prototype.forEach.call(countLabels, function (label) {
				label.textContent = count + ' produk favorit';
			});
			var utilities = document.querySelectorAll('[data-gloskin-wishlist-open], [data-gloskin-wishlist-open-from-drawer]');
			Array.prototype.forEach.call(utilities, function (utility) {
				utility.classList.toggle('is-active', count > 0);
			});
		}

		function renderWishlistBody() {
			var body = document.querySelector('[data-gloskin-wishlist-body]');
			if (!body) { return; }
			var ids = getIds();
			if (!ids.length) {
				body.innerHTML = wishlistEmptyStateMarkup();
				return;
			}
			body.innerHTML = '<div class="gloskin-ui1-search-overlay__loading"><span></span></div>';

			var url = (config.restUrl || '/wp-json/gloskin/v1/') + 'products/resolve?ids=' + ids.join(',');
			fetch(url, publicRestGetOptions())
				.then(function (res) { return res.json(); })
				.then(function (data) {
					var products = data.products || [];
					var validIds = products.map(function (p) { return p.id; });
					var currentIds = getIds().filter(function (id) { return validIds.indexOf(id) !== -1; });
					saveIds(currentIds);

					if (!products.length) {
						body.innerHTML = wishlistEmptyStateMarkup();
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

		document.addEventListener('gloskin:catalog-updated', syncToggles);
		syncToggles();
	}

	/* -----------------------------------------------------------------
	 * Hero Background Video (native <video>, Home video-only mode only)
	 * -----------------------------------------------------------------
	 * Home's pure background-video surface (gloskin_ui1_render_hero()'s
	 * 'video-only' branch): one native <video>, never a remote player.
	 * PREPARING -> READY state machine -- the video is only ever revealed
	 * once it has genuinely reached usable playback, never merely because
	 * the DOM node exists:
	 *
	 *   1. markup starts is-video-preparing, video opacity:0 (CSS);
	 *   2. wait for the browser's own loadeddata event;
	 *   3. reduced-motion: establish the first frame, then pause and
	 *      reveal -- no repeated loader/motion for those users;
	 *   4. otherwise make the single play attempt and wait for its Promise plus
	 *      the 'playing' event;
	 *   5. reveal inside one requestAnimationFrame (is-video-preparing ->
	 *      is-video-ready), which CSS fades in over ~360ms;
	 *   6. any failure (error event, rejected play() Promise) releases the
	 *      loader into a clean is-video-failed state -- white hero,
	 *      working scroll cue, page continues normally, never a broken
	 *      media icon or indefinite block.
	 *
	 * No repeating-interval polling anywhere in this section. One bounded
	 * one-shot timeout only guards against a video that never becomes
	 * usable at all, releasing it into the same clean failure state -- it
	 * never pretends readiness on its own.
	 */
	var HERO_BG_VIDEO_SAFETY_TIMEOUT_MS = 4000;

	function setupHeroBackgroundVideo(hero, wrap) {
		var video = wrap.querySelector('[data-gloskin-hero-bg-video]');
		if (!video) { return; }
		var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var state = {
			dataReady: false,
			playAttempted: false,
			playResolved: false,
			playingSeen: false,
			readyCommitted: false,
			terminalError: false,
			timeoutReleased: false
		};
		var timeoutHandle = null;

		function clearSafetyTimeout() {
			if (timeoutHandle !== null && typeof window.clearTimeout === 'function') {
				window.clearTimeout(timeoutHandle);
			}
			timeoutHandle = null;
		}

		function commitReady() {
			if (state.readyCommitted || state.terminalError) { return; }
			if (!state.dataReady || (!reduceMotion && (!state.playResolved || !state.playingSeen))) { return; }
			state.readyCommitted = true;
			clearSafetyTimeout();
			function reveal() {
				hero.classList.remove('is-video-preparing');
				hero.classList.remove('is-video-failed');
				hero.classList.add('is-video-ready');
			}
			if (typeof window.requestAnimationFrame === 'function') {
				window.requestAnimationFrame(reveal);
			} else {
				reveal();
			}
		}

		function releaseLoader() {
			if (state.readyCommitted) { return; }
			state.timeoutReleased = true;
			hero.classList.remove('is-video-preparing');
			hero.classList.add('is-video-failed');
		}

		function terminalFailure() {
			if (state.readyCommitted || state.terminalError) { return; }
			state.terminalError = true;
			clearSafetyTimeout();
			releaseLoader();
		}

		function onLoadedData() {
			state.dataReady = true;
			if (reduceMotion) {
				try { video.pause(); } catch (error) { /* no-op: already static */ }
			}
			commitReady();
		}

		function onPlaying() {
			state.playingSeen = true;
			commitReady();
		}

		/* Listener installation must precede all current-state reads and the
		 * single play attempt, so server-autoplay events cannot be missed. */
		video.addEventListener('loadeddata', onLoadedData);
		video.addEventListener('playing', onPlaying);
		video.addEventListener('error', terminalFailure);

		if (video.readyState >= 2) { state.dataReady = true; }
		if (!video.paused && video.readyState >= 2) { state.playingSeen = true; }

		if (reduceMotion) {
			if (state.dataReady) {
				try { video.pause(); } catch (error) { /* no-op: already static */ }
				commitReady();
			}
		} else if (!state.playAttempted) {
			state.playAttempted = true;
			var playPromise;
			try {
				playPromise = video.play();
			} catch (error) {
				terminalFailure();
			}
			if (playPromise && typeof playPromise.then === 'function') {
				playPromise.then(function () {
					state.playResolved = true;
					commitReady();
				}).catch(terminalFailure);
			} else if (!state.terminalError) {
				state.playResolved = true;
				commitReady();
			}
		}

		if (!state.readyCommitted && !state.terminalError) {
			timeoutHandle = window.setTimeout(releaseLoader, HERO_BG_VIDEO_SAFETY_TIMEOUT_MS);
		}
	}

	function initHeroBackgroundVideo() {
		var wraps = document.querySelectorAll('[data-gloskin-hero-bg-video-wrap]');
		for (var i = 0; i < wraps.length; i++) {
			var hero = wraps[i].closest('.gloskin-ui1-hero');
			if (hero) { setupHeroBackgroundVideo(hero, wraps[i]); }
		}
	}

	/* Home video-only hero's one scroll cue (docs/task-treatment-
	 * consultation-commerce-discovery.md section 14): a single semantic
	 * <button>, click scrolls the hero's own next real sibling section
	 * into view -- never an arbitrary pixel offset, never a second
	 * animation framework. prefers-reduced-motion swaps 'smooth' for an
	 * instant jump instead of skipping the action entirely. */
	function initHeroScrollCue() {
		var buttons = document.querySelectorAll('[data-gloskin-hero-scroll-cue]');
		for (var i = 0; i < buttons.length; i++) {
			(function (button) {
				button.addEventListener('click', function () {
					var hero = button.closest('.gloskin-ui1-hero');
					var target = hero ? hero.nextElementSibling : null;
					if (!target) { return; }
					var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
					target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
				});
			}(buttons[i]));
		}
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
		initShopCatalog();
		initWishlist();
		initHeroBackgroundVideo();
		initHeroScrollCue();
	}

	if (typeof document !== 'undefined') {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init);
		} else {
			init();
		}
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = {
			resolveWooSubmitter: resolveWooSubmitter,
			isSupportedSingleProductAjaxForm: isSupportedSingleProductAjaxForm,
			shouldInterceptWooSubmit: shouldInterceptWooSubmit,
			normalizeAddToCartPayload: normalizeAddToCartPayload,
			hasWooAjaxBridge: hasWooAjaxBridge,
			hasWooVariationRuntime: hasWooVariationRuntime,
			hasWooNativeAddToCartRuntime: hasWooNativeAddToCartRuntime,
			dispatchWooAddedToCart: dispatchWooAddedToCart,
			handleWooAddToCartResponse: handleWooAddToCartResponse,
			isWooSubmitBusy: isWooSubmitBusy,
			successFeedback: successFeedback,
			parseShopCatalogHash: parseShopCatalogHash,
			buildShopCatalogHash: buildShopCatalogHash,
			setupHeroBackgroundVideo: setupHeroBackgroundVideo,
			initHeroBackgroundVideo: initHeroBackgroundVideo
		};
	}
}());
