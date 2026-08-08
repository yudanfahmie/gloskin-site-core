# Gloskin Site Core

WordPress presentation and page-builder plugin for the Gloskin website, adapted from the stable Morgen UI V6 foundation while keeping WooCommerce as the commerce authority.

## Project status

This repository currently contains the implementation blueprint and repository setup only. No Morgen production code has been cloned yet.

The future implementation will use:

- `yudanfahmie/morgen-core` commit `374432cee6380e0aa0f81390e26b990147e5e58d` as the pinned structural baseline;
- Morgen UI V6 as the only UI source baseline;
- Gloskin raw requirements from `yudanfahmie/project-9901` as read-only reference material;
- WooCommerce for products, cart, checkout, orders, payment integrations, and product administration;
- Gloskin Site Core for the public shell, page layouts, content presentation, responsive behavior, and lightweight treatment/clinic/doctor content models.

## Repository scope

In scope:

- Gloskin UI v1 derived from Morgen V6;
- homepage and fixed content pages;
- treatments, skincare landing pages, clinics, doctors, insights, contact, and shop presentation;
- WooCommerce presentation integration;
- reusable responsive components;
- clean WordPress-admin editing for Gloskin-specific non-commerce content.

Out of scope:

- SEO/GEO execution and content production;
- backlinks, media placement, social campaigns, and marketing reporting;
- GSC/GA4/GBP operations;
- WooCommerce product/order backend replacement;
- custom payment-gateway business logic;
- DNS/domain migration and redirect execution;
- medical approval workflow tooling.

## Required reading

- `docs/implementation-plan.md` — implementation and cloning plan.
- `docs/page-matrix.csv` — required route/page-family inventory.
- `docs/prune-matrix.csv` — Morgen capabilities to retain, adapt, replace, or remove.
- `CONTRIBUTING.md` — mandatory repository workflow and commit rules.
- `plugin/README.md` — reserved production plugin workspace.
- `tests/README.md` — verification expectations.

## Workflow rules

This repository is intentionally **main-only**.

- Work directly on `main`.
- Do not create feature branches or pull requests unless the repository owner explicitly changes this rule.
- Before editing, pull the latest `origin/main` and record the current HEAD.
- Group related changes into one effective commit; do not create one commit per file.
- Keep commit messages short, lowercase, and action-oriented.
- Do not use temporary/probe commits.
- Review the diff and run available checks before every push.
- Completion means the implementation is present on remote `main`, not only locally.

## Recommended GitHub metadata

**Description**  
`WordPress presentation and page-builder plugin for Gloskin, adapted from Morgen UI V6 with WooCommerce-native commerce.`

**Suggested topics**  
`wordpress`, `woocommerce`, `page-builder`, `gloskin`, `php`, `frontend`
