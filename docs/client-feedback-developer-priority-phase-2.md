# Client Feedback — Developer Execution Handoff (Phase 2)

**Repository:** `yudanfahmie/gloskin-site-core`  
**Target implementation branch:** `phase2-client-feedback-20260820`  
**Prepared against:** `main` at `cd99cd77cef39505d2d9059d65043a5efe97f7db` before this documentation commit  
**Phase 2 scope:** `FB-989356`, `FB-989350`, `FB-989352` only  
**Raw feedback/evidence:** `docs/feedback-cases-gloskin-20260820-154828/` — read-only

> This is a recovery/execution brief for an interrupted AI-developer run. It converts the investigation into a deterministic implementation path and explicitly prevents unrelated baseline CI failures from blocking Phase 2 completion.

## 1. Current repository state

Phase 1 is already implemented on `main` and must be treated as protected behavior:

- **FB-989346** — persistent navbar labels: `Treatment`, `Promo`, `Skincare`, `Tentang Kami`
- **FB-989369** — visible Gloskin breadcrumbs removed globally while Woo breadcrumb suppression remains
- **FB-989364** — closing CTA secondary action contrast restored

The branch `phase2-client-feedback-20260820` exists and was still pointing at the Phase-1 baseline when this handoff was prepared.

A temporary branch named `phase2-preflight-20260820` also exists. **Do not treat it as the Phase-2 implementation source.** Its committed changes are temporary workflow/artifact plumbing used during the interrupted investigation, not the final client implementation.

## 2. Known baseline-red policy — do not get blocked

The canonical harness was invoked before Phase 2 and the current baseline already contains unrelated failures. The interrupted run reported:

- architecture-contract direct `$wpdb` ownership violations in existing files:
  - `revision-20260820-promo-recovery.php`
  - `media-cleanup-resolver.php`
  - `diagnostic-exporter.php`
- a stale CI/preflight version assertion expecting `0.7.159` while the runtime baseline is `0.7.170`

These are **not Phase-2 scope**.

### Required test policy

1. Before editing, run the canonical harness once and capture the exact baseline failures.
2. Confirm `php tests/phase1-client-feedback-contract.php` passes.
3. Do not edit unrelated architecture/runtime files just to turn the whole repository green.
4. Implement Phase 2.
5. Run focused Phase-2 + Phase-1 regression tests.
6. Run the canonical harness again.
7. Phase 2 may be reported as implementation-complete when:
   - all Phase-2 focused tests pass,
   - Phase-1 regression passes,
   - the post-change canonical failures are the **same pre-existing baseline failures** and no new failures are introduced.

Do not claim "full CI green" if the unrelated baseline remains red. Report it as a known pre-existing baseline condition.

## 3. Phase 2 reverse-engineered target

### A. FB-989356 — Skincare product card

**Visual authority:**

`docs/feedback-cases-gloskin-20260820-154828/FB-989356-skincare-page/evidence/product skincare.jpeg`

Also inspect the original ticket screenshot in that evidence directory.

**Observed target:** sparse product-first card, contained packshot, compact product title/price, outlined purchase action. The client explicitly requested that the review/rating region from the visual reference **not** be implemented.

**Current canonical owners:**

- page: `plugin/gloskin-site-core/templates/pages/skincare.php`
- renderer: `plugin/gloskin-site-core/templates/parts/template-helpers.php`
- existing function: `gloskin_ui1_render_product_card( $product, $variant = 'catalog' )`

**Implementation contract:**

- Extend the existing renderer's variant seam with a `skincare` presentation variant.
- Call that variant from the Skincare product grid only.
- Do **not** create a second product-card renderer.
- Keep WooCommerce as the sole product/commerce data and behavior owner.
- Preserve Woo product ID, permalink, featured image, title, price HTML, purchasable/in-stock state, product type, native add-to-cart contract, AJAX support, and variable-product Quick Add where currently supported.
- The Skincare presentation may omit description/wishlist when required to match the supplied reference.
- Deliberately render **no stars, rating average, review count, review placeholder, or fake social proof**.
- Never use evidence images as runtime product media.

**Do not change Shop/Home product-card presentation unless a truly shared harmless primitive must be corrected.**

### B. FB-989350 — Home structure

**Visual authority:**

`docs/feedback-cases-gloskin-20260820-154828/FB-989350-home-page/evidence/home.jpeg`

Also inspect the original ticket screenshot in that evidence directory.

**Reverse-engineered structural mapping from the interrupted run:**

`Hero → Why Gloskin → Treatment Unggulan → Testimoni → Piagam`

The current Home additionally places managed Promo, combined Skincare/Product discovery, and Home brand-story sections in the sequence. The task is **structural convergence**, not a new data model.

**Current canonical owners:**

- page composition: `plugin/gloskin-site-core/templates/pages/home.php`
- context/data: `Gloskin_Site_Core_Template_Service::home_context()`
- shared helpers: existing Hero, Why, card, Promo, testimonial, achievement, and closing-CTA renderers

**Implementation contract:**

- Independently verify the supplied `home.jpeg` before coding.
- Recompose/reorder existing owners to match the reference.
- Reuse existing curated Treatments, testimonials, achievements, and existing factual/editorial owners.
- Do not create duplicate queries, duplicate arrays, or another Home service.
- Do not fabricate content to fill visual slots.
- Preserve one Home hero only.
- Keep the Phase-1 closing CTA behavior protected; if the final reference sequence ends before it, the existing closing CTA may remain immediately after the reference sequence unless direct visual inspection clearly requires a different presentation-only placement.
- **Do not implement FB-989362 here.** The Home hero must not be converted to the deferred full-video/no-text/no-crop variant in this phase.
- If language flags appear in the Home design, **do not implement FB-989348** in this phase.

### C. FB-989352 — Promo page structure

**Visual authority:**

`docs/feedback-cases-gloskin-20260820-154828/FB-989352-promo-page/evidence/promo.jpeg`

Also inspect the original ticket screenshot in that evidence directory.

**Reverse-engineered target from the interrupted run:**

- a light **Promo Terbatas** featured-campaign composition
- followed by a larger **Promo** poster-selection area

**Current canonical owners:**

- page: `plugin/gloskin-site-core/templates/pages/promo.php`
- context: `Gloskin_Site_Core_Template_Service::promo_context()`
- campaign source: managed `gloskin_promo` records
- shared renderer: `gloskin_ui1_render_managed_promo_carousel()` with existing page/compact distinction

**Implementation contract:**

- Extend/recompose the existing **page** presentation; do not add another Promo query or renderer owner.
- Use the same managed `gloskin_promo` records for featured campaign and poster/selector presentation.
- Keep Home's compact Promo variant isolated and unchanged unless a shared primitive genuinely needs a harmless correction.
- Preserve zero/single/multiple-record states, configured order/date eligibility, keyboard behavior, reduced-motion behavior, and current selector synchronization where applicable.
- Do not hard-code screenshot promotions, prices, dates, terms, discounts, or other facts.
- Editor data may be hidden/repositioned by presentation but must not be deleted from WordPress.

## 4. Phase 2 non-goals

Do not implement or partially implement:

- **FB-989348** multilingual ID/EN
- **FB-989354** product catalog reconstruction/migration
- **FB-989358** About structure
- **FB-989360** Treatment taxonomy/data/media migration
- **FB-989362** Home full-video/no-text/no-crop hero

No data migration belongs in Phase 2.

## 5. Architecture guardrails

- Extend existing canonical owners only.
- No new frontend framework or SPA layer.
- No duplicate data provider/controller/renderer.
- No global fetch/history monkeypatch.
- No third-party frontend dependency.
- No runtime DOM repair for server-owned state.
- No inline-style workaround.
- No `!important`.
- Do not modify raw evidence.
- Keep accessibility, keyboard behavior, reduced motion, and responsive behavior intact.
- Follow the repository's existing release/cache-busting contract once for the runtime presentation batch if a version bump is required.

## 6. Focused acceptance tests

### FB-989356

- `/skincare/` explicitly requests the Skincare product-card variant.
- The same canonical product renderer owns both catalog and Skincare variants.
- No review/rating/star markup is emitted by the Skincare variant.
- Woo product attributes/actions remain wired.
- Existing Skincare chip filtering continues to work.
- Shop/Home catalog cards do not regress.

### FB-989350

- Home section order matches the verified design mapping.
- Exactly one Home hero exists.
- Canonical Treatment/testimonial/achievement owners remain in use.
- No multilingual or video-only hero implementation appears.
- Phase-1 closing CTA remains readable.

### FB-989352

- Promo page still consumes managed `gloskin_promo` records.
- Page-specific presentation is isolated from Home compact presentation.
- Empty/single/multiple paths remain valid.
- Existing promo date/order/selector/interaction contracts continue to pass.

### Phase 1 regression

`php tests/phase1-client-feedback-contract.php` must pass before and after Phase 2.

## 7. Responsive/browser verification

Static contracts are necessary but not sufficient for these visual tickets.

If a browser is available, compare the implementation directly against the supplied local references at approximately:

- 390px
- 768px
- 1024px
- 1440px

Check especially:

- product-card image containment, text density, price/action alignment, overflow
- Home section order and spacing
- Promo featured/selection hierarchy
- no Phase-1 CTA/nav/breadcrumb regression

If browser comparison is unavailable, state that explicitly. Do not claim pixel-perfect visual parity from static tests alone.

## 8. Repository-write completion contract

This is the part the previous AI run did not finish.

1. Work directly on `phase2-client-feedback-20260820`.
2. Re-implement from the current branch contents; do not depend on an uncommitted local diff from a previous session.
3. Do not use `phase2-preflight-20260820` as the implementation branch.
4. Commit all legitimate Phase-2 runtime/test/version changes to `phase2-client-feedback-20260820`.
5. Push/populate that branch in the repository.
6. Review the branch diff against `main`.
7. Ensure the diff contains only:
   - FB-989356 implementation
   - FB-989350 implementation
   - FB-989352 implementation
   - focused tests
   - required version/cache-busting changes
8. Stop after the implementation branch is populated and reviewed. Do **not** merge to `main` unless explicitly instructed.

Temporary preflight workflow/branch cleanup is not a prerequisite for Phase 2 implementation. It can be handled separately after the real implementation branch is safely populated.

## 9. Final report format

Report:

1. implementation commit SHA(s) on `phase2-client-feedback-20260820`
2. reverse-engineered mapping actually used for each reference
3. exact runtime/test files changed
4. root mismatch fixed for each ticket
5. confirmation that FB-989356 contains no review/rating UI
6. confirmation that Home/Promo canonical owners were reused
7. focused tests + results
8. Phase-1 regression result
9. canonical harness result, clearly separating pre-existing baseline failures from any new failures
10. browser widths checked, or explicit statement that browser validation was unavailable
11. confirmation that deferred tickets were not touched

**Definition of done:** the Phase-2 branch contains the implementation and tests, focused + Phase-1 regressions pass, no new canonical-harness failures are introduced beyond documented baseline-red items, and the final diff is scoped to these three tickets.