#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

./tests/check-presentation.sh
php tests/asset-loading-smoke.php

php tests/runtime-smoke.php
GL_TEST_ADMIN=1 php tests/runtime-smoke.php
GL_TEST_WOO=1 php tests/runtime-smoke.php

if command -v chromium >/dev/null 2>&1 && python -c 'import playwright' >/dev/null 2>&1; then
	python tests/browser-smoke.py
else
	echo "browser smoke skipped: Chromium/Playwright unavailable"
fi
