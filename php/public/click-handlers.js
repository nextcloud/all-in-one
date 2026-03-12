document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('input[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!confirm(element.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-stop-event-propagation="true"]').forEach((element) => {
        element.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    });
});
