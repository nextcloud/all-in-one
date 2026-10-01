document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('input[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!confirm(element.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });


    document.querySelectorAll('input[data-input-show-password]').forEach((passwordField) => {
        const wrapper = document.createElement('span');
        wrapper.className = 'password-field';
        passwordField.replaceWith(wrapper);
        wrapper.appendChild(passwordField);

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'password-toggle';
        toggle.setAttribute('aria-label', 'Show passphrase');
        toggle.setAttribute('aria-pressed', 'false');
        if (passwordField.id) {
            toggle.setAttribute('aria-controls', passwordField.id);
        }
        toggle.addEventListener('click', () => {
            const reveal = passwordField.type === 'password';
            passwordField.type = reveal ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(reveal));
            toggle.setAttribute('aria-label', reveal ? 'Hide passphrase' : 'Show passphrase');
        });
        wrapper.appendChild(toggle);
    });

    document.querySelectorAll('[data-stop-event-propagation="true"]').forEach((element) => {
        element.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    });
});
