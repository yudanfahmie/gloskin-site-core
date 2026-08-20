# Gloskin Client Feedback — Phase 3 Content & Migration Mapping

**Scope:** FB-989354 (Skincare product assets) + FB-989360 (Treatment original assets)  
**Baseline:** Phase 2 `main` at `ab8c6839a8aaf19faadceeff4e336b5cb994db09` / plugin 0.7.171  
**Purpose:** remove content-selection guesswork before engineering. This document and the sibling JSON manifests are the target contract; execution-time fresh WordPress/Woo inventory remains the current-state truth.

## 1. Executive decision

Phase 3 is a **data/content reconciliation migration**, not another presentation rewrite. The migration must be deterministic, resumable, idempotent, and safe around WooCommerce history.

Execution sequence:

`preflight → fresh inventory → dry-run plan → media reconcile → 3A skincare → concerns/paths → 3B Woo Treatment Products → 8 informational Treatment routes → Treatment page media → verify → complete`

Second run must be a no-op / `already_complete`.

### Action vocabulary

| Action | Meaning |
|---|---|
| `REUSE` | Object/media already matches target; write nothing. |
| `UPDATE` | Deterministically update an existing canonical record. |
| `CREATE` | Create a resolved target only when missing. |
| `SUPERSEDE` | Trash an explicitly proven obsolete/synthetic record; never hard-delete Woo products. |
| `BIND_PRESENTATION_MEDIA` | Bind a selected client image through the existing page/path media owner. |

## 2. Critical Treatment domain boundary

Do **not** flatten the client folder hierarchy into WordPress taxonomy/CPT structure.

There are three distinct existing domains:

1. **`gloskin_treatment` CPT** — public informational Treatment/category routes. Phase 3 target: **8 umbrella records**, not 48 procedure pages.
2. **`gloskin_consultation_path`** — discovery/finder paths. Preserve **exactly four stable slugs**.
3. **WooCommerce `product` + `gloskin_product_family=treatment`** — actual Treatment Product/service entities. Phase 3 target: **48 resolved procedure/service identities**.

WooCommerce remains the only commerce owner.

## 3. Current-state handling

The feedback-time diagnostic exported `gloskin_treatment` with zero records. That is useful forensic evidence only; it is not safe to assume production is still identical.

Before any mutation, generate a fresh inventory of:

- Woo parent products and variations: IDs, status, title, slug, SKU, product type, price, stock, product categories, family/concern terms, featured/gallery attachments;
- `gloskin_treatment` records and their meta/relationships;
- `gloskin_product_family`, `gloskin_concern`, and `gloskin_consultation_path` terms/meta;
- attachments with source/provenance/hash metadata;
- product references relevant to order safety. **Never rewrite historical order line items.**

Identity matching order: explicit provenance/source meta → verified exact SKU → exact normalized title/slug → otherwise create only a resolved target. **No fuzzy destructive matching.**

Existing sample-product data is not commercial truth. Only records explicitly proven synthetic by existing `_gloskin_sample_*` provenance may be superseded, and supersede means **Trash**, not hard-delete.

## 4. FB-989354 — Skincare target map

Client supplied the image pack; this mapping resolves **25 canonical product identities** and deliberately leaves 5 ambiguous identities on HOLD. Runtime product records remain Woo-owned.

| Canonical product | Category | Primary client asset |
|---|---|---|
| Acne Day Protection Cream | Day Cream / Sunscreen | `ACNE DAY PROTECTION POT.png` |
| Glam Air Cushion | Support | `NATURAL CUSHION.png` |
| Bio Calmskin | Support | `BIO CALM SKIN.png` |
| Brightening Face Wash | Facial Wash | `BRIGHTENING FACE WASH.png` |
| C Power Silk Gel | Support | `C POWER SILK GEL.png` |
| Clear Xpert Serum | Serum | `CLEAR XPERT SERU,.png` |
| Essence Bio Moisturizer | Support | `ESSENCE BIO MOIST.png` |
| Gloskin Glow Face Tonic | Support | `GLOW FACE TONIC 11.png` |
| Glow Face Tonic Pads | Support | `GLOW FACE TONIC PADS.png` |
| Glowing Facial Wash | Facial Wash | `GLOWING FACIAL WASH.png` |
| Glowing White Sunscreen | Day Cream / Sunscreen | `GWS.png` |
| Hydra Xpert Serum | Serum | `HYDRA XPERT SERUM.png` |
| Hydro Fresh Foaming With Chamomile Extract | Facial Wash | `HYDRO FRESH.png` |
| Brightening Loose Powder | Support | `LOOSE POWDER.png` |
| Skin Fresh Toner | Toner | `SKIN FRESH.png` |
| Sense Cleansing Milk | Support | `Sense-Cleansing-Milk-1-300x300.png` |
| Transforming Night Cream | Support | `TNC.png` |
| Acne Facial Cleanser | Facial Wash | `acne facial cleanser.png` |
| Acne Prone Gel | Support | `acne prone gel.png` |
| Cysteamine Advance Plus | Support | `cysteamine.png` |
| Day Protection Cream | Day Cream / Sunscreen | `day protection cream.png` |
| Flawless High Defences 50 | Day Cream / Sunscreen | `flawless.png` |
| Rejuve Xpert Serum | Serum | `rejuve expert.png` |
| Ultimate Whitening Cream | Support | `ultimate.png` |
| Whitening Sunscreen | Day Cream / Sunscreen | `whitening sunscree.png` |

Exact alternates, source URLs, observed-price fallbacks, and migration rules live in `manifests/skincare-products.json`.

### Skincare HOLD — do not guess

- `AZ EXPERT.png` + `az xpert.png`
- `glam gold serum.png`
- `Skin-Fresh-Facial-Wash-1.png`
- `DSC02911.png`
- `Untitled design (47).png`

These HOLDs are **record-level**, not migration-wide blockers. Skip them, continue all resolved work, and report them.

`day protection cream.psd` is a source/design file and must never be imported to the Media Library.

For **Glam Air Cushion**, product identity is resolved but the shade/variation model is not. Reuse a verified current Woo variation model if one exists; otherwise use a simple product + mapped gallery. Do not invent shade variations.

### Price policy

Preserve a verified current real Woo price. The skincare JSON contains dated official-store observations for several products only as a create-if-missing fallback. Do not overwrite a newer verified price. Do not invent SKU, price, ingredients, BPOM number, size, stock, or claims.

## 5. FB-989360 — 48 Woo Treatment Product targets

The client folder is evidence for service identity and original media, **not** a database hierarchy. The following normalized target set is authoritative; exact paths/concerns are in `manifests/treatment-catalog.json`.

| Group | Canonical Treatment Products |
|---|---|
| Aging | Botox |
| Body Contour & Wellness | Hollywood Body Sculpting; Lymphatic Body; We Go Slim |
| Dermatolift / Koreksi | Brow Lift; Buccal Contour; Eyebag Removal; SMAS Lift; Upper / Lower Eyelid; Lipoma Removal |
| Facial Therapy | Derma Oxy Facial Therapy; Glowing Face Therapy; Hydra Glowing Luxury Facial Therapy; Lhala Peel; Luxury Face Therapy; Lymphatic Face Therapy; Oxy Jet Light; Skin Barrier Facial Therapy; Triple Glowing |
| Flek & Pigmentasi | Laser 4G; Mesoglow; Pico Laser; Xelarederm |
| Hair Restoration | Exxohair; PRP Hair; Hair Transplant |
| Jerawat & Bekas Jerawat | Gloskin Acne Advance Peeling; Acne Spot Injection; Cautery; Injeksi Keloid; Korean Comedo Glowing Peel; Premium PRP; Rejuran HB; Sylfirm X; VIP Light |
| Kontur & Laxity Wajah | Hollywood Face Sculpting; Juvederm; Thread Lift; Ultralift |
| Skin Quality & Barrier | 5GF Glo Booster; Croma Rich; Ellanse; Exxoskin; Juvelook; Nucleofil; Profhilo; Glowing Salmon DNA; Skinvive |

Important dedupe decisions:

- `Rejuran HB` appears in more than one client folder. Create/reuse **one canonical Woo product**, assign multiple concerns, and reuse one media identity.
- identical `GLOSKIN 0203064.JPG` appears under Premium PRP and Exxoskin; SHA dedupe to one attachment, while Exxoskin uses `EXXOSKIN -1.jpg` as primary.
- identical `GLOSKIN 0203076.JPG` appears under Exxohair and PRP Hair; SHA dedupe to one attachment; their selected primary images remain distinct.
- named patient/before-after photos are not generic landing-page imagery. Keep them alternate-only unless a future explicit consent/content instruction says otherwise.

### Treatment price/copy policy

Client supplied original media but no approved service price sheet. Preserve a verified existing price. A newly created Treatment Product without an approved price must remain unpriced/non-purchasable or draft according to the existing Woo contract.

Do not invent duration, downtime, contraindications, guaranteed outcomes, pricing, or clinical claims. For client-only identities without an exact authoritative detail page, use the conservative group copy from the manifest rather than hallucinating service-specific claims.

## 6. Concern terms

Reuse current concern slugs:

`active-acne`, `acne-marks`, `dullness`, `pigmentation`, `large-pores`, `oily-skin`, `dry-dehydrated`, `sensitivity-redness`, `fine-lines`, `uneven-texture`.

Upsert only these eight additional terms:

| Slug | Label |
|---|---|
| `facial-laxity` | Kekenduran / Elastisitas Wajah |
| `facial-contour` | Kontur Wajah |
| `under-eye` | Area Mata |
| `skin-lesions` | Lesi / Benjolan Kulit |
| `scars-keloid` | Bekas Luka / Keloid |
| `hair-loss` | Kerontokan Rambut |
| `hair-density` | Kepadatan Rambut |
| `body-contour` | Kontur Tubuh |

## 7. Eight informational `gloskin_treatment` routes

Do not create one CPT route per Woo procedure. Reconcile exactly these eight umbrella records:

| Slug | Title | Client featured asset | Home |
|---|---|---|---|
| `jerawat-bekas-jerawat` | Jerawat & Bekas Jerawat | `JERAWAT & BEKAS JERAWAT/ACNE ADVANCE PEELING/PEEL.png` | Featured |
| `flek-pigmentasi` | Flek & Pigmentasi | `FLEK & PIGMENTASI/PICO LASER/PICO LASER.jpg` | Featured |
| `aging-kontur` | Aging & Kontur Wajah | `KONTUR & LAXITY WAJAH/ULTRALIFT/ULTRALIFT.png` | Featured |
| `skin-quality-barrier` | Skin Quality & Barrier | `KUSAM, SKIN BARIER & QUALITY/5GF GLO BOOSTER/5gf.png` | — |
| `facial-therapy` | Facial Therapy | `FACIAL THERAPY/HYDRA GLOWING LUXURY FACIAL THERAPY/GLOSKIN 0202982.JPG` | — |
| `hair-restoration` | Hair Restoration | `HAIR RESTORATION/HAIR PRP/DSCF3153.JPG.png` | — |
| `body-contour-wellness` | Body Contour & Wellness | `BODYCONTOUR & WELLNESS/HOLLYWOOD BODY SCULPTING/EXILIS BODY.png` | — |
| `dermatolift-koreksi-estetika` | Dermatolift & Koreksi Estetika | `DERMATOLIFT/SMASLIFT/SMASLIFT.png` | — |

Only the first three receive `gloskin_treatment_feature_on_home=1`, matching the existing Home cap of three.

## 8. Treatment landing page + four discovery paths

### Landing copy

**Hero title:** Treatment  
**Hero copy:** Temukan treatment Gloskin berdasarkan kebutuhan kulit, rambut, dan kontur. Setiap pilihan sebaiknya dibahas melalui konsultasi agar sesuai dengan kondisi dan tujuan perawatan.

**Discovery heading:** Temukan Perawatan yang Tepat  
**Discovery copy:** Pilih fokus utama dan keluhan yang ingin Anda eksplorasi. Hasil membantu menyiapkan pilihan sebelum konsultasi dan bukan diagnosis medis.

**Disclaimer:** Informasi ini membantu eksplorasi pilihan dan bukan diagnosis medis. Rencana treatment ditentukan setelah konsultasi dan evaluasi oleh tim medis Gloskin.

**Landing hero media:** `FACIAL THERAPY/DERMA OXY FACIAL THERAPY/GLOSKIN 0202993.JPG`.

Bind this through the existing canonical hero/page media owner; **never hard-code an evidence path in the public template**.

### Preserve exactly four path slugs

| Stable slug | Display label | Primary image |
|---|---|---|
| `acne-focus` | Jerawat & Bekas Jerawat | `.../ACNE ADVANCE PEELING/PEEL.png` |
| `brightening-focus` | Flek & Pigmentasi | `.../PICO LASER/PICO LASER.jpg` |
| `anti-aging-focus` | Aging & Kontur | `.../ULTRALIFT/ULTRALIFT.png` |
| `skin-health-focus` | Skin Quality & Barrier | `.../5GF GLO BOOSTER/5gf.png` |

Exact copy and concern sets are in `manifests/treatment-page-media.json`.

## 9. One-shot migration contract

Use the repository's existing bounded migration/checkpoint pattern instead of inventing another framework.

Requirements:

- persistent state + lock + manifest fingerprint;
- `start/preflight` performs **zero content mutations**;
- produce an explicit fresh current→target dry-run plan before mutation;
- resumable checkpoints with per-step audit counts and old→new mappings;
- WP/Woo APIs for writes; no direct SQL content mutation;
- media provenance + SHA-256 dedupe;
- capability + nonce for explicit admin trigger;
- unresolved item = skip that record/field and report; never fail all resolved work;
- preserve editor-owned clinic/doctor relationships unless a manifest explicitly owns that relationship;
- no historical order mutation;
- no hard-delete of Woo products.

Expected second-run result is equivalent to:

```json
{"created":0,"updated":0,"trashed":0,"media_imported":0,"status":"already_complete"}
```

## 10. Definition of done

Phase 3 is ready when the migration verifies all of the following:

- 25 resolved skincare identities reconciled; the five HOLD identities untouched;
- 48 Woo Treatment Product identities reconciled with `family=treatment` and manifest concerns;
- exactly 8 informational `gloskin_treatment` umbrella records reconciled;
- exactly four stable consultation path slugs preserved and mapped copy/media applied;
- selected Treatment landing hero uses original client media through the canonical owner;
- duplicate binaries are reused by hash;
- no fabricated prices/SKUs/medical claims;
- Phase 1 + Phase 2 focused contracts remain green;
- canonical harness has **no new failures** relative to the captured pre-change baseline;
- second run is a no-op.

## 11. Machine-readable sources

Engineer must read these files together:

- `manifests/migration-manifest.json` — execution/state/safety contract
- `manifests/skincare-products.json` — 25 resolved skincare products, alternates, sources/pricing fallbacks, HOLDs
- `manifests/treatment-catalog.json` — 48 Woo Treatment Products + concerns + exact primary client media + 8 informational routes
- `manifests/treatment-page-media.json` — Treatment landing copy/media + four path copy/media
- `manifests/unresolved.json` — explicit HOLD policy; these entries must never be guessed

The raw client feedback folders remain evidence only and must not be modified.
