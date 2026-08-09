# Contribution Rules

These rules are mandatory for AI agents and human developers working in this repository.

## Branch policy

- Work directly on `main`.
- Do not create feature/work/temp branches or pull requests unless the repository owner explicitly changes this policy.
- Never use a branch as a scratch area.

## Requirements authority

For normal Gloskin development, this repository is authoritative. Read relevant material in this order:

1. `docs/developer-source-of-truth.md`
2. `docs/architecture-efficiency-audit.md`
3. `docs/runtime-service-map.csv`
4. `docs/content-data-contracts.md`
5. `docs/seo-geo-engineering-contract.md`
6. `docs/morgen-v6-reverse-engineering.md`
7. `docs/implementation-plan.md`
8. `docs/page-matrix.csv`
9. `docs/prune-matrix.csv`

`yudanfahmie/project-9901` is provenance/raw reference only. Do not modify it, copy its raw files here, or make routine implementation dependent on re-reading it. If a value is not captured in canonical Gloskin docs, treat it as pending/new input instead of silently rediscovering raw assumptions.

The pinned Morgen source may be inspected only when implementing a documented reuse/adaptation decision.

## Before editing

1. Confirm repository `yudanfahmie/gloskin-site-core`.
2. Checkout `main`.
3. Pull latest `origin/main`.
4. Record/report current HEAD.
5. Inspect current implementation and relevant canonical docs.
6. Define one coherent outcome for the change.
7. Identify the canonical service/owner for every runtime concern being changed.
8. For route/template/navigation changes, verify the SEO/GEO engineering contract remains satisfied.

## Architecture efficiency contract

The target is a modular monolith with one micro-kernel and small internal services. Do not introduce distributed-service infrastructure, a generic DI container, or framework complexity merely to imitate microservices.

Mandatory rules:

- exactly one composition root (`Kernel`);
- at most eight first-party bootable services in v1 unless the owner approves an architecture change;
- one canonical owner per concern;
- one first-party asset registry/owner;
- native WordPress routing/storage before custom infrastructure;
- WooCommerce remains the sole commerce authority;
- optional integrations are adapters and dependency availability is resolved inside the adapter, not repeatedly in templates;
- do not create a Gloskin `System` mega-class;
- do not create a second bootstrap/workflow composition layer;
- do not add a class whose primary purpose is to repair/protect/restore another Gloskin class without first fixing the canonical owner;
- do not prebuild compatibility wrappers, recovery frameworks, migration consoles, telemetry or cache layers;
- no custom database tables in v1 without a demonstrated need and explicit architecture update;
- at most one small global settings option; entity/page/commerce data must stay in their native owners;
- do not dual-write relationships merely for convenience; keep one canonical relationship direction unless measured performance later justifies denormalization.

See `docs/architecture-efficiency-audit.md` and `docs/runtime-service-map.csv`.

## SEO/GEO engineering contract

Developer-side SEO/GEO friendliness is part of the Gloskin product baseline. It is not operational SEO work.

Any route/template/component change must preserve:

- server-rendered, crawlable primary content;
- semantic landmarks and logical heading hierarchy;
- one clear page topic/H1;
- stable WordPress/Woo canonical route behavior;
- crawlable anchor-based navigation/internal links;
- useful hub/detail relationships and breadcrumb capability;
- metadata/schema provider compatibility without duplicate output;
- meaningful WordPress Media alt-data support;
- Core Web Vitals-minded asset/media behavior;
- graceful empty states rather than invented SEO copy;
- no hidden keyword/GEO blocks, cloaking, duplicate SEO prose, or crawler-specific content hacks.

Operational SEO remains excluded: keyword campaigns, backlink work, recurring GSC/GA4/GBP operations, ranking/reporting, media/social campaigns, and content-production operations.

Do not interpret older documentation phrases such as “SEO/GEO/schema administration excluded” as excluding semantic HTML, crawlability, performance, stable IA, or provider-safe technical structure. Read `docs/seo-geo-engineering-contract.md`.

## Validation and persistence discipline

Simplification must not weaken security.

For custom state-changing admin paths use capability checks, nonces, field-appropriate validation/sanitization, then one native WordPress persistence path. Escape again for the final output context.

Do not add routine custom locks, revision choreography, read-after-write verification, rollback wrappers or manual option-cache invalidation around normal WordPress settings/meta writes. Those mechanisms require a concrete failure mode or multi-object atomicity requirement.

Prefer `register_setting()`, registered post meta, native Posts/Pages/Media, and WooCommerce APIs. Avoid direct `$wpdb` writes.

No public `wp_ajax_nopriv_*` endpoint belongs in v1 unless a later explicit feature requires it and its threat model is documented.

## Commit policy

- Group files implementing one coherent outcome into one commit.
- Do not create one commit per file.
- Do not create probe/checkpoint/temporary commits.
- Keep messages short, lowercase and action-oriented.
- If a task naturally contains independent production outcomes, use the smallest reasonable number of commits rather than forcing unrelated changes together.

## Change discipline

- Make only changes required by the current task and canonical architecture.
- Keep working Gloskin behavior unless requirements explicitly change it.
- Do not add dependencies/frameworks without demonstrated need.
- Do not add/change GitHub Actions merely as a probe/workaround.
- Do not wholesale-copy Morgen.
- Do not introduce Morgen historical migrations, repair state, compatibility aliases, virtual route engine, diagnosis bundle, telemetry, custom mail or product systems.
- Do not duplicate WooCommerce product/cart/checkout/order/payment ownership.
- Do not introduce operational SEO/marketing/infrastructure tooling that is outside developer scope; developer-side semantic/crawlable/performance structure remains mandatory.

### Staging editorial media policy

- WordPress Media Library attachments and WooCommerce-owned product images are always the factual production authority and must override any editorial fallback.
- During staging, a small curated deterministic set of Unsplash photography may be used for generic/decorative hero, skincare, treatment-discovery and editorial compositions when approved WordPress media is not yet available.
- Staging Unsplash usage must use fixed photo URLs or first-party downloaded derivatives. Do not use the Unsplash API, random/query endpoints or runtime search.
- Stock photography must never be presented as a specific Gloskin doctor, a specific clinic branch, a real WooCommerce product, or a medical before/after result.
- Missing factual doctor/clinic/product media must keep the neutral Gloskin empty-state placeholder until real WordPress/Woo media is supplied.
- Production migration should replace staging editorial stock with approved WordPress media where the surface has a factual media owner; do not create a parallel media database or service to manage that transition.

## Documentation discipline

When implementation changes architecture ownership, service boundaries, storage, content fields, relationships, routes, SEO/GEO engineering responsibilities, or retained/pruned Morgen dependencies, update the matching canonical documentation in the **same coherent commit**.

Do not let implementation knowledge live only in chat, commit messages, or developer memory.

## Verification before push

1. Review the complete diff.
2. Confirm production files changed when the task is an implementation task.
3. Run existing checks available in the environment.
4. Check for secrets, raw client files, generated archives and debug artifacts.
5. Run static architecture/exclusion checks when relevant.
6. Confirm no duplicate concern owner or corrective shim was introduced.
7. For public presentation changes, confirm SEO/GEO structural invariants and no duplicate metadata/schema owner.
8. Commit the coherent change set.
9. Push directly to `origin/main`.
10. Verify remote `main` points to the pushed commit.
11. Inspect final commit stats/diff.

Do not claim completion when changes exist only locally or push verification fails.
