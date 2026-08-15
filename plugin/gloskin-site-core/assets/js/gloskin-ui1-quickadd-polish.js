(function () {
	'use strict';

	var quickAddBody = document.querySelector('[data-gloskin-quickadd-body]');
	var label = 'Tambahkan ke keranjang';

	if (!quickAddBody) {
		return;
	}

	function normalizeCtaLabel() {
		var button = quickAddBody.querySelector('.gloskin-ui1-quickadd__form form.cart .single_add_to_cart_button');
		if (button && button.textContent.trim() !== label) {
			button.textContent = label;
		}
	}

	normalizeCtaLabel();

	if (typeof MutationObserver === 'undefined') {
		return;
	}

	/* Core Quick Add replaces the modal body's projection after its read-only
	 * request completes. Observe only that body so this presentation copy
	 * normalization follows each fresh native Woo form without owning any
	 * variation, quantity, disabled-state or cart behavior. */
	var observer = new MutationObserver(normalizeCtaLabel);
	observer.observe(quickAddBody, { childList: true, subtree: true });
}());
