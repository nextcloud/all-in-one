/**
 * Adds screen 1c's detail rail -- Owner / Region / Encryption / External links,
 * then an "Open activity log" button -- to the file sidebar, between its header
 * (name, preview, size/date/owner line) and its tab bar (Sharing/Chat/Activity/
 * Versions).
 *
 * Owner is real, read off the header's own user bubble -- everything else
 * (Region, Encryption, External links) is a BharatSuite concept with no
 * Nextcloud property behind it yet, so those three are static placeholder
 * text, same scoping as dashboard-panels.js's grid: matching the mockup's
 * layout and copy is the job here, wiring real residency/encryption/DLP
 * data is its own follow-up. Classification and per-file Residency are
 * deliberately NOT reproduced here (or anywhere in Files) -- scoped out.
 *
 * WHY A MUTATIONOBSERVER, and why it keeps observing after the first mount:
 * the sidebar is a single persistent Vue component that is shown/hidden and
 * re-populated per selected file rather than destroyed and recreated, so
 * there is no reliable one-shot "just mounted" moment to hook. Re-checking
 * on every mutation and re-inserting the rail if it is missing (e.g. Vue's
 * patch dropped the unmanaged sibling on a header re-render) is what
 * files-workspaces.js does for the same reason.
 */
(function () {
	'use strict'

	var sidebar = document.getElementById('app-sidebar-vue')
	if (!sidebar) {
		return
	}

	function activityTabButton() {
		return sidebar.querySelector('#tab-button-activity')
	}

	function ownerName() {
		var bubble = sidebar.querySelector('.app-sidebar-header__subname .user-bubble__name')
		return bubble ? bubble.textContent.trim() : 'You'
	}

	function buildRail() {
		var rail = document.createElement('div')
		rail.className = 'bs-detail-rail'

		var rows = [
			{ label: 'Owner', value: ownerName() },
			{ label: 'Region', value: 'ap-south · Mumbai' },
			{ label: 'Encryption', value: 'Org key · HSM' },
			{ label: 'External links', value: 'Blocked' },
		]

		var list = document.createElement('div')
		list.className = 'bs-detail-rail__list'
		rows.forEach(function (row) {
			var line = document.createElement('div')
			line.className = 'bs-detail-rail__row'
			line.innerHTML =
				'<span class="bs-detail-rail__label">' + row.label + '</span>' +
				'<span class="bs-detail-rail__value">' + row.value + '</span>'
			list.appendChild(line)
		})
		rail.appendChild(list)

		var button = document.createElement('button')
		button.type = 'button'
		button.className = 'bs-detail-rail__activity-btn'
		button.textContent = 'Open activity log'
		button.addEventListener('click', function () {
			var tab = activityTabButton()
			if (tab) {
				tab.click()
			}
		})
		rail.appendChild(button)

		return rail
	}

	function mount() {
		var header = sidebar.querySelector(':scope > .app-sidebar-header')
		var tabs = sidebar.querySelector(':scope > .app-sidebar-tabs')
		if (!header || !tabs) {
			return false
		}

		var existing = sidebar.querySelector(':scope > .bs-detail-rail')
		if (existing) {
			// Refresh the owner line in place: the sidebar can re-populate for a
			// newly selected file without dropping this element, and a stale owner
			// name would otherwise survive across selections.
			// Guarded by a value comparison, not just called unconditionally:
			// this runs from the same MutationObserver that fires ON textContent
			// writes (subtree:true covers the text node swap a .textContent
			// assignment does), so writing every time -- even the same string --
			// would retrigger the observer forever.
			var value = existing.querySelector('.bs-detail-rail__row .bs-detail-rail__value')
			var name = ownerName()
			if (value && value.textContent !== name) {
				value.textContent = name
			}
			return true
		}

		sidebar.insertBefore(buildRail(), tabs)
		return true
	}

	mount()
	new MutationObserver(function () {
		mount()
	}).observe(sidebar, { childList: true, subtree: true })

	// Belt-and-braces retries for the owner name specifically: the header
	// skeleton (name, preview) and the subname's owner bubble are not
	// guaranteed to land in the same mutation batch, and the very first
	// bubble insertion can be missed if it happens between mount()'s
	// synchronous call above and the observer's observe() call landing.
	// A couple of short delays catch that gap without needing to reason
	// about Vue's exact render timing.
	setTimeout(mount, 400)
	setTimeout(mount, 1200)
})()
