/**
 * Reskins the standalone /apps/assistant/ page (the "AI" nav entry added in
 * Application.php::boot) to match screen 2h -- an eyebrow + title + policy
 * badges above the thread, a MODEL section and privacy note in the sidebar,
 * right-aligned peach bubbles for the user's own turns, and a Send button next
 * to the existing mic control. See bharatsuite.css's "-- Assistant (standalone
 * chat page) --" section for the paired styles.
 *
 * NOT the header-icon modal -- that is assistant-fullscreen.js, a different
 * surface (a PrimeVue dialog teleported into #assistantTextProcessingModal)
 * with no shared DOM with this page's own Vue app.
 *
 * WHY EVERYTHING LIVES BEHIND ONE OBSERVER: switching task type (Chat with AI /
 * Work with text / Context Chat / ...) swaps .task-input-output-form's content
 * for an unrelated form, and opening or creating a conversation re-renders
 * .session-area and .convo-box independently. Re-running every mount function
 * on every mutation, each guarded by its own idempotency check, survives all of
 * that without tracking which specific change happened.
 *
 * The MODEL section and privacy note are static, visual-only additions with no
 * Nextcloud concept behind them -- same as files-workspaces.js's Workspaces
 * list: there is no model router, and "External models" blocks nothing. The
 * header's "In-country inference" badge is a different kind of claim, though,
 * and shown unconditionally rather than built to be toggled off: this fork's
 * Assistant has only ever been wired to the local Ollama container (see
 * ARCHITECTURE.md), so it is a true, permanent fact about the deployment, not a
 * per-conversation measurement. "Grounded in files" is NOT given the same
 * treatment: the mockup's copy names a specific file count for one specific
 * answer, which this build has no way to compute, so rather than invent a
 * number this shows the label unnumbered, and only while Context Chat -- the
 * one mode that is inherently file-grounded -- is the active task type.
 */
(function () {
	'use strict'

	var ICON_SHIELD =
		'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">' +
		'<circle cx="12" cy="12" r="9"/>' +
		'<path d="M8 12.5l2.5 2.5L16 9.5" stroke-linecap="round" stroke-linejoin="round"/>' +
		'</svg>'

	var ICON_LINK =
		'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">' +
		'<circle cx="9" cy="9" r="4"/>' +
		'<circle cx="15" cy="15" r="4"/>' +
		'<line x1="10.5" y1="10.5" x2="13.5" y2="13.5"/>' +
		'</svg>'

	// Screen 2h gives every history entry a clock face -- Nextcloud's own
	// conversation list renders that slot (.app-navigation-entry-icon) empty.
	var ICON_CLOCK =
		'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">' +
		'<circle cx="12" cy="12" r="9"/>' +
		'<polyline points="12 7 12 12 15 14" stroke-linecap="round" stroke-linejoin="round"/>' +
		'</svg>'

	function activeTaskType() {
		var btn = document.querySelector('.assistant-wrapper .task-type-select .categorySelected')
		return btn ? (btn.getAttribute('title') || '').trim() : ''
	}

	function currentDisplayName() {
		try {
			return (window.OC && OC.getCurrentUser && OC.getCurrentUser().displayName) || ''
		} catch (e) {
			return ''
		}
	}

	// -- Sidebar: MODEL section + privacy note -------------------------------

	var MODEL_HTML =
		'<div class="bs-dash-mlab bs-assistant-model__label">Model</div>' +
		'<div class="bs-assistant-model__card">' +
		'<span class="bs-assistant-model__icon">' + ICON_SHIELD + '</span>' +
		'<span class="bs-assistant-model__name">On-premise &middot; Mumbai</span>' +
		'<span class="bs-assistant-model__badge bs-assistant-model__badge--active">Active</span>' +
		'</div>' +
		'<div class="bs-assistant-model__card">' +
		'<span class="bs-assistant-model__icon">' + ICON_LINK + '</span>' +
		'<span class="bs-assistant-model__name">External models</span>' +
		'<span class="bs-assistant-model__badge bs-assistant-model__badge--blocked">Blocked</span>' +
		'</div>'

	// "New conversation" is Nextcloud's own copy; screen 2h's sidebar button reads
	// "New chat". Checked before writing, unlike the other mount functions' own
	// idempotency guards -- textContent has no natural "already done" marker to
	// query for, and writing it unconditionally on every observer tick (even back
	// to the same string) still replaces the text node, which the observer sees
	// as a mutation and reruns mountAll() for -- an infinite MutationObserver
	// loop that pegs the tab's CPU.
	function mountNewChatLabel() {
		var label = document.querySelector('.assistant-wrapper .app-navigation-new .button-vue__text')
		if (label && label.textContent !== ' New chat') {
			label.textContent = ' New chat'
		}
	}

	// Each history entry's icon slot (.app-navigation-entry-icon) renders empty;
	// screen 2h gives every one a clock face. :empty is the idempotency guard --
	// once filled it no longer matches, and a Vue re-render always starts a
	// replacement entry back at empty, so this self-corrects on every mutation
	// without a dataset flag.
	function mountHistoryIcons() {
		var icons = document.querySelectorAll('.assistant-wrapper .app-navigation-entry-icon:empty')
		icons.forEach(function (icon) {
			icon.innerHTML = ICON_CLOCK
		})
	}

	function mountSidebar() {
		var list = document.querySelector('.assistant-wrapper .app-navigation-list')
		if (!list || list.querySelector(':scope > .bs-assistant-model')) {
			return
		}

		var section = document.createElement('div')
		section.className = 'bs-assistant-model'
		section.innerHTML = MODEL_HTML
		list.appendChild(section)

		var note = document.createElement('div')
		note.className = 'bs-assistant-privacy'
		note.innerHTML = '<strong>Prompts and documents stay inside your tenancy.</strong> Nothing is used for training.'
		list.appendChild(note)
	}

	// -- Header: eyebrow + policy badges -------------------------------------

	function mountHeader() {
		var titleBox = document.querySelector('.assistant-wrapper .session-area__top-bar__title')
		var topBar = document.querySelector('.assistant-wrapper .session-area__top-bar')
		if (!titleBox || !topBar) {
			return
		}

		if (!titleBox.querySelector(':scope > .bs-assistant-eyebrow')) {
			var eyebrow = document.createElement('div')
			eyebrow.className = 'bs-assistant-eyebrow'
			eyebrow.textContent = 'Assistant · On-premise'
			titleBox.insertBefore(eyebrow, titleBox.firstChild)
		}

		if (!topBar.querySelector(':scope > .bs-assistant-badges')) {
			var badges = document.createElement('div')
			badges.className = 'bs-assistant-badges'
			badges.innerHTML =
				'<span class="bs-assistant-badge bs-assistant-badge--residency">In-country inference</span>' +
				'<span class="bs-assistant-badge bs-assistant-badge--grounded" hidden>Grounded in files</span>'
			topBar.appendChild(badges)
		}

		var grounded = topBar.querySelector('.bs-assistant-badge--grounded')
		if (grounded) {
			grounded.hidden = activeTaskType() !== 'Context Chat'
		}
	}

	// -- Messages: classify own vs. assistant turns for the bubble styling ---

	function mountMessages() {
		var name = currentDisplayName()
		var rows = document.querySelectorAll(
			'.assistant-wrapper .message:not(.bs-msg-user):not(.bs-msg-assistant)'
		)
		rows.forEach(function (row) {
			var nameEl = row.querySelector('.message__header__role__name')
			var isOwn = !!name && !!nameEl && nameEl.textContent.trim() === name
			row.classList.add(isOwn ? 'bs-msg-user' : 'bs-msg-assistant')
		})
	}

	// -- Input: placeholder copy, and a Send button next to the mic control --
	//
	// Drives the input's own Enter-to-send handler rather than reimplementing
	// submission -- same reasoning as assistant-fullscreen.js's maximise click:
	// the real code path already knows how to send, including whatever
	// session-creation / task-type logic that involves.

	var PLACEHOLDER_TEXT = 'Ask about your documents…'

	// NcRichContenteditable shows its placeholder via
	// .rich-contenteditable__input--empty::before{content:attr(aria-placeholder)}
	// -- there is no separate text node to overwrite, just this attribute
	// (mirrored onto aria-label for the same accessible name). Compared before
	// writing and re-run on every mutation like mountNewChatLabel above: typing
	// and clearing the field toggles the --empty class, which is itself a
	// childList-adjacent re-render the observer already wakes up for.
	function mountPlaceholder() {
		var input = document.querySelector('.assistant-wrapper .rich-contenteditable__input')
		if (!input) {
			return
		}
		if (input.getAttribute('aria-placeholder') !== PLACEHOLDER_TEXT) {
			input.setAttribute('aria-placeholder', PLACEHOLDER_TEXT)
		}
		if (input.getAttribute('aria-label') !== PLACEHOLDER_TEXT) {
			input.setAttribute('aria-label', PLACEHOLDER_TEXT)
		}
	}

	function mountSend() {
		var box = document.querySelector('.assistant-wrapper .input-area__button-box')
		if (!box || box.querySelector(':scope > .bs-assistant-send')) {
			return
		}

		var btn = document.createElement('button')
		btn.type = 'button'
		btn.className = 'button-vue bs-assistant-send'
		btn.textContent = 'Send'
		btn.addEventListener('click', function () {
			var input = document.querySelector('.assistant-wrapper .rich-contenteditable__input')
			if (!input) {
				return
			}
			input.focus()
			input.dispatchEvent(new KeyboardEvent('keydown', {
				key: 'Enter',
				code: 'Enter',
				keyCode: 13,
				which: 13,
				bubbles: true,
				cancelable: true,
			}))
		})
		// Appended, not inserted first: screen 2h's Send sits at the trailing
		// edge, past the mic -- which stays put rather than being removed, just
		// shifted a slot to the left of Send instead of the rightmost control.
		box.appendChild(btn)
	}

	function mountAll() {
		if (!document.querySelector('.assistant-wrapper')) {
			return
		}
		mountSidebar()
		mountHeader()
		mountMessages()
		mountPlaceholder()
		mountSend()
		mountNewChatLabel()
		mountHistoryIcons()
	}

	mountAll()
	new MutationObserver(mountAll).observe(document.body, { childList: true, subtree: true })
})()
