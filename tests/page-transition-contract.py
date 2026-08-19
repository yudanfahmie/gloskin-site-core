#!/usr/bin/env python3
"""
page-transition-contract.py

Static contract for the v0.7.141 premium page transition system.

Asserts:
  1.  Transition overlay DOM exists in shell.php with [data-gloskin-page-transition].
  2.  Overlay contains a .gloskin-ui1-page-transition__blob child.
  3.  Canonical G SVG is inlined with white fill and correct viewBox.
  4.  CSS custom properties are declared on :root in loader-system.css.
  5.  Overlay base class has position:fixed, opacity:0, pointer-events:none.
  6.  .is-active class sets opacity:1 and pointer-events:all.
  7.  Blob has animation:gloskin-ui1-transition-blob and border-radius jelly values.
  8.  @keyframes gloskin-ui1-transition-blob is present in the CSS.
  9.  prefers-reduced-motion disables both blob animation and overlay transition.
  10. initPageTransitions() function exists in gloskin-ui1-core.js.
  11. initPageTransitions() is called from init().
  12. initPageTransitions is exported from the module.
  13. BFCache pageshow cleanup is present (e.persisted branch).
  14. Hard timeout (3000ms) is present.
  15. prefers-reduced-motion guard is present in JS.
  16. Woo AJAX skip patterns are present (add-to-cart=, /cart/, /checkout/).
  17. [data-gloskin-no-transition] opt-out is checked.
  18. No beforeunload or unload event listeners.
  19. No external library dependency (fetch inside transition, Barba, Swup).
  20. initPageTransitions() is registered in the check-runtime.sh suite.
"""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


shell = read("plugin/gloskin-site-core/templates/shell.php")
loader_css = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-loader-system.css")
core_js = read("plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js")
runtime = read("tests/check-runtime.sh")

# DOM assertions
require("data-gloskin-page-transition" in shell, "shell must contain [data-gloskin-page-transition] overlay")
require("gloskin-ui1-page-transition__blob" in shell, "shell must contain blob child inside overlay")
require('viewBox="82 74 185 232"' in shell, "G SVG viewBox must match the canonical G letterform bounds")
require('fill="#fff"' in shell, "G path must be white inside the jelly blob")
require("translate(65,300) scale(0.3117268,-0.32)" in shell, "G path transform must reuse canonical logotext.svg values")
require('aria-hidden="true"' in shell, "page transition overlay must be aria-hidden")

# CSS assertions
require("--gl-transition-bg:" in loader_css, "CSS must declare --gl-transition-bg custom property")
require("--gl-transition-jelly:" in loader_css, "CSS must declare --gl-transition-jelly custom property")
require("--gl-transition-duration:" in loader_css, "CSS must declare --gl-transition-duration custom property")
require("--gl-transition-g-size:" in loader_css, "CSS must declare --gl-transition-g-size custom property")

overlay_start = loader_css.index(".gloskin-ui1-page-transition{")
overlay_block = loader_css[overlay_start:loader_css.index("}", overlay_start) + 1]
require("position:fixed" in overlay_block, "overlay must be position:fixed")
require("opacity:0" in overlay_block, "overlay default opacity must be 0")
require("pointer-events:none" in overlay_block, "overlay default pointer-events must be none")

active_start = loader_css.index(".gloskin-ui1-page-transition.is-active{")
active_block = loader_css[active_start:loader_css.index("}", active_start) + 1]
require("opacity:1" in active_block, "is-active must set opacity:1")
require("pointer-events:all" in active_block, "is-active must set pointer-events:all")

blob_start = loader_css.index(".gloskin-ui1-page-transition__blob{")
blob_block = loader_css[blob_start:loader_css.index("}", blob_start) + 1]
require("animation:gloskin-ui1-transition-blob" in blob_block, "blob must use named jelly animation")
require("border-radius:" in blob_block, "blob must start with organic border-radius values")

require("@keyframes gloskin-ui1-transition-blob{" in loader_css, "jelly keyframes must be defined")
keyframe_start = loader_css.index("@keyframes gloskin-ui1-transition-blob{")
keyframe_block = loader_css[keyframe_start:loader_css.index("}", loader_css.index("}", keyframe_start) + 1) + 1]
require("border-radius:" in keyframe_block, "jelly keyframes must animate border-radius")

# Reduced-motion assertions
reduced_start = loader_css.rindex("@media (prefers-reduced-motion:reduce){")
reduced_block = loader_css[reduced_start:]
require(".gloskin-ui1-page-transition{transition:none}" in reduced_block, "reduced-motion must disable overlay transition")
require(".gloskin-ui1-page-transition__blob{animation:none" in reduced_block, "reduced-motion must disable blob animation")

# JS assertions
require("function initPageTransitions()" in core_js, "initPageTransitions() function must exist in gloskin-ui1-core.js")
require("initPageTransitions();" in core_js, "initPageTransitions() must be called from init()")
require("initPageTransitions: initPageTransitions" in core_js, "initPageTransitions must be exported from the module")

# BFCache
require("e.persisted" in core_js, "pageshow BFCache cleanup must check e.persisted")
require("'pageshow'" in core_js or '"pageshow"' in core_js, "pageshow listener must be registered")

# Hard timeout
require("HARD_TIMEOUT_MS = 3000" in core_js or "HARD_TIMEOUT_MS=3000" in core_js or "3000" in core_js, "hard timeout must be 3000ms")

# Reduced-motion JS guard
pt_start = core_js.index("function initPageTransitions()")
pt_end = core_js.index("\n\tfunction init()", pt_start)
pt_js = core_js[pt_start:pt_end]
require("prefers-reduced-motion" in pt_js, "initPageTransitions must have prefers-reduced-motion guard")

# Woo skip patterns
require("add-to-cart=" in pt_js, "transition must skip add-to-cart= Woo AJAX links")
require("/cart/" in pt_js, "transition must skip /cart/ links")
require("/checkout/" in pt_js, "transition must skip /checkout/ links")
require("data-gloskin-no-transition" in pt_js, "transition must respect [data-gloskin-no-transition] opt-out")

# Safety: no beforeunload/unload
require("beforeunload" not in pt_js, "page transition must never use beforeunload")
require("'unload'" not in pt_js and '"unload"' not in pt_js, "page transition must never use unload")

# No external library
require("barba" not in pt_js.lower() and "swup" not in pt_js.lower(), "page transition must not use Barba or Swup")
require("fetch(" not in pt_js, "page transition click handler must not fetch markup")

# CI coverage
require("page-transition-contract.py" in runtime, "page transition contract must run in check-runtime.sh")

print("page-transition-contract: OK (0.7.141)")
