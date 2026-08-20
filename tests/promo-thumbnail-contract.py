"""Static contract test: promo carousel poster/thumbnail selector."""
import re,sys,os
ROOT=os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
def read(path):
    with open(os.path.join(ROOT,path),encoding='utf-8') as f:return f.read()
failures=[]
def require(cond,msg):
    if not cond:failures.append(msg)
helpers=read('plugin/gloskin-site-core/templates/parts/template-helpers.php');js=read('plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js');plugin_h=read('plugin/gloskin-site-core/gloskin-site-core.php');kernel=read('plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php')
require('data-gloskin-promo-thumb' in helpers,'carousel template must render data-gloskin-promo-thumb thumbnail buttons');require('data-gloskin-promo-thumb="' in helpers,'thumbnail buttons must have data-gloskin-promo-thumb="<index>" attribute');require('gloskin-ui1-promo-carousel__thumbs' in helpers and 'role="tablist"' in helpers,'thumbnail strip must use role=tablist for keyboard accessibility');require('aria-selected=' in helpers and 'tabindex=' in helpers,'thumbnail buttons must carry aria-selected and tabindex attributes');require('promos_with_images' in helpers or 'image_id' in helpers,'thumbnail strip must be conditional on promos having Featured Images');require('data-gloskin-promo-thumb' in js,'initPromoCarousel() JS must query data-gloskin-promo-thumb elements')
carousel_fn_start=js.find('function initPromoCarousel()');carousel_fn_end=js.find('\n\t}',carousel_fn_start+1) if carousel_fn_start!=-1 else -1;carousel_body=js[carousel_fn_start:carousel_fn_end] if carousel_fn_start!=-1 and carousel_fn_end!=-1 else ''
require('thumbs' in carousel_body and "thumbs[t].setAttribute('aria-selected'" in carousel_body and "thumbs[t].classList.add('is-active')" in carousel_body,'initPromoCarousel() must sync thumbnail selected/active state');require('thumbs[ti]' in js or 'thumb.addEventListener' in js,'thumbnail buttons must have click event listeners wired in JS');require('data-gloskin-promo-prev' in helpers and 'data-gloskin-promo-next' in helpers,'existing prev/next controls must remain in carousel template');require('data-gloskin-promo-dot' in helpers,'existing dot controls must remain in carousel template');require('data-gloskin-promo-autoplay' in helpers,'compact carousel template must opt in to autoplay via data-gloskin-promo-autoplay');require('data-gloskin-promo-live' in helpers,'carousel template must include a live region element with data-gloskin-promo-live');require('setInterval' in carousel_body,'initPromoCarousel() must contain setInterval for autoplay');require('data-gloskin-promo-autoplay' in carousel_body,'initPromoCarousel() must gate setInterval on data-gloskin-promo-autoplay attribute');require('Version: 0.7.171' in plugin_h,'plugin header must be 0.7.171');require("const VERSION = '0.7.171';" in kernel,'Kernel VERSION must be 0.7.171')
if failures:
    for f in failures:print('FAIL:',f)
    sys.exit(1)
print('promo-thumbnail-contract.py: OK (0.7.171)')
