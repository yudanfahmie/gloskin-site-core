#!/usr/bin/env python3
"""Static contract for the one reusable Gloskin variable-product modal."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-quickadd-polish.css")
core_js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
dock_js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-purchase-dock.js")
assets = read("plugin/gloskin-site-core/config/assets.php")
adapter = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-woocommerce-adapter.php")
helper = read("plugin/gloskin-site-core/templates/parts/template-helpers.php")
runtime = read("tests/check-runtime.sh")
polish_js = ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-quickadd-polish.js"

# One presentation asset layer, still bounded to the existing commerce owners.
require("!important" not in css, "variable modal CSS must add zero !important declarations")
require(".gloskin-ui1-quickadd__form .table-container" in css, "Quick Add table wrapper reset must remain scoped")
for declaration in ("margin:0", "padding:0", "border:0", "border-radius:0", "background:transparent", "box-shadow:none"):
    require(declaration in css, f"transparent wrapper reset missing: {declaration}")
require(".gloskin-ui1-variable-chip" in css, "variation chip presentation missing")
require("min-height:42px" in css, "variation chips must keep practical touch height")
require("grid-template-columns:auto minmax(0,1fr)" in css, "quantity + CTA bottom row must be auto/flexible")
require("gap:12px" in css, "quantity + CTA row must keep compact spacing")
require("is-quantity-hidden" in css, "sold-individually/full-width CTA presentation missing")
require("@media (max-width:340px)" in css, "very narrow stacking safeguard missing")
require(".gloskin-ui1-toast-region" in css and "@media (prefers-reduced-motion:reduce)" in css, "toast/reduced-motion presentation missing")
require(".gloskin-ui1-variable-select--enhanced" in css, "progressively enhanced native select presentation class missing")
require(".gloskin-ui1-variable-pdp-enhanced" in css, "PDP native fallback boundary missing")

# The historical secondary Quick Add controller stays deleted.
require(not polish_js.exists(), "secondary Quick Add JS controller must stay deleted")
require("assets/js/gloskin-ui1-quickadd-polish.js" not in assets, "secondary Quick Add controller must not be registered")
require("assets/css/gloskin-ui1-quickadd-polish.css" in assets, "approved variable modal CSS must remain registered")

quickadd_start = core_js.index("\tfunction initQuickAdd() {")
quickadd_end = core_js.index("\n\tfunction ", quickadd_start + 1)
quickadd_js = core_js[quickadd_start:quickadd_end]

# Chips are derived from actual Woo option nodes and drive the same select.
require("select[name^=\"attribute_\"]" in quickadd_js, "native Woo attribute selects must remain the canonical source")
require("select.options" in quickadd_js, "chips must be derived from real option elements")
require("chip.type = 'button'" in quickadd_js, "variation chip must be a presentation button")
require("chip.textContent = option.textContent.trim()" in quickadd_js, "chip text must come from Woo option text")
require("chip.disabled = !!option.disabled" in quickadd_js, "disabled Woo option must create disabled chip")
require("group.setAttribute('role', 'group')" in quickadd_js, "chip group accessible role missing")
require("group.setAttribute('aria-labelledby', label.id)" in quickadd_js, "chip group must reference the real Woo label")
require("select.value = chip.getAttribute('data-gloskin-variable-chip')" in quickadd_js, "chip must write the SAME native select")
require("select.dispatchEvent(new Event('change', { bubbles: true }))" in quickadd_js, "chip must dispatch native bubbling change")
require("woocommerce_update_variation_values.gloskinVariableModal" in quickadd_js, "Woo option availability sync event missing")
require("reset_data.gloskinVariableModal" in quickadd_js and "found_variation.gloskinVariableModal" in quickadd_js, "Woo reset/found variation sync missing")
require("attributeSelects(form).forEach" in quickadd_js, "multiple Woo attributes must be enhanced independently")
require("MutationObserver" not in quickadd_js, "variable modal must use ZERO MutationObserver")
require("setInterval" not in quickadd_js, "variable modal must use ZERO polling")

# Visible CTA is a non-mutating proxy; the native Woo button remains state owner.
require("data-gloskin-variable-submit-proxy" in quickadd_js, "always-active modal CTA proxy missing")
require("Pilih varian terlebih dahulu." in quickadd_js, "incomplete selection notice missing")
require("Varian yang dipilih belum tersedia." in quickadd_js, "unavailable variation notice missing")
require("submit.click();" in quickadd_js, "valid proxy must trigger the same native Woo submit")
for forbidden in (
    "submit.disabled = false",
    "nativeSubmit.disabled = false",
    "removeAttribute('disabled')",
    'removeAttribute("disabled")',
    "variationField.value =",
    "variation_id.value =",
):
    require(forbidden not in quickadd_js, f"Woo variation/submit authority must not be overridden: {forbidden}")

# Existing mutation bridge remains the one path; PDP presentation never fetches a second form.
require("ajaxAddToCart(form, submitter" in quickadd_js, "catalog must retain the existing AJAX bridge")
require("nativeFallbackSubmit(form, submitter)" in quickadd_js, "catalog native fallback must remain")
render_pdp_start = quickadd_js.index("\t\tfunction renderPdp(form, dock) {")
render_pdp_end = quickadd_js.index("\n\t\tfunction notifyPdpRequirement", render_pdp_start)
render_pdp = quickadd_js[render_pdp_start:render_pdp_end]
require("fetch(" not in render_pdp and "products/quick-add" not in render_pdp, "PDP modal must never fetch/render a second Woo form")
for forbidden in ("<form", "form.cart", "variations_form", "variation_id", "type=\"number\"", "input.qty"):
    require(forbidden not in render_pdp, f"PDP modal presentation must not contain a second native Woo control: {forbidden}")
require("data-gloskin-variable-qty-value" in render_pdp, "PDP quantity proxy must be presentation-only text/buttons")
require("getNativeQuantityInput(currentForm)" in quickadd_js, "PDP quantity proxy must write the existing native qty")

# PDP progressive enhancement requires one existing native form and derives trigger copy from native selection.
prepare_start = quickadd_js.index("\t\tfunction preparePdp(form, dock) {")
prepare_end = quickadd_js.index("\n\t\tfunction renderPdp", prepare_start)
prepare_pdp = quickadd_js[prepare_start:prepare_end]
require("1 !== dock.querySelectorAll('form.cart').length" in prepare_pdp, "PDP enhancement must require exactly one primary form.cart")
require("dock.querySelector('form.cart') !== form" in prepare_pdp, "PDP modal must bind the same existing form node")
require("form.classList.add('gloskin-ui1-variable-pdp-enhanced')" in prepare_pdp, "native PDP controls may hide only after enhancement readiness")
require("selectedSummary(form) || 'Pilih Varian'" in quickadd_js, "PDP trigger copy must derive from native select state")

# Toast/audio: one region, embedded short tone, no external request or stacking framework.
require("function showTransientNotice(message, options)" in core_js, "reusable transient notice owner missing")
require("querySelector('.gloskin-ui1-toast-region')" in core_js, "notice owner must reuse one region")
require("setAttribute('role', 'status')" in core_js and "setAttribute('aria-live', 'polite')" in core_js and "setAttribute('aria-atomic', 'true')" in core_js, "toast ARIA contract missing")
require("data:audio/wav;base64," in core_js and "NOTICE_SOUND_URI" in core_js, "embedded attention tone missing")
require("NOTICE_SOUND_COOLDOWN_MS = 600" in core_js, "attention tone cooldown must stay bounded")
require("noticeAudio.volume = 0.12" in core_js and "noticeAudio.loop = false" in core_js, "attention tone volume/loop contract missing")
require("document.visibilityState !== 'visible'" in core_js, "attention tone must not play for hidden documents")

# Purchase Dock remains one FSM/native-node owner; only explicit handoff events are added.
require("new MutationObserver" not in dock_js, "Purchase Dock must not add a disabled-state MutationObserver")
require("gloskin:purchase-dock-ready" in dock_js, "Purchase Dock must hand the same form reference to the modal owner")
require("gloskin:variable-product-modal-request" in dock_js, "Buy Now invalid variation must route to the reusable modal")
require("detail: { dock: dock, form: formBefore" in dock_js, "PDP handoff must carry the same dock/form references")
require("submitBefore.click();" in dock_js, "valid Buy Now must still trigger the same native submit")
require("cloneNode" not in dock_js, "Purchase Dock must not clone the Woo form")
require("fetch(" not in dock_js, "Purchase Dock must not become a form/cart request owner")
require("formBefore.querySelector('.single_add_to_cart_button') === submitBefore" in dock_js, "native submit identity preservation check must remain")
require("formBefore.querySelector('.quantity') === quantityBefore" in dock_js, "native quantity identity preservation check must remain")
require("sameNodeList(variationSelectsBefore, afterSelects)" in dock_js, "native select identity preservation check must remain")

# Public commerce language semantics from the previous release stay intact.
require("woocommerce_product_single_add_to_cart_text" in adapter and "woocommerce_product_add_to_cart_text" in adapter, "Woo storefront CTA filters must remain")
require("__( 'Pilih Varian', 'gloskin-site-core' )" in helper, "variable catalog trigger semantic label must remain Pilih Varian")
require("python tests/quick-add-polish-contract.py" in runtime, "variable modal contract must run in the standard runtime suite")

print("quick-add-polish-contract: OK")
