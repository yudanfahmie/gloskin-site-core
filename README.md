# Gloskin Site Core

WordPress presentation and page-builder plugin for the Gloskin website, using selected proven Morgen UI V6 patterns while keeping WordPress and WooCommerce as the authoritative platform layers.

## Current status

This repository is the canonical developer handoff for Gloskin Site Core. The developer-only requirements from the original project material have been normalized here; normal implementation work should **not** depend on reopening `project-9901`.

No Morgen production code has been cloned into the plugin yet. The next implementation engineer should build a fresh Gloskin composition root and selectively adapt approved V6 patterns according to the reverse-engineering record.

Pinned Morgen provenance:

- `yudanfahmie/morgen-core`
- commit `374432cee6380e0aa0f81390e26b990147e5e58d`
- UI V6 is structural provenance only
- production presentation name: **Gloskin UI v1**

## Canonical developer reading order

1. `CONTRIBUTING.md` — mandatory main-only workflow and commit discipline.
2. `docs/developer-source-of-truth.md` — authoritative developer scope and normalized requirements.
3. `docs/content-data-contracts.md` — exact content/entity fields, relationships and pending inputs.
4. `docs/morgen-v6-reverse-engineering.md` — source-level Morgen findings and dependency cuts.
5. `docs/implementation-plan.md` — ordered execution plan for the future implementation.
6. `docs/page-matrix.csv` — route/page-family inventory.
7. `docs/prune-matrix.csv` — source/capability retain-adapt-remove decisions.
8. `docs/source-notes.md` — provenance only; not a normal development dependency.
9. `tests/README.md` — verification contract.

## Architecture at a glance

Gloskin Site Core owns:

- public shell/header/footer;
- responsive page templates and components;
- treatments, clinics and doctors presentation/content structures;
- fixed page presentation;
- WordPress Insights presentation;
- WooCommerce presentation integration;
- external form presentation integration.

WooCommerce remains authoritative for products, categories/attributes, price/stock, cart, checkout, orders, customers and payment gateways.

WordPress remains authoritative for native Pages, Posts and Media Library data.

## Required site families

The normalized architecture covers:

- Home;
- About;
- Treatments Hub + exactly 8 treatment category pages;
- Skincare Hub + 7 category landing pages mapped to WooCommerce;
- Clinics Hub + 9 clinic pages;
- Contact;
- Insights Hub;
- Shop Hub;
- Doctors Hub + 13 doctor pages;
- up to 20 WooCommerce product pages;
- WooCommerce cart/checkout presentation compatibility.

The explicit route inventory supersedes stale raw headline counts such as `21` or `33` pages.

## Key reverse-engineering conclusion

Do **not** copy `morgen-plugin/` wholesale.

Morgen's current V6 bootstrap/shell is transitively tied to industrial products, Technical Library/Documents/PDF, Applications, Hammer, Quality Testing, inquiry/mail/form security, SEO proxy helpers, EN/DE routing, diagnosis and CASE-PROD/PROD history.

The safe strategy is:

1. create fresh Gloskin ownership;
2. create a fresh Gloskin asset registry;
3. create Gloskin content/routes;
4. adapt only proven V6 layout/accessibility/interaction patterns that survive dependency review.

See `docs/morgen-v6-reverse-engineering.md`.

## Explicit exclusions

Unless the owner later changes scope, this repository does not implement:

- SEO/GEO strategy/content/scoring;
- GSC/GA4/GTM/GBP operations;
- backlinks/media/social/marketing reporting;
- Rank Math proxy/schema administration;
- DNS/domain consolidation/redirect execution/SSL orchestration;
- medical approval workflow tooling;
- custom Midtrans/Xendit business/payment logic;
- WooCommerce backend replacement;
- a second product manager/database;
- Morgen Technical Library/Documents/PDF/download features;
- Morgen Applications/Hammer/Quality Testing domains;
- Morgen historical migrations/repair/reconciliation;
- Morgen EN/DE routing;
- custom Morgen inquiry/mail backend;
- UI V1-V5 or presentation switching.

## Workflow rules

This repository is intentionally **main-only**.

- Work directly on `main`.
- Do not create feature/work/temp branches or pull requests unless the owner explicitly changes the rule.
- Pull latest `origin/main` and record HEAD before editing.
- One coherent outcome should produce one effective commit where practical; do not commit per file.
- Commit messages must be short, lowercase and action-oriented.
- Do not create probe/checkpoint commits.
- Review the complete diff and run available checks before push.
- Verify remote `main` after push.
- Do not modify `project-9901` while working on Gloskin.

## Recommended GitHub metadata

**Description**

`WordPress presentation and page-builder plugin for Gloskin, selectively adapted from Morgen UI V6 with WooCommerce-native commerce.`

**Suggested topics**

`wordpress`, `woocommerce`, `page-builder`, `gloskin`, `php`, `frontend`