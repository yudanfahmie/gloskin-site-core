# Plugin Workspace

Production implementation belongs under `plugin/gloskin-site-core/` unless a later build/release decision explicitly moves the plugin directory to repository root.

This directory is intentionally implementation-light until the cloning/adaptation task begins.

Before writing production code:

1. read `../CONTRIBUTING.md`;
2. read `../docs/developer-source-of-truth.md`;
3. read `../docs/content-data-contracts.md`;
4. read `../docs/morgen-v6-reverse-engineering.md`;
5. read `../docs/implementation-plan.md`;
6. confirm `main` is current and record HEAD;
7. use pinned Morgen commit `374432cee6380e0aa0f81390e26b990147e5e58d` only for approved V6 pattern/source adaptation.

Do not re-read `project-9901` as a normal implementation step. The developer requirements have been canonicalized in this repository.

## Critical implementation rule

Do **not** copy the complete Morgen plugin or its Public UI Bootstrap.

Create a fresh Gloskin composition root, fresh asset registry, Gloskin content models and Gloskin routing first. Then selectively adapt only the V6 patterns classified as retained/adaptable in `../docs/prune-matrix.csv`.

The production runtime must not inherit Morgen Technical Library/Documents/PDF, Applications, Hammer, Quality Testing, custom product management, inquiry/mail stack, EN/DE routing, historical CASE-PROD/PROD migrations, V1-V5 presentation switching or SEO proxy tooling.

WooCommerce remains the product and commerce authority.