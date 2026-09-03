(function () {
	'use strict';

	if (window.location.hash === '#pysb-activity') {
		var feed = document.getElementById('pysb-activity');
		if (feed && feed.scrollIntoView) {
			feed.scrollIntoView({ block: 'start' });
		}
	}
})();
