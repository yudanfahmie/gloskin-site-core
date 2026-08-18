# Owner Doctor Photo Workpack — WebP

Source date: 2026-08-19
Source: owner-supplied `FOTO DOKTER GLOSKIN` archive.
Source SHA-256: `19d72cbd79bb0fab22a782de31e602e2abeaaa19aea7e3964e2124d570056c64`.

## Conversion

All **17 supplied source images** were converted to actual WebP for web-oriented engineering use. Conversion applies EXIF orientation, performs no crop and no upscale, limits the long edge to 700 px, and uses WebP quality 65. Source composition is preserved.

The source contains **12 unique doctor identities**. This count is independent from repository readiness target counts. Do not invent or assign a photo to a doctor that is not represented in the owner pack.

`doctor-photo-manifest.json` is the machine-readable authority for identity aliases, fixed primary selection, WebP SHA-256 and alternate filenames.

## Committed binary resources

The converted WebPs are stored compactly in two binary ZIP resources under this docs workpack:

- `doctor-photo-primaries-webp.zip` — exactly the 12 migration-approved primary WebPs. SHA-256: `9f397438e863aace9c08713d754432dde093d9c12109a93897bf559ee8ea0d6f`.
- `doctor-photo-alternates-webp.zip` — the 5 retained alternate WebPs for human review only. SHA-256: `ae884126b867c5c6c3e5cbd5df52b62570a9c10795c76727739f70a4b4d6d91f`.

`dr-arwina-sufika.webp` is also stored directly as a real binary WebP as a simple review/smoke asset. Runtime migration must still use the manifest + primary archive rather than infer behavior from this convenience copy.

## Primary mapping selected for migration

| Owner source label | Primary WebP | Alternatives retained for review |
|---|---|---|
| DR ARWINA SUFIKA | `dr-arwina-sufika.webp` | dr-arwina-sufika-alt-01.webp, dr-arwina-sufika-alt-02.webp, dr-arwina-sufika-alt-03.webp |
| DR CYINTIA MUSIUS | `dr-cyintia-musius.webp` | — |
| DR DESY PUSTIKA SARI | `dr-desy-pustika-sari.webp` | — |
| DR FLORENTINA AIRA S SYAHARANI | `dr-florentina-aira-s-syaharani.webp` | — |
| DR MARIA MAGDALENA BR MANIK_ | `dr-maria-magdalena-br-manik.webp` | — |
| DR MEGA CARKANINBA | `dr-mega-carkaninba.webp` | — |
| DR NANANG MASRANI, M BIOMED (AAM) | `dr-nanang-masrani-m-biomed-aam.webp` | dr-nanang-masrani-m-biomed-aam-alt-01.webp, dr-nanang-masrani-m-biomed-aam-alt-02.webp |
| DR NI NYOMAN AYU LAKSMI TRIMURTI | `dr-ni-nyoman-ayu-laksmi-trimurti.webp` | — |
| DR OQTI RODIA Sp.GK | `dr-oqti-rodia-sp-gk.webp` | — |
| DR PRISSILMA TANIA JONARDI Sp.DVE | `dr-prissilma-tania-jonardi-sp-dve.webp` | — |
| DR RODIAH | `dr-rodiah.webp` | — |
| DR VINDI NAZHIFA | `dr-vindi-nazhifa.webp` | — |

Primary selection is explicit for the two multi-image doctors: `dr-arwina-sufika.webp` and `dr-nanang-masrani-m-biomed-aam.webp`. Runtime migration must never choose an alternate dynamically.

## Robust apply policy

The owner explicitly confirmed that these are the doctor photos and instructed that each primary should be applied directly to the matching doctor record. Identity matching must nevertheless be deterministic and conservative.

1. Read the manifest; do not infer identity from pixels.
2. Normalize existing `gloskin_doctor` title/slug and manifest aliases deterministically: lowercase/casefold, punctuation to spaces, collapse whitespace, ignore a leading `dr` honorific; degree/source spelling variants are explicitly listed in the manifest.
3. Match only against explicit aliases. **No fuzzy/Levenshtein/AI face matching.**
4. Every supplied primary must resolve to exactly one existing doctor record before finalization. Zero or multiple matches are actionable migration errors.
5. Additional WordPress doctors without a supplied image are valid and remain unchanged.
6. Extract/import only the 12 `primary_webp` members from the primary archive into Media Library during migration. The five alternate WebPs remain docs/reference assets unless the owner later selects one.
7. Make import idempotent using a stable plugin asset identity plus SHA-256 attachment meta; reruns must reuse the attachment.
8. Snapshot the previous doctor thumbnail ID before replacement. Owner instruction authorizes replacing the featured image for a uniquely matched supplied doctor.
9. Do not rewrite doctor name, credentials, biography, clinics, treatments, slug, or relationships.
10. Verification must prove all 12 supplied primaries were uniquely matched and imported/reused, no attachment duplicates were created, no doctor was created/deleted, and no unrelated featured image changed.
11. This photo checkpoint belongs to the same one-click/resumable/consumed migration. Failure blocks finalization; success is included in the audit summary.

## Packaging/runtime rule

These resources live under `docs` as owner-approved engineering provenance. The production one-shot importer must package/extract the 12 primary WebP bytes into a deterministic first-party migration runtime; **do not hotlink docs/GitHub URLs at runtime**. Disposable runtime copies may be cleaned after successful consumption according to the repository’s existing pattern. The docs archives, README and manifest remain provenance/reference after migration.
