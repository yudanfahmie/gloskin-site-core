#!/usr/bin/env bash
# check-runtime.sh — READ-ONLY. This script MUST NOT modify any tracked file.
# All test assertions are permanently baked into the canonical test files.
# Verify after every run: git diff --exit-code && git status --porcelain
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

# Capture pre-run tree state.
_before_hash="$(git diff HEAD | sha256sum)"
_before_status="$(git status --porcelain)"

./tests/check-architecture.sh
python tests/font-integrity-contract.py
php tests/core-auth-boundary-contract.php
python tests/check-language.py
python tests/sample-product-migration-contract.py
php tests/sample-product-migration.php
php tests/sample-product-importer-behavior.php
php tests/sample-product-importer-hardening.php
php tests/sample-product-woo-missing.php
presentation_log="$(mktemp)"
if ! ./tests/check-presentation.sh >"$presentation_log" 2>&1; then
  cat "$presentation_log" >&2
  presentation_detail="$(tail -n 1 "$presentation_log" | tr -cs 'A-Za-z0-9._=-' '-' | cut -c1-58)"
  rm -f "$presentation_log"
  false "presentation-${presentation_detail:-unknown}"
fi
rm -f "$presentation_log"
python tests/prototype-refresh-contract.py
python tests/width-doctor-grid-contract.py
python tests/prototype-authority-contract.py
./tests/plugin-check-remediation-contract.sh
./tests/single-product-commerce-contract.sh
./tests/purchase-dock-controller-contract.sh
./tests/single-product-ghost-space-contract.sh
./tests/micro-interactions-contract.sh
php tests/commerce-progress-heading-contract.php
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
php tests/shop-smart-search-contract.php
php tests/product-grid-contract.php
python tests/insight-typography-contract.py
node tests/shop-catalog-controller.test.js
python tests/storefront-regression-contract.py
php tests/rest-sanitize-callback-contract.php
python tests/header-admin-contract.py
python tests/legacy-presentation-cleanup-contract.py
python tests/treatment-band-consultation-contract.py
python tests/promo-date-order-contract.py
python tests/skincare-filter-contract.py
python tests/promo-thumbnail-contract.py
python tests/no-active-variant-context-contract.py
python tests/single-migration-action-contract.py
php tests/doctor-snapshot-contract.php
php tests/final-migration-contract.php
php tests/final-migration-path-contract.php
php tests/final-migration-state-contract.php
php tests/final-migration-batch-contract.php
python tests/final-migration-dom-contract.py
python tests/final-migration-js-behavior-contract.py
php tests/final-migration-path-contract.php
php tests/final-migration-package-integrity.php
php tests/final-migration-dom-ajax-contract.php
php tests/final-migration-failed-resume-contract.php
php tests/final-migration-doctor-batch-resume-contract.php
php tests/final-migration-preflight-reset-contract.php
php tests/final-migration-error-contract.php
php tests/final-closure-contract.php
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
python tests/quick-add-polish-contract.py
python tests/variable-modal-presentation-convergence-contract.py
python tests/variable-quantity-presentation-contract.py
python tests/variable-chip-buy-now-contract.py
php tests/product-card-commerce-contract.php
php tests/catalog-discovery-contract.php
php tests/navigation-fallback-contract.php
php tests/lifecycle-shop-page-alignment-contract.php
php tests/prototype-ia-migration-contract.php
python tests/prototype-ia-loader-contract.py
python tests/page-transition-contract.py
php tests/admin-navigation-smoke.php
php -d auto_prepend_file=tests/runtime-smoke-wordpress-stubs.php tests/runtime-smoke.php
GL_TEST_ADMIN=1 php -d auto_prepend_file=tests/runtime-smoke-wordpress-stubs.php tests/runtime-smoke.php
GL_TEST_WOO=1 php -d auto_prepend_file=tests/runtime-smoke-wordpress-stubs.php tests/runtime-smoke.php
GL_TEST_WOO_LATE=1 php -d auto_prepend_file=tests/runtime-smoke-wordpress-stubs.php tests/runtime-smoke.php

if command -v chromium >/dev/null 2>&1 && python -c 'import playwright' >/dev/null 2>&1; then
  python tests/font-browser-smoke.py
  python tests/browser-smoke.py
  python tests/page-richness-smoke.py
  python tests/readiness-browser-smoke.py
  python tests/quick-add-browser-smoke.py
  python tests/commerce-closure-browser-smoke.py
  python tests/commerce-progress-heading-browser-smoke.py
  python tests/variable-commerce-hardening-browser-smoke.py
  python tests/shop-catalog-browser-smoke.py
  python tests/product-grid-browser-smoke.py
  python tests/insight-typography-browser-smoke.py
  python tests/public-rest-get-browser-smoke.py
  python tests/single-product-dock-browser-smoke.py
  python tests/single-product-ghost-space-browser-smoke.py
  python tests/micro-interactions-browser-smoke.py
  python tests/header-variant-browser-smoke.py
  python tests/width-doctor-grid-browser-smoke.py
  python tests/admin-shell-browser-smoke.py
  python tests/hero-video-browser-smoke.py
  python tests/cart-block-mobile-regression.py
  python tests/checkout-block-presentation-regression.py
else
  echo "browser smoke skipped: Chromium/Playwright unavailable"
fi

# Immutability contract: the full test run must leave the working tree clean.
# Any test that rewrites a tracked file is a bug, not a feature.
_after_hash="$(git diff HEAD | sha256sum)"
_after_status="$(git status --porcelain)"
if [[ "$_before_hash" != "$_after_hash" ]] || [[ "$_before_status" != "$_after_status" ]]; then
  echo "FAIL: check-runtime.sh left tracked files modified — tests must be read-only" >&2
  git diff --stat HEAD >&2
  exit 1
fi
echo "immutability contract: OK (no tracked files were modified by the test run)"
