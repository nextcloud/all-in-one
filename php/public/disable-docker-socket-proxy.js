document.addEventListener("DOMContentLoaded", function(event) {
    // Docker socket proxy
    let dockerSocketProxy = document.getElementById("docker-socket-proxy");
    if (dockerSocketProxy) {
        dockerSocketProxy.disabled = true;
    }
});
