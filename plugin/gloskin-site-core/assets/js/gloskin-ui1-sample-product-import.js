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

	function normalizeScreen() {
		var rows = root.querySelectorAll('table tbody tr');
		rows.forEach(function (row) {
			var th = row.querySelector('th');
			var td = row.querySelector('td');
			if (!th || !td) return;
			var label = th.textContent.trim();
			if (label === 'Produk') th.textContent = 'Products';
			if (label === 'Variasi') th.textContent = 'Variations';
			if (label === 'Media') th.textContent = 'Images';
			if (label === 'Tipe') {
				th.textContent = 'Simple';
				td.textContent = '8';
				var variableRow = row.cloneNode(true);
				variableRow.querySelector('th').textContent = 'Variable';
				variableRow.querySelector('td').textContent = '5';
				row.parentNode.insertBefore(variableRow, row.nextSibling);
			}
		});
		var paragraphs = root.querySelectorAll('p');
		paragraphs.forEach(function (paragraph) {
			if (paragraph.textContent.indexOf('Produk dan variasi dibuat sebagai draft.') !== -1) {
				paragraph.textContent = 'Parent products remain draft; child variations are prepared as publish so they are operational when the parent is later published.';
			}
		});
		if (button) {
			button.textContent = /^Validate/i.test(button.textContent.trim()) ? 'Import Sample Products' : 'Resume Import';
		}
	}

	normalizeScreen();

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
			button.textContent = 'Resume Import';
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
