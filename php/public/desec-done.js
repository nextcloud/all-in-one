"use strict";

// Rendered into the deSEC modal view (desec.twig) once a deSEC domain has been fully
// registered. The view lives inside an iframe opened by the containers page; the whole
// process is now done, so reload the parent window to show the updated containers page.
// When opened directly (not embedded), window.top === window, so this just reloads here.
(function () {
    // Give the success message a brief moment so the user sees that it worked.
    setTimeout(function () {
        window.top.location.reload();
    }, 1500);
})();
