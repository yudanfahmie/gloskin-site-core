# Gloskin Phase 4 — Presentation + Commerce Cleanup Plan

## Source of truth

This plan is based on the clearer Home/Promo wireframe supplied earlier.

### Home target
1. Navbar
2. Full-width video campaign
3. “Kenapa Memilih GLOSKIN” — image left, concise copy/bullets right
4. Treatment Unggulan — **6 cards**, desktop **3 × 2**
5. Testimoni — **3 stacked horizontal rows visible together**
6. Piagam — **exactly 4 image-only cards**
7. No extra closing CTA unless explicitly re-approved

### Promo target
1. Navbar
2. Centered **Promo Terbatas**
3. Large image-first featured promo carousel
4. Independent **Promo Poster** carousel
5. Each carousel has its own controls/dots
6. Reuse the existing `gloskin_promo` content owner
7. Artwork/image is the visual truth; avoid duplicated promotional copy UI
8. No extra legacy content/closing CTA unless explicitly re-approved

---

# Phase 4 scope

Phase 4 starts only after Phase 3 is production **COMPLETE**, verified, and second-run is a no-op.

## 1. Media Cleanup safety hotfix
Fix only the two known high-impact issues before any delete:
- candidate-specific structured reference detection
- resume/Continue behavior during delete/verify

Keep frozen cohort, authorization, dry-run, JIT reclassification, and `wp_delete_attachment()` safety boundaries.

**Do not perform destructive Media Cleanup until this hotfix passes focused validation.**

## 2. Shop AJAX
Surgical selector-contract fix only:
- existing root: `data-gloskin-shop-catalog-owner`
- JS must resolve the correct canonical catalog owner
- preserve existing pagination href/history/REST behavior
- no framework rewrite

## 3. Product card
Use one canonical retail product-card renderer for:
- Shop
- Skincare

Target:
- consistent image ratio
- title
- price
- primary Woo action
- equal card/action alignment
- no rating/dense description unless explicitly approved

## 4. Home / Promo / Hero / About
Implement fidelity against the supplied references.

### Home
- video hero: full video, no crop, no text overlay
- simple Why section
- 6 Treatment cards
- 3 stacked testimonial rows
- 4 image-only Piagam cards
- no legacy closing CTA

### Promo
- two independent image-led carousels
- reuse `gloskin_promo`
- no duplicated text-heavy legacy layout
- no legacy closing CTA

### Hero
Add/reuse a presentation mode in the shared hero owner:
- full video
- no crop
- no text overlay
- no duplicate hero implementation

### About
Reconstruct to the approved client sketch using existing managed owners where useful.
Avoid carrying forward unrelated generic legacy sections just because they already exist.

---

# Phase 4 content cleanup / migration

Cleanup should be part of Phase 4 so obsolete presentation data does not remain active.

## Promo legacy cleanup
After replacement promo data is ready:
- Trash obsolete `gloskin_promo` records not part of the new active set
- do not hard-delete first
- keep cleanup idempotent
- preserve media attachments for the separate Media Cleanup flow

For quick placeholder content:
- create/use **3–6 landscape promo/banner images**
- mark them clearly as placeholder/demo content
- use stable deterministic slugs/order
- do not pretend placeholders are client-supplied assets

## Achievement / Piagam cleanup
- Trash obsolete legacy achievement records/content that no longer matches the approved presentation
- Home final presentation remains **4 image-only Piagam cards**
- if approved certificate assets are unavailable, use **4 temporary placeholder images** so the layout matches the wireframe
- do not retain obsolete title/issuer/year/excerpt UI

## General Phase 4 cleanup rule
Use:

replacement ready
→ bind replacement
→ verify presentation
→ Trash legacy content
→ final verify

Never remove the old content before the replacement is available.

Do not delete Media Library files during this cleanup.

---

# Language switcher

Move ID/EN switcher to the **final task after Phase 4 presentation/commerce work**.

Reason:
- it is largely orthogonal to the current layout/data cleanup
- language ownership/plugin integration can introduce extra state/URL concerns
- stabilizing final page structures first reduces rework

Phase 4 should leave the header structurally ready for the switcher, but not block the rest of the work on multilingual integration.

---

# Phase 4 recommended execution order

1. Media Cleanup safety hotfix
2. Shop AJAX selector fix
3. Unified Shop/Skincare product card
4. Home fidelity
5. Promo fidelity + legacy Promo cleanup
6. Hero full-video presentation mode
7. About fidelity
8. Achievement/Piagam cleanup + replacement placeholders
9. Focused production acceptance
10. ID/EN switcher as the last feature task
11. Final 11/11 client-ticket gate

---

# Safety / implementation principles

- Low effort, high impact
- Reuse canonical owners
- No architecture redesign unless required
- Trash posts/products before considering permanent deletion
- Taxonomy deletion only when a deterministic allowlist exists
- Media deletion remains isolated to Media Cleanup
- Placeholders must be clearly marked as placeholders
- Update stale Phase 1/2 test contracts when they conflict with the latest approved wireframe
- Final verification should check actual rendered/data state, not only historical audit counters
