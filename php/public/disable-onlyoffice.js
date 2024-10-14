/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

document.addEventListener("DOMContentLoaded", function(event) {
    // OnlyOffice
    let onlyoffice = document.getElementById("onlyoffice");
    if (onlyoffice) {
        onlyoffice.disabled = true;
    }
});