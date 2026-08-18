# Doctor Photo Workpack V2 — Canonical for Next Migration

This file supersedes older packaging notes in this directory for the 2026-08-19 doctor-photo revision.

## Source

Owner-supplied archive: `FOTO DOKTER GLOSKIN-20260818T172831Z-1-001.zip`

Source SHA-256:

`19d72cbd79bb0fab22a782de31e602e2abeaaa19aea7e3964e2124d570056c64`

Owner instruction: these are factual doctor photos and should be applied to the corresponding existing Gloskin doctor records.

## Conversion result

17 source images were normalized to WebP, covering 12 unique doctor identities.

Canonical conversion for this revision:

- WebP;
- quality 43;
- max long edge 600 px;
- EXIF orientation normalized;
- no crop;
- no upscale;
- source composition preserved.

Machine-readable mapping/hashes:

`doctor-photo-manifest-v2.json`

## Fixed primary selection

The migration imports **12 primaries only**.

Arwina Sufika and Nanang Masrani have multiple owner-supplied choices. Their migration primaries are fixed in the manifest; all other images for those two doctors are alternatives for human review only.

Do not select an alternative dynamically.

## Repository transport note

The machine-readable manifest is the identity and hash authority. Any `.webp`, archive, split archive or `.b64` payload in this directory must be treated only as a transport/work asset and must pass its manifest SHA-256 before it is packaged into production migration resources.

Older experimental ZIP/binary transport files in this directory are **not canonical** and must not be used merely because their filename looks correct. The implementation engineer must consume only assets whose decoded bytes validate against `doctor-photo-manifest-v2.json`.

The currently staged text-safe `.webp.b64` payloads are engineering transport material. A `.b64` payload reconstructs the real WebP by standard Base64 decoding, followed by SHA-256 verification against the v2 manifest.

Example:

```bash
base64 -d payloads/dr-ni-nyoman-ayu-laksmi-trimurti.webp.b64 > /tmp/dr-ni-nyoman-ayu-laksmi-trimurti.webp
sha256sum /tmp/dr-ni-nyoman-ayu-laksmi-trimurti.webp
```

Do not serve Base64 docs payloads to visitors and do not hotlink GitHub/docs files.

## Production packaging requirement

Before implementing the migration checkpoint, the engineer must make the complete set of **12 manifest-verified primary WebPs** available as first-party plugin migration resources. Then the one-shot migration imports/reuses them into WordPress Media Library and sets them as featured images for uniquely matched `gloskin_doctor` records.

If a required primary byte payload cannot be verified, the migration must not silently substitute an alternative, stock photo, abstract image or another doctor's image. Report the missing asset and block finalization.

## Matching rule

Use exact deterministic aliases from `doctor-photo-manifest-v2.json` after bounded normalization. No fuzzy matching and no face recognition.

Every supplied primary must resolve to exactly one doctor before finalization. Additional WordPress doctors without a supplied photo remain unchanged.

Full migration behavior is specified in:

`../../DOCTOR-PHOTO-MIGRATION-ADDENDUM.md`

and remains part of:

`../../NEXT-TASK-PARITY-DATA-ADMIN-MIGRATION.md`.
