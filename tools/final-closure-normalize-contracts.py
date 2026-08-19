#!/usr/bin/env python3
from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    s = p.read_text(encoding='utf-8')
    if s.count(old) != 1:
        raise SystemExit(f'{path}: expected exactly one superseded contract anchor, found {s.count(old)}')
    p.write_text(s.replace(old, new, 1), encoding='utf-8')


# Generated final closure contract: literal $this references must not interpolate.
p = Path('tests/final-closure-contract.php')
s = p.read_text(encoding='utf-8')
s = s.replace('"[\'editorial_audit\'] = $this->run_managed_content()"', '"[\'editorial_audit\'] = \\$this->run_managed_content()"')
s = s.replace('"[\'ia_audit\'] = $this->run_normalize()"', '"[\'ia_audit\'] = \\$this->run_normalize()"')
s = s.replace(
    'gl_final_ok( substr_count( $migration, "\'key\' =>") === 8, \'no migration checkpoint added\' );',
    '$first_step = min( $positions ); $last_step = max( $positions ); $step_slice = substr( $migration, $first_step, ( $last_step - $first_step ) + strlen( "\'key\' => \'finalize\'" ) ); gl_final_ok( substr_count( $step_slice, "\'key\' => \'" ) === 8, \'no migration checkpoint added/reordered\' );'
)
s = s.replace(
    'gl_final_ok( ! str_contains( $ia, "wp_delete_post( $page" ), \'IA normalizer never deletes supporting pages\' );',
    'gl_final_ok( substr_count( $ia, \'wp_delete_post(\' ) === 1 && str_contains( $ia, \'wp_delete_post( $item_id, true )\' ), \'IA normalizer deletes only obsolete nav-menu items, never supporting pages\' );'
)
p.write_text(s, encoding='utf-8')

# v0.7.139 presentation baseline required an empty editorial catalog. Final closure
# replaces that baseline with a bounded local WordPress attachment catalog.
replace_once(
    'tests/check-presentation.sh',
    """grep -Fq 'return array(); /* No external image catalog' \"$helpers\" \\
  || { echo \"empty editorial media catalog contract missing\" >&2; exit 1; }""",
    """grep -Fq \"get_option( 'gloskin_site_core_editorial_media_v1', array() )\" \"$helpers\" \\
  || { echo \"bounded local editorial media catalog contract missing\" >&2; exit 1; }
grep -Fq 'wp_get_attachment_image( $attachment_id' \"$helpers\" \\
  || { echo \"local WordPress editorial attachment rendering missing\" >&2; exit 1; }""",
)

print('final-closure-normalize-contracts: OK')
