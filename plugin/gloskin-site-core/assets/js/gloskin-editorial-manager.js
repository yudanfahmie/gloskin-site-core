/* Shared native-list manager for Gloskin Promo + Testimonial. */
(function ($) {
	'use strict';

	var config = window.GloskinEditorialManager;
	if (!config || !config.postType) { return; }

	var modal = document.querySelector('[data-gloskin-editorial-modal]');
	var form = modal ? modal.querySelector('[data-gloskin-editorial-form]') : null;
	var recordsNode = document.getElementById('gloskin-editorial-records');
	var records = {};
	var mediaFrame = null;
	var lastFocus = null;

	try {
		records = recordsNode ? JSON.parse(recordsNode.textContent || '{}') : {};
	} catch (error) {
		records = {};
	}

	function setField(name, value) {
		if (!form) { return; }
		var field = form.elements[name];
		if (!field) { return; }
		if (field.type === 'checkbox') {
			field.checked = !!value;
		} else {
			field.value = value === null || typeof value === 'undefined' ? '' : String(value);
		}
	}

	function setPreview(record) {
		if (!form) { return; }
		var preview = form.querySelector('[data-gloskin-editorial-preview]');
		if (!preview) { return; }
		preview.replaceChildren();
		if (!record || !record.image_url) { return; }
		var image = document.createElement('img');
		image.src = record.image_url;
		image.alt = '';
		preview.appendChild(image);
	}

	function populate(record) {
		record = record || {};
		setField('post_id', record.id || 0);
		setField('title', record.title || '');
		setField('promo_type', record.promo_type || 'limited');
		setField('subtitle', record.subtitle || '');
		setField('quote', record.quote || '');
		setField('image_id', record.image_id || 0);
		setField('active', typeof record.active === 'undefined' ? true : !!record.active);
		setPreview(record);
		var error = form.querySelector('[data-gloskin-editorial-error]');
		if (error) { error.hidden = true; error.textContent = ''; }
	}

	function openModal(id) {
		if (!modal || !form) { return; }
		lastFocus = document.activeElement;
		var record = id && records[String(id)] ? records[String(id)] : null;
		populate(record);
		modal.hidden = false;
		document.body.classList.add('gloskin-editorial-modal-open');
		var first = form.querySelector('input[name="title"]');
		if (first) { window.setTimeout(function () { first.focus(); }, 0); }
	}

	function closeModal() {
		if (!modal) { return; }
		modal.hidden = true;
		document.body.classList.remove('gloskin-editorial-modal-open');
		if (lastFocus && typeof lastFocus.focus === 'function') { lastFocus.focus(); }
	}

	function ajax(action, payload) {
		var data = new FormData();
		data.append('action', action);
		data.append('nonce', config.nonce);
		Object.keys(payload || {}).forEach(function (key) {
			var value = payload[key];
			if (Array.isArray(value)) {
				value.forEach(function (item) { data.append(key + '[]', item); });
			} else {
				data.append(key, value);
			}
		});
		return window.fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data }).then(function (response) {
			return response.json();
		});
	}

	function interceptLinks() {
		document.addEventListener('click', function (event) {
			var close = event.target.closest('[data-gloskin-editorial-close]');
			if (close) { event.preventDefault(); closeModal(); return; }

			var edit = event.target.closest('[data-gloskin-editorial-edit], a[href*="gloskin_edit="]');
			if (edit) {
				var id = edit.getAttribute('data-gloskin-editorial-edit');
				if (!id) {
					try { id = new URL(edit.href, window.location.href).searchParams.get('gloskin_edit'); } catch (ignore) { id = ''; }
				}
				if (id) { event.preventDefault(); openModal(parseInt(id, 10)); return; }
			}

			var add = event.target.closest('.page-title-action, a[href*="post-new.php?post_type=' + config.postType + '"]');
			if (add) { event.preventDefault(); openModal(0); return; }

			var mediaButton = event.target.closest('[data-gloskin-editorial-media]');
			if (mediaButton) { event.preventDefault(); openMedia(); return; }

			var removeMedia = event.target.closest('[data-gloskin-editorial-media-remove]');
			if (removeMedia) {
				event.preventDefault();
				setField('image_id', 0);
				setPreview(null);
				return;
			}

			var toggle = event.target.closest('[data-gloskin-editorial-toggle]');
			if (toggle) { event.preventDefault(); toggleActive(toggle); }
		});
	}

	function openMedia() {
		if (!window.wp || !wp.media || !form) { return; }
		if (!mediaFrame) {
			mediaFrame = wp.media({
				title: 'Choose image',
				button: { text: 'Use image' },
				library: { type: 'image' },
				multiple: false
			});
			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				setField('image_id', attachment.id || 0);
				var source = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				setPreview({ image_url: source || '' });
			});
		}
		mediaFrame.open();
	}

	function columnCell(className) {
		var cell = document.createElement('td');
		cell.className = className + ' column-' + className;
		return cell;
	}

	function setImageCell(cell, record) {
		if (!cell) { return false; }
		cell.replaceChildren();
		if (record.image_url) {
			var image = document.createElement('img');
			image.className = 'gloskin-editorial-list-thumb';
			image.src = record.image_url;
			image.alt = '';
			cell.appendChild(image);
		} else {
			var empty = document.createElement('span');
			empty.setAttribute('aria-hidden', 'true');
			empty.textContent = '—';
			cell.appendChild(empty);
		}
		return true;
	}

	function setActiveCell(cell, record) {
		if (!cell) { return false; }
		var button = cell.querySelector('[data-gloskin-editorial-toggle]');
		if (!button) {
			cell.replaceChildren();
			button = document.createElement('button');
			button.type = 'button';
			button.className = 'button gloskin-editorial-active-toggle';
			button.setAttribute('data-gloskin-editorial-toggle', '');
			cell.appendChild(button);
		}
		button.setAttribute('data-id', String(record.id));
		button.setAttribute('data-active', record.active ? '1' : '0');
		button.setAttribute('aria-pressed', record.active ? 'true' : 'false');
		button.classList.toggle('is-active', !!record.active);
		button.textContent = record.active ? 'Active' : 'Inactive';
		return true;
	}

	function editHref(id) {
		var url = new URL(window.location.href);
		url.searchParams.set('post_type', config.postType);
		url.searchParams.set('gloskin_edit', String(id));
		url.searchParams.delete('gloskin_add');
		return url.toString();
	}

	function createRow(record) {
		var body = document.getElementById('the-list');
		if (!body) { return null; }
		var empty = body.querySelector('.no-items');
		if (empty) { empty.remove(); }

		var row = document.createElement('tr');
		row.id = 'post-' + record.id;
		row.className = 'iedit author-self level-0 status-publish hentry';

		var check = document.createElement('th');
		check.scope = 'row';
		check.className = 'check-column';
		var checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		checkbox.name = 'post[]';
		checkbox.value = String(record.id);
		check.appendChild(checkbox);
		row.appendChild(check);

		var order = columnCell('gloskin_editorial_order');
		var handle = document.createElement('span');
		handle.className = 'gloskin-editorial-order-handle';
		handle.setAttribute('aria-label', 'Drag to reorder');
		handle.title = 'Drag to reorder';
		var icon = document.createElement('span');
		icon.className = 'dashicons dashicons-menu';
		icon.setAttribute('aria-hidden', 'true');
		handle.appendChild(icon);
		order.appendChild(handle);
		row.appendChild(order);

		row.appendChild(columnCell('gloskin_editorial_image'));

		var title = columnCell('title');
		title.className += ' column-primary has-row-actions';
		var strong = document.createElement('strong');
		var titleLink = document.createElement('a');
		titleLink.className = 'row-title';
		titleLink.setAttribute('data-gloskin-editorial-edit', String(record.id));
		titleLink.href = editHref(record.id);
		strong.appendChild(titleLink);
		title.appendChild(strong);
		var actions = document.createElement('div');
		actions.className = 'row-actions';
		var editAction = document.createElement('span');
		editAction.className = 'edit';
		var editLink = document.createElement('a');
		editLink.setAttribute('data-gloskin-editorial-edit', String(record.id));
		editLink.href = editHref(record.id);
		editLink.textContent = 'Edit';
		editAction.appendChild(editLink);
		actions.appendChild(editAction);
		title.appendChild(actions);
		row.appendChild(title);

		if (config.postType === 'gloskin_promo') {
			row.appendChild(columnCell('gloskin_promo_type'));
		} else {
			row.appendChild(columnCell('gloskin_testimonial_role'));
			row.appendChild(columnCell('gloskin_testimonial_quote'));
		}
		row.appendChild(columnCell('gloskin_editorial_active'));
		var date = columnCell('date');
		date.textContent = '—';
		row.appendChild(date);
		body.appendChild(row);
		return row;
	}

	function updateRow(row, record) {
		if (!row) { return false; }
		row.id = 'post-' + record.id;
		var checkbox = row.querySelector('input[name="post[]"]');
		if (checkbox) { checkbox.value = String(record.id); }

		var titleLink = row.querySelector('.column-title .row-title, .column-title [data-gloskin-editorial-edit]');
		if (!titleLink) { return false; }
		titleLink.textContent = record.title || '';
		titleLink.setAttribute('data-gloskin-editorial-edit', String(record.id));
		titleLink.href = editHref(record.id);
		row.querySelectorAll('.column-title [data-gloskin-editorial-edit]').forEach(function (link) {
			link.setAttribute('data-gloskin-editorial-edit', String(record.id));
			link.href = editHref(record.id);
		});

		if (!setImageCell(row.querySelector('.column-gloskin_editorial_image'), record)) { return false; }
		if (config.postType === 'gloskin_promo') {
			var typeCell = row.querySelector('.column-gloskin_promo_type');
			if (!typeCell) { return false; }
			typeCell.textContent = record.promo_type === 'limited' ? 'Promo Terbatas' : 'Promo Biasa';
		} else {
			var roleCell = row.querySelector('.column-gloskin_testimonial_role');
			var quoteCell = row.querySelector('.column-gloskin_testimonial_quote');
			if (!roleCell || !quoteCell) { return false; }
			roleCell.textContent = record.subtitle || '—';
			var words = String(record.quote || '').trim().split(/\s+/).filter(Boolean);
			quoteCell.textContent = words.length ? words.slice(0, 18).join(' ') + (words.length > 18 ? '…' : '') : '—';
		}
		return setActiveCell(row.querySelector('.column-gloskin_editorial_active'), record);
	}

	function reconcileRecord(record) {
		if (!record || !record.id) { return false; }
		var row = document.getElementById('post-' + record.id);
		if (!row) { row = createRow(record); }
		if (!updateRow(row, record)) { return false; }
		var duplicateRows = document.querySelectorAll('#the-list > tr#post-' + record.id);
		for (var index = 1; index < duplicateRows.length; index += 1) { duplicateRows[index].remove(); }
		refreshSortable();
		return true;
	}

	function bindForm() {
		if (!form) { return; }
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			var save = form.querySelector('[data-gloskin-editorial-save]');
			var error = form.querySelector('[data-gloskin-editorial-error]');
			var data = new FormData(form);
			var payload = {};
			data.forEach(function (value, key) { payload[key] = value; });
			payload.active = form.elements.active && form.elements.active.checked ? '1' : '0';
			if (save) { save.disabled = true; save.textContent = config.labels.saving; }
			ajax('gloskin_editorial_save', payload).then(function (response) {
				if (!response || !response.success) { throw new Error(response && response.data && response.data.message ? response.data.message : config.labels.error); }
				var record = response.data && response.data.record ? response.data.record : null;
				if (!record || !record.id) { throw new Error(config.labels.error); }
				records[String(record.id)] = record;
				if (!reconcileRecord(record)) {
					// Explicit failure recovery only: the server save succeeded but the
					// native table DOM no longer matches the manager's known schema.
					window.location.reload();
					return;
				}
				if (save) { save.disabled = false; save.textContent = 'Save'; }
				closeModal();
			}).catch(function (requestError) {
				if (error) { error.textContent = requestError.message || config.labels.error; error.hidden = false; }
				if (save) { save.disabled = false; save.textContent = 'Save'; }
			});
		});
	}

	function toggleActive(button) {
		var next = button.getAttribute('data-active') === '1' ? '0' : '1';
		button.disabled = true;
		ajax('gloskin_editorial_toggle', { post_id: button.getAttribute('data-id'), active: next }).then(function (response) {
			if (!response || !response.success) { throw new Error(config.labels.error); }
			var active = !!response.data.active;
			var id = String(button.getAttribute('data-id') || '');
			button.setAttribute('data-active', active ? '1' : '0');
			button.setAttribute('aria-pressed', active ? 'true' : 'false');
			button.classList.toggle('is-active', active);
			button.textContent = active ? 'Active' : 'Inactive';
			button.disabled = false;
			if (records[id]) { records[id].active = active; }
		}).catch(function () { button.disabled = false; });
	}

	function initSortable() {
		var table = $('#the-list');
		if (!table.length || !$.fn.sortable) { return; }
		if (table.hasClass('ui-sortable')) {
			table.sortable('refresh');
			return;
		}
		table.sortable({
			items: '> tr[id^="post-"]',
			handle: '.gloskin-editorial-order-handle',
			axis: 'y',
			helper: function (event, row) {
				row.children().each(function () { $(this).width($(this).width()); });
				return row;
			},
			update: function () {
				var ids = table.children('tr[id^="post-"]').map(function () { return this.id.replace('post-', ''); }).get();
				table.addClass('is-gloskin-saving-order');
				ajax('gloskin_editorial_reorder', { post_type: config.postType, ids: ids }).then(function (response) {
					if (!response || !response.success) { throw new Error(config.labels.error); }
					ids.forEach(function (id, index) { if (records[String(id)]) { records[String(id)].order = index + 1; } });
					table.removeClass('is-gloskin-saving-order');
				}).catch(function () { table.removeClass('is-gloskin-saving-order'); });
			}
		});
	}

	function refreshSortable() {
		initSortable();
	}

	function bindKeyboard() {
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && modal && !modal.hidden) { event.preventDefault(); closeModal(); }
		});
	}

	interceptLinks();
	bindForm();
	bindKeyboard();
	initSortable();
	if (config.editId) { openModal(parseInt(config.editId, 10)); }
	else if (config.addId) { openModal(0); }
})(jQuery);
