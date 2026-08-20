(() => {
  'use strict';
  const root = document.querySelector('[data-gloskin-translation-root]');
  const cfg = window.GloskinTranslationAdmin;
  if (!root || !cfg || !Array.isArray(cfg.records)) return;

  const state = { records: cfg.records, selected: null, worker: null, pending: new Map(), seq: 0 };
  const rows = root.querySelector('[data-translation-rows]');
  const editor = root.querySelector('[data-translation-editor]');
  const search = root.querySelector('[data-translation-search]');
  const type = root.querySelector('[data-translation-type]');
  const missing = root.querySelector('[data-translation-missing]');
  const status = root.querySelector('[data-translation-status]');
  const generate = root.querySelector('[data-translation-generate]');

  [...new Set(state.records.map((r) => r.type))].sort().forEach((value) => {
    const option = document.createElement('option'); option.value = value; option.textContent = value; type.appendChild(option);
  });

  function count(record) {
    record.filled = record.fields.filter((field) => String(field.en || '').trim()).length;
    record.total = record.fields.length;
    return `${record.filled}/${record.total}`;
  }

  function filtered() {
    const needle = String(search.value || '').trim().toLowerCase();
    return state.records.filter((record) => {
      if (type.value && record.type !== type.value) return false;
      if (missing.checked && record.filled >= record.total) return false;
      if (needle && !`${record.type} ${record.label}`.toLowerCase().includes(needle)) return false;
      return true;
    });
  }

  function renderRows() {
    rows.textContent = '';
    filtered().forEach((record) => {
      const tr = document.createElement('tr'); tr.tabIndex = 0; tr.dataset.recordKey = record.key;
      tr.innerHTML = `<td></td><td><button type="button" class="button-link"></button></td><td></td>`;
      tr.children[0].textContent = record.type;
      tr.children[1].querySelector('button').textContent = record.label || '(untitled)';
      tr.children[2].textContent = count(record);
      const choose = () => { state.selected = record; renderEditor(); };
      tr.addEventListener('click', choose);
      tr.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(); } });
      rows.appendChild(tr);
    });
  }

  function renderEditor() {
    const record = state.selected;
    if (!record) { editor.hidden = true; return; }
    editor.hidden = false; editor.textContent = '';
    const head = document.createElement('div'); head.className = 'gloskin-translation__editor-head';
    const h2 = document.createElement('h2'); h2.textContent = `${record.type}: ${record.label}`; head.appendChild(h2);
    const recordGenerate = document.createElement('button'); recordGenerate.type = 'button'; recordGenerate.className = 'button'; recordGenerate.textContent = 'Generate Missing';
    recordGenerate.addEventListener('click', () => generateRecords([record], false)); head.appendChild(recordGenerate); editor.appendChild(head);

    record.fields.forEach((field) => {
      const wrap = document.createElement('div'); wrap.className = 'gloskin-translation__field';
      const label = document.createElement('h3'); label.textContent = field.label; wrap.appendChild(label);
      const idLabel = document.createElement('strong'); idLabel.textContent = 'ID'; wrap.appendChild(idLabel);
      const source = document.createElement('div'); source.className = 'gloskin-translation__source'; source.textContent = field.source; wrap.appendChild(source);
      const enLabel = document.createElement('strong'); enLabel.textContent = 'EN'; wrap.appendChild(enLabel);
      const input = field.rich || field.key === 'post_content' || field.key === 'post_excerpt' ? document.createElement('textarea') : document.createElement('input');
      if (input.tagName === 'INPUT') input.type = 'text'; input.value = field.en || ''; input.dataset.fieldKey = field.key; wrap.appendChild(input);
      const actions = document.createElement('p');
      const regen = document.createElement('button'); regen.type = 'button'; regen.className = 'button-link'; regen.textContent = 'Regenerate';
      regen.addEventListener('click', async () => { await generateField(record, field, true); renderEditor(); renderRows(); }); actions.appendChild(regen); wrap.appendChild(actions);
      editor.appendChild(wrap);
    });
    const save = document.createElement('button'); save.type = 'button'; save.className = 'button button-primary'; save.textContent = 'Save';
    save.addEventListener('click', async () => {
      const inputs = editor.querySelectorAll('[data-field-key]');
      save.disabled = true; status.textContent = 'Saving…';
      try {
        for (const input of inputs) {
          const field = record.fields.find((item) => item.key === input.dataset.fieldKey);
          if (!field) continue;
          const value = String(input.value || '');
          if (value !== String(field.en || '')) { await saveField(record, field, value); field.en = value; }
        }
        count(record); renderRows(); status.textContent = 'Saved.';
      } catch (error) { status.textContent = error.message || 'Save failed.'; }
      save.disabled = false;
    });
    editor.appendChild(save);
  }

  async function saveField(record, field, value) {
    const body = new URLSearchParams({ action: cfg.action, nonce: cfg.nonce, entity: record.entity, entity_id: record.entityId, field: field.key, value });
    const response = await fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() });
    const json = await response.json();
    if (!response.ok || !json.success) throw new Error(json && json.data && json.data.message ? json.data.message : 'Save failed.');
    return json.data.value;
  }

  function ensureWorker() {
    if (state.worker) return state.worker;
    state.worker = new Worker(cfg.workerUrl, { type: 'module' });
    state.worker.addEventListener('message', (event) => {
      const data = event.data || {}; const pending = state.pending.get(data.id); if (!pending) return;
      state.pending.delete(data.id); data.ok ? pending.resolve(data.text || '') : pending.reject(new Error(data.error || 'Translation failed.'));
    });
    state.worker.addEventListener('error', () => {
      state.pending.forEach((pending) => pending.reject(new Error('Translation worker failed.'))); state.pending.clear();
    });
    return state.worker;
  }

  function protect(text) {
    const replacements = [];
    let value = String(text || '');
    (cfg.protectedTerms || []).filter(Boolean).sort((a, b) => b.length - a.length).forEach((term) => {
      if (!value.includes(term)) return;
      const token = `⟪GLOTERM${replacements.length}⟫`;
      replacements.push([token, term]); value = value.split(term).join(token);
    });
    return { value, restore: (translated) => replacements.reduce((out, item) => out.split(item[0]).join(item[1]), translated) };
  }

  function translatePlain(text) {
    const protectedText = protect(text);
    const id = ++state.seq; ensureWorker().postMessage({ type: 'translate', id, text: protectedText.value });
    return new Promise((resolve, reject) => state.pending.set(id, { resolve: (value) => resolve(protectedText.restore(value)), reject }));
  }

  // Preserve markup/comments/URLs/shortcodes. Only visible text chunks are sent
  // to OPUS-MT; protected element bodies remain byte-for-byte untouched.
  async function translateRich(source) {
    const token = /<!--[\s\S]*?-->|<\/?(?:script|style|pre|code)\b[^>]*>|<[^>]+>|\[[^\]\r\n]+\]|https?:\/\/[^\s<]+/gi;
    const parts = []; let last = 0; let protectedDepth = 0; let match;
    while ((match = token.exec(source))) {
      const before = source.slice(last, match.index);
      parts.push({ text: before, translate: protectedDepth === 0 && /\S/.test(before) });
      const raw = match[0]; parts.push({ text: raw, translate: false });
      if (/^<(script|style|pre|code)\b/i.test(raw)) protectedDepth += 1;
      if (/^<\/(script|style|pre|code)\b/i.test(raw)) protectedDepth = Math.max(0, protectedDepth - 1);
      last = token.lastIndex;
    }
    const tail = source.slice(last); parts.push({ text: tail, translate: protectedDepth === 0 && /\S/.test(tail) });
    for (const part of parts) { if (part.translate) part.text = await translatePlain(part.text); }
    return parts.map((part) => part.text).join('');
  }

  async function generateField(record, field, force) {
    if (!force && String(field.en || '').trim()) return false;
    const source = String(field.source || '').trim(); if (!source) return false;
    const translated = field.rich || field.key === 'post_content' ? await translateRich(field.source) : await translatePlain(field.source);
    const saved = await saveField(record, field, translated); field.en = saved; count(record); return true;
  }

  async function generateRecords(records, force) {
    generate.disabled = true; let failures = 0; let completed = 0;
    for (const record of records) {
      for (const field of record.fields) {
        if (!force && String(field.en || '').trim()) continue;
        status.textContent = `Translating ${record.label} — ${field.label}…`;
        try { if (await generateField(record, field, force)) completed += 1; } catch (error) { failures += 1; status.textContent = `One field failed: ${error.message || 'translation error'}`; }
        renderRows(); if (state.selected === record) renderEditor();
      }
    }
    status.textContent = `${completed} field(s) generated${failures ? `; ${failures} failed and remain missing.` : '.'}`;
    generate.disabled = false;
  }

  [search, type, missing].forEach((control) => control.addEventListener(control === search ? 'input' : 'change', renderRows));
  generate.addEventListener('click', () => generateRecords(filtered(), false));
  state.records.forEach(count); renderRows();
})();
