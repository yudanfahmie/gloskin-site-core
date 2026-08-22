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
				window.location.reload();
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
			button.setAttribute('data-active', active ? '1' : '0');
			button.setAttribute('aria-pressed', active ? 'true' : 'false');
			button.classList.toggle('is-active', active);
			button.textContent = active ? 'Active' : 'Inactive';
			button.disabled = false;
		}).catch(function () { button.disabled = false; });
	}

	function initSortable() {
		var table = $('#the-list');
		if (!table.length || !$.fn.sortable) { return; }
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
					table.removeClass('is-gloskin-saving-order');
				}).catch(function () { table.removeClass('is-gloskin-saving-order'); window.location.reload(); });
			}
		});
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
