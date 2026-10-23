<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AIO\Container;

class ContainerPorts {
    /** @var ContainerPort[] */
    private array $ports = [];

    public function AddPort(ContainerPort $port) : void {
        $this->ports[] = $port;
    }

    /**
     * @return ContainerPort[]
     */
    public function GetPorts() : array {
        return $this->ports;
    }
}