'use strict';

const fs = require('fs');
const path = require('path');
function fail(message) { console.error(message); process.exit(1); }
function expect(condition, message) { if (!condition) fail(message); }

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const frontend = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-consultation.js');
const frontendCss = read('plugin/gloskin-site-core/assets/css/gloskin-ui1-consultation.css');
const admin = read('plugin/gloskin-site-core/assets/js/gloskin-ui1-consultation-admin.js');
const adminCss = read('plugin/gloskin-site-core/assets/css/gloskin-ui1-consultation-admin.css');
const assetsConfig = read('plugin/gloskin-site-core/config/assets.php');
const assetService = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php');
const adminService = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php');
const templateService = read('plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php');
const treatmentTemplate = read('plugin/gloskin-site-core/templates/pages/treatments.php');
const productHelpers = read('plugin/gloskin-site-core/templates/parts/template-helpers.php');

for (const forbidden of ['localStorage', 'sessionStorage', 'document.cookie', 'XMLHttpRequest', 'fetch(']) {
  expect(!frontend.includes(forbidden), `Finder persistence/network contract violated by ${forbidden}`);
}
for (const obsolete of ['fisherYates', 'eligibleQuestionsForPath', 'state.questions', 'state.history', 'state.scores', 'data-gloskin-consultation-question', 'data-gloskin-consultation-back', 'data-gloskin-consultation-restart']) {
  expect(!frontend.includes(obsolete), `Public questionnaire runtime must be absent: ${obsolete}`);
}
expect(frontend.includes('selectedConcernIds: []'), 'Finder state must contain only the current path and selected concern IDs');
expect(frontend.includes('state.selectedConcernIds = [];'), 'Changing path must reset selected concerns');
expect(frontend.includes('hideStaleResults();'), 'Changing any selection must hide stale recommendations');
expect(frontend.includes('scoreAndRankProducts(products, state.selectedConcernIds, 8)'), 'Explicit CTA must rank no more than eight SSR products');
expect(frontend.includes("submitButton.addEventListener('click', showResults)"), 'Recommendations must appear only from the explicit CTA');

expect(treatmentTemplate.includes("wp_json_encode( array( 'paths' => $gloskin_consultation['paths'] ) )"), 'Public payload must contain canonical paths only');
expect(!treatmentTemplate.includes("'questions' =>"), 'Private questions must never enter public template data');
expect(treatmentTemplate.includes('type="checkbox"') && treatmentTemplate.includes('data-gloskin-consultation-concern'), 'Baseline concerns must be native multi-select checkboxes');
expect(treatmentTemplate.includes('gloskin-ui1-consultation__helper') && treatmentTemplate.includes('Anda dapat memilih lebih dari satu keluhan.'), 'Finder must explain that multiple concerns may be selected');
expect(treatmentTemplate.includes("gloskin_ui1_render_product_card( $gloskin_treatment_product, 'consultation' )"), 'Finder results must use the shared consultation product-card variant');
const consultationIndex = treatmentTemplate.indexOf('data-gloskin-section="treatments-consultation"');
const closingIndex = treatmentTemplate.indexOf('data-gloskin-section="treatments-closing"');
expect(consultationIndex >= 0 && closingIndex > consultationIndex, 'Treatments composition must be finder/results followed by one final CTA');
for (const obsolete of ['treatments-orientation', 'treatments-featured', 'treatments-pathways', 'treatments-discovery', 'Sebelum memilih', 'Informasi Perawatan', 'Belum ada perawatan yang tersedia']) {
  expect(!treatmentTemplate.includes(obsolete), `Removed Treatments block must stay absent: ${obsolete}`);
}

const treatmentsContextStart = templateService.indexOf('private function treatments_context()');
const treatmentsContextEnd = templateService.indexOf('\n\tprivate function consultation_context()', treatmentsContextStart);
const treatmentsContext = templateService.slice(treatmentsContextStart, treatmentsContextEnd);
expect(treatmentsContextStart >= 0 && treatmentsContextEnd > treatmentsContextStart, 'Treatments page context owner missing');
for (const staleProjection of ['post_cards(', 'TREATMENT_POST_TYPE', "'treatments' =>", "'target' =>"]) {
  expect(!treatmentsContext.includes(staleProjection), `Treatments page context must not retain informational directory projection: ${staleProjection}`);
}

const contextStart = templateService.indexOf('private function consultation_context()');
const contextEnd = templateService.indexOf('\n\tprivate function', contextStart + 1);
const context = templateService.slice(contextStart, contextEnd < 0 ? undefined : contextEnd);
expect(contextStart >= 0, 'Consultation context owner missing');
expect(!context.includes('QUESTION_MIN_PUBLISHED') && !context.includes('QUESTION_POST_TYPE'), 'Public readiness must not depend on private questions');
expect(context.includes("'concerns'  => $path_concerns") && context.includes('4 !== count( $paths )'), 'Public readiness must require exactly four paths with resolved baseline concerns');
expect(context.includes('treatment_products_with_concerns()'), 'Products must come from the canonical Woo adapter');

const variantStart = productHelpers.indexOf("if ( 'consultation' === $variant )");
const variantEnd = productHelpers.indexOf('\n\t\t$type', variantStart);
const variant = productHelpers.slice(variantStart, variantEnd);
expect(variantStart >= 0 && variantEnd > variantStart, 'Consultation product-card variant missing');
expect((variant.match(/<a /g) || []).length === 1, 'Consultation card must contain exactly one anchor');
expect(variant.includes('gloskin_ui1_render_editorial_media') && variant.includes("'woocommerce_thumbnail'"), 'Consultation image must prefer Woo media and retain deterministic editorial fallback');
for (const forbidden of ['wishlist', 'add_to_cart', 'ajax_add_to_cart', 'quickadd', "'button'"]) {
  expect(!variant.toLowerCase().includes(forbidden.toLowerCase()), `Consultation variant must be detail-only: ${forbidden}`);
}

expect(treatmentTemplate.includes('gloskin-ui1-consultation__disclaimer'), 'Public finder disclaimer canonical class must remain');
expect(templateService.includes('Hasil ini membantu eksplorasi pilihan dan bukan diagnosis medis.'), 'Public finder disclaimer copy must remain');
expect(/\.gloskin-ui1-consultation__panel \.gloskin-ui1-consultation__disclaimer\{[\s\S]*?margin-inline:auto;[\s\S]*?text-align:center;[\s\S]*?\}/.test(frontendCss), 'Disclaimer must be centered via margin-inline:auto and text-align:center under its canonical panel-scoped owner');
expect(/\.gloskin-ui1-consultation__disclaimer\{[^}]*max-width:min\(100%,52ch\)/.test(frontendCss), 'Disclaimer must keep a bounded max-width');
for (const hack of ['gloskin-ui1-consultation__disclaimer{position:absolute', 'gloskin-ui1-consultation__disclaimer{left:', 'translateX']) {
  expect(!frontendCss.includes(hack), `Disclaimer centering must contain zero absolute/translate hack: ${hack}`);
}

expect(frontendCss.includes('grid-template-columns:repeat(2,minmax(0,1fr))'), 'Desktop result grid must use two columns');
expect(frontendCss.includes('@media (max-width:720px)') && frontendCss.includes('grid-template-columns:minmax(0,1fr)'), 'Mobile result grid must stack to one column');
expect(frontendCss.includes('-webkit-line-clamp:3'), 'Result copy must clamp without an internal scrollbar');
expect(!/overflow\s*:\s*(auto|scroll)/.test(frontendCss), 'Finder CSS must contain no internal auto/scroll overflow');
expect(!frontendCss.includes('!important'), 'Finder CSS must contain no !important rules');
expect(frontendCss.includes('width:clamp(150px,15vw,176px)') && frontendCss.includes('border-radius:50%'), 'Desktop paths must use circular 150-180px photo controls');
expect(frontendCss.includes('@media (hover:none),(pointer:coarse)') && frontendCss.includes('position:static;opacity:1'), 'Touch devices must keep the detail action visible');
expect(frontendCss.includes('@media (prefers-reduced-motion:reduce)'), 'Finder motion must respect reduced-motion preference');
expect(frontendCss.includes('@keyframes gloskin-consultation-concerns-in') && frontendCss.includes('transform:translateY(5px)'), 'Concern helper area must use only the subtle CSS entrance');
expect(/\.gloskin-ui1-consultation__helper\{[\s\S]*?display:inline-flex;[\s\S]*?background:var\(--gloskin-accent-soft\);[\s\S]*?\}/.test(frontendCss), 'Multi-select helper must use the compact accent-soft presentation');
expect(/@media \(prefers-reduced-motion:reduce\)[\s\S]*?\.gloskin-ui1-consultation__concerns\{animation:none\}/.test(frontendCss), 'Reduced motion must disable the concern helper entrance');
const invalidFlexTypo = 'dis' + ':flex';
expect(!frontendCss.includes(invalidFlexTypo), 'Consultation CSS must contain zero invalid abbreviated display declarations');
expect(/\.gloskin-ui1-consultation-card__footer\{\s*display:flex;[\s\S]*?justify-content:flex-end;[\s\S]*?\}/.test(frontendCss), 'Desktop consultation footer must remain a flex price strip aligned to the end');
const coarseStart = frontendCss.indexOf('@media (hover:none),(pointer:coarse)');
const coarseEnd = frontendCss.indexOf('@media (max-width:900px)', coarseStart);
const coarseCss = frontendCss.slice(coarseStart, coarseEnd);
expect(coarseStart >= 0 && coarseEnd > coarseStart && coarseCss.includes('.gloskin-ui1-consultation-card__footer{justify-content:space-between}'), 'Coarse-pointer footer must let the persistent detail action and price share the row');

for (const forbidden of ['localStorage', 'sessionStorage', 'document.cookie', 'fetch(', 'XMLHttpRequest']) {
  expect(!admin.includes(forbidden), `Admin mapping enhancement must not create browser persistence/network state: ${forbidden}`);
}
expect(admin.includes('data-gloskin-product-pool') && admin.includes('data-gloskin-concern-bucket'), 'Enhanced mapping must retain product pool and concern buckets');
expect(admin.includes('checkbox.checked = checked;') || admin.includes('targetCheckbox.checked = false;'), 'Enhanced mapping must synchronize canonical checkboxes');
expect(admin.includes('data-gloskin-mapped-chip') && admin.includes("remove.setAttribute('aria-label'"), 'Mapped chips must retain accessible removal controls');
expect(admin.includes('model.nativeGrid.hidden = true;') && admin.includes('Tampilkan pemetaan native'), 'Native mapping must remain a recoverable fallback');
expect(!admin.includes('.style.') && !admin.includes('gridTemplateColumns'), 'Admin JS must not own static presentation');
expect(admin.includes("item.setAttribute('draggable', 'true')") && admin.includes("bucket.addEventListener('drop'"), 'Pointer drag/drop mapping must remain available');
expect(admin.includes("var select = document.createElement('select');") && admin.includes("'Tambah'"), 'Keyboard mapping controls must remain available');
for (const nativeClass of ['button button-primary', 'button button-secondary', 'button-small', 'button-link-delete', 'button button-link']) {
  expect(!admin.includes(nativeClass), `JS-generated controls must not use native WordPress button chrome: ${nativeClass}`);
}
expect(admin.includes('gloskin-consultation-action--secondary') && admin.includes('gloskin-consultation-action--danger') && admin.includes('gloskin-consultation-action--quiet'), 'JS-generated mapping actions must use the Gloskin action kit');

for (const className of ['.gloskin-admin-mapping-enhanced', '.gloskin-admin-mapping-pool', '.gloskin-admin-mapping-product-pool', '.gloskin-admin-mapping-pool-item', '.gloskin-admin-mapping-concerns', '.gloskin-admin-mapping-buckets', '.gloskin-admin-mapping-bucket-enhanced', '.gloskin-admin-mapping-add', '.gloskin-admin-mapping-chip-list', '.gloskin-admin-mapping-chip', '.is-dragging', '.is-drop-target']) {
  expect(adminCss.includes(className), `Consultation admin stylesheet missing ${className}`);
}
expect(adminCss.includes('grid-template-columns:minmax(220px,.75fr) minmax(0,1.6fr)') && adminCss.includes('repeat(auto-fit,minmax(min(240px,100%),1fr))'), 'Admin mapping geometry must remain responsive');
expect(adminCss.includes('@media (max-width:782px)') && adminCss.includes('grid-template-columns:minmax(0,1fr)'), 'Narrow admin mapping must stack');
expect(!adminCss.includes('!important'), 'Consultation admin stylesheet must add zero !important rules');
for (const actionClass of ['.gloskin-consultation-action--primary', '.gloskin-consultation-action--secondary', '.gloskin-consultation-action--quiet', '.gloskin-consultation-action--danger']) {
  expect(adminCss.includes(actionClass), `Consultation admin action kit missing ${actionClass}`);
}
expect(adminCss.includes('.gloskin-consultation-action:focus-visible') && adminCss.includes('.gloskin-consultation-action:disabled'), 'Consultation admin actions must expose focus-visible and disabled states');
expect(adminCss.includes('input[type="search"]') && adminCss.includes('select{'), 'Consultation workspace native fields must share scoped Gloskin presentation');
expect(adminService.includes("Pertanyaan Terpublikasi (data admin)" ) && adminService.includes(', false, true );'), 'Question count must be informational, not a readiness warning');
expect(adminCss.includes('.gloskin-consultation-metric-card.is-info'), 'Informational admin metric presentation missing');

expect((assetsConfig.match(/assets\/css\/gloskin-ui1-consultation-admin\.css/g) || []).length === 1, 'Exactly one consultation admin CSS asset must be registered');
const enqueueStart = assetService.indexOf('public function enqueue_consultation_admin()');
const enqueueEnd = assetService.indexOf('public function enqueue_admin_migration(', enqueueStart);
const enqueueBody = assetService.slice(enqueueStart, enqueueEnd);
expect(enqueueBody.includes("$registry['admin_styles']['gloskin-ui1-consultation-admin']") && enqueueBody.includes("wp_enqueue_style( 'gloskin-ui1-consultation-admin' )") && enqueueBody.includes("wp_enqueue_script( 'gloskin-ui1-consultation-admin' )"), 'Consultation admin assets must retain their scoped owner');
const adminGateStart = adminService.indexOf('public function enqueue_consultation_admin_assets( $hook_suffix )');
const adminGateEnd = adminService.indexOf('private function sample_importer()', adminGateStart);
const adminGate = adminService.slice(adminGateStart, adminGateEnd);
expect(adminGate.includes('self::CONSULTATION_SLUG') && adminGate.includes('$this->assets->enqueue_consultation_admin();'), 'Consultation admin screen gate missing');
for (const forbidden of ['nav-tab-wrapper', 'nav-tab-active', 'class="nav-tab']) {
  expect(!adminService.includes(forbidden), `Consultation workspace must render zero native WP tab chrome: ${forbidden}`);
}
expect(adminService.includes('class="gloskin-consultation-tabs"') && adminService.includes('aria-current="page"'), 'Semantic pill navigation must remain');
expect(adminService.includes("esc_html__( 'Data & Import'") && adminService.includes('gloskin-consultation-import-cards'), 'Data & Import cards must remain');
const workspaceStart = adminService.indexOf('public function render_consultation_workspace()');
const workspaceEnd = adminService.indexOf('public function handle_save_mapping()', workspaceStart);
const workspaceSource = adminService.slice(workspaceStart, workspaceEnd);
expect(workspaceStart >= 0 && workspaceEnd > workspaceStart, 'Consultation workspace source boundary missing');
expect(!workspaceSource.includes('<style>') && !workspaceSource.includes('style="'), 'Consultation workspace presentation must remain stylesheet-owned');
for (const nativeClass of ['class="button button-primary"', 'class="button button-secondary"', 'button-small', 'button-link-delete']) {
  expect(!workspaceSource.includes(nativeClass), `Custom Consultation workspace must not depend on native WordPress button chrome: ${nativeClass}`);
}
expect(workspaceSource.includes('gloskin-consultation-action--primary') && workspaceSource.includes('gloskin-consultation-action--danger'), 'PHP-rendered Consultation actions must use the scoped Gloskin action kit');
expect(adminCss.includes('[data-gloskin-consultation-workspace] .gloskin-consultation-tabs{') && adminCss.includes('.gloskin-consultation-import-cards{'), 'Admin workspace presentation contracts missing');

console.log('consultation-source-contract.test.js: OK');
