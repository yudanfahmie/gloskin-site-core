'use strict';

const fs = require('fs');
const path = require('path');

function fail(message) {
  console.error(message);
  process.exit(1);
}
function expect(condition, message) {
  if (!condition) fail(message);
}

const root = path.resolve(__dirname, '..');
const frontendPath = path.join(root, 'plugin/gloskin-site-core/assets/js/gloskin-ui1-consultation.js');
const adminPath = path.join(root, 'plugin/gloskin-site-core/assets/js/gloskin-ui1-consultation-admin.js');
const adminCssPath = path.join(root, 'plugin/gloskin-site-core/assets/css/gloskin-ui1-consultation-admin.css');
const assetsConfigPath = path.join(root, 'plugin/gloskin-site-core/config/assets.php');
const assetServicePath = path.join(root, 'plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php');
const adminServicePath = path.join(root, 'plugin/gloskin-site-core/includes/class-gloskin-site-core-admin-service.php');
const treatmentTemplatePath = path.join(root, 'plugin/gloskin-site-core/templates/pages/treatments.php');
const frontend = fs.readFileSync(frontendPath, 'utf8');
const admin = fs.readFileSync(adminPath, 'utf8');
const adminCss = fs.readFileSync(adminCssPath, 'utf8');
const assetsConfig = fs.readFileSync(assetsConfigPath, 'utf8');
const assetService = fs.readFileSync(assetServicePath, 'utf8');
const adminService = fs.readFileSync(adminServicePath, 'utf8');
const treatmentTemplate = fs.readFileSync(treatmentTemplatePath, 'utf8');

for (const forbidden of ['localStorage', 'sessionStorage', 'document.cookie', 'XMLHttpRequest', 'fetch(']) {
  expect(!frontend.includes(forbidden), `Questionnaire persistence/network contract violated by ${forbidden}`);
}
expect(frontend.includes('questions: []') && frontend.includes('history: []') && frontend.includes('scores: {}'), 'Questionnaire state must remain closure-memory only');
expect(frontend.includes('state.questions = fisherYates(eligible);'), 'Fisher-Yates must run when a consultation path/run starts');
expect((frontend.match(/state\.questions = fisherYates\(eligible\);/g) || []).length === 1, 'Question order must be shuffled exactly once per run');
expect(frontend.includes('state.history = [];') && frontend.includes('state.scores = {};'), 'Restart/path selection must reset history and score state');
expect(frontend.includes('selectPath(state.pathId);'), 'Restart must reuse the clean run initialization path');
expect(frontend.includes('state.scores[last.concernId] = Math.max(0, (state.scores[last.concernId] || 0) - last.weight);'), 'Back must reverse the exact previous score weight');
expect(frontend.includes('scoreAndRankProducts(products, state.scores, 8)'), 'Recommendation cards must remain capped at eight');
expect(treatmentTemplate.includes('gloskin_ui1_render_product_card( $gloskin_treatment_product )'), 'Consultation recommendations must reuse the canonical Woo product-card renderer');
const consultationIndex = treatmentTemplate.indexOf('data-gloskin-section="treatments-consultation"');
const informationalIndex = treatmentTemplate.indexOf('data-gloskin-section="treatments-discovery"');
expect(consultationIndex >= 0 && informationalIndex > consultationIndex, 'Existing informational treatment directory must remain below consultation results');

for (const forbidden of ['localStorage', 'sessionStorage', 'document.cookie', 'fetch(', 'XMLHttpRequest']) {
  expect(!admin.includes(forbidden), `Admin mapping enhancement must not create browser persistence/network state: ${forbidden}`);
}
expect(admin.includes('data-gloskin-product-pool'), 'Enhanced mapping must render one Treatment Product pool');
expect(admin.includes('data-gloskin-concern-bucket'), 'Enhanced mapping must render concern buckets');
expect(admin.includes('checkbox.checked = checked;') || admin.includes('targetCheckbox.checked = false;'), 'Enhanced mapping must synchronize the canonical checkbox relationships');
expect(admin.includes('data-gloskin-mapped-chip'), 'Mapped relationships must render lightweight chips/references');
expect(admin.includes("remove.setAttribute('aria-label'"), 'Mapped chip remove action must expose an accessible label');
expect(admin.includes('model.nativeGrid.hidden = true;'), 'Native checkbox matrix may be hidden only after enhancement succeeds');
expect(admin.includes('Tampilkan pemetaan native'), 'Native checkbox matrix must remain user-recoverable as fallback');
expect(!admin.includes('update_option') && !admin.includes('postMessage('), 'Admin JS must stay presentation/state synchronization only');

expect(!admin.includes('.style.'), 'Static admin mapping presentation must be stylesheet-owned, not inline JS style writes');
expect(!admin.includes('gridTemplateColumns'), 'Admin JS must not own fixed workspace/bucket grid geometry');
expect(!admin.includes('tabIndex = 0') && !admin.includes('tabindex="0"'), 'Pointer-only draggable pool items must not be inert keyboard focus stops');
expect(admin.includes("item.setAttribute('draggable', 'true')"), 'Product pool must remain pointer drag/drop enabled');
expect(admin.includes("bucket.addEventListener('drop'"), 'Concern buckets must remain pointer drop targets');
expect(admin.includes("var select = document.createElement('select');") && admin.includes("'Tambah'"), 'Keyboard users must retain select + Tambah mapping controls');
expect(!admin.includes('setTimeout(') && admin.includes('updatePoolMeta(product.id);'), 'Initial pool metadata must initialize synchronously without a zero-delay timer');

const requiredAdminClasses = [
  '.gloskin-admin-mapping-enhanced',
  '.gloskin-admin-mapping-pool',
  '.gloskin-admin-mapping-product-pool',
  '.gloskin-admin-mapping-pool-item',
  '.gloskin-admin-mapping-concerns',
  '.gloskin-admin-mapping-buckets',
  '.gloskin-admin-mapping-bucket-enhanced',
  '.gloskin-admin-mapping-add',
  '.gloskin-admin-mapping-chip-list',
  '.gloskin-admin-mapping-chip',
  '.is-dragging',
  '.is-drop-target'
];
for (const className of requiredAdminClasses) {
  expect(adminCss.includes(className), `Consultation admin stylesheet missing ${className}`);
}
expect(adminCss.includes('grid-template-columns:minmax(220px,.75fr) minmax(0,1.6fr)'), 'Desktop mapping geometry must allow the concern column to shrink without overflow');
expect(adminCss.includes('repeat(auto-fit,minmax(min(240px,100%),1fr))'), 'Concern buckets must shrink below 240px when the workspace is narrower');
expect(adminCss.includes('@media (max-width:782px)') && adminCss.includes('grid-template-columns:minmax(0,1fr)'), 'Narrow wp-admin layout must stack Product pool above Concern buckets');
expect(adminCss.includes('flex-wrap:wrap'), 'Bucket select + Tambah controls must wrap when space is insufficient');
expect(!adminCss.includes('!important'), 'Consultation admin stylesheet must add zero !important rules');

expect((assetsConfig.match(/assets\/css\/gloskin-ui1-consultation-admin\.css/g) || []).length === 1, 'Exactly one consultation admin CSS asset must be registered');
expect(assetsConfig.includes("'gloskin-ui1-consultation-admin' => array(") && assetsConfig.includes("'admin_styles' => array("), 'Consultation admin CSS must live in the existing admin asset registry');
const enqueueStart = assetService.indexOf('public function enqueue_consultation_admin()');
const enqueueEnd = assetService.indexOf('public function enqueue_admin_migration(', enqueueStart);
expect(enqueueStart >= 0 && enqueueEnd > enqueueStart, 'AssetService consultation admin enqueue owner missing');
const enqueueBody = assetService.slice(enqueueStart, enqueueEnd);
expect(enqueueBody.includes("$registry['admin_styles']['gloskin-ui1-consultation-admin']"), 'Consultation admin enqueue must resolve its scoped stylesheet from the registry');
expect(enqueueBody.includes("wp_enqueue_style( 'gloskin-ui1-consultation-admin' )"), 'Consultation admin stylesheet must be enqueued by AssetService');
expect(enqueueBody.includes("wp_enqueue_script( 'gloskin-ui1-consultation-admin' )"), 'Existing consultation admin JS must remain enqueued by AssetService');
const adminGateStart = adminService.indexOf('public function enqueue_consultation_admin_assets( $hook_suffix )');
const adminGateEnd = adminService.indexOf('private function sample_importer()', adminGateStart);
expect(adminGateStart >= 0 && adminGateEnd > adminGateStart, 'Consultation admin screen gate missing');
const adminGate = adminService.slice(adminGateStart, adminGateEnd);
expect(adminGate.includes('self::CONSULTATION_SLUG') && adminGate.includes("$this->assets->enqueue_consultation_admin();"), 'Consultation assets must remain scoped through the existing Konsultasi Perawatan screen gate');

console.log('consultation-source-contract.test.js: OK');
