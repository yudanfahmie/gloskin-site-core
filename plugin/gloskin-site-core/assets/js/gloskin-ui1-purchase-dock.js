(function () {
	'use strict';

	function initPurchaseDockFloat() {
		if (!document.body || !document.body.classList.contains('single-product')) { return; }
		if (typeof window.IntersectionObserver !== 'function' || typeof window.ResizeObserver !== 'function') { return; }

		var product = document.querySelector('.gloskin-ui1-commerce-native > div.product');
		var summary = product ? product.querySelector(':scope > .summary') : null;
		var related = product ? product.querySelector(':scope > .related.products') : null;
		var docks = summary ? summary.querySelectorAll('[data-gloskin-purchase-dock]') : [];
		var dock = docks.length === 1 ? docks[0] : null;
		if (!product || !summary || !dock || dock.querySelectorAll('form.cart').length !== 1) { return; }

		var BOTTOM_GAP = 16;
		var MIN_FLOAT_HEIGHT = 560;
		var state = 'mounting';
		var ready = false;
		var boundaryReached = false;
		var boundaryObserver = null;
		var rebuildFrame = 0;
		var revealFrame = 0;
		var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		/* Anti-flicker lifecycle: hide the existing server-rendered dock only
		 * while THIS enhancement task relocates it. CSS also suppresses the
		 * pre-footer first paint when scripting is enabled, with its own
		 * fail-safe reveal. The exact same native form node is moved once. */
		dock.style.visibility = 'hidden';
		dock.style.opacity = '0';
		var safetyReveal = window.setTimeout(function () {
			if (!ready) {
				dock.style.visibility = 'visible';
				dock.style.opacity = '1';
			}
		}, 1000);

		/* The boundary is the dock's final normal-flow position. Put it directly
		 * after Related Products (or at product end when Related is absent),
		 * then move the SAME wrapper after that marker. Because the dock becomes
		 * a direct child of the primary product grid it can own the entire grid
		 * row instead of inheriting .summary's half-width geometry. */
		var boundary = document.createElement('span');
		boundary.className = 'gloskin-ui1-purchase-dock-end';
		boundary.setAttribute('aria-hidden', 'true');
		boundary.style.cssText = 'display:block;grid-column:1/-1;height:1px;margin:0;pointer-events:none;visibility:hidden;';
		if (related) {
			related.insertAdjacentElement('afterend', boundary);
		} else {
			product.appendChild(boundary);
		}
		boundary.insertAdjacentElement('afterend', dock);
		dock.classList.add('is-relocated');

		product.style.setProperty('--gloskin-purchase-dock-bottom', 'max(16px, env(safe-area-inset-bottom))');

		function dockHeight() {
			return Math.ceil(dock.getBoundingClientRect().height || dock.offsetHeight || 0);
		}

		function canFloat() {
			var height = dockHeight();
			return window.innerHeight >= MIN_FLOAT_HEIGHT && height > 0 && height <= window.innerHeight * 0.55;
		}

		function fullBlockGeometry() {
			var rect = product.getBoundingClientRect();
			var viewportWidth = document.documentElement.clientWidth || window.innerWidth;
			var left = Math.max(0, rect.left);
			var right = Math.min(viewportWidth, rect.right);
			return {
				left: left,
				width: Math.max(0, right - left)
			};
		}

		function boundaryReachedNow() {
			var height = dockHeight();
			var rect = boundary.getBoundingClientRect();
			var releaseLine = window.innerHeight - BOTTOM_GAP - height;
			return rect.top <= releaseLine;
		}

		function clearFloatingGeometry() {
			dock.style.removeProperty('left');
			dock.style.removeProperty('top');
			dock.style.removeProperty('width');
			dock.style.removeProperty('bottom');
			dock.style.removeProperty('position');
		}

		function revealStatic() {
			dock.style.visibility = 'visible';
			dock.style.opacity = '1';
			dock.style.removeProperty('transform');
		}

		function revealFloating(animate) {
			if (revealFrame) {
				window.cancelAnimationFrame(revealFrame);
				revealFrame = 0;
			}
			dock.style.visibility = 'visible';
			if (!animate || prefersReducedMotion) {
				dock.style.opacity = '1';
				dock.style.removeProperty('transform');
				return;
			}
			dock.style.opacity = '0';
			dock.style.transform = 'translateY(calc(100% + 24px))';
			void dock.offsetHeight;
			revealFrame = window.requestAnimationFrame(function () {
				revealFrame = 0;
				if (state !== 'floating') { return; }
				dock.style.opacity = '1';
				dock.style.removeProperty('transform');
			});
		}

		function setState(next, animate) {
			if (next === state) {
				if (next === 'floating') { updateFloatingGeometry(); }
				return;
			}
			state = next;
			dock.classList.toggle('is-floating', next === 'floating');
			dock.classList.toggle('is-boundary', next === 'boundary');

			if (next === 'floating') {
				updateFloatingGeometry();
				revealFloating(animate !== false);
				return;
			}

			clearFloatingGeometry();
			revealStatic();
		}

		function updateFloatingGeometry() {
			if (state !== 'floating') { return; }
			var geometry = fullBlockGeometry();
			dock.style.position = 'fixed';
			dock.style.left = geometry.left + 'px';
			dock.style.top = 'auto';
			dock.style.width = geometry.width + 'px';
			dock.style.bottom = 'var(--gloskin-purchase-dock-bottom)';
		}

		function syncState(animate) {
			if (!ready) { return; }
			boundaryReached = boundaryReachedNow();
			if (!canFloat()) {
				setState('normal', false);
				return;
			}
			setState(boundaryReached ? 'boundary' : 'floating', animate);
		}

		function rebuildBoundaryObserver() {
			if (boundaryObserver) { boundaryObserver.disconnect(); }
			var rootBottomMargin = Math.max(0, BOTTOM_GAP + dockHeight());
			boundaryObserver = new IntersectionObserver(function (entries) {
				var entry = entries[entries.length - 1];
				boundaryReached = entry.isIntersecting || entry.boundingClientRect.top < 0;
				if (ready) { syncState(true); }
			}, { root: null, rootMargin: '0px 0px -' + rootBottomMargin + 'px 0px', threshold: 0 });
			boundaryObserver.observe(boundary);
		}

		function scheduleRebuild() {
			if (rebuildFrame) { window.cancelAnimationFrame(rebuildFrame); }
			rebuildFrame = window.requestAnimationFrame(function () {
				rebuildFrame = 0;
				rebuildBoundaryObserver();
				syncState(false);
			});
		}

		var resizeObserver = new ResizeObserver(function () { scheduleRebuild(); });
		resizeObserver.observe(dock);
		window.addEventListener('resize', scheduleRebuild, { passive: true });
		window.addEventListener('orientationchange', scheduleRebuild, { passive: true });
		rebuildBoundaryObserver();

		/* One frame after relocation lets the browser resolve the full-width
		 * form geometry before the floating bar is shown. This avoids the
		 * half-width -> full-width flash and makes the first visible frame the
		 * slide-up presentation state. */
		window.requestAnimationFrame(function () {
			ready = true;
			window.clearTimeout(safetyReveal);
			boundaryReached = boundaryReachedNow();
			if (canFloat() && !boundaryReached) {
				setState('floating', true);
			} else {
				setState(boundaryReached ? 'boundary' : 'normal', false);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPurchaseDockFloat);
	} else {
		initPurchaseDockFloat();
	}
}());
