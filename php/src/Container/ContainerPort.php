<?php
// SPDX-FileCopyrightText: 2022 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace AIO\Container;

class ContainerPort {
    public function __construct(
        public string $port,
        public string $ipBinding,
        public string $protocol
    ) {
    }
}
