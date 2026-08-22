"""Static contract: Promo carousel controls and accessible live-region presentation."""
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

plugin_version = re.search(r'Version:\s*([^\s]+)', plugin_header)
kernel_version = re.search(r"const VERSION = '([^']+)';", kernel)
require(bool(plugin_version and kernel_version), 'release owners must be readable')
if plugin_version and kernel_version:
    require(plugin_version.group(1) == kernel_version.group(1), 'plugin/kernel versions must remain synchronized')

if failures:
    for failure in failures:
        print('FAIL:', failure)
    sys.exit(1)
print('promo-thumbnail-contract.py: OK')
