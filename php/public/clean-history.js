// This script is loaded after a successful token-based login.
// It replaces the browser's current history entry (stripping the token from the
// URL) before navigating to the main AIO page, so the token is never left in
// the browser history and cannot be accidentally exposed via the back-button.
history.replaceState(null, '', location.pathname);
window.location.replace('../../');
