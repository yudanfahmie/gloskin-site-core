# Plugin Workspace

Production implementation belongs under `plugin/gloskin-site-core/` unless a later build/release decision explicitly moves it to repository root.

Before writing production code, read `../CONTRIBUTING.md`, `../docs/developer-source-of-truth.md`, `../docs/architecture-efficiency-audit.md`, `../docs/runtime-service-map.csv`, `../docs/content-data-contracts.md`, `../docs/morgen-v6-reverse-engineering.md`, and `../docs/implementation-plan.md`. Confirm `main` is current and use Morgen commit `374432cee6380e0aa0f81390e26b990147e5e58d` only for approved V6 pattern adaptation.

Do not re-read `project-9901` as a normal implementation step.

## Required architectural shape

Do **not** copy the complete Morgen plugin or its Public UI/Workflow bootstraps.

Build a fresh modular Gloskin runtime around one `Kernel`. The preferred v1 production shape is intentionally small:

```text
plugin/gloskin-site-core/
├── gloskin-site-core.php
├── includes/
│   ├── class-gloskin-site-core-kernel.php
│   ├── class-gloskin-site-core-content-service.php
│   ├── class-gloskin-site-core-template-service.php
│   ├── class-gloskin-site-core-asset-service.php
│   ├── class-gloskin-site-core-navigation-service.php
│   ├── class-gloskin-site-core-woocommerce-adapter.php
│   ├── class-gloskin-site-core-form-adapter.php
│   ├── class-gloskin-site-core-admin-service.php
│   └── class-gloskin-site-core-lifecycle-service.php
├── config/
│   └── assets.php
├── templates/
└── assets/
    ├── css/
    └── js/
```

This is a target ownership map, not a mandate to create empty files. If a service is unnecessary, omit it. Do not exceed the service budget merely to preserve symmetry.

## Runtime rules

- `Kernel` coordinates only; it contains no page/business/persistence logic.
- One concern has one owner.
- Frontend/admin/activation request profiles boot only their relevant services.
- `AssetService` is the only first-party frontend enqueue owner.
- Desktop/mobile navigation consume one normalized tree.
- Page templates receive small page-specific contexts, not one global site dataset.
- Woo/form availability is resolved once in their adapters, not repeatedly in templates.
- Native WordPress Pages/CPT routes are preferred over virtual request routing.
- Standard WordPress editor/settings/meta flows are preferred over custom AJAX save applications.

## Persistence rules

Use core WordPress/WooCommerce storage:

- native Page/Post content for page/editor content;
- registered post meta for Gloskin entity fields;
- Media Library attachment IDs for media;
- WooCommerce-owned storage/APIs for products and commerce;
- at most one small Gloskin global settings option when truly needed.

No custom database table, giant content option, generic persistence engine, custom lock manager, routine read-after-write verification/rollback, or manual option-cache surgery belongs in v1.

## Security rules

Use capability checks, nonces for custom mutations, typed/field-specific sanitization, and contextual escaping. Do not weaken these boundaries in the name of simplification.

No public form-processing/mail backend, public unauthenticated AJAX endpoint, payment/order mutation logic, arbitrary filesystem path, or raw SQL belongs in v1 without a new explicitly reviewed requirement.

## Morgen exclusions

The production runtime must not inherit Morgen Technical Library/Documents/PDF, Applications, Hammer, Quality Testing, custom product management, inquiry/mail stack, EN/DE routing, virtual/proxy page engine, historical CASE-PROD/PROD migrations, diagnosis/telemetry/reconciliation, V1-V5 switching, custom settings hardening stack, queue-repair ownership guards, or SEO proxy tooling.

WooCommerce remains the product and commerce authority.
