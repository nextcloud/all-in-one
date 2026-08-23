"use strict";

// Auto-dismisses temporary notifications (see NotificationService / notifications.twig)
// a few seconds after the page loads. Permanent notifications are left untouched.
(function () {
  const TIMEOUT_MS = 5000;

  function dismissTemporaryNotifications() {
    document.querySelectorAll(".notification--temporary").forEach(function (el) {
      setTimeout(function () {
        el.remove();
      }, TIMEOUT_MS);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", dismissTemporaryNotifications);
  } else {
    dismissTemporaryNotifications();
  }
})();
