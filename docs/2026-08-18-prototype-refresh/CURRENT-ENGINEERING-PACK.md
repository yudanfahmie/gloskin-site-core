# Current Engineering Pack — 2026-08-19

Read these files together as the active implementation authority:

1. `README.md` — prototype/public IA authority and protected architecture zones.
2. `NEXT-TASK-PARITY-DATA-ADMIN-MIGRATION.md` — active prototype-parity + managed-content + cleanup + one-shot migration task.
3. `DOCTOR-PHOTO-MIGRATION-ADDENDUM.md` — required owner doctor-photo import/apply checkpoint for the same migration.
4. `resources/doctor-photos/README-V2.md` — canonical doctor-photo workpack rules.
5. `resources/doctor-photos/doctor-photo-manifest-v2.json` — canonical doctor identity, fixed primary selection, dimensions and SHA-256 mapping.

## Current protected baseline

Do not reopen the completed v0.7.133 work:

- semi-full 1320px public container;
- Doctor directory/About all-published-doctor behavior;
- doctor grid 4 desktop / 2 tablet / 1 mobile;
- Header V2 geometric centering;
- Graphik/Felix typography;
- protected WooCommerce Shop/PDP/Cart/Checkout/My Account ownership.

## Doctor photo rule

Owner-supplied doctor photos are factual entity media, not decorative fallbacks. Apply only the 12 fixed primary identities from the v2 manifest through deterministic exact-alias matching in the same bounded migration. No fuzzy matching, no face recognition and no invented photo for a doctor not represented in the source pack.

Any historical/experimental doctor-photo packaging artifact that does not validate against `doctor-photo-manifest-v2.json` is non-canonical and should be removed during the next task's zero-consumer cleanup.

## Migration user experience

One temporary migration action → one click → deterministic/resumable checkpoints → safety verification → consumed → migration UI disappears permanently. Do not leave a permanent photo importer or generic migration framework.
