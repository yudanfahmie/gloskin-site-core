#!/usr/bin/env python3
"""Focused source contract for PDP gallery spacing and shared variable-modal presentation."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
core_css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css")
polish = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-quickadd-polish.css")
geometry = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-single-product-geometry.css")
runner = read("tests/check-runtime.sh")


def section(source, start, end):
    start_at = source.index(start)
    end_at = source.index(end, start_at)
    return source[start_at:end_at]


renderer = section(js, "function renderVariableFields(form, host)", "function syncChipPresentation(form)")
catalog = section(js, "function addCatalogPresentation(form)", "function bindCatalogMutationOwner(form)")
pdp = section(js, "function renderPdp(form, dock)", "function notifyPdpRequirement(form)")
pdp_identity = section(js, "function renderPdpIdentityLikeCatalog()", "function failOpenPdp(form, dock)")

# One field renderer, reused by both enhanced surfaces.
require(js.count("function renderVariableFields(form, host)") == 1, "variable fields must have one renderer")
require("renderVariableFields(form, fields)" in catalog, "Catalog must use the shared variable-field renderer")
require("renderVariableFields(form, fields)" in pdp, "PDP must use the shared variable-field renderer")
require(js.count("renderVariableFields(form, fields)") == 2, "only Catalog and PDP should invoke the shared renderer")
require("createChipGroup(selects[i], i, field, true)" in renderer, "shared renderer must create the canonical labelled chip fields")

# Native Woo fields stay canonical state and are hidden only after Catalog enhancement succeeds.
require("var nativeFields = form.querySelector('table.variations');" in catalog, "Catalog must retain the native Woo variations table")
require("if (!renderVariableFields(form, fields))" in catalog and "return false;" in catalog, "Catalog must fail open when field enhancement fails")
require("nativeFields.classList.add('gloskin-ui1-variable-native-fields--enhanced');" in catalog, "Catalog native fields need an explicit enhanced presentation marker")
require("nativeFields.hidden = true;" in catalog, "Catalog native fields must be presentation-hidden only after successful enhancement")
require("selects.forEach(function (select) { select.classList.add('gloskin-ui1-variable-select--enhanced'); });" in catalog, "native Woo selects must remain the state owner")
require("select.remove()" not in renderer + catalog + pdp, "shared presentation must never remove native Woo selects")

# The visible enhanced modal owns chips, not Woo's native table/Clear UI.
require("<table" not in pdp and "table.variations" not in pdp, "PDP modal must render zero native variations tables")
require("reset_variations" not in pdp, "PDP modal must render zero native Clear/reset controls")
require(".gloskin-ui1 .gloskin-ui1-variable-catalog-enhanced .reset_variations" in polish and "display:none;" in polish, "Catalog enhanced mode must hide native Clear/reset presentation")
require(".gloskin-ui1-variable-modal__fields" in polish and "gap:15px" in polish, "shared modal field spacing missing")
require(".gloskin-ui1-variable-field" in polish and "gap:7px" in polish, "shared field rhythm missing")

# PDP identity reuses Catalog's image/title/price kit rather than a reduced renderer.
require("identity.className = 'gloskin-ui1-quickadd__product gloskin-ui1-variable-modal__identity-converged';" in pdp_identity, "PDP identity must reuse the Catalog product identity class")
require("image.className = 'gloskin-ui1-quickadd__image';" in pdp_identity, "PDP image must reuse the Catalog image class")
require("price.className = 'gloskin-ui1-product-price';" in pdp_identity, "PDP price must reuse the Catalog price class")
require("'<div class=\"gloskin-ui1-quickadd__product\">'" in js, "Catalog product identity class missing")
require(".gloskin-ui1-quickadd__product{" in core_css and ".gloskin-ui1-quickadd__image{" in core_css, "shared identity geometry must remain in the established Quick Add kit")
require(".gloskin-ui1-variable-modal__identity-converged" in polish and "margin:0" in polish, "PDP identity convergence shim must remain layout-only")

# Chips, quantity, CTA and action-row geometry stay shared.
require("chip.className = 'gloskin-ui1-variable-chip';" in renderer, "canonical chip class missing")
require(".gloskin-ui1 .gloskin-ui1-quickadd__qty-control" in polish and ".gloskin-ui1 .gloskin-ui1-variable-modal__qty-proxy" in polish, "Catalog/PDP quantity controls must share one visual owner")
require(catalog.count("proxy.className = 'gloskin-ui1-variable-modal__cta';") == 1, "Catalog must use the canonical modal CTA")
require(pdp.count("proxy.className = 'gloskin-ui1-variable-modal__cta';") == 1, "PDP must use the canonical modal CTA")
for fragment in ("grid-template-columns:auto minmax(0,1fr)", "gap:12px", "align-items:stretch"):
    require(fragment in polish, f"shared action-row geometry missing: {fragment}")

# PDP modal keeps zero second form/state owner and the price/availability allowlist.
require("<form" not in pdp, "PDP modal must contain zero second form")
require("['.woocommerce-variation-price', '.woocommerce-variation-availability']" in js, "price/availability allowlist missing")
require(".woocommerce-variation-description" not in js, "variation description must not enter modal presentation")
require("state.innerHTML = nativeState.innerHTML" not in js, "arbitrary native variation state mirroring is forbidden")
require("new MutationObserver" not in js and "setInterval(" not in js, "no observer/polling variation owner may be added")

# Final gallery-only spacing: keep the main media untouched/full-bleed and space only Woo's thumbnail rail.
gallery_rule = "body.single-product.gloskin-ui1 .gloskin-ui1-commerce-native>div.product>.woocommerce-product-gallery .flex-control-thumbs{margin:12px 0 14px;padding-inline:clamp(10px,1.5vw,16px);box-sizing:border-box}"
require(gallery_rule in geometry, "canonical PDP thumbnail-rail spacing rule missing")
require("woocommerce-product-gallery__wrapper" not in geometry, "gallery spacing pass must not resize/inset the main image wrapper")

# No specificity debt or new runtime owner is allowed for this convergence pass.
require("!important" not in geometry and "!important" not in polish, "presentation convergence must add zero !important")
require("variable-modal-presentation-convergence-contract.py" in runner, "focused convergence contract must run through tests/check-runtime.sh")

print("variable-modal-presentation-convergence-contract: OK")
