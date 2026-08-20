# Phase 3 Content Mapping — FB-989354 & FB-989360

**Tickets:** FB-989354 (Skincare assets/data), FB-989360 (Treatment assets/data)
**Bundle:** `docs/feedback-cases-gloskin-20260820-154828/`
**Status:** Manifested and ready for runtime reconciliation

---

## 1. Domain Boundary (Non-Negotiable)

| Entity | Count | Owner |
|--------|-------|-------|
| `gloskin_treatment` CPT | 8 umbrella informational records | WordPress CPT |
| `gloskin_consultation_path` taxonomy | 4 stable slugs (acne-focus, brightening-focus, anti-aging-focus, skin-health-focus) | Taxonomy |
| WooCommerce `product` + `gloskin_product_family=treatment` | 48 service/treatment products | WooCommerce |
| WooCommerce `product` + `gloskin_product_family=skincare` | 25 resolved skincare products | WooCommerce |

---

## 2. Skincare Products (FB-989354)

**Resolved: 25 identities** — see `manifests/skincare-products.json`
**Holds: 5 identities** — see `manifests/unresolved.json`

Client assets: `FB-989354-skincare-page/FOTO PRODUCT PNG/`

### Holds (identity not resolved — SKIP, do not guess)
- AZ Xpert → `AZ EXPERT.png` + `az xpert.png`
- Glam Gold Serum → `glam gold serum.png`
- Skin Fresh Facial Wash → `Skin-Fresh-Facial-Wash-1.png` + `SKIN FRESH.png`
- DSC02911 → `DSC02911.png` (unknown product, photo filename only)
- Untitled design (47) → `Untitled design (47).png` (generic asset name, identity unknown)

### Glam Air Cushion note
Reuse existing Woo variation/shade model if found. Otherwise simple product with NATURAL CUSHION.png (primary) + BEIGE CUSHION.png (gallery). Do NOT invent shade variations.

---

## 3. Treatment Products (FB-989360)

**Resolved: 48 Woo product identities** — see `manifests/treatment-catalog.json`
**Umbrella records: 8 gloskin_treatment CPT** — see `manifests/treatment-catalog.json`

Client assets: `FB-989360-treatment-page/FOTO TREATMENT/`

### Multi-context products (ONE canonical Woo product, multiple concerns)
- **Rejuran HB** → appears in `JERAWAT & BEKAS JERAWAT/REJURAN/` AND `KUSAM, SKIN BARIER & QUALITY/REJURAN HB/` — ONE product with concerns from both contexts
- **Keloid** → appears in `DETAIL - KOREKSI ESTETIKA/KELOID/` AND `JERAWAT & BEKAS JERAWAT/KELOID/` — ONE product with concerns from both contexts
- **Radiofrequency** → appears in `BODYCONTOUR & WELLNESS/RADIOFREQUENCY/` AND `KONTUR & LAXITY WAJAH/RADIOFREQUENCY/` — ONE product with concerns from both contexts

### Umbrella treatment CPT records (8, mapped to feature_on_home)
| # | Slug | feature_on_home |
|---|------|----------------|
| 1 | jerawat-bekas-jerawat | true |
| 2 | flek-pigmentasi | false |
| 3 | kusam-skin-barrier | true |
| 4 | kontur-laxity-wajah | true |
| 5 | aging-kerutan | false |
| 6 | facial-therapy | false |
| 7 | dermatolift | false |
| 8 | hair-restoration-category | false |

---

## 4. Treatment Page Media & Path Updates (FB-989360)

See `manifests/treatment-page-media.json`

### Path terms updated (existing slugs preserved)
| Slug | Asset |
|------|-------|
| acne-focus | JERAWAT & BEKAS JERAWAT/SYLFIRM X/SYLFIRM.jpg |
| brightening-focus | FLEK & PIGMENTASI/PICO LASER/GLOSKIN 0203043.JPG |
| anti-aging-focus | AGING - KERUTAN/BOTOX/BTX.png |
| skin-health-focus | FACIAL THERAPY/SKIN BARIER FACIAL THERAPY/SKINBARIER.png |

### Treatments page hero
`KONTUR & LAXITY WAJAH/ULTRALIFT/ULTRALIFT.png` → bound to `gloskin_hero_media_id` on the /treatments/ page

---

## 5. Unresolved Policy

Unresolved items are in `manifests/unresolved.json`. They are SKIPPED, audited, and do NOT block Phase 3 completion. No AI guess replaces an unresolved identity.
