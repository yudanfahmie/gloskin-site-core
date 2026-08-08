# Verification Workspace

The implementation task should prove required Gloskin behavior, absence of excluded Morgen dependencies, and the architecture-efficiency contract in `../docs/architecture-efficiency-audit.md`.

## Architecture invariants

Verify:

- exactly one Gloskin composition root/kernel;
- no Gloskin `System` mega-class combining routing/assets/admin/integrations/persistence;
- no more than eight first-party bootable v1 services without a documented architecture change;
- each concern has one canonical owner;
- exactly one first-party frontend asset owner/registry;
- desktop/mobile navigation consume the same normalized tree;
- frontend request does not boot admin/migration/diagnostic/mail infrastructure;
- admin request does not boot public carousel/drawer/gallery runtime without a specific admin need;
- optional Woo/form dependencies are checked centrally in adapters rather than scattered through templates;
- page templates receive page-specific contexts rather than one global all-domain data object.

## Persistence and security invariants

Verify:

- no custom database table in v1;
- no direct `$wpdb` write in production code unless a later documented exception exists;
- at most one small global Gloskin settings option;
- entity/page data uses native WordPress storage and Woo data remains Woo-owned;
- normal settings/meta saves do not use a custom global lock/readback/rollback transaction engine;
- no manual `alloptions`/`notoptions` cache surgery by default;
- custom state-changing admin paths use capability + nonce + field-appropriate sanitization;
- frontend output uses contextual escaping;
- no public `wp_ajax_nopriv_*` Gloskin endpoint exists in v1 unless explicitly added with documented threat model;
- no Gloskin mail transport/form processor/payment/order mutation logic exists.

## Activation and ownership

Verify:

- plugin activates/deactivates without PHP fatal errors;
- activation work is not repeatedly re-verified on normal requests;
- Gloskin boot does not require `Morgen_Core_*` classes;
- public assets use Gloskin-owned handles/selectors;
- no UI V1-V5 switching exists;
- no CASE-PROD/PROD migration is registered or executed;
- no generic migration console/framework exists in v1.

## Route coverage

Use `../docs/page-matrix.csv` as the route contract. Verify Home, About, Treatments Hub + eight treatment records, Skincare Hub + seven Woo mappings, Clinics Hub + nine branches, Contact, Insights Hub, Shop Hub, Doctors Hub + thirteen doctor records, and Woo single-product/cart/checkout presentation compatibility.

Prefer native WordPress/CPT/Woo routes. Flag custom query vars, request claiming, proxy pages, forced query flags or synthetic virtual pages for architecture review.

## Content/data behavior

Verify treatment, clinic and doctor fields/relationships from `../docs/content-data-contracts.md`; skincare-to-Woo mapping; missing optional values; attachment-ID based media; and absence of fabricated default branch/doctor/medical/product data.

Relationships should have one documented canonical storage direction unless a later measured optimization explicitly adds denormalization.

## WooCommerce boundary

Verify WooCommerce remains the only product CRUD/admin; standard gallery/title/price/description/attributes/add-to-cart remain functional; BPOM/composition/usage presentation reads Woo-managed data when configured; cart/checkout/gateway behavior stays Woo-owned; and Gloskin neither forces catalog mode nor disables checkout.

## Contact boundary

Verify configured external form integration/fallback, branch WhatsApp generation, and absence of Morgen/Gloskin custom inquiry-mail transport.

## Accessibility and responsive behavior

Verify desktop/mobile navigation, drawer close/backdrop/Escape behavior, keyboard focus, disclosure ARIA state, focus management, reduced motion, meaningful media alt behavior, no hover-only critical interaction, and representative responsive layouts.

## Runtime and asset quality

Verify:

- no PHP warnings/fatals on representative pages;
- no red JavaScript console errors;
- missing image/map/form/relationship data fails gracefully;
- core shell does not require Woo/form provider to render;
- optional feature assets are conditionally loaded;
- Splide is absent on routes without a carousel/gallery when practical;
- no cleanup/dequeue/re-enqueue protection dance is needed for Gloskin-owned assets;
- representative current Chrome/Safari/Firefox rendering is usable.

## Static exclusion scan

Search production code/build output for accidental architecture/dependencies including:

- `Morgen_Core_`, `morgen_core_`, `morgen-ui6-`, public `morgen-*`/`mg6-*`;
- Technical Library/Documents/PDF/download-token infrastructure;
- Morgen Applications/Hammer/Quality Testing/product manager;
- `CASE-PROD` / historical `PROD-` migrations;
- migration registry/shield/reconciliation/recovery code;
- custom inquiry/mail stack;
- V1-V5/version-switch logic;
- Rank Math proxy-management/virtual proxy pages;
- EN/DE route architecture;
- public unauthenticated AJAX handlers;
- manual `alloptions`/`notoptions` invalidation;
- duplicate first-party asset enqueue owners.

Historical provenance in Markdown/audit comments is allowed; active production dependencies are not.

## Documentation consistency

When implementation changes route/data/architecture/service ownership or pruning decisions, verify matching canonical docs changed in the same coherent commit.

## Explicit non-tests

SEO scores, GEO, GSC, GBP, analytics, backlinks, media placement, social performance and marketing KPIs are not acceptance criteria for this repository.
