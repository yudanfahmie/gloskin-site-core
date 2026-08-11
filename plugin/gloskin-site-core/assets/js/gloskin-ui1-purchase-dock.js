(function () {
	'use strict';

	function initPurchaseDockFloat() {
		if (!document.body || !document.body.classList.contains('single-product')) { return; }
		if (typeof window.IntersectionObserver !== 'function' || typeof window.ResizeObserver !== 'function') { return; }

		var product = document.querySelector('.gloskin-ui1-commerce-native > div.product');
		var summary = product ? product.querySelector(':scope > .summary') : null;
		var docks = summary ? summary.querySelectorAll('[data-gloskin-purchase-dock]') : [];
		var dock = docks.length === 1 ? docks[0] : null;
		var boundary = product ? (product.querySelector(':scope > .woocommerce-tabs') || product.querySelector(':scope > .related.products')) : null;
		if (!product || !summary || !dock || !boundary || dock.querySelectorAll('form.cart').length !== 1) { return; }

		var BOTTOM_GAP = 16;
		var BOUNDARY_GAP = 12;
		var state = 'normal';
		var summaryVisible = false;
		var markerPosition = 'unknown';
		var boundaryReached = false;
		var markerObserver = null;
		var boundaryObserver = null;
		var rebuildFrame = 0;

		var marker = document.createElement('span');
		marker.className = 'gloskin-ui1-purchase-dock-marker';
		marker.setAttribute('aria-hidden', 'true');
		marker.style.cssText = 'display:block;height:1px;margin-bottom:-1px;pointer-events:none;visibility:hidden;';

		var slot = document.createElement('div');
		slot.className = 'gloskin-ui1-purchase-dock-slot';
		slot.setAttribute('aria-hidden', 'true');
		slot.style.height = '0px';
		dock.parentNode.insertBefore(marker, dock);
		dock.parentNode.insertBefore(slot, dock);

		product.style.setProperty('--gloskin-purchase-dock-bottom', 'max(16px, env(safe-area-inset-bottom))');
		if (!product.style.position) { product.style.position = 'relative'; }

		function dockHeight() {
			return Math.ceil(dock.getBoundingClientRect().height || dock.offsetHeight || 0);
		}

		function canFloat() {
			var height = dockHeight();
			return window.innerHeight >= 560 && height > 0 && height <= window.innerHeight * 0.55;
		}

		function clearPosition() {
			dock.style.removeProperty('left');
			dock.style.removeProperty('top');
			dock.style.removeProperty('width');
			dock.style.removeProperty('bottom');
			dock.style.removeProperty('position');
		}

		function setState(next) {
			if (next === state) {
				if (next !== 'normal') { updateGeometry(); }
				return;
			}
			state = next;
			dock.classList.toggle('is-floating', next === 'floating');
			dock.classList.toggle('is-boundary', next === 'boundary');
			slot.classList.toggle('is-active', next !== 'normal');
			if (next === 'normal') {
				slot.style.height = '0px';
				clearPosition();
				return;
			}
			updateGeometry();
		}

		function updateGeometry() {
			if (state === 'normal') { return; }
			var height = dockHeight();
			var summaryRect = summary.getBoundingClientRect();
			var productRect = product.getBoundingClientRect();
			slot.style.height = height + 'px';
			dock.style.width = summaryRect.width + 'px';

			if (state === 'floating') {
				dock.style.position = 'fixed';
				dock.style.left = summaryRect.left + 'px';
				dock.style.top = 'auto';
				dock.style.bottom = 'var(--gloskin-purchase-dock-bottom)';
				return;
			}

			var boundaryRect = boundary.getBoundingClientRect();
			var topWithinProduct = boundaryRect.top - productRect.top - height - BOUNDARY_GAP;
			var leftWithinProduct = summaryRect.left - productRect.left;
			dock.style.position = 'absolute';
			dock.style.left = leftWithinProduct + 'px';
			dock.style.top = Math.max(0, topWithinProduct) + 'px';
			dock.style.bottom = 'auto';
		}

		function desiredState() {
			if (!canFloat()) { return 'normal'; }
			/* Once the dock has genuinely entered floating mode, a desktop
			 * gallery may keep the first PDP grid row alive after the summary's
			 * own content box has left the viewport. Keep the SAME dock floating
			 * until the real Tabs boundary releases it; only scrolling back above
			 * the purchase region (marker below + summary not visible) returns it
			 * to normal flow. This avoids depending on artificial summary height. */
			if (!summaryVisible && markerPosition === 'below') { return 'normal'; }
			if (boundaryReached && state !== 'normal') { return 'boundary'; }
			if (state === 'floating' || state === 'boundary') {
				return boundaryReached ? 'boundary' : 'floating';
			}
			if (!summaryVisible) { return 'normal'; }
			if (markerPosition === 'below' || markerPosition === 'above') { return 'floating'; }
			return 'normal';
		}

		function syncState() {
			setState(desiredState());
		}

		function rebuildEdgeObservers() {
			if (markerObserver) { markerObserver.disconnect(); }
			if (boundaryObserver) { boundaryObserver.disconnect(); }

			var height = dockHeight();
			var markerBottomMargin = Math.max(0, BOTTOM_GAP + height);
			markerObserver = new IntersectionObserver(function (entries) {
				var entry = entries[entries.length - 1];
				if (entry.isIntersecting) { markerPosition = 'inside'; }
				else if (entry.boundingClientRect.top >= 0) { markerPosition = 'below'; }
				else { markerPosition = 'above'; }
				syncState();
			}, { root: null, rootMargin: '0px 0px -' + markerBottomMargin + 'px 0px', threshold: 0 });
			markerObserver.observe(marker);

			var boundaryBottomMargin = Math.max(0, BOTTOM_GAP - BOUNDARY_GAP);
			boundaryObserver = new IntersectionObserver(function (entries) {
				var entry = entries[entries.length - 1];
				boundaryReached = entry.isIntersecting || entry.boundingClientRect.top < 0;
				syncState();
			}, { root: null, rootMargin: '0px 0px -' + boundaryBottomMargin + 'px 0px', threshold: 0 });
			boundaryObserver.observe(boundary);
		}

		function scheduleRebuild() {
			if (rebuildFrame) { window.cancelAnimationFrame(rebuildFrame); }
			rebuildFrame = window.requestAnimationFrame(function () {
				rebuildFrame = 0;
				if (!canFloat()) { setState('normal'); }
				else if (state !== 'normal') { updateGeometry(); }
				rebuildEdgeObservers();
			});
		}

		var summaryObserver = new IntersectionObserver(function (entries) {
			var entry = entries[entries.length - 1];
			summaryVisible = entry.isIntersecting;
			syncState();
		}, { threshold: 0 });
		summaryObserver.observe(summary);

		var resizeObserver = new ResizeObserver(function () { scheduleRebuild(); });
		resizeObserver.observe(dock);
		window.addEventListener('resize', scheduleRebuild, { passive: true });
		window.addEventListener('orientationchange', scheduleRebuild, { passive: true });
		rebuildEdgeObservers();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPurchaseDockFloat);
	} else {
		initPurchaseDockFloat();
	}
}());
