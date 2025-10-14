document.addEventListener("DOMContentLoaded", function() {
    basePath = document.getElementById("base_path")
    if (basePath) {
        // Remove '/containers' from the end of the path, to get the base path only
        basePath.value = window.location.pathname.slice(0, -11);
    }
});