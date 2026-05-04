// This script is loaded after a successful token-based login.
// It replaces the browser's current history entry (stripping the token from the
// URL) before navigating to the main AIO page, so the token is never left in
// the browser history and cannot be accidentally exposed via the back-button.
//
// The target URL is passed via the script tag's data-target attribute.
// document.currentScript is only available during synchronous script execution
// (not with defer/async), so this script is loaded without those attributes.
//
// We replace with location.pathname only (no query string, no hash), which
// intentionally strips the ?token=… parameter and any hash fragment from the
// recorded history entry.
const rawTarget = document.currentScript.dataset.target;

// Validate that the redirect target is a safe relative path (starts with '.' or '/').
// This guards against hypothetical injection (e.g. 'javascript:…') even though the
// value is server-set.
const safePattern = /^[./]/;
const unsafePattern = /^\/\//;
const target = (typeof rawTarget === 'string' && safePattern.test(rawTarget) && !unsafePattern.test(rawTarget))
    ? rawTarget
    : '/';

history.replaceState(null, '', location.pathname);
window.location.replace(target);
