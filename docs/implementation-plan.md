# Gloskin Implementation Plan

## Current objective

Translate the approved 2026-08-18 prototype revision into the mature WordPress/WooCommerce product while protecting the existing commerce engine and data ownership.

## Phase 1 — Authority + one-shot IA migration

1. keep the current authority model explicit in canonical docs;
2. provision native Pages: Home, Perawatan, Promo, Skincare, Tentang Gloskin;
3. normalize primary nav to Perawatan → Promo → Skincare → Tentang Gloskin;
4. preserve unknown editor menu content and all supporting Pages;
5. snapshot and verify Woo Shop/Cart/Checkout/My Account page configuration;
6. finalize schema/consumed state only after verification;
7. expose one-click autonomous admin runner with real spinner/progress and resumable checkpoints;
8. keep the migration bounded to revision `2026-08-18`, not a generic migration framework.

### Migration checkpoints

- **Pages** — ensure required native Pages and a valid Home/front page where not already configured.
- **Primary Menu** — create/assign or normalize the `gloskin-primary` WordPress menu.
- **Safety Verify** — assert canonical first four destinations, obsolete primary entries removed, Page records intact, and Woo page IDs unchanged.
- **Finalize** — persist consumed state and target lifecycle schema.

The browser automatically chains checkpoints after one explicit start. Server mutations remain serialized and lock-protected.

## Phase 2 — Prototype-controlled editorial convergence

Priority:

1. global shell/header/primary nav/footer;
2. Home hierarchy;
3. Treatments presentation;
4. Promo Page/Home promo discovery;
5. Skincare landing;
6. About/Tentang Gloskin;
7. shared editorial components/interactions;
8. supporting routes.

Home target:
Hero/Campaign → Why → Featured Treatments → Promo → Skincare/products → factual Testimonials (conditional) → About transition → factual Achievements (conditional) → CTA/footer.

Do not force Clinics/Doctors/Insights as primary Home sections.

## Phase 3 — Commerce visual convergence

Keep Shop/PDP/Cart/Checkout/My Account functional architecture and mature UX. Apply official typography/palette/button/field/card/spacing/radius/header/footer consistency only unless a separate functional requirement is approved.

Never remove useful search, filters, price/category discovery, pagination/AJAX, Quick Add, wishlist/cart state ownership, variations, checkout or account behavior because the prototype omitted them.

## Phase 4 — Supporting routes

Keep Clinics, Doctors, Contact, Insights and their detail routes functional and visually native to the new design system. They are supporting destinations, not primary IA.

## Phase 5 — Cleanup and verification

- remove superseded presentation rules only after proving no consumers;
- keep one visual/token owner per concern;
- do not falsely self-host Graphik/Felix until authorized binaries exist;
- verify one H1, crawlable links, keyboard/focus/reduced motion;
- verify mobile/desktop use one nav tree;
- verify migration idempotency and interruption resume;
- verify no Page deletion or Woo config mutation;
- run full existing repository checks and relevant browser checks where available.

## Definition of done

The public editorial experience clearly reflects the approved prototype, while mature WooCommerce capabilities remain intact. The repository alone explains this hybrid product model and contains deterministic safeguards for the one-time IA transition.
