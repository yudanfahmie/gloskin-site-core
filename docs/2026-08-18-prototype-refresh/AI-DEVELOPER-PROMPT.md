# AI Developer Prompt — Gloskin Prototype Revamp

Work **codebase-only** in `yudanfahmie/gloskin-site-core`. Do not use another repo, branch, PR, or external project material. Work directly on current `main`; first pull/read current HEAD and never reset newer work.

## Read first

1. `CONTRIBUTING.md`
2. `docs/2026-08-18-prototype-refresh/README.md`
3. `docs/2026-08-18-prototype-refresh/prototype.html`
4. `docs/2026-08-18-prototype-refresh/resources/BRAND-ASSETS.md`
5. existing canonical docs in their normal repository order, especially `developer-source-of-truth.md`, architecture/runtime/content contracts and SEO/GEO contract.

The refresh package is the newest owner-approved **presentation** direction. Keep existing architecture, route, security, WordPress and WooCommerce ownership. Where older docs conflict only on visual presentation (old fonts/colors/Home visual treatment), the refresh package wins and the matching canonical docs must be updated in the same implementation change.

## Goal

Ship the next Gloskin version so the production website is recognizably and consistently the same UI/UX system as `prototype.html`, while remaining the existing WordPress/WooCommerce site—not a static one-page rewrite.

## Brand target

Use only the supplied brand set as canonical tokens: `#CA050E`, `#784F0C`, `#F6D179`, `#FBE2B2`, `#FFEBBB`, `#FFF2EB`, `#000000`; derived alpha/tints are fine for UI states.

Use the owner-supplied font package described in `resources/BRAND-ASSETS.md`: **Graphik** for body/nav/buttons/forms/commerce/utility UI and **Felix Titling** for brand/display/major editorial headings. Optimize to WOFF2 if practical. Do not invent font-license claims. Retire old Marcellus/Mulish/DM Sans production usage after verifying no remaining consumers; keep fallbacks sensible and preload only critical faces.

## Execution order — low effort, high impact

1. **Audit current production owners** before editing: AssetService, global shell, `assets/css/gloskin-ui1-core*.css`, `gloskin-ui1-production.css`, `gloskin-ui1-fonts.css`, relevant JS, and page/part templates. Identify one owner per concern; do not add a parallel design system.
2. **Brand foundation:** centralize new palette + typography in the existing token/asset ownership. Replace legacy visible palette/font ownership rather than piling overrides on top.
3. **Global shell:** restyle header/nav/mobile drawer, buttons, fields, cards, radii, shadows, section spacing, footer, sheets/modals and focus/hover states to prototype parity. Reuse current accessible interaction logic when it is already good.
4. **Core parity pages:** implement prototype hierarchy/compositions on Home, Treatments, Skincare/Shop and About. Home should read in the prototype/wireframe sequence: campaign hero → why Gloskin → featured treatments → testimonials → achievements, with Promo integrated as a clear campaign section. Preserve the existing authoritative Home media owner where useful; change presentation, not storage, unless a real data need is proven.
5. **Treatment:** keep current treatment/Woo data contracts; render Face/Hair/Body/Wellness-style discovery language where supported by real configured data. Do not invent medical claims or arbitrarily remap canonical categories.
6. **Promo:** prefer a Home `#promo` section/anchor if no real Promo page already exists. Do not create a new CPT/service merely to satisfy the sketch.
7. **Skincare/commerce:** map prototype product-card language onto WooCommerce products/categories; use real title/image/price/stock/cart data and native links/actions. Do not build duplicate product/cart/checkout logic.
8. **About:** match prototype story/founder/vision-team/network/achievement compositions using only available factual WP data; graceful empty states/omission beat fake content.
9. **Extend system:** restyle Clinics, Doctors, Insights, Contact, product detail, cart, checkout and account so they belong to the same system. Do not delete these canonical route families just because the sketch omitted them.
10. **Prune:** remove superseded presentation selectors/assets/variants only after reference checks. You may take out conflicting decorative/demo UI and unsupported `ID` language control; do not take out authoritative content, routes, accessibility, SEO/GEO structure, or Woo ownership.
11. **Data changes only if needed:** first reuse existing data. If staging/demo migration is genuinely necessary, imitate the repo's existing deterministic migration bundles and one-shot identity rules; do not create a generic migration framework or mutate immutable historical archives.
12. **Verify:** run existing repository checks and targeted tests; test Home/Treatments/Skincare/About plus Woo product/cart/checkout/account, mobile nav/drawers, keyboard/focus, reduced motion, empty states and responsive layouts around 375/768/1024/1440px. Preserve server-rendered primary content, logical headings, crawlable links and schema-provider compatibility.

## Visual acceptance

Prioritize geometry and hierarchy over decorative exactness: prototype-like fixed/light glass shell, large editorial whitespace, warm surfaces, rounded media/cards, clear red CTA hierarchy, strong treatment bands/cards, campaign/promo composition, clean skincare grid, focused testimonials, editorial About blocks, and restrained motion. Avoid turning every surface red/gold; use the official palette with whitespace and black contrast.

Prototype content is **not factual truth**. Do not copy placeholder prices, awards, branch facts, medical claims, founder/doctor identities or promotion terms unless they already exist in authoritative WP/Woo data.

## Change discipline

No new repo, branch or PR. No Morgen wholesale copy. No new framework/dependency unless unavoidable. No duplicate asset/token owner. No custom DB table. No duplicate Woo commerce layer. Prefer one coherent commit; split only when a separate safe outcome is genuinely needed. Commit messages short/lowercase/action-oriented. Bump the plugin version once at the end if this repo's release convention requires it.

Before finishing: review full diff, remove dead superseded presentation code, update canonical docs for changed presentation requirements, run checks, confirm remote `main` points to the final commit, and report concise changed-files/tests/remaining factual-content gaps.
