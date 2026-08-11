'use strict';
/**
 * Behavioral proof of the Woo add_to_cart AJAX payload contract fixed in
 * this hotfix -- exercises the real production functions from
 * gloskin-ui1-core.js (required directly, not reimplemented here) against
 * fixtures shaped exactly like WooCommerce's own rendered markup. No DOM
 * emulation dependency: fields are plain objects implementing only the
 * small querySelector(All) surface the functions under test actually use.
 */
const assert = require('assert');
const {
	resolveWooSubmitter,
	shouldInterceptWooSubmit,
	buildAddToCartPayload
} = require('../plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js');

function field(attrs) {
	const f = Object.assign({ tag: 'input', type: 'text', disabled: false, checked: true, value: '', name: '', className: '' }, attrs);
	f.classList = { contains: (name) => f.className.split(' ').indexOf(name) !== -1 };
	return f;
}

/**
 * Minimal form fixture: matches only the exact selector strings the
 * production code queries, against a flat field list -- not a general
 * CSS engine, deliberately, since the functions under test never need one.
 */
function form(fields, className) {
	className = className || 'cart';
	const classes = className.split(' ');
	return {
		classList: { contains: (name) => classes.indexOf(name) !== -1 },
		querySelector(selector) {
			if (selector === 'input.variation_id, input[name="variation_id"]') {
				return fields.find((f) => f.name === 'variation_id') || null;
			}
			if (selector === 'input[name="add-to-cart"]') {
				return fields.find((f) => f.name === 'add-to-cart' && f.tag === 'input') || null;
			}
			if (selector === '.single_add_to_cart_button[type="submit"]' || selector === '.single_add_to_cart_button') {
				return fields.find((f) => f.isAddToCartButton) || null;
			}
			return null;
		},
		querySelectorAll(selector) {
			if (selector === 'input[name], select[name], textarea[name]') {
				return fields.filter((f) => f.name && f.tag !== 'button');
			}
			return [];
		}
	};
}

// -------------------------------------------------------------------
// A. SIMPLE product fixture -- Woo's real single-product/add-to-cart/
// simple.php template puts name="add-to-cart" value="<id>" on the
// *submit button itself*, never a hidden field.
// -------------------------------------------------------------------
(function testSimpleProductPayload() {
	const quantity = field({ name: 'quantity', value: '2' });
	const button = field({
		tag: 'button', type: 'submit', name: 'add-to-cart', value: '101',
		isAddToCartButton: true, className: 'single_add_to_cart_button'
	});
	const simpleForm = form([quantity, button]);

	const submitter = resolveWooSubmitter(simpleForm, { submitter: button });
	assert.strictEqual(submitter, button, 'resolveWooSubmitter must prefer event.submitter');
	assert.strictEqual(shouldInterceptWooSubmit(simpleForm, submitter), true, 'simple product with an enabled button must be intercepted');

	const payload = buildAddToCartPayload(simpleForm, submitter);
	assert.strictEqual(payload.get('product_id'), '101', 'simple product_id must come from the submit button, not a nonexistent hidden field');
	assert.strictEqual(payload.get('quantity'), '2', 'quantity field must travel through unmodified');

	// event.submitter unavailable (older engine) -- must fall back to the
	// canonical .single_add_to_cart_button and still resolve correctly.
	const fallbackSubmitter = resolveWooSubmitter(simpleForm, null);
	assert.strictEqual(fallbackSubmitter, button, 'resolveWooSubmitter must fall back to .single_add_to_cart_button');
	const fallbackPayload = buildAddToCartPayload(simpleForm, fallbackSubmitter);
	assert.strictEqual(fallbackPayload.get('product_id'), '101', 'fallback submitter path must still derive the correct product_id');

	console.log('A. simple product AJAX payload: OK');
})();

// -------------------------------------------------------------------
// B. VARIABLE product fixture -- hidden add-to-cart=202 (parent), hidden
// product_id=202 (parent), hidden variation_id=205 (Woo-selected). The
// mutation payload's product_id must become 205, never the parent 202.
// -------------------------------------------------------------------
(function testVariableProductPayload() {
	const addToCartHidden = field({ name: 'add-to-cart', value: '202' });
	const productIdHidden = field({ name: 'product_id', value: '202' });
	const variationIdHidden = field({ name: 'variation_id', value: '205' });
	const quantity = field({ name: 'quantity', value: '1' });
	const button = field({
		tag: 'button', type: 'submit', isAddToCartButton: true,
		className: 'single_add_to_cart_button'
	}); // Woo's variable.php button carries no name/value of its own.
	const variableForm = form([addToCartHidden, productIdHidden, variationIdHidden, quantity, button], 'cart variations_form');

	assert.strictEqual(shouldInterceptWooSubmit(variableForm, button), true, 'a valid Woo-selected variation must be interceptable');

	const payload = buildAddToCartPayload(variableForm, button);
	assert.strictEqual(payload.get('product_id'), '205', 'variable AJAX must post the selected variation as product_id, never the parent');
	assert.strictEqual(payload.get('variation_id'), '205', 'variation_id field must still be present');

	console.log('B. variable product AJAX payload uses the selected variation, never the parent: OK');
})();

// -------------------------------------------------------------------
// C. VARIABLE product with no selection yet (variation_id=0) -- must
// never be intercepted, so no Gloskin AJAX mutation is ever attempted.
// -------------------------------------------------------------------
(function testNoVariationSelectedNeverIntercepted() {
	const addToCartHidden = field({ name: 'add-to-cart', value: '202' });
	const variationIdHidden = field({ name: 'variation_id', value: '0' });
	const button = field({ tag: 'button', type: 'submit', isAddToCartButton: true, disabled: true });
	const unselectedForm = form([addToCartHidden, variationIdHidden, button], 'cart variations_form');

	assert.strictEqual(shouldInterceptWooSubmit(unselectedForm, button), false, 'variation_id=0 must never be intercepted for AJAX mutation');

	// Missing the field entirely must be treated identically to zero.
	const missingFieldForm = form([addToCartHidden, field({ tag: 'button', type: 'submit', isAddToCartButton: true })], 'cart variations_form');
	const enabledButton = missingFieldForm.querySelector('.single_add_to_cart_button');
	assert.strictEqual(shouldInterceptWooSubmit(missingFieldForm, enabledButton), false, 'a variations_form with no variation_id field at all must never be intercepted');

	console.log('C. unselected variation is never AJAX-mutated: OK');
})();

// -------------------------------------------------------------------
// D. A disabled Woo button (native invalid/out-of-stock state) must
// never be intercepted, simple or variable.
// -------------------------------------------------------------------
(function testDisabledButtonNeverIntercepted() {
	const disabledButton = field({ tag: 'button', type: 'submit', isAddToCartButton: true, disabled: true });
	const simpleForm = form([field({ name: 'quantity', value: '1' }), disabledButton]);
	assert.strictEqual(shouldInterceptWooSubmit(simpleForm, disabledButton), false, 'a disabled submit control must never be intercepted');
	console.log('D. disabled Woo button is never intercepted: OK');
})();

console.log('single-product AJAX payload contract: OK');
