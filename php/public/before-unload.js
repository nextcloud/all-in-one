// SPDX-FileCopyrightText: 2022 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

window.addEventListener("beforeunload", function() {
    document.getElementById('overlay').classList.add('loading')
});