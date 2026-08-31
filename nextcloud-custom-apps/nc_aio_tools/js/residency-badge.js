/**
 * Supplies the residency badge's label to CSS.
 *
 * The badge itself is drawn entirely in bharatsuite.css, off .header-end::before.
 * All this does is set the one custom property that rule reads.
 *
 * WHY THIS IS JAVASCRIPT and not the server-side <style> every other dynamic value
 * in this theme goes through: Util::addHeader() escapes its text for HTML, so a CSS
 * string literal emitted that way arrives as
 *
 *     :root:root{--bs-residency-label:&quot;IN · Mumbai&quot;}
 *
 * and the semicolon inside the entity ends the declaration. The variable resolved to
 * the token `&quot`, which is not a <string>, so `content` fell back to `none` and no
 * badge rendered. There is no way to emit a quoted CSS string through that API, and
 * CSS has no unquoted form for `content`. Going through
 * CSSStyleDeclaration.setProperty() avoids HTML escaping entirely, because the value
 * never passes through markup.
 *
 * The label arrives in a <meta> instead, where HTML escaping is exactly right: an
 * attribute value round-trips any text unchanged.
 */
(function () {
	'use strict'

	var meta = document.querySelector('meta[name="bharatsuite-residency"]')
	var label = meta && meta.getAttribute('content')
	if (!label) {
		return
	}

	// JSON string syntax is a subset of CSS string syntax: double quoted, backslash
	// escapes, and control characters escaped rather than passed through. So this is
	// both the serialiser and the escaper.
	document.documentElement.style.setProperty(
		'--bs-residency-label',
		JSON.stringify(label)
	)
})()
