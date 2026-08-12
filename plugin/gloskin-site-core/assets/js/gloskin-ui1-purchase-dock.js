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

		/* The geometry owner is the canonical primary PDP content container,
		 * never .summary/a purchase slot inside it. Full-width floating
		 * geometry is always measured from this SAME node. */
		var container = product;
		var formBefore = dock.querySelector('form.cart');

		var BOTTOM_GAP = 16;
		var MIN_FLOAT_HEIGHT = 560;
		var state = 'preparing';
		var ready = false;
		var atHome = false;
		var homeObserver = null;
		var rebuildFrame = 0;
		var revealFrame = 0;
		var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		/* Deterministic preparation sequence, all synchronous in one task:
		 * 1) mark preparing, 2) native form already captured above, 3) leave
		 * an inert origin marker, 4) create the full-width home, 5) reparent
		 * the SAME dock node into it, 6) establish observers/geometry below,
		 * 7) reveal only after a requestAnimationFrame confirms layout. */
		dock.classList.add('is-preparing');

		var safetyReveal = window.setTimeout(function () {
			if (!ready) {
				dock.classList.remove('is-preparing');
				dock.classList.add('is-ready');
			}
		}, 1000);

		/* One tiny inert marker where the purchase form originally lived.
		 * No dock-height placeholder is left behind: .summary reserves none
		 * of the dock's space -- that is intentional, not a regression. */
		var origin = document.createElement('span');
		origin.className = 'gloskin-ui1-purchase-dock-origin';
		origin.setAttribute('aria-hidden', 'true');
		dock.parentNode.insertBefore(origin, dock);

		/* The dock's real, normal-flow, full-width DOM home: directly after
		 * Related Products, or at the end of the primary product root when
		 * Related is absent. This -- not the old summary slot -- is where
		 * the SAME node settles once its floating footprint would otherwise
		 * reach it, so it can never enter Footer. */
		var home = document.createElement('div');
		home.className = 'gloskin-ui1-purchase-dock-home';
		if (related) {
			related.insertAdjacentElement('afterend', home);
		} else {
			product.appendChild(home);
		}

		/* Reparent the SAME node once. Never cloneNode/innerHTML-rebuild. */
		home.appendChild(dock);

		product.style.setProperty('--gloskin-purchase-dock-bottom', 'max(16px, env(safe-area-inset-bottom))');

		function dockHeight() {
			return Math.ceil(dock.getBoundingClientRect().height || dock.offsetHeight || 0);
		}

		function canFloat() {
			var height = dockHeight();
			return window.innerHeight >= MIN_FLOAT_HEIGHT && height > 0 && height <= window.innerHeight * 0.55;
		}

		/* Full-block fixed geometry always derives from the PDP container,
		 * never a summary/purchase slot and never an arbitrary desktop cap.
		 * Clamping only guards the safe viewport edges on narrow devices. */
		function fullWidthGeometry() {
			var rect = container.getBoundingClientRect();
			var viewportWidth = document.documentElement.clientWidth || window.innerWidth;
			var left = Math.max(0, rect.left);
			var right = Math.min(viewportWidth, rect.right);
			return { left: left, width: Math.max(0, right - left) };
		}

		function homeReachedNow() {
			var height = dockHeight();
			var rect = home.getBoundingClientRect();
			var releaseLine = window.innerHeight - BOTTOM_GAP - height;
			return rect.top <= releaseLine;
		}

		/* While floating, home reserves the dock's real measured height so
		 * Footer/next content never jumps when the dock later settles into
		 * normal flow -- intentional occupancy, not ghost space, and never
		 * reserved back inside .summary. */
		function reserveHomeHeight() {
			home.style.minHeight = dockHeight() + 'px';
		}

		function releaseHomeHeight() {
			home.style.removeProperty('min-height');
		}

		function clearFloatingGeometry() {
			dock.style.removeProperty('left');
			dock.style.removeProperty('top');
			dock.style.removeProperty('width');
			dock.style.removeProperty('bottom');
			dock.style.removeProperty('position');
		}

		function revealReady() {
			dock.classList.remove('is-preparing');
			dock.classList.add('is-ready');
		}

		function revealStatic() {
			revealReady();
			dock.style.removeProperty('opacity');
			dock.style.removeProperty('visibility');
			dock.style.removeProperty('transform');
		}

		function revealFloating(animate) {
			revealReady();
			if (revealFrame) {
				window.cancelAnimationFrame(revealFrame);
				revealFrame = 0;
			}
			if (!animate || prefersReducedMotion) {
				dock.style.removeProperty('opacity');
				dock.style.removeProperty('transform');
				return;
			}
			dock.style.opacity = '0';
			dock.style.transform = 'translateY(calc(100% + 20px))';
			void dock.offsetHeight;
			revealFrame = window.requestAnimationFrame(function () {
				revealFrame = 0;
				if (state !== 'floating') { return; }
				dock.style.opacity = '1';
				dock.style.transform = 'translateY(0)';
			});
		}

		function setState(next, animate) {
			if (next === state) {
				if (next === 'floating') { updateFloatingGeometry(); }
				return;
			}
			state = next;
			dock.classList.toggle('is-floating', next === 'floating');
			dock.classList.toggle('is-home', next === 'home');

			if (next === 'floating') {
				updateFloatingGeometry();
				revealFloating(animate !== false);
				return;
			}

			clearFloatingGeometry();
			releaseHomeHeight();
			revealStatic();
		}

		function updateFloatingGeometry() {
			if (state !== 'floating') { return; }
			var geometry = fullWidthGeometry();
			dock.style.position = 'fixed';
			dock.style.left = geometry.left + 'px';
			dock.style.top = 'auto';
			dock.style.width = geometry.width + 'px';
			dock.style.bottom = 'var(--gloskin-purchase-dock-bottom)';
			reserveHomeHeight();
		}

		function syncState(animate) {
			if (!ready) { return; }
			atHome = homeReachedNow();
			if (!canFloat()) {
				setState('home', false);
				return;
			}
			setState(atHome ? 'home' : 'floating', animate);
		}

		function rebuildHomeObserver() {
			if (homeObserver) { homeObserver.disconnect(); }
			var rootBottomMargin = Math.max(0, BOTTOM_GAP + dockHeight());
			homeObserver = new IntersectionObserver(function (entries) {
				var entry = entries[entries.length - 1];
				atHome = entry.isIntersecting || entry.boundingClientRect.top < 0;
				if (ready) { syncState(true); }
			}, { root: null, rootMargin: '0px 0px -' + rootBottomMargin + 'px 0px', threshold: 0 });
			homeObserver.observe(home);
		}

		function scheduleRebuild() {
			if (rebuildFrame) { window.cancelAnimationFrame(rebuildFrame); }
			rebuildFrame = window.requestAnimationFrame(function () {
				rebuildFrame = 0;
				rebuildHomeObserver();
				syncState(false);
			});
		}

		var resizeObserver = new ResizeObserver(function () { scheduleRebuild(); });
		resizeObserver.observe(dock);
		window.addEventListener('resize', scheduleRebuild, { passive: true });
		window.addEventListener('orientationchange', scheduleRebuild, { passive: true });
		rebuildHomeObserver();

		/* One frame after relocation lets the browser resolve the full-width
		 * home-anchored geometry before the floating bar is shown. This
		 * avoids the summary-card -> bottom-dock flash and makes the first
		 * visible frame the slide-up presentation state. */
		window.requestAnimationFrame(function () {
			ready = true;
			window.clearTimeout(safetyReveal);
			atHome = homeReachedNow();
			if (canFloat() && !atHome) {
				setState('floating', true);
			} else {
				setState('home', false);
			}

			if (dock.querySelector('form.cart') !== formBefore && window.console && window.console.error) {
				window.console.error('gloskin-ui1-purchase-dock: native form node identity changed during relocation');
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPurchaseDockFloat);
	} else {
		initPurchaseDockFloat();
	}
}());
