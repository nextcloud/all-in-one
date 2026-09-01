/**
 * The two-panel marketing split on the sign-in screen (1a).
 *
 * Nextcloud's login page has no slot for this: the guest template renders one
 * centred column (logo, then the login card, then a footer) and the whole
 * form is a Vue app mounted into an empty `<div id="login">`, so there is no
 * server-side template block to extend. This builds the left panel as plain
 * markup and drops it in ahead of `.wrapper`, then flags the body so
 * bharatsuite.css can switch the page from one centred column to a split.
 *
 * Progressive enhancement, not a requirement: if this script fails to run,
 * `body-login` never gets `.bs-login-split` and the page renders exactly as
 * it did before this file existed.
 */
(function () {
	'use strict'

	var body = document.body
	if (!body || body.id !== 'body-login') {
		return
	}

	var wrapper = document.querySelector('.wrapper')
	if (!wrapper) {
		return
	}

	var panel = document.createElement('div')
	panel.className = 'bs-login-marketing'
	panel.setAttribute('aria-hidden', 'true')

	var top = document.createElement('div')
	top.className = 'bs-login-marketing__top'

	// Reuses the logo Nextcloud already rendered into #header .logo -- moved,
	// not cloned, so the wordmark appears once, in the panel, rather than
	// twice. Its background-image comes from the --image-logo custom property
	// BrandingAssetsListener sets, so no new asset reference is needed here.
	var logo = document.querySelector('#header .logo')
	if (logo) {
		logo.classList.add('bs-login-marketing__logo')
		top.appendChild(logo)
	}

	var eyebrow = document.createElement('p')
	eyebrow.className = 'bs-login-marketing__eyebrow'
	eyebrow.textContent = 'Sovereign workplace'
	top.appendChild(eyebrow)

	var heading = document.createElement('p')
	heading.className = 'bs-login-marketing__heading'
	heading.textContent = 'Your files, mail and records stay in India.'
	top.appendChild(heading)

	var lede = document.createElement('p')
	lede.className = 'bs-login-marketing__lede'
	lede.textContent = 'Storage, mail, documents and images for SMBs, regulated '
		+ 'firms and institutions — open platform, your keys, Indian regions.'
	top.appendChild(lede)

	panel.appendChild(top)

	var facts = document.createElement('dl')
	facts.className = 'bs-login-marketing__facts'
	;[
		['Residency', 'Mumbai + Hyderabad, replicated'],
		['Compliance', 'DPDP Act · ISO 27001 · CERT-In'],
		['Encryption', 'Keys held by your organisation'],
	].forEach(function (fact) {
		var row = document.createElement('div')
		row.className = 'bs-login-marketing__fact'

		var dt = document.createElement('dt')
		dt.textContent = fact[0]
		var dd = document.createElement('dd')
		dd.textContent = fact[1]

		row.appendChild(dt)
		row.appendChild(dd)
		facts.appendChild(row)
	})
	panel.appendChild(facts)

	wrapper.parentNode.insertBefore(panel, wrapper)
	body.classList.add('bs-login-split')
})()
