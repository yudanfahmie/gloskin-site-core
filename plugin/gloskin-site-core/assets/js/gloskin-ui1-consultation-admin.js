/**
 * Konsultasi Perawatan admin: Pemetaan Produk workspace enhancements
 * (docs/task-treatment-consultation-commerce-discovery.md section 8.2).
 *
 * The checkbox matrix rendered by
 * AdminService::render_consultation_pemetaan() ([data-gloskin-mapping-form])
 * is already a complete, native, keyboard/screen-reader accessible way to
 * save the mapping -- every feature here is a progressive enhancement on
 * top of it and nothing here is required for the form to work. If this
 * script fails to load, the checkboxes still submit correctly.
 */
(function () {
	'use strict';

	function initMappingSearch(form) {
		var search = form.querySelector('[data-gloskin-mapping-search]');
		if (!search) { return; }
		var items = form.querySelectorAll('[data-gloskin-mapping-item]');
		search.addEventListener('input', function () {
			var query = search.value.trim().toLowerCase();
			for (var i = 0; i < items.length; i++) {
				var name = items[i].getAttribute('data-product-name') || '';
				items[i].style.display = ( '' === query || name.indexOf(query) !== -1 ) ? '' : 'none';
			}
		});
	}

	/* Drag-and-drop enhancement: dragging a product's row out of one
	 * concern bucket and dropping it onto another bucket ALSO checks that
	 * product's checkbox in the target bucket -- it never unchecks the
	 * source, matching the "a product may map to multiple concerns" rule.
	 * Identity across buckets is the checkbox value (product ID), which is
	 * the same in every bucket. */
	function initMappingDragAndDrop(form) {
		var buckets = form.querySelectorAll('[data-gloskin-mapping-bucket]');
		if (buckets.length < 2) { return; }

		for (var i = 0; i < buckets.length; i++) {
			var items = buckets[i].querySelectorAll('[data-gloskin-mapping-item]');
			for (var j = 0; j < items.length; j++) {
				initDraggableItem(items[j]);
			}
			initDropTarget(buckets[i]);
		}
	}

	function productIdOf(item) {
		var checkbox = item.querySelector('input[type="checkbox"]');
		return checkbox ? checkbox.value : '';
	}

	function initDraggableItem(item) {
		item.setAttribute('draggable', 'true');
		item.addEventListener('dragstart', function (event) {
			var productId = productIdOf(item);
			if (!productId || !event.dataTransfer) { return; }
			event.dataTransfer.setData('text/plain', productId);
			event.dataTransfer.effectAllowed = 'copy';
			item.classList.add('is-dragging');
		});
		item.addEventListener('dragend', function () {
			item.classList.remove('is-dragging');
		});
	}

	function initDropTarget(bucket) {
		bucket.addEventListener('dragover', function (event) {
			event.preventDefault();
			if (event.dataTransfer) { event.dataTransfer.dropEffect = 'copy'; }
			bucket.classList.add('is-drop-target');
		});
		bucket.addEventListener('dragleave', function (event) {
			if (event.target === bucket) { bucket.classList.remove('is-drop-target'); }
		});
		bucket.addEventListener('drop', function (event) {
			event.preventDefault();
			bucket.classList.remove('is-drop-target');
			var productId = event.dataTransfer ? event.dataTransfer.getData('text/plain') : '';
			if (!productId) { return; }
			var items = bucket.querySelectorAll('[data-gloskin-mapping-item]');
			for (var i = 0; i < items.length; i++) {
				if (productIdOf(items[i]) === productId) {
					var checkbox = items[i].querySelector('input[type="checkbox"]');
					if (checkbox && !checkbox.checked) {
						checkbox.checked = true;
						items[i].classList.add('is-recently-mapped');
						window.setTimeout(function (el) {
							return function () { el.classList.remove('is-recently-mapped'); };
						}(items[i]), 1200);
					}
					break;
				}
			}
		});
	}

	function init() {
		var form = document.querySelector('[data-gloskin-mapping-form]');
		if (!form) { return; }
		initMappingSearch(form);
		initMappingDragAndDrop(form);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
