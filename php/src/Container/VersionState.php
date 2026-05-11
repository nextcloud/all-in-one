<?php
// SPDX-FileCopyrightText: 2024 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace AIO\Container;

enum VersionState: string {
    case Different = 'different';
    case Equal = 'equal';
}
