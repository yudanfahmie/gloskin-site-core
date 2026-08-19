"""
promo-date-order-contract.py

Static contract test: promo date eligibility + CPT display ordering.

Asserts:
  1. is_promo_date_eligible() exists in template-service and uses DateTimeImmutable + wp_timezone().
  2. managed_promo_records() calls is_promo_date_eligible() for date filtering.
  3. managed_promo_records() sorts by gloskin_promo_order meta.
  4. published_managed_records() accepts an order_meta_key parameter.
  5. home_context() passes gloskin_testimonial_order as order key for testimonials.
  6. home_context() passes gloskin_achievement_order as order key for achievements.
  7. about_context() passes gloskin_achievement_order as order key for achievements.
  8. gloskin_promo_start_date / gloskin_promo_end_date are read in is_promo_date_eligible.
  9. Plugin / Kernel version synchronized at 0.7.138.
"""

import re, sys, os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

def read(path):
    with open(os.path.join(ROOT, path), encoding='utf-8') as f:
        return f.read()

failures = []

def require(cond, msg):
    if not cond:
        failures.append(msg)

svc      = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php')
plugin_h = read('plugin/gloskin-site-core/gloskin-site-core.php')
kernel   = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')

# 1. is_promo_date_eligible exists and uses DateTimeImmutable + wp_timezone
require('is_promo_date_eligible' in svc, 'template-service must have is_promo_date_eligible()')
require('DateTimeImmutable' in svc, 'is_promo_date_eligible must use DateTimeImmutable for timezone-correct comparison')
require('wp_timezone' in svc, 'is_promo_date_eligible must use wp_timezone()')

# 2. managed_promo_records calls is_promo_date_eligible
require(
    re.search(r'managed_promo_records.*?is_promo_date_eligible', svc, re.DOTALL),
    'managed_promo_records() must call is_promo_date_eligible()'
)

# 3. managed_promo_records sorts by gloskin_promo_order
require('gloskin_promo_order' in svc, 'managed_promo_records() must sort by gloskin_promo_order meta')

# 4. published_managed_records has order_meta_key parameter
require('order_meta_key' in svc, 'published_managed_records() must accept an $order_meta_key parameter')

# 5. home_context passes gloskin_testimonial_order
require('gloskin_testimonial_order' in svc, 'home_context() must pass gloskin_testimonial_order as order key')

# 6. home_context passes gloskin_achievement_order
require('gloskin_achievement_order' in svc, 'home_context() must pass gloskin_achievement_order as order key')

# 7. about_context passes gloskin_achievement_order (checked by presence; same key)
require(
    svc.count('gloskin_achievement_order') >= 2,
    'about_context() must also pass gloskin_achievement_order (found fewer than 2 occurrences)'
)

# 8. Date meta keys read in is_promo_date_eligible
require('gloskin_promo_start_date' in svc, 'is_promo_date_eligible must read gloskin_promo_start_date')
require('gloskin_promo_end_date' in svc, 'is_promo_date_eligible must read gloskin_promo_end_date')

# 9. Version sync
require("Version: 0.7.138" in plugin_h, "plugin header must be 0.7.138")
require("const VERSION = '0.7.138';" in kernel, "Kernel VERSION must be 0.7.138")

if failures:
    for f in failures:
        print('FAIL:', f)
    sys.exit(1)

print('promo-date-order-contract.py: OK (0.7.138)')
