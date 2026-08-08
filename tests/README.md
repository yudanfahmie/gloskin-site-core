# Verification Workspace

The implementation task should add automated and/or documented manual checks that prove both required Gloskin behavior and absence of excluded Morgen dependencies.

## Activation and ownership

Verify:

- plugin activates/deactivates without PHP fatal errors;
- Gloskin boot does not require `Morgen_Core_*` classes;
- Gloskin public assets use Gloskin-owned handles/selectors;
- no UI V1-V5 presentation switching exists;
- no CASE-PROD/PROD migration is registered or executed.

## Route coverage

Use `../docs/page-matrix.csv` as the route contract.

Verify at least:

- Home;
- About;
- Treatments Hub + eight configurable treatment detail records;
- Skincare Hub + seven Woo category landing mappings;
- Clinics Hub + all nine required branch slugs;
- Contact;
- Insights Hub;
- Shop Hub;
- Doctors Hub + thirteen configurable doctor detail records;
- WooCommerce single-product/cart/checkout presentation compatibility.

## Content/data behavior

Verify:

- treatment fields and relationships from `../docs/content-data-contracts.md`;
- clinic NAP/hours/map/WhatsApp/media/doctor relationships;
- doctor identity/specialization/branch/SIP-optional behavior;
- skincare landing-to-Woo category mapping;
- missing optional fields do not break templates;
- no fabricated default branch/doctor/medical/product data is required for rendering.

## WooCommerce boundary

Verify:

- WooCommerce remains the only product CRUD/admin;
- Gloskin does not create duplicate product records;
- standard Woo gallery/title/price/description/attributes/add-to-cart remain functional;
- product BPOM/composition/usage display reads Woo-managed attributes/meta when configured;
- cart/checkout logic remains Woo-owned;
- installed gateway hooks are not bypassed by Gloskin presentation;
- Gloskin does not force catalog mode or disable checkout.

## Contact boundary

Verify:

- configured form shortcode/block renders correctly;
- missing form integration has a deliberate fallback;
- branch WhatsApp links are generated from configured branch data;
- no Morgen inquiry/mail/auto-reply transport is required.

## Accessibility and responsive behavior

Verify:

- desktop and mobile navigation;
- drawer open/close/backdrop/Escape behavior;
- keyboard focus visibility;
- submenu/disclosure `aria-*` state;
- focus management for dialog/drawer behavior;
- reduced-motion behavior;
- meaningful media alt behavior using WordPress data;
- no hover-only critical interaction;
- representative mobile/tablet/desktop layouts.

## Runtime quality

Verify:

- no PHP warnings/fatals on representative pages under normal debug testing;
- no red JavaScript console errors on representative pages;
- missing image/map/form/relationship data fails gracefully;
- excluded feature assets are not globally enqueued;
- representative current Chrome/Safari/Firefox rendering is usable.

## Static exclusion scan

Search production code/build output for accidental dependencies including:

- `technical-library`;
- Morgen Documents/PDF/download token classes;
- Morgen Applications/Hammer/Quality Testing domains;
- Morgen custom product manager/category registry;
- `CASE-PROD` / historical `PROD-` migrations;
- `Morgen_Core_Inquiry_Mail` and mail settings/admin stack;
- V1-V5 presentation selectors;
- Rank Math proxy-management code;
- EN/DE route architecture;
- public `morgen-ui6-`, `morgen-*`, `mg6-*` identifiers.

Historical provenance in Markdown/comments/tests is allowed; active production runtime dependencies are not.

## Documentation consistency

When implementation changes routes/data contracts/architecture, verify the matching canonical docs were updated in the same coherent commit.

## Explicit non-tests

SEO scores, GEO, GSC, GBP, analytics, backlinks, media placement, social performance and marketing KPIs are not acceptance criteria for this repository.