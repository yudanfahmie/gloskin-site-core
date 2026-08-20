# Phase 4 Final Execution Prompt — Deep-Audit Revision

Repository: `yudanfahmie/gloskin-site-core`

## MODE 0 — while Phase 3 production is still running

You MAY now:

- fetch/read latest `main`;
- inspect the Phase-4 docs and original feedback evidence;
- audit the exact source owners named below;
- prepare an implementation checklist.

You MUST NOT yet:

- modify Phase-4 runtime code;
- bump the plugin version;
- commit/push Phase-4 runtime changes;
- deploy anything;
- mutate/reset Phase-3 state.

Runtime implementation starts only after the operator explicitly confirms:

`PHASE 3 PRODUCTION ALL CLEAR`

That means production Phase 3 has:

- `COMPLETE`
- final verifier PASS
- required skips = 0
- exact 25 Skincare
- exact 48 Woo Treatment
- exact 8 informational Treatments
- exact 4 consultation paths
- exact 18 concerns
- exact 4 path-media bindings
- Treatment hero bound
- exactly 3 Home-feature informational Treatments
- zero active legacy Treatment products/posts
- usable canonical prices
- second run = `already_complete` / no-op

Do not infer this gate from source tests. The operator confirms it from production.

---

# START AFTER THE GATE

Before writing:

```bash
git fetch --prune origin
git checkout -f main
git reset --hard origin/main
```

Read current runtime version from latest `main`. Preserve all newer Phase-3 work.

Read together:

- `docs/client-feedback-phase-4/phase-4-plan.md`
- `docs/client-feedback-phase-4/home-promo-wireframe.html`
- original relevant feedback under `docs/feedback-cases-gloskin-20260820-154828/`

Specifically inspect the FB-989358 About evidence image before editing About:

`docs/feedback-cases-gloskin-20260820-154828/FB-989358-tentang-kami-page/evidence/feedback-989321-1787213320-0.png`

Language switcher / FB-989348 is NOT part of Phase 4.

Do not touch Phase-3 authoritative manifests, Phase-3 packaged media binaries, Phase-3 state, or canonical Phase-3 mappings.

---

# PUBLISHING RULE

Preferred project workflow: commit/push directly to `main` in the three sequential commits below.

If your environment has a higher-priority rule that requires a branch before committing, do NOT stop implementation. Create a temporary branch from latest `main`, implement and validate there, then fast-forward `main` to the exact tested commit if permitted. Never force-push. Do not open a PR unless the environment explicitly requires it.

A publishing restriction must not be reported as implementation `FAIL`; distinguish code/test results from the publishing blocker.

---

# COMMIT 1 — MEDIA CLEANUP SAFETY ONLY

Target owners:

- `plugin/gloskin-site-core/includes/class-gloskin-site-core-media-cleanup-resolver.php`
- `plugin/gloskin-site-core/includes/class-gloskin-site-core-media-cleanup-admin.php`
- `plugin/gloskin-site-core/assets/js/gloskin-ui1-media-cleanup.js`
- focused Media Cleanup contracts

Do not perform production deletion.

## A. Candidate-bound structured references

Current bug: structured postmeta rows are globally limited first, then candidate attachment references are checked in PHP. A valid reference can exist outside that first limited window.

Replace that unsafe query shape with candidate-bound checks BEFORE any result limit:

- `_thumbnail_id`: exact attachment-ID equality;
- `_product_image_gallery`: delimiter-safe CSV membership for the exact candidate ID;
- Gloskin image/media/gallery structured meta: candidate-bound matching only.

Never use broad naked numeric `%ID%` matching.

If an unknown structured value cannot be safely interpreted, fail conservatively to `ambiguous` / protected behavior rather than delete.

Preserve exactly:

- owner auth: `manage_options` AND exact `user_login === 'namaste'`;
- frozen scan boundary;
- sealed immutable deletion manifest;
- candidate-scoped verification;
- immediate JIT reclassification before deletion;
- `wp_delete_attachment( $id, true )` as the only attachment deletion owner;
- short bounded batches;
- no new pause/resume framework.

## B. Reload/resume delete or verify

Current admin renders `data-media-cleanup-delete-continue` in `deleting` / `verifying` but it is hidden.

When the page loads or syncs in effective state `deleting` or `verifying`:

- reveal the existing Continue control;
- clicking it resumes the server-issued existing cursor + sealed manifest;
- do not restart scan/review;
- do not create another worker;
- do not auto-start destructive deletion on page load;
- retain the original explicit backup/destructive-confirmation boundary that began deletion.

Optional trivial UI repairs are allowed only if they do not broaden the safety scope or delay this commit.

Focused tests must prove both bugs are closed.

Commit message:

`Harden Media Cleanup reference safety`

Confirm remote/tested SHA before Commit 2.

---

# COMMIT 2 — SHOP AJAX + ONE RETAIL PRODUCT CARD

## A. Shop AJAX selector contract

Canonical existing template root:

`data-gloskin-shop-catalog-owner`

Current JS owner is:

`plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js`

Do not look for or create a separate `gloskin-ui1-shop.js` owner.

Make the existing Shop controller initialize against the canonical root.

Preserve:

- existing REST endpoint;
- category/search/price state;
- SSR results partial;
- page links/hrefs;
- History push/pop behavior;
- AJAX replacement;
- loading/error semantics.

Add a focused regression proving the real Shop root initializes and page-2 pagination can execute through the existing controller.

## B. One Shop/Skincare card

Current divergence:

- Skincare explicitly requests `gloskin_ui1_render_product_card( ..., 'skincare' )`;
- Shop results request the default renderer.

End state: both use ONE canonical retail presentation variant from the existing `gloskin_ui1_render_product_card()` owner.

Target:

- consistent contained product imagery;
- product title;
- Woo price;
- primary Woo purchase/action owner;
- equal card height and action baseline;
- no rating/review UI;
- no dense short-description block;
- no extra wishlist UI unless independently approved.

Do not duplicate card markup. AJAX Shop results must inherit the same renderer through the existing server partial.

Update the stale Phase-2 contract that deliberately requires Shop/Skincare divergence.

Commit message:

`Fix Shop AJAX and unify retail product cards`

Confirm remote/tested SHA before Commit 3.

---

# COMMIT 3 — HOME / PROMO / HERO / ABOUT + SMALL CONTENT FINALIZER

Use existing template/helper/context owners. No new presentation framework.

## A. Home — exact structure

Final visible order:

1. navbar
2. full-width video hero
3. simple Why Gloskin
4. Treatment Unggulan — exactly 6 cards, desktop 3×2
5. Testimoni — exactly 3 static horizontal rows visible together
6. Piagam — exactly 4 image-only cards

Nothing else.

Explicit negative requirements:

- NO Home Promo block;
- NO secondary brand-story band;
- NO testimonial carousel arrows/dots/autoplay on Home;
- NO text-heavy Achievement cards;
- NO Home closing CTA.

The current Home template already omits Promo. Preserve that omission.

### Home Treatments

Phase-3 invariant remains exact:

`gloskin_treatment_feature_on_home=1` = 3 records.

Do not change those meta values.

Update the existing `curated_home_treatments()` owner so Home visible selection is:

- first the exact 3 published Phase-3 featured records;
- then 3 other published canonical `gloskin_treatment` records;
- no duplicates;
- deterministic stable order;
- total exactly 6.

### Home Testimonials

Reuse `gloskin_ui1_render_testimonials()` with a bounded Home/static mode or equivalent single-helper seam.

Home must render exactly 3 managed records in deterministic managed order as three static rows. Do not copy a second testimonial renderer into `home.php`.

### Home Piagam

Reuse the existing Achievement renderer with an image-only Home mode.

Render exactly 4 records, image only. No title/recognition/issuer/year/excerpt in Home cards.

## B. Home Hero / FB-989362

FB-989362 explicitly requires full video, no crop, no text.

Extend the existing `gloskin_ui1_render_hero()` with ONE Home-only presentation mode (`video-only`, `home-video`, or similarly clear name).

In this mode:

- render full-width native video;
- preserve the complete video frame / natural aspect ratio;
- do not use crop behavior such as `object-fit: cover`;
- render no eyebrow, H1, copy, CTA, campaign fade, or extra overlay controls not shown by the reference;
- preserve a safe uncropped fallback if video fails;
- remain responsive;
- do not change hero presentation on other routes.

Do not create a second hero renderer/data owner.

## C. Why Gloskin

Reuse current managed Page/Why data, but match the latest simple two-column reference:

- one primary image left;
- concise heading/bullets right;
- responsive stack;
- no extra narrative/decorative band;
- no invented statistics, guarantees, awards, or medical claims.

## D. Promo / FB-989352

Canonical owner remains `gloskin_promo` and `managed_promo_records()`.

Final `/promo/` structure:

1. centered `Promo Terbatas`;
2. large landscape image-led carousel;
3. `Promo Poster`;
4. second independent landscape/image-only carousel.

Both instances need independent local arrows/dots/state.

Reuse the existing gallery/carousel helper/controller per root. Do not create a second carousel framework.

Artwork/image is the content truth.

Remove from Promo presentation:

- text-heavy campaign card composition;
- page-content / `Informasi Promo` block;
- closing CTA;
- stale thumbnail-selector presentation.

Use the same managed Promo record collection for both surfaces. If no separate poster asset owner exists, reuse each record's featured/primary image rather than inventing another data model.

## E. About / FB-989358 — evidence first

Before changing `about.php`, inspect:

`docs/feedback-cases-gloskin-20260820-154828/FB-989358-tentang-kami-page/evidence/feedback-989321-1787213320-0.png`

In your implementation notes, record:

`ABOUT SECTION ORDER: ...`

Then implement exactly that section set/order.

Current generic About may contain Story, Founder, Vision/Mission/Values, Doctors, Clinics, Achievements, and closing CTA. Do not retain any of them merely because the context already supplies data.

Rules:

- render only sections supported by the evidence;
- reuse existing factual data owners where they match;
- do not invent business facts;
- if the sketch excludes Doctors/Clinics/Achievements/closing CTA, remove those callers from About;
- update stale tests accordingly.

If the evidence cannot be inspected, do not guess the About structure. Report that specific evidence-access blocker rather than fabricating a layout.

## F. Phase-4 managed-content finalizer

Do NOT build another Phase-3-sized migration engine.

Add/reuse one small privileged, idempotent Phase-4 finalize action.

Coder must implement/test it but MUST NOT execute the production finalize action.

Required mutation order:

replacement ready
→ media bound
→ replacement renderability verified
→ Trash legacy
→ final verify

### Promo replacement set

Create/ensure 3–6 stable Phase-4 identities.

Each replacement must:

- be published + active only when a usable landscape featured/primary image is bound;
- have deterministic order;
- use suitable local/bundled media or a lightweight local placeholder banner;
- be clearly marked as Phase-4 placeholder/demo when not client artwork.

Only after all replacements are renderable:

- Trash every active/non-trash `gloskin_promo` outside the replacement allowlist;
- never hard-delete;
- never delete Media Library attachments.

### Piagam replacement set

Create/ensure exactly 4 stable Phase-4 `gloskin_achievement` identities.

Each must have a usable image. Placeholder certificate images are acceptable when real artwork is unavailable and must be marked as placeholders.

Only after all four are renderable:

- Trash every active/non-trash `gloskin_achievement` outside the replacement allowlist;
- never hard-delete;
- never delete Media Library attachments.

Ensure replacement attachment IDs are actually bound to the active records so subsequent Media Cleanup recognizes them as referenced.

Second finalize run must create zero records and Trash zero additional records.

## G. Retire obsolete Reset Demo mutation

Current `Gloskin_Site_Core_Demo_Content_Reset` can hard-delete demo Promo/Testimonial/Achievement records and recreate the obsolete 3/3/3 dataset. The Kernel currently registers that tool.

After Phase 4 this path must not be able to resurrect obsolete Promo/Piagam data or hard-delete Phase-4 content.

Use the smallest safe approach:

- Phase 4 must not call/use Reset Demo;
- after Phase-4 finalize, stop exposing/registering its destructive mutation OR make its handler fail closed when Phase 4 is complete;
- it must not recreate old Promo/Achievement data after Phase 4;
- it must not hard-delete Phase-4 replacement posts;
- do not replace it with a new broad reset framework.

Do not rework the separate historical Promo recovery unless a focused test shows it conflicts with Phase 4.

## H. Stale test contracts

Update only assertions invalidated by the newest client references.

Known stale assertions currently include:

- Phase 1: exactly one closing CTA caller in Home / Promo / About;
- Phase 2: Home campaign text/CTA must remain and `video-only` is forbidden;
- Phase 2: Home closing CTA required;
- Phase 2: Shop and Skincare must use different card variants;
- Phase 2: old Promo single-carousel / thumb-selector composition;
- Phase 2 version assertion may still name an old runtime version.

Preserve:

- navbar-label contract;
- breadcrumb suppression;
- closing-CTA component readability itself;
- Woo purchase semantics;
- canonical managed content owners;
- unrelated Phase-1/2 behavior.

Commit message:

`Implement Phase 4 presentation fidelity and content cleanup`

---

# VERSION

Read the current runtime version from latest `main` after Phase-3 production all-clear.

Bump exactly one patch version for the completed Phase-4 runtime implementation, synchronized across canonical version owners/contracts.

Do not hard-code an expected Phase-4 version in advance.

---

# FOCUSED VALIDATION

Do not run a broad unrelated repo audit.

Run:

- `php -l` for changed PHP files;
- focused Media Cleanup contracts;
- Shop/AJAX pagination contract;
- retail product-card contract;
- Phase-1/2 feedback contracts after intentional stale assertion updates;
- Phase-3 contracts that guard preserved invariants, without modifying Phase-3 source/manifests;
- new compact Phase-4 fidelity/finalize contract;
- `git diff --check`.

The Phase-4 contract must prove:

1. Home exact section order;
2. Home has no Promo, extra brand-story band, or closing CTA;
3. Home hero is full-video/no-text with uncropped media behavior;
4. Home renders exactly 6 Treatment cards while exact-3 Phase-3 Home-feature meta remains untouched;
5. Home renders exactly 3 static testimonial rows and no Home carousel controls;
6. Home renders exactly 4 image-only Piagam cards;
7. Promo renders exactly two independent image-led carousel/gallery roots;
8. Promo has no legacy campaign/page-content/CTA/thumb-selector presentation;
9. About implementation records and matches the exact FB-989358 evidence-derived section order;
10. Shop JS initializes against `data-gloskin-shop-catalog-owner`;
11. page-2 Shop AJAX pagination remains executable;
12. Shop + Skincare use the same retail card variant/owner;
13. Media Cleanup structured refs are candidate-bound before result limiting;
14. Media Cleanup deleting/verifying reload exposes resumable Continue without auto-deleting;
15. Phase-4 Promo replacements = 3–6 and all have usable image bindings;
16. Phase-4 Piagam replacements = exactly 4 and all have usable image bindings;
17. legacy Promo/Achievement records are Trashed only after replacement readiness;
18. Phase-4 finalizer hard-deletes zero Promo/Achievement posts;
19. Phase-4 finalizer deletes zero Media Library attachments;
20. obsolete Reset Demo cannot mutate/resurrect Phase-4 content after finalize;
21. second Phase-4 finalize run is a no-op;
22. Phase-3 manifests/state/media binaries remain untouched.

---

# DO NOT DO

- do not implement ID/EN switcher yet;
- do not reset/restart Phase 3;
- do not touch authoritative Phase-3 manifests/data mappings/media binaries;
- do not perform production Media Cleanup deletion;
- do not execute production Phase-4 content finalize as coder;
- do not hard-delete Promo/Achievement records;
- do not delete Media Library attachments from Phase-4 finalizer;
- do not create new CPTs for Promo/Piagam;
- do not create a second Shop controller, product-card renderer, hero renderer, testimonial framework, or Promo carousel framework;
- do not add runtime external media fetches;
- do not redesign unrelated pages;
- do not implement deep content research.

---

# FINAL REPORT

```text
BASE MAIN SHA:
FINAL TESTED SHA:
PUBLISHED TO:
VERSION:

PHASE3 SOURCE TOUCHED: NO
PHASE3 PRODUCTION STATE TOUCHED BY CODER: NO

MEDIA CLEANUP CANDIDATE-BOUND REFS: PASS/FAIL
MEDIA CLEANUP RELOAD RESUME: PASS/FAIL
PRODUCTION MEDIA DELETE EXECUTED: NO

SHOP AJAX CANONICAL ROOT: PASS/FAIL
SHOP PAGE-2 AJAX REGRESSION: PASS/FAIL
SHOP/SKINCARE ONE RETAIL CARD: PASS/FAIL

HOME EXACT ORDER: PASS/FAIL
HOME PROMO ABSENT: PASS/FAIL
HOME EXTRA BRAND STORY ABSENT: PASS/FAIL
HOME HERO FULL VIDEO / NO TEXT / NO CROP: PASS/FAIL
HOME TREATMENTS VISIBLE: 6/6
PHASE3 HOME FEATURE META PRESERVED: 3/3
HOME TESTIMONIAL STATIC ROWS: 3/3
HOME PIAGAM IMAGE-ONLY CARDS: 4/4
HOME CLOSING CTA ABSENT: PASS/FAIL

PROMO TWO INDEPENDENT CAROUSELS: PASS/FAIL
PROMO LEGACY CAMPAIGN/CONTENT/CTA/THUMBS ABSENT: PASS/FAIL
PROMO REPLACEMENTS: N (must be 3–6)
LEGACY PROMOS TRASHED BY TEST/FINALIZER LOGIC: N

ABOUT SECTION ORDER:
ABOUT CLIENT-EVIDENCE FIDELITY: PASS/FAIL

PIAGAM REPLACEMENTS: 4/4
LEGACY ACHIEVEMENTS TRASHED BY TEST/FINALIZER LOGIC: N
PHASE4 FINALIZER HARD-DELETES POSTS: 0
PHASE4 FINALIZER DELETES MEDIA ATTACHMENTS: 0
PHASE4 FINALIZE SECOND RUN NO-OP: PASS/FAIL

OBSOLETE RESET DEMO RETIRED AFTER PHASE4: PASS/FAIL
STALE PHASE1/2 ASSERTIONS UPDATED: PASS/FAIL
PHASE3 PRESERVATION CONTRACTS: PASS/FAIL
LANGUAGE SWITCHER TOUCHED: NO

PRODUCTION PHASE4 CONTENT FINALIZE EXECUTED BY CODER: NO
```

If publishing is blocked after implementation/tests, additionally report:

```text
BRANCH:
BRANCH SHA:
EXACT PUBLISHING BLOCKER:
```
