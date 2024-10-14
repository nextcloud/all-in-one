/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

document.addEventListener("DOMContentLoaded", function(event) {
    // timezone
    let timezone = document.getElementById("timezone");
    if (timezone) {
        timezone.value = Intl.DateTimeFormat().resolvedOptions().timeZone
    }
});
