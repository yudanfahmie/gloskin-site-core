# Gloskin Prototype Refresh — 2026-08-18

Owner-approved execution package for the next Gloskin UI revamp.

## Outcome

Keep the current WordPress/WooCommerce architecture and data ownership, but make the next production presentation visually and behaviorally converge on `prototype.html` across the site. This is a presentation revamp, not a platform rewrite.

## Authority / conflict rule

Use these sources together, by concern:

1. **Architecture, routes, storage, security, SEO/GEO structure:** existing canonical repository docs remain authoritative.
2. **Visual geometry, hierarchy, component feel, spacing, section rhythm, responsive behavior, and interaction direction:** `prototype.html` is the target.
3. **Color and typography:** the brand contract in `resources/BRAND-ASSETS.md` overrides temporary colors/fonts from the first prototype draft or older production CSS.
4. **Factual content and commerce data:** current WordPress/WooCommerce data is authoritative. Prototype copy/data is demonstrative only.
5. **Raw wireframe provenance:** the source archive is identified by checksum in `resources/BRAND-ASSETS.md`; use it only for ambiguity resolution when available locally.

This package is a newer owner instruction. Where an older canonical document contains presentation-only details that conflict with this package (for example old font choices, old color tokens, or the previous Home hero visual treatment), this package wins for presentation. Architecture/data ownership does not change.

## Canonical brand foundation

### Palette

Use these seven owner-supplied colors as the canonical brand set:

- `#CA050E` — primary red / high-emphasis CTA and active state
- `#784F0C` — deep brown / secondary dark accent
- `#F6D179` — gold
- `#FBE2B2` — warm cream
- `#FFEBBB` — light gold/cream
- `#FFF2EB` — blush/off-white surface
- `#000000` — primary text / strongest contrast

Derived UI neutrals are allowed only as transparent mixes/tints for borders, disabled states, overlays, shadows, and accessibility. Do not introduce a new competing brand palette.

### Typography

Owner-supplied font package contents:

- **Graphik**: Light, Regular, Medium, Semibold, Bold + supplied italics.
- **Felix Titling**: `Felixti.TTF`.

Target role:

- Graphik = body, navigation, buttons, labels, product/commerce UI, forms, utility text.
- Felix Titling = brand/display typography and major editorial headings where legibility remains strong.
- Do not keep Marcellus/Mulish/DM Sans as visible production typography once the new system is verified, unless a narrow technical fallback remains necessary.

The supplied font package contains no license file. Treat the typefaces as **owner-supplied brand assets**; do not invent redistribution/license claims. Convert approved faces to optimized WOFF2 for production if tooling permits; preload only critical faces.

## Prototype interpretation

`prototype.html` is a one-page interactive reference, not production architecture. Preserve its recognizable visual language:

- fixed/light glass header;
- strong editorial hero;
- rounded cards and large calm spacing;
- restrained warm palette with red accent;
- clear CTA hierarchy;
- Treatment categories presented as large visual bands/cards;
- Promo as a campaign section/carousel language;
- Skincare as clean product cards;
- Testimonials as a focused slider/card composition;
- About/Tentang Kami as photo/story/founder/vision-team/network/achievement blocks;
- polished drawers/modals/mobile nav and strong hover/focus feedback.

The raw wireframes establish the primary sequence: Home → Video Campaign → Why Gloskin → Treatment Unggulan → Testimoni → Piagam; Treatment → Face/Hair/Body/Wellness; Promo; Skincare; Tentang Kami. The prototype resolves these into one coherent digital system.

## Production mapping

Do **not** collapse the mature site into a literal one-page website. Apply the prototype system to existing route families.

- Logo/brand → Home.
- Treatment → existing `/treatments/` and treatment detail/category surfaces.
- Promo → prefer a Home `#promo` section/anchor unless a real native Promo page already exists; do not create a new CPT/service only for this.
- Skincare → existing `/skincare/`, category landings, Shop/product surfaces.
- Tentang Kami → existing `/about/`.
- Cart → existing WooCommerce cart/mini-cart behavior/data.
- `ID` language control from the sketch/prototype is **not required** unless the site has a real supported multilingual feature. Do not fake a language system.
- Existing Clinics, Doctors, Insights, Contact, Shop, product, cart, checkout and account routes stay; restyle them with the same design language even though the original sketch did not draw every family.

## Data rule

Prototype prices, awards, branch facts, doctor/founder identity, medical claims, promotion terms and product copy are placeholders unless they already match authoritative site data. Never publish them as truth merely for visual parity.

Prefer current WordPress/WooCommerce data and entity relationships. If staging data genuinely must change to exercise a redesigned surface, mirror the repository's existing deterministic migration pattern instead of inventing a new migration framework. Reuse current one-shot/identity/archive/runtime-copy conventions where applicable, keep synthetic data clearly marked, and do not rewrite immutable historical migration archives.

## Allowed take-out

To reach one coherent UI, the implementer may remove/retire:

- obsolete presentation-only CSS/rules that conflict with the new token system;
- old production font files after proving there are no remaining consumers;
- redundant visual variants and decorative elements that create two competing design systems;
- prototype-only fake content/claims;
- language controls without real multilingual capability;
- demo/staging content only when safe, traceable, and not authoritative production data.

Do **not** remove required routes, WooCommerce ownership, factual WP/Woo content, accessibility behavior, SEO/GEO semantics, or security boundaries just because they are absent from the sketch.

## Low-effort / high-impact task groups

### TG1 — Brand tokens first

Replace the visible production font/palette foundation before page-by-page polishing. Keep one token owner and one asset owner. Avoid selector-by-selector color hacks.

### TG2 — Global shell

Bring header, desktop/mobile navigation, CTA/button language, sheets/drawers, footer, focus states, spacing/radius/shadow system into prototype parity. This gives immediate site-wide impact.

### TG3 — Core prototype pages

Revamp Home, Treatments, Promo section, Skincare/Shop, and About to match the prototype's hierarchy and component compositions. Reuse current data owners.

### TG4 — Extend the system

Apply the same typography, cards, hero treatment, media framing, section rhythm and CTA system to Clinics, Doctors, Insights, Contact, single product, cart, checkout and account surfaces without changing their platform responsibilities.

### TG5 — Prune + verify

Remove superseded presentation code/assets only after references are gone. Run repository checks, Woo flows, keyboard/mobile checks, semantic heading/link checks and visual review at ~375, 768, 1024 and 1440px.

## Definition of done

- The site unmistakably reads as the same design system as `prototype.html` on first view.
- Official palette and owner-supplied typography are visible, centralized and reusable.
- No second theme/design mode remains visually competing with the target.
- Existing content, routes and Woo flows still work.
- Home/Treatments/Skincare/About are the strongest parity surfaces; remaining page families feel native to the same system.
- No fabricated medical/commercial/factual data was introduced for appearance.
- Mobile nav, drawers/modals, keyboard focus and reduced-motion behavior remain usable.
- Server-rendered crawlable content, logical heading hierarchy, stable links and provider-safe SEO/schema structure remain intact.
- Production asset loading is bounded; do not preload every font weight.
- Canonical docs are updated in the implementation commit wherever older presentation requirements are superseded.
- Work directly on `main`; no new repo/branch/PR; prefer one coherent commit, split only if the implementation naturally requires independent safe outcomes.

## Start here

For an AI developer, use `AI-DEVELOPER-PROMPT.md` verbatim as the execution prompt.
