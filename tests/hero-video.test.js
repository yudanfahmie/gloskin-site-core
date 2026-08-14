'use strict';

const assert = require('assert');
const path = require('path');

const corePath = path.join(__dirname, '..', 'plugin', 'gloskin-site-core', 'assets', 'js', 'gloskin-ui1-core.js');
const {
  shouldAutoEnhanceHeroVideo,
  buildHeroVideoEmbedUrl,
  enhanceHeroVideo,
  initHeroVideo,
  setupHeroBackgroundVideo,
  initHeroBackgroundVideo
} = require(corePath);

/* -----------------------------------------------------------------------
 * Pure helpers
 * ------------------------------------------------------------------- */

// buildHeroVideoEmbedUrl(): youtube-nocookie only, muted autoplay, no
// controls, own playlist=id loop trick -- never youtube.com/embed.
(function () {
  const url = buildHeroVideoEmbedUrl('otej7WLdPh0');
  assert.ok(url.indexOf('https://www.youtube-nocookie.com/embed/otej7WLdPh0') === 0, 'must use the privacy-enhanced youtube-nocookie.com domain: ' + url);
  assert.ok(url.indexOf('youtube.com/embed') === -1, 'must never use the tracking youtube.com/embed domain: ' + url);
  assert.ok(/[?&]autoplay=1(&|$)/.test(url), 'must request autoplay=1: ' + url);
  assert.ok(/[?&]mute=1(&|$)/.test(url), 'must request mute=1 (never autoplay audio): ' + url);
  assert.ok(/[?&]playsinline=1(&|$)/.test(url), 'must request playsinline=1: ' + url);
  assert.ok(/[?&]loop=1(&|$)/.test(url), 'must request loop=1: ' + url);
  assert.ok(/[?&]playlist=otej7WLdPh0(&|$)/.test(url), 'must set playlist=<id> (required by YouTube for single-video looping): ' + url);
  assert.ok(/[?&]controls=0(&|$)/.test(url), 'must request controls=0: ' + url);
})();

/* -----------------------------------------------------------------------
 * Global mocking helpers. Node's own `navigator` global is a read-only
 * getter-only built-in (v21+), so it must be overridden with
 * Object.defineProperty, not plain assignment. `window`/`document` are
 * not Node built-ins, so plain assignment/deletion is safe for them.
 * ------------------------------------------------------------------- */

function withGlobals(win, doc, nav, fn) {
  const originalWindow = global.window;
  const originalDocument = global.document;
  const originalNavDescriptor = Object.getOwnPropertyDescriptor(global, 'navigator');
  global.window = win;
  global.document = doc;
  Object.defineProperty(global, 'navigator', { value: nav, configurable: true, writable: true });
  try {
    fn();
  } finally {
    global.window = originalWindow;
    global.document = originalDocument;
    if (originalNavDescriptor) {
      Object.defineProperty(global, 'navigator', originalNavDescriptor);
    } else {
      delete global.navigator;
    }
  }
}

// shouldAutoEnhanceHeroVideo(): reduced-motion / save-data gate.
withGlobals({ matchMedia: () => ({ matches: false }) }, {}, { connection: { saveData: false } }, () => {
  assert.strictEqual(shouldAutoEnhanceHeroVideo(), true, 'must auto-enhance when neither reduced-motion nor save-data is set');
});
withGlobals({ matchMedia: () => ({ matches: true }) }, {}, { connection: { saveData: false } }, () => {
  assert.strictEqual(shouldAutoEnhanceHeroVideo(), false, 'must NOT auto-enhance when prefers-reduced-motion: reduce matches');
});
withGlobals({ matchMedia: () => ({ matches: false }) }, {}, { connection: { saveData: true } }, () => {
  assert.strictEqual(shouldAutoEnhanceHeroVideo(), false, 'must NOT auto-enhance when navigator.connection.saveData is true');
});

/* -----------------------------------------------------------------------
 * enhanceHeroVideo(): idempotent one-iframe DOM wiring, hand-rolled
 * minimal DOM mock (matching the existing style already used by
 * tests/single-product-ajax-payload.test.js), no jsdom dependency added.
 * ------------------------------------------------------------------- */

function makeFakeElement(tag) {
  const el = {
    tagName: String(tag).toUpperCase(),
    className: '',
    attributes: {},
    children: [],
    listeners: {},
    classList: {
      _set: new Set(),
      add(name) { this._set.add(name); },
      remove(name) { this._set.delete(name); },
      contains(name) { return this._set.has(name); }
    },
    setAttribute(name, value) { el.attributes[name] = String(value); },
    getAttribute(name) { return Object.prototype.hasOwnProperty.call(el.attributes, name) ? el.attributes[name] : null; },
    addEventListener(type, handler) {
      el.listeners[type] = el.listeners[type] || [];
      el.listeners[type].push(handler);
    },
    removeEventListener(type, handler) {
      if (!el.listeners[type]) { return; }
      el.listeners[type] = el.listeners[type].filter((h) => h !== handler);
    },
    dispatch(type) {
      (el.listeners[type] || []).slice().forEach((h) => h.call(el));
    },
    appendChild(child) { el.children.push(child); return child; },
    querySelector(selector) {
      if (selector === '.gloskin-ui1-hero-video__poster') { return el._poster || null; }
      if (selector === '[data-gloskin-hero-video-play]') { return el._play || null; }
      return null;
    }
  };
  return el;
}

function makeFakeContainer(videoId) {
  const container = makeFakeElement('div');
  container.attributes['data-video-id'] = videoId;
  container.attributes['data-video-title'] = 'Test hero video';
  container._poster = makeFakeElement('img');
  container._play = makeFakeElement('button');
  return container;
}

const fakeDocument = { createElement: (tag) => makeFakeElement(tag) };

// Explicit Play click creates exactly one iframe; a second click never
// creates a second one (idempotency guard). window = {} (no
// IntersectionObserver) means the auto-enhance path is skipped entirely,
// isolating this test to the explicit-click path.
withGlobals({}, fakeDocument, {}, () => {
  const container = makeFakeContainer('otej7WLdPh0');
  enhanceHeroVideo(container);
  assert.strictEqual(container.children.length, 0, 'must create ZERO iframes before any interaction');

  container._play.dispatch('click');
  assert.strictEqual(container.children.length, 1, 'first Play click must create exactly one iframe');
  assert.strictEqual(container.children[0].tagName, 'IFRAME', 'the created child must be an iframe');
  assert.ok(container.classList.contains('is-loaded'), 'container must gain is-loaded after the iframe is created');

  container._play.dispatch('click');
  assert.strictEqual(container.children.length, 1, 'a second Play click must NOT create a second iframe (idempotency)');
});

// The created iframe must itself be youtube-nocookie-only and carry a
// meaningful title, with a minimal-but-real allow attribute.
withGlobals({}, fakeDocument, {}, () => {
  const container = makeFakeContainer('otej7WLdPh0');
  enhanceHeroVideo(container);
  container._play.dispatch('click');
  const iframe = container.children[0];
  assert.ok(iframe.src.indexOf('https://www.youtube-nocookie.com/embed/otej7WLdPh0') === 0, 'iframe src must use youtube-nocookie.com: ' + iframe.src);
  assert.strictEqual(iframe.title, 'Test hero video', 'iframe must carry a meaningful title from data-video-title');
  assert.ok(iframe.attributes.allow.indexOf('autoplay') !== -1, 'iframe allow attribute must include autoplay');
});

// Poster onerror fallback: swaps to the hqdefault fallback exactly once.
withGlobals({}, fakeDocument, {}, () => {
  const container = makeFakeContainer('otej7WLdPh0');
  container._poster.src = 'https://i.ytimg.com/vi/otej7WLdPh0/maxresdefault.jpg';
  container._poster.attributes['data-gloskin-hero-video-fallback'] = 'https://i.ytimg.com/vi/otej7WLdPh0/hqdefault.jpg';
  enhanceHeroVideo(container);
  container._poster.dispatch('error');
  assert.strictEqual(container._poster.src, 'https://i.ytimg.com/vi/otej7WLdPh0/hqdefault.jpg', 'poster must fall back to hqdefault.jpg on error');
});

// A container with no data-video-id must be inert (never throw, never
// create anything) -- this is the disabled/invalid-URL fallback path,
// since the PHP renderer only ever emits the facade when a real ID
// resolved; an empty/missing id here proves the JS side is equally safe.
withGlobals({}, fakeDocument, {}, () => {
  const container = makeFakeContainer('');
  assert.doesNotThrow(() => enhanceHeroVideo(container), 'must never throw for a missing video id');
  assert.strictEqual(container.children.length, 0, 'must never create an iframe for a missing video id');
});

// initHeroVideo(): one canonical initializer that finds every
// [data-gloskin-hero-video] container via document.querySelectorAll --
// on the real Home page there is only ever one, but the initializer
// itself must not assume that.
(function () {
  const container = makeFakeContainer('otej7WLdPh0');
  const doc = Object.assign({}, fakeDocument, {
    querySelectorAll(selector) {
      assert.strictEqual(selector, '[data-gloskin-hero-video]', 'must query the documented data attribute');
      return [container];
    }
  });
  withGlobals({}, doc, {}, () => {
    initHeroVideo();
    container._play.dispatch('click');
    assert.strictEqual(container.children.length, 1, 'initHeroVideo() must wire up discovered containers so Play still works');
  });
})();

// Reduced-motion + real IntersectionObserver support: auto-enhance must
// NOT even register an observer (proven by the observer constructor never
// being invoked), and Play must still fully work afterwards.
(function () {
  let observerConstructed = false;
  function FakeObserver() { observerConstructed = true; }
  FakeObserver.prototype.observe = function () {};
  FakeObserver.prototype.disconnect = function () {};

  const win = {
    matchMedia: () => ({ matches: true }), // prefers-reduced-motion: reduce
    IntersectionObserver: FakeObserver
  };
  const container = makeFakeContainer('otej7WLdPh0');
  withGlobals(win, fakeDocument, {}, () => {
    enhanceHeroVideo(container);
    assert.strictEqual(observerConstructed, false, 'reduced-motion must skip auto-enhance entirely, never even constructing an IntersectionObserver');
    container._play.dispatch('click');
    assert.strictEqual(container.children.length, 1, 'Play must still fully work under reduced-motion');
  });
})();

// Auto-enhance path: when visible (isIntersecting) and neither
// reduced-motion nor save-data is set, the video loads without any click,
// and the observer disconnects itself (no scroll polling / repeated
// callbacks).
(function () {
  let disconnected = false;
  let observedContainer = null;
  let capturedCallback = null;
  function FakeObserver(callback) { capturedCallback = callback; }
  FakeObserver.prototype.observe = function (el) { observedContainer = el; };
  FakeObserver.prototype.disconnect = function () { disconnected = true; };

  const win = {
    matchMedia: () => ({ matches: false }),
    IntersectionObserver: FakeObserver,
    requestIdleCallback: (cb) => cb() // run the idle callback synchronously for the test
  };
  const container = makeFakeContainer('otej7WLdPh0');
  withGlobals(win, fakeDocument, {}, () => {
    enhanceHeroVideo(container);
    assert.strictEqual(observedContainer, container, 'must observe the hero video container');
    assert.strictEqual(container.children.length, 0, 'must not load before intersection fires');

    capturedCallback([{ isIntersecting: true }]);
    assert.strictEqual(container.children.length, 1, 'becoming visible must load the video without any click');
    assert.ok(disconnected, 'the observer must disconnect itself once enhanced -- no repeated callbacks/polling');

    // A second, unrelated call to the same callback (defensive against a
    // stray observer firing again) must never create a second iframe.
    capturedCallback([{ isIntersecting: true }]);
    assert.strictEqual(container.children.length, 1, 'a second intersection callback must never create a second iframe (idempotency)');
  });
})();

/* -----------------------------------------------------------------------
 * Hero Background Video (native <video>, Home video-only mode):
 * PREPARING -> READY/FAILED state machine. Fake synchronous thenables
 * (matching this file's existing hand-rolled-DOM style, no real Promise
 * microtask/timer scheduling needed) drive the play() Promise branch
 * deterministically.
 * ------------------------------------------------------------------- */

function fakeResolvedPromise() {
  return { then: function (onFulfilled) { onFulfilled(); return { catch: function () {} }; } };
}
function fakeRejectedPromise() {
  return { then: function () { return { catch: function (onRejected) { onRejected(); } }; } };
}

function makeClassList() {
  var set = new Set();
  return {
    add: function (name) { set.add(name); },
    remove: function (name) { set.delete(name); },
    contains: function (name) { return set.has(name); }
  };
}

function makeHeroBgFixture(options) {
  options = options || {};
  var listeners = {};
  var video = {
    listeners: listeners,
    paused: false,
    addEventListener(type, handler) {
      listeners[type] = listeners[type] || [];
      listeners[type].push(handler);
    },
    removeEventListener(type, handler) {
      if (!listeners[type]) { return; }
      listeners[type] = listeners[type].filter((h) => h !== handler);
    },
    dispatch(type) {
      (listeners[type] || []).slice().forEach((h) => h.call(video));
    },
    pause() { video.paused = true; },
    play() {
      video.playCalled = (video.playCalled || 0) + 1;
      return options.playReturn ? options.playReturn() : undefined;
    }
  };
  var hero = { classList: makeClassList() };
  var wrap = {
    querySelector: (selector) => (selector === '[data-gloskin-hero-bg-video]' ? video : null),
    closest: () => hero
  };
  return { video, hero, wrap };
}

// Normal (non reduced-motion) path: loadeddata -> play() -> resolved Promise
// -> revealed inside requestAnimationFrame.
(function () {
  var fixture = makeHeroBgFixture({ playReturn: fakeResolvedPromise });
  fixture.hero.classList.add('is-video-preparing');
  var win = { matchMedia: () => ({ matches: false }), requestAnimationFrame: (cb) => cb(), setTimeout: () => {} };
  withGlobals(win, fakeDocument, {}, () => {
    setupHeroBackgroundVideo(fixture.hero, fixture.wrap);
    fixture.video.dispatch('loadeddata');
  });
  assert.strictEqual(fixture.video.playCalled, 1, 'must call video.play() once loadeddata fires (non reduced-motion)');
  assert.ok(fixture.hero.classList.contains('is-video-ready'), 'must add is-video-ready once play() resolves');
  assert.ok(!fixture.hero.classList.contains('is-video-preparing'), 'must remove is-video-preparing once ready');
})();

// Reduced motion: must never call play(), must pause to stay static, but
// still reveals the established first frame (no indefinite white hero).
(function () {
  var fixture = makeHeroBgFixture({});
  fixture.hero.classList.add('is-video-preparing');
  var win = { matchMedia: () => ({ matches: true }), requestAnimationFrame: (cb) => cb(), setTimeout: () => {} };
  withGlobals(win, fakeDocument, {}, () => {
    setupHeroBackgroundVideo(fixture.hero, fixture.wrap);
    fixture.video.dispatch('loadeddata');
  });
  assert.strictEqual(fixture.video.playCalled, undefined, 'reduced motion must never call video.play()');
  assert.ok(fixture.video.paused, 'reduced motion must leave the video paused/static');
  assert.ok(fixture.hero.classList.contains('is-video-ready'), 'reduced motion must still reveal the established static frame');
})();

// A rejected play() Promise must release a clean failure state, never a
// false is-video-ready.
(function () {
  var fixture = makeHeroBgFixture({ playReturn: fakeRejectedPromise });
  fixture.hero.classList.add('is-video-preparing');
  var win = { matchMedia: () => ({ matches: false }), requestAnimationFrame: (cb) => cb(), setTimeout: () => {} };
  withGlobals(win, fakeDocument, {}, () => {
    setupHeroBackgroundVideo(fixture.hero, fixture.wrap);
    fixture.video.dispatch('loadeddata');
  });
  assert.ok(fixture.hero.classList.contains('is-video-failed'), 'a rejected play() Promise must release a clean failure state');
  assert.ok(!fixture.hero.classList.contains('is-video-ready'), 'a failed video must never be marked ready');
})();

// A native error event (before loadeddata ever fires) must also release
// the clean failure state.
(function () {
  var fixture = makeHeroBgFixture({});
  fixture.hero.classList.add('is-video-preparing');
  var win = { matchMedia: () => ({ matches: false }), setTimeout: () => {} };
  withGlobals(win, fakeDocument, {}, () => {
    setupHeroBackgroundVideo(fixture.hero, fixture.wrap);
    fixture.video.dispatch('error');
  });
  assert.ok(fixture.hero.classList.contains('is-video-failed'), 'a native error event must release a clean failure state');
})();

// Settled state is sticky: a late error after the video already became
// ready must never flip it back to failed (idempotency guard).
(function () {
  var fixture = makeHeroBgFixture({ playReturn: fakeResolvedPromise });
  var win = { matchMedia: () => ({ matches: false }), requestAnimationFrame: (cb) => cb(), setTimeout: () => {} };
  withGlobals(win, fakeDocument, {}, () => {
    setupHeroBackgroundVideo(fixture.hero, fixture.wrap);
    fixture.video.dispatch('loadeddata');
    fixture.video.dispatch('error');
  });
  assert.ok(fixture.hero.classList.contains('is-video-ready'), 'settled state must be sticky');
  assert.ok(!fixture.hero.classList.contains('is-video-failed'), 'a late error after ready must never flip it back to failed');
})();

// initHeroBackgroundVideo(): discovers every [data-gloskin-hero-bg-video-wrap]
// via document.querySelectorAll and wires it up through its closest hero.
(function () {
  var fixture = makeHeroBgFixture({ playReturn: fakeResolvedPromise });
  var doc = Object.assign({}, fakeDocument, {
    querySelectorAll(selector) {
      assert.strictEqual(selector, '[data-gloskin-hero-bg-video-wrap]', 'must query the documented wrapper data attribute');
      return [fixture.wrap];
    }
  });
  var win = { matchMedia: () => ({ matches: false }), requestAnimationFrame: (cb) => cb(), setTimeout: () => {} };
  withGlobals(win, doc, {}, () => {
    initHeroBackgroundVideo();
    fixture.video.dispatch('loadeddata');
  });
  assert.ok(fixture.hero.classList.contains('is-video-ready'), 'initHeroBackgroundVideo() must wire up discovered wrappers');
})();

console.log('hero-video.test.js: OK');
