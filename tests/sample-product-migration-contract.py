#!/usr/bin/env python3
from pathlib import Path
import hashlib,json
R=Path(__file__).resolve().parents[1]; A=R/'migration-source/gloskin-sample-products-v1'; D=R/'plugin/gloskin-site-core/migration-runtime/gloskin-sample-products-v1'; P=R/'plugin/gloskin-site-core'
def req(x,m):
    if not x: raise AssertionError(m)
def read(p): return p.read_text(encoding='utf-8')
for n in ('manifest.json','products.json','media.json'): req((A/n).read_bytes()==(D/n).read_bytes(),f'dual copy differs: {n}')
manifest=json.loads(read(A/'manifest.json')); ps=json.loads(read(A/'products.json'))['products']; ms=json.loads(read(A/'media.json'))['media']
req((manifest['expected_products'],manifest['expected_simple'],manifest['expected_variable'],manifest['expected_variations'],manifest['expected_media'])==(13,8,5,10,58),'manifest totals')
for n in ('products.json','media.json'): req(hashlib.sha256((A/n).read_bytes()).hexdigest()==manifest['checksums'][n],f'checksum {n}')
req(len(ps)==13 and sum(p['type']=='simple' for p in ps)==8 and sum(p['type']=='variable' for p in ps)==5,'parent/type totals'); req(sum(len(p.get('variations',[])) for p in ps)==10 and len(ms)==58,'variation/media totals')
req({p['category_slug'] for p in ps}=={'facial-wash','day-cream-sunscreen','toner','serum','acne-care','anti-aging','brightening-pigmentation-care'},'category coverage')
ids=[]; skus=[]
for p in ps:
    req(p['source_id'].startswith('gloskin-sample:v1:') and p['status']=='draft','product identity/status'); req(p.get('bpom','')==p.get('composition','')=='','unverified facts'); req(p.get('short_description') and p.get('description') and p.get('usage'),'copy')
    ids.append(p['source_id']); skus.append(p['sku'])
    for v in p.get('variations',[]): req(v['status']=='publish' and v['source_id'].startswith(p['source_id']+':'),'variation identity/status'); ids.append(v['source_id']); skus.append(v['sku'])
req(len(ids)==len(set(ids)) and len(skus)==len(set(skus)),'identity/SKU uniqueness')
by={p['source_id']:p for p in ps}; seen=set()
for m in ms:
    req(m['source_id'].startswith('gloskin-sample-media:v1:') and m['source_id'] not in seen,'media identity'); seen.add(m['source_id']); req(m['product_source_id'] in by and m['source_url'].startswith('https://'),'media parent/url'); req(m['license_note'] and m['alt'] and Path(m['filename']).suffix.lower() in {'.jpg','.jpeg','.png','.webp'},'media provenance')
for sid,p in by.items():
    rows=[m for m in ms if m['product_source_id']==sid]; req(3<=len(rows)<=6 and len(rows)==p['media_count'],'media per parent'); req(sum(m['role']=='featured' for m in rows)==1 and sorted(m['sort_order'] for m in rows)==list(range(1,len(rows)+1)),'media role/order')
req('synthetic' in manifest['notice'].lower() and 'not verified commercial product truth' in manifest['notice'].lower(),'notice'); req('consumed' in read(A/'README.md').lower(),'one-shot docs')
admin=read(P/'includes/class-gloskin-site-core-admin-service.php'); asset=read(P/'includes/class-gloskin-site-core-asset-service.php'); kernel=read(P/'includes/class-gloskin-site-core-kernel.php'); importer=read(P/'includes/class-gloskin-site-core-sample-product-importer.php'); bundle=read(P/'includes/class-gloskin-site-core-sample-product-bundle.php'); js=read(P/'assets/js/gloskin-ui1-sample-product-import.js')
req('manage_woocommerce' in admin and 'check_ajax_referer' in admin and 'wp_ajax_' in admin,'admin auth'); req('wp_ajax_nopriv' not in admin+importer+js and 'register_rest_route' not in admin+importer+js,'no public path'); req('enqueue_admin_migration' in asset and "const VERSION = '0.7.40'" in kernel,'asset/version')
for s in ('pending','validating','running','failed','verifying','consumed'): req(s in importer,f'state {s}')
for t in ('_gloskin_sample_source_id','_gloskin_sample_media_source_id','LOCK_TTL'): req(t in importer+bundle,f'contract {t}')
req('public function cleanup(' in bundle and '$this->bundle->cleanup( $manifest )' in importer,'cleanup contract')
req("$variation->set_status( 'publish' )" in importer and "'publish' !== (string) $variation->get_status()" in importer,'variation publish contract')
req('bundle_fingerprint' in importer and 'Bundle sample product berubah setelah import dimulai. Selesaikan/reconcile bundle sebelum melanjutkan.' in importer,'payload fingerprint contract')
req('get_attached_file' in importer and 'File attachment lokal hilang atau rusak' in importer,'broken attachment reuse contract')
req("__( 'Products', 'gloskin-site-core' )" in admin and "__( 'Simple', 'gloskin-site-core' )" in admin and "__( 'Variable', 'gloskin-site-core' )" in admin and "__( 'Variations', 'gloskin-site-core' )" in admin and "__( 'Images', 'gloskin-site-core' )" in admin,'admin counters render truthfully server-side')
req("__( 'Import Sample Products', 'gloskin-site-core' )" in admin and "__( 'Resume Import', 'gloskin-site-core' )" in admin,'admin button labels render server-side')
req('Produk parent dibuat sebagai draft. Variasi produk variable disiapkan aktif agar langsung berfungsi ketika parent dipublikasikan.' in admin,'admin status copy is truthful server-side')
req('normalizeScreen' not in js and 'cloneNode' not in js,'JS must not rewrite static admin presentation structure')
req('sideload' in importer.lower() and 'setInterval' not in js and 'Math.random' not in js,'media/orchestration')
print('sample-product-migration-contract: OK')
