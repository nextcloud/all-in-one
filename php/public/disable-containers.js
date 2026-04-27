document.addEventListener("DOMContentLoaded", function(event) {
    // Clamav
    let clamav = document.getElementById("clamav");
    clamav.disabled = true;

    // Docker socket proxy
    let dockerSocketProxy = document.getElementById("docker-socket-proxy");
    if (dockerSocketProxy) {
        dockerSocketProxy.disabled = true;
    }

    // HaRP
    let harp = document.getElementById("harp");
    if (harp) {
        harp.disabled = true;
    }

    // Talk
    let talk = document.getElementById("talk");
    talk.disabled = true;

    // Collabora
    const collabora = document.getElementById("office-collabora");
    collabora.disabled = true;

    // OnlyOffice
    const onlyoffice = document.getElementById("office-onlyoffice");
    onlyoffice.disabled = true;

    // Imaginary
    let imaginary = document.getElementById("imaginary");
    imaginary.disabled = true;

    // Fulltextsearch
    let fulltextsearch = document.getElementById("fulltextsearch");
    fulltextsearch.disabled = true;

    // Talk-recording
    document.getElementById("talk-recording").disabled = true;

    // Whiteboard
    let whiteboard = document.getElementById("whiteboard");
    whiteboard.disabled = true;

    // Windmill
    let windmill = document.getElementById("windmill");
    if (windmill) {
        windmill.disabled = true;
    }
});
