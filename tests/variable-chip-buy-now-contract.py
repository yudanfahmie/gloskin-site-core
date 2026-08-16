#!/usr/bin/env python3
"""Variable chip parity, Buy Now prerequisite and claimed-submit ownership contract."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-quickadd-polish.css")
core = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
dock = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-purchase-dock.js")
assets = read("plugin/gloskin-site-core/config/assets.php")
runner = read("tests/check-runtime.sh")

chip_selector = ".gloskin-ui1 .gloskin-ui1-variable-chips .gloskin-ui1-variable-chip{"
require(chip_selector in css, "strong shared variable-chip presentation owner missing")
chip_start = css.index(chip_selector)
chip_end = css.index("}", chip_start) + 1
chip = css[chip_start:chip_end]
for declaration in (
    "min-height:42px",
    "padding:8px 14px",
    "border:1px solid var(--gloskin-border)",
    "border-radius:999px",
    "background:var(--gloskin-surface-soft,#fbf8f1)",
    "color:var(--gloskin-ink)",
    "font-size:.88rem",
    "font-weight:650",
):
    require(declaration in chip, f"shared variable-chip presentation missing: {declaration}")
selected_marker = '.gloskin-ui1 .gloskin-ui1-variable-chips .gloskin-ui1-variable-chip[aria-pressed="true"],'
require(selected_marker in css, "shared selected-chip state missing")
selected = css[css.index(selected_marker):css.index("}", css.index(selected_marker)) + 1]
require("border-color:var(--gloskin-accent)" in selected, "selected chip must keep accent border")
require("background:var(--gloskin-accent-soft)" in selected, "selected chip must use accent-soft surface")
require("background:var(--gloskin-accent);" not in selected, "selected chip must never become solid accent/red")
require("!important" not in css, "variable-product polish must not add !important")

reset_rule = ".gloskin-ui1 .gloskin-ui1-variable-catalog-enhanced .reset_variations{\n\tdisplay:none;\n}"
require(reset_rule in css, "catalog Clear hide must be scoped to successful enhancement")
require(".reset_variations{\n\tdisplay:none" not in css.replace(reset_rule, ""), "native reset control must not be hidden globally")

quick_start = core.index("function initQuickAdd()")
quick_end = core.index("/* -----------------------------------------------------------------\n\t * Shop catalog", quick_start)
quick = core[quick_start:quick_end]
require("select[name^=\"attribute_\"]" in quick, "variable UI must remain derived from native Woo selects")
require("chip.textContent = option.textContent.trim();" in quick, "chip label must derive from native option label")
require("chip.setAttribute('data-gloskin-variable-chip', option.value);" in quick, "chip value must derive from native option value")
require("localStorage" not in quick and "sessionStorage" not in quick, "variable UI must not invent cross-page stored selection state")

catalog_start = quick.index("function addCatalogPresentation(form)")
catalog_end = quick.index("function render(data)", catalog_start)
catalog = quick[catalog_start:catalog_end]
require("if (!renderVariableFields(form, fields)) {" in catalog, "catalog enhancement must remain transactional/fail-open via the shared renderer")
require("fields.forEach(function (created) { created.remove(); });" in quick, "shared renderer must roll back partially built chip groups")
require("form.classList.add('gloskin-ui1-variable-catalog-enhanced');" in catalog, "successful catalog enhancement flag missing")

proxy_start = quick.index("function setSubmitProxyBusy(busy)")
proxy_end = quick.index("function addCatalogPresentation(form)", proxy_start)
proxy = quick[proxy_start:proxy_end]
require("showTransientNotice('Pilih varian terlebih dahulu.', { tone: true });" in proxy, "modal incomplete CTA must keep reusable toast/tone")
require("focusSelectGroup(unresolved);" in proxy, "modal incomplete CTA must keep unresolved chip-group focus")
require("submit.click();" in proxy, "visible proxy must keep delegating to the SAME native submit")

# Final ownership state: no separate post-core commerce-closure module.
# Claimed-submit propagation is owned directly by the canonical core mutation
# owner (initSingleProductAjax()); modal busy mirroring and PDP identity
# convergence are owned directly by the canonical modal owner (initQuickAdd()).
require(not (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-commerce-closure.js").exists(), "post-core commerce closure module must not exist")
require("gloskin-ui1-commerce-closure" not in assets, "commerce closure asset registration must be removed")
require("function renderSingleProductViewCartLink" not in core, "PDP View Cart create-then-delete choreography must not exist")
require("wc-forward" not in core, "core must never create a PDP added_to_cart forward link")

submit_owner_start = core.index("function claimWooAjaxSubmit(event, form, lifecycleFactory)")
submit_owner_end = core.index("function handleSingleProductAddToCartSuccess", submit_owner_start)
submit_owner = core[submit_owner_start:submit_owner_end]
for forbidden in (
    "fetch(", "XMLHttpRequest", "MutationObserver", "ResizeObserver",
    "setInterval(", "addEventListener('scroll'", "window.confirm", "window.alert",
):
    require(forbidden not in submit_owner, f"canonical submit owner must not own {forbidden}")
require("event.stopPropagation();" in submit_owner, "native submit button click-guard missing from the canonical submit owner")
require("event.stopImmediatePropagation();" in submit_owner, "claimed-submit propagation guard missing from the canonical submit owner")
require("isWooSubmitBusy(submitter)" in submit_owner, "repeat-submit-while-busy guard missing from the canonical submit owner")

require("setSubmitProxyBusy(true);" in proxy and "aria-busy') === 'true'" in proxy, "visible proxy busy lifecycle missing from the modal owner")
require("added_to_cart.gloskinVariableModal" in quick, "confirmed-success settlement hook missing from the modal owner")
require("wc_fragment_refresh.gloskinVariableModal" in quick, "known-failure settlement hook missing from the modal owner")
require("renderPdpIdentityLikeCatalog" in quick, "PDP identity convergence utility missing from the modal owner")
require("woocommerce-product-gallery__image img" in quick, "PDP identity must derive presentation image from existing gallery DOM")
require("cloneNode(true)" in quick, "PDP image must be presentation clone only")
require(core.count("function ajaxAddToCart(") == 1, "a second cart mutation owner was introduced")

# Loading has one visual language and reuses the existing core spinner keyframe.
require(".gloskin-ui1-quickadd__loading::before" in css, "initial variable-modal spinner missing")
require(".gloskin-ui1-variable-modal__cta.is-loading::before" in css, "mutation proxy spinner missing")
require(css.count("@keyframes gloskin-atc-spin") == 0, "polish must reuse the existing canonical spinner keyframe")
require("animation:gloskin-atc-spin 650ms linear infinite" in css, "shared existing spinner language not reused")

# Spotlight must raise the canonical home stacking context, never override dock
# positioning owned by the Purchase Dock FSM.
require(".gloskin-ui1-action-spotlight__backdrop" in css and "z-index:9996" in css, "spotlight backdrop stacking contract missing")
home_selector = ".gloskin-ui1-purchase-dock-home:has(>[data-gloskin-purchase-dock].is-action-spotlight)"
require(home_selector in css, "spotlight must elevate Purchase Dock home stacking context")
home_block = css[css.index(home_selector):css.index("}", css.index(home_selector)) + 1]
require("position:relative" in home_block and "z-index:9997" in home_block, "spotlight home must sit above backdrop")
require("[data-gloskin-purchase-dock].is-action-spotlight{\n\tposition:" not in css, "spotlight must never override dock position")

spot_start = core.index("function dismissActionSpotlight()")
spot_end = core.index("function emptyStateIcon", spot_start)
spot = core[spot_start:spot_end]
for forbidden in ("MutationObserver", "ResizeObserver", "setInterval(", "addEventListener('scroll'", "requestAnimationFrame("):
    require(forbidden not in spot, f"spotlight must stay presentation-only without {forbidden}")
require("focus({ preventScroll: true })" in spot and "event.key === 'Escape'" in spot, "spotlight focus/Escape lifecycle changed")

# Buy Now prerequisite and canonical Woo state remain unchanged.
helper_marker = "function isNativeSubmitUnavailable(button) {"
require(helper_marker in dock, "Purchase Dock canonical Woo submit-state helper missing")
helper_start = dock.index(helper_marker)
helper_end = dock.index("\n\t\t}", helper_start) + len("\n\t\t}")
helper = dock[helper_start:helper_end]
for required in (
    "!button", "button.disabled", "button.classList.contains('disabled')",
    "button.classList.contains('wc-variation-selection-needed')",
    "button.classList.contains('wc-variation-is-unavailable')",
):
    require(required in helper, f"native submit availability helper missing Woo state: {required}")
require("source: 'buy-now'" in dock, "Purchase Dock must retain existing Buy Now prerequisite event")
require("submitBefore.setAttribute('data-gloskin-buy-now-redirect', '1');" in dock, "valid Buy Now redirect marker path changed")
require("submitBefore.click();" in dock, "valid Buy Now must retain SAME native submit path")
require("window.confirm" not in core + dock and "window.alert" not in core + dock, "native confirm/alert must not be introduced")
require("variable-chip-buy-now-contract.py" in runner, "focused commerce contract must run through tests/check-runtime.sh")
require("commerce-closure-browser-smoke.py" in runner, "focused commerce browser regression must run through tests/check-runtime.sh")

print("variable-chip-buy-now-contract: OK")