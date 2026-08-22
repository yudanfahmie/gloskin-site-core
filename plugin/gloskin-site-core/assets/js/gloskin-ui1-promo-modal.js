/* Production Promo Modal — lightweight first-party controller. */
(function () {
	'use strict';

	var root = document.querySelector('[data-gloskin-promo-modal]');
	if (!root) { return; }

	var dialog = root.querySelector('.gloskin-promo-modal__dialog');
	var track = root.querySelector('[data-gloskin-promo-track]');
	var slider = root.querySelector('[data-gloskin-promo-slider]');
	var closeButtons = root.querySelectorAll('[data-gloskin-promo-close]');
	var previousButton = root.querySelector('[data-gloskin-promo-prev]');
	var nextButton = root.querySelector('[data-gloskin-promo-next]');
	var dotsNode = root.querySelector('[data-gloskin-promo-dots]');
	var originalSlides = track ? Array.prototype.slice.call(track.querySelectorAll('[data-gloskin-promo-slide]')) : [];
	var reducedMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	var isOpen = false;
	var hasShown = false;
	var lastFocus = null;
	var autoplayTimer = null;
	var physicalIndex = originalSlides.length > 1 ? 1 : 0;
	var realIndex = 0;
	var suppressClick = false;
	var pointerStartX = null;
	var isTransitioning = false;
	var transitionFallback = null;

	function clamp(value, minimum, maximum, fallback) {
		value = Number(value);
		if (!Number.isFinite(value)) { return fallback; }
		return Math.max(minimum, Math.min(maximum, value));
	}

	function applyCropFraming() {
		originalSlides.forEach(function (slide) {
			var image = slide.querySelector('.gloskin-promo-modal__image');
			if (!image) { return; }
			var focusX = clamp(slide.getAttribute('data-focus-x'), 0, 100, 50);
			var focusY = clamp(slide.getAttribute('data-focus-y'), 0, 100, 50);
			var zoom = clamp(slide.getAttribute('data-crop-zoom'), 100, 300, 100) / 100;
			image.style.setProperty('--gloskin-promo-focus-x', focusX + '%');
			image.style.setProperty('--gloskin-promo-focus-y', focusY + '%');
			image.style.setProperty('--gloskin-promo-scale', String(zoom));
		});
	}

	function focusables() {
		if (!dialog) { return []; }
		return Array.prototype.slice.call(dialog.querySelectorAll('a[href]:not([tabindex="-1"]),button:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter(function (element) {
			return !element.hidden && element.getAttribute('aria-hidden') !== 'true' && element.offsetParent !== null;
		});
	}

	function updateSlideAccessibility() {
		originalSlides.forEach(function (slide, index) {
			var active = index === realIndex;
			if (slide.matches('a[href]')) {
				slide.tabIndex = active ? 0 : -1;
			} else {
				slide.removeAttribute('tabindex');
			}
			slide.setAttribute('aria-hidden', active ? 'false' : 'true');
		});
		if (dotsNode) {
			dotsNode.querySelectorAll('.gloskin-promo-modal__dot').forEach(function (dot, index) {
				dot.classList.toggle('is-active', index === realIndex);
			});
		}
	}

	function setTrackPosition(immediate) {
		if (!track) { return; }
		track.classList.toggle('is-jumping', !!immediate);
		track.style.transform = 'translate3d(' + (-physicalIndex * 100) + '%,0,0)';
		if (immediate) {
			void track.offsetWidth;
			track.classList.remove('is-jumping');
		}
	}

	function settleTransition() {
		if (transitionFallback) {
			window.clearTimeout(transitionFallback);
			transitionFallback = null;
		}
		if (physicalIndex === 0) {
			physicalIndex = originalSlides.length;
			setTrackPosition(true);
		} else if (physicalIndex === originalSlides.length + 1) {
			physicalIndex = 1;
			setTrackPosition(true);
		}
		isTransitioning = false;
	}

	function resetAutoplay() {
		if (autoplayTimer) { window.clearInterval(autoplayTimer); autoplayTimer = null; }
		if (reducedMotion || !isOpen || originalSlides.length < 2 || document.hidden) { return; }
		autoplayTimer = window.setInterval(function () { move(1); }, 4800);
	}

	function move(delta) {
		if (originalSlides.length < 2 || !track || isTransitioning) { return; }
		isTransitioning = true;
		physicalIndex += delta;
		realIndex = (realIndex + delta + originalSlides.length) % originalSlides.length;
		updateSlideAccessibility();
		setTrackPosition(false);
		transitionFallback = window.setTimeout(settleTransition, reducedMotion ? 20 : 850);
		resetAutoplay();
	}

	function initializeSlider() {
		if (!track || originalSlides.length < 2) {
			updateSlideAccessibility();
			return;
		}
		var firstClone = originalSlides[0].cloneNode(true);
		var lastClone = originalSlides[originalSlides.length - 1].cloneNode(true);
		firstClone.removeAttribute('data-gloskin-promo-slide');
		lastClone.removeAttribute('data-gloskin-promo-slide');
		firstClone.setAttribute('aria-hidden', 'true');
		lastClone.setAttribute('aria-hidden', 'true');
		firstClone.tabIndex = -1;
		lastClone.tabIndex = -1;
		track.insertBefore(lastClone, originalSlides[0]);
		track.appendChild(firstClone);

		if (dotsNode) {
			originalSlides.forEach(function (_, index) {
				var dot = document.createElement('span');
				dot.className = 'gloskin-promo-modal__dot' + (index === 0 ? ' is-active' : '');
				dotsNode.appendChild(dot);
			});
		}

		track.addEventListener('transitionend', function (event) {
			if (event.propertyName !== 'transform') { return; }
			settleTransition();
		});
		updateSlideAccessibility();
		setTrackPosition(true);
	}

	function show() {
		if (isOpen || !root.isConnected) { return; }
		lastFocus = document.activeElement;
		root.hidden = false;
		root.setAttribute('aria-hidden', 'false');
		document.body.classList.add('gloskin-promo-modal-open');
		isOpen = true;
		window.requestAnimationFrame(function () {
			root.classList.add('is-open');
			var close = root.querySelector('.gloskin-promo-modal__close');
			if (close) { close.focus({ preventScroll: true }); }
			else if (dialog) { dialog.focus({ preventScroll: true }); }
		});
		resetAutoplay();
	}

	function close() {
		if (!isOpen) { return; }
		if (autoplayTimer) { window.clearInterval(autoplayTimer); autoplayTimer = null; }
		if (transitionFallback) { window.clearTimeout(transitionFallback); transitionFallback = null; }
		isTransitioning = false;
		root.classList.remove('is-open');
		document.body.classList.remove('gloskin-promo-modal-open');
		isOpen = false;
		window.setTimeout(function () {
			root.hidden = true;
			root.setAttribute('aria-hidden', 'true');
			if (lastFocus && typeof lastFocus.focus === 'function') { lastFocus.focus({ preventScroll: true }); }
		}, reducedMotion ? 0 : 560);
	}

	function onScrollTrigger() {
		if (hasShown) { return; }
		var scrollable = document.documentElement.scrollHeight - window.innerHeight;
		if (scrollable <= 0) { return; }
		var percent = (window.scrollY / scrollable) * 100;
		if (percent < 30) { return; }
		hasShown = true;
		window.removeEventListener('scroll', onScrollTrigger);
		show();
	}

	closeButtons.forEach(function (button) {
		button.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			close();
		});
	});
	if (previousButton) {
		previousButton.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			move(-1);
		});
	}
	if (nextButton) {
		nextButton.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			move(1);
		});
	}

	root.addEventListener('keydown', function (event) {
		if (!isOpen) { return; }
		if (event.key === 'Escape') {
			event.preventDefault();
			close();
			return;
		}
		if (event.key !== 'Tab') { return; }
		var items = focusables();
		if (!items.length) { event.preventDefault(); return; }
		var first = items[0];
		var last = items[items.length - 1];
		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	});

	if (slider && originalSlides.length > 1) {
		slider.addEventListener('pointerdown', function (event) {
			if (event.pointerType === 'mouse' && event.button !== 0) { return; }
			pointerStartX = event.clientX;
			suppressClick = false;
		});
		slider.addEventListener('pointerup', function (event) {
			if (pointerStartX === null) { return; }
			var delta = event.clientX - pointerStartX;
			pointerStartX = null;
			if (Math.abs(delta) < 42) { return; }
			suppressClick = true;
			move(delta < 0 ? 1 : -1);
		});
		slider.addEventListener('pointercancel', function () { pointerStartX = null; });
		slider.addEventListener('click', function (event) {
			if (!suppressClick) { return; }
			event.preventDefault();
			event.stopPropagation();
			suppressClick = false;
		}, true);
		slider.addEventListener('mouseenter', function () {
			if (autoplayTimer) { window.clearInterval(autoplayTimer); autoplayTimer = null; }
		});
		slider.addEventListener('mouseleave', resetAutoplay);
		slider.addEventListener('focusin', function () {
			if (autoplayTimer) { window.clearInterval(autoplayTimer); autoplayTimer = null; }
		});
		slider.addEventListener('focusout', resetAutoplay);
	}

	document.addEventListener('visibilitychange', resetAutoplay);
	applyCropFraming();
	initializeSlider();
	window.addEventListener('scroll', onScrollTrigger, { passive: true });
	onScrollTrigger();
})();