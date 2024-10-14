<?php
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace AIO\Container;

class ContainerPort {
    public function __construct(
        public string $port,
        public string $ipBinding,
        public string $protocol
    ) {
    }
}
