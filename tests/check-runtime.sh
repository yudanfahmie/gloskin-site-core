#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

python tests/check-language.py
./tests/check-presentation.sh
python tests/readiness-contract-smoke.py
php tests/readiness-php-smoke.php
php tests/rendered-shell-auth-smoke.php
GL_TEST_ACCOUNT=1 php tests/rendered-shell-auth-smoke.php
GL_TEST_LOGGED_IN=1 php tests/rendered-shell-auth-smoke.php
php tests/asset-loading-smoke.php
php tests/admin-navigation-smoke.php
php tests/runtime-smoke.php
GL_TEST_ADMIN=1 php tests/runtime-smoke.php
GL_TEST_WOO=1 php tests/runtime-smoke.php
GL_TEST_WOO_LATE=1 php tests/runtime-smoke.php

if command -v chromium >/dev/null 2>&1 && python -c 'import playwright' >/dev/null 2>&1; then
  python tests/browser-smoke.py
  python tests/page-richness-smoke.py
  python tests/readiness-browser-smoke.py
else
  echo "browser smoke skipped: Chromium/Playwright unavailable"
fi
