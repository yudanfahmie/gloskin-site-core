# Developer Source of Truth

## 1. Current authority model

Gloskin has two product eras. The repository was initially implemented from an earlier hierarchy. After client presentation, the public/editorial IA and presentation were substantially revised.

Use this precedence:

1. explicit current owner instruction;
2. `docs/2026-08-18-prototype-refresh/` — latest client-approved authority for public IA, primary navigation, editorial page hierarchy, Home, Treatments, Promo, Skincare landing, About/Tentang Gloskin and the visual/interaction system;
3. canonical architecture/security/data docs — authority for WordPress/WooCommerce ownership, storage, useful routes, security, SEO/GEO engineering and service ownership;
4. existing commerce implementation — protected functional baseline.

Older presentation decisions are historical when they conflict with the 2026-08-18 package.

## 2. Product definition and ownership

`gloskin-site-core` is a WordPress plugin acting as the Gloskin public presentation/page-family layer while keeping WordPress/WooCommerce authoritative.

Gloskin owns the shell, public presentation, Gloskin editorial content types, page templates, navigation presentation, shared UI and Woo presentation integration.

WordPress owns native Pages, Posts, Media Library, users/capabilities/options/meta.

WooCommerce owns products/variations/categories/attributes/images/SKU/price/stock/cart/checkout/orders/accounts/payment/gateway behavior. No parallel product/cart/checkout/payment layer is allowed.

External form integration owns form submission/mail/captcha/storage; Gloskin owns only placement/presentation.

## 3. Two production zones

### Prototype-controlled editorial zone

Must strongly converge to the approved prototype:

- global header and primary navigation;
- Home;
- Treatments;
- Promo;
- `/skincare/` discovery landing;
- About / Tentang Gloskin;
- footer;
- shared editorial cards/sections/modals/drawers.

### Commerce protected zone

`/shop/`, PDP, Cart, Checkout, My Account and commerce actions retain mature existing behavior. The prototype supplies brand/design-system direction, not a literal replacement commerce wireframe.

## 4. Public IA

Logo links Home.

Primary nav:

1. Perawatan
2. Promo
3. Skincare
4. Tentang Gloskin

Utilities may expose Search, Cart, Account, and contact/consultation.

Supporting routes remain alive: Shop, Clinics, Doctors, Contact, Insights, treatment details, clinic details, doctor details, Woo product/cart/checkout/account URLs.

## 5. Primary editorial experiences

### Home

Target editorial flow:

Hero/Campaign → Why Gloskin → Featured Treatments/discovery → Promo → Skincare/product discovery → factual Testimonials when available → About/brand-story transition → factual Achievements when available → closing CTA/footer.

One strong semantic primary hero and one H1. Reuse the existing Media Library video owner when configured; do not create a second video service. Omit testimonials/achievements when factual data is absent. Doctors/Clinics/Insights are no longer mandatory large Home sections.

### Treatments

Keep the existing consultation/path/concern/product relationships and scoring where useful. Present discovery in the newer prototype language, especially Face/Hair/Body/Wellness when configured canonical path data supports those labels. Do not duplicate treatment/product storage or recommendation engines.

### Promo

`/promo/` is a native WordPress Page and primary IA destination. No Promo CPT/custom database. Home promo discovery links to this Page. Empty content must not invent prices, dates, discounts or terms.

### Skincare vs Shop

`/skincare/` is prototype-controlled editorial/product discovery. `/shop/` remains the protected mature Woo catalog. Do not collapse them.

### About

Target: editorial story, approved overview, vision/mission/values, factual founder/team sections when data exists, clinic/network representation, factual achievements when available, closing CTA. Do not fabricate people/awards.

## 6. One-shot IA migration

Revision `2026-08-18` uses one bounded deterministic migration within Lifecycle/schema discipline.

It ensures native Pages for Home, Perawatan, Promo, Skincare and Tentang Gloskin; normalizes the assigned primary menu to Perawatan → Promo → Skincare → Tentang Gloskin; removes only known obsolete Gloskin primary-menu entries; preserves unrelated editor menu items; preserves support routes and Woo page configuration; verifies the result; then records consumed/schema state.

The wp-admin runner is one-click and automatically chains bounded server checkpoints with real progress/loading feedback. It is resumable and idempotent. Writes stay serial to avoid menu races. Ordinary `admin_init` does not continuously reassert the new menu after migration is consumed.

## 7. Data contracts

Detailed entity fields/relationships live in `docs/content-data-contracts.md`.

Core rules:

- no invented medical/commercial/factual data;
- use native WordPress/Woo storage first;
- no custom DB required;
- keep editor content intact;
- support missing optional data gracefully.

## 8. Technical invariants

- one Kernel;
- one AssetService;
- one NavigationService normalized tree consumed by desktop/mobile;
- one WooCommerce authority;
- server-rendered crawlable output;
- logical headings and stable links;
- provider-safe SEO/schema structure;
- keyboard/focus/reduced-motion support;
- no generic migration framework;
- no second design system or framework.

## 9. Historical provenance

Pinned Morgen/UI-V6 material and `project-9901` may explain early implementation patterns, but do not outrank the latest client-approved editorial IA. They are provenance, not current public product authority.
