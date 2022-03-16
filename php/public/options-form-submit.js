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
});
