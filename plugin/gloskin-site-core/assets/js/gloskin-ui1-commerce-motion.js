(function (factory) {
	'use strict';
	var api = factory();
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	} else if (typeof window !== 'undefined' && typeof document !== 'undefined') {
		window.GloskinCommerceMotion = api;
		api.boot(window, document);
	}
}(function () {
	'use strict';

	var FLY_CLASS = 'gloskin-ui1-commerce-fly-target';
	var IMPACT_CLASS = 'is-commerce-badge-added';
	var FLY_DURATION_MS = 520;
	var FLY_FAILSAFE_MS = 700;
	var SOURCE_MAX_AGE_MS = 15000;
	var MAX_ACTIVE_PARTICLES = 3;
	var activeParticles = [];
	var pendingSource = { cart: null, wishlist: null };
	var wishlistPending = typeof WeakMap === 'function' ? new WeakMap() : null;

	function runtimeWindow(runtime) {
		if (runtime) { return runtime; }
		return typeof window !== 'undefined' ? window : null;
	}

	function validType(type) {
		return type === 'cart' || type === 'wishlist';
	}

	function finiteNumber(value) {
		return typeof value === 'number' && isFinite(value);
	}

	function snapshotRect(rect) {
		if (!rect) { return null; }
		var left = Number(rect.left);
		var top = Number(rect.top);
		var width = Number(rect.width);
		var height = Number(rect.height);
		if (!finiteNumber(left) || !finiteNumber(top) || !finiteNumber(width) || !finiteNumber(height) || width <= 0 || height <= 0) {
			return null;
		}
		return {
			left: left,
			top: top,
			width: width,
			height: height,
			right: finiteNumber(Number(rect.right)) ? Number(rect.right) : left + width,
			bottom: finiteNumber(Number(rect.bottom)) ? Number(rect.bottom) : top + height
		};
	}

	function measureNode(node) {
		if (!node || typeof node.getBoundingClientRect !== 'function') { return null; }
		try { return snapshotRect(node.getBoundingClientRect()); }
		catch (error) { return null; }
	}

	function renderedRect(node, root) {
		var rect = measureNode(node);
		if (!rect || !root) { return null; }
		var cursor = node;
		while (cursor) {
			if (cursor.hidden || cursor.inert) { return null; }
			if (typeof root.getComputedStyle === 'function') {
				var style = root.getComputedStyle(cursor);
				if (style && (
					style.display === 'none' ||
					style.visibility === 'hidden' ||
					style.visibility === 'collapse' ||
					style.opacity === '0' ||
					style.pointerEvents === 'none'
				)) { return null; }
			}
			cursor = cursor.parentElement || null;
		}
		var viewportWidth = Number(root.innerWidth) || 0;
		var viewportHeight = Number(root.innerHeight) || 0;
		if (viewportWidth > 0 && (rect.right <= 0 || rect.left >= viewportWidth)) { return null; }
		if (viewportHeight > 0 && (rect.bottom <= 0 || rect.top >= viewportHeight)) { return null; }
		return rect;
	}

	function sourceFallback(node) {
		if (!node || typeof node.closest !== 'function') { return null; }
		var host = node.closest('.gloskin-ui1-product-card, div.product, [data-gloskin-purchase-dock], [data-gloskin-overlay="quickadd"]');
		if (!host) { return null; }
		if (typeof host.querySelector === 'function') {
			var media = host.querySelector('.gloskin-ui1-product-card__media img, .woocommerce-product-gallery__image img, .gloskin-ui1-quickadd__image, img.wp-post-image');
			if (media) { return media; }
		}
		return host;
	}

	function sourceRect(source) {
		var direct = source && !source.nodeType && typeof source.getBoundingClientRect !== 'function' ? snapshotRect(source) : measureNode(source);
		if (direct) { return direct; }
		var fallback = sourceFallback(source);
		return fallback ? measureNode(fallback) : null;
	}

	function rememberSource(type, source, runtime) {
		if (!validType(type)) { return false; }
		var rect = sourceRect(source);
		if (!rect) { return false; }
		var root = runtimeWindow(runtime);
		pendingSource[type] = { rect: rect, time: root && root.Date && typeof root.Date.now === 'function' ? root.Date.now() : Date.now() };
		return true;
	}

	function consumeSourceRect(type, source, runtime) {
		var direct = sourceRect(source);
		if (direct) {
			pendingSource[type] = null;
			return direct;
		}
		var remembered = pendingSource[type];
		pendingSource[type] = null;
		if (!remembered) { return null; }
		var root = runtimeWindow(runtime);
		var now = root && root.Date && typeof root.Date.now === 'function' ? root.Date.now() : Date.now();
		if (now - remembered.time > SOURCE_MAX_AGE_MS) { return null; }
		return snapshotRect(remembered.rect);
	}

	function targetSelector(type) {
		return type === 'cart' ? '[data-gloskin-cart-open]' : '[data-gloskin-wishlist-open]';
	}

	function badgeSelector(type) {
		return type === 'cart' ? '[data-gloskin-cart-count]' : '[data-gloskin-wishlist-count]';
	}

	function resolveVisibleTarget(type, runtime) {
		var root = runtimeWindow(runtime);
		if (!validType(type) || !root || !root.document || typeof root.document.querySelectorAll !== 'function') { return null; }
		var candidates = root.document.querySelectorAll(targetSelector(type));
		for (var i = 0; i < candidates.length; i += 1) {
			var trigger = candidates[i];
			var triggerRect = renderedRect(trigger, root);
			if (!triggerRect) { continue; }
			var badge = typeof trigger.querySelector === 'function' ? trigger.querySelector(badgeSelector(type)) : null;
			var badgeRect = badge ? renderedRect(badge, root) : null;
			return { node: badgeRect ? badge : trigger, rect: badgeRect || triggerRect, trigger: trigger };
		}
		return null;
	}

	function reducedMotion(root) {
		return !!(root && root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function activeClass(type) {
		return 'gloskin-ui1-commerce-fly-active--' + type;
	}

	function typeStillActive(type) {
		for (var i = 0; i < activeParticles.length; i += 1) {
			if (activeParticles[i].type === type) { return true; }
		}
		return false;
	}

	function setTypeActive(type, root, active) {
		if (!root || !root.document || !root.document.documentElement || !validType(type)) { return; }
		root.document.documentElement.classList.toggle(activeClass(type), !!active);
	}

	function removeRecord(record, cancelAnimation) {
		if (!record) { return; }
		var index = activeParticles.indexOf(record);
		if (index !== -1) { activeParticles.splice(index, 1); }
		if (record.timer && record.root && typeof record.root.clearTimeout === 'function') { record.root.clearTimeout(record.timer); }
		if (cancelAnimation && record.animation && typeof record.animation.cancel === 'function') {
			try { record.animation.cancel(); } catch (error) {}
		}
		if (record.node && record.node.parentNode) { record.node.parentNode.removeChild(record.node); }
		if (record.root && record.type && !typeStillActive(record.type)) { setTypeActive(record.type, record.root, false); }
	}

	function boundParticles() {
		while (activeParticles.length >= MAX_ACTIVE_PARTICLES) {
			removeRecord(activeParticles[0], true);
		}
	}

	function impactTarget(type, target, runtime) {
		var root = runtimeWindow(runtime);
		if (!root || !target || reducedMotion(root)) { return false; }
		var node = target.node || target;
		if (!node || !node.classList) { return false; }
		node.classList.remove(IMPACT_CLASS);
		void node.offsetWidth;
		node.classList.add(IMPACT_CLASS);
		if (typeof root.setTimeout === 'function') {
			root.setTimeout(function () { node.classList.remove(IMPACT_CLASS); }, 280);
		}
		return true;
	}

	function animateCommerceFlyToTarget(type, source, runtime, onComplete) {
		var root = runtimeWindow(runtime);
		if (!validType(type) || !root || !root.document || !root.document.body) { return false; }
		if (reducedMotion(root)) { return false; }
		var start = consumeSourceRect(type, source, root);
		var target = resolveVisibleTarget(type, root);
		if (!start || !target || !target.rect) { return false; }
		if (typeof root.document.createElement !== 'function') { return false; }

		var node = root.document.createElement('span');
		node.className = FLY_CLASS + ' ' + FLY_CLASS + '--' + type;
		node.setAttribute('aria-hidden', 'true');
		node.style.left = (start.left + start.width / 2) + 'px';
		node.style.top = (start.top + start.height / 2) + 'px';
		root.document.body.appendChild(node);
		if (typeof node.animate !== 'function') {
			if (node.parentNode) { node.parentNode.removeChild(node); }
			return false;
		}

		var endX = target.rect.left + target.rect.width / 2 - (start.left + start.width / 2);
		var endY = target.rect.top + target.rect.height / 2 - (start.top + start.height / 2);
		var distance = Math.sqrt(endX * endX + endY * endY);
		var arc = Math.min(96, Math.max(30, distance * 0.12));
		var midX = endX * 0.58;
		var midY = endY * 0.52 - arc;

		boundParticles();
		setTypeActive(type, root, true);
		var animation;
		try {
			animation = node.animate([
				{ transform: 'translate3d(0,0,0) scale(1)', opacity: 0.9, offset: 0 },
				{ transform: 'translate3d(' + (endX * 0.18) + 'px,' + (endY * 0.12 - arc * 0.45) + 'px,0) scale(1.04)', opacity: 1, offset: 0.22 },
				{ transform: 'translate3d(' + midX + 'px,' + midY + 'px,0) scale(.88)', opacity: 0.96, offset: 0.62 },
				{ transform: 'translate3d(' + endX + 'px,' + endY + 'px,0) scale(.46)', opacity: 0.16, offset: 1 }
			], { duration: FLY_DURATION_MS, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'none' });
		} catch (error) {
			if (node.parentNode) { node.parentNode.removeChild(node); }
			setTypeActive(type, root, false);
			return false;
		}

		var record = { node: node, animation: animation, root: root, type: type, timer: 0, completed: false };
		activeParticles.push(record);
		function finish(runImpact) {
			if (record.completed) { return; }
			record.completed = true;
			removeRecord(record, false);
			if (runImpact) { impactTarget(type, target, root); }
			if (typeof onComplete === 'function') { onComplete(); }
		}
		animation.onfinish = function () { finish(true); };
		animation.oncancel = function () { finish(false); };
		if (typeof root.setTimeout === 'function') {
			record.timer = root.setTimeout(function () { finish(true); }, FLY_FAILSAFE_MS);
		}
		return true;
	}

	function confirmedSuccess(type, source, onComplete, runtime) {
		var root = runtimeWindow(runtime);
		if (!validType(type) || !root) { return false; }
		if (source && source.hasAttribute && source.hasAttribute('data-gloskin-buy-now-redirect')) { return false; }
		if (reducedMotion(root)) {
			if (typeof onComplete === 'function') { onComplete(); }
			return true;
		}
		var target = resolveVisibleTarget(type, root);
		if (!target) {
			if (typeof onComplete === 'function') { onComplete(); }
			return true;
		}
		if (animateCommerceFlyToTarget(type, source, root, onComplete)) { return true; }
		impactTarget(type, target, root);
		if (typeof onComplete === 'function') { onComplete(); }
		return true;
	}

	function afterCurrentStack(root, callback) {
		if (root && typeof root.queueMicrotask === 'function') { root.queueMicrotask(callback); return; }
		if (typeof Promise === 'function') { Promise.resolve().then(callback); return; }
		if (root && typeof root.setTimeout === 'function') { root.setTimeout(callback, 0); }
	}

	function bindCartSuccess(root, doc) {
		if (!root || typeof root.jQuery !== 'function' || !doc || !doc.body) { return false; }
		root.jQuery(doc.body).on('added_to_cart.gloskinCommerceMotion', function (event, fragments, cartHash, $button) {
			var source = $button && $button.length ? $button[0] : null;
			if (source && source.hasAttribute && source.hasAttribute('data-gloskin-buy-now-redirect')) { return; }
			confirmedSuccess('cart', source, null, root);
		});
		return true;
	}

	function normalizeCommercePath(pathname) {
		return String(pathname || '').replace(/\/+$/, '') || '/';
	}

	function isCommerceJourneyPage(doc) {
		var body = doc && doc.body;
		return !!(body && body.classList && (
			body.classList.contains('woocommerce-cart') ||
			body.classList.contains('woocommerce-checkout')
		));
	}

	function isCommerceJourneyDestination(link, root) {
		if (!link || !root || !root.location || typeof root.URL !== 'function') { return false; }
		var destination;
		try {
			destination = new root.URL(link.href, root.location.href);
		} catch (error) {
			return false;
		}
		if (destination.host !== root.location.host) { return false; }
		var config = root.gloskinData || {};
		var candidates = [config.cartUrl, config.checkoutUrl, '/cart/', '/checkout/'];
		var destinationPath = normalizeCommercePath(destination.pathname);
		for (var i = 0; i < candidates.length; i += 1) {
			if (!candidates[i]) { continue; }
			try {
				var candidate = new root.URL(candidates[i], root.location.href);
				if (candidate.host === root.location.host && normalizeCommercePath(candidate.pathname) === destinationPath) {
					return true;
				}
			} catch (error) {}
		}
		return false;
	}

	/* Commerce owns Cart/Checkout loading and presentation. Mark the existing
	 * global transition escape hatch during capture so its bubble listener
	 * declines the native journey without intercepting or replacing navigation. */
	function markCommerceJourneyTransitionBypass(event, root, doc) {
		var target = event && event.target;
		if (!target || typeof target.closest !== 'function') { return false; }
		var link = target.closest('a[href]');
		if (!link) { return false; }
		if (!isCommerceJourneyPage(doc) && !isCommerceJourneyDestination(link, root)) { return false; }
		link.setAttribute('data-gloskin-no-transition', '');
		return true;
	}

	function captureAction(event, root, doc) {
		markCommerceJourneyTransitionBypass(event, root, doc);
		var target = event && event.target;
		if (!target || typeof target.closest !== 'function') { return; }
		var wishlist = target.closest('[data-gloskin-wishlist-toggle]');
		if (wishlist) {
			var wasActive = wishlist.getAttribute('aria-pressed') === 'true';
			rememberSource('wishlist', wishlist, root);
			if (!wasActive) { setTypeActive('wishlist', root, true); }
			if (wishlistPending) { wishlistPending.set(wishlist, { wasActive: wasActive }); }
			afterCurrentStack(root, function () {
				var pending = wishlistPending ? wishlistPending.get(wishlist) : { wasActive: wasActive };
				if (wishlistPending) { wishlistPending.delete(wishlist); }
				var active = wishlist.getAttribute('aria-pressed') === 'true';
				if (pending && !pending.wasActive && active) {
					confirmedSuccess('wishlist', wishlist, null, root);
					return;
				}
				if (!typeStillActive('wishlist')) { setTypeActive('wishlist', root, false); }
			});
			return;
		}
		var cart = target.closest('[data-gloskin-variable-submit-proxy], .ajax_add_to_cart, button.single_add_to_cart_button');
		if (cart && !(cart.hasAttribute && cart.hasAttribute('data-gloskin-buy-now-redirect'))) {
			rememberSource('cart', cart, root);
		}
	}

	function boot(root, doc) {
		if (!root || !doc || typeof doc.addEventListener !== 'function') { return false; }
		doc.addEventListener('click', function (event) { captureAction(event, root, doc); }, true);
		function bindConfirmedBoundaries() { bindCartSuccess(root, doc); }
		if (doc.readyState === 'loading') {
			doc.addEventListener('DOMContentLoaded', bindConfirmedBoundaries, { once: true });
		} else {
			bindConfirmedBoundaries();
		}
		return true;
	}

	return {
		MAX_ACTIVE_PARTICLES: MAX_ACTIVE_PARTICLES,
		FLY_DURATION_MS: FLY_DURATION_MS,
		snapshotRect: snapshotRect,
		rememberSource: rememberSource,
		consumeSourceRect: consumeSourceRect,
		resolveVisibleTarget: resolveVisibleTarget,
		impactTarget: impactTarget,
		animateCommerceFlyToTarget: animateCommerceFlyToTarget,
		confirmedSuccess: confirmedSuccess,
		normalizeCommercePath: normalizeCommercePath,
		isCommerceJourneyPage: isCommerceJourneyPage,
		isCommerceJourneyDestination: isCommerceJourneyDestination,
		markCommerceJourneyTransitionBypass: markCommerceJourneyTransitionBypass,
		activeParticleCount: function () { return activeParticles.length; },
		boot: boot
	};
}));
