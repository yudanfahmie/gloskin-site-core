# SEO/GEO Engineering Contract

## 1. Scope clarification

Gloskin Site Core has always been developer-only. This document makes one boundary explicit:

**SEO/GEO operations are excluded; SEO/GEO-friendly engineering is required.**

The plugin is responsible for producing a semantic, crawlable, stable, accessible and performant website presentation that normal search engines and modern answer systems can understand through standard web mechanisms.

The plugin is not responsible for keyword campaigns, backlinks, recurring ranking work, content calendars, GSC/GA4/GBP operations, media placement, social campaigns or SEO reporting retainers.

When older canonical docs say SEO/GEO/schema administration is excluded, interpret that as excluding **operational/admin ownership**, not excluding technical web quality.

## 2. Structural baseline

Every intended indexable page family should provide:

- server-rendered primary content;
- one clear primary topic/H1;
- logical heading hierarchy;
- semantic page landmarks;
- real `<a href>` navigation for important destinations;
- stable WordPress/WooCommerce routes;
- meaningful parent/hub/detail relationships;
- accessible breadcrumb capability on deep content;
- useful visible content rather than crawler-only blocks;
- graceful empty states rather than fabricated copy.

JavaScript may enhance presentation and interaction but must not be the only mechanism exposing primary content or critical internal links.

## 3. Route and canonical behavior

- Prefer native WordPress Pages, Posts, CPT rewrites and WooCommerce routes already defined by the product contract.
- One content entity should have one intentional public canonical route.
- Do not add alternate UI-version routes or duplicate indexable views for presentation experiments.
- Internal links should point directly to canonical final routes.
- Pagination/filter behavior must not accidentally create uncontrolled duplicate indexable surfaces.
- Do not output a plugin canonical tag when an authoritative SEO provider or WordPress already owns it.

This contract does not authorize a custom virtual routing layer.

## 4. Metadata and schema ownership

Goal: **one authoritative output owner**, not maximum markup.

When Rank Math, Yoast or another approved provider owns metadata/schema/sitemaps, Gloskin Site Core must integrate through supported WordPress/provider mechanisms and avoid competing output.

Do not create a duplicate:

- title owner;
- meta-description owner;
- canonical owner;
- robots-meta owner;
- Open Graph/Twitter metadata set;
- sitemap engine;
- JSON-LD graph.

WooCommerce remains authoritative for commerce/product data and its supported product-schema integration.

Gloskin’s responsibility is clean semantic source content, stable relationships and provider-compatible data/markup.

## 5. Semantic page-family requirements

### Homepage and hubs

- one clear page H1;
- crawlable links to important Treatments, Skincare, Clinics, Doctors, Shop and Insights destinations;
- sections use meaningful headings rather than visual-only heading levels;
- avoid hard-coded repetitive keyword paragraphs.

### Treatments

- category/title is represented semantically;
- approved benefits/contraindications/content render as visible content only when supplied;
- related clinics/doctors use real links;
- no generated medical efficacy or SEO claim copy.

### Clinics and doctors

- names, branch relationships and approved factual details remain visible/semantic where supplied;
- missing NAP, SIP, hours or credentials are not invented;
- entity relationships are represented by normal internal links;
- provider-owned structured data may consume these facts without Gloskin creating a competing graph.

### Skincare/WooCommerce

- WooCommerce remains the product/category authority;
- product/category names, descriptions, attributes and links stay native/provider-compatible;
- no parallel product schema or duplicate product-content store;
- category/product discovery is crawlable through normal anchors and native pagination where present.

### Insights

- native Posts remain article authority;
- article titles/content/featured media/categories use semantic WordPress output;
- no custom SEO-copy production workflow in the plugin.

## 6. Internal linking and breadcrumbs

The information architecture should remain legible to users and crawlers:

- Home → major hubs;
- hub → detail/category pages;
- treatment → explicitly related clinic/doctor where configured;
- clinic → explicitly related doctors/treatments where configured;
- doctor → explicitly related branches/treatments where configured;
- skincare landing → mapped Woo category/products;
- Insights → Posts and editorially configured relevant destinations.

Do not create invisible keyword link clouds.

Deep pages should support one accessible breadcrumb system. If the SEO provider owns breadcrumb output/data, integrate or yield to it instead of printing a duplicate breadcrumb system.

## 7. GEO readiness

For this repository, GEO readiness means making approved public information easy to extract through standard visible web structure. It does not mean hiding content for AI crawlers or guaranteeing inclusion in generated answers.

Engineering principles:

- clear page/entity titles;
- concise approved summaries where content supplies them;
- explicit relationships between treatment, clinic, doctor and product surfaces;
- semantic facts/attributes;
- stable entity/detail URLs;
- consistent approved contact/branch facts;
- descriptive headings;
- visible FAQs only when real approved FAQ content exists;
- no fake citations, invented claims or crawler-specific hidden blocks.

Do not add speculative `llms.txt`, vector feeds, “AI schema”, crawler cloaking or answer-engine endpoints as baseline work without a separate evidence-based requirement.

## 8. Images and media

- WordPress Media Library data is authoritative for factual media and alt text;
- use responsive WordPress image APIs where practical;
- provide dimensions/aspect ratio to reduce layout shift;
- lazy-load non-critical images;
- do not blindly lazy-load likely LCP imagery;
- decorative images may have empty alt;
- do not synthesize product/doctor/clinic/medical claims from filenames for alt text;
- staging stock media must follow `CONTRIBUTING.md` and never impersonate a real doctor, branch, product or medical result.

## 9. Performance/Core Web Vitals

SEO-friendly engineering includes performance discipline:

- one asset owner;
- conditional CSS/JS loading;
- no excluded Morgen feature bundles globally enqueued;
- server-rendered primary content;
- responsive media and reserved layout space;
- minimal client-side hydration;
- no duplicate copies of libraries already owned by WordPress/WooCommerce unless required;
- pagination rather than unbounded archive payloads;
- sticky header/navigation behavior must not create avoidable layout shift.

Measure representative staging pages; do not add speculative caches/frameworks merely to chase a synthetic score.

## 10. Accessibility overlap

Technical SEO/GEO does not replace accessibility, but good semantic engineering supports both.

Preserve:

- keyboard-accessible navigation/disclosures;
- visible focus;
- meaningful link/button names;
- valid labels/errors on forms;
- reduced-motion support;
- no hover-only critical interaction;
- logical landmarks/headings.

## 11. Content hygiene

Templates must never leak:

- keyword target notes;
- “Meta Title”/“Meta Description” editor labels;
- prompts/internal instructions;
- migration markers;
- staging-only copy;
- hidden repeated SEO paragraphs;
- fabricated medical or commercial claims.

Content governance remains outside plugin tooling, but the presentation layer must not convert control data into public body content.

## 12. Definition of done

A public page-family implementation is not complete until representative pages show:

- server-rendered primary content;
- one intended H1 and logical hierarchy;
- crawlable important internal links;
- stable canonical route behavior;
- one breadcrumb owner where used;
- no duplicate canonical/meta/schema/sitemap owner;
- meaningful media semantics and stable layout;
- native WordPress/Woo data ownership;
- accessible responsive behavior;
- no invented or crawler-only SEO/GEO content.

These are engineering acceptance criteria. Ranking outcomes and SEO campaign performance remain outside repository scope.