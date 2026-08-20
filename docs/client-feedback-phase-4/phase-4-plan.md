# Gloskin Phase 4 — Final Presentation, Cleanup, and Safety Plan

## Execution gate

Phase 4 may be **audited and prepared** while Phase 3 is running in production, but Phase-4 runtime code must not be merged/deployed until Phase 3 production is confirmed:

- `COMPLETE`
- final Phase-3 verifier passes
- required skips = 0
- exact 25 Skincare Woo products
- exact 48 Treatment Woo products
- exact 8 informational `gloskin_treatment` records
- exact 4 consultation paths
- exact 18 concerns
- exact 4 consultation-path media bindings
- Treatment hero bound
- exactly 3 `gloskin_treatment_feature_on_home=1`
- zero active legacy Treatment products/posts
- canonical prices valid
- second Phase-3 run is `already_complete` / no-op

Do not reset or mutate Phase-3 state from Phase 4.

---

# Presentation authority

For Home and Promo, the latest authority is:

1. `docs/client-feedback-phase-4/home-promo-wireframe.html`
2. the original client-feedback evidence for FB-989350 / FB-989352 / FB-989362

For About, the original FB-989358 evidence image is authoritative:

`docs/feedback-cases-gloskin-20260820-154828/FB-989358-tentang-kami-page/evidence/feedback-989321-1787213320-0.png`

The implementation must inspect that evidence and derive the exact About section order before editing `about.php`. Existing generic sections are not authoritative merely because they already exist.

Language switcher / FB-989348 is explicitly deferred until after Phase 4.

---

# Final Home target

Exact visible structure:

1. Navbar
2. Full-width Home video hero
3. `Kenapa Memilih GLOSKIN` — simple two-column image + concise heading/bullets
4. `Treatment Unggulan` — exactly 6 visible cards, desktop 3×2
5. `Testimoni` — exactly 3 horizontal rows visible simultaneously
6. `Piagam` — exactly 4 image-only cards

Negative requirements:

- no text/CTA/eyebrow overlay on the Home video
- no cropped Home video
- no Home Promo campaign block
- no secondary brand-story band
- no testimonial slider-only UI, arrows, or dots
- no Achievement title/issuer/year/excerpt text on Home
- no Home closing CTA

The current Home template already omits Promo, so preserve that omission rather than accidentally reintroducing it.

## Home Treatment invariant

Phase 3 owns exactly 3 records with:

`gloskin_treatment_feature_on_home = 1`

Do not change that count to six.

Home selection logic must be deterministic:

1. take the exact 3 published Phase-3 featured records;
2. fill the remaining 3 from other published canonical `gloskin_treatment` records;
3. exclude duplicates;
4. use stable deterministic ordering;
5. render exactly 6.

---

# Home Hero / FB-989362

Keep one shared hero renderer: `gloskin_ui1_render_hero()`.

Add/reuse one Home-only presentation mode such as `video-only` / `home-video` rather than creating another renderer.

That mode must:

- render the native Media Library MP4/WebM source full-width;
- preserve the whole frame — no `object-fit: cover` crop;
- render no eyebrow, H1, copy, or CTA overlay;
- avoid campaign fade/scroll-cue if those elements are not in the approved reference;
- keep a safe uncropped fallback when the video is unavailable;
- remain responsive without distorting the source aspect ratio;
- leave shared hero behavior on other routes unchanged.

---

# Why Gloskin

Reuse the current Why owner/data rather than inventing a second content model, but simplify its presentation to the latest reference:

- one primary image on the left;
- one concise copy/bullet column on the right;
- no extra decorative narrative band;
- no fabricated statistics, medical claims, awards, or guarantees;
- responsive single-column stack on narrow screens.

---

# Testimonials

Reuse the existing managed `gloskin_testimonial` records and shared helper.

Home presentation must be a dedicated helper mode, not copied markup:

- exactly 3 active/published records in deterministic managed order;
- three static horizontal rows visible at once;
- no carousel viewport, arrows, dots, or autoplay on Home;
- retain semantic attribution/copy and responsive stacking.

Other routes may retain their existing presentation if used elsewhere.

---

# Piagam / Achievement

Canonical content owner remains `gloskin_achievement`.

Home presentation:

- exactly 4 records;
- image only;
- one row of four on desktop;
- responsive grid on smaller screens;
- no title, recognition, issuer, year, excerpt, or CTA in the Home card UI.

Use a helper presentation mode rather than duplicating an achievement renderer.

---

# Promo target

Reuse `gloskin_promo`; do not create another Promo CPT or poster data model.

Exact `/promo/` presentation:

1. centered `Promo Terbatas`
2. first large landscape image-led carousel
3. second independent `Promo Poster` landscape/image-only carousel
4. each carousel has its own local controls/dots

Negative requirements:

- no duplicated text-heavy campaign composition
- no page-content block below the carousels
- no closing CTA
- no thumbnail-selector UI from the stale Phase-2 design
- no runtime external image fetch

The same managed Promo records may feed both presentation surfaces. If there is no separate poster asset owner, reuse each record's canonical primary/featured image. Do not create a second data store.

The existing promo-gallery JS controller is already scoped per gallery root; reuse that per-instance behavior instead of creating another carousel framework.

---

# About / FB-989358

Before implementation, inspect the original evidence image and write the exact ordered section list into the implementation report.

Then render only those sections in that order.

Current generic About context/template includes story, founder, vision/mission/values, doctors, clinics, achievements, and closing CTA. None of those sections should survive solely because a data owner exists.

Rules:

- keep factual Page/founder/content owners only when the sketch actually needs them;
- remove Doctors / Clinics / Achievements / generic closing CTA when absent from the sketch;
- do not invent business facts to fill missing sketch content;
- update stale tests when they require an About closing CTA that the authoritative sketch does not contain.

---

# Shop AJAX

This is a surgical selector-contract repair.

Canonical Shop root:

`data-gloskin-shop-catalog-owner`

The current JS owner is:

`assets/js/gloskin-ui1-core.js`

It must initialize against the canonical root instead of looking for a different `data-gloskin-shop-catalog` root.

Preserve:

- REST endpoint
- search/category/price state
- pagination hrefs
- browser History behavior
- SSR partial
- AJAX partial replacement
- loading/error behavior

Do not create a second catalog root/controller.

---

# One Shop / Skincare retail product card

Use `gloskin_ui1_render_product_card()` as the single renderer.

Shop and Skincare must request the same canonical retail presentation variant.

Target:

- contained/consistent product image ratio
- product title
- Woo price
- primary Woo action
- equal card height and action baseline
- no rating/review UI
- no dense short-description block
- no extra wishlist presentation unless explicitly approved elsewhere

AJAX Shop results must inherit the same renderer automatically through the existing server partial.

---

# Media Cleanup safety hotfix

Do not perform production deletion as part of implementation.

Fix the two known safety defects only.

## Candidate-bound structured references

The current resolver fetches a globally limited structured postmeta window and only then checks whether a row references the candidate attachment. That can miss a real reference outside the first window.

For the candidate attachment ID, resolve before any result cap:

- `_thumbnail_id` — exact ID equality;
- `_product_image_gallery` — delimiter-safe CSV membership;
- Gloskin image/media/gallery structured meta — candidate-bound matching only.

Never restore naked numeric `%ID%` searches.

If a structured value cannot be safely interpreted, classify conservatively / ambiguous rather than deleting.

Preserve frozen scan boundary, sealed manifest, JIT reclassification, candidate-scoped verification, current authorization, and `wp_delete_attachment( $id, true )` as the only attachment deletion owner.

## Reload/resume

When an existing cleanup run reloads in `deleting` or `verifying`:

- show the existing Continue button;
- resume the existing deletion/verification cursor and sealed manifest;
- never restart the scan;
- never create a second worker;
- do not auto-start destructive deletion after page load.

---

# Phase-4 managed-content finalize

Use one small idempotent Phase-4 finalize action; do not build another Phase-3-sized migration engine.

Required order:

replacement ready
→ replacement media bound
→ replacement renderability verified
→ Trash legacy records
→ final verify

## Promo replacements

- create/ensure 3–6 stable Phase-4 Promo identities;
- published + active only after a usable landscape featured/primary image is bound;
- prefer suitable bundled/local assets; otherwise use lightweight local placeholder banners;
- clearly mark placeholders as Phase-4 demo/placeholder content;
- stable deterministic order;
- after replacements pass verification, Trash every non-allowlisted active/non-trash `gloskin_promo` record;
- never hard-delete here;
- never delete Media Library attachments here.

## Piagam replacements

- create/ensure exactly 4 stable Phase-4 `gloskin_achievement` identities;
- each must have a usable image;
- mark temporary artwork as placeholder when real certificate artwork is unavailable;
- after all four pass verification, Trash every non-allowlisted active/non-trash `gloskin_achievement` record;
- never hard-delete here;
- never delete Media Library attachments here.

Bind every placeholder attachment to its active replacement record so Media Cleanup sees a real WordPress reference.

Second finalize run must create zero records and Trash zero additional records.

---

# Retire obsolete Reset Demo mutation

The existing `Gloskin_Site_Core_Demo_Content_Reset` admin tool can hard-delete demo Promo/Testimonial/Achievement posts and recreate the old 3-promo / 3-testimonial / 3-achievement dataset. After Phase 4 this would be able to resurrect obsolete presentation data and bypass the Trash-only cleanup policy.

Phase 4 must retire that mutation path with the smallest safe change:

- do not use it for Phase-4 population;
- stop exposing/registering its destructive reset action after Phase-4 finalize, or make it fail closed once Phase-4 is complete;
- it must not hard-delete or recreate Phase-4 Promo/Piagam records;
- do not replace it with another broad reset framework.

The old one-shot Promo recovery workflow is separate; do not re-run or redesign it unless a focused regression proves it interferes with Phase 4.

---

# Stale contract updates

Update only assertions made obsolete by the latest approved client references.

Known stale assertions include:

- Phase 1 requiring a closing CTA caller on Home / Promo / About;
- Phase 2 requiring Home campaign text/CTA instead of video-only;
- Phase 2 locking the old Home closing CTA;
- Phase 2 locking Shop vs Skincare card divergence;
- Phase 2 locking old Promo single-carousel / thumbnail-selector assumptions.

Preserve unrelated guarantees:

- approved navbar labels;
- breadcrumb removal;
- shared CTA component readability itself (even when a page no longer calls it);
- Woo action semantics;
- canonical data ownership.

---

# Required Phase-4 regression contract

The new compact Phase-4 contract must prove at minimum:

- Home exact section order matches the latest wireframe;
- Home does not render Promo, extra brand-story content, or closing CTA;
- Home hero is video-only/no-text and uses an uncropped presentation rule;
- Home shows exactly 6 Treatment cards while Phase-3 feature meta remains exactly 3;
- Home shows exactly 3 static testimonial rows with no Home slider controls;
- Home shows exactly 4 image-only Piagam cards;
- Promo renders two independent image-led gallery/carousel roots and no legacy page content/CTA/thumb-selector composition;
- About section set/order is explicitly derived from FB-989358 evidence;
- Shop JS resolves `data-gloskin-shop-catalog-owner` and AJAX pagination remains wired;
- Shop and Skincare use the same retail-card variant;
- Media Cleanup structured-reference detection is candidate-bound before result limiting;
- deleting/verifying reload can resume without auto-starting deletion;
- Phase-4 Promo replacement count is 3–6;
- Phase-4 Piagam replacement count is exactly 4;
- legacy Promo/Achievement records are Trashed only after replacements are ready;
- Phase-4 finalize deletes zero Media Library attachments;
- obsolete Reset Demo cannot resurrect/hard-delete Phase-4 content after finalize;
- second Phase-4 finalize is a no-op.

---

# Recommended execution order

1. Media Cleanup safety hotfix
2. Shop AJAX selector repair
3. unified Shop/Skincare product card
4. Home Hero + Home composition fidelity
5. Promo two-carousel fidelity
6. About fidelity from original evidence
7. Phase-4 Promo/Piagam replacement + Trash-only legacy cleanup
8. retire obsolete Reset Demo mutation
9. focused contracts and production acceptance
10. ID/EN language switcher as the final feature task
11. final 11/11 client-ticket gate

---

# Principles

- low effort, high impact
- reuse canonical owners
- no architecture redesign without necessity
- no new CPT for existing concepts
- no runtime external image fetch
- no Phase-3 state/manifests/media mutation
- Trash managed legacy posts before any permanent-deletion discussion
- attachment deletion remains isolated to Media Cleanup
- placeholders are explicitly marked as placeholders
- production acceptance checks current rendered/data state, not historical counters alone
