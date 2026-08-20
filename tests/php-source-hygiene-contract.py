#!/usr/bin/env python3
"""Focused PHP source and WordPress AJAX response hygiene contract."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
RUNTIME_ROOTS = tuple(path for path in (ROOT / "plugin", ROOT / "theme", ROOT / "themes") if path.exists())


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


php_files = sorted(path for base in RUNTIME_ROOTS for path in base.rglob("*.php"))
require(php_files, "no plugin/theme PHP files found")

for path in php_files:
    raw = path.read_bytes()
    relative = path.relative_to(ROOT)
    require(raw.startswith(b"<?php"), f"PHP must begin at byte zero with <?php: {relative}")
    require(b"\xef\xbb\xbf" not in raw, f"UTF-8 BOM found in PHP: {relative}")
    text = raw.decode("utf-8")
    require(not re.search(r"^(?:<<<<<<<|=======|>>>>>>>)", text, re.MULTILINE),
            f"merge-conflict marker found in PHP: {relative}")
    if "templates" not in relative.parts:
        require(not raw.rstrip().endswith(b"?>"), f"library PHP must not end with ?>: {relative}")

main = (ROOT / "plugin/gloskin-site-core/gloskin-site-core.php").read_bytes()
kernel = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php").read_bytes()
admin = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260820-promo-recovery-admin.php").read_text(encoding="utf-8")
require(main[:5] == b"<?php" and kernel[:5] == b"<?php",
        "bootstrap sources must not emit bytes before WordPress AJAX JSON")
require("wp_send_json_success( $this->migration->advance( $mode ) )" in admin,
        "wp_send_json_success must remain the success response owner")
require("wp_send_json_error(" in admin, "wp_send_json_error must remain the error response owner")

print(f"php-source-hygiene-contract.py: OK ({len(php_files)} PHP files, clean JSON prefix)")
