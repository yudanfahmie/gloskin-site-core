(function () {
	'use strict';

	/* One explicit state machine, one canonical owner. Internal names below;
	 * CSS classes are the same names prefixed "is-". No other code path may
	 * toggle these classes directly -- every transition goes through
	 * setState()/settleWithFlip()/liftWithFlip() below. */
	var STATE = {
		PREPARING: 'preparing',
		FLOATING_ENTER: 'floating-enter',
		FLOATING: 'floating',
		SETTLING: 'settling',
		HOME: 'home',
		LIFTING: 'lifting'
	};

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
		var buyNowBefore = null;

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

			/* Buy Now: a JS-composed sibling control, never a second form/
			 * mutation owner -- it only ever triggers the SAME real native
			 * submit button's own click (see the delegated dock click
			 * handler below), reusing 100% of the existing add-to-cart
			 * validation/AJAX/native-fallback path. gloskin-ui1-core.js's
			 * existing single-product success handler recognizes the
			 * one-shot data-gloskin-buy-now-redirect flag set right before
			 * that click and redirects to the cart page instead of
			 * rendering the normal View Cart link. */
			buyNowBefore = document.createElement('button');
			buyNowBefore.type = 'button';
			buyNowBefore.className = 'gloskin-ui1-purchase-dock__buy-now';
			buyNowBefore.setAttribute('data-gloskin-buy-now', '');
			buyNowBefore.textContent = 'Beli Sekarang';
			buyNowBefore.disabled = !!submitBefore.disabled;
			actionRegion.appendChild(buyNowBefore);

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

		/* -----------------------------------------------------------------
		 * Explicit, documented constants -- no magic numbers scattered
		 * through the geometry/hysteresis math below.
		 * ----------------------------------------------------------------- */
		var BOTTOM_GAP = 16; // px clearance kept between the floating dock and the viewport edge.
		var MIN_FLOAT_HEIGHT = 560; // px: viewports shorter than this never float (section 16).
		var MAX_FLOAT_RATIO = 0.55; // dock may never occupy more than this share of viewport height while floating.
		var SETTLE_EPSILON = 4; // px past the natural boundary before floating -> settling actually commits.
		var RESUME_HYSTERESIS = 32; // px the user must scroll back up beyond the settle line before home -> floating resumes.
		var HEIGHT_CHANGE_EPSILON = 2; // px: ResizeObserver churn below this is treated as noise, never rebuilds anything.
		var TRANSITION_MS = 280; // must match the CSS transform transition duration for is-settling/is-lifting.

		var state = STATE.PREPARING;
		var ready = false;
		var transitionLocked = false;
		var syncPending = false;
		var cachedDockHeight = 0;
		var focusWithinDock = false;
		var resizeFrame = 0;
		var viewportResizeFrame = 0;
		var flipFrame = 0;
		var transitionFallback = 0;
		var sentinelObserver = null;
		var observerMarginPx = -1;
		var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		/* Deterministic preparation sequence, all synchronous in one task:
		 * 1) mark preparing, 2) native form already captured above, 3) leave
		 * an inert origin marker, 4) create the stable sentinel + full-width
		 * home, 5) reparent the SAME dock node into home, 6) establish
		 * observers/geometry below, 7) reveal only after two RAFs confirm
		 * layout (measure, then commit visible state) -- never reveal first
		 * and reposition second, which is what causes a flash. */
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
			if (minusButton || plusButton) {
				var control = (minusButton || plusButton).closest('.gloskin-ui1-purchase-dock__qty-control');
				var input = control ? control.querySelector('input.qty') : null;
				if (!input) { return; }
				event.preventDefault();
				stepQuantityInput(input, minusButton ? -1 : 1);
				return;
			}
			var buyNowButton = event.target.closest ? event.target.closest('[data-gloskin-buy-now]') : null;
			if (buyNowButton) {
				event.preventDefault();
				if (!submitBefore || submitBefore.disabled) { return; }
				/* One-shot flag, consumed and removed by gloskin-ui1-core.js's
				 * existing single-product AJAX success handler. Triggering the
				 * REAL submit button's own click reuses every existing
				 * validation/interception/native-fallback path unchanged --
				 * this never submits a second form or opens a new mutation
				 * owner. */
				submitBefore.setAttribute('data-gloskin-buy-now-redirect', '1');
				submitBefore.click();
			}
		});

		/* Keep Buy Now's enabled state mirrored to the real submit button's
		 * own disabled state (Woo's variation-form JS toggles this as the
		 * shopper picks a variation) -- observed, never polled. */
		if (buyNowBefore && submitBefore && window.MutationObserver) {
			var buyNowSync = new MutationObserver(function () {
				buyNowBefore.disabled = !!submitBefore.disabled;
			});
			buyNowSync.observe(submitBefore, { attributes: true, attributeFilter: ['disabled'] });
		}

		/* Section 17: a viewport-height change (mobile keyboard opening)
		 * while focus is inside the dock must never itself start a
		 * floating/home transition. Delegated, one-shot listeners -- no
		 * keyboard-detection framework. */
		dock.addEventListener('focusin', function () { focusWithinDock = true; });
		dock.addEventListener('focusout', function () {
			window.setTimeout(function () {
				if (!dock.contains(document.activeElement)) {
					focusWithinDock = false;
					requestSync();
				}
			}, 0);
		});

		var safetyReveal = window.setTimeout(function () {
			if (!ready) {
				ready = true;
				dock.classList.remove('is-preparing');
				dock.classList.add('is-home');
				state = STATE.HOME;
			}
		}, 1000);

		/* One tiny inert marker where the purchase form originally lived.
		 * No dock-height placeholder is left behind: .summary reserves none
		 * of the dock's space -- that is intentional, not a regression. */
		var origin = document.createElement('span');
		origin.className = 'gloskin-ui1-purchase-dock-origin';
		origin.setAttribute('aria-hidden', 'true');
		dock.parentNode.insertBefore(origin, dock);

		/* Stable footer-handoff sentinel (section 6): a permanently zero-
		 * footprint marker, inserted once and never reparented/resized. Its
		 * own geometry depends only on scroll position and the surrounding
		 * static content -- never on the dock's own floating/home state --
		 * which is what breaks the prior self-referential boundary (the old
		 * implementation measured `home`, whose own occupied height changed
		 * depending on the very state it was deciding). */
		var sentinel = document.createElement('div');
		sentinel.className = 'gloskin-ui1-purchase-dock-sentinel';
		sentinel.setAttribute('aria-hidden', 'true');

		/* The dock's real, normal-flow, full-width DOM home: directly after
		 * Related Products, or at the end of the primary product root when
		 * Related is absent. This -- not the old summary slot -- is where
		 * the SAME node settles once its floating footprint would otherwise
		 * reach it, so it can never enter Footer. */
		var home = document.createElement('div');
		home.className = 'gloskin-ui1-purchase-dock-home';
		if (related) {
			related.insertAdjacentElement('afterend', sentinel);
			sentinel.insertAdjacentElement('afterend', home);
		} else {
			product.appendChild(sentinel);
			product.appendChild(home);
		}

		/* Reparent the SAME node once. Never cloneNode/innerHTML-rebuild. */
		home.appendChild(dock);

		product.style.setProperty('--gloskin-purchase-dock-bottom', 'max(16px, env(safe-area-inset-bottom))');

		function measureDockHeight() {
			return Math.round(dock.getBoundingClientRect().height || dock.offsetHeight || 0);
		}

		function canFloat() {
			var height = cachedDockHeight || measureDockHeight();
			return window.innerHeight >= MIN_FLOAT_HEIGHT && height > 0 && height <= window.innerHeight * MAX_FLOAT_RATIO;
		}

		/* Full-block fixed geometry always derives from the PDP container,
		 * never a summary/purchase slot and never an arbitrary desktop cap.
		 * Clamping only guards the safe viewport edges on narrow devices.
		 * Only left/width are genuine runtime measurements requiring inline
		 * style -- position/bottom are owned by the is-floating/is-floating-
		 * enter/is-lifting CSS classes instead (section 20). */
		function fullWidthGeometry() {
			var rect = container.getBoundingClientRect();
			var viewportWidth = document.documentElement.clientWidth || window.innerWidth;
			var left = Math.max(0, rect.left);
			var right = Math.min(viewportWidth, rect.right);
			return { left: left, width: Math.max(0, right - left) };
		}

		function applyFloatingGeometry() {
			var geometry = fullWidthGeometry();
			dock.style.left = geometry.left + 'px';
			dock.style.width = geometry.width + 'px';
		}

		function clearFloatingGeometry() {
			dock.style.removeProperty('left');
			dock.style.removeProperty('width');
		}

		function reserveHomeHeight() {
			home.style.minHeight = (cachedDockHeight || measureDockHeight()) + 'px';
		}

		function releaseHomeHeight() {
			home.style.removeProperty('min-height');
		}

		/* Signed distance from the sentinel to the line the floating dock's
		 * own top edge currently occupies. Positive: sentinel is still
		 * below that line, safe to float. Zero/negative: the sentinel has
		 * reached/passed it, the dock should settle. This is the ONE
		 * boundary authority -- both the IntersectionObserver callback and
		 * the resize-triggered resync read this same function, and it never
		 * depends on the dock's own current state. */
		function computeSentinelDistance() {
			var rect = sentinel.getBoundingClientRect();
			var floatTopLine = window.innerHeight - BOTTOM_GAP - (cachedDockHeight || measureDockHeight());
			return rect.top - floatTopLine;
		}

		/* -----------------------------------------------------------------
		 * Transition lock (section 8): while settling/lifting is actively
		 * animating, observer activity may only request a re-sync, never
		 * start a second transition. Exactly one fallback timer is ever
		 * scheduled per transition -- never stacked, never a queued RAF
		 * loop.
		 * ----------------------------------------------------------------- */
		function lockTransition() {
			transitionLocked = true;
		}

		function unlockTransition() {
			transitionLocked = false;
			if (transitionFallback) {
				window.clearTimeout(transitionFallback);
				transitionFallback = 0;
			}
			if (syncPending) {
				syncPending = false;
				syncState();
			}
		}

		function afterTransition(callback) {
			var done = false;
			function finish(event) {
				if (done) { return; }
				if (event && (event.target !== dock || event.propertyName !== 'transform')) { return; }
				done = true;
				dock.removeEventListener('transitionend', finish);
				callback();
				unlockTransition();
			}
			dock.addEventListener('transitionend', finish);
			transitionFallback = window.setTimeout(finish, TRANSITION_MS + 80);
		}

		/* -----------------------------------------------------------------
		 * FLIP-style settle/lift (sections 9-10): the SAME dock node glides
		 * between its floating and home geometry instead of teleporting.
		 * Only transform/opacity are animated; left/width/bottom are never
		 * animated (section 5, section 23).
		 * ----------------------------------------------------------------- */
		function settleWithFlip() {
			if (transitionLocked) { syncPending = true; return; }
			var first = dock.getBoundingClientRect();

			dock.classList.remove('is-floating', 'is-floating-enter');
			clearFloatingGeometry();
			dock.classList.add('is-settling');

			/* The dock is now a normal static child of `home`; measure its
			 * real resting position in the SAME synchronous pass. */
			var last = dock.getBoundingClientRect();

			/* Section 11: never a frame where both the reserved home height
			 * and the settled dock's own static height are counted, and
			 * never a frame where neither is -- release the reservation in
			 * this same synchronous pass, immediately after the dock itself
			 * already occupies that space in normal flow. */
			releaseHomeHeight();

			if (prefersReducedMotion) {
				dock.classList.remove('is-settling');
				dock.classList.add('is-home');
				state = STATE.HOME;
				rebuildSentinelObserver(true);
				return;
			}

			state = STATE.SETTLING;
			lockTransition();
			var dx = first.left - last.left;
			var dy = first.top - last.top;
			dock.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
			void dock.offsetHeight; // force layout before animating from this inverted position
			flipFrame = window.requestAnimationFrame(function () {
				flipFrame = 0;
				dock.style.transform = 'translate(0,0)';
			});
			afterTransition(function () {
				dock.style.removeProperty('transform');
				dock.classList.remove('is-settling');
				dock.classList.add('is-home');
				state = STATE.HOME;
				/* IntersectionObserver guarantees an initial callback on
				 * .observe(); forcing one fresh re-subscription right after
				 * a completed transition re-validates the current true
				 * geometry deterministically, rather than depending on a
				 * native re-fire for the exact next scroll after a layout
				 * change this significant (fixed -> static). */
				rebuildSentinelObserver(true);
			});
		}

		function liftWithFlip() {
			if (transitionLocked) { syncPending = true; return; }
			var first = dock.getBoundingClientRect();

			dock.classList.remove('is-home');
			reserveHomeHeight();
			dock.classList.add('is-lifting');
			applyFloatingGeometry();

			var last = dock.getBoundingClientRect();

			if (prefersReducedMotion) {
				dock.classList.remove('is-lifting');
				dock.classList.add('is-floating');
				state = STATE.FLOATING;
				rebuildSentinelObserver(true);
				return;
			}

			state = STATE.LIFTING;
			lockTransition();
			var dx = first.left - last.left;
			var dy = first.top - last.top;
			dock.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
			void dock.offsetHeight;
			flipFrame = window.requestAnimationFrame(function () {
				flipFrame = 0;
				dock.style.transform = 'translate(0,0)';
			});
			afterTransition(function () {
				dock.style.removeProperty('transform');
				dock.classList.remove('is-lifting');
				dock.classList.add('is-floating');
				state = STATE.FLOATING;
				rebuildSentinelObserver(true);
			});
		}

		function settleImmediate() {
			clearFloatingGeometry();
			releaseHomeHeight();
			dock.classList.remove('is-floating', 'is-floating-enter', 'is-settling', 'is-lifting');
			dock.classList.add('is-home');
			state = STATE.HOME;
		}

		/* -----------------------------------------------------------------
		 * State machine entry point. Observers/resize/focusout only ever
		 * call requestSync(); the machine itself decides what, if anything,
		 * changes. Never toggles classes directly from outside this
		 * function family.
		 * ----------------------------------------------------------------- */
		function requestSync() {
			if (!ready) { return; }
			if (transitionLocked) { syncPending = true; return; }
			syncState();
		}

		function syncState() {
			if (!canFloat()) {
				if (state === STATE.FLOATING || state === STATE.FLOATING_ENTER) { settleWithFlip(); }
				else if (state !== STATE.HOME) { settleImmediate(); }
				return;
			}

			var distance = computeSentinelDistance();

			if (state === STATE.FLOATING || state === STATE.FLOATING_ENTER) {
				if (distance <= -SETTLE_EPSILON) { settleWithFlip(); }
				return;
			}

			if (state === STATE.HOME) {
				if (distance >= RESUME_HYSTERESIS) { liftWithFlip(); }
				return;
			}
		}

		/* -----------------------------------------------------------------
		 * ResizeObserver discipline (section 12): update cached geometry,
		 * coalesce into one RAF, and only escalate to a real re-sync (or
		 * observer rebuild) when the measured height actually changed by a
		 * meaningful amount -- never on sub-pixel churn. The dock's own
		 * content growing/shrinking (e.g. a variation selected) is never a
		 * keyboard concern and always syncs normally, regardless of focus. */
		function scheduleContentResize() {
			if (resizeFrame) { return; }
			resizeFrame = window.requestAnimationFrame(function () {
				resizeFrame = 0;
				var height = measureDockHeight();
				var changed = Math.abs(height - cachedDockHeight) >= HEIGHT_CHANGE_EPSILON;
				cachedDockHeight = height;
				if (state === STATE.FLOATING || state === STATE.FLOATING_ENTER || state === STATE.LIFTING) {
					reserveHomeHeight();
					applyFloatingGeometry();
				}
				if (changed) { rebuildSentinelObserver(); }
				requestSync();
			});
		}

		/* Section 17: a viewport-height change (mobile keyboard opening) is
		 * a genuinely different signal from the dock's own content
		 * resizing above. Safe geometry (left/width, reservation) is still
		 * kept current, but while focus is inside the dock this must never
		 * itself start a floating/home transition -- the focusout handler
		 * performs one normal sync once focus actually leaves. */
		function scheduleViewportResize() {
			if (viewportResizeFrame) { return; }
			viewportResizeFrame = window.requestAnimationFrame(function () {
				viewportResizeFrame = 0;
				if (state === STATE.FLOATING || state === STATE.FLOATING_ENTER || state === STATE.LIFTING) {
					reserveHomeHeight();
					applyFloatingGeometry();
				}
				if (focusWithinDock) { return; }
				requestSync();
			});
		}

		/* The single line the observer must actually catch the sentinel
		 * crossing depends on which direction the machine currently cares
		 * about: while HOME, the resume (lift) line; otherwise the settle
		 * line. A single static/generous margin cannot do this -- a
		 * binary threshold=0 observer only re-fires on ITS OWN boundary
		 * crossing, so if that boundary sits far from the real hysteresis
		 * line, the state machine can stop hearing about further scrolling
		 * once the user is on the far side of the observer line but has
		 * not yet reached (or already passed back over) the real decision
		 * line. Deriving the margin from the SAME constants
		 * computeSentinelDistance() uses keeps the observer boundary and
		 * the real decision line identical, so every relevant crossing
		 * re-fires it. This is why every settle/lift completion forces a
		 * rebuild (sections 6/9/10) -- the relevant line moves each time
		 * state changes. */
		function currentBoundaryLine() {
			var floatTopLine = window.innerHeight - BOTTOM_GAP - (cachedDockHeight || measureDockHeight());
			return state === STATE.HOME ? floatTopLine + RESUME_HYSTERESIS : floatTopLine - SETTLE_EPSILON;
		}

		function rebuildSentinelObserver(force) {
			var marginPx = Math.max(0, Math.min(window.innerHeight, Math.round(window.innerHeight - currentBoundaryLine())));
			if (!force && sentinelObserver && marginPx === observerMarginPx) { return; }
			if (sentinelObserver) { sentinelObserver.disconnect(); }
			observerMarginPx = marginPx;
			sentinelObserver = new IntersectionObserver(function () {
				requestSync();
			}, { root: null, rootMargin: "0px 0px -" + marginPx + "px 0px", threshold: 0 });
			sentinelObserver.observe(sentinel);
		}

		var domResizeObserver = new ResizeObserver(function () { scheduleContentResize(); });
		domResizeObserver.observe(dock);
		window.addEventListener('resize', scheduleViewportResize, { passive: true });
		window.addEventListener('orientationchange', scheduleViewportResize, { passive: true });

		/* -----------------------------------------------------------------
		 * Zero-flicker initial reveal (section 4): first RAF measures and
		 * positions while still invisible; second RAF commits the visible
		 * entrance state. Never reveal first and reposition second.
		 * ----------------------------------------------------------------- */
		window.requestAnimationFrame(function () {
			cachedDockHeight = measureDockHeight();
			var willFloat = canFloat() && computeSentinelDistance() > 0;

			if (willFloat) {
				reserveHomeHeight();
				dock.classList.add('is-floating-enter');
				applyFloatingGeometry();
			} else {
				dock.classList.add('is-home');
			}
			dock.classList.remove('is-preparing');

			window.requestAnimationFrame(function () {
				ready = true;
				window.clearTimeout(safetyReveal);

				if (willFloat) {
					dock.classList.remove('is-floating-enter');
					dock.classList.add('is-floating');
					state = STATE.FLOATING;
				} else {
					state = STATE.HOME;
				}
				dock.classList.add('is-ready');

				rebuildSentinelObserver();

				if (!nativeNodesPreserved() && window.console && window.console.error) {
					window.console.error('gloskin-ui1-purchase-dock: native Woo node identity changed during presentation composition');
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPurchaseDockFloat);
	} else {
		initPurchaseDockFloat();
	}
}());
