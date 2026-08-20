"""Static contract: promo date eligibility + CPT display ordering."""
import re, sys, os
ROOT=os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
def read(path):
    with open(os.path.join(ROOT,path),encoding='utf-8') as f:return f.read()
failures=[]
def require(cond,msg):
    if not cond:failures.append(msg)
svc=read('plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php'); plugin_h=read('plugin/gloskin-site-core/gloskin-site-core.php'); kernel=read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')
require('is_promo_date_eligible' in svc,'template-service must have is_promo_date_eligible()'); require('DateTimeImmutable' in svc,'is_promo_date_eligible must use DateTimeImmutable for timezone-correct comparison'); require('wp_timezone' in svc,'is_promo_date_eligible must use wp_timezone()'); require(re.search(r'managed_promo_records.*?is_promo_date_eligible',svc,re.DOTALL),'managed_promo_records() must call is_promo_date_eligible()'); require('gloskin_promo_order' in svc,'managed_promo_records() must sort by gloskin_promo_order meta'); require('order_meta_key' in svc,'published_managed_records() must accept an $order_meta_key parameter'); require('gloskin_testimonial_order' in svc,'home_context() must pass gloskin_testimonial_order as order key'); require('gloskin_achievement_order' in svc,'home_context() must pass gloskin_achievement_order as order key'); require(svc.count('gloskin_achievement_order')>=2,'about_context() must also pass gloskin_achievement_order (found fewer than 2 occurrences)'); require('gloskin_promo_start_date' in svc,'is_promo_date_eligible must read gloskin_promo_start_date'); require('gloskin_promo_end_date' in svc,'is_promo_date_eligible must read gloskin_promo_end_date'); require("Version: 0.7.161" in plugin_h,"plugin header must be 0.7.161"); require("const VERSION = '0.7.161';" in kernel,"Kernel VERSION must be 0.7.161")
if failures:
    for f in failures:print('FAIL:',f)
    sys.exit(1)
print('promo-date-order-contract.py: OK (0.7.161)')
