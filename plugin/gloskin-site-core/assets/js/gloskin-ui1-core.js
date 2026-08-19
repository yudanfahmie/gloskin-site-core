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

	/* ONE canonical semantic label for every visible first-party DIRECT
	 * cart-mutation CTA JS ever sets a proxy/native-fallback label from.
	 * Sourced from the SAME server-side label owner (Gloskin_Site_Core_
	 * WooCommerce_Adapter::direct_cart_cta_label()) via gloskinData.cartCtaLabel
	 * -- this file never hard-codes the wording a second time. */
	function cartCtaLabel() {
		var config = window.gloskinData || {};
		return config.cartCtaLabel || 'Keranjang';
	}

	/* Presentation-only commerce badge memory. The canonical cart count still
	 * comes from Woo fragments and the canonical wishlist count still comes
	 * from updateBadges(); this cache only remembers what the DOM last rendered
	 * so a confirmed success can decide whether there is a visual delta. */
	var commerceBadgeLastRendered = { cart: null, wishlist: null };
	var COMMERCE_BADGE_ANIMATION_CLASS = 'is-commerce-badge-added';

	function commerceBadgeSelector(type) {
		if (type === 'cart') { return '[data-gloskin-cart-count]'; }
		if (type === 'wishlist') { return '[data-gloskin-wishlist-count]'; }
		return '';
	}

	function readCommerceBadgeCount(type, root) {
		var selector = commerceBadgeSelector(type);
		if (!selector || !root || !root.document) { return null; }
		var badge = root.document.querySelector(selector);
		if (!badge) { return null; }
		var count = parseInt(String(badge.textContent || '').trim(), 10);
		return isNaN(count) ? 0 : count;
	}

	function initializeCommerceBadgeCounts(runtime) {
		var root = runtimeWindow(runtime);
		if (!root || !root.document) { return false; }
		['cart', 'wishlist'].forEach(function (type) {
			var count = readCommerceBadgeCount(type, root);
			if (count !== null) { commerceBadgeLastRendered[type] = count; }
		});
		return true;
	}

	function animateCommerceBadgeDelta(type, runtime) {
		var root = runtimeWindow(runtime);
		var selector = commerceBadgeSelector(type);
		if (!root || !root.document || !selector) { return false; }
		var badges = root.document.querySelectorAll(selector);
		if (!badges.length) { return false; }
		var current = readCommerceBadgeCount(type, root);
		var previous = commerceBadgeLastRendered[type];
		commerceBadgeLastRendered[type] = current;

		/* Added-in motion is intentionally increase-only. Decreases still pass
		 * through this helper to keep the presentation cache synchronized, but
		 * cart/wishlist removals never inherit an "added" celebration. */
		if (current === null || previous === null || current <= previous) { return false; }
		if (feedbackReducedMotion(root)) {
			Array.prototype.forEach.call(badges, function (badge) {
				badge.classList.remove(COMMERCE_BADGE_ANIMATION_CLASS);
			});
			return false;
		}

		Array.prototype.forEach.call(badges, function (badge) {
			badge.classList.remove(COMMERCE_BADGE_ANIMATION_CLASS);
			void badge.offsetWidth;
			badge.classList.add(COMMERCE_BADGE_ANIMATION_CLASS);
			if (typeof root.setTimeout === 'function') {
				root.setTimeout(function () { badge.classList.remove(COMMERCE_BADGE_ANIMATION_CLASS); }, 320);
			}
		});
		return true;
	}

	/* Woo owns the remove link and click lifecycle. This helper only attaches
	 * reusable presentation classes to the already-rendered native link; no
	 * listener, endpoint, href or Woo data attribute is created or replaced. */
	function applyCommerceRemovePresentation(rootDocument) {
		var doc = rootDocument && typeof rootDocument.querySelectorAll === 'function'
			? rootDocument
			: (typeof document !== 'undefined' ? document : null);
		if (!doc) { return 0; }
		var removes = doc.querySelectorAll('.gloskin-ui1-cart-sheet__item-remove.remove_from_cart_button');
		Array.prototype.forEach.call(removes, function (remove) {
			remove.classList.add('gloskin-ui1-action-icon');
			remove.classList.add('gloskin-ui1-action-icon--danger');
		});
		return removes.length;
	}

	/* -----------------------------------------------------------------
	 * Shared confirmed-success feedback. Badge motion is owned above; this
	 * helper keeps the existing short success sound only, so cart/wishlist
	 * successes have one visually dominant motion owner.
	 * ----------------------------------------------------------------- */

	var SUCCESS_SOUND_URI = 'data:audio/wav;base64,UklGRuQDAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YcADAACAgICAgIB/f35+f3+AgIGAgICAf39+fn5+f4GCg4OCf317ent+goWGhYJ/fHp6fH6Bg4ODgoGAf359fHt7fYGEh4iGgXt2dHZ7gYiLi4eBenZ1d3yBhIaGhIOBf358enh4e3+FioyKhX11cHF2f4iOj4uEfHVzdHl/hIaHhoSCgH59e3l4en2DiIyLh4B3cnB0e4SMj42Hf3dzc3d9goaHhoSCgH99e3l4eXyBhouMiYN6c3ByeIGKjo6Jgnp0c3V7gIWHh4WDgYB+fHp4eHp/hIqMi4V9dnFxdX6HjY+MhXx2c3R5foOGh4aEgoB/fXt5eHl9goiMjIiAeHJwc3uEi4+NiH94dHN3fIKFh4aEgoF/fXt5eHl7gIaLjIqDe3RwcXeAiY6PioJ6dXN1eoCEh4eFg4GAfnx6eXh6foSJjIuGfnZxcHV9ho2PjIV9dnN0eH6DhoeGhIKAf317eXh5fYKHi4yIgXlycHN6g4uPjoiAeXRzdnyBhYeGhYOBf358enh5e4CFioyKhHx0cHF3f4iOj4uDe3VzdXp/hIaHhYOBgH58enl4en6DiYyLhn93cXB0fIWMj42GfndzdHh9goaHhoSCgH99e3l4eXyBh4uMiYJ6c3ByeYKKj46JgXl0c3Z7gYWHhoWDgX9+fHp4eHt/hYqMioV9dXBxdn+Ijo+LhHx1c3R5f4SGh4aEgoB+fXt5eHp9g4iMi4eAd3JwdHuEjI+Nh393c3N3fYKGh4aEgoB/fXt5eHl8gYaLjImDenNwcniBio6OiYJ6dHN1e4CFh4eFg4GAfnx6eHh6f4SKjIuFfXZxcXV+h42PjIV8dnN0eX6DhoeGhIKAf317eXh5fYKIjIyIgHhycHN7hIuPjYh/eHRzd3yChYeGhIKBf317eXh5e4CGioyJg3t0cHJ4gImOjoqCe3V0dnqAhIaGhYOBgH59e3l5e36DiIqJhX54c3N3fYWKjIqEfnh2dnp+goWFhIOBgH9+fHt6e32BhYiIhoF7dnR2e4KHiomFgHt4d3l9gYOEhIOBgH9+fXx7e32Ag4aHhoJ9eXd3en+EiIiGgn16eXp8f4KDg4OBgIB/fn18fH1/gYSFhYN/fHl5en6ChYaFgn98ent8f4GCgoKBgIB/f359fX1+gIKDhIOAfnt6e32Ag4SEgoB+fHx9foCBgYGBgIB/f39+fn5+f4GCgoKBf319fX5/gYKCgYB/fn5+f3+AgICAgICAf39/f39/f4CAgYCAgH9/f39/gICAgIB/f39/f3+AgIA=';
	var successAudio = null;
	var lastSuccessSoundAt = 0;
	var SUCCESS_SOUND_COOLDOWN_MS = 280;

	/* Variation-required feedback follows the same safe, embedded-audio
	 * pattern as successFeedback(): one short data URI, lazy Audio instance,
	 * bounded cooldown, no network/media file and no mutation ownership. */
	var NOTICE_SOUND_URI = 'data:audio/wav;base64,UklGRpQDAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YXADAACAgICBgYCAfn18fH1/gYOFhIOAfXp4eXt+g4aIiIaBfHd1dXh9g4mMjImCe3VxcXV8hIuPkIyEe3NubXJ6g42TlI+GfHNta3B4goySk5CHfXRtbG93gYuRk5CIf3VubG92gImQk5CJgHZvbG51f4iQk5GKgXdwbW50fYePkpGLgnlxbW50fIaOkpGMg3pybW5ze4WNkZGMhHtzbm5yeoOMkZGNhXx0b25yeYKLkJGNhn11b25xeIGKj5GOh352cG5xd4CIjpGOiIB3cW9xd3+HjpCOiYF4cm9wdn6GjZCPiYJ5c29wdX2FjI+PioN6c3BwdXyEi4+Pi4N7dHBwdHuDio6Pi4R8dXFwdHqCiY6Pi4V9dnJxc3qBiI2OjIZ+d3Jxc3mAh4yOjId/eHNxc3h/hoyOjIeAeXRxc3h+houNjIiBenRyc3d+hYqNjIiCe3Vyc3d9hImNjImDfHZzc3Z8g4mMjImDfXdzc3Z7goiMjImEfnh0c3Z7gYeLjIqFfnh0c3V6gIaKjIqFf3l1c3V6f4WKi4qGgHp2dHV5f4WJi4qGgXt2dHV5foSIi4qHgXx3dHV4fYOIioqHgnx4dXV4fYKHioqHgn14dXV4fIKGiYqHg355dnV4fIGGiYqIg356dnZ3e4CFiImIhH96d3Z3e4CEiImIhIB7eHZ3e3+Eh4mIhYB8eHZ3en+Dh4iIhYF8eXd3en6ChoiIhYF9eXd3en6ChYiHhYJ9enh4en2BhYeHhYJ+enh4eX2BhIeHhoJ/e3l4eXyAhIaHhoN/fHl4eXyAg4aHhoOAfHp5eXx/g4WGhoOAfXp5enx/goWGhYOAfXp5enx/goSGhYOBfnt6ent+gYSFhYSBfnt6ent+gYOFhYSBfnx6ent+gIOEhYSBf3x7ent9gIKEhISCf317e3x9gIKEhIOCf318e3x9f4KDhIOCgH58e3x9f4GDhIOCgH58fHx9f4GCg4OCgH59fHx9f4GCg4OCgH99fHx9f4CCg4OCgH99fX19f4CBgoKCgH9+fX19foCBgoKCgX9+fX1+foCBgoKBgX9+fn1+fn+AgYGBgIB/fn5+f3+AgYGBgIB/fn5+f3+AgYGBgIB/f35+f3+AgIGAgIB/f39/f3+AgICAgIB/f39/f3+AgICAgIB/f39/f3+AgICAgIB/';
	var noticeAudio = null;
	var lastNoticeSoundAt = 0;
	var NOTICE_SOUND_COOLDOWN_MS = 600;
	var noticeTimer = 0;
	var ACTION_SPOTLIGHT_DURATION_MS = 2200;
	var actionSpotlight = {
		target: null,
		dock: null,
		backdrop: null,
		timer: 0,
		escapeHandler: null
	};

	function feedbackReducedMotion(root) {
		return !!(root && root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function successFeedback(type, runtime) {
		var root = runtimeWindow(runtime);
		if ((type !== 'cart' && type !== 'wishlist') || !root || !root.document) { return false; }
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

	function playNoticeSound(runtime) {
		var root = runtimeWindow(runtime);
		if (!root || !root.document || root.document.visibilityState !== 'visible' || typeof root.Audio !== 'function') { return false; }
		var now = Date.now();
		if (now - lastNoticeSoundAt < NOTICE_SOUND_COOLDOWN_MS) { return true; }
		try {
			if (!noticeAudio) {
				noticeAudio = new root.Audio(NOTICE_SOUND_URI);
				noticeAudio.preload = 'auto';
				noticeAudio.volume = 0.12;
				noticeAudio.loop = false;
			}
			lastNoticeSoundAt = now;
			noticeAudio.currentTime = 0;
			var playback = noticeAudio.play();
			if (playback && typeof playback.catch === 'function') { playback.catch(function () {}); }
			return true;
		} catch (e) {
			return false;
		}
	}

	function showTransientNotice(message, options) {
		var root = runtimeWindow();
		if (!root || !root.document || !root.document.body || !message) { return null; }
		var region = root.document.querySelector('.gloskin-ui1-toast-region');
		if (!region) {
			region = root.document.createElement('div');
			region.className = 'gloskin-ui1-toast-region';
			region.setAttribute('role', 'status');
			region.setAttribute('aria-live', 'polite');
			region.setAttribute('aria-atomic', 'true');
			root.document.body.appendChild(region);
		}
		region.textContent = message;
		region.classList.add('is-visible');
		if (noticeTimer) { root.clearTimeout(noticeTimer); }
		noticeTimer = root.setTimeout(function () {
			region.classList.remove('is-visible');
			noticeTimer = 0;
		}, 2200);
		if (options && options.tone) { playNoticeSound(root); }
		return region;
	}

	function dismissActionSpotlight() {
		var root = runtimeWindow();
		if (!root || !root.document) { return; }
		if (actionSpotlight.timer) { root.clearTimeout(actionSpotlight.timer); }
		if (actionSpotlight.target) { actionSpotlight.target.classList.remove('is-action-spotlight-target'); }
		if (actionSpotlight.dock) { actionSpotlight.dock.classList.remove('is-action-spotlight'); }
		if (actionSpotlight.backdrop && actionSpotlight.backdrop.parentNode) {
			actionSpotlight.backdrop.parentNode.removeChild(actionSpotlight.backdrop);
		}
		if (actionSpotlight.escapeHandler) {
			root.document.removeEventListener('keydown', actionSpotlight.escapeHandler);
		}
		actionSpotlight = { target: null, dock: null, backdrop: null, timer: 0, escapeHandler: null };
	}

	function focusWithoutScroll(target) {
		if (!target || typeof target.focus !== 'function') { return; }
		try { target.focus({ preventScroll: true }); }
		catch (e) { target.focus(); }
	}

	function showActionSpotlight(target) {
		var root = runtimeWindow();
		if (!root || !root.document || !root.document.body || !target) { return false; }
		dismissActionSpotlight();
		var backdrop = root.document.createElement('div');
		backdrop.className = 'gloskin-ui1-action-spotlight__backdrop';
		backdrop.setAttribute('aria-hidden', 'true');
		root.document.body.appendChild(backdrop);
		var dock = target.closest ? target.closest('[data-gloskin-purchase-dock]') : null;
		target.classList.add('is-action-spotlight-target');
		if (dock) { dock.classList.add('is-action-spotlight'); }
		actionSpotlight.target = target;
		actionSpotlight.dock = dock;
		actionSpotlight.backdrop = backdrop;
		actionSpotlight.escapeHandler = function (event) {
			if (event.key === 'Escape') { dismissActionSpotlight(); }
		};
		root.document.addEventListener('keydown', actionSpotlight.escapeHandler);
		focusWithoutScroll(target);
		actionSpotlight.timer = root.setTimeout(dismissActionSpotlight, ACTION_SPOTLIGHT_DURATION_MS);
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
			cancelPending(id);
			previousFocus = document.activeElement;
			current = id;
			el.hidden = false;
			void el.offsetWidth;
			window.requestAnimationFrame(function () {
				el.setAttribute('aria-hidden', 'false');
			});
			document.documentElement.classList.add('gloskin-ui1-overlay-open');
			holdStickyNav();
			setTriggersExpanded(id, true);
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

		var authFromDrawer = drawer.querySelector('[data-gloskin-auth-open-from-drawer]');
		if (authFromDrawer) {
			authFromDrawer.addEventListener('click', function (event) {
				event.preventDefault();
				closeDrawer();
				setTimeout(function () { overlay.open('auth'); }, 80);
			});
		}

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
	 * Top-level desktop nav bubble.
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
		var naturalSurfaceHeight = surface.offsetHeight;

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

	function initCompactSticky() {
		var header = document.querySelector('.gloskin-ui1-header');
		var navRow = document.querySelector('.gloskin-ui1-header__nav-row');
		var compactBrand = document.querySelector('.gloskin-ui1-compact-brand');
		var compactZone = document.querySelector('.gloskin-ui1-header__zone--compact');
		if (!header || !navRow || typeof IntersectionObserver === 'undefined') { return; }

		function setCompact(active) {
			navRow.classList.toggle('is-compact-sticky', active);
			if (compactBrand) { compactBrand.inert = !active; }
			if (compactZone) { compactZone.inert = !active; }
		}

		var observer = new IntersectionObserver(function (entries) {
			var entry = entries[entries.length - 1];
			setCompact(!entry.isIntersecting);
		}, { threshold: 0 });
		observer.observe(header);
	}

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
		document.addEventListener('keydown', function (e) {
			var id = overlay.active();
			if (!id) { return; }
			var el = document.querySelector('[data-gloskin-overlay="' + id + '"]');
			if (!el) { return; }
			var panel = el.querySelector('[role="dialog"]') || el;
			trapFocus(panel, e);
		});
	}

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

	function initAuth() {
		var auth = document.querySelector('[data-gloskin-overlay="auth"]');
		if (!auth) { return; }
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
		applyCommerceRemovePresentation(document);

		var triggers = document.querySelectorAll('[data-gloskin-cart-open]');
		Array.prototype.forEach.call(triggers, function (trigger) {
			trigger.addEventListener('click', function () { overlay.open('cart'); });
		});

		document.body.addEventListener('click', function (event) {
			var button = event.target.closest && event.target.closest('.ajax_add_to_cart');
			if (!button) { return; }
			button.setAttribute('aria-busy', 'true');
			window.setTimeout(function () {
				if (button.getAttribute('aria-busy') === 'true') { button.setAttribute('aria-busy', 'false'); }
			}, 12000);
		}, true);

		if (window.jQuery) {
			window.jQuery(document.body).on('added_to_cart', function (event, fragments, cartHash, $button) {
				if ($button && $button.length) { $button.attr('aria-busy', 'false'); }
				overlay.open('cart');
				window.requestAnimationFrame(function () {
					applyCommerceRemovePresentation(document);
					animateCommerceBadgeDelta('cart');
					successFeedback('cart');
				});
			});
		}

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
				window.requestAnimationFrame(function () {
					applyCommerceRemovePresentation(document);
					animateCommerceBadgeDelta('cart');
				});
			});
		}
	}

	/* -----------------------------------------------------------------
	 * SP-003/SP-004 -- Woo-owned AJAX add-to-cart bridge.
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

	function resolveWooSubmitter(form, event) {
		if (event && event.submitter) { return event.submitter; }
		return form.querySelector('.single_add_to_cart_button[type="submit"]') || form.querySelector('.single_add_to_cart_button');
	}

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

	function normalizeAddToCartPayload(formData, submitter) {
		var variationId = parseInt(formData.get('variation_id'), 10) || 0;
		var existingProductId = formData.get('product_id');
		var simpleProductId = !existingProductId && submitter && submitter.name === 'add-to-cart' && submitter.value ? submitter.value : '';

		/* The native Woo button keeps name="add-to-cart" for untouched
		 * fallback submission. The wc-ajax projection must never carry that
		 * field, otherwise WC_Form_Handler and WC_AJAX can both mutate the
		 * cart during the same HTTP request. */
		formData.delete('add-to-cart');

		if (variationId > 0) {
			formData.set('product_id', String(variationId));
		} else if (!existingProductId && simpleProductId) {
			formData.set('product_id', simpleProductId);
		}
		return formData;
	}

	function buildAddToCartPayload(form, submitter) {
		return normalizeAddToCartPayload(new FormData(form), submitter);
	}

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

	var wooAjaxInFlightForms = typeof WeakSet === 'function' ? new WeakSet() : null;
	var wooAjaxBoundForms = typeof WeakSet === 'function' ? new WeakSet() : null;

	function isWooAjaxFormInFlight(form) {
		if (!form) { return false; }
		return wooAjaxInFlightForms ? wooAjaxInFlightForms.has(form) : form.getAttribute('data-gloskin-ajax-in-flight') === '1';
	}

	function setWooAjaxFormInFlight(form, busy) {
		if (!form) { return; }
		if (wooAjaxInFlightForms) {
			if (busy) { wooAjaxInFlightForms.add(form); }
			else { wooAjaxInFlightForms.delete(form); }
			return;
		}
		if (busy) { form.setAttribute('data-gloskin-ajax-in-flight', '1'); }
		else { form.removeAttribute('data-gloskin-ajax-in-flight'); }
	}

	function claimWooAjaxSubmit(event, form, lifecycleFactory) {
		if (!event || !form) { return false; }
		if (form.getAttribute('data-gloskin-ajax-bypass') === '1') {
			form.removeAttribute('data-gloskin-ajax-bypass');
			return false;
		}

		var submitter = resolveWooSubmitter(form, event);
		if (isWooAjaxFormInFlight(form) || isWooSubmitBusy(submitter)) {
			event.preventDefault();
			event.stopImmediatePropagation();
			return true;
		}
		if (!shouldInterceptWooSubmit(form, submitter) || !hasWooAjaxBridge()) {
			return false;
		}

		event.preventDefault();
		event.stopImmediatePropagation();

		var lifecycle = typeof lifecycleFactory === 'function' ? (lifecycleFactory(submitter) || {}) : {};
		var originalSuccess = lifecycle.onSuccess;
		var originalFailure = lifecycle.onFailure;
		setWooAjaxFormInFlight(form, true);

		var claimed = ajaxAddToCart(form, submitter, {
			redirectOnError: lifecycle.redirectOnError,
			onSuccess: function (response) {
				setWooAjaxFormInFlight(form, false);
				if (typeof originalSuccess === 'function') { originalSuccess(response); }
			},
			onFailure: function (response, error) {
				setWooAjaxFormInFlight(form, false);
				if (typeof originalFailure === 'function') { originalFailure(response, error); }
			}
		});

		if (!claimed) {
			setWooAjaxFormInFlight(form, false);
			nativeFallbackSubmit(form, submitter);
		}
		return true;
	}

	function bindWooAjaxSubmitOwner(form, lifecycleFactory) {
		if (!form) { return false; }
		if ((wooAjaxBoundForms && wooAjaxBoundForms.has(form)) || form.getAttribute('data-gloskin-ajax-owner-bound') === '1') {
			return true;
		}
		form.setAttribute('data-gloskin-ajax-owner-bound', '1');
		if (wooAjaxBoundForms) { wooAjaxBoundForms.add(form); }

		var button = form.querySelector('.single_add_to_cart_button');
		if (button && button.getAttribute('data-gloskin-ajax-click-owner') !== '1') {
			button.setAttribute('data-gloskin-ajax-click-owner', '1');
			button.addEventListener('click', function (event) { event.stopPropagation(); });
		}

		form.addEventListener('submit', function (event) {
			claimWooAjaxSubmit(event, form, lifecycleFactory);
		});
		return true;
	}

	function initSingleProductAjax() {
		if (!document.body.classList.contains('single-product')) { return; }
		var form = document.querySelector('[data-gloskin-purchase-dock] form.cart');
		if (!form || !isSupportedSingleProductAjaxForm(form)) { return; }
		bindWooAjaxSubmitOwner(form, function (submitter) {
			return {
				onSuccess: function () { handleSingleProductAddToCartSuccess(submitter); }
			};
		});
	}

	function handleSingleProductAddToCartSuccess(submitter) {
		if (submitter && submitter.hasAttribute('data-gloskin-buy-now-redirect')) {
			submitter.removeAttribute('data-gloskin-buy-now-redirect');
			var cartUrl = (window.gloskinData || {}).cartUrl;
			if (cartUrl) {
				window.location.href = cartUrl;
				return;
			}
		}
	}

	/* -----------------------------------------------------------------
	 * SP-004 -- one reusable Variable Product Modal for catalog + PDP.
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
		var currentMode = '';
		var currentForm = null;
		var labelSequence = 0;

		function canOpenQuickAdd() {
			return hasWooVariationRuntime() && typeof fetch === 'function';
		}

		function attributeSelects(form) {
			return form ? Array.prototype.slice.call(form.querySelectorAll('select[name^="attribute_"]')) : [];
		}

		function getNativeSubmit(form) {
			return form ? form.querySelector('.single_add_to_cart_button') : null;
		}

		function getNativeQuantityInput(form) {
			return form ? form.querySelector('.quantity input.qty') : null;
		}

		function getNativeQuantity(form) {
			var input = getNativeQuantityInput(form);
			return input ? input.closest('.quantity') : null;
		}

		function selectLabel(select) {
			if (!select) { return null; }
			var row = select.closest('tr');
			var label = row ? row.querySelector('label') : null;
			if (!label && select.id) { label = document.querySelector('label[for="' + select.id + '"]'); }
			if (label && !label.id) {
				labelSequence += 1;
				label.id = 'gloskin-variable-label-' + labelSequence;
			}
			return label;
		}

		function ensureSelectKey(select, index) {
			if (!select.dataset.gloskinVariableKey) {
				select.dataset.gloskinVariableKey = String(index + 1);
			}
			return select.dataset.gloskinVariableKey;
		}

		function optionForValue(select, value) {
			for (var i = 0; i < select.options.length; i += 1) {
				if (select.options[i].value === value) { return select.options[i]; }
			}
			return null;
		}

		function chipOptions(select) {
			return select ? Array.prototype.filter.call(select.options, function (option) {
				return '' !== option.value;
			}) : [];
		}

		function selectCanEnhance(select) {
			return !!(select && chipOptions(select).length);
		}

		function allSelectsCanEnhance(selects) {
			if (!selects.length) { return false; }
			for (var i = 0; i < selects.length; i += 1) {
				if (!selectCanEnhance(selects[i])) { return false; }
			}
			return true;
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

		function enhanceQuantityControls(quantity) {
			if (!quantity || quantity.dataset.gloskinQtyEnhanced === '1') { return; }
			var input = quantity.querySelector('input.qty');
			if (!input) { return; }
			quantity.classList.add('gloskin-ui1-quickadd__qty-control');
			var minus = document.createElement('button');
			minus.type = 'button';
			minus.className = 'gloskin-ui1-quickadd__qty-minus';
			minus.setAttribute('aria-label', 'Kurangi jumlah');
			minus.textContent = '−';
			var plus = document.createElement('button');
			plus.type = 'button';
			plus.className = 'gloskin-ui1-quickadd__qty-plus';
			plus.setAttribute('aria-label', 'Tambah jumlah');
			plus.textContent = '+';
			input.insertAdjacentElement('beforebegin', minus);
			input.insertAdjacentElement('afterend', plus);
			quantity.dataset.gloskinQtyEnhanced = '1';
		}

		function stepQuantityInput(input, direction) {
			if (!input || input.disabled || input.readOnly) { return; }
			var before = input.value;
			var stepped = false;
			if (typeof input.stepUp === 'function' && typeof input.stepDown === 'function') {
				try {
					if (direction > 0) { input.stepUp(); } else { input.stepDown(); }
					stepped = true;
				} catch (error) { stepped = false; }
			}
			if (!stepped) {
				var step = parseFloat(input.step);
				if (!isFinite(step) || step <= 0) { step = 1; }
				var min = input.min !== '' && input.min != null ? parseFloat(input.min) : -Infinity;
				var max = input.max !== '' && input.max != null ? parseFloat(input.max) : Infinity;
				if (!isFinite(min)) { min = -Infinity; }
				if (!isFinite(max)) { max = Infinity; }
				var current = parseFloat(input.value);
				if (isNaN(current)) { current = isFinite(min) ? min : 0; }
				var next = current + (direction * step);
				if (next < min) { next = min; }
				if (next > max) { next = max; }
				next = Math.round(next * 1e6) / 1e6;
				input.value = next;
			}
			if (input.value === before) { return; }
			input.dispatchEvent(new Event('input', { bubbles: true }));
			input.dispatchEvent(new Event('change', { bubbles: true }));
		}

		function createChipGroup(select, index, host, includeHeading) {
			if (!select || !host) { return null; }
			var options = chipOptions(select);
			if (!options.length) { return null; }
			var label = selectLabel(select);
			var key = ensureSelectKey(select, index);

			if (includeHeading) {
				var heading = document.createElement('span');
				heading.className = 'gloskin-ui1-variable-field__label';
				heading.textContent = label ? label.textContent.trim() : select.name.replace(/^attribute_/, '').replace(/[_-]+/g, ' ');
				host.appendChild(heading);
			}

			var group = document.createElement('div');
			group.className = 'gloskin-ui1-variable-chips';
			group.setAttribute('role', 'group');
			group.setAttribute('tabindex', '-1');
			group.setAttribute('data-gloskin-variable-select-key', key);
			if (label && label.id) { group.setAttribute('aria-labelledby', label.id); }

			options.forEach(function (option) {
				var chip = document.createElement('button');
				chip.type = 'button';
				chip.className = 'gloskin-ui1-variable-chip';
				chip.textContent = option.textContent.trim();
				chip.setAttribute('data-gloskin-variable-chip', option.value);
				chip.setAttribute('aria-pressed', option.selected ? 'true' : 'false');
				chip.disabled = !!option.disabled;
				group.appendChild(chip);
			});

			host.appendChild(group);
			return group;
		}

		function renderVariableFields(form, host) {
			if (!form || !host) { return false; }
			var selects = attributeSelects(form);
			if (!allSelectsCanEnhance(selects)) { return false; }
			var fields = [];
			for (var i = 0; i < selects.length; i += 1) {
				var field = document.createElement('div');
				field.className = 'gloskin-ui1-variable-field';
				host.appendChild(field);
				if (!createChipGroup(selects[i], i, field, true)) {
					fields.forEach(function (created) { created.remove(); });
					field.remove();
					return false;
				}
				fields.push(field);
			}
			return true;
		}

		function syncChipPresentation(form) {
			attributeSelects(form).forEach(function (select, index) {
				var key = ensureSelectKey(select, index);
				var groups = body.querySelectorAll('[data-gloskin-variable-select-key="' + key + '"]');
				Array.prototype.forEach.call(groups, function (group) {
					Array.prototype.forEach.call(group.querySelectorAll('[data-gloskin-variable-chip]'), function (chip) {
						var value = chip.getAttribute('data-gloskin-variable-chip') || '';
						var option = optionForValue(select, value);
						chip.setAttribute('aria-pressed', select.value === value ? 'true' : 'false');
						chip.disabled = !option || !!option.disabled;
					});
				});
			});
		}

		function quantityHiddenByWoo(form) {
			var quantity = getNativeQuantity(form);
			return !quantity || quantity.hidden || 'none' === quantity.style.display;
		}

		function selectedSummary(form) {
			var selects = attributeSelects(form);
			if (!selects.length) { return ''; }
			var values = [];
			for (var i = 0; i < selects.length; i += 1) {
				if (!selects[i].value) { return ''; }
				var option = optionForValue(selects[i], selects[i].value);
				values.push(option ? option.textContent.trim() : selects[i].value);
			}
			var submit = getNativeSubmit(form);
			var variation = form.querySelector('input.variation_id, input[name="variation_id"]');
			if (!submit || submit.disabled || submit.classList.contains('disabled') || !variation || !(parseInt(variation.value, 10) > 0)) { return ''; }
			if (1 === values.length) {
				var label = selectLabel(selects[0]);
				return (label ? label.textContent.trim() + ': ' : '') + values[0] + ' · Ubah Varian';
			}
			return values.join(' · ') + ' · Ubah Varian';
		}

		function syncPdpTrigger(form) {
			var dock = form ? form.closest('[data-gloskin-purchase-dock]') : null;
			var trigger = dock ? dock.querySelector('[data-gloskin-variable-pdp-trigger]') : null;
			var summary = selectedSummary(form);
			if (trigger) { trigger.textContent = summary || 'Pilih Varian'; }
			if (summary) { dismissActionSpotlight(); }
		}

		function syncVariableStatePresentation(form, target) {
			if (!target) { return; }
			target.innerHTML = '';
			var nativeState = form ? form.querySelector('.woocommerce-variation.single_variation') : null;
			if (!nativeState) { return; }
			['.woocommerce-variation-price', '.woocommerce-variation-availability'].forEach(function (selector) {
				var node = nativeState.querySelector(selector);
				if (node) { target.appendChild(node.cloneNode(true)); }
			});
		}

		function syncModalPresentation(form) {
			if (!form) { return; }
			syncChipPresentation(form);
			syncPdpTrigger(form);
			var catalogAction = form.querySelector('.woocommerce-variation-add-to-cart.variations_button');
			if (catalogAction) { catalogAction.classList.toggle('is-quantity-hidden', quantityHiddenByWoo(form)); }
			var stateTarget = form.querySelector('[data-gloskin-variable-state]');
			if ('pdp' === currentMode && currentForm === form) {
				var actions = body.querySelector('[data-gloskin-variable-actions]');
				if (actions) { actions.classList.toggle('is-quantity-hidden', quantityHiddenByWoo(form)); }
				var qtyValue = body.querySelector('[data-gloskin-variable-qty-value]');
				var input = getNativeQuantityInput(form);
				if (qtyValue && input) { qtyValue.textContent = input.value; }
				stateTarget = body.querySelector('[data-gloskin-variable-state]');
			}
			syncVariableStatePresentation(form, stateTarget);
		}

		function bindSelectionSync(form) {
			if (!form || '1' === form.dataset.gloskinVariableSync) { return; }
			form.dataset.gloskinVariableSync = '1';
			form.addEventListener('change', function (event) {
				if (event.target && event.target.matches && (event.target.matches('select[name^="attribute_"]') || event.target.matches('input.qty'))) {
					syncModalPresentation(form);
				}
			});
			form.addEventListener('input', function (event) {
				if (event.target && event.target.matches && event.target.matches('input.qty')) { syncModalPresentation(form); }
			});
			if (window.jQuery) {
				window.jQuery(form).on('woocommerce_update_variation_values.gloskinVariableModal reset_data.gloskinVariableModal found_variation.gloskinVariableModal', function () {
					syncModalPresentation(form);
				});
			}
		}

		function bindForm(form) {
			if (!form.classList.contains('variations_form') || !hasWooVariationRuntime()) { return false; }
			window.jQuery(form).wc_variation_form();
			return true;
		}

		function unresolvedSelect(form) {
			var selects = attributeSelects(form);
			for (var i = 0; i < selects.length; i += 1) {
				if (!selects[i].value) { return selects[i]; }
			}
			return null;
		}

		function focusSelectGroup(select) {
			if (!select) { return; }
			var key = select.dataset.gloskinVariableKey;
			var group = key ? body.querySelector('[data-gloskin-variable-select-key="' + key + '"]') : null;
			if (group && typeof group.focus === 'function') { group.focus(); }
		}

		function setSubmitProxyBusy(busy) {
			var proxy = body.querySelector('[data-gloskin-variable-submit-proxy]');
			if (!proxy) { return; }
			proxy.classList.toggle('is-loading', !!busy);
			if (busy) { proxy.setAttribute('aria-busy', 'true'); }
			else { proxy.removeAttribute('aria-busy'); }
		}

		function handleProxySubmit(form) {
			if (!form) { return; }
			var submit = getNativeSubmit(form);
			if (submit && (submit.getAttribute('aria-busy') === 'true' || isWooAjaxFormInFlight(form))) { return; }
			var unresolved = unresolvedSelect(form);
			if (unresolved) {
				showTransientNotice('Pilih varian terlebih dahulu.', { tone: true });
				focusSelectGroup(unresolved);
				return;
			}
			if (!submit || submit.disabled || submit.classList.contains('disabled')) {
				showTransientNotice('Varian yang dipilih belum tersedia.', { tone: true });
				return;
			}
			setSubmitProxyBusy(true);
			submit.click();
		}

		function addCatalogPresentation(form) {
			var selects = attributeSelects(form);
			var submit = getNativeSubmit(form);
			var action = submit ? (submit.closest('.woocommerce-variation-add-to-cart.variations_button') || submit.parentNode) : null;
			var nativeState = form.querySelector('.woocommerce-variation.single_variation');
			var nativeFields = form.querySelector('table.variations');
			if (!allSelectsCanEnhance(selects) || !submit || !action || !nativeState || !nativeState.parentNode || !nativeFields || !nativeFields.parentNode) { return false; }

			var fields = document.createElement('div');
			fields.className = 'gloskin-ui1-variable-modal__fields';
			fields.setAttribute('data-gloskin-variable-fields', '');
			nativeFields.parentNode.insertBefore(fields, nativeFields);
			if (!renderVariableFields(form, fields)) {
				fields.remove();
				return false;
			}

			var proxy = action.querySelector('[data-gloskin-variable-submit-proxy]');
			if (!proxy) {
				proxy = document.createElement('button');
				proxy.type = 'button';
				proxy.className = 'gloskin-ui1-variable-modal__cta';
				proxy.setAttribute('data-gloskin-variable-submit-proxy', '');
				proxy.textContent = cartCtaLabel();
				action.appendChild(proxy);
			}

			var stateTarget = form.querySelector('[data-gloskin-variable-state]');
			if (!stateTarget) {
				stateTarget = document.createElement('div');
				stateTarget.className = 'gloskin-ui1-variable-modal__variation-state';
				stateTarget.setAttribute('data-gloskin-variable-state', '');
				nativeState.insertAdjacentElement('afterend', stateTarget);
			}

			var host = document.createElement('div');
			host.setAttribute('data-gloskin-variable-native-host', '');
			nativeFields.parentNode.insertBefore(host, nativeFields);
			host.appendChild(nativeFields);
			host.appendChild(nativeState);
			var resetLink = form.querySelector('.reset_variations');
			if (resetLink) { host.appendChild(resetLink); }

			selects.forEach(function (select) { select.classList.add('gloskin-ui1-variable-select--enhanced'); });
			submit.textContent = cartCtaLabel();
			submit.classList.add('gloskin-ui1-variable-native-submit--enhanced');
			nativeState.classList.add('gloskin-ui1-variable-native-state--enhanced');
			nativeState.hidden = true;
			nativeFields.classList.add('gloskin-ui1-variable-native-fields--enhanced');
			nativeFields.hidden = true;
			host.hidden = true;
			enhanceQuantityControls(form.querySelector('.quantity'));
			bindSelectionSync(form);
			form.classList.add('gloskin-ui1-variable-catalog-enhanced');
			syncModalPresentation(form);
			return true;
		}

		function bindCatalogMutationOwner(form) {
			return bindWooAjaxSubmitOwner(form, function () {
				clearMutationStatus();
				return {
					redirectOnError: false,
					onFailure: function (response) { renderMutationError(response); }
				};
			});
		}

		function render(data) {
			currentMode = 'catalog';
			currentForm = null;
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
			if (form) {
				currentForm = form;
				if (bindForm(form) && addCatalogPresentation(form)) {
					bindCatalogMutationOwner(form);
				}
			}
		}

		function open(productId, productUrl) {
			currentId = productId;
			currentUrl = productUrl || '';
			currentMode = 'catalog';
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

		function afterCurrentStack(callback) {
			if (typeof queueMicrotask === 'function') { queueMicrotask(callback); return; }
			if (typeof Promise === 'function') { Promise.resolve().then(callback); return; }
			window.setTimeout(callback, 0);
		}

		function identityPartsFromPdp() {
			var dock = document.querySelector('[data-gloskin-purchase-dock]');
			var product = dock && dock.closest ? dock.closest('div.product') : null;
			if (!dock || !product) { return null; }
			var galleryImage = product.querySelector('.woocommerce-product-gallery__image img, .woocommerce-product-gallery img.wp-post-image, img.wp-post-image');
			var dockIdentity = dock.querySelector('[data-gloskin-purchase-identity]');
			var title = (dockIdentity && dockIdentity.querySelector('.gloskin-ui1-purchase-dock__title')) || product.querySelector('.product_title');
			var price = (dockIdentity && dockIdentity.querySelector('.gloskin-ui1-purchase-dock__price')) || product.querySelector('.summary .price');
			return { image: galleryImage || null, name: title ? title.textContent.trim() : '', priceHtml: price ? price.innerHTML : '' };
		}

		function renderPdpIdentityLikeCatalog() {
			if (modal.hidden) { return; }
			var pdp = body.querySelector('.gloskin-ui1-variable-modal__pdp');
			var oldIdentity = pdp ? pdp.querySelector('.gloskin-ui1-variable-modal__identity') : null;
			if (!oldIdentity) { return; }
			var parts = identityPartsFromPdp();
			if (!parts) { return; }

			var identity = document.createElement('div');
			identity.className = 'gloskin-ui1-quickadd__product gloskin-ui1-variable-modal__identity-converged';
			identity.setAttribute('data-gloskin-variable-modal-identity', '');
			if (parts.image) {
				var image = parts.image.cloneNode(true);
				image.removeAttribute('id');
				image.className = 'gloskin-ui1-quickadd__image';
				identity.appendChild(image);
			} else {
				var placeholder = document.createElement('span');
				placeholder.className = 'gloskin-ui1-quickadd__image gloskin-ui1-quickadd__image--placeholder';
				placeholder.setAttribute('aria-hidden', 'true');
				identity.appendChild(placeholder);
			}
			var copy = document.createElement('div');
			var name = document.createElement('strong');
			name.textContent = parts.name;
			copy.appendChild(name);
			if (parts.priceHtml) {
				var price = document.createElement('div');
				price.className = 'gloskin-ui1-product-price';
				price.innerHTML = parts.priceHtml;
				copy.appendChild(price);
			}
			identity.appendChild(copy);
			oldIdentity.replaceWith(identity);
		}

		function failOpenPdp(form, dock) {
			if (form) { form.classList.remove('gloskin-ui1-variable-pdp-enhanced'); }
			var trigger = dock ? dock.querySelector('[data-gloskin-variable-pdp-trigger]') : null;
			if (trigger) { trigger.remove(); }
			if ('pdp' === currentMode && currentForm === form) {
				currentMode = '';
				currentForm = null;
			}
			return false;
		}

		function preparePdp(form, dock) {
			if (!form || !dock || !form.classList.contains('variations_form') || 1 !== dock.querySelectorAll('form.cart').length || dock.querySelector('form.cart') !== form) { return false; }
			var action = dock.querySelector('[data-gloskin-purchase-action]');
			var selects = attributeSelects(form);
			if (!action || !getNativeSubmit(form) || !allSelectsCanEnhance(selects)) { return failOpenPdp(form, dock); }

			selects.forEach(function (select, index) {
				ensureSelectKey(select, index);
				selectLabel(select);
			});

			var trigger = action.querySelector('[data-gloskin-variable-pdp-trigger]');
			if (!trigger) {
				trigger = document.createElement('button');
				trigger.type = 'button';
				trigger.className = 'gloskin-ui1-variable-pdp-trigger';
				trigger.setAttribute('data-gloskin-variable-pdp-trigger', '');
				trigger.textContent = 'Pilih Varian';
				action.insertBefore(trigger, action.firstChild);
				trigger.addEventListener('click', function () {
					dismissActionSpotlight();
					if (renderPdp(form, dock)) {
						overlay.open('quickadd');
						afterCurrentStack(renderPdpIdentityLikeCatalog);
					}
				});
			}

			bindSelectionSync(form);
			form.classList.add('gloskin-ui1-variable-pdp-enhanced');
			syncPdpTrigger(form);
			return true;
		}

		function renderPdp(form, dock) {
			dismissActionSpotlight();
			if (!preparePdp(form, dock)) { return false; }
			currentMode = 'pdp';
			currentForm = form;
			currentId = null;
			currentUrl = '';
			var identity = dock.querySelector('[data-gloskin-purchase-identity]');
			body.innerHTML = '<div class="gloskin-ui1-variable-modal__pdp">' +
				'<div class="gloskin-ui1-variable-modal__identity">' + (identity ? identity.innerHTML : '') + '</div>' +
				'<div class="gloskin-ui1-variable-modal__fields" data-gloskin-variable-fields></div>' +
				'<div class="gloskin-ui1-variable-modal__variation-state" data-gloskin-variable-state></div>' +
				'<div class="gloskin-ui1-variable-modal__actions" data-gloskin-variable-actions></div>' +
				'</div>';

			var fields = body.querySelector('[data-gloskin-variable-fields]');
			if (!fields || !renderVariableFields(form, fields)) {
				body.innerHTML = '';
				return failOpenPdp(form, dock);
			}

			var actions = body.querySelector('[data-gloskin-variable-actions]');
			if (!actions) {
				body.innerHTML = '';
				return failOpenPdp(form, dock);
			}
			if (getNativeQuantityInput(form)) {
				var qty = document.createElement('div');
				qty.className = 'gloskin-ui1-variable-modal__qty-proxy';
				qty.innerHTML = '<button type="button" data-gloskin-variable-qty-minus aria-label="Kurangi jumlah">−</button>' +
					'<span class="gloskin-ui1-variable-modal__qty-value" data-gloskin-variable-qty-value></span>' +
					'<button type="button" data-gloskin-variable-qty-plus aria-label="Tambah jumlah">+</button>';
				actions.appendChild(qty);
			}
			var proxy = document.createElement('button');
			proxy.type = 'button';
			proxy.className = 'gloskin-ui1-variable-modal__cta';
			proxy.setAttribute('data-gloskin-variable-submit-proxy', '');
			proxy.textContent = cartCtaLabel();
			actions.appendChild(proxy);
			syncModalPresentation(form);
			return true;
		}

		function notifyPdpRequirement(form) {
			var unresolved = unresolvedSelect(form);
			if (unresolved) {
				showTransientNotice('Pilih varian terlebih dahulu.', { tone: true });
				focusSelectGroup(unresolved);
				return;
			}
			showTransientNotice('Varian yang dipilih belum tersedia.', { tone: true });
		}

		body.addEventListener('click', function (event) {
			var chip = event.target.closest ? event.target.closest('[data-gloskin-variable-chip]') : null;
			if (chip && currentForm) {
				var group = chip.closest('[data-gloskin-variable-select-key]');
				var key = group ? group.getAttribute('data-gloskin-variable-select-key') : '';
				var select = null;
				attributeSelects(currentForm).some(function (candidate, index) {
					if (ensureSelectKey(candidate, index) === key) { select = candidate; return true; }
					return false;
				});
				if (select && !chip.disabled) {
					event.preventDefault();
					select.value = chip.getAttribute('data-gloskin-variable-chip') || '';
					select.dispatchEvent(new Event('change', { bubbles: true }));
				}
				return;
			}

			var minusButton = event.target.closest ? event.target.closest('.gloskin-ui1-quickadd__qty-minus') : null;
			var plusButton = event.target.closest ? event.target.closest('.gloskin-ui1-quickadd__qty-plus') : null;
			if (minusButton || plusButton) {
				var control = (minusButton || plusButton).closest('.gloskin-ui1-quickadd__qty-control');
				var input = control ? control.querySelector('input.qty') : null;
				if (!input) { return; }
				event.preventDefault();
				stepQuantityInput(input, minusButton ? -1 : 1);
				return;
			}

			var pdpMinus = event.target.closest ? event.target.closest('[data-gloskin-variable-qty-minus]') : null;
			var pdpPlus = event.target.closest ? event.target.closest('[data-gloskin-variable-qty-plus]') : null;
			if ((pdpMinus || pdpPlus) && 'pdp' === currentMode && currentForm) {
				event.preventDefault();
				stepQuantityInput(getNativeQuantityInput(currentForm), pdpMinus ? -1 : 1);
				syncModalPresentation(currentForm);
				return;
			}

			var proxy = event.target.closest ? event.target.closest('[data-gloskin-variable-submit-proxy]') : null;
			if (proxy) {
				event.preventDefault();
				handleProxySubmit(proxy.closest('form.cart') || currentForm);
			}
		});

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

		document.addEventListener('gloskin:purchase-dock-ready', function (event) {
			var detail = event.detail || {};
			preparePdp(detail.form, detail.dock);
		});

		document.addEventListener('gloskin:variable-product-modal-request', function (event) {
			var detail = event.detail || {};
			if ('buy-now' === detail.source) {
				if (!preparePdp(detail.form, detail.dock)) { return; }
				var trigger = detail.dock ? detail.dock.querySelector('[data-gloskin-variable-pdp-trigger]') : null;
				if (!trigger) { return; }
				showTransientNotice('Pilih varian terlebih dahulu.', { tone: true });
				showActionSpotlight(trigger);
				return;
			}
			if (!renderPdp(detail.form, detail.dock)) { return; }
			overlay.open('quickadd');
			notifyPdpRequirement(detail.form);
		});

		if (window.jQuery && document.body) {
			window.jQuery(document.body).on('added_to_cart.gloskinVariableModal wc_fragment_refresh.gloskinVariableModal', function () {
				setSubmitProxyBusy(false);
			});
		}

		var existingDock = document.querySelector('[data-gloskin-purchase-dock][data-gloskin-purchase-composed="true"]');
		var existingForm = existingDock ? existingDock.querySelector('form.variations_form') : null;
		if (existingDock && existingForm) { preparePdp(existingForm, existingDock); }
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
			animateCommerceBadgeDelta('wishlist');
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
						html += '<button class="gloskin-ui1-wishlist-sheet__item-remove gloskin-ui1-action-icon gloskin-ui1-action-icon--danger" type="button" data-gloskin-wishlist-toggle="' + p.id + '" aria-label="Hapus ' + escapeHtml(p.name) + '"><span class="gloskin-ui1-icon-remove" aria-hidden="true"></span></button>';
						html += '</li>';
					}
					html += '</ul>';
					body.innerHTML = html;
					updateBadges();
					animateCommerceBadgeDelta('wishlist');
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
		commerceBadgeLastRendered.wishlist = readCommerceBadgeCount('wishlist', window);
	}

	/* -----------------------------------------------------------------
	 * Hero Background Video (native <video>, Home video-only mode only)
	 * ----------------------------------------------------------------- */
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
			video.muted = true;
			video.defaultMuted = true;
			video.autoplay = true;
			video.loop = true;
			video.playsInline = true;
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

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str || ''));
		return div.innerHTML;
	}

	/**
	 * Skincare product chip filter.
	 * Filters [data-gloskin-product-card] elements by their data-category-slugs attribute
	 * using chip buttons from [data-gloskin-chip-filter].
	 * No-JS: all products visible; chip "" (Semua) shows all.
	 */
	function initSkincareChips() {
		var bar = document.querySelector('[data-gloskin-chip-filter]');
		if (!bar) { return; }
		var chips = bar.querySelectorAll('[data-gloskin-chip]');
		var grid = document.querySelector('[data-gloskin-product-grid]');
		if (!grid || !chips.length) { return; }
		var cards = grid.querySelectorAll('[data-gloskin-product-card]');

		function activateChip(chip) {
			var slug = chip.getAttribute('data-gloskin-chip') || '';
			for (var i = 0; i < chips.length; i++) {
				var isActive = chips[i] === chip;
				chips[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
				chips[i].setAttribute('tabindex', isActive ? '0' : '-1');
				if (isActive) { chips[i].classList.add('is-active'); }
				else { chips[i].classList.remove('is-active'); }
			}
			for (var j = 0; j < cards.length; j++) {
				if ('' === slug) {
					cards[j].hidden = false;
				} else {
					var catSlugs = (cards[j].getAttribute('data-category-slugs') || '').split(' ');
					cards[j].hidden = catSlugs.indexOf(slug) === -1;
				}
			}
		}

		for (var ci = 0; ci < chips.length; ci++) {
			(function (chip) {
				chip.addEventListener('click', function () { activateChip(chip); });
				chip.addEventListener('keydown', function (e) {
					if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activateChip(chip); }
				});
			}(chips[ci]));
		}
		/* Initial state: first chip ("Semua") is already marked active in PHP; show all. */
		for (var k = 0; k < cards.length; k++) { cards[k].hidden = false; }
	}

	/**
	 * Promo carousel controller.
	 * Activates prev/next buttons, dot indicators, and poster thumbnail selectors
	 * on [data-gloskin-promo-carousel]. No autoplay. Keyboard-accessible.
	 * Reduced-motion: instant transitions.
	 */
	function initPromoCarousel() {
		var carousels = document.querySelectorAll('[data-gloskin-promo-carousel]');
		for (var ci = 0; ci < carousels.length; ci++) {
			(function (root) {
				var slides = root.querySelectorAll('[data-gloskin-promo-slide]');
				var dots   = root.querySelectorAll('[data-gloskin-promo-dot]');
				var thumbs = root.querySelectorAll('[data-gloskin-promo-thumb]');
				var prev   = root.querySelector('[data-gloskin-promo-prev]');
				var next   = root.querySelector('[data-gloskin-promo-next]');
				if (!slides.length) { return; }
				var current = 0;

				function activate(index) {
					var n = slides.length;
					index = ((index % n) + n) % n;
					for (var i = 0; i < slides.length; i++) {
						var active = i === index;
						slides[i].hidden = !active;
						slides[i].setAttribute('aria-hidden', active ? 'false' : 'true');
					}
					for (var j = 0; j < dots.length; j++) {
						var dotActive = j === index;
						dots[j].setAttribute('aria-selected', dotActive ? 'true' : 'false');
						dots[j].setAttribute('tabindex', dotActive ? '0' : '-1');
						if (dotActive) { dots[j].classList.add('is-active'); }
						else { dots[j].classList.remove('is-active'); }
					}
					for (var t = 0; t < thumbs.length; t++) {
						var thumbActive = t === index;
						thumbs[t].setAttribute('aria-selected', thumbActive ? 'true' : 'false');
						thumbs[t].setAttribute('tabindex', thumbActive ? '0' : '-1');
						if (thumbActive) { thumbs[t].classList.add('is-active'); }
						else { thumbs[t].classList.remove('is-active'); }
					}
					current = index;
				}

				if (prev) { prev.addEventListener('click', function () { activate(current - 1); }); }
				if (next) { next.addEventListener('click', function () { activate(current + 1); }); }
				for (var di = 0; di < dots.length; di++) {
					(function (dot, idx) {
						dot.addEventListener('click', function () { activate(idx); });
						dot.addEventListener('keydown', function (e) {
							if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activate(idx); }
						});
					}(dots[di], di));
				}
				for (var ti = 0; ti < thumbs.length; ti++) {
					(function (thumb, idx) {
						thumb.addEventListener('click', function () { activate(idx); });
						thumb.addEventListener('keydown', function (e) {
							if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activate(idx); }
						});
					}(thumbs[ti], ti));
				}
				activate(0);
			}(carousels[ci]));
		}
	}

	/**
	 * Testimonial dots controller.
	 * Activates dot indicators on [data-gloskin-testimonials].
	 * No autoplay. Keyboard-accessible.
	 */
	function initTestimonials() {
		var widgets = document.querySelectorAll('[data-gloskin-testimonials]');
		for (var wi = 0; wi < widgets.length; wi++) {
			(function (root) {
				var cards = root.querySelectorAll('[data-gloskin-testimonial]');
				var dots  = root.querySelectorAll('[data-gloskin-testimonial-dot]');
				if (!cards.length) { return; }
				var current = 0;

				function activate(index) {
					var n = cards.length;
					index = ((index % n) + n) % n;
					for (var i = 0; i < cards.length; i++) {
						var active = i === index;
						cards[i].hidden = !active;
						cards[i].setAttribute('aria-hidden', active ? 'false' : 'true');
					}
					for (var j = 0; j < dots.length; j++) {
						var dotActive = j === index;
						dots[j].setAttribute('aria-selected', dotActive ? 'true' : 'false');
						dots[j].setAttribute('tabindex', dotActive ? '0' : '-1');
						if (dotActive) { dots[j].classList.add('is-active'); }
						else { dots[j].classList.remove('is-active'); }
					}
					current = index;
				}

				for (var di = 0; di < dots.length; di++) {
					(function (dot, idx) {
						dot.addEventListener('click', function () { activate(idx); });
						dot.addEventListener('keydown', function (e) {
							if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activate(idx); }
						});
					}(dots[di], di));
				}
				activate(0);
			}(widgets[wi]));
		}
	}

	/**
	 * Treatment band CTA → consultation engine contract.
	 *
	 * Canonical contract: the CTA element owns `data-gloskin-band-path="<path_id>"`.
	 * On click it intercepts navigation, selects the matching consultation path in
	 * the recommendation engine ([data-gloskin-consultation-path="<id>"]), reveals
	 * concerns, and scrolls/focuses correctly.
	 *
	 * No-JS fallback: each CTA href is `/treatments/?path=<id>#consultation` so the
	 * user lands on the consultation section. On page load with ?path=<id> in the URL
	 * the engine auto-selects that path (see the end of this function).
	 *
	 * Reduced-motion safe; no duplicate recommendation engine; no autoplay.
	 */
	function initTreatmentBands() {
		var ctaElements = document.querySelectorAll('[data-gloskin-band-path]');
		for (var bi = 0; bi < ctaElements.length; bi++) {
			(function (cta) {
				var pathId = cta.getAttribute('data-gloskin-band-path') || '';
				if (!pathId) { return; }
				cta.addEventListener('click', function (e) {
					var pathBtn = document.querySelector('[data-gloskin-consultation-path="' + pathId + '"]');
					if (!pathBtn) { return; } /* consultation not on this page; follow href */
					e.preventDefault();
					/* Select the consultation path (triggers the engine's path-selection handler) */
					pathBtn.click();
					/* Scroll to the consultation section */
					var consultation = document.querySelector('[data-gloskin-consultation]');
					var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
					if (consultation) {
						consultation.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
						/* Focus first interactive element inside the engine */
						var focusTarget = consultation.querySelector('button:not([disabled]), input:not([disabled]), [tabindex="0"]');
						if (focusTarget) { focusTarget.focus({ preventScroll: true }); }
					}
				});
			}(ctaElements[bi]));
		}

		/* No-JS fallback auto-select: when the page loaded with ?path=<id> in the URL,
		 * select that path automatically so the user lands in the correct consultation state. */
		if (typeof window !== 'undefined' && window.location && window.location.search) {
			var searchStr = window.location.search;
			var pathMatch = searchStr.match(/(?:^|[?&])path=([^&]+)/);
			if (pathMatch) {
				var autoPathId = decodeURIComponent(pathMatch[1]);
				var autoBtn = document.querySelector('[data-gloskin-consultation-path="' + autoPathId + '"]');
				if (autoBtn) {
					autoBtn.click();
					var autoConsultation = document.querySelector('[data-gloskin-consultation]');
					if (autoConsultation) {
						var autoReduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
						autoConsultation.scrollIntoView({ behavior: autoReduceMotion ? 'auto' : 'smooth', block: 'start' });
					}
				}
			}
		}
	}

	function init() {
		initializeCommerceBadgeCounts();
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
		initPromoCarousel();
		initTestimonials();
		initTreatmentBands();
		initSkincareChips();
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
			isWooAjaxFormInFlight: isWooAjaxFormInFlight,
			claimWooAjaxSubmit: claimWooAjaxSubmit,
			successFeedback: successFeedback,
			initializeCommerceBadgeCounts: initializeCommerceBadgeCounts,
			animateCommerceBadgeDelta: animateCommerceBadgeDelta,
			applyCommerceRemovePresentation: applyCommerceRemovePresentation,
			showTransientNotice: showTransientNotice,
			playNoticeSound: playNoticeSound,
			parseShopCatalogHash: parseShopCatalogHash,
			buildShopCatalogHash: buildShopCatalogHash,
			setupHeroBackgroundVideo: setupHeroBackgroundVideo,
			initHeroBackgroundVideo: initHeroBackgroundVideo,
			initPromoCarousel: initPromoCarousel,
			initTestimonials: initTestimonials,
			initTreatmentBands: initTreatmentBands,
			initSkincareChips: initSkincareChips
		};
	}
}());
