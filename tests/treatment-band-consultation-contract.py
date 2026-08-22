"""Static contract: shared Treatment single journey + consultation handoff."""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

def read(path):
    with open(os.path.join(ROOT, path), encoding='utf-8') as handle:
        return handle.read()

failures = []

def require(condition, message):
    if not condition:
        failures.append(message)

js = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js')
helpers = read('plugin/gloskin-site-core/templates/parts/template-helpers.php')
treatment = read('plugin/gloskin-site-core/templates/pages/treatment.php')
footer = read('plugin/gloskin-site-core/templates/parts/footer.php')
template_service = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php')
editorial_css = read('plugin/gloskin-site-core/assets/css/gloskin-ui1-editorial.css')
plugin_header = read('plugin/gloskin-site-core/gloskin-site-core.php')
kernel = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')
reference_path = os.path.join(ROOT, 'docs', 'treatment-flek-pigmentasi-canonical-reference.html')

# Existing consultation handoff behavior remains intact.
require('data-gloskin-band-path' in js, 'JS must use data-gloskin-band-path attribute')
require('[data-gloskin-consultation-path' in js, 'JS must activate consultation paths')
require('?path=' in js or 'URLSearchParams' in js, 'JS must handle ?path= consultation fallback')
require('?path=' in helpers and '#consultation' in helpers, 'shared CTA must preserve ?path=<id>#consultation fallback')
require('https://images.unsplash.com' not in helpers, 'runtime helper must not contain Unsplash URLs')

# Canonical single Treatment reference and one shared runtime owner.
require(os.path.isfile(reference_path), 'canonical Flek & Pigmentasi reference must exist under docs/')
section_order = [
    'data-gloskin-section="treatment-hero"',
    'data-gloskin-section="treatment-consideration"',
    'data-gloskin-section="treatment-related"',
    'data-gloskin-section="treatment-transition"',
]
last = -1
for needle in section_order:
    position = treatment.find(needle)
    require(position > last, 'Treatment section order must include ' + needle)
    require(treatment.count(needle) == 1, 'Treatment section owner must be unique: ' + needle)
    last = position

require('get_the_title( $gloskin_post )' in treatment, 'Treatment hero must use the real post title')
require("$gloskin_context['image_id']" in treatment, 'Treatment hero must use canonical image_id')
require('wp_get_attachment_image( $gloskin_image_id' in treatment, 'Treatment hero must render first-party attachment media')
require("home_url( '/contact/' )" in treatment and 'Buka Kontak' in treatment, 'consideration CTA must point to Contact')
require("$gloskin_context['related_treatments']" in treatment, 'related cards must use canonical related_treatments data')
require('gloskin_ui1_arrow_icon()' in treatment, 'related/transition links must use the shared arrow icon')
require('array_slice' not in treatment, 'Treatment template must not create a second related-item limit owner')
require('post_cards_except( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 3, $post->ID )' in template_service, 'TemplateService must own the maximum of three related Treatments')
require("$gloskin_context['booking_target']" in treatment, 'transition CTA must use the current booking target')

# Static presentation is owned by existing editorial CSS, never by treatment.php.
require('style=' not in treatment, 'Treatment template must contain zero inline style attributes')
require("'style' =>" not in treatment, 'Treatment attachment rendering must contain zero inline style arguments')
for selector in (
    '.gloskin-treatment-single__hero-media{',
    '.gloskin-treatment-single__hero-image{',
    '.gloskin-treatment-single__consideration{',
    '.gloskin-treatment-single__consideration-media{',
    '.gloskin-treatment-single__related-media{',
    '.gloskin-treatment-single__related-body{',
    '.gloskin-treatment-single__transition{',
):
    require(editorial_css.count(selector) == 1, 'existing editorial CSS must own Treatment presentation exactly once: ' + selector)
require('aspect-ratio:3/4' in editorial_css, 'Treatment hero media keeps canonical portrait geometry')
require('aspect-ratio:16/10' in editorial_css, 'Treatment consideration media keeps canonical geometry')
require('aspect-ratio:16/11' in editorial_css, 'Treatment related media keeps canonical geometry')

for legacy in ('treatment-facts', 'treatment-clinics', 'treatment-doctors'):
    require(legacy not in treatment, 'legacy visual band must not render: ' + legacy)
for forbidden in ('fonts.googleapis', 'cdnjs.cloudflare', 'images.unsplash', 'flagcdn', 'FontAwesome', 'href="#"'):
    require(forbidden not in treatment, 'canonical reference dependency must not leak into runtime: ' + forbidden)

require('gloskin-ui1-dark-consultation' not in treatment, 'Treatment template must not duplicate the shared dark CTA')
require('gloskin-ui1-dark-consultation' in footer, 'shared footer must remain the dark consultation CTA owner')

pages_dir = os.path.join(ROOT, 'plugin', 'gloskin-site-core', 'templates', 'pages')
slug_specific = [name for name in os.listdir(pages_dir) if 'flek' in name.lower()]
require(not slug_specific, 'no slug-specific Flek Treatment template may exist')

plugin_version = re.search(r'Version:\s*([^\s]+)', plugin_header)
kernel_version = re.search(r"const VERSION = '([^']+)';", kernel)
require(bool(plugin_version and kernel_version), 'release owners must be readable')
if plugin_version and kernel_version:
    require(plugin_version.group(1) == kernel_version.group(1), 'plugin/kernel versions must remain synchronized')

if failures:
    for failure in failures:
        print('FAIL:', failure)
    sys.exit(1)
print('treatment-band-consultation-contract.py: OK (shared journey + zero-inline presentation ownership)')
