#!/usr/bin/env bash
# Focused regression contract for docs/audits/plugin-check-remediation-2026-08-11.csv.
# Each check below pins one WPPC-### row's fix/suppression so it cannot silently
# regress. This is not a Plugin Check/PHPCS re-implementation -- it is a static
# guard over the specific patterns this remediation pass introduced.
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_root="$repo_root/plugin/gloskin-site-core"
includes="$plugin_root/includes"
templates="$plugin_root/templates"
header="$templates/parts/header.php"
admin_service="$includes/class-gloskin-site-core-admin-service.php"
navigation_service="$includes/class-gloskin-site-core-navigation-service.php"
template_helpers="$templates/parts/template-helpers.php"
bundle="$includes/class-gloskin-site-core-sample-product-bundle.php"
importer="$includes/class-gloskin-site-core-sample-product-importer.php"
import_js="$plugin_root/assets/js/gloskin-ui1-sample-product-import.js"
plugin_header="$plugin_root/gloskin-site-core.php"

fail() { echo "$1" >&2; exit 1; }

# WPPC-001: mini-cart stays a single trusted renderer; header only echoes its
# output behind a documented, narrowly-scoped suppression -- never raw request/meta data.
grep -qF "Trusted-renderer contract" "$header" || fail "WPPC-001: mini-cart trusted-renderer contract comment missing from header.php"
grep -qF "echo \$gloskin_commerce['mini_cart']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped" "$header" \
  || fail "WPPC-001: narrow mini-cart output suppression missing/changed in header.php"
if grep -qE "echo \\\$_(GET|POST|REQUEST|SERVER)" "$header"; then
  fail "WPPC-001: header.php echoes a superglobal directly"
fi

# WPPC-002: posted meta must run through sanitize_meta() (the registered
# per-field sanitizer) before update_post_meta(), not rely on an implicit boundary.
save_meta_block="$(awk '/public function save_meta\(/,/^\t\}$/' "$admin_service")"
[[ -n "$save_meta_block" ]] || fail "WPPC-002: save_meta() not found in admin-service.php"
echo "$save_meta_block" | grep -q "sanitize_meta(" || fail "WPPC-002: save_meta() no longer calls sanitize_meta() before persistence"
echo "$save_meta_block" | grep -q "update_post_meta(" || fail "WPPC-002: save_meta() no longer persists via update_post_meta()"

# WPPC-003: REQUEST_URI must be unslashed AND sanitized before any comparison.
grep -qF "sanitize_text_field( wp_unslash( \$_SERVER['REQUEST_URI'] ) )" "$navigation_service" \
  || fail "WPPC-003: REQUEST_URI is no longer unslashed+sanitized in navigation-service.php"

# WPPC-004 (spot check): a translators comment sits immediately before each of a
# few representative previously-flagged placeholder strings.
declare -A translator_spots=(
  ["$header"]='Buka submenu %s'
  ["$template_helpers"]='Simpan %s ke favorit'
)
for file in "${!translator_spots[@]}"; do
  needle="${translator_spots[$file]}"
  line="$(grep -n -F "$needle" "$file" | head -n1 | cut -d: -f1)"
  [[ -n "$line" ]] || fail "WPPC-004: expected placeholder string not found: $needle"
  prev_lines="$(sed -n "$((line>3 ? line-3 : 1)),${line}p" "$file")"
  echo "$prev_lines" | grep -q 'translators:' || fail "WPPC-004: no translators comment near: $needle ($file:$line)"
done

# WPPC-005: shared template-chain top-level locals are gloskin_-prefixed; the
# old unprefixed names must not reappear in the files this pass renamed.
if grep -RInE '\$(navigation|commerce|woo|logo_url|quick_auth|auth_attrs)\b' "$header" "$templates/parts/mobile-drawer.php"; then
  fail "WPPC-005: unprefixed top-level variable reintroduced in header.php/mobile-drawer.php"
fi
if grep -RInE '\bif \( \$has_details|\$booking_url\b' "$templates/pages/treatment.php" "$templates/pages/doctor.php" "$templates/pages/clinic.php"; then
  fail "WPPC-005: unprefixed \$has_details/\$booking_url reintroduced in a detail page template"
fi

# WPPC-006: the core `the_content` filter call is a documented, narrow
# false-positive suppression, not a renamed/duplicated hook.
grep -B2 "apply_filters( 'the_content'" "$template_helpers" | grep -q "phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound" \
  || fail "WPPC-006: the_content hook suppression/comment missing from template-helpers.php"

# WPPC-007: importer/bundle domain exceptions stay unescaped at the throw site
# (escaped once at the real output boundary instead); the suppression is scoped
# to the class, and the two boundaries it depends on are still in place.
for file in "$bundle" "$importer"; do
  grep -q "phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped" "$file" \
    || fail "WPPC-007: scoped ExceptionNotEscaped suppression missing from $(basename "$file")"
  grep -q "phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped" "$file" \
    || fail "WPPC-007: ExceptionNotEscaped suppression is not closed with phpcs:enable in $(basename "$file")"
done
grep -q "wp_send_json_error" "$admin_service" || fail "WPPC-007: AJAX error boundary (wp_send_json_error) missing from admin-service.php"
grep -qF "esc_html( isset( \$summary['last_error'] )" "$admin_service" \
  || fail "WPPC-007: server-rendered last_error is no longer esc_html()-ed"
grep -q "textContent = message" "$import_js" || fail "WPPC-007: admin import JS no longer assigns the error message via textContent"
if grep -qE "\.innerHTML\s*=.*(error|message)" "$import_js"; then
  fail "WPPC-007: admin import JS assigns an error/message string via innerHTML"
fi

# WPPC-008: no direct unlink() remains in the importer/bundle; the one
# remaining direct rmdir() is narrowly suppressed with a documented rationale.
if grep -RInE 'unlink\(' "$bundle" "$importer"; then
  fail "WPPC-008: a direct unlink() call reappeared in bundle/importer"
fi
grep -qF 'wp_delete_file(' "$bundle" || fail "WPPC-008: wp_delete_file() no longer used for file cleanup in bundle.php"
grep -qF 'wp_delete_file(' "$importer" || fail "WPPC-008: wp_delete_file() no longer used for tmp cleanup in importer.php"
grep -q "phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir" "$bundle" \
  || fail "WPPC-008: documented rmdir() suppression missing from bundle.php"

# WPPC-009: bundle URL host/scheme validation uses wp_parse_url(), not parse_url().
if grep -F 'parse_url(' "$bundle" | grep -v 'wp_parse_url(' | grep -q .; then
  fail "WPPC-009: raw parse_url() reappeared in bundle.php"
fi
grep -q 'wp_parse_url(' "$bundle" || fail "WPPC-009: wp_parse_url() no longer used in bundle.php"

# WPPC-010: get_posts() calls stay on the native suppress_filters default.
if grep -q "'suppress_filters'" "$importer"; then
  fail "WPPC-010: explicit suppress_filters key reintroduced in importer.php get_posts() calls"
fi

# WPPC-011: bounded one-shot meta_key/meta_value identity queries carry a
# documented, narrow SlowDBQuery suppression instead of a new query architecture.
meta_key_lines="$(grep -c "'meta_key'" "$importer")"
meta_key_suppressed="$(grep -c "'meta_key'.*phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key" "$importer")"
[[ "$meta_key_lines" == "$meta_key_suppressed" ]] || fail "WPPC-011: not every meta_key lookup in importer.php carries the documented suppression"
meta_value_lines="$(grep -c "'meta_value'" "$importer")"
meta_value_suppressed="$(grep -c "'meta_value'.*phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value" "$importer")"
[[ "$meta_value_lines" == "$meta_value_suppressed" ]] || fail "WPPC-011: not every meta_value lookup in importer.php carries the documented suppression"

# WPPC-014: plugin header carries no staging/deployment-test wording.
if grep -qiE 'test auto pull|debug mode|staging only|do not deploy' "$plugin_header"; then
  fail "WPPC-014: staging/deployment-test wording found in plugin header"
fi

echo "plugin check remediation contract passed"
