# Gloskin Site Core Implementation Plan

## 1. Read this first

The requirements authority is `docs/developer-source-of-truth.md`.

A normal implementation task must **not** reopen or re-analyze `yudanfahmie/project-9901`. The relevant developer requirements have already been transformed into this repository. Historical source identifiers remain in `docs/source-notes.md` only for provenance.

The pinned Morgen implementation reference is:

- repository: `yudanfahmie/morgen-core`
- commit: `374432cee6380e0aa0f81390e26b990147e5e58d`
- presentation provenance: UI V6 only

Read before coding:

1. `CONTRIBUTING.md`
2. `docs/developer-source-of-truth.md`
3. `docs/content-data-contracts.md`
4. `docs/morgen-v6-reverse-engineering.md`
5. `docs/page-matrix.csv`
6. `docs/prune-matrix.csv`

## 2. Repository workflow

This repository is main-only.

Before every implementation change:

1. confirm repository `yudanfahmie/gloskin-site-core`;
2. checkout `main`;
3. pull latest `origin/main`;
4. record current HEAD;
5. inspect current production implementation and docs;
6. define one coherent outcome for the commit.

Do not create feature branches, temporary branches, PRs, probe commits or one-commit-per-file history.

Commit messages must remain short, lowercase and action-oriented.

## 3. Implementation strategy

Do **not** begin by copying the entire Morgen plugin.

Reverse engineering shows that the current Morgen V6 composition root transitively loads excluded domains and historical compatibility code. The Gloskin implementation must start with a fresh plugin composition root and import only approved patterns/files after dependency review.

The guiding rule is:

> Build Gloskin ownership first; then selectively adapt V6 behavior into it.

## 4. Target plugin identity

Recommended identity:

- Plugin Name: `Gloskin Site Core`
- directory: `plugin/gloskin-site-core/`
- text domain: `gloskin-site-core`
- class prefix: `Gloskin_Site_Core_`
- function prefix: `gloskin_site_core_`
- public UI namespace: `gloskin-ui1-*` or a consistently documented equivalent
- initial implementation version: `0.1.0`

No production public class, function, handle, option, selector, route, admin label or generated markup should remain Morgen-branded except comments/docs explaining provenance.

## 5. Batch 0 — establish clean Gloskin core

Goal: an activatable empty/minimal plugin with Gloskin ownership and no Morgen runtime dependency.

Tasks:

- create `plugin/gloskin-site-core/gloskin-site-core.php`;
- create a small plugin composition root;
- register activation/deactivation only if truly needed;
- establish constants/path/version helpers;
- establish WordPress text domain/i18n readiness;
- do not introduce migration payloads;
- do not introduce UI version switching;
- do not require any Morgen PHP file at runtime.

Exit criteria:

- plugin activates/deactivates without fatal errors;
- no `Morgen_Core_*` class is required to boot Gloskin;
- no CASE-PROD/PROD state exists;
- no frontend assets are loaded yet unless part of the minimal shell task.

Suggested commit message: `initialize gloskin core`

## 6. Batch 1 — asset foundation

Goal: build a fresh Gloskin asset owner inspired by the good parts of Morgen's registry design.

Tasks:

- create a minimal registry for Gloskin UI v1;
- use Gloskin-owned handles;
- support explicit style/script dependencies;
- use deterministic versioning, preferably content/file based;
- include only actual Gloskin assets;
- evaluate Splide only when a required carousel/gallery uses it;
- avoid compatibility aliases/legacy paths;
- avoid diagnosis hooks and retired owner compatibility;
- avoid globally enqueueing Application/Technical Library/other excluded feature assets.

Candidate V6 behavior to adapt after source review:

- core responsive primitives;
- carousel/gallery behavior;
- hero navigation behavior;
- reduced-motion/focus behavior;
- image loading/dimension behavior.

Explicitly do not import Application or Technical Library assets.

Exit criteria:

- asset handles are Gloskin-owned;
- registry contains no excluded feature assets;
- no `morgen-ui6-*`, `morgen-*` or `mg6-*` public runtime dependency remains in the new asset owner.

Suggested commit message: `build ui1 asset core`

## 7. Batch 2 — content models and data contracts

Goal: establish the Gloskin-owned data model before page rendering.

Implement:

- `gloskin_treatment` or equivalent for eight treatment categories;
- `gloskin_clinic` or equivalent for nine branches;
- `gloskin_doctor` or equivalent for thirteen doctors;
- registered meta/relationships defined in `docs/content-data-contracts.md`;
- skincare landing-to-WooCommerce category mapping;
- branch phone/WhatsApp/hours/map/media fields;
- doctor branch relationships;
- treatment related clinic/doctor relationships as required.

Use native WordPress storage. No custom DB table or ACF/framework dependency without explicit approval.

Do not seed unapproved treatment names, doctor identities or fabricated branch data.

Exit criteria:

- required records can be created/edited safely;
- relationships use a documented canonical storage direction;
- missing optional fields are accepted;
- all writes use capability/nonce/sanitization controls.

Suggested commit message: `model gloskin content`

## 8. Batch 3 — routing and templates

Goal: implement Gloskin routes without Morgen's virtual industrial route system.

Tasks:

- use native WordPress page/CPT routing wherever possible;
- create a small template-resolution layer only where the plugin must own presentation;
- support every family in `docs/page-matrix.csv`;
- preserve native WooCommerce routes;
- preserve native WordPress post permalinks for Insights;
- implement deliberate 404/not-found behavior through WordPress conventions.

Do not port Morgen's product/category/Application/Technical Library route catalog.

Exit criteria:

- all Gloskin route families resolve to an implementation path;
- Woo routes remain Woo-owned;
- excluded Morgen route strings are not runtime routes.

Suggested commit message: `define gloskin routes`

## 9. Batch 4 — shell, header, navigation and footer

Goal: adapt proven V6 interaction quality into a clean Gloskin UI v1 shell.

Tasks:

- build global shell from scratch;
- adapt V6 spacing/grid/accessibility patterns;
- rebuild desktop nav using Gloskin IA;
- rebuild mobile drawer with accessible dialog/disclosure/focus/close behavior;
- use Gloskin logo/identity placeholders only until approved assets are supplied;
- support booking/contact/WhatsApp CTA behavior;
- build Gloskin footer around actual Gloskin destinations/contact data.

Do not port:

- Morgen navigation registry;
- EN/DE switch;
- Quick Inquiry trigger infrastructure;
- Technical Library link;
- product/application/quality/Hammer footer sections;
- industrial notices;
- Morgen frontend version marker.

Exit criteria:

- shell is responsive and keyboard-usable;
- mobile drawer is focus-safe;
- no Morgen public namespace/content remains.

Suggested commit message: `adapt v6 shell`

## 10. Batch 5 — design tokens and UI variants

Goal: establish one component system capable of the three requested initial design directions.

Required design directions:

- Medical Professional;
- Modern Aesthetic;
- Premium Luxury.

Tasks:

- define typography, spacing, radius, surface, border, color and motion tokens;
- keep one component/template tree;
- implement design directions as token/variant sets;
- avoid three copied layouts;
- after a final direction is selected, make unused production variant complexity removable.

The production interface is always called Gloskin UI v1. Do not reintroduce V1-V6 presentation selection.

Suggested commit message: `define ui1 design tokens`

## 11. Batch 6 — core fixed pages

Build in a coherent sequence:

### Homepage

- hero;
- treatment discovery;
- nine-clinic discovery;
- doctor preview;
- WooCommerce skincare/shop preview;
- Insights preview;
- booking/contact CTA.

### About

- overview;
- Vision;
- Mission;
- Values;
- clinic-network summary;
- doctor/team teaser;
- CTA.

### Contact

- branch contact cards/selector;
- branch-specific WhatsApp;
- external form shortcode/block integration;
- missing-form fallback.

### Insights

- native Posts query;
- post grid/list;
- pagination/load-more using standard WordPress-compatible behavior;
- empty state.

Exit criteria: no industrial/Morgen presentation semantics remain.

Suggested commit message: `build core pages`

## 12. Batch 7 — treatments, clinics and doctors

### Treatments

- hub plus exactly eight configurable detail records;
- description, benefits, contraindications, media and relationships;
- public hub composition is hero → four-photo Treatment Finder → all eight informational records → one final CTA;
- finder uses canonical path baseline-concern chips, an explicit CTA, local positive-match scoring and at most eight SSR Woo Treatment Product cards;
- private question content remains admin-only and does not gate or enter the public finder;
- consultation results use the shared detail-only product-card variant, leaving the catalog/cart/Quick Add owner unchanged;
- no speculative grouping of the fifteen draft service labels.

### Clinics

- hub plus all nine required route identities;
- gallery, NAP, hours, map, branch WhatsApp, doctors, treatments;
- graceful missing-data behavior.

### Doctors

- hub plus thirteen configurable records;
- portrait, identity/title, specialization, branches, SIP when available, credentials, related treatments and booking CTA.

Exit criteria:

- all record counts/route families are supported;
- content remains editable, not hard-coded;
- relationships render correctly in both hub/detail contexts.

Suggested commit messages should follow actual coherent scope, e.g. `build clinic pages` or `build people and treatments`; do not force unrelated work into one giant commit if implementation naturally separates.

## 13. Batch 8 — skincare and WooCommerce presentation

Goal: integrate WooCommerce without duplicating it.

Tasks:

- Skincare Hub with seven configurable category links;
- seven category landings mapped to Woo terms;
- Shop wrapper around supported Woo output;
- single-product visual compatibility;
- cart and checkout visual compatibility;
- read BPOM/composition/usage from Woo-managed attributes/meta when present;
- preserve Woo hooks/extensions as much as possible;
- safe empty/dependency behavior when Woo content is unavailable.

Do not enforce catalog mode. Do not disable checkout. Do not implement Midtrans/Xendit logic.

Exit criteria:

- Woo product CRUD is the only product admin;
- add-to-cart/cart/checkout remain Woo behavior;
- payment gateway hooks remain intact;
- no Morgen product manager is present.

Suggested commit message: `integrate woocommerce views`

## 14. Batch 9 — media/admin refinements only if required

Do this only after real page/content needs are known.

Potential need: hero/gallery media configuration.

If required:

- use WordPress Media Library;
- validate attachment types/IDs;
- cap payload/item counts where appropriate;
- use nonce/capability checks;
- keep storage small and Gloskin-specific.

Do not copy Morgen Homepage Media Admin wholesale. Do not inherit EN/DE field structure, historical snapshots/audits or build-profile coupling unless a real requirement justifies equivalent behavior.

Suggested commit message: `add media controls`

## 15. Batch 10 — cleanup and dependency proof

Perform a static/runtime cleanup pass.

Search production code for accidental dependencies/identifiers including:

- `Morgen_Core_`;
- `morgen_core_`;
- `morgen-ui6-`;
- `mg6-`;
- `technical-library`;
- `applications` in Morgen-domain context;
- `hammer` in Morgen-domain context;
- `quality-testing`;
- `CASE-PROD`;
- `PROD-` historical migration IDs;
- Morgen mail/inquiry classes;
- Rank Math proxy management;
- V1-V5 presentation selectors.

Provenance comments/tests may mention Morgen; public/runtime identifiers should not.

Also confirm no raw Gloskin DOCX/XLSX file was copied into this repo.

Suggested commit message: `remove morgen legacy`

## 16. Verification contract

At minimum test/document:

- plugin activation/deactivation;
- no PHP fatal on representative routes;
- required route matrix;
- eight treatment records/templates;
- seven skincare mappings;
- nine clinic route identities;
- thirteen doctor records/templates;
- Contact form integration/fallback;
- branch WhatsApp behavior;
- map behavior;
- native Insights posts;
- Woo Shop/product/cart/checkout presentation;
- Woo ownership of commerce logic;
- responsive desktop/mobile behavior;
- keyboard navigation/focus;
- reduced motion;
- missing optional content;
- representative current Chrome/Safari/Firefox behavior;
- no red console errors on representative pages;
- no active excluded Morgen dependency.

SEO scores, GSC/GBP/analytics/backlinks/GEO/marketing KPIs are not acceptance criteria for this repository.

## 17. Handling missing client data

Do not block architecture work merely because content is pending, but do not fabricate content.

Known pending/configurable inputs are listed in `docs/developer-source-of-truth.md` and `docs/content-data-contracts.md`.

When a required value is missing:

- implement the field/data path;
- provide graceful template behavior;
- document the pending input;
- ask the owner/content team for the value when it becomes necessary for final population.

Do not reopen `project-9901` as a discovery shortcut.

## 18. Definition of done

An implementation phase is not complete until:

- the real production files are on remote `main`;
- remote `main` HEAD is verified;
- the final commit includes all files required for its coherent outcome;
- available checks pass;
- the runtime does not depend on excluded Morgen modules;
- documentation is updated in the same change when architecture/data contracts change.

The complete product-level acceptance definition remains in `docs/developer-source-of-truth.md`.
