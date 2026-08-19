#!/usr/bin/env python3
from pathlib import Path

p = Path('tools/final-hardening-0-7-142.py')
s = p.read_text(encoding='utf-8')
old = r'''    "\n\t\t$doctor_admin = new Gloskin_Site_Core_Doctor_Migration_Admin( $plugin_file );\n\t\t$doctor_admin->register();\n\t\tself::$services[] = $doctor_admin;",
'''
new = r'''    "\n\t\t\t$doctor_admin = new Gloskin_Site_Core_Doctor_Migration_Admin( $plugin_file );\n\t\t\t$doctor_admin->register();\n\t\t\tself::$services[] = $doctor_admin;",
'''
if old not in s:
    raise SystemExit('doctor-admin driver source target not found')
s = s.replace(old, new, 1)

fixes = {
    r'''str_contains( $ia, "'publish' === (string) $page->post_status" )''': r'''str_contains( $ia, "'publish' === (string) \$page->post_status" )''',
    r'''str_contains( $ia, "'publish' !== $page->post_status" )''': r'''str_contains( $ia, "'publish' !== \$page->post_status" )''',
    r'''str_contains( $helpers, "in_array( $kind, array( 'doctor', 'clinic', 'product' ), true ) ) { return; }" )''': r'''str_contains( $helpers, "in_array( \$kind, array( 'doctor', 'clinic', 'product' ), true ) ) { return; }" )''',
    r'''str_contains( $helpers, "'alt' => $title" )''': r'''str_contains( $helpers, "'alt' => \$title" )''',
    r'''str_contains( $helpers, "'alt' => $name" )''': r'''str_contains( $helpers, "'alt' => \$name" )''',
    r'''$service = new Gloskin_Site_Core_Template_Service('', null, null, null);
$method = new ReflectionMethod($service, 'compare_managed_posts'); $method->setAccessible(true);''': r'''$service_ref = new ReflectionClass(Gloskin_Site_Core_Template_Service::class);
$service = $service_ref->newInstanceWithoutConstructor();
$method = new ReflectionMethod($service, 'compare_managed_posts'); $method->setAccessible(true);''',
}
for source, target in fixes.items():
    if source not in s:
        raise SystemExit('hardening driver source target not found: ' + source)
    s = s.replace(source, target, 1)

p.write_text(s, encoding='utf-8')


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text(encoding='utf-8')
    if text.count(old) != 1:
        raise SystemExit(f'{path}: replacement target count={text.count(old)}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')

# Normal factual detail pages are text-first when identity media is absent.
doctor = Path('plugin/gloskin-site-core/templates/pages/doctor.php')
replace_once(
    doctor,
    '<div class="gloskin-ui1-container gloskin-ui1-detail-hero__grid">',
    '<div class="gloskin-ui1-container gloskin-ui1-detail-hero__grid<?php echo $gloskin_context[\'image_id\'] ? \'\' : \' gloskin-ui1-detail-hero__grid--text-first\'; ?>">',
)
replace_once(
    doctor,
    '<div class="gloskin-ui1-detail-hero__media"><?php if ( $gloskin_context[\'image_id\'] ) : ?><?php echo wp_get_attachment_image( $gloskin_context[\'image_id\'], \'large\', false, array( \'class\' => \'gloskin-ui1-detail-image\' ) ); ?><?php else : ?><?php gloskin_ui1_render_presentation_media( \'doctor\', get_the_title( $gloskin_post ), \'gloskin-ui1-detail-abstract\' ); ?><?php endif; ?></div>',
    '<?php if ( $gloskin_context[\'image_id\'] ) : ?><div class="gloskin-ui1-detail-hero__media"><?php echo wp_get_attachment_image( $gloskin_context[\'image_id\'], \'large\', false, array( \'class\' => \'gloskin-ui1-detail-image\', \'alt\' => get_the_title( $gloskin_post ) ) ); ?></div><?php endif; ?>',
)

clinic = Path('plugin/gloskin-site-core/templates/pages/clinic.php')
replace_once(
    clinic,
    '<div class="gloskin-ui1-container gloskin-ui1-detail-hero__grid">',
    '<div class="gloskin-ui1-container gloskin-ui1-detail-hero__grid<?php echo $gloskin_context[\'gallery_ids\'] ? \'\' : \' gloskin-ui1-detail-hero__grid--text-first\'; ?>">',
)
replace_once(
    clinic,
    '<div class="gloskin-ui1-detail-hero__media"><?php if ( $gloskin_context[\'gallery_ids\'] ) : ?><?php echo wp_get_attachment_image( $gloskin_context[\'gallery_ids\'][0], \'large\', false, array( \'class\' => \'gloskin-ui1-detail-image\' ) ); ?><?php else : ?><?php gloskin_ui1_render_presentation_media( \'clinic\', get_the_title( $gloskin_post ), \'gloskin-ui1-detail-abstract\' ); ?><?php endif; ?></div>',
    '<?php if ( $gloskin_context[\'gallery_ids\'] ) : ?><div class="gloskin-ui1-detail-hero__media"><?php echo wp_get_attachment_image( $gloskin_context[\'gallery_ids\'][0], \'large\', false, array( \'class\' => \'gloskin-ui1-detail-image\', \'alt\' => get_the_title( $gloskin_post ) ) ); ?></div><?php endif; ?>',
)

css = Path('plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css')
css_text = css.read_text(encoding='utf-8')
rule = '.gloskin-ui1-detail-hero__grid--text-first{grid-template-columns:minmax(0,1fr)}'
if rule not in css_text:
    css.write_text(css_text.rstrip() + '\n' + rule + '\n', encoding='utf-8')

# Superseded contract: product consultation may not fabricate an editorial identity image.
consultation_contract = Path('tests/consultation-source-contract.test.js')
replace_once(
    consultation_contract,
    "expect(variant.includes('gloskin_ui1_render_editorial_media') && variant.includes(\"'woocommerce_thumbnail'\"), 'Consultation image must prefer Woo media and retain deterministic editorial fallback');",
    "expect(variant.includes(\"'woocommerce_thumbnail'\") && !variant.includes('gloskin_ui1_render_editorial_media') && variant.includes('gloskin-ui1-card--text-first'), 'Consultation image must use factual Woo media only and degrade text-first when absent');",
)

# The main driver updates literal version strings. Normalize regex-escaped legacy
# version expectations as well so contracts inspect 0.7.142 rather than 0.7.141.
for test in Path('tests').iterdir():
    if test.is_file() and test.suffix in {'.php', '.py', '.sh', '.js'}:
        text = test.read_text(encoding='utf-8')
        if r'0\.7\.141' in text:
            test.write_text(text.replace(r'0\.7\.141', r'0\.7\.142'), encoding='utf-8')

print('final-hardening-driver-fixes: OK')
