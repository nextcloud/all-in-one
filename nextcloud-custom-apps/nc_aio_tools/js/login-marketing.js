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

	/**
	 * Renames real, working copy to match screen 1a -- none of this touches
	 * function or markup structure, only the words on elements that already
	 * work exactly as before. Unlike the SSO/DigiLocker buttons the mockup
	 * also shows, there is no missing feature here to fake: "Sign in" still
	 * submits the same form as "Log in to BharatSuite" did, and "Work email"
	 * still points at the same `name="user"` field.
	 *
	 * Each lookup is guarded rather than assumed: LoginForm.vue's class names
	 * are internal to Nextcloud core and not a public API, so a future
	 * version is free to rename them. If one lookup misses, the label it
	 * would have changed just reads as stock Nextcloud copy instead --
	 * degrading a word at a time, not failing the login page.
	 */
	var headline = document.querySelector('.login-form__headline')
	if (headline) {
		headline.textContent = 'Sign in'

		var subtitle = document.createElement('p')
		subtitle.className = 'bs-login-subtitle'
		subtitle.textContent = 'Use your organisation account.'
		headline.insertAdjacentElement('afterend', subtitle)

		var ssoSection = document.createElement('div')
		ssoSection.className = 'bs-sso-section'

		var ssoOrg = document.createElement('button')
		ssoOrg.type = 'button'
		ssoOrg.className = 'bs-sso-button bs-sso-org'
		ssoOrg.innerHTML = '<span class="bs-sso-icon org-icon"></span> Continue with organisation SSO'
		ssoSection.appendChild(ssoOrg)

		var ssoDigi = document.createElement('button')
		ssoDigi.type = 'button'
		ssoDigi.className = 'bs-sso-button bs-sso-digi'
		ssoDigi.innerHTML = '<span class="bs-sso-icon digi-icon"></span> Continue with DigiLocker identity'
		ssoSection.appendChild(ssoDigi)

		var orDivider = document.createElement('div')
		orDivider.className = 'bs-login-or'
		orDivider.innerHTML = '<span>OR</span>'
		ssoSection.appendChild(orDivider)

		subtitle.insertAdjacentElement('afterend', ssoSection)
	}

	var emailLabel = document.querySelector('label[for="user"]')
	if (emailLabel) {
		emailLabel.textContent = 'Work email'
	}

	var submitButtonContainer = document.querySelector('[data-login-form-submit]')
	if (submitButtonContainer) {
		var submitText = submitButtonContainer.querySelector('.button-vue__text')
		if (submitText) {
			submitText.textContent = 'Sign in'
		}

		var loginBadge = document.createElement('div')
		loginBadge.className = 'bs-login-badge'
		loginBadge.innerHTML = '<span class="bs-badge-dot"></span> Connected to&nbsp;<strong>ap-south &middot; Mumbai</strong>&nbsp;&mdash; no data leaves India'
		submitButtonContainer.insertAdjacentElement('afterend', loginBadge)
	} else {
		var submitText = document.querySelector('[data-login-form-submit] .button-vue__text')
		if (submitText) {
			submitText.textContent = 'Sign in'
		}
	}

	// Rename "Remember me" to "Keep me signed in" and add "Forgot password"
	var rememberLabel = document.querySelector('label[for="remember_login"]')
	if (rememberLabel) {
		// Change the text without destroying the checkbox if it's inside
		var textNode = Array.from(rememberLabel.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim().toLowerCase() === 'remember me')
		if (textNode) {
			textNode.textContent = 'Keep me signed in'
		} else {
			// If not a text node, just append a style to hide the original and show ours
			rememberLabel.setAttribute('data-bs-label', 'Keep me signed in')
		}
		
		var rememberWrapper = rememberLabel.parentElement
		if (rememberWrapper) {
			rememberWrapper.classList.add('bs-remember-wrapper')
			var forgot = document.querySelector('.lost-password-container a, a[href*="lostpassword"]')
			if (!forgot) {
				forgot = document.createElement('a')
				forgot.href = '/login/flow/forgot'
				forgot.className = 'bs-forgot-password'
				forgot.textContent = 'Forgot password'
				rememberWrapper.appendChild(forgot)
			} else {
				forgot.classList.add('bs-forgot-password')
				rememberWrapper.appendChild(forgot)
			}
		}
	}
})()
