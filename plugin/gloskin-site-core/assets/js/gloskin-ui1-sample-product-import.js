(function () {
	'use strict';

	var root = document.querySelector('[data-gloskin-sample-import]');
	var config = window.GloskinSampleImport;
	if (!root || !config) {
		return;
	}

	var button = root.querySelector('[data-gloskin-sample-run]');
	var statusNode = root.querySelector('[data-gloskin-sample-status]');
	var progressNode = root.querySelector('[data-gloskin-sample-progress]');
	var errorNode = root.querySelector('[data-gloskin-sample-error]');
	var running = false;

	function setError(message) {
		if (!errorNode) return;
		var paragraph = errorNode.querySelector('p');
		if (paragraph) paragraph.textContent = message || '';
		errorNode.hidden = !message;
	}

	function render(state) {
		if (!state) return;
		if (statusNode && state.status) statusNode.textContent = state.status;
		if (progressNode) {
			progressNode.textContent = String(state.processed_products || 0) + '/' + String(state.expected_products || 13);
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
					throw new Error(data.message || 'Sample product import gagal.');
				}
				return payload.data || {};
			});
		});
	}

	function continueChain(state) {
		render(state);
		if (state.status === 'consumed') {
			running = false;
			if (button) button.disabled = true;
			window.setTimeout(function () {
				window.location.href = 'admin.php?page=gloskin-content';
			}, 350);
			return;
		}
		request('continue').then(continueChain).catch(fail);
	}

	function fail(error) {
		running = false;
		if (button) {
			button.disabled = false;
			button.textContent = 'Resume import';
		}
		setError(error && error.message ? error.message : 'Sample product import gagal.');
	}

	if (button) {
		button.addEventListener('click', function () {
			if (running) return;
			running = true;
			button.disabled = true;
			setError('');
			if (statusNode) statusNode.textContent = 'validating';
			request('start').then(continueChain).catch(fail);
		});
	}
}());
