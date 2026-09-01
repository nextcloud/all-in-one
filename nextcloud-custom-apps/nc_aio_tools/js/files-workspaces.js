/**
 * Adds screen 1c's "Workspaces" section to the Files navigation sidebar --
 * Finance / Compliance / Admissions, each a coloured dot plus a label.
 *
 * There is no Nextcloud concept behind these: no workspace, group folder or
 * saved view named "Finance" etc. exists. This is a static, visual-only
 * addition matching the mockup's copy, same as dashboard-panels.js's grid --
 * wiring it to real group folders / saved views is its own follow-up.
 *
 * WHY A MUTATIONOBSERVER: #app-navigation-vue's child list re-renders on
 * route change (All files / Recent / Favorites / ...), which can wipe an
 * inserted sibling the Vue vdom does not know about. Re-checking on every
 * mutation and re-inserting if missing survives that, at the cost of the
 * mount function being idempotent (guarded by whether .bs-workspaces is
 * already present).
 */
(function () {
	'use strict'

	var nav = document.getElementById('app-navigation-vue')
	if (!nav) {
		return
	}

	var WORKSPACES = [
		{ name: 'Finance', color: 'var(--bs-saffron)' },
		{ name: 'Compliance', color: 'var(--bs-green)' },
		{ name: 'Admissions', color: 'var(--bs-blue-500)' },
	]

	function buildBlock() {
		var block = document.createElement('div')
		block.className = 'bs-workspaces'

		var label = document.createElement('div')
		// .bs-dash-mlab, not a bare .mlab -- see bharatsuite.css's "The
		// micro-label" section: there is no standalone .mlab utility class,
		// only a fixed list of selectors that get its rules, and
		// .bs-dash-mlab is the one already on that list.
		label.className = 'bs-dash-mlab bs-workspaces__label'
		label.textContent = 'Workspaces'
		block.appendChild(label)

		var list = document.createElement('ul')
		list.className = 'bs-workspaces__list'
		WORKSPACES.forEach(function (ws) {
			var item = document.createElement('li')
			item.className = 'bs-workspaces__item'
			item.innerHTML =
				'<span class="bs-workspaces__dot" style="background:' + ws.color + '"></span>' +
				'<span class="bs-workspaces__name">' + ws.name + '</span>'
			list.appendChild(item)
		})
		block.appendChild(list)

		return block
	}

	function mount() {
		// Only the Files app's own nav has this body/settings pairing --
		// Settings, Contacts etc. reuse #app-navigation-vue with a different
		// internal structure, so this doubles as the "is this Files" guard.
		var body = nav.querySelector(':scope > .app-navigation__body')
		var settings = nav.querySelector(':scope > .app-navigation-entry__settings')
		if (!body || !settings) {
			return false
		}

		if (!nav.querySelector(':scope > .bs-workspaces')) {
			nav.insertBefore(buildBlock(), settings)
		}

		return true
	}

	if (!mount()) {
		var observer = new MutationObserver(function () {
			mount()
		})
		observer.observe(nav, { childList: true, subtree: true })
	} else {
		// Still watch after a successful first mount -- route changes re-render
		// the child list and can drop the block, so this has to keep re-adding it
		// rather than disconnecting like dashboard-panels.js's one-shot mount.
		new MutationObserver(function () {
			mount()
		}).observe(nav, { childList: true })
	}
})()
