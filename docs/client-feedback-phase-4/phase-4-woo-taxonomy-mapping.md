# Phase 4 — Exact Woo `product_cat` Mapping

This file is the authoritative Phase-4 native WooCommerce category mapping for the canonical Phase-3 products.

It does **not** replace Phase-3 private taxonomies. Preserve `gloskin_product_family`, `gloskin_concern`, canonical slugs, prices, media, provenance, stock policy, and Phase-3 state exactly as they are.

## Rules

- Apply only to the canonical **25 Skincare + 48 Treatment** Woo products.
- Reuse existing `product_cat` terms by slug; create only a required missing term.
- Preserve legitimate additional native Woo categories already attached to a canonical product.
- Remove `uncategorized` from a canonical product only after at least one canonical category below is successfully attached.
- Never globally delete the Woo `uncategorized` term.
- Never mutate unrelated Woo products.
- Second finalizer run must perform zero taxonomy mutations.

## Required Woo category terms

| Source key | Woo slug | Woo label |
|---|---|---|
| `facial-wash` | `facial-wash` | Facial Wash |
| `day-cream-sunscreen` | `day-cream-sunscreen` | Day Cream / Sunscreen |
| `toner` | `toner` | Toner |
| `serum` | `serum` | Serum |
| `support` | `produk-penunjang` | Produk Penunjang |
| Treatment family | `perawatan` | Perawatan |

The repository also defines `acne-care`, `anti-aging`, and `brightening-pigmentation-care`, but none of the authoritative 25 Phase-3 Skincare records currently maps to those values. **Do not assign them merely because the terms exist.**

## Exact Skincare product mapping — 25/25

| Canonical product slug | Manifest category | Native Woo `product_cat` |
|---|---|---|
| `acne-day-protection-cream` | `day-cream-sunscreen` | `day-cream-sunscreen` |
| `glam-air-cushion` | `support` | `produk-penunjang` |
| `bio-calmskin` | `support` | `produk-penunjang` |
| `brightening-face-wash` | `facial-wash` | `facial-wash` |
| `c-power-silk-gel` | `support` | `produk-penunjang` |
| `clear-xpert-serum` | `serum` | `serum` |
| `essence-bio-moisturizer` | `support` | `produk-penunjang` |
| `gloskin-glow-face-tonic` | `support` | `produk-penunjang` |
| `glow-face-tonic-pads` | `support` | `produk-penunjang` |
| `glowing-facial-wash` | `facial-wash` | `facial-wash` |
| `glowing-white-sunscreen` | `day-cream-sunscreen` | `day-cream-sunscreen` |
| `hydra-xpert-serum` | `serum` | `serum` |
| `hydro-fresh-foaming` | `facial-wash` | `facial-wash` |
| `brightening-loose-powder` | `support` | `produk-penunjang` |
| `skin-fresh-toner` | `toner` | `toner` |
| `sense-cleansing-milk` | `support` | `produk-penunjang` |
| `transforming-night-cream` | `support` | `produk-penunjang` |
| `acne-facial-cleanser` | `facial-wash` | `facial-wash` |
| `acne-prone-gel` | `support` | `produk-penunjang` |
| `cysteamine-advance-plus` | `support` | `produk-penunjang` |
| `day-protection-cream` | `day-cream-sunscreen` | `day-cream-sunscreen` |
| `flawless-high-defences-50` | `day-cream-sunscreen` | `day-cream-sunscreen` |
| `rejuve-xpert-serum` | `serum` | `serum` |
| `ultimate-whitening-cream` | `support` | `produk-penunjang` |
| `whitening-sunscreen` | `day-cream-sunscreen` | `day-cream-sunscreen` |

Expected Skincare distribution after alignment:

- `produk-penunjang`: **11**
- `day-cream-sunscreen`: **5**
- `facial-wash`: **4**
- `serum`: **4**
- `toner`: **1**
- total: **25**

## Exact Treatment mapping — 48/48

Every canonical Treatment Woo product below must include native `product_cat=perawatan`:

`botox`, `hollywood-body-sculpting`, `lymphatic-body`, `we-go-slim`, `brow-lift`, `buccal-contour`, `eyebag-removal`, `smas-lift`, `upper-lower-eyelid`, `lipoma-removal`, `derma-oxy-facial-therapy`, `glowing-face-therapy`, `hydra-glowing-luxury-facial-therapy`, `lhala-peel`, `luxury-face-therapy`, `lymphatic-face-therapy`, `oxy-jet-light`, `skin-barrier-facial-therapy`, `triple-glowing`, `laser-4g`, `mesoglow`, `pico-laser`, `xelarederm`, `exxohair`, `prp-hair`, `hair-transplant`, `acne-advance-peeling`, `acne-spot-injection`, `cautery`, `injeksi-keloid`, `korean-comedo-glowing-peel`, `premium-prp`, `rejuran-hb`, `sylfirm-x`, `vip-light`, `hollywood-face-sculpting`, `juvederm`, `thread-lift`, `ultralift`, `5gf-glo-booster`, `croma-rich`, `ellanse`, `exxoskin`, `juvelook`, `nucleofil`, `profhilo`, `glowing-salmon-dna`, `skinvive`.

Do **not** create native Woo categories from Treatment `group` or `gloskin_concern` during Phase 4. Those classifications already have canonical owners.

## Storefront / admin meaning

Keep both concepts visible and distinct:

- **Gloskin Product Family** = canonical business family (`skincare` / `treatment`), owned by Phase 3.
- **Woo Categories** = merchandising/navigation taxonomy used by Woo and storefront category filtering.

Do not hide Woo Categories from wp-admin. The native taxonomy remains useful for Skincare category pages/sidebar filtering and the Shop catalog.

## Final acceptance

- Skincare native mapping: **25/25**
- Treatment with `perawatan`: **48/48**
- canonical products left only `Uncategorized`: **0**
- unrelated Woo products mutated: **0**
- Phase-3 private taxonomy mutations: **0**
- second taxonomy-finalizer run mutations: **0**
