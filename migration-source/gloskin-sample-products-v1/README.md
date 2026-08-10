# Gloskin Sample Products v1

> Synthetic staging/demo catalog — not verified commercial product truth.

This directory is the permanent repository archive for the temporary one-shot WooCommerce sample catalog importer. It is provenance/audit material and is **never** read or deleted by production runtime code.

## Scope

The bundle contains a deterministic staging catalog with:

- 13 parent WooCommerce products;
- 8 simple products;
- 5 variable products;
- 10 child variations;
- 58 media definitions;
- coverage of all 7 configured Gloskin skincare category slugs.

Names, SKUs (`GLS-SMP-*`), prices, descriptions and usage text in this bundle are synthetic demo data. They are not verified commercial Gloskin product truth. `BPOM` and `composition` are intentionally empty. The copy must not be interpreted as a clinical, medical, certification, composition, efficacy, or official commercial claim.

## Media provenance

`media.json` stores deterministic HTTPS source URLs, source-page URLs where available, filename, alt text, role, order and a provenance/license note for every record. The staging references use fixed Unsplash-hosted image URLs; the importer copies them into the WordPress Media Library and never hotlinks them on the storefront. No random/query API is used during import.

Stock-photo provenance must be reviewed again before any non-staging reuse. The demo image selection is intentionally generic and must not be presented as verified Gloskin packaging or as a competitor product.

## Dual-copy rule

Permanent immutable archive:

`migration-source/gloskin-sample-products-v1/`

Disposable deployable runtime copy:

`plugin/gloskin-site-core/migration-runtime/gloskin-sample-products-v1/`

At commit time, `manifest.json`, `products.json`, and `media.json` in those two locations must be byte-identical. Production reads only the runtime copy. Runtime code must never read, modify, or delete this archive.

## One-shot semantics

An authorized WooCommerce admin performs one explicit action from **Gloskin Content → Sample Product Import**. The browser then advances deterministic authenticated AJAX checkpoints. The first request validates the complete bundle; each subsequent product checkpoint processes at most one parent plus its 3–6 media records and variations.

Persistent state is authoritative. A verified successful import is saved as `consumed` **before** runtime cleanup is attempted. A redeployment may restore the disposable runtime files because `.cpanel.yml` copies the plugin directory, but `consumed` state still prevents any rerun.

Products and variations are imported as **draft**. Publishing synthetic records is a separate editorial action and is not part of this migration.

## Retry and identity rules

Products and variations use `_gloskin_sample_source_id`; media uses `_gloskin_sample_media_source_id`. The importer reconciles exactly one object with the expected deterministic identity, rejects duplicate identities, separately rejects unrelated SKU collisions, and reuses already-imported attachments on retry. Partial valid Woo objects are not rolled back when a later checkpoint fails.

## Runtime cleanup

After final verification succeeds, only the fixed declared runtime files are deleted from:

`plugin/gloskin-site-core/migration-runtime/gloskin-sample-products-v1/`

Imported Woo products, variations, categories and Media Library attachments are never deleted by runtime-bundle cleanup. If filesystem cleanup fails, logical state remains `consumed`, the importer remains hidden/non-rerunnable, and the admin overview may show a warning.

## Repository housekeeping after real-site import

After the actual target-site import has been confirmed, make a later repository housekeeping commit that removes:

`plugin/gloskin-site-core/migration-runtime/gloskin-sample-products-v1/`

while permanently preserving:

`migration-source/gloskin-sample-products-v1/`

Do not change `.cpanel.yml` merely to work around the deploy-copy behavior.
