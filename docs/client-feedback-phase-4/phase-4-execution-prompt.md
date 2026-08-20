# Phase 4 Final Execution Prompt

## Gate

Do **not** deploy or execute Phase 4 until Phase 3 production is:

- `COMPLETE`
- final Phase-3 verifier passes
- second run is `already_complete` / no-op

At start:

```bash
git fetch --prune origin
git checkout -f main
git reset --hard origin/main
```

Work directly on `main`. Preserve all newer Phase-3 work. No branch, PR, workflow, architecture rewrite, or force push.

Use these repo references as the Phase-4 presentation authority:

- `docs/client-feedback-phase-4/phase-4-plan.md`
- `docs/client-feedback-phase-4/home-promo-wireframe.html`
- original client feedback under `docs/feedback-cases-gloskin-20260820-154828/`

Language switcher / FB-989348 is **not part of this implementation**; leave it for the final task after Phase 4.

## Goal

Low-effort / high-impact completion of the remaining client-facing work:

1. Media Cleanup safety hotfix
2. Shop AJAX selector fix
3. one canonical Shop/Skincare product card
4. Home fidelity
5. Promo fidelity + Promo legacy cleanup
6. Home Hero full-video presentation
7. About fidelity
8. Achievement/Piagam replacement + legacy cleanup
9. focused production acceptance

Do not touch Phase-3 authoritative manifests, packaged Phase-3 binaries, canonical 25/48/8 Treatment/Skincare data, or Phase-3 state.

---

# COMMIT 1 — MEDIA CLEANUP SAFETY ONLY

Target owners:

- `includes/class-gloskin-site-core-media-cleanup-resolver.php`
- Media Cleanup admin owner
- `assets/js/gloskin-ui1-media-cleanup.js`
- focused Media Cleanup tests

Fix only the two known safety defects.

### A. Candidate-specific structured references

Current structured-postmeta scan applies a global `LIMIT` before checking the candidate attachment ID. Remove that unsafe shape.

For the current candidate ID, query references **before LIMIT**:

- `_thumbnail_id`: exact attachment ID
- `_product_image_gallery`: delimiter-safe membership for the current candidate
- Gloskin image/media/gallery structured meta: candidate-bound matching only

Never restore naked broad numeric `%ID%` matching.
Never scan an arbitrary first-N global postmeta window and then inspect it in PHP.

Preserve:

- frozen scan boundary
- candidate-scoped verification
- JIT reclassification immediately before delete
- `wp_delete_attachment( $id, true )` as the only deletion owner
- current authorization/capability boundary
- no new pause/resume state machine

### B. Reload/resume during delete or verify

When Media Cleanup reloads in `deleting` or `verifying` state:

- `data-media-cleanup-delete-continue` must be visible and usable
- clicking it resumes the existing cursor
- do not restart the scan
- do not create a second worker

Do not auto-start destructive deletion without the existing explicit confirmation boundary.

Focused validation only. No production deletion.

Commit directly to main:

`Harden Media Cleanup reference safety`

---

# COMMIT 2 — SHOP AJAX + ONE RETAIL PRODUCT CARD

## A. Shop AJAX surgical selector fix

Current template owner:

`templates/pages/shop.php`

uses:

`data-gloskin-shop-catalog-owner`

Current JS initialization looks for:

`[data-gloskin-shop-catalog]`

Make JS resolve the canonical existing owner. Prefer changing the JS selector contract, not duplicating state or adding another catalog root.

Preserve existing:

- REST endpoint
- category/search/price state
- pagination hrefs
- History behavior
- SSR partial
- AJAX result replacement

Add one focused regression proving AJAX initialization finds the real Shop root and page-2 pagination can execute.

## B. Unify Shop and Skincare cards

Current divergence:

- `templates/pages/skincare.php` calls `gloskin_ui1_render_product_card( ..., 'skincare' )`
- `templates/parts/shop-results.php` calls the default renderer

End state: both surfaces use **one canonical retail card variant**.

Client target:

- centered/consistent product image ratio
- product title
- price
- primary Woo action
- equal card height/action baseline
- no rating
- no dense description
- no extra wishlist UI unless already explicitly approved

Reuse `gloskin_ui1_render_product_card()` as the single owner. Do not create a second renderer.

Update stale Phase-2 assertions that deliberately locked the old Shop/Skincare divergence.

Commit directly to main:

`Fix Shop AJAX and unify retail product cards`

---

# COMMIT 3 — PRESENTATION FIDELITY + SMALL CONTENT CLEANUP

Presentation references:

- `docs/client-feedback-phase-4/home-promo-wireframe.html`
- `docs/client-feedback-phase-4/phase-4-plan.md`
- FB-989350 Home
- FB-989352 Promo
- FB-989358 About
- FB-989362 Home Hero

Keep implementation inside existing template/helper/data owners.

## A. Home

Target structure exactly:

1. navbar
2. full-width video hero
3. simple Why Gloskin: image left + concise heading/bullets right
4. Treatment Unggulan: **6 cards**, desktop 3×2
5. Testimoni: **3 horizontal rows visible together**
6. Piagam: **exactly 4 image-only cards**
7. no closing CTA

Important Phase-3 invariant:

`gloskin_treatment_feature_on_home=1` must remain exactly **3** records.

Do **not** change that canonical count to 6.

Home visible Treatment selection:

- first the exact 3 Phase-3 featured records
- deterministically fill the remaining 3 from other published canonical `gloskin_treatment` records
- total visible = 6

No random order.

Remove current Home closing CTA and stale Achievement text UI.

## B. Home Hero / FB-989362

Extend/reuse `gloskin_ui1_render_hero()` with one presentation mode for Home only.

Home requirements:

- full video visible
- no crop
- no heading/copy/CTA overlay
- no second hero owner
- preserve safe fallback when video is unavailable
- responsive

Do not break other routes using the shared hero.

Update stale Phase-2 assertions that require campaign hero text/CTA.

## C. Promo

Reuse `gloskin_promo`; do not create another Promo CPT.

Target:

- centered `Promo Terbatas`
- first large image-led carousel
- second independent `Promo Poster` carousel
- independent controls/dots
- artwork/image is the content truth
- no duplicated text-heavy promo UI
- no page-content block below it
- no closing CTA

Use the same Promo records/data owner for both presentations; where no distinct poster owner exists, reuse the primary image instead of inventing a second content model.

## D. About / FB-989358

Rebuild `templates/pages/about.php` to the original client sketch.

Do not preserve current generic sections merely because they already exist.
Render only sections supported by the client sketch.
If Doctors / Clinics / Achievements / generic closing CTA are not present in the sketch, remove them from About presentation.

Reuse existing factual Page/founder/content owners where they fit. Do not invent business facts.

## E. Phase-4 Promo + Achievement replacement/cleanup

This is a tiny managed-content operation. **Do not build another Phase-3-sized migration framework.**

Prefer an existing migration/admin owner if compatible; otherwise add one small idempotent Phase-4 finalize action.

Required order:

replacement ready
→ bind replacement
→ verify replacement is renderable
→ Trash legacy content
→ final verify

### Promo

Create/ensure **3–6** replacement Promo records using stable deterministic Phase-4 identities.

For speed:

- use existing local landscape/editorial assets when available
- otherwise add lightweight local placeholder banner assets
- no runtime external fetch
- clearly mark records/assets as Phase-4 placeholder/demo content

After replacements are ready, Trash every obsolete `gloskin_promo` record outside the replacement set.
Do not hard-delete.
Do not delete Media Library attachments.

### Achievement / Piagam

Canonical CPT is `gloskin_achievement`.

Create/ensure exactly **4** replacement Piagam items for Home.
Use image-only presentation.
Use local placeholder images when approved certificate artwork is unavailable.
Do not show title/issuer/year/excerpt on Home.

After the 4 replacements are ready, Trash legacy `gloskin_achievement` records outside the replacement set.
Do not hard-delete.
Do not delete their Media Library attachments here.

The Phase-4 finalize action must be idempotent: a second run makes no new placeholder records and performs no new trashing.

## F. Stale contracts

Update only test assertions that conflict with the latest approved presentation:

- old Home closing CTA requirement
- old Promo closing CTA/content requirement
- old Home campaign text-overlay requirement
- old Promo thumb-selector/single-carousel assumptions
- old Shop/Skincare product-card divergence

Preserve unrelated Phase-1/2 guarantees such as navbar labels, breadcrumb removal, and CTA readability component behavior.

Commit directly to main:

`Implement Phase 4 presentation fidelity and content cleanup`

---

# VERSION

At implementation time, read the current runtime version from latest `main` after Phase 3 is finalized.
Bump exactly one patch version for the completed Phase-4 runtime changes.
Do not assume `0.7.178` is still current.

---

# FOCUSED VALIDATION

Do not run a broad unrelated repo audit.

Run only:

- `php -l` changed PHP files
- Media Cleanup focused contracts
- Shop catalog/pagination focused contract
- product-card focused contract
- Phase-1/2 client-feedback contracts after updating stale assertions
- a new compact Phase-4 presentation/cleanup contract
- `git diff --check`

Phase-4 contract must prove:

- Home has no closing CTA
- Home hero uses full-video/no-text mode
- Home renders 6 Treatment cards without changing exact-3 Phase-3 feature meta contract
- Home renders 3 testimonial rows
- Home renders exactly 4 image-only Piagam items
- Promo has two independent image-led carousel surfaces
- Promo has no legacy content/closing CTA
- About no longer renders unsupported generic legacy sections
- Shop JS resolves the canonical catalog owner
- Shop + Skincare use the same retail card owner/variant
- Media Cleanup structured references are candidate-specific before LIMIT
- delete/verify state can resume after reload
- Phase-4 Promo replacement count is 3–6
- Phase-4 Piagam replacement count is exactly 4
- legacy Promo/Achievement records are trashed only after replacements exist
- no Media Library attachment is deleted by Phase-4 content cleanup
- second Phase-4 finalize run is a no-op

---

# DO NOT DO

- Do not implement ID/EN switcher yet
- Do not touch Phase-3 manifests/data mappings/state
- Do not delete Media Library attachments during Promo/Achievement cleanup
- Do not hard-delete Promo/Achievement posts
- Do not create new CPTs/frameworks for Promo, Piagam, Shop cards, or Hero
- Do not redesign unrelated pages
- Do not add deep research/content work

---

# FINAL REPORT

```text
REMOTE MAIN SHA:
VERSION:

MEDIA CLEANUP STRUCTURED-REF FIX: PASS/FAIL
MEDIA CLEANUP RELOAD RESUME: PASS/FAIL
PRODUCTION MEDIA DELETE EXECUTED: NO

SHOP AJAX ROOT FIX: PASS/FAIL
SHOP PAGINATION REGRESSION: PASS/FAIL
SHOP/SKINCARE ONE CARD OWNER: PASS/FAIL

HOME WIREFRAME FIDELITY: PASS/FAIL
HOME TREATMENTS VISIBLE: 6/6
PHASE3 HOME FEATURE META PRESERVED: 3/3
HOME TESTIMONIAL ROWS: 3/3
HOME PIAGAM IMAGE CARDS: 4/4
HOME CLOSING CTA REMOVED: PASS/FAIL

HOME HERO FULL VIDEO / NO TEXT: PASS/FAIL

PROMO TWO CAROUSELS: PASS/FAIL
PROMO PLACEHOLDERS: N (must be 3–6)
LEGACY PROMOS TRASHED: N
PROMO LEGACY CTA/CONTENT REMOVED: PASS/FAIL

ABOUT CLIENT-SKETCH FIDELITY: PASS/FAIL

PIAGAM REPLACEMENTS: 4/4
LEGACY ACHIEVEMENTS TRASHED: N
MEDIA ATTACHMENTS DELETED BY PHASE4 CLEANUP: 0
PHASE4 FINALIZE SECOND RUN NO-OP: PASS/FAIL

STALE PHASE1/2 ASSERTIONS UPDATED: PASS/FAIL
LANGUAGE SWITCHER TOUCHED: NO

PRODUCTION PHASE4 CONTENT FINALIZE EXECUTED BY CODER: NO
```
