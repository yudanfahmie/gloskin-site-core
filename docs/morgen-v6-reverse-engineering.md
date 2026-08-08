# Morgen UI V6 Reverse Engineering

## Purpose

This document records the reverse-engineering conclusions required to adapt the pinned Morgen UI V6 baseline into Gloskin Site Core safely.

Pinned source:

- repository: `yudanfahmie/morgen-core`
- commit: `374432cee6380e0aa0f81390e26b990147e5e58d`

The goal is not source-code parity. The goal is to preserve proven presentation behavior while preventing Morgen's industrial domain, historical production repairs and unrelated backend systems from entering Gloskin.

## Executive conclusion

**Do not clone `morgen-plugin/` wholesale.**

Morgen V6 is not a clean isolated theme layer. Its current composition root, shell, navigation, asset registry and compatibility services are intertwined with:

- UI V1-V5 compatibility;
- Morgen product data;
- industrial categories;
- Applications;
- Hammer;
- Quality Testing;
- Technical Library/Documents/PDFs;
- inquiry/mail/form-security infrastructure;
- SEO/proxy helpers;
- EN/DE routing/content;
- diagnosis/telemetry;
- CASE-PROD/PROD migrations, repair and reconciliation history.

The correct adaptation strategy is **pattern extraction plus a new Gloskin composition root**, not copy-then-delete.

## 1. Repository-level observation

The pinned Morgen plugin is a mature production plugin with a large bootstrap, extensive `includes/`, templates, assets, migration resources and many historical repair modules.

This history is valuable because it contains hardened frontend patterns, but it also means filenames that sound generic can have transitive dependencies on unrelated Morgen production systems.

Rule: evaluate dependencies by actual requires/hooks/data access, not filename.

## 2. Public composition root

Source:

`morgen-plugin/includes/class-morgen-core-public-ui-bootstrap.php`

Observed behavior:

- boots the canonical asset owner;
- boots UI6 cleanup ownership;
- boots UI6 motion;
- then boots a retained-service registry containing Admin Library, Admin Library cover compatibility, proxy system author, proxy featured image, form-security classes, Contact UI, settings compatibility/hardening and deployment resource guard;
- finally requires the build profile and UI6 feedback migration.

### Decision: REWRITE FROM SCRATCH

This class is the clearest example of a transitive dependency hazard. Copying it would immediately reintroduce several systems Gloskin explicitly excludes.

Create a new `Gloskin_Site_Core_Plugin` or equivalent composition root that boots only:

- Gloskin assets;
- Gloskin content types/meta;
- template routing;
- Gloskin navigation;
- WooCommerce presentation integration;
- form presentation integration;
- minimal admin/settings services that actually exist.

No compatibility retained-service registry is required for a fresh Gloskin plugin.

## 3. V6 shell

Source:

`morgen-plugin/templates/morgen-shell-v6.php`

Observed direct data/domain dependencies include:

- Morgen route/system state;
- products;
- product categories;
- Technical Library documents;
- Applications;
- Quality Testing;
- individual product lookup;
- custom breadcrumbs;
- Rank Math ownership checks;
- contact state;
- language-dependent behavior;
- document/PDF thumbnail helpers and industrial fallback graphics.

Observed route families include product/category, home, products, About Morgen, Applications, Technical Library, Contact, Quality Testing, Hammer and default/not-found paths.

### Decision: STRUCTURE ONLY

Do not copy this file as the Gloskin router/shell.

Potentially reusable ideas:

- one global shell coordinating header/main/footer;
- compact local render helpers where genuinely generic;
- consistent route-to-template dispatch;
- safe 404 status handling;
- reusable section composition discipline.

Must be replaced:

- all Morgen data loading;
- product/category custom routing;
- document/PDF code;
- application/quality/Hammer routes;
- Rank Math ownership logic;
- language-specific title helper behavior;
- industrial SVG/document fallback systems.

## 4. Header and mobile navigation

Source:

`morgen-plugin/templates/v6/header.php`

Useful behavior:

- desktop primary navigation;
- disclosure buttons separated from parent links;
- `aria-expanded`, `aria-controls` and submenu relationships;
- active-link state;
- mobile dialog/drawer template;
- mobile nested disclosure controls;
- explicit close/backdrop controls.

Morgen-specific behavior:

- Morgen logo/system calls;
- fallback nav items for Products, Brands, Applications, Technical Library, Quality Testing, About and Contact;
- EN/DE switch;
- Quick Inquiry trigger;
- Technical Library drawer link;
- `mg6-*` classes and `data-morgen-*` attributes.

### Decision: REBUILD MARKUP USING INTERACTION PATTERNS

Preserve the accessibility model and interaction quality, not the registry/content/identifiers.

Target Gloskin navigation should use `gloskin-ui1-*` or another Gloskin-owned namespace and route only to approved Gloskin destinations.

CTA semantics should be booking/contact/WhatsApp according to current Gloskin configuration, not “Quick Inquiry”.

## 5. Footer

Source:

`morgen-plugin/templates/v6/footer.php`

Useful patterns:

- structured footer grid;
- CTA band;
- semantic grouped navigation;
- contact/social quick channels;
- responsive composition.

Morgen-specific content:

- technical application desk copy;
- Technical Library links;
- Applications links;
- product family links;
- Quality & Testing;
- Hammer;
- industrial technical notice;
- Morgen identity/version marker.

### Decision: REBUILD CONTENT, ADAPT LAYOUT CONCEPTS

Do not port industrial columns or notices. Gloskin footer content should be driven by its actual page inventory/contact information.

## 6. Navigation service

Source:

`morgen-plugin/includes/class-morgen-core-navigation.php`

Observed behavior:

- explicit versions `v1` through `v6`;
- default registry with English/German labels;
- product/category child generation;
- Applications generation;
- Brands/Hammer/Morgen brand routes;
- route catalog per UI version;
- managed-navigation normalization/sanitization utilities.

### Decision: REWRITE REGISTRY, OPTIONALLY REUSE SMALL PURE ALGORITHMS

Do not port:

- version switching;
- route catalog;
- product/application/brand discovery;
- EN/DE fields;
- Morgen option keys.

Potentially reusable after extraction/review:

- item sanitization;
- tree normalization;
- parent/child validation;
- active-state calculation patterns.

The Gloskin route catalog must originate from `docs/page-matrix.csv` / WordPress objects, not Morgen's registry.

## 7. Asset owner

Source:

`morgen-plugin/includes/class-morgen-core-assets.php`

Strong reusable ideas:

- one canonical asset owner;
- registry-driven registration;
- dependency validation;
- deterministic asset versioning;
- SHA-based runtime manifest support;
- explicit distinction between style/script dependencies.

Morgen-specific baggage:

- enforced `morgen-ui6-*` handle namespace;
- legacy aliases/path maps;
- retired owner compatibility;
- diagnosis snapshot integration;
- “complete-public-ui6-set” strategy that enqueues the entire registry;
- assumptions about the Morgen plugin file/path.

### Decision: ADAPT THE PATTERN, NOT THE CLASS

Build a fresh `Gloskin_Site_Core_Assets` with:

- `gloskin-ui1-*` handles;
- only assets actually used by Gloskin;
- no Morgen compatibility aliases;
- no legacy-path map;
- no diagnosis bundle dependency;
- conditional/page-aware loading where useful;
- optional deterministic manifest/version strategy.

## 8. Canonical UI6 asset registry

Source:

`morgen-plugin/includes/morgen-core-asset-registry.php`

Current styles include:

- Splide vendor;
- UI6 core;
- carousel;
- hero navigation;
- home showcase;
- gallery loader;
- motion;
- application cards;
- technical library;
- ornaments;
- homepage process controls.

Current scripts include:

- Splide vendor;
- UI6 core;
- gallery loader/viewer;
- carousel;
- hero navigation;
- motion;
- presentation;
- application reveal;
- technical library;
- ornaments.

All are part of the current canonical set, including excluded Application and Technical Library features.

### Decision: BUILD A NEW REGISTRY

Candidate Gloskin assets to evaluate and adapt:

- core layout/runtime;
- Splide only if a Gloskin carousel/gallery actually uses it;
- carousel;
- hero navigation;
- gallery loader/viewer;
- motion/presentation where generic and required.

Do not import:

- application cards/reveal;
- technical library assets.

Evaluate before importing:

- ornaments;
- homepage process controls;
- any asset that contains industrial selectors/copy or assumes Morgen route state.

## 9. UI6 motion coordinator

Source:

`morgen-plugin/includes/class-morgen-core-ui6-motion.php`

Actual class responsibility is small: promote the motion script to a head/prepaint group and add diagnosis readback.

### Decision: REWRITE SMALL

If Gloskin uses a prepaint motion runtime, implement a small Gloskin-owned coordinator. Drop diagnosis integration and Morgen handle names.

Do not copy the class merely to preserve naming/provenance.

## 10. Drawer portal

Source:

`morgen-plugin/includes/class-morgen-core-v6-drawer-portal.php`

Observed dependencies:

- `Morgen_Core_System`;
- UI version check;
- Morgen request check;
- reads old drawer CSS/JS files directly;
- dequeues historical `morgen-case-prod-004`;
- injects inline assets into Morgen handles.

### Decision: DO NOT COPY

Rebuild the mobile drawer as part of Gloskin's normal frontend assets/templates. There is no Gloskin reason to retain historical body-reparenting compatibility or CASE-PROD cleanup.

## 11. Home Hero V6 compatibility class

Source:

`morgen-plugin/includes/class-morgen-core-home-hero-v6.php`

The class is explicitly a compatibility shell for a retired asset owner. It boots Public UI Bootstrap and exposes no-op compatibility methods.

### Decision: REMOVE ENTIRELY

Do not use this as the Gloskin hero implementation. Build the Gloskin hero from the relevant V6 template/CSS/JS behavior only.

## 12. Homepage media admin

Source:

`morgen-plugin/includes/class-morgen-core-homepage-media-admin.php`

Useful engineering patterns:

- attachment validation;
- media-library integration;
- maximum payload/item limits;
- save locking;
- state hashing;
- snapshots/audit/rollback concepts;
- explicit poster handling for video.

Morgen coupling:

- `morgen-content` admin page;
- Morgen option/action keys;
- Morgen admin asset dependencies;
- English/German caption/title/description fields;
- large production-hardening history.

### Decision: PATTERN REFERENCE ONLY

If Gloskin ultimately needs plugin-owned hero/gallery media editing, build a smaller Gloskin-specific service using normal WordPress Media Library selection and only fields that Gloskin actually owns.

Do not inherit snapshots/audit/rollback or bilingual field complexity unless a real requirement justifies it.

## 13. V6 media alt repair

Source:

`morgen-plugin/includes/class-morgen-core-v6-media-alt.php`

Observed coupling:

- Morgen content option;
- repair/version state;
- hard-coded hydraulic/industrial hero alt defaults;
- hard-coded Quality Testing alt defaults;
- attachment metadata synchronization migration;
- diagnosis readback.

### Decision: REMOVE CLASS, RETAIN ACCESSIBILITY REQUIREMENT

Gloskin should use approved WordPress attachment alt text and sensible template fallback behavior. No inherited industrial defaults or migration state.

## 14. Inquiry mail

Source:

`morgen-plugin/includes/class-morgen-core-inquiry-mail.php`

Observed behavior:

- hooks `wp_mail`;
- rewrites recipients/reply-to;
- fingerprint/deduplication locks;
- scheduled lock expiry;
- auto-replies;
- Morgen mail settings;
- Morgen-specific contact submission hooks.

### Decision: REMOVE

Gloskin uses an external WordPress form layer. Do not port this class or its mail/settings stack.

## 15. Form security/contact stack

The Public UI Bootstrap explicitly retains multiple form-security/contact classes because Morgen owns its inquiry flow.

### Decision: DO NOT TRANSITIVELY IMPORT

If Gloskin later implements any plugin-owned form/settings write, apply standard WordPress security locally: capabilities, nonces, sanitization, escaping and rate/security controls appropriate to that specific feature. Do not bring the Morgen inquiry stack merely for “hardening”.

## 16. Build profile and migrations

Sources:

- `morgen-plugin/includes/class-morgen-core-build-profile.php`
- `morgen-plugin/includes/morgen-core-build-profile.generated.php`

Key findings:

- repository default model contains migration concepts;
- generated profile in the pinned source currently sets `case_id` to `CASE-PROD-011`;
- generated profile has `migration_enabled => true`;
- payload is `resources/migrations/CASE-PROD-011`;
- build-profile file also boots diagnosis, admin horizontal tabs, homepage media persistence and homepage media save v2.

### Decision: DO NOT IMPORT MORGEN BUILD PROFILE

Gloskin begins as a clean plugin with no historical migration payload.

If migrations are later needed, introduce a Gloskin-specific migration mechanism only when there is actual persisted Gloskin data to migrate. Fresh development should not include CASE-PROD/PROD identifiers, payloads, repair flags or incident reconciliation.

## 17. Technical Library, Documents and PDF stack

Morgen has public/admin Technical Library functionality, Documents services, PDF preview/raster/poster resources and secure/download behavior.

### Decision: REMOVE ALL

There is no normalized Gloskin developer requirement for a technical-document catalog or PDF download subsystem.

This exclusion includes public templates, admin library, document indexes, previews, raster generation, signed/secure download logic, packaged PDF assets and compatibility migrations.

## 18. Morgen product system

Morgen's shell and system are built around a custom industrial product catalog, categories, product routes and product migrations/fixes.

### Decision: REMOVE/REPLACE WITH WOOCOMMERCE INTEGRATION

Do not adapt Morgen product records into skincare records.

Use WooCommerce products directly. Gloskin only provides skincare landing mappings and presentation styling/hooks.

Remove product copy migrations, product-image migration/repair classes, product-specific CASE/PROD fixes and custom product/category routing.

## 19. Applications, Hammer and Quality Testing

These are Morgen industrial content domains with their own routes/assets/data/migrations.

### Decision: REMOVE

No equivalent domain is required by the normalized Gloskin site.

Do not rename Applications to Treatments or Hammer to another Gloskin concept. That would preserve the wrong data model. Build Gloskin treatment/clinic/doctor models intentionally.

## 20. SEO/proxy/image-SEO systems

Morgen includes proxy-page/featured-image/system-author and SEO-oriented helpers, and its shell has Rank Math ownership awareness.

### Decision: REMOVE FROM GLoskin SITE CORE

SEO/GEO/schema management is outside this repository's developer scope.

Accessibility alt support is still required, but implement it as normal semantic media behavior rather than importing SEO repair/proxy infrastructure.

## 21. EN/DE architecture and i18n

Morgen navigation/header/admin data carry English/German fields and route behavior.

### Decision: REMOVE MORGEN MULTILINGUAL MODEL

Gloskin strings should use normal WordPress internationalization functions/text domain. The project may hold approved Indonesian/English content, but a multilingual route/switcher system must be a separate explicit requirement.

## 22. Telemetry, diagnosis, reconciliation and incident tooling

Morgen contains diagnosis bundles, telemetry, site reconciliation, CASE-PROD repair state and numerous production incident controls.

### Decision: EXCLUDE BY DEFAULT

Use normal WordPress logging/debugging during development. Add a small production-safe diagnostic capability only if a concrete Gloskin operational need appears.

Do not import historical incident machinery as preventive complexity.

## 23. What can be reused with highest confidence

The following are the best reuse candidates, subject to actual source inspection during implementation:

- responsive spacing/grid ideas from V6 CSS;
- header/nav disclosure accessibility patterns;
- mobile drawer interaction behavior after a clean rewrite;
- focus/reduced-motion behavior;
- generic carousel/gallery UX patterns;
- Splide vendor dependency only if still needed;
- deterministic asset registry/versioning design;
- image dimension/loading patterns;
- safe WordPress media-selection validation patterns;
- small pure sanitization/tree helpers where they have no domain coupling.

Reuse should be measured by fewer lines and fewer dependencies, not by maximizing copied files.

## 24. What must never be used as a shortcut

Do not:

- copy Public UI Bootstrap and disable services afterward;
- copy the complete asset registry and hide excluded components with CSS;
- copy the V6 shell and leave dormant document/product/application branches;
- rename Morgen products to skincare products;
- rename Applications to Treatments;
- keep V1-V5 “just in case”;
- retain CASE-PROD/PROD migrations “for safety”;
- keep `morgen-*` aliases for a fresh Gloskin runtime;
- port inquiry/mail infrastructure simply because Contact exists;
- port EN/DE routing simply because two languages appeared in source content;
- use raw `project-9901` as an implementation dependency.

## 25. Dependency-cut sequence

When implementation begins, perform dependency cuts in this order:

1. create fresh Gloskin plugin bootstrap/composition root;
2. create fresh Gloskin asset owner/registry with no excluded assets;
3. create Gloskin content types/data contracts;
4. create Gloskin route/template resolution;
5. rebuild navigation from the Gloskin route contract;
6. adapt shell/header/footer markup patterns into Gloskin namespace;
7. selectively adapt core CSS/JS primitives;
8. add page-family templates;
9. add WooCommerce presentation integration;
10. add external-form presentation integration;
11. add only the admin editing/settings needed by actual Gloskin data;
12. static-scan for Morgen/industrial/history identifiers and remove accidental dependencies.

This sequence deliberately prevents excluded Morgen systems from becoming foundational dependencies.

## 26. Static exclusion targets

The final runtime should not depend on identifiers/modules representing:

- `technical-library`;
- Morgen Documents/PDF/download tokens;
- `applications` domain;
- Hammer pages/assets;
- `quality-testing`;
- Morgen custom products/categories;
- `CASE-PROD-*` or `PROD-*` migration payloads;
- UI `v1` through `v5` presentation selection;
- `Morgen_Core_Inquiry_Mail` and associated mail admin/settings;
- Morgen proxy/Rank Math management;
- EN/DE routing state;
- diagnosis bundle/telemetry unless deliberately reintroduced under a Gloskin requirement.

Public asset handles/selectors should be Gloskin-owned. Historical provenance references may remain in comments/docs/tests only.

## 27. Developer handoff rule

A future developer may inspect the pinned `morgen-core` source to implement a documented reuse decision. They should not need to reverse-engineer the Gloskin raw project again.

If a new Morgen dependency is discovered that this document does not classify, evaluate it against `docs/developer-source-of-truth.md` and update `docs/prune-matrix.csv` in the same coherent implementation commit.