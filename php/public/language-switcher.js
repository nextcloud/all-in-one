(function () {
    'use strict';

    var STORAGE_KEY = 'aio_language';
    var API_ENDPOINT = 'api/language';

    /**
     * Read the CSRF token fields that CsrfExtension injects into every page as
     * hidden inputs inside the logout form.  We reuse them for the JSON POST so
     * that Slim's CSRF guard accepts our request.
     */
    function getCsrfFields() {
        var nameInput  = document.querySelector('input[name$="__token_name"]')  // fallback selector
                      || document.querySelector('input[name^="csrf_name"]');
        var valueInput = document.querySelector('input[name$="__token_value"]')
                      || document.querySelector('input[name^="csrf_value"]');

        // The Slim CSRF guard stores two hidden fields; their *name* attributes
        // are themselves dynamic (csrf_name / csrf_value carry the key names,
        // and csrf.name / csrf.value carry the actual token strings).
        // The simplest reliable approach: grab all hidden inputs from the logout
        // form and forward them all.
        var logoutForm = document.querySelector('form[action*="api/auth/logout"]');
        if (!logoutForm) {
            return {};
        }

        var fields = {};
        var hiddenInputs = logoutForm.querySelectorAll('input[type="hidden"]');
        hiddenInputs.forEach(function (input) {
            fields[input.name] = input.value;
        });
        return fields;
    }

    /**
     * POST the chosen language to the server, then reload on success.
     * Returns a Promise that resolves to true on success, false on failure.
     */
    function postLanguage(lang) {
        var csrfFields = getCsrfFields();
        var body = Object.assign({ language: lang }, csrfFields);

        return fetch(API_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(body).toString(),
        }).then(function (response) {
            return response.ok || response.status === 204;
        }).catch(function () {
            return false;
        });
    }

    /**
     * Persist the language choice to localStorage and reload the page so the
     * server can render in the new language.
     */
    function applyLanguage(lang, reload) {
        localStorage.setItem(STORAGE_KEY, lang);
        postLanguage(lang).then(function (ok) {
            if (ok && reload) {
                window.location.reload();
            }
        });
    }

    /**
     * Wire up the <select> drop-down once the DOM is ready.
     */
    function initSwitcher() {
        var select = document.getElementById('language-switcher');
        if (!select) {
            return;
        }

        select.addEventListener('change', function () {
            var chosen = select.value;
            if (chosen) {
                applyLanguage(chosen, true);
            }
        });

        // On page load: if localStorage holds a preference that differs from
        // the current server-side language, silently sync once and reload.
        var saved = localStorage.getItem(STORAGE_KEY);
        var current = select.dataset.current || select.value;

        if (saved && saved !== current) {
            // Update the select to match the stored preference before posting,
            // so the UI doesn't flicker if the reload is slow.
            select.value = saved;
            applyLanguage(saved, true);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSwitcher);
    } else {
        initSwitcher();
    }
}());