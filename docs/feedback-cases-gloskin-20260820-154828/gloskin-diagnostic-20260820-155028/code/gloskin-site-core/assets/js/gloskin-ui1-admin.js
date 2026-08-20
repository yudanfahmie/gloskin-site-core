(function () {
	'use strict';

	function idsFromSelection(selection) {
		return selection.map(function (attachment) {
			return attachment.id;
		}).filter(Boolean);
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-gloskin-media-picker]');
		if (!button || !window.wp || !wp.media) {
			return;
		}

		event.preventDefault();
		var target = document.querySelector(button.getAttribute('data-target'));
		if (!target) {
			return;
		}

		var multiple = button.getAttribute('data-multiple') === 'true';
		var frame = wp.media({
			title: button.getAttribute('data-title') || 'Choose media',
			button: { text: button.getAttribute('data-button') || 'Use media' },
			library: { type: 'image' },
			multiple: multiple
		});

		frame.on('select', function () {
			var selection = idsFromSelection(frame.state().get('selection').toJSON());
			target.value = multiple ? selection.join(',') : (selection[0] || '');
			target.dispatchEvent(new Event('change', { bubbles: true }));
		});

		frame.open();
	});
}());
