// SPDX-FileCopyrightText: 2024 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

document.addEventListener("DOMContentLoaded", function(event) {
    // timezone
    let timezone = document.getElementById("timezone");
    if (timezone) {
        timezone.placeholder = Intl.DateTimeFormat().resolvedOptions().timeZone
    }
});
