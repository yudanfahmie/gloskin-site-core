"""
final-migration-js-behavior-contract.py

Static contract: JS-level behaviors for the v0.7.160 AJAX migration controller.
"""
import os,re,sys
ROOT=os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
def read(path):
    with open(os.path.join(ROOT,path),encoding='utf-8') as f:return f.read()
failures=[]
def require(cond,msg):
    if not cond:failures.append(msg)
js=read('plugin/gloskin-site-core/assets/js/gloskin-ui1-final-migration.js'); kernel=read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php'); plugin_h=read('plugin/gloskin-site-core/gloskin-site-core.php')
require("if ( ! root ) { return; }" in js or "if (!root)" in js or "if ( ! root )" in js,"JS must return early if [data-gloskin-final-migration] root not found")
require("if ( ! ajaxUrl || ! action || ! nonce ) { return; }" in js or "if (!ajaxUrl || !action || !nonce)" in js,"JS must return early if data-ajax, data-action, or data-nonce are empty")
require("'pending' === status" in js or '"pending" === status' in js or "=== 'pending'" in js,"JS must call mode=start only when data-status is 'pending'"); require("'start'" in js,"JS must issue mode=start AJAX request for fresh starts"); require("'continue'" in js,"JS must issue mode=continue AJAX request for resume"); require("failed" in js and "'continue'" in js,"JS must use mode=continue for failed/running/verifying states (no start handshake)")
fail_fn_match=re.search(r'function onFail\s*\([^)]*\)\s*\{(.+?)\n\s*\}',js,re.S)
if fail_fn_match: require('window.location' not in fail_fn_match.group(1),"onFail() must NOT reload the page (no window.location assignment)")
else: require(False,"onFail() function must be defined in JS")
require('showError(' in js,"onFail() must call showError() to display inline error message"); require('Lanjutkan Finalisasi' in js,"onFail() must restore button with 'Lanjutkan Finalisasi' label"); require('button.disabled = false' in js or 'button.disabled=false' in js,"onFail() must re-enable the button"); require('gloskin-content' in js,"onConsumed() must navigate to gloskin-content admin page"); require('window.location.href' in js,"onConsumed() must set window.location.href for navigation"); require('Finalisasi Prototype' in js,"onConsumed() must target 'Finalisasi Prototype' text to remove notice and menu item"); require('#adminmenu' in js,"onConsumed() must remove the migration item from #adminmenu"); require('requestAnimationFrame' in js,"continueChain() must use requestAnimationFrame to yield between checkpoints"); require('preventDefault' in js,"JS must call event.preventDefault() on form submit to intercept the POST fallback"); require('fetch(' in js or 'fetch (' in js,"JS must use fetch() for AJAX requests"); require('application/x-www-form-urlencoded' in js,"AJAX request must use application/x-www-form-urlencoded Content-Type"); require('processed_steps' in js,"JS must read processed_steps from AJAX state response"); require('processed_products' not in js,"JS must NOT reference processed_products (old sample-import key)"); require('total_steps' in js,"JS must read total_steps from AJAX state response"); require('expected_products' not in js,"JS must NOT reference expected_products (old sample-import key)"); require("Version: 0.7.160" in plugin_h,"Plugin header must be 0.7.160"); require("const VERSION = '0.7.160';" in kernel,"Kernel VERSION must be 0.7.160")
if failures:
    for f in failures:print('FAIL:',f)
    sys.exit(1)
print('final-migration-js-behavior-contract.py: OK (0.7.160)')
