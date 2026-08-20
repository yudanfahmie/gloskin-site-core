'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const rootDir = path.join(__dirname, '..');
const ownerPath = path.join(rootDir, 'plugin', 'gloskin-site-core', 'assets', 'js', 'gloskin-ui1-shop-discovery.js');
const cssPath = path.join(rootDir, 'plugin', 'gloskin-site-core', 'assets', 'css', 'gloskin-ui1-shop-discovery.css');
const corePath = path.join(rootDir, 'plugin', 'gloskin-site-core', 'assets', 'js', 'gloskin-ui1-core.js');
const templatePath = path.join(rootDir, 'plugin', 'gloskin-site-core', 'templates', 'pages', 'shop.php');
const routeTraitPath = path.join(rootDir, 'plugin', 'gloskin-site-core', 'includes', 'gloskin-site-core-shop-discovery-route-trait.php');
const productionBatchPath = path.join(rootDir, 'plugin', 'gloskin-site-core', 'includes', 'class-gloskin-site-core-production-batch.php');
const assetConfigPath = path.join(rootDir, 'plugin', 'gloskin-site-core', 'config', 'assets.php');
const source = fs.readFileSync(ownerPath, 'utf8');
const cssSource = fs.readFileSync(cssPath, 'utf8');
const coreSource = fs.readFileSync(corePath, 'utf8');
const templateSource = fs.readFileSync(templatePath, 'utf8');
const routeTraitSource = fs.readFileSync(routeTraitPath, 'utf8');
const productionBatchSource = fs.readFileSync(productionBatchPath, 'utf8');
const assetConfigSource = fs.readFileSync(assetConfigPath, 'utf8');
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
assert(templateSource.includes('data-gloskin-shop-catalog-owner'), 'Shop template must expose the canonical owner root');
assert(!/\sdata-gloskin-shop-catalog(?:\s|=|>)/.test(templateSource), 'legacy Shop catalog root must stay absent so the old core controller remains inert');
assert(!coreSource.includes("document.querySelector('[data-gloskin-shop-catalog-owner]')"), 'core must not target the canonical Shop owner root');
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

const categoryClick = source.indexOf("var categoryLink = event.target.closest && event.target.closest('[data-gloskin-shop-category]');");
const categoryPrevent = source.indexOf('event.preventDefault();', categoryClick);
const categoryState = source.indexOf('updateCategoryState(nextState.category);', categoryClick);
const categoryRequest = source.indexOf('requestFilterState(nextState,', categoryClick);
assert(categoryClick !== -1 && categoryPrevent > categoryClick && categoryState > categoryPrevent && categoryRequest > categoryState,
    'category clicks must stay in the canonical AJAX owner: prevent native navigation, update state, then request');

assert(templateSource.includes('<nav class="gloskin-ui1-shop-categories"') && templateSource.includes('<li><a href='),
    'Shop categories must stay crawlable anchor navigation for progressive enhancement');
assert(!templateSource.includes('role="tablist"') && !templateSource.includes('role="tab"'),
    'Shop category filters are not ARIA tabs because results are not a tabpanel');
const categoryAnchors = templateSource.match(/<a[^>]*data-gloskin-shop-category=[^>]*>/g) || [];
assert(categoryAnchors.length >= 2 && categoryAnchors.every((anchor) => anchor.includes('data-gloskin-no-transition')),
    'every enhanced Shop category anchor must opt out of the global document transition so AJAX clicks never reload the page');
assert(coreSource.includes("link.hasAttribute('data-gloskin-no-transition')"),
    'global transition must retain the shared no-transition escape hatch consumed by Shop filters');
assert(cssSource.includes('.gloskin-ui1-shop-categories ul {') && cssSource.includes('list-style: none;'),
    'Shop category presentation must reset global list bullets');
const categoryNavRule = cssSource.split('.gloskin-ui1-shop-categories {', 2)[1].split('}', 1)[0];
assert(categoryNavRule.includes('margin-block-start: 8px;'),
    'Shop category controls need deliberate breathing room after the category heading');
assert(cssSource.includes('.gloskin-ui1-shop-categories a {') && cssSource.includes('min-height: 44px;') && cssSource.includes('text-decoration: none;'),
    'Shop category choices need a full touch target and control-like anchor presentation');
assert(cssSource.includes('.gloskin-ui1-shop-categories a[aria-current="page"] {'),
    'Shop category active state must be visually keyed from the existing aria-current state');
const mobileCategoryCss = cssSource.split('@media (max-width: 900px) {', 2)[1] || '';
assert(mobileCategoryCss.includes('.gloskin-ui1-shop-categories ul {') && mobileCategoryCss.includes('display: flex;') && mobileCategoryCss.includes('overflow-x: auto;'),
    'narrow Shop category controls must become a horizontal scroll strip rather than a long bullet list');
assert(!cssSource.includes('!important'), 'Shop route-specific CSS must not introduce !important');

assert(routeTraitSource.includes("'shop' ===") && routeTraitSource.includes("function_exists( 'is_shop' )") && routeTraitSource.includes('if ( ! $is_shop )'),
    'Shop asset gate must remain route-aware without adding a second route owner');
assert(routeTraitSource.includes("assets/js/gloskin-ui1-shop-discovery.js"), 'Shop Discovery JS must be enqueued by the canonical Shop route owner');
assert(routeTraitSource.includes("assets/css/gloskin-ui1-shop-discovery.css"), 'Shop Discovery CSS must be enqueued by the canonical Shop route owner');
assert(routeTraitSource.includes("array( 'gloskin-ui1-prototype-refresh' )"), 'Shop Discovery CSS must remain final after prototype refresh');
assert(productionBatchSource.includes('$shop = new Gloskin_Site_Core_Shop_Discovery') && productionBatchSource.includes('$shop->register();'),
    'production batch must register the single Shop discovery route owner');
assert(!assetConfigSource.includes("'gloskin-ui1-shop-discovery'"), 'Shop Discovery assets must not be duplicated in the global asset registry');

assert(!/window\.fetch\s*=(?!=)/.test(source), 'global fetch monkeypatch forbidden');
assert(!/(?:window\.)?history\.pushState\s*=(?!=)/.test(source), 'global pushState monkeypatch forbidden');
assert(!/(?:window\.)?history\.replaceState\s*=(?!=)/.test(source), 'global replaceState monkeypatch forbidden');

console.log('shop-catalog-controller.test.js: OK (single owner + non-reloading category filters)');
