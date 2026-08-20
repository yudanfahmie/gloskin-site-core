(function (factory) {
	'use strict';
	var api = factory();
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	} else if (typeof window !== 'undefined' && typeof document !== 'undefined') {
		api.boot(window, document);
	}
}(function () {
	'use strict';

	var STORAGE_KEY = 'gloskinCommerceJourneyTarget';
	var LEAVING_CLASS = 'gloskin-ui1-commerce-journey-leaving';
	var ARRIVING_CLASS = 'gloskin-ui1-commerce-journey-arriving';
	var HANDOFF_DELAY_MS = 100;
	var OUTGOING_FAIL_OPEN_MS = 1800;
	var ARRIVAL_SETTLE_MS = 120;
	var ARRIVAL_FAIL_OPEN_MS = 1600;

	function targetKey(href, root) {
		try {
			var target = new URL(href, root.location.href);
			return target.pathname + target.search;
		} catch (error) {
			return '';
		}
	}

	function currentKey(root) {
		return String(root.location.pathname || '') + String(root.location.search || '');
	}

	function isSameOriginHttpTarget(href, root) {
		try {
			var target = new URL(href, root.location.href);
			return (target.protocol === 'http:' || target.protocol === 'https:') && target.origin === root.location.origin;
		} catch (error) {
			return false;
		}
	}

	function shouldIntercept(event, anchor, root) {
		if (!event || !anchor || !root || !root.location || event.defaultPrevented) { return false; }
		if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return false; }
		if (anchor.hasAttribute && anchor.hasAttribute('download')) { return false; }
		if (anchor.target && anchor.target !== '_self') { return false; }
		if (!anchor.href || !isSameOriginHttpTarget(anchor.href, root)) { return false; }
		return !!(root.location && typeof root.location.assign === 'function');
	}

	function reducedMotion(root) {
		return !!(root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function writeMarker(root, href) {
		if (!root.sessionStorage) { return false; }
		var key = targetKey(href, root);
		if (!key) { return false; }
		try {
			root.sessionStorage.setItem(STORAGE_KEY, key);
			return true;
		} catch (error) {
			return false;
		}
	}

	function clearMarker(root) {
		try {
			if (root.sessionStorage) { root.sessionStorage.removeItem(STORAGE_KEY); }
		} catch (error) {}
	}

	function nativeNavigate(root, href) {
		try {
			root.location.assign(href);
			return true;
		} catch (error) {
			try {
				root.location.href = href;
				return true;
			} catch (fallbackError) {
				clearMarker(root);
				return false;
			}
		}
	}

	function clearJourneyVisualState(doc) {
		if (!doc || !doc.documentElement || !doc.documentElement.classList) { return false; }
		doc.documentElement.classList.remove(LEAVING_CLASS);
		doc.documentElement.classList.remove(ARRIVING_CLASS);
		return true;
	}

	function recoverOutgoing(root, doc, href) {
		clearJourneyVisualState(doc);
		if (targetKey(href, root) !== currentKey(root)) {
			clearMarker(root);
		}
	}

	function scheduleOutgoingRecovery(root, doc, href) {
		if (typeof root.setTimeout !== 'function') { return false; }
		root.setTimeout(function () {
			recoverOutgoing(root, doc, href);
		}, OUTGOING_FAIL_OPEN_MS);
		return true;
	}

	function handleJourneyClick(event, anchor, root, doc) {
		if (!shouldIntercept(event, anchor, root)) { return false; }

		/* Marker storage is deliberately attempted before preventDefault(). If
		 * storage is blocked, the enhancement declines ownership and the real
		 * anchor continues through the browser's normal Woo navigation. */
		if (!writeMarker(root, anchor.href)) { return false; }

		event.preventDefault();
		if (reducedMotion(root)) {
			var reducedNavigated = nativeNavigate(root, anchor.href);
			if (reducedNavigated) { scheduleOutgoingRecovery(root, doc, anchor.href); }
			return reducedNavigated;
		}

		if (typeof root.setTimeout !== 'function') {
			return nativeNavigate(root, anchor.href);
		}

		try {
			doc.documentElement.classList.add(LEAVING_CLASS);
			root.setTimeout(function () {
				if (!nativeNavigate(root, anchor.href)) {
					recoverOutgoing(root, doc, anchor.href);
				}
			}, HANDOFF_DELAY_MS);
			scheduleOutgoingRecovery(root, doc, anchor.href);
			return true;
		} catch (error) {
			clearJourneyVisualState(doc);
			return nativeNavigate(root, anchor.href);
		}
	}

	function prepareArrival(root, doc) {
		if (!root.sessionStorage || !doc || !doc.documentElement) { return false; }
		var target = '';
		try {
			target = root.sessionStorage.getItem(STORAGE_KEY) || '';
			root.sessionStorage.removeItem(STORAGE_KEY);
		} catch (error) {
			return false;
		}
		if (!target || target !== currentKey(root)) { return false; }
		doc.documentElement.classList.add(ARRIVING_CLASS);
		return true;
	}

	function releaseArrival(doc) {
		if (doc && doc.documentElement) {
			doc.documentElement.classList.remove(ARRIVING_CLASS);
		}
	}

	function scheduleArrivalRelease(root, doc, prepared) {
		if (!prepared) { return false; }
		var released = false;
		function release() {
			if (released) { return; }
			released = true;
			releaseArrival(doc);
		}
		function settle() {
			if (reducedMotion(root) || typeof root.setTimeout !== 'function') {
				release();
				return;
			}
			root.setTimeout(release, ARRIVAL_SETTLE_MS);
		}

		if (doc.readyState === 'complete') {
			settle();
		} else if (typeof root.addEventListener === 'function') {
			root.addEventListener('load', settle, { once: true });
		} else if (doc.readyState === 'loading') {
			doc.addEventListener('DOMContentLoaded', settle, { once: true });
		} else {
			settle();
		}
		if (typeof root.setTimeout === 'function') {
			root.setTimeout(release, ARRIVAL_FAIL_OPEN_MS);
		}
		return true;
	}

	function handlePageShow(event, root, doc) {
		if (!event || event.persisted !== true) { return false; }
		clearJourneyVisualState(doc);
		clearMarker(root);
		return true;
	}

	function bindPageShowRecovery(root, doc) {
		if (typeof root.addEventListener !== 'function') { return false; }
		root.addEventListener('pageshow', function (event) {
			handlePageShow(event, root, doc);
		});
		return true;
	}

	function isExcludedCheckoutEndpoint(doc) {
		var body = doc && doc.body;
		return !!(body && body.classList && (
			body.classList.contains('woocommerce-order-received') ||
			body.classList.contains('woocommerce-order-pay')
		));
	}

	function bindJourneyLinks(root, doc) {
		if (isExcludedCheckoutEndpoint(doc)) { return 0; }
		var links = doc.querySelectorAll('[data-gloskin-commerce-progress] a[href]');
		Array.prototype.forEach.call(links, function (anchor) {
			anchor.addEventListener('click', function (event) {
				handleJourneyClick(event, anchor, root, doc);
			});
		});
		return links.length;
	}

	function boot(root, doc) {
		bindPageShowRecovery(root, doc);
		var prepared = prepareArrival(root, doc);
		scheduleArrivalRelease(root, doc, prepared);
		if (doc.readyState === 'loading') {
			doc.addEventListener('DOMContentLoaded', function () { bindJourneyLinks(root, doc); }, { once: true });
		} else {
			bindJourneyLinks(root, doc);
		}
	}

	return {
		STORAGE_KEY: STORAGE_KEY,
		LEAVING_CLASS: LEAVING_CLASS,
		ARRIVING_CLASS: ARRIVING_CLASS,
		HANDOFF_DELAY_MS: HANDOFF_DELAY_MS,
		OUTGOING_FAIL_OPEN_MS: OUTGOING_FAIL_OPEN_MS,
		targetKey: targetKey,
		shouldIntercept: shouldIntercept,
		writeMarker: writeMarker,
		clearJourneyVisualState: clearJourneyVisualState,
		recoverOutgoing: recoverOutgoing,
		handleJourneyClick: handleJourneyClick,
		prepareArrival: prepareArrival,
		scheduleArrivalRelease: scheduleArrivalRelease,
		handlePageShow: handlePageShow,
		bindPageShowRecovery: bindPageShowRecovery,
		isExcludedCheckoutEndpoint: isExcludedCheckoutEndpoint,
		bindJourneyLinks: bindJourneyLinks,
		boot: boot
	};
}));
