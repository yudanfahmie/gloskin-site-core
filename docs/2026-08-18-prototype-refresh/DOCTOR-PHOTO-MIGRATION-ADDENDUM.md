# Addendum — Owner Doctor Photo Apply

Date: 2026-08-19
Status: **Required part of the active next task**
Applies to: `NEXT-TASK-PARITY-DATA-ADMIN-MIGRATION.md`

This addendum is explicit owner input and therefore overrides any older doctor-image placeholder/fallback assumption.

## Owner direction

The owner confirmed that the supplied `FOTO DOKTER GLOSKIN` archive contains the doctor photographs that should be applied to the corresponding Gloskin doctor records.

The photos are not generic decorative assets. They are factual entity media and should replace the current doctor featured-image/fallback state when identity matching is deterministic.

## Converted workpack

The source archive has been normalized into **17 WebP images covering 12 doctor identities**.

Canonical conversion specification:

- WebP;
- quality 43;
- maximum long edge 600 px;
- no crop;
- no upscale;
- orientation normalized;
- original composition preserved.

Canonical identity/hash specification:

`resources/doctor-photos/doctor-photo-manifest-v2.json`

The older manifest is superseded by the v2 manifest for this revision.

The two doctors with multiple supplied photographs are:

- Arwina Sufika — 1 fixed primary + 3 alternates;
- Nanang Masrani — 1 fixed primary + 2 alternates.

Alternates are reference-only. Migration must never choose them randomly.

## Matching contract

Do not use image recognition, AI face matching, fuzzy string similarity, Levenshtein matching, or broad title search.

Normalize WordPress doctor identity and manifest aliases deterministically:

1. Unicode/casefold or lowercase;
2. trim;
3. punctuation → spaces;
4. collapse repeated whitespace;
5. ignore only a leading `dr` honorific;
6. compare against explicit manifest aliases, including known source spelling variants such as `cyntia/cyintia`, `laksmy/laksmi`, `vindy/vindi`, and degree-suffixed aliases already encoded in the manifest.

A supplied primary is eligible only when it resolves to **exactly one existing published or intended canonical `gloskin_doctor` record**.

- zero match → migration blocks and reports the unmatched source label;
- more than one match → migration blocks and reports the ambiguous candidates;
- one match → eligible to apply;
- additional WordPress doctors without a supplied owner photo remain unchanged and do not block migration.

Do not create a doctor record from a photograph.

## Media Library import/apply contract

For each of the 12 fixed primaries:

1. verify the decoded WebP SHA-256 against `doctor-photo-manifest-v2.json`;
2. find/reuse an attachment previously imported by this revision using stable plugin asset identity + SHA meta;
3. otherwise import exactly one attachment into WordPress Media Library using a deterministic filename;
4. store source identity/hash provenance in attachment meta;
5. snapshot the matched doctor's previous `_thumbnail_id` in migration audit state before replacement;
6. set the imported/reused attachment as the doctor's featured image;
7. never change doctor title, slug, degree, specialization, biography, clinic/treatment relationships, status, or other editor-owned fields.

Owner direction authorizes replacement of the featured image for a uniquely matched supplied doctor.

Rerunning/resuming the migration must reuse the same attachment and must not create duplicate Media Library records.

## One-click migration integration

This is **not** a separate permanent Doctor Photo Importer.

Add one checkpoint to the same bounded next-revision migration:

`Preflight → managed content structures → demo seed → doctor-photo preflight/match → doctor-photo import/apply → page/data normalization → cleanup → safety verify → consumed`

Doctor-photo preflight should compute all 12 intended matches before destructive thumbnail replacement begins. If the set is not safe, fail before applying any doctor photo, or use a resumable transaction-like checkpoint with an explicit rollback/snapshot strategy.

The final safety verifier must assert:

- exactly 12 supplied primaries resolved uniquely;
- each resolved doctor has the expected attachment hash;
- no duplicate revision-owned attachment exists for a primary hash;
- no doctor was created or deleted;
- doctors not represented in the owner photo pack were not modified;
- previous thumbnail IDs are recorded in audit state;
- all Woo page IDs and protected commerce state remain unchanged.

Only after this and the other revision checks pass may the migration finalize as `consumed`.

After `consumed`, the temporary migration action disappears permanently and plugin reactivation must not resurrect it.

## Runtime packaging

Do not hotlink GitHub/docs images in production.

The docs workpack is engineering provenance. During implementation, package the **12 fixed primary WebP bytes** into the plugin's bounded migration resources (or another existing first-party migration bundle owner), verify hashes, and let WordPress Media Library become the runtime media owner after import.

The five alternates stay out of the runtime importer unless the owner explicitly changes a primary selection.

## Regression protection

The engineer must preserve the already-completed v0.7.133 doctor presentation work:

- all published doctors are rendered on the Doctor directory and About team area;
- desktop doctor grid = 4 columns;
- tablet = 2;
- mobile = 1;
- factual doctor photo uses the WordPress featured-image path and therefore automatically replaces the existing abstract fallback;
- no separate image rendering path should be added merely for imported photos.

## Tests required

Add focused tests proving:

- manifest has 12 unique primary identities and 5 alternates;
- primary hashes are unique;
- exact alias normalization is deterministic;
- fuzzy matching is absent;
- unmatched/ambiguous doctor identity blocks apply;
- all matches are preflighted before mutation;
- imported attachment is reused on rerun;
- existing thumbnail is snapshotted before replacement;
- unrelated doctors remain unchanged;
- doctor fields/relationships other than thumbnail are unchanged;
- photo checkpoint resumes safely after interruption;
- consumed migration cannot run again.

This addendum is part of Definition of Done for `NEXT-TASK-PARITY-DATA-ADMIN-MIGRATION.md`.
