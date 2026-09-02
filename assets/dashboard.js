(function () {
	'use strict';

	var filters = document.querySelectorAll('.pysb-filter');
	var rows = document.querySelectorAll('.pysb-row');
	var empty = document.querySelector('[data-empty-filter]');

	if (!filters.length) {
		return;
	}

	function applyFilter(value) {
		var visible = 0;

		rows.forEach(function (row) {
			var show = value === 'all' || row.getAttribute('data-action') === value;
			row.hidden = !show;
			if (show) {
				visible += 1;
			}
		});

		if (empty) {
			empty.classList.toggle('is-hidden', visible > 0 || rows.length === 0);
		}
	}

	filters.forEach(function (button) {
		button.addEventListener('click', function () {
			filters.forEach(function (item) {
				item.classList.toggle('is-active', item === button);
			});
			applyFilter(button.getAttribute('data-filter') || 'all');
		});
	});
})();
