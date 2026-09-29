(function () {
	var form = document.querySelector('[data-cseed-form]');

	if (!form) {
		return;
	}

	form.addEventListener('submit', function (event) {
		if (!window.confirm('Existing posts with matching slugs will be overwritten. Continue?')) {
			event.preventDefault();
		}
	});

	// Per-group "Select all / Deselect all" link, kept in sync with the checkboxes.
	form.querySelectorAll('[data-cseed-toggle]').forEach(function (button) {
		var boxes = button.closest('.cseed__group').querySelectorAll('input[type=checkbox]');

		function updateLabel() {
			var allChecked = Array.prototype.every.call(boxes, function (box) { return box.checked; });
			button.textContent = allChecked ? 'Deselect all' : 'Select all';
		}

		button.addEventListener('click', function () {
			var check = button.textContent === 'Select all';
			boxes.forEach(function (box) { box.checked = check; });
			updateLabel();
		});

		boxes.forEach(function (box) { box.addEventListener('change', updateLabel); });
	});
})();
