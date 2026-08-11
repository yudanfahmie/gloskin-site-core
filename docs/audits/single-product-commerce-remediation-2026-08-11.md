# Single Product Commerce Remediation — 2026-08-11

## Status and execution order

This document is the canonical engineer work specification for the single-product commerce issue observed on staging on 2026-08-11.

Audited repository HEAD when this specification was written:

`efa7e46651b50139f1490a92bf47bcbfcfec509a`

Do **not** implement this work until the current Plugin Check remediation work from `docs/audits/plugin-check-remediation-2026-08-11.csv` has been completed/merged. When starting this task, fetch the latest `main` again and treat the current implementation as source of truth.

This document intentionally separates two concerns:

1. **P0 correctness:** eliminate the duplicated/nested single-product render and establish one render owner.
2. **Commerce UX:** make single-product and variable-product purchasing seamless through Woo-native AJAX behavior and a small Gloskin quick-add modal, without creating a second cart or variation engine.

WooCommerce remains the sole authority for products, variations, purchasability, stock, validation, cart/session state, fragments, checkout, payment, and orders. Gloskin owns presentation and a thin interaction bridge only.

---

## Runtime evidence from staging screenshot

The captured variable-product page for **Gloskin Fresh Gel Facial Wash** shows a real rendering defect, not merely weak styling:

- the first normal single-product layout renders gallery + summary + variation form;
- the Woo Description tab then contains what appears to be a **second full single-product layout**, including gallery, thumbnails, price, variation form, SKU/category/facts;
- Woo product tabs appear again after that nested block;
- **Related products** is rendered twice;
- native Woo gallery/tabs/related-product presentation remains visually inconsistent with the Gloskin product-card/Form Kit language.

This means the page has duplicate semantic and interactive product UI in the same document. It can cause duplicate form submissions, duplicate IDs/data hooks, variation-script ambiguity, accessibility problems and major layout instability.

### Repository-side validation

At the audited HEAD, Gloskin's shell itself calls the Woo single-product entrypoint only once:

- `TemplateService` classifies product requests as `commerce-native` with `commerce_render_mode = woocommerce`;
- `templates/shell.php` calls `woocommerce_content()` once when that mode is active;
- Gloskin's Woo adapter only adds small hooks for product facts and wishlist presentation; it does not intentionally render a second gallery/summary/tabs stack.

Therefore the duplicate must be diagnosed at the actual runtime render boundary before fixing it. A likely investigation area is `the_content`/description-tab re-entry or a theme/plugin hook interaction, because Woo's product description tab passes stored product description through the normal content filter stack. **Do not assume this is the exact root cause until the runtime hook/DOM evidence proves it.**

The imported sample product description itself is normal content and is not designed to contain a full product-page embed.

---

# Findings and required remediation

## SP-001 — P0 — one single-product render owner

### Problem

A single Woo product page currently renders nested/duplicated product UI on staging.

### Required investigation

Before changing CSS or markup, reproduce on staging and record:

- number of `.product.type-product` roots;
- number of `.woocommerce-product-gallery` elements;
- number of `.summary.entry-summary` elements;
- number of `.woocommerce-tabs` elements;
- number of `.related.products` elements;
- the DOM ancestry of the second gallery/summary;
- whether the nested product lives inside `.woocommerce-Tabs-panel--description`;
- active callbacks/filter owners around product description/`the_content` and Woo single-product hooks;
- whether the problem still occurs with only WooCommerce + Gloskin active in the same staging environment, if a safe isolation test is available.

### Fix rule

Fix the **canonical owner/root cause** that causes re-entry.

Do not:

- hide the second product with CSS;
- delete Woo hooks blindly;
- suppress all `the_content` filters globally;
- fork Woo single-product templates merely to hide symptoms;
- render a separate Gloskin copy of the entire single-product template.

### Acceptance

A rendered single product has exactly one meaningful instance of:

- gallery;
- summary;
- cart/variation form;
- product tabs;
- related-products section.

Product Description contains only the stored description content expected for that product and never contains another `.product` root.

---

## SP-002 — P1 — Gloskin single-product presentation kit

### Problem

The native product page has no deliberate single-product layout owner beyond generic Woo field/button styling. Gallery, summary, tabs, meta and related products therefore still look like largely default Woo UI and do not match the premium Gloskin catalog/forms.

### Direction

Keep Woo's native semantic markup and hooks, but add a scoped Gloskin presentation layer for `body.single-product.gloskin-ui1` / the Gloskin commerce shell.

Desktop target:

- controlled max-width aligned with Gloskin containers;
- premium two-column gallery / summary layout;
- stable gap and vertical rhythm;
- summary may become softly sticky only if it does not conflict with admin bar, header, variations, validation or short viewports;
- title, price, short description, variation form, quantity, CTA, SKU/category/facts use the existing Marcellus/Mulish/Form Kit/button language;
- native notices and validation remain visible;
- no marketplace-style clutter.

Mobile target:

- one column;
- gallery first, summary second;
- touch-safe thumbnails, fields, quantity and CTA;
- no horizontal overflow;
- no sticky summary on narrow/short viewports;
- variation error/stock messages remain in normal reading order.

Do not create a second form kit or Woo-specific design system. Extend existing CSS ownership only.

---

## SP-003 — P1 — AJAX add to cart on the single product page

### Intent

A customer should not experience a full page refresh when adding an eligible product from its single-product page.

### Architecture

This must remain a **Woo-owned mutation**.

Do not add:

- `wp_ajax_gloskin_add_to_cart`;
- `wp_ajax_nopriv_gloskin_add_to_cart`;
- custom REST cart mutation endpoint;
- custom session/cart service;
- custom database/cart state.

Use WooCommerce's supported frontend add-to-cart path and its resulting fragment/event lifecycle. The existing Gloskin `initCart()` already listens to Woo's `added_to_cart` event, clears presentation busy state and opens the existing cart sheet. Reuse that bridge.

### Simple product single page

For an eligible simple product form:

1. preserve Woo form markup and validation hooks;
2. progressively intercept submit only when AJAX is available and the product remains purchasable/in stock;
3. submit through Woo's native add-to-cart mechanism;
4. on success, allow Woo to return/refresh fragments;
5. emit/use Woo's normal successful add-to-cart event lifecycle so the existing Gloskin cart badge/sheet updates;
6. restore button state correctly on failure;
7. if AJAX cannot run, normal Woo POST navigation remains the fallback.

Do not make JavaScript the only purchasing path.

### Variable product single page

The page itself already has the full variation form, so **do not open a quick modal from the single product page**. Let the customer select the variation inline, then AJAX-add the selected variation through Woo's native cart mutation path.

Never attempt to add the variable parent without a valid selected variation.

Preserve third-party/native Woo validation hooks and variation availability/stock logic.

---

## SP-004 — P1 — Gloskin variable-product quick-add modal for catalog cards

### Intent

Variable products in Shop/category/related-product cards should no longer require a full product-page navigation merely to select one simple variation such as `Ukuran`.

### Progressive-enhancement contract

Without JavaScript, a variable-product card must still link to the canonical product detail page. The modal is an enhancement, never the only path.

### Modal behavior

For a JS-enabled variable product card:

1. card CTA opens a small **Gloskin Quick Add** modal/sheet;
2. fetch/render only the minimum product purchasing projection needed for the native Woo variation form;
3. show factual product name, small/medium product image, price/variation price state, native variation attributes, stock/availability response, quantity and Add to Cart;
4. use Woo's native `variations_form` contract and the Woo variation script rather than implementing a Gloskin variation resolver;
5. selected variation ID and variation attributes must be Woo-derived;
6. CTA remains disabled/unavailable until Woo considers the selection valid;
7. successful Add to Cart is Woo-owned AJAX, then Gloskin's existing cart fragments/cart sheet lifecycle takes over;
8. close the quick-add modal before or as the cart sheet opens, with one overlay/focus owner at a time;
9. Escape/backdrop/close button/focus trap/focus return follow the existing Gloskin overlay system.

### Data-loading rule

Do not preload full variation payloads for every product card on every page if they are not needed. Prefer lazy loading when the modal is opened, while keeping the implementation small and cache-friendly.

Use a Woo/WordPress-native read path. If one small Gloskin read-only presentation endpoint is genuinely necessary for rendering the quick-add projection, it must return normalized Woo data only and must not mutate cart state. Reuse an existing endpoint/adapter capability when possible before adding another route.

### Do not

- write a custom variation availability algorithm;
- mirror Woo stock rules in JavaScript;
- mutate Woo cart through a Gloskin endpoint;
- create React/Vue or a new state framework;
- create a second global overlay controller;
- duplicate the single-product page inside the modal.

---

## SP-005 — P1 — HD single-product gallery without wasteful full-size catalog delivery

### User intent

Single-product imagery must look sharp/HD.

### Correct implementation

Interpret this as **high-resolution single-product gallery delivery**, not "force the original full image for every product image everywhere".

Do **not** set all catalog cards, thumbnails, related products, cart images and mini-cart images to WordPress `full`; that would unnecessarily inflate transfer size and hurt Core Web Vitals.

For the single-product gallery:

- preserve Woo's native gallery markup/semantics and responsive image support;
- preserve the full-size source used for zoom/lightbox;
- if staging proves the displayed main gallery is visibly under-resolved, scope the Woo gallery display-size filter to the single-product main gallery and use `full` there, while retaining responsive `srcset`/`sizes` behavior;
- thumbnails must remain appropriately sized thumbnails, not full originals;
- product cards/related/cart/mini-cart retain optimized sizes;
- never upscale a low-resolution source image and call it HD.

### Presentation

Add a deliberate gallery kit:

- consistent main-image frame;
- predictable aspect/contain strategy suitable for cosmetic packaging photography;
- restrained radius/border/background;
- thumbnail rail/grid with active state;
- native zoom/lightbox behavior preserved if enabled by Woo/theme support;
- no duplicate gallery plugin/framework.

For product packshots, avoid destructive `object-fit: cover` cropping when it cuts packaging. Prefer `contain` or natural responsive geometry where appropriate.

---

## SP-006 — P2 — product tabs and related products consolidation

Once SP-001 is fixed:

### Tabs

- keep one native Woo tabs owner;
- style Description / Additional information in the Gloskin typographic/Form Kit language;
- do not duplicate stored product information already presented in Gloskin product facts unless Woo naturally owns a separate semantic field;
- keep keyboard/focus behavior accessible.

### Related products

- exactly one section;
- visually align related products with the canonical Gloskin product-card system where this can be done without duplicating Woo product querying/state;
- if keeping native Woo related-product markup is simpler/safer, style it to visual parity rather than introducing a second related-products query owner;
- native simple products should use the existing Woo AJAX add-to-cart contract;
- variable related products should use the same quick-add enhancement and product-page fallback as other catalog cards.

Do not render a second custom related-products query merely to achieve styling.

---

## SP-007 — P1 — assets, interaction ownership and regression prevention

### Asset ownership

`Gloskin_Site_Core_Asset_Service` remains the only first-party asset owner.

Current Gloskin frontend already ensures Woo `wc-add-to-cart` and `wc-cart-fragments` when appropriate. For variable quick-add, ensure Woo's official variation script is available only where a variable variation form can actually be rendered.

Do not enqueue Woo scripts from templates.

Prefer extending `gloskin-ui1-core.js` for the small interaction bridge unless the final code becomes materially clearer with one focused file under the existing asset registry. Do not create a framework or generic commerce store.

### Overlay ownership

Quick Add must integrate with the existing single-overlay controller. Search, Auth, Wishlist, Cart and Quick Add must never simultaneously claim focus/scroll lock.

### Required regression tests

Add focused contracts, not a new testing framework.

At minimum prove:

1. Gloskin shell still has one Woo native commerce render entrypoint and does not intentionally call `woocommerce_content()` twice.
2. A single-product rendered fixture/browser run contains one gallery, one summary, one cart/variation form, one tabs section and one related-products section.
3. Description panel contains no nested `.product.type-product` root.
4. Simple single-product AJAX uses Woo-owned mutation; no public/custom Gloskin cart mutation endpoint is introduced.
5. Simple single-product has a functioning non-JS/native POST fallback.
6. Variable single-product requires a valid Woo-selected variation before AJAX add.
7. Variable catalog card keeps a canonical product-detail `href` fallback.
8. JS-enabled variable card opens the quick-add UI and uses native Woo variation-form semantics rather than a Gloskin variation resolver.
9. No selection means no variable cart mutation.
10. Successful quick-add refreshes Woo fragments and results in the existing Gloskin cart sheet/badge update lifecycle.
11. Gallery HD policy is scoped to single-product main display only; cards/thumbnails/related/cart are not globally forced to `full`.
12. Product images preserve responsive image attributes where WordPress/Woo provides them.
13. Existing wishlist, catalog discovery, cart/checkout Form Kit and sample importer contracts stay green.
14. Mobile single product has no horizontal overflow and modal controls remain touch/focus safe.

### Browser UAT

This work is not complete from static tests alone. When staging/browser tooling is available, verify:

- simple product: Add to Cart -> no page navigation -> badge/cart sheet updates;
- variable product single: choose size -> Add to Cart -> no page navigation -> correct variation appears in cart;
- variable Shop/category/related card: Quick Add opens -> choose size -> correct variation price/availability -> AJAX add -> cart sheet;
- close/reopen quick modal rapidly without focus/hidden-state corruption;
- invalid/missing variation never adds parent blindly;
- out-of-stock variation cannot be added;
- gallery main image and all thumbnails switch correctly;
- zoom/lightbox remains functional if enabled;
- exactly one Description/Additional information tabs owner;
- exactly one Related products owner;
- desktop and mobile;
- JS disabled: normal Woo purchase/navigation fallback still works.

If browser tooling/live WordPress is unavailable, report browser UAT as **SKIPPED**, never PASS.

---

# Implementation constraints

The engineer must preserve all of the following:

- WooCommerce is the commerce state authority.
- No custom cart/session database.
- No custom public add-to-cart endpoint.
- No duplicate variation engine.
- No single-product template fork unless a root-cause audit proves a narrowly scoped override is the only safe option; hooks/CSS/native template ownership are preferred.
- No CSS hiding workaround for duplicated product content.
- No global removal of `the_content` filtering.
- No global `full` image-size override.
- No React/Vue/state manager.
- No new service solely for presentation if the existing Woo adapter/AssetService/TemplateService owns the concern.
- Do not alter sample importer/bundle/fingerprint/media state for this task.
- Do not alter checkout/payment/order ownership.
- Keep developer-side SEO/GEO semantics: one product H1, one factual primary product body, stable canonical Woo product URL, crawlable product data and no duplicated hidden content.

---

# Recommended work sequence

1. **Reproduce and fix SP-001 first.** Do not build AJAX/modal behavior on top of duplicated DOM.
2. Establish the single-product Gloskin presentation kit and gallery behavior.
3. Add simple + selected-variable AJAX behavior on the single product page through Woo-owned mutation.
4. Add variable-card Quick Add as progressive enhancement using Woo's native variation form.
5. Normalize tabs/related-product presentation.
6. Add regression tests and browser UAT.
7. Run the full repository suite and fresh Plugin Check regression scan.

---

# Version and delivery

When implementation begins, read the latest plugin version from `main`; do not assume `0.7.24` is still current after Plugin Check remediation.

Because this task changes frontend PHP/JS/CSS behavior, bump exactly one patch release consistently according to repository policy:

- plugin header;
- Kernel `VERSION`;
- exact-version regression expectations.

Do not change lifecycle schema unless persistent schema/provisioning actually changes; this task should not require it.

Run:

`./tests/check-runtime.sh`

Then run a fresh WordPress Plugin Check if available, because this work follows the Plugin Check remediation phase and must not reintroduce unexplained findings.

Suggested coherent implementation commit:

`fix single product commerce experience`

Push direct fast-forward to `main` according to `CONTRIBUTING.md`.

Final engineer report must include:

- Starting HEAD
- Final HEAD
- Plugin version
- SP-001 root cause proven
- Duplicate render: PASS
- Single simple AJAX: PASS / browser SKIPPED
- Single variable AJAX: PASS / browser SKIPPED
- Variable Quick Add: PASS / browser SKIPPED
- Gallery HD/responsive policy: PASS
- One tabs owner: PASS
- One related-products owner: PASS
- Progressive fallback: PASS
- `./tests/check-runtime.sh`: PASS
- fresh Plugin Check: PASS / findings / SKIPPED
- Remote `main`

---

# Definition of done

The product journey must become:

**Catalog simple product -> Woo AJAX add -> Gloskin cart sheet**

**Catalog variable product -> Gloskin Quick Add -> Woo native variation selection -> Woo AJAX add -> Gloskin cart sheet**

**Single simple product -> Woo AJAX add -> Gloskin cart sheet**

**Single variable product -> native inline Woo variation selection -> Woo AJAX add -> Gloskin cart sheet**

while the single-product page contains exactly one product UI, one gallery, one form, one tabs section and one related-products section, with sharp responsive product imagery and no second commerce engine.

---

# 2026-08-11 hotfix verification/closure addendum

Targeted release-blocker correction on top of `df49478` (the commit that closed the sections above). This does not rewrite any finding recorded earlier in this document; it records what the hotfix pass actually verified, fixed, and could not verify.

## Real defects found and fixed in the original AJAX bridge

- **Simple-product `product_id` was silently never sent.** WooCommerce's own `single-product/add-to-cart/simple.php` template puts `name="add-to-cart" value="<id>"` on the submit **button**, not a hidden field. The original bridge only ever read `input[name="add-to-cart"]`, which does not exist for simple products, so `product_id` was never included in the AJAX payload. `WC_AJAX::add_to_cart()` calls `wp_die( 0 )` when `product_id` is absent, which is not valid JSON, so every simple-product AJAX attempt was silently failing and falling back to a native submit. Fixed: `product_id` is now derived from the real activated submitter (`event.submitter`, with a `.single_add_to_cart_button` fallback), matching Woo's own button-carries-the-id convention.
- **Variable AJAX would have posted the wrong product.** The bridge sent Woo's rendered `product_id` field as-is, which is the *parent* variable product's ID. `WC_AJAX::add_to_cart()` resolves which product to mutate purely from `product_id`, and only correctly identifies a variation add when `product_id` itself is the **variation's** post ID. Fixed: once Woo's own variation script has produced a valid `variation_id > 0`, the payload's `product_id` is overridden to that variation ID (kept alongside the native `variation_id` field). No variation is ever guessed; the value is only ever read from Woo's own computed hidden field.
- **Native fallback used `form.submit()`.** This bypasses the `submit` event entirely (no interactive validation, no other registered submit listeners), and for a simple product it could never include the button's own `name="add-to-cart"` value since `.submit()` never carries a submitter. Fixed: `nativeFallbackSubmit()` now calls `form.requestSubmit(submitter)`, which re-dispatches a genuine `submit` event carrying the real submitter, with a one-shot `data-gloskin-ajax-bypass` flag so that specific resubmission does not re-enter AJAX interception. A bare `form.submit()` remains only as the smallest possible fallback for engines without `requestSubmit()`.
- **Native Woo Related Products cards had no Quick Add bridge.** They render through Woo's own `content-product.php` loop, not the Gloskin product-card helper, so the original `[data-gloskin-quickadd-open]` delegation never matched them. Fixed: a second, narrowly scoped delegated click listener matches `body.single-product .related.products a.add_to_cart_button.product_type_variable[data-product_id]` directly (Woo's own native loop markup and `data-product_id` contract) and opens the same Quick Add modal. No second related-products query or card renderer was introduced.
- **The Quick Add public projection did not check catalog visibility.** A product explicitly marked "Search results only" or "Hidden" could still be pulled into the catalog Quick Add surface by guessing its ID. Fixed: `rest_quick_add_projection()` now also rejects products excluded from the catalog via the same Woo-native `exclude-from-catalog` visibility term the rest of the catalog projection already enforces (`is_excluded_from_catalog()`). This is a read-only consistency check, not an authentication system.

All four are proven behaviorally (not just by grep) in `tests/single-product-ajax-payload.test.js`, run via plain Node against fixtures shaped exactly like WooCommerce's real simple/variable markup -- no DOM-emulation dependency was added; the payload-construction functions are exported from `gloskin-ui1-core.js` behind a `typeof module !== 'undefined'` guard that is always dead code in a browser.

## SP-001 root cause: still not runtime-verified; guard narrowed

The canonical sample bundle's own "Gloskin Fresh Gel Facial Wash" description (`plugin/gloskin-site-core/migration-runtime/gloskin-sample-products-v1/products.json`) was inspected directly in this pass: it contains only plain `<h3>`/`<p>` HTML, no Woo block or shortcode of any kind. This confirms the original finding's own observation and means the previous **broad** content guard (stripping entire shortcode families -- `[products]`, `[product_category]`, `[product]`, any `woocommerce_*` shortcode, and eight different Woo block names) was not justified by any verified evidence and risked silently deleting legitimate editorial/cross-sell content a merchant might deliberately place in a description.

This environment has no live WordPress/WooCommerce/theme/browser runtime, so the actual staging `post_content` for the specific product in the screenshot, and the live `the_content` filter chain/callback list active on that install, could not be inspected. **SP-001 runtime root cause remains PENDING, not VERIFIED.**

The guard has been narrowed to the one mechanism that is unambiguously never legitimate regardless of the unconfirmed trigger: a product description embedding a `woocommerce/single-product` block or legacy `[product_page]` shortcode that targets **that exact same product's own ID** (true self-recursion). A single-product block/shortcode referencing a *different* product, and every other Woo catalog shortcode (`[products]`, `[product_category]`, `[product]`, `[add_to_cart]`), now passes through completely untouched. Proven behaviorally in `tests/single-product-guard-contract.php` (self-reference stripped; other-product and legitimate content preserved byte-for-byte).

If the duplicate still reproduces on staging after this deploys, the remaining candidates are genuinely external to this plugin (an active theme's own `single-product.php` override, or another active plugin double-invoking the Woo template loader) and require direct staging inspection (view-source, Query Monitor's hook list, or equivalent) that only someone with staging/wp-admin access can perform.

## `tests/check-architecture.sh` Woo-availability-gate audit

Three files contained `class_exists( 'WooCommerce' )`. Audited each:

- `class-gloskin-site-core-woocommerce-adapter.php` -- the canonical adapter; kept, unconditionally.
- `class-gloskin-site-core-asset-service.php` -- **redundant**, removed. Its per-handle `wp_script_is( '...', 'registered' )` checks are already sufficient: when WooCommerce is inactive, Woo never registers those handles, so the class-existence check added no additional safety.
- `class-gloskin-site-core-lifecycle-service.php::align_woo_shop_page()` -- **genuinely necessary, kept and documented in place.** This method runs from `admin_init` and from the static `Kernel::activate()`/`deactivate()` entrypoints WordPress calls directly; `Kernel::boot()` only ever constructs `WooCommerce_Adapter` on the non-admin frontend branch, so there is no adapter instance for this method to delegate to in either of its real call paths.

`tests/check-architecture.sh` now asserts this by name (exactly the adapter, plus at most this one documented lifecycle exception) instead of a blind count, so it is wired into `tests/check-runtime.sh` and genuinely enforces the rule rather than gaming the grep.

## Plugin Check ledger

WPPC-013 changed from ambiguous `OPEN` to `BLOCKED_OWNER/DEFERRED`, consistent with WPPC-012 -- both wait on the same owner license/distribution decision and neither is independently actionable. No license was invented; no WPPC-001..011/014/015 fix already closed was reopened or reverted.

A fresh WordPress Plugin Check scan was **not run** -- this environment has no WP-CLI, PHPCS, or Plugin Check tooling installed, and no staging access. Reported SKIPPED, not PASS.

## 2026-08-12 final hardening: SP-001 proven and fixed, purchase dock added

This pass had live staging/browser access (`https://gloskin-id.markas.cloud`, the site `.cpanel.yml` deploys to) and a local PHP 8.2 CLI (`C:\xampp\php\php.exe`) not found by the previous pass, so the previously PENDING/SKIPPED items below could actually be exercised instead of assumed.

**SP-001 root cause: proven, not narrowed further.** Live DOM inspection of `/product/fresh-gel-facial-wash/` (product #988106, SKU `GLS-SMP-002`) reproduced the reported duplication exactly (`div.product.type-product`: 2, gallery/summary/form.cart/tabs/related: 2 each, H1: 1) and captured the second `div.product`'s full DOM ancestry: it sits inside `.woocommerce-Tabs-panel--description > div.woocommerce > div.single-product > div#product-988106...` -- the literal output markup of WooCommerce's own `[product_page]` shortcode template, carrying the identical product ID. The product's own description embeds `[product_page sku="GLS-SMP-002"]`, targeting its own SKU. The guard deployed at the audited HEAD only ever matched a self-referencing numeric `id="…"` attribute, so this SKU-targeted self-reference always survived to execute -- the confirmed, evidence-backed root cause. Fixed in `guard_single_product_description_content()`/`is_self_referencing_product_page_shortcode()`: a `[product_page sku="…"]` self-reference is now also resolved through Woo's own documented `wc_get_product_id_by_sku()` and stripped before shortcode execution, exactly like the existing id-based case. Proven behaviorally in `tests/single-product-guard-contract.php` (cases G/H: self-referencing sku stripped; different-product and unresolvable sku preserved untouched).

**Purchase dock added.** `WooCommerce_Adapter::open_purchase_dock()`/`close_purchase_dock()` wrap (never clone) Woo's own `form.cart` via `woocommerce_before_add_to_cart_form`/`woocommerce_after_add_to_cart_form`, scoped to the page's own primary product only (`is_primary_single_product_context()` -- distinguishes the page's real product from a legitimate different-product `[product_page]` embed that may still be nested in its description, since both keep `is_product()`/`in_the_loop()` true throughout the same outer `the_content()` call). CSS makes the wrapper a bordered, elevated, sticky-toward-bottom surface on both desktop and mobile (height-only media gate, degrades to normal flow on short viewports, safe-area aware, never an internal scroll box). The old whole-summary `position:sticky; overflow-y:auto; max-height:…` model (SP-002) is removed; `.summary` is normal document flow.

**Commerce accent normalized.** The existing `.woocommerce`-scoped base rule already colors every Woo `.button` the Gloskin accent; that ancestor is genuinely absent for Shop/Skincare/Home product cards and the Quick Add modal, so those two contexts had an unstyled `.gloskin-ui1-button` base with no `--primary` variant. Added a scoped accent rule for exactly those two gaps, plus a neutral disabled/out-of-stock state.

**View Cart.** Woo's own `wc-add-to-cart.js` already inserts/reuses a `a.added_to_cart.wc-forward` link for catalog-loop AJAX buttons when `woocommerce_enable_ajax_add_to_cart` is on; that script never binds to a single-product `form.cart` submit at all, so nothing created one there. Added the smallest idempotent equivalent (`renderSingleProductViewCartLink()`), fired only from `ajaxAddToCart()`'s `onSuccess` callback (never on dispatch), reusing/updating the same node on repeat adds. Both variants now share one secondary-action style (`a.added_to_cart.wc-forward` in `gloskin-ui1-core.css`), never the primary accent treatment.

**Pre-existing, unrelated failure noted, not fixed:** `tests/check-presentation.sh` fails on `shop.php` no longer containing `gloskin_ui1_render_product_card` as a literal string (it now delegates to `templates/parts/shop-results.php`, introduced by the unrelated `enhance shop catalog navigation` commit prior to this pass). Confirmed via `git stash` to already fail identically on unmodified `main`. Out of this task's file scope (single-product commerce only; "do not redesign Shop"), left for a separate follow-up. `tests/shop-catalog-contract.php` also has a pre-existing PHP parse error (line 57, an unescaped array-index inside a double-quoted string) that likewise reproduces identically on unmodified `main`; same disposition.

## Verification status

| Item | Status |
|---|---|
| Simple AJAX payload contract | PASS (behavioral, `tests/single-product-ajax-payload.test.js`) |
| Variable AJAX payload contract | PASS (behavioral) |
| Native fallback semantics | PASS (behavioral + static) |
| Catalog Quick Add | PASS (static + endpoint hardening) |
| Related-product Quick Add | PASS (static bridge added) |
| SP-001 runtime root cause | **PROVEN** 2026-08-12 live on staging -- sku-targeted `[product_page]` self-reference; fixed and behaviorally proven (`tests/single-product-guard-contract.php` cases G/H) |
| Duplicate DOM counts (pre-fix) | PROVEN live 2026-08-12: 2/2/2/2/2/1 (product/gallery/summary/form/tabs/related/H1) -- see addendum above |
| `./tests/check-architecture.sh` | PASS |
| `./tests/check-runtime.sh` | PASS except two pre-existing, unrelated failures (see addendum above); not caused by this pass |
| Fresh Plugin Check | SKIPPED -- no Plugin Check tooling/staging admin access |
| Staging browser UAT (A-H) | Pre-fix SP-001 duplication PROVEN live; post-fix dock/accent/View Cart UAT reported in the final engineer report |