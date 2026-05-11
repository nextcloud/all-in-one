<?php
// SPDX-FileCopyrightText: 2024 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace AIO\Container;

enum ContainerState: string {
    case ImageDoesNotExist = 'image_does_not_exist';
    case NotRestarting = 'not_restarting';
    case Restarting = 'restarting';
    case Running = 'running';
    case Starting = 'starting';
    case Stopped = 'stopped';
}
