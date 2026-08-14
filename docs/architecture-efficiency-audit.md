# Architecture Efficiency Audit

## 1. Purpose

This document is the canonical architecture-efficiency contract for Gloskin Site Core.

It answers a narrower question than the general Morgen reverse-engineering record: **what is the smallest, clearest, secure architecture that preserves the useful Morgen UI V6 behavior without inheriting Morgen's accumulated production-recovery complexity?**

The target is a low-effort/high-impact WordPress implementation that is easy to reason about, easy to change, and difficult to accidentally couple back to Morgen history.

The intended model is a **modular monolith with a micro-kernel and small internal services**. “Microservices” in this repository means narrowly owned internal modules with explicit dependencies. It does **not** mean network services, containers, RPC, a service mesh, or a generic dependency-injection framework.

## 2. Executive decision

Gloskin must not be implemented by cloning Morgen and then deleting unwanted modules.

Morgen UI V6 remains valuable as a source of proven responsive layout, accessibility, drawer/disclosure, carousel/gallery, motion, and presentation patterns. The surrounding Morgen runtime is not an efficient foundation for a fresh Gloskin plugin because it carries years of compatibility, production repair, multiple UI generations, custom content systems, and defensive persistence layers.

The Gloskin strategy is therefore:

1. build a tiny Gloskin kernel;
2. give each concern exactly one owner;
3. use native WordPress/WooCommerce persistence and routing;
4. load only services needed for the current request;
5. adapt V6 behavior into those owners;
6. add recovery/diagnostic/cache complexity only after a demonstrated Gloskin need exists.

## 3. What the second-pass Morgen audit found

### 3.1 `Morgen_Core_System` is a God object

The pinned `morgen-plugin/morgen-core.php` combines plugin boot, capabilities, route/query variables, virtual request ownership, UI-version preview handling, assets, critical CSS, cache invalidation, favicon/login customization, SEO/title handling, inquiry/PDF dialogs, sitemap behavior, product media behavior, migrations, and other cross-cutting concerns.

This shape is understandable for a mature compatibility-heavy plugin, but it is the wrong starting point for Gloskin. A Gloskin equivalent would become the highest-risk maintenance surface immediately.

**Decision:** there will be no `Gloskin_Site_Core_System` mega-class. The kernel may coordinate services but may not own page/business/persistence logic.

### 3.2 Morgen has more than one composition layer

The public UI bootstrap already boots retained services. A separate workflow bootstrap additionally brings assets, critical-assets correction, mail, migration resources, migration registry protection, migrations, legacy schema migration, product-copy migration, site reconciliation, and homepage media administration into a coordinated lifecycle.

**Decision:** Gloskin has one canonical kernel. Optional integrations register through that kernel; there is no second “workflow bootstrap” that silently broadens runtime scope.

### 3.3 Asset ownership needs corrective layers in Morgen

Morgen's current UI6 cleanup ownership service snapshots the global WordPress style/script queues around competing cleanup hooks and restores canonical handles afterward. Critical-assets code also corrects preload/stylesheet markup behavior.

These are recovery mechanisms for a runtime with multiple historical asset owners. They should not become permanent architecture in a new plugin.

**Decision:** one Gloskin `AssetService`, one declarative registry, one enqueue owner. No queue snapshot/restore guard. No compatibility aliases. No post-hoc style-tag correction layer unless a real third-party conflict is reproduced and cannot be solved at the owner.

### 3.4 Persistence hardening is repeated across Morgen admin paths

Morgen has separate settings-hardening, mutation-hardening, record-hardening, lock-safety, rollback-safety, verified-save, and option-cache-safety layers. Ordinary writes are guarded by custom nonces, request limits, revision values, application locks, explicit cache invalidation, read-after-write verification, and rollback behavior.

That defense grew around a very large legacy option and multiple custom AJAX writers. Repeating it in Gloskin would create more code than the data being protected.

**Decision:** Gloskin uses WordPress persistence primitives as the transaction boundary for normal writes:

- `register_setting()` / Settings API for the very small set of true global settings;
- native Page/Post content for page copy;
- `register_post_meta()` or equivalent native metadata for treatment/clinic/doctor fields;
- WordPress Media Library attachment IDs for media;
- native WooCommerce CRUD/data APIs for commerce data.

Validation happens once at the write boundary. Output is escaped at render time. Ordinary single-record WordPress writes do not get custom locks, manual cache purges, read-after-write comparison, or application rollback wrappers.

### 3.5 Historical migration infrastructure is much larger than Gloskin needs

Morgen has a generic migration manager with multiple traits for admin UI, state, support, completion safety, entrypoints and control, plus migration registry shielding, integrity recovery, compatibility rescue, resources, audits, global locks and incident-specific handlers. `CASE-PROD-011` site reconciliation alone verifies/imports PDFs, wording rules and image-alt repairs before retiring its payload.

The pinned generated profile actively points to historical production migration state. This is appropriate incident machinery for Morgen, not a fresh Gloskin requirement.

**Decision:** Gloskin v1 has no generic runtime migration console, migration registry, recovery payload, CASE/PROD concept, completion audit, or username-specific migration gate. If a future released Gloskin version genuinely needs a schema/data upgrade, add the smallest idempotent versioned upgrade for that specific change at that time.

### 3.6 Manual option-cache repair should not be preemptively inherited

Morgen explicitly deletes option, `alloptions`, and `notoptions` cache entries after selected settings writes.

**Decision:** Gloskin uses WordPress APIs and relies on WordPress cache invalidation semantics. Direct database writes and manual cache surgery are prohibited in v1 unless a reproducible cache bug or measured performance requirement justifies an exception.

### 3.7 Diagnostic and telemetry surface is disproportionate for a fresh plugin

Morgen contains large diagnosis, archive, sanitizer and telemetry modules that support an established production system.

**Decision:** no custom diagnosis bundle or telemetry subsystem in Gloskin v1. Use normal WordPress/PHP logging under development/debug conditions. A tiny Site Health check may be added later only for a concrete operational need.

## 4. Target architecture: micro-kernel + mini-services

The target is intentionally boring.

### Kernel

`Kernel` is the only composition root.

Responsibilities:

- know the plugin path/version;
- instantiate/register the approved services;
- choose request profile: shared, frontend, admin, activation/deactivation;
- expose no content/business logic;
- contain no route catalog, Woo query logic, form submission logic, page copy, or custom persistence implementation.

Do not add a generic service container. A short explicit service list is easier to inspect and safer for this project.

### Service 1 — ContentService

Owns:

- registration of `gloskin_treatment`;
- registration of `gloskin_clinic`;
- registration of `gloskin_doctor`;
- registered metadata and sanitizers;
- canonical relationship storage direction;
- native editor/meta UI only where WordPress does not already provide it.

Does not own frontend templates or Woo products.

### Service 2 — TemplateService

Owns:

- plugin template selection for Gloskin-owned page families;
- small page-context builders;
- template helpers that are presentation-specific and side-effect free;
- native 404 behavior.

Each page context queries only what that page needs. There is no global `$data` payload containing products, categories, library, applications, quality and unrelated domains.

Prefer native Page/CPT rewrite behavior. Do not recreate Morgen's virtual route engine unless a later explicit requirement cannot be met with WordPress routes.

### Service 3 — AssetService

Owns all first-party frontend CSS/JS registration and enqueue decisions.

Rules:

- one declarative registry;
- Gloskin-only handles;
- explicit dependencies;
- conditional page/component loading;
- no legacy aliases/path maps;
- no queue cleanup protection;
- no diagnosis hooks;
- no global feature bundle when a page does not use that feature.

A release/plugin version is sufficient as the initial cache-busting strategy. Content-hash manifests may be introduced later only if the build process actually generates them reliably.

### Service 4 — NavigationService

Owns presentation of primary navigation and mobile navigation data.

Preferred data source is a native WordPress menu or a small code-owned fallback derived from the canonical Gloskin IA.

There is no UI-version registry, no EN/DE label matrix, no custom navigation persistence engine, and no duplicated desktop/mobile registries. Desktop and mobile views consume the same normalized menu tree.

### Service 5 — WooCommerceAdapter

Optional integration service. It boots only when WooCommerce is available and the request needs commerce presentation.

Owns:

- read-only helpers for Woo product/category presentation;
- supported Woo template hooks/classes needed by Gloskin styling;
- skincare landing-to-Woo category resolution;
- graceful unavailable/empty behavior.

It does not own product CRUD, cart, checkout, orders, customers, payment, gateway configuration or transaction logic.

Woo dependency availability is resolved once in the adapter, not through scattered `class_exists()` checks in templates.

### Service 6 — FormAdapter

Optional integration service.

Owns:

- rendering the configured external form integration;
- presentation-level success/error compatibility where possible;
- graceful fallback if the configured provider is unavailable.

It never owns public form submission, mail routing, auto-replies, captcha/anti-spam or submission storage in v1.

### Service 7 — AdminService

Boots only in admin requests.

Owns the smallest required Gloskin settings/editor enhancements. Prefer native WordPress screens and controls before custom AJAX applications.

Do not build a general Gloskin admin framework, dashboard shell, testing-request system, custom settings transaction engine, or design-token editor unless a later requirement demonstrates the value.

### Service 8 — LifecycleService

Runs only for activation/deactivation and narrowly defined future upgrades.

Initial responsibilities should be minimal:

- register required types/rewrite rules during activation context;
- flush rewrite rules once when necessary;
- no production-content seeding unless explicitly approved;
- no historical migration runner.

This is the maximum intended first-party service set for v1. Fewer services are preferred when a concern can stay a small private helper inside its owner without reducing clarity.

## 5. Request profiles

### Shared boot

Load only definitions required by both frontend/admin, primarily ContentService registration and lightweight constants/helpers.

### Frontend request

Boot:

- TemplateService;
- AssetService;
- NavigationService;
- WooCommerceAdapter only when applicable;
- FormAdapter only when applicable.

Do not load admin editors, migration code, diagnostic archives, mail engines, SEO proxy tooling or unused feature bundles.

### Admin request

Boot:

- ContentService;
- AdminService;
- optional integration settings only when their screen is active.

Do not initialize the public carousel/drawer/gallery runtime in wp-admin.

### Activation/deactivation

Run LifecycleService deliberately. Do not make normal requests repeatedly check whether activation work is complete.

## 6. One-owner rule

Every runtime concern has one canonical owner.

Examples:

- assets -> AssetService;
- templates/page contexts -> TemplateService;
- nav tree -> NavigationService;
- Gloskin entity schema -> ContentService;
- Woo bridge -> WooCommerceAdapter;
- form bridge -> FormAdapter;
- global settings/admin UI -> AdminService;
- activation/upgrades -> LifecycleService.

A new class whose main purpose is to repair, protect, override, restore, normalize, or reconcile the output of another Gloskin class is an architecture warning. Fix the canonical owner first.

Compatibility wrappers/no-op endpoints are not added preemptively. They are allowed only if a released Gloskin API later creates a real backward-compatibility obligation.

## 7. Persistence model

“Persistence” in Gloskin means durable, understandable WordPress-native storage, not a custom persistence framework.

### Global settings

Maximum one small option namespace is preferred, for values that are genuinely global and not content:

- selected UI design direction if runtime selection is still needed;
- configured form integration value;
- narrowly required global CTA/contact fallback settings that are not better represented as content.

Do not put treatment/clinic/doctor records, navigation trees, Woo products, homepage content, analytics, migration state and UI editor history into one giant option.

### Entity fields

Use registered post meta with explicit type, `single`, sanitizer and authorization rules. Use core title/content/featured image where those fields already fit.

### Relationships

Store one canonical direction for each relationship and derive the reverse relation by query. Do not persist both `doctor -> clinics` and `clinic -> doctors` unless measured query cost later proves a need; dual-write relationships create consistency work.

### Media

Store attachment IDs, not duplicated paths/URLs or custom file copies.

### Commerce

Read/write through WooCommerce APIs and Woo-managed fields only where Gloskin is authorized to edit them. Gloskin v1 primarily reads commerce data for presentation.

## 8. Validation strategy: strict boundaries, not repeated validation

The correct simplification is not “less security”; it is **one validation owner per boundary**.

### Code-owned configuration

Validate static/declarative registries in tests or once during development/activation. Do not re-run expensive structural assertions on every frontend request if the configuration ships with the plugin.

### Admin writes

At the mutation boundary:

1. capability check;
2. nonce verification when using a custom form/action;
3. size/count guard only where payload size is actually variable;
4. field-appropriate sanitization/validation;
5. one native WordPress persistence call.

Do not then independently sanitize the same value through multiple owners and compare the database readback for a normal WordPress save.

### Frontend output

Escape for output context every time: HTML, attribute, URL, JSON, etc. Output escaping is not redundant with write sanitization because the contexts differ.

### External integration input

Resolve dependency/configuration once in its adapter. Templates consume normalized adapter results, not raw plugin globals.

## 9. Security contract

The simpler architecture must retain a strong security boundary.

Required defaults:

- direct PHP entry protection;
- WordPress capability checks for administrative mutations;
- WordPress nonces for custom state-changing requests;
- typed/sanitized registered meta/settings;
- escaped output;
- no arbitrary filesystem paths;
- no raw SQL/custom tables in v1;
- no `unserialize()` of user-controlled content;
- no public `wp_ajax_nopriv_*` endpoint in v1 unless a later explicit feature truly requires one;
- no Gloskin mail transport or public form processor;
- no payment/order mutation logic;
- no secret/API credential storage unless a future integration explicitly requires it.

### Maps

Store a validated Google Maps URL/embed source or structured location value. Render a controlled iframe/template. Do not store/render arbitrary iframe HTML from untrusted roles.

### WhatsApp

Normalize the configured phone/contact value and construct the outbound URL centrally. Do not concatenate unchecked frontend input into URLs.

### Form shortcode/block integration

Only trusted administrative users may configure the integration value. The adapter should render only the configured integration path and fail closed/gracefully when unavailable.

## 10. Routing and page contexts

Native WordPress routes are the default.

Avoid custom query variables, request claiming, forced query flags, synthetic virtual pages, proxy posts and custom permalink filters unless a documented route cannot be implemented natively.

Templates should receive small page-specific contexts. Example:

- Home queries selected treatments/clinics/doctors/Woo/posts needed by Home;
- Clinic detail queries that clinic + related doctors/treatments;
- Doctor detail queries that doctor + related branches/treatments;
- Shop delegates product loops to Woo.

Do not build one global site dataset and pass it to every page.

## 11. Asset and frontend runtime budget

Default target:

- one core stylesheet;
- one small core interaction script only if needed globally;
- feature CSS/JS loaded conditionally;
- Splide loaded only on pages/components that instantiate a carousel/gallery;
- no V1-V5 CSS/JS;
- no compatibility aliases;
- no cleanup/re-enqueue dance;
- no frontend diagnostics payload.

Header/footer/navigation must not require WooCommerce or form-provider code to render.

## 12. Design variants without a settings subsystem

The raw project requests three initial visual directions. That does not justify importing Morgen's editable multi-version design-token administration.

Initial implementation should define the three variants as code/CSS token sets sharing one component tree. If the selected direction is known before launch, collapse production to the selected direction or keep only a tiny global variant setting.

Do not build a general token editor, revisioning, save locks or V1-V6 appearance engine for v1.

## 13. Caching policy

No custom cache layer by default.

Use normal WordPress/Woo query behavior first. Add a transient/object cache only when profiling identifies a repeated expensive query, and put invalidation in the service that owns that cache.

Prohibited preemptive patterns:

- manual `alloptions` / `notoptions` cache deletion;
- custom global cache-purge hooks for every post/meta update;
- cache schema/version options before a cache exists;
- duplicate caches of Woo product data.

## 14. Upgrade policy

Do not design a migration platform before there is a released Gloskin schema to migrate.

When a future release needs an upgrade:

- use one plugin schema/version option;
- run a narrowly scoped idempotent upgrade;
- gate destructive work behind explicit admin intent/backups if needed;
- remove temporary migration payloads after the supported upgrade window when safe;
- do not build a generic admin migration console unless multiple real migrations prove it necessary.

## 15. Complexity budgets

These are architecture guardrails, not arbitrary style preferences:

- exactly one composition root/kernel;
- at most eight first-party bootable services in v1;
- one canonical asset owner and registry;
- one canonical navigation data tree per request;
- at most one small global Gloskin settings option;
- zero custom database tables in v1;
- zero generic runtime migration frameworks in v1;
- zero historical Morgen repair/reconciliation services;
- zero UI version switchers;
- zero custom product/cart/checkout/order/payment ownership;
- zero custom mail/form submission backend;
- zero manual WordPress option-cache surgery by default;
- zero routine read-after-write verification/rollback wrappers for standard WP API saves;
- zero compatibility aliases/no-op compatibility classes at first release;
- zero scattered integration availability checks inside templates.

A service approaching a large multi-domain class should be split by ownership before more features are added. Prefer narrow files and pure helpers over another central “system” class.

## 16. Low-effort / high-impact implementation order

### P0 — architecture guardrails

- create Kernel and explicit service list;
- enforce namespace/prefix ownership;
- create static exclusion scan;
- establish one asset owner;
- establish native content model and route ownership.

### P0 — remove unnecessary infrastructure before it exists

Do not implement:

- migration console;
- diagnosis archive;
- telemetry;
- custom settings transaction engine;
- custom nav persistence;
- custom form/mail backend;
- virtual route/proxy-page engine;
- manual option cache safety;
- multi-version presentation engine.

### P1 — presentation value

- shell/header/footer/nav;
- page-family contexts/templates;
- design tokens;
- treatments/clinics/doctors;
- responsive/accessibility behavior.

### P1 — integrations

- WooCommerceAdapter;
- FormAdapter.

### P2 — editor polish

Only add custom editor/admin UX where native WordPress editing is genuinely inadequate.

### P3 — operational extras

Caching, advanced diagnostics, background processing, additional security middleware or upgrade tooling are justified by evidence, not by Morgen parity.

## 17. Static architecture checks for future implementation

The production plugin should be scanned for prohibited accidental architecture, including:

- `Morgen_Core_`, `morgen_core_`, `morgen-ui6-`, `mg6-` runtime identifiers;
- `CASE-PROD`, historical `PROD-` migration IDs;
- Technical Library/Documents/PDF/download infrastructure;
- Application/Hammer/Quality Testing domain code;
- custom inquiry/mail classes;
- V1-V5/version switch logic;
- custom virtual route/proxy-page ownership;
- public unauthenticated AJAX handlers;
- direct `$wpdb` writes/custom table creation;
- manual `alloptions`/`notoptions` invalidation;
- duplicated asset enqueue owners;
- generic migration/reconciliation classes.

Historical references in Markdown/audit comments are allowed.

## 18. Decision rule for adding infrastructure

Treatment Finder closure stays within the existing owners: ContentService retains the private path/concern/question schema, TemplateService projects valid paths/concerns and mapped SSR Woo products, the existing WooCommerceAdapter remains the sole product-query boundary, and the conditionally loaded consultation asset owns request-local interaction only. It adds no service, custom table, public endpoint, fetch/AJAX path, browser persistence or duplicate commerce controller; private questions remain admin data rather than a public readiness dependency.

Before adding a new framework/service/hardening layer, the developer must be able to answer:

1. Which current Gloskin requirement needs it?
2. Which existing owner cannot reasonably handle it?
3. What failure is being prevented and has that failure actually been observed or is it inherent to the feature?
4. Why does a WordPress/WooCommerce native primitive not solve it?
5. What is the deletion path if the feature is later removed?

If those answers are weak, do not add the infrastructure.

## 19. Definition of architecture done

The architecture foundation is successful when a new developer can trace every runtime responsibility from Kernel to one owner without learning Morgen's production history; a normal content save uses native WordPress persistence without custom transaction choreography; a normal frontend request loads only relevant services/assets; WooCommerce and the form provider remain isolated adapters; and removing an optional feature requires deleting one service/asset/template path rather than repairing a network of compatibility hooks.
