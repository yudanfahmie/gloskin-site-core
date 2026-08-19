#!/usr/bin/env python3
"""Exact follow-up normalization for protected v0.7.138 presentation contracts."""
from pathlib import Path

path = Path(__file__).resolve().parent / "check-presentation.sh"
src = path.read_text(encoding="utf-8")

stale_thead = """if 'border-bottom:1px solid' not in thead:
    raise SystemExit('Desktop Cart table header must own exactly one bottom divider')
"""
correct_thead = """if 'border:0' not in thead:
    raise SystemExit('Desktop Cart table header must keep the protected baseline border reset')
"""
if stale_thead in src:
    if src.count(stale_thead) != 1:
        raise SystemExit("check-presentation: unexpected stale Cart thead assertion count")
    src = src.replace(stale_thead, correct_thead, 1)
elif "Desktop Cart table header must keep the protected baseline border reset" not in src:
    raise SystemExit("check-presentation: Cart thead assertion is neither known stale nor normalized")

malformed_row = "body.woocommerce-cart .wp-block-woocommerce-cart table.wc-block-cart-items.wp-block-woocommerce-cart-line-items-block__row"
baseline_row = "body.woocommerce-cart .wp-block-woocommerce-cart table.wc-block-cart-items.wp-block-woocommerce-cart-line-items-block .wc-block-cart-items__row"
if malformed_row in src:
    if src.count(malformed_row) != 2:
        raise SystemExit(f"check-presentation: expected 2 malformed Cart row selectors, found {src.count(malformed_row)}")
    src = src.replace(malformed_row, baseline_row)
elif src.count(baseline_row) < 2:
    raise SystemExit("check-presentation: Cart row selectors are neither malformed nor protected-baseline form")

path.write_text(src, encoding="utf-8")
print("v139-presentation-followup-contract: normalized exact stale assertions")
