# Gloskin Site Core Implementation Plan

## 1. Objective

Create `gloskin-site-core` as a WordPress presentation and page-builder plugin for the Gloskin website by adapting the proven UI V6 foundation from `morgen-core`.

The plugin should behave like a theme-level presentation system while remaining installable and manageable as a plugin. WooCommerce remains the authoritative commerce platform. Gloskin Site Core must not create a parallel product database or replace WooCommerce product, cart, checkout, order, payment, or customer logic.

This repository currently contains setup and implementation guidance only. The actual Morgen cloning/adaptation is a later implementation task.

## 2. Immutable source references

### Morgen source baseline

- Repository: `yudanfahmie/morgen-core`
- Commit: `374432cee6380e0aa0f81390e26b990147e5e58d`
- Presentation baseline: UI V6 only

Do not use a moving `main` branch as the implementation baseline unless the owner explicitly asks for a new review of Morgen changes.

### Gloskin raw requirements

Repository: `yudanfahmie/project-9901`

Treat that repository as read-only reference material. Do not store Gloskin implementation work there.

Pinned raw files:

- `gloskin/Handover_Tim_IT_Gloskin.docx`
  - blob `8f9546d13662046927bc8e547931f68841a78c83`
- `gloskin/Gloskin_Project_Framework_Final (1).xlsx`
  - blob `a3b09371bff4b6ca3a5672d9213e1179172a1887`
- `gloskin/Gloskin_Client_Project_Tracker.xlsx`
  - blob `e004d3efa937c296d6d5689d123b666f11e93464`
- `gloskin/Data Digital Marketing Onboarding.xlsx`
  - blob `073cacc37672fbc1e4f6a58c426eb5bb18e971e2`

Only developer-relevant website requirements should be translated into this repository. SEO/GEO delivery, backlinks, media placement, social campaigns, marketing reporting, and retainer work are out of scope.

## 3. Requirement reconciliation

The raw Gloskin documents contain inconsistent headline page counts such as `21 core pages` and `33 pages`. Engineering must follow the explicit route/task inventory rather than those stale counters.

The raw implementation tasks explicitly require:

- Homepage
- About
- Treatments Hub
- 8 treatment-category pages
- Skincare Hub
- 7 skincare-category pages
- Clinics Hub
- 9 clinic-location pages
- Contact
- Insights Hub
- Shop Hub
- Doctors Hub
- 13 doctor pages
- up to 20 WooCommerce product pages

If all explicit visitor-facing URLs are present simultaneously, the site inventory can reach roughly 66 URLs. This is a route inventory, not a request for duplicate content storage. WooCommerce single-product pages remain WooCommerce-owned.

The onboarding material currently contains more treatment/service names than the framework's required eight treatment categories. Therefore the implementation must support exactly eight approved treatment-category records without hard-coding a speculative grouping. Final names and slugs are content data.

The onboarding material identifies seven provisional skincare group labels:

- Facial Wash
- Day Cream/Sunscreen
- Toner
- Serum
- Acne Care
- Anti-Aging
- Brightening & Pigmentation Care

Treat those as configurable mappings to WooCommerce categories, not as a custom product catalog.

## 4. Repository and plugin identity

Repository: `yudanfahmie/gloskin-site-core`

Recommended production plugin identity:

- Plugin Name: `Gloskin Site Core`
- directory: `gloskin-site-core/`
- text domain: `gloskin-site-core`
- PHP class prefix: `Gloskin_Site_Core_`
- function prefix: `gloskin_site_core_`
- initial implementation version: `0.1.0`

No public Morgen naming should remain in classes, functions, handles, options, routes, CSS selectors, asset names, admin labels, or generated markup except historical comments explaining provenance.

## 5. Repository workflow

This repository is main-only.

- Work directly on `main`.
- Do not create feature branches or pull requests unless the owner explicitly changes this rule.
- Pull latest `origin/main` and record HEAD before editing.
- Group related files into effective commits.
- Do not create one commit per file.
- Do not use temporary/probe commits.
- Commit messages must be short, lowercase, and action-oriented.
- Verify remote `main` after every push.

See `CONTRIBUTING.md` for the complete workflow contract.

## 6. Ownership boundaries

### Gloskin Site Core owns

- public header, navigation, footer, and global shell;
- responsive layout system and design tokens;
- page-family templates;
- reusable presentation components;
- Gloskin-specific treatment, clinic, and doctor presentation/content structures;
- styling and layout integration with WordPress posts and WooCommerce output;
- lightweight settings required by the presentation layer.

### WooCommerce owns

- products and variations;
- product categories and attributes;
- product images;
- price and stock;
- add-to-cart behavior;
- cart;
- checkout;
- orders;
- customer/account flows;
- payment gateways;
- product CRUD and product administration.

Gloskin Site Core may read and render WooCommerce data or style WooCommerce-supported output. It must not recreate Morgen's product-management layer.

## 7. Recommended WordPress content model

Use native WordPress concepts wherever practical.

Recommended model:

- Native Pages for Home, About, Skincare Hub, Clinics Hub, Doctors Hub, Contact, Insights Hub, and Shop Hub.
- Lightweight CPT `gloskin_treatment` for eight treatment-category records.
- Lightweight CPT `gloskin_clinic` for nine clinic records.
- Lightweight CPT `gloskin_doctor` for thirteen doctor records.
- Seven skincare landing pages map to WooCommerce product categories.
- Native WordPress posts power Insights content.
- WooCommerce owns product URLs.

A developer may choose native Pages instead of a CPT where that produces a simpler maintainable result, but the route contract and editing capability must remain equivalent.

## 8. Required route contract

The minimum route families are:

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
- WooCommerce-managed product, cart, and checkout URLs

Do not add Morgen Technical Library, PDF download, custom product catalog, Applications, Hammer, or Quality Testing routes.

See `docs/page-matrix.csv` for the detailed page inventory.

## 9. Page-family directions

### Homepage

Use Morgen V6 only as the structural starting point. The final page is Gloskin UI v1, not a Morgen-branded variant.

Recommended structure:

1. global header/navigation;
2. responsive hero;
3. treatment discovery linking to eight categories;
4. clinic discovery linking to nine branches;
5. doctor preview;
6. skincare/shop preview using WooCommerce data;
7. insights preview using native WordPress posts;
8. booking/contact CTA;
9. global footer.

Remove industrial product language, document/download cards, specification-table motifs, and other Morgen-specific visual semantics.

### About

Support official Gloskin overview, Vision, Mission, Values, clinic-network summary, doctor/team teaser, and a contact/booking CTA.

The raw documents mention Indonesian and English company descriptions. Do not build a Morgen-style multilingual routing system unless a later requirement explicitly asks for one.

### Treatments Hub

Display exactly eight approved treatment categories with a consistent card system and cross-links to relevant clinics/doctors where data exists.

### Treatment category

Each record should support:

- title;
- summary/description;
- benefits;
- contraindications;
- hero/featured media;
- related clinics;
- related doctors;
- booking/contact CTA.

Templates must remain neutral containers and must not hard-code unapproved medical claims.

### Skincare Hub

Provide seven category landing links, a product discovery section driven by WooCommerce, and a Shop link.

### Skincare category landing

Each landing page should:

- render an approved title and intro;
- map to a WooCommerce product-category slug;
- render WooCommerce products through supported WooCommerce output/hooks/shortcodes;
- show a useful empty state;
- avoid duplicate product records or custom product CRUD.

### Clinics Hub

Display all nine required branches consistently with image, area/city, opening-hours summary, and detail link.

Required branches:

- Kebayoran Baru
- Tebet
- Bekasi
- Cibubur
- Serpong
- Surabaya
- Banjarmasin
- Balikpapan
- Denpasar

### Clinic detail

The raw requirements explicitly call for NAP consistency, Google Maps, operating hours, photos, doctors per branch, and branch-specific WhatsApp.

Recommended order:

1. branch hero/gallery;
2. branch title and location introduction;
3. NAP block: name, address, phone;
4. operating hours;
5. Google Maps embed;
6. branch WhatsApp booking CTA;
7. doctors practicing at the branch;
8. related treatments;
9. contact/booking CTA.

Support at least three branch images when approved assets are available.

### Doctors Hub

Display all thirteen doctor records with photo, full name/title, specialization, branch information, and detail-page link.

### Doctor detail

Support:

- professional photo;
- full name;
- title/degree;
- specialization;
- practice branches;
- SIP number when available;
- credentials/profile content;
- related treatments;
- booking CTA.

Keep data semantically clean. SEO/schema management is outside this repository scope.

### Contact

Do not port Morgen's custom inquiry/mail backend by default.

Provide:

- contact intro;
- branch contact cards/selector;
- branch WhatsApp links;
- configurable form-shortcode/block area for the site's chosen form plugin;
- graceful fallback if that form integration is unavailable.

Submission and auto-email behavior belong to the form plugin/configuration unless a later task explicitly adds custom backend behavior.

### Insights Hub

Use native WordPress posts. Provide a hero, post grid/list, normal WordPress pagination or load-more behavior, and an accessible empty state.

### Shop Hub

Treat Shop as a page-builder wrapper around WooCommerce-supported output. Preserve WooCommerce product/cart/checkout links and avoid a custom Morgen-style product manager.

### WooCommerce product/cart/checkout presentation

Gloskin Site Core may style and structure WooCommerce-owned pages using safe hooks/templates.

Single-product presentation should remain compatible with WooCommerce gallery, title, price, description, attributes, add-to-cart controls, and related products. BPOM, composition, and usage information should be read from WooCommerce-managed attributes/meta when present.

Cart and checkout logic remain native WooCommerce.

## 10. Design-system direction

The raw framework requires three design directions before final development:

1. `Medical Professional` — clean navy/white, authority-oriented serif typography.
2. `Modern Aesthetic` — pastel, sans-serif, visual-led.
3. `Premium Luxury` — dark tones, gold accent, editorial typography.

Implementation guidance:

- use one shared component system;
- implement design directions primarily through design tokens/variants, not three copied template trees;
- use Morgen V6 as code/layout provenance only;
- expose the result as Gloskin UI v1;
- do not carry Morgen UI V1-V5 into the production plugin;
- after a direction is selected, remove unused production-facing variant complexity where practical.

## 11. Morgen reuse and pruning

### Retain or adapt only when useful

- V6 shell patterns;
- V6 header/footer structure;
- responsive grid/spacing behavior;
- keyboard focus and reduced-motion behavior;
- mobile navigation/drawer patterns;
- generic gallery/carousel behavior when Gloskin uses it;
- generic image-loading helpers;
- generic admin save/security patterns only for retained Gloskin settings;
- generic release-validation ideas.

### Remove or replace

- UI V1-V5 templates/assets and presentation switching;
- Morgen custom product management and product data layer;
- Morgen product templates/routes;
- PROD product image/copy migrations and repair code;
- Technical Library public UI and admin;
- Documents subsystem;
- PDF preview/poster generation;
- signed PDF/download-token flows;
- packaged PDF resources and related migration payloads;
- Applications pages/assets and application seed migrations;
- Hammer pages/assets;
- Quality Testing pages/assets;
- industrial categories/brand content;
- CASE-PROD/PROD historical production repair payloads;
- German routing/content and Morgen-specific multilingual UI;
- Rank Math proxy-page management and SEO scoring controls;
- Morgen-specific telemetry/diagnosis code without a Gloskin use case;
- custom Morgen inquiry/mail transport when Contact delegates to a form plugin.

See `docs/prune-matrix.csv` for the removal checklist.

## 12. Safe cloning sequence

### Batch 0 — verify source and workspace

1. Confirm repository is `yudanfahmie/gloskin-site-core`.
2. Checkout and pull `main`; do not create a branch.
3. Record HEAD.
4. Confirm this planning workspace is present.
5. Fetch Morgen commit `374432cee6380e0aa0f81390e26b990147e5e58d` read-only.
6. Confirm the raw Gloskin blob SHAs listed above.
7. Do not modify `project-9901`.

### Batch 1 — import the V6-capable foundation

1. Inspect Morgen V6 bootstrap, shell, templates, assets, and required shared classes.
2. Build a dependency map before deleting modules.
3. Import only the dependency set needed to make V6 functional.
4. Rename plugin identity and public namespaces to Gloskin.
5. Make V6 the only presentation baseline.
6. Remove UI switching for V1-V5.
7. Confirm plugin activation before page-specific development.

### Batch 2 — remove Morgen-only domains

1. Replace Morgen route/data registry with Gloskin routes.
2. Remove Technical Library/Documents/PDF dependencies.
3. Remove Morgen product-management dependencies.
4. Remove Applications/Hammer/Quality Testing dependencies.
5. Remove historical CASE-PROD/PROD payloads.
6. Remove Morgen-specific SEO proxy controls.
7. Search for retired identifiers and dead asset handles.
8. Re-test plugin activation after removals.

### Batch 3 — create Gloskin content model

1. Register treatments, clinics, and doctors using the chosen lightweight approach.
2. Create fixed page requirements without duplicating existing content.
3. Define seven skincare-to-WooCommerce-category mappings.
4. Support all nine clinic branches.
5. Support doctor-to-clinic relationships.
6. Support treatment-to-doctor/clinic relationships when needed.
7. Keep content editable through normal WordPress administration.

### Batch 4 — create Gloskin UI v1

1. Rename CSS/JS handles and selectors.
2. Define Gloskin design tokens.
3. Adapt V6 header, mobile navigation, footer, buttons, cards, grids, hero, and required galleries.
4. Implement three design directions as shared-system variants.
5. Verify desktop/mobile behavior before individual page families.

### Batch 5 — build core page families

Implement in this order:

1. Homepage
2. About
3. Treatments Hub + treatment template
4. Skincare Hub + skincare category template
5. Clinics Hub + clinic detail template
6. Contact
7. Insights Hub
8. Shop Hub

### Batch 6 — doctors and WooCommerce presentation

1. Doctors Hub
2. doctor detail template
3. WooCommerce single-product presentation
4. WooCommerce cart/checkout presentation compatibility
5. verify WooCommerce remains the only product authority

### Batch 7 — cleanup and hardening

1. Search for remaining `Morgen`, `morgen_`, `mg6`, Technical Library, PDF, Hammer, Applications, Quality Testing, and product-manager identifiers.
2. Rename legitimate reusable internals or delete dead code.
3. Remove unused assets/dependencies.
4. Verify no V1-V5 presentation assets are loaded.
5. Verify no historical migration payload runs on activation.
6. Verify deactivation does not damage WooCommerce-owned data.
7. Verify uninstall cannot delete WooCommerce products/orders.

### Batch 8 — verification

Test at minimum:

- plugin activation/deactivation;
- page/route resolution and 404 behavior;
- mobile navigation;
- eight treatment records/templates;
- seven skincare landing mappings;
- all nine clinic URLs;
- thirteen doctor records/templates;
- Shop with products and empty state;
- WooCommerce single product;
- add-to-cart handoff;
- cart/checkout presentation compatibility;
- contact form integration and missing-integration fallback;
- Google Maps embeds;
- branch WhatsApp links;
- responsive layout;
- keyboard focus and reduced motion;
- representative Chrome/Safari/Firefox rendering;
- no PHP fatal errors;
- no red JavaScript console errors on representative pages.

SEO scores, GSC, GBP, analytics, backlinks, GEO, and marketing KPIs are not acceptance criteria for this repository.

## 13. Minimum data contracts

### Clinic

- branch name
- slug
- address
- phone
- WhatsApp
- operating hours
- map embed/location URL
- branch images
- associated doctors
- associated treatments when used

### Doctor

- full name
- title/degree
- specialization
- practice branches
- SIP number when available
- portrait
- credentials/profile

### Treatment

- name
- slug
- description
- benefits
- contraindications
- featured media
- related clinics
- related doctors

### Skincare landing mapping

- page title
- page slug
- WooCommerce product-category slug
- optional intro
- optional featured media

No product CRUD fields belong in this plugin-owned data contract.

## 14. Dependency-removal rule

Never delete a Morgen file merely because its filename looks irrelevant.

For every removal:

1. locate `require/include` references;
2. locate actions/filters;
3. locate template-routing references;
4. locate enqueued handles;
5. locate admin-menu references;
6. locate test references;
7. replace/remove callers first;
8. delete the module only after runtime references are gone;
9. run activation and representative route tests.

This is important because the Morgen V6 shell references several industrial/product domains directly.

## 15. Explicitly out of scope

Do not implement the following unless a future task adds them:

- SEO/GEO strategy or content production;
- Rank Math scoring workflows;
- GSC/GA4/GBP setup or monitoring;
- backlinks/citation campaigns;
- media placement;
- social/TikTok work;
- marketing reporting;
- payment-gateway business configuration beyond preserving WooCommerce compatibility;
- WooCommerce product/order backend replacement;
- domain/DNS migration;
- redirect-map execution;
- medical approval workflow tooling.

## 16. Definition of done for the future cloning task

The future cloning/adaptation task is complete only when:

1. production `gloskin-site-core` plugin files exist on remote `main`;
2. Morgen V6 is the sole Morgen-derived presentation baseline and is exposed as Gloskin UI v1;
3. no V1-V5 UI switching remains;
4. every route family in `docs/page-matrix.csv` has an implementation path;
5. no Morgen product manager remains;
6. no Technical Library/Documents/PDF-download subsystem remains;
7. WooCommerce remains the product/commerce authority;
8. all nine clinic branches are supported;
9. all thirteen doctor records/templates are supported;
10. all eight treatment records/templates are supported;
11. seven skincare landing mappings are supported;
12. Contact delegates submission handling to the configured form layer unless explicitly changed;
13. retired Morgen routes/assets/classes are absent or unreachable;
14. representative activation/page/WooCommerce tests pass;
15. documentation describes the final Gloskin implementation rather than Morgen.
