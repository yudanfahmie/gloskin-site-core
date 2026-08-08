# Provenance Notes

## Status

This file exists for auditability only.

Normal Gloskin development must use `docs/developer-source-of-truth.md` and the other canonical documents in this repository. A developer should not need to open `yudanfahmie/project-9901` to understand developer scope.

`project-9901` is raw/reference-only and must not be modified or copied into this repository.

## Raw source fingerprints

The normalized Gloskin developer requirements were derived from these immutable source files:

- `gloskin/Handover_Tim_IT_Gloskin.docx`
  - blob `8f9546d13662046927bc8e547931f68841a78c83`
- `gloskin/Gloskin_Project_Framework_Final (1).xlsx`
  - blob `a3b09371bff4b6ca3a5672d9213e1179172a1887`
- `gloskin/Gloskin_Client_Project_Tracker.xlsx`
  - blob `e004d3efa937c296d6d5689d123b666f11e93464`
- `gloskin/Data Digital Marketing Onboarding.xlsx`
  - blob `073cacc37672fbc1e4f6a58c426eb5bb18e971e2`

The repository state was intentionally cleaned after extraction; temporary extraction artifacts are not part of the developer workflow.

## Normalization decisions already made

These ambiguities have already been resolved for engineering and should not be rediscovered from raw files:

### Page counts

Raw headline totals such as `21 core pages` and `33 pages` conflict with explicit task rows. Engineering follows the explicit route/page-family inventory in `docs/page-matrix.csv`.

### Treatments

The project requires exactly eight treatment category pages, but onboarding did not finalize an eight-category taxonomy. A larger draft service list exists. Engineering supports eight configurable approved records and does not invent a grouping.

### Skincare

Seven onboarding labels are preserved as provisional/configurable category landing mappings to WooCommerce, not as a second product catalog.

### WooCommerce

Raw material mentions both catalog mode and checkout/payment testing. Gloskin Site Core therefore does not enforce catalog-only behavior and does not disable checkout. It preserves normal WooCommerce compatibility; deployment commerce mode is site configuration.

### Product template

The framework mentions a uniform twelve-field product template, but normalized source inputs do not reliably define all twelve fields. Known fields are represented through WooCommerce; no missing fields are invented and no custom product backend is created.

### SEO/schema/marketing

The raw project includes SEO/GEO/schema/GSC/GA4/GBP/backlink/media/social/reporting obligations. The repository owner explicitly scoped this codebase to developer-only site/plugin requirements. Those broader activities are excluded unless later added explicitly.

### Domain migration and redirects

Raw project delivery also mentions three-domain consolidation, redirects, DNS, SSL and go-live activities. These are infrastructure/project-delivery work, not Gloskin Site Core plugin functionality.

### Medical approval

The raw source requires review/sign-off for medical claims. The plugin provides neutral content fields and semantic presentation but does not implement a medical approval workflow.

### Multilingual content

Raw material mentions Indonesian/English company content. Morgen has an EN/DE route system. The Gloskin developer scope does not currently require a multilingual routing engine; normal WordPress i18n readiness is retained.

## Morgen provenance

Morgen implementation reference:

- repository `yudanfahmie/morgen-core`
- pinned commit `374432cee6380e0aa0f81390e26b990147e5e58d`

Reverse-engineering conclusions are captured in `docs/morgen-v6-reverse-engineering.md`. A future developer may inspect this pinned Morgen source when implementing an approved reuse decision, but should not repeat the Gloskin raw-requirements discovery process.

## Change rule

If future owner instructions change a normalized requirement, update the canonical Gloskin documents directly in the same coherent commit as the implementation change. Keep this provenance file only as a historical fingerprint; do not turn raw project material back into a live dependency.