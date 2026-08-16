'use strict';

var assert = require('assert');
var fs = require('fs');
var path = require('path');
var journey = require('../plugin/gloskin-site-core/assets/js/gloskin-ui1-commerce-journey.js');

function classList() {
	var state = {};
	return {
		add: function (name) { state[name] = true; },
		remove: function (name) { delete state[name]; },
		contains: function (name) { return !!state[name]; }
	};
}

function storage(initial, failWrites) {
	var data = initial || {};
	return {
		getItem: function (key) { return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null; },
		setItem: function (key, value) { if (failWrites) { throw new Error('blocked'); } data[key] = String(value); },
		removeItem: function (key) { delete data[key]; },
		data: data
	};
}

function root(url, options) {
	options = options || {};
	var parsed = new URL(url);
	var assigned = [];
	var timers = [];
	var events = {};
	return {
		location: {
			href: parsed.href,
			origin: parsed.origin,
			pathname: parsed.pathname,
			search: parsed.search,
			assign: function (href) { if (options.failAssign) { throw new Error('assign blocked'); } assigned.push(href); }
		},
		sessionStorage: options.storage || storage(),
		matchMedia: function () { return { matches: !!options.reduced }; },
		setTimeout: function (fn, delay) { timers.push({ fn: fn, delay: delay }); return timers.length; },
		addEventListener: function (type, fn) { events[type] = fn; },
		assigned: assigned,
		timers: timers,
		events: events
	};
}

function doc(links) {
	var events = {};
	return {
		readyState: 'complete',
		documentElement: { classList: classList() },
		querySelectorAll: function (selector) {
			assert.strictEqual(selector, '[data-gloskin-commerce-progress] a[href]');
			return links || [];
		},
		addEventListener: function (type, fn) { events[type] = fn; },
		events: events
	};
}

function anchor(href, options) {
	options = options || {};
	return {
		href: href,
		target: options.target || '',
		hasAttribute: function (name) { return name === 'download' && !!options.download; },
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

var checkout = anchor('https://example.test/checkout/');
var normalRoot = root('https://example.test/cart/');
var normalDoc = doc();
var normalClick = click();
assert.strictEqual(journey.handleJourneyClick(normalClick, checkout, normalRoot, normalDoc), true);
assert.strictEqual(normalClick.prevented, 1);
assert.strictEqual(normalRoot.sessionStorage.data[journey.STORAGE_KEY], '/checkout/');
assert.strictEqual(normalDoc.documentElement.classList.contains(journey.LEAVING_CLASS), true);
assert.strictEqual(normalRoot.timers[0].delay, journey.HANDOFF_DELAY_MS);
assert.strictEqual(normalRoot.timers[1].delay, journey.OUTGOING_FAIL_OPEN_MS);
normalRoot.timers[0].fn();
assert.deepStrictEqual(normalRoot.assigned, ['https://example.test/checkout/']);

var stalledRoot = root('https://example.test/cart/');
var stalledDoc = doc();
assert.strictEqual(journey.handleJourneyClick(click(), checkout, stalledRoot, stalledDoc), true);
stalledRoot.timers[0].fn();
stalledRoot.timers[1].fn();
assert.strictEqual(stalledDoc.documentElement.classList.contains(journey.LEAVING_CLASS), false);
assert.strictEqual(stalledRoot.sessionStorage.getItem(journey.STORAGE_KEY), null);

[
	{ button: 1 }, { metaKey: true }, { ctrlKey: true }, { shiftKey: true },
	{ altKey: true }, { defaultPrevented: true }
].forEach(function (mods) {
	assert.strictEqual(journey.shouldIntercept(click(mods), checkout, root('https://example.test/cart/')), false);
});
assert.strictEqual(journey.shouldIntercept(click(), anchor('https://other.test/checkout/'), root('https://example.test/cart/')), false);
assert.strictEqual(journey.shouldIntercept(click(), anchor('https://example.test/checkout/', { target: '_blank' }), root('https://example.test/cart/')), false);
assert.strictEqual(journey.shouldIntercept(click(), anchor('https://example.test/checkout/', { download: true }), root('https://example.test/cart/')), false);

var blocked = root('https://example.test/cart/', { storage: storage({}, true) });
var blockedClick = click();
assert.strictEqual(journey.handleJourneyClick(blockedClick, checkout, blocked, doc()), false);
assert.strictEqual(blockedClick.prevented, 0);

var fallback = root('https://example.test/cart/', { failAssign: true });
assert.strictEqual(journey.handleJourneyClick(click(), checkout, fallback, doc()), true);
fallback.timers[0].fn();
assert.strictEqual(fallback.location.href, 'https://example.test/checkout/');
assert.strictEqual(fallback.sessionStorage.getItem(journey.STORAGE_KEY), '/checkout/');

var reduced = root('https://example.test/cart/', { reduced: true });
var reducedDoc = doc();
assert.strictEqual(journey.handleJourneyClick(click(), checkout, reduced, reducedDoc), true);
assert.deepStrictEqual(reduced.assigned, ['https://example.test/checkout/']);
assert.strictEqual(reducedDoc.documentElement.classList.contains(journey.LEAVING_CLASS), false);
assert.strictEqual(reduced.timers.length, 1);

var arrivalStore = storage({ gloskinCommerceJourneyTarget: '/checkout/?step=1' });
var arrivalRoot = root('https://example.test/checkout/?step=1', { storage: arrivalStore });
var arrivalDoc = doc();
assert.strictEqual(journey.prepareArrival(arrivalRoot, arrivalDoc), true);
assert.strictEqual(arrivalDoc.documentElement.classList.contains(journey.ARRIVING_CLASS), true);
assert.strictEqual(arrivalStore.getItem(journey.STORAGE_KEY), null);

var cacheStore = storage({ gloskinCommerceJourneyTarget: '/checkout/' });
var cacheRoot = root('https://example.test/cart/', { storage: cacheStore });
var cacheDoc = doc();
cacheDoc.documentElement.classList.add(journey.LEAVING_CLASS);
cacheDoc.documentElement.classList.add(journey.ARRIVING_CLASS);
assert.strictEqual(journey.handlePageShow({ persisted: false }, cacheRoot, cacheDoc), false);
assert.strictEqual(journey.handlePageShow({ persisted: true }, cacheRoot, cacheDoc), true);
assert.strictEqual(cacheDoc.documentElement.classList.contains(journey.LEAVING_CLASS), false);
assert.strictEqual(cacheDoc.documentElement.classList.contains(journey.ARRIVING_CLASS), false);
assert.strictEqual(cacheStore.getItem(journey.STORAGE_KEY), null);

var bootRoot = root('https://example.test/cart/');
journey.boot(bootRoot, doc());
assert.strictEqual(typeof bootRoot.events.pageshow, 'function');
var link = anchor('https://example.test/checkout/');
assert.strictEqual(journey.bindJourneyLinks(root('https://example.test/cart/'), doc([link])), 1);
assert.strictEqual(link.listenerType, 'click');

var excluded = doc([anchor('https://example.test/cart/')]);
excluded.body = { classList: classList() };
excluded.body.classList.add('woocommerce-order-received');
assert.strictEqual(journey.bindJourneyLinks(root('https://example.test/checkout/order-received/42/'), excluded), 0);

var source = fs.readFileSync(path.join(__dirname, '../plugin/gloskin-site-core/assets/js/gloskin-ui1-commerce-journey.js'), 'utf8');
var css = fs.readFileSync(path.join(__dirname, '../plugin/gloskin-site-core/assets/css/gloskin-ui1-commerce-polish.css'), 'utf8');
var shell = fs.readFileSync(path.join(__dirname, '../plugin/gloskin-site-core/templates/shell.php'), 'utf8');

assert(source.includes("querySelectorAll('[data-gloskin-commerce-progress] a[href]')"));
assert(source.includes("root.addEventListener('pageshow'"));
assert(source.includes('event.persisted !== true'));
assert(source.includes('OUTGOING_FAIL_OPEN_MS'));
['fetch(', 'XMLHttpRequest', 'DOMParser', '.innerHTML', 'history.pushState', 'history.replaceState', 'MutationObserver', 'ResizeObserver', 'setInterval(', 'requestAnimationFrame(', '@view-transition', 'view-transition-name'].forEach(function (forbidden) {
	assert.strictEqual(source.includes(forbidden), false, forbidden);
});
assert.strictEqual(source.includes('/cart/'), false);
assert.strictEqual(source.includes('/checkout/'), false);

assert(css.includes('body.woocommerce-cart .gloskin-ui1-commerce-native'));
assert(css.includes('.gloskin-ui1-commerce-native > *'));
assert.strictEqual(css.includes('favicon-32x32.png'), false);
assert.strictEqual(css.includes('gloskin-ui1-commerce-handoff-bloom-outer'), false);
assert.strictEqual(css.toLowerCase().includes('codepen.io'), false);
assert.strictEqual(shell.toLowerCase().includes('codepen.io'), false);
assert(shell.includes('data-gloskin-commerce-handoff'));
assert(shell.includes('id="gloskin-ui1-commerce-handoff-goo"'));
assert(shell.includes('stdDeviation="10"'));
assert(shell.includes('0 0 0 20 -10'));
assert.strictEqual((shell.match(/gloskin-ui1-commerce-handoff__blob/g) || []).length, 4);
assert(css.includes('filter:url("#gloskin-ui1-commerce-handoff-goo")'));
assert(css.includes('--gloskin-commerce-handoff-size:clamp(104px,8vw,120px)'));
assert(css.includes('--gloskin-commerce-handoff-size:clamp(82px,23vw,96px)'));
assert(css.includes('background:var(--gloskin-accent)'));
assert(css.includes('background:var(--gloskin-accent-strong)'));
assert(css.includes('animation:gloskin-ui1-commerce-handoff-goo-dance 3.5s infinite ease-in-out'));
assert(css.includes('--gloskin-commerce-handoff-delay:-.8s'));
assert(css.includes('--gloskin-commerce-handoff-delay:-1.6s'));
assert(css.includes('--gloskin-commerce-handoff-delay:-2.4s'));
assert(css.includes('25%{transform:translate(calc(-50% + var(--gloskin-commerce-handoff-travel)),calc(-50% - var(--gloskin-commerce-handoff-travel))) scale(.8)}'));
assert(css.includes('50%{transform:translate(calc(-50% - var(--gloskin-commerce-handoff-travel)),calc(-50% + var(--gloskin-commerce-handoff-travel))) scale(1.1)}'));
assert(css.includes('75%{transform:translate(calc(-50% + var(--gloskin-commerce-handoff-travel)),calc(-50% + var(--gloskin-commerce-handoff-travel))) scale(.9)}'));
assert(css.includes('gloskin-ui1-commerce-journey-leaving body.woocommerce-cart .gloskin-ui1-commerce-handoff'));
assert(css.includes('gloskin-ui1-commerce-journey-arriving body.woocommerce-cart .gloskin-ui1-commerce-handoff'));
assert(css.includes('transition:opacity 170ms cubic-bezier(.22,1,.36,1)'));
var reducedCss = css.slice(css.indexOf('@media (prefers-reduced-motion:reduce)'));
assert(reducedCss.includes('animation:none'));
assert.strictEqual(reducedCss.includes('infinite'), false);
assert.strictEqual(css.includes('@view-transition'), false);
assert.strictEqual(css.includes('view-transition-name'), false);
assert.strictEqual((css.match(/!important/g) || []).length, 0);

console.log('commerce journey navigation contract passed');
