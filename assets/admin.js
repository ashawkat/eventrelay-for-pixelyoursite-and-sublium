(function () {
	'use strict';

	function unlockCheckbox(name, shouldCheck) {
		var input = document.querySelector('input[type="checkbox"][name="' + name + '"]');
		if (!input) {
			return false;
		}

		input.disabled = false;
		input.removeAttribute('disabled');

		if (typeof shouldCheck === 'boolean') {
			input.checked = shouldCheck;
		}

		return true;
	}

	function addNotice(anchor) {
		if (!anchor || !window.pysSubliumBridge || !window.pysSubliumBridge.notice) {
			return;
		}

		if (document.querySelector('.eventrelay-note')) {
			return;
		}

		var note = document.createElement('p');
		note.className = 'text-gray eventrelay-note';
		note.textContent = window.pysSubliumBridge.notice;
		anchor.parentNode.insertBefore(note, anchor.nextSibling);
	}

	function markFormUnlocked(input) {
		var form = input.closest('form');
		if (!form || form.querySelector('input[name="eventrelay_ui"]')) {
			return;
		}

		var hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'eventrelay_ui';
		hidden.value = '1';
		form.appendChild(hidden);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var config = window.pysSubliumBridge || {};
		var autoUnlocked = unlockCheckbox(
			'pys[core][woo_purchase_dont_track_auto_renewal]',
			config.excludeAuto
		);
		var manualUnlocked = unlockCheckbox(
			'pys[core][woo_purchase_dont_track_manual_renewal]',
			config.excludeManual
		);

		unlockCheckbox('pys[core][woo_subscriptions_dont_track_auto_renewal]');
		unlockCheckbox('pys[core][woo_subscriptions_dont_track_manual_renewal]');

		var autoInput = document.querySelector(
			'input[type="checkbox"][name="pys[core][woo_purchase_dont_track_auto_renewal]"]'
		);

		if (autoUnlocked && autoInput) {
			addNotice(autoInput.closest('.small-checkbox') || autoInput);
			markFormUnlocked(autoInput);
		}

		if (manualUnlocked && autoInput) {
			markFormUnlocked(autoInput);
		}
	});
})();
