(function () {
	'use strict';

	function publicRestGetOptions() {
		return { method: 'GET', credentials: 'same-origin' };
	}

	/* -----------------------------------------------------------------
	 * IDR currency formatting  (e.g. 100000 → "Rp 100.000")
	 * ----------------------------------------------------------------- */

	function formatIDR(value) {
		var n = Math.round(Number(value) || 0);
		return 'Rp ' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	}

	/* -----------------------------------------------------------------
	 * Slider step: 1k for narrow ranges, 5k/10k for wider ones.
	 * ----------------------------------------------------------------- */

	function computeStep(spread) {
		if (spread <= 0) { return 1000; }
		if (spread <= 500000) { return 1000; }
		if (spread <= 2000000) { return 5000; }
		return 10000;
	}

	/* -----------------------------------------------------------------
	 * Shop catalog -- SSR-first, read-only AJAX enhancement.
	 * ONE canonical state owner · ONE request builder · ONE AbortController.
	 * No window.fetch monkeypatch · No history API monkeypatch.
	 * ----------------------------------------------------------------- */

	function normalizeShopCatalogState(state, defaultPage) {
		state = state || {};
		return {
			category: String(state.category || ''),
			q: String(state.q || ''),
			min_price: String(state.min_price || ''),
			max_price: String(state.max_price || ''),
			page: Math.max(1, parseInt(state.page, 10) || Math.max(1, parseInt(defaultPage, 10) || 1))
		};
	}

	function parseShopCatalogHash(hash, defaultPage) {
		var state = normalizeShopCatalogState({ page: defaultPage }, defaultPage);
		var raw = String(hash || '').replace(/^#/, '');
		if (!raw) { return state; }
		state.page = 1;
		raw.split('&').forEach(function (pair) {
			var bits = pair.split('=');
			var key = decodeURIComponent(bits.shift() || '');
			var value = decodeURIComponent(bits.join('=') || '');
			if (key === 'category') { state.category = value; }
			if (key === 'q') { state.q = value; }
			if (key === 'min_price') { state.min_price = value; }
			if (key === 'max_price') { state.max_price = value; }
			if (key === 'page') { state.page = Math.max(1, parseInt(value, 10) || 1); }
		});
		return state;
	}

	function buildShopCatalogHash(state) {
		state = normalizeShopCatalogState(state, 1);
		var parts = [];
		if (state.category) { parts.push('category=' + encodeURIComponent(state.category)); }
		if (state.q) { parts.push('q=' + encodeURIComponent(state.q)); }
		if (state.min_price) { parts.push('min_price=' + encodeURIComponent(state.min_price)); }
		if (state.max_price) { parts.push('max_price=' + encodeURIComponent(state.max_price)); }
		if (state.page > 1) { parts.push('page=' + state.page); }
		return parts.length ? '#' + parts.join('&') : '';
	}

	function buildShopCatalogRequestUrl(restUrl, state) {
		state = normalizeShopCatalogState(state, 1);
		var base = restUrl || '/wp-json/gloskin/v1/';
		return base + 'shop/catalog?' + [
			'category='  + encodeURIComponent(state.category),
			'q='         + encodeURIComponent(state.q),
			'min_price=' + encodeURIComponent(state.min_price),
			'max_price=' + encodeURIComponent(state.max_price),
			'page='      + encodeURIComponent(state.page)
		].join('&');
	}

	function initCanonicalShopCatalog() {
		var root = document.querySelector('[data-gloskin-shop-catalog-owner]');
		if (!root || typeof window.fetch !== 'function') { return; }
		var categories = root.querySelector('[data-gloskin-shop-categories]');
		var results    = root.querySelector('[data-gloskin-shop-results]');
		if (!categories || !results) { return; }

		/* Search field elements */
		var searchForm  = root.querySelector('[data-gloskin-shop-search-form]');
		var searchInput = root.querySelector('[data-gloskin-shop-search]');
		var searchClear = root.querySelector('[data-gloskin-shop-search-clear]');

		/* Price slider elements */
		var priceFilter   = root.querySelector('[data-gloskin-shop-price-filter]');
		var minSlider     = root.querySelector('[data-gloskin-shop-min-price-slider]');
		var maxSlider     = root.querySelector('[data-gloskin-shop-max-price-slider]');
		var priceMinLabel = root.querySelector('[data-gloskin-price-label-min]');
		var priceMaxLabel = root.querySelector('[data-gloskin-price-label-max]');
		var priceReset    = root.querySelector('[data-gloskin-shop-price-reset]');

		/* Other controls */
		var clearAll       = root.querySelector('[data-gloskin-shop-clear-all]');
		var config         = window.gloskinData || {};
		var initialPage    = Math.max(1, parseInt(root.getAttribute('data-gloskin-shop-initial-page'), 10) || 1);
		var initialUrl     = window.location.href;
		var shopUrl        = root.getAttribute('data-gloskin-shop-url') || '/shop/';
		var currentState   = normalizeShopCatalogState({ page: initialPage }, initialPage);
		var requestSequence = 0;
		var abortController = null;
		var retryRequest    = null;
		var searchTimer     = null;
		var SEARCH_DELAY    = 325;

		/* --- Price slider state ------------------------------------------------ */
		var availMin    = priceFilter ? (parseFloat(priceFilter.getAttribute('data-gloskin-price-avail-min') || '0') || 0)       : 0;
		var availMax    = priceFilter ? (parseFloat(priceFilter.getAttribute('data-gloskin-price-avail-max') || '5000000') || 5000000) : 5000000;
		if (availMax <= availMin) { availMax = availMin + 5000000; }
		var selectedMin = availMin;
		var selectedMax = availMax;
		var sliderStep  = computeStep(availMax - availMin);

		function applySliderBounds(aMin, aMax) {
			if (aMax <= aMin || aMax <= 0) { return; }
			availMin   = aMin;
			availMax   = aMax;
			sliderStep = computeStep(availMax - availMin);
			if (minSlider) { minSlider.min = availMin; minSlider.max = availMax; minSlider.step = sliderStep; }
			if (maxSlider) { maxSlider.min = availMin; maxSlider.max = availMax; maxSlider.step = sliderStep; }
			selectedMin = Math.max(availMin, Math.min(selectedMin, availMax));
			selectedMax = Math.max(selectedMin, Math.min(selectedMax, availMax));
			renderSlider(selectedMin, selectedMax);
		}

		function renderSlider(sMin, sMax) {
			var spread  = availMax - availMin;
			var minPct  = spread > 0 ? ((sMin - availMin) / spread * 100) : 0;
			var maxPct  = spread > 0 ? ((sMax - availMin) / spread * 100) : 100;
			if (minSlider) {
				minSlider.value = sMin;
				minSlider.setAttribute('aria-valuenow', String(Math.round(sMin)));
				minSlider.setAttribute('aria-valuetext', formatIDR(sMin));
			}
			if (maxSlider) {
				maxSlider.value = sMax;
				maxSlider.setAttribute('aria-valuenow', String(Math.round(sMax)));
				maxSlider.setAttribute('aria-valuetext', formatIDR(sMax));
			}
			if (priceFilter) {
				priceFilter.style.setProperty('--gloskin-price-min-pct', minPct.toFixed(2) + '%');
				priceFilter.style.setProperty('--gloskin-price-max-pct', maxPct.toFixed(2) + '%');
			}
			if (priceMinLabel) { priceMinLabel.textContent = formatIDR(sMin); }
			if (priceMaxLabel) { priceMaxLabel.textContent = formatIDR(sMax); }
			if (priceReset)    { priceReset.hidden = (sMin === availMin && sMax === availMax); }
		}

		/* Initialise slider visuals */
		if (minSlider) { minSlider.step = sliderStep; }
		if (maxSlider) { maxSlider.step = sliderStep; }
		renderSlider(selectedMin, selectedMax);

		/* --- Utilities --------------------------------------------------------- */

		function stateForLocation() {
			var defaultPage = window.location.href.split('#')[0] === initialUrl.split('#')[0] ? initialPage : 1;
			return parseShopCatalogHash(window.location.hash, defaultPage);
		}

		function categoryFallback(category) {
			var links = categories.querySelectorAll('[data-gloskin-shop-category]');
			for (var i = 0; i < links.length; i++) {
				if ((links[i].getAttribute('data-gloskin-shop-category') || '') === category) {
					return links[i].getAttribute('href') || shopUrl;
				}
			}
			return shopUrl;
		}

		function updateCategoryState(category) {
			var links = categories.querySelectorAll('[data-gloskin-shop-category]');
			Array.prototype.forEach.call(links, function (link) {
				var active = (link.getAttribute('data-gloskin-shop-category') || '') === category;
				if (active) { link.setAttribute('aria-current', 'page'); }
				else { link.removeAttribute('aria-current'); }
			});
		}

		function syncControls(state) {
			state = normalizeShopCatalogState(state, 1);
			if (searchInput) { searchInput.value = state.q; }
			if (searchClear) { searchClear.hidden = !state.q; }

			/* Sync slider to state */
			if (minSlider && maxSlider) {
				var stMin = '' !== state.min_price ? parseFloat(state.min_price) : availMin;
				var stMax = '' !== state.max_price ? parseFloat(state.max_price) : availMax;
				if (!isFinite(stMin)) { stMin = availMin; }
				if (!isFinite(stMax)) { stMax = availMax; }
				stMin = Math.max(availMin, Math.min(stMin, availMax));
				stMax = Math.max(stMin, Math.min(stMax, availMax));
				selectedMin = stMin;
				selectedMax = stMax;
				renderSlider(selectedMin, selectedMax);
			}

			if (clearAll) {
				clearAll.hidden = !state.category && !state.q && !state.min_price && !state.max_price;
			}
			updateCategoryState(state.category);
		}

		function clearStatus() {
			var status = results.querySelector('[data-gloskin-shop-status]');
			if (status) { status.innerHTML = ''; }
		}

		function showCatalogFailure(fallbackHref) {
			var status = results.querySelector('[data-gloskin-shop-status]');
			if (!status) { return; }
			status.innerHTML = '';
			var copy    = document.createElement('span');
			copy.textContent = 'Katalog belum dapat diperbarui. Hasil sebelumnya tetap ditampilkan.';
			var retry   = document.createElement('button');
			retry.type = 'button';
			retry.className = 'gloskin-ui1-button gloskin-ui1-button--ghost gloskin-ui1-button--small';
			retry.setAttribute('data-gloskin-shop-retry', '');
			retry.textContent = 'Coba lagi';
			var fallback = document.createElement('a');
			fallback.className = 'gloskin-ui1-text-link';
			fallback.href = fallbackHref || shopUrl;
			fallback.textContent = 'Buka halaman biasa';
			status.appendChild(copy);
			status.appendChild(retry);
			status.appendChild(fallback);
		}

		var SKELETON_CARD_COUNT = 8;

		function skeletonMarkup() {
			var card  = '<div class="gloskin-ui1-shop-skeleton__card"><div class="gloskin-ui1-shop-skeleton__media"></div><div class="gloskin-ui1-shop-skeleton__line gloskin-ui1-shop-skeleton__line--title"></div><div class="gloskin-ui1-shop-skeleton__line gloskin-ui1-shop-skeleton__line--price"></div></div>';
			var cards = '';
			for (var i = 0; i < SKELETON_CARD_COUNT; i += 1) { cards += card; }
			return '<div class="gloskin-ui1-shop-skeleton" data-gloskin-shop-skeleton aria-hidden="true"><div class="gloskin-ui1-shop-skeleton__grid">' + cards + '</div></div>';
		}

		function setBusy(busy) {
			results.setAttribute('aria-busy', busy ? 'true' : 'false');
			root.classList.toggle('is-loading', !!busy);
			var live = root.querySelector('[data-gloskin-shop-status-live]');
			if (busy) {
				if (!results.querySelector('[data-gloskin-shop-skeleton]')) {
					var height = results.getBoundingClientRect().height;
					if (height > 0) { results.style.minHeight = height + 'px'; }
					results.insertAdjacentHTML('beforeend', skeletonMarkup());
				}
				if (live) { live.textContent = 'Memuat produk'; }
			} else {
				var skeleton = results.querySelector('[data-gloskin-shop-skeleton]');
				if (skeleton) { skeleton.remove(); }
				results.style.removeProperty('min-height');
				if (live) { live.textContent = ''; }
			}
		}

		function historyTarget(state) {
			try {
				var target = new URL(shopUrl, window.location.href);
				target.hash = buildShopCatalogHash(state);
				return target.pathname + target.search + target.hash;
			} catch (e) {
				return shopUrl + buildShopCatalogHash(state);
			}
		}

		function updateHistory(state, mode) {
			if (!window.history || mode === 'none') { return; }
			state = normalizeShopCatalogState(state, 1);
			var target       = historyTarget(state);
			var historyState = {
				gloskinShop: true,
				category:    state.category,
				q:           state.q,
				min_price:   state.min_price,
				max_price:   state.max_price,
				page:        state.page
			};
			if (mode === 'replace' && typeof window.history.replaceState === 'function') {
				window.history.replaceState(historyState, '', target);
			} else if (mode === 'push' && typeof window.history.pushState === 'function') {
				window.history.pushState(historyState, '', target);
			}
		}

		function revealPaginationContext() {
			var heading = results.querySelector('[data-gloskin-shop-results-heading]');
			if (!heading) { return; }
			try { heading.focus({ preventScroll: true }); } catch (e) { heading.focus(); }
			if (typeof heading.scrollIntoView === 'function') {
				var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
				heading.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
			}
		}

		function requestCatalog(nextState, options) {
			options = options || {};
			nextState    = normalizeShopCatalogState(nextState, 1);
			currentState = nextState;
			var sequence = ++requestSequence;
			if (abortController) { abortController.abort(); }
			abortController = typeof window.AbortController !== 'undefined' ? new window.AbortController() : null;
			var fallbackHref = options.fallbackHref || categoryFallback(nextState.category);
			retryRequest = { state: nextState, options: options, fallbackHref: fallbackHref };
			clearStatus();
			setBusy(true);

			var endpoint     = buildShopCatalogRequestUrl(config.restUrl || '/wp-json/gloskin/v1/', nextState);
			var fetchOptions = publicRestGetOptions();
			if (abortController) { fetchOptions.signal = abortController.signal; }

			return window.fetch(endpoint, fetchOptions)
				.then(function (response) {
					if (!response.ok) { throw new Error('shop_catalog_http'); }
					return response.json();
				})
				.then(function (data) {
					if (sequence !== requestSequence) { return false; }
					if (!data || typeof data.html !== 'string') { throw new Error('shop_catalog_response'); }
					results.innerHTML = data.html;
					currentState = normalizeShopCatalogState({
						category:  data.category,
						q:         data.q,
						min_price: data.min_price,
						max_price: data.max_price,
						page:      data.page
					}, 1);

					/* Update available price bounds from the catalog response */
					if (typeof data.available_min_price === 'number' &&
						typeof data.available_max_price === 'number' &&
						data.available_max_price > data.available_min_price) {
						applySliderBounds(data.available_min_price, data.available_max_price);
					}

					syncControls(currentState);
					updateHistory(currentState, options.historyMode || 'none');
					document.dispatchEvent(new CustomEvent('gloskin:catalog-updated', { detail: currentState }));
					if (options.pagination) { revealPaginationContext(); }

					/* Wire inline empty-state buttons in the freshly-rendered HTML */
					wireEmptyStateButtons();

					return true;
				})
				.catch(function (error) {
					if (error && error.name === 'AbortError') { return false; }
					if (sequence !== requestSequence) { return false; }
					showCatalogFailure(fallbackHref);
					return false;
				})
				.then(function (result) {
					if (sequence === requestSequence) {
						setBusy(false);
						abortController = null;
					}
					return result;
				});
		}

		function requestFilterState(nextState, fallbackHref) {
			nextState = normalizeShopCatalogState(nextState, 1);
			nextState.page = 1;
			return requestCatalog(nextState, { historyMode: 'push', fallbackHref: fallbackHref || categoryFallback(nextState.category) });
		}

		/* ---- Search ---------------------------------------------------------- */

		function applySearchNow() {
			if (!searchInput) { return; }
			window.clearTimeout(searchTimer);
			var nextState = normalizeShopCatalogState(currentState, 1);
			nextState.q = String(searchInput.value || '').trim();
			requestFilterState(nextState);
		}

		if (searchInput) {
			searchInput.addEventListener('input', function () {
				var query = String(searchInput.value || '').trim();
				currentState.q = query;
				currentState.page = 1;
				if (searchClear) { searchClear.hidden = !query; }
				window.clearTimeout(searchTimer);
				searchTimer = window.setTimeout(applySearchNow, SEARCH_DELAY);
			});
		}
		if (searchForm) {
			searchForm.addEventListener('submit', function (event) {
				event.preventDefault();
				applySearchNow();
			});
		}
		if (searchClear) {
			searchClear.addEventListener('click', function () {
				if (!searchInput) { return; }
				searchInput.value = '';
				applySearchNow();
				searchInput.focus();
			});
		}

		/* ---- Price slider ---------------------------------------------------- */
		/* input  → visual update only (track fill, labels, aria); NO fetch.
		 * change → commit; fetch via the canonical request owner.               */

		function onSliderInput(which) {
			var rawMin = parseFloat(minSlider ? minSlider.value : selectedMin);
			var rawMax = parseFloat(maxSlider ? maxSlider.value : selectedMax);
			if ('min' === which && rawMin > selectedMax) { rawMin = selectedMax; if (minSlider) { minSlider.value = rawMin; } }
			if ('max' === which && rawMax < selectedMin) { rawMax = selectedMin; if (maxSlider) { maxSlider.value = rawMax; } }
			selectedMin = rawMin;
			selectedMax = rawMax;
			renderSlider(selectedMin, selectedMax);
		}

		function onSliderChange() {
			var nextState = normalizeShopCatalogState(currentState, 1);
			nextState.min_price = (selectedMin === availMin) ? '' : String(Math.round(selectedMin));
			nextState.max_price = (selectedMax === availMax) ? '' : String(Math.round(selectedMax));
			requestFilterState(nextState);
		}

		if (minSlider) {
			minSlider.addEventListener('input',  function () { onSliderInput('min'); });
			minSlider.addEventListener('change', onSliderChange);
		}
		if (maxSlider) {
			maxSlider.addEventListener('input',  function () { onSliderInput('max'); });
			maxSlider.addEventListener('change', onSliderChange);
		}
		if (priceReset) {
			priceReset.addEventListener('click', function () {
				selectedMin = availMin;
				selectedMax = availMax;
				renderSlider(selectedMin, selectedMax);
				var nextState = normalizeShopCatalogState(currentState, 1);
				nextState.min_price = '';
				nextState.max_price = '';
				requestFilterState(nextState);
			});
		}

		/* ---- Clear all ------------------------------------------------------- */

		if (clearAll) {
			clearAll.addEventListener('click', function () {
				window.clearTimeout(searchTimer);
				selectedMin = availMin;
				selectedMax = availMax;
				renderSlider(selectedMin, selectedMax);
				requestFilterState({ category: '', q: '', min_price: '', max_price: '', page: 1 }, shopUrl);
			});
		}

		/* ---- Empty-state inline buttons (rendered into results by server) ---- */

		function wireEmptyStateButtons() {
			/* "Reset pencarian" clears only q */
			var clearSearch = results.querySelector('[data-gloskin-shop-clear-search]');
			if (clearSearch) {
				clearSearch.addEventListener('click', function () {
					if (searchInput) { searchInput.value = ''; }
					var nextState = normalizeShopCatalogState(currentState, 1);
					nextState.q = '';
					requestFilterState(nextState);
				});
			}
			/* "Hapus semua filter" in results area */
			var inlineAll = results.querySelector('[data-gloskin-shop-clear-all]');
			if (inlineAll) {
				inlineAll.addEventListener('click', function () {
					window.clearTimeout(searchTimer);
					selectedMin = availMin;
					selectedMax = availMax;
					renderSlider(selectedMin, selectedMax);
					requestFilterState({ category: '', q: '', min_price: '', max_price: '', page: 1 }, shopUrl);
				});
			}
		}

		/* ---- Delegation: category, pagination, retry ------------------------- */

		root.addEventListener('click', function (event) {
			var categoryLink = event.target.closest && event.target.closest('[data-gloskin-shop-category]');
			if (categoryLink && categories.contains(categoryLink)) {
				event.preventDefault();
				window.clearTimeout(searchTimer);
				var nextState = normalizeShopCatalogState(currentState, 1);
				nextState.category = categoryLink.getAttribute('data-gloskin-shop-category') || '';
				if (searchInput) { nextState.q = String(searchInput.value || '').trim(); }
				requestFilterState(nextState, categoryLink.getAttribute('href') || shopUrl);
				return;
			}

			var pageLink = event.target.closest && event.target.closest('[data-gloskin-shop-page]');
			if (pageLink && results.contains(pageLink)) {
				event.preventDefault();
				window.clearTimeout(searchTimer);
				var nextPageState = normalizeShopCatalogState(currentState, 1);
				nextPageState.page = Math.max(1, parseInt(pageLink.getAttribute('data-gloskin-shop-page'), 10) || 1);
				requestCatalog(nextPageState, { historyMode: 'push', pagination: true, fallbackHref: pageLink.getAttribute('href') || shopUrl });
				return;
			}

			var retry = event.target.closest && event.target.closest('[data-gloskin-shop-retry]');
			if (retry && results.contains(retry) && retryRequest) {
				event.preventDefault();
				requestCatalog(retryRequest.state, {
					historyMode:  retryRequest.options.historyMode || 'none',
					pagination:   !!retryRequest.options.pagination,
					fallbackHref: retryRequest.fallbackHref
				});
			}
		});

		/* ---- Back / Forward -------------------------------------------------- */

		window.addEventListener('popstate', function () {
			window.clearTimeout(searchTimer);
			var state = stateForLocation();
			syncControls(state);
			requestCatalog(state, { historyMode: 'none', fallbackHref: categoryFallback(state.category) });
		});

		/* ---- Initial state from hash ----------------------------------------- */

		currentState = stateForLocation();
		syncControls(currentState);
		if (window.location.hash) {
			requestCatalog(currentState, { historyMode: 'replace', fallbackHref: categoryFallback(currentState.category) });
		}
	}

	function startCanonicalShopCatalog() {
		initCanonicalShopCatalog();
	}

	if (typeof document !== 'undefined') {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', startCanonicalShopCatalog);
		} else {
			startCanonicalShopCatalog();
		}
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = {
			normalizeShopCatalogState:    normalizeShopCatalogState,
			parseShopCatalogHash:         parseShopCatalogHash,
			buildShopCatalogHash:         buildShopCatalogHash,
			buildShopCatalogRequestUrl:   buildShopCatalogRequestUrl,
			formatIDR:                    formatIDR,
			computeStep:                  computeStep
		};
	}
}());
