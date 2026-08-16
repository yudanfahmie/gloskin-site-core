'use strict';

var assert = require('assert');
var motion = require('../plugin/gloskin-site-core/assets/js/gloskin-ui1-commerce-motion.js');

function classList(initial) {
	var values = {};
	(initial || []).forEach(function (name) { values[name] = true; });
	return {
		add: function (name) { values[name] = true; },
		remove: function (name) { delete values[name]; },
		contains: function (name) { return !!values[name]; }
	};
}

function node(rect, options) {
	options = options || {};
	var attrs = {};
	var n = {
		nodeType: 1,
		hidden: !!options.hidden,
		classList: classList(),
		style: {},
		offsetWidth: rect.width,
		parentNode: options.parentNode || null,
		parentElement: options.parentElement || null,
		getBoundingClientRect: function () { return Object.assign({}, rect); },
		setAttribute: function (key, value) { attrs[key] = value; },
		getAttribute: function (key) { return attrs[key] || null; },
		hasAttribute: function (key) { return Object.prototype.hasOwnProperty.call(attrs, key); },
		closest: function () { return options.closest || null; },
		querySelector: function (selector) {
			if (options.query && Object.prototype.hasOwnProperty.call(options.query, selector)) { return options.query[selector]; }
			return null;
		},
		animate: options.animate || null
	};
	return n;
}

function rootFixture(options) {
	options = options || {};
	var timers = [];
	var bodyChildren = [];
	var cartBadge = node({ left: 930, top: 24, right: 948, bottom: 42, width: 18, height: 18 });
	var cartTrigger = node({ left: 912, top: 10, right: 956, bottom: 54, width: 44, height: 44 }, {
		query: { '[data-gloskin-cart-count]': cartBadge }
	});
	var hiddenAncestor = { hidden: false, inert: false, parentElement: null, _opacityZero: true };
	var hiddenCart = node({ left: 800, top: 10, right: 844, bottom: 54, width: 44, height: 44 }, { parentElement: hiddenAncestor });
	var wishBadge = node({ left: 874, top: 24, right: 892, bottom: 42, width: 18, height: 18 }, { hidden: !!options.hiddenWishBadge });
	var wishQuery = {};
	if (!options.missingWishBadge) { wishQuery['[data-gloskin-wishlist-count]'] = wishBadge; }
	var wishTrigger = node({ left: 856, top: 10, right: 900, bottom: 54, width: 44, height: 44 }, {
		query: wishQuery
	});
	var created = [];
	var doc = {
		body: {
			appendChild: function (child) {
				bodyChildren.push(child);
				child.parentNode = this;
			},
			removeChild: function (child) {
				var index = bodyChildren.indexOf(child);
				if (index !== -1) { bodyChildren.splice(index, 1); }
				child.parentNode = null;
			}
		},
		querySelectorAll: function (selector) {
			if (selector === '[data-gloskin-cart-open]') { return options.hiddenFirst ? [hiddenCart, cartTrigger] : [cartTrigger]; }
			if (selector === '[data-gloskin-wishlist-open]') { return [wishTrigger]; }
			return [];
		},
		createElement: function () {
			var animation = { onfinish: null, oncancel: null, cancel: function () { if (this.oncancel) { this.oncancel(); } } };
			var createdNode = node({ left: 0, top: 0, right: 14, bottom: 14, width: 14, height: 14 }, {
				animate: function (frames, config) {
					createdNode.frames = frames;
					createdNode.config = config;
					createdNode.animation = animation;
					return animation;
				}
			});
			created.push(createdNode);
			return createdNode;
		},
		addEventListener: function () {}
	};
	var root = {
		document: doc,
		innerWidth: 1024,
		innerHeight: 768,
		getComputedStyle: function (n) { return { display: n.hidden ? 'none' : 'block', visibility: 'visible', opacity: n._opacityZero ? '0' : '1', pointerEvents: 'auto' }; },
		matchMedia: function () { return { matches: !!options.reduced }; },
		setTimeout: function (fn, delay) { timers.push({ fn: fn, delay: delay }); return timers.length; },
		clearTimeout: function () {},
		Date: Date,
		timers: timers,
		created: created,
		bodyChildren: bodyChildren,
		cartBadge: cartBadge,
		wishBadge: wishBadge,
		wishTrigger: wishTrigger
	};
	return root;
}

// Visible target resolution must skip a geometrically non-zero duplicate hidden by an ancestor and measure at call time.
var visibleRoot = rootFixture({ hiddenFirst: true });
var target = motion.resolveVisibleTarget('cart', visibleRoot);
assert(target, 'visible cart target resolved');
assert.strictEqual(target.node, visibleRoot.cartBadge, 'visible badge is the destination, hidden duplicate ignored');

var wishlistTarget = motion.resolveVisibleTarget('wishlist', visibleRoot);
assert(wishlistTarget, 'visible wishlist target resolved');
assert.strictEqual(wishlistTarget.node, visibleRoot.wishBadge, 'visible Wishlist badge is the exact destination');

var missingWishlistRoot = rootFixture({ missingWishBadge: true });
var missingWishlistTarget = motion.resolveVisibleTarget('wishlist', missingWishlistRoot);
assert(missingWishlistTarget, 'Wishlist trigger resolves when badge is absent');
assert.strictEqual(missingWishlistTarget.node, missingWishlistRoot.wishTrigger, 'missing Wishlist badge falls back to visible Wishlist trigger');

var hiddenWishlistRoot = rootFixture({ hiddenWishBadge: true });
var hiddenWishlistTarget = motion.resolveVisibleTarget('wishlist', hiddenWishlistRoot);
assert(hiddenWishlistTarget, 'Wishlist trigger resolves when badge is hidden');
assert.strictEqual(hiddenWishlistTarget.node, hiddenWishlistRoot.wishTrigger, 'hidden Wishlist badge falls back to visible Wishlist trigger');

// Dynamic source/target geometry + decorative transient node + cleanup.
var source = node({ left: 120, top: 520, right: 164, bottom: 564, width: 44, height: 44 });
var completed = 0;
assert.strictEqual(motion.animateCommerceFlyToTarget('cart', source, visibleRoot, function () { completed += 1; }), true, 'fly launches');
assert.strictEqual(visibleRoot.created.length, 1, 'one transient node created');
var orb = visibleRoot.created[0];
assert.strictEqual(orb.getAttribute('aria-hidden'), 'true', 'orb is aria-hidden');
assert.strictEqual(orb.style.left, '142px', 'source X derives from bounding rect');
assert.strictEqual(orb.style.top, '542px', 'source Y derives from bounding rect');
assert.strictEqual(orb.config.duration, motion.FLY_DURATION_MS, 'bounded duration used');
assert.strictEqual(orb.config.easing, 'cubic-bezier(.22,1,.36,1)', 'restrained easing used');
assert.strictEqual(motion.activeParticleCount(), 1, 'particle tracked while active');
orb.animation.onfinish();
assert.strictEqual(motion.activeParticleCount(), 0, 'particle cleaned after completion');
assert.strictEqual(visibleRoot.bodyChildren.length, 0, 'transient DOM removed');
assert.strictEqual(completed, 1, 'completion callback runs once');
assert.strictEqual(visibleRoot.cartBadge.classList.contains('is-commerce-badge-added'), true, 'badge impact happens at landing');

// Reduced motion: no travel node, continuation still runs.
var reducedRoot = rootFixture({ reduced: true });
var reducedDone = 0;
assert.strictEqual(motion.confirmedSuccess('wishlist', source, function () { reducedDone += 1; }, reducedRoot), true, 'reduced-motion success is handled');
assert.strictEqual(reducedRoot.created.length, 0, 'reduced motion creates no fly node');
assert.strictEqual(reducedDone, 1, 'reduced motion does not block success continuation');
assert.strictEqual(reducedRoot.wishBadge.classList.contains('is-commerce-badge-added'), false, 'reduced motion adds no impact animation');

// Missing source geometry safely falls back to target impact and continuation.
var missingRoot = rootFixture();
var missingDone = 0;
assert.strictEqual(motion.confirmedSuccess('cart', null, function () { missingDone += 1; }, missingRoot), true, 'missing source does not break confirmed success');
assert.strictEqual(missingRoot.created.length, 0, 'missing source creates no particle');
assert.strictEqual(missingDone, 1, 'missing source still continues Cart flow');

// Pre-captured geometry survives source disappearance until success.
var pendingRoot = rootFixture();
assert.strictEqual(motion.rememberSource('cart', source, pendingRoot), true, 'source snapshot captured');
var consumed = motion.consumeSourceRect('cart', null, pendingRoot);
assert(consumed && consumed.left === 120 && consumed.top === 520, 'captured geometry recovered after source disappearance');

// Buy-now is explicitly excluded from this Cart-drawer success presentation.
var buyNow = node({ left: 100, top: 100, right: 144, bottom: 144, width: 44, height: 44 });
buyNow.setAttribute('data-gloskin-buy-now-redirect', '1');
assert.strictEqual(motion.confirmedSuccess('cart', buyNow, null, rootFixture()), false, 'buy-now redirect is not claimed');

// Source contract is bounded and presentation-only.
assert.strictEqual(motion.MAX_ACTIVE_PARTICLES, 3, 'active particles are bounded');
var fs = require('fs');
var path = require('path');
var sourceText = fs.readFileSync(path.join(__dirname, '../plugin/gloskin-site-core/assets/js/gloskin-ui1-commerce-motion.js'), 'utf8');
['localStorage', 'sessionStorage', 'fetch(', 'XMLHttpRequest', 'pushState', 'replaceState', 'MutationObserver', 'ResizeObserver', 'setInterval('].forEach(function (needle) {
	assert.strictEqual(sourceText.indexOf(needle), -1, 'motion helper must not contain state/request/router primitive: ' + needle);
});
assert(sourceText.indexOf('getBoundingClientRect') !== -1, 'source/target geometry is measured dynamically');
assert(sourceText.indexOf('position:fixed') === -1, 'layout styling stays in CSS, not duplicated in JS');
assert(sourceText.indexOf('added_to_cart.gloskinCommerceMotion') !== -1, 'Cart travel binds only to confirmed Woo added_to_cart');
assert(sourceText.indexOf("confirmedSuccess('wishlist'") !== -1 && sourceText.indexOf("getAttribute('aria-pressed') === 'true'") !== -1, 'Wishlist travel verifies post-owner active state before confirmed success');
assert(sourceText.indexOf("'[data-gloskin-wishlist-count]'") !== -1, 'motion source uses the canonical Wishlist badge selector');
assert.strictEqual(sourceText.indexOf('[data-glosin-wishlist-count]'), -1, 'misspelled Wishlist badge selector cannot recur silently');
assert.strictEqual(sourceText.indexOf('preventDefault('), -1, 'motion module never intercepts commerce clicks');

var cssText = fs.readFileSync(path.join(__dirname, '../plugin/gloskin-site-core/assets/css/gloskin-ui1-commerce-polish.css'), 'utf8');
assert(cssText.indexOf('body.gloskin-ui1 .woocommerce a.gloskin-ui1-cart-sheet__item-remove') !== -1, 'Mini-cart has one narrow final CSS owner');
assert(cssText.indexOf('box-sizing:border-box') !== -1, '38px Mini-cart geometry includes its border');
assert(cssText.indexOf('--gloskin-remove-icon-color:var(--gloskin-inverse)') !== -1, 'Mini-cart owns white mask paint independently of Woo currentColor');
assert(cssText.indexOf('.gloskin-ui1-commerce-fly-target{') !== -1 && cssText.indexOf('position:fixed') !== -1 && cssText.indexOf('pointer-events:none') !== -1, 'fly node is fixed and pointerless');
assert(cssText.indexOf('gloskin-ui1-commerce-fly-active--cart [data-gloskin-cart-count].is-commerce-badge-added') !== -1, 'early Cart badge impact is suppressed while travel is active');
assert(cssText.indexOf('gloskin-ui1-commerce-fly-active--wishlist [data-gloskin-wishlist-count].is-commerce-badge-added') !== -1, 'early Wishlist badge impact is suppressed while travel is active');
assert.strictEqual((cssText.match(/!important/g) || []).length, 0, 'commerce polish adds no !important');
['@view-transition', 'view-transition-name:', '::view-transition-old(', '::view-transition-new('].forEach(function (needle) {
	assert.strictEqual(cssText.indexOf(needle), -1, 'retired View Transition remains absent: ' + needle);
});

var configText = fs.readFileSync(path.join(__dirname, '../plugin/gloskin-site-core/config/assets.php'), 'utf8');
assert(configText.indexOf("'gloskin-ui1-commerce-motion' => array(") !== -1, 'motion module is registered declaratively');
assert(configText.indexOf("'deps'      => array( 'gloskin-ui1-commerce-motion' )") !== -1, 'motion module executes before existing core success listeners');

console.log('commerce success motion contract passed');
