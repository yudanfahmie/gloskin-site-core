/* Promo-only extension of the existing EditorialManager admin surface. */
(function () {
	'use strict';

	var config = window.GloskinEditorialManager;
	if (!config || config.postType !== 'gloskin_promo') { return; }
	var modal = document.querySelector('[data-gloskin-editorial-modal]');
	var form = modal ? modal.querySelector('[data-gloskin-editorial-form]') : null;
	var recordsNode = document.getElementById('gloskin-editorial-records');
	var statusNode = document.querySelector('[data-gloskin-editorial-status]');
	var statusMessage = statusNode ? statusNode.querySelector('[data-gloskin-editorial-status-message]') : null;
	var records = {};
	var pendingSave = null;

	if (!form) { return; }
	try { records = recordsNode ? JSON.parse(recordsNode.textContent || '{}') : {}; } catch (ignore) { records = {}; }

	var popupField = form.elements.popup_enabled;
	var visibilityField = form.elements.visibility;
	var destinationField = form.elements.destination_url;
	var pageIdsField = form.elements.visibility_page_ids_csv;
	var pageSelect = form.querySelector('[data-gloskin-promo-page-select]');
	var pageSearch = form.querySelector('[data-gloskin-promo-page-search]');
	var specificWrap = form.querySelector('[data-gloskin-promo-specific-pages]');
	var popupSettings = form.querySelector('.gloskin-editorial-popup-settings');
	var popupNotice = null;

	function label(name, fallback) {
		return config.labels && config.labels[name] ? config.labels[name] : fallback;
	}

	function setStatus(message, isError) {
		if (!statusNode || !statusMessage) { return; }
		statusMessage.textContent = message || '';
		statusNode.classList.remove('notice-info', 'notice-success', 'notice-error');
		statusNode.classList.add(isError ? 'notice-error' : 'notice-success');
		statusNode.hidden = !message;
	}

	function ajax(payload) {
		var data = new FormData();
		data.append('action', 'gloskin_editorial_toggle');
		data.append('nonce', config.nonce);
		Object.keys(payload).forEach(function (key) { data.append(key, payload[key]); });
		return window.fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		}).then(function (response) {
			return response.text().then(function (text) {
				var payloadJson = null;
				try { payloadJson = text ? JSON.parse(text) : null; } catch (ignore) { payloadJson = null; }
				if (!response.ok || !payloadJson || !payloadJson.success) {
					var message = payloadJson && payloadJson.data && payloadJson.data.message
						? payloadJson.data.message
						: (response.ok ? label('popupFailed', 'Popup state could not be updated.') : 'The server could not update this popup. No changes were made.');
					var error = new Error(message);
					error.status = response.status || 0;
					error.payload = payloadJson;
					throw error;
				}
				return payloadJson;
			});
		});
	}

	function selectedPageIds() {
		if (!pageSelect) { return []; }
		return Array.prototype.slice.call(pageSelect.options).filter(function (option) { return option.selected; }).map(function (option) { return parseInt(option.value, 10) || 0; }).filter(Boolean);
	}

	function syncPageIdsField() {
		if (pageIdsField) { pageIdsField.value = selectedPageIds().join(','); }
	}

	function updateSpecificVisibility() {
		var specific = visibilityField && visibilityField.value === 'specific_pages';
		if (specificWrap) { specificWrap.hidden = !specific; }
		if (pageSearch) { pageSearch.disabled = !specific; }
		if (pageSelect) { pageSelect.disabled = !specific; }
	}

	function updatePopupRequirements() {
		var enabled = !!(popupField && popupField.checked);
		if (destinationField) { destinationField.required = enabled; }
		if (visibilityField) { visibilityField.required = enabled; }
	}

	function setPageSelection(ids) {
		ids = Array.isArray(ids) ? ids.map(function (id) { return String(id); }) : [];
		if (pageSelect) {
			Array.prototype.slice.call(pageSelect.options).forEach(function (option) { option.selected = ids.indexOf(option.value) !== -1; });
		}
		syncPageIdsField();
	}

	function clearPopupGuidance() {
		if (popupNotice) {
			popupNotice.remove();
			popupNotice = null;
		}
	}

	function showPopupGuidance(message) {
		if (!popupSettings) { return; }
		clearPopupGuidance();
		popupNotice = document.createElement('div');
		popupNotice.className = 'gloskin-editorial-popup-settings__notice';
		popupNotice.setAttribute('role', 'status');
		var strong = document.createElement('strong');
		strong.textContent = 'Popup needs one more setting';
		var body = document.createElement('span');
		body.textContent = message;
		popupNotice.appendChild(strong);
		popupNotice.appendChild(body);
		popupSettings.insertBefore(popupNotice, popupSettings.firstElementChild);
	}

	function syncForm(record) {
		record = record || {};
		clearPopupGuidance();
		if (popupField) { popupField.checked = !!record.popup_enabled; }
		if (visibilityField) { visibilityField.value = record.visibility || 'homepage'; }
		if (destinationField) { destinationField.value = record.destination_url || ''; }
		setPageSelection(record.visibility_page_ids || []);
		if (pageSearch) { pageSearch.value = ''; filterPages(''); }
		updateSpecificVisibility();
		updatePopupRequirements();
	}

	function filterPages(query) {
		if (!pageSelect) { return; }
		query = String(query || '').trim().toLowerCase();
		Array.prototype.slice.call(pageSelect.options).forEach(function (option) {
			var match = !query || option.textContent.toLowerCase().indexOf(query) !== -1 || option.selected;
			option.hidden = !match;
		});
	}

	function popupButton(row, create) {
		if (!row) { return null; }
		var button = row.querySelector('[data-gloskin-promo-popup-toggle]');
		if (button || !create) { return button; }
		var cell = row.querySelector('.column-gloskin_editorial_active');
		if (!cell) { return null; }
		button = document.createElement('button');
		button.type = 'button';
		button.className = 'button gloskin-editorial-active-toggle gloskin-editorial-popup-toggle';
		button.setAttribute('data-gloskin-promo-popup-toggle', '');
		cell.appendChild(document.createTextNode(' '));
		cell.appendChild(button);
		return button;
	}

	function paintPopupButton(row, enabled) {
		var button = popupButton(row, true);
		if (!button) { return; }
		var id = row.id ? row.id.replace('post-', '') : '';
		button.setAttribute('data-id', id);
		button.setAttribute('data-popup', enabled ? '1' : '0');
		button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
		button.classList.toggle('is-active', !!enabled);
		button.textContent = enabled ? label('popupOn', 'Popup On') : label('popupOff', 'Popup Off');
	}

	function readinessIssue(record) {
		if (!record) { return null; }
		if (!(parseInt(record.image_id, 10) > 0)) {
			return { message: 'Choose a featured Promo image before enabling popup display.', focus: 'media' };
		}
		if (!String(record.destination_url || '').trim()) {
			return { message: 'Add the destination URL, then save this Promo. Popup display will be enabled with that save.', focus: 'destination' };
		}
		if ((record.visibility || 'homepage') === 'specific_pages' && (!Array.isArray(record.visibility_page_ids) || !record.visibility_page_ids.length)) {
			return { message: 'Select at least one WordPress page for Specific Pages visibility, then save this Promo.', focus: 'pages' };
		}
		return null;
	}

	function focusPopupField(kind) {
		var target = null;
		if (kind === 'destination') { target = destinationField; }
		else if (kind === 'pages') { target = pageSearch || pageSelect; }
		else if (kind === 'media') { target = form.querySelector('[data-gloskin-editorial-media]'); }
		else { target = popupField; }
		if (target && typeof target.focus === 'function') { target.focus(); }
	}

	function openPopupEditor(id, message, focusKind) {
		setStatus('', false);
		var record = Object.assign({}, records[id] || {}, { popup_enabled: true });
		var edit = document.querySelector('[data-gloskin-editorial-edit="' + String(id).replace(/[^0-9]/g, '') + '"]');
		if (edit) {
			edit.click();
			window.setTimeout(function () {
				syncForm(record);
				if (popupField) { popupField.checked = true; }
				updatePopupRequirements();
				showPopupGuidance(message || 'Complete the popup settings and save this Promo.');
				focusPopupField(focusKind);
			}, 0);
			return;
		}

		try {
			var url = new URL(window.location.href);
			url.searchParams.set('post_type', 'gloskin_promo');
			url.searchParams.set('gloskin_edit', id);
			window.location.assign(url.toString());
		} catch (ignore) {
			setStatus(message || label('popupFailed', 'Popup state could not be updated.'), true);
		}
	}

	function applyPendingSave() {
		if (!pendingSave) { return; }
		var id = pendingSave.id;
		if (!id) { return; }
		var row = document.getElementById('post-' + id);
		if (row) { paintPopupButton(row, pendingSave.popup_enabled); }
		records[String(id)] = Object.assign({}, records[String(id)] || {}, pendingSave, { id: id });
		pendingSave = null;
	}

	if (pageSearch) { pageSearch.addEventListener('input', function () { filterPages(pageSearch.value); }); }
	if (pageSelect) { pageSelect.addEventListener('change', syncPageIdsField); }
	if (visibilityField) { visibilityField.addEventListener('change', updateSpecificVisibility); }
	if (popupField) { popupField.addEventListener('change', function () { clearPopupGuidance(); updatePopupRequirements(); }); }
	if (destinationField) { destinationField.addEventListener('input', clearPopupGuidance); }

	form.addEventListener('submit', function () {
		syncPageIdsField();
		pendingSave = {
			id: parseInt(form.elements.post_id ? form.elements.post_id.value : '0', 10) || 0,
			image_id: parseInt(form.elements.image_id ? form.elements.image_id.value : '0', 10) || 0,
			popup_enabled: !!(popupField && popupField.checked),
			visibility: visibilityField ? visibilityField.value : 'homepage',
			visibility_page_ids: selectedPageIds(),
			destination_url: destinationField ? destinationField.value : ''
		};
	}, true);

	document.addEventListener('click', function (event) {
		var close = event.target.closest('[data-gloskin-editorial-close]');
		if (close) { pendingSave = null; clearPopupGuidance(); return; }

		var edit = event.target.closest('[data-gloskin-editorial-edit], a[href*="gloskin_edit="]');
		if (edit) {
			var id = edit.getAttribute('data-gloskin-editorial-edit');
			if (!id) {
				try { id = new URL(edit.href, window.location.href).searchParams.get('gloskin_edit'); } catch (ignore) { id = ''; }
			}
			if (id) { syncForm(records[String(parseInt(id, 10) || 0)] || {}); }
			return;
		}

		var add = event.target.closest('.page-title-action, a[href*="post-new.php?post_type=gloskin_promo"]');
		if (add) { syncForm({ popup_enabled: false, visibility: 'homepage', visibility_page_ids: [], destination_url: '' }); return; }

		var toggle = event.target.closest('[data-gloskin-promo-popup-toggle]');
		if (!toggle) { return; }
		event.preventDefault();
		event.stopPropagation();
		var id = String(toggle.getAttribute('data-id') || '');
		var next = toggle.getAttribute('data-popup') === '1' ? '0' : '1';
		if (next === '1') {
			var issue = readinessIssue(records[id]);
			if (issue) {
				openPopupEditor(id, issue.message, issue.focus);
				return;
			}
		}

		toggle.disabled = true;
		ajax({ post_id: id, field: 'popup', active: next }).then(function (response) {
			var enabled = !!response.data.active;
			var row = document.getElementById('post-' + id);
			if (row) { paintPopupButton(row, enabled); }
			if (records[id]) { records[id].popup_enabled = enabled; }
			toggle.disabled = false;
			setStatus(label('popupUpdated', 'Popup display state updated.'), false);
		}).catch(function (error) {
			toggle.disabled = false;
			if (next === '1' && error && error.status === 400) {
				openPopupEditor(id, error.message || label('popupFailed', 'Complete the popup settings and save this Promo.'), 'destination');
				return;
			}
			var message = error && error.message ? error.message : 'Could not reach the server. No popup changes were made; please try again.';
			setStatus(message, true);
		});
	});

	var tableBody = document.getElementById('the-list');
	if (tableBody && window.MutationObserver) {
		new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				Array.prototype.slice.call(mutation.addedNodes || []).forEach(function (node) {
					if (!node || node.nodeType !== 1 || !node.matches('tr[id^="post-"]')) { return; }
					var id = node.id.replace('post-', '');
					if (pendingSave && !pendingSave.id) { pendingSave.id = parseInt(id, 10) || 0; }
					var state = pendingSave && (!pendingSave.id || String(pendingSave.id) === id) ? pendingSave.popup_enabled : !!(records[id] && records[id].popup_enabled);
					paintPopupButton(node, state);
				});
			});
		}).observe(tableBody, { childList: true });
	}

	if (modal && window.MutationObserver) {
		new MutationObserver(function () {
			if (modal.hidden && pendingSave) { applyPendingSave(); }
		}).observe(modal, { attributes: true, attributeFilter: ['hidden'] });
	}

	document.querySelectorAll('#the-list > tr[id^="post-"]').forEach(function (row) {
		var id = row.id.replace('post-', '');
		if (!popupButton(row, false)) { paintPopupButton(row, !!(records[id] && records[id].popup_enabled)); }
	});

	if (config.editId) { syncForm(records[String(parseInt(config.editId, 10) || 0)] || {}); }
	else if (config.addId) { syncForm({ popup_enabled: false, visibility: 'homepage', visibility_page_ids: [], destination_url: '' }); }
	else { updateSpecificVisibility(); updatePopupRequirements(); }
})();
