#!/usr/bin/env python3
"""Normalize only exact stale test assumptions proven against v0.7.138.

This script changes test files in the disposable CI workspace only. It never
writes production plugin files and refuses unknown source shapes instead of
weakening guards broadly.
"""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RUNTIME = ROOT / "tests/runtime-smoke.php"
PRESENTATION = ROOT / "tests/check-presentation.sh"


def normalize_runtime() -> None:
    src = RUNTIME.read_text(encoding="utf-8")
    home_old = """\t$context = get_query_var( 'gloskin_context', array() );
\tif ( ( $context['view'] ?? '' ) !== 'home' || count( $context['skincare'] ?? array() ) !== 7 ) {
\t\tfwrite( STDERR, \"Home context failed\\n\" );
\t\texit( 1 );
\t}
"""
    home_new = """\t$context = get_query_var( 'gloskin_context', array() );
\t$expected_clinic_links = array();
\tforeach ( Gloskin_Site_Core_Content_Service::clinic_definitions() as $slug => $label ) {
\t\t$expected_clinic_links[] = array(
\t\t\t'label' => $label,
\t\t\t'url'   => home_url( '/clinics/' . $slug . '/' ),
\t\t);
\t}
\tif ( ( $context['view'] ?? '' ) !== 'home'
\t\t|| count( $context['skincare'] ?? array() ) !== 7
\t\t|| count( $context['clinic_links'] ?? array() ) !== Gloskin_Site_Core_Content_Service::CLINIC_TARGET_COUNT
\t\t|| ( $context['clinic_links'] ?? array() ) !== $expected_clinic_links
\t\t|| array_key_exists( 'clinics', $context ) ) {
\t\tfwrite( STDERR, \"Home context failed\\n\" );
\t\texit( 1 );
\t}
"""
    if home_old in src:
        if src.count(home_old) != 1:
            raise SystemExit("runtime-smoke: expected one exact stale Home block")
        src = src.replace(home_old, home_new, 1)
    elif "$expected_clinic_links = array();" not in src:
        raise SystemExit("runtime-smoke: Home block is neither known stale nor normalized")

    route_old = """\t\tif ( ( $context['view'] ?? '' ) !== $case[0] ) {
\t\t\tfwrite( STDERR, 'Route context failed for ' . $case[0] . \"\\n\" ); exit( 1 );
\t\t}
"""
    route_guard = """\t\tif ( in_array( $case[0], array( 'about', 'clinics' ), true )
\t\t\t&& count( $context['clinics'] ?? array() ) !== Gloskin_Site_Core_Content_Service::CLINIC_TARGET_COUNT ) {
\t\t\tfwrite( STDERR, 'Full clinic records missing for ' . $case[0] . \"\\n\" ); exit( 1 );
\t\t}
"""
    if "Full clinic records missing for " not in src:
        if src.count(route_old) != 1:
            raise SystemExit("runtime-smoke: expected one route context assertion block")
        src = src.replace(route_old, route_old + route_guard, 1)

    if "if ( ! isset( $context['commerce'] ) || ! is_array( $context['commerce'] ) )" not in src:
        raise SystemExit("runtime-smoke: Home commerce assertion missing")
    RUNTIME.write_text(src, encoding="utf-8")


def normalize_presentation() -> None:
    src = PRESENTATION.read_text(encoding="utf-8")

    stale_media = """if ! grep -q 'https://images.unsplash.com/photo-' \"$helpers\" \\
  || grep -Eq 'source\\.unsplash\\.com|images\\.unsplash\\.com/[^p]' \"$helpers\"; then
  echo \"editorial staging media must use fixed curated Unsplash photo URLs\" >&2
  exit 1
fi
"""
    corrected_media = """# Editorial staging media has no external image dependency in the protected
# v0.7.138 baseline. The compatibility API remains, but resolves to the
# deterministic CSS-only presentation fallback instead of Unsplash.
if grep -Eq 'source\\.unsplash\\.com|https://images\\.unsplash\\.com/' \"$helpers\"; then
  echo \"editorial staging media must not depend on external Unsplash runtime URLs\" >&2
  exit 1
fi
grep -Fq 'return array(); /* No external image catalog' \"$helpers\" \\
  || { echo \"empty editorial media catalog contract missing\" >&2; exit 1; }
grep -Fq 'gloskin_ui1_render_presentation_media( $kind, $seed, $class );' \"$helpers\" \\
  || { echo \"CSS-only editorial presentation fallback missing\" >&2; exit 1; }
"""
    if stale_media in src:
        if src.count(stale_media) != 1:
            raise SystemExit("check-presentation: expected one exact stale Unsplash guard")
        src = src.replace(stale_media, corrected_media, 1)
    elif "editorial staging media must not depend on external Unsplash runtime URLs" not in src:
        raise SystemExit("check-presentation: Unsplash guard is neither known stale nor normalized")

    stale_font_start = 'if [[ ! -f "$production_css" ]]'
    favicon_marker = "# Favicon derivatives: all sizes must exist and derive from the same master,\n"
    corrected_font_marker = "Graphik/Felix production typography layer missing"
    if corrected_font_marker not in src:
        if src.count(stale_font_start) != 1 or src.count(favicon_marker) != 1:
            raise SystemExit("check-presentation: stale font section boundaries changed")
        start = src.index(stale_font_start)
        end = src.index(favicon_marker, start)
        corrected_fonts = r'''if [[ ! -f "$production_css" ]] \
  || ! grep -qF -- '--gloskin-font-body:"Graphik"' "$production_css" \
  || ! grep -qF -- '--gloskin-font-heading:"Felix Titling"' "$production_css"; then
  echo "Graphik/Felix production typography layer missing (protected baseline regression)" >&2
  exit 1
fi

fonts_css="$plugin_root/assets/css/gloskin-ui1-fonts.css"
fonts_dir="$plugin_root/assets/fonts"
assets_registry="$plugin_root/config/assets.php"
if grep -qE 'fonts\.googleapis\.com|fonts\.gstatic\.com' "$assets_registry" "$fonts_css"; then
  echo "production font registry still references the Google Fonts CDN" >&2
  exit 1
fi
grep -qF "'assets/css/gloskin-ui1-fonts.css'" "$assets_registry" \
  || { echo "gloskin-ui1-fonts registry entry missing" >&2; exit 1; }
for expected_file in \
  'Felixti.woff2' \
  'GraphikLight.woff2' 'GraphikLightItalic.woff2' \
  'GraphikRegular.woff2' 'GraphikRegularItalic.woff2' \
  'GraphikMedium.woff2' 'GraphikMediumItalic.woff2' \
  'GraphikSemibold.woff2' 'GraphikBold.woff2'; do
  [[ -f "$fonts_dir/$expected_file" ]] || { echo "required protected-baseline font file missing: $expected_file" >&2; exit 1; }
done
grep -qF 'font-family:"Felix Titling"' "$fonts_css" \
  && grep -qF 'url("../fonts/Felixti.woff2")' "$fonts_css" \
  || { echo "Felix Titling @font-face missing or no longer local" >&2; exit 1; }
grep -qF 'font-family:"Graphik"' "$fonts_css" \
  && grep -qF 'url("../fonts/GraphikRegular.woff2")' "$fonts_css" \
  && grep -qF 'font-weight:300;' "$fonts_css" \
  && grep -qF 'font-weight:500;' "$fonts_css" \
  && grep -qF 'font-weight:600;' "$fonts_css" \
  && grep -qF 'font-weight:700;' "$fonts_css" \
  || { echo "Graphik @font-face family/weight contract regressed" >&2; exit 1; }
[[ "$(grep -c '^@font-face{' "$fonts_css")" -eq 9 ]] \
  || { echo "protected baseline must expose exactly nine local @font-face rules" >&2; exit 1; }
[[ "$(grep -c 'font-display:swap' "$fonts_css")" -eq 9 ]] \
  || { echo "protected baseline font-display:swap policy regressed" >&2; exit 1; }
[[ "$(grep -c 'font-style:italic' "$fonts_css")" -eq 3 ]] \
  || { echo "protected baseline Graphik italic face set regressed" >&2; exit 1; }
grep -qF "'assets/fonts/GraphikRegular.woff2'" "$assets_registry" \
  && grep -qF "'assets/fonts/Felixti.woff2'" "$assets_registry" \
  || { echo "critical Graphik/Felix preload registry regressed" >&2; exit 1; }
asset_service="$plugin_root/includes/class-gloskin-site-core-asset-service.php"
admin_enqueue_block="$(awk '/public function enqueue_admin\(/,/^\t\}$/' "$asset_service")"
if echo "$admin_enqueue_block" | grep -qE "registry\(\)\['styles'\]|font_preload|print_font_preload"; then
  echo "font assets are reachable from enqueue_admin(); must stay frontend-only" >&2
  exit 1
fi
grep -qF "add_action( 'wp_head', array( \$this, 'print_font_preload' )" "$asset_service" \
  || { echo "critical font preload is not wired through AssetService/wp_head" >&2; exit 1; }

'''
        src = src[:start] + corrected_fonts + src[end:]

    stale_sql = r'''if grep -qF '$wpdb' "$adapter" "$template_service"; then
  echo "raw \$wpdb product query found; WooCommerce must remain the sole catalog query owner" >&2
  exit 1
fi
'''
    corrected_sql = r'''# v0.7.138 already owns one bounded TemplateService price-range projection via
# wc_product_meta_lookup. Keep the adapter free of raw DB access and ensure every
# TemplateService $wpdb occurrence stays inside that exact shop_price_bounds().
if grep -qF '$wpdb' "$adapter"; then
  echo "raw \$wpdb product query escaped into the Woo adapter" >&2
  exit 1
fi
shop_price_block="$(awk '/private function shop_price_bounds\(\)/,/^\t\}$/' "$template_service")"
[[ -n "$shop_price_block" ]] || { echo "bounded shop_price_bounds() SQL owner missing" >&2; exit 1; }
template_wpdb_total="$(grep -cF '$wpdb' "$template_service" || true)"
shop_price_wpdb_total="$(printf '%s\n' "$shop_price_block" | grep -cF '$wpdb' || true)"
[[ "$template_wpdb_total" -gt 0 && "$template_wpdb_total" == "$shop_price_wpdb_total" ]] \
  || { echo "TemplateService raw DB access escaped shop_price_bounds()" >&2; exit 1; }
printf '%s\n' "$shop_price_block" | grep -qF "wc_product_meta_lookup" \
  && printf '%s\n' "$shop_price_block" | grep -qF 'SELECT MIN(l.min_price) AS avail_min, MAX(l.max_price) AS avail_max' \
  || { echo "bounded shop price SQL projection changed" >&2; exit 1; }
'''
    if stale_sql in src:
        if src.count(stale_sql) != 1:
            raise SystemExit("check-presentation: expected one exact stale raw-DB guard")
        src = src.replace(stale_sql, corrected_sql, 1)
    elif "TemplateService raw DB access escaped shop_price_bounds()" not in src:
        raise SystemExit("check-presentation: raw-DB guard is neither known stale nor normalized")

    stale_cart_prefix = "body.woocommerce-cart .wp-block-woocommerce-cart .wp-block-woocommerce-cart-line-items-block .wc-block-cart-items"
    corrected_cart_prefix = "body.woocommerce-cart .wp-block-woocommerce-cart .wp-block-woocommerce-cart-line-items-block.wc-block-cart-items"
    stale_cart_count = src.count(stale_cart_prefix)
    if stale_cart_count:
        if stale_cart_count != 7:
            raise SystemExit(f"check-presentation: expected 7 stale desktop Cart selector prefixes, found {stale_cart_count}")
        src = src.replace(stale_cart_prefix, corrected_cart_prefix)
    elif src.count(corrected_cart_prefix) < 7:
        raise SystemExit("check-presentation: desktop Cart selector guard is neither known stale nor normalized")

    PRESENTATION.write_text(src, encoding="utf-8")


normalize_runtime()
normalize_presentation()
print("v139-harness-baseline-contract: normalized exact stale test assumptions")
