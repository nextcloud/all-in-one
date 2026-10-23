<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AIO\Container;

class ContainerVolume {
    public function __construct(
        public string $name,
        public string $mountPoint,
        public bool $isWritable
    ) {
    }
}
