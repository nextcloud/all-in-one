<?php
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace AIO\Container;

enum VersionState: string {
    case Different = 'different';
    case Equal = 'equal';
}
