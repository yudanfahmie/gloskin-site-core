'use strict';

/**
 * Behavioral contract for the Treatment Consultation frontend controller
 * (docs/task-treatment-consultation-commerce-discovery.md sections 7/9):
 * Fisher-Yates question order, per-path question eligibility, and
 * deterministic score-then-rank product recommendations. Requires the
 * real, unmodified assets/js/gloskin-ui1-consultation.js -- see its
 * module.exports guard at the bottom of that file (mirrors
 * gloskin-ui1-core.js's own require()-ability, see hero-video.test.js).
 */
const assert = require('assert');
const path = require('path');

const consultationPath = path.join(__dirname, '..', 'plugin', 'gloskin-site-core', 'assets', 'js', 'gloskin-ui1-consultation.js');
const { fisherYates, eligibleQuestionsForPath, scoreAndRankProducts } = require(consultationPath);

/* -----------------------------------------------------------------------
 * fisherYates(): real uniform shuffle, not a sort()-comparator trick.
 * ------------------------------------------------------------------- */

// Same multiset of elements, same length, order not guaranteed identical.
(function () {
  const input = [1, 2, 3, 4, 5, 6, 7, 8];
  const shuffled = fisherYates(input);
  assert.strictEqual(shuffled.length, input.length, 'shuffle must preserve length');
  assert.deepStrictEqual(shuffled.slice().sort((a, b) => a - b), input, 'shuffle must preserve the exact same multiset of elements');
  assert.notStrictEqual(shuffled, input, 'must return a new array, never mutate the input in place');
})();

// Statistical sanity: across many shuffles of a small array, every
// position is reachable by every element (a broken/no-op "shuffle" would
// fail this near-instantly; this is not proving uniformity, only that
// shuffling actually happens).
(function () {
  const input = [0, 1, 2, 3];
  const seenAtPosition0 = new Set();
  for (let i = 0; i < 200; i++) {
    seenAtPosition0.add(fisherYates(input)[0]);
  }
  assert.ok(seenAtPosition0.size > 1, 'repeated shuffles of the same input must vary (got only: ' + Array.from(seenAtPosition0) + ')');
})();

/* -----------------------------------------------------------------------
 * eligibleQuestionsForPath(): universal (empty path_ids) + path-tagged
 * questions only; falls back to the full set if a path has zero matches
 * (never leaves the questionnaire empty).
 * ------------------------------------------------------------------- */

(function () {
  const questions = [
    { id: 'universal-1', path_ids: [] },
    { id: 'path-1-only', path_ids: [1] },
    { id: 'path-2-only', path_ids: [2] },
    { id: 'path-1-and-2', path_ids: [1, 2] },
  ];
  const forPath1 = eligibleQuestionsForPath(questions, 1).map((q) => q.id);
  assert.deepStrictEqual(forPath1.sort(), ['path-1-and-2', 'path-1-only', 'universal-1'].sort(), 'path 1 must get universal + path-1-tagged questions, and nothing tagged only for another path');

  const noUniversalQuestions = [
    { id: 'path-1-only', path_ids: [1] },
    { id: 'path-2-only', path_ids: [2] },
  ];
  const forUnmatchedPath = eligibleQuestionsForPath(noUniversalQuestions, 999);
  assert.strictEqual(forUnmatchedPath.length, noUniversalQuestions.length, 'a path with zero explicit matches (and no universal questions) must fall back to the full question set, never an empty questionnaire');
})();

/* -----------------------------------------------------------------------
 * scoreAndRankProducts(): deterministic scoring (section 7) -- product
 * score = sum of current concern scores for its attached concerns; only
 * score > 0 is eligible; sort score DESC then stable original-index
 * fallback; capped to `limit`.
 * ------------------------------------------------------------------- */

(function () {
  const products = [
    { concernIds: [10] },      // index 0: score 3
    { concernIds: [20] },      // index 1: score 0 -> excluded (score not > 0)
    { concernIds: [10, 20] },  // index 2: score 3
    { concernIds: [30] },      // index 3: score 5
  ];
  const scores = { 10: 3, 20: 0, 30: 5 };
  const winners = scoreAndRankProducts(products, scores, 8);
  // 30 (score 5) first; then 10 and 10+20 tie at score 3, stable original-
  // index order (0 before 2); index 1 (score 0) is excluded entirely.
  assert.deepStrictEqual(winners, [3, 0, 2], 'ranking must be score DESC, ties broken by stable original order, zero-score products excluded: got ' + JSON.stringify(winners));
})();

(function () {
  // Only score > 0 is eligible -- a product with concern IDs but all-zero
  // current scores must never appear, even alone.
  const winners = scoreAndRankProducts([{ concernIds: [1] }], { 1: 0 }, 8);
  assert.deepStrictEqual(winners, [], 'a product whose only concern has zero score must be excluded, not treated as a fallback match');
})();

(function () {
  // limit is respected even when more products are eligible.
  const products = [];
  const scores = {};
  for (let i = 0; i < 12; i++) {
    products.push({ concernIds: [i] });
    scores[i] = 12 - i; // strictly descending, unambiguous order
  }
  const winners = scoreAndRankProducts(products, scores, 8);
  assert.strictEqual(winners.length, 8, 'must cap results to the requested limit even when more products are eligible');
  assert.deepStrictEqual(winners, [0, 1, 2, 3, 4, 5, 6, 7], 'must keep the highest-scoring products under the limit');
})();

(function () {
  // A product with no concernIds (malformed/empty) scores 0 and is excluded, never fatal.
  const winners = scoreAndRankProducts([{ concernIds: [] }, { concernIds: undefined }], { 1: 5 }, 8);
  assert.deepStrictEqual(winners, [], 'products with no concern IDs must score 0 and be excluded without throwing');
})();

console.log('consultation.test.js: OK');
