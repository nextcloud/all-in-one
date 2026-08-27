(function() {
	'use strict';

	function readInitialState(key) {
		var el = document.getElementById('initial-state-nc_aio_tools-' + key);
		if (!el) {
			return null;
		}
		try {
			return JSON.parse(atob(el.value));
		} catch (e) {
			return null;
		}
	}

	function label() {
		// The unlimited case travels as its own boolean -- getFreeSlots() is
		// always a scalar on the PHP side, and its unlimited value (PHP_INT_MAX)
		// is not representable in JS, so never infer the state from the count.
		if (readInitialState('unlimited') !== false) {
			return 'Unlimited users';
		}
		var freeUsers = readInitialState('freeUsers');
		if (typeof freeUsers !== 'number') {
			return 'Unlimited users';
		}
		if (freeUsers === 1) {
			return '1 free user slot remaining';
		}
		return freeUsers + ' free user slots remaining';
	}

	document.addEventListener('DOMContentLoaded', function() {
		var badge = document.createElement('div');
		badge.id = 'nc-aio-tools-free-count';
		badge.textContent = label();
		document.body.appendChild(badge);
	});
})();
