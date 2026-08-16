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
	var events = {};
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
		addEventListener: function (type, fn) { events[type] = fn; },
		assigned: assigned,
		timers: timers,
		events: events
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
assert.strictEqual(root.timers[0].delay, journey.HANDOFF_DELAY_MS, 'handoff delay remains short and bounded');
assert.strictEqual(root.timers[1].delay, journey.OUTGOING_FAIL_OPEN_MS, 'one bounded outgoing fail-open timer is armed');
root.timers[0].fn();
assert.deepStrictEqual(root.assigned, ['https://example.test/checkout/'], 'handoff finishes through canonical native location.assign');

var stalledRoot = makeRoot('https://example.test/cart/');
var stalledDoc = makeDoc();
var stalledEvent = click();
assert.strictEqual(journey.handleJourneyClick(stalledEvent, checkout, stalledRoot, stalledDoc), true, 'stalled navigation starts from normal enhanced path');
stalledRoot.timers[0].fn();
assert.strictEqual(stalledDoc.documentElement.classList.contains(journey.LEAVING_CLASS), true, 'fake stalled document remains masked until fail-open boundary');
stalledRoot.timers[1].fn();
assert.strictEqual(stalledDoc.documentElement.classList.contains(journey.LEAVING_CLASS), false, 'fail-open restores Woo content interaction if document navigation does not complete');
assert.strictEqual(stalledRoot.sessionStorage.getItem(journey.STORAGE_KEY), null, 'fail-open clears the stale destination marker on the source document');

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
assert.strictEqual(assignFallbackRoot.sessionStorage.getItem(journey.STORAGE_KEY), '/checkout/', 'href fallback preserves the one-shot arrival marker for the destination');

var reducedRoot = makeRoot('https://example.test/cart/', { reduced: true });
var reducedDoc = makeDoc();
var reducedEvent = click();
assert.strictEqual(journey.handleJourneyClick(reducedEvent, checkout, reducedRoot, reducedDoc), true, 'reduced motion keeps enhancement functional');
assert.deepStrictEqual(reducedRoot.assigned, ['https://example.test/checkout/'], 'reduced motion navigates immediately');
assert.strictEqual(reducedDoc.documentElement.classList.contains(journey.LEAVING_CLASS), false, 'reduced motion adds no outgoing animation state');
assert.strictEqual(reducedRoot.timers.length, 1, 'reduced motion keeps only the bounded fail-open timer');
assert.strictEqual(reducedRoot.timers[0].delay, journey.OUTGOING_FAIL_OPEN_MS, 'reduced-motion fail-open remains bounded');

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

var bfcacheStorage = makeStorage({ gloskinCommerceJourneyTarget: '/checkout/' });
var bfcacheRoot = makeRoot('https://example.test/cart/', { storage: bfcacheStorage });
var bfcacheDoc = makeDoc();
bfcacheDoc.documentElement.classList.add(journey.LEAVING_CLASS);
bfcacheDoc.documentElement.classList.add(journey.ARRIVING_CLASS);
assert.strictEqual(journey.handlePageShow({ persisted: false }, bfcacheRoot, bfcacheDoc), false, 'normal pageshow does not reset journey state globally');
assert.strictEqual(bfcacheDoc.documentElement.classList.contains(journey.LEAVING_CLASS), true, 'normal pageshow leaves current visual state untouched');
assert.strictEqual(journey.handlePageShow({ persisted: true }, bfcacheRoot, bfcacheDoc), true, 'BFCache restoration activates journey-owned recovery');
assert.strictEqual(bfcacheDoc.documentElement.classList.contains(journey.LEAVING_CLASS), false, 'BFCache clears stale leaving state');
assert.strictEqual(bfcacheDoc.documentElement.classList.contains(journey.ARRIVING_CLASS), false, 'BFCache clears stale arriving state');
assert.strictEqual(bfcacheStorage.getItem(journey.STORAGE_KEY), null, 'BFCache recovery clears stale journey marker');

var bootRoot = makeRoot('https://example.test/cart/');
var bootDoc = makeDoc();
journey.boot(bootRoot, bootDoc);
assert.strictEqual(typeof bootRoot.events.pageshow, 'function', 'boot binds the single journey-owned pageshow recovery listener');

var linkA = anchor('https://example.test/checkout/');
assert.strictEqual(journey.bindJourneyLinks(makeRoot('https://example.test/cart/'), makeDoc([linkA])), 1, 'only journey link set is bound');
assert.strictEqual(linkA.listenerType, 'click', 'journey anchor receives its own click listener');

var excludedDoc = makeDoc([anchor('https://example.test/cart/')]);
excludedDoc.body = { classList: classes() };
excludedDoc.body.classList.add('woocommerce-order-received');
assert.strictEqual(journey.bindJourneyLinks(makeRoot('https://example.test/checkout/order-received/42/'), excludedDoc), 0, 'order-received endpoint is not enhanced');

var source = fs.readFileSync(path.join(__dirname, '../plugin/gloskin-site-core/assets/js/gloskin-ui1-commerce-journey.js'), 'utf8');
var css = fs.readFileSync(path.join(__dirname, '../plugin/gloskin-site-core/assets/css/gloskin-ui1-commerce-polish.css'), 'utf8');
assert(source.indexOf("querySelectorAll('[data-gloskin-commerce-progress] a[href]')") !== -1, 'source is explicitly journey-scoped');
assert(source.indexOf("root.addEventListener('pageshow'") !== -1, 'BFCache recovery is the only journey-owned global lifecycle listener');
assert(source.indexOf("event.persisted !== true") !== -1, 'pageshow recovery is gated to genuine BFCache restores');
assert(source.indexOf('OUTGOING_FAIL_OPEN_MS') !== -1, 'outgoing navigation retains the named bounded fail-open boundary');
assert(source.indexOf("document.addEventListener('click'") === -1, 'no global anchor interception');
['fetch(', 'XMLHttpRequest', 'DOMParser', '.innerHTML', 'history.pushState', 'history.replaceState', 'MutationObserver', 'ResizeObserver', 'setInterval(', 'requestAnimationFrame(', '@view-transition', 'view-transition-name'].forEach(function (forbidden) {
	assert.strictEqual(source.indexOf(forbidden), -1, 'forbidden fake-SPA/polling/retired transition mechanism absent: ' + forbidden);
});
assert.strictEqual(source.indexOf('/cart/'), -1, 'Cart route is never hard-coded in production journey JS');
assert.strictEqual(source.indexOf('/checkout/'), -1, 'Checkout route is never hard-coded in production journey JS');

assert(css.indexOf('body.woocommerce-cart .gloskin-ui1-commerce-native,\nbody.woocommerce-checkout .gloskin-ui1-commerce-native{\n\t--gloskin-commerce-handoff-size:clamp(96px,8vw,116px);') !== -1, 'native Woo parent remains the geometry-stable stage and owns a large responsive loader size');
assert(css.indexOf('.gloskin-ui1-commerce-native > *') !== -1, 'journey masks rendered Woo child content rather than the parent stage');
assert(css.indexOf('.gloskin-ui1-commerce-native{\n\topacity:0;') === -1, 'parent commerce stage is never opacity-zero');
assert.strictEqual(css.indexOf('favicon-32x32.png'), -1, 'old favicon is no longer a handoff loader dependency');
assert.strictEqual(css.indexOf('gloskin-ui1-commerce-handoff-breathe'), -1, 'old favicon breathing choreography is retired');
assert.strictEqual(css.toLowerCase().indexOf('codepen.io'), -1, 'CodePen is not embedded or loaded at runtime');
assert.strictEqual(css.indexOf('MYJJrVL'), -1, 'reference identifier is not a runtime dependency');
assert(css.indexOf('body.woocommerce-cart .gloskin-ui1-commerce-native::before') !== -1 && css.indexOf('body.woocommerce-cart .gloskin-ui1-commerce-native::after') !== -1, 'loader uses only the existing stage pseudo-elements');
assert(css.indexOf('position:fixed;\n\ttop:50%;\n\tleft:50%;') !== -1, 'loader is fixed and viewport-centered');
assert(css.indexOf('--gloskin-commerce-handoff-size:clamp(96px,8vw,116px);') !== -1, 'desktop loader footprint is substantially larger than 32px');
assert(css.indexOf('--gloskin-commerce-handoff-size:clamp(78px,22vw,92px);') !== -1, 'mobile loader footprint stays substantial and responsive');
['var(--gloskin-accent)', 'var(--gloskin-accent-strong)', 'var(--gloskin-text)', 'var(--gloskin-border)', 'var(--gloskin-inverse)'].forEach(function (token) {
	assert(css.indexOf(token) !== -1, 'loader stays in the Gloskin token palette: ' + token);
});
assert(css.indexOf('gloskin-ui1-commerce-journey-leaving body.woocommerce-cart .gloskin-ui1-commerce-native::before') !== -1, 'outer loader appears for leaving state');
assert(css.indexOf('gloskin-ui1-commerce-journey-arriving body.woocommerce-cart .gloskin-ui1-commerce-native::before') !== -1, 'outer loader appears for arriving state');
assert(css.indexOf('gloskin-ui1-commerce-journey-leaving body.woocommerce-cart .gloskin-ui1-commerce-native::after') !== -1, 'core loader appears for leaving state');
assert(css.indexOf('gloskin-ui1-commerce-journey-arriving body.woocommerce-cart .gloskin-ui1-commerce-native::after') !== -1, 'core loader appears for arriving state');
assert(css.indexOf('animation:gloskin-ui1-commerce-handoff-bloom-outer 1480ms cubic-bezier(.45,0,.25,1) infinite;') !== -1, 'outer bloom loops only while journey state is active');
assert(css.indexOf('animation:gloskin-ui1-commerce-handoff-bloom-core 1480ms cubic-bezier(.45,0,.25,1) infinite;') !== -1, 'inner bloom loops only while journey state is active');
assert(css.indexOf('0%,100%{opacity:.34;transform:translate(-50%,-50%) scale(.72)}') !== -1 && css.indexOf('0%,100%{opacity:.92;transform:translate(-50%,-50%) scale(1)}') !== -1, 'active bloom layers never share a zero-opacity frame');
assert(css.indexOf('opacity:0;\n\tpointer-events:none;\n\ttransform:translate(-50%,-50%) scale(.72);') !== -1, 'loader disappears automatically when journey classes clear');
assert(css.indexOf('opacity:0;\n\tpointer-events:none;\n}') !== -1, 'Woo content remains masked while active loader is visible');
assert(css.indexOf('transition:opacity 170ms cubic-bezier(.22,1,.36,1);') !== -1, 'Woo content crossfade remains short and unchanged');
var reducedCss = css.slice(css.indexOf('@media (prefers-reduced-motion:reduce)'));
assert(reducedCss.indexOf('animation:none;') !== -1, 'reduced motion uses static loader frames');
assert.strictEqual(reducedCss.indexOf('infinite'), -1, 'reduced-motion block contains no repeating loader choreography');
['@view-transition', 'view-transition-name'].forEach(function (forbiddenCss) {
	assert.strictEqual(css.indexOf(forbiddenCss), -1, 'retired View Transition ownership remains absent from CSS: ' + forbiddenCss);
});
assert.strictEqual((css.match(/!important/g) || []).length, 0, 'commerce polish adds no !important');

console.log('commerce journey navigation contract passed');
