#!/usr/bin/env python3
"""Variable chip parity and Buy Now prerequisite presentation contract."""
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
runner = read("tests/check-runtime.sh")

# One semantic chip owner must win normally over `.gloskin-ui1-form button`
# for both catalog and PDP, without an escalation layer.
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
selected_start = css.index(selected_marker)
selected_end = css.index("}", selected_start) + 1
selected = css[selected_start:selected_end]
require("border-color:var(--gloskin-accent)" in selected, "selected chip must keep accent border")
require("background:var(--gloskin-accent-soft)" in selected, "selected chip must use accent-soft surface")
require("background:var(--gloskin-accent);" not in selected, "selected chip must never become solid accent/red")
require(":hover" in selected and ":focus-visible" in selected, "selected hover/focus must remain on the same soft state")
require("!important" not in css, "variable-product polish must not add !important")

# Native reset is hidden only after complete catalog enhancement succeeds.
reset_rule = ".gloskin-ui1 .gloskin-ui1-variable-catalog-enhanced .reset_variations{\n\tdisplay:none;\n}"
require(reset_rule in css, "catalog Clear hide must be scoped to successful enhancement")
require(".reset_variations{\n\tdisplay:none" not in css.replace(reset_rule, ""), "native reset control must not be hidden globally")

quick_start = core.index("function initQuickAdd()")
quick_end = core.index("/* -----------------------------------------------------------------\n\t * Shop catalog", quick_start)
quick = core[quick_start:quick_end]
require("select[name^=\"attribute_\"]" in quick, "variable UI must remain derived from native Woo selects")
require("chip.textContent = option.textContent.trim();" in quick, "chip label must derive from the native option label")
require("chip.setAttribute('data-gloskin-variable-chip', option.value);" in quick, "chip value must derive from the native option value")
require("localStorage" not in quick and "sessionStorage" not in quick, "variable UI must not invent cross-page stored selection state")
require("history.pushState" not in quick and "history.replaceState" not in quick, "variable UI must not encode custom selection state in history/URL")

catalog_start = quick.index("function addCatalogPresentation(form)")
catalog_end = quick.index("function render(data)", catalog_start)
catalog = quick[catalog_start:catalog_end]
require("groups.forEach(function (created) { created.remove(); });" in catalog, "catalog enhancement must remain transactional/fail-open")
require("form.classList.add('gloskin-ui1-variable-catalog-enhanced');" in catalog, "successful catalog enhancement flag missing")
require(
    catalog.index("form.classList.add('gloskin-ui1-variable-catalog-enhanced');") > catalog.index("syncModalPresentation(form);"),
    "catalog enhanced flag must be applied only after chip/native presentation setup succeeds",
)

# Existing modal incomplete CTA stays toast + tone + unresolved chip-group focus.
proxy_start = quick.index("function handleProxySubmit(form)")
proxy_end = quick.index("function addCatalogPresentation(form)", proxy_start)
proxy = quick[proxy_start:proxy_end]
require("showTransientNotice('Pilih varian terlebih dahulu.', { tone: true });" in proxy, "modal incomplete CTA must keep reusable toast/tone")
require("focusSelectGroup(unresolved);" in proxy, "modal incomplete CTA must keep unresolved chip-group focus")

# Tiny spotlight utility: one body backdrop, Escape/timeout cleanup and focus,
# with no geometry observers, polling, scroll listener or animation RAF owner.
spot_start = core.index("function dismissActionSpotlight()")
spot_end = core.index("function emptyStateIcon", spot_start)
spot = core[spot_start:spot_end]
for required in (
    "function showActionSpotlight(target)",
    "dismissActionSpotlight();",
    "gloskin-ui1-action-spotlight__backdrop",
    "backdrop.setAttribute('aria-hidden', 'true');",
    "is-action-spotlight-target",
    "is-action-spotlight",
    "focus({ preventScroll: true })",
    "target.focus();",
    "event.key === 'Escape'",
    "root.setTimeout(dismissActionSpotlight, ACTION_SPOTLIGHT_DURATION_MS)",
):
    require(required in spot, f"Buy Now spotlight contract missing: {required}")
for forbidden in ("MutationObserver", "ResizeObserver", "setInterval(", "addEventListener('scroll'", "requestAnimationFrame("):
    require(forbidden not in spot, f"spotlight must stay presentation-only without {forbidden}")
require("ACTION_SPOTLIGHT_DURATION_MS = 2200" in core, "spotlight timeout must roughly match the reusable toast duration")
require("gloskin-action-spotlight-pulse" in css, "spotlight attention presentation missing")
require("@media (prefers-reduced-motion:reduce)" in css and "animation:none" in css, "spotlight reduced-motion static state missing")
require(".gloskin-ui1-action-spotlight__backdrop" in css and "z-index:9996" in css, "spotlight backdrop stacking contract missing")
require("[data-gloskin-purchase-dock].is-action-spotlight" in css and "z-index:9997" in css, "Purchase Dock spotlight foreground stacking contract missing")

# Invalid Buy Now uses the existing prerequisite event but does not render/open
# the modal. Valid Buy Now remains entirely owned by the unchanged dock path.
event_marker = "document.addEventListener('gloskin:variable-product-modal-request', function (event) {"
event_start = quick.index(event_marker)
event_end = quick.index("var existingDock", event_start)
event_block = quick[event_start:event_end]
branch_start = event_block.index("if ('buy-now' === detail.source)")
branch_end = event_block.index("if (!renderPdp(detail.form, detail.dock))", branch_start)
invalid_branch = event_block[branch_start:branch_end]
require("showTransientNotice('Pilih varian terlebih dahulu.', { tone: true });" in invalid_branch, "invalid Buy Now must reuse toast + attention tone")
require("showActionSpotlight(trigger);" in invalid_branch, "invalid Buy Now must spotlight the existing variation trigger")
require("return;" in invalid_branch, "invalid Buy Now must stop before modal rendering/mutation")
require("renderPdp(" not in invalid_branch and "overlay.open(" not in invalid_branch, "invalid Buy Now must not auto-open the modal")

require("source: 'buy-now'" in dock, "Purchase Dock must retain the existing Buy Now prerequisite event")
require("submitBefore.setAttribute('data-gloskin-buy-now-redirect', '1');" in dock, "valid Buy Now redirect marker path changed")
require("submitBefore.click();" in dock, "valid Buy Now must retain the same native submit click path")
require("window.confirm" not in core + dock and "window.alert" not in core + dock, "native confirm/alert must not be introduced")
require(core.count("function ajaxAddToCart(") == 1, "a second cart mutation owner was introduced")
require("variable-chip-buy-now-contract.py" in runner, "focused chip/Buy Now contract must run through tests/check-runtime.sh")

print("variable-chip-buy-now-contract: OK")
