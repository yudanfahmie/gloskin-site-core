#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'plugin' / 'gloskin-site-core'
PUBLIC_FILES = list((PLUGIN / 'templates').rglob('*.php')) + [
    PLUGIN / 'includes' / 'class-gloskin-site-core-navigation-service.php',
    PLUGIN / 'includes' / 'class-gloskin-site-core-template-service.php',
    PLUGIN / 'includes' / 'class-gloskin-site-core-woocommerce-adapter.php',
    PLUGIN / 'includes' / 'class-gloskin-site-core-lifecycle-service.php',
]

forbidden = [
    'Treatments', 'Clinic network', 'Doctors', 'Contact', 'View treatments',
    'Visit shop', 'Find a clinic', 'Explore clinic', 'Related treatments',
    'Medical team', 'Browse skincare', 'View details', 'View product',
    'Open map', 'Primary navigation', 'Mobile navigation', 'Close navigation',
    'Skip to content', 'Book / Contact', 'Practice branches', 'Benefits',
    'Contraindications', 'Profile', 'Credentials', 'Schedule', 'Address',
    'Phone', 'Operating hours', 'Contact form', 'Products', 'Shop', 'Insights',
    'About Gloskin', 'Clinics', 'Treatment', 'Doctor', 'Clinic information',
]

forbidden_dummy = [
    'lorem ipsum', 'placeholder', 'dummy', 'coming soon', 'content pending',
    'not configured', 'staging', 'developer', 'morgen', 'test product',
    'test-001', 'na00000000000', 'test composition', 'test usage',
]

# Review Gloskin-owned public literals: translated strings plus canonical fallback labels.
patterns = [
    re.compile(r"(?:__|esc_html__|esc_attr__)\(\s*'([^']+)'") ,
    re.compile(r"fallback_item\(\s*'([^']+)'") ,
]
reviewed = []
violations = []
for path in PUBLIC_FILES:
    text = path.read_text(encoding='utf-8')
    strings = []
    for pattern in patterns:
        strings.extend(pattern.findall(text))
    for value in strings:
        reviewed.append((path, value))
        for phrase in forbidden + forbidden_dummy:
            if phrase.lower() in value.lower():
                violations.append((path, value, phrase))


# Fresh-install page titles are also Gloskin-owned public copy. Limit this
# parser to LifecycleService so internal route/context maps are not mistaken
# for visitor labels.
lifecycle = PLUGIN / 'includes' / 'class-gloskin-site-core-lifecycle-service.php'
page_title_pattern = re.compile(r"'(?:home|about|treatments|clinics|contact|insights|shop|doctors)'\s*=>\s*'([^']+)'")
for value in page_title_pattern.findall(lifecycle.read_text(encoding='utf-8')):
    reviewed.append((lifecycle, value))
    for phrase in forbidden + forbidden_dummy:
        if phrase.lower() in value.lower():
            violations.append((lifecycle, value, phrase))

if violations:
    for path, value, phrase in violations:
        print(f'{path.relative_to(ROOT)}: English public UI leak {phrase!r} in {value!r}', file=sys.stderr)
    raise SystemExit(1)

unique_files = len({p for p, _ in reviewed})
print(f'language checks passed ({len(reviewed)} Gloskin-owned public strings reviewed across {unique_files} files)')
