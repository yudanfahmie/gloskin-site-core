# Gloskin Site Core

WordPress presentation and page-builder plugin for Gloskin, using selected proven Morgen UI V6 patterns while keeping WordPress and WooCommerce as the authoritative platform layers.

## Current status

This repository is the canonical developer handoff for Gloskin Site Core. Developer-only requirements from the original project material have already been normalized here; routine implementation must not depend on reopening `project-9901`.

No Morgen production code has been cloned into the plugin. The implementation must start from a fresh Gloskin micro-kernel and selectively adapt only approved V6 behavior.

Pinned Morgen provenance:

- repository: `yudanfahmie/morgen-core`;
- commit: `374432cee6380e0aa0f81390e26b990147e5e58d`;
- UI V6 is structural/interaction provenance only;
- production presentation name: **Gloskin UI v1**.

### Current owner-approved presentation refresh

The next Gloskin presentation revamp is packaged in `docs/2026-08-18-prototype-refresh/` with the interactive prototype, raw-wireframe provenance, official color contract, owner-supplied font manifest, implementation rules, acceptance criteria and one codebase-only AI developer prompt.

For that work, the refresh package is the newest owner-approved authority for **visual presentation and interaction direction**. Existing canonical documents remain authoritative for architecture, routes, storage, security, WordPress/WooCommerce ownership and SEO/GEO structure. Factual site data remains WordPress/WooCommerce-owned; prototype copy is not commercial/medical truth.

## Canonical developer reading order

1. `CONTRIBUTING.md` — mandatory main-only workflow and architecture discipline.
2. `docs/2026-08-18-prototype-refresh/README.md` — current owner-approved presentation target and task group.
3. `docs/developer-source-of-truth.md` — authoritative normalized product/developer requirements outside presentation overrides above.
4. `docs/architecture-efficiency-audit.md` — canonical runtime architecture, simplification and security contract.
5. `docs/runtime-service-map.csv` — one-owner service/request/storage boundaries.
6. `docs/content-data-contracts.md` — content/entity fields, relationships and pending inputs.
7. `docs/morgen-v6-reverse-engineering.md` — source-level Morgen findings and dependency cuts.
8. `docs/implementation-plan.md` — ordered implementation sequence.
9. `docs/page-matrix.csv` — route/page-family inventory.
10. `docs/prune-matrix.csv` — source/capability retain-adapt-remove decisions.
11. `docs/source-notes.md` — provenance only; not a normal development dependency.
12. `tests/README.md` — verification contract.

## Architecture at a glance

The target is a **modular monolith with one micro-kernel and small internal services**, not distributed/network microservices.

The intended first-party owners are:

- `ContentService` — Gloskin content types/meta/relationships;
- `TemplateService` — page contexts and template ownership;
- `AssetService` — the only first-party frontend asset owner;
- `NavigationService` — one normalized desktop/mobile menu tree;
- `WooCommerceAdapter` — optional Woo presentation bridge only;
- `FormAdapter` — optional external form presentation bridge only;
- `AdminService` — minimal native-first admin/settings enhancements;
- `LifecycleService` — activation/deactivation and narrowly scoped future upgrades only.

`Kernel` is composition only. It must not become a Gloskin equivalent of the Morgen `System` mega-class.

### Complexity guardrails

Gloskin UI v1 starts with:

- one composition root;
- at most eight first-party bootable services;
- one asset registry/owner;
- at most one small global plugin option;
- zero custom database tables;
- zero generic runtime migration framework;
- zero custom form/mail backend;
- zero duplicate WooCommerce commerce ownership;
- zero UI version switcher;
- zero routine custom write locks/read-after-write rollback/cache surgery;
- zero compatibility/recovery layers without a real released Gloskin compatibility requirement.

The principle is **strict boundaries, not repeated validation**: capability + nonce + sanitization at writes, contextual escaping at output, dependency checks once in adapters, and native WordPress/WooCommerce persistence.

## Required site families

The normalized architecture covers Home, About, Treatments Hub + exactly eight treatment category pages, Skincare Hub + seven Woo-mapped landing pages, Clinics Hub + nine clinic pages, Contact, Insights Hub, Shop Hub, Doctors Hub + thirteen doctor pages, up to twenty WooCommerce product pages, and Woo cart/checkout presentation compatibility.

The explicit route inventory supersedes stale raw headline counts such as `21` or `33` pages.

## Key Morgen pruning conclusion

Do **not** copy `morgen-plugin/` wholesale.

The pinned Morgen runtime combines multiple UI generations, virtual routing, industrial domains, custom product/document/PDF systems, inquiry/mail handling, large custom admin persistence, compatibility guards, diagnostics, telemetry and historical migration/reconciliation machinery.

Gloskin keeps only proven V6 presentation behavior that can live cleanly under Gloskin ownership. If a proposed class mainly exists to repair/protect another Gloskin class, fix the original owner instead of adding another layer.

## Explicit exclusions

Unless the owner later changes scope, this repository does not implement SEO/GEO operations, Rank Math proxy/schema administration, GSC/GA4/GTM/GBP, backlinks/media/social/reporting, DNS/domain/redirect/SSL orchestration, medical approval workflow tooling, custom Midtrans/Xendit business logic, WooCommerce backend replacement, a second product manager, Morgen Technical Library/Documents/PDF/download features, Applications/Hammer/Quality Testing, historical Morgen migrations/repairs, Morgen EN/DE routing, custom inquiry/mail handling, or UI V1-V5/presentation switching.

## Workflow rules

This repository is main-only. Work directly on `main`, pull/record HEAD before editing, make one coherent change set, use short lowercase action-oriented commit messages, run available checks, verify remote `main`, and update canonical architecture/docs in the same commit when implementation decisions change.

Do not modify `project-9901` while working on Gloskin.
