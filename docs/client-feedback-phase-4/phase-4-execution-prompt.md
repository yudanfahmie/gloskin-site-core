# Phase 4 / 4.1 / 5 — Final Production Closure

Repository: `yudanfahmie/gloskin-site-core`

## Goal

Finish the remaining real work and push it. Do not start another broad audit, another design-interpretation cycle, or another migration framework.

The final state must be durable WordPress/WooCommerce data + final templates, not temporary frontend patchers.

Before writing:

```bash
git fetch --prune origin
git checkout -f main
git reset --hard origin/main
```

Use newer `origin/main` if it has advanced.

Read only:

- `docs/client-feedback-phase-4/home-promo-wireframe.html`
- `docs/client-feedback-phase-4/phase-4-plan.md`
- `docs/client-feedback-phase-4/product-content-research.md`
- `docs/client-feedback-phase-4/phase-4-woo-taxonomy-addendum.md`
- `docs/client-feedback-phase-4/phase-4-woo-taxonomy-mapping.md`
- Phase-3 canonical manifests for the exact 25 Skincare + 48 Treatment identities.

## Preserve already-green work

Do not redo Phase 3, Media Cleanup safety/resume, Shop AJAX root, shared Shop/Skincare cards, Phase-4.1 Treatment presentation, Skincare intro removal, Phase-3 runtime retirement, or the current real Phase-5 ID/EN Translation system.

`home-promo-wireframe.html` is the sole structural authority for Home, Promo, About, and the Home video. Do not block on screenshots/OCR/Drive. If runtime differs from the HTML, change runtime to match the HTML.

---

# 1. Finish Home / Promo / About

## Home

Exact visible order:

1. Navbar
2. uncropped full-width Home video only
3. Why Gloskin
4. Treatment Unggulan — exactly 6
5. Testimoni — exactly 3 static rows
6. Piagam — exactly 4 image-only cards

Nothing after Piagam.

Home video: whole frame visible, `object-fit: contain` or equivalent, no heading/copy/CTA/overlay, Home-only behavior.

Treatments: exact 3 current `gloskin_treatment_feature_on_home=1` records first + deterministic 3 other published canonical Treatments, no duplicates, desktop 3×2. Feature-meta count stays exactly 3.

No Home Promo, extra brand-story band, testimonial slider controls, text-heavy Achievement cards, or Home closing CTA.

## Promo

Exact structure:

1. `Promo Terbatas`
2. independent landscape image carousel
3. `Promo Poster`
4. second independent image-only carousel

Reuse `gloskin_promo` + existing per-root carousel controller. No page-content block, closing CTA, text-heavy legacy composition, thumbnail selector, or runtime external image fetch.

## About

Implement only the HTML contract:

1. simple Tentang Kami header
2. Tentang GLOSKIN story
3. Founder
4. Visi · Misi · Nilai
5. end

Use existing factual owners/media only. No Doctors, Clinics, Achievements, generic closing CTA, or invented facts.

---

# 2. Extend the ONE Phase-4 finalizer into the permanent content resolver

Reuse the staged/current `Gloskin_Site_Core_Phase4_Finalizer_Admin`. Do not create another migration class or runtime patcher.

This single explicit admin action must permanently reconcile all Phase-4 data in one seamless run:

```text
resolve canonical records
→ write product content
→ attach Woo categories
→ bind/create usable Promo/Piagam images
→ verify replacements
→ Trash explicit legacy/demo records
→ final verify
→ complete
```

Requirements:

- `manage_options` + nonce
- explicit operator action only
- fail closed
- idempotent
- second run = `already_complete`, zero mutation
- no hard-delete posts
- no Media Library delete
- coder implements/tests but DOES NOT run it in production

The resolver writes real Woo/post/meta/taxonomy state. Frontend must not depend on a special fallback description/content injector after the resolver runs.

---

# 3. Woo products — remove demo legacy + make every single-product page complete

Canonical Gloskin Woo scope is exactly:

- 25 Skincare products from `skincare-products.json`
- 48 Treatment products from `treatment-catalog.json`
- total = 73

## Legacy/demo cleanup

There are still non-production legacy/demo Woo products visible beside the new canonical products.

Trash every product that is explicitly identifiable as old Gloskin demo/legacy/sample content, using strong evidence such as existing sample/demo/provenance markers, known old managed identities, or explicit legacy Gloskin family ownership.

Never fuzzy-trash unrelated Woo products.

Final invariant:

- canonical Gloskin products active = 73
- explicit active Gloskin demo/legacy products = 0
- unrelated Woo products mutated = 0

Trash only; never hard-delete.

## Product descriptions — REQUIRED, not optional

Current canonical product single pages are effectively empty because descriptions are missing.

Use `docs/client-feedback-phase-4/product-content-research.md` as the prepared research source. Do not launch another open-ended research project.

For all 73 canonical Woo products persist:

- non-empty Woo short description (`post_excerpt`)
- non-empty Woo full description (`post_content`)

Required:

- Skincare short/full = 25/25
- Treatment short/full = 48/48
- total short/full = 73/73

Use concise paraphrased official Gloskin information where available.

If an exact official description is unavailable, still fill the record using the conservative fallback rules in the research guide. Do not invent unverified ingredients, SPF, BPOM, size, dosage, device technology, session count, permanence, guaranteed results, or medical claims.

For medical/invasive treatments, keep suitability/evaluation language and avoid guarantees.

Recommended durable ownership metadata:

- `_gloskin_phase4_content_source`
- `_gloskin_phase4_content_version`

Resolver behavior:

- fill empty fields
- replace known demo/placeholder copy
- may refresh copy it previously owns when content version advances
- preserve unrelated/manual non-demo copy
- never duplicate a product to enrich content

## Woo categories — REQUIRED for AJAX Shop sidebar

Assign native Woo `product_cat` in the same resolver before completion.

Required:

- Skincare: 25/25 mapped
- Treatment: 48/48 → `perawatan`
- canonical Uncategorized = 0
- unrelated Woo mutations = 0

Skincare distribution:

- `produk-penunjang` 11
- `day-cream-sunscreen` 5
- `facial-wash` 4
- `serum` 4
- `toner` 1

Preserve legitimate extra categories. Remove Uncategorized only after a valid category is attached.

Do not change prices, SKU, stock, media/provenance, private Phase-3 taxonomies, or Phase-3 state.

This category work is part of the permanent data resolve so the existing Shop AJAX/category sidebar works from real Woo taxonomy, not frontend special cases.

---

# 4. Promo + Achievement/Piagam — real-looking local images

Do not leave blank gray placeholder UI/data.

Prepare local, usable image assets for the replacement records. Real client artwork is preferred; if unavailable, create committed local branded raster placeholders that visibly look like finished promo/certificate artwork rather than generic blocks.

Acceptable placeholder direction:

- Promo: landscape campaign-style artwork with clean Gloskin branding/layout
- Piagam: certificate/award-style artwork with a finished framed composition

Do not claim a fake real award, issuer, date, discount, price, medical result, or accreditation inside placeholder artwork. Label placeholder/demo provenance in metadata if needed, not as ugly frontend text.

Finalizer data contract:

- Promo replacements = exactly 3 stable image-ready records
- Piagam/Achievement replacements = exactly 4 stable image-ready records
- every replacement has a usable local/Media-Library image before it becomes the accepted replacement
- image attachment is actually bound to the record
- obsolete managed Promo/Achievement records are Trashed only after replacements verify
- no attachment deletion

Reuse existing `gloskin_promo` and `gloskin_achievement`; no new CPT/data store.

---

# 5. Retirement cleanup

Retire active `Gloskin_Site_Core_Demo_Content_Reset` Kernel registration so it cannot recreate obsolete demo content after this resolver.

Phase-3 PHP runtime is already retired. Run one quick active-reference grep; if active dependency count is zero, remove:

`plugin/gloskin-site-core/resources/phase3/`

Keep `docs/client-feedback-phase-3/` and all production DB/media/provenance state.

cPanel copy deployment may leave deleted source files live; report that as a one-time operator cleanup note only.

---

# 6. Phase 5 compatibility only

Do not rebuild multilingual.

After final Home/Promo/About templates land, add only the newly visible static labels to the existing `Gloskin_Site_Core_Translation::interface_registry()` so EN does not contain stray Indonesian UI.

If small, make the existing ID/EN switcher server-rendered so basic switching works without JS; reuse the current Language owner/cookie/query mechanism. Do not let this optional hardening delay the main closure.

Product descriptions written in Indonesian remain canonical; the existing Phase-5 Translation admin/projection must discover/provide their EN companion content using the current translation architecture rather than duplicate English products.

---

# 7. Validation — keep it focused

Do not build many tests. Do not rerun the entire historical suite unless a focused failure requires it.

Run only:

- syntax/lint for changed files
- `git diff --check`
- existing focused Shop/Media Cleanup/Phase-5 preservation checks touched by this change
- ONE compact final resolver/Phase-4 contract

That compact contract must prove only the high-value final invariants:

```text
Home structure/counts/omissions PASS
Promo two carousels PASS
About HTML structure/omissions PASS

canonical Woo products = 73
active explicit Gloskin demo/legacy products = 0
Skincare descriptions short/full = 25/25
Treatment descriptions short/full = 48/48
Woo category mapping = 25/25 + 48/48
canonical Uncategorized = 0
unrelated Woo mutations = 0

Promo replacements image-ready = 3/3
Piagam replacements image-ready = 4/4
hard-deleted posts = 0
media deletions = 0

Reset Demo active = 0
plugin resources/phase3 absent
second resolver run = no-op
Phase-5 Translation preserved
```

If an old Phase-1/2 presentation assertion conflicts with the newer HTML authority, update that stale assertion; do not restore obsolete UI.

---

# 8. Push real work

Work directly on main if permitted. No PR, workflow, force push, or broad refactor.

Prefer one coherent implementation commit, for example:

`Complete Phase 4 content and presentation closure`

Push tested ready work promptly. Time should go into the actual resolver/data/templates/assets, not additional planning or test scaffolding.

Bump exactly one patch from the actual current plugin version after the completion batch (expected `0.7.182 → 0.7.183` if unchanged).

## Final report

Report compactly:

```text
MAIN SHA:
VERSION:
PUSHED TO MAIN: YES/NO
SOURCE-READY CLIENT TICKETS: X/11

ACTIVE CANONICAL GLOSKIN PRODUCTS: X/73
ACTIVE LEGACY/DEMO GLOSKIN PRODUCTS: X
SKINCARE DESCRIPTIONS: X/25 short, X/25 full
TREATMENT DESCRIPTIONS: X/48 short, X/48 full
SKINCARE PRODUCT_CAT: X/25
TREATMENT PERAWATAN: X/48
CANONICAL UNCATEGORIZED: X
UNRELATED WOO MUTATIONS: X

HOME: PASS/FAIL
PROMO: PASS/FAIL
ABOUT: PASS/FAIL
PROMO IMAGE-READY: X/3
PIAGAM IMAGE-READY: X/4
RESET DEMO ACTIVE: YES/NO
PLUGIN resources/phase3 PRESENT: YES/NO
SECOND RESOLVER RUN: NO-OP/FAIL
HARD-DELETED POSTS: 0
MEDIA DELETIONS: 0
PHASE-5 ID/EN PRESERVED: PASS/FAIL

PHASE4 RESOLVER EXECUTED BY CODER IN PRODUCTION: NO
POST-DEPLOY OPERATOR RESOLVER REQUIRED: YES/NO
```

Do not claim production completion until the operator actually runs the final resolver post-deploy and its verifier passes.
