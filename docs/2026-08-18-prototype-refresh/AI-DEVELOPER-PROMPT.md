# AI Developer Execution Prompt — Gloskin 2026-08-18 Revision

Work only in `yudanfahmie/gloskin-site-core`, directly on `main`. Do not create a branch or PR.

## Authority

Use, in order:

1. explicit current owner instruction;
2. this `docs/2026-08-18-prototype-refresh/` package for public IA and editorial presentation;
3. canonical architecture/security/data docs for WordPress/WooCommerce ownership/storage/security/SEO/service boundaries;
4. current WooCommerce implementation as protected commerce behavior.

Do not preserve superseded public hierarchy merely because an older document describes it.

## Public IA target

Logo → Home.

Primary menu:
Perawatan → Promo → Skincare → Tentang Gloskin.

Search, Cart, Account and consultation/contact may remain utilities. Shop, Clinics, Doctors, Contact and Insights stay functional supporting destinations.

## Editorial target

Prototype-controlled:
header/nav, Home, Treatments, Promo, Skincare landing, About, footer, shared editorial components/interactions.

Home:
Hero/Campaign → Why → Featured Treatments → Promo → Skincare/products → factual Testimonials if available → About transition → factual Achievements if available → CTA/footer.
One primary hero, one H1. Reuse the existing Media Library video owner.

Treatments:
reuse current path/concern/product mappings; change public presentation/hierarchy, not storage/recommendation ownership.

Promo:
ensure `/promo/` as native WordPress Page; no CPT/custom DB; no invented promotion facts.

Skincare:
editorial discovery landing distinct from protected `/shop/`.

About:
story/overview/vision/mission/values plus factual founder/team/network/achievement sections when data exists.

## Commerce protected zone

Do not remove or rebuild useful Shop filtering/search/category/price behavior, pagination/AJAX, PDP, Quick Add, wishlist/cart ownership, native Woo add-to-cart/variation flows, Checkout or My Account. Apply design-system convergence without duplicating commerce state.

## One-shot IA migration

Use Lifecycle/schema discipline. Do not create a generic migration framework.

The bounded revision migration must:

- ensure Home, Perawatan, Promo, Skincare, Tentang Gloskin Pages;
- normalize/assign `gloskin-primary`;
- put Perawatan, Promo, Skincare, Tentang Gloskin first and in that order;
- remove only known obsolete Gloskin primary items;
- preserve unknown/custom editor menu content;
- never delete support Pages or Woo/customer/product/order data;
- preserve Woo Shop/Cart/Checkout/My Account page IDs;
- be idempotent;
- record consumed/schema state only after verification.

Admin UX requirement: one explicit start action, then autonomous checkpoint chaining with a real loader/progress indicator. Persist checkpoints so interruption can resume. Keep server writes serial/deterministic; do not use parallel menu mutations.

## Engineering constraints

- one Kernel;
- one AssetService;
- one normalized nav tree for desktop/mobile;
- no second repo/framework/design system/data store;
- no custom DB;
- no duplicate Woo layer;
- no generic migration/recovery framework;
- capability + nonce + sanitization for admin writes;
- semantic/crawlable output;
- focus/reduced-motion/responsive support;
- no fabricated Gloskin facts.

## Validation

Inspect full diff, run existing checks, add migration regression coverage for order, `/promo/`, idempotency, consumed state, unknown editor preservation, support Page preservation and Woo config preservation, then commit/push directly to `main` and verify remote.
