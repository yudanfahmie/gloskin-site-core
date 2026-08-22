#!/usr/bin/env python3
"""Static UX contract for visible, truthful Media Cleanup progress feedback."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
js = (ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-media-cleanup.js").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


# Clicking a long-running action must produce immediate visible feedback, not
# merely disable the button while the first AJAX batch is pending.
for token in (
    "beginScanFeedback",
    "Menyiapkan scan Media Library",
    "Sedang memindai...",
    "data-media-cleanup-spinner",
    "spinner is-active",
    "aria-busy",
    "Progress aktual akan muncul setelah batch pertama selesai",
):
    require(token in js, f"scan busy feedback missing: {token}")

# Before the server knows total/processed counts, the native progress element is
# deliberately indeterminate. No invented percentage is allowed.
require("progress.removeAttribute( 'value' )" in js, "scan must use indeterminate progress before first server batch")
require("fakePercent" not in js and "Math.random" not in js, "fake progress is forbidden")

# Once real state arrives, the same progress element must switch to real values.
require("progress.max   = Math.max( 1, Number( state.total || 0 ) )" in js, "actual scan total missing")
require("progress.value = Math.min( Number( state.processed || 0 ), progress.max )" in js, "actual scan processed value missing")
require("gambar sudah dipindai" in js and "Sedang memeriksa:" in js, "plain-language actual scan feedback missing")

# Transition to review must render the review screen instead of leaving the
# initial Scan button/card on screen after the server reaches review_ready.
require("'review_ready' === status" in js and "Membuka hasil untuk ditinjau" in js, "review transition feedback missing")
require("window.setTimeout( function () { window.location.reload(); }, 250 )" in js, "review-ready UI refresh missing")

# Optimization receives the same immediate/actual distinction.
for token in (
    "beginOptimizationFeedback",
    "Menyiapkan optimasi gambar",
    "Sedang mengoptimalkan...",
    "data-media-optimization-spinner",
    "optimizeProgress.removeAttribute( 'value' )",
    "gambar sudah diperiksa",
):
    require(token in js, f"optimization busy feedback missing: {token}")

print("media-cleanup-progress-feedback-contract.py: OK")
