<?php
declare(strict_types=1);


// SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace AIO\Container;

class ContainerVolume {
    public function __construct(
        public string $name,
        public string $mountPoint,
        public bool $isWritable
    ) {
    }
}
