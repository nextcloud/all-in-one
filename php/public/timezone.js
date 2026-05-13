document.addEventListener("DOMContentLoaded", function(event) {
    // timezone
    let timezone = document.getElementById("timezone");
    if (timezone) {
        timezone.placeholder = Intl.DateTimeFormat().resolvedOptions().timeZone
    }
});
