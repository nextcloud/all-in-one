// This script is loaded after a successful token-based login.
// It replaces the browser's current history entry (stripping the token from the
// URL) before navigating to the main AIO page, so the token is never left in
// the browser history and cannot be accidentally exposed via the back-button.
//
// The target URL is passed via the script tag's data-target attribute.
// document.currentScript is only available during synchronous script execution
// (not with defer/async), so this script is loaded without those attributes.
const target = document.currentScript.dataset.target;
history.replaceState(null, '', location.pathname);
window.location.replace(target);
