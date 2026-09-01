/**
 * Opens the Assistant modal (the header sparkle icon, screen 2h) already maximised.
 *
 * The modal is a PrimeVue Dialog (`.p-dialog.assistant-modal`, teleported into
 * `#assistantTextProcessingModal` at the end of <body>) that ships its own
 * maximise toggle -- `.p-dialog-maximize-button` adds a `p-dialog-maximized`
 * class that a PrimeVue stylesheet expands to fill the viewport. That class is
 * the entire mechanism; nothing about the maximised layout is recomputed in JS.
 * So rather than reverse-engineer and duplicate whatever CSS
 * `p-dialog-maximized` expands to (and having it drift the next time the
 * assistant app's PrimeVue version changes), this just clicks the app's own
 * button once per open -- the same effect a user gets by clicking it
 * themselves, produced through the exact code path that already knows how to
 * produce it.
 *
 * WHY A MUTATIONOBSERVER ON body, not a click handler on the sparkle icon:
 * the icon only opens the dialog; the dialog's own contents (and the maximise
 * button inside them) do not exist in the DOM until PrimeVue teleports them in
 * afterwards, so there is nothing to click yet at click-time. Observing body
 * for the dialog's arrival catches it the moment it exists, however it was
 * opened (the header icon, or any other trigger this app or another might add
 * later).
 *
 * WHY MARK THE NODE RATHER THAN JUST CHECKING FOR p-dialog-maximized: clicking
 * the button toggles it, so if the mutation callback ever ran twice against
 * the same still-unmaximised dialog (observer callbacks can batch multiple
 * mutation records) a second click would immediately un-maximise it again.
 * The dataset flag makes the click idempotent per dialog instance. It is only
 * ever "open already maximised", never "force it to stay maximised" -- once
 * marked, a user who manually restores the dialog down is left alone.
 */
(function () {
	'use strict'

	function tryMaximize() {
		var dialog = document.querySelector('#assistantTextProcessingModal .p-dialog.assistant-modal')
		if (!dialog || dialog.dataset.bsAutoMaximized) {
			return
		}
		var button = dialog.querySelector('.p-dialog-maximize-button')
		if (!button) {
			return
		}
		dialog.dataset.bsAutoMaximized = '1'
		button.click()
	}

	tryMaximize()

	var observer = new MutationObserver(tryMaximize)
	observer.observe(document.body, { childList: true, subtree: true })
})()
