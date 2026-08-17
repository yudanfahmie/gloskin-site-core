#!/usr/bin/env python3
"""Static ownership/semantics contract for Insight editorial typography."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def read(rel): return (ROOT / rel).read_text(encoding='utf-8')
def require(cond, message):
    if not cond: raise AssertionError(message)

base = read('plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css')
production = read('plugin/gloskin-site-core/assets/css/gloskin-ui1-production.css')
editorial = read('plugin/gloskin-site-core/assets/css/gloskin-ui1-editorial.css')
card = read('plugin/gloskin-site-core/templates/parts/insight-card.php')
single = read('plugin/gloskin-site-core/templates/pages/insight-single.php')
runner = read('tests/check-runtime.sh')
core_js = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-core.js')
shop_js = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-shop-discovery.js')

# Global display scales are explicitly out of scope and must remain byte-identical.
require('.gloskin-ui1 h1{font-size:clamp(2.6rem,7vw,5.6rem)}' in base, 'foundation global H1 scale changed')
require('.gloskin-ui1 h2{font-size:clamp(2rem,4vw,3.4rem)}' in base, 'foundation global H2 scale changed')
require('.gloskin-ui1 h1{font-size:clamp(2.5rem,6.4vw,5.25rem)}' in production, 'production global H1 scale changed')
require('.gloskin-ui1 h2{font-size:clamp(1.95rem,3.7vw,3.2rem)}' in production, 'production global H2 scale changed')

regular = '.gloskin-ui1 h2.gloskin-ui1-insights-archive__title{margin:0 0 12px;font-size:clamp(1.1rem,1.4vw,1.45rem);line-height:1.24}'
lead = '.gloskin-ui1 .gloskin-ui1-insights-archive__card--lead h2.gloskin-ui1-insights-archive__title{font-size:clamp(1.6rem,2.5vw,2.3rem);line-height:1.15}'
single_h1 = '.gloskin-ui1 h1.gloskin-ui1-insight-single__title{max-width:21ch;margin:0 auto;font-size:clamp(2rem,4vw,3.6rem);line-height:1.09;text-wrap:balance}'
related = '.gloskin-ui1 .gloskin-ui1-insight-single__related-head h2{margin:0;font-size:clamp(1.55rem,2.4vw,2.15rem);line-height:1.16}'
require(regular in editorial and editorial.count(regular) == 1, 'one regular Insight-card typography owner required')
require(lead in editorial and editorial.count(lead) == 1, 'lead Insight-card typography owner missing')
require(single_h1 in editorial and editorial.count(single_h1) == 1, 'single Insight H1 typography owner missing')
require(related in editorial and editorial.count(related) == 1, 'related-section H2 typography owner missing')
require('.gloskin-ui1-insight-single__content>h2{margin:2.1em 0 .7em;font-size:clamp(1.5rem,2.2vw,2rem);line-height:1.2}' in editorial, 'article-body H2 upper bound missing')
require('.gloskin-ui1-insight-single__content>h3{margin:1.8em 0 .6em;font-size:clamp(1.2rem,2vw,1.5rem)}' in editorial, 'article-body H3 owner changed unexpectedly')
require('padding:clamp(48px,6vw,80px) 0 clamp(30px,4vw,48px)' in editorial, 'single article header rhythm not rebalanced')

# No competing/truncating owner is allowed to reappear.
require(re.search(r'(?m)^\.gloskin-ui1-insights-archive__title\{', editorial) is None, 'low-specificity regular card owner returned')
for forbidden in ('!important', 'line-clamp', '-webkit-line-clamp', 'text-overflow:ellipsis', 'white-space:nowrap'):
    require(forbidden not in editorial, f'forbidden Insight typography/truncation rule: {forbidden}')
require(re.search(r'(^|[;{])(?:max-)?height:', single_h1) is None, 'single title must not use fixed-height clipping')
for js in (core_js, shop_js):
    require('gloskin-ui1-insight-single__title' not in js and 'gloskin-ui1-insights-archive__title' not in js, 'Insight typography must not gain a JS measurement/layout owner')

# Semantic markup and WordPress content ownership stay intact.
require('<h2 class="gloskin-ui1-insights-archive__title">' in card, 'Insight card title must remain H2')
require('<h1 class="gloskin-ui1-insight-single__title">' in single, 'single Insight title must remain H1')
require('<h2 id="gloskin-related-insights">' in single, 'related section title must remain H2')
require("apply_filters( 'the_content', $gloskin_post->post_content )" in single, 'WordPress the_content ownership changed')
require("$gloskin_insight_lead = false; require __DIR__ . '/../parts/insight-card.php';" in single, 'related cards must keep reusing the canonical Insight card partial')
require('insight-typography-contract.py' in runner and 'insight-typography-browser-smoke.py' in runner, 'Insight typography regressions must run through check-runtime.sh')

print('insight typography contract: OK')
