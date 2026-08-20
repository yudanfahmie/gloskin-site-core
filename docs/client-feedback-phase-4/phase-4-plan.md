# Gloskin Phase 4 — Current Completion Plan

This file is the **current-state plan**, not the historical execution sequence.

Baseline at this refresh:

- `main`: `414ff73d4016ac46c7ed777f47de4af827c533a8`
- plugin version: `0.7.182`

If `origin/main` advances, the newer main is authoritative. Never reset it backward to this SHA.

## Structural authority — no screenshot interpretation

For Phase-4 page composition, the sole implementation structure reference is now:

`docs/client-feedback-phase-4/home-promo-wireframe.html`

Despite the legacy filename, it now contains **Home + Promo + About**.

The original FB-989350 / FB-989352 / FB-989358 / FB-989362 images remain provenance only. **Coder must not block on opening, OCRing, reverse-engineering, or reinterpreting those images.** Implement the HTML wireframe directly.

If an old prompt/test says the screenshots are the runtime authority, this plan supersedes that instruction.

---

## Already PASS / preserve

Do not redo these items:

| Area | Current status |
| --- | --- |
| Phase-3 production data contract | PASS / preserve; do not rerun migration |
| Media Cleanup candidate-bound structured references | PASS |
| Media Cleanup deleting/verifying reload-resume | PASS |
| Shop AJAX canonical root `data-gloskin-shop-catalog-owner` | PASS |
| Shop + Skincare shared retail product-card presentation | PASS |
| Phase-4.1 Treatment focus media 1:1 circles | PASS |
| Phase-4.1 Treatment editorial band sizing/hover/reduced-motion | PASS |
| Skincare intro removal | PASS |
| Phase-3 PHP migration runtime retirement | PASS |
| Phase-5 Translation admin + OPUS-MT generation | PASS |
| Phase-5 saved ID/EN frontend language context/projections/fallback | PASS |

Phase-5 ID/EN is already real functionality. A server-rendered/no-JS switcher is useful hardening, but **do not rebuild multilingual**.

---

## Remaining Phase-4 source work

### 1. Home

Implement exactly the wireframe order:

1. Navbar
2. Home full-width video only
3. Why Gloskin
4. Treatment Unggulan — exactly 6
5. Testimoni — exactly 3 static rows
6. Piagam — exactly 4 image-only cards

Nothing after Piagam.

Home hero:

- whole video frame visible;
- `object-fit: contain` / equivalent uncropped behavior;
- no heading/copy/CTA/overlay;
- Home-only scope; other page heroes unchanged.

Treatment invariant:

- Phase 3 still owns exactly 3 `gloskin_treatment_feature_on_home=1` records;
- those exact 3 render first;
- fill deterministic 3 other published canonical Treatments;
- no duplicate IDs;
- desktop 3×2.

### 2. Promo

Exact structure:

1. `Promo Terbatas`
2. independent landscape image carousel
3. `Promo Poster`
4. second independent image-only carousel

Reuse `gloskin_promo` and the current carousel controller. No page content, closing CTA, text-heavy campaign card, thumbnail selector, or external runtime image fetch.

### 3. About

Exact structure is now normalized in the HTML wireframe:

1. Navbar
2. simple `Tentang Kami` page header; no sales CTA
3. `Tentang GLOSKIN` story — canonical About media + Page content
4. Founder — canonical founder identity/role/story + canonical portrait
5. `Visi · Misi · Nilai` — three concise managed blocks
6. end

Explicit omissions:

- Doctors
- Clinics
- Achievements
- generic closing CTA

Do not invent content when a canonical field is empty.

---

## Phase-4 staged work that may be reused

Useful work exists on:

`origin/phase4-commit3-work-20260821`

That branch is stale/divergent from current main. **Do not merge it wholesale.**

Safe candidates to selectively reuse:

- `plugin/gloskin-site-core/assets/css/gloskin-ui1-phase4.css`
- `plugin/gloskin-site-core/templates/pages/home.php`
- `plugin/gloskin-site-core/templates/pages/promo.php`
- `plugin/gloskin-site-core/templates/parts/phase4-home-selection.php`
- `plugin/gloskin-site-core/includes/class-gloskin-site-core-phase4-finalizer-admin.php`

For `config/assets.php`, apply only the Phase-4 stylesheet registration hunk to current main.

Never restore an old Kernel/plugin bootstrap/Translation implementation from that branch.

---

## Phase-4 finalizer

Reuse the staged `Gloskin_Site_Core_Phase4_Finalizer_Admin`; wire it minimally into the **current** admin Kernel.

Contract:

- explicit operator action only;
- `manage_options` + nonce;
- no automatic production execution;
- idempotent;
- second run = `already_complete`, zero mutation;
- replacement ready → media/category bound → verify → Trash obsolete → verify → complete;
- no hard delete;
- no Media Library delete.

Replacement contract:

- Promo: exactly 3 stable image-ready Phase-4 replacements is accepted within the approved 3–6 range;
- Piagam: exactly 4 stable image-ready replacements.

Coder implements/tests the finalizer but **does not run it in production**.

---

## Native Woo `product_cat` final alignment

Use the authoritative mapping docs already in this directory.

Required finalizer verification:

- Skincare: `25/25`
- Treatment: `48/48 → perawatan`
- canonical Uncategorized: `0`
- unrelated Woo products mutated: `0`

Skincare distribution:

- `produk-penunjang`: 11
- `day-cream-sunscreen`: 5
- `facial-wash`: 4
- `serum`: 4
- `toner`: 1

Preserve legitimate extra categories. Remove Uncategorized only after a valid category is attached.

Do not mutate Phase-3 private taxonomy identities, prices, stock, SKU, media/provenance, or Phase-3 state.

---

## Retirement cleanup

### Reset Demo

`Gloskin_Site_Core_Demo_Content_Reset` is still active on current main and can recreate obsolete content. Retire its active Kernel registration with the smallest safe change. Do not create a replacement reset framework.

### Plugin Phase-3 resource bundle

Phase-3 PHP migration runtime is retired, but current source still contains:

`plugin/gloskin-site-core/resources/phase3/`

Run one quick runtime-reference grep. If active dependencies are zero as expected, remove that entire plugin resource directory.

Keep:

`docs/client-feedback-phase-3/`

Do not delete production DB state, products, Treatments, attachments, or provenance.

Because cPanel deployment copies without delete-sync, report stale live-file cleanup as a one-time operator note; coder does not delete production files.

---

## Phase-5 compatibility after final Phase-4 templates

Do not rebuild Phase 5.

After Home/Promo/About are final, register only the actual new visible static strings in the existing `Gloskin_Site_Core_Translation::interface_registry()` so EN is not mixed with Indonesian.

Likely final labels include:

- `Kenapa Memilih GLOSKIN`
- `Testimoni`
- `Piagam`
- `Promo Poster`
- `Informasi promo belum tersedia.`
- final About headings used by the wireframe

Expose only textual custom fields that the final templates actually render. Never expose media IDs, URLs, booleans, ordering, prices, SKU, stock, taxonomy IDs, or migration state.

Optional high-value hardening: render current ID/EN controls server-side so switching works without JavaScript. Keep the same `Gloskin_Site_Core_Language` owner and cookie/query mechanism; no new service.

---

## Version

Current source is `0.7.182`.

After the remaining completion batch, if main has not already advanced, bump exactly one patch to `0.7.183` and synchronize only active canonical version owners/tests.

---

## Focused validation

No broad historical audit.

Run only changed-source lint plus focused contracts for:

- Home exact structure/counts/omissions;
- Promo two independent roots/omissions;
- About HTML-wireframe order/omissions;
- Phase-4 finalizer registration + no-op + no hard/media delete;
- 25/48 Woo mapping;
- Demo Reset inactive;
- plugin `resources/phase3` absent;
- Phase-4.1 preserved;
- Shop/Media Cleanup preserved;
- Phase-5 admin/frontend preserved;
- final static translation labels covered;
- `git diff --check`.

If an older test encodes a superseded Phase-2 presentation, update the stale assertion rather than restoring obsolete UI.

---

## Client-ticket source status at this refresh

| Ticket | Status |
| --- | --- |
| FB-989346 Navbar | PASS |
| FB-989348 real ID/EN | PASS functionally; optional no-JS hardening remains |
| FB-989350 Home | OPEN until final Phase-4 Home lands on main |
| FB-989352 Promo | OPEN until two-carousel Phase-4 Promo lands on main |
| FB-989354 Skincare media | PASS |
| FB-989356 retail card | PASS |
| FB-989358 About | OPEN until HTML-wireframe About lands on main |
| FB-989360 Treatment catalog/media | PASS |
| FB-989362 Home full video/no crop/no text | OPEN until final Home hero lands on main |
| FB-989364 CTA readability | PASS; final Home also removes obsolete closing CTA |
| FB-989369 breadcrumbs | PASS |

Current practical source status: **7/11 PASS, 4/11 presentation tickets still need the final Phase-4 integration.**

Production is not 11/11 merely because source is ready: the Phase-4 finalizer still requires an explicit post-deploy operator run and verification.
