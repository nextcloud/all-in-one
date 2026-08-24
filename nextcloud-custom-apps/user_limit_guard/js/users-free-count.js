(function() {
	'use strict';

	function readFreeUsers() {
		var el = document.getElementById('initial-state-user_limit_guard-freeUsers');
		if (!el) {
			return null;
		}
		try {
			return JSON.parse(atob(el.value));
		} catch (e) {
			return null;
		}
	}

	function labelFor(freeUsers) {
		if (freeUsers === null) {
			return 'Unlimited users';
		}
		if (freeUsers === 1) {
			return '1 free user slot remaining';
		}
		return freeUsers + ' free user slots remaining';
	}

	document.addEventListener('DOMContentLoaded', function() {
		var badge = document.createElement('div');
		badge.id = 'user-limit-guard-free-count';
		badge.textContent = labelFor(readFreeUsers());
		document.body.appendChild(badge);
	});
})();
