# Gloskin Client Presentation Readiness

This checklist records the task-by-task staging review for Gloskin Site Core. It is evidence of implementation and verification, not permission to invent unresolved client or medical content.

## Task-by-task status

| Priority | Status | Repository evidence / behavior |
| --- | --- | --- |
| 1. Maximize real content completeness | DONE to canonical-source limit | Canonical Gloskin docs were re-audited. Automatic population remains limited to 9 approved clinic identities/routes and 7 normalized skincare landing groups. No approved final 8-treatment taxonomy, 13 doctor identities, branch NAP/contact data, About copy, real Woo catalog/media, or form configuration exists in canonical sources. |
| 2. Client-safe content states | DONE | Public templates render approved data, collapse unresolved optional sections, and omit non-applicable components. Visitor templates are scanned for implementation/pending/configuration language. |
| 3. Presentation content polish | DONE | Public headings/copy use concise structural UX language only where factual copy is unavailable. Developer/status language and unsupported medical claims are excluded. |
| 4. Home client-demo quality | DONE | Home keeps the clinic network and skincare discovery visible, renders treatments/doctors/products/Insights only when records exist, and avoids fake media or empty database sections. |
| 5. Clinic experience | DONE to available data | All 9 clinic identities remain present. Address, phone, hours, WhatsApp, map, gallery, related doctors and related treatments render only when values/relationships exist. |
| 6. Doctor experience | DONE to available data | Doctor hub/detail infrastructure remains ready for 13 records. No doctor identities, SIP values, specialties, schedules, credentials or photos are fabricated. Empty doctor discovery is intentionally composed. |
| 7. Treatment experience | DONE to available data | Treatment hub/detail infrastructure remains ready for exactly 8 approved records. The earlier candidate label list is not promoted into final taxonomy and no medical claims are generated. |
| 8. Media quality | DONE | Approved factual images use WordPress attachment rendering. When factual media is unavailable, Gloskin-owned abstract presentation media supplies decorative layout without implying a real person, clinic interior, result, or product. |
| 9. WooCommerce presentation | DONE at integration level | WooCommerce remains the product/cart/checkout/account authority. Gloskin reads supported product data only when Woo is available. No fake production products or duplicate price/stock/order logic exist. |
| 10. Contact / booking | DONE to available data | Phone/WhatsApp URL generation is centralized in FormAdapter. Empty destinations do not render CTAs. Contact uses clinic discovery when the external form provider is unavailable. |
| 11. Admin content usability | DONE | Native metaboxes/settings expose treatment, clinic, doctor, relationship, media, About and skincare-mapping fields so remaining content can be completed without editing PHP. Registered meta sanitization enforces field formats. |
| 12. Client presentation QA | DONE in available browser | Browser smoke covers 13 representative views at 390x844, 820x1180, 1440x900, 1920x1080 and 2560x1440. It checks overflow, heading structure, Indonesian/client-safety leakage, exhibition sizing and console/page errors. |
| 13. Accessibility regression | DONE in available browser | Keyboard focus, drawer focus transfer/return, Escape close, backdrop close, submenu disclosure ARIA and reduced-motion behavior are automatically exercised. |
| 14. Performance pass | DONE for current measured scope | Gloskin frontend CSS/JS are conditionally enqueued only for Gloskin-owned shell routes and native Woo presentation routes. Unrelated WordPress requests do not receive Gloskin frontend assets. No cache framework or extra CSS/JS framework was introduced. |
| 15. Client demo safety | DONE | `tests/check-presentation.sh` rejects public implementation language, test fixture values in production runtime, dummy/javascript CTAs, and manual public `<img>` markup. |
| 16. Safe content seeding | DONE | Runtime smoke verifies 16 structural Pages, exactly 9 clinic identities, zero automatically seeded treatment/doctor records, idempotent reactivation, and editor-content preservation. |

## Automated verification

Run from repository root:

```bash
./tests/check-architecture.sh
./tests/check-presentation.sh
./tests/check-runtime.sh
```

`check-runtime.sh` includes frontend activation, admin upgrade, Woo integration simulation, and the Chromium presentation matrix when Chromium + Playwright are available.

## Current approved population state

- main Gloskin Pages: provisioned;
- clinic identities/routes: 9/9;
- skincare landing groups: 7/7;
- approved final treatment records: 0/8;
- approved doctor records: 0/13;
- real Woo products supplied by canonical repository data: 0;
- actual branch NAP/contact/hours/maps/media: pending client/site data;
- approved About/Vision/Mission/Values: pending client data;
- chosen external form provider/shortcode: pending site configuration.

## Browser scope note

Automated browser presentation checks run in Chromium at all five required sizes. The suite also attempts Firefox and WebKit; in the current environment those Playwright engine binaries are unavailable, so neither is claimed as tested. See `docs/global-launch-readiness.md` for the final launch review.
