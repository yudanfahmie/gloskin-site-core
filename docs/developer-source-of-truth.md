# Developer Source of Truth

## 1. Authority

This document is the canonical developer-facing requirements source for `yudanfahmie/gloskin-site-core`.

A developer implementing Gloskin should not need to inspect `yudanfahmie/project-9901` for normal development work. The raw client/project material has already been normalized into this repository. `project-9901` is provenance only and must remain read-only.

Use the following order of authority when requirements appear to conflict:

1. explicit instructions from the repository owner;
2. this document and the current repository documentation;
3. `docs/page-matrix.csv` and `docs/prune-matrix.csv`;
4. the pinned Morgen source only for implementation provenance and reusable patterns;
5. raw Gloskin files only for historical audit, never as a routine developer dependency.

Do not invent missing client content. Where this document marks a value as pending, keep the implementation data-driven and expose an editable field or graceful empty state.

## 2. Product definition

`gloskin-site-core` is a WordPress plugin that provides the Gloskin website presentation system: global shell, page-family templates, reusable UI, responsive behavior, Gloskin-owned non-commerce content models, and safe presentation integration with WordPress and WooCommerce.

It behaves like a theme-level/page-builder presentation layer while remaining a plugin.

The initial structural provenance is Morgen UI V6 from:

- repository: `yudanfahmie/morgen-core`
- pinned commit: `374432cee6380e0aa0f81390e26b990147e5e58d`

Morgen V6 is a source of proven layout and interaction patterns, not a package to clone wholesale. The Gloskin production presentation is named **Gloskin UI v1**.

## 3. Non-negotiable ownership boundaries

### Gloskin Site Core owns

- global public shell;
- header and navigation presentation;
- footer presentation;
- responsive layout primitives and design tokens;
- page-family templates;
- reusable cards, galleries, carousels, drawers, CTAs and content sections where required;
- treatment, clinic and doctor content structures;
- fixed-page presentation;
- Insights presentation using WordPress posts;
- WooCommerce visual integration;
- form-plugin presentation integration;
- minimal Gloskin-specific settings required by those presentation features.

### WordPress owns

- native Pages;
- native Posts used for Insights;
- Media Library attachments;
- normal users/capabilities/options/meta infrastructure.

### WooCommerce owns

- products and variations;
- product CRUD and product administration;
- product categories and attributes;
- product images;
- SKU;
- price and stock;
- cart and add-to-cart behavior;
- checkout;
- orders;
- customer/account flows;
- payment-gateway integration and transaction logic.

Gloskin Site Core may read WooCommerce data and may style supported WooCommerce output. It must not introduce a parallel product database, Morgen-style product registry, duplicate product admin, duplicate cart, duplicate checkout, or payment logic.

### External form layer owns

- form submission;
- anti-spam/captcha implementation;
- mail delivery;
- auto-replies;
- submission storage, when the chosen form plugin provides it.

Gloskin owns only the form placement, surrounding layout, compatible success/error styling, and fallback behavior.

## 4. Normalized site inventory

The raw project contained inconsistent headline counts such as `21 core pages` and `33 pages`. Those counters are stale and internally inconsistent. The explicit task and route inventory is authoritative.

Required visitor-facing families are:

- Homepage: 1
- About: 1
- Treatments Hub: 1
- Treatment category pages: exactly 8
- Skincare Hub: 1
- Skincare category landing pages: 7
- Clinics Hub: 1
- Clinic detail pages: 9
- Contact: 1
- Insights Hub: 1
- Shop Hub: 1
- Doctors Hub: 1
- Doctor detail pages: 13
- WooCommerce product pages: up to 20
- WooCommerce cart and checkout surfaces

With all hubs, detail pages and up to twenty products present, the explicit inventory can reach roughly 66 visitor-facing URLs. That number is a route/surface inventory, not a request to duplicate records.

The canonical route matrix is `docs/page-matrix.csv`.

## 5. Required route contract

Required bases:

- `/`
- `/about/`
- `/treatments/`
- `/treatments/{approved-category-slug}/`
- `/skincare/`
- `/skincare/{approved-category-slug}/`
- `/clinics/`
- `/clinics/kebayoran-baru/`
- `/clinics/tebet/`
- `/clinics/bekasi/`
- `/clinics/cibubur/`
- `/clinics/serpong/`
- `/clinics/surabaya/`
- `/clinics/banjarmasin/`
- `/clinics/balikpapan/`
- `/clinics/denpasar/`
- `/contact/`
- `/insights/`
- `/shop/`
- `/doctors/`
- `/doctors/{doctor-slug}/`
- WooCommerce-managed single-product, cart, checkout and account URLs as configured by WooCommerce

Do not recreate Morgen routes such as Technical Library, Applications, Hammer, Quality Testing, custom product catalog/category routes, PDF/download routes or old UI preview routes.

## 6. Recommended WordPress content model

Use native WordPress/WooCommerce concepts before inventing custom storage.

Recommended structure:

- native Page: Home
- native Page: About
- native Page: Skincare Hub
- native Pages: seven skincare category landings
- native Page: Clinics Hub
- native Page: Doctors Hub
- native Page: Contact
- native Page: Insights Hub
- native Page: Shop Hub
- CPT `gloskin_treatment`: eight approved treatment category records
- CPT `gloskin_clinic`: nine clinic records
- CPT `gloskin_doctor`: thirteen doctor records
- native Posts: Insights articles
- WooCommerce Products: skincare products and any purchasable Treatment Products
- private taxonomy `gloskin_product_family`: product classification (`skincare`, `treatment`)
- private taxonomy `gloskin_concern`: Treatment Product recommendation vocabulary
- private taxonomy `gloskin_consultation_path`: questionnaire path vocabulary
- private CPT `gloskin_question`: questionnaire questions only; never a public route or purchasable entity

For Treatment Consultation, WooCommerce `product` is the sole purchasable entity. The existing eight `gloskin_treatment` records remain an informational directory and must not be converted into products. Product↔concern taxonomy relationships are the sole recommendation mapping source; visitor questionnaire answers/scores/history are frontend runtime state only and must not be persisted.

If a future developer can implement a simpler equivalent with native Pages instead of one of the three public CPTs, that is acceptable only if URL behavior, editing capability, relationships and templates remain equivalent. Do not add a custom database table for these content types without a demonstrated technical need.

## 7. Navigation and information architecture

The primary navigation should be Gloskin-specific. Do not inherit Morgen's managed registry.

Expected top-level destinations should cover the core public areas without exposing implementation history. A reasonable base IA is:

- About
- Treatments
- Skincare
- Clinics
- Doctors
- Shop
- Insights
- Contact / Book

Exact ordering and CTA wording remain presentation/content decisions, but the implementation must support desktop navigation, accessible submenus where needed, and a mobile drawer/menu.

The mobile menu must use proper disclosure/dialog semantics, keyboard support, close behavior, focus management and escape/backdrop behavior. Preserve the proven interaction quality of Morgen V6, but use fresh Gloskin markup/classes/data attributes.

Do not expose EN/DE switches from Morgen. WordPress strings should still be internationalization-ready. The source material mentions Indonesian and English company copy, but a multilingual routing system is not currently a plugin requirement.

## 8. Page-family requirements

### 8.1 Homepage

Required structure:

1. global header/navigation;
2. responsive hero with approved media/content;
3. treatment discovery linking to the eight approved treatment categories;
4. clinic discovery linking to all nine branches;
5. doctor preview linking to Doctors Hub and selected doctors;
6. skincare/shop preview sourced from WooCommerce;
7. Insights preview sourced from WordPress posts;
8. booking/contact CTA;
9. global footer.

The page must not contain industrial specification UI, technical-document cards, PDF download patterns or Morgen product-selection language.

If hero media is not yet supplied, render a deliberate non-broken fallback. Do not ship fake client imagery as production content.

Current production Home intentionally specializes the existing shared hero renderer into `video-only` mode. It keeps one admin-configurable owner and must render no visible eyebrow/heading/copy/CTA; a screen-reader structural heading is acceptable. The media is one native `<video>` sourced from a WordPress Media Library MP4 or WebM attachment (`hero_video_media_id` in the existing `gloskin_site_core_settings` option) -- muted, autoplay, loop, playsinline, preloaded, no controls/poster/Play chrome, no iframe -- with true full-bleed `object-fit:cover` (never letterboxed) and a pointerless media/loading layer. It starts on a pure-white preparing surface. The controller installs media listeners before reconciling an already-usable `readyState` or already-playing state; normal-motion readiness requires usable data, a resolved `play()` Promise, and observed/reconciled playback. The bounded timeout only releases the loader into a clean white fallback and remains recoverable if valid readiness arrives later; a native media error is terminal. Reduced motion pauses and reveals a usable static frame whether data was ready before or after initialization. When no supported native video is configured, the attachment-image/editorial fallback takes over. Bottom gradient and exactly one SVG scroll cue targeting the actual next sibling section remain. There is no Home YouTube network or runtime path and no second Home hero/video service.

### 8.2 About

Support:

- page hero/introduction;
- approved Gloskin company overview;
- Vision;
- Mission;
- Values;
- clinic-network summary;
- doctor/team teaser;
- booking/contact CTA.

The source material expected a substantial company description and referenced Indonesian and English versions. Store/render approved content without building a Morgen-style language route engine.

### 8.3 Treatments Hub

Support:

- hub hero/introduction;
- exactly eight approved treatment category cards/entries;
- optional featured treatment area driven by content, not hard-coded template assumptions;
- related clinic/doctor discovery where data exists;
- booking CTA;
- additive Treatment Consultation flow when its private data contract is ready.

Treatment Consultation path/question scoring is client-side presentation over canonical WordPress/Woo data. It must reuse the canonical Woo product-card/add-to-cart runtime, cap recommendations at eight cards, and leave the informational treatment directory below it.

### 8.4 Treatment category

Each of eight approved records must support:

- title;
- slug;
- summary/description;
- benefits;
- contraindications;
- hero or featured media;
- related clinics;
- related doctors;
- booking/contact CTA.

Templates are neutral presentation containers. Do not hard-code medical efficacy claims or generate claim copy. Medical-content approval is a content/governance concern outside plugin tooling.

#### Treatment taxonomy status

The source requires **eight** treatment category pages, but onboarding did not provide a finalized eight-category taxonomy. A draft service list contained more than eight labels:

- Facial
- Peeling
- Barrier Reset
- Quality Repair
- Face Contour
- Infusion
- Hair Restoration
- Body Contour
- Dermatolift
- Thread Lift
- Botox
- Filler
- Juvelook
- Salmon DNA
- Laser

These are evidence of candidate service naming only. Do not arbitrarily group these fifteen labels into eight categories. Build for eight configurable approved records; final names/slugs/content remain pending client data.

### 8.5 Skincare Hub

Support:

- hero/introduction;
- seven skincare-category cards;
- WooCommerce-backed product discovery;
- link to Shop.

Provisional seven labels from onboarding are:

- Facial Wash
- Day Cream/Sunscreen
- Toner
- Serum
- Acne Care
- Anti-Aging
- Brightening & Pigmentation Care

The labels/slugs must remain configurable mappings to WooCommerce product categories.

### 8.6 Skincare category landing

Each landing should:

- show approved category title/intro;
- map to a WooCommerce product-category term;
- render products using supported WooCommerce APIs/loops/blocks/shortcodes/hooks;
- preserve native product links and commerce behavior;
- show an intentional empty state when no matching products exist;
- avoid duplicate product storage and duplicate product CRUD.

### 8.7 Clinics Hub

Display all nine required branches with a consistent card/list system.

Required branches:

1. Kebayoran Baru
2. Tebet
3. Bekasi
4. Cibubur
5. Serpong
6. Surabaya
7. Banjarmasin
8. Balikpapan
9. Denpasar

Each card should support branch image, area/city, concise hours summary and detail link.

### 8.8 Clinic detail

Each branch detail must support:

- branch hero/gallery;
- branch name;
- address/location introduction;
- NAP: name, address, phone;
- operating hours;
- Google Maps embed or configured map URL/embed data;
- branch-specific WhatsApp booking/contact CTA;
- doctors practicing at that branch;
- related treatments;
- final booking/contact CTA.

Source asset expectation: at least three branch photos/angles when approved assets are supplied.

Do not invent addresses, phone numbers, hours, WhatsApp numbers or map coordinates. Missing fields must fail gracefully and remain editable.

### 8.9 Doctors Hub

Display thirteen doctor records with:

- professional photo;
- full name/title;
- specialization;
- branch information;
- detail link.

### 8.10 Doctor detail

Support:

- professional portrait;
- full name;
- title/degree;
- specialization;
- practice branch(es);
- SIP number when available;
- credentials/profile content;
- branch/location links;
- related treatments;
- booking CTA.

The broader raw project referenced Physician schema, but schema/SEO management is excluded from this plugin scope. Keep the HTML/data semantically clean so an external SEO layer can consume it later.

### 8.11 Contact

Support:

- contact introduction;
- branch contact cards or selector;
- branch-specific WhatsApp links;
- configurable form shortcode/block area for the site's chosen form plugin;
- useful fallback when the configured form integration is unavailable.

Do not port Morgen's inquiry-mail transport, custom auto-replies, mail admin or dedupe infrastructure.

### 8.12 Insights Hub

Use native WordPress posts.

Support:

- page hero;
- post grid/list;
- pagination or a standard WordPress-compatible load-more pattern;
- accessible empty state.

No SEO-content production or editorial marketing workflow is required in this plugin.

### 8.13 Shop Hub

Treat Shop as a Gloskin presentation wrapper around WooCommerce.

Use supported WooCommerce output: native loop/template integration, block or shortcode as appropriate. Preserve product, cart and checkout links. Do not create Morgen-style product filters/data registries unless an actual Gloskin requirement later demands a frontend filtering feature that cannot be met by WooCommerce.

### 8.14 WooCommerce single product

Gloskin presentation may style:

- WooCommerce gallery;
- product title;
- price;
- description;
- attributes;
- add-to-cart controls;
- related products.

Raw product inputs include:

- product name;
- SKU;
- price;
- BPOM number;
- composition;
- usage instructions;
- product photos, with a source expectation of at least three angles where assets exist.

The framework also mentions a uniform twelve-field product template, but the source material does not define twelve sufficiently reliable developer fields. Do not invent the missing fields and do not create a fixed parallel schema merely to reach twelve. Use WooCommerce-managed standard fields, attributes and metadata, and let future approved fields extend that Woo-owned model.

### 8.15 Cart and checkout

Cart and checkout remain native WooCommerce flows. Gloskin changes are presentation-safe only.

The raw material is internally mixed: one area mentions WooCommerce catalog mode, while other requirements mention checkout testing and Midtrans/Xendit. Therefore **Gloskin Site Core must not enforce catalog mode, disable purchasing, or own payment mode**. It must remain compatible with normal WooCommerce commerce. Whether the deployed site runs catalog-only or transactional is site configuration outside this plugin.

Midtrans/Xendit business configuration and gateway logic are not implemented here; the plugin must avoid breaking WooCommerce gateways.

Gloskin's cart overlay already owns successful Add-to-Cart/cart-sheet mutation feedback. Redundant page-level Woo success messages for those exact operations may be suppressed only at Woo's narrow operation hooks before session queue/render. Woo errors, info notices, account/profile/password success, coupon feedback, checkout validation, shipping/payment notices and order/payment failures remain Woo-owned and must not be globally cleared or hidden. Never use global `.woocommerce-message` hiding, global `wc_clear_notices()`, DOM polling, or notice-cleanup observers.

## 9. Content and relationship contracts

Detailed field contracts are in `docs/content-data-contracts.md`.

Required relationships are conceptually:

- clinic ↔ doctor: many-to-many;
- clinic ↔ treatment: many-to-many when used;
- doctor ↔ treatment: many-to-many when used;
- skincare landing → WooCommerce product category: one configured mapping;
- clinic → branch WhatsApp/map/contact data;
- product data remains entirely WooCommerce-owned;
- Woo Treatment Product ↔ `gloskin_concern`: native taxonomy relationship and sole recommendation mapping source;
- `gloskin_question` → `gloskin_consultation_path`: native taxonomy relationship;
- question answer options: bounded registered question meta; visitor answer/history state is not persisted.

Prefer WordPress-native post meta/registered meta/taxonomy relationships and IDs over custom tables. Do not add ACF or another framework solely to model these fields unless explicitly approved.

## 10. Design system

The source material requested three initial design directions:

1. **Medical Professional** — clean navy/white, authority-oriented serif typography.
2. **Modern Aesthetic** — pastel, sans-serif, visual-led.
3. **Premium Luxury** — dark tones, gold accent, editorial typography.

Engineering interpretation:

- one component tree;
- one Gloskin UI v1 runtime;
- token/variant-driven visual directions;
- no duplicated template trees;
- no Morgen UI V1-V5 runtime;
- no public `morgen-*` or `mg6-*` production identifiers after adaptation;
- once a final visual direction is selected, unused production-facing variant complexity should be removable without restructuring page templates.

Design variants are not a reason to build three separate websites or plugin modes.

## 11. Non-functional requirements

### Responsive behavior

- All required page families must work on mobile, tablet and desktop.
- Header/drawer/navigation must remain usable at narrow widths.
- Media must not cause uncontrolled layout overflow.
- WooCommerce product/cart/checkout presentation must remain usable on mobile.

### Accessibility

Preserve the useful quality of Morgen V6 patterns, but reimplement them under Gloskin ownership:

- visible keyboard focus;
- semantic headings and landmarks;
- accessible navigation/disclosures;
- dialog/drawer focus behavior;
- meaningful alt-text support using normal WordPress media data;
- reduced-motion support;
- buttons and links with clear accessible names;
- no interaction that requires hover only.

Do not port Morgen's industrial alt-text repair migration or hard-coded alt defaults.

### Performance

The raw project includes performance as a developer concern. Therefore:

- do not enqueue excluded Morgen feature assets globally;
- keep the Gloskin asset registry minimal;
- avoid loading Technical Library/Application/PDF/product-manager code;
- lazy-load non-critical media where appropriate;
- preserve image dimensions/aspect ratio to reduce layout shift;
- use deterministic asset versioning;
- avoid duplicate copies of frontend libraries already owned by WooCommerce/WordPress unless required;
- avoid large inline compatibility payloads inherited from Morgen history.

No arbitrary numeric performance score is invented here. Optimize for a lean production bundle and validate representative pages in the actual target environment.

### Robustness

- No PHP fatal if optional content is missing.
- Non-commerce pages should not fatal when WooCommerce is temporarily inactive; commerce sections should fail safely or expose an admin-facing dependency warning.
- Consultation taxonomy registration must not depend on WooCommerce's product CPT having registered first; canonical object-type slugs are sufficient and preserve either plugin load order.
- Missing form integration should produce an intentional fallback, not broken shortcode text.
- Missing branch media/map/optional doctor SIP should not break layout.
- A missing related-content relationship should simply omit that section or show an appropriate empty state.

### Security

For plugin-owned data/settings:

- use capability checks;
- use nonces for writes;
- sanitize input according to field type;
- escape output at render time;
- validate attachment IDs and post IDs;
- validate/sanitize URLs, phone and WhatsApp values;
- do not create custom mail endpoints without explicit scope;
- do not store secrets in the repository.

## 12. Morgen V6 adaptation policy

The reverse engineering record is `docs/morgen-v6-reverse-engineering.md` and the executable decision table is `docs/prune-matrix.csv`.

The central rule is:

> Reuse proven behavior and small generic patterns; do not inherit Morgen's composition root, registries, route model, production migrations or domain modules.

Critical findings:

- the V6 shell directly reads Morgen products, categories, Technical Library, Applications and Quality data;
- the V6 navigation registry knows V1-V6 and industrial routes plus EN/DE;
- the Public UI Bootstrap transitively boots Admin Library, proxy helpers, form-security/contact services, build profile and feedback migration;
- the generated build profile currently points at historical `CASE-PROD-011` with migration enabled;
- the canonical asset registry currently marks excluded Application and Technical Library assets as required and the loader enqueues the complete UI6 set;
- the drawer portal depends on Morgen system/version/request checks and historical CASE-PROD cleanup;
- the old Home Hero V6 class is a compatibility shell, not a reusable hero service;
- V6 media-alt repair embeds industrial default text and migration state;
- homepage media admin has useful transaction/validation patterns but is strongly tied to Morgen option keys, admin pages and EN/DE data;
- inquiry mail is a full Morgen-specific transport/deduplication/auto-reply subsystem and must not be carried into Gloskin.

Therefore the implementation must start with a fresh Gloskin composition root and a fresh Gloskin asset registry.

## 13. Target technical topology

Recommended initial production layout:

```text
plugin/gloskin-site-core/
  gloskin-site-core.php
  includes/
    class-plugin.php
    class-assets.php
    class-content-types.php
    class-template-router.php
    class-navigation.php
    class-settings.php              # only if real settings are needed
    integrations/
      class-woocommerce.php
      class-form.php
  templates/
    shell.php
    parts/
      header.php
      footer.php
      mobile-drawer.php
    pages/
      home.php
      about.php
      treatments.php
      treatment.php
      skincare.php
      skincare-category.php
      clinics.php
      clinic.php
      doctors.php
      doctor.php
      contact.php
      insights.php
      shop.php
  assets/
    css/
    js/
    vendor/                         # only retained vendor code actually used
```

Exact class splitting may change during implementation, but the boundaries should remain clear: bootstrap, assets, content, routing/templates, navigation and external integrations.

Do not reproduce Morgen's monolithic historical plugin structure merely for parity.

## 14. Admin/editing requirements

Keep the admin surface proportional to actual Gloskin-owned data.

Required editing capabilities should cover:

- treatments;
- clinics;
- doctors;
- relationships between them;
- skincare-to-Woo category mappings;
- Treatment Consultation paths, concerns and private questions;
- Treatment Product↔concern taxonomy mapping through one `Konsultasi Perawatan` submenu;
- branch contact/map/WhatsApp fields;
- minimal presentation settings such as hero media only if those values are genuinely plugin-owned.

The mapping UI may progressively enhance the canonical native checkbox matrix into one searchable Treatment Product pool and concern buckets with removable chips. Products remain available in the pool because one product may map to multiple concerns. JavaScript must only synchronize those same form relationships; if JavaScript fails, the native checkbox matrix remains a complete save path. Do not add another persistence layer.

Avoid rebuilding a broad Morgen admin shell. Native WordPress edit screens, registered meta and small purpose-built metaboxes/settings are preferred when sufficient.

If a custom hero/gallery media editor becomes necessary, reuse only proven ideas from Morgen's media admin: media validation, nonce/capability checks, payload bounds and safe persistence. Do not copy the Morgen EN/DE field model, historical snapshots/audit machinery or option naming by default.

## 15. Required external inputs that remain pending

These are content/configuration dependencies, not reasons to consult raw project files:

- final approved eight treatment names/slugs and content;
- final mapping of the seven skincare landing pages to actual WooCommerce category slugs;
- actual clinic NAP data, hours, branch WhatsApp numbers, map data and approved photos;
- actual thirteen doctor identities, photos, titles, specializations, branch relationships, SIP values and profile copy;
- actual WooCommerce product data and approved product media;
- approved About copy, Vision, Mission and Values;
- final chosen design direction/tokens;
- chosen WordPress form plugin and its shortcode/block configuration;
- final booking/contact CTA destinations if they differ by branch;
- target-site WooCommerce commerce mode and installed payment gateways.

A developer should request these concrete inputs from the owner/content team when needed. They should not reopen `project-9901` looking for an answer that the normalized source did not contain.

## 16. Explicit exclusions

Unless the owner later changes scope, do not implement:

- SEO strategy or SEO content production;
- GEO/AI visibility work;
- Rank Math scoring/proxy-page/schema administration;
- Google Search Console submission/monitoring;
- GA4/GTM setup or reporting;
- Google Business Profile work;
- backlinks/citations/media placement;
- social/TikTok campaigns;
- marketing reporting/retainer tooling;
- DNS or three-domain consolidation;
- redirect-map execution;
- SSL/go-live infrastructure orchestration;
- medical/compliance approval workflow software;
- custom Midtrans/Xendit payment logic;
- WooCommerce backend replacement;
- custom product manager/catalog database;
- duplicate Treatment Product/recommendation mapping store;
- questionnaire-answer persistence;
- second cart mutation/notice framework;
- Morgen Technical Library/Documents/PDF/secure-download stack;
- Morgen Applications/Hammer/Quality Testing domains;
- Morgen production incident migrations/repair/reconciliation history;
- Morgen EN/DE route system;
- custom Morgen inquiry/mail transport;
- Morgen telemetry/diagnosis bundle by default;
- UI V1-V5 or presentation version switching.

## 17. Acceptance definition

The future implementation is complete only when all applicable points are true:

- production plugin files exist on remote `main`;
- Gloskin UI v1 is the only production presentation baseline;
- no feature branch/PR workflow was introduced;
- every route family in `docs/page-matrix.csv` has a real implementation path;
- exactly eight configurable treatment records/templates are supported;
- seven skincare landing mappings are supported;
- all nine clinic branches are supported;
- thirteen doctor records/templates are supported;
- WooCommerce remains the sole product/commerce authority;
- Shop, product, cart and checkout presentation do not replace Woo logic;
- Treatment Consultation uses private WordPress taxonomy/question structures without a second purchasable entity or custom table;
- product↔concern mapping has one native taxonomy source and questionnaire answers remain runtime-only;
- Contact delegates submission/mail to the configured form layer;
- branch-specific WhatsApp and map/contact fields are supported;
- desktop/mobile navigation is accessible;
- reduced-motion/focus behavior is present;
- missing optional data fails gracefully;
- excluded Morgen routes/modules/assets are not active dependencies;
- no CASE-PROD/PROD migration runs on Gloskin;
- no public Morgen branding/handles/selectors remain in the production Gloskin runtime except provenance comments/tests;
- representative PHP/JS/runtime checks pass;
- documentation remains synchronized with the implementation.

## 18. Provenance only

The normalized requirements originated from four raw files in `yudanfahmie/project-9901`. Their immutable blob identifiers are retained in `docs/source-notes.md` for auditability.

Those source files are **not part of the normal development workflow**. If this document and another canonical Gloskin repository document disagree, fix the canonical Gloskin documents in one coherent commit; do not silently fall back to raw project interpretation.
