#!/usr/bin/env python3
"""Static UX contract for the Media Cleanup card hierarchy."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
admin = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-media-cleanup-admin.php").read_text(encoding="utf-8")
css = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-admin.css").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


# Root cause: scan progress + metrics must live in one stable server-rendered card,
# rather than a plain progress card followed by a duplicate summary card.
for token in (
    'data-media-cleanup-scan-card',
    'gloskin-media-cleanup-progress-block',
    'data-media-cleanup-spinner',
    'data-media-cleanup-progress',
    'data-media-cleanup-stage',
    'data-media-cleanup-current',
    'gloskin-media-cleanup-metrics',
    'data-media-cleanup-counts',
):
    require(token in admin, f"stable scan card primitive missing: {token}")

require(admin.count('data-media-cleanup-counts') == 1, "scan counters must have one canonical live block")
require('<div class="gloskin-admin-card"><ul data-media-cleanup-counts>' not in admin, "obsolete duplicate summary card still rendered")
require('style="background:#c00' not in admin, "destructive presentation must be CSS-owned, not inline")

# Minimal visual system: narrower workspace, structured header, live progress,
# compact metric row and responsive collapse, all scoped to Media Cleanup.
for token in (
    '#gloskin-admin-root[data-gloskin-media-cleanup]{max-width:1040px}',
    '.gloskin-media-cleanup-card__head',
    '.gloskin-media-cleanup-progress-block',
    '.gloskin-media-cleanup-metrics{display:grid',
    'progress::-webkit-progress-value{background:var(--gloskin-admin-accent)',
    '.gloskin-media-cleanup-danger-card',
    '@media (max-width:900px)',
):
    require(token in css, f"Media Cleanup layout contract missing: {token}")

print("media-cleanup-card-layout-contract.py: OK")
