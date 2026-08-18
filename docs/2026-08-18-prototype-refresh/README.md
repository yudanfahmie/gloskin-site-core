# Gloskin Prototype Refresh — 2026-08-18

This package is the latest client-approved authority for Gloskin's **public/editorial IA and presentation**.

## Precedence

1. explicit current owner instruction;
2. this package for public IA, primary navigation, editorial page hierarchy, Home, Treatments, Promo, Skincare landing, About and global visual/interaction direction;
3. canonical repository architecture/security/data docs for WordPress/WooCommerce ownership, storage, useful routes, security, SEO/GEO engineering and service boundaries;
4. current WooCommerce implementation as the protected functional commerce baseline.

This supersedes older presentation-only decisions. It does not supersede WordPress/WooCommerce data ownership or commerce correctness.

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

`/promo/` is a native WordPress Page and primary IA destination. No Promo CPT/database. Home links to it. Missing content yields a clean state, never invented discounts, prices, terms or dates.

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

Owner-supplied Graphik/Felix WOFF2 assets are now present in the production plugin and self-hosted by `gloskin-ui1-fonts.css`. This records runtime implementation only. The source package did not include a license file, so public-repository redistribution rights must be confirmed separately rather than inferred from asset presence.

## Bounded IA migration

Revision `2026-08-18` has a one-click wp-admin runner with real progress UI. It automatically chains deterministic checkpoints:

1. Pages;
2. Primary Menu;
3. Safety Verify;
4. Finalize/Consumed.

It is resumable/idempotent, preserves unknown editor menu items and support Pages, snapshots Woo page configuration, and writes target schema only after verification. It is **not** a generic migration framework.

## Acceptance

- primary menu matches approved IA;
- `/promo/` exists natively;
- desktop/mobile consume the same normalized menu tree;
- support routes/data are preserved;
- Woo Shop/PDP/Cart/Checkout/My Account functionality remains;
- migration is one-click, resumable and idempotent;
- no fabricated facts;
- keyboard/focus/reduced-motion/responsive behavior remains sound;
- repository docs contain one clear current authority model.
