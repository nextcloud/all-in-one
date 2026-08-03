document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('input[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!confirm(element.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });


    document.querySelectorAll('input[data-input-show-password]').forEach((element) => {
        element.addEventListener('input', (event) => {
            let passwordField = event.target;
            if (passwordField.type === "password" && passwordField.value !== "") {
                passwordField.type = "text";
            } else if (passwordField.type === "text" && passwordField.value === "") {
                passwordField.type = "password";
            }
        });
    });

    document.querySelectorAll('[data-stop-event-propagation="true"]').forEach((element) => {
        element.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    });
});
