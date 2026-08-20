(function () {
	'use strict';

	var root = document.querySelector('[data-gloskin-sample-import], [data-gloskin-ia-migration]');
	var config = window.GloskinSampleImport;
	if (!root || !config) {
		return;
	}

	var button = root.querySelector('[data-gloskin-sample-run]');
	var statusNode = root.querySelector('[data-gloskin-sample-status]');
	var progressNode = root.querySelector('[data-gloskin-sample-progress]');
	var errorNode = root.querySelector('[data-gloskin-sample-error]');
	var loaderNode = root.querySelector('[data-gloskin-migration-loader]');
	var progressBar = root.querySelector('[data-gloskin-migration-progressbar]');
	var stepNode = root.querySelector('[data-gloskin-migration-step]');
	var running = false;

	function setBusy(busy) {
		running = busy;
		root.setAttribute('aria-busy', busy ? 'true' : 'false');
		if (loaderNode) {
			loaderNode.classList.toggle('is-active', busy);
		}
		if (button) {
			button.disabled = busy;
		}
	}

	function setError(message) {
		if (!errorNode) return;
		var paragraph = errorNode.querySelector('p');
		if (paragraph) paragraph.textContent = message || '';
		errorNode.hidden = !message;
	}

	function render(state) {
		if (!state) return;
		var processed = Number(state.processed_products || 0);
		var expected = Number(state.expected_products || 13);
		if (statusNode && state.status) statusNode.textContent = state.status;
		if (progressNode) {
			progressNode.textContent = String(processed) + '/' + String(expected);
		}
		if (progressBar) {
			progressBar.max = expected > 0 ? expected : 1;
			progressBar.value = Math.min(processed, progressBar.max);
		}
		if (stepNode && state.current_step) {
			stepNode.textContent = state.current_step;
		}
		if (state.last_error) setError(state.last_error);
	}

	function request(mode) {
		var body = new URLSearchParams();
		body.set('action', config.action);
		body.set('nonce', config.nonce);
		body.set('mode', mode);

		return fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: body.toString()
		}).then(function (response) {
			return response.json().then(function (payload) {
				if (!response.ok || !payload || !payload.success) {
					var data = payload && payload.data ? payload.data : {};
					throw new Error(data.message || 'Migration process gagal.');
				}
				return payload.data || {};
			});
		});
	}

	function continueChain(state) {
		render(state);
		if (state.status === 'consumed') {
			setBusy(false);
			if (button) {
				button.disabled = true;
				button.textContent = 'Selesai';
			}
			if (root.hasAttribute('data-gloskin-no-redirect')) {
				return;
			}
			window.setTimeout(function () {
				window.location.href = 'admin.php?page=gloskin-content';
			}, 350);
			return;
		}

		/* Yield one paint frame so status/progress reflects the real server
		 * checkpoint before the next bounded request starts. No polling and
		 * no parallel writes: this keeps the mutation order deterministic. */
		window.requestAnimationFrame(function () {
			request('continue').then(continueChain).catch(fail);
		});
	}

	function fail(error) {
		setBusy(false);
		if (button) {
			button.disabled = false;
			button.textContent = root.hasAttribute('data-gloskin-ia-migration') ? 'Lanjutkan Migrasi Otomatis' : 'Resume Import';
		}
		setError(error && error.message ? error.message : 'Migration process gagal.');
	}

	if (button) {
		button.addEventListener('click', function () {
			if (running) return;
			setBusy(true);
			setError('');
			if (statusNode) statusNode.textContent = 'validating';
			if (stepNode && root.hasAttribute('data-gloskin-ia-migration')) {
				stepNode.textContent = 'Memvalidasi checkpoint';
			}
			request('start').then(continueChain).catch(fail);
		});
	}
}());
