<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
