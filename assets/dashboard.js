(function () {
	'use strict';

	var filters = document.querySelectorAll('.pysb-filter');
	var groups = document.querySelectorAll('.pysb-group');
	var empty = document.querySelector('[data-empty-filter]');

	if (!filters.length) {
		return;
	}

	function applyFilter(value) {
		var visible = 0;

		groups.forEach(function (group) {
			var rows = group.querySelectorAll('.pysb-row');
			var groupVisible = 0;

			rows.forEach(function (row) {
				var show = value === 'all' || row.getAttribute('data-action') === value;
				row.hidden = !show;
				if (show) {
					groupVisible += 1;
				}
			});

			group.hidden = groupVisible === 0;
			if (groupVisible) {
				visible += 1;
			}
		});

		if (empty) {
			empty.classList.toggle('is-hidden', visible > 0 || groups.length === 0);
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
