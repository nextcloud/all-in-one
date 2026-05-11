<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: 2021 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

namespace AIO\Container;

class ContainerVolume {
    public function __construct(
        public string $name,
        public string $mountPoint,
        public bool $isWritable
    ) {
    }
}
