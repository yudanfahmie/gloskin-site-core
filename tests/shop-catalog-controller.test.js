'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const ownerPath = path.join(__dirname, '..', 'plugin', 'gloskin-site-core', 'assets', 'js', 'gloskin-ui1-shop-discovery.js');
const source = fs.readFileSync(ownerPath, 'utf8');
const shop = require(ownerPath);

const state = shop.parseShopCatalogHash('#category=serum&q=brightening&min_price=100000&max_price=300000&page=2', 1);
assert.deepStrictEqual(state, {
    category: 'serum',
    q: 'brightening',
    min_price: '100000',
    max_price: '300000',
    page: 2
});
assert.deepStrictEqual(shop.parseShopCatalogHash('', 3), {
    category: '', q: '', min_price: '', max_price: '', page: 3
});
assert.strictEqual(
    shop.buildShopCatalogHash(state),
    '#category=serum&q=brightening&min_price=100000&max_price=300000&page=2'
);

const request = new URL(shop.buildShopCatalogRequestUrl('https://gloskin.test/wp-json/gloskin/v1/', state));
assert.strictEqual(request.pathname, '/wp-json/gloskin/v1/shop/catalog');
assert.strictEqual(request.searchParams.get('category'), 'serum');
assert.strictEqual(request.searchParams.get('q'), 'brightening');
assert.strictEqual(request.searchParams.get('min_price'), '100000');
assert.strictEqual(request.searchParams.get('max_price'), '300000');
assert.strictEqual(request.searchParams.get('page'), '2');

assert(source.includes("document.querySelector('[data-gloskin-shop-catalog-owner]')"), 'dedicated canonical Shop owner marker missing');
assert.strictEqual((source.match(/function buildShopCatalogRequestUrl\(/g) || []).length, 1, 'one Shop URL builder expected');
assert.strictEqual((source.match(/function requestCatalog\(/g) || []).length, 1, 'one Shop request owner expected');
assert.strictEqual((source.match(/return window\.fetch\(/g) || []).length, 1, 'one logical Shop fetch path expected');
assert(source.includes('new window.AbortController()'), 'AbortController must remain');
assert(source.includes('sequence !== requestSequence'), 'stale-response protection must remain');
assert(source.includes('currentState = nextState;'), 'canonical state must transfer before request begins');
assert(source.includes('nextState.page = 1;'), 'filter changes must reset page=1');
assert(source.includes('currentState.q = query;') && source.includes('currentState.page = 1;'), 'debounced search must update canonical state before requesting');
assert(source.includes('nextPageState = normalizeShopCatalogState(currentState, 1);'), 'pagination must start from current canonical filters');
assert(source.includes("window.addEventListener('popstate'"), 'popstate restoration missing');
assert(source.includes('syncControls(state);') && source.includes("historyMode: 'none'"), 'popstate must restore controls and issue one owner request');
assert(source.includes("searchForm.addEventListener('submit'"), 'Enter/form submit must apply search immediately');
assert(source.includes('window.clearTimeout(searchTimer);'), 'immediate search must cancel pending debounce');
assert((source.match(/window\.clearTimeout\(searchTimer\);/g) || []).length >= 5, 'competing filter/page actions must cancel pending search debounce before requesting');
assert(!/window\.fetch\s*=(?!=)/.test(source), 'global fetch monkeypatch forbidden');
assert(!/(?:window\.)?history\.pushState\s*=(?!=)/.test(source), 'global pushState monkeypatch forbidden');
assert(!/(?:window\.)?history\.replaceState\s*=(?!=)/.test(source), 'global replaceState monkeypatch forbidden');

console.log('shop-catalog-controller.test.js: OK');
