/**
 * Simple Treatment Finder controller. All canonical paths, concerns and Woo
 * Treatment Product cards are server-rendered; this runtime keeps only the
 * current selections in memory and shows/reorders existing DOM nodes.
 */
(function () {
	'use strict';

	function prefersReducedMotion() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function concernsForPath(paths, pathId) {
		for (var i = 0; i < paths.length; i++) {
			if (String(paths[i].id) === String(pathId)) {
				return Array.isArray(paths[i].concerns) ? paths[i].concerns.slice() : [];
			}
		}
		return [];
	}

	function toggleConcernSelection(selectedIds, concernId, checked) {
		var id = Number(concernId) || 0;
		var next = selectedIds.filter(function (selectedId) { return Number(selectedId) !== id; });
		if (checked && id) { next.push(id); }
		return next;
	}

	/* Score is the number of selected canonical concern IDs attached to a
	 * product. Ties preserve the server/Woo order; only positive matches are
	 * eligible and at most `limit` indexes are returned. */
	function scoreAndRankProducts(products, selectedConcernIds, limit) {
		var selected = {};
		for (var s = 0; s < selectedConcernIds.length; s++) {
			selected[Number(selectedConcernIds[s])] = true;
		}
		var eligible = [];
		for (var i = 0; i < products.length; i++) {
			var concernIds = Array.isArray(products[i].concernIds) ? products[i].concernIds : [];
			var counted = {};
			var score = 0;
			for (var c = 0; c < concernIds.length; c++) {
				var concernId = Number(concernIds[c]) || 0;
				if (selected[concernId] && !counted[concernId]) {
					counted[concernId] = true;
					score++;
				}
			}
			if (score > 0) { eligible.push({ index: i, score: score }); }
		}
		eligible.sort(function (left, right) {
			return right.score !== left.score ? right.score - left.score : left.index - right.index;
		});
		return eligible.slice(0, limit).map(function (entry) { return entry.index; });
	}

	function initConsultation() {
		var root = document.querySelector('[data-gloskin-consultation]');
		if (!root) { return; }

		var data;
		try { data = JSON.parse(root.getAttribute('data-gloskin-consultation-data') || '{}'); } catch (parseError) { return; }
		var paths = Array.isArray(data.paths) ? data.paths : [];
		if (paths.length !== 4) { return; }

		var pathButtons = root.querySelectorAll('[data-gloskin-consultation-path]');
		var concernsEl = root.querySelector('[data-gloskin-consultation-concerns]');
		var concernGroups = root.querySelectorAll('[data-gloskin-consultation-concern-group]');
		var concernInputs = root.querySelectorAll('[data-gloskin-consultation-concern]');
		var submitButton = root.querySelector('[data-gloskin-consultation-submit]');
		var resultsEl = root.querySelector('[data-gloskin-consultation-results]');
		var resultsGrid = root.querySelector('[data-gloskin-consultation-results-grid]');
		var emptyEl = root.querySelector('[data-gloskin-consultation-empty]');
		var resultCards = Array.prototype.slice.call(root.querySelectorAll('[data-gloskin-consultation-result]'));
		if (!concernsEl || !submitButton || !resultsEl || !resultsGrid || !emptyEl) { return; }

		var state = { pathId: null, selectedConcernIds: [] };

		function hideStaleResults() {
			resultsEl.hidden = true;
			emptyEl.hidden = true;
			for (var i = 0; i < resultCards.length; i++) { resultCards[i].hidden = true; }
		}

		function syncSubmitState() {
			submitButton.disabled = !state.pathId || state.selectedConcernIds.length === 0;
		}

		function selectPath(pathId) {
			if (!concernsForPath(paths, pathId).length) { return; }
			state.pathId = String(pathId);
			state.selectedConcernIds = [];
			concernsEl.hidden = false;
			for (var i = 0; i < pathButtons.length; i++) {
				var active = pathButtons[i].getAttribute('data-gloskin-consultation-path') === state.pathId;
				pathButtons[i].classList.toggle('is-active', active);
				pathButtons[i].setAttribute('aria-pressed', active ? 'true' : 'false');
			}
			for (var g = 0; g < concernGroups.length; g++) {
				concernGroups[g].hidden = concernGroups[g].getAttribute('data-gloskin-consultation-concern-group') !== state.pathId;
			}
			for (var c = 0; c < concernInputs.length; c++) { concernInputs[c].checked = false; }
			hideStaleResults();
			syncSubmitState();
		}

		function onConcernChange(event) {
			state.selectedConcernIds = toggleConcernSelection(
				state.selectedConcernIds,
				event.currentTarget.value,
				event.currentTarget.checked
			);
			hideStaleResults();
			syncSubmitState();
		}

		function showResults() {
			if (!state.pathId || !state.selectedConcernIds.length) { return; }
			var products = resultCards.map(function (card) {
				var values = (card.getAttribute('data-gloskin-concern-ids') || '').split(',');
				return { concernIds: values.filter(function (value) { return value !== ''; }).map(Number) };
			});
			var winnerIndexes = scoreAndRankProducts(products, state.selectedConcernIds, 8);
			var visible = [];
			for (var w = 0; w < winnerIndexes.length; w++) {
				var card = resultCards[winnerIndexes[w]];
				resultsGrid.appendChild(card);
				card.hidden = false;
				visible.push(card);
			}
			for (var i = 0; i < resultCards.length; i++) {
				if (visible.indexOf(resultCards[i]) === -1) { resultCards[i].hidden = true; }
			}
			emptyEl.hidden = visible.length !== 0;
			resultsEl.hidden = false;
			resultsEl.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
		}

		for (var p = 0; p < pathButtons.length; p++) {
			pathButtons[p].addEventListener('click', function (event) {
				selectPath(event.currentTarget.getAttribute('data-gloskin-consultation-path'));
			});
		}
		for (var c = 0; c < concernInputs.length; c++) {
			concernInputs[c].addEventListener('change', onConcernChange);
		}
		submitButton.addEventListener('click', showResults);
		hideStaleResults();
		syncSubmitState();
	}

	if (typeof document !== 'undefined') {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initConsultation);
		} else {
			initConsultation();
		}
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = {
			concernsForPath: concernsForPath,
			toggleConcernSelection: toggleConcernSelection,
			scoreAndRankProducts: scoreAndRankProducts
		};
	}
}());
