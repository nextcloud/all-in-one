/**
 * Replaces the stock Dashboard widget grid with screen 1b's static panel layout.
 *
 * WHY JAVASCRIPT, not another server-side <style> like the rest of this theme: the
 * six cards here (Needs you today, Sovereignty status, the storage split, ...) are
 * BharatSuite concepts with no Nextcloud dashboard widget behind them at all, so
 * there is no existing markup to re-skin -- real DOM has to be built. See
 * bharatsuite.css's "-- Dashboard --" section for the component styles this
 * targets, and its own comment for why the content is static for now rather than
 * wired to Mail/Calendar/Activity: matching the mockup's copy and layout was the
 * scoped task, live data is its own follow-up.
 *
 * WHY A MUTATIONOBSERVER: #app-dashboard exists in the initial page markup, but
 * its content -- the greeting <h2> and the .panels widget grid -- is rendered by
 * Vue after the bundle mounts, which has not necessarily happened yet by the time
 * this script runs. Polling on a timer would either fire too early (nothing to
 * find yet) or waste cycles after the real mount; observing childList changes on
 * the container fires exactly once, right when the content actually appears.
 */
(function () {
	'use strict'

	var root = document.getElementById('app-dashboard')
	if (!root) {
		return
	}

	// Verbatim from exports/png/1b-dashboard.png / BharatSuite UI.dc.html's
	// data-scr="1b". Every value that has a live Nextcloud source (the user's
	// name, real mail, real storage use) is called out in the memory this task
	// was scoped against as "still not built" -- this is the visual pass only.
	var GRID_HTML =
		'<div class="bs-dash-card">' +
		'<div class="bs-dash-card__head">' +
		'<div class="bs-dash-mlab">Needs you today</div>' +
		'<span class="bs-dash-count">4</span>' +
		'</div>' +
		'<div class="bs-dash-list">' +
		'<div class="bs-dash-task bs-dash-task--due">' +
		'<span class="bs-dash-avatar" style="background:var(--bs-saffron-400)"></span>' +
		'<div class="bs-dash-task__body">' +
		'<div class="bs-dash-task__title">Vendor agreement — Kolhapur unit</div>' +
		'<div class="bs-dash-task__meta">Awaiting your signature · due today</div>' +
		'</div>' +
		'<button type="button" class="bs-dash-btn bs-dash-btn--primary" style="padding:7px 14px;font-size:12px;min-height:auto">Sign</button>' +
		'</div>' +
		'<div class="bs-dash-task bs-dash-task--muted">' +
		'<span class="bs-dash-avatar" style="background:var(--bs-green-400)"></span>' +
		'<div class="bs-dash-task__body">' +
		'<div class="bs-dash-task__title">Q2 audit pack — 3 files missing</div>' +
		'<div class="bs-dash-task__meta">Finance workspace · Files</div>' +
		'</div>' +
		'<span class="bs-dash-tag">Review</span>' +
		'</div>' +
		'<div class="bs-dash-task bs-dash-task--muted">' +
		'<span class="bs-dash-avatar" style="background:var(--bs-green-400)"></span>' +
		'<div class="bs-dash-task__body">' +
		'<div class="bs-dash-task__title">Rahul shared “Lab intake forms”</div>' +
		'<div class="bs-dash-task__meta">Can edit · expires in 6 days</div>' +
		'</div>' +
		'<span class="bs-dash-tag">Open</span>' +
		'</div>' +
		'</div>' +
		'</div>' +
		'<div class="bs-dash-card">' +
		'<div class="bs-dash-mlab" style="margin-bottom:13px">Mail</div>' +
		'<div class="bs-dash-list" style="gap:13px">' +
		'<div class="bs-dash-mail-row">' +
		'<span class="bs-dash-avatar bs-dash-avatar--sm" style="background:var(--bs-green)"></span>' +
		'<div><div class="bs-dash-mail-row__name">Meera Iyer</div><div class="bs-dash-mail-row__subject">RBI circular — retention change</div></div>' +
		'</div>' +
		'<div class="bs-dash-mail-row">' +
		'<span class="bs-dash-avatar bs-dash-avatar--sm" style="background:var(--bs-green)"></span>' +
		'<div><div class="bs-dash-mail-row__name">Admissions desk</div><div class="bs-dash-mail-row__subject">Batch 2026 document checklist</div></div>' +
		'</div>' +
		'<div class="bs-dash-mail-row">' +
		'<span class="bs-dash-avatar bs-dash-avatar--sm" style="background:var(--bs-saffron)"></span>' +
		'<div><div class="bs-dash-mail-row__name">Payroll</div><div class="bs-dash-mail-row__subject">August payslips ready to release</div></div>' +
		'</div>' +
		'</div>' +
		'<div class="bs-dash-mail-summary">14 unread · 2 flagged</div>' +
		'</div>' +
		'<div class="bs-dash-card--sovereignty">' +
		'<div class="bs-dash-mlab bs-dash-mlab--on-dark" style="margin-bottom:13px">Sovereignty status</div>' +
		'<div class="bs-dash-figure">100%</div>' +
		'<div class="bs-dash-sub">of your organisation’s data resident in Indian regions</div>' +
		'<div class="bs-dash-stat-list">' +
		'<div class="bs-dash-stat"><span class="bs-dash-stat__label">Primary</span><span>Mumbai · ap-south-1</span></div>' +
		'<div class="bs-dash-stat"><span class="bs-dash-stat__label">Replica</span><span>Hyderabad</span></div>' +
		'<div class="bs-dash-stat"><span class="bs-dash-stat__label">External transfers</span><span class="bs-dash-stat__value--strong">None (30 days)</span></div>' +
		'</div>' +
		'</div>' +
		'<div class="bs-dash-card">' +
		'<div class="bs-dash-mlab" style="margin-bottom:13px">Storage</div>' +
		'<div class="bs-dash-storage-figure">' +
		'<span class="bs-dash-storage-figure__value">412 GB</span>' +
		'<span class="bs-dash-storage-figure__of">of 1 TB</span>' +
		'</div>' +
		'<div class="bs-dash-storage-bar">' +
		'<span style="width:26%;background:var(--bs-green)"></span>' +
		'<span style="width:9%;background:var(--bs-saffron)"></span>' +
		'<span style="width:6%;background:var(--bs-blue-500)"></span>' +
		'</div>' +
		'<div class="bs-dash-storage-legend"><span>Files</span><span>Photos</span><span>Mail</span></div>' +
		'</div>' +
		'<div class="bs-dash-card">' +
		'<div class="bs-dash-mlab" style="margin-bottom:13px">Today</div>' +
		'<div class="bs-dash-agenda">' +
		'<div class="bs-dash-agenda-row"><span class="bs-dash-agenda-row__time">10:30</span><span class="bs-dash-agenda-row__title">Compliance stand-up</span></div>' +
		'<div class="bs-dash-agenda-row"><span class="bs-dash-agenda-row__time">13:00</span><span class="bs-dash-agenda-row__title">Vendor call — Sahyadri Foods</span></div>' +
		'<div class="bs-dash-agenda-row"><span class="bs-dash-agenda-row__time">16:15</span><span class="bs-dash-agenda-row__title">Retention policy review</span></div>' +
		'</div>' +
		'</div>' +
		'<div class="bs-dash-card--mint">' +
		'<div class="bs-dash-mlab bs-dash-mlab--on-mint" style="margin-bottom:13px">Recent activity</div>' +
		'<div class="bs-dash-activity">' +
		'<div><strong>Priya</strong> edited <em>Fee structure 2026.ods</em> · 12m</div>' +
		'<div><strong>You</strong> restored 4 files from Trash · 1h</div>' +
		'<div><strong>Devansh</strong> revoked an external link · 3h</div>' +
		'</div>' +
		'</div>'

	function filesUrl() {
		try {
			if (window.OC && typeof window.OC.generateUrl === 'function') {
				return window.OC.generateUrl('/apps/files')
			}
		} catch (e) {
			// fall through to the static path below
		}
		return '/index.php/apps/files/'
	}

	function mount() {
		if (root.classList.contains('bs-dash-mounted')) {
			return true
		}

		var panels = root.querySelector(':scope > .panels')
		var heading = root.querySelector(':scope > h2:first-of-type')
		if (!panels || !heading) {
			return false
		}

		root.classList.add('bs-dash-mounted')

		// Screen 1b's eyebrow: "Tuesday, 22 August", always today's real date
		// rather than the mockup's fixed one -- a static string here would go
		// visibly stale the day after this ships.
		// Built from separate weekday/day/month formatters and joined by hand,
		// rather than one call with all three options: en-GB's combined
		// weekday+day+month pattern drops the comma the mockup's eyebrow has
		// ("Tuesday, 22 August"), where the weekday-only and day+month
		// patterns each keep their own punctuation reliably.
		var today = new Date()
		var weekday = today.toLocaleDateString('en-GB', { weekday: 'long' })
		var dayMonth = today.toLocaleDateString('en-GB', { day: 'numeric', month: 'long' })
		var eyebrow = document.createElement('div')
		eyebrow.className = 'bs-dash-mlab bs-dash-eyebrow'
		eyebrow.textContent = weekday + ', ' + dayMonth

		// heading is the real, already-dynamic "Good morning/afternoon/evening"
		// Vue renders -- moved, not replaced, so its own time-of-day logic and
		// any future core changes to it keep working.
		var titles = document.createElement('div')
		titles.appendChild(eyebrow)
		heading.parentNode.insertBefore(titles, heading)
		titles.appendChild(heading)
		titles.className = 'bs-dash-titles'

		var actions = document.createElement('div')
		actions.className = 'bs-dash-actions'
		actions.innerHTML =
			'<a class="bs-dash-btn bs-dash-btn--secondary" href="' + filesUrl() + '">Upload</a>' +
			'<a class="bs-dash-btn bs-dash-btn--primary" href="' + filesUrl() + '">New document</a>'

		var header = document.createElement('div')
		header.className = 'bs-dash-header'
		titles.parentNode.insertBefore(header, titles)
		header.appendChild(titles)
		header.appendChild(actions)

		var grid = document.createElement('div')
		grid.className = 'bs-dash-grid'
		grid.innerHTML = GRID_HTML
		panels.parentNode.insertBefore(grid, panels)

		return true
	}

	if (mount()) {
		return
	}

	var observer = new MutationObserver(function () {
		if (mount()) {
			observer.disconnect()
		}
	})
	observer.observe(root, { childList: true, subtree: true })
})()
