let pipelinePromise = null;

async function createTranslator(device) {
  const transformers = await import('https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.7.2');
  return transformers.pipeline('translation', 'Xenova/opus-mt-id-en', { device });
}

async function translator() {
  if (!pipelinePromise) {
    pipelinePromise = (async () => {
      if (self.navigator && self.navigator.gpu) {
        try { return await createTranslator('webgpu'); } catch (error) { /* fall through */ }
      }
      return createTranslator('wasm');
    })();
  }
  return pipelinePromise;
}

self.addEventListener('message', async (event) => {
  const data = event.data || {};
  if (data.type !== 'translate') return;
  try {
    const pipe = await translator();
    const result = await pipe(String(data.text || ''));
    const text = Array.isArray(result) && result[0] ? (result[0].translation_text || result[0].generated_text || '') : '';
    self.postMessage({ id: data.id, ok: true, text });
  } catch (error) {
    self.postMessage({ id: data.id, ok: false, error: error && error.message ? error.message : 'Translation failed.' });
  }
});
