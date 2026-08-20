# Phase 4 Product Content Research Guide

Purpose: give the final one-shot resolver enough source material to fill every canonical Woo product without another broad research pass.

## Canonical scope

Use the authoritative Phase-3 identities only:

- 25 Skincare products from `docs/client-feedback-phase-3/manifests/skincare-products.json`
- 48 Treatment Woo products from `docs/client-feedback-phase-3/manifests/treatment-catalog.json`

Required after resolve: **73/73 canonical products have a non-empty Woo short description and full description.**

## Preferred public Gloskin sources

Use these official Gloskin pages as the first content source and paraphrase them concisely; do not copy long passages verbatim:

### Skincare

- `https://gloskin.id/day-cream-sunscreen`
- `https://gloskin.id/facial-wash`
- `https://gloskin.id/serum`
- `https://gloskin.id/toner`
- `https://gloskin.id/produk-penunjang`
- `https://gloskin.id/brightening-and-pigmentation-care`

These pages currently provide usable ingredient/use information for many canonical products including Acne Day Protection Cream, Whitening Sunscreen, Glowing White Sunscreen, Day Protection Cream, Flawless High Defences 50, Brightening Face Wash, Acne Facial Cleanser, Hydro Fresh Foaming, Glowing Facial Wash, Clear Xpert Serum, Rejuve Xpert Serum, Hydra Xpert Serum, Skin Fresh Toner, Bio Calmskin, Essence Bio Moisturizer, Sense Cleansing Milk, Ultimate Whitening Cream, Transforming Night Cream, C Power Silk Gel, Acne Prone Gel, Cysteamine Advance Plus, Glam Air Cushion and Gloskin Glow Face Tonic.

### Treatment

- `https://gloskin.id/facial`
- `https://gloskin.id/peeling`
- `https://gloskin.id/quality-repair`
- `https://gloskin.id/face-contour`
- `https://gloskin.id/hair`
- `https://gloskin.id/dermatolift`
- `https://gloskin.id/`

These pages currently provide usable descriptions/benefits for many canonical treatments including Glowing Face Therapy, Derma Oxy Face Therapy, Lymphatic Face Therapy, Skin Barrier Facial Therapy, Oxy Jet Light, Hydra Glowing/Luxury facial therapy, Korean Comedo Glowing Peel, Acne Advance Peeling, Pico Laser, Laser 4G, Glowing Salmon DNA, Xelarederm, Profhilo, Rejuran HB, Croma Rich, PRP, Juvelook, Sylfirm X, Botox, Juvederm/Face Contour, Thread Lift, Hollywood Face Sculpting, Ultralift, Hair Transplant, PRP Hair, Buccal Contour, Eyebag Removal, Upper/Lower Eyelid, Lipoma Removal and SMAS Lift.

## Copy policy

For each canonical product write:

- Woo short description (`post_excerpt`): 1–2 concise sentences explaining what the product/treatment is and its main purpose.
- Woo full description (`post_content`): 2–4 short paragraphs or a short intro + compact benefits/use section.

Prefer exact official Gloskin facts when the exact product appears on an official source page.

When an exact official description cannot be found, **do not leave the product empty**. Use a conservative fallback based only on the canonical manifest title, family, group/category and concern mapping.

### Safe Skincare fallback

Describe the product type/category and intended skincare role only. Do not invent ingredients, SPF, BPOM, size, pregnancy suitability, frequency or quantitative claims unless verified by an official source.

Example shape:

`[Product] adalah bagian dari rangkaian [category] GLOSKIN untuk melengkapi rutinitas perawatan kulit. Gunakan sesuai kebutuhan kulit dan petunjuk penggunaan produk; konsultasikan dengan tim Gloskin bila membutuhkan rekomendasi rangkaian.`

### Safe Treatment fallback

Describe the treatment as a Gloskin option for the manifest concern/group without inventing device technology, injectable ingredient, dosage, session count, permanence or guaranteed result.

Example shape:

`[Treatment] merupakan pilihan perawatan Gloskin yang ditujukan untuk membantu membahas kebutuhan terkait [manifest concern/group]. Pemilihan tindakan, kesesuaian pasien, jumlah sesi, dan ekspektasi hasil ditentukan setelah evaluasi/konsultasi tenaga medis.`

Medical or invasive treatments should always retain consultation/suitability language.

## Resolver ownership

The one-shot Phase-4 resolver should persist real Woo content, not inject frontend fallback copy.

Recommended ownership metadata:

- `_gloskin_phase4_content_source`
- `_gloskin_phase4_content_version`

Rules:

- fill empty canonical descriptions;
- replace old known demo/placeholder content;
- resolver may update content it previously owns when its content version changes;
- preserve unrelated/manual non-demo content unless explicitly identified as stale resolver-owned content;
- never create duplicate Woo products for content enrichment.

## Verification

The final resolver verifier needs only these content invariants:

- canonical Skincare descriptions: 25/25 short + full
- canonical Treatment descriptions: 48/48 short + full
- total canonical descriptions: 73/73 short + full
- no canonical product single page is description-empty
- zero frontend-time description patch/fallback is required
