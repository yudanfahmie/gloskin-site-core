# Shop Discovery Filters, Contact Operations & Doctor Data Migration — Implementation Plan

Status: implementation-ready audit/research package  
Scope: `gloskin-site-core` only  
Audited baseline: `main` at `dd0ad302a634f6322cf598a63c79c89ca4fb7fe4`, runtime `0.7.114`  
Research date: 2026-08-17  

## 1. Objective

Ship the next low-effort/high-impact vertical slice without introducing duplicate owners:

1. extend the existing Gloskin Shop AJAX catalog with text search and price-range filtering in the existing left discovery rail;
2. replace the optional external-shortcode Contact surface with a first-party professional Gloskin contact workflow, including private inbox, recipient routing, SMTP transport, auto reply, reCAPTCHA v2 checkbox, and test-email/readiness UI;
3. seed the existing `gloskin_doctor` model from currently verifiable official Gloskin doctor data through a deterministic one-shot migration.

The implementation must preserve the owners already established by the plugin: WooCommerce remains product/catalog truth, WordPress remains content/storage truth, the existing Gloskin Template/Asset/Admin services remain presentation/admin owners, and imported doctor facts must come from authoritative sources rather than synthetic completion.

---

## 2. Deep audit findings

### 2.1 Shop already has the correct AJAX skeleton

`templates/pages/shop.php` already renders one catalog root, one left category navigation, one results column, one results partial, and an `aria-live` status owner.

`assets/js/gloskin-ui1-core.js` already owns Shop filtering/navigation through the existing `data-gloskin-shop-catalog` runtime and one public REST endpoint:

`GET /wp-json/gloskin/v1/shop/catalog?category=...&page=...`

It already has request sequencing/stale-response protection, `AbortController`, busy state, history/hash synchronization, category controls, pagination, retry/fallback behavior, and partial replacement rather than a full document reload.

`Template_Service::rest_shop_catalog()` already validates mapped categories and calls the same Woo adapter used by server-rendered Shop output.

Decision: **extend this exact state machine and endpoint. Do not create a second AJAX endpoint, second catalog renderer, or client-side product store.**

### 2.2 Important existing Woo query constraint

`WooCommerce_Adapter::products_paginated()` currently has two internal paths:

- mapped category: `wc_get_products(... paginate => true ...)`;
- unfiltered “Semua Produk”: a deliberate fallback path using a bounded WordPress product projection because the repository documents a live REST fatal in the unfiltered paginated Woo query shape.

This is proven existing compatibility code and must not be casually removed just to simplify the new filters.

Decision: expose one expanded adapter contract for category/search/price, but preserve the proven unfiltered fallback until a focused test demonstrates it can safely be retired.

WooCommerce’s current official developer guidance still recommends `wc_get_products()` / `WC_Product_Query` as the stable product-query API. Woo core itself implements catalog min/max price semantics against the product price lookup range, so the new Gloskin filter must match Woo’s *range overlap* semantics rather than treating variable products as one arbitrary scalar price.

### 2.3 Contact currently has no first-party operational workflow

`Form_Adapter` only resolves an administrator-configured shortcode and delegates rendering to an external provider. It owns no submission, storage, mail, SMTP, CAPTCHA, auto reply, or inbox.

`templates/pages/contact.php` only renders the “Kirim Pesan” section when that shortcode successfully resolves. Therefore Contact currently has no guaranteed form.

`Admin_Service::settings_defaults()` only contains:

- design variant;
- legacy form shortcode;
- header variant;
- home hero video attachment ID.

Decision: **Gloskin becomes the canonical owner of its Contact form.** The legacy `form_shortcode` setting may remain temporarily for backward compatibility/migration visibility, but it must not render simultaneously with the new native form and must not remain the canonical Contact implementation.

### 2.4 Morgen Core reference is useful as UI language, not as a dependency

Reference repository: `yudanfahmie/morgen-core`.

Relevant admin implementation:

- `morgen-plugin/templates/admin/partial-sidebar.php`
- `morgen-plugin/templates/admin/page-misc.php`
- `morgen-plugin/templates/admin/page-inquiries.php`

Morgen’s current admin language provides the useful patterns to adopt:

- clear sidebar sections with counts/badges;
- one Settings destination with discoverable sub-sections/tabs;
- fieldsets/cards with short help copy;
- switches, structured fields and inline status feedback;
- a dedicated Inquiries table with search, status and pagination;
- content settings with explicit inquiry recipient;
- a dedicated Form Security settings section.

Decision: **adopt this information architecture and interaction quality in Gloskin’s own admin CSS/markup; do not copy Morgen classes, dependencies, global admin shell, database model, or business logic.**

### 2.5 Doctor model already exists and already targets 13 records

`Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE` is already `gloskin_doctor`, and `DOCTOR_TARGET_COUNT` is already `13`.

Existing doctor payload supports:

- native title/slug/content/featured image;
- `gloskin_degree_title`;
- `gloskin_specialization`;
- `gloskin_branch_ids`;
- `gloskin_sip_number`;
- `gloskin_credentials`;
- `gloskin_profile`;
- `gloskin_schedule`;
- `gloskin_booking_target`.

Decision: **do not create another doctor CPT or schema. Seed this model only.**

---

## 3. External research conclusions

### 3.1 WooCommerce query behavior

Current official WooCommerce docs state that `wc_get_products()` / `WC_Product_Query` are the supported product retrieval layer and support pagination/category/product query extension. Woo core’s catalog query accepts `min_price` / `max_price` and filters using its product price lookup range.

Implementation consequence:

- preserve Woo ownership;
- do not query a shadow product table;
- do not maintain a JS product cache as truth;
- variable products must match a requested price interval when their purchasable price range intersects it;
- price values sent over the REST endpoint are decimal store values, not localized formatted strings.

### 3.2 reCAPTCHA v2 checkbox

Google’s current official reCAPTCHA documentation still lists **reCAPTCHA v2 “I’m not a robot” Checkbox** as a supported type. The widget uses a site key client-side and its response token must be verified server-side through the `siteverify` endpoint. Response tokens are short-lived and single-use.

Implementation consequence:

- load reCAPTCHA only on Contact when enabled;
- render the standard checkbox widget;
- verify server-side before accepting the public submission;
- never store/log the response token;
- an enabled but incomplete reCAPTCHA configuration must fail closed and surface an admin readiness warning.

### 3.3 WordPress mail transport

WordPress `wp_mail()` is the normal mail API, while `phpmailer_init` exposes the underlying PHPMailer instance for SMTP configuration. `wp_mail()` returning `true` means the transport accepted the send attempt, not guaranteed human delivery.

Implementation consequence:

- report “sent/accepted by mail transport”, never “delivered”;
- keep SMTP behavior scoped to Gloskin contact/test mail by default rather than silently modifying all WordPress mail;
- visitor email is `Reply-To`, never the SMTP `From` identity;
- persist the inquiry before any outbound mail attempt.

### 3.4 Indonesian privacy boundary

UU No. 27 Tahun 2022 on Pelindungan Data Pribadi classifies health information as specific personal data. The Contact form should therefore be deliberately minimal and should not become a clinical-history/medical-record intake form.

Implementation consequence:

- no diagnosis/medical-history fields;
- no photo/document attachment in v1;
- clear copy that the form is for general contact and does not replace doctor consultation or emergency care;
- consent/privacy acknowledgement;
- bounded retention/admin deletion capability;
- no raw IP storage unless a later documented need exists; abuse throttling may use a one-way request fingerprint/hash with short retention.

### 3.5 Official doctor source — exactly 13 current public roster entries

Authoritative source reviewed:

- Gloskin official site home/team section (`gloskin.id`);
- Gloskin official “Founder & dokter Gloskin” page (`gloskin.id/founder-and-dokter-gloskin`).

The current official site publicly exposes a 13-doctor roster matching the codebase target count:

1. dr. Nanang Masrani, M.Biomed (AAM)
2. dr. Cyintia Musius
3. dr. Desy Pustika Sari
4. dr. Diah Pasaribu
5. dr. Arwina Sufika
6. dr. Lorentina Aira Syaharani
7. dr. Ni Nyoman Ayu Laksmi Trimurti, M.Biomed (AAM)
8. dr. Visca Pakarti Suhardi
9. dr. Vindi Nazhifa
10. dr. Prissilma Tania Jonardi, Sp.DVE
11. dr. Oqti Rodia, Sp.GK
12. dr. Mega Carkaninba
13. dr. Maria Magdalena br. Manik

The official source also publishes practice-area/specialization copy for the roster and richer profiles/training for a subset/all entries on the doctor page.

However, the public sources reviewed do **not** reliably expose SIP number, individual clinic schedule, or clinic/branch assignment for all 13 doctors.

Hard rule: those unsupported fields stay blank unless a second authoritative Gloskin source is explicitly added to the bundle. Never invent SIP, schedule, branch, credentials, or doctor identity data to make a card look complete.

---

## 4. Shop implementation contract

### 4.1 UX composition

Keep the current two-column Shop composition. Expand the existing left discovery area in this order:

1. **Cari Produk**
   - native `<input type="search">`;
   - placeholder e.g. `Cari skincare…`;
   - visible label;
   - clear/reset affordance when non-empty.

2. **Rentang Harga**
   - `Harga minimum` numeric/text currency-aware input;
   - `Harga maksimum` numeric/text currency-aware input;
   - small `Terapkan` action;
   - `Reset harga` text action when active.

3. existing **Kategori** list.

Do not add a dual-range-slider dependency. Two bounded fields are lower risk, keyboard friendly, mobile friendly, and much cheaper to maintain.

### 4.2 State

Expand the current Shop state from:

```text
category
page
```

to:

```text
category
q
min_price
max_price
page
```

Rules:

- changing category/search/price resets `page = 1`;
- pagination preserves all active filters;
- search debounces ~300–350 ms;
- Enter/submitting search executes immediately;
- price range applies on button/Enter, not on every digit;
- `min_price > max_price` is normalized safely (prefer validation message; do not silently produce an impossible query);
- empty values mean no constraint;
- no negative prices;
- apply practical numeric bounds and max input length.

### 4.3 URL/history

Preserve the existing history/hash model so back/forward restores filter state without full reload.

Recommended compact hash keys:

```text
#category=serum&q=brightening&min_price=100000&max_price=500000&page=2
```

Only serialize non-default state.

Do not create canonical/SEO metadata ownership for filtered AJAX states.

### 4.4 REST contract

Extend the same route:

```text
GET /wp-json/gloskin/v1/shop/catalog
```

with validated optional args:

```text
page
category
q
min_price
max_price
```

Return the same server-rendered results partial/response shape used now.

No second endpoint.
No authenticated nonce for this guest-readable GET projection.
No public mutation.

### 4.5 Query ownership

Extend the existing Woo adapter rather than querying products in Template Service or JS.

Recommended normalized adapter shape:

```php
products_paginated( $page, $per_page, $filters )
```

or an equivalent backwards-compatible expansion where `$filters` contains category/search/min/max.

Implementation must preserve the current unfiltered REST fallback documented in the adapter until focused tests prove an alternative stable.

Search should use the Woo/WordPress product title/search projection already used by this adapter where appropriate; no client-side filtering of only the current page.

Price filtering must use Woo-compatible current-price/range semantics. For variable products, filter against the product price range rather than only one parent `_price` scalar.

If a scoped Woo product-query extension hook is needed, register it only for the duration/identity of this adapter query and remove it immediately afterward. Do not add a global `pre_get_posts` filter for Gloskin Shop.

### 4.6 AJAX behavior

Preserve:

- one request owner;
- `AbortController` stale cancellation;
- request sequence guard;
- `aria-busy`;
- live result-count announcement;
- current category/focus semantics;
- server-rendered cards;
- shared empty state;
- retry behavior.

Filtered zero results should say something useful such as:

> Tidak ada produk yang cocok dengan filter ini.

with a clear filters action, while Woo-unavailable remains a distinct readiness state.

---

## 5. Native Contact form

### 5.1 Public fields

Professional clinic-style general enquiry form:

Required:

- Nama lengkap
- Email
- Nomor WhatsApp / telepon
- Topik
- Pesan
- privacy/consent checkbox

Optional:

- Klinik pilihan (from published `gloskin_clinic` records)

Topic allowlist:

- Konsultasi / Perawatan
- Skincare / Produk
- Klinik / Janji Temu
- Kerja Sama
- Masukan / Lainnya

Do not request:

- diagnosis;
- medical history;
- KTP/NIK;
- date of birth;
- medical image;
- prescription;
- attachment.

Microcopy must clearly state that this is a general contact channel, not an emergency service and not a substitute for examination/consultation by a doctor.

### 5.2 Submission lifecycle

Use a narrow first-party submission owner. A normal `admin-post.php` action with both authenticated and `admin_post_nopriv_*` handlers is appropriate; AJAX submission is optional presentation polish, not required for correctness.

Required sequence:

```text
POST
→ capability-independent public validation
→ nonce/intention check
→ honeypot/timing/rate guard
→ reCAPTCHA server verify when enabled
→ sanitize + bound payload
→ persist inbox record
→ send staff notification
→ send visitor auto reply if enabled
→ update delivery attempt states
→ PRG redirect to success/error state
```

**Persist before mail.** A temporary SMTP failure must never erase the visitor inquiry.

Public error copy must remain generic. Detailed mail/transport errors are admin-only and must not expose credentials/server details.

### 5.3 Private inbox model

Use a private WordPress CPT, e.g.:

```text
gloskin_contact_message
```

Contract:

```text
public              false
publicly_queryable  false
show_ui             true or Gloskin-owned admin screen
show_in_rest        false
rewrite             false
exclude_from_search true
```

No custom SQL table.

Store bounded meta:

```text
name
email
phone
topic
clinic_id
message
status: new|read|resolved|spam
staff_mail_status: pending|accepted|failed
autoreply_status: disabled|pending|accepted|failed
mail_error_code / safe admin summary
source_path
created_at (native post date is acceptable)
optional short-lived one-way abuse fingerprint
```

Never store:

```text
SMTP password
reCAPTCHA response token
raw PHPMailer debug transcript
raw IP unless separately justified
```

### 5.4 Inbox admin UX

Add `Kotak Masuk` under `Gloskin Content`.

Adopt Morgen’s `page-inquiries.php` interaction language:

- page title + concise lede;
- badge with unread/new count;
- search sender/email/topic;
- status filters;
- columns: Pengirim, Topik, Klinik, Status, Diterima;
- pagination;
- detail view with message and mail-attempt state;
- explicit status actions New/Read/Resolved/Spam;
- safe deletion only with capability + nonce.

No CRM pipeline, chat, internal notes, assignment system, or reply composer in v1.

---

## 6. Contact & Email admin settings

### 6.1 Information architecture

Keep the existing Gloskin Settings destination and its asset owner. Evolve its presentation toward the Morgen admin pattern rather than adding another top-level settings plugin.

Recommended Gloskin Settings sections/tabs:

```text
General
Contact & Email
```

`Contact & Email` contains five cards/fieldsets:

1. Form & Destination
2. SMTP Transport
3. Auto Reply
4. reCAPTCHA v2
5. Email Test & Readiness

The inbox remains a separate operational submenu because it is a record list, not configuration.

### 6.2 Settings storage

Prefer a dedicated non-autoloaded option such as:

```text
gloskin_site_core_contact_settings
```

rather than continuously expanding the general presentation option with credentials.

Sanitize every field through one canonical schema/defaults owner.

### 6.3 Form & Destination

Settings:

```text
form_enabled
recipient_emails[]        max 5, valid emails only
reply_to_behavior         visitor email only
retention_days            bounded, e.g. 30..730
```

Do not let arbitrary visitor input choose a destination address.

Optional topic-routing can be a later feature; v1 should use one bounded recipient list unless there is a concrete operational mapping requirement.

### 6.4 SMTP Transport

Settings:

```text
smtp_enabled
smtp_host
smtp_port
smtp_encryption: tls|ssl|none
smtp_auth_enabled
smtp_username
smtp_password
from_email
from_name
smtp_scope: gloskin_contact|site_wide
```

Default scope:

```text
gloskin_contact
```

Do **not** silently hijack every WordPress email just because SMTP is enabled for Contact.

Safe password UX:

- render masked/empty password field;
- blank save means “preserve current secret”;
- never send current secret back to browser;
- support optional constant override such as `GLOSKIN_SMTP_PASSWORD`;
- if constant is active, UI says “configured externally” and field is disabled/ignored;
- never claim database encryption if the implementation has no environment-held encryption key.

Transport implementation:

- use WordPress/PHPMailer already shipped with WordPress;
- scope `phpmailer_init`/mail filters only around Gloskin-owned sends, or explicitly honor `site_wide` when the admin deliberately chooses it;
- always remove temporary filters/hooks after the send;
- visitor email is `Reply-To`, not `From`.

### 6.5 Auto reply

Settings:

```text
autoreply_enabled
autoreply_subject
autoreply_body
```

Allowed placeholders only:

```text
{name}
{topic}
{site_name}
{message_id}
```

Provide a professional Indonesian default that:

- confirms the message was received;
- says the Gloskin team will review it;
- avoids promising a hard response SLA unless configured operationally;
- does not diagnose or recommend treatment;
- directs urgent medical matters to an appropriate clinic/health service;
- does not echo the visitor’s full free-text message by default.

Use plain text by default or tightly sanitized HTML. No arbitrary executable template syntax.

### 6.6 reCAPTCHA v2 checkbox

Settings:

```text
recaptcha_enabled
recaptcha_site_key
recaptcha_secret_key
```

Safe secret behavior mirrors SMTP password.

Public behavior:

- only enqueue Google reCAPTCHA JS on Contact when enabled and fully configured;
- render normal v2 checkbox;
- verify `g-recaptcha-response` server-side using `wp_remote_post()` to Google `siteverify`;
- bounded timeout;
- require `success=true`;
- validate hostname when appropriate as defense in depth;
- discard token immediately;
- failed verification = reject submission before persistence.

If reCAPTCHA is enabled but either key is missing, mark Contact readiness invalid and fail closed rather than silently dropping CAPTCHA.

### 6.7 Abuse controls

reCAPTCHA is not the only abuse control.

Also include:

- hidden honeypot;
- minimum form-fill timing check;
- per-request fingerprint throttling using a one-way hash/transient with short TTL;
- bounded field lengths;
- no HTML in ordinary text inputs;
- `sanitize_textarea_field()` or equivalent for message;
- no attachments.

Do not block legitimate users solely because JavaScript is disabled unless reCAPTCHA is explicitly enabled (the Google widget itself requires JS).

### 6.8 Test email & readiness

Admin-only test form:

```text
Test destination email
Kirim Email Tes
```

Requirements:

- `manage_options` (or a dedicated appropriately strong capability);
- nonce;
- uses the same Gloskin mail transport service/settings as real contact mail;
- no alternate test-only SMTP code path;
- result differentiates accepted vs failed;
- never prints PHPMailer password/debug trace;
- no statement that inbox delivery is guaranteed.

Readiness panel should show concise statuses:

```text
Form destination      Ready / Needs setup
SMTP                  Ready / Disabled / Invalid
Auto reply            Enabled / Disabled
reCAPTCHA v2          Ready / Disabled / Invalid
Last test             Accepted / Failed / Never
```

---

## 7. Doctor migration package

### 7.1 Canonical entity

Use only:

```text
post_type = gloskin_doctor
```

No new doctor type, table, taxonomy or duplicate profile store.

### 7.2 Source-driven payload

Create a deterministic source bundle, suggested:

```text
migration-source/gloskin-doctors-v1/
  README.md
  manifest.json
  doctors.json
  media.json        only if exact official doctor-image mapping is verified
```

and matching deployable runtime bundle:

```text
plugin/gloskin-site-core/migration-runtime/gloskin-doctors-v1/
```

Every doctor record must include provenance:

```text
source_id
source_url
source_label
source_checked_at
source_display_name
```

Optional fact fields may be populated only where explicitly supported by the cited official source:

```text
post_title
slug
degree_title
specialization
credentials
profile
sip_number
schedule
branch source IDs
booking target
featured media source ID
```

### 7.3 Initial verified roster

Seed the 13 official roster identities listed in section 3.5.

Use official source wording for practice area/specialization and richer profile/training only where it can be unambiguously matched to that doctor.

Current public-source gaps:

```text
SIP number        unsupported for full roster → blank
individual schedule unsupported for full roster → blank
branch assignment unsupported for full roster → blank
photo mapping       import only when exact official mapping is verified
```

Never use generic stock people photography as a factual doctor image.
Never synthesize a SIP number.
Never infer a schedule from clinic operating hours.
Never infer branch membership just because a clinic exists.

### 7.4 Identity/upsert rules

Add explicit migration ownership meta, for example:

```text
_gloskin_doctor_source_id
_gloskin_doctor_bundle_id
_gloskin_doctor_source_url
_gloskin_doctor_source_checked_at
```

Stable `source_id` is the upsert owner.

If an existing unowned `gloskin_doctor` record has the target slug/name:

```text
FAIL/FLAG FOR RECONCILIATION
```

Do not overwrite or delete it automatically.

If a record already has the same migration source ID, update only fields owned by the bundle. Preserve unrelated editor-managed data unless the ownership contract explicitly covers it.

### 7.5 Import lifecycle

Reuse the proven sample-product migration semantics without refactoring all importers into a framework:

```text
fixed runtime path
manifest allowlist
size bounds
SHA-256 checksums
validate all payload before mutation
lock + TTL
explicit Start / Continue
checkpoint one doctor per step
idempotent source-ID upsert
safe media reuse
final verification
persist consumed BEFORE filesystem cleanup
remove only manifest-declared runtime files
cleanup failure does not reopen consumed state
```

Doctor import must never delete unrelated posts or media.

Admin migration surface can live under `Gloskin Content` and disappear after consumed, consistent with the existing one-shot migration pattern.

---

## 8. Implementation structure

Prefer narrow responsibilities rather than a monolith.

Likely touch/add:

### Shop

- `templates/pages/shop.php`
- `templates/parts/shop-results.php` only if filter-summary/reset copy is needed
- `assets/js/gloskin-ui1-core.js`
- Shop CSS in the existing frontend asset owner
- `includes/class-gloskin-site-core-template-service.php`
- `includes/class-gloskin-site-core-woocommerce-adapter.php`
- focused Shop catalog tests/contracts

### Contact

- evolve/replace `class-gloskin-site-core-form-adapter.php` into a first-party form renderer, or introduce one narrow Contact service and retire Form Adapter as runtime owner;
- private Contact-message schema registration in Content Service or a narrow content owner;
- a narrow Contact mail/transport service;
- Contact submission handler;
- `templates/pages/contact.php`;
- `Admin_Service` settings/inbox hooks;
- existing admin CSS/JS owner extended for tabs/cards/inbox/test mail;
- reCAPTCHA loaded only when needed;
- focused security/storage/mail tests.

Avoid one giant “Contact Manager” class that owns rendering, persistence, SMTP, admin and CAPTCHA all at once.

### Doctors

- doctor bundle validator/importer classes following existing migration naming/style;
- source/runtime JSON + manifest;
- temporary admin migration integration;
- focused migration contracts/tests.

---

## 9. Non-goals / explicit prohibitions

Do not add:

- Shop SPA/router;
- second Shop REST endpoint;
- client-side product truth/cache;
- external range-slider library;
- global `pre_get_posts` Shop mutation;
- Contact Form 7 dependency;
- external SMTP plugin dependency;
- a second external form owner rendered alongside native Contact;
- custom SQL inbox table;
- public REST endpoint exposing inbox records;
- attachment upload on Contact v1;
- medical-history intake fields;
- raw reCAPTCHA token logging;
- plaintext secret echoed back into admin HTML;
- hardcoded application encryption key marketed as secure encryption-at-rest;
- invented doctor facts/images;
- generic stock portrait used as a doctor identity;
- migration cleanup that searches/deletes by loose title/slug;
- changes to Rank Math/schema ownership.

---

## 10. Focused acceptance contracts

### Shop

```text
LEFT DISCOVERY OWNER          SAME
CATEGORY FILTER               PRESERVED
SEARCH                        YES
MIN/MAX PRICE                 YES
FILTER COMPOSITION            YES
RESULTS PER PAGE              PRESERVED (current 12)
AJAX ENDPOINT                 SAME /gloskin/v1/shop/catalog
REQUEST OWNER                 ONE
STALE REQUEST ABORT           YES
PAGE RESET ON FILTER CHANGE   YES
BACK/FORWARD STATE            YES
FULL DOCUMENT RELOAD          ZERO FOR FILTERING
SERVER-RENDERED CARDS         YES
CUSTOM RANGE LIBRARY          ZERO
SHADOW PRODUCT CATALOG        ZERO
GLOBAL SHOP QUERY HOOK        ZERO
```

Test at least:

- category only;
- search only;
- min only;
- max only;
- min+max;
- category+search+price;
- pagination with active filters;
- rapid search cancellation;
- invalid range;
- zero results;
- Woo unavailable;
- unfiltered existing compatibility path remains passing.

### Contact

```text
NATIVE GLOSKIN FORM           YES
EXTERNAL SHORTCODE CO-OWNER   ZERO
PRIVATE INBOX                 YES
CUSTOM SQL TABLE              ZERO
PERSIST BEFORE MAIL           YES
MESSAGE LOSS ON MAIL FAILURE  ZERO
DESTINATION ALLOWLISTED       YES
VISITOR ADDRESS AS FROM       ZERO
SMTP TEST SAME TRANSPORT      YES
SMTP DEFAULT SITE-WIDE HIJACK ZERO
AUTO REPLY                    YES
RECAPTCHA V2 CHECKBOX         YES WHEN ENABLED
SERVER CAPTCHA VERIFY         YES
RAW CAPTCHA TOKEN STORED      ZERO
HONEYPOT/RATE GUARD           YES
ATTACHMENTS                   ZERO
SENSITIVE CLINICAL FIELDS     ZERO
PUBLIC INBOX REST             ZERO
SECRET ECHO TO ADMIN HTML     ZERO
```

Test:

- valid submit persists one record;
- invalid nonce/required fields rejected;
- honeypot rejected;
- rate guard bounded;
- reCAPTCHA disabled path;
- enabled but incomplete config fails closed;
- mocked success/failure verification;
- staff mail accepted/failed state;
- auto reply accepted/failed state;
- SMTP password blank-save preserves secret;
- constant override hides secret;
- test email capability+nonce;
- inquiry search/status actions require capability+nonce;
- public route cannot query message records.

### Doctor migration

```text
CANONICAL ENTITY              gloskin_doctor
OFFICIAL ROSTER TARGET        13
STABLE SOURCE IDS             YES
SOURCE PROVENANCE             REQUIRED
INVENTED NAME                 ZERO
INVENTED SIP                  ZERO
INVENTED SCHEDULE             ZERO
INVENTED BRANCH               ZERO
STOCK DOCTOR PORTRAIT         ZERO
UNOWNED COLLISION OVERWRITE   ZERO
PARTIAL IMPORT RESUMABLE      YES
DOUBLE IMPORT DUPLICATES      ZERO
VERIFY BEFORE CONSUMED        YES
CONSUMED BEFORE CLEANUP       YES
UNRELATED POST DELETE         ZERO
UNRELATED MEDIA DELETE        ZERO
```

---

## 11. Recommended implementation order

1. Shop state/REST/query extension + tests.
2. Contact private message schema and settings schema.
3. Contact submission + persistence.
4. Scoped mail/SMTP + test mail + auto reply.
5. reCAPTCHA/honeypot/rate protection.
6. Contact frontend polish + admin Settings UX + Inbox UX.
7. Doctor source bundle/validator/importer + migration UI.
8. Full focused regression suite.
9. One runtime patch bump only after implementation is complete.

This ordering keeps the highest-risk Contact data path testable before visual/admin polish and keeps doctor factual ingestion isolated from operational mail work.

---

## 12. Release rule

This document-only planning commit does not change runtime version.

When the implementation batch is actually shipped, re-read current `main` and bump exactly one patch from the then-current runtime. If implementation still starts from `0.7.114`, target `0.7.115` and synchronize:

- plugin header;
- Kernel `VERSION`;
- release-version contract.

Preferred implementation commit:

```text
build shop filters contact operations and doctor migration
```

Do not label production/network delivery checks as passing unless actually executed. Static/unit/browser-smoke checks are not proof that an SMTP server accepted credentials, Google reCAPTCHA verified a real token, or a real recipient inbox delivered a message; those integration checks may be reported `SKIPPED` when unavailable.
