function makeOptionsFormSubmitVisible() {
    var optionsFormSubmit = document.getElementById("options-form-submit");
    optionsFormSubmit.style.display = 'block';
}

document.addEventListener("DOMContentLoaded", function(event) {
    // handle submit button for options form
    var optionsFormSubmit = document.getElementById("options-form-submit");
    optionsFormSubmit.style.display = 'none';

    // Clamav
    var clamav = document.getElementById("clamav");
    clamav.addEventListener('change', makeOptionsFormSubmitVisible);

    // OnlyOffice
    var onlyoffice = document.getElementById("onlyoffice");
    onlyoffice.addEventListener('change', makeOptionsFormSubmitVisible);

    // Collabora
    var collabora = document.getElementById("collabora");
    collabora.addEventListener('change', makeOptionsFormSubmitVisible);

    // Talk
    var talk = document.getElementById("talk");
    talk.addEventListener('change', makeOptionsFormSubmitVisible);

    // Imaginary
    var imaginary = document.getElementById("imaginary");
    imaginary.addEventListener('change', makeOptionsFormSubmitVisible);
});
