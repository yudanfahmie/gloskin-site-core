# Next Task — Prototype Parity, Managed Content, Doctor Photo Apply, Cleanup & One-Shot Migration

Date: 2026-08-19
Status: Ready for engineering
Repository: `yudanfahmie/gloskin-site-core`
Target branch: `main`
Current protected baseline: `f9e8d39d162a6745ce50a37dd9aea6f86fa66765` / v0.7.133

## 1. Context and authority

This task continues the client-approved 2026-08-18 Gloskin prototype refresh.

The handwritten wireframes were produced after the client requested a total revision of the earlier website direction. The approved prototype remains the authority for primary public/editorial experience. Existing WordPress/WooCommerce architecture remains authoritative for data ownership, commerce correctness, security, lifecycle discipline, and protected Shop/PDP/Cart/Checkout/My Account behavior.

The following corrections are now completed in v0.7.133 and must be treated as regression-protected baseline, not reopened:

- canonical public container widened to semi-full-width 1320px while preserving wider large-screen breakpoints and bounded reading widths;
- Doctors Hub and About now consume all published doctors rather than display ceilings;
- doctor grids use dedicated 4-column desktop / 2-column tablet / 1-column mobile geometry;
- Header V2 geometric centering remains canonical;
- Graphik/Felix typography system remains canonical.

This task covers the remaining prototype parity, managed-content, doctor-photo ingestion, cleanup, and one-shot migration work.

## 2. Product objective

Finish the remaining journey from handwritten wireframe → approved prototype → production implementation without introducing a second data architecture.

The result must:

1. materially improve parity on Treatments, Promo, Home “Why Gloskin”, Skincare, Testimonials, Achievements/Piagam, and About;
2. move repeatable Promo/Testimonial/Achievement data into native WordPress CRUD;
3. provide safe deterministic sample/demo data for those new repeatable modules;
4. ingest the newly owner-supplied doctor photographs, match them deterministically to existing doctor records, import them into Media Library, and apply them as doctor featured images;
5. provide one intelligent, resumable, idempotent one-click migration that resolves all bounded setup/data/image work and then permanently disappears after successful consumption;
6. clean obsolete presentation/admin/code paths only after zero-consumer verification;
7. preserve WooCommerce as the sole commerce owner.

## 3. Non-negotiable engineering constraints

Do not create a generic migration framework.

Do not create custom database tables.

Do not duplicate WooCommerce products, cart state, checkout state, orders, customers, pricing, stock, or product categories.

Do not redesign Shop, PDP, Cart, Checkout, or My Account.

Do not fabricate live business facts. Dummy records must be clearly marked as demo/draft/sample content and must never silently masquerade as factual production data.

Prefer native WordPress constructs:

- Pages/posts/CPTs only where a true repeatable content entity is needed;
- post meta for bounded structured fields;
- Media Library for images;
- existing Gloskin Content admin area as the parent management surface;
- existing service boundaries and composition root.

No new bootable service unless impossible within the current service cap and architecture. Prefer extending existing ContentService, TemplateService, AdminService, LifecycleService, AssetService, and existing bounded migration ownership.

Do not reintroduce old presentation variants or old 1180px width ownership.

## 4. Scope A — Treatments Hub parity

### Current gap

The consultation/recommendation engine is useful and must remain authoritative, but the first public discovery step is still visually different from the handwritten/prototype hierarchy.

The approved discovery language is:

`Face → Hair → Body → Wellness`

as four large alternating horizontal bands, each with a strong visual area and content/action area. Face then exposes the detailed concern/solution journey.

### Required improvement

Keep the existing canonical consultation paths, concerns, Woo Treatment Product mapping, recommendation/scoring logic, and SSR result ownership.

Change the public discovery composition so the first four configured paths render as large prototype-style alternating bands on desktop.

Target:

- one path per large horizontal band;
- alternating media/content order;
- configured path image when available;
- safe first-party/CSS decorative fallback otherwise;
- configured path label remains authoritative;
- use Face/Hair/Body/Wellness only when those are actual configured labels;
- selecting a path reveals the path's existing concern choices;
- current recommendation results remain authoritative;
- simple “Solusi”/discovery CTA language, no diagnosis claim;
- tablet/mobile stack cleanly;
- no duplicate treatment/product storage.

Do not create a second finder/recommendation engine.

## 5. Scope B — Promo becomes managed multi-campaign content

Create a bounded native WordPress repeatable Promo entity.

Preferred model:

- CPT `gloskin_promo`;
- `publicly_queryable=false` unless a concrete future requirement changes this;
- admin UI under existing **Gloskin Content**, not another top-level owner;
- supports title/editor/excerpt/featured image where useful;
- bounded post meta: short summary, CTA label/URL, eyebrow, optional dates, optional secondary media IDs, display order, active flag.

Do not create a pricing/discount engine or custom table.

### Native CRUD

Provide native list/add/edit/publish/draft/trash, featured media, campaign metadata, deterministic ordering, and readiness status.

### Frontend

Home Promo and `/promo/` consume the same managed records.

Target:

- featured campaign;
- previous/next;
- poster/thumb selector row;
- selector swaps title/copy/media/CTA;
- keyboard accessible and reduced-motion safe;
- incomplete/unpublished production records do not render;
- no fake prices/discounts/dates/terms;
- `/promo/` remains the native public Page destination.

### Dummy data

Seed several deterministic clearly-marked demo Promo records. Prefer Draft unless staging requires published visibility. Stable identity meta must prevent duplication.

## 6. Scope C — Why Gloskin Home composition

Replace the generic editorial split with the structure implied by the board/prototype:

- section intro/eyebrow + heading + richer supporting copy;
- one dominant value proposition block;
- two or three supporting cards;
- restrained editorial geometry, not dashboard cards;
- copy richer than current generic text but factual/non-medical.

Use approved themes only when supported by current Gloskin messaging: consultation-led journey, clearer discovery by concern/need, connected treatment/skincare/clinic ecosystem, doctor/clinic support where factually established.

No numbers, guarantees, outcome rates, superiority claims, or invented awards.

Where practical, make main narrative editor-owned via Home Page/meta instead of hard-coded factual business copy.

## 7. Scope D — Skincare becomes product-first discovery

Keep `/shop/` mature Woo catalog unchanged.

Change `/skincare/` to prototype-style discovery:

- actual Woo skincare-family products visually primary;
- lightweight filter/discovery based on existing factual Gloskin family/concern mappings or existing Woo categories/tags;
- product cards continue using Woo price/stock/URL/wishlist/Quick Add behavior already supported;
- category editorial links become secondary;
- do not hard-code prototype sample names unless real Woo products exist;
- no duplicate catalog or product classification owner.

## 8. Scope E — Testimonials managed factual content

Create native repeatable `gloskin_testimonial` content:

- non-public queryable;
- admin under Gloskin Content;
- title/internal label, editor/excerpt for quote, optional featured image;
- bounded meta: attribution/display name, optional subtitle/location/type, order, active flag, optional internal source/reference note.

Home renders only valid published records.

Presentation:

- strong intro panel + large quote panel;
- dots/slider when multiple;
- static state for one;
- keyboard accessible;
- no inaccessible forced auto-rotation;
- omit section if no factual published records.

Seed deterministic clearly-marked demo testimonials, preferably Draft.

## 9. Scope F — Achievement / Piagam managed factual content

Create one repeatable `gloskin_achievement` source shared by Home and About:

- non-public queryable;
- admin under Gloskin Content;
- title/editor/excerpt/featured media;
- bounded meta: issuing organization optional, year/date optional, source URL optional, display order, featured-on-home flag optional.

Home renders compact Piagam only from valid published data.

About renders fuller Achievement/Penghargaan from the same records.

Seed deterministic clearly-marked demo records, preferably Draft.

## 10. Scope G — About remaining parity

Keep current useful story, Visi/Misi/Nilai, doctors, clinics, CTA.

Converge toward:

1. large editorial About hero;
2. optional factual Founder;
3. Vision/Mission/Values;
4. Team visual / doctors;
5. Temukan Kami / network;
6. Achievement/Penghargaan;
7. closing CTA.

For Founder, use existing approved factual source if available. Otherwise use optional bounded About Page meta: founder name, role, story, media ID. Render only when required fields are populated. Do not invent identity or create a Founder CPT just for one profile.

Team visual must reuse doctor records and/or an editor-owned team image; no duplicate doctor profiles.

## 11. Scope H — Footer/supporting IA cleanup

Footer should reflect approved primary IA and useful support routes:

- Perawatan;
- Promo;
- Skincare;
- Tentang Gloskin;
- Klinik;
- Dokter;
- Belanja;
- Kontak;
- Insight if still active.

Promo must no longer be absent. Deep links to Achievement/Find Us only when stable anchors exist.

## 12. Scope I — Hard-coded factual copy audit

Audit public templates/helpers.

Classify copy as:

- safe UI instruction/generic copy → may remain;
- factual business claim → move to editor-owned content or verify canonical source;
- medical/outcome claim → approved factual content only.

Pay attention to Why Gloskin, footer description, doctor/clinic/consultation copy, treatment orientation.

Do not make every label configurable; move only content where governance materially improves.

## 13. Scope J — Remove production staging-photo dependency

Retire fixed remote Unsplash staging images after consumer audit.

Factual doctor/clinic/product imagery remains Media Library/Woo-owned.

Generic fallback should be local first-party neutral media or deterministic CSS/abstract primitives. No hotlinked replacement photography and no fabricated identity imagery.

## 14. Scope K — Retire obsolete presentation settings

Public shell already uses one approved presentation, but admin still contains historical design/header options.

After zero-consumer verification:

- hide/deprecate/remove active controls for medical/modern/luxury and header-1/header-2;
- harmless stored historical values may remain if needed;
- no migration solely to erase harmless values;
- delete dead preview assets/CSS/JS only after reference check.

Header V2 remains canonical.

## 15. Scope L — CSS / unused-part cleanup

Do not add another broad convergence layer.

Consolidate each rebuilt section into current component owners and clean only after zero-consumer verification:

- stale Header V1 assets/selectors;
- obsolete medical/modern/luxury rules;
- dead prototype-convergence selectors;
- unused remote editorial media helpers;
- stale comments contradicting Graphik/Felix/prototype authority;
- redundant width/type declarations competing with canonical tokens;
- unreachable template/helpers after section convergence.

Do not delete supporting routes/editor content or Woo pages/data.

## 16. Scope M — Owner-supplied doctor photo ingestion and direct apply

### New owner instruction

The owner supplied a new `FOTO DOKTER GLOSKIN` archive and explicitly stated that the photos are the correct doctor photos and should be applied directly to each matching doctor record.

The engineering workpack is committed under:

`docs/2026-08-18-prototype-refresh/resources/doctor-photos/`

Read these as source of truth:

- `README.md` — human mapping + migration rules;
- `doctor-photo-manifest.json` — machine-readable identities, aliases, primary image, SHA-256 and alternates;
- 17 converted WebP files — 12 fixed primary images plus 5 retained alternatives;
- `contact-sheet.webp` — review aid only.

Source archive SHA-256:

`19d72cbd79bb0fab22a782de31e602e2abeaaa19aea7e3964e2124d570056c64`

### Conversion facts

The supplied set contains 17 images for 12 unique doctor folders. All were converted to WebP with EXIF orientation applied, no crop, no upscale, maximum long edge 700 px, quality 65. The workpack identifies exactly one `primary_webp` per supplied doctor. Five alternate images are preserved for human review, but runtime migration must not choose among alternatives dynamically.

Do not infer identity from image pixels.

### Matching strategy — deterministic, conservative, no fuzzy guessing

Map each supplied primary to an existing `gloskin_doctor` using only manifest aliases.

Required:

1. normalize post title/slug and aliases deterministically: Unicode/casefold/lowercase, punctuation→spaces, collapse whitespace, ignore leading `dr` honorific;
2. use only explicit aliases, including listed degree-stripped/source-spelling variants;
3. no fuzzy/Levenshtein/AI face matching;
4. every supplied primary resolves to exactly one existing doctor;
5. zero or multiple matches stop the checkpoint with actionable report;
6. additional WordPress doctors with no supplied image are valid and unchanged;
7. never create doctor records from photo folders;
8. never rewrite doctor names, credentials, biography, clinics, treatments, slug, or relationships.

### Media Library import

Import only the 12 manifest `primary_webp` files into WordPress Media Library.

Use deterministic plugin asset identity + SHA-256 attachment meta so reruns reuse attachments rather than duplicate them. Store provenance such as manifest revision/source hash/doctor source label following repository naming conventions.

Do not hotlink GitHub/docs paths at runtime.

The docs workpack is provenance. For deployed migration, package/copy the 12 manifest-selected primary WebP bytes into the bounded first-party migration runtime using the repository’s existing one-shot resource pattern.

### Apply featured images

For every uniquely matched supplied doctor:

- snapshot existing featured-image attachment ID in migration audit state;
- import/reuse manifest primary attachment;
- set it as the doctor Featured Image.

Owner instruction authorizes replacement for a uniquely matched supplied doctor.

Do not alter unrelated doctors/media.

### Fixed primary choices for multi-image doctors

- Arwina Sufika → `dr-arwina-sufika.webp`;
- Nanang Masrani → `dr-nanang-masrani-m-biomed-aam.webp`.

Alternates remain docs/reference only unless owner later chooses differently.

### Success criteria

Before full revision finalizes:

- all 12 supplied primary identities have exactly one doctor match;
- all 12 primaries imported or safely reused;
- attachment identity/hash is unique and rerunnable;
- each primary applied at most once;
- no doctor created/deleted;
- no extra doctor without supplied photo changed;
- no unrelated featured image changed;
- previous thumbnail IDs present in audit summary;
- rerun after interruption creates no duplicate attachments.

Repository doctor readiness target 13 does not justify inventing a 13th photo. This owner pack contains 12 unique doctor identities; any additional doctor remains untouched until owner supplies their photo.

## 17. Dummy data policy

Dummy data exists only for new repeatable Promo/Testimonial/Achievement modules.

Required sample families:

- Promo;
- Testimonial;
- Achievement.

Doctor photos are **not dummy data**. They are owner-supplied factual identity assets and must be handled separately under Scope M.

Every sample record must:

- deterministic identity metadata;
- clearly marked plugin-generated demo/sample;
- rerunnable without duplication;
- never overwrite editor-authored records;
- preferably Draft unless staging needs published demo visibility;
- removable by deterministic identity, never broad title matching.

Do not create a fake named Founder.

## 18. Intelligent one-click / one-shot migration

Extend existing bounded migration discipline; no second migration framework.

### User experience

One authorized temporary wp-admin migration action for this revision.

One click resolves all bounded work. User must not run separate seeds, image imports, content setup, or cleanup tasks.

### Required checkpoints

1. **Preflight**
   - verify WordPress/Woo requirements/capabilities;
   - snapshot relevant settings/configuration;
   - detect current canonical structures;
   - load/validate doctor-photo manifest and runtime bytes;
   - precompute doctor-photo matches without mutation;
   - fail early on zero/multiple doctor matches or missing/corrupt assets.

2. **Ensure managed content structures**
   - Promo;
   - Testimonial;
   - Achievement;
   - bounded optional About meta.

3. **Seed deterministic sample data**
   - only missing plugin-generated demo identities;
   - never overwrite editor data;
   - audit what was created/reused.

4. **Import and apply owner doctor photos**
   - import/reuse 12 primary WebPs by deterministic identity/hash;
   - snapshot prior thumbnail IDs;
   - set matched Featured Images;
   - audit attachment IDs and doctor IDs;
   - no fuzzy matching/no extra doctor mutation.

5. **Normalize page/content relationships**
   - `/promo/` remains valid;
   - frontend consumes new managed entities;
   - no Woo page ID changes.

6. **Retire obsolete plugin-owned presentation surfaces where safe**
   - known plugin-owned obsolete UI/presentation only;
   - preserve ambiguous/editor content.

7. **Safety verification**
   - primary IA remains Perawatan → Promo → Skincare → Tentang Gloskin;
   - 1320px canonical container and all-doctor 4/2/1 grid remain intact;
   - Header V2 remains canonical/centered;
   - Shop/PDP/Cart/Checkout/My Account intact;
   - support routes exist;
   - Woo data/page IDs unchanged;
   - no editor data overwritten except owner-authorized featured-image replacement for uniquely matched supplied doctors;
   - managed queries resolve;
   - sample identities unique;
   - 12 doctor primaries uniquely matched/imported/applied;
   - no duplicate doctor-photo attachments.

8. **Finalize / consumed**
   - target revision/schema only after verification;
   - store consumed marker + detailed audit summary;
   - flush rewrites only when required;
   - cleanup disposable runtime migration assets/state according to existing pattern.

### Resumability/idempotency

Interrupted migration resumes from last safe checkpoint.

Rerun must not duplicate samples, attachments, menus, Pages, or metadata.

If doctor-photo apply partially completed, rerun recognizes imported attachments/already-applied identities and continues safely.

### One-shot disappearance

After consumed:

- temporary migration submenu/action disappears permanently;
- activation/deactivation does not resurrect it;
- schema/version remains monotonic;
- no normal-page-load auto-rerun;
- no generic migration UI remains.

If disposable cleanup fails after successful consumption, keep consumed state locked and surface a warning rather than re-enable migration.

## 19. Admin information architecture

Keep native/simple:

`Gloskin Content`

- Overview
- Treatments
- Clinics
- Doctors
- Promo
- Testimonials
- Achievements
- Settings
- temporary revision migration only while unconsumed

Treatment Consultation may remain under existing Woo Products location.

Doctor photo mapping does not need a permanent admin module; owner workpack + one-shot migration is sufficient. After migration, normal Doctor Featured Image editing remains the ongoing owner.

## 20. Acceptance criteria by surface

### Home

- canonical Campaign hero;
- Why Gloskin dominant + supporting-card composition;
- factual treatment discovery;
- managed multi-campaign Promo;
- product-first Skincare discovery;
- Testimonials only from published factual data;
- Piagam only from published factual data;
- About transition + CTA;
- no unsupported claims.

### Treatments

- large alternating prototype bands;
- canonical path labels/data retained;
- current concern/recommendation engine intact;
- no second recommendation owner.

### Promo

- `/promo/` remains Page destination;
- native managed Promo CRUD;
- multiple campaigns + accessible controls;
- safe empty state;
- no invented commercial facts.

### Skincare

- real Woo products visually primary;
- lightweight discovery/filtering;
- Shop remains authoritative;
- no duplicate product state.

### About

- story;
- optional factual Founder;
- Vision/Mission/Values;
- team/doctors;
- clinic/network;
- managed achievements;
- CTA.

### Doctor photos

- owner primary WebPs applied to unique matching doctor records;
- additional doctors without owner photos unchanged;
- alternate images not randomly selected;
- featured-image ownership returns to normal WordPress UI after one-shot apply.

### Admin

- native CRUD Promo/Testimonial/Achievement;
- demo identities clearly distinguishable;
- obsolete design/header variant controls no longer mislead editors;
- no unnecessary custom SPA/admin framework.

## 21. Regression protection

Do not regress:

- v0.7.133 1320px semi-full container and large-screen breakpoints;
- all-published doctor directory behavior;
- doctor 4/2/1 responsive grid;
- Header V2 geometric centering;
- Graphik/Felix typography;
- primary nav and shared mobile/desktop tree;
- Shop filters/search/category/price/pagination/AJAX;
- PDP gallery/variations/add-to-cart;
- wishlist/Quick Add;
- Cart;
- Checkout/payment lifecycle;
- My Account;
- Woo page IDs;
- editor Pages/posts/media;
- Clinics/Doctors/Contact/Insights supporting routes;
- keyboard/focus/reduced-motion behavior.

## 22. Required tests

Add/update focused contracts for at minimum:

- Promo/Testimonial/Achievement structures register once;
- non-commerce ownership remains separate from Woo;
- sample seed idempotency/no editor overwrite;
- multi-campaign Promo consumption;
- Testimonials/Achievements published-only rendering;
- Treatments consumes current canonical paths/concerns/products and only changes presentation;
- Skincare discovery consumes real Woo products;
- obsolete presentation settings cannot change public shell;
- migration resumable/idempotent/consumed only after verification;
- consumed migration UI never returns on reactivation;
- Woo page configuration unchanged/no destructive Page cleanup;
- accessibility controls;
- doctor manifest parses and every primary asset hash matches;
- alias normalizer gives unique deterministic matches;
- zero/multiple doctor match blocks before mutation;
- imported doctor attachments dedupe by stable identity/hash;
- rerun does not duplicate attachments;
- exactly supplied matched doctors have featured images replaced;
- unrelated doctors/fields/relationships remain untouched;
- previous thumbnail IDs are audited;
- multi-image doctors use fixed primary assets.

Run full repository suite afterward.

## 23. Definition of done

Complete only when:

1. Treatments, Promo, Why Gloskin, Skincare, Testimonials, Achievement, About materially match owner-approved direction;
2. Promo/Testimonial/Achievement are editor-manageable native CRUD;
3. deterministic demo data makes those modules immediately testable without pretending factual production data;
4. all 12 owner-supplied doctor primary WebPs deterministically import/reuse and apply to unique existing doctors, with no guessed identity/unrelated mutation;
5. one click resolves all setup/seed/photo apply/cleanup work;
6. migration is resumable/idempotent and permanently disappears after consumed;
7. obsolete code cleaned only after zero-consumer verification;
8. protected Woo flows and v0.7.133 sizing/doctor improvements remain intact;
9. repository docs/assets fully explain the revision without chat history.

## 24. Suggested implementation grouping

Prefer a small number of coherent main-only commits:

1. **Managed content + migration + doctor assets** — Promo/Testimonial/Achievement models, admin CRUD, sample seeds, bundled doctor primaries, photo matching/import/apply checkpoint, tests.
2. **Prototype parity surfaces** — Treatments bands, Promo carousel, Why Gloskin, Skincare, Home testimonial/achievement, About.
3. **Cleanup + regression** — stale settings, staging imagery, dead CSS/helpers, docs, full suite.

No branch/PR unless owner changes workflow explicitly.
