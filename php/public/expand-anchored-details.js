"use strict";

// Expands the <details> section that directly follows the <h2> which is the
// current URL anchor target (mirroring the h2:target headline highlight). So
// following an in-page link like the "enabling it below" nag both highlights the
// headline and opens its section. Runs on load and whenever the hash changes.
(function () {
  function expandAnchoredDetails() {
    const headline = document.querySelector("h2:target");
    if (!headline) {
      return;
    }
    const details = headline.nextElementSibling;
    if (details && details.tagName === "DETAILS") {
      details.open = true;
    }
  }

  window.addEventListener("hashchange", expandAnchoredDetails);
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", expandAnchoredDetails);
  } else {
    expandAnchoredDetails();
  }
})();
