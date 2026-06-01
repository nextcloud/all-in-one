"use strict";

// Apply the saved theme immediately to avoid a flash of the wrong theme.
try { document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') ?? ''); } catch (e) {}

// React when the user toggles the theme on the parent page while this page is
// open in an iframe.  localStorage.setItem() fires a 'storage' event on every
// other window / frame that shares the same origin, so we can keep in sync
// without the parent having to know about us.
window.addEventListener('storage', (e) => {
    if (e.key === 'theme') {
        document.documentElement.setAttribute('data-theme', e.newValue ?? '');
    }
});
