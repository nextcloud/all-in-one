document.addEventListener("DOMContentLoaded", function(event) {
    // timezone
    let timezone = document.getElementById("timezone");
    if (timezone) {
        timezone.value = Intl.DateTimeFormat().resolvedOptions().timeZone
    }
});
