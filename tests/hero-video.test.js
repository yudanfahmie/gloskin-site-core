'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const {
  setupHeroBackgroundVideo,
  initHeroBackgroundVideo
} = require('../plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js');

function classList(initial) {
  const values = new Set(initial || []);
  return {
    add(value) { values.add(value); },
    remove(value) { values.delete(value); },
    contains(value) { return values.has(value); }
  };
}

function resolvedThenable() {
  return { then(resolve) { resolve(); return { catch() {} }; } };
}

function rejectedThenable() {
  return { then() { return { catch(reject) { reject(new Error('blocked')); } }; } };
}

function deferredThenable() {
  let resolveHandler = null;
  let rejectHandler = null;
  return {
    value: {
      then(resolve) {
        resolveHandler = resolve;
        return { catch(reject) { rejectHandler = reject; } };
      }
    },
    resolve() { resolveHandler(); },
    reject() { rejectHandler(new Error('blocked')); }
  };
}

function fixture(options) {
  const config = options || {};
  const listeners = {};
  let playCalls = 0;
  let pauseCalls = 0;
  const video = {
    readyState: config.readyState || 0,
    paused: config.paused !== undefined ? config.paused : true,
    addEventListener(name, callback) { listeners[name] = callback; },
    dispatch(name) { if (listeners[name]) listeners[name](); },
    play() {
      playCalls += 1;
      if (config.playThrows) throw new Error('sync play failure');
      return config.playReturn === undefined ? resolvedThenable() : config.playReturn;
    },
    pause() { pauseCalls += 1; this.paused = true; }
  };
  const hero = { classList: classList(['is-video-preparing']) };
  const wrap = {
    querySelector(selector) {
      assert.strictEqual(selector, '[data-gloskin-hero-bg-video]');
      return video;
    },
    closest() { return hero; }
  };
  return {
    hero,
    wrap,
    video,
    get playCalls() { return playCalls; },
    get pauseCalls() { return pauseCalls; }
  };
}

function run(config, callback) {
  const timers = [];
  const cleared = [];
  const previousWindow = global.window;
  global.window = {
    matchMedia: () => ({ matches: !!config.reducedMotion }),
    requestAnimationFrame: (fn) => fn(),
    setTimeout(fn) { timers.push(fn); return timers.length; },
    clearTimeout(handle) { cleared.push(handle); }
  };
  try {
    callback(timers, cleared);
  } finally {
    if (previousWindow === undefined) delete global.window;
    else global.window = previousWindow;
  }
}

function isReady(value) {
  return value.hero.classList.contains('is-video-ready');
}

// Already-loaded state is reconciled after listeners are attached; promise
// resolution alone is insufficient until genuine playing is observed.
(function () {
  const value = fixture({ readyState: 2, playReturn: resolvedThenable() });
  run({}, () => {
    setupHeroBackgroundVideo(value.hero, value.wrap);
    assert.strictEqual(value.playCalls, 1);
    assert.strictEqual(isReady(value), false);
    value.video.dispatch('playing');
    assert.strictEqual(isReady(value), true);
  });
})();

// Already-playing state is reconciled without waiting for a future event.
(function () {
  const value = fixture({ readyState: 3, paused: false, playReturn: resolvedThenable() });
  run({}, () => setupHeroBackgroundVideo(value.hero, value.wrap));
  assert.strictEqual(isReady(value), true);
  assert.strictEqual(value.playCalls, 1);
})();

// playing -> loadeddata and loadeddata -> playing both converge.
(function () {
  const value = fixture({ playReturn: resolvedThenable() });
  run({}, () => {
    setupHeroBackgroundVideo(value.hero, value.wrap);
    value.video.dispatch('playing');
    assert.strictEqual(isReady(value), false);
    value.video.dispatch('loadeddata');
    assert.strictEqual(isReady(value), true);
  });
})();

// Promise resolution before playing does not reveal early.
(function () {
  const deferred = deferredThenable();
  const value = fixture({ readyState: 2, playReturn: deferred.value });
  run({}, () => {
    setupHeroBackgroundVideo(value.hero, value.wrap);
    deferred.resolve();
    assert.strictEqual(isReady(value), false);
    value.video.dispatch('playing');
    assert.strictEqual(isReady(value), true);
  });
})();

// playing before Promise resolution does not reveal early.
(function () {
  const deferred = deferredThenable();
  const value = fixture({ readyState: 2, playReturn: deferred.value });
  run({}, () => {
    setupHeroBackgroundVideo(value.hero, value.wrap);
    value.video.dispatch('playing');
    assert.strictEqual(isReady(value), false);
    deferred.resolve();
    assert.strictEqual(isReady(value), true);
  });
})();

// play() is attempted once even when several readiness events repeat.
(function () {
  const value = fixture({ playReturn: resolvedThenable() });
  run({}, () => {
    setupHeroBackgroundVideo(value.hero, value.wrap);
    value.video.dispatch('loadeddata');
    value.video.dispatch('loadeddata');
    value.video.dispatch('playing');
  });
  assert.strictEqual(value.playCalls, 1);
})();

// Synchronous and asynchronous play failures release the loader cleanly.
for (const config of [{ playThrows: true }, { playReturn: rejectedThenable() }]) {
  const value = fixture(config);
  run({}, () => setupHeroBackgroundVideo(value.hero, value.wrap));
  assert.strictEqual(value.hero.classList.contains('is-video-failed'), true);
  assert.strictEqual(isReady(value), false);
}

// A native media error is terminal and clears its safety timeout.
(function () {
  const deferred = deferredThenable();
  const value = fixture({ readyState: 2, playReturn: deferred.value });
  run({}, (timers, cleared) => {
    setupHeroBackgroundVideo(value.hero, value.wrap);
    value.video.dispatch('error');
    assert.strictEqual(value.hero.classList.contains('is-video-failed'), true);
    assert.strictEqual(cleared.length, 1);
    value.video.dispatch('playing');
    deferred.resolve();
    assert.strictEqual(isReady(value), false);
  });
})();

// Timeout releases the loader but does not settle the controller; late
// valid media readiness recovers to READY and removes the fallback class.
(function () {
  const deferred = deferredThenable();
  const value = fixture({ playReturn: deferred.value });
  run({}, (timers) => {
    setupHeroBackgroundVideo(value.hero, value.wrap);
    assert.strictEqual(timers.length, 1);
    timers[0]();
    assert.strictEqual(value.hero.classList.contains('is-video-preparing'), false);
    assert.strictEqual(value.hero.classList.contains('is-video-failed'), true);
    value.video.dispatch('loadeddata');
    value.video.dispatch('playing');
    deferred.resolve();
    assert.strictEqual(isReady(value), true);
    assert.strictEqual(value.hero.classList.contains('is-video-failed'), false);
  });
})();

// Reduced motion reveals an already-usable frame immediately, paused.
(function () {
  const value = fixture({ readyState: 2 });
  run({ reducedMotion: true }, () => setupHeroBackgroundVideo(value.hero, value.wrap));
  assert.strictEqual(value.playCalls, 0);
  assert.strictEqual(value.pauseCalls, 1);
  assert.strictEqual(isReady(value), true);
})();

// Reduced motion also handles usable data arriving after initialization.
(function () {
  const value = fixture({});
  run({ reducedMotion: true }, () => {
    setupHeroBackgroundVideo(value.hero, value.wrap);
    assert.strictEqual(isReady(value), false);
    value.video.dispatch('loadeddata');
    assert.strictEqual(value.pauseCalls, 1);
    assert.strictEqual(isReady(value), true);
  });
  assert.strictEqual(value.playCalls, 0);
})();

// The initializer discovers and wires the canonical native wrapper.
(function () {
  const value = fixture({ readyState: 2, paused: false, playReturn: resolvedThenable() });
  const previousDocument = global.document;
  global.document = { querySelectorAll: () => [value.wrap] };
  try {
    run({}, () => initHeroBackgroundVideo());
  } finally {
    if (previousDocument === undefined) delete global.document;
    else global.document = previousDocument;
  }
  assert.strictEqual(isReady(value), true);
})();

const controllerSource = fs.readFileSync(path.join(__dirname, '../plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js'), 'utf8');
const section = controllerSource.slice(controllerSource.indexOf('Hero Background Video'), controllerSource.indexOf("Home video-only hero's one scroll cue"));
assert.strictEqual(section.includes('setInterval'), false, 'controller must not poll');
assert.strictEqual((section.match(/video\.play\(\)/g) || []).length, 1, 'controller source must contain one play call');

console.log('hero-video.test.js: OK');
