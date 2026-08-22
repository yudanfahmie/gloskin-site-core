/* Promo-specific fields and popup-toggle UX for the canonical EditorialManager. */
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
	var pendingGuidance = null;
	var guidanceMode = false;

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
	var popupOptions = form.querySelector('[data-gloskin-promo-popup-options]');
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

	function selectedPageIds() {
		if (!pageSelect) { return []; }
		return Array.prototype.slice.call(pageSelect.options).filter(function (option) {
			return option.selected;
		}).map(function (option) {
			return parseInt(option.value, 10) || 0;
		}).filter(Boolean);
	}

	function syncPageIdsField() {
		if (pageIdsField) { pageIdsField.value = selectedPageIds().join(','); }
	}

	function removePopupNotice() {
		if (!popupNotice) { return; }
		popupNotice.remove();
		popupNotice = null;
	}

	function updatePopupUi() {
		var enabled = !!(popupField && popupField.checked);
		var revealOptions = enabled || guidanceMode;
		if (popupOptions) { popupOptions.hidden = !revealOptions; }
		if (destinationField) { destinationField.required = enabled; }
		if (visibilityField) { visibilityField.required = enabled; }

		var specific = !!(revealOptions && visibilityField && visibilityField.value === 'specific_pages');
		if (specificWrap) { specificWrap.hidden = !specific; }
		if (pageSearch) { pageSearch.disabled = !specific; }
		if (pageSelect) {
			pageSelect.disabled = !specific;
			pageSelect.required = enabled && specific;
		}
	}

	function setPageSelection(ids) {
		ids = Array.isArray(ids) ? ids.map(function (id) { return String(id); }) : [];
		if (pageSelect) {
			Array.prototype.slice.call(pageSelect.options).forEach(function (option) {
				option.selected = ids.indexOf(option.value) !== -1;
			});
		}
		syncPageIdsField();
	}

	function filterPages(query) {
		if (!pageSelect) { return; }
		query = String(query || '').trim().toLowerCase();
		Array.prototype.slice.call(pageSelect.options).forEach(function (option) {
			var match = !query || option.textContent.toLowerCase().indexOf(query) !== -1 || option.selected;
			option.hidden = !match;
		});
	}

	function resetGuidance() {
		guidanceMode = false;
		removePopupNotice();
		updatePopupUi();
	}

	function showPopupGuidance(message) {
		if (!popupSettings) { return; }
		removePopupNotice();
		guidanceMode = true;
		popupNotice = document.createElement('div');
		popupNotice.className = 'gloskin-editorial-popup-settings__notice';
		popupNotice.setAttribute('role', 'status');
		var strong = document.createElement('strong');
		strong.textContent = 'Complete popup settings';
		var body = document.createElement('span');
		body.textContent = message || 'Complete the highlighted popup setting, then enable popup display.';
		popupNotice.appendChild(strong);
		popupNotice.appendChild(body);
		popupSettings.insertBefore(popupNotice, popupOptions || null);
		updatePopupUi();
	}

	function syncForm(record) {
		record = record || {};
		guidanceMode = false;
		removePopupNotice();
		if (popupField) { popupField.checked = !!record.popup_enabled; }
		if (visibilityField) { visibilityField.value = record.visibility || 'homepage'; }
		if (destinationField) { destinationField.value = record.destination_url || ''; }
		setPageSelection(record.visibility_page_ids || []);
		if (pageSearch) { pageSearch.value = ''; filterPages(''); }
		updatePopupUi();
	}

	function focusPopupField(kind) {
		var target = null;
		if (kind === 'destination') { target = destinationField; }
		else if (kind === 'pages') { target = pageSearch || pageSelect; }
		else if (kind === 'image') { target = form.querySelector('[data-gloskin-editorial-media]'); }
		else { target = popupField; }
		if (target && typeof target.focus === 'function') { target.focus(); }
	}

	function paintPopupButton(button, enabled) {
		if (!button) { return; }
		button.setAttribute('data-popup', enabled ? '1' : '0');
		button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
		button.classList.toggle('is-active', !!enabled);
		button.textContent = enabled ? label('popupOn', 'Popup On') : label('popupOff', 'Popup Off');
	}

	function ajaxPopup(id, enabled) {
		var data = new FormData();
		data.append('action', 'gloskin_editorial_toggle');
		data.append('nonce', config.nonce);
		data.append('post_id', String(id));
		data.append('field', 'popup');
		data.append('active', enabled ? '1' : '0');
		return window.fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		}).then(function (response) {
			return response.text().then(function (text) {
				var payload = null;
				try { payload = text ? JSON.parse(text) : null; } catch (ignore) { payload = null; }
				if (!payload || !payload.success) {
					var dataPayload = payload && payload.data ? payload.data : {};
					var error = new Error(dataPayload.message || label('popupFailed', 'Popup state could not be updated.'));
					error.code = dataPayload.code || '';
					error.field = dataPayload.field || '';
					error.status = response.status || 0;
					throw error;
				}
				return payload;
			});
		});
	}

	function openPopupEditor(id, issue) {
		pendingGuidance = {
			id: String(id),
			field: issue && issue.field ? issue.field : 'popup',
			message: issue && issue.message ? issue.message : 'Complete the popup settings, then enable this Promo popup.'
		};
		var edit = document.querySelector('[data-gloskin-editorial-edit="' + String(id).replace(/[^0-9]/g, '') + '"]');
		if (edit) {
			edit.click();
			return;
		}
		setStatus(pendingGuidance.message, true);
	}

	function handleModalOpen(event) {
		var detail = event && event.detail ? event.detail : {};
		var id = String(parseInt(detail.id, 10) || 0);
		var record = id !== '0' && records[id] ? records[id] : (detail.record || {});
		if (detail.isNew) { record = { popup_enabled: false, visibility: 'homepage', visibility_page_ids: [], destination_url: '' }; }
		syncForm(record);
		if (pendingGuidance && pendingGuidance.id === id) {
			var issue = pendingGuidance;
			pendingGuidance = null;
			showPopupGuidance(issue.message);
			focusPopupField(issue.field);
		}
	}

	if (pageSearch) { pageSearch.addEventListener('input', function () { filterPages(pageSearch.value); }); }
	if (pageSelect) { pageSelect.addEventListener('change', syncPageIdsField); }
	if (visibilityField) { visibilityField.addEventListener('change', updatePopupUi); }
	if (popupField) {
		popupField.addEventListener('change', function () {
			guidanceMode = false;
			removePopupNotice();
			updatePopupUi();
		});
	}

	form.addEventListener('submit', syncPageIdsField, true);

	document.addEventListener('gloskin:editorial-modal-open', handleModalOpen);
	document.addEventListener('gloskin:editorial-record-saved', function (event) {
		var record = event && event.detail ? event.detail.record : null;
		if (record && record.id) { records[String(record.id)] = record; }
	});

	document.addEventListener('click', function (event) {
		var close = event.target.closest('[data-gloskin-editorial-close]');
		if (close) {
			pendingGuidance = null;
			resetGuidance();
			return;
		}
		var toggle = event.target.closest('[data-gloskin-promo-popup-toggle]');
		if (!toggle) { return; }
		event.preventDefault();
		event.stopPropagation();
		var id = String(toggle.getAttribute('data-id') || '');
		var enable = toggle.getAttribute('data-popup') !== '1';

		toggle.disabled = true;
		ajaxPopup(id, enable).then(function (response) {
			var enabled = !!(response.data && response.data.active);
			paintPopupButton(toggle, enabled);
			if (records[id]) { records[id].popup_enabled = enabled; }
			toggle.disabled = false;
			setStatus(label('popupUpdated', 'Popup display state updated.'), false);
		}).catch(function (error) {
			toggle.disabled = false;
			if (enable && error && error.code === 'popup_incomplete') {
				openPopupEditor(id, { field: error.field || 'popup', message: error.message });
				return;
			}
			setStatus(error && error.message ? error.message : label('popupFailed', 'Popup state could not be updated.'), true);
		});
	});

	if (modal && !modal.hidden) {
		var currentId = form.elements.post_id ? String(parseInt(form.elements.post_id.value, 10) || 0) : '0';
		syncForm(currentId !== '0' && records[currentId] ? records[currentId] : { popup_enabled: false, visibility: 'homepage', visibility_page_ids: [], destination_url: '' });
	}
})();