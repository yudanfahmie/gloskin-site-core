"""
single-migration-action-contract.py

Static contract test: admin exposes exactly one relevant prototype-finalization action.

Asserts:
  1.  Kernel requires Final Migration admin file.
  2.  Kernel instantiates Final Migration admin class.
  3.  Kernel does NOT require Prototype IA Migration admin file.
  4.  Kernel does NOT instantiate Prototype IA Migration admin class.
  5.  Final Migration admin has a distinct SLUG (not the old revision slug).
  6.  Insight Migration admin is still required (genuinely independent â€” must NOT be removed).
  7.  Final Migration admin title is "Finalisasi Prototype & Data".
  8.  Old revision migration admin (non-final) is not registered in kernel.
  9.  Plugin / Kernel version synchronized at 0.7.160.
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

kernel   = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')
final_adm = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration-admin.php')
plugin_h = read('plugin/gloskin-site-core/gloskin-site-core.php')

require('class-gloskin-site-core-revision-20260819-final-migration-admin.php' in kernel,'Kernel must require the final migration admin file')
require('Gloskin_Site_Core_Revision_20260819_Final_Migration_Admin' in kernel,'Kernel must instantiate Gloskin_Site_Core_Revision_20260819_Final_Migration_Admin')
require('class-gloskin-site-core-prototype-ia-migration-admin.php' not in kernel,'Kernel must NOT require prototype-ia-migration-admin.php (single migration UI)')
require('Gloskin_Site_Core_Prototype_IA_Migration_Admin' not in kernel,'Kernel must NOT instantiate Prototype_IA_Migration_Admin (single migration UI)')
require('gloskin-revision-20260819-final-migration' in final_adm,'Final Migration admin SLUG must use -final- suffix (distinct from old revision slug)')
require("const SLUG = 'gloskin-revision-20260819-migration'" not in final_adm,'Final Migration admin SLUG must not be the old (non-final) revision slug')
require('class-gloskin-site-core-insight-migration-admin.php' in kernel,'Insight Migration admin MUST remain registered in kernel (independent editorial content)')
require('Finalisasi Prototype & Data' in final_adm or 'Finalisasi Prototype' in final_adm,"Final Migration admin title must be 'Finalisasi Prototype & Data'")
require('class-gloskin-site-core-revision-20260819-migration-admin.php' not in kernel,'Old (non-final) revision migration admin must not be registered in kernel')
require("Version: 0.7.160" in plugin_h, "plugin header must be 0.7.160")
require("const VERSION = '0.7.160';" in kernel, "Kernel VERSION must be 0.7.160")

if failures:
    for f in failures:
        print('FAIL:', f)
    sys.exit(1)

print('single-migration-action-contract.py: OK (0.7.160)')
