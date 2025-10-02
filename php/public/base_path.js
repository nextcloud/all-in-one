document.addEventListener("DOMContentLoaded", function(event) {
    document.getElementById("base_path") && (document.getElementById("base_path").value = window.location.pathname.slice(0, -11));
});