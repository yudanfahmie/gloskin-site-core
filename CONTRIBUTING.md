# Contribution Rules

These rules are mandatory for AI agents and human developers.

## Branch policy

- Work directly on `main`.
- Do not create feature/work/temp branches or pull requests unless the repository owner explicitly changes this policy.
- Pull latest `origin/main` and record HEAD before editing.

## Requirements authority

When requirements conflict, use this order:

1. explicit current repository-owner instruction;
2. `docs/2026-08-18-prototype-refresh/` for **public/editorial product IA and presentation**;
3. canonical architecture/security/data docs for storage, WordPress/WooCommerce ownership, useful routes, security, SEO/GEO engineering and service ownership;
4. existing WooCommerce implementation as the protected commerce behavior baseline;
5. pinned Morgen/raw project material only as historical provenance.

The 2026-08-18 client revision supersedes older presentation-only decisions, including the old primary menu, old mandatory Home Doctors/Clinics/Insights hierarchy, old strict video-only Home requirement, and old multiple-design-direction expectations. Do not keep two competing current truths.

## Product zones

### Prototype-controlled editorial zone

Converge strongly to the prototype: header, primary nav, Home, Treatments, Promo, Skincare landing, About, footer, shared editorial components and interactions.

### Commerce protected zone

Do not simplify away mature Shop/PDP/Cart/Checkout/My Account behavior. Preserve filtering, search, category/price discovery, pagination/AJAX, Quick Add, wishlist/cart ownership, Woo hooks, native variation/add-to-cart behavior, checkout and account flows. Converge only the design system/presentation unless an explicit owner instruction changes commerce behavior.

## Primary IA

Logo → Home.

Primary navigation is:

1. Perawatan
2. Promo
3. Skincare
4. Tentang Gloskin

Search/Cart/Account/contact-consultation are utilities. Shop, Clinics, Doctors, Insights and Contact remain supporting destinations and must not be deleted merely because they leave primary navigation.

## Architecture efficiency contract

- exactly one Kernel composition root;
- at most eight first-party bootable `*-service.php` / `*-adapter.php` owners unless explicitly approved;
- one owner per concern;
- one first-party asset registry/owner;
- native WordPress routing/storage first;
- WooCommerce remains the sole commerce authority;
- no custom DB without demonstrated need and architecture update;
- no second bootstrap/workflow composition layer;
- no duplicate commerce/data store;
- no generic migration framework;
- no compatibility/recovery framework without a real released compatibility requirement.

A bounded one-shot migration for a known product revision is allowed inside lifecycle discipline. It must have deterministic identity/state, capability+nonce boundaries, idempotency, safety verification, and a consumed/version state.

## 2026-08-18 IA migration discipline

The post-client IA migration is a specific bounded migration, not a reusable framework. It may:

- ensure Home, Perawatan, Promo, Skincare and Tentang Gloskin native Pages exist;
- normalize the assigned `gloskin-primary` menu to Perawatan → Promo → Skincare → Tentang Gloskin;
- remove only known obsolete Gloskin primary-menu items;
- preserve unrelated/custom editor menu items;
- preserve support pages and all Woo page configuration/data;
- store a consumed/schema state after verification.

The admin UX should require one user action only. A real loader/progress UI may automatically chain bounded server checkpoints; server writes must remain serial/deterministic rather than parallel race-prone mutations. Interrupted runs must resume from persisted checkpoints.

Removing a menu item never authorizes deleting its destination Page.

## Security and persistence

For custom state-changing admin paths:

- capability check;
- nonce;
- field-appropriate sanitization/validation;
- native WordPress persistence;
- output escaping at the final context.

No public `wp_ajax_nopriv_*` migration endpoint. Avoid direct `$wpdb` writes. Multi-object migration locks are acceptable only where concurrent requests could mutate the same Page/menu state.

## SEO/GEO engineering baseline

Route/template/component changes must preserve server-rendered crawlable primary content, semantic landmarks/headings, one clear H1/topic, stable WordPress/Woo routes, crawlable anchors, breadcrumbs/provider compatibility, meaningful Media alt-data support, performance-minded assets, and graceful non-fabricated empty states.

Operational SEO/marketing remains out of scope.

## Data/content safety

Never invent medical claims, doctor/founder identities, clinic facts, awards, promotion terms, product pricing/BPOM facts or business data. WordPress/WooCommerce data is factual authority. Missing factual data should be omitted or rendered as a neutral empty state.

## Validation before push

1. inspect the complete diff;
2. run relevant/full repository checks when available;
3. confirm no duplicate owner/framework/state store was introduced;
4. verify migration idempotency, consumed state, editor-item preservation and Woo page preservation when migration code changes;
5. verify responsive/focus/reduced-motion semantics for public/admin UI changes;
6. commit coherent changes;
7. push directly to `origin/main`;
8. verify remote `main` and final diff.

Do not claim checks that could not run in the available environment.
