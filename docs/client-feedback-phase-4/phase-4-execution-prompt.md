# Phase 4 / 4.1 / 5 — Final Closure Prompt

Repository: `yudanfahmie/gloskin-site-core`

## Mission

Finish only the remaining Phase-4 presentation/cleanup work and the small Phase-5 compatibility hardening required by the final Phase-4 templates.

This is a completion pass, not another audit or planning cycle.

Do not rebuild work that is already green. Reuse current canonical owners and the good staged Phase-4 implementation where useful.

Target: **11/11 client-feedback tickets source-ready for production**, while keeping production-only finalization explicit and operator-triggered.

---

## Start from current authoritative main

```bash
git fetch --prune origin
git checkout -f main
git reset --hard origin/main
```

Baseline when this prompt was refreshed:

- main before docs refresh: `414ff73d4016ac46c7ed777f47de4af827c533a8`
- plugin version: `0.7.182`

If `origin/main` is newer, use it. Never reset main backward to the baseline above.

Read only the current execution authorities needed for this pass:

- `docs/client-feedback-phase-4/home-promo-wireframe.html`
- `docs/client-feedback-phase-4/phase-4-plan.md`
- `docs/client-feedback-phase-4/phase-4-woo-taxonomy-addendum.md`
- `docs/client-feedback-phase-4/phase-4-woo-taxonomy-mapping.md`

No new design-analysis pass is required.

---

# Mandatory structural authority — DO NOT READ OR INTERPRET THE SCREENSHOTS

For **Home, Promo, About, and the Home video structure**, implement directly from:

`docs/client-feedback-phase-4/home-promo-wireframe.html`

The legacy filename now contains **Home + Promo + About**.

That HTML is the normalized implementation authority for:

- section order;
- component shape;
- visible counts;
- explicit omissions;
- Home video no-crop/no-text behavior;
- responsive hierarchy.

The original client images remain provenance only.

**Do not block on opening images, OCR, image viewers, Drive access, screenshot interpretation, or another reverse-engineering pass.**

**Do not derive a second page structure from the screenshots. If current code differs from the HTML wireframe, change the code to match the HTML wireframe.**

This rule exists specifically to remove the repeated Phase-4 blocker around visual interpretation.

---

# Already PASS — preserve, do not redo

The following work is already landed or functionally complete and is outside the rewrite scope:

- Phase-3 production data contract and canonical migrated content;
- Media Cleanup candidate-bound structured-reference safety;
- Media Cleanup deleting/verifying reload-resume;
- Shop AJAX canonical root `[data-gloskin-shop-catalog-owner]`;
- shared Shop/Skincare retail product-card presentation;
- Phase-4.1 Treatment focus media as true 1:1 circles;
- Phase-4.1 Treatment editorial band sizing/hover/reduced-motion behavior;
- Skincare intro removal;
- Phase-3 PHP migration runtime retirement;
- Phase-5 Translation admin console;
- Phase-5 manual EN editing and Generate Missing;
- Phase-5 browser-admin OPUS-MT generation;
- Phase-5 real ID/EN language context and first-party cookie;
- Phase-5 HTML language switching;
- Phase-5 saved post/meta/term/Woo/interface projections;
- Phase-5 Indonesian fallback behavior.

Do not rerun Phase 3.
Do not redesign Media Cleanup.
Do not rewrite Shop AJAX.
Do not rebuild multilingual.

---

# Reuse the good staged Phase-4 work safely

Useful unfinished source exists at:

`origin/phase4-commit3-work-20260821`

That branch is stale/divergent from current main and predates the latest Phase-5 work.

**Do not merge it wholesale.**
**Do not rebase main onto it.**
**Do not cherry-pick its whole history blindly.**

Safe candidates to selectively reuse are:

```text
plugin/gloskin-site-core/assets/css/gloskin-ui1-phase4.css
plugin/gloskin-site-core/templates/pages/home.php
plugin/gloskin-site-core/templates/pages/promo.php
plugin/gloskin-site-core/templates/parts/phase4-home-selection.php
plugin/gloskin-site-core/includes/class-gloskin-site-core-phase4-finalizer-admin.php
```

For:

`plugin/gloskin-site-core/config/assets.php`

apply only the Phase-4 stylesheet-registration hunk to the current file.

Never restore the stale branch's:

- Kernel;
- plugin bootstrap;
- Translation/Language classes;
- version owners;
- old tests wholesale.

---

# 1. Home — final Phase-4 structure

Match the HTML wireframe exactly.

Final visible Home order:

1. Navbar
2. full-width Home video only
3. `Kenapa Memilih GLOSKIN`
4. `Treatment Unggulan`
5. `Testimoni`
6. `Piagam`

Nothing renders after Piagam.

## Home hero

Home-only behavior:

- native current Media Library video owner;
- whole frame visible;
- full width;
- uncropped: `object-fit: contain` or equivalent;
- no eyebrow;
- no heading;
- no body copy;
- no CTA;
- no text overlay;
- no competing campaign fade/scroll-cue presentation;
- safe uncropped fallback;
- other route heroes unchanged.

Do not create another shared hero renderer.

## Why Gloskin

Use the wireframe's simple two-column composition:

- one primary image/media area;
- one concise heading/copy/bullet area;
- responsive stack on narrow screens.

Reuse existing canonical/editorial owners where applicable.
Do not invent statistics, guarantees, awards, or medical claims.

## Treatment Unggulan

Exactly **6** visible cards.

Selection invariant:

1. exact 3 published Phase-3 records with `gloskin_treatment_feature_on_home = 1` first;
2. deterministic 3 other published canonical `gloskin_treatment` records;
3. exclude duplicate IDs;
4. stable deterministic ordering;
5. desktop = 3 × 2.

Do not change the feature-meta contract: it remains exactly **3**, not 6.

The staged `phase4-home-selection.php` is an acceptable reuse seam.

## Testimoni

Exactly **3** managed testimonials visible simultaneously as static rows.

No Home:

- carousel viewport-only presentation;
- arrows;
- dots;
- autoplay.

## Piagam

Exactly **4** image-only Achievement/Piagam cards.

No Home title/issuer/year/excerpt/CTA inside those cards.

## Home negative contract

Do not render:

- Home Promo;
- extra brand-story band;
- testimonial controls;
- text-heavy Achievement presentation;
- Home closing CTA.

---

# 2. Promo — final Phase-4 structure

Match the HTML wireframe exactly.

Final Promo page:

1. centered `Promo Terbatas`
2. first independent landscape image-led carousel
3. centered `Promo Poster`
4. second independent image-only carousel

Both instances:

- reuse current managed `gloskin_promo` records;
- reuse the existing per-root carousel/controller owner;
- have independent root/state/controls/dots;
- remain responsive.

Do not create another Promo CPT/data store/controller.

Remove or keep absent:

- old text-heavy campaign composition;
- Promo page-content block;
- closing CTA;
- thumbnail selector;
- external runtime image fetch.

---

# 3. About — implement the HTML contract, not the image

Do **not** inspect or reinterpret FB-989358 screenshots for structure.

The normalized About contract is already encoded in `home-promo-wireframe.html`.

Render exactly:

1. Navbar
2. simple `Tentang Kami` page header — no sales CTA
3. `Tentang GLOSKIN` story — existing canonical About/Page content plus appropriate canonical current media
4. Founder — existing canonical founder identity/role/story plus existing canonical portrait/media when available
5. `Visi · Misi · Nilai` — three concise existing managed blocks
6. end of page

Explicit omissions from About:

- Doctors section;
- Clinics section;
- Achievements section;
- generic closing CTA.

Do not keep an old section merely because a context owner exists.

Do not invent company history, founder claims, credentials, statistics, awards, medical claims, guarantees, or replacement factual copy when a canonical field is empty.

If a canonical optional field is empty, degrade gracefully rather than fabricating content.

---

# 4. Wire the staged Phase-4 finalizer into the CURRENT Kernel

Reuse:

`Gloskin_Site_Core_Phase4_Finalizer_Admin`

from the staged Phase-4 work.

Do not rewrite it from zero.

Wire it minimally into the **current** admin Kernel:

- `require_once` the finalizer class;
- instantiate it;
- call `->register()`;
- retain the service reference if consistent with current Kernel style.

Preserve all current Phase-5 services:

- `Gloskin_Site_Core_Translation`
- `Gloskin_Site_Core_Language`
- `Gloskin_Site_Core_Language_Projection`

Also preserve Media Cleanup and all current production services.

Coder implements/tests the Phase-4 finalizer but **DOES NOT execute it in production**.

---

# 5. Phase-4 finalizer contract

The finalizer remains:

- explicit operator-triggered action only;
- `manage_options` protected;
- nonce protected;
- fail closed;
- idempotent;
- no automatic frontend/upgrade mutation;
- second run = `already_complete`, mutations `0`.

Required operation order:

```text
replacement ready
→ bind media/category
→ verify replacement renderability/data
→ Trash obsolete managed records
→ final verify
→ complete
```

## Promo replacement data

Use exactly **3** stable Phase-4 Promo replacements; 3 is valid inside the approved 3–6 range.

Every replacement must have a usable local image before publish/readiness verification.

Trash obsolete Promo records only after replacements verify.

Never hard-delete posts.
Never delete Media Library attachments.

## Piagam replacement data

Use exactly **4** stable Phase-4 Achievement/Piagam replacements.

Every replacement must have a usable local image.

Trash obsolete Achievement records only after all four replacements verify.

Never hard-delete posts.
Never delete Media Library attachments.

Bind placeholder attachments to their active records so standard WordPress references remain visible to Media Cleanup.

---

# 6. Woo native `product_cat` alignment

Use exactly:

- `docs/client-feedback-phase-4/phase-4-woo-taxonomy-addendum.md`
- `docs/client-feedback-phase-4/phase-4-woo-taxonomy-mapping.md`

Canonical products only.

Required final verification:

- Skincare mapped: **25/25**
- Treatment Woo products: **48/48 → `perawatan`**
- canonical products in Uncategorized: **0**
- unrelated Woo products mutated: **0**

Skincare native distribution:

- `produk-penunjang`: 11
- `day-cream-sunscreen`: 5
- `facial-wash`: 4
- `serum`: 4
- `toner`: 1

Rules:

- append required valid category;
- preserve legitimate extra categories;
- remove Uncategorized only after valid category attachment is proven.

Do not modify:

- `gloskin_product_family`;
- `gloskin_concern`;
- `gloskin_consultation_path` identity/relations;
- price;
- SKU;
- stock policy/quantity;
- product media;
- attachment provenance;
- Phase-3 DB state.

---

# 7. Retire obsolete Reset Demo runtime

Current code still actively registers:

`Gloskin_Site_Core_Demo_Content_Reset`

Retire its active mutation path with the smallest safe current-Kernel change.

Preferred:

- remove active `require_once`;
- remove instantiation;
- remove `register()` call;
- remove service ownership.

The historical class file may remain if deleting it adds no practical value.

Acceptance:

- no active runtime path can recreate the obsolete Demo Promo/Achievement dataset;
- no reset path can hard-delete Phase-4 replacement content.

Do not build another reset framework.

---

# 8. Remove the missed Phase-3 plugin resource bundle

Current source still contains:

`plugin/gloskin-site-core/resources/phase3/`

Phase-3 migration PHP runtime is already retired.

Run one quick active-runtime dependency grep.

Expected active runtime dependencies = **0**.

If zero, delete the entire plugin directory:

`plugin/gloskin-site-core/resources/phase3/`

Keep historical authoritative docs:

`docs/client-feedback-phase-3/`

Do not delete/reset:

- production Phase-3 DB state/options;
- Woo products;
- Treatments;
- Skincare records;
- Media Library attachments;
- attachment provenance.

If a historical test still reads plugin-local Phase-3 resources only for manifest integrity, update it to read the preserved authoritative docs instead of retaining dead runtime resources.

Deployment note: cPanel copy semantics may leave removed source files/directories on an already deployed server. Report that as a one-time operator cleanup note; coder does not delete production files automatically.

---

# 9. Phase 5 — compatibility only, no rebuild

Phase 5 is already functional.

Do not redesign Translation.
Do not consolidate the three current classes.
Do not create another Language/Translation owner.

After Home/Promo/About final templates are implemented, inspect only the **actual visible Gloskin-owned strings those final templates render**.

Register missing final static strings in the existing:

`Gloskin_Site_Core_Translation::interface_registry()`

At minimum cover final strings where actually rendered, including:

- `Kenapa Memilih GLOSKIN` → natural English equivalent
- `Testimoni` → `Testimonials`
- `Piagam` → `Certificates`
- `Promo Poster` → `Promo Posters`
- `Informasi promo belum tersedia.` → natural English equivalent
- final About headings from the HTML wireframe

Also register final visible Why copy if it is hard-coded Gloskin-owned interface copy.

Use natural English defaults, not broken word-for-word translation.

Translation admin remains the manual editing authority.

Only expose textual custom fields that the final templates actually consume.

Never expose translation targets for:

- media IDs;
- URLs;
- booleans/feature flags;
- ordering;
- prices;
- SKU;
- stock;
- taxonomy IDs;
- migration/finalizer state.

---

# 10. Small ID/EN switcher hardening

Current real ID/EN already works, but basic switching is still activated from inert header spans through footer inline JavaScript.

If this remains a small surgical change, harden the **existing** language owner so the existing header renders real server-side language controls.

Preferred behavior:

When ID is current:

- ID = current non-link element;
- EN = real link.

When EN is current:

- EN = current non-link element;
- ID = real link.

Reuse:

- `Gloskin_Site_Core_Language::language()`;
- current `gloskin_lang=id|en` mechanism;
- current first-party cookie;
- existing switcher classes/styles.

Basic ID/EN switching should work with JavaScript disabled.

Remove the requirement for `activate_existing_switcher()` / footer DOM mutation if server-side controls replace it cleanly.

Do not create a new service.

Do not allow this secondary hardening to block a clean tested Phase-4 completion if it unexpectedly expands.

---

# 11. Version

Current expected runtime version is `0.7.182`.

After all remaining completion work is integrated, bump exactly **one patch** from the actual current version.

Expected if main has not advanced:

`0.7.182 → 0.7.183`

Synchronize only active canonical version owners/tests:

- plugin header;
- Kernel `VERSION`;
- active release/version assertions.

Do not mass-edit historical docs.

---

# 12. Focused validation only

No broad historical audit.
No new giant test framework.

Run:

- `php -l` on changed PHP;
- changed JS syntax check only if JS changed;
- `git diff --check`;
- existing Media Cleanup safety contract;
- existing Shop AJAX canonical-root/card contracts;
- Phase-4.1 preservation contract;
- Phase-3 retirement/preservation contract;
- Phase-5 translation admin/frontend contracts;
- one compact final Phase-4 contract.

The final Phase-4 contract must prove at minimum:

## Home

- exact wireframe section order;
- no `home-closing`;
- exactly 6 Treatment selection contract;
- Phase-3 feature meta remains exactly 3;
- exactly 3 static testimonials;
- exactly 4 image-only Piagam;
- Home hero heading/copy/CTA cleared;
- Home no-crop/contain rule exists.

## Promo

- `Promo Terbatas` exists;
- `Promo Poster` exists;
- 2 independent carousel roots;
- no `promo-content`;
- no `promo-closing`;
- no stale thumbnail-selector composition.

## About

Exact structural markers/order:

`about-header → about-story → about-founder → about-principles`

And no:

- About doctors;
- About clinics;
- About achievements;
- About closing CTA.

## Finalizer

- class is loaded/registered in current admin Kernel;
- explicit `manage_options` + nonce;
- Promo replacement definitions = 3;
- Piagam definitions = 4;
- Skincare mapping identities = 25;
- Treatment mapping identities = 48;
- second-run no-op path exists;
- hard-delete post path = 0;
- Media Library delete path = 0.

## Retirement

- active Demo Reset registration = 0;
- plugin `resources/phase3` absent;
- Phase-3 migration runtime remains retired.

## Phase 5

- Translation admin preserved;
- Language preserved;
- Language Projection preserved;
- final visible Home/Promo/About strings are covered;
- saved EN projection preserved;
- ID fallback preserved;
- public OPUS-MT/Transformers/model loading = 0.

If an older Phase-1/2 assertion encodes presentation explicitly superseded by this Phase-4 HTML authority, update the stale assertion rather than restoring obsolete UI.

If a full WP/browser runtime is unavailable, do not block indefinitely: run the focused static/source contracts available locally and report the missing runtime check accurately.

---

# 13. Commit and push

Work directly on main if permitted.

No PR.
No force push.
No workflow creation.

Preferred completion commit:

`Complete final Phase 4 presentation and production closure`

If the no-JS language hardening is cleaner separately:

`Harden final ID EN presentation`

Push tested ready work promptly.

Do not leave final implementation only on `phase4-commit3-work-20260821` or another temporary branch.

---

# Do not

Do not:

- inspect/reinterpret client screenshots for page structure;
- block on image viewers/OCR/Drive;
- merge the stale Phase-4 branch wholesale;
- overwrite current Phase-5 Kernel/bootstrap;
- rebuild Translation/multilingual;
- create a fourth language/translation service;
- create new CPTs/frameworks;
- rerun or reset Phase 3;
- execute the Phase-4 finalizer in production;
- execute production Media Cleanup deletion;
- hard-delete Promo/Achievement records;
- delete Media Library attachments;
- alter product prices/stock/SKU;
- fetch runtime external images;
- create `/en/` duplicate routes;
- perform another broad audit/planning cycle.

---

# Final report — required

Report exactly:

```text
MAIN SHA:
VERSION:
PUSHED TO MAIN: YES/NO

FB-989346 Navbar exact: PASS/FAIL
FB-989348 Real ID/EN multilingual: PASS/FAIL
FB-989350 Home reconstruction: PASS/FAIL
FB-989352 Promo reconstruction: PASS/FAIL
FB-989354 Skincare product media: PASS/FAIL
FB-989356 Shop/Skincare retail-card consistency: PASS/FAIL
FB-989358 About HTML-wireframe fidelity: PASS/FAIL
FB-989360 Treatment catalog/media: PASS/FAIL
FB-989362 Home video no-crop/no-text: PASS/FAIL
FB-989364 CTA readability / obsolete Home CTA removed: PASS/FAIL
FB-989369 Visible breadcrumbs removed: PASS/FAIL

SOURCE-READY CLIENT TICKETS: X/11

HOME TREATMENTS: X/6
PHASE3 FEATURE META: X/3
HOME TESTIMONIALS: X/3
HOME PIAGAM: X/4
PROMO CAROUSELS: X/2
PROMO REPLACEMENTS: X/3
PIAGAM REPLACEMENTS: X/4
SKINCARE PRODUCT_CAT: X/25
TREATMENT PERAWATAN: X/48
CANONICAL UNCATEGORIZED: X
UNRELATED WOO MUTATIONS: X
ABOUT HTML-WIREFRAME FIDELITY: PASS/FAIL
RESET DEMO ACTIVE: YES/NO
PLUGIN resources/phase3 PRESENT: YES/NO
PHASE4 SECOND-RUN CONTRACT: PASS/FAIL
HARD-DELETED POSTS: 0
MEDIA DELETIONS: 0

TRANSLATION ADMIN: PASS/FAIL
GENERATE MISSING: PASS/FAIL
MANUAL EN EDIT: PASS/FAIL
REAL ID SWITCH: PASS/FAIL
REAL EN SWITCH: PASS/FAIL
SWITCH REQUIRES JS: YES/NO
LANGUAGE COOKIE: PASS/FAIL
HTML LANG: PASS/FAIL
SAVED EN PROJECTION: PASS/FAIL
ID FALLBACK: PASS/FAIL
FINAL HOME/PROMO/ABOUT EN COVERAGE: PASS/FAIL
WOO EN PROJECTION: PASS/FAIL
PUBLIC OPUS LOADS: 0
PUBLIC TRANSFORMERS LOADS: 0

PHASE4 FINALIZER EXECUTED BY CODER: NO
PRODUCTION MEDIA CLEANUP DELETION BY CODER: NO
POST-DEPLOY OPERATOR FINALIZER REQUIRED: YES/NO
STALE CPANEL CLEANUP NOTE REQUIRED: YES/NO

SOURCE / REPO READY: X/11
PRODUCTION FINAL DATA STATE: pending operator finalizer / complete
```

Do not claim production is 11/11 solely because source is ready. Distinguish repository/source readiness from the post-deploy Phase-4 finalizer/data-state acceptance.
