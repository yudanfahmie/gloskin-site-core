(function () {
	'use strict';

	/*
	 * Search/price extension for the EXISTING Shop controller in
	 * gloskin-ui1-core.js. This file deliberately owns no catalog request:
	 * it never calls fetch for Shop itself, never creates AbortController,
	 * and never replaces results. It only decorates the existing request URL
	 * and history state with q/min/max, then reuses the current category action
	 * to ask the existing controller to perform its normal page=1 request.
	 */
	var SEARCH_DELAY = 325;
	var activeFilters = parseFilterHash(window.location.hash);
	var originalFetch = typeof window.fetch === 'function' ? window.fetch : null;
	var originalPushState = window.history && window.history.pushState ? window.history.pushState.bind(window.history) : null;
	var originalReplaceState = window.history && window.history.replaceState ? window.history.replaceState.bind(window.history) : null;

	function parseFilterHash(hash) {
		var state = { q: '', min_price: '', max_price: '' };
		var params = new URLSearchParams(String(hash || '').replace(/^#/, ''));
		['q', 'min_price', 'max_price'].forEach(function (key) {
			state[key] = params.get(key) || '';
		});
		return state;
	}

	function appendFiltersToHash(hash) {
		var params = new URLSearchParams(String(hash || '').replace(/^#/, ''));
		['q', 'min_price', 'max_price'].forEach(function (key) {
			if (activeFilters[key]) { params.set(key, activeFilters[key]); }
			else { params.delete(key); }
		});
		var value = params.toString();
		return value ? '#' + value : '';
	}

	function decorateHistoryUrl(url) {
		try {
			var target = new URL(String(url || window.location.href), window.location.href);
			target.hash = appendFiltersToHash(target.hash);
			return target.pathname + target.search + target.hash;
		} catch (error) {
			return url;
		}
	}

	if (originalFetch) {
		window.fetch = function (input, options) {
			if (typeof input === 'string' && input.indexOf('/gloskin/v1/shop/catalog') !== -1) {
				try {
					var url = new URL(input, window.location.href);
					['q', 'min_price', 'max_price'].forEach(function (key) {
						if (activeFilters[key]) { url.searchParams.set(key, activeFilters[key]); }
						else { url.searchParams.delete(key); }
					});
					input = url.toString();
				} catch (error) { /* Existing controller fallback remains untouched. */ }
			}
			return originalFetch.call(window, input, options);
		};
	}

	if (originalPushState) {
		window.history.pushState = function (state, title, url) {
			return originalPushState(state, title, state && state.gloskinShop ? decorateHistoryUrl(url) : url);
		};
	}
	if (originalReplaceState) {
		window.history.replaceState = function (state, title, url) {
			return originalReplaceState(state, title, state && state.gloskinShop ? decorateHistoryUrl(url) : url);
		};
	}

	function initShopFilters() {
		var root = document.querySelector('[data-gloskin-shop-catalog]');
		if (!root) { return; }
		var categories = root.querySelector('[data-gloskin-shop-categories]');
		var searchForm = root.querySelector('[data-gloskin-shop-search-form]');
		var search = root.querySelector('[data-gloskin-shop-search]');
		var searchReset = root.querySelector('[data-gloskin-shop-search-clear]');
		var priceForm = root.querySelector('[data-gloskin-shop-price-form]');
		var minPrice = root.querySelector('[data-gloskin-shop-min-price]');
		var maxPrice = root.querySelector('[data-gloskin-shop-max-price]');
		var priceReset = root.querySelector('[data-gloskin-shop-price-reset]');
		var clearAll = root.querySelector('[data-gloskin-shop-clear-all]');
		var filterStatus = root.querySelector('[data-gloskin-shop-filter-validation]');
		if (!categories || !searchForm || !search || !priceForm || !minPrice || !maxPrice) { return; }
		var searchTimer = 0;

		function normalizedPrice(value) {
			value = String(value || '').trim();
			if (!value) { return ''; }
			if (!/^\d{1,9}(?:\.\d{1,2})?$/.test(value)) { return null; }
			var number = Number(value);
			if (!Number.isFinite(number) || number < 0 || number > 999999999.99) { return null; }
			return value;
		}

		function syncControls() {
			search.value = activeFilters.q || '';
			minPrice.value = activeFilters.min_price || '';
			maxPrice.value = activeFilters.max_price || '';
			if (searchReset) { searchReset.hidden = !activeFilters.q; }
			if (priceReset) { priceReset.hidden = !(activeFilters.min_price || activeFilters.max_price); }
			if (clearAll) { clearAll.hidden = !(activeFilters.q || activeFilters.min_price || activeFilters.max_price); }
		}

		function announce(message) {
			if (!filterStatus) { return; }
			filterStatus.textContent = message || '';
			filterStatus.hidden = !message;
		}

		function activeCategoryLink() {
			return categories.querySelector('[data-gloskin-shop-category][aria-current="page"]') || categories.querySelector('[data-gloskin-shop-category=""]');
		}

		function requestPageOne() {
			var link = activeCategoryLink();
			if (!link) { return; }
			link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
		}

		function requestAllProductsPageOne() {
			var link = categories.querySelector('[data-gloskin-shop-category=""]');
			if (!link) { requestPageOne(); return; }
			link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
		}

		function applySearch() {
			window.clearTimeout(searchTimer);
			activeFilters.q = String(search.value || '').trim().slice(0, 100);
			announce('');
			syncControls();
			requestPageOne();
		}

		search.addEventListener('input', function () {
			window.clearTimeout(searchTimer);
			searchTimer = window.setTimeout(applySearch, SEARCH_DELAY);
		});
		searchForm.addEventListener('submit', function (event) {
			event.preventDefault();
			applySearch();
		});
		if (searchReset) {
			searchReset.addEventListener('click', function () {
				window.clearTimeout(searchTimer);
				search.value = '';
				applySearch();
			});
		}

		priceForm.addEventListener('submit', function (event) {
			event.preventDefault();
			var min = normalizedPrice(minPrice.value);
			var max = normalizedPrice(maxPrice.value);
			if (min === null || max === null || (min !== '' && max !== '' && Number(min) > Number(max))) {
				announce('Rentang harga tidak valid. Pastikan nilai tidak negatif dan harga minimum tidak melebihi harga maksimum.');
				return;
			}
			activeFilters.min_price = min;
			activeFilters.max_price = max;
			announce('');
			syncControls();
			requestPageOne();
		});
		if (priceReset) {
			priceReset.addEventListener('click', function () {
				activeFilters.min_price = '';
				activeFilters.max_price = '';
				announce('');
				syncControls();
				requestPageOne();
			});
		}
		if (clearAll) {
			clearAll.addEventListener('click', function () {
				window.clearTimeout(searchTimer);
				activeFilters = { q: '', min_price: '', max_price: '' };
				announce('');
				syncControls();
				requestAllProductsPageOne();
			});
		}

		root.addEventListener('click', function (event) {
			var category = event.target.closest && event.target.closest('[data-gloskin-shop-category]');
			if (category && categories.contains(category)) {
				/* Category is still executed by core. Commit only the pending search
				 * draft first so that one core request composes both changes. */
				window.clearTimeout(searchTimer);
				activeFilters.q = String(search.value || '').trim().slice(0, 100);
				syncControls();
				return;
			}
			var resetResults = event.target.closest && event.target.closest('[data-gloskin-shop-reset-results]');
			if (resetResults) {
				event.preventDefault();
				window.clearTimeout(searchTimer);
				activeFilters = { q: '', min_price: '', max_price: '' };
				announce('');
				syncControls();
				requestAllProductsPageOne();
			}
		});

		window.addEventListener('popstate', function () {
			activeFilters = parseFilterHash(window.location.hash);
			syncControls();
			announce('');
		});
		document.addEventListener('gloskin:catalog-updated', syncControls);
		syncControls();
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initShopFilters); }
	else { initShopFilters(); }

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = { parseFilterHash: parseFilterHash, appendFiltersToHash: appendFiltersToHash, SEARCH_DELAY: SEARCH_DELAY };
	}
}());
