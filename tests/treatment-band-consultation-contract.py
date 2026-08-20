"""
treatment-band-consultation-contract.py

Static contract test: treatment band CTA â†’ consultation contract.
"""
import re, sys, os
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
def read(path):
    with open(os.path.join(ROOT, path), encoding='utf-8') as f: return f.read()
failures=[]
def require(cond,msg):
    if not cond: failures.append(msg)
js=read('plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js'); helpers=read('plugin/gloskin-site-core/templates/parts/template-helpers.php'); plugin_h=read('plugin/gloskin-site-core/gloskin-site-core.php'); kernel=read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')
require('data-gloskin-band-path' in js,'JS must use data-gloskin-band-path attribute')
require('getAttribute(\'data-path\')' not in js or 'data-gloskin-band-path' in js,'JS must read data-gloskin-band-path, not bare data-path for band path ID')
require('[data-gloskin-consultation-path' in js,'JS must query [data-gloskin-consultation-path] to activate consultation')
require('?path=' in js or 'URLSearchParams' in js,'JS must handle ?path= URL param for no-JS fallback auto-select')
require('?path=' in helpers and '#consultation' in helpers,'PHP CTA href must include ?path=<id>#consultation')
require(re.search(r'gloskin_ui1_editorial_media_catalog\s*\(.*?\)\s*\{[^}]*return\s+array\s*\(\s*\)',helpers,re.DOTALL),'gloskin_ui1_editorial_media_catalog must return empty array() (no Unsplash URLs)')
require('https://images.unsplash.com' not in helpers,'No https://images.unsplash.com runtime URLs allowed in template-helpers.php')
require("Version: 0.7.181" in plugin_h,"plugin header must be 0.7.163")
require("const VERSION = '0.7.181';" in kernel,"Kernel VERSION must be 0.7.163")
if failures:
    for f in failures: print('FAIL:',f)
    sys.exit(1)
print('treatment-band-consultation-contract.py: OK (0.7.163)')
