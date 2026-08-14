/**
 * Treatment Consultation discovery controller (docs/task-treatment-
 * consultation-commerce-discovery.md sections 6-10). Enqueued only on the
 * Treatments Hub (see AssetService::maybe_enqueue_treatment_consultation()).
 *
 * Ownership boundaries this file never crosses:
 * - answers live ONLY in this closure's in-memory state -- zero database/
 *   cookie/analytics persistence, zero network request;
 * - question order is shuffled once per consultation run via a real
 *   client-side Fisher-Yates, never re-shuffled after an answer;
 * - the recommendation grid is entirely server-rendered (every Treatment
 *   Product's real gloskin_ui1_render_product_card() markup already sits
 *   in the DOM, see templates/pages/treatments.php) -- this file only
 *   shows/hides/reorders those existing nodes by moving them, never
 *   creates new card markup and never touches Add to Cart/cart-fragment
 *   behavior, which stays entirely owned by wc-add-to-cart/gloskin-ui1-
 *   core.js's own existing handlers (moving a DOM node preserves any
 *   listeners already bound to it).
 */
(function () {
	'use strict';

	function prefersReducedMotion() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	/* Real Fisher-Yates, not Math.random().sort() (which is not a uniform
	 * shuffle) and never ORDER BY RAND(). Runs exactly once per
	 * consultation path selection -- see selectPath() below, never inside
	 * the per-answer flow. Pure/exported for direct unit testing (see the
	 * module.exports block at the bottom of this file). */
	function fisherYates(list) {
		var shuffled = list.slice();
		for (var i = shuffled.length - 1; i > 0; i--) {
			var j = Math.floor(Math.random() * (i + 1));
			var temp = shuffled[i];
			shuffled[i] = shuffled[j];
			shuffled[j] = temp;
		}
		return shuffled;
	}

	/* Questions eligible for a given path: universal (no path_ids) or
	 * explicitly tagged with this path. Pure/exported for testing. */
	function eligibleQuestionsForPath(allQuestions, pathId) {
		var eligible = [];
		for (var q = 0; q < allQuestions.length; q++) {
			var question = allQuestions[q];
			var pathIds = Array.isArray(question.path_ids) ? question.path_ids : [];
			if (!pathIds.length || pathIds.indexOf(Number(pathId)) !== -1) {
				eligible.push(question);
			}
		}
		return eligible.length ? eligible : allQuestions.slice();
	}

	/* Deterministic scoring (section 7): product score = sum of CURRENT
	 * concern scores for concerns attached to that product; only score > 0
	 * is eligible; sort score DESC then stable original-index fallback
	 * (server-rendered order); capped to `limit`. Pure/exported for direct
	 * unit testing -- no DOM involved.
	 *
	 * @param {Array<{concernIds: number[]}>} products
	 * @param {Object<number, number>} scores concern ID -> current score
	 * @param {number} limit
	 * @return {Array<number>} indexes into `products`, winners first
	 */
	function scoreAndRankProducts(products, scores, limit) {
		var scored = [];
		for (var i = 0; i < products.length; i++) {
			var ids = Array.isArray(products[i].concernIds) ? products[i].concernIds : [];
			var score = 0;
			for (var c = 0; c < ids.length; c++) {
				score += scores[ids[c]] || 0;
			}
			scored.push({ index: i, score: score });
		}
		var eligible = scored.filter(function (entry) { return entry.score > 0; });
		eligible.sort(function (x, y) {
			if (y.score !== x.score) { return y.score - x.score; }
			return x.index - y.index;
		});
		return eligible.slice(0, limit).map(function (entry) { return entry.index; });
	}

	function initConsultation() {
		var root = document.querySelector('[data-gloskin-consultation]');
		if (!root) { return; }

		var raw = root.getAttribute('data-gloskin-consultation-data');
		var data;
		try { data = JSON.parse(raw || '{}'); } catch (parseError) { return; }
		var paths = Array.isArray(data.paths) ? data.paths : [];
		var allQuestions = Array.isArray(data.questions) ? data.questions : [];
		if (!paths.length || !allQuestions.length) { return; }

		var pathButtons = root.querySelectorAll('[data-gloskin-consultation-path]');
		var questionnaireEl = root.querySelector('[data-gloskin-consultation-questionnaire]');
		var resultsEl = root.querySelector('[data-gloskin-consultation-results]');
		var resultsGrid = root.querySelector('[data-gloskin-consultation-results-grid]');
		var emptyEl = root.querySelector('[data-gloskin-consultation-empty]');
		var resultCards = root.querySelectorAll('[data-gloskin-consultation-result]');

		/* In-memory only. Never written to localStorage/sessionStorage/
		 * cookies, never posted anywhere -- see the file-level comment. */
		var state = {
			pathId: null,
			questions: [],
			index: 0,
			history: [],
			scores: {}
		};

		function selectPath(pathId) {
			var path = null;
			for (var p = 0; p < paths.length; p++) {
				if (String(paths[p].id) === String(pathId)) { path = paths[p]; break; }
			}
			var eligible = eligibleQuestionsForPath(allQuestions, pathId);

			state.pathId = pathId;
			state.questions = fisherYates(eligible);
			state.index = 0;
			state.history = [];
			state.scores = {};

			if (path && Array.isArray(path.baseline_concerns)) {
				for (var b = 0; b < path.baseline_concerns.length; b++) {
					var baselineId = path.baseline_concerns[b];
					state.scores[baselineId] = (state.scores[baselineId] || 0) + 1;
				}
			}

			markActivePath(pathId);
			questionnaireEl.hidden = false;
			resultsEl.hidden = false;
			renderQuestion();
			updateResults();

			if (!prefersReducedMotion()) {
				questionnaireEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}
		}

		function markActivePath(pathId) {
			for (var i = 0; i < pathButtons.length; i++) {
				var isActive = pathButtons[i].getAttribute('data-gloskin-consultation-path') === String(pathId);
				pathButtons[i].setAttribute('aria-pressed', isActive ? 'true' : 'false');
				pathButtons[i].classList.toggle('is-active', isActive);
			}
		}

		function renderQuestion() {
			var current = state.questions[state.index];
			questionnaireEl.innerHTML = '';
			if (!current) { renderComplete(); return; }

			var progress = document.createElement('p');
			progress.className = 'gloskin-ui1-consultation__progress';
			progress.setAttribute('aria-live', 'polite');
			progress.textContent = (state.index + 1) + ' / ' + state.questions.length;
			questionnaireEl.appendChild(progress);

			var heading = document.createElement('h3');
			heading.className = 'gloskin-ui1-consultation__question-text';
			heading.textContent = current.text;
			questionnaireEl.appendChild(heading);

			var answers = document.createElement('div');
			answers.className = 'gloskin-ui1-consultation__answers';
			var currentAnswers = Array.isArray(current.answers) ? current.answers : [];
			for (var a = 0; a < currentAnswers.length; a++) {
				answers.appendChild(buildAnswerButton(currentAnswers[a]));
			}
			questionnaireEl.appendChild(answers);

			questionnaireEl.appendChild(buildControls());
		}

		function buildAnswerButton(answer) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'gloskin-ui1-consultation__answer';
			button.textContent = String(answer.label || '');
			button.addEventListener('click', function () { selectAnswer(answer); });
			return button;
		}

		function buildControls() {
			var controls = document.createElement('div');
			controls.className = 'gloskin-ui1-consultation__controls';
			if (state.history.length > 0) {
				var back = document.createElement('button');
				back.type = 'button';
				back.className = 'gloskin-ui1-button gloskin-ui1-button--ghost gloskin-ui1-button--small';
				back.textContent = 'Kembali';
				back.addEventListener('click', goBack);
				controls.appendChild(back);
			}
			var restart = document.createElement('button');
			restart.type = 'button';
			restart.className = 'gloskin-ui1-text-link gloskin-ui1-consultation__restart';
			restart.textContent = 'Mulai Ulang';
			restart.addEventListener('click', function () { selectPath(state.pathId); });
			controls.appendChild(restart);
			return controls;
		}

		function selectAnswer(answer) {
			var concernId = Number(answer.concern_id) || 0;
			var weight = Number(answer.weight) || 0;
			state.history.push({ concernId: concernId, weight: weight });
			if (concernId) {
				state.scores[concernId] = (state.scores[concernId] || 0) + weight;
			}
			state.index++;
			updateResults();
			renderQuestion();
		}

		function goBack() {
			var last = state.history.pop();
			if (!last) { return; }
			if (last.concernId) {
				state.scores[last.concernId] = Math.max(0, (state.scores[last.concernId] || 0) - last.weight);
			}
			state.index = Math.max(0, state.index - 1);
			updateResults();
			renderQuestion();
		}

		function renderComplete() {
			var done = document.createElement('p');
			done.className = 'gloskin-ui1-consultation__complete';
			done.setAttribute('aria-live', 'polite');
			done.textContent = 'Terima kasih! Lihat rekomendasi perawatan di bawah.';
			questionnaireEl.appendChild(done);
			questionnaireEl.appendChild(buildControls());
		}

		/* DOM half of the scoring contract: reads each already-server-
		 * rendered result card's concern IDs, delegates the actual scoring/
		 * ranking to the pure scoreAndRankProducts() above, then only
		 * shows/hides/reorders the SAME existing nodes (appendChild on an
		 * already-attached node moves it) -- never removes/recreates a
		 * card, so any Add to Cart listeners already bound stay intact. */
		function updateResults() {
			var cardList = Array.prototype.slice.call(resultCards);
			var products = cardList.map(function (card) {
				var idsAttr = card.getAttribute('data-gloskin-concern-ids') || '';
				return { concernIds: idsAttr.split(',').filter(function (value) { return value !== ''; }).map(Number) };
			});
			var winnerIndexes = scoreAndRankProducts(products, state.scores, 8);

			var visible = [];
			for (var w = 0; w < winnerIndexes.length; w++) {
				var el = cardList[winnerIndexes[w]];
				resultsGrid.appendChild(el);
				visible.push(el);
			}
			for (var s = 0; s < cardList.length; s++) {
				cardList[s].hidden = visible.indexOf(cardList[s]) === -1;
			}
			emptyEl.hidden = visible.length > 0;
		}

		for (var i = 0; i < pathButtons.length; i++) {
			pathButtons[i].setAttribute('aria-pressed', 'false');
			pathButtons[i].addEventListener('click', function (event) {
				var pathId = event.currentTarget.getAttribute('data-gloskin-consultation-path');
				selectPath(pathId);
			});
		}
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
			fisherYates: fisherYates,
			eligibleQuestionsForPath: eligibleQuestionsForPath,
			scoreAndRankProducts: scoreAndRankProducts
		};
	}
}());
