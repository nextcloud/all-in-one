"use strict";

// Opens the deSEC registration flow (the /desec view) inside a modal iframe so the user can
// run the multi-step register -> verify -> domain process without leaving the containers
// page. The iframe re-navigates itself between steps; once a domain is registered, the
// /desec view reloads the parent window via desec-done.js, so this script only has to deal
// with opening and closing the modal.
(function () {
    const modal = document.getElementById('desec-modal');
    const frame = document.getElementById('desec-frame');
    if (!modal || !frame) {
        return;
    }

    function openModal() {
        // Load (or reload) the flow each time the modal is opened so it always reflects the
        // current registration state on the server.
        frame.src = 'desec';
        modal.hidden = false;
        document.body.classList.add('modal-open');
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
        // Drop the iframe content so credentials are not left rendered in the background.
        frame.src = 'about:blank';
    }

    document.querySelectorAll('[data-desec-open]').forEach((el) => {
        el.addEventListener('click', openModal);
    });
    document.querySelectorAll('[data-desec-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    // Close when clicking the dimmed backdrop (but not when clicking inside the dialog).
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    // Close on Escape for keyboard users.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
})();
