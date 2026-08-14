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
const treatmentTemplatePath = path.join(root, 'plugin/gloskin-site-core/templates/pages/treatments.php');
const frontend = fs.readFileSync(frontendPath, 'utf8');
const admin = fs.readFileSync(adminPath, 'utf8');
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

console.log('consultation-source-contract.test.js: OK');
