#!/usr/bin/env python3
"""Static contract for canonical Quick Add CTA ownership and approved visual polish."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-quickadd-polish.css")
core_js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
assets = read("plugin/gloskin-site-core/config/assets.php")
adapter = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php")
helper = read("plugin/gloskin-site-core/templates/parts/template-helpers.php")
runtime = read("tests/check-runtime.sh")
polish_js = ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-quickadd-polish.js"

# Approved visual contract remains exactly a bounded Quick Add presentation layer.
require("!important" not in css, "Quick Add polish must add zero !important declarations")
require(".gloskin-ui1-quickadd__form .table-container" in css, "runtime table wrapper must stay neutralized only inside Quick Add")
for declaration in (
    "margin:0",
    "padding:0",
    "border:0",
    "border-radius:0",
    "background:transparent",
    "box-shadow:none",
):
    require(declaration in css, f"inner wrapper reset missing: {declaration}")
require("grid-template-columns:minmax(0,1fr) auto" in css, "desktop Quick Add must keep selector primary and quantity compact")
require("form.cart>table.variations" in css, "unwrapped native Woo variation table fallback missing")
require("grid-column:2" in css and "justify-self:end" in css, "desktop quantity must stay compact and right aligned")
require(".single_add_to_cart_button" in css and "grid-column:1 / -1" in css and "width:100%" in css, "single native CTA must keep the full bottom row")
require("@media (max-width:600px)" in css and "grid-column:1" in css[css.index("@media (max-width:600px)"):], "mobile Quick Add stack safeguard missing")
require("display:none" not in css, "Quick Add polish must not hide duplicate controls instead of preserving one owner")

# CTA ownership must live inside the existing canonical Quick Add render lifecycle.
quickadd_start = core_js.index("\tfunction initQuickAdd() {")
quickadd_end = core_js.index("\n\tfunction ", quickadd_start + 1)
quickadd_js = core_js[quickadd_start:quickadd_end]
render_start = quickadd_js.index("\t\tfunction render(data) {")
render_end = quickadd_js.index("\n\t\tfunction open(", render_start)
render_js = quickadd_js[render_start:render_end]
require("MutationObserver" not in quickadd_js, "Quick Add must have ZERO MutationObserver ownership")
require(not polish_js.exists(), "secondary Quick Add polish controller must be deleted")
require("assets/js/gloskin-ui1-quickadd-polish.js" not in assets, "secondary Quick Add polish controller must not be registered")
body_idx = render_js.index("body.innerHTML = html;")
form_idx = render_js.index("var form = body.querySelector('form.cart');")
button_idx = render_js.index("var addToCartButton = form.querySelector('.single_add_to_cart_button');")
label_idx = render_js.index("addToCartButton.textContent = 'Tambahkan ke keranjang';")
require(body_idx < form_idx < button_idx < label_idx, "CTA normalization must occur on the same native Woo button inside canonical render(data), after projection insertion")
for forbidden in ("setTimeout", "setInterval", "observer.observe"):
    require(forbidden not in render_js, f"Quick Add CTA normalization must not add asynchronous ownership: {forbidden}")

# Storefront direct-add wording is normalized at Woo's native label filters while
# variable/view/detail semantics remain owned by their product types/templates.
require("woocommerce_product_single_add_to_cart_text" in adapter, "native single-product CTA label filter missing")
require("woocommerce_product_add_to_cart_text" in adapter, "native loop CTA label filter missing")
require("storefront_single_add_to_cart_text" in adapter and "storefront_loop_add_to_cart_text" in adapter, "WooCommerceAdapter must own public direct-add wording")
require(adapter.count("Tambahkan ke keranjang") >= 3, "direct-add storefront wording must use the normalized Indonesian copy")
require("Tambah ke keranjang" not in adapter, "legacy Gloskin direct-add wording must not leak from normalized product data")
require("__( 'Pilih Varian', 'gloskin-site-core' )" in helper, "variable-card semantic label must remain Pilih Varian")
require("'simple'" in adapter[adapter.index("storefront_loop_add_to_cart_text"):], "loop wording filter must stay limited to direct simple-product adds")

# Canonical AssetService remains the sole asset registration owner; only CSS polish remains.
require("assets/css/gloskin-ui1-quickadd-polish.css" in assets, "approved Quick Add polish stylesheet must remain registered")
require("python tests/quick-add-polish-contract.py" in runtime, "Quick Add ownership/polish contract must run in the standard runtime suite")

print("quick-add-polish-contract: OK")
