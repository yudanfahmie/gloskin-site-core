# Gloskin Prototype Refresh — 2026-08-18

This package is the latest client-approved authority for Gloskin's **public/editorial IA and presentation**.

## Precedence

1. explicit current owner instruction;
2. `CURRENT-ENGINEERING-PACK.md` for the active implementation bundle and conflict resolution;
3. this package for public IA, primary navigation, editorial page hierarchy, Home, Treatments, Promo, Skincare landing, About and global visual/interaction direction;
4. canonical repository architecture/security/data docs for WordPress/WooCommerce ownership, storage, useful routes, security, SEO/GEO engineering and service boundaries;
5. current WooCommerce implementation as the protected functional commerce baseline.

This supersedes older presentation-only decisions. It does not supersede WordPress/WooCommerce data ownership or commerce correctness.

## Current next implementation task

Start from:

`CURRENT-ENGINEERING-PACK.md`

The primary task specification is:

`NEXT-TASK-PARITY-DATA-ADMIN-MIGRATION.md`

The doctor-photo rules are additionally governed by:

- `DOCTOR-PHOTO-MIGRATION-ADDENDUM.md`;
- `resources/doctor-photos/README-V2.md`;
- `resources/doctor-photos/doctor-photo-manifest-v2.json`.

These v2 doctor-photo references supersede the older doctor-photo README/manifest and any experimental binary packaging artifacts in the same folder.

The next-task specification intentionally evolves Promo from one Page/Media composition into bounded repeatable managed campaign records while keeping `/promo/` as the native public destination. It also adds doctor-photo import/apply as a deterministic checkpoint in the same bounded migration.

## Product zones

### Prototype-controlled editorial zone

Header, primary nav, Home, Treatments, Promo, Skincare landing, About, footer, shared editorial cards/sections/modals/drawers should achieve recognizable parity in hierarchy, rhythm, typography, palette, card/media geometry, CTA hierarchy, responsive behavior and interaction feel.

### Commerce protected zone

Shop, PDP, Cart, Checkout, My Account and Woo actions keep their mature behavior. The prototype is a design-system reference here, not a literal replacement wireframe.

## Primary IA

Logo → Home.

Primary menu:

- Perawatan
- Promo
- Skincare
- Tentang Gloskin

Search/Cart/Account/contact-consultation are utilities. Shop, Clinics, Doctors, Contact and Insights remain useful supporting routes.

No fake language switcher.

## Home target

Hero/Campaign → Why Gloskin → Featured Treatments/discovery → Promo → Skincare/product discovery → Testimonials only when factual → About/brand-story transition → Achievements only when factual → closing CTA/footer.

There must be one strong semantic primary hero and one H1. Reuse the existing Media Library video owner when configured. Do not create another video service. Do not force Doctors/Clinics/Insights onto Home.

## Treatments

Retain the existing data relationships/recommendation engine. Converge presentation toward prototype discovery, using Face/Hair/Body/Wellness only when configured canonical path data supports those labels. No duplicate treatment/product storage or fabricated claims.

## Promo

`/promo/` remains a native WordPress Page and primary IA destination. The next implementation task adds bounded native WordPress repeatable Promo campaign records for carousel/CRUD while keeping the Page as the public destination. No custom database and never invent terms/prices/dates.

## Skincare and Shop

`/skincare/` is editorial/discovery and prototype-controlled. `/shop/` is protected mature Woo commerce. This distinction is intentional.

## About

Use approved brand-story content, vision/mission/values, factual founder/team/network/achievement data when available, and a strong CTA. Omit unavailable factual blocks.

## Official brand foundation

Palette:

- `#CA050E`
- `#784F0C`
- `#F6D179`
- `#FBE2B2`
- `#FFEBBB`
- `#FFF2EB`
- `#000000`

Graphik roles: body/nav/buttons/forms/commerce/utility.

Felix Titling roles: major display/editorial typography.

Owner-supplied Graphik/Felix WOFF2 assets are present in the production plugin and self-hosted by `gloskin-ui1-fonts.css`. This records runtime implementation only. Public-repository redistribution rights must not be inferred from asset presence.

## Current production baseline protected by next task

As of v0.7.133:

- canonical public container is semi-full 1320px with existing wider desktop breakpoints;
- Doctors Hub and About consume all published doctors;
- doctor grid is 4-column desktop / 2 tablet / 1 mobile;
- Header V2 remains the sole canonical public header and its nav is geometrically centered;
- Graphik/Felix typography remains canonical.

These are regression-protected, not work to reopen.

## Bounded migration discipline

The previous IA migration established the required pattern: one-click wp-admin runner, deterministic checkpoints, resumable/idempotent execution, safety verification before finalize, monotonic schema, and permanent disappearance after `consumed`.

The next revision must preserve the same discipline while adding managed-content setup/seeding and owner doctor-photo import/apply. It is **not** a generic migration framework.

## Acceptance

- primary menu matches approved IA;
- `/promo/` remains the public Promo destination;
- desktop/mobile consume the same normalized menu tree;
- support routes/data are preserved;
- Woo Shop/PDP/Cart/Checkout/My Account functionality remains;
- migration is one-click, resumable and idempotent;
- owner doctor photos are applied only through deterministic exact manifest matching;
- no fabricated facts;
- keyboard/focus/reduced-motion/responsive behavior remains sound;
- repository docs contain one clear current authority model.
