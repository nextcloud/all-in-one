document.addEventListener("DOMContentLoaded", function(event) {
    // OnlyOffice
    try {
        var onlyoffice = document.getElementById("onlyoffice");
        onlyoffice.disabled = true;
    } catch (error) {
        // console.error(error);
    }
});