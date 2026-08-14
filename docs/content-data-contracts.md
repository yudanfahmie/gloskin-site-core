# Content and Data Contracts

This document defines the developer-facing data contract already normalized from the Gloskin project requirements. It is not a content-writing brief and it does not authorize invented client data.

## General rules

- Use WordPress/WooCommerce-native storage before custom infrastructure.
- No custom database table is required for v1.
- Every user-editable write must use capability checks, nonces and field-appropriate sanitization.
- Store relationships using stable WordPress object IDs or another WordPress-native mechanism.
- Frontend templates must tolerate missing optional fields.
- Do not populate production records with guessed addresses, credentials, medical claims, prices, BPOM numbers or contact details.

## Treatment record

Recommended content type: `gloskin_treatment`.

Cardinality: exactly eight approved category records are required for the launch architecture.

Required/editable fields:

| Field | Type | Requirement | Frontend behavior |
| --- | --- | --- | --- |
| title | core post title | required before publish | category/page heading |
| slug | core post slug | required before publish | `/treatments/{slug}/` |
| summary | text/textarea | recommended | card and intro copy |
| description | rich text | required content input | main treatment overview |
| benefits | structured text/rich text | required content input | benefits section |
| contraindications | structured text/rich text | required content input | contraindications section |
| featured_media_id | attachment ID | optional until asset supplied | hero/featured visual |
| clinic_ids | list of clinic post IDs | optional relationship | related clinics |
| doctor_ids | list of doctor post IDs | optional relationship | related doctors |
| booking_target | URL/branch-aware action | optional override | CTA target |

Foundation storage mapping in `0.1.0`:

- `description` uses core `post_content`;
- `featured_media_id` uses the core featured-image attachment (`_thumbnail_id`), not duplicate Gloskin media meta;
- `summary`, `benefits`, `contraindications`, `clinic_ids`, `doctor_ids` and `booking_target` use registered Gloskin-prefixed post meta;
- treatment `clinic_ids` and `doctor_ids` are the canonical storage direction for treatment-to-clinic and treatment-to-doctor relationships.

Do not generate medical claim text in code.

### Taxonomy ambiguity already resolved for engineering

The project requires eight approved categories, but the available onboarding draft listed more than eight service labels:

`Facial`, `Peeling`, `Barrier Reset`, `Quality Repair`, `Face Contour`, `Infusion`, `Hair Restoration`, `Body Contour`, `Dermatolift`, `Thread Lift`, `Botox`, `Filler`, `Juvelook`, `Salmon DNA`, `Laser`.

These are not an approved eight-category mapping. The developer must build eight configurable records and wait for approved names/slugs instead of grouping this draft list.

## Clinic record

Recommended content type: `gloskin_clinic`.

Required architecture contains these nine branch identities:

1. Kebayoran Baru — canonical route `/clinics/kebayoran-baru/`
2. Tebet — `/clinics/tebet/`
3. Bekasi — `/clinics/bekasi/`
4. Cibubur — `/clinics/cibubur/`
5. Serpong — `/clinics/serpong/`
6. Surabaya — `/clinics/surabaya/`
7. Banjarmasin — `/clinics/banjarmasin/`
8. Balikpapan — `/clinics/balikpapan/`
9. Denpasar — `/clinics/denpasar/`

Recommended fields:

| Field | Type | Requirement | Notes |
| --- | --- | --- | --- |
| title | core post title | required | branch name |
| slug | core post slug | required | fixed route identity above |
| address | textarea | required content input | NAP address |
| phone_display | text | required content input | human-readable phone |
| phone_uri | normalized tel value | derived/configurable | `tel:` link when valid |
| whatsapp_number | normalized phone | required content input | branch-specific WhatsApp |
| whatsapp_message | text | optional | prefilled message, no marketing automation |
| operating_hours | structured text | required content input | editable hours |
| map_url | URL | optional | external Google Maps link |
| map_embed | validated embed URL/data | required by target page when supplied | do not allow arbitrary unsafe markup |
| gallery_image_ids | attachment ID list | required when assets ready | source expectation: at least 3 branch photos |
| doctor_ids | doctor post ID list | relationship | doctors at branch |
| treatment_ids | treatment post ID list | optional relationship | treatments associated with branch |
| short_location | text | optional | card/intro area label |

Foundation storage mapping in `0.1.0` stores clinic-owned scalar/media fields as registered Gloskin-prefixed post meta. `doctor_ids` is reverse-derived from doctor `branch_ids`, and `treatment_ids` is reverse-derived from treatment `clinic_ids`; the clinic record does not duplicate either relationship list.

NAP means the public branch Name/Address/Phone representation must stay internally consistent wherever displayed.

### Clinic fallbacks

- Missing gallery: render a deliberate placeholder/container or omit gallery; never broken images.
- Missing map: show address/contact without broken iframe.
- Missing WhatsApp: hide the WhatsApp action rather than using a fake number.
- Missing hours: omit or display an explicit content-pending state only in non-production/editor contexts.

## Doctor record

Recommended content type: `gloskin_doctor`.

Cardinality: thirteen doctor records are required by the site architecture. Actual doctor identities and data are pending content inputs unless already entered into WordPress.

Recommended fields:

| Field | Type | Requirement | Notes |
| --- | --- | --- | --- |
| title/full_name | core post title | required | full professional name |
| slug | core post slug | required | `/doctors/{slug}/` |
| degree_title | text | required content input | title/degree |
| specialization | text | required content input | specialty |
| portrait_id | attachment ID | required when asset supplied | professional photo |
| branch_ids | clinic post ID list | relationship | one or more practice branches |
| treatment_ids | treatment post ID list | optional relationship | related treatment expertise/presentation only |
| sip_number | text | optional | only when supplied/approved |
| credentials | rich text | content input | credentials/profile |
| profile | rich text | content input | biography/profile if separate |
| schedule | structured text | optional | only if approved schedule data is available |
| booking_target | URL/action | optional override | otherwise derive from branch/contact system |

Foundation storage mapping in `0.1.0` uses the core featured-image attachment (`_thumbnail_id`) for `portrait_id`. Doctor `branch_ids` is the canonical doctor-to-clinic relationship. Doctor `treatment_ids` is reverse-derived from treatment `doctor_ids` and is not duplicated on the doctor record.

Do not infer credentials, SIP numbers, practice schedules or specialties.

## Skincare landing mapping

These seven landing concepts are provisional but developer-complete as a mapping contract:

| Provisional label | Proposed landing slug | WooCommerce mapping |
| --- | --- | --- |
| Facial Wash | `facial-wash` | configurable product category slug/term ID |
| Day Cream/Sunscreen | `day-cream-sunscreen` | configurable product category slug/term ID |
| Toner | `toner` | configurable product category slug/term ID |
| Serum | `serum` | configurable product category slug/term ID |
| Acne Care | `acne-care` | configurable product category slug/term ID |
| Anti-Aging | `anti-aging` | configurable product category slug/term ID |
| Brightening & Pigmentation Care | `brightening-pigmentation-care` | configurable product category slug/term ID |

Recommended landing fields:

- page title;
- page slug;
- intro/description;
- featured media, optional;
- WooCommerce category term ID or validated slug;
- optional section/CTA copy.

Product records do not belong to this mapping object.

## WooCommerce product contract

WooCommerce is authoritative.

The normalized project inputs explicitly require support for up to twenty products and identify at least:

- product name;
- SKU;
- price;
- BPOM number;
- composition;
- usage instructions;
- product images, with a source expectation of at least three angles when available.

Use WooCommerce standard fields first. BPOM/composition/usage may be WooCommerce attributes or product metadata according to the target site's agreed data model. The plugin only reads and presents them.

The project framework references a uniform twelve-field product template but does not provide twelve reliable field definitions in the normalized developer input. Therefore:

- do not invent additional fields;
- do not build a custom twelve-field product database;
- make the product presentation extensible enough to render approved Woo attributes/meta later;
- preserve standard WooCommerce hooks where possible so gateway/product extensions remain compatible.

## Treatment Consultation data contract

Treatment Consultation is additive to the existing informational `gloskin_treatment` directory. It does not replace or repurpose those eight records.

Canonical ownership is:

| Entity | Canonical owner/purpose |
| --- | --- |
| purchasable treatment | WooCommerce `product` only |
| `gloskin_product_family` | private classification vocabulary; stable terms include `skincare` and `treatment` |
| `gloskin_concern` | private recommendation-mapping vocabulary |
| `gloskin_consultation_path` | private questionnaire-path vocabulary |
| `gloskin_question` | private/non-public questionnaire entity; native admin edit capability only |
| product ↔ concern | native taxonomy relationship via `wp_set_object_terms()`; sole recommendation mapping source |
| question answers | registered bounded question meta defining label + concern ID + weight 1..3 |
| visitor answers/scores/history | frontend runtime memory only |

The three consultation taxonomies are registered against their canonical object-type slugs without requiring WooCommerce's `product` post type to have registered first. This is intentional: WordPress taxonomy registration can precede the corresponding object-type registration, and the schema must remain deterministic regardless of plugin load order.

The admin Pemetaan Produk screen may progressively enhance the native checkbox matrix into a searchable Treatment Product pool plus concern buckets/chips. That JavaScript is presentation/state synchronization only: the same native checkboxes remain the submitted canonical relationships and the no-JS fallback. Do not add JSON mapping options, product-meta mapping arrays, custom tables, browser persistence, or another recommendation store.

The questionnaire runtime may shuffle eligible questions once per consultation run and score concerns client-side. Answers, score history, and path progress must not be written to localStorage, sessionStorage, cookies, options, post meta, custom tables, or another server-side store. Restart clears the run; Back reverses the prior answer's score contribution. Recommendation output reuses the existing canonical Woo product-card/add-to-cart runtime and is capped at eight cards.

The optional demo importer is staging/development/local only. It uses deterministic path/concern slugs, question source IDs, and treatment-product SKUs so every upsert phase converges after a partial rerun. Production must refuse the import. The demo target is at least four paths, ten concerns, thirteen questions, and exactly eight Woo Treatment Products; demo products remain distinct from the existing eight informational `gloskin_treatment` records.

## About content contract

The About page must be able to render:

- company overview;
- Vision;
- Mission;
- Values;
- clinic network summary;
- team/doctor teaser.

The source material referenced long Indonesian and English company descriptions. Current engineering scope does not define multilingual routing. Store approved copy through normal WordPress content/meta or a minimal settings structure; do not port Morgen EN/DE routing.

## Homepage content contract

The homepage requires editable/renderable data for:

- hero heading/copy/media/CTA;
- treatment discovery selection/order;
- clinic discovery selection/order;
- doctor preview selection/order;
- skincare/shop preview configuration or Woo query parameters;
- Insights preview configuration or standard recent-post query;
- booking/contact CTA.

Avoid a huge all-in-one serialized option if native Page content, block data or small registered meta fields are simpler. If plugin-owned structured settings are used, version and sanitize them explicitly.

The current Home presentation intentionally uses the existing shared hero/video owner in video-only mode: no visible eyebrow/heading/copy/CTA, a native background `<video>` sourced from a WordPress Media Library attachment (muted, autoplay, loop, playsinline, true `object-fit:cover`), a pure-white preparing state with a small non-interactive loader until real playback readiness is proven, bottom gradient, one SVG scroll cue to the actual following section, and reduced-motion handling (established/paused static frame, no repeated loader motion). No YouTube iframe or poster/facade chrome renders on this surface. Do not create a second Home hero/video service.

## Contact and booking contract

Global/branch contact presentation should support:

- branch name;
- address;
- phone;
- WhatsApp;
- operating hours;
- optional booking CTA URL/action;
- configured form shortcode/block reference.

Gloskin Site Core does not own mail settings, recipient routing, auto-replies or submission deduplication.

For WhatsApp links:

- store a normalized international number separate from display formatting;
- URL-encode any optional prefilled message;
- use safe external-link attributes when opening a new tab;
- never hard-code a generic number when branch-specific data is required.

## Maps contract

The clinic page requires Google Maps presentation.

Prefer a validated map/embed URL or structured map configuration rather than storing arbitrary iframe HTML. If raw embed HTML must be supported, restrict it with a strict allowlist. Missing map data must not break the page.

## Insights contract

Source: native WordPress posts.

Gloskin may define presentation behavior such as:

- featured image;
- title;
- excerpt;
- publication date;
- permalink;
- optional category display.

Do not create a duplicate `gloskin_insight` CPT unless a later functional requirement demands it.

## Relationship editing

The v0.1.0 foundation fixes one canonical persistence direction per many-to-many relationship:

- clinic ↔ doctor: doctor `branch_ids` stores clinic post IDs;
- clinic ↔ treatment: treatment `clinic_ids` stores clinic post IDs;
- doctor ↔ treatment: treatment `doctor_ids` stores doctor post IDs.

Reverse relationships are derived by querying the canonical meta key. Do not independently persist clinic `doctor_ids`, clinic `treatment_ids`, or doctor `treatment_ids`; that would introduce contradictory dual-write state and reconciliation work.

Future admin UI may expose relationship choices from either side, but any write must update the same canonical owner above unless profiling later justifies a documented denormalization change.

## Content readiness states

The implementation must distinguish:

- **required architecture**: page/content type/field exists;
- **required launch content**: value must be provided before production launch;
- **optional content**: section can be omitted;
- **pending content**: architecture exists but no value should be invented.

Developer placeholders must be visibly non-production or neutral. Never convert assumptions from old Morgen content into Gloskin defaults.

## No raw-project dependency

All developer-relevant content fields and known normalized values are captured here and in `docs/developer-source-of-truth.md` / `docs/page-matrix.csv`.

If a future implementation needs a value not listed here, that value is a new/pending client input. Ask for it or expose an editable field; do not reopen the raw `project-9901` material as a normal discovery step.
