# Public CSS cascade ownership

The public cascade is a dependency chain, not a collection of competing
overrides. Each recurring concern has one final owner; earlier layers expose
structure or tokens only. Conditional route layers load after the shared final
layer and own only their route-specific components.

| Concern | Canonical owner | Boundary |
|---|---|---|
| Fonts, semantic tokens, resets, base elements | `gloskin-ui1-core-base.css` | No component-specific visual redefinitions |
| Header mechanics, overlays, forms, Woo structure | `gloskin-ui1-core.css` | Structure and interaction states only |
| PDP layout | `gloskin-ui1-single-product-geometry.css` | Geometry not purchase-action brand skin |
| Navigation typography, editorial media, footer geometry | `gloskin-ui1-production.css` | All nav links inherit Graphik 400 from this layer |
| Quick Add | `gloskin-ui1-quickadd-polish.css` | Existing Woo/modal state owners remain unchanged |
| Shared loader/remove primitive | `gloskin-ui1-loader-system.css` | One visual primitive; no request lifecycle |
| Purchase-action and brand skin | `gloskin-ui1-brand-purchase-polish.css` | Presentation only |
| Product grid | `gloskin-ui1-product-grid.css` | Shared product-column matrix only |
| Shared sections, cards, inverse surfaces, body copy | `gloskin-ui1-prototype-refresh.css` | Final shared presentation owner, including section headings |
| Treatments consultation | `gloskin-ui1-consultation.css` | Treatments Hub only, after prototype refresh |
| Shop discovery | `gloskin-ui1-shop-discovery.css` | Shop only, after prototype refresh |

The machine-readable census and collision inventory is generated at
`tests/artifacts/css-collision-report.json` by `tests/css-ownership-audit.py`.
The contract fails on any exact collision, shorthand/longhand collision,
cross-file duplicate, duplicate handle/source, or undocumented `!important`.

The only `!important` allowlist boundaries are:

- WordPress-compatible screen-reader positioning.
- The global reduced-motion accessibility stop for descendants and pseudo-elements.
- Fluid Select2 width, which must override the widget's inline width.
