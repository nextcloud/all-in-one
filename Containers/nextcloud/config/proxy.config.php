<?php
// SPDX-FileCopyrightText: 2024 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

if (getenv('HTTP_PROXY')) {
    $CONFIG['proxy'] = getenv('HTTP_PROXY');
}
if (getenv('HTTPS_PROXY')) {
    $CONFIG['proxy'] = getenv('HTTPS_PROXY');
}
if (getenv('PROXY_USER_PASSWORD')) {
    $CONFIG['proxyuserpwd'] = getenv('PROXY_USER_PASSWORD');
}
if (getenv('NO_PROXY')) {
    $CONFIG['proxyexclude'] = explode(',', getenv('NO_PROXY'));
}
