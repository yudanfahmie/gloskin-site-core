# Source Notes

This file records the engineering facts extracted from the Gloskin raw project documents. The raw documents stay in `yudanfahmie/project-9901`; they must not be copied into this repository.

## Source files

- `Handover_Tim_IT_Gloskin.docx` — blob `8f9546d13662046927bc8e547931f68841a78c83`
- `Gloskin_Project_Framework_Final (1).xlsx` — blob `a3b09371bff4b6ca3a5672d9213e1179172a1887`
- `Gloskin_Client_Project_Tracker.xlsx` — blob `e004d3efa937c296d6d5689d123b666f11e93464`
- `Data Digital Marketing Onboarding.xlsx` — blob `073cacc37672fbc1e4f6a58c426eb5bb18e971e2`

## Developer-facing requirements extracted from the raw material

The framework explicitly calls for:

- WordPress + WooCommerce;
- Homepage;
- About;
- Treatments Hub and 8 treatment-category pages;
- Skincare Hub and 7 skincare-category pages;
- Clinics Hub and 9 clinic-location pages;
- Contact;
- Insights Hub;
- Shop Hub;
- Doctors Hub and 13 doctor pages;
- up to 20 WooCommerce product pages.

The raw task rows are more reliable than stale headline page totals in the same documents.

## Required clinic branches

- Kebayoran Baru
- Tebet
- Bekasi
- Cibubur
- Serpong
- Surabaya
- Banjarmasin
- Balikpapan
- Denpasar

Clinic detail requirements include consistent NAP data, operating hours, Google Maps, branch imagery, doctors per branch, and a branch-specific WhatsApp contact.

## Doctor inputs

The raw delivery checklist requests thirteen doctors with professional photos and data including full name, title/degree, specialization, practice branch, and SIP number where available.

## Treatment inputs

The raw delivery checklist requests eight approved treatment categories with description, benefits, and contraindications. Content involving medical claims requires client medical review outside this repository's implementation scope.

## Product inputs

The raw delivery checklist requests up to twenty active skincare products with product name, SKU, price, BPOM number, composition, usage instructions, and product photos.

These fields should be represented using WooCommerce-managed product data/attributes/meta. They are not a reason to create a Gloskin-specific product backend.

## Provisional skincare groups

The onboarding file currently names:

- Facial Wash
- Day Cream/Sunscreen
- Toner
- Serum
- Acne Care
- Anti-Aging
- Brightening & Pigmentation Care

These names remain provisional/configurable until final client content is approved.

## Design directions in the framework

The implementation process is expected to support three initial directions:

1. Medical Professional — clean, navy/white, authority-oriented serif typography.
2. Modern Aesthetic — pastel, sans-serif, visual-led.
3. Premium Luxury — dark tones, gold accent, editorial typography.

These should be variants of one component system rather than separate codebases.

## Deliberately excluded from Gloskin Site Core

The raw project documents also contain broader project obligations such as SEO/GEO work, GSC/GA4/GBP, schema tasks, redirect/domain migration, marketing production, media placement, and reporting. Those items are not plugin implementation requirements unless the owner later adds them explicitly.
