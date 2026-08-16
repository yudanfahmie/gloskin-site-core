(function () {
	'use strict';

	/*
	 * Commerce closure hardening.
	 *
	 * This module deliberately owns no variation resolution, cart endpoint,
	 * quantity state, modal open/close state, or success navigation. The core
	 * module remains the sole Gloskin AJAX mutation owner. This layer only
	 * closes propagation after that owner has claimed a native submit, mirrors
	 * the native busy state onto the already-visible modal proxy, removes the
	 * transient Woo-style PDP forward link before paint, and converges PDP
	 * identity presentation from existing PDP DOM.
	 */

	var proxyByForm = typeof WeakMap === 'function' ? new WeakMap() : null;
	var boundForms = typeof WeakSet === 'function' ? new WeakSet() : null;

	function nativeSubmit(form) {
		return form ? form.querySelector('.single_add_to_cart_button') : null;
	}

	function primaryPdpForm() {
		return document.querySelector('[data-gloskin-purchase-dock] form.cart');
	}

	function formForProxy(proxy) {
		if (!proxy) { return null; }
		var form = proxy.closest ? proxy.closest('form.cart') : null;
		if (form) { return form; }
		var modal = proxy.closest ? proxy.closest('[data-gloskin-overlay="quickadd"]') : null;
		if (modal && modal.querySelector('.gloskin-ui1-variable-modal__pdp')) { return primaryPdpForm(); }
		return null;
	}

	function setProxyBusy(proxy, busy) {
		if (!proxy) { return; }
		proxy.classList.toggle('is-loading', !!busy);
		if (busy) { proxy.setAttribute('aria-busy', 'true'); }
		else { proxy.removeAttribute('aria-busy'); }
	}

	function trackedProxy(form) {
		return proxyByForm && form ? proxyByForm.get(form) : null;
	}

	function trackProxy(form, proxy) {
		if (proxyByForm && form && proxy) { proxyByForm.set(form, proxy); }
	}

	function clearProxy(form) {
		var proxy = trackedProxy(form);
		if (proxy) { setProxyBusy(proxy, false); }
		if (proxyByForm && form) { proxyByForm.delete(form); }
	}

	function bindClaimedSubmitGuard(form) {
		if (!form) { return; }
		if (boundForms && boundForms.has(form)) { return; }
		if (form.getAttribute('data-gloskin-commerce-closure') === '1') { return; }
		form.setAttribute('data-gloskin-commerce-closure', '1');
		if (boundForms) { boundForms.add(form); }

		/* The native button must still perform its default submit activation, but
		 * a delegated document-level click mutation must not run in parallel with
		 * the canonical form-submit bridge. Stopping bubbling at the SAME button
		 * leaves its default form action intact and adds no mutation path. */
		var button = nativeSubmit(form);
		if (button && button.getAttribute('data-gloskin-commerce-click-guard') !== '1') {
			button.setAttribute('data-gloskin-commerce-click-guard', '1');
			button.addEventListener('click', function (event) { event.stopPropagation(); });
		}

		/* Core's form submit listener is registered first. Once it has chosen
		 * the AJAX path it synchronously marks the SAME native submit aria-busy.
		 * Seeing that marker here means the mutation has already been claimed;
		 * stop the same submit event from reaching any delegated Woo/plugin
		 * submit handler. No network call happens here. */
		form.addEventListener('submit', function (event) {
			var submitter = event.submitter || nativeSubmit(form);
			if (!submitter || submitter.getAttribute('aria-busy') !== 'true') { return; }
			var proxy = trackedProxy(form);
			if (proxy) { setProxyBusy(proxy, true); }
			event.stopImmediatePropagation();
		});
	}

	function clearAllProxyBusy() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-gloskin-variable-submit-proxy].is-loading'), function (proxy) {
			setProxyBusy(proxy, false);
		});
		var pdp = primaryPdpForm();
		if (pdp) { clearProxy(pdp); }
	}

	function cleanupPdpForwardLink() {
		var dock = document.querySelector('[data-gloskin-purchase-dock]');
		if (!dock) { return; }
		Array.prototype.forEach.call(dock.querySelectorAll('a.added_to_cart.wc-forward'), function (link) { link.remove(); });
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
		var modal = document.querySelector('[data-gloskin-overlay="quickadd"]');
		if (!modal || modal.getAttribute('aria-hidden') === 'true') { return; }
		var pdp = modal.querySelector('.gloskin-ui1-variable-modal__pdp');
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

	function onProxyCapture(event) {
		var proxy = event.target.closest && event.target.closest('[data-gloskin-variable-submit-proxy]');
		if (!proxy) { return; }
		var form = formForProxy(proxy);
		if (!form) { return; }
		bindClaimedSubmitGuard(form);
		var submit = nativeSubmit(form);
		if (submit && submit.getAttribute('aria-busy') === 'true') {
			event.preventDefault();
			event.stopImmediatePropagation();
			setProxyBusy(proxy, true);
			return;
		}
		trackProxy(form, proxy);
	}

	function boot() {
		var pdpForm = primaryPdpForm();
		if (pdpForm) { bindClaimedSubmitGuard(pdpForm); }
		document.addEventListener('gloskin:purchase-dock-ready', function (event) {
			var detail = event.detail || {};
			if (detail.form) { bindClaimedSubmitGuard(detail.form); }
		});
		document.addEventListener('click', onProxyCapture, true);
		document.addEventListener('click', function (event) {
			var trigger = event.target.closest && event.target.closest('[data-gloskin-variable-pdp-trigger]');
			if (trigger) { afterCurrentStack(renderPdpIdentityLikeCatalog); }
		}, true);
		if (window.jQuery && document.body) {
			window.jQuery(document.body).on('added_to_cart.gloskinCommerceClosure', function () {
				clearAllProxyBusy();
				afterCurrentStack(cleanupPdpForwardLink);
			});
			window.jQuery(document.body).on('wc_fragment_refresh.gloskinCommerceClosure', clearAllProxyBusy);
		}
	}

	if (typeof document !== 'undefined') {
		if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); }
		else { boot(); }
	}
}());