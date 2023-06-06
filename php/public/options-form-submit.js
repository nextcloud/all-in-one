function makeOptionsFormSubmitVisible() {
    var optionsFormSubmit = document.getElementById("options-form-submit");
    optionsFormSubmit.style.display = 'block';
}

function handleTalkVisibility(talk) {
    let talkRecording = document.getElementById("talk-recording")
    if (talk.checked) {
        talkRecording.disabled = false
    } else {
        talkRecording.checked = false
        talkRecording.disabled = true
    }
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
    if (onlyoffice) {
        onlyoffice.addEventListener('change', makeOptionsFormSubmitVisible);
    }

    // Collabora
    var collabora = document.getElementById("collabora");
    collabora.addEventListener('change', makeOptionsFormSubmitVisible);

    // Talk
    var talk = document.getElementById("talk");
    talk.addEventListener('change', makeOptionsFormSubmitVisible);
    talk.addEventListener('change', handleTalkVisibility);

    // Talk-recording
    var talkRecording = document.getElementById("talk-recording");
    talkRecording.addEventListener('change', makeOptionsFormSubmitVisible);
    if (!talk.checked) {
        talkRecording.disabled = true
    }

    // Imaginary
    var imaginary = document.getElementById("imaginary");
    imaginary.addEventListener('change', makeOptionsFormSubmitVisible);

    // Fulltextsearch
    var fulltextsearch = document.getElementById("fulltextsearch");
    fulltextsearch.addEventListener('change', makeOptionsFormSubmitVisible);
});
