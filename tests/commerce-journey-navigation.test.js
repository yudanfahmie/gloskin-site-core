'use strict';

var assert = require('assert');
var fs = require('fs');
var path = require('path');
var journey = require('../plugin/gloskin-site-core/assets/js/gloskin-ui1-commerce-journey.js');

function classes() {
	var state = {};
	return {
		add: function (name) { state[name] = true; },
		remove: function (name) { delete state[name]; },
		contains: function (name) { return !!state[name]; }
	};
}

function makeStorage(initial, failWrites) {
	var data = initial || {};
	return {
		getItem: function (key) { return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null; },
		setItem: function (key, value) { if (failWrites) { throw new Error('blocked'); } data[key] = String(value); },
		removeItem: function (key) { delete data[key]; },
		data: data
	};
}

function makeRoot(url, options) {
	options = options || {};
	var target = new URL(url);
	var assigned = [];
	var timers = [];
	return {
		location: {
			href: target.href,
			origin: target.origin,
			pathname: target.pathname,
			search: target.search,
			assign: function (href) { if (options.failAssign) { throw new Error('assign blocked'); } assigned.push(href); }
		},
		sessionStorage: options.storage || makeStorage(),
		matchMedia: function () { return { matches: !!options.reduced }; },
		setTimeout: function (fn, delay) { timers.push({ fn: fn, delay: delay }); return timers.length; },
		assigned: assigned,
		timers: timers
	};
}

function makeDoc(links) {
	var events = {};
	return {
		readyState: 'complete',
		documentElement: { classList: classes() },
		querySelectorAll: function (selector) {
			assert.strictEqual(selector, '[data-gloskin-commerce-progress] a[href]', 'selector stays scoped to journey anchors');
			return links || [];
		},
		addEventListener: function (type, fn) { events[type] = fn; },
		events: events
	};
}

function anchor(href, options) {
	options = options || {};
	var attrs = options.download ? { download: true } : {};
	return {
		href: href,
		target: options.target || '',
		hasAttribute: function (name) { return !!attrs[name]; },
		addEventListener: function (type, fn) { this.listenerType = type; this.listener = fn; }
	};
}

function click(options) {
	options = options || {};
	return {
		button: options.button === undefined ? 0 : options.button,
		metaKey: !!options.metaKey,
		ctrlKey: !!options.ctrlKey,
		shiftKey: !!options.shiftKey,
		altKey: !!options.altKey,
		defaultPrevented: !!options.defaultPrevented,
		prevented: 0,
		preventDefault: function () { this.prevented += 1; this.defaultPrevented = true; }
	};
}

var root = makeRoot('https://example.test/cart/');
var doc = makeDoc();
var checkout = anchor('https://example.test/checkout/');
var normal = click();
assert.strictEqual(journey.shouldIntercept(normal, checkout, root), true, 'normal primary same-origin click is eligible');
assert.strictEqual(journey.handleJourneyClick(normal, checkout, root, doc), true, 'normal journey click is enhanced');
assert.strictEqual(normal.prevented, 1, 'eligible click is prevented only after fallback marker succeeds');
assert.strictEqual(checkout.href, 'https://example.test/checkout/', 'canonical anchor href is never rewritten');
assert.strictEqual(root.sessionStorage.data[journey.STORAGE_KEY], '/checkout/', 'only destination presentation marker is stored');
assert.strictEqual(doc.documentElement.classList.contains(journey.LEAVING_CLASS), true, 'outgoing Woo region enters handoff state');
assert.strictEqual(root.assigned.length, 0, 'native navigation waits for bounded handoff');
assert.strictEqual(root.timers[0].delay, 100, 'handoff delay remains short and bounded');
root.timers[0].fn();
assert.deepStrictEqual(root.assigned, ['https://example.test/checkout/'], 'handoff finishes through canonical native location.assign');

[
	{ button: 1 },
	{ metaKey: true },
	{ ctrlKey: true },
	{ shiftKey: true },
	{ altKey: true },
	{ defaultPrevented: true }
].forEach(function (mods) {
	var event = click(mods);
	assert.strictEqual(journey.shouldIntercept(event, checkout, makeRoot('https://example.test/cart/')), false, 'non-primary/modified/default-owned click remains native');
});
assert.strictEqual(journey.shouldIntercept(click(), anchor('https://other.test/checkout/'), makeRoot('https://example.test/cart/')), false, 'cross-origin link remains native');
assert.strictEqual(journey.shouldIntercept(click(), anchor('https://example.test/checkout/', { target: '_blank' }), makeRoot('https://example.test/cart/')), false, 'new-tab target remains native');
assert.strictEqual(journey.shouldIntercept(click(), anchor('https://example.test/checkout/', { download: true }), makeRoot('https://example.test/cart/')), false, 'download link remains native');

var blockedRoot = makeRoot('https://example.test/cart/', { storage: makeStorage({}, true) });
var blockedEvent = click();
assert.strictEqual(journey.handleJourneyClick(blockedEvent, checkout, blockedRoot, makeDoc()), false, 'blocked storage declines enhancement');
assert.strictEqual(blockedEvent.prevented, 0, 'blocked storage fails open to untouched anchor navigation');
assert.strictEqual(blockedRoot.assigned.length, 0, 'fail-open path leaves navigation to browser default');

var assignFallbackRoot = makeRoot('https://example.test/cart/', { failAssign: true });
var assignFallbackEvent = click();
assert.strictEqual(journey.handleJourneyClick(assignFallbackEvent, checkout, assignFallbackRoot, makeDoc()), true, 'assign failure still has native href fallback');
assignFallbackRoot.timers[0].fn();
assert.strictEqual(assignFallbackRoot.location.href, 'https://example.test/checkout/', 'assign failure falls through to canonical location.href');

var reducedRoot = makeRoot('https://example.test/cart/', { reduced: true });
var reducedDoc = makeDoc();
var reducedEvent = click();
assert.strictEqual(journey.handleJourneyClick(reducedEvent, checkout, reducedRoot, reducedDoc), true, 'reduced motion keeps enhancement functional');
assert.deepStrictEqual(reducedRoot.assigned, ['https://example.test/checkout/'], 'reduced motion navigates immediately');
assert.strictEqual(reducedDoc.documentElement.classList.contains(journey.LEAVING_CLASS), false, 'reduced motion adds no outgoing animation state');

var arrivingStorage = makeStorage({ gloskinCommerceJourneyTarget: '/checkout/?step=1' });
var arrivingRoot = makeRoot('https://example.test/checkout/?step=1', { storage: arrivingStorage });
var arrivingDoc = makeDoc();
assert.strictEqual(journey.prepareArrival(arrivingRoot, arrivingDoc), true, 'matching destination marker prepares incoming mask');
assert.strictEqual(arrivingDoc.documentElement.classList.contains(journey.ARRIVING_CLASS), true, 'incoming Woo region is masked, not cloned');
assert.strictEqual(arrivingStorage.getItem(journey.STORAGE_KEY), null, 'arrival marker is one-shot');

var staleStorage = makeStorage({ gloskinCommerceJourneyTarget: '/checkout/' });
var staleRoot = makeRoot('https://example.test/cart/', { storage: staleStorage });
assert.strictEqual(journey.prepareArrival(staleRoot, makeDoc()), false, 'stale marker never masks another route');
assert.strictEqual(staleStorage.getItem(journey.STORAGE_KEY), null, 'stale marker is cleared');

var linkA = anchor('https://example.test/checkout/');
assert.strictEqual(journey.bindJourneyLinks(makeRoot('https://example.test/cart/'), makeDoc([linkA])), 1, 'only journey link set is bound');
assert.strictEqual(linkA.listenerType, 'click', 'journey anchor receives its own click listener');

var excludedDoc = makeDoc([anchor('https://example.test/cart/')]);
excludedDoc.body = { classList: classes() };
excludedDoc.body.classList.add('woocommerce-order-received');
assert.strictEqual(journey.bindJourneyLinks(makeRoot('https://example.test/checkout/order-received/42/'), excludedDoc), 0, 'order-received endpoint is not enhanced');

var source = fs.readFileSync(path.join(__dirname, '../plugin/gloskin-site-core/assets/js/gloskin-ui1-commerce-journey.js'), 'utf8');
assert(source.indexOf("querySelectorAll('[data-gloskin-commerce-progress] a[href]')") !== -1, 'source is explicitly journey-scoped');
assert(source.indexOf("document.addEventListener('click'") === -1, 'no global anchor interception');
['fetch(', 'XMLHttpRequest', 'DOMParser', '.innerHTML', 'history.pushState', 'history.replaceState', 'MutationObserver', 'ResizeObserver', '@view-transition', 'view-transition-name'].forEach(function (forbidden) {
	assert.strictEqual(source.indexOf(forbidden), -1, 'forbidden fake-SPA/retired transition mechanism absent: ' + forbidden);
});
assert.strictEqual(source.indexOf('/cart/'), -1, 'Cart route is never hard-coded in production journey JS');
assert.strictEqual(source.indexOf('/checkout/'), -1, 'Checkout route is never hard-coded in production journey JS');

console.log('commerce journey navigation contract passed');
