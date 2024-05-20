document.addEventListener("DOMContentLoaded", function(event) {
    // domain
    let domain = document.getElementById("domain_input");
    if (domain) {
        domain.value = window.location.host
    }
});
