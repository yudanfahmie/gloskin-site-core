#!/usr/bin/env python3
"""Presentation contract for the one active Gloskin quantity stepper."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


polish = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-quickadd-polish.css")
base = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css")
core = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css")
geometry = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-single-product-geometry.css")
assets = read("plugin/gloskin-site-core/config/assets.php")
core_js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
dock_js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-purchase-dock.js")

# One final-loaded component aliases all stable behavioral hooks. The semantic
# hook exists for future markup without requiring a controller/FSM change now.
dock_owner = "body.single-product.gloskin-ui1 .gloskin-ui1-commerce-native>div.product>.gloskin-ui1-purchase-dock-home>.gloskin-ui1-purchase-dock .gloskin-ui1-purchase-dock__qty-control"
owner_selector = (
    ".gloskin-ui1 .gloskin-ui1-qty-stepper,\n"
    ".gloskin-ui1 .gloskin-ui1-quickadd__qty-control,\n"
    ".gloskin-ui1 .gloskin-ui1-variable-modal__qty-proxy,\n"
    + dock_owner
)
require(owner_selector in polish, "one shared quantity-stepper owner must alias catalog, PDP modal and Purchase Dock")
owner_start = polish.index(owner_selector)
owner_end = polish.index("}", owner_start) + 1
owner = polish[owner_start:owner_end]
for declaration in (
    "display:grid",
    "grid-template-columns:34px 44px 34px",
    "width:fit-content",
    "min-height:46px",
    "border:1px solid var(--gloskin-border)",
    "border-radius:var(--gloskin-radius-sm)",
    "background:var(--gloskin-bg)",
    "color:var(--gloskin-text)",
    "overflow:hidden",
):
    require(declaration in owner, f"shared quantity-stepper geometry missing: {declaration}")

value_start = polish.index(".gloskin-ui1 .gloskin-ui1-quickadd__qty-control input.qty,")
value_end = polish.index("}", value_start) + 1
value = polish[value_start:value_end]
for declaration in ("width:44px", "min-width:44px", "max-width:44px", "min-height:44px", "background:transparent", "color:var(--gloskin-text)"):
    require(declaration in value, f"shared quantity value geometry missing: {declaration}")
require(dock_owner + " input.qty" in value, "Purchase Dock native qty must use the shared value geometry")
require(".gloskin-ui1 .gloskin-ui1-variable-modal__qty-value" in value, "PDP proxy value must use the shared value geometry")

button_start = polish.index(".gloskin-ui1 .gloskin-ui1-quickadd__qty-control .gloskin-ui1-quickadd__qty-minus,")
button_end = polish.index("}", button_start) + 1
buttons = polish[button_start:button_end]
for declaration in (
    "display:grid",
    "width:34px",
    "min-width:34px",
    "min-height:44px",
    "background:transparent",
    "color:var(--gloskin-text)",
    "border-radius:0",
):
    require(declaration in buttons, f"shared quantity button presentation missing: {declaration}")
require(".gloskin-ui1 .gloskin-ui1-variable-modal__qty-proxy button" in buttons, "PDP proxy buttons must use the shared button geometry")
require(dock_owner + " .gloskin-ui1-purchase-dock__qty-minus" in buttons, "Purchase Dock minus must use shared button geometry")
require(dock_owner + " .gloskin-ui1-purchase-dock__qty-plus" in buttons, "Purchase Dock plus must use shared button geometry")

# Generic Form Kit buttons are accent-filled by design. The shared Quick Add
# selector deliberately includes the stepper parent, giving it higher normal
# specificity; no !important is needed and +/- remain neutral.
require(".gloskin-ui1-form button" in base and "background:var(--gloskin-accent)" in base, "generic Form Kit button baseline missing")
require(".gloskin-ui1 .gloskin-ui1-quickadd__qty-control .gloskin-ui1-quickadd__qty-minus" in buttons, "Quick Add minus must be scoped through shared stepper parent")
require(".gloskin-ui1 .gloskin-ui1-quickadd__qty-control .gloskin-ui1-quickadd__qty-plus" in buttons, "Quick Add plus must be scoped through shared stepper parent")
require("background:transparent" in buttons and "var(--gloskin-accent)" not in buttons, "modal +/- must never inherit solid accent fill")
require("!important" not in polish, "shared quantity presentation must add ZERO !important")

# Separators, hover and disabled state are also one shared treatment.
require("border-right:1px solid var(--gloskin-border)" in polish, "shared minus separator missing")
require("border-left:1px solid var(--gloskin-border)" in polish, "shared plus separator missing")
require("background:var(--gloskin-surface)" in polish, "shared stepper hover surface missing")
require("opacity:.4" in polish and "color:var(--gloskin-muted)" in polish, "shared disabled presentation missing")

# Lower-level compatibility fallbacks may remain because they also protect
# native/no-JS geometry, but they may not define a different visual language.
for declaration in (
    "min-height:46px",
    "width:44px",
    "width:34px",
    "border:1px solid var(--gloskin-border)",
    "border-radius:var(--gloskin-radius-sm)",
    "background:var(--gloskin-bg)",
    "background:transparent",
    "color:var(--gloskin-text)",
):
    require(declaration in core + geometry, f"compatibility fallback diverged from shared geometry: {declaration}")

# Sold individually still hides only the presentation proxy and lets the CTA
# span the existing action row; Woo's native qty is not removed or replaced.
qty_hidden = ".gloskin-ui1-variable-modal__actions.is-quantity-hidden .gloskin-ui1-variable-modal__qty-proxy"
cta_hidden = ".gloskin-ui1-variable-modal__actions.is-quantity-hidden .gloskin-ui1-variable-modal__cta"
require(qty_hidden in polish and "display:none" in polish[polish.index(qty_hidden):polish.index("}", polish.index(qty_hidden)) + 1], "sold-individually must hide PDP quantity proxy")
require(cta_hidden in polish and "grid-column:1 / -1" in polish[polish.index(cta_hidden):polish.index("}", polish.index(cta_hidden)) + 1], "sold-individually CTA must stay full width")

# Purchase Dock variable trigger is a light actionable control, independent of
# Woo's disabled native variation submit. Selected-summary text keeps this class.
dock_trigger = "[data-gloskin-purchase-dock] .gloskin-ui1-variable-pdp-trigger"
require(dock_trigger in polish, "Purchase Dock variable trigger must be explicitly scoped")
trigger_start = polish.index(dock_trigger)
trigger_end = polish.index("}", trigger_start) + 1
trigger = polish[trigger_start:trigger_end]
for declaration in (
    "min-height:46px",
    "border:1px solid var(--gloskin-inverse)",
    "border-radius:var(--gloskin-action-radius)",
    "background:var(--gloskin-inverse)",
    "color:var(--gloskin-accent-strong)",
    "font-weight:700",
):
    require(declaration in trigger, f"Purchase Dock trigger contrast missing: {declaration}")
trigger_region = polish[trigger_start:polish.index(".gloskin-ui1-toast-region", trigger_start)]
require(":disabled" not in trigger_region, "Pilih/Ubah Varian trigger must not inherit Woo disabled presentation")
require("single_add_to_cart_button" not in trigger_region, "Pilih/Ubah Varian trigger must not be visually coupled to native submit")

# Canonical active ownership comes from load order plus equal/higher selector
# specificity, not a specificity war and never !important.
core_pos = assets.index("'gloskin-ui1-core' => array(")
geometry_pos = assets.index("'gloskin-ui1-single-product-geometry' => array(")
production_pos = assets.index("'gloskin-ui1-production' => array(")
polish_pos = assets.index("'gloskin-ui1-quickadd-polish' => array(")
require(core_pos < geometry_pos < production_pos < polish_pos, "shared variable-product presentation must be the final active layer")
require("array( 'gloskin-ui1-production' )" in assets[polish_pos:], "variable-product presentation must retain production dependency")

# Presentation-only convergence: state/mutation owners stay byte-for-byte in
# their existing JS files and continue to use native Woo controls.
quickadd_start = core_js.index("\tfunction initQuickAdd() {")
quickadd_end = core_js.index("\n\tfunction ", quickadd_start + 1)
quickadd_js = core_js[quickadd_start:quickadd_end]
for forbidden in ("submit.disabled = false", "removeAttribute('disabled')", "MutationObserver", "setInterval"):
    require(forbidden not in quickadd_js, f"Quick Add ownership regression: {forbidden}")
require("new MutationObserver" not in dock_js and "fetch(" not in dock_js and "cloneNode(" not in dock_js, "Purchase Dock must keep native/FSM ownership unchanged")
require("var input = quantity.querySelector('input.qty');" in core_js and "var input = quantity.querySelector('input.qty');" in dock_js, "catalog/dock must retain SAME native input.qty")

print("variable-quantity-presentation-contract: OK")
