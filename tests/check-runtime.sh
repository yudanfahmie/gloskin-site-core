#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

./tests/check-architecture.sh
python tests/check-language.py
python tests/sample-product-migration-contract.py
php tests/sample-product-migration.php
php tests/sample-product-importer-behavior.php
php tests/sample-product-importer-hardening.php
php tests/sample-product-woo-missing.php
./tests/check-presentation.sh
./tests/plugin-check-remediation-contract.sh
./tests/single-product-commerce-contract.sh
./tests/purchase-dock-controller-contract.sh
./tests/single-product-ghost-space-contract.sh
./tests/micro-interactions-contract.sh
php tests/single-product-guard-contract.php
php tests/single-product-description-boundary-contract.php
node tests/single-product-ajax-payload.test.js
node tests/hero-video.test.js
node tests/consultation.test.js
node tests/consultation-source-contract.test.js
php tests/consultation-contract.php
php tests/consultation-demo-importer-contract.php
php tests/woo-cart-notice-contract.php
php tests/release-version-contract.php
php tests/shop-catalog-contract.php
node tests/shop-catalog-controller.test.js
python tests/storefront-regression-contract.py
php tests/rest-sanitize-callback-contract.php
python tests/header-admin-contract.py
php tests/description-consolidation-contract.php
php tests/hero-video-contract.php
GL_TEST_MODE=success php tests/description-consolidation-bootstrap-contract.php
GL_TEST_MODE=idempotent_second_run php tests/description-consolidation-bootstrap-contract.php
GL_TEST_MODE=missing_woo php tests/description-consolidation-bootstrap-contract.php
GL_TEST_MODE=failing_query php tests/description-consolidation-bootstrap-contract.php
GL_TEST_MODE=false_complete_selfheal php tests/description-consolidation-bootstrap-contract.php
GL_TEST_MODE=real_complete_gate php tests/description-consolidation-bootstrap-contract.php
python tests/readiness-contract-smoke.py
php tests/readiness-php-smoke.php
php tests/rendered-shell-auth-smoke.php
GL_TEST_ACCOUNT=1 php tests/rendered-shell-auth-smoke.php
GL_TEST_LOGGED_IN=1 php tests/rendered-shell-auth-smoke.php
php tests/asset-loading-smoke.php
php tests/product-card-commerce-contract.php
php tests/catalog-discovery-contract.php
php tests/navigation-fallback-contract.php
php tests/lifecycle-shop-page-alignment-contract.php
php tests/admin-navigation-smoke.php
php tests/runtime-smoke.php
GL_TEST_ADMIN=1 php tests/runtime-smoke.php
GL_TEST_WOO=1 php tests/runtime-smoke.php
GL_TEST_WOO_LATE=1 php tests/runtime-smoke.php

if command -v chromium >/dev/null 2>&1 && python -c 'import playwright' >/dev/null 2>&1; then
  python tests/browser-smoke.py
  python tests/page-richness-smoke.py
  python tests/readiness-browser-smoke.py
  python tests/quick-add-browser-smoke.py
  python tests/shop-catalog-browser-smoke.py
  python tests/public-rest-get-browser-smoke.py
  python tests/single-product-dock-browser-smoke.py
  python tests/single-product-ghost-space-browser-smoke.py
  python tests/micro-interactions-browser-smoke.py
  python tests/header-variant-browser-smoke.py
  python tests/admin-shell-browser-smoke.py
  python tests/hero-video-browser-smoke.py
else
  echo "browser smoke skipped: Chromium/Playwright unavailable"
fi
