#!/usr/bin/env python3
"""Static authority contract for the post-client 2026-08-18 product model."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")

def require(condition, message):
    if not condition:
        raise AssertionError(message)

docs = {
    "README": read("README.md"),
    "CONTRIBUTING": read("CONTRIBUTING.md"),
    "source": read("docs/developer-source-of-truth.md"),
    "plan": read("docs/implementation-plan.md"),
    "workpack": read("docs/2026-08-18-prototype-refresh/README.md"),
    "prompt": read("docs/2026-08-18-prototype-refresh/AI-DEVELOPER-PROMPT.md"),
}
matrix = read("docs/page-matrix.csv")

for label, text in docs.items():
    low = text.lower()
    require("2026-08-18" in text, f"{label}: current client revision not explicit")
    require("woocommerce" in low, f"{label}: Woo protected ownership absent")
    require("promo" in low, f"{label}: Promo absent from current product model")

joined = "\n".join(docs.values())
for phrase in (
    "Perawatan",
    "Promo",
    "Skincare",
    "Tentang Gloskin",
):
    require(phrase in joined, f"primary IA item missing from canonical docs: {phrase}")

# Old public hierarchy may be mentioned only as historical/superseded context,
# never as the current primary IA contract.
require("Perawatan → Promo → Skincare → Tentang Gloskin" in joined,
        "canonical primary IA order is not explicit")
require("/promo/" in matrix, "page matrix does not provision the native Promo route")
require("not primary navigation" in matrix, "support-route role is not explicit in matrix")
require("generic migration framework" in joined.lower(),
        "bounded-vs-generic migration boundary is not documented")
require("one-click" in joined.lower() or "satu klik" in joined.lower(),
        "autonomous one-action migration UX is not documented")
require("Doctors/Clinics/Insights are not mandatory Home sections" in matrix,
        "superseded mandatory Home support-route hierarchy remains ambiguous")

print("prototype-authority-contract: OK")
