"""Static contract: Promo carousel accessibility + canonical 1648:928 smart crop geometry."""
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

promo = read('plugin/gloskin-site-core/templates/pages/promo.php')
js = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js')
core_base = read('plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css')
editorial = read('plugin/gloskin-site-core/assets/css/gloskin-ui1-editorial.css')
admin_crop = read('plugin/gloskin-site-core/assets/css/gloskin-editorial-manager.css')
content_service = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-content-service.php')
plugin_header = read('plugin/gloskin-site-core/gloskin-site-core.php')
kernel = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')

# Existing carousel controls remain bound to the existing controller.
require('data-gloskin-promo-prev' in promo and 'data-gloskin-promo-next' in promo, 'Promo prev/next controls must remain')
require('data-gloskin-promo-dot' in promo, 'Promo dot controls must remain')
require('data-gloskin-promo-carousel' in promo, 'Promo carousel shell must remain')
require('data-gloskin-promo-live' in js, 'existing Promo controller must update the live region')
require('data-gloskin-promo-prev' in js and 'data-gloskin-promo-next' in js, 'existing controller must bind prev/next')
require('data-gloskin-promo-dot' in js, 'existing controller must bind dots')

# One live-region owner per rendered carousel; visually hidden, never removed from accessibility APIs.
require(promo.count('data-gloskin-promo-live') == 1, 'Promo template must define exactly one live-region owner per carousel')
live_match = re.search(r'<div\s+class="([^"]*gloskin-ui1-promo-carousel__live[^"]*)"\s+aria-live="polite"\s+aria-atomic="true"\s+data-gloskin-promo-live', promo)
require(bool(live_match), 'Promo live region must preserve aria-live=polite and aria-atomic=true')
if live_match:
    require('screen-reader-text' in live_match.group(1).split(), 'Promo live region must use the canonical screen-reader-text primitive')
require('gloskin-ui1-promo-carousel__counter' not in promo, 'no visible Promo counter component may be rendered')

screen_reader_rule = re.search(r'\.gloskin-ui1\s+\.screen-reader-text\s*\{([^}]*)\}', core_base, re.S)
require(bool(screen_reader_rule), 'canonical .screen-reader-text CSS primitive must exist')
if screen_reader_rule:
    require('display:none' not in screen_reader_rule.group(1).replace(' ', ''), 'screen-reader-text must not use display:none')

# Admin crop modal keeps actions/footer visible and uses horizontal space before vertical stacking.
require('.gloskin-editorial-modal__dialog>form{display:flex;min-height:0;flex:1 1 auto;flex-direction:column}' in admin_crop, 'modal form must be a bounded flex column so footer cannot be clipped')
require('.gloskin-editorial-modal__body{display:grid;min-height:0;flex:1 1 auto;' in admin_crop and 'overflow-y:auto' in admin_crop, 'modal body must own scrolling with min-height:0')
require('.gloskin-editorial-modal__dialog:has([data-gloskin-promo-crop]){width:min(1120px,100%)}' in admin_crop, 'Promo editor must use a wider desktop workspace')
require('grid-template-areas:"workspace toolbar" "workspace output" "workspace quality" "workspace hint"' in admin_crop, 'desktop crop UI must place controls/output beside the workspace rather than below it')
require('@media (max-width:960px)' in admin_crop and 'grid-template-areas:"workspace" "toolbar" "output" "quality" "hint"' in admin_crop, 'crop editor must collapse cleanly on narrower screens')
require('max-height:calc(100dvh - 40px)' in admin_crop, 'desktop modal height must track the dynamic viewport')

# Frontend owns one fixed production crop ratio across all responsive widths.
require(editorial.count('aspect-ratio:1648 / 928') == 1, 'frontend editorial CSS must own exactly one canonical Promo ratio declaration')
require('.gloskin-promo__media{position:relative;width:100%;aspect-ratio:1648 / 928;overflow:hidden' in editorial, 'Promo media geometry must be width-driven, not intrinsic-image-driven')
require('object-fit:cover;object-position:var(--gloskin-promo-focus-x,50%) var(--gloskin-promo-focus-y,50%);transform:scale(var(--gloskin-promo-scale,1));transform-origin:var(--gloskin-promo-focus-x,50%) var(--gloskin-promo-focus-y,50%)' in editorial, 'Promo image must reproduce focus + crop-size state')
require('.gloskin-promo__media{height:' not in editorial, 'Promo media must not switch to fixed pixel heights at breakpoints')
require('--gloskin-promo-focus-x:' in promo and '--gloskin-promo-focus-y:' in promo, 'Promo template must inline only dynamic focal custom-property values')
require('--gloskin-promo-scale:' in promo and '$gloskin_promo_scale' in promo, 'Promo template must expose persisted crop zoom as one dynamic scale property')
require("get_post_meta( $gloskin_promo_id, 'gloskin_promo_crop_zoom', true )" in promo, 'Promo template consumes canonical crop zoom when projection predates zoom')
require("'zoom_meta'        => 'gloskin_promo_crop_zoom'" in content_service, 'ContentService owns canonical Promo zoom meta')
require("'zoom_min'         => 100" in content_service and "'zoom_max'         => 300" in content_service, 'canonical crop zoom is bounded 100..300')
require("wp_get_attachment_image( $gloskin_promo_image, 'full'" in promo, 'frontend uses full validated source image rather than a down-sized large derivative')

# Both limited and regular collections share exactly the same renderer/geometry owner.
require(promo.count('$gloskin_render_promo_carousel(') == 2, 'limited and regular Promo collections must use the same renderer')
require("'limited' );" in promo and "'regular' );" in promo, 'both Promo types must enter the shared renderer')

plugin_version = re.search(r'Version:\s*([^\s]+)', plugin_header)
kernel_version = re.search(r"const VERSION = '([^']+)';", kernel)
require(bool(plugin_version and kernel_version), 'release owners must be readable')
if plugin_version and kernel_version:
    require(plugin_version.group(1) == kernel_version.group(1), 'plugin/kernel versions must remain synchronized')

if failures:
    for failure in failures:
        print('FAIL:', failure)
    sys.exit(1)
print('promo-thumbnail-contract.py: OK (a11y + responsive admin smart crop + shared 1648:928 focus/zoom geometry)')
