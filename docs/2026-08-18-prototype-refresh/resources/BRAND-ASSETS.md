# Gloskin Brand Assets — 2026-08-18

Machine-readable contract distilled from the owner-supplied palette/font/wireframe packages for the prototype revamp.

## Color palette

Canonical brand colors:

| Token | HEX | RGB | Intended UI role |
|---|---|---|---|
| `brand-red` | `#CA050E` | `202, 5, 14` | primary CTA, active state, key accent |
| `brand-brown` | `#784F0C` | `120, 79, 12` | secondary dark accent, muted editorial contrast |
| `brand-gold` | `#F6D179` | `246, 209, 121` | highlight / premium accent |
| `brand-cream` | `#FBE2B2` | `251, 226, 178` | warm card / campaign surface |
| `brand-light-gold` | `#FFEBBB` | `255, 235, 187` | soft section/card surface |
| `brand-blush` | `#FFF2EB` | `255, 242, 235` | main light background / soft neutral |
| `brand-black` | `#000000` | `0, 0, 0` | primary text / maximum contrast |

Suggested CSS seed:

```css
:root {
  --gloskin-brand-red: #CA050E;
  --gloskin-brand-brown: #784F0C;
  --gloskin-brand-gold: #F6D179;
  --gloskin-brand-cream: #FBE2B2;
  --gloskin-brand-light-gold: #FFEBBB;
  --gloskin-brand-blush: #FFF2EB;
  --gloskin-brand-black: #000000;
}
```

Transparent mixes/tints may be derived for borders, shadows, overlays, disabled states and accessibility. Do not introduce a second competing brand palette.

Owner-supplied palette source checksum:

`COLOR PALETTE GLOSKIN.pdf`  
SHA-256: `f82160fe51c847a22abe31ee741948cfbbad4f92b0af3bf2c03adde7caf71754`

## Typography

Owner-supplied package: `Font Utama-20260818T082222Z-1-001.zip`  
SHA-256: `25e0abfc15a659f408dcb9dfea1fe29ab3bf3cb69937570a76a7342801730eb1`

Observed contents:

```text
Font Utama/Felix/Felixti.TTF
Font Utama/Graphik/GraphikBold.otf
Font Utama/Graphik/GraphikLight.otf
Font Utama/Graphik/GraphikLightItalic.otf
Font Utama/Graphik/GraphikMedium.otf
Font Utama/Graphik/GraphikMediumItalic.otf
Font Utama/Graphik/GraphikRegular.otf
Font Utama/Graphik/GraphikRegularItalic.otf
Font Utama/Graphik/GraphikSemibold.otf
```

Production role:

- Graphik: body copy, navigation, buttons, forms, labels, commerce, utilities.
- Felix Titling: brand/display and major editorial headings.
- Prefer the minimum required production faces and WOFF2 derivatives; do not preload every weight.
- The source package supplied for this task does not include a license file. Treat it as an owner-supplied brand source; do not invent license/redistribution claims.

## Raw wireframe provenance

Owner-supplied archive: `Struktur Web Gloskin-20260818T080628Z-1-001.zip`  
SHA-256: `3c6076d404c020fdbd975059b896a38741110596ba67d8ba9602cad2f6a7474c`

Observed files:

```text
Struktur Web Gloskin/home.jpeg
Struktur Web Gloskin/product skincare.jpeg
Struktur Web Gloskin/promo.jpeg
Struktur Web Gloskin/skincare.jpeg
Struktur Web Gloskin/tentang kami.jpeg
Struktur Web Gloskin/tentang-kami2.jpeg
Struktur Web Gloskin/treatment-2.jpeg
Struktur Web Gloskin/treatment.jpeg
```

Prototype interpretation is authoritative for normal implementation; raw handwriting exists to resolve ambiguity, not to override a clearer prototype interaction/layout decision.

## Binary handoff note

The GitHub connector used to create this workpack writes UTF-8 repository files but does not expose a local-file/binary upload parameter. Therefore this document records exact supplied filenames, contents and SHA-256 identities so an implementation agent with normal checkout/filesystem access can verify and place the owner-supplied binary packages without guessing. The palette values themselves are fully captured above and are sufficient to implement the design tokens. Do not substitute/download lookalike fonts from the web.
