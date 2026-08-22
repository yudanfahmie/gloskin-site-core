/* Shared native-list manager for Gloskin Promo + Testimonial. */
(function ($) {
	'use strict';

	var config = window.GloskinEditorialManager;
	if (!config || !config.postType) { return; }

	var modal = document.querySelector('[data-gloskin-editorial-modal]');
	var form = modal ? modal.querySelector('[data-gloskin-editorial-form]') : null;
	var recordsNode = document.getElementById('gloskin-editorial-records');
	var statusNode = document.querySelector('[data-gloskin-editorial-status]');
	var statusMessage = statusNode ? statusNode.querySelector('[data-gloskin-editorial-status-message]') : null;
	var records = {};
	var mediaFrame = null;
	var mediaFrameActive = false;
	var mediaTrigger = null;
	var lastFocus = null;
	var sortableSnapshot = [];
	var isPromo = config.postType === 'gloskin_promo';
	var promoCrop = isPromo && form ? form.querySelector('[data-gloskin-promo-crop]') : null;
	var promoCropViewport = promoCrop ? promoCrop.querySelector('[data-gloskin-promo-crop-viewport]') : null;
	var promoCropQuality = promoCrop ? promoCrop.querySelector('[data-gloskin-promo-crop-quality]') : null;
	var promoCropApply = form ? form.querySelector('[data-gloskin-promo-crop-apply]') : null;
	var promoCropReset = form ? form.querySelector('[data-gloskin-promo-crop-reset]') : null;
	var promoCropSource = null;
	var promoCropSelection = null;
	var promoCropOutput = null;
	var promoCropOutputImage = null;
	var promoCropZoom = null;
	var promoCropZoomValue = null;
	var promoCropSmart = null;
	var PROMO_MIN_WIDTH = Number(config.promoCropWidth) || 1648;
	var PROMO_MIN_HEIGHT = Number(config.promoCropHeight) || 928;
	var PROMO_RATIO = PROMO_MIN_WIDTH / PROMO_MIN_HEIGHT;
	var PROMO_ZOOM_MIN = Number(config.promoZoomMin) || 100;
	var PROMO_ZOOM_MAX = Number(config.promoZoomMax) || 300;
	var cropState = {
		draftX: 50,
		draftY: 50,
		draftZoom: 100,
		appliedX: 50,
		appliedY: 50,
		appliedZoom: 100,
		width: 0,
		height: 0,
		dirty: false,
		replacement: false,
		dragging: false,
		pointerId: null,
		mode: '',
		startX: 0,
		startY: 0,
		startFocusX: 50,
		startFocusY: 50,
		startZoom: 100
	};

	try {
		records = recordsNode ? JSON.parse(recordsNode.textContent || '{}') : {};
	} catch (error) {
		records = {};
	}

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

	function formError(message) {
		if (!form) { return; }
		var error = form.querySelector('[data-gloskin-editorial-error]');
		if (!error) { return; }
		error.textContent = message || '';
		error.hidden = !message;
	}

	function normalizeModalQuery() {
		try {
			var url = new URL(window.location.href);
			var changed = url.searchParams.has('gloskin_edit') || url.searchParams.has('gloskin_add');
			url.searchParams.delete('gloskin_edit');
			url.searchParams.delete('gloskin_add');
			if (changed && window.history && window.history.replaceState) {
				window.history.replaceState(null, '', url.toString());
			}
		} catch (ignore) {
			// Query normalization is best-effort only.
		}
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

	function ensureHiddenField(name, value) {
		if (!form || form.elements[name]) { return; }
		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = name;
		input.value = String(value);
		form.appendChild(input);
	}

	function clampFocus(value) {
		value = Number(value);
		if (!isFinite(value)) { return 50; }
		return Math.max(0, Math.min(100, value));
	}

	function clampZoom(value, maxValue) {
		value = Number(value);
		if (!isFinite(value)) { value = PROMO_ZOOM_MIN; }
		maxValue = Number(maxValue);
		if (!isFinite(maxValue) || maxValue < PROMO_ZOOM_MIN) { maxValue = PROMO_ZOOM_MAX; }
		return Math.max(PROMO_ZOOM_MIN, Math.min(maxValue, value));
	}

	function hasSelectedImage() {
		return !!(form && form.elements.image_id && (parseInt(form.elements.image_id.value, 10) || 0));
	}

	function cropIsLowResolution() {
		return hasSelectedImage() && cropState.width > 0 && cropState.height > 0 &&
			(cropState.width < PROMO_MIN_WIDTH || cropState.height < PROMO_MIN_HEIGHT);
	}

	function baseCropSize() {
		var width = Math.max(1, cropState.width || 1);
		var height = Math.max(1, cropState.height || 1);
		if ((width / height) >= PROMO_RATIO) {
			return { width: height * PROMO_RATIO, height: height };
		}
		return { width: width, height: width / PROMO_RATIO };
	}

	function qualityMaxZoom() {
		if (!cropState.width || !cropState.height) { return PROMO_ZOOM_MAX; }
		var base = baseCropSize();
		var byWidth = (base.width / PROMO_MIN_WIDTH) * 100;
		var byHeight = (base.height / PROMO_MIN_HEIGHT) * 100;
		return Math.max(PROMO_ZOOM_MIN, Math.min(PROMO_ZOOM_MAX, byWidth, byHeight));
	}

	function cropGeometry(zoom, focusX, focusY) {
		var width = Math.max(1, cropState.width || 1);
		var height = Math.max(1, cropState.height || 1);
		var base = baseCropSize();
		var maxZoom = qualityMaxZoom();
		var safeZoom = clampZoom(zoom, maxZoom);
		var cropWidth = base.width * 100 / safeZoom;
		var cropHeight = base.height * 100 / safeZoom;
		var centerX = clampFocus(focusX) * width / 100;
		var centerY = clampFocus(focusY) * height / 100;
		centerX = Math.max(cropWidth / 2, Math.min(width - cropWidth / 2, centerX));
		centerY = Math.max(cropHeight / 2, Math.min(height - cropHeight / 2, centerY));
		return {
			x: centerX - cropWidth / 2,
			y: centerY - cropHeight / 2,
			width: cropWidth,
			height: cropHeight,
			centerX: centerX,
			centerY: centerY,
			focusX: centerX / width * 100,
			focusY: centerY / height * 100,
			zoom: safeZoom
		};
	}

	function renderedSourceRect() {
		if (!promoCropViewport || !cropState.width || !cropState.height) { return null; }
		var rect = promoCropViewport.getBoundingClientRect();
		if (!rect.width || !rect.height) { return null; }
		var scale = Math.min(rect.width / cropState.width, rect.height / cropState.height);
		var width = cropState.width * scale;
		var height = cropState.height * scale;
		return {
			left: (rect.width - width) / 2,
			top: (rect.height - height) / 2,
			width: width,
			height: height,
			scale: scale
		};
	}

	function markCropDirty() {
		cropState.dirty = Math.abs(cropState.draftX - cropState.appliedX) > 0.01 ||
			Math.abs(cropState.draftY - cropState.appliedY) > 0.01 ||
			Math.abs(cropState.draftZoom - cropState.appliedZoom) > 0.01 ||
			cropState.replacement;
	}

	function setCropDraft(x, y, zoom) {
		if (!isPromo || !hasSelectedImage()) { return; }
		var geometry = cropGeometry(
			typeof zoom === 'undefined' ? cropState.draftZoom : zoom,
			typeof x === 'undefined' ? cropState.draftX : x,
			typeof y === 'undefined' ? cropState.draftY : y
		);
		cropState.draftX = geometry.focusX;
		cropState.draftY = geometry.focusY;
		cropState.draftZoom = geometry.zoom;
		markCropDirty();
		syncCropPreviewPosition();
	}

	function ensureCropUi() {
		if (!isPromo || !promoCrop || !promoCropViewport || promoCropSource) { return; }
		ensureHiddenField('crop_zoom', 100);
		promoCropViewport.replaceChildren();

		promoCropSource = document.createElement('img');
		promoCropSource.className = 'gloskin-editorial-crop__source';
		promoCropSource.setAttribute('data-gloskin-promo-crop-source', '');
		promoCropSource.alt = '';
		promoCropViewport.appendChild(promoCropSource);

		promoCropSelection = document.createElement('div');
		promoCropSelection.className = 'gloskin-editorial-crop__selection';
		promoCropSelection.setAttribute('data-gloskin-promo-crop-selection', '');
		promoCropSelection.tabIndex = 0;
		promoCropSelection.setAttribute('role', 'application');
		promoCropSelection.setAttribute('aria-label', label('cropSelectionLabel', 'Crop selection. Drag to move, drag a corner handle to resize, or use arrow keys.'));
		['nw', 'ne', 'sw', 'se'].forEach(function (corner) {
			var handle = document.createElement('span');
			handle.className = 'gloskin-editorial-crop__handle gloskin-editorial-crop__handle--' + corner;
			handle.setAttribute('data-gloskin-crop-handle', corner);
			handle.setAttribute('aria-hidden', 'true');
			promoCropSelection.appendChild(handle);
		});
		promoCropViewport.appendChild(promoCropSelection);

		var toolbar = document.createElement('div');
		toolbar.className = 'gloskin-editorial-crop__toolbar';

		promoCropSmart = document.createElement('button');
		promoCropSmart.type = 'button';
		promoCropSmart.className = 'button';
		promoCropSmart.setAttribute('data-gloskin-promo-crop-smart', '');
		promoCropSmart.textContent = label('cropSmart', 'Smart select');
		toolbar.appendChild(promoCropSmart);

		var zoomLabel = document.createElement('label');
		zoomLabel.className = 'gloskin-editorial-crop__zoom';
		var zoomText = document.createElement('span');
		zoomText.textContent = label('cropZoom', 'Crop size');
		zoomLabel.appendChild(zoomText);
		promoCropZoom = document.createElement('input');
		promoCropZoom.type = 'range';
		promoCropZoom.min = String(PROMO_ZOOM_MIN);
		promoCropZoom.max = String(PROMO_ZOOM_MAX);
		promoCropZoom.step = '1';
		promoCropZoom.value = '100';
		promoCropZoom.setAttribute('aria-label', label('cropZoomAria', 'Crop zoom'));
		zoomLabel.appendChild(promoCropZoom);
		promoCropZoomValue = document.createElement('output');
		promoCropZoomValue.textContent = '100%';
		zoomLabel.appendChild(promoCropZoomValue);
		toolbar.appendChild(zoomLabel);
		promoCropViewport.insertAdjacentElement('afterend', toolbar);

		promoCropOutput = document.createElement('div');
		promoCropOutput.className = 'gloskin-editorial-crop__output';
		promoCropOutput.setAttribute('aria-label', label('cropOutput', 'Production crop preview'));
		promoCropOutputImage = document.createElement('img');
		promoCropOutputImage.alt = '';
		promoCropOutput.appendChild(promoCropOutputImage);
		toolbar.insertAdjacentElement('afterend', promoCropOutput);

		promoCropZoom.addEventListener('input', function () {
			setCropDraft(cropState.draftX, cropState.draftY, Number(promoCropZoom.value));
		});
		promoCropSmart.addEventListener('click', function () {
			smartSelectPromo(false);
		});
	}

	function syncCropPreviewPosition() {
		if (!promoCropViewport || !promoCropSelection) { return; }
		var geometry = cropGeometry(cropState.draftZoom, cropState.draftX, cropState.draftY);
		cropState.draftX = geometry.focusX;
		cropState.draftY = geometry.focusY;
		cropState.draftZoom = geometry.zoom;
		var sourceRect = renderedSourceRect();
		if (sourceRect) {
			promoCropSelection.style.left = (sourceRect.left + geometry.x / cropState.width * sourceRect.width) + 'px';
			promoCropSelection.style.top = (sourceRect.top + geometry.y / cropState.height * sourceRect.height) + 'px';
			promoCropSelection.style.width = (geometry.width / cropState.width * sourceRect.width) + 'px';
			promoCropSelection.style.height = (geometry.height / cropState.height * sourceRect.height) + 'px';
		}
		var scale = cropState.draftZoom / 100;
		if (promoCropOutput) {
			promoCropOutput.style.setProperty('--gloskin-promo-focus-x', cropState.draftX + '%');
			promoCropOutput.style.setProperty('--gloskin-promo-focus-y', cropState.draftY + '%');
			promoCropOutput.style.setProperty('--gloskin-promo-scale', String(scale));
		}
		if (promoCropZoom) {
			promoCropZoom.max = String(Math.max(PROMO_ZOOM_MIN, Math.floor(qualityMaxZoom())));
			promoCropZoom.value = String(Math.round(cropState.draftZoom));
		}
		if (promoCropZoomValue) { promoCropZoomValue.textContent = Math.round(cropState.draftZoom) + '%'; }
	}

	function refreshCropStateUi() {
		if (!isPromo || !promoCrop) { return; }
		ensureCropUi();
		var hasImage = hasSelectedImage();
		var lowResolution = cropIsLowResolution();
		var blockingLowResolution = !!(cropState.replacement && lowResolution);
		promoCrop.hidden = !hasImage;
		if (promoCropApply) {
			promoCropApply.hidden = !hasImage;
			promoCropApply.disabled = !hasImage || blockingLowResolution;
		}
		if (promoCropReset) {
			promoCropReset.hidden = !hasImage;
			promoCropReset.disabled = !hasImage;
		}
		if (promoCropSmart) { promoCropSmart.disabled = !hasImage; }
		if (!promoCropQuality) { return; }
		promoCropQuality.classList.toggle('is-error', blockingLowResolution);
		promoCropQuality.classList.toggle('is-warning', lowResolution && !blockingLowResolution);
		if (!hasImage) {
			promoCropQuality.textContent = '';
			return;
		}
		if (blockingLowResolution) {
			promoCropQuality.textContent = label(
				'cropLowResolution',
				'Image is below the required 1648 × 928 production size. Choose a larger source image.'
			);
			return;
		}
		if (lowResolution) {
			promoCropQuality.textContent = label(
				'cropLegacyLowResolution',
				'Legacy image is below 1648 × 928. You can reframe it, but replacing it requires a larger source image.'
			);
			return;
		}
		if (cropState.width > 0 && cropState.height > 0) {
			var geometry = cropGeometry(cropState.draftZoom, cropState.draftX, cropState.draftY);
			promoCropQuality.textContent = cropState.width + ' × ' + cropState.height + ' px source · selected crop ≈ ' + Math.round(geometry.width) + ' × ' + Math.round(geometry.height) + ' px';
		} else {
			promoCropQuality.textContent = label('cropDimensionsUnknown', 'Image dimensions will be validated when you save.');
		}
	}

	function setPreview(record) {
		if (!form) { return; }
		var preview = form.querySelector('[data-gloskin-editorial-preview]');
		if (!preview) { return; }

		if (isPromo) {
			ensureCropUi();
			record = record || {};
			cropState.draftX = clampFocus(typeof record.focus_x === 'undefined' ? 50 : record.focus_x);
			cropState.draftY = clampFocus(typeof record.focus_y === 'undefined' ? 50 : record.focus_y);
			cropState.draftZoom = clampZoom(typeof record.zoom === 'undefined' ? 100 : record.zoom, PROMO_ZOOM_MAX);
			cropState.appliedX = cropState.draftX;
			cropState.appliedY = cropState.draftY;
			cropState.appliedZoom = cropState.draftZoom;
			cropState.width = parseInt(record.image_width, 10) || 0;
			cropState.height = parseInt(record.image_height, 10) || 0;
			cropState.dirty = !!record.crop_dirty;
			cropState.replacement = !!record.crop_replacement;
			setField('crop_zoom', cropState.appliedZoom);
			if (!record.image_url) {
				if (promoCropSource) { promoCropSource.removeAttribute('src'); }
				if (promoCropOutputImage) { promoCropOutputImage.removeAttribute('src'); }
				refreshCropStateUi();
				return;
			}
			var source = record.crop_image_url || record.image_url;
			promoCropSource.src = source;
			promoCropOutputImage.src = source;
			promoCropSource.onload = function () {
				if (!cropState.width) { cropState.width = promoCropSource.naturalWidth || 0; }
				if (!cropState.height) { cropState.height = promoCropSource.naturalHeight || 0; }
				setCropDraft(cropState.draftX, cropState.draftY, cropState.draftZoom);
				cropState.dirty = !!record.crop_dirty;
				if (record.crop_auto_select) {
					smartSelectPromo(true);
				} else {
					syncCropPreviewPosition();
					refreshCropStateUi();
				}
			};
			if (promoCropSource.complete && promoCropSource.naturalWidth) { promoCropSource.onload(); }
			return;
		}

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
		setField('focus_x', typeof record.focus_x === 'undefined' ? 50 : record.focus_x);
		setField('focus_y', typeof record.focus_y === 'undefined' ? 50 : record.focus_y);
		ensureHiddenField('crop_zoom', typeof record.zoom === 'undefined' ? 100 : record.zoom);
		setField('crop_zoom', typeof record.zoom === 'undefined' ? 100 : record.zoom);
		setField('active', typeof record.active === 'undefined' ? true : !!record.active);
		setPreview(record);
		formError('');
	}

	function openModal(id) {
		if (!modal || !form) { return false; }
		var requestedId = parseInt(id, 10) || 0;
		if (requestedId > 0 && !records[String(requestedId)]) {
			setStatus(label('invalidEdit', 'That record is no longer available. The list was left unchanged.'), true);
			normalizeModalQuery();
			return false;
		}
		lastFocus = document.activeElement;
		populate(requestedId > 0 ? records[String(requestedId)] : null);
		modal.hidden = false;
		document.body.classList.add('gloskin-editorial-modal-open');
		var first = form.querySelector('input[name="title"]');
		if (first) { first.focus(); }
		return true;
	}

	function closeModal() {
		if (!modal) { return; }
		modal.hidden = true;
		document.body.classList.remove('gloskin-editorial-modal-open');
		normalizeModalQuery();
		if (lastFocus && typeof lastFocus.focus === 'function') { lastFocus.focus(); }
	}

	function responseMessage(response, fallback) {
		return response && response.data && response.data.message ? response.data.message : fallback;
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

	function applyPromoCrop() {
		if (!isPromo || !hasSelectedImage()) { return false; }
		if (cropState.replacement && cropIsLowResolution()) {
			formError(label('cropLowResolution', 'Image is below the required 1648 × 928 production size. Choose a larger source image.'));
			return false;
		}
		var geometry = cropGeometry(cropState.draftZoom, cropState.draftX, cropState.draftY);
		cropState.appliedX = geometry.focusX;
		cropState.appliedY = geometry.focusY;
		cropState.appliedZoom = geometry.zoom;
		setField('focus_x', cropState.appliedX.toFixed(2).replace(/\.?0+$/, ''));
		setField('focus_y', cropState.appliedY.toFixed(2).replace(/\.?0+$/, ''));
		setField('crop_zoom', cropState.appliedZoom.toFixed(2).replace(/\.?0+$/, ''));
		cropState.dirty = false;
		formError('');
		setStatus(label('cropApplied', 'Crop selection applied. Save the Promo to persist it.'), false);
		return true;
	}

	function resetPromoCrop() {
		if (!isPromo || !hasSelectedImage()) { return false; }
		setCropDraft(50, 50, 100);
		return true;
	}

	function removeSelectedMedia() {
		setField('image_id', 0);
		setField('focus_x', 50);
		setField('focus_y', 50);
		setField('crop_zoom', 100);
		cropState.draftX = 50;
		cropState.draftY = 50;
		cropState.draftZoom = 100;
		cropState.appliedX = 50;
		cropState.appliedY = 50;
		cropState.appliedZoom = 100;
		cropState.width = 0;
		cropState.height = 0;
		cropState.dirty = false;
		cropState.replacement = false;
		setPreview(null);
		formError('');
	}

	function unionRects(rects) {
		if (!rects || !rects.length) { return null; }
		var left = Infinity;
		var top = Infinity;
		var right = -Infinity;
		var bottom = -Infinity;
		rects.forEach(function (rect) {
			left = Math.min(left, Number(rect.x || rect.left || 0));
			top = Math.min(top, Number(rect.y || rect.top || 0));
			right = Math.max(right, Number(rect.x || rect.left || 0) + Number(rect.width || 0));
			bottom = Math.max(bottom, Number(rect.y || rect.top || 0) + Number(rect.height || 0));
		});
		if (!isFinite(left) || !isFinite(top) || right <= left || bottom <= top) { return null; }
		return { x: left, y: top, width: right - left, height: bottom - top };
	}

	function detectFaceSubject() {
		if (!promoCropSource || typeof window.FaceDetector !== 'function') { return Promise.resolve(null); }
		try {
			var detector = new window.FaceDetector({ fastMode: true, maxDetectedFaces: 6 });
			return detector.detect(promoCropSource).then(function (faces) {
				if (!faces || !faces.length) { return null; }
				return unionRects(faces.map(function (face) { return face.boundingBox; }));
			}).catch(function () { return null; });
		} catch (error) {
			return Promise.resolve(null);
		}
	}

	function detectSalientSubject() {
		if (!promoCropSource || !promoCropSource.naturalWidth || !promoCropSource.naturalHeight) { return null; }
		try {
			var maxSide = 180;
			var scale = Math.min(1, maxSide / Math.max(promoCropSource.naturalWidth, promoCropSource.naturalHeight));
			var width = Math.max(24, Math.round(promoCropSource.naturalWidth * scale));
			var height = Math.max(24, Math.round(promoCropSource.naturalHeight * scale));
			var canvas = document.createElement('canvas');
			canvas.width = width;
			canvas.height = height;
			var context = canvas.getContext('2d', { willReadFrequently: true });
			if (!context) { return null; }
			context.drawImage(promoCropSource, 0, 0, width, height);
			var pixels = context.getImageData(0, 0, width, height).data;
			var samples = [];
			var sum = 0;
			for (var y = 1; y < height - 1; y += 2) {
				for (var x = 1; x < width - 1; x += 2) {
					var index = (y * width + x) * 4;
					var right = index + 4;
					var below = index + width * 4;
					var r = pixels[index];
					var g = pixels[index + 1];
					var b = pixels[index + 2];
					var lum = 0.299 * r + 0.587 * g + 0.114 * b;
					var lumRight = 0.299 * pixels[right] + 0.587 * pixels[right + 1] + 0.114 * pixels[right + 2];
					var lumBelow = 0.299 * pixels[below] + 0.587 * pixels[below + 1] + 0.114 * pixels[below + 2];
					var maxRgb = Math.max(r, g, b);
					var minRgb = Math.min(r, g, b);
					var saturation = maxRgb ? (maxRgb - minRgb) / maxRgb : 0;
					var skinBonus = r > 80 && g > 40 && b > 20 && r > g && g > b && (r - b) > 25 ? 18 : 0;
					var score = Math.abs(lum - lumRight) + Math.abs(lum - lumBelow) + saturation * 42 + skinBonus;
					samples.push({ x: x, y: y, score: score });
					sum += score;
				}
			}
			if (!samples.length || !sum) { return null; }
			samples.sort(function (a, b) { return b.score - a.score; });
			var keep = Math.max(12, Math.floor(samples.length * 0.18));
			var chosen = samples.slice(0, keep);
			var weight = 0;
			var centerX = 0;
			var centerY = 0;
			chosen.forEach(function (sample) {
				var w = Math.max(1, sample.score);
				weight += w;
				centerX += sample.x * w;
				centerY += sample.y * w;
			});
			centerX /= weight;
			centerY /= weight;
			var varianceX = 0;
			var varianceY = 0;
			chosen.forEach(function (sample) {
				var w = Math.max(1, sample.score);
				varianceX += Math.pow(sample.x - centerX, 2) * w;
				varianceY += Math.pow(sample.y - centerY, 2) * w;
			});
			varianceX = Math.sqrt(varianceX / weight);
			varianceY = Math.sqrt(varianceY / weight);
			var left = Math.max(0, centerX - Math.max(width * 0.13, varianceX * 2.4));
			var top = Math.max(0, centerY - Math.max(height * 0.13, varianceY * 2.4));
			var rightEdge = Math.min(width, centerX + Math.max(width * 0.13, varianceX * 2.4));
			var bottomEdge = Math.min(height, centerY + Math.max(height * 0.13, varianceY * 2.4));
			var inverseScale = 1 / scale;
			return {
				x: left * inverseScale,
				y: top * inverseScale,
				width: (rightEdge - left) * inverseScale,
				height: (bottomEdge - top) * inverseScale
			};
		} catch (error) {
			return null;
		}
	}

	function cropAroundSubject(subject) {
		if (!subject || !cropState.width || !cropState.height) {
			setCropDraft(50, 50, 100);
			return;
		}
		var centerX = subject.x + subject.width / 2;
		var centerY = subject.y + subject.height / 2;
		var paddedWidth = Math.max(subject.width * 1.65, PROMO_MIN_WIDTH);
		var paddedHeight = Math.max(subject.height * 1.65, PROMO_MIN_HEIGHT);
		if ((paddedWidth / paddedHeight) < PROMO_RATIO) { paddedWidth = paddedHeight * PROMO_RATIO; }
		else { paddedHeight = paddedWidth / PROMO_RATIO; }
		var base = baseCropSize();
		paddedWidth = Math.min(base.width, paddedWidth);
		paddedHeight = paddedWidth / PROMO_RATIO;
		if (paddedHeight > base.height) {
			paddedHeight = base.height;
			paddedWidth = paddedHeight * PROMO_RATIO;
		}
		var zoom = Math.min(qualityMaxZoom(), base.width / Math.max(1, paddedWidth) * 100);
		setCropDraft(centerX / cropState.width * 100, centerY / cropState.height * 100, zoom);
	}

	function smartSelectPromo(autoRun) {
		if (!isPromo || !hasSelectedImage() || !promoCropSource || !promoCropSource.naturalWidth) { return Promise.resolve(false); }
		if (promoCropSmart) {
			promoCropSmart.disabled = true;
			promoCropSmart.textContent = label('cropSmartWorking', 'Finding subject…');
		}
		return detectFaceSubject().then(function (subject) {
			if (!subject) { subject = detectSalientSubject(); }
			cropAroundSubject(subject);
			refreshCropStateUi();
			if (!autoRun) { setStatus(label('cropSmartReady', 'Smart crop selected. Fine-tune the selection, then choose Crop & Apply.'), false); }
			return true;
		}).catch(function () {
			setCropDraft(50, 50, 100);
			return false;
		}).then(function (result) {
			if (promoCropSmart) {
				promoCropSmart.disabled = false;
				promoCropSmart.textContent = label('cropSmart', 'Smart select');
			}
			return result;
		});
	}

	function pointerSourcePosition(event) {
		var sourceRect = renderedSourceRect();
		if (!sourceRect || !promoCropViewport) { return null; }
		var viewportRect = promoCropViewport.getBoundingClientRect();
		var x = event.clientX - viewportRect.left - sourceRect.left;
		var y = event.clientY - viewportRect.top - sourceRect.top;
		return {
			x: Math.max(0, Math.min(cropState.width, x / sourceRect.width * cropState.width)),
			y: Math.max(0, Math.min(cropState.height, y / sourceRect.height * cropState.height))
		};
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
			if (mediaButton) { event.preventDefault(); openMedia(mediaButton); return; }

			var cropApply = event.target.closest('[data-gloskin-promo-crop-apply]');
			if (cropApply) { event.preventDefault(); applyPromoCrop(); return; }

			var cropReset = event.target.closest('[data-gloskin-promo-crop-reset]');
			if (cropReset) { event.preventDefault(); resetPromoCrop(); return; }

			var removeMedia = event.target.closest('[data-gloskin-editorial-media-remove]');
			if (removeMedia) {
				event.preventDefault();
				removeSelectedMedia();
				return;
			}

			var toggle = event.target.closest('[data-gloskin-editorial-toggle]');
			if (toggle) { event.preventDefault(); toggleActive(toggle); }
		});
	}

	function bindCropInteraction() {
		if (!isPromo || !promoCropViewport) { return; }
		ensureCropUi();
		promoCropViewport.addEventListener('pointerdown', function (event) {
			if (!hasSelectedImage()) { return; }
			var position = pointerSourcePosition(event);
			if (!position) { return; }
			var handle = event.target.closest('[data-gloskin-crop-handle]');
			var insideSelection = !!event.target.closest('[data-gloskin-promo-crop-selection]');
			cropState.dragging = true;
			cropState.pointerId = event.pointerId;
			cropState.mode = handle ? 'resize' : 'move';
			cropState.startX = position.x;
			cropState.startY = position.y;
			cropState.startFocusX = cropState.draftX;
			cropState.startFocusY = cropState.draftY;
			cropState.startZoom = cropState.draftZoom;
			if (!insideSelection) {
				setCropDraft(position.x / cropState.width * 100, position.y / cropState.height * 100, cropState.draftZoom);
				cropState.startFocusX = cropState.draftX;
				cropState.startFocusY = cropState.draftY;
				cropState.startX = position.x;
				cropState.startY = position.y;
			}
			if (typeof promoCropViewport.setPointerCapture === 'function') {
				promoCropViewport.setPointerCapture(event.pointerId);
			}
			event.preventDefault();
		});
		promoCropViewport.addEventListener('pointermove', function (event) {
			if (!cropState.dragging || cropState.pointerId !== event.pointerId) { return; }
			var position = pointerSourcePosition(event);
			if (!position) { return; }
			if (cropState.mode === 'resize') {
				var geometry = cropGeometry(cropState.startZoom, cropState.startFocusX, cropState.startFocusY);
				var dx = Math.abs(position.x - geometry.centerX) * 2;
				var dy = Math.abs(position.y - geometry.centerY) * 2;
				var desiredWidth = Math.max(PROMO_MIN_WIDTH, dx, dy * PROMO_RATIO);
				var base = baseCropSize();
				desiredWidth = Math.min(base.width, desiredWidth);
				var nextZoom = base.width / Math.max(1, desiredWidth) * 100;
				setCropDraft(cropState.startFocusX, cropState.startFocusY, nextZoom);
			} else {
				var deltaX = (position.x - cropState.startX) / cropState.width * 100;
				var deltaY = (position.y - cropState.startY) / cropState.height * 100;
				setCropDraft(cropState.startFocusX + deltaX, cropState.startFocusY + deltaY, cropState.startZoom);
			}
			event.preventDefault();
		});
		function finishCropPointer(event) {
			if (cropState.pointerId !== null && event.pointerId !== cropState.pointerId) { return; }
			cropState.dragging = false;
			cropState.pointerId = null;
			cropState.mode = '';
			refreshCropStateUi();
		}
		promoCropViewport.addEventListener('pointerup', finishCropPointer);
		promoCropViewport.addEventListener('pointercancel', finishCropPointer);

		function cropKeyboard(event) {
			if (!hasSelectedImage()) { return; }
			if (/^Arrow(Left|Right|Up|Down)$/.test(event.key)) {
				event.preventDefault();
				var step = event.shiftKey ? 5 : 1;
				var x = cropState.draftX;
				var y = cropState.draftY;
				if (event.key === 'ArrowLeft') { x -= step; }
				if (event.key === 'ArrowRight') { x += step; }
				if (event.key === 'ArrowUp') { y -= step; }
				if (event.key === 'ArrowDown') { y += step; }
				setCropDraft(x, y, cropState.draftZoom);
				return;
			}
			if (event.key === '+' || event.key === '=' || event.key === 'PageUp') {
				event.preventDefault();
				setCropDraft(cropState.draftX, cropState.draftY, cropState.draftZoom + (event.shiftKey ? 20 : 5));
			}
			if (event.key === '-' || event.key === '_' || event.key === 'PageDown') {
				event.preventDefault();
				setCropDraft(cropState.draftX, cropState.draftY, cropState.draftZoom - (event.shiftKey ? 20 : 5));
			}
		}
		promoCropViewport.addEventListener('keydown', cropKeyboard);
		if (promoCropSelection) { promoCropSelection.addEventListener('keydown', cropKeyboard); }
		window.addEventListener('resize', syncCropPreviewPosition);
	}

	/* ONE safe media-selection accessor. Guards both state existence and
	 * the get method before the frame has entered its open lifecycle. */
	function getMediaSelection() {
		if (!mediaFrame || typeof mediaFrame.state !== 'function') { return null; }
		var state = mediaFrame.state();
		if (!state || typeof state.get !== 'function') { return null; }
		return state.get('selection') || null;
	}

	function resetMediaSelection() {
		if (!mediaFrame || !form) { return; }
		var selection = getMediaSelection();
		if (!selection) { return; }
		selection.reset();
		var currentId = parseInt(form.elements.image_id ? form.elements.image_id.value : '0', 10) || 0;
		if (currentId && window.wp && wp.media && wp.media.attachment) {
			selection.add(wp.media.attachment(currentId));
		}
	}

	function openMedia(trigger) {
		if (!form) { return false; }
		if (!window.wp || typeof wp.media !== 'function') {
			setStatus(label('mediaUnavailable', 'Media Library could not be initialized. Refresh this page and try again.'), true);
			return false;
		}
		mediaTrigger = trigger || null;
		if (!mediaFrame) {
			mediaFrame = wp.media({
				title: 'Choose image',
				button: { text: 'Use image' },
				library: { type: 'image' },
				multiple: false
			});
			/* Reset/preselection runs inside the open event — at this point the
			 * WordPress frame owns an active state and getMediaSelection() is safe. */
			mediaFrame.on('open', function () {
				mediaFrameActive = true;
				resetMediaSelection();
			});
			mediaFrame.on('close', function () {
				mediaFrameActive = false;
				if (mediaTrigger && typeof mediaTrigger.focus === 'function') { mediaTrigger.focus(); }
				mediaTrigger = null;
			});
			mediaFrame.on('select', function () {
				var selection = getMediaSelection();
				if (!selection || typeof selection.first !== 'function') { return; }
				var selected = selection.first();
				if (!selected) { return; }
				var attachment = selected.toJSON();
				setField('image_id', attachment.id || 0);
				if (isPromo) {
					setField('focus_x', 50);
					setField('focus_y', 50);
					setField('crop_zoom', 100);
					setPreview({
						image_url: attachment.url || '',
						crop_image_url: attachment.url || '',
						image_width: attachment.width || 0,
						image_height: attachment.height || 0,
						focus_x: 50,
						focus_y: 50,
						zoom: 100,
						crop_dirty: true,
						crop_replacement: true,
						crop_auto_select: true
					});
					formError('');
				} else {
					var source = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
					setPreview({ image_url: source || '' });
				}
			});
		}
		mediaFrame.open();
		return true;
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
		if (record.trash_url) {
			var trashAction = document.createElement('span');
			trashAction.className = 'trash';
			var trashLink = document.createElement('a');
			trashLink.className = 'submitdelete';
			trashLink.href = record.trash_url;
			trashLink.textContent = 'Trash';
			trashAction.appendChild(trashLink);
			actions.appendChild(trashAction);
		}
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
			if (isPromo && hasSelectedImage() && cropState.dirty) {
				formError(label('cropApplyRequired', 'Use Crop & Apply before saving this Promo.'));
				return;
			}
			if (isPromo && cropState.replacement && cropIsLowResolution()) {
				formError(label('cropLowResolution', 'Image is below the required 1648 × 928 production size. Choose a larger source image.'));
				return;
			}
			var data = new FormData(form);
			var payload = {};
			data.forEach(function (value, key) { payload[key] = value; });
			payload.active = form.elements.active && form.elements.active.checked ? '1' : '0';
			if (save) { save.disabled = true; save.textContent = label('saving', 'Saving…'); }
			ajax('gloskin_editorial_save', payload).then(function (response) {
				if (!response || !response.success) { throw new Error(responseMessage(response, label('error', 'Could not save this record.'))); }
				var record = response.data && response.data.record ? response.data.record : null;
				if (!record || !record.id) { throw new Error(label('error', 'Could not save this record.')); }
				records[String(record.id)] = record;
				setField('post_id', record.id);
				if (!reconcileRecord(record)) {
					setStatus(label('saveListFailed', 'Saved, but the native list could not be updated in place. Refresh the list manually if needed.'), true);
				} else {
					setStatus(label('saved', 'Saved.'), false);
				}
				if (save) { save.disabled = false; save.textContent = 'Save'; }
				closeModal();
			}).catch(function (requestError) {
				formError(requestError.message || label('error', 'Could not save this record.'));
				if (save) { save.disabled = false; save.textContent = 'Save'; }
			});
		});
	}

	function toggleActive(button) {
		var next = button.getAttribute('data-active') === '1' ? '0' : '1';
		button.disabled = true;
		ajax('gloskin_editorial_toggle', { post_id: button.getAttribute('data-id'), active: next }).then(function (response) {
			if (!response || !response.success) { throw new Error(responseMessage(response, label('activeFailed', 'Active state could not be updated.'))); }
			var active = !!response.data.active;
			var id = String(button.getAttribute('data-id') || '');
			button.setAttribute('data-active', active ? '1' : '0');
			button.setAttribute('aria-pressed', active ? 'true' : 'false');
			button.classList.toggle('is-active', active);
			button.textContent = active ? 'Active' : 'Inactive';
			button.disabled = false;
			if (records[id]) { records[id].active = active; }
			setStatus(label('activeUpdated', 'Active state updated.'), false);
		}).catch(function (requestError) {
			button.disabled = false;
			setStatus(requestError.message || label('activeFailed', 'Active state could not be updated.'), true);
		});
	}

	function currentRowIds(table) {
		return table.children('tr[id^="post-"]').map(function () { return this.id.replace('post-', ''); }).get();
	}

	function restoreRowOrder(ids) {
		var body = document.getElementById('the-list');
		if (!body) { return; }
		ids.forEach(function (id) {
			var row = document.getElementById('post-' + id);
			if (row) { body.appendChild(row); }
		});
	}

	function applyReorderAvailability() {
		var enabled = !!config.canReorder;
		document.querySelectorAll('.gloskin-editorial-order-handle').forEach(function (handle) {
			if (enabled) {
				handle.removeAttribute('aria-disabled');
				handle.title = 'Drag to reorder';
			} else {
				handle.setAttribute('aria-disabled', 'true');
				handle.title = label('reorderHint', 'Clear filters to reorder items.');
			}
		});
	}

	function initSortable() {
		var table = $('#the-list');
		applyReorderAvailability();
		if (!table.length || !$.fn.sortable) { return; }
		if (!config.canReorder) {
			if (table.hasClass('ui-sortable')) { table.sortable('destroy'); }
			return;
		}
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
			start: function () {
				sortableSnapshot = currentRowIds(table);
			},
			update: function () {
				var ids = currentRowIds(table);
				table.addClass('is-gloskin-saving-order');
				ajax('gloskin_editorial_reorder', { post_type: config.postType, ids: ids }).then(function (response) {
					if (!response || !response.success || !response.data || Number(response.data.ordered) !== ids.length) {
						throw new Error(responseMessage(response, label('reorderFailed', 'Order could not be saved.')));
					}
					ids.forEach(function (id, index) { if (records[String(id)]) { records[String(id)].order = index + 1; } });
					table.removeClass('is-gloskin-saving-order');
					setStatus(label('reorderSaved', 'Order saved.'), false);
				}).catch(function (requestError) {
					restoreRowOrder(sortableSnapshot);
					table.removeClass('is-gloskin-saving-order');
					setStatus(requestError.message || label('reorderFailed', 'Order could not be saved.'), true);
				});
			}
		});
	}

	function refreshSortable() {
		initSortable();
	}

	function focusableElements() {
		if (!modal) { return []; }
		var dialog = modal.querySelector('.gloskin-editorial-modal__dialog');
		if (!dialog) { return []; }
		return Array.prototype.slice.call(dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter(function (element) {
			return element.getAttribute('aria-hidden') !== 'true' && element.offsetParent !== null;
		});
	}

	function bindKeyboard() {
		document.addEventListener('keydown', function (event) {
			if (!modal || modal.hidden) { return; }
			if (mediaFrameActive) { return; }
			if (event.key === 'Escape') {
				event.preventDefault();
				closeModal();
				return;
			}
			if (event.key !== 'Tab') { return; }
			var focusables = focusableElements();
			if (!focusables.length) { event.preventDefault(); return; }
			var first = focusables[0];
			var last = focusables[focusables.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});
	}

	interceptLinks();
	bindCropInteraction();
	bindForm();
	bindKeyboard();
	initSortable();
	if (config.editId) { openModal(parseInt(config.editId, 10)); }
	else if (config.addId) { openModal(0); }
})(jQuery);
