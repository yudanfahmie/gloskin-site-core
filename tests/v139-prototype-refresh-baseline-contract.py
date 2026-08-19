#!/usr/bin/env python3
"""Normalize the exact stale About/founder assertion in prototype refresh tests."""
from pathlib import Path

path = Path(__file__).resolve().parent / "prototype-refresh-contract.py"
src = path.read_text(encoding="utf-8")

stale = '''for fabricated in ("founder", "pendiri", "award", "penghargaan", "sertifikasi terbaik"):
    require(fabricated not in about.lower(), f"About must not fabricate {fabricated}")
'''
corrected = '''require("$gloskin_founder" in about, "About founder projection must remain source-gated")
require("if ( $gloskin_founder )" in about, "About founder section must render only when source data exists")
for fabricated in ("award", "penghargaan", "sertifikasi terbaik"):
    require(fabricated not in about.lower(), f"About must not fabricate {fabricated}")
'''

if stale in src:
    if src.count(stale) != 1:
        raise SystemExit("prototype-refresh: unexpected stale founder assertion count")
    src = src.replace(stale, corrected, 1)
elif "About founder section must render only when source data exists" not in src:
    raise SystemExit("prototype-refresh: founder assertion is neither known stale nor normalized")

path.write_text(src, encoding="utf-8")
print("v139-prototype-refresh-baseline-contract: normalized exact stale assertion")
