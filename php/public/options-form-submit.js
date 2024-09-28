function makeOptionsFormSubmitVisible() {
    let optionsFormSubmit = document.getElementById("options-form-submit");
    optionsFormSubmit.style.display = 'block';
}

function handleTalkVisibility() {
    let talk = document.getElementById("talk");
    let talkRecording = document.getElementById("talk-recording")
    if (talk.checked) {
        talkRecording.disabled = false
    } else {
        talkRecording.checked = false
        talkRecording.disabled = true
    }
}

function handleDockerSocketProxyWarning() {
    let dockerSocketProxy = document.getElementById("docker-socket-proxy");
    if (dockerSocketProxy.checked) {
        alert('⚠️ Warning! Enabling this container comes with possible Security problems since you are exposing the docker socket and all its privileges to the Nextcloud container. Enable this only if you are sure what you are doing!')
    }
}

document.addEventListener("DOMContentLoaded", function(event) {
    // handle submit button for options form
    let optionsFormSubmit = document.getElementById("options-form-submit");
    optionsFormSubmit.style.display = 'none';

    // Clamav
    let clamav = document.getElementById("clamav");
    clamav.addEventListener('change', makeOptionsFormSubmitVisible);

    // OnlyOffice
    let onlyoffice = document.getElementById("onlyoffice");
    if (onlyoffice) {
        onlyoffice.addEventListener('change', makeOptionsFormSubmitVisible);
    }

    // Collabora
    let collabora = document.getElementById("collabora");
    collabora.addEventListener('change', makeOptionsFormSubmitVisible);

    // Talk
    let talk = document.getElementById("talk");
    talk.addEventListener('change', makeOptionsFormSubmitVisible);
    talk.addEventListener('change', handleTalkVisibility);

    // Talk-recording
    let talkRecording = document.getElementById("talk-recording");
    talkRecording.addEventListener('change', makeOptionsFormSubmitVisible);
    if (!talk.checked) {
        talkRecording.disabled = true
    }

    // Imaginary
    let imaginary = document.getElementById("imaginary");
    imaginary.addEventListener('change', makeOptionsFormSubmitVisible);

    // Fulltextsearch
    let fulltextsearch = document.getElementById("fulltextsearch");
    fulltextsearch.addEventListener('change', makeOptionsFormSubmitVisible);

    // Docker socket proxy
    let dockerSocketProxy = document.getElementById("docker-socket-proxy");
    if (dockerSocketProxy) {
        dockerSocketProxy.addEventListener('change', makeOptionsFormSubmitVisible);
        // dockerSocketProxy.addEventListener('change', handleDockerSocketProxyWarning);
    }

    // Whiteboard
    let whiteboard = document.getElementById("whiteboard");
    whiteboard.addEventListener('change', makeOptionsFormSubmitVisible);
});
