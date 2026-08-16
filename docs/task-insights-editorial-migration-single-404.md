# Insights Editorial Content, Migration, Single Post & 404 — Implementation Plan

Status: implementation-ready design/audit package  
Scope: `gloskin-site-core` only  
Audited baseline: `main` at `461da2a0e5004871eb73ce6007086de30d1643a0`, runtime `0.7.114`  

## 1. Objective

Replace the current Insights placeholder/navigation-card section with a real WordPress editorial experience backed by native `post` records, ship a deterministic 13-post migration bundle with real featured media + native categories, add a Gloskin-owned single-post view, and add a Gloskin 404 experience inspired by Sangspa's visual idea while preserving Gloskin's existing shell/template ownership.

The implementation should be low-effort/high-impact: use native WordPress primitives already present in the plugin, reuse the shared Gloskin empty-state/card/shell vocabulary, and copy the proven *migration behavior* already in this repository rather than introducing another CMS model.

## 2. Audit findings

### Current Insights route

`templates/pages/insights.php` currently renders:

1. the normal Gloskin hero;
2. an `insights-intro` static pathway grid with three navigation cards (Perawatan, Skincare, Lokasi);
3. a real post list headed `Artikel dan Pembaruan`;
4. the shared `insight` empty state when no published posts exist.

The three pathway cards are the circled placeholder-like block in the supplied screenshot. They are not article records and should be removed from this route.

### Existing data model is already correct

`Gloskin_Site_Core_Template_Service::insights_context()` already queries native WordPress `post` records with `post_status=publish`, pagination and `ignore_sticky_posts=true`.

Decision: **keep native `post` as the sole Insight article entity. Do not create an Insight CPT.**

Benefits:

- Gutenberg/classic editor remains native;
- author/date/excerpt/featured image are standard WordPress fields;
- category taxonomy is already available;
- permalink and SEO-provider integrations remain conventional;
- Home already consumes Insight post cards, so seeded posts naturally populate existing surfaces.

### Empty-state capability already exists

`templates/parts/readiness-helpers.php` already has the shared `gloskin_ui1_render_empty_state()` component and an `insight` icon. The same component family is used by the native empty Cart integration.

Decision: reuse this shared empty-state system; do not create an Insights-only empty component.

### Missing route ownership

`Template_Service::identify_view()` currently does not claim:

- `is_singular('post')`;
- `is_404()`.

Therefore those requests currently fall through to the active theme instead of the Gloskin shell.

Decision:

- add a Gloskin `insight-single` view for native posts;
- add a Gloskin `not-found` view for 404;
- keep one existing shell and one Template Service owner.

### Migration infrastructure already exists

The sample-product migration already demonstrates a mature one-shot pattern:

- fixed runtime bundle path;
- manifest contract;
- file-size bounds;
- SHA-256 checksums;
- full validation before mutation;
- deterministic source IDs;
- resume/checkpoint state;
- lock with bounded TTL;
- upsert/reuse rather than blind duplication;
- final verification;
- mark logical state `consumed` before runtime filesystem cleanup;
- delete only manifest-declared runtime files;
- report cleanup failure without reopening a consumed migration.

The Consultation demo importer additionally proves deterministic/idempotent source keys for partial-failure convergence.

Decision: create a **narrow Insight migration bundle/importer** using these proven semantics. Do not refactor the existing product importer into a generic framework during this task.

### Existing media migration convention

The current sample-product bundle uses fixed Pexels asset URLs plus source-page, author, license-note, filename and alt metadata, then imports them to the WordPress Media Library.

Decision: use the same provenance shape for the 13 Insight featured images. No random image endpoint and no front-end dependency on Pexels after import.

### Sangspa 404 / single-post audit

Sangspa currently contains canonical modules:

- `sang-spa-suite/frontend/modules/not-found-page.php`
- `sang-spa-suite/frontend/modules/single-post.php`

Useful ideas to adopt:

- clearly branded large 404 numeral;
- calm ambient decorative background;
- human copy and clear recovery destinations;
- editorial hero image + title/meta treatment on single posts;
- narrow, readable article column;
- related stories.

Do **not** copy Sangspa's output-buffer/shutdown HTML injection or its large inline-CSS/template-redirect implementation. Gloskin already has a deterministic `template_include` + shell architecture; the Sangspa approach would create a second template owner and is therefore the wrong architecture here.

## 3. Architecture decisions

### Canonical content owner

Use WordPress core:

- post type: `post`;
- taxonomy: native `category`;
- featured image: `_thumbnail_id` / Media Library attachment;
- body: normal `post_content`;
- excerpt: normal `post_excerpt`;
- author: importing administrator/current user unless explicitly configured otherwise;
- dates: deterministic values from payload;
- comments/pings: closed for the seed payload unless current site editorial policy says otherwise.

No custom table.  
No Insight CPT.  
No shadow content store.  
No JSON front-end data source.

### Taxonomy

Seed/reuse five native categories:

1. `Perawatan` — slug `perawatan`
2. `Skincare` — slug `skincare`
3. `Kesehatan Kulit` — slug `kesehatan-kulit`
4. `Anti-Aging` — slug `anti-aging`
5. `Rambut` — slug `rambut`

Each migrated article gets exactly one primary seed category for deterministic presentation.

The archive/single templates should initially render the category as **metadata text/chip, not a required clickable route**, unless category archives are also deliberately brought into the Gloskin shell in the same implementation. Do not create a half-branded journey that sends a visitor to an unowned theme category archive.

Do not invent a second `gloskin_insight_category` taxonomy.

## 4. Archive UX

Remove the entire static pathway-grid block from `templates/pages/insights.php`.

Target sequence:

`Hero → Artikel/Pembaruan editorial lead + real cards → pagination/footer`

Recommended first-page composition:

- first query result: one larger editorial feature card;
- remaining eight: responsive real article grid;
- continue using 9 posts per page, so 13 seeded posts produce a balanced 9 + 4 pagination rather than 12 + 1;
- page 2 may use the regular grid without forcing a giant lead, or use the same first-item feature treatment if it remains visually balanced.

Each Insight card should render only real post data:

- local WordPress featured image when available;
- category label;
- title;
- excerpt;
- published date;
- optional deterministic reading-time label;
- `Baca artikel` CTA / linked title.

Do not use the current generic `Lihat Detail` language for the dedicated Insight presentation if a small branch/specialized renderer can make the editorial intent clearer.

A legacy/editor-created post without a thumbnail may keep the existing generic editorial fallback as resilience, but **all 13 migration posts must have a verified real Media Library featured image**.

### Empty state

Keep the existing shared `gloskin_ui1_render_empty_state('insight', ...)` language.

Recommended copy:

- title: `Belum ada artikel yang dipublikasikan`
- copy: `Insight dan pembaruan Gloskin akan tampil di sini setelah tersedia.`
- CTA: `Lihat Perawatan` → `/treatments/`

The visual component must remain the same shared family as empty Cart; only content/kind differ.

## 5. Seed editorial package — exactly 13 posts

The payload must contain **full publishable-looking Indonesian editorial copy**, not lorem ipsum, not one-paragraph stubs and not claims of individualized diagnosis/results.

Recommended article length: approximately 550–900 words each, with a short introduction, 4–6 meaningful H2 sections, practical closing guidance, and natural internal links only where the destination already exists.

Medical/clinical tone boundary: educational and consultation-first. Avoid efficacy guarantees, before/after claims, fabricated statistics, diagnosis, prescription advice, named patient stories, or invented doctor quotations.

| # | Slug | Title | Category | Editorial focus |
|---|---|---|---|---|
| 1 | `memahami-skin-barrier-dan-cara-menjaganya` | Memahami Skin Barrier dan Cara Menjaganya | Kesehatan Kulit | fungsi barrier, tanda umum terganggu, kebiasaan sederhana, kapan konsultasi |
| 2 | `rutinitas-skincare-pagi-yang-sederhana` | Rutinitas Skincare Pagi yang Sederhana dan Konsisten | Skincare | cleansing, moisturizer, sunscreen, konsistensi, menyesuaikan kebutuhan |
| 3 | `sunscreen-sebagai-kebiasaan-harian` | Sunscreen sebagai Kebiasaan Harian untuk Menjaga Kulit | Skincare | penggunaan harian, reapplication context, kenyamanan formula, no SPF guarantee claims |
| 4 | `eksfoliasi-wajah-kapan-dan-bagaimana` | Eksfoliasi Wajah: Kapan Kulit Membutuhkannya? | Skincare | tujuan, over-exfoliation signs, interval cautious, barrier first |
| 5 | `jerawat-kapan-sebaiknya-berkonsultasi` | Jerawat: Kapan Sebaiknya Mulai Berkonsultasi? | Kesehatan Kulit | pattern recognition, gentle routine, avoid picking, consultation signals |
| 6 | `flek-dan-pigmentasi-memahami-pemicunya` | Flek dan Pigmentasi: Memahami Pemicu sebelum Memilih Perawatan | Kesehatan Kulit | multiple causes, sun exposure, post-inflammatory marks, assessment-first |
| 7 | `rutinitas-untuk-kulit-sensitif` | Menyusun Rutinitas yang Lebih Tenang untuk Kulit Sensitif | Kesehatan Kulit | simplify products, patch/gradual intro framing, triggers, consultation |
| 8 | `anti-aging-dimulai-dari-kebiasaan-dasar` | Anti-Aging Dimulai dari Kebiasaan Dasar yang Konsisten | Anti-Aging | protection, hydration, lifestyle framing, realistic expectations |
| 9 | `persiapan-sebelum-konsultasi-perawatan-estetika` | Apa yang Perlu Disiapkan sebelum Konsultasi Perawatan Estetika? | Perawatan | goals, current routine, history/questions, expectation setting |
| 10 | `aftercare-setelah-perawatan-estetika` | Aftercare setelah Perawatan Estetika: Prinsip Dasar yang Perlu Diingat | Perawatan | follow clinician instructions, gentle routine, warning signs, no universal protocol claims |
| 11 | `rambut-rontok-mengenali-pola` | Rambut Rontok: Mengenali Pola dan Kapan Perlu Berkonsultasi | Rambut | normal vs persistent concern framing, scalp/hair routine, professional evaluation |
| 12 | `merawat-kulit-kepala-dalam-rutinitas-harian` | Merawat Kulit Kepala sebagai Bagian dari Rutinitas Harian | Rambut | cleansing, buildup, sensitivity, product use, hair/scalp balance |
| 13 | `membangun-rencana-perawatan-yang-realistis` | Membangun Rencana Perawatan Kulit yang Realistis dan Bertahap | Perawatan | consultation-first, priorities, timeline expectations, consistency, review |

### Per-post payload fields

Each `posts.json` record must include at least:

```json
{
  "source_id": "gloskin-insight:v1:<stable-slug>",
  "slug": "...",
  "title": "...",
  "excerpt": "...",
  "content_html": "<p>...</p><h2>...</h2>...",
  "category_slug": "...",
  "post_date": "YYYY-MM-DD HH:MM:SS",
  "media_source_id": "gloskin-insight-media:v1:<stable-slug>:featured",
  "status": "publish"
}
```

Rules:

- unique stable `source_id` independent of WordPress ID;
- unique slug;
- sanitized body HTML limited to ordinary editorial block-compatible tags;
- no scripts, iframes, inline event handlers or external embeds in seed body;
- no hidden SEO fields/schema ownership;
- no fake author name in payload; importer assigns an actual WP user;
- deterministic dates and sort by newest first.

## 6. Featured-media package

Create exactly 13 media records, one per article.

Recommended source style: fixed Pexels image URLs, following the current sample-product migration convention.

Each record:

```json
{
  "source_id": "gloskin-insight-media:v1:<slug>:featured",
  "post_source_id": "gloskin-insight:v1:<slug>",
  "source_url": "https://images.pexels.com/...",
  "source_page_url": "https://www.pexels.com/photo/.../",
  "author": "...",
  "license_note": "Pexels editorial seed; verify current source/license before production reuse.",
  "filename": "gloskin-insight-v1-01.jpg",
  "alt": "Descriptive Indonesian alt text",
  "role": "featured"
}
```

Requirements:

- all URLs fixed/deterministic, never a random/query endpoint;
- 13 unique images where practical;
- no before/after imagery;
- no image implying a named person is a Gloskin patient/doctor;
- no factual treatment-result representation;
- sideload into WordPress Media Library;
- front end uses local attachment ID after migration;
- preserve provenance in attachment meta;
- attachment alt text must be populated;
- verification fails until every one of the 13 posts has its expected attachment as `_thumbnail_id`.

## 7. Migration bundle structure

Create source bundle:

```text
migration-source/gloskin-insights-v1/
  README.md
  manifest.json
  posts.json
  media.json
```

Create the deployable runtime mirror using the same build/deployment convention as existing migration bundles:

```text
plugin/gloskin-site-core/migration-runtime/gloskin-insights-v1/
  manifest.json
  posts.json
  media.json
```

Manifest must declare:

- `bundle_id = gloskin-insights-v1`
- schema/source version;
- migration type;
- expected posts = 13;
- expected media = 13;
- expected categories = 5;
- exact allowed payload filenames;
- SHA-256 checksum for each payload file;
- explicit notice that this is an initial editorial seed bundle and media licensing/provenance must be retained.

Reject unexpected runtime files.

## 8. Importer behavior

Create a narrow `Gloskin_Site_Core_Insight_Importer` + bundle validator modeled on the proven sample-product importer.

Do not turn this task into a generic importer framework refactor.

### Security/admin boundary

Expose one temporary native admin migration surface under existing `Gloskin Content`.

Use:

- explicit administrator-level capability (prefer `manage_options` for this one-shot site-content migration);
- nonce verification;
- no public REST route;
- no unauthenticated AJAX;
- escaped admin output;
- one-shot menu hidden after successful consumption.

### Full validation before mutation

`start` must validate the entire bundle before creating/updating any WordPress object:

- manifest shape/version/bundle ID;
- allowed files only;
- bounded file sizes;
- checksums;
- exactly 13 posts / 13 media / 5 declared category slugs;
- unique post source IDs and slugs;
- unique media source IDs;
- all category references valid;
- all post→media references valid;
- safe content HTML;
- fixed HTTPS image source URLs;
- required image provenance fields.

### Deterministic identity / anti-duplicate

Post meta:

```text
_gloskin_insight_source_id
_gloskin_insight_bundle_id
_gloskin_insight_seed = 1
```

Attachment meta:

```text
_gloskin_insight_media_source_id
_gloskin_insight_bundle_id
_gloskin_insight_seed = 1
_gloskin_insight_media_source_url
_gloskin_insight_media_source_page
_gloskin_insight_media_author
_gloskin_insight_media_license
```

On resume/retry, find by stable source ID and update the same object.

Never identify a seed record by title alone.

If an unowned existing post already uses a payload slug, **fail that record explicitly rather than overwriting real content**.

If more than one record claims the same source ID, fail verification; do not guess which duplicate to keep.

### Checkpointing

Use the existing migration pattern:

- lock option + TTL;
- `start` validates and initializes state only;
- `continue` imports at most one post/media pair per request;
- state records next index / created / updated / imported media / reused media;
- resumable after transient media/network failure;
- no long browser request attempting all 13 remote images at once.

### Safe per-record publishing

For a newly created seed item:

1. create/upsert as owned `draft`;
2. resolve/reuse category;
3. sideload/reuse featured media;
4. write alt/provenance;
5. set featured image;
6. verify required fields;
7. publish that record.

This prevents a visibly incomplete public article from appearing if media import fails halfway through a checkpoint.

### Categories

Use/reuse native `category` terms by slug.

Do not rename/delete an existing matching category created by a user.

If importer creates a missing seed category, it may mark term meta as bundle-created for diagnostics; runtime cleanup **must not delete a category if any non-bundle post uses it**. The safest default after a successful one-shot import is to leave the five harmless category terms in place.

### Final verification

Before marking consumed, verify:

- 13 unique bundle-owned published posts;
- exact source IDs;
- all expected slugs/titles/excerpts/content non-empty;
- expected category relationship per post;
- 13 valid featured attachment IDs;
- featured files resolve to local Media Library URLs;
- media alt/provenance exists;
- no duplicate owned source IDs;
- all post permalinks resolve through standard WP post routing.

### Cleanup

Mirror the existing proven order:

1. final data verification passes;
2. persist migration state `consumed` first;
3. release import lock;
4. remove only manifest-declared files from the fixed `migration-runtime/gloskin-insights-v1` directory;
5. remove manifest;
6. remove the fixed runtime directory if empty;
7. cleanup failure is reported but **does not make the migration runnable again**.

Do not delete the imported 13 posts/media after success.

Do not delete unrelated posts, media or category terms.

Do not scan/delete based on title patterns.

## 9. Single Insight template

Add route ownership before generic page/theme fallback:

```php
if ( is_singular( 'post' ) ) {
    return 'insight-single';
}
```

Add `insight-single` context and `templates/pages/insight-single.php`.

### Data/context

Context should expose only ordinary WP-derived data:

- queried `WP_Post`;
- title;
- excerpt/dek;
- feature image ID;
- category label;
- published date;
- author display name if desired;
- deterministic reading-time estimate based on stripped body words;
- related 3 published posts, preferring same category and falling back to latest posts excluding current.

Related query should be bounded and use `no_found_rows=true` because it is not paginated.

### Editorial layout

Target:

1. Gloskin breadcrumb: `Home › Insight › [Article title]`;
2. editorial header: category chip, H1, short dek, date/read time;
3. large native featured image with stable aspect ratio and responsive WP `srcset`;
4. reading column around 720–780px;
5. excellent native content typography for H2/H3/p/lists/blockquote/figure/table;
6. restrained Gloskin-red/champagne editorial accents;
7. related Insight cards (3);
8. `Kembali ke Insight` / useful clinic or treatment CTA near the end.

Keep normal `post_content` filtering so Gutenberg blocks, shortcodes and normal WP content semantics continue to work.

Do not create a second article content field.

Do not add a third-party slider, reading-progress library, share library or animation framework.

Do not copy Sangspa's inline Tailwind-like markup or giant inline stylesheet. Translate the editorial *idea* into Gloskin's existing CSS tokens/components.

### SEO boundary

Do not emit duplicate Article schema, OpenGraph tags, canonical tags or crawler metadata from this feature. WordPress/installed SEO provider remains owner.

The single-post view should not replace the natural post title with the static `Insight` document title mapping.

## 10. Breadcrumb support

Extend fallback breadcrumbs:

- `insights` → existing `Home › Insight` behavior;
- `insight-single` → `Home › Insight › [current title]`;
- `not-found` → `Home › Halaman tidak ditemukan` or a similarly concise current state.

Rank Math remains preferred visible breadcrumb owner when it actually outputs markup; Gloskin fallback stays schema-free.

## 11. Gloskin 404

Claim `is_404()` in `Template_Service` and route to `templates/pages/not-found.php` through the existing shell.

Do not use Sangspa's output buffering, shutdown injection, separate `template_redirect` output owner, or inline page takeover.

### Visual idea to adopt from Sangspa

Translate `Lost in the Garden` into a Gloskin editorial/clinical mood:

- large elegant `404` numeral;
- soft ivory surface;
- Gloskin red + champagne ambient radial accents;
- one or two very subtle decorative orbit/halo shapes, not botanical petals;
- concise human copy;
- clear recovery actions;
- optional faint brand-G/wordmark geometry as a decorative watermark only if it stays subtle;
- restrained entrance motion with reduced-motion fallback.

Suggested copy:

**404**  
**Halaman ini tidak ditemukan**  
`Alamat yang Anda buka mungkin sudah berubah atau tidak tersedia. Pilih salah satu jalur di bawah untuk melanjutkan.`

Primary CTA: `Kembali ke Beranda`  
Secondary CTA: `Buka Insight`

Helpful destinations, using real canonical URLs:

- Perawatan
- Skincare
- Klinik

No fuzzy-JS router is required. Static real destinations are lower risk and higher value in this codebase.

### HTTP correctness

404 remains a real WordPress 404 response. No redirect to home and no intentional HTTP 200 takeover.

Header/footer and the normal Gloskin shell remain visible.

## 12. CSS / presentation strategy

Prefer one dedicated small stylesheet such as `gloskin-ui1-editorial.css` registered through the existing Asset Service and loaded only where useful (`insights`, `insight-single`, `not-found`) if route-aware enqueuing is already easy in the current asset architecture.

If the current asset registry intentionally loads a consolidated frontend stylesheet, add narrowly scoped classes there instead of creating a second asset ownership model.

Scopes:

```text
.gloskin-ui1-insights-archive__*
.gloskin-ui1-insight-single__*
.gloskin-ui1-not-found__*
```

Avoid broad `.post`, `.entry-content`, `.category` rules that could leak into Woo/theme content.

No `!important` unless a proven third-party specificity collision makes it unavoidable and a contract documents why.

## 13. Performance / accessibility

- Archive feature image: eager/high priority only if truly above fold; all grid images lazy.
- Single hero image: native WP image helper, responsive `srcset`, stable dimensions, `fetchpriority=high` if above fold.
- No JS required for archive/single/404 core behavior.
- No new runtime external image calls after migration.
- Heading hierarchy: one H1 per single/404; cards use H2/H3 according to section context.
- Category/date metadata should remain readable text, not color-only meaning.
- 404 CTAs keyboard/focus accessible.
- Respect existing reduced-motion conventions.

## 14. Primary technical references

Use WordPress core APIs rather than handwritten DB writes:

- Template hierarchy: https://developer.wordpress.org/themes/templates/template-hierarchy/
- `wp_insert_post()`: https://developer.wordpress.org/reference/functions/wp_insert_post/
- `wp_update_post()`: https://developer.wordpress.org/reference/functions/wp_update_post/
- `wp_set_post_categories()`: https://developer.wordpress.org/reference/functions/wp_set_post_categories/
- `wp_insert_term()`: https://developer.wordpress.org/reference/functions/wp_insert_term/
- `media_sideload_image()`: https://developer.wordpress.org/reference/functions/media_sideload_image/
- `set_post_thumbnail()`: https://developer.wordpress.org/reference/functions/set_post_thumbnail/
- `wp_delete_post()`: https://developer.wordpress.org/reference/functions/wp_delete_post/
- `wp_delete_attachment()`: https://developer.wordpress.org/reference/functions/wp_delete_attachment/
- `check_admin_referer()`: https://developer.wordpress.org/reference/functions/check_admin_referer/
- `current_user_can()`: https://developer.wordpress.org/reference/functions/current_user_can/

## 15. Focused tests / contracts

Add focused coverage, not broad production UAT.

### Archive contract

Assert:

- old static pathway copy/cards are absent from `insights.php`;
- real `post` query remains the source;
- 9 posts/page remains deterministic;
- empty branch uses shared `gloskin_ui1_render_empty_state('insight', ...)`;
- card payload has category/date/featured-image fields;
- no fake post objects are rendered.

### Single-post contract

Assert:

- `is_singular('post')` is claimed by Template Service;
- context uses queried native post;
- `post_content` passes through standard WP content filters;
- featured image is real attachment if present;
- related query excludes current post, is bounded, and `no_found_rows=true`;
- breadcrumb includes Insight hub;
- no SEO/schema owner is added.

### 404 contract

Assert:

- `is_404()` is claimed;
- Gloskin shell/template is used;
- no redirect to home;
- recovery URLs are canonical site URLs;
- no Sangspa output-buffer/shutdown injector is copied;
- response remains 404 in an integration-capable test, otherwise mark that HTTP-level check SKIPPED rather than pretending.

### Migration tests

Adapt existing importer hardening patterns:

- manifest/checksum validation;
- unexpected file rejection;
- exact 13 posts/13 media/5 categories;
- duplicate source ID rejection;
- slug collision with unowned post fails safely;
- partial failure resumes without duplicate post/media;
- each successful record has local featured image;
- consumed state persists before runtime cleanup;
- cleanup deletes only declared runtime bundle files;
- runtime cleanup failure cannot unlock/re-run consumed migration;
- no unrelated post/media deletion;
- nonce + capability boundary;
- no SQL write path when core APIs exist.

## 16. Likely implementation files

Existing files likely touched:

```text
plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php
plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php
plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php
plugin/gloskin-site-core/config/assets.php
plugin/gloskin-site-core/templates/pages/insights.php
plugin/gloskin-site-core/templates/parts/template-helpers.php
plugin/gloskin-site-core/templates/parts/readiness-helpers.php
plugin/gloskin-site-core/gloskin-site-core.php
```

Likely new files:

```text
plugin/gloskin-site-core/templates/pages/insight-single.php
plugin/gloskin-site-core/templates/pages/not-found.php
plugin/gloskin-site-core/includes/class-gloskin-site-core-insight-bundle.php
plugin/gloskin-site-core/includes/class-gloskin-site-core-insight-importer.php
migration-source/gloskin-insights-v1/README.md
migration-source/gloskin-insights-v1/manifest.json
migration-source/gloskin-insights-v1/posts.json
migration-source/gloskin-insights-v1/media.json
plugin/gloskin-site-core/migration-runtime/gloskin-insights-v1/manifest.json
plugin/gloskin-site-core/migration-runtime/gloskin-insights-v1/posts.json
plugin/gloskin-site-core/migration-runtime/gloskin-insights-v1/media.json
```

Add one editorial stylesheet only if consistent with the current Asset Service ownership model.

## 17. Non-goals / hard boundaries

Do not:

- create an Insight CPT;
- build a headless JSON article frontend;
- create a custom category database/table;
- take over Rank Math/SEO schema;
- add a category filter SPA;
- add a JS masonry framework;
- add a slider library for related posts;
- add a custom router;
- copy Sangspa's template interception/output buffering;
- blindly delete existing WordPress posts/categories/media;
- auto-overwrite user-authored posts on slug/title collision;
- leave 13 front-end cards that are not backed by 13 real WordPress post records.

## 18. Release / execution

The documentation commit itself does not require a runtime version bump.

When production implementation changes land, bump exactly one patch from the **actual** runtime version found on `main` at execution time.

If production work begins from runtime `0.7.114`, target `0.7.115`.

Implementation should be one coherent direct-to-`main` commit after focused validation; no PR, extra branch, workflow change or force push unless the owner changes that instruction.

## 19. Acceptance gate

Do not report complete until all of the following are true:

```text
INSIGHTS STATIC PATHWAY CARDS      REMOVED
INSIGHTS REAL POST ARCHIVE         YES
NATIVE POST OWNER                  YES
NATIVE CATEGORY TAXONOMY           YES
SEEDED POSTS                       13
SEEDED FEATURED MEDIA              13
LOCAL MEDIA AFTER MIGRATION        YES
IMPORT RESUMABLE / IDEMPOTENT      YES
RUNTIME BUNDLE AUTO-CLEANUP        YES
UNRELATED CONTENT DELETION         ZERO
SHARED INSIGHT EMPTY STATE         YES
GLOSKIN SINGLE POST                YES
RELATED POSTS                      YES
GLOSKIN 404                        YES
HTTP 404 PRESERVED                 YES
SANGSPA VISUAL IDEA ADAPTED        YES
SANGSPA INJECTION ARCHITECTURE     ZERO
NEW SEO/SCHEMA OWNER               ZERO
NEW FRONTEND FRAMEWORK             ZERO
```
