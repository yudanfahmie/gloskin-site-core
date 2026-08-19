#!/usr/bin/env python3
"""Graphik WOFF integrity/provenance contract for the v0.7.140 font closure.

Verifies the five canonical runtime WOFF files directly against fixed expected
SHA-256 values and structural WOFF-header validity. No docs/fonts mirror is
required; the runtime binaries in plugin/gloskin-site-core/assets/fonts/ are
the sole canonical Graphik source.
"""
from pathlib import Path
import hashlib
import re
import struct

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugin/gloskin-site-core"
CSS = PLUGIN / "assets/css/gloskin-ui1-fonts.css"
FONT_DIR = PLUGIN / "assets/fonts"
CONFIG = PLUGIN / "config/assets.php"

EXPECTED = {
    300: "Graphik-Light.woff",
    400: "Graphik-Regular.woff",
    500: "Graphik-Medium.woff",
    600: "Graphik-Semibold.woff",
    700: "Graphik-Bold.woff",
}

# Fixed SHA-256 fingerprints for the five canonical runtime Graphik WOFF files.
# These are the authoritative hashes for the v0.7.140 closure — do NOT change
# these values unless a deliberate font replacement is performed and reviewed.
EXPECTED_SHA256 = {
    "Graphik-Light.woff":    "d4c4406dd3c40e545598daec9ae7c5872f62ade56dd59957a8360eadd27b6e71",
    "Graphik-Regular.woff":  "37c0b04c6187802d2e80ef3015b5e501b567c604e2f3c78910ba6e41bda27aee",
    "Graphik-Medium.woff":   "89d86c635731cf9854947e3d7f0c5e5168da1e1213282252a9b4b1247ed4a604",
    "Graphik-Semibold.woff": "7aeca3a21c553d2af7f8cc1184520d860157594ebd9a6be4d110bf56f2c81113",
    "Graphik-Bold.woff":     "0edc5658fe5e3b0494eddf3538239fd99abff567834f476346931342501dc787",
}

css = CSS.read_text(encoding="utf-8")
blocks = re.findall(r"@font-face\s*\{([^}]*)\}", css, flags=re.S)
graphik = []
felix = []
for block in blocks:
    family = re.search(r'font-family:\s*"([^"]+)"', block)
    if not family:
        continue
    if family.group(1) == "Graphik":
        graphik.append(block)
    elif family.group(1) == "Felix Titling":
        felix.append(block)

if len(graphik) != 5:
    raise SystemExit(f"expected exactly five Graphik normal faces, found {len(graphik)}")
if len(felix) != 1:
    raise SystemExit(f"expected exactly one Felix Titling face, found {len(felix)}")

seen = {}
for block in graphik:
    weight = re.search(r"font-weight:\s*(\d+)", block)
    source = re.search(r'url\("\.\./fonts/([^"?]+)"\)\s*format\("([^"]+)"\)', block)
    if not weight or not source:
        raise SystemExit("Graphik @font-face missing weight/src/format")
    numeric_weight = int(weight.group(1))
    filename, fmt = source.groups()
    if fmt != "woff":
        raise SystemExit(f"Graphik {numeric_weight} is not declared as WOFF")
    if "font-style:normal" not in block or "font-display:swap" not in block:
        raise SystemExit(f"Graphik {numeric_weight} style/display policy regressed")
    seen[numeric_weight] = filename
if seen != EXPECTED:
    raise SystemExit(f"Graphik weight mapping mismatch: {seen!r}")
if re.search(r'font-family:\s*"Graphik"[^}]*font-style:\s*(italic|oblique)', css, flags=re.S | re.I):
    raise SystemExit("Graphik italic/oblique face declared without a source WOFF")

felix_block = felix[0]
for required in (
    'url("../fonts/Felixti.woff2") format("woff2")',
    "font-style:normal",
    "font-weight:400",
    "font-display:swap",
):
    if required not in felix_block:
        raise SystemExit(f"Felix Titling declaration changed: missing {required}")

header_fmt = ">4s4sIHHIHHIIIII"
header_size = struct.calcsize(header_fmt)
for weight, filename in EXPECTED.items():
    runtime = FONT_DIR / filename
    if not runtime.is_file():
        raise SystemExit(f"missing runtime WOFF: {filename}")
    runtime_bytes = runtime.read_bytes()
    # Verify SHA-256 fingerprint against the fixed canonical value.
    actual_sha256 = hashlib.sha256(runtime_bytes).hexdigest()
    expected_sha256 = EXPECTED_SHA256[filename]
    if actual_sha256 != expected_sha256:
        raise SystemExit(
            f"runtime WOFF SHA-256 mismatch for {filename}:\n"
            f"  expected: {expected_sha256}\n"
            f"  actual:   {actual_sha256}"
        )
    if len(runtime_bytes) < header_size:
        raise SystemExit(f"{filename}: shorter than WOFF header")
    signature, _flavor, declared_len, num_tables, reserved, total_sfnt, _major, _minor, _meta_off, _meta_len, _meta_orig, _priv_off, _priv_len = struct.unpack(
        header_fmt, runtime_bytes[:header_size]
    )
    if signature != b"wOFF":
        raise SystemExit(f"{filename}: invalid WOFF magic {signature!r}")
    if declared_len != len(runtime_bytes):
        raise SystemExit(f"{filename}: declared length {declared_len} != actual {len(runtime_bytes)}")
    if num_tables < 1 or reserved != 0 or total_sfnt < 12 + (16 * num_tables):
        raise SystemExit(f"{filename}: invalid WOFF structural header")

leftovers = sorted(path.name for path in FONT_DIR.glob("Graphik*.woff2"))
if leftovers:
    raise SystemExit(f"superseded Graphik WOFF2 files remain: {leftovers}")

for suffix in (".otf", ".ttf", ".eot"):
    copied = sorted(path.name for path in FONT_DIR.glob(f"Graphik*{suffix}"))
    if copied:
        raise SystemExit(f"non-WOFF Graphik binaries copied into runtime: {copied}")

config = CONFIG.read_text(encoding="utf-8")
if "'assets/fonts/Graphik-Regular.woff'" not in config:
    raise SystemExit("critical Graphik preload is not the canonical Regular WOFF")
if "GraphikRegular.woff2" in config:
    raise SystemExit("superseded Graphik WOFF2 preload remains")

runtime_text = []
for path in PLUGIN.rglob("*"):
    if path.is_file() and path.suffix.lower() in {".php", ".css", ".js", ".json", ".md"}:
        runtime_text.append(path.read_text(encoding="utf-8", errors="ignore"))
if re.search(r"Graphik[^\s\"']*\.woff2", "\n".join(runtime_text), flags=re.I):
    raise SystemExit("production plugin still contains a Graphik WOFF2 consumer/reference")

print("font-integrity-contract: OK")
