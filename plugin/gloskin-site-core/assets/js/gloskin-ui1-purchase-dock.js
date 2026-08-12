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
		var identityBefore = dock.querySelector('[data-gloskin-purchase-identity]');
		var variationTableBefore = formBefore.querySelector('table.variations');
		var variationSelectsBefore = Array.prototype.slice.call(formBefore.querySelectorAll('table.variations select'));
		var singleVariationBefore = formBefore.querySelector('.woocommerce-variation.single_variation');
		var singleVariationWrapBefore = formBefore.querySelector('.single_variation_wrap');
		var quantityBefore = formBefore.querySelector('.quantity');
		var quantityInputBefore = quantityBefore ? quantityBefore.querySelector('input.qty') : null;
		var submitBefore = formBefore.querySelector('.single_add_to_cart_button');

		function sameNodeList(before, after) {
			if (before.length !== after.length) { return false; }
			for (var index = 0; index < before.length; index += 1) {
				if (before[index] !== after[index]) { return false; }
			}
			return true;
		}

		/* Compact minus/plus steppers around the SAME native input.qty --
		 * never a clone, never a second quantity state. Idempotent: checks
		 * a data flag before ever inserting, so it is safe to call again
		 * (dock re-entry/recomposition) without duplicating buttons. The
		 * click behavior itself is one delegated listener bound once on the
		 * dock root below, so no per-button listener is ever attached and
		 * there is nothing to duplicate even if this runs again. */
		function enhanceQuantityControls(quantity) {
			if (!quantity || quantity.dataset.gloskinQtyEnhanced === '1') { return; }
			var input = quantity.querySelector('input.qty');
			if (!input) { return; }
			quantity.classList.add('gloskin-ui1-purchase-dock__qty-control');
			var minus = document.createElement('button');
			minus.type = 'button';
			minus.className = 'gloskin-ui1-purchase-dock__qty-minus';
			minus.setAttribute('aria-label', 'Decrease quantity');
			minus.textContent = '−';
			var plus = document.createElement('button');
			plus.type = 'button';
			plus.className = 'gloskin-ui1-purchase-dock__qty-plus';
			plus.setAttribute('aria-label', 'Increase quantity');
			plus.textContent = '+';
			input.insertAdjacentElement('beforebegin', minus);
			input.insertAdjacentElement('afterend', plus);
			quantity.dataset.gloskinQtyEnhanced = '1';
		}

		function stepQuantityInput(input, direction) {
			if (!input || input.disabled || input.readOnly) { return; }
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
			if (next === current) { return; }
			input.value = next;
			input.dispatchEvent(new Event('input', { bubbles: true }));
			input.dispatchEvent(new Event('change', { bubbles: true }));
		}

		function prepareComposition() {
			if (!identityBefore || !submitBefore) { return false; }

			/* Presentation-only CSS ownership hooks on the SAME captured
			 * native Woo nodes -- never a clone, never rebuilt markup, never
			 * a second submit/quantity/variation control. This lets the
			 * dock's own geometry own its cascade by class instead of
			 * racing broad native Woo selectors for specificity. */
			formBefore.classList.add('gloskin-ui1-purchase-dock__form');
			if (variationTableBefore) { variationTableBefore.classList.add('gloskin-ui1-purchase-dock__variants'); }
			if (singleVariationWrapBefore) { singleVariationWrapBefore.classList.add('gloskin-ui1-purchase-dock__variation-action'); }
			if (singleVariationBefore) { singleVariationBefore.classList.add('gloskin-ui1-purchase-dock__variation-state'); }
			if (quantityBefore) {
				quantityBefore.classList.add('gloskin-ui1-purchase-dock__quantity');
				enhanceQuantityControls(quantityBefore);
			}
			submitBefore.classList.add('gloskin-ui1-purchase-dock__submit');

			var productRegion = document.createElement('div');
			productRegion.className = 'gloskin-ui1-purchase-dock__product';
			productRegion.setAttribute('data-gloskin-purchase-product', '');
			var actionRegion = document.createElement('div');
			actionRegion.className = 'gloskin-ui1-purchase-dock__action';
			actionRegion.setAttribute('data-gloskin-purchase-action', '');

			productRegion.appendChild(identityBefore);
			if (variationTableBefore) { productRegion.appendChild(variationTableBefore); }

			if (singleVariationWrapBefore) {
				actionRegion.appendChild(singleVariationWrapBefore);
			} else {
				if (quantityBefore) { actionRegion.appendChild(quantityBefore); }
				actionRegion.appendChild(submitBefore);
			}

			formBefore.appendChild(productRegion);
			formBefore.appendChild(actionRegion);
			dock.setAttribute('data-gloskin-purchase-composed', 'true');
			return true;
		}

		function nativeNodesPreserved() {
			var afterSelects = Array.prototype.slice.call(formBefore.querySelectorAll('table.variations select'));
			return dock.querySelector('form.cart') === formBefore
				&& formBefore.classList.contains('gloskin-ui1-purchase-dock__form')
				&& dock.querySelector('[data-gloskin-purchase-identity]') === identityBefore
				&& sameNodeList(variationSelectsBefore, afterSelects)
				&& formBefore.querySelector('.quantity') === quantityBefore
				&& (!quantityBefore || (quantityBefore.classList.contains('gloskin-ui1-purchase-dock__quantity') && quantityBefore.classList.contains('gloskin-ui1-purchase-dock__qty-control') && quantityBefore.querySelector('input.qty') === quantityInputBefore))
				&& formBefore.querySelector('.single_add_to_cart_button') === submitBefore
				&& submitBefore.classList.contains('gloskin-ui1-purchase-dock__submit')
				&& (!singleVariationBefore || (formBefore.querySelector('.woocommerce-variation.single_variation') === singleVariationBefore && singleVariationBefore.classList.contains('gloskin-ui1-purchase-dock__variation-state')));
		}

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
		prepareComposition();

		/* One delegated listener on the stable dock root, bound exactly
		 * once for the dock's lifetime. It resolves the current
		 * input.qty at click time rather than closing over a captured
		 * reference, so it stays correct even if Woo's own variation
		 * lifecycle ever changes what is inside .gloskin-ui1-purchase-
		 * dock__qty-control -- no duplicate listeners, no stale nodes,
		 * no polling. */
		dock.addEventListener('click', function (event) {
			var minusButton = event.target.closest ? event.target.closest('.gloskin-ui1-purchase-dock__qty-minus') : null;
			var plusButton = event.target.closest ? event.target.closest('.gloskin-ui1-purchase-dock__qty-plus') : null;
			if (!minusButton && !plusButton) { return; }
			var control = (minusButton || plusButton).closest('.gloskin-ui1-purchase-dock__qty-control');
			var input = control ? control.querySelector('input.qty') : null;
			if (!input) { return; }
			event.preventDefault();
			stepQuantityInput(input, minusButton ? -1 : 1);
		});

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

			if (!nativeNodesPreserved() && window.console && window.console.error) {
				window.console.error('gloskin-ui1-purchase-dock: native Woo node identity changed during presentation composition');
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPurchaseDockFloat);
	} else {
		initPurchaseDockFloat();
	}
}());
