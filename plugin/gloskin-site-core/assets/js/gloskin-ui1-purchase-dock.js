(function () {
	'use strict';

	function initPurchaseDockFloat() {
		if (!document.body || !document.body.classList.contains('single-product')) { return; }
		if (typeof window.IntersectionObserver !== 'function' || typeof window.ResizeObserver !== 'function') { return; }

		var product = document.querySelector('.gloskin-ui1-commerce-native > div.product');
		var summary = product ? product.querySelector(':scope > .summary') : null;
		var docks = summary ? summary.querySelectorAll('[data-gloskin-purchase-dock]') : [];
		var dock = docks.length === 1 ? docks[0] : null;
		if (!product || !summary || !dock || dock.querySelectorAll('form.cart').length !== 1) { return; }

		/* Release boundary: the SAME dock must stay floating through Tabs AND
		 * Related Products, releasing only at the end of Related Products. A
		 * dedicated end sentinel placed immediately after ".related.products"
		 * (never the tabs section itself) is the boundary target; when no
		 * Related Products section exists, the sentinel falls back to the
		 * canonical product-end boundary (the last position inside the
		 * primary product root) so the dock still has a defined release
		 * point. This sentinel is a presentation-only marker, not cloned/
		 * moved product content. */
		var related = product.querySelector(':scope > .related.products');
		var boundary = document.createElement('span');
		boundary.className = 'gloskin-ui1-purchase-dock-end';
		boundary.setAttribute('aria-hidden', 'true');
		boundary.style.cssText = 'display:block;height:1px;pointer-events:none;visibility:hidden;';
		if (related) {
			related.insertAdjacentElement('afterend', boundary);
		} else {
			product.appendChild(boundary);
		}

		var BOTTOM_GAP = 16;
		var BOUNDARY_GAP = 12;
		var VIEWPORT_GUTTER = 16;
		var DESKTOP_MIN_WIDTH = 1024;
		var DESKTOP_MAX_WIDTH = 720;
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

		/* This zero-height slot remains in the summary's ORIGINAL normal-flow
		 * purchase column for the entire controller lifecycle. Its width/left
		 * therefore stay independent of whether the dock itself is static,
		 * fixed or boundary-settled. It is both the placeholder and the one
		 * stable geometry anchor -- never measure width from the fixed dock or
		 * from the full two-column product grid. */
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

		function anchorGeometry() {
			var slotRect = slot.getBoundingClientRect();
			var availableWidth = Math.max(0, window.innerWidth - (VIEWPORT_GUTTER * 2));
			var widthCap = window.innerWidth >= DESKTOP_MIN_WIDTH ? Math.min(DESKTOP_MAX_WIDTH, availableWidth) : availableWidth;
			var width = Math.min(slotRect.width, widthCap);
			var left = slotRect.left + Math.max(0, (slotRect.width - width) / 2);
			var maxLeft = Math.max(VIEWPORT_GUTTER, window.innerWidth - VIEWPORT_GUTTER - width);
			left = Math.min(Math.max(left, VIEWPORT_GUTTER), maxLeft);
			return { left: left, width: width, slotRect: slotRect };
		}

		function setState(next) {
			if (next === state) {
				if (next !== 'normal') { updateGeometry(); }
				return;
			}
			/* Entrance remains the existing transform-only enhancement. Width,
			 * internal structure and purchase state are untouched by it. */
			var entering = next === 'floating' && state !== 'floating';
			state = next;
			dock.classList.toggle('is-floating', next === 'floating');
			dock.classList.toggle('is-boundary', next === 'boundary');
			slot.classList.toggle('is-active', next !== 'normal');
			if (next === 'normal') {
				slot.style.height = '0px';
				clearPosition();
				dock.style.removeProperty('transform');
				return;
			}
			updateGeometry();
			if (entering) {
				dock.style.transform = 'translateY(100%)';
				void dock.offsetHeight;
				window.requestAnimationFrame(function () {
					dock.style.removeProperty('transform');
				});
			}
		}

		function updateGeometry() {
			if (state === 'normal') { return; }
			var height = dockHeight();
			var productRect = product.getBoundingClientRect();
			var anchor = anchorGeometry();
			slot.style.height = height + 'px';
			dock.style.width = anchor.width + 'px';

			if (state === 'floating') {
				dock.style.position = 'fixed';
				dock.style.left = anchor.left + 'px';
				dock.style.top = 'auto';
				dock.style.bottom = 'var(--gloskin-purchase-dock-bottom)';
				return;
			}

			/* Release at the existing end-of-Related boundary, but settle the
			 * same node back over its preserved normal-flow slot. This avoids
			 * covering the final Related card while keeping the exact same width
			 * and native form node through floating -> boundary. */
			dock.style.position = 'absolute';
			dock.style.left = (anchor.left - productRect.left) + 'px';
			dock.style.top = (anchor.slotRect.top - productRect.top) + 'px';
			dock.style.bottom = 'auto';
		}

		function currentMarkerPosition() {
			var rect = marker.getBoundingClientRect();
			if (rect.bottom < 0) { return 'above'; }
			if (rect.top > window.innerHeight) { return 'below'; }
			return 'inside';
		}

		function desiredState() {
			if (!canFloat()) { return 'normal'; }
			/* IntersectionObserver callbacks for summary and marker are not
			 * guaranteed to arrive in the same order after a large scroll jump.
			 * Read the marker's CURRENT rect when resolving state so a stale
			 * cached `below` value cannot unlatch a dock that has already begun
			 * floating through Tabs/Related. This is event-driven geometry read,
			 * never a scroll listener/polling loop. */
			var markerNow = currentMarkerPosition();
			if (!summaryVisible && markerNow === 'below') { return 'normal'; }
			if (boundaryReached && state !== 'normal') { return 'boundary'; }
			if (state === 'floating' || state === 'boundary') {
				return boundaryReached ? 'boundary' : 'floating';
			}
			if (!summaryVisible) { return 'normal'; }
			if (markerNow === 'below' || markerNow === 'above') { return 'floating'; }
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

			/* Shrink the observer root to the floating dock's own top edge. The
			 * end-of-Related sentinel releases the dock only when the final
			 * Related content is about to enter the dock's footprint -- not
			 * merely when the sentinel first becomes visible in the viewport. */
			var boundaryBottomMargin = Math.max(0, BOTTOM_GAP + height + BOUNDARY_GAP);
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
