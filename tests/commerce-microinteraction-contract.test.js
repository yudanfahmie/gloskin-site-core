'use strict';

var assert = require('assert');
var core = require('../plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js');

function classListRecorder() {
	var classes = {};
	var adds = {};
	return {
		add: function (name) { classes[name] = true; adds[name] = (adds[name] || 0) + 1; },
		remove: function (name) { delete classes[name]; },
		contains: function (name) { return !!classes[name]; },
		addCount: function (name) { return adds[name] || 0; }
	};
}

function badge(value) {
	return { textContent: String(value), classList: classListRecorder(), offsetWidth: 18 };
}

function runtime(cartBadges, wishlistBadges, reduced) {
	var map = {
		'[data-gloskin-cart-count]': cartBadges || [],
		'[data-gloskin-wishlist-count]': wishlistBadges || []
	};
	return {
		document: {
			querySelector: function (selector) { return map[selector] && map[selector][0] ? map[selector][0] : null; },
			querySelectorAll: function (selector) { return map[selector] || []; }
		},
		matchMedia: function () { return { matches: !!reduced }; },
		setTimeout: function () { return 1; }
	};
}

var cartA = badge(10);
var cartB = badge(10);
var wishA = badge(4);
var wishB = badge(4);
var root = runtime([cartA, cartB], [wishA, wishB], false);

assert.strictEqual(core.initializeCommerceBadgeCounts(root), true, 'SSR badge counts initialize');

cartA.textContent = '11';
cartB.textContent = '11';
assert.strictEqual(core.animateCommerceBadgeDelta('cart', root), true, 'cart 10 -> 11 animates');
assert.strictEqual(cartA.textContent, '11', 'cart canonical text remains 11');
assert.strictEqual(cartA.classList.addCount('is-commerce-badge-added'), 1, 'first cart badge animates exactly once');
assert.strictEqual(cartB.classList.addCount('is-commerce-badge-added'), 1, 'second cart badge animates from same signal');

wishA.textContent = '5';
wishB.textContent = '5';
assert.strictEqual(core.animateCommerceBadgeDelta('wishlist', root), true, 'wishlist 4 -> 5 animates');
assert.strictEqual(wishA.textContent, '5', 'wishlist canonical text remains 5');
assert.strictEqual(wishA.classList.addCount('is-commerce-badge-added'), 1, 'first wishlist badge animates exactly once');
assert.strictEqual(wishB.classList.addCount('is-commerce-badge-added'), 1, 'second wishlist badge animates from same signal');

assert.strictEqual(core.animateCommerceBadgeDelta('wishlist', root), false, 'wishlist 5 -> 5 does not animate');
assert.strictEqual(wishA.classList.addCount('is-commerce-badge-added'), 1, 'no-change animation count remains zero');

wishA.textContent = '4';
wishB.textContent = '4';
assert.strictEqual(core.animateCommerceBadgeDelta('wishlist', root), false, 'wishlist removal does not use added motion');
assert.strictEqual(wishA.classList.addCount('is-commerce-badge-added'), 1, 'removal adds no animation');

var reducedCart = badge(7);
var reducedRoot = runtime([reducedCart], [], true);
core.initializeCommerceBadgeCounts(reducedRoot);
reducedCart.textContent = '8';
assert.strictEqual(core.animateCommerceBadgeDelta('cart', reducedRoot), false, 'reduced motion suppresses animation');
assert.strictEqual(reducedCart.textContent, '8', 'reduced motion never delays canonical count');
assert.strictEqual(reducedCart.classList.addCount('is-commerce-badge-added'), 0, 'reduced motion applies no animation class');

console.log('commerce microinteraction contract passed');
