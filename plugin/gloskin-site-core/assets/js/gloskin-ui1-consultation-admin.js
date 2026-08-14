/**
 * Konsultasi Perawatan admin: Pemetaan Produk progressive enhancement.
 *
 * AdminService renders the canonical checkbox matrix first. That matrix is
 * the only submitted state and remains a complete no-JS/keyboard fallback.
 * This file builds a friendlier projection of those SAME checkboxes:
 * one searchable Treatment Product pool on the left and concern buckets on
 * the right. Drag/add/remove only toggles the existing checkbox relationship;
 * it never creates another mapping store and never persists browser state.
 */
(function () {
	'use strict';

	function text(el) {
		return el ? String(el.textContent || '').trim() : '';
	}

	function concernIdFromCheckbox(checkbox) {
		var match = String(checkbox && checkbox.name || '').match(/^mapping\[(\d+)\]\[\]$/);
		return match ? match[1] : '';
	}

	function buildModel(form) {
		var nativeGrid = form.querySelector('.gloskin-admin-mapping-grid');
		var nativeBuckets = form.querySelectorAll('[data-gloskin-mapping-bucket]');
		var products = {};
		var productOrder = [];
		var concerns = [];

		for (var i = 0; i < nativeBuckets.length; i++) {
			var bucket = nativeBuckets[i];
			var legend = bucket.querySelector('legend');
			var checkboxes = bucket.querySelectorAll('input[type="checkbox"][name^="mapping["]');
			if (!checkboxes.length) { continue; }
			var concernId = concernIdFromCheckbox(checkboxes[0]);
			if (!concernId) { continue; }
			var concern = {
				id: concernId,
				name: text(legend) || ('Keluhan ' + concernId),
				checkboxes: {}
			};

			for (var j = 0; j < checkboxes.length; j++) {
				var checkbox = checkboxes[j];
				var productId = String(checkbox.value || '');
				if (!productId) { continue; }
				var item = checkbox.closest ? checkbox.closest('[data-gloskin-mapping-item]') : checkbox.parentNode;
				var productName = text(item).replace(/^\s+|\s+$/g, '');
				concern.checkboxes[productId] = checkbox;
				if (!products[productId]) {
					products[productId] = { id: productId, name: productName || ('Produk ' + productId) };
					productOrder.push(productId);
				}
			}
			concerns.push(concern);
		}

		return {
			nativeGrid: nativeGrid,
			products: products,
			productOrder: productOrder,
			concerns: concerns
		};
	}

	function createEl(tag, className, content) {
		var el = document.createElement(tag);
		if (className) { el.className = className; }
		if (typeof content === 'string') { el.textContent = content; }
		return el;
	}

	function enhanceMapping(form) {
		var model = buildModel(form);
		if (!model.nativeGrid || !model.productOrder.length || !model.concerns.length) { return; }

		var search = form.querySelector('[data-gloskin-mapping-search]');
		var workspace = createEl('div', 'gloskin-admin-mapping-enhanced');
		workspace.setAttribute('data-gloskin-mapping-enhanced', '');

		var status = createEl('p', 'screen-reader-text');
		status.setAttribute('aria-live', 'polite');
		status.setAttribute('data-gloskin-mapping-status', '');
		workspace.appendChild(status);

		var poolPanel = createEl('section', 'gloskin-admin-mapping-pool');
		poolPanel.setAttribute('aria-labelledby', 'gloskin-treatment-product-pool-title');
		var poolTitle = createEl('h3', '', 'Treatment Product');
		poolTitle.id = 'gloskin-treatment-product-pool-title';
		poolPanel.appendChild(poolTitle);
		poolPanel.appendChild(createEl('p', 'description', 'Cari lalu seret produk ke keluhan. Produk tetap tersedia agar dapat dipetakan ke beberapa keluhan.'));
		var pool = createEl('ul', 'gloskin-admin-mapping-product-pool');
		pool.setAttribute('data-gloskin-product-pool', '');

		function mappedCount(productId) {
			var count = 0;
			for (var c = 0; c < model.concerns.length; c++) {
				var checkbox = model.concerns[c].checkboxes[productId];
				if (checkbox && checkbox.checked) { count++; }
			}
			return count;
		}

		function updatePoolMeta(productId) {
			var meta = pool.querySelector('[data-gloskin-pool-meta="' + productId + '"]');
			if (!meta) { return; }
			var count = mappedCount(productId);
			meta.textContent = count ? (count + ' keluhan') : 'Belum dipetakan';
		}

		for (var p = 0; p < model.productOrder.length; p++) {
			(function (product) {
				var item = createEl('li', 'gloskin-admin-mapping-pool-item');
				item.setAttribute('data-gloskin-pool-product', product.id);
				item.setAttribute('data-product-name', product.name.toLowerCase());
				item.setAttribute('draggable', 'true');
				item.appendChild(createEl('strong', '', product.name));
				var meta = createEl('span', 'description');
				meta.setAttribute('data-gloskin-pool-meta', product.id);
				item.appendChild(meta);
				item.addEventListener('dragstart', function (event) {
					if (!event.dataTransfer) { return; }
					event.dataTransfer.setData('text/plain', product.id);
					event.dataTransfer.effectAllowed = 'copy';
					item.classList.add('is-dragging');
				});
				item.addEventListener('dragend', function () { item.classList.remove('is-dragging'); });
				pool.appendChild(item);
				updatePoolMeta(product.id);
			}(model.products[model.productOrder[p]]));
		}
		poolPanel.appendChild(pool);
		workspace.appendChild(poolPanel);

		var bucketsPanel = createEl('section', 'gloskin-admin-mapping-concerns');
		bucketsPanel.setAttribute('aria-labelledby', 'gloskin-concern-buckets-title');
		var bucketsTitle = createEl('h3', '', 'Keluhan');
		bucketsTitle.id = 'gloskin-concern-buckets-title';
		bucketsPanel.appendChild(bucketsTitle);
		var bucketsGrid = createEl('div', 'gloskin-admin-mapping-buckets');
		bucketsPanel.appendChild(bucketsGrid);

		function setRelationship(concern, productId, checked) {
			var checkbox = concern.checkboxes[productId];
			if (!checkbox || checkbox.checked === checked) { return false; }
			checkbox.checked = checked;
			checkbox.dispatchEvent(new Event('change', { bubbles: true }));
			return true;
		}

		function renderBucket(concern, bucket, chipList) {
			chipList.innerHTML = '';
			var count = 0;
			for (var i = 0; i < model.productOrder.length; i++) {
				var productId = model.productOrder[i];
				var checkbox = concern.checkboxes[productId];
				if (!checkbox || !checkbox.checked) { continue; }
				count++;
				(function (product, targetCheckbox) {
					var chip = createEl('li', 'gloskin-admin-mapping-chip');
					chip.setAttribute('data-gloskin-mapped-chip', product.id);
					chip.appendChild(createEl('span', '', product.name));
					var remove = createEl('button', 'button-link-delete', 'Hapus');
					remove.type = 'button';
					remove.setAttribute('aria-label', 'Hapus ' + product.name + ' dari keluhan ' + concern.name);
					remove.addEventListener('click', function () {
						targetCheckbox.checked = false;
						targetCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
						status.textContent = product.name + ' dihapus dari ' + concern.name + '.';
					});
					chip.appendChild(remove);
					chipList.appendChild(chip);
				}(model.products[productId], checkbox));
			}
			if (!count) {
				var empty = createEl('li', 'description', 'Belum ada produk.');
				empty.setAttribute('data-gloskin-empty-bucket', '');
				chipList.appendChild(empty);
			}
			bucket.setAttribute('data-mapped-count', String(count));
		}

		for (var c = 0; c < model.concerns.length; c++) {
			(function (concern) {
				var bucket = createEl('fieldset', 'gloskin-admin-mapping-bucket-enhanced');
				bucket.setAttribute('data-gloskin-concern-bucket', concern.id);
				var legend = createEl('legend', '', concern.name);
				bucket.appendChild(legend);

				var controls = createEl('div', 'gloskin-admin-mapping-add');
				var select = document.createElement('select');
				select.setAttribute('aria-label', 'Pilih Treatment Product untuk ' + concern.name);
				var placeholder = document.createElement('option');
				placeholder.value = '';
				placeholder.textContent = 'Pilih produk…';
				select.appendChild(placeholder);
				for (var i = 0; i < model.productOrder.length; i++) {
					var product = model.products[model.productOrder[i]];
					var option = document.createElement('option');
					option.value = product.id;
					option.textContent = product.name;
					select.appendChild(option);
				}
				var add = createEl('button', 'button button-secondary', 'Tambah');
				add.type = 'button';
				add.addEventListener('click', function () {
					var productId = select.value;
					if (!productId) { return; }
					if (setRelationship(concern, productId, true)) {
						status.textContent = model.products[productId].name + ' dipetakan ke ' + concern.name + '.';
					}
					select.value = '';
				});
				controls.appendChild(select);
				controls.appendChild(add);
				bucket.appendChild(controls);

				var chipList = createEl('ul', 'gloskin-admin-mapping-chip-list');
				bucket.appendChild(chipList);

				bucket.addEventListener('dragover', function (event) {
					event.preventDefault();
					if (event.dataTransfer) { event.dataTransfer.dropEffect = 'copy'; }
					bucket.classList.add('is-drop-target');
				});
				bucket.addEventListener('dragleave', function (event) {
					if (!bucket.contains(event.relatedTarget)) {
						bucket.classList.remove('is-drop-target');
					}
				});
				bucket.addEventListener('drop', function (event) {
					event.preventDefault();
					bucket.classList.remove('is-drop-target');
					var productId = event.dataTransfer ? event.dataTransfer.getData('text/plain') : '';
					if (!productId || !model.products[productId]) { return; }
					if (setRelationship(concern, productId, true)) {
						status.textContent = model.products[productId].name + ' dipetakan ke ' + concern.name + '.';
					}
				});

				var relatedCheckboxes = Object.keys(concern.checkboxes);
				for (var r = 0; r < relatedCheckboxes.length; r++) {
					(function (productId) {
						concern.checkboxes[productId].addEventListener('change', function () {
							renderBucket(concern, bucket, chipList);
							updatePoolMeta(productId);
						});
					}(relatedCheckboxes[r]));
				}
				renderBucket(concern, bucket, chipList);
				bucketsGrid.appendChild(bucket);
			}(model.concerns[c]));
		}
		workspace.appendChild(bucketsPanel);

		if (search) {
			search.addEventListener('input', function () {
				var query = search.value.trim().toLowerCase();
				var items = pool.querySelectorAll('[data-gloskin-pool-product]');
				for (var i = 0; i < items.length; i++) {
					var name = items[i].getAttribute('data-product-name') || '';
					items[i].hidden = !!query && name.indexOf(query) === -1;
				}
			});
		}

		var fallbackControls = createEl('p', 'gloskin-admin-mapping-fallback');
		var toggle = createEl('button', 'button button-link', 'Tampilkan pemetaan native');
		toggle.type = 'button';
		toggle.setAttribute('aria-expanded', 'false');
		toggle.addEventListener('click', function () {
			var show = model.nativeGrid.hidden;
			model.nativeGrid.hidden = !show;
			toggle.setAttribute('aria-expanded', show ? 'true' : 'false');
			toggle.textContent = show ? 'Sembunyikan pemetaan native' : 'Tampilkan pemetaan native';
		});
		fallbackControls.appendChild(toggle);

		model.nativeGrid.parentNode.insertBefore(workspace, model.nativeGrid);
		model.nativeGrid.parentNode.insertBefore(fallbackControls, model.nativeGrid);
		model.nativeGrid.hidden = true;
	}

	function init() {
		var form = document.querySelector('[data-gloskin-mapping-form]');
		if (!form) { return; }
		enhanceMapping(form);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
