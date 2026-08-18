# Current Engineering Pack — 2026-08-19

This is the single entry point for the next Gloskin implementation pass.

## Read order / precedence

1. `README.md` — prototype/public IA authority and protected architecture zones.
2. `NEXT-TASK-PARITY-DATA-ADMIN-MIGRATION.md` — main implementation scope.
3. `DOCTOR-PHOTO-MIGRATION-ADDENDUM.md` — required doctor-photo checkpoint; this **supersedes any older doctor-photo conversion/reference details inside the main task**.
4. `resources/doctor-photos/README-V2.md` — canonical doctor-photo workpack rules.
5. `resources/doctor-photos/doctor-photo-manifest-v2.json` — canonical doctor identity, fixed primary selection, dimensions and SHA-256 mapping.

If an older doctor-photo README/manifest, old 700px/q65 conversion note, temporary `.b64`, `parts/`, test WebP, or experimental ZIP disagrees with the v2 files above, **v2 wins**. Such experimental artifacts are cleanup candidates after the canonical runtime bytes are safely packaged and verified.

## Current protected baseline

Do not reopen the completed v0.7.133 work:

- semi-full 1320px public container;
- Doctor directory/About all-published-doctor behavior;
- doctor grid 4 desktop / 2 tablet / 1 mobile;
- Header V2 geometric centering;
- Graphik/Felix typography;
- protected WooCommerce Shop/PDP/Cart/Checkout/My Account ownership.

## Owner-reviewed remaining product scope

The next pass must finish these gaps without redesigning unrelated areas:

- Treatments Hub: move the opening discovery to prototype/board-style large alternating path bands while preserving the existing consultation/recommendation engine;
- Promo: managed repeatable campaigns + native WordPress CRUD + carousel + deterministic demo records;
- Why Gloskin: dominant editorial block + supporting cards, richer but factual copy;
- Skincare: product-first discovery using real Woo products/taxonomy/mappings; Shop remains protected;
- Testimonials: managed native CRUD + deterministic demo records + conditional factual frontend slider;
- Achievements/Piagam: one managed native source shared by Home/About + deterministic demo records;
- About: complete optional factual Founder/Team/Network/Achievement hierarchy without duplicate doctor data;
- footer/supporting IA, hard-coded factual copy, remote staging imagery, obsolete presentation settings, dead CSS/helpers and other zero-consumer cleanup;
- owner doctor photos: deterministic import/apply through the same bounded migration.

## Managed content rules

Use WordPress-native ownership. No custom tables and no duplicate commerce owner.

Preferred repeatable entities:

- `gloskin_promo`;
- `gloskin_testimonial`;
- `gloskin_achievement`.

Expose them under the existing **Gloskin Content** admin area using native WordPress list/edit/publish/draft/trash flows. Add only bounded post meta required by presentation and ordering. Do not build a custom SPA CRUD.

Make editor management low-effort:

- useful admin labels and columns;
- deterministic display order;
- clear readiness/incomplete state;
- featured media through Media Library;
- sample/demo identity visibly distinguishable from factual editor content.

## Demo-data safety

Promo/Testimonial/Achievement need deterministic sample records so new UI can be tested.

Rules:

- stable plugin-generated identity meta;
- no duplication on rerun;
- never overwrite editor records;
- never use broad title matching for destructive cleanup;
- on production, sample records default to Draft;
- only an explicitly detected `development`/`staging` environment may publish sample records automatically for immediate visual testing;
- sample records must remain clearly marked as demo-generated even on staging;
- doctor photos are factual owner assets, **not** demo data.

## Doctor photo rule

The owner-supplied package represents factual doctor identity media.

Canonical conversion in v2:

- WebP;
- quality 43;
- max long edge 600 px;
- no crop;
- no upscale;
- EXIF/orientation normalized.

The external handoff archive for this task is `gloskin-doctor-photos-webp-v2.zip` with 17 WebPs. Implementation must validate each canonical primary against `doctor-photo-manifest-v2.json` before packaging/import. Do not trust filename alone.

Apply only the 12 fixed primary identities from the v2 manifest through deterministic exact-alias matching. No fuzzy matching, no AI/face recognition and no invented photo for a doctor not represented in the source pack.

Before mutation, preflight the **entire 12-doctor mapping set**. A zero/ambiguous match or hash mismatch blocks the photo checkpoint before any thumbnail replacement.

On safe match:

- reuse/import exactly one Media Library attachment by stable revision asset identity + SHA;
- snapshot previous `_thumbnail_id`;
- replace only the featured image;
- never alter doctor title/slug/degree/specialization/bio/clinic/treatment relationships;
- extra doctors without supplied photo remain unchanged.

## Migration user experience

One temporary migration action → one click → deterministic/resumable checkpoints → safety verification → consumed → migration UI disappears permanently.

Recommended sequence:

1. preflight/snapshots;
2. ensure managed content structures/admin ownership;
3. deterministic demo seed;
4. doctor-photo full-set preflight/hash/match;
5. doctor-photo import/reuse/apply;
6. normalize frontend/page relationships;
7. retire obsolete plugin-owned presentation/admin surfaces where safe;
8. zero-consumer cleanup;
9. safety verification;
10. finalize/consumed.

Migration requirements:

- no second/generic migration framework;
- no permanent photo importer;
- resume from last safe checkpoint after interruption;
- schema/version monotonic;
- do not finalize if any required checkpoint fails;
- activation/deactivation cannot resurrect consumed UI;
- cleanup failure after successful business-state consumption must surface an actionable warning without rerunning mutation checkpoints;
- store an audit summary of created/reused demo IDs, photo attachment IDs/hashes, matched doctor IDs and previous thumbnail IDs.

## Cleanup rule

Cleanup must be reference-based, not aggressive.

Remove/hide only plugin-owned elements proven to have zero active consumer, including obsolete header/design-variant admin controls, dead Header V1/presentation CSS/JS, old remote staging-image helpers, stale comments and experimental doctor-photo packaging artifacts.

Never delete editor-authored Pages/posts/media, supporting routes, Woo pages/products/orders/customers, or ambiguous content merely because it is no longer primary navigation.

## Final acceptance

Do not call the task complete until:

- board/prototype parity gaps above are visibly resolved;
- managed Promo/Testimonial/Achievement CRUD works and is editor-friendly;
- demo records are deterministic and production-safe;
- doctor-photo preflight/import/apply is deterministic and idempotent;
- all 12 canonical supplied primaries are uniquely resolved or migration blocks with actionable diagnostics;
- no unrelated doctor or Woo/editor data is changed;
- one-click migration reaches `consumed` only after full verification and then disappears;
- completed v0.7.133 wrapper/doctors/header/font/commerce behavior does not regress;
- focused contracts and full existing repository test suite pass.
