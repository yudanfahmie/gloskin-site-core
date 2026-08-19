#!/usr/bin/env python3
"""Normalize only the exact stale test assumptions proven against v0.7.138.

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

    # Commerce remains a separate Home runtime contract; never replace/remove it.
    commerce_contract = "if ( ! isset( $context['commerce'] ) || ! is_array( $context['commerce'] ) )"
    if commerce_contract not in src:
        raise SystemExit("runtime-smoke: Home commerce assertion missing")

    RUNTIME.write_text(src, encoding="utf-8")


def normalize_presentation() -> None:
    src = PRESENTATION.read_text(encoding="utf-8")
    stale = """if ! grep -q 'https://images.unsplash.com/photo-' \"$helpers\" \\
  || grep -Eq 'source\\.unsplash\\.com|images\\.unsplash\\.com/[^p]' \"$helpers\"; then
  echo \"editorial staging media must use fixed curated Unsplash photo URLs\" >&2
  exit 1
fi
"""
    corrected = """# Editorial staging media has no external image dependency in the protected
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

    if stale in src:
        if src.count(stale) != 1:
            raise SystemExit("check-presentation: expected one exact stale Unsplash guard")
        src = src.replace(stale, corrected, 1)
    elif "editorial staging media must not depend on external Unsplash runtime URLs" not in src:
        raise SystemExit("check-presentation: Unsplash guard is neither known stale nor normalized")

    PRESENTATION.write_text(src, encoding="utf-8")


normalize_runtime()
normalize_presentation()
print("v139-harness-baseline-contract: normalized exact stale test assumptions")
