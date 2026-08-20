# Client Feedback — Developer Priority Plan (Phase 1)

**Repository:** `yudanfahmie/gloskin-site-core`  
**Baseline:** `main` at `d411d0f3acb0ce4a7a09610546d4348712cc9981`  
**Purpose:** convert the raw client-feedback bundle into a developer-facing execution plan, prioritizing the clearest **low-effort / high-impact** work first.

> The raw evidence remains under `docs/feedback-cases-gloskin-20260820-154828/`. This document is intentionally outside that folder and should be treated as an implementation brief, not as a replacement for the source feedback/evidence.

## Phase 1 selection

Start with these **three** tickets together:

| Order | Ticket | Task | Why now | Risk | Expected impact |
|---|---|---|---|---|---|
| 1 | **FB-989346** | Navbar labels → `Treatment`, `Promo`, `Skincare`, `Tentang Kami` | Small bounded navigation-data change; requirement is explicit | Low | High: visible on every page |
| 2 | **FB-989369** | Remove all visible breadcrumbs | One obvious shell owner; very small code surface | Low | High: global visual cleanup |
| 3 | **FB-989364** | Fix invisible `Konsultasi` CTA text | Presentation-only defect with existing content/action already present | Low–medium | High: restores a primary conversion action |

These tasks are grouped because they are independent, easy to verify, and do **not** depend on unfinished translation strategy, external content research, treatment/product data reconstruction, or missing video assets.

---

## 1) FB-989346 — persistent navbar labels

### Client intent
The public primary navigation must read exactly:

- `Treatment`
- `Promo`
- `Skincare`
- `Tentang Kami`

### Current owner
`plugin/gloskin-site-core/includes/class-gloskin-site-core-navigation-service.php`

The current service still hard-codes `Perawatan` and `Tentang Gloskin` in the approved projection, fallback tree, and URL label normalization.

### Required implementation shape
Use a **bounded one-shot persistent resolver**.

- The resolver must write the approved label map into a persistent WordPress option.
- The option becomes the data source consumed by the existing `Gloskin_Site_Core_Navigation_Service`.
- Do **not** implement a temporary filter, request-time repair loop, transient, cookie, or client-side URL matcher.
- Keep the Navigation Service as the one public navigation owner for both desktop and mobile.
- The resolver must be idempotent/versioned: once the correct option value is present and verified, subsequent requests do nothing.
- Keep deterministic in-code fallback values only for failure/bootstrap safety, and make those fallback values match the approved labels.
- Do not disturb the existing server-owned active-route state (`active` / `aria-current`) or submenu behavior.

### Acceptance
- Desktop and mobile show exactly the four approved labels.
- Reload, cache clear, login/logout, and route changes do not revert the labels.
- `/treatments/` and child/detail routes continue to highlight Treatment correctly.
- No duplicate navigation resolver or runtime repair mechanism is introduced.

---

## 2) FB-989369 — remove visible breadcrumbs globally

### Client intent
No visible breadcrumb trail anywhere on the public site.

### Current owner
`plugin/gloskin-site-core/templates/shell.php` currently calls:

```php
gloskin_ui1_render_breadcrumbs( $gloskin_context );
```

The same shell also suppresses WooCommerce's classic breadcrumb on Gloskin-owned commerce requests.

### Required implementation shape
- Remove the visible Gloskin breadcrumb render call from the shell.
- **Keep WooCommerce breadcrumb suppression in place.** Removing both would allow Woo's native breadcrumb to return on commerce pages.
- Search for breadcrumb-specific CSS/tests/helpers after removing the render call.
- Remove orphan presentation rules/tests only when they are no longer consumed anywhere.
- Do not change Woo routing, templates, structured product data, or unrelated commerce headings.

### Acceptance
No visible breadcrumb is present on:

- Home
- Treatments + treatment details
- Promo
- Skincare
- About
- Shop
- Single product
- Cart
- Checkout

WooCommerce must not reintroduce its classic breadcrumb.

---

## 3) FB-989364 — restore `Konsultasi` CTA text visibility

### Client intent
The consultation closing CTA is already structurally present, but its visible text/action presentation must be readable.

### Current owners
- Shared renderer: `plugin/gloskin-site-core/templates/parts/composition-helpers.php`
- Final presentation layer: `plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css`
- Base button/component rules remain in the canonical core CSS stack.

### Required implementation shape
- Diagnose the actual computed cascade first: text color, background, button variant, inherited section color, hover/focus state.
- Fix the issue in the **existing canonical CTA/button presentation owner**.
- Prefer a narrowly scoped shared correction if the defect is truly component-wide; otherwise scope to the affected CTA instance.
- Do not add duplicate text nodes, inline styles, JS style mutations, or `!important`.
- Preserve the existing CTA labels, URLs, semantic anchors, keyboard focus, hover behavior, and responsive layout.
- Regression-check every caller of `gloskin_ui1_render_closing_cta()` before broadening a shared style.

### Acceptance
- CTA text is clearly readable at default, hover, and keyboard focus states.
- No regression on Treatments, Promo, About, or other closing-CTA callers.
- No layout shift or mobile overflow at approximately 390px, 768px, 1024px, and 1440px widths.

---

## Explicitly deferred from Phase 1

| Ticket | Reason for deferral |
|---|---|
| **FB-989348** ID/EN | Architecture is understood, but the translation-provider/storage strategy should be locked before implementation. |
| **FB-989350** Home structure | Design is available and clear, but this is a broader composition pass than the three bounded Phase-1 fixes. |
| **FB-989352** Promo structure | Design is available; keep for the next visual composition batch. |
| **FB-989354** Product catalog rebuild | Requires deduplication, identity research, description/price enrichment, media import, and a larger migration/verification flow. |
| **FB-989356** Product card | Design is available and review UI should be omitted; still touches a shared commerce renderer, so it should follow the basic global cleanup. |
| **FB-989358** About structure | Sketch is available; requires a dedicated composition pass and must not invent factual founder/history content. |
| **FB-989360** Treatment migration | Raw media is now present, but folder semantics mix concerns, treatment families, and procedures; requires a deliberate mapping/migration audit. |
| **FB-989362** Full-video Home hero | Historical architecture is reusable, but the new requirement is no-crop and the final raw video asset still matters. |

---

## Engineering guardrails for Phase 1

1. Extend existing owners; do not create parallel navigation, breadcrumb, or CTA controllers.
2. Prefer persistent WordPress data for client-approved state; avoid request-time repair loops.
3. No new framework, SPA behavior, global fetch/history monkeypatch, or third-party JS dependency.
4. No `!important`, inline-style workaround, hard-coded DOM mutation, or redundant active-route matcher.
5. Keep desktop/mobile/reduced-motion/accessibility behavior intact.
6. Update/add focused regression contracts for each changed behavior.
7. Run the repository's canonical runtime/test harness before completion and keep the working tree clean.

## Definition of done for Phase 1

Phase 1 is complete only when all three tickets pass together:

- the four navbar labels persist from the new canonical option-backed data path;
- no visible breadcrumbs remain, including Woo surfaces;
- the consultation CTA text is readable in all relevant states and callers;
- existing active-navigation behavior still works;
- no new competing state or presentation owner exists;
- focused contracts and the normal runtime suite pass.

## Next recommended batch

After this Phase 1 cleanup, the next low-to-medium effort visual batch should be:

1. **FB-989356** Product-card presentation (with review/rating omitted), then
2. **FB-989350** Home structure, and
3. **FB-989352** Promo structure.

Keep catalog reconstruction (**FB-989354**), Treatment migration (**FB-989360**), multilingual (**FB-989348**), and no-crop video hero (**FB-989362**) as separate implementation workstreams because they require stronger data/provider/asset verification.
