#!/usr/bin/env python3
"""Static contract for bounded Quick Add modal presentation polish."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-quickadd-polish.css")
js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-quickadd-polish.js")
assets = read("plugin/gloskin-site-core/config/assets.php")
runtime = read("tests/check-runtime.sh")

# The polish layer must stay bounded to Quick Add and never use cascade force.
require("!important" not in css, "Quick Add polish must add zero !important declarations")
require(".gloskin-ui1-quickadd__form .table-container" in css, "runtime table wrapper must be neutralized only inside Quick Add")
for declaration in (
    "margin:0",
    "padding:0",
    "border:0",
    "border-radius:0",
    "background:transparent",
    "box-shadow:none",
):
    require(declaration in css, f"inner wrapper reset missing: {declaration}")
require("grid-template-columns:minmax(0,1fr) auto" in css, "desktop Quick Add must allocate primary space to variations and compact space to quantity")
require("form.cart>table.variations" in css, "unwrapped native Woo variation table fallback missing")
require("grid-column:2" in css and "justify-self:end" in css, "desktop quantity must stay compact and right aligned")
require(".single_add_to_cart_button" in css and "grid-column:1 / -1" in css and "width:100%" in css, "single native CTA must own the full bottom row")
require("@media (max-width:600px)" in css, "mobile Quick Add stack safeguard missing")
require("display:none" not in css, "Quick Add polish must not hide duplicate controls instead of preserving one owner")

# Copy normalization targets the same native Woo CTA and owns no commerce state.
require("Tambahkan ke keranjang" in js, "Quick Add CTA Indonesian label normalization missing")
require("[data-gloskin-quickadd-body]" in js, "CTA normalization must be scoped to the Quick Add body")
require(".single_add_to_cart_button" in js, "CTA normalization must target the existing native Woo button")
require("MutationObserver" in js, "CTA copy must follow each freshly projected native Woo form")
for forbidden in (
    "fetch(",
    "wc_variation_form",
    "stepUp",
    "stepDown",
    "variation_id",
    ".disabled",
    "setAttribute('disabled'",
    'setAttribute("disabled"',
):
    require(forbidden not in js, f"presentation-only Quick Add polish must not own commerce behavior: {forbidden}")

# Canonical AssetService remains the one registration/enqueue owner.
require("assets/css/gloskin-ui1-quickadd-polish.css" in assets, "Quick Add polish stylesheet must be registered")
require("array( 'gloskin-ui1-production' )" in assets[assets.index("gloskin-ui1-quickadd-polish"):], "Quick Add polish CSS must load after the Gloskin/Woo presentation chain")
require("assets/js/gloskin-ui1-quickadd-polish.js" in assets, "Quick Add polish controller must be registered")
require("array( 'gloskin-ui1-core' )" in assets[assets.index("assets/js/gloskin-ui1-quickadd-polish.js"):], "Quick Add polish JS must depend on the canonical core controller")
require("python tests/quick-add-polish-contract.py" in runtime, "Quick Add polish contract must run in the standard runtime suite")

print("quick-add-polish-contract: OK")
