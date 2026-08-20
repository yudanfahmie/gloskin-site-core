# Phase 4 — Final Execution Prompt

Repository: `yudanfahmie/gloskin-site-core`

## Gate

Do not write Phase-4 runtime until the operator confirms:

`PHASE 3 PRODUCTION ALL CLEAR`

Required production evidence:

- Phase 3 status `COMPLETE`
- final verifier PASS
- required skips = 0
- exact 25 Skincare / 48 Woo Treatment / 8 informational Treatments / 4 paths / 18 concerns
- 4/4 path-media bindings + Treatment hero bound
- exactly 3 `gloskin_treatment_feature_on_home=1`
- zero active legacy Treatment products/posts
- canonical prices usable
- second Phase-3 run = `already_complete` / no-op

Phase 3 is closed after this gate. Do not reopen or redesign it.

## Start

```bash
git fetch --prune origin
git checkout -f main
git reset --hard origin/main
```

Read together before writing:

- `docs/client-feedback-phase-4/phase-4-plan.md`
- `docs/client-feedback-phase-4/home-promo-wireframe.html`
- `docs/client-feedback-phase-4/phase-4-woo-taxonomy-addendum.md`
- `docs/client-feedback-phase-4/phase-4-woo-taxonomy-mapping.md`
- relevant original FB evidence under `docs/feedback-cases-gloskin-20260820-154828/`

For About, inspect this image before editing:

`docs/feedback-cases-gloskin-20260820-154828/FB-989358-tentang-kami-page/evidence/feedback-989321-1787213320-0.png`

Language switcher / FB-989348 is explicitly deferred until after Phase 4.

## Operating principle

Move fast and stay surgical:

- reuse existing owners;
- no architecture rewrite;
- no new CPTs/frameworks/controllers when an owner already exists;
- no deep research;
- no broad repo audit;
- only focused tests after each commit;
- preserve all Phase-3 manifests/state/media binaries and canonical mappings byte-for-byte.

Preferred workflow is three small sequential commits directly to `main`. If the environment forces a temporary branch, implement/test there and fast-forward `main` when permitted; never force-push and do not stop coding merely because direct-main publishing is blocked.

---

# COMMIT 1 — Media Cleanup safety

Target only existing Media Cleanup owners.

Fix these two defects:

### 1. Candidate-bound structured references

Current unsafe shape globally LIMITs postmeta rows before checking the current attachment.

Replace with candidate-specific checks before any result limit:

- `_thumbnail_id` = exact candidate ID;
- `_product_image_gallery` = delimiter-safe CSV membership;
- Gloskin image/media/gallery structured meta = candidate-bound matching only.

Never use naked `%ID%` numeric LIKE matching.
If an unknown structure cannot be interpreted safely, classify/protect conservatively rather than delete.

Preserve:

- exact owner auth: `manage_options` AND `user_login === 'namaste'`;
- frozen cohort;
- sealed manifest;
- JIT reclassification immediately before delete;
- candidate-scoped verify;
- `wp_delete_attachment( $id, true )` as the only deletion owner.

### 2. Reload/resume

If page reloads in `deleting` or `verifying`:

- show the existing Continue control;
- resume existing server cursor/manifest;
- never restart scan;
- never create a second worker;
- never auto-start destructive deletion on page load.

Do not perform production media deletion.

Focused validation only.

Commit:

`Harden Media Cleanup reference safety`

---

# COMMIT 2 — Shop AJAX + one retail card

### 1. Shop AJAX

Canonical template root is:

`data-gloskin-shop-catalog-owner`

Current controller owner is:

`plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js`

Make the existing controller initialize against the real root.

Preserve current REST endpoint, search/category/price state, SSR partial, pagination hrefs, History behavior, AJAX replacement, loading/error behavior.

Prove page-2 AJAX works.

### 2. Product card

Shop and Skincare must use the same existing `gloskin_ui1_render_product_card()` retail presentation.

Target:

- contained/consistent product image;
- title;
- Woo price;
- canonical Woo primary action;
- equal card/action alignment;
- no rating/review block;
- no dense description;
- no duplicate renderer.

Update only stale Phase-2 assertions that intentionally locked the old divergence.

Commit:

`Fix Shop AJAX and unify retail product cards`

---

# COMMIT 3 — Presentation + small idempotent finalizer

Use existing template/helper/context/data owners.

## A. Home

Exact visible structure:

1. navbar
2. full-width Home video hero
3. simple Why Gloskin
4. Treatment Unggulan — exactly 6 cards, desktop 3×2
5. Testimoni — exactly 3 static horizontal rows
6. Piagam — exactly 4 image-only cards

Nothing else.

Explicitly absent:

- Home Promo
- extra brand-story band
- testimonial slider arrows/dots/autoplay on Home
- text-heavy Achievement cards
- closing CTA

### Home Treatment selection

Do not change Phase-3 feature meta.

Keep exactly 3 records with `gloskin_treatment_feature_on_home=1`.

Existing Home selection owner should return:

- those exact 3 first;
- then deterministic 3 other published canonical `gloskin_treatment` records;
- no duplicates;
- total = 6.

### Hero / FB-989362

Extend the shared hero with one Home-only video mode:

- full-width native video;
- complete frame visible / no crop;
- no eyebrow, heading, copy, CTA, fade, or text overlay;
- responsive;
- safe uncropped fallback;
- other route heroes unchanged.

Do not create another hero owner.

### Why Gloskin

Keep existing factual Page/Why owner, simplify presentation to:

- image left;
- concise heading/bullets right;
- responsive stack;
- no invented facts/statistics/claims.

## B. Promo

Reuse `gloskin_promo` + `managed_promo_records()`.

Exact structure:

1. centered `Promo Terbatas`
2. large landscape image-led carousel
3. `Promo Poster`
4. second independent landscape/image-only carousel

Both carousel roots have independent arrows/dots/state but reuse the existing carousel/controller owner.

Remove:

- text-heavy campaign card composition;
- page-content/Informasi Promo block;
- closing CTA;
- stale thumbnail-selector presentation.

Use the same managed Promo collection for both surfaces. Reuse primary/featured image if no separate poster owner exists.

## C. About / FB-989358

Inspect the original evidence image first and record:

`ABOUT SECTION ORDER: ...`

Then render exactly that section set/order.

Do not retain current Story/Founder/Vision-Mission/Doctors/Clinics/Achievements/CTA merely because data exists. Keep only what the evidence supports. Reuse factual existing owners where applicable; invent nothing.

If evidence cannot be inspected, stop only the About portion and report that exact blocker; do not guess.

## D. One small Phase-4 finalizer

Do not build another migration engine.

Implement one privileged, idempotent finalizer. Coder implements/tests it but does **not** execute it in production.

Mutation order:

replacement ready → media/category bound → verify → Trash legacy → final verify → complete

### Promo content

- ensure 3–6 stable Phase-4 Promo identities;
- use local/bundled landscape placeholder images when client artwork is unavailable;
- publish/activate only after usable image binding;
- deterministic order;
- clearly mark placeholders;
- after all replacements verify, Trash obsolete `gloskin_promo` records;
- never hard-delete posts;
- never delete Media Library attachments.

### Piagam

- ensure exactly 4 stable Phase-4 `gloskin_achievement` identities;
- each must have a usable image;
- placeholder certificate images are allowed and must be marked;
- Home renders image only;
- after all 4 verify, Trash obsolete Achievement records;
- never hard-delete posts or media.

Second finalizer run must create/trash/mutate nothing.

### Woo native category alignment

`docs/client-feedback-phase-4/phase-4-woo-taxonomy-mapping.md` is authoritative.

Apply only to canonical Phase-3 Woo products:

- exact 25 Skincare → exact native `product_cat` mapping from the file;
- all exact 48 Treatment → ensure `product_cat=perawatan`;
- `support` → `produk-penunjang`;
- preserve legitimate additional categories;
- remove `uncategorized` only after a valid canonical category is attached;
- never mutate unrelated products;
- never alter `gloskin_product_family`, `gloskin_concern`, Phase-3 state, manifests, prices, media, provenance, or stock policy.

Do **not** create Woo categories from Treatment groups/concerns.
Do **not** hide Woo Categories in wp-admin.

Ensure the existing Shop/category path can reach/filter `Perawatan` without adding a second filter-state owner.

Expected final taxonomy acceptance:

- Skincare aligned: 25/25
- Treatment with `perawatan`: 48/48
- canonical products only `Uncategorized`: 0
- unrelated products mutated: 0

### Retire obsolete Reset Demo mutation

Current Reset Demo can recreate obsolete Promo/Achievement data and hard-delete demo records.

Use the smallest safe fix so after Phase-4 completion it cannot mutate/resurrect Phase-4 Promo/Piagam or hard-delete the replacement set. Do not replace it with another reset framework.

## E. Stale contracts

Update only assertions invalidated by the latest client references.

Known stale areas:

- Home/Promo/About closing CTA expectations;
- Home campaign text hero / prohibition of video-only mode;
- old Shop/Skincare card divergence;
- old Promo single-carousel/thumb assumptions;
- stale runtime-version assertion.

Preserve unrelated Phase-1/2 behavior: navbar labels, breadcrumb suppression, CTA component readability, Woo purchase semantics, canonical managed owners.

Commit:

`Implement Phase 4 presentation fidelity and content cleanup`

---

# Version

Read the current runtime version from latest `main` at implementation time.
Bump exactly one patch version for the completed Phase-4 runtime changes and keep canonical version owners synchronized.

---

# Focused validation

Run only:

- `php -l` changed PHP;
- focused Media Cleanup contract;
- Shop AJAX/pagination contract;
- retail-card contract;
- updated Phase-1/2 feedback contracts;
- Phase-3 preservation contracts without changing Phase-3 source/manifests;
- one compact Phase-4 fidelity/finalizer contract;
- `git diff --check`.

Phase-4 contract must prove at minimum:

- Home exact order, 6 Treatments, 3 static testimonials, 4 image-only Piagam, no Promo/closing CTA;
- exact-3 Phase-3 Home feature meta preserved;
- Home hero full-video/no-text/no-crop;
- Promo has two independent image-led carousel roots and no legacy content/CTA/thumb UI;
- About order matches FB-989358 evidence;
- Shop controller uses canonical root and page-2 AJAX works;
- Shop + Skincare share one card presentation owner;
- Media Cleanup candidate-bound reference safety + reload resume;
- Promo replacements 3–6 with images;
- Piagam replacements exactly 4 with images;
- legacy Promo/Achievement Trash happens only after replacement readiness;
- hard-deleted Promo/Achievement posts = 0;
- media attachments deleted by Phase-4 finalizer = 0;
- Woo mapping = 25/25 Skincare + 48/48 Treatment Perawatan + 0 canonical-only Uncategorized;
- unrelated Woo mutations = 0;
- Reset Demo cannot resurrect Phase-4 content after finalize;
- second finalizer run = no-op;
- Phase-3 manifests/state/media binaries untouched.

---

# Hard boundaries

Do not:

- implement ID/EN switcher yet;
- restart/reset Phase 3;
- modify Phase-3 authoritative manifests/mappings/state/media binaries;
- perform production Media Cleanup deletion;
- execute production Phase-4 finalizer as coder;
- hard-delete Promo/Achievement posts;
- delete Media Library attachments from Phase-4 finalizer;
- create new Promo/Piagam CPTs;
- create second Shop/product-card/hero/testimonial/carousel frameworks;
- add runtime external media fetches;
- redesign unrelated pages;
- perform deep content research.

---

# Final report

```text
BASE MAIN SHA:
FINAL TESTED SHA:
PUBLISHED TO:
VERSION:

MEDIA CLEANUP REFS: PASS/FAIL
MEDIA CLEANUP RELOAD RESUME: PASS/FAIL
PRODUCTION MEDIA DELETE: NO

SHOP AJAX ROOT/PAGE2: PASS/FAIL
ONE SHOP/SKINCARE CARD: PASS/FAIL

HOME FIDELITY: PASS/FAIL
HOME TREATMENTS: 6/6
PHASE3 HOME FEATURE META: 3/3
HOME TESTIMONIALS: 3/3
HOME PIAGAM: 4/4
HOME HERO FULL VIDEO/NO TEXT/NO CROP: PASS/FAIL

PROMO TWO CAROUSELS: PASS/FAIL
PROMO REPLACEMENTS: N (3–6)
LEGACY PROMOS TRASHED: N

ABOUT SECTION ORDER:
ABOUT FIDELITY: PASS/FAIL

PIAGAM REPLACEMENTS: 4/4
LEGACY ACHIEVEMENTS TRASHED: N

SKINCARE PRODUCT_CAT: 25/25
TREATMENT PRODUCT_CAT=PERAWATAN: 48/48
CANONICAL ONLY UNCATEGORIZED: 0
UNRELATED WOO PRODUCTS MUTATED: 0
PHASE3 PRIVATE TAXONOMIES PRESERVED: PASS/FAIL

PHASE4 FINALIZER HARD-DELETES POSTS: 0
PHASE4 FINALIZER DELETES MEDIA: 0
PHASE4 SECOND RUN NO-OP: PASS/FAIL
RESET DEMO RETIRED: PASS/FAIL
PHASE3 PRESERVATION CONTRACTS: PASS/FAIL
LANGUAGE SWITCHER TOUCHED: NO
PRODUCTION PHASE4 FINALIZER EXECUTED BY CODER: NO
```

If publishing is blocked after implementation/tests, additionally report the temporary branch, branch SHA, and exact publishing blocker. Do not mark completed implementation checks as FAIL merely because direct-main publishing was unavailable.
