# Task — Treatment Consultation + WooCommerce Discovery

**Status:** implementation-ready owner brief  
**Repository:** `yudanfahmie/gloskin-site-core`  
**Branch policy:** `main` only  
**Baseline when this task was written:** `d5f459f4013fe7ad22846cae7ee2fba4fc586edd`, plugin `0.7.61`  
**Implementation rule:** always pull the actual latest `main` before editing; the baseline above is informational, not a pinned implementation target.

## 1. Why this task exists

Client direction for the Treatments experience has evolved beyond a static directory:

1. `/treatments/` should present **four visually soft circular entry cards**.
2. Selecting one entry starts an **inline treatment consultation/questionnaire**.
3. Questionnaire answers represent user-reported cosmetic **concerns**.
4. As concerns are selected/scored, matching **purchasable treatment products** appear below in a responsive WooCommerce product grid.
5. Those treatment products must remain normal WooCommerce products so price, purchasability, cart, checkout, orders, payments and account behavior remain native WooCommerce.
6. Admin must not be forced to manage Skincare and Treatment products in separate databases or confusing duplicate CRUD systems.
7. Concern-to-treatment-product mapping must be friendly enough for non-technical admins, preferably drag-and-drop, while persistence remains precise and WordPress-native.
8. A deterministic **demo/sample dataset** is required so the complete experience is visible immediately during staging/client review, including **at least 13 questionnaire questions whose display order is randomized once per page load/consultation initialization**.

This task is a new owner requirement. It supplements the current canonical documentation. When implemented, update the canonical docs listed in section 18 in the same coherent implementation commit.

---

## 2. Final architecture decision

### 2.1 Keep three concepts deliberately separate

Do not overload the word “treatment.” There are three different objects:

1. **`gloskin_treatment` CPT** — existing informational treatment/category records and routes under `/treatments/{slug}/`. The repository currently requires eight approved records. **Do not replace, reduce, or repurpose these eight records.**
2. **Consultation Path** — the four circular discovery entries on `/treatments/`. They are lightweight questionnaire entry points, not purchasable products and not replacements for the eight informational treatment records.
3. **WooCommerce Treatment Product** — a normal Woo `product` classified internally as family `treatment`. This is what can be added to cart and checked out.

The Treatments Hub therefore becomes:

```text
Hero / intro
  ↓
4 Consultation Path circles
  ↓ click
Inline questionnaire
  ↓ answers update score
Recommended Treatment Product grid (Woo)
  ↓
Native Add to Cart / PDP / Checkout
  ↓
Existing informational treatment sections / 8 treatment records remain available
```

### 2.2 WooCommerce remains sole commerce authority

Never create:

- `product_perawatan` CPT;
- a custom treatment-product table;
- a shadow price/stock field;
- a second product editor;
- custom cart/checkout/order/payment logic;
- duplicated variation logic.

Treatment products are standard Woo products/variations and use existing Gloskin Woo presentation + Add-to-Cart behavior.

### 2.3 No new bootable service

This feature must fit the current modular monolith and eight-service ceiling.

Canonical ownership:

- schema/taxonomies/private question entity → `ContentService`;
- public page context/rendering → `TemplateService` + current template helpers;
- Woo product querying/presentation → `WooCommerceAdapter`;
- admin menu/workspace/mutations → existing `AdminService`;
- CSS/JS registry/enqueue → existing `AssetService`;
- structural version upgrade only when necessary → existing `LifecycleService`.

A small non-service demo importer/helper is acceptable, following the repository’s current sample-import pattern, but do not register another bootable service.

---

## 3. Stable internal vocabulary and user-facing labels

Internal identifiers stay English and stable. User-facing labels are translatable and may appear in Indonesian.

| Concern | Stable internal key | Default Indonesian UI label |
| --- | --- | --- |
| product family taxonomy | `gloskin_product_family` | Jenis Produk |
| family term | `skincare` | Skincare |
| family term | `treatment` | Perawatan |
| concern taxonomy | `gloskin_concern` | Keluhan |
| consultation-path taxonomy | `gloskin_consultation_path` | Jalur Konsultasi |
| questionnaire CPT | `gloskin_question` | Pertanyaan Konsultasi |
| admin workspace | stable code slug such as `gloskin-treatment-consultation` | Konsultasi Perawatan |

Do not translate database keys/slugs per locale. Wrap visible labels in the plugin text domain. Do not solve naming with broad/global `gettext` interception.

---

## 4. Data model

### 4.1 Product family — private taxonomy on Woo products

Register `gloskin_product_family` for `product`.

Recommended registration characteristics:

- non-public;
- no rewrite;
- no public query var;
- no standalone taxonomy submenu;
- exactly the stable terms `skincare` and `treatment` for this feature.

Purpose: administrative/product-domain classification only. **Do not use Woo `product_cat` as the family discriminator**, because product categories already own merchandising/category IA and can change independently.

Backward compatibility rule:

- a product explicitly assigned `treatment` is a Treatment Product;
- existing legacy products with no family assignment are treated as Skincare for admin filtering/backward compatibility;
- when a product is subsequently saved through the supported product editor and no family is selected, default it to `skincare`;
- do not run a broad destructive backfill merely to populate taxonomy relationships on every existing product.

### 4.2 Concern — private taxonomy on Woo products

Register `gloskin_concern` for `product`.

This is the correct model for “keluhan”, not a comma-separated custom field, because:

- one treatment product can address multiple concerns;
- one concern can map to multiple treatment products;
- concerns are reusable across questions and products;
- taxonomy relationships are native WordPress many-to-many persistence;
- filtering/counting is straightforward;
- there is no custom table.

Recommended term examples are in section 11.

**Canonical mapping direction:** Woo product ↔ `gloskin_concern` term relationship (`wp_term_relationships` through WordPress taxonomy APIs).

Do not also persist a duplicate concern→product-ID mapping option/meta array.

### 4.3 Consultation Path — private taxonomy

Register `gloskin_consultation_path`, associated with `gloskin_question` for native grouping and term lifecycle.

A path term may have small presentation/recommendation term meta:

- optional image/attachment ID;
- display order integer;
- default/baseline concern term IDs.

The four paths are **discovery entry points**, not the eight `gloskin_treatment` records.

If fewer than four valid path terms exist, the consultation feature must fail gracefully and the existing Treatments Hub content remains usable. Do not invent production path names automatically outside the explicit demo importer.

### 4.4 Questionnaire question — private CPT

Register private CPT `gloskin_question`.

Recommended behavior:

- `public=false`;
- no rewrite / public route;
- `show_ui=true` but `show_in_menu=false` so the record can still use native WordPress editing while the sidebar remains compact;
- post title = question text;
- `publish` = active question; `draft` = inactive question;
- no separate “active” custom field required;
- consultation path assignment uses `gloskin_consultation_path` taxonomy;
- answer options use one registered post-meta array.

Recommended answer meta shape:

```php
array(
    array(
        'label'      => 'Sering',
        'concern_id' => 123,
        'weight'     => 3,
    ),
)
```

Boundary rules:

- label: plain text, bounded length;
- `concern_id`: existing `gloskin_concern` term ID only;
- weight: small integer, recommended `1..3`;
- reasonable max answer count per question (for example 12);
- missing/deleted concern IDs are ignored safely at render/scoring time and surfaced as an admin readiness warning, never fatal.

Do not create an “answer” CPT. Answers are bounded structured children of one question and belong in that question’s registered meta.

---

## 5. Compact admin information architecture

### 5.1 Product menu footprint

Do **not** add separate `Produk Skincare` and `Produk Perawatan` sidebar submenus.

The feature’s custom footprint under Woo Products should be only one new management destination:

```text
Produk (existing Woo product parent)
├── Semua Produk        (native Woo list)
├── Tambah Produk       (native Woo add screen)
└── Konsultasi Perawatan (one Gloskin workspace)
```

Important: do not break/remove essential WooCommerce-owned screens such as product categories/attributes if Woo currently exposes them. The goal is that this feature adds only **one** custom submenu, not that Gloskin rewrites WooCommerce’s complete admin IA.

Where safe, make the visible Product/All Products/Add New labels language-friendly through the product/menu owner only. Internal route slugs remain native Woo.

### 5.2 All Products family filter

On native Woo `edit.php?post_type=product`, add one compact family filter using the normal list-table/query lifecycle:

```text
Jenis Produk: Semua | Skincare | Perawatan
```

A compact select or equivalent small filter control is acceptable if it integrates more cleanly than injecting another row of status links.

Rules:

- `Perawatan` = explicit `gloskin_product_family:treatment`;
- `Skincare` = explicit `skincare` plus legacy/unclassified products;
- `Semua` = native full product list;
- do not create duplicate custom product list tables.

### 5.3 Product editor family field

Use supported Woo admin hooks in the existing product editor to expose one simple field:

```text
Jenis Produk
(•) Skincare
( ) Perawatan
```

Persist through `wp_set_object_terms()` / Woo-supported save hook. Default new/unclassified save to `skincare`.

Do not create duplicate family meta.

If the site is using a Woo Product Block Editor that does not support the current classic hook surface, do not DOM-hack React. Keep the list/mapping feature functional and document that editor-specific limitation.

### 5.4 One consultation workspace, internal tabs

`Produk → Konsultasi Perawatan` should be one AdminService-owned screen with four internal tabs:

```text
Ringkasan | Keluhan | Pertanyaan | Pemetaan Produk
```

Use the existing Gloskin admin visual language where practical. No new admin framework.

#### Ringkasan

Show high-value readiness only:

- 4 consultation path cards;
- number of Treatment Products;
- number of concerns;
- published question count;
- **unmapped Treatment Product count**;
- invalid/orphan question-answer mappings count;
- explicit demo import card while demo bundle is available/unconsumed.

This is the admin’s “is this consultation ready?” view.

#### Keluhan

Friendly term management for `gloskin_concern`:

- name;
- stable slug;
- number of mapped Treatment Products;
- number of questionnaire answers referencing it;
- create/rename capability.

Do not silently delete a concern while it is still referenced. Either block deletion with a clear message/count or require references to be removed first.

#### Pertanyaan

Compact list of `gloskin_question`:

- question text;
- publish/draft state;
- consultation path(s);
- answer count;
- mapping/readiness indicator;
- Add/Edit links to the native hidden CPT edit screen.

Do not build a second custom rich editor when native WordPress editing is sufficient.

#### Pemetaan Produk

This is the high-impact management surface described in section 8.

---

## 6. Public Treatments Hub UX

Do not replace the current `/treatments/` page architecture wholesale.

Add one new consultation/discovery block near the top of `templates/pages/treatments.php`, after the page intro/orientation area and before the long informational treatment directory where it fits naturally.

The existing eight informational `gloskin_treatment` records continue to render lower on the page.

### 6.1 Initial state

Render four circular path cards in one responsive group.

Each card is a real `<button type="button">` (or an equally accessible control), not a clickable `<div>`.

Desired presentation:

- soft circular media/surface;
- concise label below/inside;
- restrained hover/focus lift;
- no giant pill buttons;
- adapts naturally from four columns to fewer columns without horizontal overflow.

### 6.2 Path selection

On click:

- select/highlight the path;
- reveal the inline questionnaire below the circles;
- keep focus behavior understandable;
- scroll only if needed and do not hard-jump the page unnecessarily;
- use a short CSS transition, with `prefers-reduced-motion` respected.

No modal is required. Inline is simpler, easier to access, and lets recommendations appear naturally below.

### 6.3 Questionnaire interaction

Default interaction: one question at a time.

Recommended elements:

- progress (`1 / 13` etc.);
- question text;
- answer options as buttons/cards;
- Back;
- Restart/Change focus.

Selecting an answer:

1. records the answer only in frontend memory;
2. adds the mapped concern weight to the current score;
3. updates the recommendations below;
4. advances smoothly to the next question.

Do **not** persist answers to WordPress/database/cookies/server logs as part of this feature. They are potentially health-adjacent preference data and are not required for commerce. This version is intentionally stateless/private.

### 6.4 Random question order

The demo/current requirement is **minimum 13 published questions**.

Randomize the eligible question array **once per page load / consultation controller initialization** using a normal client-side Fisher–Yates shuffle.

Do not use database `ORDER BY RAND()`.

Do not reshuffle after every answer; the user’s active run must remain stable.

If fewer than 13 published valid questions exist in production, render what is valid and show an admin readiness warning; never fabricate medical/cosmetic questions in PHP.

---

## 7. Recommendation model — simple deterministic scoring

No AI/recommendation service is required.

Use deterministic concern scoring:

1. selecting a consultation path may add a small baseline score to the path’s configured baseline concerns;
2. each selected questionnaire answer adds `weight` to one concern;
3. each Treatment Product is related to zero or more `gloskin_concern` terms;
4. product score = sum of the current concern scores for concerns assigned to that product;
5. include products with score > 0;
6. sort by score descending, then a stable Woo fallback (`menu_order`, then title/ID);
7. de-duplicate product IDs;
8. render a reasonable first result set (for the current small catalog, up to 6–8 is enough).

This is why **mapping order does not need to be persisted**. Ranking comes from questionnaire score, not from a second ordered product-ID array. This preserves one canonical taxonomy relationship and avoids dual-write state.

If no mapped products match, show a deliberate empty state and keep consultation/contact pathways available.

---

## 8. Concern ↔ Treatment Product mapping UI

### 8.1 Canonical persistence

The mapping is only the product’s `gloskin_concern` term relationships.

Do not store the same mapping in:

- a global option;
- term meta containing product IDs;
- product meta containing concern IDs;
- a custom table.

### 8.2 Friendly admin enhancement

Render:

- searchable pool of **Treatment Products only**;
- concern buckets/cards;
- mapped product chips inside each concern;
- clear count per concern;
- “Belum dipetakan” filter/count;
- remove/unassign affordance.

Drag-and-drop is progressive enhancement.

For long-term stability, the actual form state should remain a native checkbox/multi-select relationship model that JavaScript enhances. Dragging a product into/out of a bucket changes the corresponding checkbox/relationship state. A single **Simpan Pemetaan** action posts the normalized mapping.

This yields:

- keyboard/no-JS fallback;
- no custom AJAX requirement;
- one nonce/capability boundary;
- one persistence owner;
- visually satisfying DnD when JS is available.

A product may belong to multiple concern buckets.

On save:

- capability: `manage_woocommerce` (or the narrow existing Woo product-management capability if already standardized in repo);
- nonce verification;
- accept only real Woo product IDs explicitly classified as Treatment Products;
- accept only existing `gloskin_concern` term IDs;
- use native taxonomy APIs;
- escape output on render.

---

## 9. Treatment product grid and commerce behavior

The consultation result grid must reuse existing Gloskin/Woo product-card conventions rather than inventing a new commerce card.

Required:

- canonical Woo product title/image/price;
- simple-product Add to Cart uses existing native/AJAX path;
- variable/non-directly-purchasable product follows the existing product-card/PDP/Quick-Add rules rather than creating a second variation controller;
- current custom Add-to-Cart loader remains the only presentation owner;
- cart fragments/count/success remain Woo-owned;
- unavailable/non-purchasable products show the existing native-safe state.

Treatment products may be Woo `virtual` products where appropriate, but Gloskin does not invent booking/scheduling fulfillment in this task.

The demo importer may set treatment sample products to `catalog_visibility=hidden` so they are purchase-ready through Consultation/PDP without polluting the existing skincare-oriented Shop. Production editors may change Woo catalog visibility normally later.

---

## 10. Asset/runtime strategy

Prefer one small feature runtime loaded only where it is used:

- Treatment consultation JS only on Treatments Hub;
- feature CSS either in the current presentation owner when genuinely tiny or conditionally enqueued as one treatment-consultation stylesheet through `AssetService`;
- no frontend framework/dependency;
- no public `wp_ajax_nopriv_*` endpoint;
- no polling;
- no analytics/telemetry payload;
- no custom cache.

The current expected catalog is small. Render the normalized Treatment Product candidate set with the Treatments page and filter/score client-side. Do **not** introduce another REST endpoint only for theoretical scale.

If a future measured catalog becomes large enough that initial payload is material, a paginated/read-only REST recommendation endpoint may be considered then in `WooCommerceAdapter`.

---

## 11. Deterministic demo/sample dataset

Create a narrow staging/demo bundle such as:

`migration-source/gloskin-treatment-consultation-demo-v1/`

The exact file layout may follow the existing sample bundle style, but do not build a generic migration framework.

### 11.1 Demo paths — exactly 4

Stable sample slugs and labels:

1. `acne-focus` — **Jerawat**
2. `brightening-focus` — **Brightening**
3. `anti-aging-focus` — **Anti-Aging**
4. `skin-health-focus` — **Skin Health**

Suggested baseline concerns:

- `acne-focus` → active acne, acne marks, large pores, oily skin;
- `brightening-focus` → dullness, pigmentation, uneven texture;
- `anti-aging-focus` → fine lines, uneven texture, dry/dehydrated;
- `skin-health-focus` → dry/dehydrated, sensitivity/redness, oily skin, large pores.

These are **synthetic demo discovery labels**, not the approved eight production treatment-category names.

### 11.2 Demo concerns — minimum 10

Recommended stable slugs:

1. `active-acne` — Jerawat Aktif
2. `acne-marks` — Bekas Jerawat
3. `dullness` — Kulit Kusam
4. `pigmentation` — Flek & Pigmentasi
5. `large-pores` — Pori Besar
6. `oily-skin` — Kulit Berminyak
7. `dry-dehydrated` — Kulit Kering / Dehidrasi
8. `sensitivity-redness` — Sensitif / Kemerahan
9. `fine-lines` — Garis Halus
10. `uneven-texture` — Tekstur Tidak Merata

### 11.3 Demo questions — at least 13 published questions

Seed at least these 13 cosmetic-preference questions. Wording may be polished but must stay non-diagnostic:

1. Apa keluhan utama yang paling ingin Anda fokuskan?
2. Bagaimana kondisi minyak kulit Anda sepanjang hari?
3. Seberapa sering komedo atau sumbatan pori menjadi perhatian?
4. Seberapa sering jerawat aktif muncul?
5. Seberapa besar bekas jerawat menjadi perhatian Anda?
6. Bagaimana tampilan kecerahan warna kulit Anda saat ini?
7. Apakah flek atau noda gelap menjadi perhatian?
8. Bagaimana kondisi tekstur permukaan kulit Anda?
9. Seberapa terlihat pori-pori pada area wajah Anda?
10. Seberapa sering kulit terasa kering atau tertarik?
11. Seberapa mudah kulit terasa sensitif atau tampak kemerahan?
12. Apakah garis halus menjadi perhatian Anda saat ini?
13. Hasil perawatan apa yang paling ingin Anda prioritaskan?

Each question must have 2–6 concise answer options. Every meaningful option maps to an existing demo concern with weight `1..3`; neutral answers may contribute zero/no concern.

The importer must produce a valid pool of at least 13 published questions so the random-order requirement is visible immediately.

### 11.4 Demo Treatment Products — 8 Woo simple virtual products

Recommended synthetic products/source IDs:

| Source | Name | Example mapped concerns |
| --- | --- | --- |
| `treat-demo-001` | Acne Clarifying Facial | active-acne, oily-skin |
| `treat-demo-002` | Deep Pore Facial | large-pores, oily-skin, uneven-texture |
| `treat-demo-003` | Brightening Facial | dullness, pigmentation |
| `treat-demo-004` | Pigmentation Care Treatment | pigmentation, dullness |
| `treat-demo-005` | Rejuvenation Facial | fine-lines, uneven-texture |
| `treat-demo-006` | Hydration Booster | dry-dehydrated, sensitivity-redness |
| `treat-demo-007` | Skin Barrier Therapy | sensitivity-redness, dry-dehydrated |
| `treat-demo-008` | Texture Renewal Treatment | uneven-texture, acne-marks, large-pores |

Demo product requirements:

- Woo simple products;
- virtual=true;
- synthetic/demo price values clearly documented as non-production truth;
- family=`treatment`;
- catalog visibility may default `hidden` for demo safety;
- purchasable through the consultation grid/PDP;
- SKU/source IDs deterministic and collision-checked;
- normal Woo description/short-description ownership;
- use existing product-image/fallback rules.

Do not make successful demo import depend on a remote image download. If deterministic staging imagery is included, follow `CONTRIBUTING.md` staging editorial media policy. A neutral Gloskin placeholder is an acceptable fallback.

---

## 12. Demo importer flow — low effort, safe, idempotent

### 12.1 Entry point

Do not add another permanent sidebar submenu.

Place the demo import card/button inside:

`Produk → Konsultasi Perawatan → Ringkasan`

Only show it when:

- user can `manage_woocommerce`;
- environment is `local`, `development`, or `staging` (`wp_get_environment_type()` where available);
- the demo bundle has not been successfully consumed.

On `production`, do not expose/run synthetic sample import.

### 12.2 Implementation form

Prefer one explicit nonce-protected `admin-post.php` action because the dataset is small. AJAX/checkpoint machinery is not required unless the implementation genuinely needs remote media downloads or request-length resilience.

A small non-service importer/helper may coordinate the one-shot import. Do not register it in Kernel as a service.

### 12.3 Deterministic phases

Run in this order:

1. validate bundle shape/version/source IDs;
2. ensure product-family structural terms exist (`skincare`, `treatment`);
3. upsert consultation-path demo terms;
4. upsert concern demo terms;
5. upsert 13+ question records + answer meta + path relationships;
6. upsert 8 Woo Treatment Products using Woo CRUD;
7. assign product family `treatment`;
8. assign canonical product↔concern taxonomy relationships;
9. verify expected counts, relationships, source identities and Woo purchasability;
10. only after verification, mark demo bundle consumed.

### 12.4 Provenance and collision behavior

Use narrow sample provenance markers, analogous to the existing sample-product importer.

Requirements:

- deterministic source ID per product/question/term;
- do not take over a non-demo product just because a name/slug looks similar;
- Woo SKU collision with unrelated data = stop with clear error;
- if a term slug already exists but is not demo-owned, reuse it only without overwriting the merchant’s name/description unless explicitly safe;
- rerun after partial failure must converge rather than duplicate objects;
- after successful verification, future runs are locked/hidden unless the owner explicitly changes the bundle/version.

A failed partial run may leave objects already safely created; idempotency is the recovery mechanism. Do not add a generic rollback platform.

### 12.5 Demo state

A small dedicated one-shot state option is acceptable for this temporary importer (status/error/bundle-version/consumed timestamp). Do not put demo import state into `gloskin_site_core_settings`, and do not create a generic migration registry.

---

## 13. Admin readiness/edge cases

The Consultation overview should make bad mapping obvious before the client finds it.

Surface compact counts/warnings for:

- Treatment Products with **zero concerns**;
- concerns with zero mapped Treatment Products;
- published questions with zero valid answers;
- question answers referencing deleted/missing concern IDs;
- fewer than 4 valid consultation paths;
- fewer than 13 published questions;
- Treatment Product IDs that are no longer purchasable/available.

Do not auto-repair these states by inventing mappings. Admin should see and fix them.

Deleting a concern/path while referenced should be blocked or require explicit cleanup first. Renaming labels is safe because relationships use IDs and internal slugs remain stable where practical.

---

## 14. Privacy, medical-content and safety boundary

This questionnaire is **discovery/merchandising guidance**, not diagnosis.

Required:

- no medical diagnosis claim;
- no “you have X disease” output;
- no user name/email/phone collection inside questionnaire;
- no questionnaire answer persistence in this task;
- no automatic treatment prescription language;
- include a concise presentation disclaimer such as “Hasil ini membantu eksplorasi pilihan dan bukan diagnosis medis.”;
- existing clinic/contact consultation path remains available.

Do not hard-code efficacy, contraindication, doctor recommendation or other factual medical claims into demo logic.

---

## 15. Accessibility and progressive enhancement

Minimum code contract:

- consultation path cards are keyboard-operable native controls;
- questionnaire answer choices are native buttons/radios, not div click targets;
- clear focus-visible state;
- progress is understandable to assistive tech;
- result updates use a restrained `aria-live` announcement where useful, not noisy repeated announcements;
- DnD mapping has a checkbox/multi-select fallback and is not mouse-only;
- `prefers-reduced-motion` removes/shortens transition-only effects;
- page remains useful with JS disabled because existing informational treatment content still renders.

---

## 16. Performance/scalability choices

Low-effort/high-impact decisions for v1:

- no custom DB table;
- no AI model;
- no REST recommendation API yet;
- no server-stored session/questionnaire state;
- no per-relationship ranking table;
- no `ORDER BY RAND()`;
- no global product dataset on unrelated pages;
- only Treatments Hub queries treatment-consultation data;
- only explicit Treatment Products participate;
- recommendation ranking derives from concern scores, avoiding stored mapping order;
- current small catalog can be rendered once and filtered client-side.

Future scale trigger: only if Treatment Product count/payload becomes measurably large should recommendations move to a read-only paginated endpoint owned by `WooCommerceAdapter`.

---

## 17. Suggested implementation sequence

1. Pull latest `main`; report START HEAD/version.
2. Read `CONTRIBUTING.md` and canonical docs in required order.
3. Add ContentService registrations:
   - `gloskin_product_family`;
   - `gloskin_concern`;
   - `gloskin_consultation_path`;
   - private `gloskin_question` + answer meta.
4. Add the smallest LifecycleService structural upgrade needed to ensure only the stable family terms exist; **do not seed synthetic consultation data in lifecycle**.
5. Extend AdminService:
   - one Consultation submenu;
   - product-family list filter/editor field;
   - consultation tabs;
   - concern CRUD/readiness;
   - question links/readiness;
   - relationship mapping form + DnD enhancement;
   - staging-only demo import card/action.
6. Add narrow demo bundle/importer.
7. Extend WooCommerceAdapter with treatment-family product retrieval/presentation helper only as needed.
8. Extend Treatments page context/template with the consultation block while retaining eight informational treatments.
9. Add one small feature JS controller and corresponding presentation CSS through AssetService.
10. Reuse existing product-card/Add-to-Cart owner for recommendation results.
11. Update canonical docs named below.
12. Run repository checks available locally; review diff; patch-version bump because production PHP/CSS/JS behavior changes; commit coherently; push directly to `main`; verify remote HEAD.

---

## 18. Canonical docs that implementation must update

In the same implementation commit, update at least the relevant sections of:

- `docs/developer-source-of-truth.md`
  - Treatments Hub now includes consultation discovery;
  - Woo products may be skincare or purchasable treatment/service products;
  - explicitly distinguish 8 informational treatment records vs 4 consultation paths.
- `docs/content-data-contracts.md`
  - product-family taxonomy;
  - concern taxonomy;
  - consultation paths;
  - question CPT/meta;
  - canonical concern↔product relationship direction.
- `docs/architecture-efficiency-audit.md`
  - record that this feature remains inside existing owners/no new service/no custom table.
- `docs/runtime-service-map.csv`
  - add the new responsibilities to existing Content/Admin/Template/Woo/Asset owners where appropriate.
- `docs/implementation-plan.md`
  - mark/describe treatment consultation implementation.

Do not update page route count: this feature adds no new public route requirement.

---

## 19. Code-level validation expectations

Keep validation practical and repository-local. Do not turn this task into a staging/device test project.

Add/extend focused automated contracts for at least:

- no custom treatment-product CPT/table;
- Woo remains commerce owner;
- three private schema objects + private question CPT registered by ContentService;
- family stable terms and legacy-unclassified-as-skincare semantics;
- question answer sanitizer accepts only valid concern IDs / bounded weights;
- randomizer runs client-side once, no `ORDER BY RAND()`;
- questionnaire state is not persisted/server-posted;
- mapping save uses canonical taxonomy relationship only;
- no duplicate product-ID mapping option/meta;
- only one custom Consultation submenu added by this feature;
- demo importer refuses production environment;
- demo import is deterministic/idempotent and creates expected minimum counts;
- recommendation grid reuses current Woo Add-to-Cart/product-card path;
- Treatments Hub still renders/retains the existing informational treatment data path;
- zero new public `wp_ajax_nopriv_*`;
- no new `!important` unless an already-documented unavoidable owner exception exists (default expectation: zero new).

Run existing repository gates:

```bash
./tests/check-architecture.sh
./tests/check-presentation.sh
./tests/check-runtime.sh
```

Targeted PHP/JS contract tests are enough for this task. Browser/mobile/staging automation is not required by this brief.

---

## 20. Explicit non-goals

Do not add in this task:

- appointment calendar/scheduling engine;
- doctor auto-assignment;
- diagnostic/medical decision engine;
- AI recommendations;
- questionnaire analytics/history;
- questionnaire account persistence;
- email/lead capture;
- custom checkout/payment logic;
- treatment inventory system separate from Woo;
- generic migration manager;
- new public routes;
- new admin framework;
- mobile app/SPAs.

---

## 21. Definition of done

Implementation is complete when all of the following are true at code level:

- Woo products remain the only purchasable product model.
- Product family cleanly distinguishes Treatment Products without duplicating CRUD.
- Existing/unclassified Woo products remain safely usable as Skincare.
- `/treatments/` retains its existing eight informational-treatment capability and gains a separate four-path consultation layer.
- Consultation has 13+ demo questions, shuffled once per load/run.
- Answers deterministically score concerns.
- Concern taxonomy relationships are the only product-mapping persistence.
- Treatment Product recommendations reuse canonical Woo product cards/Add-to-Cart.
- Admin adds one compact `Konsultasi Perawatan` workspace rather than multiple sidebar entities.
- Mapping is friendly DnD when JS is available and remains a precise checkbox/form relationship editor underneath.
- Unmapped/orphan states are visible to admins instead of silently guessed.
- Synthetic demo import is explicit, staging-safe, idempotent, one-shot and creates the 4 paths + 10 concerns + 13+ questions + 8 treatment products + mappings needed for a complete demo.
- No new service, custom table, duplicate commerce owner, public mutation endpoint, or broad migration framework is introduced.
- Canonical docs are updated in the same implementation commit.
- Production behavior receives one patch version bump from the actual latest version, with plugin header/Kernel/exact-version tests synchronized.
- One coherent implementation is committed and pushed directly to `origin/main`, with remote HEAD verified.
