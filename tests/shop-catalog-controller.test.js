'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const corePath = path.join(__dirname, '..', 'plugin', 'gloskin-site-core', 'assets', 'js', 'gloskin-ui1-core.js');
const source = fs.readFileSync(corePath, 'utf8');
const { parseShopCatalogHash, buildShopCatalogHash } = require(corePath);

assert.deepStrictEqual(parseShopCatalogHash('#category=serum&page=2', 1), { category: 'serum', page: 2 }, 'hash state must restore category + page');
assert.deepStrictEqual(parseShopCatalogHash('#category=toner', 4), { category: 'toner', page: 1 }, 'explicit catalog hash must reset unspecified page to 1');
assert.deepStrictEqual(parseShopCatalogHash('', 3), { category: '', page: 3 }, 'no hash must preserve the SSR page as the enhancement baseline');
assert.deepStrictEqual(parseShopCatalogHash('#page=0', 1), { category: '', page: 1 }, 'page must remain positive and bounded at the client boundary');
assert.strictEqual(buildShopCatalogHash('serum', 2), '#category=serum&page=2', 'category/page state must use non-indexable hash state');
assert.strictEqual(buildShopCatalogHash('serum', 1), '#category=serum', 'page 1 should not add noise to hash state');
assert.strictEqual(buildShopCatalogHash('', 1), '', 'default Shop state must keep the canonical URL clean');

const start = source.indexOf('function initShopCatalog()');
const end = source.indexOf('/* -----------------------------------------------------------------\n\t * Wishlist', start);
assert(start >= 0 && end > start, 'Shop controller source block must be locatable');
const shop = source.slice(start, end);

assert(shop.includes("event.target.closest('[data-gloskin-shop-category]')"), 'category handling must be delegated to the stable Shop root');
assert(shop.includes('requestCatalog(category, 1,'), 'category interactions must reset pagination to page 1');
assert(shop.includes('requestCatalog(currentCategory, page,'), 'pagination must preserve selected category');
assert(shop.includes('var requestSequence = 0'), 'sequence fallback must exist when AbortController is unavailable');
assert(shop.includes('new window.AbortController()'), 'AbortController must cancel superseded requests when available');
assert((shop.match(/sequence !== requestSequence/g) || []).length >= 2, 'stale success and failure paths must both be ignored');
assert(shop.includes("results.setAttribute('aria-busy', busy ? 'true' : 'false')"), 'aria-busy must be driven by one result owner');
assert(shop.includes('showCatalogFailure(fallbackHref)'), 'failed GET must surface inline recovery');
assert(shop.includes('var fetchOptions = publicRestGetOptions();'), 'Shop GET must use the shared guest-safe public REST transport');
assert(!shop.includes('X-WP-Nonce') && !shop.includes('restNonce'), 'Shop public GET must not send/read a REST nonce');
assert(shop.includes('Hasil sebelumnya tetap ditampilkan'), 'failure copy must explicitly preserve previous results');
assert(!shop.includes("results.innerHTML = ''"), 'Shop controller must never blank previous results while loading/failing');
assert(shop.includes("window.addEventListener('popstate'"), 'back/forward restoration must be wired');
assert(shop.includes("history.pushState"), 'enhanced interactions must update browser history');
assert(shop.includes("document.dispatchEvent(new CustomEvent('gloskin:catalog-updated'"), 'successful replacement must dispatch the internal catalog event');
assert(shop.includes('if (options.pagination) { revealPaginationContext(); }'), 'pagination success must expose the new result context');

const wishlistStart = source.indexOf('function initWishlist()');
const wishlistEnd = source.indexOf('/* -----------------------------------------------------------------\n\t * Utility', wishlistStart);
const wishlist = source.slice(wishlistStart, wishlistEnd);
assert(wishlist.includes("document.addEventListener('gloskin:catalog-updated', syncToggles)"), 'wishlist owner must re-run only toggle-state sync after replacement');

const quickStart = source.indexOf('function initQuickAdd()');
const quickEnd = source.indexOf('/* -----------------------------------------------------------------\n\t * Shop catalog', quickStart);
const quick = source.slice(quickStart, quickEnd);
assert(quick.includes("event.target.closest('[data-gloskin-quickadd-open]')"), 'Quick Add must remain delegated and work on injected cards');

console.log('shop catalog controller contract: OK');
