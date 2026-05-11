<?php
// SPDX-FileCopyrightText: 2021 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

$overwriteHost = getenv('OVERWRITEHOST');
if ($overwriteHost) {
  $CONFIG['overwritehost'] = $overwriteHost;
}

$overwriteProtocol = getenv('OVERWRITEPROTOCOL');
if ($overwriteProtocol) {
  $CONFIG['overwriteprotocol'] = $overwriteProtocol;
}

$overwriteWebRoot = getenv('OVERWRITEWEBROOT');
if ($overwriteWebRoot) {
  $CONFIG['overwritewebroot'] = $overwriteWebRoot;
}

$overwriteCondAddr = getenv('OVERWRITECONDADDR');
if ($overwriteCondAddr) {
  $CONFIG['overwritecondaddr'] = $overwriteCondAddr;
}
