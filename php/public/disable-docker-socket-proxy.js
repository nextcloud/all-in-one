/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

document.addEventListener("DOMContentLoaded", function(event) {
    // Docker socket proxy
    let dockerSocketProxy = document.getElementById("docker-socket-proxy");
    if (dockerSocketProxy) {
        dockerSocketProxy.disabled = true;
    }
});
