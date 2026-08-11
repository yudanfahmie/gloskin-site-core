'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const corePath = path.join(__dirname, '..', 'plugin', 'gloskin-site-core', 'assets', 'js', 'gloskin-ui1-core.js');
const coreSource = fs.readFileSync(corePath, 'utf8');
const {
  resolveWooSubmitter,
  isSupportedSingleProductAjaxForm,
  shouldInterceptWooSubmit,
  normalizeAddToCartPayload,
  hasWooAjaxBridge,
  hasWooVariationRuntime,
  hasWooNativeAddToCartRuntime,
  dispatchWooAddedToCart,
  handleWooAddToCartResponse,
  isWooSubmitBusy
} = require(corePath);

function classList(names) {
  const set = new Set(names || []);
  return { contains: (name) => set.has(name) };
}

function makeRoot(typeClass) {
  return { classList: classList([typeClass]) };
}

function makeForm(options = {}) {
  const root = options.root || null;
  const variationId = options.variationId || '';
  const fallbackSubmitter = options.fallbackSubmitter || null;
  return {
    classList: classList(options.classes || []),
    closest(selector) {
      return selector === 'div.product' ? root : null;
    },
    querySelector(selector) {
      if (selector === 'input.variation_id, input[name="variation_id"]') {
        return variationId === null ? null : { value: String(variationId) };
      }
      if (selector === '.single_add_to_cart_button[type="submit"]' || selector === '.single_add_to_cart_button') {
        return fallbackSubmitter;
      }
      return null;
    }
  };
}

function submitter(value = '101', extra = {}) {
  return Object.assign({
    name: 'add-to-cart',
    value: String(value),
    disabled: false,
    classList: classList([])
  }, extra);
}

// Production ownership: the browser serializes successful controls. Gloskin
// must not recreate input/select/file/repeated-control semantics by hand.
assert(coreSource.includes('new FormData(form)'), 'production payload must originate from native FormData(form)');
assert(!coreSource.includes('function serializeWooForm'), 'manual Woo form serializer must not remain in production');
assert(!coreSource.includes("form.querySelectorAll('input[name], select[name], textarea[name]')"), 'manual successful-control scan must be removed');
assert(!coreSource.includes("formData.set('variation_id'"), 'variation_id must remain the browser/Woo supplied value');

// Actual submitter wins, with fallback only for implicit submissions.
const clicked = submitter('101');
const fallback = submitter('999');
const simpleForm = makeForm({ root: makeRoot('product-type-simple'), fallbackSubmitter: fallback });
assert.strictEqual(resolveWooSubmitter(simpleForm, { submitter: clicked }), clicked, 'SubmitEvent.submitter must win');
assert.strictEqual(resolveWooSubmitter(simpleForm, {}), fallback, 'canonical Woo submitter fallback must remain');
assert.strictEqual(isSupportedSingleProductAjaxForm(simpleForm), true, 'simple product root must be supported');
assert.strictEqual(shouldInterceptWooSubmit(simpleForm, clicked), true, 'eligible simple submit must be interceptable');

const simpleData = new FormData();
simpleData.append('quantity', '2');
normalizeAddToCartPayload(simpleData, clicked);
assert.strictEqual(simpleData.get('add-to-cart'), '101', 'actual submitter name/value must be appended');
assert.strictEqual(simpleData.get('product_id'), '101', 'simple product_id must derive from actual submitter when absent');
assert.strictEqual(simpleData.get('quantity'), '2', 'native payload fields must survive normalization');

// Variable payload keeps Woo's selected variation_id intact and uses that
// variation as product_id for WC_AJAX::add_to_cart(). Repeated values survive.
const variableForm = makeForm({
  root: makeRoot('product-type-variable'),
  classes: ['variations_form'],
  variationId: '205'
});
const variableButton = submitter('202');
assert.strictEqual(isSupportedSingleProductAjaxForm(variableForm), true, 'variable variations_form must be supported');
assert.strictEqual(shouldInterceptWooSubmit(variableForm, variableButton), true, 'selected variation must be interceptable');
const variableData = new FormData();
variableData.append('product_id', '202');
variableData.append('variation_id', '205');
variableData.append('attribute_pa_size', '30ml');
variableData.append('addon[]', 'alpha');
variableData.append('addon[]', 'beta');
normalizeAddToCartPayload(variableData, variableButton);
assert.strictEqual(variableData.get('product_id'), '205', 'selected variation must become AJAX product_id');
assert.strictEqual(variableData.get('variation_id'), '205', 'variation_id must remain intact');
assert.deepStrictEqual(variableData.getAll('addon[]'), ['alpha', 'beta'], 'repeated/multi-value native fields must survive');

const unselectedVariable = makeForm({
  root: makeRoot('product-type-variable'),
  classes: ['variations_form'],
  variationId: '0'
});
assert.strictEqual(shouldInterceptWooSubmit(unselectedVariable, variableButton), false, 'variable form with no valid variation must not mutate cart');
assert.strictEqual(shouldInterceptWooSubmit(simpleForm, submitter('101', { disabled: true })), false, 'disabled submitter must stay native');
assert.strictEqual(shouldInterceptWooSubmit(simpleForm, submitter('101', { classList: classList(['disabled']) })), false, 'Woo disabled class must stay native');

// Busy state is an explicit duplicate-submit guard while the POST is in flight.
assert.strictEqual(isWooSubmitBusy({ getAttribute: (name) => name === 'aria-busy' ? 'true' : null }), true, 'aria-busy=true must block repeat submission');
assert.strictEqual(isWooSubmitBusy({ getAttribute: () => 'false' }), false, 'aria-busy=false must not block a later user submission');

// Unsupported Woo product roots must never enter the custom single-product AJAX bridge.
for (const type of ['product-type-grouped', 'product-type-external', 'product-type-affiliate', 'product-type-custom']) {
  assert.strictEqual(isSupportedSingleProductAjaxForm(makeForm({ root: makeRoot(type) })), false, `${type} must bypass Gloskin AJAX`);
}
assert.strictEqual(isSupportedSingleProductAjaxForm(makeForm({ root: makeRoot('product-type-variable') })), false, 'variable root without variations_form must bypass');

function jq() { return {}; }
jq.fn = { wc_variation_form() {} };
const completeRuntime = {
  gloskinData: { woo: true, addToCartAjaxUrl: '/?wc-ajax=add_to_cart' },
  fetch() {},
  FormData,
  jQuery: jq,
  wc_cart_fragments_params: {}
};
assert.strictEqual(hasWooAjaxBridge(completeRuntime), true, 'complete Woo AJAX/event/fragment bridge must be accepted');
assert.strictEqual(hasWooVariationRuntime(completeRuntime), true, 'Woo variation runtime must be accepted');
assert.strictEqual(hasWooAjaxBridge(Object.assign({}, completeRuntime, { jQuery: null })), false, 'AJAX must decline without jQuery event bridge');
assert.strictEqual(hasWooAjaxBridge(Object.assign({}, completeRuntime, { wc_cart_fragments_params: undefined })), false, 'AJAX must decline without Woo cart-fragments runtime');
const noVariationPlugin = Object.assign({}, completeRuntime, { jQuery: function () {} });
noVariationPlugin.jQuery.fn = {};
assert.strictEqual(hasWooVariationRuntime(noVariationPlugin), false, 'Quick Add must decline without wc_variation_form');

// Network lifecycle: Woo's native add-to-cart runtime already owns the
// fragments returned with added_to_cart, so a successful custom add must not
// unconditionally issue a second fragment refresh. Only the compatibility
// path where wc-add-to-cart itself is absent may ask wc-cart-fragments to
// reconcile once.
function eventRuntime(withNativeAddToCart) {
  const events = [];
  function eventJq(target) {
    return {
      length: target ? 1 : 0,
      trigger(name) { events.push(name); return this; },
      attr() { return this; }
    };
  }
  eventJq.fn = { wc_variation_form() {} };
  const runtime = {
    gloskinData: { woo: true, addToCartAjaxUrl: '/?wc-ajax=add_to_cart' },
    fetch() {},
    FormData,
    jQuery: eventJq,
    document: { body: {} },
    location: { href: '/before/' },
    wc_cart_fragments_params: {}
  };
  if (withNativeAddToCart) { runtime.wc_add_to_cart_params = {}; }
  return { runtime, events };
}

const nativeLifecycle = eventRuntime(true);
assert.strictEqual(hasWooNativeAddToCartRuntime(nativeLifecycle.runtime), true, 'native wc-add-to-cart runtime must be detected');
dispatchWooAddedToCart({ fragments: { '.mini': '<div></div>' }, cart_hash: 'abc' }, null, nativeLifecycle.runtime);
assert.deepStrictEqual(nativeLifecycle.events, ['added_to_cart'], 'native wc-add-to-cart success must emit added_to_cart only, without redundant fragment refresh');

const fragmentFallbackLifecycle = eventRuntime(false);
assert.strictEqual(hasWooNativeAddToCartRuntime(fragmentFallbackLifecycle.runtime), false, 'missing wc-add-to-cart runtime must be distinguishable');
dispatchWooAddedToCart({ fragments: { '.mini': '<div></div>' }, cart_hash: 'abc' }, null, fragmentFallbackLifecycle.runtime);
assert.deepStrictEqual(fragmentFallbackLifecycle.events, ['added_to_cart', 'wc_fragment_refresh'], 'fragment refresh is allowed only as the compatibility fallback when wc-add-to-cart runtime is absent');

const successLifecycle = eventRuntime(true);
const successBusyOps = [];
const successSubmitter = {
  removeAttribute(name) { successBusyOps.push(['remove', name]); }
};
handleWooAddToCartResponse({ fragments: { '.mini': '<div></div>' }, cart_hash: 'ok' }, successSubmitter, successLifecycle.runtime);
assert.deepStrictEqual(successBusyOps, [['remove', 'aria-busy']], 'successful AJAX response must clear aria-busy before Woo lifecycle dispatch');
assert.deepStrictEqual(successLifecycle.events, ['added_to_cart'], 'successful native-runtime path must dispatch Woo added_to_cart');

// Default single-product behavior preserves Woo's own error redirect convention.
const redirectLifecycle = eventRuntime(true);
const busyOps = [];
const busySubmitter = {
  removeAttribute(name) { busyOps.push(['remove', name]); },
  setAttribute(name, value) { busyOps.push(['set', name, value]); }
};
handleWooAddToCartResponse({ error: true, product_url: '/product/needs-options/' }, busySubmitter, redirectLifecycle.runtime);
assert.strictEqual(redirectLifecycle.runtime.location.href, '/product/needs-options/', 'default response.error + product_url must follow Woo product URL');
assert.deepStrictEqual(busyOps, [['remove', 'aria-busy']], 'Woo error redirect must clear aria-busy');
assert.deepStrictEqual(redirectLifecycle.events, [], 'Woo error redirect must not dispatch success events');

// Quick Add may retain context on a Woo error response instead of navigating;
// this path is non-mutating and only allows fragment reconciliation.
const quickErrorLifecycle = eventRuntime(true);
const quickBusyOps = [];
const quickBusySubmitter = { removeAttribute(name) { quickBusyOps.push(['remove', name]); } };
handleWooAddToCartResponse(
  { error: true, product_url: '/product/needs-options/' },
  quickBusySubmitter,
  quickErrorLifecycle.runtime,
  { redirectOnError: false }
);
assert.strictEqual(quickErrorLifecycle.runtime.location.href, '/before/', 'Quick Add error must keep user context instead of redirecting');
assert.deepStrictEqual(quickBusyOps, [['remove', 'aria-busy']], 'Quick Add Woo error must clear aria-busy');
assert.deepStrictEqual(quickErrorLifecycle.events, ['wc_fragment_refresh'], 'Quick Add error may only reconcile fragments non-mutatively');

// Once ajaxAddToCart has reached fetch(), no catch path may replay the same
// mutation through requestSubmit/native fallback. Pre-dispatch callers still
// retain nativeFallbackSubmit when ajaxAddToCart returns false.
const ajaxStart = coreSource.indexOf('function ajaxAddToCart(form, submitter)');
const ajaxEnd = coreSource.indexOf('/* -----------------------------------------------------------------\n\t * SP-003', ajaxStart);
assert(ajaxStart >= 0 && ajaxEnd > ajaxStart, 'ajaxAddToCart source block must be locatable');
const ajaxSource = coreSource.slice(ajaxStart, ajaxEnd);
assert(!ajaxSource.includes('nativeFallbackSubmit(form, submitter)'), 'post-dispatch AJAX failure must never replay add-to-cart through native submission');
assert(ajaxSource.includes('clearWooSubmitBusy(submitter)'), 'post-dispatch failures must clear aria-busy');
assert(ajaxSource.includes('requestWooFragmentRefresh()'), 'post-dispatch ambiguity may reconcile visible cart state non-mutatively');
assert(ajaxSource.includes('notifyFailure(null, error)'), 'post-dispatch failure must expose a recoverable lifecycle callback');
assert(coreSource.includes('if (!ajaxAddToCart(form, submitter)) {\n\t\t\t\tnativeFallbackSubmit(form, submitter);'), 'pre-dispatch payload/runtime failure must retain native fallback');

const singleStart = coreSource.indexOf('function initSingleProductAjax()');
const singleEnd = coreSource.indexOf('/* -----------------------------------------------------------------\n\t * SP-004', singleStart);
const singleSource = coreSource.slice(singleStart, singleEnd);
const bridgeGate = singleSource.indexOf('if (!shouldInterceptWooSubmit(form, submitter) || !hasWooAjaxBridge())');
const interceptPrevent = singleSource.indexOf('event.preventDefault();', bridgeGate);
assert(bridgeGate >= 0 && interceptPrevent > bridgeGate, 'unavailable Woo bridge must return before the AJAX interception preventDefault so native submission remains authoritative');
assert(singleSource.includes('if (isWooSubmitBusy(submitter))'), 'single-product AJAX must prevent accidental repeat submission while busy');

// Quick Add dispatch is no longer treated as success. The modal remains open
// until dispatchWooAddedToCart emits Woo's actual added_to_cart lifecycle;
// initCart then switches to Cart through the one existing overlay controller.
const quickStart = coreSource.indexOf('function initQuickAdd()');
const quickEnd = coreSource.indexOf('/* -----------------------------------------------------------------\n\t * Wishlist', quickStart);
assert(quickStart >= 0 && quickEnd > quickStart, 'Quick Add source block must be locatable');
const quickSource = coreSource.slice(quickStart, quickEnd);
assert(!quickSource.includes('if (ajaxAddToCart(form, submitter)) {\n\t\t\t\toverlay.close();'), 'Quick Add must not close merely because AJAX was dispatched');
assert(!quickSource.includes('overlay.close();'), 'Quick Add must not own a competing success overlay transition');
assert(quickSource.includes('redirectOnError: false'), 'Quick Add must preserve user context on Woo error response');
assert(quickSource.includes('onFailure: function (response) { renderMutationError(response); }'), 'Quick Add must render post-dispatch recovery without replay');
assert(quickSource.includes('if (isWooSubmitBusy(submitter))'), 'Quick Add must prevent accidental repeat submission while busy');
assert(quickSource.includes("open(productId, trigger.getAttribute('href') || '')"), 'Quick Add recovery must retain the triggering canonical product URL');
assert(quickSource.includes("open(relatedProductId, relatedTrigger.getAttribute('href') || '')"), 'Related Products must feed the same Quick Add recovery URL/controller');
assert(quickSource.includes('data-gloskin-quickadd-status'), 'Quick Add must expose an in-dialog recovery/status region');
assert(quickSource.includes('Lihat Produk'), 'Quick Add recovery must offer a real product-detail action when available');

const cartStart = coreSource.indexOf('function initCart()');
const cartEnd = coreSource.indexOf('/* -----------------------------------------------------------------\n\t * SP-003/SP-004', cartStart);
const cartSource = coreSource.slice(cartStart, cartEnd);
assert(cartSource.includes("on('added_to_cart'"), 'Woo actual success event must remain the transition signal');
assert(cartSource.includes("overlay.open('cart');"), 'actual Woo success must switch through the existing overlay controller');

// Both Quick Add entry paths call the same progressive runtime gate before
// preventDefault; their real href remains server-rendered by the card/related link.
const quickAddGateOccurrences = (coreSource.match(/!canOpenQuickAdd\(\)/g) || []).length;
assert.strictEqual(quickAddGateOccurrences, 2, 'catalog and Related Products Quick Add must both gate runtime before interception');
assert(coreSource.includes("if (!trigger || !canOpenQuickAdd()) { return; }"), 'catalog Quick Add must return before preventDefault when runtime is absent');
assert(coreSource.includes("if (!relatedTrigger || !canOpenQuickAdd()) { return; }"), 'Related Products Quick Add must return before preventDefault when runtime is absent');

console.log('single-product AJAX payload/runtime contract: OK');
