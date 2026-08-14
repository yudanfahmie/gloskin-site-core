'use strict';

const assert = require('assert');
const path = require('path');
const finderPath = path.join(__dirname, '..', 'plugin', 'gloskin-site-core', 'assets', 'js', 'gloskin-ui1-consultation.js');
const { concernsForPath, toggleConcernSelection, scoreAndRankProducts } = require(finderPath);

(function () {
  const concerns = [{ id: 10, label: 'Acne' }, { id: 20, label: 'Texture' }];
  const paths = [{ id: 1, concerns }, { id: 2, concerns: [{ id: 30, label: 'Hair' }] }];
  const selected = concernsForPath(paths, '1');
  assert.deepStrictEqual(selected, concerns, 'path lookup must expose its canonical baseline concerns');
  assert.notStrictEqual(selected, concerns, 'path lookup must not expose the mutable source array');
  assert.deepStrictEqual(concernsForPath(paths, 999), [], 'unknown paths must expose no concerns');
})();

(function () {
  let selected = [];
  selected = toggleConcernSelection(selected, 10, true);
  selected = toggleConcernSelection(selected, 20, true);
  selected = toggleConcernSelection(selected, 10, true);
  assert.deepStrictEqual(selected, [20, 10], 'multi-select must contain each selected concern only once');
  selected = toggleConcernSelection(selected, 20, false);
  assert.deepStrictEqual(selected, [10], 'unchecking must remove only that concern');
})();

(function () {
  const products = [
    { concernIds: [10] },
    { concernIds: [20] },
    { concernIds: [10, 20] },
    { concernIds: [30] },
    { concernIds: [10, 10] },
  ];
  assert.deepStrictEqual(
    scoreAndRankProducts(products, [10, 20], 8),
    [2, 0, 1, 4],
    'ranking must count selected concern matches, de-duplicate product mappings, exclude zero matches and preserve server order on ties'
  );
})();

(function () {
  const products = Array.from({ length: 12 }, (_, index) => ({ concernIds: [index + 1] }));
  const selected = Array.from({ length: 12 }, (_, index) => index + 1);
  assert.deepStrictEqual(scoreAndRankProducts(products, selected, 8), [0, 1, 2, 3, 4, 5, 6, 7], 'results must cap at eight');
  assert.deepStrictEqual(scoreAndRankProducts([{ concernIds: [] }, {}], [1], 8), [], 'unmapped products must be excluded safely');
})();

console.log('consultation.test.js: OK');
