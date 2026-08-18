# Gloskin Site Core

WordPress presentation and page-builder plugin for Gloskin. WordPress and WooCommerce remain the authoritative platform/data layers; Gloskin Site Core owns the public presentation shell, Gloskin editorial content structures, and safe WooCommerce presentation integration.

## Current product authority

Gloskin was initially built from an earlier public hierarchy. After the client presentation, that public editorial IA and presentation were substantially revised. The **2026-08-18 prototype revision is now the current product authority for the public/editorial experience**.

When requirements conflict, use this order:

1. explicit current repository-owner instruction;
2. `docs/2026-08-18-prototype-refresh/` for public IA, primary navigation, editorial hierarchy, Home, Treatments, Promo, Skincare landing, About, and the global visual/interaction system;
3. canonical architecture/security/data documentation for WordPress/WooCommerce ownership, storage, useful routes, security, SEO/GEO engineering, and service ownership;
4. the existing WooCommerce implementation as the protected commerce UX/functionality baseline.

Prototype copy, prices, claims, awards, identities, promotion terms, and other demonstrative data are never authoritative site facts.

## Two production zones

### Prototype-controlled editorial zone

Strongly converge to the approved prototype:

- global header and primary navigation;
- Home;
- Treatments;
- `/promo/`;
- Skincare landing;
- About / Tentang Gloskin;
- global footer;
- shared editorial cards, sections, drawers and modals.

### Commerce protected zone

Keep the mature existing implementation for:

- `/shop/`;
- Woo product detail;
- Cart;
- Checkout;
- My Account;
- Woo commerce actions, Quick Add, wishlist/cart ownership, filtering/search/category/price behavior.

Commerce consumes the new brand system but is **not** rebuilt as the simplified prototype.

## Primary public IA

Logo → Home.

Primary navigation:

1. Perawatan
2. Promo
3. Skincare
4. Tentang Gloskin

Search, Cart, Account and consultation/contact utilities remain separate. Shop, Clinics, Doctors, Insights and Contact remain functional supporting routes, discoverable contextually and from footer/commerce journeys.

## One-shot 2026-08-18 IA migration

The post-client IA revision is applied by one bounded deterministic migration owned by Lifecycle discipline, not by a generic migration framework.

In wp-admin, **Prototype IA Migration** runs with one user action and a real progress loader. The browser automatically chains four server checkpoints:

1. native Page provisioning;
2. primary-menu normalization;
3. page/menu/Woo safety verification;
4. consumed/schema finalization.

The process is resumable and idempotent. It never deletes supporting pages or Woo/customer/order/product data, and it preserves unrelated editor-created primary-menu items. Schema `0.3.0` is written only after verification/finalization succeeds; ordinary `admin_init` does not continuously repair the menu.

## Architecture at a glance

The runtime remains a modular monolith with one Kernel and small owners:

- ContentService
- TemplateService
- AssetService
- NavigationService
- WooCommerceAdapter
- FormAdapter
- AdminService
- LifecycleService

No custom database, duplicate commerce layer, generic migration console/framework, second design system, or custom Woo backend is introduced.

## Canonical reading order

1. `CONTRIBUTING.md`
2. `docs/2026-08-18-prototype-refresh/README.md`
3. `docs/2026-08-18-prototype-refresh/AI-DEVELOPER-PROMPT.md`
4. `docs/developer-source-of-truth.md`
5. `docs/architecture-efficiency-audit.md`
6. `docs/runtime-service-map.csv`
7. `docs/content-data-contracts.md`
8. `docs/seo-geo-engineering-contract.md`
9. `docs/implementation-plan.md`
10. `docs/page-matrix.csv`
11. `tests/README.md`

`project-9901` and pinned Morgen material are provenance/reference only, not current public product authority.

## Workflow

This repository is main-only. Work directly on `main`, pull/record HEAD, make coherent changes, run available checks, push directly to `origin/main`, and verify remote `main`.
